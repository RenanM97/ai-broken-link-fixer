<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABLF_License {

	// Freemius plan ID → tier mapping.
	const PLAN_ID_PRO    = 46424;
	const PLAN_ID_AGENCY = 46425;

	public static function get_tier() {
		// Prefer the Freemius SDK tier when available — source of truth post-purchase.
		if ( function_exists( 'ablf_fs' ) ) {
			$fs = ablf_fs();
			if ( $fs && $fs->is_paying() ) {
				$plan_id = (int) $fs->get_plan_id();
				if ( self::PLAN_ID_AGENCY === $plan_id ) {
					return 'agency';
				}
				if ( self::PLAN_ID_PRO === $plan_id ) {
					return 'pro';
				}
				// Paying but unknown plan → treat as Pro.
				return 'pro';
			}
		}

		$tier = get_option( 'ablf_license_tier', 'free' );
		if ( ! in_array( $tier, array( 'free', 'pro', 'agency' ), true ) ) {
			return 'free';
		}
		return $tier;
	}

	public static function is_pro() {
		return in_array( self::get_tier(), array( 'pro', 'agency' ), true );
	}

	/**
	 * Monthly credit allocation per tier.
	 * free: 100 | pro: 1,000 | agency: 5,000
	 */
	public static function get_monthly_limit() {
		switch ( self::get_tier() ) {
			case 'agency':
				return 5000;
			case 'pro':
				return 1000;
			default:
				return 100;
		}
	}

	public static function get_usage_this_month() {
		return ABLF_DB_Handler::get_usage_this_month();
	}

	/** Credits remaining from the monthly allowance (does not include top-ups). */
	public static function get_credits_remaining() {
		return max( 0, self::get_monthly_limit() - self::get_usage_this_month() );
	}

	/** Purchased top-up credits available (never expire, not reset monthly). */
	public static function get_topup_credits() {
		return ABLF_DB_Handler::get_topup_credits();
	}

	/** Total credits available: monthly remaining + top-up credits. */
	public static function get_total_credits_available() {
		return self::get_credits_remaining() + self::get_topup_credits();
	}

	/** Add purchased top-up credits to the pool. */
	public static function add_topup_credits( $amount ) {
		return ABLF_DB_Handler::add_topup_credits( (int) $amount );
	}

	public static function can_use_pathfinder() {
		return self::get_total_credits_available() > 0;
	}

	public static function can_use_scheduled_scans() {
		return self::is_pro();
	}

	/**
	 * Consume one credit. Draws from monthly allowance first;
	 * falls back to top-up credits when monthly is exhausted.
	 */
	public static function increment_usage( $by = 1 ) {
		$by = max( 1, (int) $by );

		if ( self::get_credits_remaining() > 0 ) {
			return ABLF_DB_Handler::increment_usage( $by );
		}

		return ABLF_DB_Handler::consume_topup_credit( $by );
	}

	public static function reset_monthly_usage() {
		// 1. Zero out suggestions_used, carry forward topup_credits, stamp period_start.
		ABLF_DB_Handler::reset_monthly_usage();

		// 2. Advance the next-reset date by 30 days from now.
		$next = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
		update_option( 'ablf_next_reset_date', $next );

		// 3. Schedule the next single-fire cron event.
		wp_schedule_single_event( strtotime( $next ), 'ablf_reset_monthly_usage' );
	}

	/**
	 * Validate a manually entered license key against the Freemius API.
	 * On success, stores the mapped tier in ablf_license_tier and returns it.
	 * Returns 'free' for an invalid / empty key.
	 *
	 * Note: under normal use Freemius handles license activation through its
	 * own opt-in flow; this method is a fallback for manual key entry.
	 */
	public static function validate_license_key( $key ) {
		$key = trim( (string) $key );
		if ( '' === $key ) {
			update_option( 'ablf_license_tier', 'free' );
			return 'free';
		}

		$response = wp_remote_get(
			'https://api.freemius.com/v1/products/28106/licenses/' . rawurlencode( $key ) . '.json',
			array(
				'timeout'   => 8,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return 'free';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return 'free';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['plan_id'] ) ) {
			return 'free';
		}

		$plan_id = (int) $body['plan_id'];
		$tier    = 'free';
		if ( self::PLAN_ID_AGENCY === $plan_id ) {
			$tier = 'agency';
		} elseif ( self::PLAN_ID_PRO === $plan_id ) {
			$tier = 'pro';
		}

		update_option( 'ablf_license_tier', $tier );
		return $tier;
	}
}
