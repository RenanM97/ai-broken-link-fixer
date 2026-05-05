<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABLF_Scheduler {

	public static function register_hooks() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_custom_intervals' ) );
		add_action( 'ablf_run_scheduled_scan', array( __CLASS__, 'run_scheduled_scan' ) );
		add_action( 'ablf_process_scan_queue', array( 'ABLF_Scanner', 'process_queue_batch' ) );
		// Rolling 30-day usage stat reset — informational only, no feature gating.
		add_action( 'ablf_reset_monthly_usage', array( __CLASS__, 'reset_monthly_usage' ) );
		add_action( 'ablf_cleanup_old_data', array( __CLASS__, 'run_cleanup' ) );
	}

	public static function add_custom_intervals( $schedules ) {
		$schedules['ablf_every_5_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes (ABLF)', 'pathfinder-link-repair' ),
		);
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'pathfinder-link-repair' ),
			);
		}
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'pathfinder-link-repair' ),
			);
		}
		return $schedules;
	}

	public static function register_schedules() {
		if ( ! wp_next_scheduled( 'ablf_process_scan_queue' ) ) {
			wp_schedule_event( time() + 300, 'ablf_every_5_minutes', 'ablf_process_scan_queue' );
		}
		if ( ! wp_next_scheduled( 'ablf_reset_monthly_usage' ) ) {
			// Guard: seed the option for installs that pre-date the rolling reset feature.
			$next = get_option( 'ablf_next_reset_date', '' );
			if ( ! $next ) {
				$next = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
				update_option( 'ablf_next_reset_date', $next );
			}
			wp_schedule_single_event( strtotime( $next ), 'ablf_reset_monthly_usage' );
		}
		if ( ! wp_next_scheduled( 'ablf_cleanup_old_data' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'ablf_cleanup_old_data' );
		}

		self::sync_scheduled_scan();
	}

	public static function sync_scheduled_scan() {
		$frequency = get_option( 'ablf_scan_frequency', 'manual' );
		$scheduled = wp_next_scheduled( 'ablf_run_scheduled_scan' );

		if ( 'manual' === $frequency ) {
			if ( $scheduled ) {
				wp_clear_scheduled_hook( 'ablf_run_scheduled_scan' );
			}
			return;
		}

		$interval_map = array(
			'daily'   => 'daily',
			'weekly'  => 'weekly',
			'monthly' => 'monthly',
		);
		if ( ! isset( $interval_map[ $frequency ] ) ) {
			return;
		}

		if ( ! $scheduled ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $interval_map[ $frequency ], 'ablf_run_scheduled_scan' );
		}
	}

	public static function clear_all_schedules() {
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

	public static function run_scheduled_scan() {
		if ( class_exists( 'ABLF_Scanner' ) ) {
			ABLF_Scanner::scan_all_posts();
		}
	}

	public static function run_cleanup() {
		$days = (int) get_option( 'ablf_data_retention_days', 90 );
		ABLF_DB_Handler::cleanup_old_data( $days );
		ABLF_DB_Handler::clear_finished_queue();
	}

	/**
	 * Roll the 30-day usage stat: zero out monthly counter, stamp the next reset,
	 * and schedule the next single-fire cron event.
	 */
	public static function reset_monthly_usage() {
		ABLF_DB_Handler::reset_monthly_usage();
		$next = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
		update_option( 'ablf_next_reset_date', $next );
		wp_schedule_single_event( strtotime( $next ), 'ablf_reset_monthly_usage' );
	}

	public static function get_next_scan_time() {
		$ts = wp_next_scheduled( 'ablf_run_scheduled_scan' );
		if ( ! $ts ) {
			return '';
		}
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
	}
}
