<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page = 20;
$rows     = ABLF_DB_Handler::get_fix_log( $page, $per_page );
$total    = ABLF_DB_Handler::count_fix_log();
$pages    = max( 1, (int) ceil( $total / $per_page ) );

$export_url = add_query_arg( array(
	'page'   => 'ablf-log',
	'action' => 'export',
	'nonce'  => wp_create_nonce( 'ablf_export' ),
), admin_url( 'admin.php' ) );
?>
<div class="wrap ablf-wrap">
	<h1><?php esc_html_e( 'Fix Log', 'ai-broken-link-fixer' ); ?>
		<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'ai-broken-link-fixer' ); ?></a>
	</h1>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date Fixed', 'ai-broken-link-fixer' ); ?></th>
				<th><?php esc_html_e( 'Fixed By', 'ai-broken-link-fixer' ); ?></th>
				<th><?php esc_html_e( 'Source', 'ai-broken-link-fixer' ); ?></th>
				<th><?php esc_html_e( 'Original URL', 'ai-broken-link-fixer' ); ?></th>
				<th><?php esc_html_e( 'Replacement URL', 'ai-broken-link-fixer' ); ?></th>
				<th><?php esc_html_e( 'Redirect', 'ai-broken-link-fixer' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No fixes logged yet.', 'ai-broken-link-fixer' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $r ) :
					$user       = (int) $r->fixed_by > 0 ? get_user_by( 'id', (int) $r->fixed_by ) : null;
					$post_edit  = (int) $r->source_post_id > 0 ? get_edit_post_link( (int) $r->source_post_id ) : null;
					$post_title = (int) $r->source_post_id > 0 ? get_the_title( (int) $r->source_post_id ) : __( 'Manual redirect', 'ai-broken-link-fixer' );
				?>
					<tr>
						<td><?php echo esc_html( $r->fixed_at ); ?></td>
						<td><?php echo esc_html( $user ? $user->display_name : __( 'Unknown', 'ai-broken-link-fixer' ) ); ?></td>
						<td><?php if ( $post_edit ) : ?><a href="<?php echo esc_url( $post_edit ); ?>" target="_blank"><?php echo esc_html( $post_title ); ?></a><?php else : echo esc_html( $post_title ); endif; ?></td>
						<td class="ablf-url"><?php echo esc_html( $r->original_url ); ?></td>
						<td class="ablf-url"><?php echo esc_html( $r->replacement_url ); ?></td>
						<td><?php if ( $r->redirect_created ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=ablf-redirects' ) ); ?>">&#x2705; Yes</a>
							<?php else : ?>
								&mdash;
							<?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php echo wp_kses_post( paginate_links( array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'total'   => $pages,
					'current' => $page,
				) ) ); ?>
			</div>
		</div>
	<?php endif; ?>
</div>
