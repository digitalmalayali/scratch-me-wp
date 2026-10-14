<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * @var array  $campaigns
 * @var int    $campaign_id
 * @var array  $leads
 * @var int    $total
 * @var string $view        'all' or 'trash'
 * @var int    $count_all
 * @var int    $count_trash
 * @var string $search
 * @var int    $paged
 * @var int    $total_pages
 */
$is_trash = ( 'trash' === $view );

$base_args = array( 'page' => 'scratch-win-leads' );
if ( $campaign_id ) {
	$base_args['campaign_id'] = $campaign_id;
}
$url_all   = add_query_arg( $base_args, admin_url( 'admin.php' ) );
$url_trash = add_query_arg( array_merge( $base_args, array( 'lead_status' => 'trash' ) ), admin_url( 'admin.php' ) );

/**
 * Build a nonce-protected link for a single-row action.
 */
$row_action_url = function( $do, $lead_id ) use ( $campaign_id, $is_trash ) {
	$args = array(
		'action'          => 'sw_lead_action',
		'do'              => $do,
		'ids'             => (int) $lead_id,
		'redirect_status' => $is_trash ? 'trash' : 'all',
	);
	if ( $campaign_id ) {
		$args['campaign_id'] = (int) $campaign_id;
	}
	return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'sw_lead_action' );
};

/**
 * Page links, e.g. « ‹ 2 of 7 › ».
 */
$pagination = function() use ( $paged, $total_pages, $total, $base_args, $is_trash, $search ) {
	$args = $base_args;
	if ( $is_trash ) {
		$args['lead_status'] = 'trash';
	}
	if ( '' !== $search ) {
		$args['s'] = $search;
	}
	?>
	<div class="tablenav-pages<?php echo $total_pages <= 1 ? ' one-page' : ''; ?>">
		<span class="displaying-num">
			<?php
			/* translators: %s: number of leads */
			echo esc_html( sprintf( _n( '%s item', '%s items', $total, 'scratch-win' ), number_format_i18n( $total ) ) );
			?>
		</span>
		<?php if ( $total_pages > 1 ) : ?>
			<span class="pagination-links">
				<?php
				$link = function( $page, $label, $text, $enabled ) use ( $args ) {
					if ( ! $enabled ) {
						printf( '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span> ', esc_html( $text ) );
						return;
					}
					printf(
						'<a class="button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a> ',
						esc_url( add_query_arg( array_merge( $args, array( 'paged' => $page ) ), admin_url( 'admin.php' ) ) ),
						esc_html( $label ),
						esc_html( $text )
					);
				};
				$link( 1, __( 'First page', 'scratch-win' ), '«', $paged > 1 );
				$link( max( 1, $paged - 1 ), __( 'Previous page', 'scratch-win' ), '‹', $paged > 1 );
				?>
				<span class="paging-input">
					<?php
					/* translators: 1: current page, 2: total pages */
					echo esc_html( sprintf( __( '%1$s of %2$s', 'scratch-win' ), number_format_i18n( $paged ), number_format_i18n( $total_pages ) ) );
					?>
				</span>
				<?php
				$link( min( $total_pages, $paged + 1 ), __( 'Next page', 'scratch-win' ), '›', $paged < $total_pages );
				$link( $total_pages, __( 'Last page', 'scratch-win' ), '»', $paged < $total_pages );
				?>
			</span>
		<?php endif; ?>
	</div>
	<?php
};

/**
 * Bulk actions dropdown + Apply button (rendered above and below the table).
 */
