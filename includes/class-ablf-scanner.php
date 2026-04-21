<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABLF_Scanner {

	const OK_CODES     = array( 200, 201, 202, 203, 204, 205, 206 );
	const BROKEN_CODES = array( 404, 410, 500, 502, 503, 504 );

	public static function register_hooks() {
		add_action( 'wp_ajax_ablf_start_scan', array( __CLASS__, 'ajax_start_scan' ) );
		add_action( 'wp_ajax_ablf_scan_progress', array( __CLASS__, 'ajax_scan_progress' ) );
		add_action( 'save_post', array( __CLASS__, 'queue_post_for_rescan' ), 20, 2 );
	}

	public static function scan_all_posts() {
		$post_types = (array) apply_filters( 'ablf_scan_post_types', (array) get_option( 'ablf_scan_post_types', array( 'post', 'page' ) ) );
		if ( empty( $post_types ) ) {
			return 0;
		}

		// Clear old queue entries and purge stale exclusion matches from the broken links table.
		ABLF_DB_Handler::clear_finished_queue();
		ABLF_DB_Handler::purge_excluded_broken_links(
			self::parse_list_option( 'ablf_excluded_urls' ),
			self::parse_list_option( 'ablf_excluded_domains' ),
			ABLF_DB_Handler::get_allowlist()
		);

		$posts = get_posts( array(
			'post_type'        => $post_types,
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		) );

		$queued = 0;
		foreach ( $posts as $post_id ) {
			$queued += self::queue_links_for_post( $post_id );
		}

		update_option( 'ablf_last_scan_at', current_time( 'mysql' ) );

		// Kick off background processing — never block the current request.
		wp_schedule_single_event( time(), 'ablf_process_scan_queue' );

		return $queued;
	}

	public static function queue_post_for_rescan( $post_id, $post = null ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$post = $post ? $post : get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}
		$types = (array) get_option( 'ablf_scan_post_types', array( 'post', 'page' ) );
		if ( ! in_array( $post->post_type, $types, true ) ) {
			return;
		}
		self::queue_links_for_post( $post_id );
	}

	private static function queue_links_for_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 0;
		}
		$links = self::extract_links_from_post( $post );
		if ( empty( $links ) ) {
			return 0;
		}

		$rows = array();
		foreach ( $links as $link ) {
			$rows[] = array(
				'source_post_id'      => (int) $post_id,
				'url'                 => $link['url'],
				'anchor_text'         => $link['anchor_text'],
				'surrounding_context' => $link['surrounding_context'],
			);
		}
		return ABLF_DB_Handler::queue_urls( $rows );
	}

	public static function extract_links_from_post( $post ) {
		if ( empty( $post->post_content ) ) {
			return array();
		}

		$content = $post->post_content;

		$excluded_urls_array    = self::parse_list_option( 'ablf_excluded_urls' );
		$excluded_domains_array = (array) apply_filters( 'ablf_excluded_domains', self::parse_list_option( 'ablf_excluded_domains' ) );
		$allowlist              = ABLF_DB_Handler::get_allowlist();

		$links = array();
		$seen  = array();

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$wrapped = '<?xml encoding="utf-8" ?><html><body>' . $content . '</body></html>';
		$dom->loadHTML( $wrapped, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//a[@href]' );
		if ( ! $nodes ) {
			return array();
		}

		foreach ( $nodes as $node ) {
			$href = trim( $node->getAttribute( 'href' ) );
			if ( '' === $href ) {
				continue;
			}
			// Keep only absolute http/https URLs — skip everything else (mailto, tel, javascript, anchors, relative paths).
			if ( ! preg_match( '/^https?:\/\//i', $href ) ) {
				continue;
			}
			// Skip excluded URLs, domains, and allowlisted entries.
			if ( self::is_excluded( $href, $excluded_urls_array, $excluded_domains_array, $allowlist ) ) {
				continue;
			}

			$key = md5( $href );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$anchor  = self::truncate( trim( $node->textContent ), 500 );
			$context = self::get_surrounding_context( $node );

			$links[] = array(
				'url'                 => esc_url_raw( $href ),
				'anchor_text'         => $anchor,
				'surrounding_context' => $context,
			);
		}

		return $links;
	}

	private static function get_surrounding_context( DOMNode $node ) {
		$parent = $node->parentNode;
		while ( $parent && ! in_array( strtolower( $parent->nodeName ), array( 'p', 'li', 'div', 'body' ), true ) ) {
			$parent = $parent->parentNode;
		}
		if ( ! $parent ) {
			return '';
		}
		return self::truncate( trim( preg_replace( '/\s+/', ' ', $parent->textContent ) ), 300 );
	}

	private static function truncate( $str, $max ) {
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $str ) > $max ) {
			return mb_substr( $str, 0, $max );
		}
		if ( strlen( $str ) > $max ) {
			return substr( $str, 0, $max );
		}
		return $str;
	}

	private static function parse_list_option( $option ) {
		$raw = (string) get_option( $option, '' );
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$out = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line !== '' ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * @param string   $url
	 * @param string[] $excluded_urls    Substring patterns from settings.
	 * @param string[] $excluded_domains Domain names (or full URLs) from settings.
	 * @param array    $allowlist        Rows from ablf_allowlist table (stdClass with pattern/pattern_type).
	 */
	private static function is_excluded( $url, $excluded_urls, $excluded_domains, $allowlist = array() ) {
		// URL patterns — substring match, case-insensitive.
		foreach ( $excluded_urls as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( $pattern !== '' && false !== stripos( $url, $pattern ) ) {
				return true;
			}
		}

		// Domain exclusions — exact match with www normalisation.
		// If the entry contains "://" it is a full URL, so match as a substring
		// rather than extracting the host (extracting the host would accidentally
		// exclude the entire site when the user stores an internal URL here).
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		foreach ( $excluded_domains as $d ) {
			$d = strtolower( trim( (string) $d ) );
			if ( $d === '' ) {
				continue;
			}
			if ( strpos( $d, '://' ) !== false ) {
				// Full URL stored in domain field — substring match only.
				if ( false !== stripos( $url, $d ) ) {
					return true;
				}
				continue;
			}
			// Bare domain — exact match with www normalisation.
			if ( $host !== '' && ( $host === $d || $host === 'www.' . $d || 'www.' . $host === $d ) ) {
				return true;
			}
		}

		// Allowlist entries.
		foreach ( $allowlist as $item ) {
			$p = strtolower( trim( (string) $item->pattern ) );
			if ( $p === '' ) {
				continue;
			}
			if ( 'domain' === $item->pattern_type ) {
				if ( $host !== '' && ( $host === $p || $host === 'www.' . $p || 'www.' . $host === $p ) ) {
					return true;
				}
			} else {
				if ( false !== stripos( $url, $p ) ) {
					return true;
				}
			}
		}

		return false;
	}

	public static function process_queue_batch() {
		// Recover any items orphaned by a previous crashed run.
		ABLF_DB_Handler::recover_stale_queue_items();

		// Hard cap: 5 URLs per batch — external requests are slow.
		$items = ABLF_DB_Handler::get_queued_urls( 5 );
		if ( empty( $items ) ) {
			return 0;
		}

		// Load exclusion lists once for the whole batch.
		$excl_urls    = self::parse_list_option( 'ablf_excluded_urls' );
		$excl_domains = self::parse_list_option( 'ablf_excluded_domains' );
		$allowlist    = ABLF_DB_Handler::get_allowlist();

		foreach ( $items as $item ) {
			ABLF_DB_Handler::mark_queue_item_processing( (int) $item->id );
			ABLF_DB_Handler::increment_queue_attempts( (int) $item->id );

			// Hard 5-second timeout — never block longer than this.
			$response = wp_remote_get( $item->url, array(
				'timeout'     => 5,
				'redirection' => 2,
				'sslverify'   => false,
				'blocking'    => true,
				'user-agent'  => 'ABLF Scanner/1.0',
			) );

			if ( is_wp_error( $response ) ) {
				$code = 0; // timeout or connection failed
			} else {
				$code = (int) wp_remote_retrieve_response_code( $response );
			}

			// Always mark done — never leave an item in 'processing'.
			ABLF_DB_Handler::mark_queue_item_done( (int) $item->id, 'done' );

			if ( self::is_broken_result( array( 'code' => $code ) ) ) {
				// Re-check exclusions in case settings changed since the URL was queued.
				if ( ! self::is_excluded( $item->url, $excl_urls, $excl_domains, $allowlist ) ) {
					ABLF_DB_Handler::insert_broken_link( array(
						'source_post_id'      => (int) $item->source_post_id,
						'broken_url'          => $item->url,
						'anchor_text'         => $item->anchor_text,
						'surrounding_context' => $item->surrounding_context,
						'http_status'         => $code,
						'status'              => 'broken',
					) );
				}
			}
		}

		// If more items remain, schedule the next batch in 5 seconds.
		$progress = ABLF_DB_Handler::queue_progress();
		if ( (int) $progress['queued'] > 0 ) {
			wp_schedule_single_event( time() + 5, 'ablf_process_scan_queue' );
		}

		return count( $items );
	}

	public static function check_url( $url ) {
		$timeout = (int) get_option( 'ablf_http_timeout', 10 );
		$timeout = max( 3, min( 15, $timeout ) ); // hard cap at 15s — prevents queue hangs

		$args = array(
			'timeout'     => $timeout,
			'redirection' => 3,
			'user-agent'  => 'ABLF Scanner/' . ABLF_VERSION,
			'sslverify'   => false,
			'blocking'    => true,
		);

		$response = wp_remote_head( $url, $args );
		if ( is_wp_error( $response ) ) {
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return array( 'code' => 0, 'error' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 405 === $code || 403 === $code ) {
			$response = wp_remote_get( $url, $args );
			if ( ! is_wp_error( $response ) ) {
				$code = (int) wp_remote_retrieve_response_code( $response );
			}
		}

		return array( 'code' => $code, 'error' => '' );
	}

	private static function is_broken_result( $result ) {
		$code = (int) $result['code'];
		if ( 0 === $code ) {
			return true;
		}
		if ( in_array( $code, self::BROKEN_CODES, true ) ) {
			return true;
		}
		if ( $code >= 400 && $code !== 403 && $code !== 405 && $code !== 429 ) {
			return true;
		}
		return false;
	}

	public static function ajax_start_scan() {
		check_ajax_referer( 'ablf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-broken-link-fixer' ) ), 403 );
		}

		// Extract links and populate the queue only — no HTTP checks here.
		// HTTP checking happens exclusively in process_queue_batch() via cron.
		$total = self::scan_all_posts();

		// Force cron to fire immediately on local dev environments.
		spawn_cron();

		wp_send_json_success( array(
			'message' => __( 'Scan started', 'ai-broken-link-fixer' ),
			'total'   => (int) $total,
		) );
	}

	public static function ajax_scan_progress() {
		check_ajax_referer( 'ablf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-broken-link-fixer' ) ), 403 );
		}

		// Recover stale items so the bar never freezes.
		ABLF_DB_Handler::recover_stale_queue_items();

		$progress = ABLF_DB_Handler::queue_progress();

		// If items are still queued but no cron is pending, reschedule.
		if ( (int) $progress['queued'] > 0 && ! wp_next_scheduled( 'ablf_process_scan_queue' ) ) {
			wp_schedule_single_event( time(), 'ablf_process_scan_queue' );
		}

		// Force cron to fire on every progress poll — keeps local dev moving.
		spawn_cron();

		wp_send_json_success( $progress );
	}
}
