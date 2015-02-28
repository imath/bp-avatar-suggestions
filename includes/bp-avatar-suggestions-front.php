<?php
/**
 * The Front class.
 *
 * @package BP Avatar Suggestions
 * @subpackage Front
 * @since   1.1.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

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
		// Get BuddyPress instance
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
		// Get BuddyPress instance
		$bp = buddypress();

		$this->avatar_post_id = $bp->extend->avatar_suggestions->avatar_post_id;
		$this->enable_users   = $bp->extend->avatar_suggestions->enable_users;
		$this->enable_groups  = $bp->extend->avatar_suggestions->enable_groups;
		$this->min            = SCRIPT_DEBUG ? '' : '.min';
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
		add_action( 'bp_enqueue_scripts',                     array( $this, 'enqueue_script'       ) );

		// Load the javascript template for the Suggestions selector
		add_action( 'bp_after_profile_avatar_upload_content', array( $this, 'suggestions_selector' ) );
		add_action( 'bp_after_group_avatar_creation_step',    array( $this, 'suggestions_selector' ) );
		add_action( 'bp_after_group_admin_content',           array( $this, 'suggestions_selector' ) );

		// Get Suggestions
		add_action( 'wp_ajax_get_avatar_suggestions',   array( $this, 'get_avatar_suggestions' ) );

		// Set suggestion
		add_action( 'wp_ajax_set_avatar_suggestion',    array( $this, 'set_suggestion'         ) );

		// remove suggestion
		add_action( 'wp_ajax_remove_avatar_suggestion', array( $this, 'remove_suggestion'      ) );

		// filter avatar
		add_filter( 'bp_core_fetch_avatar',                array( $this, 'suggestion_avatar' ), 1, 2 );
		add_filter( 'bp_core_fetch_avatar_url',            array( $this, 'suggestion_avatar' ), 1, 2 );
	}

	/**
	 * Bail if avatars are not enabled
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 *
	 * @return bool whether avatars are enabled or not
	 */
	public function bail() {
		// Get BuddyPress instance
		$bp = buddypress();

		return empty( $bp->avatar->show_avatars );
	}

	/**
	 * Get and prepare the suggestions for BackBone
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 */
	public function get_avatar_suggestions() {
		$avatar_types = array(
			'user'  => array( 1, 2 ),
			'group' => array( 1, 3 ),
		);

		if ( ! isset( $_POST['item_object'] ) || ! isset( $avatar_types[ $_POST['item_object'] ] ) || empty( $_POST['item_id'] ) ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'avatar_suggestions_selector', 'nonce' );

		// Set the suggestions arguments
		$suggestions_args = array(
			'post_type'      => 'attachment',
			'post_parent'    => $this->avatar_post_id,
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_bpas_avatar_type',
					'compare' => 'IN',
					'value'  => $avatar_types[ $_POST['item_object'] ],
				)
			),
		);

		$query = new WP_Query( $suggestions_args );

		$suggestions = array_map( 'wp_prepare_attachment_for_js', $query->posts );

		if ( ! empty( $suggestions ) ) {
			$item_id = absint( $_POST['item_id'] );

			if ( bp_is_group_admin_page() || bp_is_group_create() ) {
				$current_item_avatar = groups_get_groupmeta( $item_id, 'group_avatar_choice', true );
			} else {
				$current_item_avatar = bp_get_user_meta( $item_id, 'user_avatar_choice', true );
			}

			// Set the current user's avatar as selected
			if ( ! empty( $current_item_avatar ) ) {
				foreach ( $suggestions as $key => $suggestion ) {
					if ( $current_item_avatar == $suggestion['sizes']['thumbnail']['url'] ) {
						$suggestions[ $key ]['selected'] = 1;
					}
				}
			}
		}

		$suggestions = array_filter( $suggestions );

		wp_send_json_success( $suggestions );
	}

	/**
	 * Are we editing a user avatar ?
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 *
	 * @return bool true if editing a user avatar, false otherwise
	 */
	public function is_user_set_avatar() {
		$retval = false;

		if ( empty( $this->enable_users ) || ! bp_is_user() ) {
			return $retval;
		}

		if ( bp_is_user_change_avatar() && $this->enable_users && 'crop-image' != bp_get_avatar_admin_step() && ! bp_get_user_has_avatar( bp_displayed_user_id() ) ) {
			$retval = true;
		}

		return true;
	}

	/**
	 * Are we editing a group avatar during group creation ?
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 *
	 * @return bool true if editing a group avatar, false otherwise
	 */
	public function is_group_create_avatar() {
		$retval = false;

		if ( empty( $this->enable_groups ) ) {
			return $retval;
		}

		$action = 'group-avatar';
		if ( buddypress()->site_options['bp-disable-avatar-uploads'] ) {
			$action = 'avatar-suggestions';
		}

		// create
		if ( bp_is_group_create() && bp_is_group_creation_step( $action ) && 'crop-image' != bp_get_avatar_admin_step() ) {
			$retval = true;
		}

		return $retval;
	}

	/**
	 * Are we editing a group avatar ?
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 *
	 * @return bool true if editing a group avatar, false otherwise
	 */
	public function is_group_manage_avatar() {
		$retval = false;

		if ( empty( $this->enable_groups ) ) {
			return $retval;
		}

		$action = 'group-avatar';
		if ( buddypress()->site_options['bp-disable-avatar-uploads'] ) {
			$action = 'avatar-suggestions';
		}

		// manage
		if ( bp_is_group_admin_page() && bp_is_group_admin_screen( $action ) && 'crop-image' != bp_get_avatar_admin_step() && ! bp_get_group_has_avatar() ) {
			$retval = true;
		}

		return $retval;
	}

	/**
	 * Enqueue script
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.1.0
	 */
	public function enqueue_script() {
		// Bail if avatar feature is completely disabled
		if ( $this->bail() ) {
			return;
		}

		// Bail if we're not on the change-avatar page
		if ( ! $this->is_user_set_avatar() && ! $this->is_group_create_avatar() && ! $this->is_group_manage_avatar() ) {
			return;
		}

		$suggestions_settings = array(
			'nonce'               => wp_create_nonce( 'avatar_suggestions_selector' ),
			'avatarSaved'         => esc_html__( 'Success: Avatar saved.', 'bp-avatar-suggestions' ),
			'avatarNotSaved'      => esc_html__( 'Error: Avatar not saved.', 'bp-avatar-suggestions' ),
			'avatarRemoved'       => esc_html__( 'Success: Avatar removed.', 'bp-avatar-suggestions' ),
			'avatarNotRemoved'    => esc_html__( 'Error: Avatar not removed.', 'bp-avatar-suggestions' ),
			'groupCreateContext'  => $this->is_group_create_avatar(),
		);

		if ( bp_is_user() ) {
			$suggestions_settings['item_object'] = 'user';
			$suggestions_settings['item_id']     = bp_displayed_user_id();
		} else if ( bp_is_group() || bp_is_group_create() ) {
			// Get the group id
			$group_id =  bp_get_current_group_id();

			// Get the new group id
			if ( empty( $group_id ) ) {
				$group_id = bp_get_new_group_id();
			}

			$suggestions_settings['item_object'] = 'group';
			$suggestions_settings['item_id']     = $group_id;
		}

		// Get BuddyPress instance
		$bp = buddypress();

		wp_enqueue_style  ( 'bp-as-front-style', $bp->extend->avatar_suggestions->plugin_css . "bp-as-front{$this->min}.css", array(), $bp->extend->avatar_suggestions->version );
		wp_enqueue_script ( 'bp-as-backbone-front', $bp->extend->avatar_suggestions->plugin_js . "bp-as-backbone-front{$this->min}.js", array( 'wp-backbone' ), $bp->extend->avatar_suggestions->version, true );
		wp_localize_script( 'bp-as-backbone-front', 'avatar_suggestions_vars', $suggestions_settings );
	}

	/**
	 * Template for the Avatar Suggestions selector
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 */
	public function suggestions_selector() {
		// Bail if avatar feature is completely disabled
		if ( $this->bail() ) {
			return;
		}

		// Bail if we're not on the change-avatar page
		if ( ! $this->is_user_set_avatar() && ! $this->is_group_create_avatar() && ! $this->is_group_manage_avatar() ) {
			return;
		}

		?>
		<div id="avatar-suggestions-selector">

			<h4><?php esc_html_e( 'You can select one of the suggested avatars below', 'bp-avatar-suggestions' ) ;?></h4>
			<p class="description"><?php esc_html_e( 'Click to add or remove the suggested avatar. If a suggestion is selected, you will need to remove it before choosing another one.', 'bp-avatar-suggestions' ) ;?></p>

			<div class="avatar-suggestions-list"></div>
			<div class="avatar-suggestions-action"></div>
		</div>

		<script id="tmpl-suggestion" type="text/html">
			<img class="avatar avatar-suggestion-item" src="{{ data.sizes.thumbnail.url }}" data-suggestionid="{{data.id}}" />
		</script>
		<?php
	}

	/**
	 * Set suggestion
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 */
	public function set_suggestion() {
		if ( empty( $_POST['item_id'] ) || empty( $_POST['avatar_url'] ) ) {
			wp_send_json_error();
		}

		$item_id = absint( $_POST['item_id'] );
		$avatar = esc_url( $_POST['avatar_url'] );

		check_ajax_referer( 'avatar_suggestions_selector', 'nonce' );

		if ( bp_is_group_admin_page() || bp_is_group_create() ) {
			groups_update_groupmeta( $item_id, 'group_avatar_choice', $avatar );

			if ( bp_is_group_admin_page() ) {
				do_action( 'groups_screen_group_admin_avatar', $item_id );
			}
		} else {
			bp_update_user_meta( $item_id, 'user_avatar_choice', $avatar );

			do_action( 'xprofile_avatar_uploaded' );
		}

		wp_send_json_success();
	}

	/**
	 * Remove suggestion
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 */
	public function remove_suggestion() {
		if ( empty( $_POST['item_id'] ) || empty( $_POST['item_object'] ) ) {
			wp_send_json_error();
		}

		$item_id = absint( $_POST['item_id'] );

		check_ajax_referer( 'avatar_suggestions_selector', 'nonce' );

		if ( bp_is_group_admin_page() || bp_is_group_create() ) {
			groups_delete_groupmeta( $item_id, 'group_avatar_choice' );

			if ( bp_is_group_admin_page() ) {
				do_action( 'groups_screen_group_admin_avatar', $item_id );
			}
		} else {
			bp_delete_user_meta( $item_id, 'user_avatar_choice' );

			do_action( 'bp_core_delete_existing_avatar' );
		}

		$this->avatar_removed = true;

		$avatar_url = bp_core_fetch_avatar( array(
			'item_id' => $_POST['item_id'],
			'object'  => $_POST['item_object'],
			'type'    => 'full',
			'html'    => false,
		) );

		$this->avatar_removed = false;

		wp_send_json_success( $avatar_url );
	}

	/**
	 * Fitler avatar to eventually replace it by a suggestion
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.1.0
	 */
	function suggestion_avatar( $image = '', $params = array() ) {
		if ( ! empty( $this->avatar_removed ) ) {
			return $image;
		}

		if ( ! empty( $params['no_grav'] ) || empty( $params['item_id'] ) ) {
			return $image;
		}

		if ( ! $this->enable_users && 'user' == $params['object'] ) {
			return $image;
		}

		if ( ! $this->enable_groups && 'group' == $params['object'] ) {
			return $image;
		}

		$component_items = array( 'user' => 1, 'group' => 1 );
		if ( empty( $component_items[ $params['object'] ] ) ) {
			return $image;
		}

		$item_id = absint( $params['item_id'] );

		switch( $params['object'] ) {
			case 'group' :
			if ( ! bp_get_group_has_avatar( $item_id ) ) {

				$group_choice = groups_get_groupmeta( $item_id, 'group_avatar_choice', true );

				if ( empty( $group_choice ) ) {
					return $image;
				}

				if ( ! empty( $params['html'] ) ){
					$image = preg_replace('/src="([^"]*)"/i', 'src="' . $group_choice . '"', $image );
				} else {
					$image = $group_choice;
				}

			}

			case 'user' :
			if ( ! bp_get_user_has_avatar( $item_id ) ) {

				$user_choice = get_user_meta( $item_id, 'user_avatar_choice', true );

				if ( empty( $user_choice ) ) {
					return $image;
				}

				if ( ! empty( $params['html'] ) ){
					$image = preg_replace('/src="([^"]*)"/i', 'src="' . $user_choice . '"', $image );
				} else {
					$image = $user_choice;
				}

			}
		}

		/* in case you need to filter with your own function... */
		return apply_filters( 'bp_as_fetch_suggested_avatar', $image, $params );
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
