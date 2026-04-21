<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABLF_Deactivator {

	public static function deactivate() {
		$hooks = array(
			'ablf_run_scheduled_scan',
			'ablf_process_scan_queue',
			'ablf_reset_monthly_usage',
			'ablf_cleanup_old_data',
		);
		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
}
