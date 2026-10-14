<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SW_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_sw_save_campaign', array( $this, 'handle_save_campaign' ) );
		add_action( 'admin_post_sw_delete_campaign', array( $this, 'handle_delete_campaign' ) );
		add_action( 'admin_post_sw_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_sw_export_csv', array( $this, 'handle_export_csv' ) );
		add_action( 'admin_post_sw_leads_bulk', array( $this, 'handle_leads_bulk' ) );
		add_action( 'admin_post_sw_lead_action', array( $this, 'handle_lead_action' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'Scratch & Win', 'scratch-win' ),
			__( 'Scratch & Win', 'scratch-win' ),
			'manage_options',
			'scratch-win',
			array( $this, 'render_campaigns_page' ),
			'dashicons-tickets-alt',
			26
		);

		add_submenu_page( 'scratch-win', __( 'Campaigns', 'scratch-win' ), __( 'Campaigns', 'scratch-win' ), 'manage_options', 'scratch-win', array( $this, 'render_campaigns_page' ) );
		add_submenu_page( 'scratch-win', __( 'Leads', 'scratch-win' ), __( 'Leads', 'scratch-win' ), 'manage_options', 'scratch-win-leads', array( $this, 'render_leads_page' ) );
		add_submenu_page( 'scratch-win', __( 'Settings', 'scratch-win' ), __( 'Settings', 'scratch-win' ), 'manage_options', 'scratch-win-settings', array( $this, 'render_settings_page' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'scratch-win' ) === false ) {
			return;
		}
		wp_enqueue_style( 'sw-admin-css', SW_PLUGIN_URL . 'assets/css/admin.css', array(), SW_VERSION );
		wp_enqueue_media();
		wp_enqueue_script( 'sw-admin-js', SW_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), SW_VERSION, true );
		wp_localize_script(
			'sw-admin-js',
			'SW_Admin',
			array(
				'maxInputVars' => (int) ini_get( 'max_input_vars' ),
				'i18n' => array(
					/* translators: 1: number of fields in the form, 2: server limit */
					'tooManyFields' => __( 'This campaign form has %1$d fields but your server accepts only %2$d (PHP max_input_vars). Saving now would lose data, so it was stopped. Ask your hosting provider to raise max_input_vars, or reduce the number of prizes or questions.', 'scratch-win' ),
					'mediaTitle'    => __( 'Select or Upload Image', 'scratch-win' ),
					'mediaButton'   => __( 'Use this image', 'scratch-win' ),
					'confirmDelete' => __( 'You are about to permanently delete the selected leads. This cannot be undone. Continue?', 'scratch-win' ),
					/* translators: %d: number of prizes in the file */
					'importReplace' => __( 'Replace the current prize rows with the %d prizes from this file? Click OK to replace them, or Cancel to add them below the existing rows.', 'scratch-win' ),
					/* translators: %d: number of prizes imported */
					'importDone'    => __( '%d prizes imported. Review them, then click Save Campaign.', 'scratch-win' ),
					'importEmpty'   => __( 'No prizes found in this file. Please use the sample CSV format.', 'scratch-win' ),
					'importError'   => __( 'Could not read this file. Please upload a .csv file.', 'scratch-win' ),
					/* translators: %s: question text */
					'deleteQuestion' => __( 'Delete the question "%s"?', 'scratch-win' ),
					'qNeedsLabel'    => __( 'Please type the question text, or delete this question.', 'scratch-win' ),
					'qNeedsOptions'  => __( 'Please add at least one option (one per line).', 'scratch-win' ),
					'qMinSelect'     => __( 'Min selections is more than the number of options.', 'scratch-win' ),
					'qMinMax'        => __( 'The minimum is larger than the maximum.', 'scratch-win' ),
				),
			)
		);
	}

	public function render_notices() {
		if ( ! isset( $_GET['page'] ) || strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'scratch-win' ) === false ) {
			return;
		}
		if ( isset( $_GET['sw_notice'] ) ) {
			$notice = sanitize_text_field( wp_unslash( $_GET['sw_notice'] ) );
			$map = array(
				'saved'   => array( 'success', __( 'Saved successfully.', 'scratch-win' ) ),
				'deleted' => array( 'success', __( 'Campaign deleted.', 'scratch-win' ) ),
			);
			if ( isset( $map[ $notice ] ) ) {
				printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $notice ][0] ), esc_html( $map[ $notice ][1] ) );
			}
			if ( 'saved' === $notice && isset( $_GET['sw_win_total'] ) ) {
				$win_total = (float) sanitize_text_field( wp_unslash( $_GET['sw_win_total'] ) );
				if ( $win_total > 100 ) {
					/* translators: %s: total chance of all winning prizes */
					$text = sprintf( __( 'Your winning prizes add up to %s%%. Only the first 100%% can ever be drawn, so prizes lower in the list may never be won. Lower their Chance (%%) values.', 'scratch-win' ), number_format_i18n( $win_total, 2 ) );
					printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $text ) );
				} elseif ( $win_total <= 0 ) {
					printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'This campaign has no active winning prize with a Chance above 0%, so every visitor will lose.', 'scratch-win' ) );
				}
			}
			$this->render_lead_notice( $notice );
		}
	}

	/**
	 * Notices for Trash actions on the Leads page, e.g.
	 * "2 leads moved to the Trash. Undo".
	 */
	private function render_lead_notice( $notice ) {
		$count = isset( $_GET['sw_count'] ) ? absint( $_GET['sw_count'] ) : 0;

		switch ( $notice ) {
			case 'leads_trashed':
				/* translators: %s: number of leads */
				$text = sprintf( _n( '%s lead moved to the Trash.', '%s leads moved to the Trash.', $count, 'scratch-win' ), number_format_i18n( $count ) );
				$undo = '';
				$ids  = isset( $_GET['sw_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['sw_ids'] ) ) : '';
				if ( $ids && preg_match( '/^[0-9,]+$/', $ids ) ) {
					$undo_url = wp_nonce_url(
						admin_url( 'admin-post.php?action=sw_lead_action&do=restore&ids=' . $ids . '&redirect_status=all' ),
						'sw_lead_action'
					);
					$undo = ' <a href="' . esc_url( $undo_url ) . '">' . esc_html__( 'Undo', 'scratch-win' ) . '</a>';
				}
				printf( '<div class="notice notice-success is-dismissible"><p>%s%s</p></div>', esc_html( $text ), $undo ); // phpcs:ignore
				return;
			case 'leads_restored':
				/* translators: %s: number of leads */
				$text = sprintf( _n( '%s lead restored from the Trash.', '%s leads restored from the Trash.', $count, 'scratch-win' ), number_format_i18n( $count ) );
				break;
			case 'leads_deleted':
				/* translators: %s: number of leads */
				$text = sprintf( _n( '%s lead permanently deleted.', '%s leads permanently deleted.', $count, 'scratch-win' ), number_format_i18n( $count ) );
				break;
			case 'leads_none':
				printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html__( 'Please select at least one lead and an action.', 'scratch-win' ) );
				return;
			default:
				return;
		}
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $text ) );
	}

	// -----------------------------------------------------------------
	// Campaigns list + editor
	// -----------------------------------------------------------------
	public function render_campaigns_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		$editing_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
		$action     = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'edit' === $action || 'new' === $action ) {
			$this->render_campaign_editor( $editing_id );
			return;
		}

		$campaigns = SW_Leads::get_all_campaigns();
		include SW_PLUGIN_DIR . 'admin/views/campaigns-list.php';
	}

	private function render_campaign_editor( $campaign_id ) {
		$campaign = $campaign_id ? SW_Leads::get_campaign( $campaign_id ) : null;
		$prizes   = $campaign_id ? SW_Leads::get_prizes( $campaign_id ) : array();
		include SW_PLUGIN_DIR . 'admin/views/campaign-editor.php';
	}

	public function handle_save_campaign() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sw_save_campaign' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'scratch-win' ) );
		}

		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0;

		// The editor's very last field. If it is missing, PHP cut the form short
		// (max_input_vars) and saving would silently delete the prizes and
		// questions that were dropped — so save nothing and explain.
		if ( empty( $_POST['sw_form_end'] ) ) {
			$limit = (int) ini_get( 'max_input_vars' );
			wp_die(
				'<h1>' . esc_html__( 'Nothing was saved', 'scratch-win' ) . '</h1><p>' .
				esc_html(
					sprintf(
						/* translators: %d: PHP max_input_vars value */
						__( 'Your server only accepted part of this form (its PHP "max_input_vars" limit is %d fields), so the campaign was NOT saved and your existing prizes and questions are unchanged. Ask your hosting provider to raise max_input_vars (e.g. to 3000), or reduce the number of prizes or questions.', 'scratch-win' ),
						$limit
					)
				) . '</p><p><a href="javascript:history.back()">' . esc_html__( '← Go back', 'scratch-win' ) . '</a></p>',
				esc_html__( 'Form too large', 'scratch-win' ),
				array( 'response' => 413 )
			);
		}

		$data = array(
			'title'                => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'promo_text'           => sanitize_textarea_field( wp_unslash( $_POST['promo_text'] ?? '' ) ),
			'template'             => sanitize_text_field( wp_unslash( $_POST['template'] ?? 'default' ) ),
			'background_image'     => esc_url_raw( wp_unslash( $_POST['background_image'] ?? '' ) ),
			'scratch_layer_image'  => esc_url_raw( wp_unslash( $_POST['scratch_layer_image'] ?? '' ) ),
			'status'               => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
			'show_footer_credit'   => isset( $_POST['show_footer_credit'] ) ? 1 : 0,
			'require_phone_before' => isset( $_POST['require_phone_before'] ) ? 1 : 0,
			'sold_out_mode'        => sanitize_key( wp_unslash( $_POST['sold_out_mode'] ?? 'redistribute' ) ),
			'win_message'          => sanitize_text_field( wp_unslash( $_POST['win_message'] ?? '' ) ),
			'lose_message'         => sanitize_text_field( wp_unslash( $_POST['lose_message'] ?? '' ) ),
			'already_won_message'  => sanitize_text_field( wp_unslash( $_POST['already_won_message'] ?? '' ) ),
			'already_lost_message' => sanitize_text_field( wp_unslash( $_POST['already_lost_message'] ?? '' ) ),
		);

		// Questions post as questions[<card key>][field]; card order = display order.
		$data['questions'] = isset( $_POST['questions'] ) && is_array( $_POST['questions'] ) ? array_values( wp_unslash( $_POST['questions'] ) ) : array(); // phpcs:ignore -- sanitized in SW_Questions::sanitize_questions().

		$campaign_id = SW_Leads::save_campaign( $data, $campaign_id );

		// Each prize row posts as prizes[<row key>][field], so a row's checkboxes
		// always stay with that row (unticked boxes are simply missing).
		$prizes = array();
		$rows   = isset( $_POST['prizes'] ) && is_array( $_POST['prizes'] ) ? wp_unslash( $_POST['prizes'] ) : array(); // phpcs:ignore -- sanitized in SW_Leads::save_prizes().
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || '' === trim( (string) ( $row['label'] ?? '' ) ) ) {
				continue;
			}
			$prizes[] = array(
				'id'          => $row['id'] ?? 0,
				'label'       => (string) $row['label'],
				'prize_type'  => (string) ( $row['prize_type'] ?? 'Common Coupon' ),
				'coupon_code' => (string) ( $row['coupon_code'] ?? '' ),
				'description' => (string) ( $row['description'] ?? '' ),
				'image_url'   => (string) ( $row['image_url'] ?? '' ),
				'chance'      => $row['chance'] ?? 0,
				'stock_total' => (string) ( $row['stock_total'] ?? '' ),
				'is_active'   => ! empty( $row['is_active'] ) ? 1 : 0,
				'is_winning'  => ! empty( $row['is_winning'] ) ? 1 : 0,
			);
		}
		SW_Leads::save_prizes( $campaign_id, $prizes );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'scratch-win',
					'action'       => 'edit',
					'campaign_id'  => $campaign_id,
					'sw_notice'    => 'saved',
					'sw_win_total' => SW_Leads::winning_chance_total( $campaign_id ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function handle_delete_campaign() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sw_delete_campaign' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'scratch-win' ) );
		}
		$campaign_id = absint( $_GET['campaign_id'] ?? 0 );
		if ( $campaign_id ) {
			SW_Leads::delete_campaign( $campaign_id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=scratch-win&sw_notice=deleted' ) );
		exit;
	}

	// -----------------------------------------------------------------
	// Leads
	// -----------------------------------------------------------------
	public function render_leads_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		$campaigns   = SW_Leads::get_all_campaigns();
		$campaign_id = isset( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
		$view        = ( isset( $_GET['lead_status'] ) && 'trash' === $_GET['lead_status'] ) ? 'trash' : 'all';
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$per_page    = 50;
		$status      = 'trash' === $view ? 'trash' : 'active';
		$count_all   = SW_Leads::count_leads( $campaign_id, 'active' );
		$count_trash = SW_Leads::count_leads( $campaign_id, 'trash' );
		$total       = SW_Leads::count_leads( $campaign_id, $status, $search );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$paged       = isset( $_GET['paged'] ) ? min( $total_pages, max( 1, absint( $_GET['paged'] ) ) ) : 1;
		$leads       = SW_Leads::get_leads( $campaign_id, $per_page, ( $paged - 1 ) * $per_page, $status, $search );

		include SW_PLUGIN_DIR . 'admin/views/leads-list.php';
	}

	/**
	 * Bulk actions + "Empty Trash" button from the Leads table (POST).
	 */
	public function handle_leads_bulk() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sw_leads_bulk' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'scratch-win' ) );
		}

		$campaign_id = absint( $_POST['campaign_id'] ?? 0 );
		$status      = ( 'trash' === ( $_POST['lead_status'] ?? '' ) ) ? 'trash' : 'all';
		$ids         = array_map( 'absint', (array) wp_unslash( $_POST['lead_ids'] ?? array() ) );

		if ( isset( $_POST['sw_empty_trash'] ) ) {
			$count = SW_Leads::empty_trash( $campaign_id );
			$this->redirect_leads( $campaign_id, 'trash', 'leads_deleted', $count );
		}

		// Top and bottom dropdowns, like WordPress: use whichever one is set.
		$bulk = sanitize_key( wp_unslash( $_POST['bulk_action'] ?? '-1' ) );
		if ( '-1' === $bulk || '' === $bulk ) {
			$bulk = sanitize_key( wp_unslash( $_POST['bulk_action2'] ?? '-1' ) );
		}

		$this->run_lead_action( $bulk, $ids, $campaign_id, $status );
	}

	/**
	 * Single-row links (Trash / Restore / Delete Permanently) and the "Undo" link (GET).
	 */
	public function handle_lead_action() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sw_lead_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'scratch-win' ) );
		}

		$do          = sanitize_key( wp_unslash( $_GET['do'] ?? '' ) );
		$ids         = array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['ids'] ?? '' ) ) ) );
		$campaign_id = absint( $_GET['campaign_id'] ?? 0 );
		$status      = ( 'trash' === ( $_GET['redirect_status'] ?? '' ) ) ? 'trash' : 'all';

		$this->run_lead_action( $do, $ids, $campaign_id, $status );
	}

	private function run_lead_action( $do, array $ids, $campaign_id, $status ) {
		$ids = array_values( array_filter( $ids ) );
		if ( empty( $ids ) ) {
			$this->redirect_leads( $campaign_id, $status, 'leads_none', 0 );
		}

		switch ( $do ) {
			case 'trash':
				$count = SW_Leads::trash_leads( $ids );
				$this->redirect_leads( $campaign_id, $status, 'leads_trashed', $count, $ids );
				break;
			case 'restore':
				$count = SW_Leads::restore_leads( $ids );
				$this->redirect_leads( $campaign_id, $status, 'leads_restored', $count );
				break;
			case 'delete':
				$count = SW_Leads::delete_leads_permanently( $ids );
				$this->redirect_leads( $campaign_id, $status, 'leads_deleted', $count );
				break;
			default:
				$this->redirect_leads( $campaign_id, $status, 'leads_none', 0 );
		}
	}

	private function redirect_leads( $campaign_id, $status, $notice, $count, array $ids = array() ) {
		$args = array(
			'page'      => 'scratch-win-leads',
			'sw_notice' => $notice,
			'sw_count'  => (int) $count,
		);
		if ( $campaign_id ) {
			$args['campaign_id'] = (int) $campaign_id;
		}
		if ( 'trash' === $status ) {
			$args['lead_status'] = 'trash';
		}
		if ( $ids ) {
			$args['sw_ids'] = implode( ',', array_map( 'absint', $ids ) );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_export_csv() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sw_export_csv' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'scratch-win' ) );
		}
		$campaign_id = absint( $_GET['campaign_id'] ?? 0 );
		SW_Export::export_csv( $campaign_id );
	}

	// -----------------------------------------------------------------
	// Settings
	// -----------------------------------------------------------------
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$settings = SW_Settings::get_all();
		include SW_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'sw_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'scratch-win' ) );
		}

		$timezone    = sanitize_text_field( wp_unslash( $_POST['timezone'] ?? '' ) );
		$date_format = sanitize_text_field( wp_unslash( $_POST['date_format'] ?? 'DD/MM/YYYY' ) );

		$data = array(
			'business_name'       => sanitize_text_field( wp_unslash( $_POST['business_name'] ?? '' ) ),
			'business_url'        => esc_url_raw( wp_unslash( $_POST['business_url'] ?? '' ) ),
			'admin_email'         => sanitize_email( wp_unslash( $_POST['admin_email'] ?? '' ) ),
			'timezone'            => SW_Settings::is_valid_timezone( $timezone ) ? $timezone : wp_timezone_string(),
			'date_format'         => in_array( $date_format, array( 'MM/DD/YYYY', 'DD/MM/YYYY' ), true ) ? $date_format : 'DD/MM/YYYY',
			'default_country'     => 'in',
			'notify_admin'        => isset( $_POST['notify_admin'] ) ? 1 : 0,
			'rate_limit'          => min( 10000, absint( $_POST['rate_limit'] ?? 20 ) ),
			'trust_proxy'         => isset( $_POST['trust_proxy'] ) ? 1 : 0,
			'delete_on_uninstall' => isset( $_POST['delete_on_uninstall'] ) ? 1 : 0,
		);

		SW_Settings::update( $data );

		wp_safe_redirect( admin_url( 'admin.php?page=scratch-win-settings&sw_notice=saved' ) );
		exit;
	}
}
