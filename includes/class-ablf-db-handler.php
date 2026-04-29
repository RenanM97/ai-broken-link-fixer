<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABLF_DB_Handler {

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'ablf_' . $name;
	}

	/* ---------- Broken links ---------- */

	public static function insert_broken_link( $data ) {
		global $wpdb;
		$table = self::table( 'broken_links' );

		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, status FROM {$table} WHERE source_post_id = %d AND broken_url = %s LIMIT 1",
			(int) $data['source_post_id'],
			$data['broken_url']
		) );

		$now = current_time( 'mysql' );

		if ( $existing ) {
			// Never overwrite status — only the fixer and ignore action may change it.
			$wpdb->update(
				$table,
				array(
					'http_status'     => isset( $data['http_status'] ) ? (int) $data['http_status'] : 0,
					'last_checked_at' => $now,
				),
				array( 'id' => (int) $existing->id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
			return (int) $existing->id;
		}

		$wpdb->insert(
			$table,
			array(
				'source_post_id'      => (int) $data['source_post_id'],
				'broken_url'          => $data['broken_url'],
				'anchor_text'         => isset( $data['anchor_text'] ) ? $data['anchor_text'] : '',
				'surrounding_context' => isset( $data['surrounding_context'] ) ? $data['surrounding_context'] : '',
				'http_status'         => isset( $data['http_status'] ) ? (int) $data['http_status'] : 0,
				'status'              => isset( $data['status'] ) ? $data['status'] : 'broken',
				'first_found_at'      => $now,
				'last_checked_at'     => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function get_broken_links( $filters = array(), $page = 1, $per_page = 20 ) {
		global $wpdb;
		$table = self::table( 'broken_links' );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['status'] ) && 'all' !== $filters['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $filters['status'];
		}
		if ( ! empty( $filters['source_post_id'] ) ) {
			$where[]  = 'source_post_id = %d';
			$params[] = (int) $filters['source_post_id'];
		}

		$offset = max( 0, ( (int) $page - 1 ) * (int) $per_page );

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY first_found_at DESC LIMIT %d OFFSET %d';
		$params[] = (int) $per_page;
		$params[] = (int) $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	public static function count_broken_links( $filters = array() ) {
		global $wpdb;
		$table = self::table( 'broken_links' );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['status'] ) && 'all' !== $filters['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $filters['status'];
		}

		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );
		if ( $params ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	public static function get_broken_link_by_id( $id ) {
		global $wpdb;
		$table = self::table( 'broken_links' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	public static function update_broken_link_status( $id, $status ) {
		global $wpdb;
		$data = array( 'status' => $status );
		if ( 'fixed' === $status ) {
			$data['fixed_at'] = current_time( 'mysql' );
		}
		return $wpdb->update(
			self::table( 'broken_links' ),
			$data,
			array( 'id' => (int) $id )
		);
	}

	public static function mark_pathfinder_run( $id ) {
		global $wpdb;
		return $wpdb->update(
			self::table( 'broken_links' ),
			array( 'pathfinder_run' => 1 ),
			array( 'id' => (int) $id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	public static function ignore_link( $id ) {
		return self::update_broken_link_status( (int) $id, 'ignored' );
	}

	public static function restore_link( $id ) {
		return self::update_broken_link_status( (int) $id, 'broken' );
	}

	/* ---------- Suggestions ---------- */

	public static function insert_suggestion( $data ) {
		global $wpdb;
		$wpdb->insert(
			self::table( 'suggestions' ),
			array(
				'broken_link_id'  => (int) $data['broken_link_id'],
				'suggested_url'   => $data['suggested_url'],
				'suggested_title' => isset( $data['suggested_title'] ) ? $data['suggested_title'] : '',
				'confidence'      => isset( $data['confidence'] ) ? (float) $data['confidence'] : 0.0,
				'reasoning'       => isset( $data['reasoning'] ) ? $data['reasoning'] : '',
				'status'          => 'pending',
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function get_suggestions_for_link( $broken_link_id ) {
		global $wpdb;
		$table = self::table( 'suggestions' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE broken_link_id = %d ORDER BY confidence DESC, id ASC",
			(int) $broken_link_id
		) );
	}

	public static function delete_suggestions_for_link( $broken_link_id ) {
		global $wpdb;
		return $wpdb->delete(
			self::table( 'suggestions' ),
			array( 'broken_link_id' => (int) $broken_link_id ),
			array( '%d' )
		);
	}

	public static function update_suggestion_status( $id, $status ) {
		global $wpdb;
		return $wpdb->update(
			self::table( 'suggestions' ),
			array( 'status' => $status ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/* ---------- Fix log ---------- */

	public static function insert_fix_log( $data ) {
		global $wpdb;
		$wpdb->insert(
			self::table( 'fix_log' ),
			array(
				'broken_link_id'   => (int) $data['broken_link_id'],
				'source_post_id'   => (int) $data['source_post_id'],
				'original_url'     => $data['original_url'],
				'replacement_url'  => $data['replacement_url'],
				'anchor_text'      => isset( $data['anchor_text'] ) ? $data['anchor_text'] : '',
				'fixed_by'         => (int) $data['fixed_by'],
				'fixed_at'         => current_time( 'mysql' ),
				'redirect_created' => ! empty( $data['redirect_created'] ) ? 1 : 0,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function get_fix_log( $page = 1, $per_page = 20 ) {
		global $wpdb;
		$table  = self::table( 'fix_log' );
		$offset = max( 0, ( (int) $page - 1 ) * (int) $per_page );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT id, broken_link_id, source_post_id, original_url, replacement_url, anchor_text, fixed_by, fixed_at, redirect_created FROM {$table} ORDER BY fixed_at DESC LIMIT %d OFFSET %d",
			(int) $per_page,
			(int) $offset
		) );
	}

	public static function count_fix_log() {
		global $wpdb;
		$table = self::table( 'fix_log' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function count_fixed_this_month() {
		global $wpdb;
		$table = self::table( 'fix_log' );
		$start = gmdate( 'Y-m-01 00:00:00' );
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE fixed_at >= %s",
			$start
		) );
	}

	/* ---------- Scan queue ---------- */

	public static function queue_urls( $rows ) {
		global $wpdb;
		if ( empty( $rows ) ) {
			return 0;
		}
		$table   = self::table( 'scan_queue' );
		$now     = current_time( 'mysql' );
		$inserted = 0;

		foreach ( $rows as $row ) {
			$source_post_id = (int) $row['source_post_id'];
			$url            = $row['url'];

			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE source_post_id = %d AND url = %s AND status IN ('queued','processing') LIMIT 1",
				$source_post_id,
				$url
			) );
			if ( $exists ) {
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'source_post_id'      => $source_post_id,
					'url'                 => $url,
					'anchor_text'         => isset( $row['anchor_text'] ) ? $row['anchor_text'] : '',
					'surrounding_context' => isset( $row['surrounding_context'] ) ? $row['surrounding_context'] : '',
					'status'              => 'queued',
					'attempts'            => 0,
					'queued_at'           => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
			$inserted++;
		}
		return $inserted;
	}

	public static function get_queued_urls( $limit = 20 ) {
		global $wpdb;
		$table = self::table( 'scan_queue' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = 'queued' ORDER BY queued_at ASC LIMIT %d",
			(int) $limit
		) );
	}

	public static function mark_queue_item_processing( $id ) {
		global $wpdb;
		return $wpdb->update(
			self::table( 'scan_queue' ),
			array(
				'status'       => 'processing',
				'processed_at' => current_time( 'mysql' ), // start-time stamp for staleness detection
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Reset ALL items stuck in 'processing' back to 'queued' or 'failed'.
	 *
	 * No timestamp check — any row still in 'processing' when a new batch
	 * starts (or on a progress poll) is orphaned from a crashed previous run.
	 * Timestamp-based detection silently fails when processed_at IS NULL
	 * (rows marked processing before the column was stamped), so we avoid it.
	 *
	 * - attempts < 3  → back to 'queued' for retry
	 * - attempts >= 3 → 'failed' so the progress bar can reach 100%
	 */
	public static function recover_stale_queue_items() {
		global $wpdb;
		$table = self::table( 'scan_queue' );

		$wpdb->query(
			"UPDATE {$table} SET status = 'queued' WHERE status = 'processing' AND attempts < 3"
		);

		$wpdb->query(
			"UPDATE {$table} SET status = 'failed' WHERE status = 'processing' AND attempts >= 3"
		);
	}

	public static function mark_queue_item_done( $id, $status = 'done' ) {
		global $wpdb;
		return $wpdb->update(
			self::table( 'scan_queue' ),
			array(
				'status'       => $status,
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function increment_queue_attempts( $id ) {
		global $wpdb;
		$table = self::table( 'scan_queue' );
		return $wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET attempts = attempts + 1 WHERE id = %d",
			(int) $id
		) );
	}

	public static function queue_progress() {
		global $wpdb;
		$table = self::table( 'scan_queue' );
		$row = $wpdb->get_row(
			"SELECT
				SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) AS queued,
				SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing,
				SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
				COUNT(*) AS total
			FROM {$table}",
			ARRAY_A
		);
		if ( ! $row ) {
			return array( 'queued' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0, 'total' => 0, 'percent' => 100 );
		}
		$total   = (int) $row['total'];
		$done    = (int) $row['done'] + (int) $row['failed'];
		$percent = $total > 0 ? min( 100, (int) floor( ( $done / $total ) * 100 ) ) : 100;

		return array(
			'queued'     => (int) $row['queued'],
			'processing' => (int) $row['processing'],
			'done'       => (int) $row['done'],
			'failed'     => (int) $row['failed'],
			'total'      => $total,
			'percent'    => $percent,
		);
	}

	public static function clear_finished_queue() {
		global $wpdb;
		$table = self::table( 'scan_queue' );
		return $wpdb->query( "DELETE FROM {$table} WHERE status IN ('done','failed')" );
	}

	/* ---------- Usage ---------- */

	public static function get_usage_this_month() {
		global $wpdb;
		$table = self::table( 'usage' );
		$month = gmdate( 'Y-m' );
		$used = $wpdb->get_var( $wpdb->prepare(
			"SELECT suggestions_used FROM {$table} WHERE month_year = %s",
			$month
		) );
		return (int) $used;
	}

	public static function increment_usage( $by = 1 ) {
		global $wpdb;
		$table = self::table( 'usage' );
		$month = gmdate( 'Y-m' );
		$now   = current_time( 'mysql' );

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE month_year = %s",
			$month
		) );

		if ( $existing ) {
			return $wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET suggestions_used = suggestions_used + %d, last_updated = %s WHERE id = %d",
				(int) $by,
				$now,
				(int) $existing
			) );
		}

		return $wpdb->insert(
			$table,
			array(
				'month_year'       => $month,
				'suggestions_used' => (int) $by,
				'topup_credits'    => 0,
				'last_updated'     => $now,
			),
			array( '%s', '%d', '%d', '%s' )
		);
	}

	public static function reset_monthly_usage() {
		global $wpdb;
		$table = self::table( 'usage' );
		$month = gmdate( 'Y-m' );
		$now   = current_time( 'mysql' );

		// Carry forward topup_credits from all rows before wiping them.
		$carried_topup = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(topup_credits), 0) FROM {$table} WHERE month_year != %s",
			$month
		) );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE month_year != %s",
			$month
		) );

		if ( $carried_topup > 0 ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$table} (month_year, suggestions_used, topup_credits, period_start, last_updated)
				 VALUES (%s, 0, %d, %s, %s)
				 ON DUPLICATE KEY UPDATE
				   suggestions_used = 0,
				   topup_credits    = topup_credits + %d,
				   period_start     = %s,
				   last_updated     = %s",
				$month, $carried_topup, $now, $now, $carried_topup, $now, $now
			) );
		} else {
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$table} (month_year, suggestions_used, topup_credits, period_start, last_updated)
				 VALUES (%s, 0, 0, %s, %s)
				 ON DUPLICATE KEY UPDATE
				   suggestions_used = 0,
				   period_start     = %s,
				   last_updated     = %s",
				$month, $now, $now, $now, $now
			) );
		}
	}

	public static function get_topup_credits() {
		global $wpdb;
		$table = self::table( 'usage' );
		$month = gmdate( 'Y-m' );
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT topup_credits FROM {$table} WHERE month_year = %s",
			$month
		) );
	}

	public static function add_topup_credits( $amount ) {
		global $wpdb;
		$table  = self::table( 'usage' );
		$month  = gmdate( 'Y-m' );
		$now    = current_time( 'mysql' );
		$amount = max( 0, (int) $amount );

		return $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (month_year, suggestions_used, topup_credits, last_updated)
			 VALUES (%s, 0, %d, %s)
			 ON DUPLICATE KEY UPDATE
			   topup_credits = topup_credits + %d,
			   last_updated  = %s",
			$month, $amount, $now, $amount, $now
		) );
	}

	public static function consume_topup_credit( $by = 1 ) {
		global $wpdb;
		$table = self::table( 'usage' );
		$month = gmdate( 'Y-m' );
		$now   = current_time( 'mysql' );

		return $wpdb->query( $wpdb->prepare(
			"UPDATE {$table}
			 SET topup_credits = GREATEST(0, topup_credits - %d),
			     last_updated  = %s
			 WHERE month_year = %s",
			(int) $by, $now, $month
		) );
	}

	/* ---------- Allowlist ---------- */

	public static function get_allowlist() {
		global $wpdb;
		$table = self::table( 'allowlist' );
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
	}

	public static function count_allowlist() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( 'allowlist' ) );
	}

	public static function add_to_allowlist( $pattern, $type, $note, $user_id ) {
		global $wpdb;
		$type = in_array( $type, array( 'url', 'domain' ), true ) ? $type : 'url';
		return $wpdb->insert(
			self::table( 'allowlist' ),
			array(
				'pattern'      => sanitize_text_field( $pattern ),
				'pattern_type' => $type,
				'note'         => sanitize_text_field( $note ),
				'created_at'   => current_time( 'mysql' ),
				'created_by'   => (int) $user_id,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);
	}

	public static function remove_from_allowlist( $id ) {
		global $wpdb;
		return $wpdb->delete(
			self::table( 'allowlist' ),
			array( 'id' => (int) $id ),
			array( '%d' )
		);
	}

	/**
	 * Delete 'broken' status rows whose URLs are now covered by the exclusion
	 * settings or the allowlist. Called at the start of every scan so the
	 * dashboard is immediately clean after a user adds an exclusion.
	 */
	public static function purge_excluded_broken_links( $excluded_urls, $excluded_domains, $allowlist = array() ) {
		global $wpdb;
		$table = self::table( 'broken_links' );

		$rows = $wpdb->get_results( "SELECT id, broken_url FROM {$table} WHERE status = 'broken'" );
		if ( empty( $rows ) ) {
			return 0;
		}

		$to_delete = array();
		foreach ( $rows as $row ) {
			$url  = $row->broken_url;
			$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );

			// Excluded URL patterns (substring match).
			foreach ( $excluded_urls as $pattern ) {
				$pattern = trim( (string) $pattern );
				if ( $pattern !== '' && false !== stripos( $url, $pattern ) ) {
					$to_delete[] = (int) $row->id;
					continue 2;
				}
			}

			// Excluded domains (exact + www-aware).
			// If the entry contains "://" it is a full URL — match as substring
			// rather than extracting the host, to avoid accidentally excluding
			// the entire site when an internal URL is stored in the domain field.
			foreach ( $excluded_domains as $d ) {
				$d = strtolower( trim( (string) $d ) );
				if ( $d === '' ) {
					continue;
				}
				if ( strpos( $d, '://' ) !== false ) {
					if ( false !== stripos( $url, $d ) ) {
						$to_delete[] = (int) $row->id;
						continue 2;
					}
					continue;
				}
				if ( $host !== '' && ( $host === $d || $host === 'www.' . $d || 'www.' . $host === $d ) ) {
					$to_delete[] = (int) $row->id;
					continue 2;
				}
			}

			// Allowlist entries.
			foreach ( $allowlist as $item ) {
				$p = strtolower( trim( (string) $item->pattern ) );
				if ( $p === '' ) {
					continue;
				}
				if ( 'domain' === $item->pattern_type ) {
					if ( $host !== '' && ( $host === $p || $host === 'www.' . $p || 'www.' . $host === $p ) ) {
						$to_delete[] = (int) $row->id;
						continue 2;
					}
				} else {
					if ( false !== stripos( $url, $p ) ) {
						$to_delete[] = (int) $row->id;
						continue 2;
					}
				}
			}
		}

		if ( empty( $to_delete ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $to_delete ), '%d' ) );
		return (int) $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $to_delete )
		);
	}

	/* ---------- Dashboard stats ---------- */

	public static function get_dashboard_stats() {
		global $wpdb;
		$table = self::table( 'broken_links' );
		$row = $wpdb->get_row(
			"SELECT
				SUM(CASE WHEN status = 'broken' THEN 1 ELSE 0 END) AS total_broken,
				SUM(CASE WHEN status = 'ignored' THEN 1 ELSE 0 END) AS ignored,
				SUM(CASE WHEN status = 'fixed' THEN 1 ELSE 0 END) AS total_fixed
			FROM {$table}",
			ARRAY_A
		);
		return array(
			'total_broken'      => $row ? (int) $row['total_broken'] : 0,
			'ignored'           => $row ? (int) $row['ignored'] : 0,
			'total_fixed'       => $row ? (int) $row['total_fixed'] : 0,
			'fixed_this_month'  => self::count_fixed_this_month(),
			'suggestions_used'  => self::get_usage_this_month(),
		);
	}

	/* ---------- Cleanup ---------- */

	public static function cleanup_old_data( $days = 90 ) {
		global $wpdb;
		$days  = max( 1, (int) $days );
		$table = self::table( 'broken_links' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		return $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE status IN ('fixed','ignored') AND COALESCE(fixed_at, last_checked_at, first_found_at) < %s",
			$cutoff
		) );
	}

	public static function ajax_ignore_link() {
		check_ajax_referer( 'ablf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pathfinder-link-repair' ) ), 403 );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'pathfinder-link-repair' ) ) );
		}
		self::ignore_link( $id );
		wp_send_json_success( array( 'id' => $id ) );
	}

	public static function ajax_restore_link() {
		check_ajax_referer( 'ablf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pathfinder-link-repair' ) ), 403 );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'pathfinder-link-repair' ) ) );
		}
		self::restore_link( $id );
		wp_send_json_success( array( 'id' => $id ) );
	}
}
