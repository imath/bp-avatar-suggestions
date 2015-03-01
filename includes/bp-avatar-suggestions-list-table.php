<?php
/**
 * BP Avatar Suggestions List Table Class
 *
 * @package BP Avatar Suggestions
 * @subpackage list_table
 * @since   1.2.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WP_List_Table') ) :
/**
 * List table class for avatar suggestions settings page.
 *
 * @since   1.2.0
 */
class BP_Avatar_Suggestions_List_Table extends WP_List_Table {

	/**
	 * Suggestion counts.
	 *
	 * @since   1.2.0
	 *
	 * @access public
	 * @var int
	 */
	public $suggestion_counts = 0;

	/**
	 * Constructor.
	 *
	 * @since   1.2.0
	 */
	public function __construct() {
		// Define singular and plural labels, as well as whether we support AJAX.
		parent::__construct( array(
			'ajax'        => false,
			'plural'      => 'suggestions',
			'singular'    => 'suggestion',
		) );
	}

	/**
	 * Set up items for display in the list table.
	 *
	 * @since   1.2.0
	 */
	public function prepare_items() {
		$suggestions_per_page = $this->get_items_per_page( str_replace( '-', '_', "{$this->screen->id}_per_page" ) );
		$paged                = $this->get_pagenum();
		$post_parent          = buddypress()->extend->avatar_suggestions->avatar_post_id;

		if ( ! empty( $post_parent ) ) {
			$args = array(
				'offset'         => ( $paged - 1 ) * $suggestions_per_page,
				'posts_per_page' => $suggestions_per_page,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'post_type'      => 'attachment',
				'post_parent'    => $post_parent,
				'post_status'    => 'inherit',
			);

			if ( ! empty( $_REQUEST['avatar_type'] ) && 1 !== (int) $_REQUEST['avatar_type'] ) {
				$args['meta_key']   = '_bpas_avatar_type';
				$args['meta_value'] = (int) $_REQUEST['avatar_type'];
			}

			if ( isset( $_REQUEST['orderby'] ) ) {
				$args['orderby'] = $_REQUEST['orderby'];
			}

			if ( isset( $_REQUEST['order'] ) ) {
				$args['order'] = $_REQUEST['order'];
			}

			$suggestions = new WP_Query( $args );

			$this->items             = $suggestions->posts;
			$this->suggestion_counts = $suggestions->found_posts;
		} else {
			$this->items             = array();
			$this->suggestion_counts = 0;
		}

		$this->set_pagination_args( array(
			'total_items' => $this->suggestion_counts,
			'per_page'    => $suggestions_per_page,
		) );
	}

	/**
	 * Get the views (the links above the WP List Table).
	 *
	 * @since   1.2.0
	 */
	public function get_views() {
		$views = array();

		$base_arg = array(
			'page' => 'bp-avatar-suggestions',
		);

		$type = 0;
		if ( isset( $_REQUEST['avatar_type'] ) ) {
			$type = (int) $_REQUEST['avatar_type'];
		}

		$views['all'] = sprintf(
			'<a href="%1$s" class="%2$s">%3$s</a>',
			add_query_arg( $base_arg, bp_get_admin_url( 'admin.php' ) ),
			( empty( $type ) || 1 == $type ) ? 'current' : false,
			_x( 'All', 'All avatar suggestions type', 'bp-avatar-suggestions' )
		);

		if ( bp_is_active( 'xprofile' ) ) {
			$users_arg = array_merge( $base_arg, array( 'avatar_type' => 2 ) );

			$views['users'] = sprintf(
				'<a href="%1$s" class="%2$s">%3$s</a>',
				add_query_arg( $users_arg, bp_get_admin_url( 'admin.php' ) ),
				( ! empty( $type ) && 2 == $type ) ? 'current' : false,
				_x( 'Users', 'Users avatar suggestions type', 'bp-avatar-suggestions' )
			);
		}

		if ( bp_is_active( 'groups' ) ) {
			$groups_arg = array_merge( $base_arg, array( 'avatar_type' => 3 ) );

			$views['groups'] = sprintf(
				'<a href="%1$s" class="%2$s">%3$s</a>',
				add_query_arg( $groups_arg, bp_get_admin_url( 'admin.php' ) ),
				( ! empty( $type ) && 3 == $type ) ? 'current' : false,
				_x( 'Groups', 'Groups avatar suggestions type', 'bp-avatar-suggestions' )
			);
		}

		return apply_filters( 'bp_avatar_suggestions_get_views', $views );
	}

	/**
	 * Set the suggestion type
	 *
	 * @since   1.2.0
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' != $which  ) {
			return;
		}

		if ( ! bp_is_active( 'xprofile' ) && ! bp_is_active( 'groups' ) ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<?php if ( bp_current_user_can( 'bp_moderate' ) ) : ?>
			<label class="screen-reader-text" for="avatar_suggestions_type"><?php _e( 'Change avatar type to&hellip;', 'bp-avatar-suggestions' ) ?></label>
			<select name="avatar_suggestions_type" id="avatar_suggestions_type">
				<option value=""><?php _e( 'Change avatar type to&hellip;', 'bp-avatar-suggestions' ) ?></option>
				<option value="1"><?php _e( 'All', 'bp-avatar-suggestions' ) ?></option>

				<?php if ( bp_is_active( 'xprofile' ) ) : ?>
					<option value="2"><?php _e( 'Users', 'bp-avatar-suggestions' ) ?></option>
				<?php endif; ?>

				<?php if ( bp_is_active( 'groups' ) ) : ?>
					<option value="3"><?php _e( 'Groups', 'bp-avatar-suggestions' ) ?></option>
				<?php endif; ?>

			</select>
		<?php
			submit_button( __( 'Change' ), 'button', 'changeit', false );
		endif;

		do_action( 'bp_avatar_suggestions_set_avatar_type' );
		echo '</div>';
	}

	/**
	 * Specific avatar suggestions columns
	 *
	 * @since   1.2.0
	 */
	public function get_columns() {

		$columns = array(
			'cb'         => '<input type="checkbox" />',
			'avatar'     => '',
			'filename'   => __( 'File', 'bp-avatar-suggestions' ),
		);

		if ( bp_is_active( 'groups' ) || bp_is_active( 'xprofile' ) ) {
			$columns['type'] = __( 'Type', 'bp-avatar-suggestions' );
		}

		return apply_filters( 'bp_avatar_suggestions_manage_columns', $columns );
	}

