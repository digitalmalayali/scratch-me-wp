<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SW_Export {

	const BATCH = 1000;

	/**
	 * Stream a CSV of leads (not in the Trash) to the browser and exit.
	 * Rows are read in batches so large lists don't run out of memory.
	 */
	public static function export_csv( $campaign_id = 0 ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'scratch-win' ) );
		}

		global $wpdb;
		$table = SW_DB::leads_table();
		$where = 'deleted_at IS NULL';
		if ( $campaign_id ) {
			$where .= $wpdb->prepare( ' AND campaign_id = %d', $campaign_id );
		}

		$filename = 'scratch-win-leads-' . ( $campaign_id ? 'campaign-' . (int) $campaign_id . '-' : '' ) . gmdate( 'Y-m-d' ) . '.csv';

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore
		}
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		fwrite( $output, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel shows ₹ and Hindi text correctly.
		// One column per question when exporting a single campaign; answers to
		// questions that were since deleted go in "Other answers". For an
		// all-campaigns export, answers share one "Answers" column.
		$question_cols = array();
		if ( $campaign_id ) {
			foreach ( SW_Questions::for_campaign( SW_Leads::get_campaign( $campaign_id ) ) as $q ) {
				$question_cols[ $q['id'] ] = wp_specialchars_decode( $q['label'], ENT_QUOTES );
			}
		}

		$header = array( 'ID', 'Campaign ID', 'Phone Number', 'Country Code', 'Prize Won', 'Coupon Code', 'Result', 'Date/Time', 'IP Address' );
		if ( $campaign_id ) {
			foreach ( $question_cols as $label ) {
				$header[] = self::safe_cell( $label );
			}
			$header[] = 'Other answers';
		} else {
			$header[] = 'Answers';
		}
		self::put_row( $output, $header );

		$last_id = PHP_INT_MAX;
		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} AND id < %d ORDER BY id DESC LIMIT %d", $last_id, self::BATCH ) // phpcs:ignore
			);
			foreach ( $rows as $row ) {
				$answers = SW_Questions::lead_answers( $row );
				$extra   = array();
				if ( $campaign_id ) {
					$by_id = array();
					$other = array();
					foreach ( $answers as $a ) {
						if ( isset( $question_cols[ $a['id'] ] ) ) {
							$by_id[ $a['id'] ] = SW_Questions::value_text( $a['value'] );
						} else {
							$other[] = $a;
						}
					}
					foreach ( array_keys( $question_cols ) as $qid ) {
						$extra[] = self::safe_cell( $by_id[ $qid ] ?? '' );
					}
					$extra[] = self::safe_cell( SW_Questions::answers_text( $other, '; ' ) );
				} else {
					$extra[] = self::safe_cell( SW_Questions::answers_text( $answers, '; ' ) );
				}

				self::put_row(
					$output,
					array_merge(
					array(
						$row->id,
						$row->campaign_id,
						$row->phone_e164,
						strtoupper( (string) $row->country_code ),
						self::safe_cell( $row->prize_won ),
						self::safe_cell( $row->prize_coupon ?? '' ),
						$row->result,
						SW_Settings::format_datetime( $row->created_at ),
						$row->ip_address,
					),
					$extra
					)
				);
				$last_id = (int) $row->id;
			}
			flush();
		} while ( count( $rows ) === self::BATCH );

		fclose( $output );
		exit;
	}

	/**
	 * fputcsv() with explicit arguments (PHP 8.4 deprecates relying on the
	 * default escape character). '' = standard RFC 4180 quoting.
	 */
	private static function put_row( $handle, array $fields ) {
		fputcsv( $handle, $fields, ',', '"', '' );
	}

	/**
	 * Stop spreadsheet apps from running text that starts with = + - @ as a
	 * formula (CSV injection).
	 */
	private static function safe_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}
}
