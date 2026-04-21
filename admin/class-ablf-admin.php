<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABLF_Admin {

	const MENU_SLUG = 'ablf-dashboard';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_notices' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_export_csv' ) );
		add_action( 'wp_ajax_ablf_bulk_action',       array( __CLASS__, 'ajax_bulk_action' ) );
		add_action( 'wp_ajax_ablf_add_allowlist',    array( __CLASS__, 'ajax_add_allowlist' ) );
		add_action( 'wp_ajax_ablf_remove_allowlist', array( __CLASS__, 'ajax_remove_allowlist' ) );
	}

	public static function register_menu() {
		$cap = 'manage_options';

		add_menu_page(
			__( 'Broken Links', 'ai-broken-link-fixer' ),
			__( 'Broken Links', 'ai-broken-link-fixer' ),
			$cap,
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-admin-links',
			80
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'ai-broken-link-fixer' ),
			__( 'Dashboard', 'ai-broken-link-fixer' ),
			$cap,
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Redirects', 'ai-broken-link-fixer' ),
			__( 'Redirects', 'ai-broken-link-fixer' ),
			$cap,
			'ablf-redirects',
			array( __CLASS__, 'render_redirects' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Fix Log', 'ai-broken-link-fixer' ),
			__( 'Fix Log', 'ai-broken-link-fixer' ),
			$cap,
			'ablf-log',
			array( __CLASS__, 'render_log' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'ai-broken-link-fixer' ),
			__( 'Settings', 'ai-broken-link-fixer' ),
			$cap,
			'ablf-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( strpos( (string) $hook, 'ablf' ) === false && strpos( (string) $hook, self::MENU_SLUG ) === false ) {
			return;
		}

		wp_enqueue_style(
			'ablf-admin',
			ABLF_PLUGIN_URL . 'assets/css/ablf-admin.css',
			array(),
			ABLF_VERSION
		);

		wp_enqueue_script(
			'ablf-scanner',
			ABLF_PLUGIN_URL . 'assets/js/ablf-scanner.js',
			array( 'jquery' ),
			ABLF_VERSION,
			true
		);

		wp_enqueue_script(
			'ablf-dashboard',
			ABLF_PLUGIN_URL . 'assets/js/ablf-dashboard.js',
			array( 'jquery' ),
			ABLF_VERSION,
			true
		);

		wp_enqueue_script(
			'ablf-settings',
			ABLF_PLUGIN_URL . 'assets/js/ablf-settings.js',
			array( 'jquery' ),
			ABLF_VERSION,
			true
		);

		$localized = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ablf_nonce' ),
			'i18n'    => array(
				'confirmFix'     => __( 'Replace this link in the post?', 'ai-broken-link-fixer' ),
				'confirmIgnore'  => __( 'Ignore this broken link?', 'ai-broken-link-fixer' ),
				'confirmClear'   => __( 'This will permanently delete all scan data. Continue?', 'ai-broken-link-fixer' ),
				'thinking'       => __( 'Pathfinder is thinking…', 'ai-broken-link-fixer' ),
				'scanning'       => __( 'Scanning…', 'ai-broken-link-fixer' ),
				/* translators: %1$s: number of URLs checked, %2$s: total URLs */
				'checking'       => __( 'Checking %1$s of %2$s URLs…', 'ai-broken-link-fixer' ),
				'connectionOk'   => __( 'Connection successful.', 'ai-broken-link-fixer' ),
				'connectionFail' => __( 'Connection failed.', 'ai-broken-link-fixer' ),
				'error'          => __( 'Something went wrong.', 'ai-broken-link-fixer' ),
			),
		);
		wp_localize_script( 'ablf-scanner', 'ABLF', $localized );
		wp_localize_script( 'ablf-dashboard', 'ABLF', $localized );
		wp_localize_script( 'ablf-settings', 'ABLF', $localized );
	}

	public static function show_notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || strpos( (string) $screen->id, 'ablf' ) === false ) {
			return;
		}

		$key_set = ( defined( 'ABLF_ANTHROPIC_API_KEY' ) && ! empty( ABLF_ANTHROPIC_API_KEY ) )
		           || get_option( 'ablf_anthropic_api_key' );
		if ( ! $key_set ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'Pathfinder API key is not set. Add it in Settings to enable AI suggestions.', 'ai-broken-link-fixer' ) .
				' <a href="' . esc_url( admin_url( 'admin.php?page=ablf-settings' ) ) . '">' .
				esc_html__( 'Go to Settings', 'ai-broken-link-fixer' ) . '</a></p></div>';
		}

		if ( class_exists( 'ABLF_License' ) && ! ABLF_License::can_use_pathfinder() ) {
			echo '<div class="notice notice-info"><p>' .
				esc_html__( 'You have reached the free Pathfinder limit for this month. Upgrade to Pro for unlimited suggestions.', 'ai-broken-link-fixer' ) .
				'</p></div>';
		}
	}

	public static function register_settings() {
		$fields = array(
			'ablf_api_key'             => array( __CLASS__, 'sanitize_api_key' ),
			'ablf_scan_frequency'      => array( __CLASS__, 'sanitize_scan_frequency' ),
			'ablf_scan_post_types'     => array( __CLASS__, 'sanitize_array' ),
			'ablf_batch_size'          => 'absint',
			'ablf_http_timeout'        => 'absint',
			'ablf_concurrent_requests' => 'absint',
			'ablf_auto_redirect'       => array( __CLASS__, 'sanitize_bool' ),
			'ablf_data_retention_days' => 'absint',
			'ablf_license_key'         => 'sanitize_text_field',
		);
		foreach ( $fields as $key => $cb ) {
			register_setting( 'ablf_settings', $key, array( 'sanitize_callback' => $cb ) );
		}

		add_action( 'update_option_ablf_license_key', array( __CLASS__, 'sync_license_tier' ), 10, 2 );
		add_action( 'add_option_ablf_license_key', array( __CLASS__, 'sync_license_tier_added' ), 10, 2 );
		add_action( 'update_option_ablf_scan_frequency', array( 'ABLF_Scheduler', 'sync_scheduled_scan' ) );
	}

	public static function sync_license_tier( $old, $new ) {
		if ( ! class_exists( 'ABLF_License' ) ) {
			return;
		}
		update_option( 'ablf_license_tier', ABLF_License::validate_license_key( $new ) );
	}

	public static function sync_license_tier_added( $option, $value ) {
		self::sync_license_tier( '', $value );
	}

	public static function sanitize_array( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_map( 'sanitize_text_field', $value ) );
	}

	public static function sanitize_bool( $value ) {
		return ! empty( $value ) ? 1 : 0;
	}

	public static function sanitize_scan_frequency( $value ) {
		$value   = sanitize_text_field( (string) $value );
		$allowed = array( 'manual', 'daily', 'weekly', 'monthly' );
		if ( ! in_array( $value, $allowed, true ) ) {
			return 'manual';
		}
		if ( 'manual' !== $value && class_exists( 'ABLF_License' ) && ! ABLF_License::can_use_scheduled_scans() ) {
			add_settings_error(
				'ablf_scan_frequency',
				'ablf_pro_required',
				__( 'Scheduled scans are a Pro feature. Upgrade to unlock automatic scanning.', 'ai-broken-link-fixer' ),
				'warning'
			);
			return 'manual';
		}
		return $value;
	}

	public static function sanitize_api_key( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		return ablf_encrypt( $value );
	}

	public static function maybe_export_csv() {
		if (
			! isset( $_GET['page'], $_GET['action'], $_GET['nonce'] ) ||
			'ablf-log' !== $_GET['page'] ||
			'export' !== $_GET['action']
		) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ai-broken-link-fixer' ) );
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'ablf_export' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'ai-broken-link-fixer' ) );
		}

		$rows = ABLF_DB_Handler::get_fix_log( 1, 100000 );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="pathfinder-fix-log.csv"' );
		header( 'Pragma: no-cache' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Date Fixed', 'Fixed By', 'Source Page', 'Original URL', 'Replacement URL', 'Redirect Created' ) );

		foreach ( $rows as $r ) {
			$user       = get_user_by( 'id', (int) $r->fixed_by );
			$fixed_by   = $user ? $user->display_name : (string) $r->fixed_by;
			$post_title = get_the_title( (int) $r->source_post_id );
			fputcsv( $out, array(
				$r->fixed_at,
				$fixed_by,
				$post_title,
				$r->original_url,
				$r->replacement_url,
				$r->redirect_created ? 'yes' : 'no',
			) );
		}

		fclose( $out );
		die();
	}

	public static function render_dashboard() {
		self::render_page( 'dashboard' );
	}

	public static function render_settings() {
		self::render_page( 'settings' );
	}

	public static function render_log() {
		self::render_page( 'log' );
	}

	public static function render_redirects() {
		self::render_page( 'redirects' );
	}

	private static function render_page( $slug ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ai-broken-link-fixer' ) );
		}
		$file = ABLF_PLUGIN_DIR . 'admin/pages/' . $slug . '.php';
		if ( file_exists( $file ) ) {
			require $file;
		}
	}

	public static function render_partial( $slug, $vars = array() ) {
		$file = ABLF_PLUGIN_DIR . 'admin/partials/' . $slug . '.php';
		if ( ! file_exists( $file ) ) {
			return '';
		}
		if ( is_array( $vars ) ) {
			extract( $vars, EXTR_SKIP );
		}
		ob_start();
		include $file;
		return ob_get_clean();
	}

	public static function ajax_bulk_action() {
		check_ajax_referer( 'ablf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-broken-link-fixer' ) ), 403 );
		}

		$action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();

		if ( empty( $action ) || empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Nothing to do.', 'ai-broken-link-fixer' ) ) );
		}

		$processed = 0;
		if ( 'ignore' === $action ) {
			foreach ( $ids as $id ) {
				ABLF_DB_Handler::ignore_link( $id );
				$processed++;
			}
		} elseif ( 'restore' === $action ) {
			foreach ( $ids as $id ) {
				ABLF_DB_Handler::restore_link( $id );
				$processed++;
			}
		} elseif ( 'add_to_allowlist' === $action ) {
			$user_id = get_current_user_id();
			$added   = array();
			foreach ( $ids as $id ) {
				$link = ABLF_DB_Handler::get_broken_link_by_id( $id );
				if ( ! $link ) {
					continue;
				}
				$host   = strtolower( (string) parse_url( $link->broken_url, PHP_URL_HOST ) );
				$domain = preg_replace( '/^www\./', '', $host );
				if ( $domain !== '' && ! in_array( $domain, $added, true ) ) {
					ABLF_DB_Handler::add_to_allowlist( $domain, 'domain', '', $user_id );
					$added[] = $domain;
				}
				ABLF_DB_Handler::ignore_link( $id );
				$processed++;
			}
		}
		wp_send_json_success( array( 'processed' => $processed, 'action' => $action ) );
	}

	public static function ajax_add_allowlist() {
		check_ajax_referer( 'ablf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-broken-link-fixer' ) ), 403 );
		}
		$pattern = isset( $_POST['pattern'] ) ? sanitize_text_field( wp_unslash( $_POST['pattern'] ) ) : '';
		$type    = isset( $_POST['pattern_type'] ) ? sanitize_text_field( wp_unslash( $_POST['pattern_type'] ) ) : 'url';
		$note    = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		if ( ! $pattern ) {
			wp_send_json_error( array( 'message' => __( 'Pattern is required.', 'ai-broken-link-fixer' ) ) );
		}
		ABLF_DB_Handler::add_to_allowlist( $pattern, $type, $note, get_current_user_id() );
		$id = (int) $GLOBALS['wpdb']->insert_id;
		$user = get_userdata( get_current_user_id() );
		wp_send_json_success( array(
			'id'           => $id,
			'pattern'      => $pattern,
			'pattern_type' => $type,
			'note'         => $note,
			'created_at'   => current_time( 'mysql' ),
			'added_by'     => $user ? $user->display_name : '',
		) );
	}

	public static function ajax_remove_allowlist() {
		check_ajax_referer( 'ablf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-broken-link-fixer' ) ), 403 );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'ai-broken-link-fixer' ) ) );
		}
		ABLF_DB_Handler::remove_from_allowlist( $id );
		wp_send_json_success( array( 'id' => $id ) );
	}
}
