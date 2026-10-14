<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * CRUD helpers for leads (phone captures) and campaigns/prizes.
 */
class SW_Leads {

	const PRIZE_TYPES = array( 'Common Coupon', 'Physical Product', 'Digital Reward', 'No Prize' );
	const STATUSES    = array( 'active', 'paused' );
	const TEMPLATES   = array( 'default', 'holiday', 'custom' );

	// -----------------------------------------------------------------
	// Phone numbers
	// -----------------------------------------------------------------

	/**
	 * Normalize visitor input to +91XXXXXXXXXX, or return false if it is not
	 * a valid Indian mobile number.
	 *
	 * Accepts "9876543210", "09876543210", "919876543210", "+91 98765 43210".
	 * The prefix is only stripped when the number is longer than 10 digits, so
	 * real numbers that start with 91 (e.g. 9123456789) are kept intact.
	 */
	public static function normalize_indian_mobile( $raw ) {
		$digits = preg_replace( '/\D/', '', (string) $raw );

		if ( 12 === strlen( $digits ) && 0 === strpos( $digits, '91' ) ) {
			$digits = substr( $digits, 2 );
		} elseif ( 11 === strlen( $digits ) && 0 === strpos( $digits, '0' ) ) {
			$digits = substr( $digits, 1 );
		}

		if ( ! preg_match( '/^[6-9]\d{9}$/', $digits ) ) {
			return false;
		}
		return '+91' . $digits;
	}

	// -----------------------------------------------------------------
	// Leads
	// -----------------------------------------------------------------

