<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABLF_Pathfinder {

	const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
	const MODEL        = 'claude-haiku-4-5-20251001';
	const MAX_TOKENS   = 500;

	public static function register_hooks() {
		add_action( 'wp_ajax_ablf_get_suggestions', array( __CLASS__, 'ajax_get_suggestions' ) );
	}

	/** Server-side Anthropic key — never exposed to users or the frontend. */
	private static function get_server_api_key() {
		if ( defined( 'ABLF_ANTHROPIC_API_KEY' ) && ! empty( ABLF_ANTHROPIC_API_KEY ) ) {
			return ABLF_ANTHROPIC_API_KEY;
		}
		$stored = get_option( 'ablf_anthropic_api_key', '' );
		return $stored ? ablf_decrypt( $stored ) : '';
	}

	public static function get_suggestions( $broken_link_id ) {
		$broken = ABLF_DB_Handler::get_broken_link_by_id( $broken_link_id );
		if ( ! $broken ) {
			return new WP_Error( 'ablf_not_found', __( 'Broken link not found.', 'ai-broken-link-fixer' ) );
		}

		if ( class_exists( 'ABLF_License' ) && ! ABLF_License::can_use_pathfinder() ) {
			return new WP_Error( 'ablf_limit', __( 'No credits remaining. Purchase more credits or upgrade your plan.', 'ai-broken-link-fixer' ) );
		}

		$api_key = self::get_server_api_key();
		if ( ! $api_key ) {
			return new WP_Error( 'ablf_no_key', __( 'Pathfinder is not configured. Please contact support.', 'ai-broken-link-fixer' ) );
		}

		$candidates = self::find_local_candidates( $broken );
		if ( empty( $candidates ) ) {
			ABLF_DB_Handler::mark_pathfinder_run( $broken_link_id );
			return array();
		}

		$api_result = self::call_claude_api( $broken, $candidates, $api_key );
		if ( is_wp_error( $api_result ) ) {
			return $api_result;
		}

		ABLF_DB_Handler::delete_suggestions_for_link( $broken_link_id );

		$stored = array();
		foreach ( $api_result as $s ) {
			$id = ABLF_DB_Handler::insert_suggestion( array(
				'broken_link_id'  => (int) $broken_link_id,
				'suggested_url'   => esc_url_raw( $s['url'] ),
				'suggested_title' => isset( $s['title'] ) ? sanitize_text_field( $s['title'] ) : '',
				'confidence'      => isset( $s['confidence'] ) ? (float) $s['confidence'] : 0.0,
				'reasoning'       => isset( $s['reasoning'] ) ? sanitize_text_field( $s['reasoning'] ) : '',
			) );
			$s['id'] = $id;
			$stored[] = $s;
		}

		ABLF_DB_Handler::mark_pathfinder_run( $broken_link_id );

		if ( class_exists( 'ABLF_License' ) ) {
			ABLF_License::increment_usage();
		} else {
			ABLF_DB_Handler::increment_usage();
		}

		return $stored;
	}

	public static function find_local_candidates( $broken ) {
		static $stop_words = array(
			'the', 'a', 'an', 'this', 'that', 'for', 'to', 'of', 'and', 'or',
			'in', 'on', 'at', 'with', 'is', 'it', 'as', 'by', 'totally', 'made',
			'up', 'fake', 'test', 'dummy', 'page', 'url', 'click', 'here', 'read',
			'more', 'third', 'resource', 'another',
		);

		$post_types = (array) get_option( 'ablf_scan_post_types', array( 'post', 'page' ) );

		// Extract words from anchor text.
		$raw_words = array();
		if ( ! empty( $broken->anchor_text ) ) {
			$raw_words = array_merge( $raw_words, preg_split( '/\s+/', strtolower( $broken->anchor_text ), -1, PREG_SPLIT_NO_EMPTY ) );
		}

		// Extract words from the last path segment of the broken URL.
		$path = wp_parse_url( $broken->broken_url, PHP_URL_PATH );
		if ( $path ) {
			$segment = trim( basename( $path ) );
			if ( $segment ) {
				$slug_words = preg_split( '/[\-_]+/', strtolower( $segment ), -1, PREG_SPLIT_NO_EMPTY );
				$raw_words  = array_merge( $raw_words, $slug_words );
			}
		}

		// Remove stop words and duplicates.
		$filtered = array_unique( array_filter( $raw_words, function( $w ) use ( $stop_words ) {
			return strlen( $w ) > 1 && ! in_array( $w, $stop_words, true );
		} ) );

		$candidates = array();

		if ( ! empty( $filtered ) ) {
			$search = implode( ' ', $filtered );
			$query  = new WP_Query( array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				's'                      => $search,
				'posts_per_page'         => 8,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );
			$candidates = self::posts_to_candidates( $query->posts, $broken->broken_url );
		}

		// Fall back to recent posts when search yielded nothing.
		if ( empty( $candidates ) ) {
			$fallback = new WP_Query( array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => 5,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );
			$candidates = self::posts_to_candidates( $fallback->posts, $broken->broken_url );
		}

		return $candidates;
	}

	private static function posts_to_candidates( $posts, $broken_url ) {
		$candidates = array();
		foreach ( $posts as $p ) {
			$url = get_permalink( $p );
			if ( $url === $broken_url ) {
				continue;
			}
			$candidates[] = array(
				'url'     => $url,
				'title'   => get_the_title( $p ),
				'excerpt' => wp_trim_words( wp_strip_all_tags( $p->post_content ), 30, '…' ),
			);
		}
		return $candidates;
	}

	public static function build_prompt( $broken, $candidates ) {
		$source_title = get_the_title( (int) $broken->source_post_id );

		$cand_lines = array();
		foreach ( $candidates as $c ) {
			$cand_lines[] = '- ' . $c['title'] . ' | ' . $c['url'] . ' | ' . $c['excerpt'];
		}
		$cand_block = implode( "\n", $cand_lines );

		$prompt = "You are Pathfinder, an AI engine that fixes broken links on WordPress websites.\n\n";
		$prompt .= "A broken link was found with this context:\n\n";
		$prompt .= "Broken URL: {$broken->broken_url}\n";
		$prompt .= "Anchor text: \"{$broken->anchor_text}\"\n";
		$prompt .= "Surrounding text: \"{$broken->surrounding_context}\"\n";
		$prompt .= "Found on page: \"{$source_title}\"\n\n";
		$prompt .= "Candidate replacement URLs from the same website:\n{$cand_block}\n\n";
		$prompt .= "Analyze the context and candidates. Return ONLY a valid JSON object — no markdown, no explanation outside the JSON:\n";
		$prompt .= "{\n";
		$prompt .= "  \"suggestions\": [\n";
		$prompt .= "    {\n";
		$prompt .= "      \"url\": \"https://...\",\n";
		$prompt .= "      \"title\": \"Page title\",\n";
		$prompt .= "      \"confidence\": 0.95,\n";
		$prompt .= "      \"reasoning\": \"One sentence explaining why this is the best match\"\n";
		$prompt .= "    }\n";
		$prompt .= "  ]\n";
		$prompt .= "}\n\n";
		$prompt .= "Rules:\n";
		$prompt .= "- Return max 3 suggestions ordered by confidence (highest first)\n";
		$prompt .= "- confidence is a float between 0.00 and 1.00\n";
		$prompt .= "- If no candidates are a reasonable match, return {\"suggestions\": []}\n";
		$prompt .= "- Never suggest the broken URL itself\n";
		$prompt .= "- Never invent URLs not in the candidates list\n";

		return (string) apply_filters( 'ablf_pathfinder_prompt', $prompt, array(
			'broken'     => $broken,
			'candidates' => $candidates,
		) );
	}

	public static function call_claude_api( $broken, $candidates, $api_key ) {
		$prompt = self::build_prompt( $broken, $candidates );

		$body = array(
			'model'      => self::MODEL,
			'max_tokens' => self::MAX_TOKENS,
			'messages'   => array(
				array( 'role' => 'user', 'content' => $prompt ),
			),
		);

		$response = wp_remote_post( self::API_ENDPOINT, array(
			'timeout' => 30,
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ablf_api_error', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			/* translators: %d: HTTP status code returned by the Pathfinder API */
			return new WP_Error( 'ablf_api_http', sprintf( __( 'Pathfinder API returned HTTP %d.', 'ai-broken-link-fixer' ), $code ) );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded['content'][0]['text'] ) ) {
			return array();
		}

		$text = trim( $decoded['content'][0]['text'] );
		$text = preg_replace( '/^```(?:json)?\s*|\s*```$/m', '', $text );

		$parsed = json_decode( $text, true );
		if ( ! is_array( $parsed ) || ! isset( $parsed['suggestions'] ) || ! is_array( $parsed['suggestions'] ) ) {
			return array();
		}

		$candidate_urls = array_map( function( $c ) { return $c['url']; }, $candidates );
		$clean = array();
		foreach ( $parsed['suggestions'] as $s ) {
			if ( empty( $s['url'] ) ) {
				continue;
			}
			if ( $s['url'] === $broken->broken_url ) {
				continue;
			}
			if ( ! in_array( $s['url'], $candidate_urls, true ) ) {
				continue;
			}
			$clean[] = array(
				'url'        => $s['url'],
				'title'      => isset( $s['title'] ) ? $s['title'] : '',
				'confidence' => isset( $s['confidence'] ) ? (float) $s['confidence'] : 0.0,
				'reasoning'  => isset( $s['reasoning'] ) ? $s['reasoning'] : '',
			);
			if ( count( $clean ) >= 3 ) {
				break;
			}
		}
		return $clean;
	}

	public static function ajax_get_suggestions() {
		check_ajax_referer( 'ablf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-broken-link-fixer' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'ai-broken-link-fixer' ) ) );
		}

		$result = self::get_suggestions( $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$broken = ABLF_DB_Handler::get_broken_link_by_id( $id );
		$html = ABLF_Admin::render_partial( 'suggestion-card', array(
			'broken'      => $broken,
			'suggestions' => $result,
		) );

		wp_send_json_success( array( 'html' => $html, 'count' => count( $result ) ) );
	}

}
