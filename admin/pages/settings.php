<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_types = get_post_types( array( 'public' => true ), 'objects' );
$selected   = (array) get_option( 'ablf_scan_post_types', array( 'post', 'page' ) );
?>
<div class="wrap ablf-wrap">
	<h1><?php esc_html_e( 'Pathfinder Link Repair — Settings', 'pathfinder-link-repair' ); ?></h1>

	<?php if ( isset( $_GET['settings-updated'] ) && sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'pathfinder-link-repair' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="options.php" class="ablf-settings-form">
		<?php settings_fields( 'ablf_settings' ); ?>

		<h2><?php esc_html_e( 'Pathfinder AI', 'pathfinder-link-repair' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ablf_anthropic_api_key"><?php esc_html_e( 'Anthropic API Key', 'pathfinder-link-repair' ); ?></label></th>
				<td>
					<?php
					$key_constant_set = defined( 'ABLF_ANTHROPIC_API_KEY' ) && ! empty( ABLF_ANTHROPIC_API_KEY );
					$has_stored_key   = (bool) get_option( 'ablf_anthropic_api_key', '' );
					if ( $key_constant_set ) : ?>
						<p class="description"><?php esc_html_e( 'API key is set via the ABLF_ANTHROPIC_API_KEY constant in wp-config.php and cannot be changed here.', 'pathfinder-link-repair' ); ?></p>
					<?php else : ?>
						<input type="password" id="ablf_anthropic_api_key" name="ablf_anthropic_api_key" value="" class="regular-text" autocomplete="off" placeholder="<?php echo $has_stored_key ? esc_attr__( '••••••••  (saved — leave blank to keep)', 'pathfinder-link-repair' ) : 'sk-ant-...'; ?>">
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to Anthropic console */
								esc_html__( 'Required for AI suggestions. Get a key at %s. The key is encrypted and stored server-side; it is never exposed to the browser.', 'pathfinder-link-repair' ),
								'<a href="https://console.anthropic.com" target="_blank" rel="noopener noreferrer">console.anthropic.com</a>'
							); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

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
						<?php foreach ( $freq_options as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_freq, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( 'manual' !== $current_freq ) :
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
						<input type="checkbox" name="ablf_auto_redirect" value="1" <?php checked( (bool) get_option( 'ablf_auto_redirect', false ) ); ?>>
						<?php esc_html_e( 'When a link is fixed, create a 301 from the old URL.', 'pathfinder-link-repair' ); ?>
					</label>
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
		</table>

		<?php submit_button(); ?>
	</form>
</div>
