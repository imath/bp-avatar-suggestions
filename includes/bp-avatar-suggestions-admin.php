<?php
/**
 * The Admin class.
 *
 * @package BP Avatar Suggestions
 * @subpackage Admin
 * @since   1.1.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Load Admin class.
 *
 * @package BP Avatar Suggestions
 * @subpackage Admin
 * @since   1.1.0
 */
class Avatar_Suggestions_Admin {

	/**
	 * Setup Admin.
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 *
	 * @uses buddypress() to get BuddyPress main instance.
	 */
	public static function start() {
		// Get BuddyPress instance
		$bp = buddypress();

		if( empty( $bp->extend->avatar_suggestions->admin ) ) {
			$bp->extend->avatar_suggestions->admin = new self;
		}

		return $bp->extend->avatar_suggestions->admin;
	}

	/**
	 * Constructor method.
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
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
	 * @subpackage Admin
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
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	private function setup_hooks() {
		// Settings
		add_action( 'bp_register_admin_settings',        array( $this, 'settings'           )         );

		// update plugin's db version
		add_action( 'bp_admin_init',                     array( $this, 'maybe_update'       )         );

		add_action( 'bp_admin_enqueue_scripts',          array( $this, 'register_script'    ),   2    );

		// javascript
		add_action( 'bp_admin_enqueue_scripts',          array( $this, 'enqueue_script'     )         );

		// Page
		add_action( bp_core_admin_hook(),                array( $this, 'admin_menu'         )         );

		add_action( 'admin_head',                        array( $this, 'admin_head'         ), 999    );

		add_action( 'bp_admin_tabs',                     array( $this, 'admin_tab'          )         );

		add_filter( 'set-screen-option',                 array( $this, 'screen_options'     ),  10, 3 );

		// Suggestions Upload
		add_action( 'wp_ajax_avatar_suggestions_upload', array( $this, 'handle_upload'      )         );
	}

	/**
	 * Bail if avatars are not enabled
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
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
	 * Add a setting to BuddyPress xprofile ones
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function settings() {
		if ( $this->bail() ) {
			return;
		}

		$components = array(
			'users'  => 'bp_xprofile',
			'groups' => 'bp_groups',
		);

		foreach ( $components as $key => $setting_section ) {

			// Allow suggestions
			add_settings_field(
				"bp-avatar-suggestions-enable-{$key}",
				__( 'Avatar Suggestions', 'bp-avatar-suggestions' ),
				array( $this, "settings_callback_{$key}" ),
				'buddypress',
				$setting_section
			);

			register_setting(
				'buddypress',
				"bp-avatar-suggestions-enable-{$key}",
				'intval'
			);
		}
	}

	/**
	 * Update plugin version if needed
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function maybe_update() {
		$db_version = bp_get_option( 'bp-avatar-suggestions-version', 0 );
		$version    = buddypress()->extend->avatar_suggestions->version;

		if ( version_compare( $db_version, $version, '<' ) ) {

			if ( version_compare( $db_version, '1.2.0', '<' ) ) {

				if ( empty( $this->avatar_post_id ) ) {
					$this->avatar_post_id = wp_insert_post( array(
						'post_title'   => __( 'BP Avatar Suggestions required post (do not edit or delete)', 'bp-avatar-suggestions' ),
						'post_name'    => 'bp-avatar-suggestions',
						'post_status'  => 'draft',
					) );
				}

				// Set the post id to attach the avatar suggestions to.
				bp_update_option( 'bp_avatar_suggestions_post_id', $this->avatar_post_id );

				// Get all attachment ids
				$attachment_ids = bp_get_option( 'suggestion_list_avatar_array', array() );

				// Loop attachments to set their parent
				foreach ( $attachment_ids as $attachment_id ) {
					wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $this->avatar_post_id ) );
					update_post_meta( $attachment_id, '_bpas_avatar_type', 1 );
				}

				// Remove an option not used anymore
				bp_delete_option( 'bp-disable-avatar-suggestions' );
				bp_delete_option( 'suggestion_list_avatar_array' );

				// Init new options
				bp_update_option( 'bp-avatar-suggestions-enable-users', $this->enable_users );
				bp_update_option( 'bp-avatar-suggestions-enable-groups', $this->enable_groups );
			}

			// Upgrade db version
			bp_update_option( 'bp-avatar-suggestions-version', $version );
		}
	}

	/**
	 * Users Setting callback function
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.2.0
	 */
	public function settings_callback_users() {
		?>
		<input id="bp-avatar-suggestions-enable-users" name="bp-avatar-suggestions-enable-users" type="checkbox" value="1" <?php checked( $this->enable_users ); ?> />
		<label for="bp-avatar-suggestions-enable-users"><?php _e( 'Enable suggestions for users', 'bp-avatar-suggestions' ); ?></label>
		<?php
	}

