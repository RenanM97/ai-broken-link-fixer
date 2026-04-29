<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var object $broken */
/** @var array  $suggestions */
?>
<div class="ablf-suggestion-card" data-broken-id="<?php echo esc_attr( $broken->id ); ?>">
	<h3>🧭 <?php esc_html_e( 'Pathfinder suggests:', 'pathfinder-link-repair' ); ?></h3>

	<?php if ( ! empty( $suggestions ) ) : ?>
		<?php foreach ( $suggestions as $s ) :
			$pct = max( 0, min( 100, (int) round( ( isset( $s['confidence'] ) ? (float) $s['confidence'] : 0 ) * 100 ) ) );
		?>
			<div class="ablf-sug-row">

				<div class="ablf-suggestion-info">
					<div class="ablf-suggestion-title"><?php echo esc_html( isset( $s['title'] ) ? $s['title'] : '' ); ?></div>
					<div class="ablf-suggestion-url"><a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $s['url'] ); ?></a></div>
					<?php if ( ! empty( $s['reasoning'] ) ) : ?>
						<div class="ablf-suggestion-reasoning"><?php echo esc_html( $s['reasoning'] ); ?></div>
					<?php endif; ?>
				</div>

				<div class="ablf-suggestion-confidence">
					<div class="ablf-confidence-bar-wrap">
						<div class="ablf-confidence-bar" style="width:<?php echo esc_attr( $pct ); ?>%;"></div>
					</div>
					<span class="ablf-confidence-pct"><?php echo esc_html( $pct ); ?>%</span>
				</div>

				<div class="ablf-suggestion-action">
					<button type="button"
						class="button button-primary ablf-fix-suggestion"
						data-id="<?php echo esc_attr( $broken->id ); ?>"
						data-suggestion="<?php echo esc_attr( isset( $s['id'] ) ? $s['id'] : 0 ); ?>"
						data-url="<?php echo esc_attr( $s['url'] ); ?>">
						<?php esc_html_e( 'Fix', 'pathfinder-link-repair' ); ?>
					</button>
				</div>

			</div>
		<?php endforeach; ?>

		<p class="ablf-suggestion-footer">
			<a href="#" class="ablf-ignore-from-suggestion" data-id="<?php echo esc_attr( $broken->id ); ?>"><?php esc_html_e( 'None of these — ignore', 'pathfinder-link-repair' ); ?></a>
		</p>
	<?php else : ?>
		<p class="ablf-suggestion-empty"><?php esc_html_e( 'No confident matches found on this site.', 'pathfinder-link-repair' ); ?></p>
	<?php endif; ?>
</div>
