<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// $context is passed by the caller: 'limit_reached' or '' (generic upsell).
$context = isset( $context ) ? (string) $context : '';
?>
<div class="ablf-upgrade-overlay">
	<?php if ( 'limit_reached' === $context ) : ?>
		<h2><?php esc_html_e( "You've used all 100 free credits this month.", 'ai-broken-link-fixer' ); ?></h2>
		<p><?php esc_html_e( 'Upgrade to Pro for 1,000 credits/month — or add 500 more for just $5.', 'ai-broken-link-fixer' ); ?></p>
		<p>
			<a href="<?php echo esc_url( ABLF_URL_PRO ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Upgrade to Pro — $29/year', 'ai-broken-link-fixer' ); ?>
			</a>
			&nbsp;
			<a href="<?php echo esc_url( ABLF_URL_AGENCY ); ?>" class="button" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Agency Plan — $79/year', 'ai-broken-link-fixer' ); ?>
			</a>
			&nbsp;
			<a href="<?php echo esc_url( ABLF_URL_CREDITS_500 ); ?>" class="button" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Buy 500 Credits — $5', 'ai-broken-link-fixer' ); ?>
			</a>
		</p>
	<?php else : ?>
		<h2><?php esc_html_e( 'Upgrade to Pathfinder Pro', 'ai-broken-link-fixer' ); ?></h2>
		<p><?php esc_html_e( 'Unlock 1,000 credits/month, scheduled scans, and the redirect manager.', 'ai-broken-link-fixer' ); ?></p>
		<p>
			<a href="<?php echo esc_url( ABLF_URL_PRO ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Upgrade to Pro — $29/year', 'ai-broken-link-fixer' ); ?>
			</a>
			&nbsp;
			<a href="<?php echo esc_url( ABLF_URL_AGENCY ); ?>" class="button" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Agency Plan — $79/year', 'ai-broken-link-fixer' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
