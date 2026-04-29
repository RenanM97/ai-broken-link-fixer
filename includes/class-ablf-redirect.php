<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABLF_Redirect {

	public static function register_hooks() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_redirect' ) );
		add_action( 'wp_ajax_ablf_delete_redirect', array( __CLASS__, 'ajax_delete_redirect' ) );
		add_action( 'wp_ajax_ablf_add_redirect', array( __CLASS__, 'ajax_add_redirect' ) );
	}

	private static function sanitize_redirect_path( $input ) {
		$input = trim( $input );
		if ( 0 === strpos( $input, 'http' ) ) {
			$parsed = parse_url( $input );
			$input  = isset( $parsed['path'] ) ? $parsed['path'] : '/';
		}
		return '/' . ltrim( $input, '/' );
	}

	public static function create_redirect( $from_url, $to_url, $http_code = 301 ) {
		global $wpdb;
		$from_url = esc_url_raw( home_url( self::sanitize_redirect_path( $from_url ) ) );
		$to_url   = esc_url_raw( home_url( self::sanitize_redirect_path( $to_url ) ) );
		if ( ! $from_url || ! $to_url || $from_url === $to_url ) {
			return false;
		}
		$wpdb->insert(
			ABLF_DB_Handler::table( 'redirects' ),
			array(
				'from_url'   => $from_url,
				'to_url'     => $to_url,
				'http_code'  => (int) $http_code,
				'created_at' => current_time( 'mysql' ),
				'hit_count'  => 0,
			),
			array( '%s', '%s', '%d', '%s', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function get_redirects( $page = 1, $per_page = 20 ) {
		global $wpdb;
		$table = ABLF_DB_Handler::table( 'redirects' );
		$offset = max( 0, ( (int) $page - 1 ) * (int) $per_page );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			(int) $per_page,
			(int) $offset
		) );
	}

	public static function count_redirects() {
		global $wpdb;
		$table = ABLF_DB_Handler::table( 'redirects' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function delete_redirect( $id ) {
		global $wpdb;
		return $wpdb->delete(
			ABLF_DB_Handler::table( 'redirects' ),
			array( 'id' => (int) $id ),
			array( '%d' )
		);
	}

	public static function handle_redirect() {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}
		if ( class_exists( 'ABLF_License' ) && ! ABLF_License::is_pro() ) {
			return;
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( ! $request ) {
			return;
		}
		$current = home_url( $request );

		global $wpdb;
		$table = ABLF_DB_Handler::table( 'redirects' );
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE from_url = %s LIMIT 1",
			$current
		) );
		if ( ! $row ) {
			return;
		}

		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET hit_count = hit_count + 1 WHERE id = %d", (int) $row->id ) );

		wp_redirect( esc_url_raw( $row->to_url ), (int) $row->http_code );
		exit;
	}

	public static function ajax_add_redirect() {
		if ( ! check_ajax_referer( 'ablf_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'pathfinder-link-repair' ) ) );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pathfinder-link-repair' ) ) );
			return;
		}
		$from = isset( $_POST['from_url'] ) ? sanitize_text_field( wp_unslash( $_POST['from_url'] ) ) : '';
		$to   = isset( $_POST['to_url'] ) ? sanitize_text_field( wp_unslash( $_POST['to_url'] ) ) : '';
		if ( ! $from || ! $to ) {
			wp_send_json_error( array( 'message' => __( 'Both URLs are required.', 'pathfinder-link-repair' ) ) );
			return;
		}
		$id = self::create_redirect( $from, $to );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Could not add redirect. URLs may be identical or invalid.', 'pathfinder-link-repair' ) ) );
			return;
		}

		$from_full = esc_url_raw( home_url( self::sanitize_redirect_path( $from ) ) );
		$to_full   = esc_url_raw( home_url( self::sanitize_redirect_path( $to ) ) );

		// Log to fix log so it appears in Fix Log immediately.
		ABLF_DB_Handler::insert_fix_log( array(
			'broken_link_id'  => 0,
			'source_post_id'  => 0,
			'original_url'    => $from_full,
			'replacement_url' => $to_full,
			'anchor_text'     => 'Manual redirect',
			'fixed_by'        => get_current_user_id(),
			'redirect_created' => 1,
		) );

		wp_send_json_success( array(
			'id'         => (int) $id,
			'from_url'   => $from_full,
			'to_url'     => $to_full,
			'http_code'  => 301,
			'hit_count'  => 0,
			'created_at' => current_time( 'mysql' ),
		) );
	}

	public static function ajax_delete_redirect() {
		check_ajax_referer( 'ablf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pathfinder-link-repair' ) ), 403 );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'pathfinder-link-repair' ) ) );
		}
		self::delete_redirect( $id );
		wp_send_json_success( array( 'id' => $id ) );
	}
}
