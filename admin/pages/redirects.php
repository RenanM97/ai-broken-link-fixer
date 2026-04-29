<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_pro = class_exists( 'ABLF_License' ) ? ABLF_License::is_pro() : false;
?>
<div class="wrap ablf-wrap">
	<h1><?php esc_html_e( 'Redirects', 'pathfinder-link-repair' ); ?></h1>

	<?php if ( ! $is_pro ) : ?>
		<div class="ablf-upgrade-overlay">
			<h2>🔒 <?php esc_html_e( 'Pro feature', 'pathfinder-link-repair' ); ?></h2>
			<p><?php esc_html_e( 'Upgrade to Pro to manage 301 redirects directly from WordPress.', 'pathfinder-link-repair' ); ?></p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php
	if ( isset( $_GET['delete'] ) && check_admin_referer( 'ablf_delete_redirect' ) ) {
		ABLF_Redirect::delete_redirect( absint( $_GET['delete'] ) );
		echo '<div class="notice notice-success ablf-notice"><p>' . esc_html__( 'Redirect deleted.', 'pathfinder-link-repair' ) . '</p></div>';
	}

	$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per_page = 20;
	$rows     = ABLF_Redirect::get_redirects( $page, $per_page );
	$total    = ABLF_Redirect::count_redirects();
	$pages    = max( 1, (int) ceil( $total / $per_page ) );
	?>

	<h2><?php esc_html_e( 'Add Redirect', 'pathfinder-link-repair' ); ?></h2>
	<form id="ablf-add-redirect-form" method="post" class="ablf-redirect-add">
		<input type="text" name="from_url" id="ablf-redirect-from" placeholder="/old-page-slug" required class="regular-text">
		<input type="text" name="to_url" id="ablf-redirect-to" placeholder="/new-page-slug" required class="regular-text">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Add', 'pathfinder-link-repair' ); ?></button>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'From URL', 'pathfinder-link-repair' ); ?></th>
				<th><?php esc_html_e( 'To URL', 'pathfinder-link-repair' ); ?></th>
				<th><?php esc_html_e( 'HTTP', 'pathfinder-link-repair' ); ?></th>
				<th><?php esc_html_e( 'Hits', 'pathfinder-link-repair' ); ?></th>
				<th><?php esc_html_e( 'Created', 'pathfinder-link-repair' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No redirects yet.', 'pathfinder-link-repair' ); ?></td></tr>
			<?php else : foreach ( $rows as $r ) :
			?>
				<tr>
					<td class="ablf-url"><?php echo esc_html( $r->from_url ); ?></td>
					<td class="ablf-url"><?php echo esc_html( $r->to_url ); ?></td>
					<td><?php echo esc_html( (int) $r->http_code ); ?></td>
					<td><?php echo esc_html( (int) $r->hit_count ); ?></td>
					<td><?php echo esc_html( $r->created_at ); ?></td>
					<td><button type="button" class="button-link-delete ablf-delete-redirect" data-id="<?php echo esc_attr( $r->id ); ?>"><?php esc_html_e( 'Delete', 'pathfinder-link-repair' ); ?></button></td>
				</tr>
			<?php endforeach; endif; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'total' => $pages, 'current' => $page ) ) ); ?>
			</div>
		</div>
	<?php endif; ?>
</div>