	/**
	 * Fetch a phone number's existing lead for this campaign, if any.
	 *
	 * Trashed leads are intentionally included: a number stays "already played"
	 * until its lead is permanently deleted.
	 */
	public static function get_lead_by_phone( $campaign_id, $phone_e164 ) {
		global $wpdb;
		$table = SW_DB::leads_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE campaign_id = %d AND phone_e164 = %s ORDER BY id DESC LIMIT 1",
				$campaign_id,
				$phone_e164
			)
		);
	}

	/**
	 * Insert a lead. Returns the new lead ID, or false if the insert failed
	 * (for example because the same number was submitted twice at once and
	 * the unique campaign+phone key rejected the second copy).
	 */
	public static function insert_lead( array $data ) {
		global $wpdb;
		$table = SW_DB::leads_table();

		$row = array(
			'campaign_id'       => absint( $data['campaign_id'] ),
			'phone_e164'        => sanitize_text_field( $data['phone_e164'] ),
			'country_code'      => sanitize_text_field( $data['country_code'] ?? '' ),
			'raw_input'         => sanitize_text_field( $data['raw_input'] ?? '' ),
			'prize_won'         => sanitize_text_field( $data['prize_won'] ?? '' ),
			'prize_coupon'      => sanitize_text_field( $data['prize_coupon'] ?? '' ),
			'prize_description' => sanitize_textarea_field( $data['prize_description'] ?? '' ),
			'prize_image'       => esc_url_raw( $data['prize_image'] ?? '' ),
			'result'            => ( 'win' === ( $data['result'] ?? '' ) ) ? 'win' : 'lose',
			'ip_address'        => sanitize_text_field( $data['ip_address'] ?? '' ),
			'user_agent'        => substr( sanitize_text_field( $data['user_agent'] ?? '' ), 0, 255 ),
			'created_at'        => current_time( 'mysql' ),
			'answers'           => ! empty( $data['answers'] ) && is_array( $data['answers'] ) ? wp_json_encode( $data['answers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : null,
		);
		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		// prize_id is nullable: only include it when there is one, so NULL is stored
		// instead of 0.
		if ( ! empty( $data['prize_id'] ) ) {
			$row['prize_id'] = absint( $data['prize_id'] );
			$formats[]       = '%d';
		}

		// A duplicate-key error here is expected under concurrency; don't print it.
		$suppress = $wpdb->suppress_errors( true );
		$ok       = $wpdb->insert( $table, $row, $formats );
		$wpdb->suppress_errors( $suppress );

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Build the WHERE clause for lead listings.
	 *
	 * @param int    $campaign_id 0 = all campaigns.
	 * @param string $status      'active' (not trashed) or 'trash'.
	 * @param string $search      Optional phone / prize search.
	 */
	private static function leads_where( $campaign_id, $status, $search = '' ) {
		global $wpdb;
		$where = ( 'trash' === $status ) ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
		if ( $campaign_id ) {
			$where .= $wpdb->prepare( ' AND campaign_id = %d', $campaign_id );
		}
		$search = trim( (string) $search );
		if ( '' !== $search ) {
			$like   = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= $wpdb->prepare( ' AND (phone_e164 LIKE %s OR prize_won LIKE %s OR prize_coupon LIKE %s OR answers LIKE %s)', $like, $like, $like, $like );
		}
		return $where;
	}

	public static function get_leads( $campaign_id = 0, $limit = 50, $offset = 0, $status = 'active', $search = '' ) {
		global $wpdb;
		$table = SW_DB::leads_table();
		$where = self::leads_where( $campaign_id, $status, $search );
		$order = ( 'trash' === $status ) ? 'deleted_at DESC, id DESC' : 'id DESC';

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d", $limit, $offset ) // phpcs:ignore
		);
	}

	public static function count_leads( $campaign_id = 0, $status = 'active', $search = '' ) {
		global $wpdb;
		$table = SW_DB::leads_table();
		$where = self::leads_where( $campaign_id, $status, $search );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore
	}

	/**
	 * Turn a list of IDs into a safe "1,2,3" string for an IN() clause.
	 */
	private static function ids_sql( array $ids ) {
		$ids = array_filter( array_map( 'absint', $ids ) );
		return $ids ? implode( ',', array_unique( $ids ) ) : '';
	}

	/**
	 * Move leads to the Trash. Trashed leads are hidden from the list and the
	 * CSV export, but the phone number still counts as "already played".
	 */
	public static function trash_leads( array $ids ) {
		global $wpdb;
		$in = self::ids_sql( $ids );
		if ( '' === $in ) {
			return 0;
		}
		$table = SW_DB::leads_table();
		return (int) $wpdb->query(
			$wpdb->prepare( "UPDATE {$table} SET deleted_at = %s WHERE id IN ({$in}) AND deleted_at IS NULL", current_time( 'mysql' ) ) // phpcs:ignore
		);
	}

	public static function restore_leads( array $ids ) {
		global $wpdb;
		$in = self::ids_sql( $ids );
		if ( '' === $in ) {
			return 0;
		}
		$table = SW_DB::leads_table();
		return (int) $wpdb->query( "UPDATE {$table} SET deleted_at = NULL WHERE id IN ({$in}) AND deleted_at IS NOT NULL" ); // phpcs:ignore
	}

	/**
	 * Permanently delete leads. Only leads already in the Trash can be deleted.
	 * After this the phone number can play again.
	 */
	public static function delete_leads_permanently( array $ids ) {
		global $wpdb;
		$in = self::ids_sql( $ids );
		if ( '' === $in ) {
			return 0;
		}
		$table = SW_DB::leads_table();
		return (int) $wpdb->query( "DELETE FROM {$table} WHERE id IN ({$in}) AND deleted_at IS NOT NULL" ); // phpcs:ignore
	}

	public static function empty_trash( $campaign_id = 0 ) {
		global $wpdb;
		$table = SW_DB::leads_table();
		$where = self::leads_where( $campaign_id, 'trash' );
		return (int) $wpdb->query( "DELETE FROM {$table} WHERE {$where}" ); // phpcs:ignore
	}

	// -----------------------------------------------------------------
	// Campaigns
	// -----------------------------------------------------------------

	public static function get_campaign( $campaign_id ) {
		global $wpdb;
		$table = SW_DB::campaigns_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $campaign_id ) );
	}

	public static function get_active_campaigns() {
		global $wpdb;
		$table = SW_DB::campaigns_table();
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'active' ORDER BY id DESC" );
	}

	public static function get_all_campaigns() {
		global $wpdb;
		$table = SW_DB::campaigns_table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
	}

	public static function save_campaign( array $data, $campaign_id = 0 ) {
		global $wpdb;
		$table = SW_DB::campaigns_table();

		$status   = sanitize_key( $data['status'] ?? 'active' );
		$template = sanitize_key( $data['template'] ?? 'default' );

		$fields = array(
			'title'                => sanitize_text_field( $data['title'] ?? '' ),
			'promo_text'           => sanitize_textarea_field( $data['promo_text'] ?? '' ),
			'template'             => in_array( $template, self::TEMPLATES, true ) ? $template : 'default',
			'background_image'     => esc_url_raw( $data['background_image'] ?? '' ),
			'scratch_layer_image'  => esc_url_raw( $data['scratch_layer_image'] ?? '' ),
			'status'               => in_array( $status, self::STATUSES, true ) ? $status : 'active',
			'show_footer_credit'   => ! empty( $data['show_footer_credit'] ) ? 1 : 0,
			'require_phone_before' => ! empty( $data['require_phone_before'] ) ? 1 : 0,
			'sold_out_mode'        => ( 'lose' === ( $data['sold_out_mode'] ?? '' ) ) ? 'lose' : 'redistribute',
			'win_message'          => sanitize_text_field( $data['win_message'] ?? '' ),
			'lose_message'         => sanitize_text_field( $data['lose_message'] ?? '' ),
			'already_won_message'  => sanitize_text_field( $data['already_won_message'] ?? '' ),
			'already_lost_message' => sanitize_text_field( $data['already_lost_message'] ?? '' ),
		);

		// Questions are only touched when the caller passes them.
		if ( array_key_exists( 'questions', $data ) ) {
			$fields['questions'] = wp_json_encode( SW_Questions::sanitize_questions( $data['questions'] ) );
		}

		if ( $campaign_id && self::get_campaign( $campaign_id ) ) {
			$wpdb->update( $table, $fields, array( 'id' => $campaign_id ) );
			return (int) $campaign_id;
		}

		$wpdb->insert( $table, $fields );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a campaign and its prizes. Leads are kept (they carry their own
	 * copy of the prize details).
	 */
	public static function delete_campaign( $campaign_id ) {
		global $wpdb;
		$wpdb->delete( SW_DB::campaigns_table(), array( 'id' => $campaign_id ), array( '%d' ) );
		$wpdb->delete( SW_DB::prizes_table(), array( 'campaign_id' => $campaign_id ), array( '%d' ) );
	}

	// -----------------------------------------------------------------
	// Prizes
	// -----------------------------------------------------------------

	public static function get_prizes( $campaign_id ) {
		global $wpdb;
		$table = SW_DB::prizes_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE campaign_id = %d ORDER BY sort_order ASC, id ASC", $campaign_id ) );
	}

	public static function get_prize( $prize_id ) {
		global $wpdb;
		$table = SW_DB::prizes_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $prize_id ) );
	}

	/**
	 * Save a campaign's prize list.
	 *
	 * Existing prizes are UPDATED in place (matched by id), new ones inserted,
	 * and prizes removed from the form deleted. Prize IDs therefore stay stable
	 * across saves, and each prize keeps its Winner(s) counter.
	 *
	 * Stock: if the Stock/Count total is unchanged the running remainder is
	 * kept. If the total changes, remaining = new total − winners so far
	 * (never below 0). Blank stock = unlimited.
	 */
	public static function save_prizes( $campaign_id, array $prizes ) {
		global $wpdb;
		$table = SW_DB::prizes_table();

		$existing_by_id = array();
		foreach ( self::get_prizes( $campaign_id ) as $existing ) {
			$existing_by_id[ (int) $existing->id ] = $existing;
		}

		$kept_ids = array();
		$sort     = 0;

		foreach ( $prizes as $prize ) {
			$label = sanitize_text_field( $prize['label'] ?? '' );
			if ( '' === $label ) {
				continue;
			}

			$prize_id = absint( $prize['id'] ?? 0 );
			$existing = ( $prize_id && isset( $existing_by_id[ $prize_id ] ) ) ? $existing_by_id[ $prize_id ] : null;

			$raw_stock   = trim( (string) ( $prize['stock_total'] ?? '' ) );
			$stock_total = ( '' === $raw_stock ) ? null : absint( $raw_stock );

			$winners_count = $existing ? (int) $existing->winners_count : 0;
			$old_total     = ( $existing && null !== $existing->stock_total ) ? (int) $existing->stock_total : null;

			if ( null === $stock_total ) {
				$stock_remaining = null;
			} elseif ( $existing && $old_total === $stock_total && null !== $existing->stock_remaining ) {
				$stock_remaining = (int) $existing->stock_remaining;
			} else {
				$stock_remaining = max( 0, $stock_total - $winners_count );
			}

			$type = sanitize_text_field( $prize['prize_type'] ?? 'Common Coupon' );
			if ( ! in_array( $type, self::PRIZE_TYPES, true ) ) {
				$type = 'Common Coupon';
			}
			// A "No Prize" row can never be a win.
			$is_winning = ( 'No Prize' === $type ) ? 0 : ( ! empty( $prize['is_winning'] ) ? 1 : 0 );

			$fields = array(
				'label'           => $label,
				'prize_type'      => $type,
				'coupon_code'     => sanitize_text_field( $prize['coupon_code'] ?? '' ),
				'description'     => sanitize_textarea_field( $prize['description'] ?? '' ),
				'image_url'       => esc_url_raw( $prize['image_url'] ?? '' ),
				'chance'          => round( max( 0, min( 100, (float) ( $prize['chance'] ?? 0 ) ) ), 2 ),
				'weight'          => 1,
				'is_winning'      => $is_winning,
				'is_active'       => ! empty( $prize['is_active'] ) ? 1 : 0,
				'stock_total'     => $stock_total,
				'stock_remaining' => $stock_remaining,
				'winners_count'   => $winners_count,
				'sort_order'      => $sort++,
			);

			if ( $existing ) {
				$wpdb->update( $table, $fields, array( 'id' => $prize_id, 'campaign_id' => $campaign_id ) );
				$kept_ids[] = $prize_id;
			} else {
				$fields['campaign_id'] = $campaign_id;
				$wpdb->insert( $table, $fields );
				$kept_ids[] = (int) $wpdb->insert_id;
			}
		}

		// Remove prizes that were deleted from the form.
		foreach ( array_keys( $existing_by_id ) as $old_id ) {
			if ( ! in_array( $old_id, $kept_ids, true ) ) {
				$wpdb->delete( $table, array( 'id' => $old_id, 'campaign_id' => $campaign_id ), array( '%d', '%d' ) );
			}
		}
	}

	/**
	 * Sum of Chance (%) over active winning prizes — the real overall win rate.
	 */
	public static function winning_chance_total( $campaign_id ) {
		$total = 0.0;
		foreach ( self::get_prizes( $campaign_id ) as $p ) {
			if ( (int) $p->is_active && (int) $p->is_winning ) {
				$total += (float) $p->chance;
			}
		}
		return round( $total, 2 );
	}

	/**
	 * Is this prize available to be drawn right now (active and in stock)?
	 */
	public static function prize_available( $p ) {
		if ( ! (int) $p->is_active ) {
			return false;
		}
		return ! ( null !== $p->stock_total && (int) $p->stock_remaining <= 0 );
	}

	/**
	 * The chance each winning prize REALLY has right now.
	 *
	 * The planned win rate is the sum of Chance (%) over all active winning
	 * prizes, including sold-out ones. In "redistribute" mode (default) the
	 * share of sold-out prizes is handed to the prizes that still have stock,
	 * in proportion to their own Chance, so the planned win rate is kept for
	 * as long as any prize is left. In "lose" mode a sold-out prize's share
	 * simply becomes a "No Prize" result.
	 *
	 * @param object $campaign    Campaign row.
	 * @param int[]  $exclude_ids Prize IDs to treat as sold out.
	 * @param array  $prizes      Optional pre-loaded prize rows.
	 * @return array prize_id => effective chance (0–100).
	 */
	public static function effective_chances( $campaign, array $exclude_ids = array(), $prizes = null ) {
		$prizes  = null === $prizes ? self::get_prizes( $campaign->id ) : $prizes;
		$planned = 0.0;
		$avail   = array();

		foreach ( $prizes as $p ) {
			if ( ! (int) $p->is_active || 1 !== (int) $p->is_winning ) {
				continue;
			}
			$planned += (float) $p->chance;
			if ( self::prize_available( $p ) && ! in_array( (int) $p->id, $exclude_ids, true ) ) {
				$avail[ (int) $p->id ] = (float) $p->chance;
			}
		}

		$planned   = min( 100.0, $planned );
		$available = array_sum( $avail );
		$mode      = isset( $campaign->sold_out_mode ) && 'lose' === $campaign->sold_out_mode ? 'lose' : 'redistribute';

		if ( 'redistribute' === $mode && $available > 0 && $available < $planned ) {
			$scale = $planned / $available;
			foreach ( $avail as $id => $chance ) {
				$avail[ $id ] = $chance * $scale;
			}
		}
		return $avail;
	}

	/**
	 * Pick a prize.
	 *
	 * A roll of 0.01–100.00 is compared against the winning prizes' cumulative
	 * effective chances (see effective_chances()); anything above that is a
	 * loss (a random active "No Prize" row, or a plain loss).
	 *
	 * @param object $campaign    Campaign row.
	 * @param int[]  $exclude_ids Prize IDs to skip (e.g. ones that just ran out).
	 */
	public static function draw_prize( $campaign, array $exclude_ids = array() ) {
		$prizes  = self::get_prizes( $campaign->id );
		$chances = self::effective_chances( $campaign, $exclude_ids, $prizes );

		$by_id  = array();
		$losing = array();
		foreach ( $prizes as $p ) {
			$by_id[ (int) $p->id ] = $p;
			if ( 0 === (int) $p->is_winning && self::prize_available( $p ) ) {
				$losing[] = $p;
			}
		}

		$roll       = wp_rand( 1, 10000 ) / 100; // 0.01–100.00
		$cumulative = 0.0;
		$chosen     = null;
		foreach ( $chances as $id => $chance ) {
			$cumulative += $chance;
			if ( $chance > 0 && $roll <= $cumulative + 0.000001 ) {
				$chosen = $by_id[ $id ];
				break;
			}
		}

		if ( ! $chosen && ! empty( $losing ) ) {
			$chosen = $losing[ array_rand( $losing ) ];
		}

		if ( ! $chosen ) {
			return array(
				'label'       => __( 'No Prize', 'scratch-win' ),
				'result'      => 'lose',
				'prize_id'    => 0,
				'coupon'      => '',
				'description' => '',
				'image'       => '',
			);
		}

		return array(
			'label'       => $chosen->label,
			'result'      => 1 === (int) $chosen->is_winning ? 'win' : 'lose',
			'prize_id'    => (int) $chosen->id,
			'coupon'      => (string) $chosen->coupon_code,
			'description' => (string) $chosen->description,
			'image'       => (string) $chosen->image_url,
		);
	}

	/**
	 * Atomically claim one unit of a prize: bumps winners_count and, for
	 * limited prizes, decrements stock_remaining — in ONE guarded UPDATE, so
	 * two simultaneous winners can never both get the last unit.
	 *
	 * @return bool True if the unit was claimed.
	 */
	public static function claim_prize_stock( $prize_id ) {
		global $wpdb;
		$table = SW_DB::prizes_table();

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET winners_count = winners_count + 1,
				     stock_remaining = IF(stock_total IS NULL, NULL, stock_remaining - 1)
				 WHERE id = %d AND is_active = 1 AND (stock_total IS NULL OR stock_remaining > 0)",
				$prize_id
			)
		);
		return 1 === (int) $updated;
	}

	/**
	 * Undo claim_prize_stock() when the lead could not be saved.
	 */
	public static function release_prize_stock( $prize_id ) {
		global $wpdb;
		$table = SW_DB::prizes_table();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET winners_count = IF(winners_count > 0, winners_count - 1, 0),
				     stock_remaining = IF(stock_total IS NULL, NULL, stock_remaining + 1)
				 WHERE id = %d",
				$prize_id
			)
		);
	}
}