	/**
	 * Groups Setting callback function
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.2.0
	 */
	public function settings_callback_groups() {
		?>
		<input id="bp-avatar-suggestions-enable-groups" name="bp-avatar-suggestions-enable-groups" type="checkbox" value="1" <?php checked( $this->enable_groups ); ?> />
		<label for="bp-avatar-suggestions-enable-groups"><?php _e( 'Enable suggestions for groups', 'bp-avatar-suggestions' ); ?></label>
		<?php
	}

	/**
	 * Register admin scripts
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.3.0
	 */
	public function register_script() {
		$bp = buddypress();

		// Register the style
		wp_register_style(
			'bp-as-admin-style',
			$bp->extend->avatar_suggestions->plugin_css . "bp-as-admin{$this->min}.css",
			array( 'bp-avatar' ),
			$bp->extend->avatar_suggestions->version
		);

		// Register the script
		wp_register_script(
			'bp-as-admin-js',
			$bp->extend->avatar_suggestions->plugin_js . "bp-as-admin{$this->min}.js",
			array(),
			$bp->extend->avatar_suggestions->version,
			true
		);

		// Include some data to it
		wp_localize_script( 'bp-as-admin-js', 'bp_as_admin_vars', array(
			'redirect'     => esc_url( add_query_arg( 'page', 'bp-avatar-suggestions', bp_get_admin_url( 'admin.php' ) ) )
		) );
	}

	/**
	 * Enqueue script
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function enqueue_script() {
		// Bail if we're not on the change-avatar page
		if ( strpos( get_current_screen()->id, 'bp-avatar-suggestions' ) === false ) {
			return;
		}

		// Get BuddyPress instance
		$bp = buddypress();

		wp_enqueue_style ( 'thickbox' );
		wp_enqueue_script( 'media-upload' );

		// Get Our Uploader
		bp_attachments_enqueue_scripts( 'Avatar_Suggestions_Attachment' );
	}

	/**
	 * Set the plugin's page & eventually update db version
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function admin_menu() {
		if ( $this->bail() ) {
			return;
		}

		$page  = bp_core_do_network_admin()  ? 'settings.php' : 'options-general.php';

		$hook_as = add_submenu_page(
			$page,
			__( 'Avatar Suggestions Settings', 'bp-avatar-suggestions' ),
			__( 'Avatar Suggestions Settings', 'bp-avatar-suggestions' ),
			'manage_options',
			'bp-avatar-suggestions',
			array( $this, 'admin_display' )
		);

		add_action( "admin_head-$hook_as", array( $this, 'modify_highlight' ) );
		add_action( "load-$hook_as",       array( $this, 'admin_load'       ) );
	}

	/**
	 * Load the Suggestion WP List table.
	 *
	 * @since   1.2.0
	 */
	public static function get_list_table_class( $class = '' ) {
		if ( empty( $class ) ) {
			return;
		}

		if ( ! class_exists( 'WP_List_Table') ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
		}

		require_once( buddypress()->extend->avatar_suggestions->includes_dir . 'bp-avatar-suggestions-list-table.php' );

		return new $class();
	}

	/**
	 * Set pagination.
	 *
	 * @since   1.2.0
	 */
	public function screen_options( $value = 0, $option = '', $new_value = 0 ) {
		if ( 'settings_page_bp_avatar_suggestions_network_per_page' != $option && 'settings_page_bp_avatar_suggestions_per_page' != $option ) {
			return $value;
		}

		// Per page
		$new_value = (int) $new_value;
		if ( $new_value < 1 || $new_value > 999 ) {
			return $value;
		}

		return $new_value;
	}

