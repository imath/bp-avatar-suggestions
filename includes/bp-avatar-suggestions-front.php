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
		$this->min            = ( defined( 'SCRIPT_DEBUG' ) && true == SCRIPT_DEBUG ) ? '' : '.min';
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
		add_action( 'bp_enqueue_scripts',       array( $this, 'register_script' ),  2 );
		add_action( 'bp_admin_enqueue_scripts', array( $this, 'register_script' ),  2 );
		add_action( 'bp_enqueue_scripts',       array( $this, 'enqueue_script'  ), 10 );

		// Extend New avatar UI
		add_filter( 'bp_attachment_avatar_script_data',    array( $this, 'include_script'      ), 10, 2 );
		add_filter( 'bp_attachments_avatar_nav',           array( $this, 'suggestions_nav'     ), 10, 2 );
		add_action( 'bp_attachments_avatar_main_template', array( $this, 'js_template'         )        );
		add_filter( 'bp_attachments_get_plupload_l10n',    array( $this, 'suggestions_strings' ), 10, 1 );

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
	 * Prepare a suggestion for javascript
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.3.0
	 *
	 * @param  object $avatar the attachment object
	 * @return array the avatar prepared for javascript
	 */
	public function prepare_for_js( $avatar = null ) {
		if ( empty( $avatar ) ) {
			return false;
		}

		// Reset the suggestion size
		$suggestion_size = array();
		$avatar = wp_prepare_attachment_for_js( $avatar );

		if ( ! empty( $avatar['id'] ) ) {
			// Set the thumbnail size if not available
			if ( empty( $avatar['sizes']['thumbnail'] ) ) {
				$suggestion_size['thumbnail'] = image_downsize( $avatar['id'], 'thumbnail' );
			}

			if ( empty( $suggestion_size['thumbnail'] ) || ( ! empty( $suggestion_size['thumbnail'] ) && ( $suggestion_size['thumbnail'][2] != bp_core_avatar_full_height() || $suggestion_size['thumbnail'][1] != bp_core_avatar_full_width() ) ) ) {
				// Try to use the bp_avatar_suggestions if it's an intermediate size
				$suggestion_size['bp_avatar_suggestions'] = image_downsize( $avatar['id'], 'bp_avatar_suggestions' );

				if ( ! empty( $suggestion_size['bp_avatar_suggestions'] ) ) {
					$suggestion_size['thumbnail'] = $suggestion_size['bp_avatar_suggestions'];
				}
			}

			if ( ! empty( $suggestion_size['thumbnail'] ) ) {
				$avatar['sizes']['thumbnail'] = array(
					'height'      => $suggestion_size['thumbnail'][2],
					'width'       => $suggestion_size['thumbnail'][1],
					'url'         => str_replace( array( 'https:', 'http:' ), '', $suggestion_size['thumbnail'][0] ),
					'orientation' => $suggestion_size['thumbnail'][2] > $suggestion_size['thumbnail'][1] ? 'portrait' : 'landscape',
				);
			} else {
				return false;
			}
		}

		return $avatar;
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

		$object  = sanitize_key( $_POST['item_object'] );
		$item_id = absint( $_POST['item_id'] );

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
					'value'  => $avatar_types[ $object ],
				)
			),
		);

		$query = new WP_Query( $suggestions_args );

		$suggestions = array_map( array( $this, 'prepare_for_js' ), $query->posts );

		if ( ! empty( $suggestions ) ) {
			if ( 'group' == $object ) {
				$current_item_avatar = str_replace( array( 'https:', 'http:' ), '', groups_get_groupmeta( $item_id, 'group_avatar_choice', true ) );
			} else {
				$current_item_avatar = str_replace( array( 'https:', 'http:' ), '', bp_get_user_meta( $item_id, 'user_avatar_choice', true ) );
			}

			foreach ( $suggestions as $key => $suggestion ) {
				// If no thumbnail url, skip the suggestion
				if ( empty( $suggestion['sizes']['thumbnail']['url'] ) ) {
					unset( $suggestions[ $key ] );
					continue;
				}

				// Set the current user's avatar as selected
				if ( ! empty( $current_item_avatar ) && $current_item_avatar == $suggestion['sizes']['thumbnail']['url'] ) {
					$suggestions[ $key ]['selected'] = 1;
				}

				// Set the BuddyPress object (user or group)
				$suggestions[ $key ]['object'] = $object;
			}
		} else {
			wp_send_json_error();
		}

		if ( empty( $suggestions ) ) {
			wp_send_json_error();
		}

		// Send the successful response
		wp_send_json_success( array_filter( $suggestions ) );
	}

	/**
	 * Are we editing a user avatar suggestion ?
	 *
	 * Since 1.3.0, as BuddyPress 2.3.0 includes a new avatar UI, we will use it unless
	 * User avatar uploads have been disabled. In this case we will load the scripts
	 * introduced in 1.2.0
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 *
	 * @return bool true if editing a user avatar, false otherwise
	 */
	public function is_user_set_avatar() {
		$retval = false;

		if ( empty( $this->enable_users ) || ! bp_is_user() || ! bp_core_get_root_option( 'bp-disable-avatar-uploads' ) ) {
			return $retval;
		}

		if ( bp_is_user_change_avatar() && $this->enable_users ) {
			$retval = true;
		}

		return $retval;
	}

	/**
	 * Are we editing a group suggestion during group creation ?
	 *
	 * Since 1.3.0, as BuddyPress 2.3.0 includes a new avatar UI, we will use it unless
	 * Group avatar uploads have been disabled. In this case we will load the scripts
	 * introduced in 1.2.0
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 *
	 * @return bool true if editing a group avatar, false otherwise
	 */
	public function is_group_create_avatar() {
		$retval = false;

		if ( has_filter( 'bp_disable_group_avatar_uploads', array( 'Avatar_Suggestions_Group', 'display_avatars' ), 10, 1 ) ) {
			$reset_filter = true;
			remove_filter( 'bp_disable_group_avatar_uploads', array( 'Avatar_Suggestions_Group', 'display_avatars' ), 10, 1 );
 		}

		if ( empty( $this->enable_groups ) || ! bp_disable_group_avatar_uploads() ) {
			return $retval;
 		}

		// create
		if ( bp_is_group_create() && bp_is_group_creation_step( 'avatar-suggestions' ) ) {
			$retval = true;
		}

		if ( ! empty( $reset_filter ) ) {
			add_filter( 'bp_disable_group_avatar_uploads', array( 'Avatar_Suggestions_Group', 'display_avatars' ), 10, 1 );
		}

		return $retval;
	}

	/**
	 * Are we editing a group suggestion ?
	 *
	 * Since 1.3.0, as BuddyPress 2.3.0 includes a new avatar UI, we will use it unless
	 * Group avatar uploads have been disabled. In this case we will load the scripts
	 * introduced in 1.2.0
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 *
	 * @return bool true if editing a group avatar, false otherwise
	 */
	public function is_group_manage_avatar() {
		$retval = false;

		if ( has_filter( 'bp_disable_group_avatar_uploads', array( 'Avatar_Suggestions_Group', 'display_avatars' ), 10, 1 ) ) {
			$reset_filter = true;
			remove_filter( 'bp_disable_group_avatar_uploads', array( 'Avatar_Suggestions_Group', 'display_avatars' ), 10, 1 );
 		}

		if ( empty( $this->enable_groups ) || ! bp_disable_group_avatar_uploads() ) {
			return $retval;
 		}

		if ( bp_is_group_admin_page() && bp_is_group_admin_screen( 'avatar-suggestions' ) ) {
 			$retval = true;
 		}

		if ( ! empty( $reset_filter ) ) {
			add_filter( 'bp_disable_group_avatar_uploads', array( 'Avatar_Suggestions_Group', 'display_avatars' ), 10, 1 );
		}

		return $retval;
	}

	/**
	 * Register the script that will be used in the new avatar UI
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.3.0
	 */
	public function register_script() {
		$bp = buddypress();

		// Register the style
		wp_register_style(
			'bp-as-front-style',
			$bp->extend->avatar_suggestions->plugin_css . "bp-as-front{$this->min}.css",
			array(),
			$bp->extend->avatar_suggestions->version
		);

		// Register the script
		wp_register_script(
			'suggestions-bp-avatar',
			$bp->extend->avatar_suggestions->plugin_js . "avatar-suggestions-front{$this->min}.js",
			array( 'bp-avatar' ),
			$bp->extend->avatar_suggestions->version,
			true
		);
	}

	/**
	 * Make sure suggestions script and css will be loaded into the avatar UI
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.3.0
	 *
	 * @param  array  $script_data an array containing the avatar UI needed data
	 * @param  string $object the object the avatar is related to
	 * @return array  the script/css to load
	 */
	public function include_script( $script_data = array(), $object = '' ) {
		if ( 'group' === $object || 'user' === $object ) {

			$check = 'enable_' . $object . 's';
			if ( empty( $this->{$check} ) ) {
				return $script_data;
			}

			$script_data['extra_js'][]  = 'suggestions-bp-avatar';
			$script_data['extra_css'][] = 'bp-as-front-style';
		}

		return $script_data;
	}

	/**
	 * Add a new navigation into the avatar UI
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.3.0
	 *
	 * @param  array  $avatar_nav an array containing the avatar UI navs
	 * @param  string $object the object the avatar is related to
	 * @return array  the avatar nav including the suggestions one if needed
	 */
	public function suggestions_nav( $avatar_nav = array(), $object = '' ) {
		if ( 'group' === $object || 'user' === $object ) {

			$check = 'enable_' . $object . 's';
			if ( empty( $this->{$check} ) ) {
				return $avatar_nav;
			}

			$avatar_nav['avatar_suggestions'] = array(
				'id' => 'avatar_suggestions',
				'caption' => __( 'Suggestions', 'bp-avatar-suggestions' ),
				'order' => 15,
				'hide'  => (int) ! $avatar_nav['delete']['hide'],
			);
		}

		return $avatar_nav;
	}

	/**
	 * Add custom strings for the avatar UI
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.3.0
	 *
	 * @param  array  $strings an array containing the strings
	 * @return array  the strings including the suggestions ones if needed
	 */
	public function suggestions_strings( $strings = array() ) {

		if ( empty( $this->enable_groups ) && empty( $this->enable_users ) ) {
			return $strings;
		}

		$strings['avatar_suggestions'] = array(
			'nonce'              => wp_create_nonce( 'avatar_suggestions_selector' ),
			'avatarSaved'        => esc_html__( 'Success: profile photo saved.', 'bp-avatar-suggestions' ),
			'avatarNotSaved'     => esc_html__( 'Error: profile photo not saved.', 'bp-avatar-suggestions' ),
			'avatarRemoved'      => esc_html__( 'Success: profile photo removed.', 'bp-avatar-suggestions' ),
			'avatarNotRemoved'   => esc_html__( 'Error: profile photo not removed.', 'bp-avatar-suggestions' ),
			'fetching'           => esc_html__( 'Please wait, requesting available suggestions.', 'bp-avatar-suggestions' ),
			'fetchingFailed'     => esc_html__( 'There was a problem while requesting suggestions.', 'bp-avatar-suggestions' ),
			'fetchingSuccess'    => esc_html__( 'Click on one of the suggestions to use it as your profile photo.', 'bp-avatar-suggestions' ),
			'suggestionSelected' => esc_html__( 'To delete your profile photo, click on the selected suggestion.', 'bp-avatar-suggestions' ),
		);

		return $strings;
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

		// Bail if the user or the group already has an avatar
		if ( ( bp_is_user() && bp_get_user_has_avatar( bp_displayed_user_id() ) ) || ( bp_is_group() && bp_get_group_has_avatar() ) ) {
			return;
		}

		$suggestions_settings = $this->suggestions_strings();

		$suggestions_settings = array_merge( $suggestions_settings['avatar_suggestions'], array(
			'groupCreateContext'  => $this->is_group_create_avatar()
		) );

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

		wp_enqueue_style  ( 'bp-as-front-style' );
		wp_enqueue_script ( 'bp-as-backbone-front', $bp->extend->avatar_suggestions->plugin_js . "bp-as-backbone-front{$this->min}.js", array( 'wp-backbone' ), $bp->extend->avatar_suggestions->version, true );
		wp_localize_script( 'bp-as-backbone-front', 'avatar_suggestions_vars', $suggestions_settings );
	}

	/**
	 * JS Template for the suggestions
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.3.0
	 */
	public function js_template() {
		?>
		<script id="tmpl-suggestion" type="text/html">
			<img class="avatar avatar-suggestion-item" src="{{ data.sizes.thumbnail.url }}" data-suggestionid="{{data.id}}" />
		</script>
		<?php
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

		// Bail if the user or the group already has an avatar
		if ( ( bp_is_user() && bp_get_user_has_avatar( bp_displayed_user_id() ) ) || ( bp_is_group() && bp_get_group_has_avatar() ) ) {
			return;
		}

		?>
		<div id="avatar-suggestions-selector">

			<h4><?php esc_html_e( 'You can select one of the suggested avatars below', 'bp-avatar-suggestions' ) ;?></h4>
			<p class="description"><?php esc_html_e( 'Click to add or remove the suggested avatar. If a suggestion is selected, you will need to remove it before choosing another one.', 'bp-avatar-suggestions' ) ;?></p>

			<div class="avatar-suggestions-list"></div>
			<div class="avatar-suggestions-action"></div>
		</div>
		<?php
		// Load the JS Template
		$this->js_template();
	}

	/**
	 * Set suggestion
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 */
	public function set_suggestion() {
		$response = array(
			'feedback_code' => 'avatarNotSaved',
		);

		if ( empty( $_POST['item_id'] ) || empty( $_POST['avatar_url'] ) || empty( $_POST['item_object'] ) ) {
			wp_send_json_error( $response );
 		}

		check_ajax_referer( 'avatar_suggestions_selector', 'nonce' );

		$item_id = absint( $_POST['item_id'] );
		$avatar = 'http:' . $_POST['avatar_url'];
		if ( is_ssl() ) {
			$avatar = 'https:' . $_POST['avatar_url'];
		}

		$avatar  = esc_url_raw( $avatar );
		$object  = sanitize_key( $_POST['item_object'] );

		if ( 'group' == $object && bp_is_active( 'groups' ) ) {
			groups_update_groupmeta( $item_id, 'group_avatar_choice', $avatar );

			if ( bp_is_group_admin_page() ) {
				do_action( 'groups_screen_group_admin_avatar', $item_id );
			}
		} else if ( 'user' == $object ) {
			bp_update_user_meta( $item_id, 'user_avatar_choice', $avatar );

			do_action( 'xprofile_avatar_uploaded' );
		} else {
			wp_send_json_error( $response );
 		}

		// Send the response
		wp_send_json_success( array(
			'avatar' => html_entity_decode( bp_core_fetch_avatar( array(
				'object'  => $object,
				'item_id' => $item_id,
				'html'    => false,
				'type'    => 'full',
			) ) ),
			'feedback_code' => 'avatarSaved',
			'item_id'       => $item_id,
		) );
	}

	/**
	 * Remove suggestion
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.2.0
	 */
	public function remove_suggestion() {
		$response = array(
			'feedback_code' => 'avatarNotRemoved',
		);

		if ( empty( $_POST['item_id'] ) || empty( $_POST['item_object'] ) ) {
			wp_send_json_error( $response );
		}

		$item_id = absint( $_POST['item_id'] );
		$object  = sanitize_key( $_POST['item_object'] );

		check_ajax_referer( 'avatar_suggestions_selector', 'nonce' );

		if ( 'group' == $object && bp_is_active( 'groups' ) ) {
			groups_delete_groupmeta( $item_id, 'group_avatar_choice' );

			if ( bp_is_group_admin_page() ) {
				do_action( 'groups_screen_group_admin_avatar', $item_id );
			}
		} elseif ( 'user' == $object ) {
			bp_delete_user_meta( $item_id, 'user_avatar_choice' );

			do_action( 'bp_core_delete_existing_avatar' );
		} else {
			wp_send_json_error( $response );
 		}

		$this->avatar_removed = true;

		$avatar = html_entity_decode( bp_core_fetch_avatar( array(
			'object'  => $object,
			'item_id' => $item_id,
			'type'    => 'full',
			'html'    => false,
		) ) );

		$this->avatar_removed = false;

		// Send the response
		wp_send_json_success( array(
			'avatar'        => $avatar,
			'feedback_code' => 'avatarRemoved',
			'item_id'       => $item_id,
		) );
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
					} else {
						$group_choice = str_replace( array( 'https:', 'http:' ), '', $group_choice );
 					}

					if ( ! empty( $params['html'] ) ){
						$image = preg_replace('/src="([^"]*)"/i', 'src="' . $group_choice . '"', $image );
					} else {
						$image = $group_choice;
					}
				}
				break;

			case 'user' :
				if ( ! bp_get_user_has_avatar( $item_id ) ) {

					$user_choice = get_user_meta( $item_id, 'user_avatar_choice', true );

					if ( empty( $user_choice ) ) {
						return $image;
					} else {
						$user_choice = str_replace( array( 'https:', 'http:' ), '', $user_choice );
 					}

					if ( ! empty( $params['html'] ) ){
						$image = preg_replace('/src="([^"]*)"/i', 'src="' . $user_choice . '"', $image );
					} else {
						$image = $user_choice;
					}

				}
				break;
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