$bulk_actions = function( $which ) use ( $is_trash, $count_trash, $pagination ) {
	$name = ( 'top' === $which ) ? 'bulk_action' : 'bulk_action2';
	?>
	<div class="tablenav <?php echo esc_attr( $which ); ?>">
		<div class="alignleft actions bulkactions">
			<label for="sw-bulk-<?php echo esc_attr( $which ); ?>" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'scratch-win' ); ?></label>
			<select name="<?php echo esc_attr( $name ); ?>" id="sw-bulk-<?php echo esc_attr( $which ); ?>">
				<option value="-1"><?php esc_html_e( 'Bulk actions', 'scratch-win' ); ?></option>
				<?php if ( $is_trash ) : ?>
					<option value="restore"><?php esc_html_e( 'Restore', 'scratch-win' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete permanently', 'scratch-win' ); ?></option>
				<?php else : ?>
					<option value="trash"><?php esc_html_e( 'Move to Trash', 'scratch-win' ); ?></option>
				<?php endif; ?>
			</select>
			<input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'scratch-win' ); ?>">
		</div>
		<?php if ( $is_trash && $count_trash > 0 ) : ?>
			<div class="alignleft actions">
				<input type="submit" name="sw_empty_trash" class="button apply" value="<?php esc_attr_e( 'Empty Trash', 'scratch-win' ); ?>"
					onclick="return confirm('<?php echo esc_js( __( 'Permanently delete ALL leads in the Trash? This cannot be undone.', 'scratch-win' ) ); ?>');">
			</div>
		<?php endif; ?>
		<?php $pagination(); ?>
		<br class="clear">
	</div>
	<?php
};
?>
<div class="wrap sw-wrap">
	<h1><?php esc_html_e( 'Scratch & Win Leads', 'scratch-win' ); ?></h1>

	<ul class="subsubsub">
		<li class="all">
			<a href="<?php echo esc_url( $url_all ); ?>" class="<?php echo $is_trash ? '' : 'current'; ?>" <?php echo $is_trash ? '' : 'aria-current="page"'; ?>>
				<?php esc_html_e( 'All', 'scratch-win' ); ?> <span class="count">(<?php echo (int) $count_all; ?>)</span>
			</a><?php echo ( $count_trash || $is_trash ) ? ' |' : ''; ?>
		</li>
		<?php if ( $count_trash || $is_trash ) : ?>
		<li class="trash">
			<a href="<?php echo esc_url( $url_trash ); ?>" class="<?php echo $is_trash ? 'current' : ''; ?>" <?php echo $is_trash ? 'aria-current="page"' : ''; ?>>
				<?php esc_html_e( 'Trash', 'scratch-win' ); ?> <span class="count">(<?php echo (int) $count_trash; ?>)</span>
			</a>
		</li>
		<?php endif; ?>
	</ul>
	<br class="clear">

	<form method="get" class="sw-leads-toolbar">
		<input type="hidden" name="page" value="scratch-win-leads">
		<?php if ( $is_trash ) : ?>
			<input type="hidden" name="lead_status" value="trash">
		<?php endif; ?>
		<label for="campaign_id"><?php esc_html_e( 'Filter by campaign:', 'scratch-win' ); ?></label>
		<select name="campaign_id" id="campaign_id" onchange="this.form.submit()">
			<option value="0"><?php esc_html_e( 'All Campaigns', 'scratch-win' ); ?></option>
			<?php foreach ( $campaigns as $c ) : ?>
				<option value="<?php echo (int) $c->id; ?>" <?php selected( $campaign_id, $c->id ); ?>><?php echo esc_html( $c->title ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! $is_trash ) : ?>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sw_export_csv&campaign_id=' . $campaign_id ), 'sw_export_csv' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Export CSV', 'scratch-win' ); ?></a>
		<?php endif; ?>
		<span class="sw-search">
			<label class="screen-reader-text" for="sw-lead-search"><?php esc_html_e( 'Search leads', 'scratch-win' ); ?></label>
			<input type="search" id="sw-lead-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Phone, prize or coupon', 'scratch-win' ); ?>">
			<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'scratch-win' ); ?>">
		</span>
	</form>

	<p><?php
	if ( $is_trash ) {
		/* translators: %d: number of leads in the Trash */
		printf( esc_html__( 'Leads in Trash: %d', 'scratch-win' ), (int) $total );
	} else {
		/* translators: %d: total number of leads */
		printf( esc_html__( 'Total leads: %d', 'scratch-win' ), (int) $total );
	}
	if ( '' !== $search ) {
		/* translators: %s: search term */
		echo ' — ' . esc_html( sprintf( __( 'matching “%s”', 'scratch-win' ), $search ) );
	}
	?></p>

	<?php if ( $is_trash ) : ?>
		<p class="description"><?php esc_html_e( 'Numbers in the Trash still count as "already played". Delete them permanently to let those numbers play again.', 'scratch-win' ); ?></p>
	<?php endif; ?>

	<form method="post" id="sw-leads-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sw_leads_bulk">
		<input type="hidden" name="campaign_id" value="<?php echo (int) $campaign_id; ?>">
		<input type="hidden" name="lead_status" value="<?php echo $is_trash ? 'trash' : 'all'; ?>">
		<?php wp_nonce_field( 'sw_leads_bulk' ); ?>

		<?php $bulk_actions( 'top' ); ?>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<td id="cb" class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select All', 'scratch-win' ); ?></label>
						<input id="cb-select-all-1" type="checkbox">
					</td>
					<th class="column-primary"><?php esc_html_e( 'Phone Number', 'scratch-win' ); ?></th>
					<th><?php esc_html_e( 'Country', 'scratch-win' ); ?></th>
					<th><?php esc_html_e( 'Result', 'scratch-win' ); ?></th>
					<th><?php esc_html_e( 'Prize', 'scratch-win' ); ?></th>
					<th><?php esc_html_e( 'Coupon', 'scratch-win' ); ?></th>
					<th class="sw-col-answers"><?php esc_html_e( 'Answers', 'scratch-win' ); ?></th>
					<th><?php esc_html_e( 'Date', 'scratch-win' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $leads ) ) : ?>
				<tr><td colspan="8"><?php echo $is_trash ? esc_html__( 'No leads found in Trash.', 'scratch-win' ) : esc_html__( 'No leads captured yet.', 'scratch-win' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $leads as $lead ) : ?>
					<tr>
						<th scope="row" class="check-column">
							<label class="screen-reader-text" for="sw-lead-<?php echo (int) $lead->id; ?>"><?php echo esc_html( $lead->phone_e164 ); ?></label>
							<input type="checkbox" id="sw-lead-<?php echo (int) $lead->id; ?>" name="lead_ids[]" value="<?php echo (int) $lead->id; ?>">
						</th>
						<td class="column-primary">
							<strong><?php echo esc_html( $lead->phone_e164 ); ?></strong>
							<div class="row-actions">
								<?php if ( $is_trash ) : ?>
									<span class="untrash"><a href="<?php echo esc_url( $row_action_url( 'restore', $lead->id ) ); ?>"><?php esc_html_e( 'Restore', 'scratch-win' ); ?></a> | </span>
									<span class="delete"><a class="submitdelete" href="<?php echo esc_url( $row_action_url( 'delete', $lead->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this lead? This cannot be undone.', 'scratch-win' ) ); ?>');"><?php esc_html_e( 'Delete Permanently', 'scratch-win' ); ?></a></span>
								<?php else : ?>
									<span class="trash"><a class="submitdelete" href="<?php echo esc_url( $row_action_url( 'trash', $lead->id ) ); ?>"><?php esc_html_e( 'Trash', 'scratch-win' ); ?></a></span>
								<?php endif; ?>
							</div>
						</td>
						<td><?php echo esc_html( strtoupper( $lead->country_code ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $lead->result ) ); ?></td>
						<td><?php echo esc_html( $lead->prize_won ); ?></td>
						<td><?php echo 'win' === $lead->result ? esc_html( $lead->prize_coupon ?? '' ) : ''; ?></td>
						<td class="sw-col-answers">
							<?php foreach ( SW_Questions::lead_answers( $lead ) as $answer ) : ?>
								<div class="sw-answer"><strong><?php echo esc_html( wp_specialchars_decode( $answer['label'], ENT_QUOTES ) ); ?>:</strong> <?php echo esc_html( SW_Questions::value_text( $answer['value'] ) ); ?></div>
							<?php endforeach; ?>
						</td>
						<td><?php echo esc_html( SW_Settings::format_datetime( $lead->created_at ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
			<tfoot>
				<tr>
					<td class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="cb-select-all-2"><?php esc_html_e( 'Select All', 'scratch-win' ); ?></label>
						<input id="cb-select-all-2" type="checkbox">
					</td>
					<th class="column-primary"><?php esc_html_e( 'Phone Number', 'scratch-win' ); ?></th>
					<th><?php esc_html_e( 'Country', 'scratch-win' ); ?></th>
					<th><?php esc_html_e( 'Result', 'scratch-win' ); ?></th>
					<th><?php esc_html_e( 'Prize', 'scratch-win' ); ?></th>
					<th><?php esc_html_e( 'Coupon', 'scratch-win' ); ?></th>
					<th class="sw-col-answers"><?php esc_html_e( 'Answers', 'scratch-win' ); ?></th>
					<th><?php esc_html_e( 'Date', 'scratch-win' ); ?></th>
				</tr>
			</tfoot>
		</table>

		<?php $bulk_actions( 'bottom' ); ?>
	</form>
</div>
