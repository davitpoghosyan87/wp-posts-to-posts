<?php

define( 'P2P_BOX_NONCE', 'p2p-box' );

$GLOBALS['_p2p_connection_types'] = array();

class P2P_Box_Factory {

	function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'wp_ajax_p2p_box', array( __CLASS__, 'wp_ajax_p2p_box' ) );
	}

	/**
	 * Add all the metaboxes.
	 */
	static function add_meta_boxes( $from ) {
		foreach ( $GLOBALS['_p2p_connection_types'] as $box_id => $args ) {
			$box = self::make_box( $box_id, $from );
			if ( !$box )
				continue;

			$box->register();
		}
	}

	/**
	 * Collect metadata from all boxes.
	 */
	function save_post( $post_id, $post ) {
		if ( 'revision' == $post->post_type || defined( 'DOING_AJAX' ) )
			return;

		// Custom fields
		if ( isset( $_POST['p2p_meta'] ) ) {
			foreach ( $_POST['p2p_meta'] as $p2p_id => $data ) {
				foreach ( $data as $key => $value ) {
					p2p_update_meta( $p2p_id, $key, $value );
				}
			}
		}

		// Ordering
		if ( isset( $_POST['p2p_order'] ) ) {
			foreach ( $_POST['p2p_order'] as $key => $list ) {
				foreach ( $list as $i => $p2p_id ) {
					p2p_update_meta( $p2p_id, $key, $i );
				}
			}
		}
	}

	/**
	 * Controller for all box ajax requests.
	 */
	function wp_ajax_p2p_box() {
		check_ajax_referer( P2P_BOX_NONCE, 'nonce' );

		$box = self::make_box( $_REQUEST['box_id'], $_REQUEST['post_type'] );
		if ( !$box )
			die(0);

		if ( !current_user_can( $box->ptype->cap->edit_posts ) )
			die(-1);

		$method = 'ajax_' . $_REQUEST['subaction'];

		$box->$method();
	}

	private static function make_box( $box_id, $post_type ) {
		if ( !isset( $GLOBALS['_p2p_connection_types'][ $box_id ] ) )
			return false;

		$policy = $GLOBALS['_p2p_connection_types'][ $box_id ];

		$direction = $policy->get_direction( $post_type );

		if ( !$direction || ( !$policy->reciprocal && 'from' != $direction ) )
			return false;

		$policy->set_direction( $direction ); // TODO: always calculate on the fly?

		return new P2P_Box( $box_id, $policy );
	}
}

P2P_Box_Factory::init();

