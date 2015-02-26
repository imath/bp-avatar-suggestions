<?php
/*
Plugin Name: BP Avatar Suggestions
Plugin URI: http://imathi.eu/tag/bp-avatar-suggestions/
Description: Adds an avatar suggestions list to your BuddyPress powered community
Version: 1.2-alpha
Requires at least: 4.1
Tested up to: 4.1.1
License: GNU/GPL 2
Author: imath
Author URI: http://imathi.eu/
Text Domain: bp-avatar-suggestions
Domain Path: /languages/
*/

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

class Avatar_Suggestions {
	/**
	 * Instance of this class.
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Required BuddyPress version for this plugin.
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 *
	 * @var      string
	 */
	public static $required_bp_version = '2.2';

	/**
	 * BuddyPress config.
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 *
	 * @var      array
	 */
	public static $bp_config = array();

	/**
	 * Plugin name.
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 *
	 * @var      string
	 */
	public static $plugin_name = 'BP Avatar Suggestions';

	/**
	 * Initialize the plugin
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 */
	private function __construct() {
		// First you will set your plugin's globals
		$this->setup_globals();
		// Then include the needed files
		$this->includes();
		// Then hook to BuddyPress actions & filters
		$this->setup_hooks();
	}

	/**
	 * Return an instance of this class.
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function start() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Sets some globals for the plugin
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 */
	private function setup_globals() {

		/** Versions & domain ***********************************/
		$this->version       = '1.2-alpha';
		$this->domain        = 'bp-avatar-suggestions';

		/** Paths ***********************************************/
		$this->file          = __FILE__;
		$this->basename      = plugin_basename( $this->file );
		$this->plugin_dir    = plugin_dir_path( $this->file );
		$this->includes_dir  = trailingslashit( $this->plugin_dir . 'includes' );
		$this->lang_dir      = trailingslashit( $this->plugin_dir . 'languages' );

		/** Urls ***********************************************/
		$this->plugin_url    = plugin_dir_url( $this->file );
		$this->plugin_js     = trailingslashit( $this->plugin_url . 'js' );
		$this->plugin_css    = trailingslashit( $this->plugin_url . 'css' );

		$this->avatar_post_id = bp_get_option( 'bp_avatar_suggestions_post_id', 0 );
	}

	/**
	 * Include the component's files.
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 */
	private function includes() {
		if ( self::bail() || ( ! bp_is_active( 'xprofile' ) && ! bp_is_active( 'groups' ) ) ) {
			return;
		}

		require( $this->includes_dir . 'bp-avatar-suggestions-front.php' );

		if ( is_admin() ) {
			require( $this->includes_dir . 'bp-avatar-suggestions-admin.php' );
		}
	}

	/**
	 * Sets the key hooks to add an action or a filter to
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 */
	private function setup_hooks() {

		if ( ! self::bail() && ( bp_is_active( 'xprofile' ) || bp_is_active( 'groups' ) ) ) {
			// Load Front
			add_action( 'bp_loaded', 'bp_avatar_suggestions_front', 20 );

			// Make sure to intercept a deleted avatar
			add_action( 'delete_attachment', array( $this, 'cleanup_avatar_data'  ), 10, 1 );

			// Make sure to intercept an edited avatar
			add_action( 'edit_attachment',   array( $this, 'is_avatar_suggestion' ), 10, 1 );

			// Make sure to intercept a new avatar
			add_action( 'add_attachment',    array( $this, 'is_avatar_suggestion' ), 10, 1 );

			// Load Admin
			if ( is_admin() ) {
				add_action( 'bp_loaded', 'bp_avatar_suggestions_admin', 20 );
			}

			// loads the languages..
			add_action( 'bp_init', array( $this, 'load_textdomain' ), 5 );

		} else {
			// Display a warning message in network admin or admin
			add_action( self::$bp_config['network_active'] ? 'network_admin_notices' : 'admin_notices', array( $this, 'warning' ) );
		}
	}

	/**
	 * Remove all avatar datas related to an attachment
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.2.0
	 */
	public function cleanup_avatar_data( $attachment_id = 0 ) {
		// All avatar suggestions are saved in the root blog
		if ( ! bp_is_root_blog() || empty( $attachment_id ) ) {
			return;
		}

		$attachement = get_post( $attachment_id );

		// Make sure it's an avatar suggestion
		if ( empty( $attachement->post_parent ) || $this->avatar_post_id != $attachement->post_parent ) {
			return;
		}

		// Get the url of the avatar
		$avatar_url = wp_get_attachment_image_src( $attachment_id, array( 150, 150 ) );

		// Delete all user metas having the $avatar_url
		delete_metadata( 'user', false, 'user_avatar_choice', $avatar_url[0], true );
	}

