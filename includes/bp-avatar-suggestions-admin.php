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

		// Make sure only one tab will be displayed
		add_action( 'load-media-upload.php',             array( $this, 'set_media_upload'   )         );

		// javascript
		add_action( 'bp_admin_enqueue_scripts',          array( $this, 'enqueue_script'     )         );

		// Page
		add_action( bp_core_admin_hook(),                array( $this, 'admin_menu'         )         );

		add_action( 'admin_head',                        array( $this, 'admin_head'         ), 999    );

		add_action( 'bp_admin_tabs',                     array( $this, 'admin_tab'          )         );

		add_filter( 'set-screen-option',                 array( $this, 'screen_options'     ),  10, 3 );
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

			if ( version_compare( $db_version, '1.2-alpha', '<' ) ) {

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

		wp_enqueue_script( 'media-upload' );
		add_thickbox();
		wp_enqueue_script ( 'bp-as-admin-js', $bp->extend->avatar_suggestions->plugin_js . "bp-as-admin{$this->min}.js", array( 'jquery', 'media-upload', 'thickbox' ), $bp->extend->avatar_suggestions->version, true );
		wp_localize_script( 'bp-as-admin-js', 'bp_as_admin_vars', array(
			'bpas_post_id' => $this->avatar_post_id,
			'redirect'     => add_query_arg( 'page', 'bp-avatar-suggestions', bp_get_admin_url( 'admin.php' ) )
		) );
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
		<a href="<?php echo bp_get_admin_url( add_query_arg( array( 'page' => 'bp-avatar-suggestions' ), 'admin.php' ) );?>" class="nav-tab <?php echo $class;?>" style="margin-left:-6px"><?php esc_html_e( 'Avatar Suggestions', 'bp-avatar-suggestions' );?></a>
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

					<a href="<?php echo esc_url( admin_url( 'media-upload.php' ) );?>" class="avatar_upload_image_button add-new-h2"><?php echo esc_html_x( 'Add New', 'Avatar suggestions add button', 'bp-avatar-suggestions' ); ?></a>

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
	 * Set the media upload iframe for the old interface
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.2.0
	 */
	public function set_media_upload() {
		if ( empty( $_GET['bpas'] ) || 1 != $_GET['bpas'] ) {
			return;
		}

		add_filter( 'media_upload_tabs', array( $this, 'media_upload_tabs' ), 10, 1 );
		add_action( 'admin_head_media_upload_type_form', array( $this, 'media_upload_header' ) );
	}

	/**
	 * Make sure the old interface only displays the From my computer tab
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.2.0
	 */
	public function media_upload_tabs( $tabs = array() ) {
		return array_intersect_key( $tabs, array( 'type' => true ) );
	}

	/**
	 * Print some arbitrary css rules in the media upload iframe (old interface)
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.2.0
	 */
	public function media_upload_header() {
		?>
		<style type="text/css" media="screen">
		/*<![CDATA[*/

			/* Bubble style for Main Post type menu */
			#media-items a.toggle,
			#media-items table.slidetoggle thead.media-item-info,
			#media-items table.slidetoggle tbody,
			.upload-flash-bypass,
			.ml-submit #save {
				display:none;
			}

		/*]]>*/
		</style>
		<?php
	}
}

/**
 * Stars Admin.
 *
 * @package BP Avatar Suggestions
 * @subpackage Admin
 * @since   1.1.0
 */
function bp_avatar_suggestions_admin() {
	return Avatar_Suggestions_Admin::start();
}
