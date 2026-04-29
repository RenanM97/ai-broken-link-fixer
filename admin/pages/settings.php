<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_types = get_post_types( array( 'public' => true ), 'objects' );
$selected   = (array) get_option( 'ablf_scan_post_types', array( 'post', 'page' ) );

$tier              = class_exists( 'ABLF_License' ) ? ABLF_License::get_tier() : 'free';
$is_pro            = class_exists( 'ABLF_License' ) ? ABLF_License::is_pro() : false;
$monthly_limit     = class_exists( 'ABLF_License' ) ? ABLF_License::get_monthly_limit() : 100;
$monthly_used      = class_exists( 'ABLF_License' ) ? ABLF_License::get_usage_this_month() : 0;
$credits_remaining = class_exists( 'ABLF_License' ) ? ABLF_License::get_credits_remaining() : $monthly_limit;
$topup_credits     = class_exists( 'ABLF_License' ) ? ABLF_License::get_topup_credits() : 0;
$total_available   = class_exists( 'ABLF_License' ) ? ABLF_License::get_total_credits_available() : $monthly_limit;
$usage_percent = min( 100, (int) round( ( $monthly_used / max( 1, $monthly_limit ) ) * 100 ) );
if ( $usage_percent < 50 ) {
	$bar_color = 'green';
} elseif ( $usage_percent <= 80 ) {
	$bar_color = 'orange';
} else {
	$bar_color = 'red';
}
$next_reset_raw = get_option( 'ablf_next_reset_date', '' );
$next_reset_fmt = $next_reset_raw
	? date_i18n( 'F j, Y', strtotime( $next_reset_raw ) )
	: __( 'within 30 days', 'pathfinder-link-repair' );
