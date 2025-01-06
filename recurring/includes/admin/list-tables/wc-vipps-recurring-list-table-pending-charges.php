<?php
/**
 * Vipps/MobilePay Recurring List Pending Charges Table
 */

defined( 'ABSPATH' ) || exit;

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
	 */
	public function __construct( $args = [] ) {
		parent::__construct(
			[
				'singular' => 'pending charge',
				'plural'   => 'pending charges',
				'screen'   => $args['screen'] ?? null,
			]
		);
	}

	/**
	 * Check the current user's permissions.
	 *
	 * @return bool
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

		$args = [
			'limit'          => $orders_per_page,
			'offset'         => ( $paged - 1 ) * $orders_per_page,
			'search'         => $ordersearch,
			'paginate'       => true,
			'type'           => 'shop_order',
			'meta_key'       => WC_Vipps_Recurring_Helper::META_CHARGE_PENDING,
			'meta_compare'   => '=',
			'meta_value'     => 1,
			'payment_method' => 'vipps_recurring'
		];

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
		 */
		$args = apply_filters( 'wc_vipps_recurring_pending_charges_list_table_query_args', $args );

		// Query the user IDs for this page.
		$wp_pending_order_search = wc_get_orders( $args );

		$this->items = $wp_pending_order_search->orders;

		$this->set_pagination_args(
			[
				'total_items' => $wp_pending_order_search->total,
				'per_page'    => $orders_per_page,
			]
		);
	}

	/**
	 * Output 'no users' message.
	 */
	public function no_items() {
		_e( 'No pending charges found.' );
	}

	/**
	 * Retrieve an associative array of bulk actions available on this table.
	 *
	 * @return string[] Array of bulk action labels keyed by their action.
	 *
	 */
	protected function get_bulk_actions() {
		return [
			'check_status' => __( 'Check Status', 'woo-vipps' )
		];
	}

	/**
	 * Output the controls to allow user roles to be changed in bulk.
	 *
	 * @param string $which Whether this is being invoked above ("top")
	 *                      or below the table ("bottom").
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
	 */
	public function get_columns() {
		return [
			'cb'           => '<input type="checkbox" />',
			'order'        => __( 'Order', 'woocommerce' ),
			'agreement_id' => __( 'Agreement ID', 'woo-vipps' ),
			'charge_id'    => __( 'Charge ID', 'woo-vipps' ),
			'captured'     => __( 'Captured', 'woo-vipps' ),
			'api_status'   => __( 'Latest API Status', 'woo-vipps' ),
			'created_at'   => __( 'Created At', 'woo-vipps' ),
		];
	}

	/**
	 * Get a list of sortable columns for the list table.
	 */
	protected function get_sortable_columns() {
		return [
			'order'      => [ 'order', true ],
			'created_at' => [ 'created_at', true ]
		];
	}

	/**
	 * Generate the list table rows.
	 */
	public function display_rows() {
		foreach ( $this->items as $post_id => $order_object ) {
			echo "\n\t" . $this->single_row( $order_object, '' );
		}
	}

	/**
	 * Generate HTML for a single row on the Vipps/MobilePay pending charges admin panel.
	 *
	 * @param WC_Order $order_object The current user object.
	 * @param string $style Deprecated. Not used.
	 * to zero, as in, a new user has made zero posts.
	 *
	 * @return string
	 */
	public function single_row( $order_object, $style = '' ) {
		if ( ! ( $order_object instanceof WC_Order ) ) {
			$order_object = wc_get_order( (int) $order_object );
		}

		$order_object->filter = 'display';

		// Set up the hover actions for this user.
		$actions = [];

		$checkbox = sprintf(
			'<label class="screen-reader-text" for="order_%1$s">%2$s</label>' .
			'<input type="checkbox" name="orders[]" id="order_%1$s" value="%1$s" />',
			$order_object->get_id(),
			/* translators: %s: Order ID. */
			sprintf( __( 'Select order %s' ), $order_object->get_id(), 'woo-vipps' )
		);

		$edit = "<strong>{$order_object->get_id()}</strong>";

		$r = "<tr id='order-{$order_object->get_id()}'>";

		list( $columns, $hidden, $primary ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$classes = "$column_name column-$column_name";
			if ( $primary === $column_name ) {
				$classes .= ' has-row-actions column-primary';
			}

			if ( 'posts' === $column_name ) {
				$classes .= ' num';
			}

			if ( in_array( $column_name, $hidden, true ) ) {
				$classes .= ' hidden';
			}

			$data = 'data-colname="' . wp_strip_all_tags( $column_display_name ) . '"';

			$attributes = "class='$classes' $data";

			if ( 'cb' === $column_name ) {
				$r .= "<th scope='row' class='check-column'>$checkbox</th>";
			} else {
				$id = WC_Vipps_Recurring_Helper::get_id( $order_object );
				$r  .= "<td $attributes>";

				switch ( $column_name ) {
					case 'order':
						$r .= "<a href='post.php?post={$id}&action=edit' target='_blank'>#{$edit}</a>";

						break;
					case 'agreement_id':
						$r .= WC_Vipps_Recurring_Helper::get_agreement_id_from_order( $order_object );

						break;
					case 'charge_id':
						if ( WC_Vipps_Recurring_Helper::is_charge_captured_for_order( $order_object ) ) {
							$r .= WC_Vipps_Recurring_Helper::get_charge_id_from_order( $order_object ) ?: __( "Charge ID not available. Check the order's notes instead.", 'woo-vipps' );
						} else {
							$r .= __( 'This order has not yet been captured.', 'woo-vipps' );
						}

						break;
					case 'captured':
						$r .= WC_Vipps_Recurring_Helper::is_charge_captured_for_order( $order_object )
							? __( 'Yes', 'woo-vipps' )
							: __( 'No', 'woo-vipps' );

						break;
					case 'api_status':
						$api_status = WC_Vipps_Recurring_Helper::get_latest_api_status_from_order( $order_object );
						$r          .= $api_status ? $api_status : '-';

						break;
					case 'created_at':
						$order_post = wcs_get_objects_property( $order_object, 'post' );

						$timestamp_gmt = wcs_get_objects_property( $order_object, 'date_created' )
							->getTimestamp();

						// translators: php date format
						$t_time          = get_the_time( _x( 'Y/m/d g:i:s A', 'post date', 'woocommerce-subscriptions' ), $order_post );
						$date_to_display = ucfirst( wcs_get_human_time_diff( $timestamp_gmt ) );

						$t_esc_time = esc_attr( $t_time );
						$esc_time   = esc_html( apply_filters( 'post_date_column_time', $date_to_display, $order_post ) );

						$r .= "<abbr title='$t_esc_time'>
							$esc_time
						</abbr>";

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
						$r .= apply_filters( 'wc_vipps_recurring_manage_pending_charges_custom_column', '', $column_name, $order_object->get_id() );
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
	 * @return string Name of the default primary column, in this case, 'order'.
	 *
	 */
	protected function get_default_primary_column_name() {
		return 'order';
	}
}
