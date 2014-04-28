<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Load Front class.
 *
 * @package BP Avatar Suggestions
 * @subpackage Front
 * @since   1.1.0
 */
class Avatar_Suggestions_Front {

	/**
	 * Setup Front.
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.1.0
	 *
	 * @uses buddypress() to get BuddyPress main instance.
	 */
	public static function start() {

		$bp = buddypress();

		if( empty( $bp->extend->avatar_suggestions->front ) ) {
			$bp->extend->avatar_suggestions->front = new self;
		}

		return $bp->extend->avatar_suggestions->front;
	}

	/**
	 * Constructor method.
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.1.0
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_hooks();
	}

	/**
	 * Set the suggestions attachment ids.
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.1.0
	 */
	private function setup_globals() {
		$this->attachment_ids = bp_get_option( 'suggestion_list_avatar_array', array() );
	}

	/**
	 * Set the actions & filters
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.1.0
	 */
	private function setup_hooks() {
		// javascript
		add_action( 'bp_enqueue_scripts',                  array( $this, 'enqueue_script'    )       );

		// Set suggestion
		add_action( 'wp_ajax_user_chose_suggested_avatar', array( $this, 'set_suggestion'    )       );

		// Reset suggestion
		add_action( 'xprofile_screen_change_avatar',       array( $this, 'reset_suggestion'  )       );

		// filter avater
		add_filter( 'bp_core_fetch_avatar',                array( $this, 'suggestion_avatar' ), 1, 9 );
	}

	/**
	 * Enqueue script
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.1.0
	 */
	public function enqueue_script() {
		// Bail if we're not on the change-avatar page
		if ( ! bp_is_user_change_avatar() )
			return false;

		$suggested_avatars = $avatar_list = array();

		if ( ! empty( $this->attachment_ids ) ) {
			// get the suggested avatars
			$suggested_avatars = get_posts( array(
				'post_type' => 'attachment',
				'include' => $this->attachment_ids
			) );
		}
			
		// Bail if no suggested avatars	
		if ( empty( $suggested_avatars ) )
			return false;

		foreach ( $suggested_avatars as $attachment ) {
			$avatar = wp_get_attachment_image_src( $attachment->ID, array( 150, 150 ) );
			$avatar_list[] = $avatar[0];
		}

		// Let others add avatars
		$avatar_list = apply_filters( 'bp_as_filter_avatar_list', $avatar_list );
		
		// No avatar or user has one
		if ( empty( $avatar_list ) || bp_get_user_has_avatar( bp_displayed_user_id() ) )
			return false;
			
		$avatar_list_array = array(
			'json_avatar'      => json_encode( $avatar_list ),
			'displayeduser_id' => bp_displayed_user_id(),
			'success'          => __( 'Bravo, i also like this one!', 'bp-avatar-suggestions' ),
			'error'            => __( 'OOps something went wrong.', 'bp-avatar-suggestions' ),
			'delsuccess'       => __( 'Avatar deleted.', 'bp-avatar-suggestions' ),
			'noselection'      => __( 'Please select an avatar', 'bp-avatar-suggestions' ),
			'intro'            => __( 'Or choose one of the suggested avatars below:', 'bp-avatar-suggestions' ),
			'btn_activate'     => __( 'Activate selected', 'bp-avatar-suggestions' ),
			'btn_deactivate'   => __( 'Deactivate selected', 'bp-avatar-suggestions' ),
			'bpasnonce'        => wp_create_nonce( 'set_avatar_suggestion-' . bp_displayed_user_id() ),
			'alreadyactive'    => __( 'The selected avatar is already set as your avatar', 'bp-avatar-suggestions' ),
			'nodeactivate'     => __( 'Deactivate is only applying for the active avatar', 'bp-avatar-suggestions' ),
			'unknownaction'    => __( 'We were not able to achieve this action', 'bp-avatar-suggestions' ),
		);
		
		// Do current user has already chosen a suggestion ?
		$user_choice = get_user_meta( bp_displayed_user_id(), 'user_avatar_choice', true );
			
		if ( ! empty( $user_choice ) )
			$avatar_list_array['user_avatar_choice'] = $user_choice ;

		// Finally enqueue script
		wp_enqueue_script( 'bp-as-front-js', buddypress()->extend->avatar_suggestions->plugin_js .'bp-as-front.js', array( 'jquery' ), buddypress()->extend->avatar_suggestions->version, true );
		wp_localize_script('bp-as-front-js', 'avatar_list_vars', $avatar_list_array );
	}

	/**
	 * Set suggestion
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.1.0
	 */
	public function set_suggestion() {

		if ( empty( $_POST['user_id'] ) || empty( $_POST['url'] ) )
			wp_die( -1 );

		$user_id = absint( $_POST['user_id'] );
		$avatar = esc_url( $_POST['url'] );

		check_ajax_referer( 'set_avatar_suggestion-'. $user_id, 'nonce' );
	
		if ( bp_update_user_meta( $user_id, 'user_avatar_choice', $avatar ) ) {
			
			do_action( 'xprofile_avatar_uploaded' );
			wp_die( 1 );
			
		} else {
			wp_die( -1 );
		}
	}

	/**
	 * Reset suggestion
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.1.0
	 */
	public function reset_suggestion() {

		if ( ! empty( $_POST['avatar_choice'] ) ) {
		
			delete_user_meta( bp_displayed_user_id(), 'user_avatar_choice' );
			
			do_action( 'bp_core_delete_existing_avatar');
			
			// avoid when avatar upload.
			if ( empty( $_FILES["file"]["name"] ) )
				bp_core_redirect( wp_get_referer() . "?del-avatar");
		}
	}

	/**
	 * Fitler avatar to eventually replace it by a suggestion
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.1.0
	 */
	function suggestion_avatar( $image, $params, $item_id, $avatar_dir, $css_id, $html_width, $html_height, $avatar_folder_url, $avatar_folder_dir ) {
		if ( $params['object'] != "user" || false == $params['html'] ) 
			return $image;
	
		if ( ! bp_get_user_has_avatar( $item_id ) ) {
			
			$user_choice = get_user_meta( $item_id, 'user_avatar_choice', true );

			if( empty( $user_choice ) )
				return $image;
			
			$image = preg_replace('/src="([^"]*)"/i', 'src="' .$user_choice.'"', $image );
		}
		
		/* in case you need to filter with your own function... */
		return apply_filters( 'bp_as_fetch_suggested_avatar', $image, $params, $item_id, $css_id, $html_width, $html_height, $avatar_folder_url, $avatar_folder_dir );
	}
}

/**
 * Stars Front.
 *
 * @package BP Avatar Suggestions
 * @subpackage Admin
 * @since   1.1.0
 */
function bp_avatar_suggestions_front() {
	return Avatar_Suggestions_Front::start();
}
