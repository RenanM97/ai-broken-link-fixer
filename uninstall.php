<?php
/**
 * Uninstall handler — drops all ABLF tables and deletes all options.
 *
 * This script only runs when WordPress invokes plugin uninstall, so schema
 * changes (DROP TABLE) are not only allowed but expected. Variables are local
 * to the script scope, not true globals — PrefixAllGlobals false-positives.
 * The {$full} table-name interpolation is built from $wpdb->prefix and a
 * hard-coded suffix; it is not user input.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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
