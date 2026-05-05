<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABLF_Activator {

	public static function activate() {
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( ABLF_PLUGIN_BASENAME );
			wp_die( esc_html__( 'Pathfinder Link Repair requires PHP 7.4 or higher.', 'pathfinder-link-repair' ) );
		}

		global $wp_version;
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			deactivate_plugins( ABLF_PLUGIN_BASENAME );
			wp_die( esc_html__( 'Pathfinder Link Repair requires WordPress 6.0 or higher.', 'pathfinder-link-repair' ) );
		}

		self::create_tables();
		self::set_default_options();
		self::schedule_events();

		update_option( 'ablf_plugin_version', ABLF_VERSION );
		update_option( 'ablf_db_version', ABLF_DB_VERSION );
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$prefix}ablf_broken_links (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_post_id BIGINT(20) UNSIGNED NOT NULL,
			broken_url VARCHAR(2083) NOT NULL,
			anchor_text VARCHAR(500) DEFAULT '',
			surrounding_context TEXT,
			http_status SMALLINT(5) DEFAULT 0,
			status ENUM('broken','fixed','ignored','pending') NOT NULL DEFAULT 'broken',
			pathfinder_run TINYINT(1) DEFAULT 0,
			first_found_at DATETIME NOT NULL,
			last_checked_at DATETIME DEFAULT NULL,
			fixed_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY source_post_id (source_post_id),
			KEY status (status),
			KEY last_checked_at (last_checked_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}ablf_suggestions (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			broken_link_id BIGINT(20) UNSIGNED NOT NULL,
			suggested_url VARCHAR(2083) NOT NULL,
			suggested_title VARCHAR(500) DEFAULT '',
			confidence DECIMAL(4,2) DEFAULT 0.00,
			reasoning TEXT,
			status ENUM('pending','accepted','rejected') DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY broken_link_id (broken_link_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}ablf_fix_log (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			broken_link_id BIGINT(20) UNSIGNED NOT NULL,
			source_post_id BIGINT(20) UNSIGNED NOT NULL,
			original_url VARCHAR(2083) NOT NULL,
			replacement_url VARCHAR(2083) NOT NULL,
			anchor_text VARCHAR(500) DEFAULT '',
			fixed_by BIGINT(20) UNSIGNED NOT NULL,
			fixed_at DATETIME NOT NULL,
			redirect_created TINYINT(1) DEFAULT 0,
			PRIMARY KEY  (id),
			KEY source_post_id (source_post_id),
			KEY fixed_at (fixed_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}ablf_redirects (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			from_url VARCHAR(2083) NOT NULL,
			to_url VARCHAR(2083) NOT NULL,
			http_code SMALLINT(5) DEFAULT 301,
			created_at DATETIME NOT NULL,
			hit_count BIGINT(20) DEFAULT 0,
			PRIMARY KEY  (id),
			KEY from_url (from_url(255))
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}ablf_scan_queue (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_post_id BIGINT(20) UNSIGNED NOT NULL,
			url VARCHAR(2083) NOT NULL,
			anchor_text VARCHAR(500) DEFAULT '',
			surrounding_context TEXT,
			status ENUM('queued','processing','done','failed') DEFAULT 'queued',
			attempts TINYINT(3) DEFAULT 0,
			queued_at DATETIME NOT NULL,
			processed_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY queued_at (queued_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}ablf_allowlist (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			pattern VARCHAR(2083) NOT NULL,
			pattern_type ENUM('url','domain') DEFAULT 'url',
			note VARCHAR(500) DEFAULT '',
			created_at DATETIME NOT NULL,
			created_by BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			KEY pattern_type (pattern_type)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}ablf_usage (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			month_year VARCHAR(7) NOT NULL,
			suggestions_used BIGINT(20) DEFAULT 0,
			topup_credits BIGINT(20) DEFAULT 0,
			period_start DATETIME DEFAULT NULL,
			last_updated DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY month_year (month_year)
		) {$charset_collate};";

		foreach ( $sql as $stmt ) {
			dbDelta( $stmt );
		}
	}

	private static function set_default_options() {
		$defaults = array(
			'ablf_anthropic_api_key'   => '',
			'ablf_scan_frequency'      => 'manual',
			'ablf_scan_post_types'     => array( 'post', 'page' ),
			'ablf_batch_size'          => 20,
			'ablf_http_timeout'        => 10,
			'ablf_concurrent_requests' => 5,
			'ablf_excluded_urls'       => '',
			'ablf_excluded_domains'    => '',
			'ablf_auto_redirect'       => false,
			'ablf_data_retention_days' => 90,
			'ablf_last_scan_at'        => '',
		);
		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $value );
			}
		}

		// Rolling 30-day credit reset — set once on first activation, never overwrite.
		if ( ! get_option( 'ablf_install_date' ) ) {
			$now      = current_time( 'mysql' );
			$next     = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
			add_option( 'ablf_install_date',    $now );
			add_option( 'ablf_next_reset_date', $next );
		}
	}

	/**
	 * Run DB migrations for existing installs. Safe to call on every load
	 * when ablf_db_version is behind ABLF_DB_VERSION — dbDelta is idempotent.
	 */
	public static function maybe_upgrade_db() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		// Create allowlist table if it doesn't exist yet.
		dbDelta( "CREATE TABLE {$prefix}ablf_allowlist (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			pattern VARCHAR(2083) NOT NULL,
			pattern_type ENUM('url','domain') DEFAULT 'url',
			note VARCHAR(500) DEFAULT '',
			created_at DATETIME NOT NULL,
			created_by BIGINT(20) UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			KEY pattern_type (pattern_type)
		) {$charset_collate};" );

		// Re-run usage table SQL so dbDelta can add topup_credits and period_start columns.
		dbDelta( "CREATE TABLE {$prefix}ablf_usage (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			month_year VARCHAR(7) NOT NULL,
			suggestions_used BIGINT(20) DEFAULT 0,
			topup_credits BIGINT(20) DEFAULT 0,
			period_start DATETIME DEFAULT NULL,
			last_updated DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY month_year (month_year)
		) {$charset_collate};" );

		// Seed install/reset dates for existing installs that pre-date this feature.
		if ( ! get_option( 'ablf_install_date' ) ) {
			$now  = current_time( 'mysql' );
			$next = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
			update_option( 'ablf_install_date',    $now );
			update_option( 'ablf_next_reset_date', $next );
		}

		// Replace the old recurring monthly cron with a single-event if needed.
		if ( ! wp_next_scheduled( 'ablf_reset_monthly_usage' ) ) {
			$next = get_option( 'ablf_next_reset_date', '' );
			$ts   = $next ? strtotime( $next ) : ( time() + 30 * DAY_IN_SECONDS );
			wp_schedule_single_event( $ts, 'ablf_reset_monthly_usage' );
		}

		update_option( 'ablf_db_version', ABLF_DB_VERSION );
	}

	private static function schedule_events() {
		if ( ! wp_next_scheduled( 'ablf_process_scan_queue' ) ) {
			wp_schedule_event( time() + 300, 'ablf_every_5_minutes', 'ablf_process_scan_queue' );
		}
		// Rolling 30-day reset — single event, rescheduled by ABLF_Scheduler::reset_monthly_usage().
		if ( ! wp_next_scheduled( 'ablf_reset_monthly_usage' ) ) {
			$next = get_option( 'ablf_next_reset_date', '' );
			$ts   = $next ? strtotime( $next ) : ( time() + 30 * DAY_IN_SECONDS );
			wp_schedule_single_event( $ts, 'ablf_reset_monthly_usage' );
		}
		if ( ! wp_next_scheduled( 'ablf_cleanup_old_data' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'ablf_cleanup_old_data' );
		}
	}
}