	/**
	 * Bulk actions for suggestions.
	 *
	 * @since   1.2.0
	 */
	public function get_bulk_actions() {
		$actions = array();

		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$actions['delete'] = __( 'Delete', 'bp-avatar-suggestions' );
		}

		return $actions;
	}

	/**
	 * The text shown when no items are found.
	 *
	 * @since   1.2.0
	 */
	public function no_items() {
		esc_html_e( 'No avatar suggestions found.', 'bp-avatar-suggestions' );
	}

	/**
	 * The columns suggestions can be reordered with.
	 *
	 * @since   1.2.0
	 */
	public function get_sortable_columns() {
		return array();
	}

	/**
	 * Display suggestions rows.
	 *
	 * @since   1.2.0
	 */
	public function display_rows() {
		$style = '';
		foreach ( $this->items as $id => $suggestion_object ) {
			$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
			echo "\n\t" . $this->single_row( $suggestion_object, $style );
		}
	}

	/**
	 * Display an avatar suggestion row.
	 *
	 * @since   1.2.0
	 */
	public function single_row( $suggestion_object = null, $style = '' ) {
		echo '<tr' . $style . ' id="suggestion-' . esc_attr( $suggestion_object->ID ) . '">';
		echo $this->single_row_columns( $suggestion_object );
		echo '</tr>';
	}

	/**
	 * Markup for the checkbox used to select items for bulk actions.
	 *
	 * @since   1.2.0
	 */
	public function column_cb( $suggestion_object = null ) {
	?>
		<label class="screen-reader-text" for="suggestion_<?php echo intval( $suggestion_object->ID ); ?>"><?php printf( esc_html__( 'Select %s', 'bp-avatar-suggestions' ), $suggestion_object->post_title ); ?></label>
		<input type="checkbox" id="suggestion_<?php echo intval( $suggestion_object->ID ) ?>" name="allsuggestions[]" value="<?php echo esc_attr( $suggestion_object->ID ) ?>" />
		<?php
	}

	/**
	 * The avatar suggestion thumbnail
	 *
	 * @since   1.2.0
	 */
	public function column_avatar( $suggestion_object = null ) {
		$avatar = wp_get_attachment_image( $suggestion_object->ID, array( 80, 60 ), true );

		if ( ! empty( $avatar ) ) {
			echo $avatar;
		}
	}

	/**
	 * Display filename, if any.
	 *
	 * @since   1.2.0
	 */
	public function column_filename( $suggestion_object = null ) {
		// No action to set on the attachment title
		?>
		<strong><?php echo esc_html( $suggestion_object->post_title ); ?></strong>

		<p>
		<?php if ( preg_match( '/^.*?\.(\w+)$/', get_attached_file( $suggestion_object->ID ), $matches ) ) :
				echo esc_html( strtoupper( $matches[1] ) );
			else :
				echo strtoupper( str_replace( 'image/', '', get_post_mime_type() ) );
		endif; ?>
		</p>

		<?php
		$actions = array();

		if ( bp_current_user_can( 'bp_moderate' ) ) {
			// Build the delete link
			$delete_link = wp_nonce_url( add_query_arg(
				array(
					'page'          => 'bp-avatar-suggestions',
					'suggestion_id' => $suggestion_object->ID,
					'action'        => 'delete',
				),
				bp_get_admin_url( 'admin.php' )
			), 'bulk-suggestions' );

			$actions['delete'] = sprintf( '<a href="%1$s" class="delete">%2$s</a>', esc_url( $delete_link ), __( 'Delete', 'bp-avatar-suggestions' ) );
		}

		$actions = apply_filters( 'bp_avatar_suggestions_row_actions', $actions, $suggestion_object );

		echo $this->row_actions( $actions );
	}

	/**
	 * Display the avatar type
	 *
	 * @since BuddyPress (2.0.0)
	 */
	public function column_type( $suggestion_object = null ) {
		$type_key = (int) get_post_meta( $suggestion_object->ID, '_bpas_avatar_type', true );

		if ( 1 == $type_key ) {
			$type = _x( 'All', 'All avatar suggestions type', 'bp-avatar-suggestions' );
		} else if ( 2 == $type_key ) {
			$type = _x( 'Users', 'Users avatar suggestions type', 'bp-avatar-suggestions' );
		} else if ( 3 == $type_key ) {
			$type = _x( 'Groups', 'Groups avatar suggestions type', 'bp-avatar-suggestions' );
		} else {
			$type = __( 'Unknown', 'bp-avatar-suggestions' );
		}

		echo esc_html( $type );
	}

	/**
	 * Allow plugins to add their custom column.
	 *
	 * @since   1.2.0
	 */
	function column_default( $suggestion_object = null, $column_name = '' ) {
		return apply_filters( 'bp_avatar_suggestions_custom_column', '', $column_name, $suggestion_object );
	}
}

endif;