	/**
	 * Make sure an avatar suggestion has a type
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.2.0
	 */
	public function is_avatar_suggestion( $attachment_id = 0 ) {
		// All avatar suggestions are saved in the root blog
		if ( ! bp_is_root_blog() || empty( $attachment_id ) ) {
			return;
		}

		$attachement = get_post( $attachment_id );

		// Make sure it's an avatar suggestion
		if ( empty( $attachement->post_parent ) || $this->avatar_post_id != $attachement->post_parent ) {
			return;
		}

		$has_meta = get_post_meta( $attachment_id, '_bpas_avatar_type', true );

		// Update to the 'All type'
		if ( empty( $has_meta ) ) {
			update_post_meta( $attachment_id, '_bpas_avatar_type', 1 );
		}
	}

	/**
	 * Display a warning message to admin
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 */
	public function warning() {
		$warnings = array();

		if( ! self::version_check() ) {
			$warnings[] = sprintf( __( '%1$s requires at least version %2$s of BuddyPress.', 'bp-avatar-suggestions' ), self::$plugin_name, self::$required_bp_version );
		}

		if ( ! empty( self::$bp_config ) ) {
			$config = self::$bp_config;
		} else {
			$config = self::config_check();
		}

		if ( bp_core_do_network_admin() && ! $config['network_status'] ) {
			$warnings[] = sprintf( __( '%s and BuddyPress need to share the same network configuration.', 'bp-avatar-suggestions' ), self::$plugin_name );
		}

		if ( ! empty( $warnings ) ) :
		?>
		<div id="message" class="error">
			<?php foreach ( $warnings as $warning ) : ?>
				<p><?php echo esc_html( $warning ) ; ?></p>
			<?php endforeach ; ?>
		</div>
		<?php
		endif;
	}

	/** Utilities *****************************************************************************/

	/**
	 * Checks BuddyPress version
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 */
	public static function version_check() {
		// taking no risk
		if ( ! defined( 'BP_VERSION' ) )
			return false;

		return version_compare( BP_VERSION, self::$required_bp_version, '>=' );
	}

	/**
	 * Checks if your plugin's config is similar to BuddyPress
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 */
	public static function config_check() {
		/**
		 * blog_status    : true if your plugin is activated on the same blog
		 * network_active : true when your plugin is activated on the network
		 * network_status : BuddyPress & your plugin share the same network status
		 */
		self::$bp_config = array(
			'blog_status'    => false,
			'network_active' => false,
			'network_status' => true,
		);

		if ( get_current_blog_id() == bp_get_root_blog_id() ) {
			self::$bp_config['blog_status'] = true;
		}

		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

		// No Network plugins
		if ( empty( $network_plugins ) ) {
			return self::$bp_config;
		}

		$plugin_basename = plugin_basename( __FILE__ );

		// Looking for BuddyPress and this plugin
		$check = array( buddypress()->basename, $plugin_basename );

		// Are they active on the network ?
		$network_active = array_diff( $check, array_keys( $network_plugins ) );

		// If result is 1, your plugin is network activated
		// and not BuddyPress or vice & versa. Config is not ok
		if ( count( $network_active ) == 1 ) {
			self::$bp_config['network_status'] = false;
		}

		self::$bp_config['network_active'] = isset( $network_plugins[ $plugin_basename ] );

		return self::$bp_config;
	}

	/**
	 * Bail if BuddyPress config is different than this plugin
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 */
	public static function bail() {
		$retval = false;

		$config = self::config_check();

		if ( ! self::version_check() || ( ! $config['blog_status'] && ! $config['network_status'] ) ) {
			$retval = true;
		}

		return $retval;
	}

	/**
	 * Loads the translation files
	 *
	 * @package BP Avatar Suggestions
	 * @since   1.1.0
	 *
	 * @uses get_locale() to get the language of WordPress config
	 * @uses load_texdomain() to load the translation if any is available for the language
	 */
	public function load_textdomain() {
		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/' . $this->domain . '/' . $mofile;

		// Look in global /wp-content/languages/bp-avatar-suggestions folder
		load_textdomain( $this->domain, $mofile_global );

		// Look in local /wp-content/plugins/bp-avatar-suggestions/languages/ folder
		load_textdomain( $this->domain, $mofile_local );
	}

}

// BuddyPress is loaded and initialized, let's start !
function bp_avatar_suggestions() {
	$bp = buddypress();

	if ( empty( $bp->extend ) ) {
		$bp->extend = new StdClass();
	}

	$bp->extend->avatar_suggestions = Avatar_Suggestions::start();
}
add_action( 'bp_include', 'bp_avatar_suggestions' );
