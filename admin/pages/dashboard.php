<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats          = ABLF_DB_Handler::get_dashboard_stats();
$current_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'broken';
$allowed        = array( 'all', 'broken', 'fixed', 'ignored', 'allowlist' );
if ( ! in_array( $current_filter, $allowed, true ) ) {
	$current_filter = 'broken';
}

$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$per_page = 20;

// Only query broken links when not on the Allowlist tab.
if ( 'allowlist' !== $current_filter ) {
	$filters = array( 'status' => $current_filter );
	$links   = ABLF_DB_Handler::get_broken_links( $filters, $page, $per_page );
	$total   = ABLF_DB_Handler::count_broken_links( $filters );
	$pages   = max( 1, (int) ceil( $total / $per_page ) );
} else {
	$links = array();
	$total = 0;
	$pages = 1;
	$allowlist_items = ABLF_DB_Handler::get_allowlist();
}

$last_scan = get_option( 'ablf_last_scan_at', '' );
$progress  = ABLF_DB_Handler::queue_progress();

$tier                 = class_exists( 'ABLF_License' ) ? ABLF_License::get_tier() : 'free';
$monthly_limit        = class_exists( 'ABLF_License' ) ? ABLF_License::get_monthly_limit() : 100;
$credits_remaining    = class_exists( 'ABLF_License' ) ? ABLF_License::get_credits_remaining() : $monthly_limit;
$total_available      = class_exists( 'ABLF_License' ) ? ABLF_License::get_total_credits_available() : $monthly_limit;
$low_credit_threshold = (int) floor( $monthly_limit * 0.20 );
$show_low_credit      = ( $credits_remaining < $low_credit_threshold );
?>
<div class="wrap ablf-wrap">
	<h1><?php esc_html_e( 'Broken Links Dashboard', 'pathfinder-link-repair' ); ?></h1>

	<div class="ablf-stats-grid">
		<div class="ablf-stat-card">
			<span class="ablf-stat-label"><?php esc_html_e( 'Broken', 'pathfinder-link-repair' ); ?></span>
			<span class="ablf-stat-value"><?php echo esc_html( (int) $stats['total_broken'] ); ?></span>
		</div>
		<div class="ablf-stat-card">
			<span class="ablf-stat-label"><?php esc_html_e( 'Fixed This Month', 'pathfinder-link-repair' ); ?></span>
			<span class="ablf-stat-value"><?php echo esc_html( (int) $stats['fixed_this_month'] ); ?></span>
		</div>
		<div class="ablf-stat-card">
			<span class="ablf-stat-label"><?php esc_html_e( 'Ignored', 'pathfinder-link-repair' ); ?></span>
			<span class="ablf-stat-value"><?php echo esc_html( (int) $stats['ignored'] ); ?></span>
		</div>
		<div class="ablf-stat-card">
			<span class="ablf-stat-label"><?php esc_html_e( 'Credits Remaining', 'pathfinder-link-repair' ); ?></span>
			<span class="ablf-stat-value"><?php echo esc_html( number_format_i18n( $total_available ) ); ?></span>
		</div>
	</div>

	<?php if ( $show_low_credit ) : ?>
		<div class="notice notice-warning ablf-low-credit-notice">
			<p>
				<?php
				printf(
					/* translators: %s: number of credits remaining */
					esc_html__( 'Running low on credits — %s credits remaining this month.', 'pathfinder-link-repair' ),
					'<strong>' . esc_html( number_format_i18n( $credits_remaining ) ) . '</strong>'
				); ?>
				&nbsp;<a href="<?php echo esc_url( ABLF_URL_CREDITS_500 ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Buy Credits', 'pathfinder-link-repair' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<div class="ablf-scan-controls">
		<button type="button" class="button button-primary" id="ablf-start-scan"><?php esc_html_e( 'Scan Now', 'pathfinder-link-repair' ); ?></button>
		<span class="ablf-last-scan">
			<?php if ( $last_scan ) : ?>
				<?php
				/* translators: %s: date and time of last scan */
				printf( esc_html__( 'Last scan: %s', 'pathfinder-link-repair' ), esc_html( $last_scan ) ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Last scan: Never', 'pathfinder-link-repair' ); ?>
			<?php endif; ?>
		</span>
		<div class="ablf-progress" id="ablf-scan-progress" style="display:<?php echo ( (int) $progress['queued'] > 0 || (int) $progress['processing'] > 0 ) ? 'block' : 'none'; ?>;">
			<div class="ablf-progress-bar"><span class="ablf-progress-fill" style="width:<?php echo esc_attr( $progress['percent'] ); ?>%;"></span><span class="ablf-progress-percent"><?php echo esc_html( (int) $progress['percent'] ); ?>%</span></div>
			<div class="ablf-progress-label"></div>
		</div>
	</div>

	<ul class="subsubsub ablf-filters">
		<?php foreach ( $allowed as $slug ) :
			$tab_url = add_query_arg( array( 'page' => 'ablf-dashboard', 'status' => $slug ), admin_url( 'admin.php' ) );
			if ( 'allowlist' === $slug ) {
				$count = ABLF_DB_Handler::count_allowlist();
			} elseif ( 'all' === $slug ) {
				$count = ABLF_DB_Handler::count_broken_links();
			} else {
				$count = ABLF_DB_Handler::count_broken_links( array( 'status' => $slug ) );
			}
			$cls = ( $current_filter === $slug ) ? 'current' : '';
		?>
			<li>
				<a href="<?php echo esc_url( $tab_url ); ?>" class="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( ucfirst( $slug ) ); ?> <span class="count">(<?php echo esc_html( $count ); ?>)</span></a>
				<?php if ( $slug !== end( $allowed ) ) echo ' | '; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="ablf-bulk-bar">
		<select id="ablf-bulk-action">
			<option value=""><?php esc_html_e( 'Bulk actions', 'pathfinder-link-repair' ); ?></option>
			<?php if ( 'broken' === $current_filter || 'all' === $current_filter ) : ?>
				<option value="ignore"><?php esc_html_e( 'Ignore Selected', 'pathfinder-link-repair' ); ?></option>
				<option value="ask_pathfinder"><?php esc_html_e( 'Ask Pathfinder for Selected', 'pathfinder-link-repair' ); ?></option>
				<option value="add_to_allowlist"><?php esc_html_e( 'Add to Allowlist', 'pathfinder-link-repair' ); ?></option>
			<?php endif; ?>
			<?php if ( 'ignored' === $current_filter ) : ?>
				<option value="restore"><?php esc_html_e( 'Restore Selected', 'pathfinder-link-repair' ); ?></option>
			<?php endif; ?>
		</select>
		<button type="button" class="button" id="ablf-bulk-apply"><?php esc_html_e( 'Apply', 'pathfinder-link-repair' ); ?></button>
		<span class="ablf-bulk-count"></span>
	</div>

	<?php if ( 'allowlist' === $current_filter ) : ?>

		<div class="ablf-allowlist-section">
			<div class="ablf-allowlist-add">
				<input type="text" id="ablf-allowlist-pattern" placeholder="<?php esc_attr_e( 'e.g. example.com or https://example.com/page', 'pathfinder-link-repair' ); ?>" class="regular-text">
				<select id="ablf-allowlist-type">
					<option value="domain"><?php esc_html_e( 'Domain', 'pathfinder-link-repair' ); ?></option>
					<option value="url"><?php esc_html_e( 'URL', 'pathfinder-link-repair' ); ?></option>
				</select>
				<input type="text" id="ablf-allowlist-note" placeholder="<?php esc_attr_e( 'Note (optional)', 'pathfinder-link-repair' ); ?>" class="regular-text">
				<button type="button" class="button button-primary" id="ablf-allowlist-add-btn"><?php esc_html_e( 'Add', 'pathfinder-link-repair' ); ?></button>
				<span class="ablf-allowlist-feedback"></span>
			</div>

			<table class="widefat striped ablf-allowlist-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Pattern', 'pathfinder-link-repair' ); ?></th>
						<th><?php esc_html_e( 'Type', 'pathfinder-link-repair' ); ?></th>
						<th><?php esc_html_e( 'Note', 'pathfinder-link-repair' ); ?></th>
						<th><?php esc_html_e( 'Added', 'pathfinder-link-repair' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'pathfinder-link-repair' ); ?></th>
					</tr>
				</thead>
				<tbody id="ablf-allowlist-tbody">
					<?php if ( empty( $allowlist_items ) ) : ?>
						<tr class="ablf-allowlist-empty"><td colspan="5"><?php esc_html_e( 'No allowlist entries yet. Add URLs or domains to prevent them from being flagged as broken.', 'pathfinder-link-repair' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $allowlist_items as $item ) : ?>
							<tr class="ablf-allowlist-row" data-id="<?php echo esc_attr( $item->id ); ?>">
								<td><code><?php echo esc_html( $item->pattern ); ?></code></td>
								<td><?php echo esc_html( ucfirst( $item->pattern_type ) ); ?></td>
								<td><?php echo esc_html( $item->note ); ?></td>
								<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $item->created_at ) ) ); ?></td>
								<td><button type="button" class="button ablf-allowlist-remove" data-id="<?php echo esc_attr( $item->id ); ?>"><?php esc_html_e( 'Remove', 'pathfinder-link-repair' ); ?></button></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

	<?php else : ?>

		<table class="widefat striped ablf-links-table">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="ablf-select-all"></th>
					<th><?php esc_html_e( 'Source Page', 'pathfinder-link-repair' ); ?></th>
					<th><?php esc_html_e( 'Broken URL', 'pathfinder-link-repair' ); ?></th>
					<th><?php esc_html_e( 'Anchor', 'pathfinder-link-repair' ); ?></th>
					<th><?php esc_html_e( 'HTTP', 'pathfinder-link-repair' ); ?></th>
					<th><?php esc_html_e( 'First Found', 'pathfinder-link-repair' ); ?></th>
					<th><?php esc_html_e( 'Status', 'pathfinder-link-repair' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'pathfinder-link-repair' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $links ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No broken links found. Run a scan to get started.', 'pathfinder-link-repair' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $links as $link ) :
						$post_title = get_the_title( (int) $link->source_post_id );
						$post_edit  = get_edit_post_link( (int) $link->source_post_id );
					?>
						<tr class="ablf-row" data-id="<?php echo esc_attr( $link->id ); ?>">
							<td><input type="checkbox" class="ablf-row-check" value="<?php echo esc_attr( $link->id ); ?>"></td>
							<td><?php if ( $post_edit ) : ?><a href="<?php echo esc_url( $post_edit ); ?>" target="_blank"><?php echo esc_html( $post_title ? $post_title : '#' . $link->source_post_id ); ?></a><?php else : ?><?php echo esc_html( $post_title ); ?><?php endif; ?></td>
							<td class="ablf-url col-url" title="<?php echo esc_attr( $link->broken_url ); ?>"><a href="<?php echo esc_url( $link->broken_url ); ?>" target="_blank" rel="noopener noreferrer" class="ablf-url-link"><?php echo esc_html( $link->broken_url ); ?></a></td>
							<td class="col-anchor" title="<?php echo esc_attr( $link->anchor_text ); ?>"><?php echo esc_html( $link->anchor_text ); ?></td>
							<td><?php echo esc_html( (int) $link->http_status ); ?></td>
							<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $link->first_found_at ) ) ); ?></td>
							<td><span class="ablf-status ablf-status-<?php echo esc_attr( $link->status ); ?>"><?php echo esc_html( $link->status ); ?></span></td>
							<td class="ablf-actions">
								<?php if ( 'broken' === $link->status ) : ?>
									<button type="button" class="button button-primary ablf-ask-pathfinder" data-id="<?php echo esc_attr( $link->id ); ?>"><?php esc_html_e( 'Ask Pathfinder', 'pathfinder-link-repair' ); ?></button>
									<button type="button" class="button ablf-ignore" data-id="<?php echo esc_attr( $link->id ); ?>"><?php esc_html_e( 'Ignore', 'pathfinder-link-repair' ); ?></button>
								<?php elseif ( 'ignored' === $link->status ) : ?>
									<button type="button" class="button ablf-restore" data-id="<?php echo esc_attr( $link->id ); ?>"><?php esc_html_e( 'Restore', 'pathfinder-link-repair' ); ?></button>
								<?php elseif ( 'fixed' === $link->status ) : ?>
									<button type="button" class="button ablf-reopen" data-id="<?php echo esc_attr( $link->id ); ?>"><?php esc_html_e( 'Mark as Broken', 'pathfinder-link-repair' ); ?></button>
								<?php else : ?>
									<span class="description"><?php echo esc_html( $link->status ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr class="ablf-suggestion-row" data-for="<?php echo esc_attr( $link->id ); ?>" style="display:none;">
							<td colspan="8" class="ablf-suggestion-cell"></td>
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

	<?php endif; ?>
</div>
