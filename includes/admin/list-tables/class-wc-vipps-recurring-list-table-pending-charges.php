<?php
/**
 * Vipps Recurring List Table
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WC_Vipps_Recurring_Admin_List_Pending_Charges extends WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @param array $args An associative array of arguments.
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @since 3.1.0
	 *
	 */
	public function __construct( $args = array() ) {
		parent::__construct(
			array(
				'singular' => 'pending charge',
				'plural'   => 'pending charges',
				'screen'   => isset( $args['screen'] ) ? $args['screen'] : null,
			)
		);
	}

	/**
	 * Check the current user's permissions.
	 *
	 * @return bool
	 * @since 3.1.0
	 *
	 */
	public function ajax_user_can() {
		return current_user_can( 'activate_plugins' );
	}

	/**
	 * Prepare the pending charges list for display.
	 *
	 * @throws Exception
	 * @global string $ordersearch
	 */
	public function prepare_items() {
		global $ordersearch;

		$ordersearch = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';

		$per_page        = 'wc_vipps_recurring_pending_charges_per_page';
		$orders_per_page = $this->get_items_per_page( $per_page );

		$paged = $this->get_pagenum();

		$args = array(
			'number'         => $orders_per_page,
			'offset'         => ( $paged - 1 ) * $orders_per_page,
			'search'         => $ordersearch,
			'fields'         => 'all_with_meta',
			'type'           => 'shop_order',
			'meta_key'       => '_vipps_recurring_pending_charge',
			'meta_compare'   => '=',
			'meta_value'     => 1,
			'payment_method' => 'vipps_recurring'
		);

		if ( '' !== $args['search'] ) {
			$args['search'] = '*' . $args['search'] . '*';
		}

		if ( isset( $_REQUEST['orderby'] ) ) {
			$args['orderby'] = $_REQUEST['orderby'];
		}

		if ( isset( $_REQUEST['order'] ) ) {
			$args['order'] = $_REQUEST['order'];
		}

		/**
		 * Filters the query arguments used to retrieve users for the current users list table.
		 *
		 * @param array $args Arguments passed to WP_User_Query to retrieve items for the current
		 *                    users list table.
		 *
		 * @since 4.4.0
		 *
		 */
		$args = apply_filters( 'woo_vipps_recurring_pending_charges_list_table_query_args', $args );

		// Query the user IDs for this page.
		$wp_pending_order_search = new WC_Order_Query( $args );

		$this->items = $wp_pending_order_search->get_orders();

		$this->set_pagination_args(
			array(
				'total_items' => count( (array) $wp_pending_order_search ),
				'per_page'    => $orders_per_page,
			)
		);
	}

	/**
	 * Output 'no users' message.
	 *
	 * @since 3.1.0
	 */
	public function no_items() {
		_e( 'No pending charges found.' );
	}

	/**
	 * Retrieve an associative array of bulk actions available on this table.
	 *
	 * @return string[] Array of bulk action labels keyed by their action.
	 * @since 3.1.0
	 *
	 */
	protected function get_bulk_actions() {
		$actions = array();

		if ( current_user_can( 'activate_plugins' ) ) {
			$actions['check_status'] = __( 'Check Status', 'woo_vipps_recurring' );
		}

		return $actions;
	}

	/**
	 * Output the controls to allow user roles to be changed in bulk.
	 *
	 * @param string $which Whether this is being invoked above ("top")
	 *                      or below the table ("bottom").
	 *
	 * @since 3.1.0
	 *
	 */
	protected function extra_tablenav( $which ) {
		?>
        <div class="alignleft actions">
			<?php
			/**
			 * Fires just before the closing div containing the custom bulk actions
			 * in the Users list table.
			 *
			 * @param string $which The location of the extra table nav markup: 'top' or 'bottom'.
			 */
			do_action( 'wc_vipps_recurring_restrict_manage_pending_charges', $which );
			?>
        </div>
		<?php
		/**
		 * Fires immediately following the closing "actions" div in the tablenav for the pending charges
		 * list table.
		 *
		 * @param string $which The location of the extra table nav markup: 'top' or 'bottom'.
		 *
		 */
		do_action( 'wc_vipps_recurring_manage_pending_charges_extra_tablenav', $which );
	}

	/**
	 * Capture the bulk action required, and return it.
	 *
	 * Overridden from the base class implementation to capture
	 * the role change drop-down.
	 *
	 */
	public function current_action() {
		return parent::current_action();
	}

	/**
	 * Get a list of columns for the list table.
	 *
	 */
	public function get_columns() {
		return [
			'cb'           => '<input type="checkbox" />',
			'order'        => __( 'Order', 'woocommerce' ),
			'agreement_id' => __( 'Agreement ID', 'woo-vipps-recurring' ),
			'charge_id'    => __( 'Charge ID', 'woo-vipps-recurring' ),
			'created_at'   => __( 'Created At', 'woo-vipps-recurring' ),
		];
	}

	/**
	 * Get a list of sortable columns for the list table.
	 */
	protected function get_sortable_columns() {
		return [
			'order'      => 'order',
			'created_at' => 'created_at',
		];
	}

	/**
	 * Generate the list table rows.
	 *
	 * @since 3.1.0
	 */
	public function display_rows() {
		foreach ( $this->items as $post_id => $order_object ) {
			echo "\n\t" . $this->single_row( $order_object, '' );
		}
	}

	/**
	 * Generate HTML for a single row on the vipps pending charges admin panel.
	 *
	 * @param WC_Order $order_object The current user object.
	 * @param string $style Deprecated. Not used.
	 * @param string $role Deprecated. Not used.
	 * @param int $numposts Optional. Post count to display for this user. Defaults
	 * to zero, as in, a new user has made zero posts.
	 *
	 * @return string
	 */
	public function single_row( $order_object, $style = '' ) {
		if ( ! ( $order_object instanceof WC_Order ) ) {
			$order_object = wc_get_order( (int) $order_object );
		}

		$order_object->filter = 'display';
		$url                  = 'options-general.php?page=woo-vipps-recurring&';

		// Set up the hover actions for this user.
		$actions = array();

		$checkbox = sprintf(
			'<label class="screen-reader-text" for="order_%1$s">%2$s</label>' .
			'<input type="checkbox" name="orders[]" id="order_%1$s" value="%1$s" />',
			$order_object->get_id(),
			/* translators: %s: Order ID. */
			sprintf( __( 'Select order %s' ), $order_object->get_id(), 'woo_vipps_recurring' )
		);

		$edit = "<strong>{$order_object->get_id()}</strong>";

		$r = "<tr id='order-{$order_object->get_id()}'>";

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$classes = "$column_name column-$column_name";
			if ( $primary === $column_name ) {
				$classes .= ' has-row-actions column-primary';
			}
			if ( 'posts' === $column_name ) {
				$classes .= ' num'; // Special case for that column.
			}

			if ( in_array( $column_name, $hidden ) ) {
				$classes .= ' hidden';
			}

			$data = 'data-colname="' . wp_strip_all_tags( $column_display_name ) . '"';

			$attributes = "class='$classes' $data";

			if ( 'cb' === $column_name ) {
				$r .= "<th scope='row' class='check-column'>$checkbox</th>";
			} else {
				$r .= "<td $attributes>";
				switch ( $column_name ) {
					case 'order':
						$r .= "<a href='post.php?post={$order_object->get_id()}&action=edit' target='_blank'>#{$edit}</a>";
						break;
					case 'agreement_id':
						$r .= $order_object->get_meta( '_agreement_id' );
						break;
					case 'charge_id':
						$r .= $order_object->get_meta( '_charge_id' ) ?: __( "Charge ID not available. Check the order's notes instead", 'woo_vipps_recurring' );
						break;
					case 'created_at':
						$r .= wc_format_datetime( $order_object->get_date_created() );
						break;
					default:
						/**
						 * Filters the display output of custom columns in the pending charges list table.
						 *
						 * @param string $output Custom column output. Default empty.
						 * @param string $column_name Column name.
						 * @param int $user_id ID of the currently-listed user.
						 *
						 * @since 2.8.0
						 *
						 */
						$r .= apply_filters( 'woo_vipps_recurring_manage_pending_charges_custom_column', '', $column_name, $order_object->get_id() );
				}

				if ( $primary === $column_name ) {
					$r .= $this->row_actions( $actions );
				}
				$r .= '</td>';
			}
		}
		$r .= '</tr>';

		return $r;
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @return string Name of the default primary column, in this case, 'username'.
	 * @since 4.3.0
	 *
	 */
	protected function get_default_primary_column_name() {
		return 'Order ID';
	}

	/**
	 * Returns an array of user roles for a given user object.
	 *
	 * @param WP_User $user_object The WP_User object.
	 *
	 * @return string[] An array of user roles.
	 * @since 4.4.0
	 *
	 */
	protected function get_role_list( $user_object ) {
		$wp_roles = wp_roles();

		$role_list = array();

		foreach ( $user_object->roles as $role ) {
			if ( isset( $wp_roles->role_names[ $role ] ) ) {
				$role_list[ $role ] = translate_user_role( $wp_roles->role_names[ $role ] );
			}
		}

		if ( empty( $role_list ) ) {
			$role_list['none'] = _x( 'None', 'no user roles' );
		}

		/**
		 * Filters the returned array of roles for a user.
		 *
		 * @param string[] $role_list An array of user roles.
		 * @param WP_User $user_object A WP_User object.
		 *
		 * @since 4.4.0
		 *
		 */
		return apply_filters( 'get_role_list', $role_list, $user_object );
	}
}
