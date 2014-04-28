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
		$this->is_active      = buddypress()->extend->avatar_suggestions->is_active;
		$this->attachment_ids = bp_get_option( 'suggestion_list_avatar_array', array() );
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
		add_action( 'bp_register_admin_settings',       array( $this, 'settings'           )      );

		// update plugin's db version
		add_action( 'bp_admin_init',                    array( $this, 'maybe_update'       )      );

		if ( empty( $this->is_active ) )
			return;

		// javascript
		add_action( 'bp_admin_enqueue_scripts',          array( $this, 'enqueue_script'    )      );

		// Page
		add_action( bp_core_admin_hook(),                array( $this, 'admin_menu'        )      );

		add_action( 'admin_head',                        array( $this, 'admin_head'        ), 999 );

		add_action( 'bp_admin_tabs',                     array( $this, 'admin_tab'         )      );

		add_action( 'wp_ajax_bp_as_admin_avatar_delete', array( $this, 'suggestion_delete' )      );
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
			'bp-disable-avatar-suggestions',
			__( 'Avatar Suggestions', 'bp-avatar-suggestions' ),
			array( $this, 'settings_callback' ),
			'buddypress',
			'bp_xprofile'
		);

		register_setting(
			'buddypress', 
			'bp-disable-avatar-suggestions',
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
		if ( version_compare( bp_get_option( 'bp-avatar-suggestions-version', 0 ), buddypress()->extend->avatar_suggestions->version, '<' ) ) {
			//might be useful one of these days..
			bp_update_option( 'bp-avatar-suggestions-version', buddypress()->extend->avatar_suggestions->version );

			$this->is_active = 1;
			bp_update_option( 'bp-disable-avatar-suggestions', $this->is_active );
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
		<input id="bp-disable-avatar-suggestions" name="bp-disable-avatar-suggestions" type="checkbox" value="1" <?php checked( $this->is_active ); ?> />
		<label for="bp-disable-avatar-suggestions"><?php _e( 'Allow registered members to choose a suggested avatar', 'bp-avatar-suggestions' ); ?></label>
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
		if( strpos( get_current_screen()->id, 'bp-avatar-suggestions' ) === false )
			return;

		wp_enqueue_script('media-upload');
		add_thickbox();
		wp_register_script( 'bp-as-admin-js', buddypress()->extend->avatar_suggestions->plugin_js . 'bp-as-admin.js', array( 'jquery', 'media-upload', 'thickbox' ) );
		wp_enqueue_script ( 'bp-as-admin-js' );
		wp_localize_script( 'bp-as-admin-js', 'bp_as_admin_vars', array( 
			'error'     => __( 'OOps something went wrong.', 'bp-avatar-suggestions' ),
			'bpasnonce' => wp_create_nonce( 'delete_avatar_suggestion' ),
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
			__( 'BuddyPress Settings', 'buddypress' ),
			__( 'BuddyPress Settings', 'buddypress' ),
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
		if ( $plugin_page == 'bp-avatar-suggestions')
			$submenu_file = 'bp-components';
	}

	/**
	 * Update the suggestion list if needed
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function admin_load() {
		if ( isset( $_POST['bp-as-admin-submit'] ) ) {

			$listing_avatar = array();
			
			check_admin_referer( 'bp-as-admin-setup' );
			
			if ( ! empty( $_POST['suggestion_list_avatar_ids'] ) && count( $_POST['suggestion_list_avatar_ids'] ) >= 1 ) {
				$listing_avatar = $_POST['suggestion_list_avatar_ids'];
			}
			
			if ( ! empty( $_POST['avatar-0-id'] ) )
				$listing_avatar[] = $_POST['avatar-0-id'];
				
			sort( $listing_avatar );
			
			if ( count( $listing_avatar ) >= 1 ) {
				bp_update_option( 'suggestion_list_avatar_array', $listing_avatar );
				$redirect = add_query_arg( 'updated', 1, wp_get_referer() );
				bp_core_redirect( $redirect );
			}
		}
	}

	/**
	 * Display the admin
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Admin
	 * @since   1.1.0
	 */
	public function admin_display() {
		$suggested_avatars = array();

		if ( ! empty( $this->attachment_ids ) ) {
			$suggested_avatars = get_posts(  array(
				'post_type' => 'attachment',
				'include' => $this->attachment_ids
			) );
		}
		$message = false;

		$form_url = remove_query_arg(
			array(
				'updated'
			), $_SERVER['REQUEST_URI']
		);
		?>
		<div class="wrap">
			<?php screen_icon( 'buddypress'); ?>
			
			<h2 class="nav-tab-wrapper"><?php bp_core_admin_tabs( __( 'Avatar Suggestions', 'buddypress' ) ); ?></h2>
			
			<form action="<?php echo esc_url( $form_url ) ;?>" method="post" id="bp-admin-avatar-choose-form">
				
				<div style="margin-top:10px">
					
					<?php if ( count( $suggested_avatars ) >= 1 ) : ?>
					
						<div style="width:40%;float:left;">
							
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
				
					<div style="width:40%;float:left;margin-left:10px">
						
						<table class="widefat">
							<thead>
								<tr>
									<th colspan="2"><?php _e( 'New Avatar', 'bp-avatar-suggestions' );?></th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<th colspan="2"><?php _e( 'New Avatar', 'bp-avatar-suggestions' );?></th>
								</tr>
							</tfoot>
							<tbody>
								<tr id="avatar-0">
									<td id="avatar_image"></td>
									<td>
										<p class="submit clear"><a href="<?php echo esc_url( admin_url( 'media-upload.php' ) );?>" class="avatar_upload_image_button button-secondary"><?php _e( 'Add an avatar', 'bp-avatar-suggestions' );?></a></p>
										<input type="hidden" name="avatar-0-id" id="avatar-0-id" class="avatar_thumbnail_id">
									</td>
								</tr>
								<tr>
									<td colspan="2">
										<p class="description">
											<?php _e( 'To add an avatar, click on the &#39;Add an avatar&#39; button then in the thickbox window, upload your avatar. Once done, click on the insert into post button of the thickbox. The avatar will show at the left of the &#39;Add an avatar&#39; button. Finally, use the &#39;Save Settings&#39; button to update the avatar suggestions list.', 'bp-avatar-suggestions' );?>
										</p>
									</td>
								</tr>
								<tr>
									<td colspan="2">
										<p class="submit clear" style="text-align:center"><input class="button-primary" type="submit" name="bp-as-admin-submit" id="bp-as-admin-submit" value="<?php _e( 'Save Settings', 'bp-avatar-suggestions' ) ?>"/></p>
									</td>
								</tr>
							</tbody>
						</table>

					</div>
				
					<br style="clear:both">
					
				</div>

				<?php wp_nonce_field( 'bp-as-admin-setup' ); ?>

			</form>
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

		if ( empty( $post_id ) )
			return;

		check_ajax_referer( 'delete_avatar_suggestion', 'nonce' );
	
		//first we need to catch the url of the image
		$deleted_url = wp_get_attachment_image_src( $post_id, array(150, 150) );
		
		$result = wp_delete_attachment( $post_id, true );
		
		if ( ! empty( $result->ID ) ){
			
			foreach( $this->attachment_ids as $k => $v ) {
				if (  $v == $post_id ) {
					unset( $this->attachment_ids[$k] );
				}
			}
			
			if ( count( $this->attachment_ids ) > 0 ) {
				bp_update_option( 'suggestion_list_avatar_array', $this->attachment_ids );
			} else {
				bp_delete_option('suggestion_list_avatar_array');
			}
				
				
			// we also need to delete all the user metas with the deleted_url !
			delete_metadata('user', false, 'user_avatar_choice', $deleted_url[0], true);
			
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
