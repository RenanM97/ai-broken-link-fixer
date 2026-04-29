<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $progress */
$percent = isset( $progress['percent'] ) ? (int) $progress['percent'] : 0;
$done    = isset( $progress['done'] ) ? (int) $progress['done'] : 0;
$total   = isset( $progress['total'] ) ? (int) $progress['total'] : 0;
?>
<div class="ablf-progress">
	<div class="ablf-progress-bar">
		<span class="ablf-progress-fill" style="width:<?php echo esc_attr( $percent ); ?>%;"></span>
		<span class="ablf-progress-percent"><?php echo esc_html( $percent ); ?>%</span>
	</div>
	<div class="ablf-progress-label">
		<?php /* translators: 1: URLs checked so far, 2: total URLs, 3: percent complete */ ?>
		<?php printf( esc_html__( 'Checked %1$d of %2$d URLs (%3$d%%)', 'pathfinder-link-repair' ), absint( $done ), absint( $total ), absint( $percent ) ); ?>
	</div>
</div>