	/**
	 * Update the suggestion list if needed
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function admin_load() {
		$this->suggestion_list_table = self::get_list_table_class( 'BP_Avatar_Suggestions_List_Table' );

		// Build redirection URL
		$redirect_to = add_query_arg(
			array(
				'page' => 'bp-avatar-suggestions',
			),
			bp_get_admin_url( 'admin.php' )
		);

		// Get action
		$doaction = bp_admin_list_table_current_bulk_action();

		do_action( 'bp_avatar_suggestions_admin_load', $doaction, $_REQUEST, $redirect_to );

		if ( ( ! empty( $doaction ) && -1 != $doaction ) || ! empty( $_POST['changeit'] ) ) {

			check_admin_referer( 'bulk-suggestions' );
			$suggestions = array();

			if ( ! empty( $_GET['suggestion_id'] ) ) {
				$suggestions = wp_parse_id_list( $_GET['suggestion_id'] );
			} else if ( ! empty( $_POST['allsuggestions'] ) ) {
				$suggestions = wp_parse_id_list( $_POST['allsuggestions'] );
			}

			if ( 'delete' == $doaction && ! empty( $suggestions ) ) {

				// Delete avatar
				foreach ( $suggestions as $suggestion_id ) {
					wp_delete_attachment( $suggestion_id, true );
				}

				wp_safe_redirect( add_query_arg( 'deleted', count( $suggestions ), $redirect_to ) );
				exit();
			} else if ( ! empty( $_POST['avatar_suggestions_type'] ) && ! empty( $suggestions ) ) {

				// Update avatar types
				foreach ( $suggestions as $suggestion_id ) {
					update_post_meta( $suggestion_id, '_bpas_avatar_type', (int) $_POST['avatar_suggestions_type'] );
				}

				wp_safe_redirect( add_query_arg( 'changed', count( $suggestions ), $redirect_to ) );
				exit();
			}
		}

		// per_page screen option
		add_screen_option( 'per_page', array( 'label' => _x( 'Suggestions', 'Suggestions per page (screen options)', 'bp-avatar-suggestions' ) ) );

		// Set help tabs
		get_current_screen()->add_help_tab( array(
			'id'      => 'bp-avatar-suggestions-overview',
			'title'   => __( 'Overview', 'bp-avatar-suggestions' ),
			'content' =>
			'<p>' . __( 'This is the administration screen for avatar suggestions.', 'bp-avatar-suggestions' ) . '</p>' .
			'<p>' . __( 'From the screen options, you can customize the displayed columns and the pagination of this screen.', 'bp-avatar-suggestions' ) . '</p>' .
			'<p>' . __( 'You can add a new suggestion by clicking on the &quot;Add new&quot; button to open the uploader window.', 'bp-avatar-suggestions' ) . '&nbsp;' .
			__( ' Send your image files from this window and once every image has been uploaded, simply close the uploader window to refresh the list of suggestions.', 'bp-avatar-suggestions' ) . '</p>'
		) );

		get_current_screen()->add_help_tab( array(
			'id'      => 'bp-avatar-suggestions-actions',
			'title'   => __( 'Actions', 'bp-avatar-suggestions' ),
			'content' =>
			'<p>' . __( 'Hovering over a row in the suggestions list will display the delete link to eventually remove an avatar.', 'bp-avatar-suggestions' ) . '</p>' .
			'<p>' . __( 'If you need to remove more than one avatar, you can use the Delete Bulk action.', 'bp-avatar-suggestions' ) . '</p>' .
			'<p>' . __( 'To change the avatar type, activate the corresponding checkboxes and use the &quot;Change avatar type&quot; dropdown to select the type before clicking on the &quot;Change&quot; button.', 'bp-avatar-suggestions' ) . '</p>'
		) );

		// Help panel - sidebar links
		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'bp-avatar-suggestions' ) . '</strong></p>' .
			'<p>' . __( '<a href="https://wordpress.org/support/plugin/bp-avatar-suggestions">Support Forums</a>', 'bp-avatar-suggestions' ) . '</p>'
		);
	}

	/**
	 * Modify highlighted menu
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function modify_highlight() {
		global $plugin_page, $submenu_file;

		// This tweaks the Settings subnav menu to show only one BuddyPress menu item
		if ( $plugin_page == 'bp-avatar-suggestions') {
			$submenu_file = 'bp-components';
		}
	}

	/**
	 * Hide submenu
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function admin_head() {
		$page  = bp_core_do_network_admin()  ? 'settings.php' : 'options-general.php';

		remove_submenu_page( $page, 'bp-avatar-suggestions' );
	}

	/**
	 * Suggestions tab
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function admin_tab() {
		if ( $this->bail() ) {
			return;
		}

		$class = false;

		if ( strpos( get_current_screen()->id, 'bp-avatar-suggestions' ) !== false ) {
			$class = "nav-tab-active";
		}
		?>
		<a href="<?php echo esc_url( bp_get_admin_url( add_query_arg( array( 'page' => 'bp-avatar-suggestions' ), 'admin.php' ) ) );?>" class="nav-tab <?php echo $class;?>" style="margin-left:-6px"><?php esc_html_e( 'Avatar Suggestions', 'bp-avatar-suggestions' );?></a>
		<?php
	}

	/**
	 * Display the admin
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function admin_display() {
		// Prepare the group items for display
		$this->suggestion_list_table->prepare_items();

		$form_url = add_query_arg(
			array(
				'page' => 'bp-avatar-suggestions',
			),
			bp_get_admin_url( 'admin.php' )
		);

		// User feedback
		$message = false;
		if ( ! empty( $_GET['deleted'] ) ) {
			$message = sprintf(
				_nx( '%s suggestion was deleted.', '%s suggestions were deleted.',
				absint( $_GET['deleted'] ),
				'suggestion deleted',
				'bp-avatar-suggestions'
			), number_format_i18n( absint( $_GET['deleted'] ) ) );
		} else if ( ! empty( $_GET['changed'] ) ) {
			$message = sprintf(
				_nx( '%s suggestion had their type changed.', '%s suggestions had their type changed.',
				absint( $_GET['changed'] ),
				'suggestion type edited',
				'bp-avatar-suggestions'
			), number_format_i18n( absint( $_GET['changed'] ) ) );
		}
		?>

		<div class="wrap">

			<h2 class="nav-tab-wrapper"><?php bp_core_admin_tabs( __( 'Avatar Suggestions', 'bp-avatar-suggestions' ) ); ?></h2>

			<?php if ( ! empty( $message ) ) : ?>
				<div id="message" class="updated"><p><?php echo esc_html( $message ); ?></p></div>
			<?php
			unset( $message );
			endif; ?>

			<h3><?php _e( 'Suggestions', 'bp-avatar-suggestions' ); ?>

				<?php if ( bp_current_user_can( 'upload_files' ) ) : ?>
					<a href="#TB_inline?width=800px&height=400px&inlineId=bp-avatar-suggestions-uploader" title="<?php esc_attr_e( 'Add new suggestion(s)', 'bp-avatar-suggestions' );?>" class="thickbox avatar_upload_image_button add-new-h2">
						<?php echo esc_html_x( 'Add New', 'Avatar suggestions add button', 'bp-avatar-suggestions' ); ?>
					</a>
					<div id="bp-avatar-suggestions-uploader" style="display:none;">
						<?php /* Markup for the uploader */ ?>
							<div class="bp-avatar-suggestions"></div>
							<div class="bp-avatar-status"></div>

