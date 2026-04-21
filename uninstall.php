<?php
/**
 * Uninstall handler — drops all ABLF tables and deletes all options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	'ablf_broken_links',
	'ablf_suggestions',
	'ablf_fix_log',
	'ablf_redirects',
	'ablf_scan_queue',
	'ablf_usage',
	'ablf_allowlist',
);

foreach ( $tables as $table ) {
	$full = $wpdb->prefix . $table;
	$wpdb->query( "DROP TABLE IF EXISTS {$full}" );
}

$options = array(
	'ablf_anthropic_api_key',
	'ablf_scan_frequency',
	'ablf_scan_post_types',
	'ablf_batch_size',
	'ablf_http_timeout',
	'ablf_concurrent_requests',
	'ablf_excluded_urls',
	'ablf_excluded_domains',
	'ablf_auto_redirect',
	'ablf_data_retention_days',
	'ablf_license_key',
	'ablf_license_tier',
	'ablf_db_version',
	'ablf_last_scan_at',
	'ablf_plugin_version',
	'ablf_install_date',
	'ablf_next_reset_date',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

$cron_hooks = array(
	'ablf_run_scheduled_scan',
	'ablf_process_scan_queue',
	'ablf_reset_monthly_usage',
	'ablf_cleanup_old_data',
);

foreach ( $cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

// Freemius cleanup — remove its stored connections for this plugin.
if ( function_exists( 'ablf_fs' ) ) {
	ablf_fs()->remove_connections();
}
