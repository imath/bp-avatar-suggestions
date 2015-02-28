<?php
/**
 * Load Groups class.
 *
 * @package BP Avatar Suggestions
 * @subpackage Groups
 * @since   1.2.0
 */
// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Avatar_Suggestions_Group' ) && class_exists( 'BP_Group_Extension' ) ) :
/**
 * BP Avatar Suggestions group class
 *
 * @package BP Avatar Suggestions
 * @subpackage Groups
 * @since   1.2.0
 */
class Avatar_Suggestions_Group extends BP_Group_Extension {

	public $screen  = null;

	/**
	 * Constructor
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Groups
	 * @since   1.2.0
	 */
	public function __construct() {
		/**
		 * Init the Group Extension vars
		 */
		$this->init_vars();
	}

	/** Group extension methods ***************************************************/

	/**
	 * Registers the BP Avatar Suggestions extension
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Groups
	 * @since   1.2.0
	 */
	public function init_vars() {
		$args = array(
			'slug'              => 'avatar-suggestions',
			'name'              => __( 'Photo', 'bp-avatar-suggestions' ),
			'visibility'        => 'private',
			'enable_nav_item'   => false,
			'screens'           => array(
				'admin' => array(
					'enabled' => false,
				),
				'create' => array(
					'position' => 20,
					'enabled'  => true,
				),
				'edit' => array(
					'position'          => 20,
					'enabled'           => true,
					'show_in_admin_bar' => true,
				),
			)
		);

        parent::init( $args );
	}

	/**
	 * The create screen method
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Groups
	 * @since   1.2.0
	 */
	public function create_screen( $group_id = null ) {
		?>
		<div class="left-menu">

			<?php bp_new_group_avatar(); ?>

		</div><!-- .left-menu -->

		<div class="main-column">

			<?php do_action( 'bp_after_group_avatar_creation_step' ); ?>

		</div><!-- .main-column -->
		<?php
	}

	/**
	 * The create screen save method
	 *
	 * Nothing to save all is done thanks to AJAX
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Groups
	 * @since   1.2.0
	 */
	public function create_screen_save( $group_id = null ) {}

	/**
	 * Group extension settings form
	 *
	 * BuddyPress is already adding the needed hook
	 * to load the suggestions editor, so no need to
	 * output anything.
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Groups
	 * @since   1.2.0
	 */
	public function edit_screen( $group_id = null ) {}

	/**
	 * We do not need any button on the edit screen
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Groups
	 * @since   1.2.0
	 *
	 * @return boolean false
	 */
	public static function has_submit_button( $screen = '' ) {
		return true;
	}

	/**
	 * Save the settings for the current the group
	 *
	 * Nothing to save all is done thanks to AJAX
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Groups
	 * @since   1.2.0
	 */
	public function edit_screen_save( $group_id = null ) {}

	/**
	 * Adds a Meta Box in Group's Administration screen
	 *
	 * Nothing to output
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Groups
	 * @since   1.2.0
	 */
	public function admin_screen( $group_id = null ) {}

	/**
	 * Saves the group settings (set in the Meta Box of the Group's Administration screen)
	 *
	 * Nothing to save
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Groups
	 * @since   1.2.0
	 */
	public function admin_screen_save( $group_id = null ) {}

	/**
	 * Nothing to display
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Groups
	 * @since   1.2.0
	 */
	public function display( $group_id = null ) {}

	/**
	 * We do not use group widgets
	 *
	 * @package BP Avatar Suggestions
	 * @subpackage Groups
	 * @since   1.2.0
	 *
	 * @return boolean false
	 */
	public function widget_display() {}
}

endif ;

/**
 * Registers the BP Avatar Suggestions group's component
 *
 * @package BP Avatar Suggestions
 * @subpackage Groups
 * @since   1.2.0
 *
 * @uses bp_register_group_extension() to register the group extension
 */
function bp_avatar_suggestions_group() {
	// Get BuddyPress instance
	$bp = buddypress();

	// Only to use when avatars are not completely
	// disabled and BuddyPress user uploads are disabled
	if( empty( $bp->avatar->show_avatars ) || empty( $bp->site_options['bp-disable-avatar-uploads'] ) ) {
		return;
	}

	bp_register_group_extension( 'Avatar_Suggestions_Group' );
}
add_action( 'bp_init', 'bp_avatar_suggestions_group' );