?>
<div class="wrap ablf-wrap">
	<h1><?php esc_html_e( 'Pathfinder Link Repair — Settings', 'pathfinder-link-repair' ); ?></h1>

	<?php if ( isset( $_GET['settings-updated'] ) && sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'pathfinder-link-repair' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="options.php" class="ablf-settings-form">
		<?php settings_fields( 'ablf_settings' ); ?>

		<h2>
			<?php esc_html_e( 'Pathfinder Credits', 'pathfinder-link-repair' ); ?>
			<?php
			$tier_labels = array(
				'free'   => __( 'Free Plan', 'pathfinder-link-repair' ),
				'pro'    => __( 'Pro Plan', 'pathfinder-link-repair' ),
				'agency' => __( 'Agency Plan', 'pathfinder-link-repair' ),
			);
			$tier_label = isset( $tier_labels[ $tier ] ) ? $tier_labels[ $tier ] : $tier_labels['free'];
			?>
			<span class="ablf-plan-badge ablf-plan-<?php echo esc_attr( $tier ); ?>"><?php echo esc_html( $tier_label ); ?></span>
		</h2>
		<div class="ablf-credit-card">

			<?php if ( ! $is_pro ) : ?>
				<div class="ablf-credit-section ablf-upgrade-section">
					<h3 class="ablf-credit-section-title"><?php esc_html_e( 'Upgrade Your Plan', 'pathfinder-link-repair' ); ?></h3>
					<p class="ablf-section-desc"><?php esc_html_e( 'Get more credits per month and unlock scheduled scans.', 'pathfinder-link-repair' ); ?></p>
					<div class="ablf-plan-grid">
						<div class="ablf-plan-tile">
							<div class="ablf-plan-tile-name"><?php esc_html_e( 'Pro', 'pathfinder-link-repair' ); ?></div>
							<div class="ablf-plan-tile-price">$29<span>/year</span></div>
							<div class="ablf-plan-tile-desc"><?php esc_html_e( '1,000 credits/month · Scheduled scans', 'pathfinder-link-repair' ); ?></div>
							<a href="<?php echo esc_url( ABLF_URL_PRO ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Upgrade to Pro', 'pathfinder-link-repair' ); ?>
							</a>
						</div>
						<div class="ablf-plan-tile">
							<div class="ablf-plan-tile-name"><?php esc_html_e( 'Agency', 'pathfinder-link-repair' ); ?></div>
							<div class="ablf-plan-tile-price">$79<span>/year</span></div>
							<div class="ablf-plan-tile-desc"><?php esc_html_e( '5,000 credits/month · Everything in Pro', 'pathfinder-link-repair' ); ?></div>
							<a href="<?php echo esc_url( ABLF_URL_AGENCY ); ?>" class="button" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Upgrade to Agency', 'pathfinder-link-repair' ); ?>
							</a>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<div class="ablf-credit-section">
				<h3 class="ablf-credit-section-title"><?php esc_html_e( 'Current Usage', 'pathfinder-link-repair' ); ?></h3>

				<p class="ablf-credit-total">
					<?php
					printf(
						/* translators: %s: number of credits remaining */
						esc_html__( '%s credits remaining this month', 'pathfinder-link-repair' ),
						'<strong>' . esc_html( number_format_i18n( $total_available ) ) . '</strong>'
					); ?>
				</p>

				<div class="ablf-credit-bar-wrap">
					<div class="ablf-credit-bar <?php echo esc_attr( $bar_color ); ?>" style="width:<?php echo esc_attr( $usage_percent ); ?>%;">
						<?php if ( $usage_percent >= 10 ) : ?>
							<span class="ablf-credit-bar-label"><?php echo esc_html( $usage_percent ); ?>%</span>
						<?php endif; ?>
					</div>
				</div>

				<ul class="ablf-credit-stats">
					<li>
						<span class="ablf-credit-stat-label"><?php esc_html_e( 'Monthly usage', 'pathfinder-link-repair' ); ?></span>
						<span class="ablf-credit-stat-value">
							<?php
							printf(
								/* translators: %1$s: number of credits used, %2$s: total monthly credit allowance */
								esc_html__( '%1$s / %2$s', 'pathfinder-link-repair' ),
								esc_html( number_format_i18n( $monthly_used ) ),
								esc_html( number_format_i18n( $monthly_limit ) )
							); ?>
						</span>
					</li>
					<li>
						<span class="ablf-credit-stat-label"><?php esc_html_e( 'Monthly remaining', 'pathfinder-link-repair' ); ?></span>
						<span class="ablf-credit-stat-value"><?php echo esc_html( number_format_i18n( $credits_remaining ) ); ?></span>
					</li>
					<li>
						<span class="ablf-credit-stat-label"><?php esc_html_e( 'Top-up credits', 'pathfinder-link-repair' ); ?></span>
						<span class="ablf-credit-stat-value"><?php echo esc_html( number_format_i18n( $topup_credits ) ); ?></span>
					</li>
					<li>
						<span class="ablf-credit-stat-label"><?php esc_html_e( 'Total available', 'pathfinder-link-repair' ); ?></span>
						<span class="ablf-credit-stat-value"><strong><?php echo esc_html( number_format_i18n( $total_available ) ); ?></strong></span>
					</li>
					<li>
						<span class="ablf-credit-stat-label"><?php esc_html_e( 'Next reset', 'pathfinder-link-repair' ); ?></span>
						<span class="ablf-credit-stat-value"><?php echo esc_html( $next_reset_fmt ); ?></span>
					</li>
				</ul>
			</div>

			<div class="ablf-credit-section ablf-topup-section">
				<h3 class="ablf-credit-section-title"><?php esc_html_e( 'Need More Credits?', 'pathfinder-link-repair' ); ?></h3>
				<p class="ablf-section-desc"><?php esc_html_e( 'Top-up credits never expire and stack with your monthly allowance.', 'pathfinder-link-repair' ); ?></p>
				<div class="ablf-topup-grid">
					<a href="<?php echo esc_url( ABLF_URL_CREDITS_500 ); ?>" class="ablf-topup-tile" target="_blank" rel="noopener noreferrer">
						<span class="ablf-topup-tile-amount">500</span>
						<span class="ablf-topup-tile-label"><?php esc_html_e( 'credits', 'pathfinder-link-repair' ); ?></span>
						<span class="ablf-topup-tile-price">$5</span>
					</a>
					<a href="<?php echo esc_url( ABLF_URL_CREDITS_1000 ); ?>" class="ablf-topup-tile" target="_blank" rel="noopener noreferrer">
						<span class="ablf-topup-tile-amount">1,000</span>
						<span class="ablf-topup-tile-label"><?php esc_html_e( 'credits', 'pathfinder-link-repair' ); ?></span>
						<span class="ablf-topup-tile-price">$9</span>
					</a>
					<a href="<?php echo esc_url( ABLF_URL_CREDITS_5000 ); ?>" class="ablf-topup-tile" target="_blank" rel="noopener noreferrer">
						<span class="ablf-topup-tile-amount">5,000</span>
						<span class="ablf-topup-tile-label"><?php esc_html_e( 'credits', 'pathfinder-link-repair' ); ?></span>
						<span class="ablf-topup-tile-price">$39</span>
					</a>
				</div>
			</div>

		</div>

		<h2><?php esc_html_e( 'Scan Settings', 'pathfinder-link-repair' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Post Types to Scan', 'pathfinder-link-repair' ); ?></th>
				<td>
					<?php foreach ( $post_types as $pt ) : ?>
						<label><input type="checkbox" name="ablf_scan_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $selected, true ) ); ?>> <?php echo esc_html( $pt->label ); ?></label><br>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ablf_scan_frequency"><?php esc_html_e( 'Automatic Scan Frequency', 'pathfinder-link-repair' ); ?></label></th>
				<td>
					<?php
					$current_freq = get_option( 'ablf_scan_frequency', 'manual' );
					$freq_options = array(
						'manual'  => __( 'Manual', 'pathfinder-link-repair' ),
						'daily'   => __( 'Daily', 'pathfinder-link-repair' ),
						'weekly'  => __( 'Weekly', 'pathfinder-link-repair' ),
						'monthly' => __( 'Monthly', 'pathfinder-link-repair' ),
					);
					?>
					<select name="ablf_scan_frequency" id="ablf_scan_frequency">
						<?php foreach ( $freq_options as $val => $label ) :
							$lock = ( 'manual' !== $val && ! $is_pro ) ? ' 🔒' : '';
						?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_freq, $val ); ?>><?php echo esc_html( $label . $lock ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( $is_pro && 'manual' !== $current_freq ) :
						$next_scan = class_exists( 'ABLF_Scheduler' ) ? ABLF_Scheduler::get_next_scan_time() : '';
						if ( $next_scan ) : ?>
							<?php /* translators: %s: date and time of next scheduled scan */ ?>
							<p class="description"><?php printf( esc_html__( 'Next scan: %s', 'pathfinder-link-repair' ), '<strong>' . esc_html( $next_scan ) . '</strong>' ); ?></p>
						<?php endif;
					endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ablf_batch_size"><?php esc_html_e( 'Batch Size', 'pathfinder-link-repair' ); ?></label></th>
				<td><input type="number" id="ablf_batch_size" name="ablf_batch_size" value="<?php echo esc_attr( (int) get_option( 'ablf_batch_size', 20 ) ); ?>" min="5" max="50" class="small-text"> <span class="description"><?php esc_html_e( 'URLs per cron batch (5–50).', 'pathfinder-link-repair' ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><label for="ablf_http_timeout"><?php esc_html_e( 'HTTP Timeout (s)', 'pathfinder-link-repair' ); ?></label></th>
				<td><input type="number" id="ablf_http_timeout" name="ablf_http_timeout" value="<?php echo esc_attr( (int) get_option( 'ablf_http_timeout', 10 ) ); ?>" min="5" max="30" class="small-text"></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'On Fix', 'pathfinder-link-repair' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Auto-create 301 Redirect', 'pathfinder-link-repair' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="ablf_auto_redirect" value="1" <?php checked( (bool) get_option( 'ablf_auto_redirect', false ) ); ?> <?php disabled( ! $is_pro ); ?>>
						<?php esc_html_e( 'When a link is fixed, create a 301 from the old URL.', 'pathfinder-link-repair' ); ?>
					</label>
					<?php if ( ! $is_pro ) : ?>
						<p class="description"><?php esc_html_e( 'Pro feature.', 'pathfinder-link-repair' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Advanced', 'pathfinder-link-repair' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ablf_data_retention_days"><?php esc_html_e( 'Data Retention', 'pathfinder-link-repair' ); ?></label></th>
				<td>
					<select name="ablf_data_retention_days" id="ablf_data_retention_days">
						<?php foreach ( array( 30, 60, 90, 180 ) as $d ) : ?>
							<?php /* translators: %d: number of days */ ?>
							<option value="<?php echo esc_attr( $d ); ?>" <?php selected( (int) get_option( 'ablf_data_retention_days', 90 ), $d ); ?>><?php printf( esc_html__( '%d days', 'pathfinder-link-repair' ), (int) $d ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ablf_license_key"><?php esc_html_e( 'License Key', 'pathfinder-link-repair' ); ?></label></th>
				<td><input type="text" id="ablf_license_key" name="ablf_license_key" value="<?php echo esc_attr( get_option( 'ablf_license_key', '' ) ); ?>" class="regular-text"></td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