							<?php bp_attachments_get_template_part( 'uploader' );
						/* Markup for the uploader */ ?>
					</div>

				<?php endif; ?>
			</h3>

			<?php $this->suggestion_list_table->views(); ?>

			<form id="bp-avatar-suggestions-form" action="<?php echo esc_url( $form_url );?>" method="post">
				<?php $this->suggestion_list_table->display(); ?>
			</form>

		</div>
		<?php
	}

	/**
	 * Upload the suggestions as WordPress attachments
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.3.0
	 */
	public function handle_upload() {
		// Bail if not a POST action
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			wp_die();
		}

		/**
		 * Sending the json response will be different if
		 * the current Plupload runtime is html4
		 */
		$is_html4 = false;
		if ( ! empty( $_POST['html4' ] ) ) {
			$is_html4 = true;
		}

		// Check the nonce
		check_admin_referer( 'bp-uploader' );

		// Init the BuddyPress parameters
		$bp_params = array();

		// We need it to carry on
		if ( ! empty( $_POST['bp_params' ] ) ) {
			$bp_params = $_POST['bp_params' ];
		} else {
			bp_attachments_json_response( false, $is_html4 );
		}

		// Check params
		if ( empty( $bp_params['object'] ) || empty( $bp_params['item_id'] ) || 'post' !== $bp_params['object'] || (int) $this->avatar_post_id !== (int) $bp_params['item_id'] ) {
			bp_attachments_json_response( false, $is_html4 );
		}

		// Capability check
		if ( ! bp_current_user_can( 'upload_files' ) ) {
			bp_attachments_json_response( false, $is_html4 );
		}

		$suggestion_attachment = new Avatar_Suggestions_Attachment();
		$suggestion            = $suggestion_attachment->upload( $_FILES );

		// Error while trying to upload the file
		if ( ! empty( $suggestion['error'] ) ) {
			bp_attachments_json_response( false, $is_html4, array(
				'type'    => 'upload_error',
				'message' => $suggestion['error'],
			) );
		}

		/**
		 * Simulate WordPress's media_handle_upload() function
		 */
		$time = current_time( 'mysql' );
		$post = get_post( $bp_params['item_id'] );

		if ( is_a( $post, 'WP_Post' ) ) {
			if ( substr( $post->post_date, 0, 4 ) > 0 ) {
				$time = $post->post_date;
			}
		}

		$name_parts = pathinfo( $suggestion['file'] );
		$url        = $suggestion['url'];
		$type       = $suggestion['type'];
		$file       = $suggestion['file'];
		$title      = $name_parts['filename'];
		$content    = '';
		$excerpt    = '';
		$image_meta = @wp_read_image_metadata( $file );

		if ( 0 === strpos( $type, 'image/' ) && ! empty( $image_meta ) ) {
			if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
				$title = $image_meta['title'];
			}

			if ( trim( $image_meta['caption'] ) ) {
				$excerpt = $image_meta['caption'];
			}
		}

		// Construct the attachment array
		$attachment = array(
			'post_mime_type' => $type,
			'guid'           => $url,
			'post_parent'    => $post->ID,
			'post_title'     => $title,
			'post_content'   => $content,
			'post_excerpt'   => $excerpt,
		);

		// Save the data
		$attachment_id = wp_insert_attachment( $attachment, $file, $post->ID );
		if ( ! is_wp_error( $attachment_id ) ) {

			// Add the avatar image sizes
			add_image_size( 'bp_avatar_suggestions', bp_core_avatar_full_width(), bp_core_avatar_full_height(), true );
			add_filter( 'intermediate_image_sizes_advanced', array( $this, 'restrict_image_sizes' ), 10, 1 );

			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file ) );

			// Remove it so no other attachments will be affected
			remove_image_size( 'bp_avatar_suggestions' );
			remove_filter( 'intermediate_image_sizes_advanced', array( $this, 'restrict_image_sizes' ), 10, 1 );

			// Try to get the bp_avatar_suggestions size
			$avatar = wp_get_attachment_image_src( $attachment_id, 'bp_avatar_suggestions', true );
			if ( ! empty( $avatar[0] ) ) {
				$url = $avatar[0];
			}

			// Finally return the avatar to the editor
			bp_attachments_json_response( true, $is_html4, array(
				'name'      => esc_html( $title ),
				'url'       => esc_url_raw( $url ),
			) );
		} else {
			bp_attachments_json_response( false, $is_html4, array(
				'type'    => 'upload_error',
				'message' => esc_html__( 'Something went wrong while saving the image', 'bp-avatar-suggestions' ),
			) );
		}
	}

	/**
	 * Only keep the avatar suggestions size and thumbnail one for backcompat
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.3.0
	 *
	 * @param  array $sizes the available WordPress image sizes
	 */
	public function restrict_image_sizes( $sizes = array() ) {
		return array_intersect_key( $sizes, array( 'thumbnail' => true, 'bp_avatar_suggestions' => true ) );
	}
}

/**
 * Start Admin.
 *
 * @package BP Avatar Suggestions
 * @subpackage Admin
 * @since   1.1.0
 */
function bp_avatar_suggestions_admin() {
	return Avatar_Suggestions_Admin::start();
}
