<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
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
		$this->avatar_post_id = buddypress()->extend->avatar_suggestions->avatar_post_id;
		$this->use_old_ui     = bp_get_option( 'bp-avatar-suggestions-disable-backbone-ui', 0 );
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

		add_action( 'wp_ajax_bp_as_admin_avatar_delete', array( $this, 'suggestion_delete'  )         );
	}

	/**
	 * Add a setting to BuddyPress xprofile ones
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function settings() {
		// Allow avatar uploads
		add_settings_field(
			'bp-avatar-suggestions-disable-backbone-ui',
			__( 'Avatar Suggestions', 'bp-avatar-suggestions' ),
			array( $this, 'settings_callback' ),
			'buddypress',
			'bp_xprofile'
		);

		register_setting(
			'buddypress',
			'bp-avatar-suggestions-disable-backbone-ui',
			'intval'
		);
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

				$this->avatar_post_id = wp_insert_post( array(
					'post_title'   => __( 'BP Avatar Suggestions required post (do not edit or delete)' ),
					'post_name'    => 'bp-avatar-suggestions',
					'post_status'  => 'draft',
				) );

				// Set the post id to attach the avatar suggestions to.
				bp_update_option( 'bp_avatar_suggestions_post_id', $this->avatar_post_id );

				// Get all attachment ids
				$attachment_ids = bp_get_option( 'suggestion_list_avatar_array', array() );

				// Loop attachments to set their parent
				foreach ( $attachment_ids as $attachment_id ) {
					wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $this->avatar_post_id ) );
				}

				// Remove an option not used anymore
				bp_delete_option( 'bp-disable-avatar-suggestions' );
				bp_delete_option( 'suggestion_list_avatar_array' );
			}

			// Upgrade db version
			bp_update_option( 'bp-avatar-suggestions-version', $version );
		}
	}

	/**
	 * Setting callback function
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function settings_callback() {
		?>
		<input id="bp-avatar-suggestions-disable-backbone-ui" name="bp-avatar-suggestions-disable-backbone-ui" type="checkbox" value="1" <?php checked( $this->use_old_ui ); ?> />
		<label for="bp-avatar-suggestions-disable-backbone-ui"><?php _e( 'Use the old administration interface', 'bp-avatar-suggestions' ); ?></label>
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

		$bp = buddypress();

		wp_enqueue_script( 'media-upload' );
		add_thickbox();
		wp_enqueue_script ( 'bp-as-admin-js', $bp->extend->avatar_suggestions->plugin_js . 'bp-as-admin.js', array( 'jquery', 'media-upload', 'thickbox' ), $bp->extend->avatar_suggestions->version, true );
		wp_localize_script( 'bp-as-admin-js', 'bp_as_admin_vars', array(
			'bpas_post_id' => $this->avatar_post_id,
			'error'        => __( 'OOps something went wrong.', 'bp-avatar-suggestions' ),
			'bpasnonce'    => wp_create_nonce( 'delete_avatar_suggestion' ),
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
	 * Update the suggestion list if needed
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function admin_load() {}

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

	/**
	 * Display the admin (old interface)
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function admin_display() {
		$suggested_avatars = array();

		if ( ! empty( $this->avatar_post_id ) ) {
			$suggested_avatars = get_posts(  array(
				'post_type'   => 'attachment',
				'post_parent' => $this->avatar_post_id,
				'numberposts' => -1,
			) );
		}
		$message = false;
		?>
		<div class="wrap">
			<?php screen_icon( 'buddypress'); ?>

			<h2 class="nav-tab-wrapper"><?php bp_core_admin_tabs( __( 'Avatar Suggestions', 'bp-avatar-suggestions' ) ); ?></h2>

			<div style="margin-top:10px">

				<p class="description"><?php esc_html_e( 'Click to add as much avatar suggestions as needed, once done, simply close the lightbox window.', 'bp-avatar-suggestions' );?></p>
				<p class="submit clear"><a href="<?php echo esc_url( admin_url( 'media-upload.php' ) );?>" class="avatar_upload_image_button button-primary"><?php _e( 'Add an avatar', 'bp-avatar-suggestions' );?></a></p>

				<?php if ( count( $suggested_avatars ) >= 1 ) : ?>

					<div style="width:50%;">

						<table class="widefat">
							<thead>
								<tr>
									<th><?php _e( 'Avatars', 'bp-avatar-suggestions' );?></th>
									<th><?php _e( 'Actions', 'bp-avatar-suggestions' );?></th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<th><?php _e( 'Avatars', 'bp-avatar-suggestions' );?></th>
									<th><?php _e( 'Actions', 'bp-avatar-suggestions' );?></th>
								</tr>
							</tfoot>
							<tbody>
								<?php foreach( $suggested_avatars as $attachment ) :?>

									<?php $avatar = wp_get_attachment_image_src( $attachment->ID, array(50, 50) );?>

									<tr id="avatar-<?php echo $attachment->ID;?>">
										<td>
											<img src="<?php echo $avatar[0];?>" alt="avatar choice" id="avatar-<?php echo $attachment->ID;?>-avatar" width="<?php echo $avatar[1];?>" height="<?php echo $avatar[1];?>">
										</td>
										<td>
											<p><a href="#" class="avatar_delete_image_button button-secondary" data-attachmentid="<?php echo $attachment->ID;?>"><?php _e( 'Delete this avatar', 'bp-avatar-suggestions' );?></a></p>
											<input type="hidden" name="suggestion_list_avatar_ids[]" id="avatar-<?php echo $attachment->ID;?>-id" class="avatar_thumbnail_id" value="<?php echo $attachment->ID;?>">
										</td>
									</tr>

								<?php endforeach;?>
							</tbody>
						</table>
					</div>

				<?php endif;?>

			</div>
		</div>
		<?php
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
		$class = false;

		if ( strpos( get_current_screen()->id, 'bp-avatar-suggestions' ) !== false )
			$class = "nav-tab-active";
		?>
		<a href="<?php echo bp_get_admin_url( add_query_arg( array( 'page' => 'bp-avatar-suggestions' ), 'admin.php' ) );?>" class="nav-tab <?php echo $class;?>" style="margin-left:-6px"><?php esc_html_e( 'Avatar Suggestions', 'bp-avatar-suggestions' );?></a>
		<?php
	}

	/**
	 * Removes a suggestion
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function suggestion_delete() {
		$post_id = ! empty( $_POST['attachmentid'] ) ? absint( $_POST['attachmentid'] ) : 0;

		if ( empty( $post_id ) ) {
			return;
		}

		check_ajax_referer( 'delete_avatar_suggestion', 'nonce' );

		$result = wp_delete_attachment( $post_id, true );

		if ( ! empty( $result->ID ) ) {
			wp_die( $result->ID );
		} else {
			wp_die( -1 );
		}
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
