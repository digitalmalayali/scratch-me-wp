<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** @var array $campaigns */
?>
<div class="wrap sw-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Scratch & Win Campaigns', 'scratch-win' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=scratch-win&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'scratch-win' ); ?></a>
	<hr class="wp-header-end">

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Title', 'scratch-win' ); ?></th>
				<th><?php esc_html_e( 'Shortcode', 'scratch-win' ); ?></th>
				<th><?php esc_html_e( 'Status', 'scratch-win' ); ?></th>
				<th><?php esc_html_e( 'Prizes', 'scratch-win' ); ?></th>
				<th><?php esc_html_e( 'Leads', 'scratch-win' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'scratch-win' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $campaigns ) ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No campaigns yet. Click "Add New" to create your first Scratch & Win campaign.', 'scratch-win' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $campaigns as $c ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $c->title ); ?></strong></td>
					<td><code>[scratch_win id="<?php echo (int) $c->id; ?>"]</code></td>
					<td><?php echo esc_html( ucfirst( $c->status ) ); ?></td>
					<td><?php echo (int) count( SW_Leads::get_prizes( $c->id ) ); ?></td>
					<td><?php echo (int) SW_Leads::count_leads( $c->id ); ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=scratch-win&action=edit&campaign_id=' . $c->id ) ); ?>"><?php esc_html_e( 'Edit', 'scratch-win' ); ?></a>
						|
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=scratch-win-leads&campaign_id=' . $c->id ) ); ?>"><?php esc_html_e( 'Leads', 'scratch-win' ); ?></a>
						|
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sw_delete_campaign&campaign_id=' . $c->id ), 'sw_delete_campaign' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this campaign and its prizes? Leads are kept.', 'scratch-win' ) ); ?>');" class="sw-delete-link"><?php esc_html_e( 'Delete', 'scratch-win' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
