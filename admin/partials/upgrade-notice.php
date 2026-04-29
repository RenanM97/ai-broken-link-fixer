<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// $context is passed by the caller: 'limit_reached' or '' (generic upsell).
$context = isset( $context ) ? (string) $context : '';
?>
<div class="ablf-upgrade-overlay">
	<?php if ( 'limit_reached' === $context ) : ?>
		<h2><?php esc_html_e( "You've used all 100 free credits this month.", 'pathfinder-link-repair' ); ?></h2>
		<p><?php esc_html_e( 'Upgrade to Pro for 1,000 credits/month — or add 500 more for just $5.', 'pathfinder-link-repair' ); ?></p>
		<p>
			<a href="<?php echo esc_url( ABLF_URL_PRO ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Upgrade to Pro — $29/year', 'pathfinder-link-repair' ); ?>
			</a>
			&nbsp;
			<a href="<?php echo esc_url( ABLF_URL_AGENCY ); ?>" class="button" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Agency Plan — $79/year', 'pathfinder-link-repair' ); ?>
			</a>
			&nbsp;
			<a href="<?php echo esc_url( ABLF_URL_CREDITS_500 ); ?>" class="button" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Buy 500 Credits — $5', 'pathfinder-link-repair' ); ?>
			</a>
		</p>
	<?php else : ?>
		<h2><?php esc_html_e( 'Upgrade to Pathfinder Pro', 'pathfinder-link-repair' ); ?></h2>
		<p><?php esc_html_e( 'Unlock 1,000 credits/month, scheduled scans, and the redirect manager.', 'pathfinder-link-repair' ); ?></p>
		<p>
			<a href="<?php echo esc_url( ABLF_URL_PRO ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Upgrade to Pro — $29/year', 'pathfinder-link-repair' ); ?>
			</a>
			&nbsp;
			<a href="<?php echo esc_url( ABLF_URL_AGENCY ); ?>" class="button" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Agency Plan — $79/year', 'pathfinder-link-repair' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
