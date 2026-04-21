<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ABLF_Fixer {

	public static function register_hooks() {
		add_action( 'wp_ajax_ablf_fix_link', array( __CLASS__, 'ajax_fix_link' ) );
		add_action( 'wp_ajax_ablf_ignore_link', array( 'ABLF_DB_Handler', 'ajax_ignore_link' ) );
		add_action( 'wp_ajax_ablf_restore_link', array( 'ABLF_DB_Handler', 'ajax_restore_link' ) );
	}

	public static function fix_link( $broken_link_id, $replacement_url, $user_id, $suggestion_id = 0 ) {
		$broken = ABLF_DB_Handler::get_broken_link_by_id( $broken_link_id );
		if ( ! $broken ) {
			return new WP_Error( 'ablf_not_found', __( 'Broken link not found.', 'ai-broken-link-fixer' ) );
		}

		$post = get_post( (int) $broken->source_post_id );
		if ( ! $post ) {
			return new WP_Error( 'ablf_post_missing', __( 'Source post not found.', 'ai-broken-link-fixer' ) );
		}

		$original = $broken->broken_url;
		$content  = $post->post_content;

		if ( strpos( $content, $original ) === false ) {
			ABLF_DB_Handler::update_broken_link_status( $broken_link_id, 'fixed' );
			return new WP_Error( 'ablf_url_absent', __( 'Original URL no longer present in post content.', 'ai-broken-link-fixer' ) );
		}

		$new_content = str_replace( $original, $replacement_url, $content );
		$result = wp_update_post( array(
			'ID'           => (int) $post->ID,
			'post_content' => $new_content,
		), true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		ABLF_DB_Handler::update_broken_link_status( $broken_link_id, 'fixed' );

		$redirect_created = 0;
		if ( get_option( 'ablf_auto_redirect', false ) && class_exists( 'ABLF_Redirect' ) && class_exists( 'ABLF_License' ) && ABLF_License::is_pro() ) {
			ABLF_Redirect::create_redirect( $original, $replacement_url );
			$redirect_created = 1;
		}

		ABLF_DB_Handler::insert_fix_log( array(
			'broken_link_id'   => (int) $broken_link_id,
			'source_post_id'   => (int) $post->ID,
			'original_url'     => $original,
			'replacement_url'  => $replacement_url,
			'anchor_text'      => $broken->anchor_text,
			'fixed_by'         => (int) $user_id,
			'redirect_created' => $redirect_created,
		) );

		if ( $suggestion_id ) {
			ABLF_DB_Handler::update_suggestion_status( $suggestion_id, 'accepted' );
		}

		return true;
	}

	public static function ajax_fix_link() {
		check_ajax_referer( 'ablf_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ai-broken-link-fixer' ) ), 403 );
		}

		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$sid = isset( $_POST['suggestion_id'] ) ? absint( $_POST['suggestion_id'] ) : 0;
		$url = isset( $_POST['replacement_url'] ) ? esc_url_raw( wp_unslash( $_POST['replacement_url'] ) ) : '';

		if ( ! $id || ! $url ) {
			wp_send_json_error( array( 'message' => __( 'Missing parameters.', 'ai-broken-link-fixer' ) ) );
		}

		$result = self::fix_link( $id, $url, get_current_user_id(), $sid );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'id' => $id ) );
	}
}
