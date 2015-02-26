<?php
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
		$this->avatar_post_id = buddypress()->extend->avatar_suggestions->avatar_post_id;
		$this->enable_users   = bp_get_option( 'bp-avatar-suggestions-enable-users', 1 );
		$this->enable_groups  = bp_get_option( 'bp-avatar-suggestions-enable-groups', 1 );
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
		add_action( 'bp_after_profile_avatar_upload_content', array( $this, 'suggestions_selector' ) );

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
			$current_item_avatar = bp_get_user_meta( absint( $_POST['item_id'] ), 'user_avatar_choice', true );

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
	 * Enqueue script
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Front
	 * @since   1.1.0
	 */
	public function enqueue_script() {
		// Bail if we're not on the change-avatar page
		if ( ! bp_is_user_change_avatar() || ! $this->enable_users || ( 'crop-image' == bp_get_avatar_admin_step() ) ) {
			return false;
		}

		$suggestions_settings = array(
			'nonce'               => wp_create_nonce( 'avatar_suggestions_selector' ),
			'avatarSaved'         => esc_html__( 'Success: Avatar saved.', 'bp-avatar-suggestions' ),
			'avatarNotSaved'      => esc_html__( 'Error: Avatar not saved.', 'bp-avatar-suggestions' ),
			'avatarRemoved'       => esc_html__( 'Success: Avatar removed.', 'bp-avatar-suggestions' ),
			'avatarNotRemoved'    => esc_html__( 'Error: Avatar not removed.', 'bp-avatar-suggestions' ),
		);

		if ( bp_is_user() ) {

			if ( bp_get_user_has_avatar( bp_displayed_user_id() ) ) {
				return;
			}

			$suggestions_settings['item_object'] = 'user';
			$suggestions_settings['item_id']     = bp_displayed_user_id();
		} else if ( bp_is_group() ) {
			$suggestions_settings['item_object'] = 'group';
			$suggestions_settings['item_id']     = bp_get_current_group_id();
		}

		wp_enqueue_style  ( 'bp-as-front-style', buddypress()->extend->avatar_suggestions->plugin_css . 'bp-as-front.css', array(), buddypress()->extend->avatar_suggestions->version );
		wp_enqueue_script ( 'bp-as-backbone-front', buddypress()->extend->avatar_suggestions->plugin_js . 'bp-as-backbone-front.js', array( 'wp-backbone' ), buddypress()->extend->avatar_suggestions->version, true );
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
		// Bail if we're not on the change-avatar page
		if ( ! bp_is_user_change_avatar() || ! $this->enable_users || bp_get_user_has_avatar( bp_displayed_user_id() ) || ( 'crop-image' == bp_get_avatar_admin_step() ) ) {
			return false;
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

		$user_id = absint( $_POST['item_id'] );
		$avatar = esc_url( $_POST['avatar_url'] );

		check_ajax_referer( 'avatar_suggestions_selector', 'nonce' );

		bp_update_user_meta( $user_id, 'user_avatar_choice', $avatar );

		do_action( 'xprofile_avatar_uploaded' );

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

		check_ajax_referer( 'avatar_suggestions_selector', 'nonce' );

		bp_delete_user_meta( $_POST['item_id'], 'user_avatar_choice' );

		do_action( 'bp_core_delete_existing_avatar' );

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
		if ( ! $this->enable_users || 'user' != $params['object'] || ! empty( $params['no_grav'] ) || empty( $params['item_id'] ) || ! empty( $this->avatar_removed ) ) {
			return $image;
		}

		$item_id = absint( $params['item_id'] );

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
