<?php

defined( 'ABSPATH' ) || exit;

?>

<div class="wrap">
	<h1>
		<?php echo __( 'Vipps/MobilePay Recurring Payments', 'woo-vipps' ); ?>
	</h1>

	<?php
	/* translators: link to the plugin's settings page */
	echo sprintf( __( "This area is for special actions that aren't settings. If you are looking for the plugin's settings, click <a href='%s'>here</a>.", 'woo-vipps' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=vipps_recurring' ) );
	?>

	<div class="card">
		<p>
			<?php echo __( 'If you have a lot of Vipps/MobilePay subscription orders that are currently on-hold you might want to force check the status of all the orders instead of waiting for the cron-job to do it\'s job.', 'woo-vipps' ); ?>
		</p>

		<button
			class="button button-primary"
			type="submit"
			id="check_charge_statuses_now"
		>
			<?php echo __( 'Check status of all Vipps/MobilePay subscription orders now', 'woo-vipps' ); ?>
		</button>
	</div>

	<h2>
		<?php echo __( 'Pending Charges', 'woo-vipps' ); ?>
	</h2>

	<form method="get">
		<input type="hidden" name="page" value="woo-vipps-recurring">

		<?php
		global $wc_vipps_recurring_list_table_pending_charges;

		if ( $wc_vipps_recurring_list_table_pending_charges ) {
			$wc_vipps_recurring_list_table_pending_charges->prepare_items();
			echo $wc_vipps_recurring_list_table_pending_charges->display();
		}
		?>
	</form>

	<h2>
		<?php echo __( 'Failed Charges', 'woo-vipps' ); ?>
	</h2>

	<p>
		<?php

		echo sprintf(
		/* translators: %s: link to possible failure reasons */
			__( 'A list of possible failure reasons and what they mean can be found %s', 'woo-vipps' ),
			'<a href="https://developer.vippsmobilepay.com/docs/APIs/recurring-api/vipps-recurring-api/#charge-failure-reasons" target="_blank" rel="noreferrer">' . __( 'here', 'woo-vipps' ) . '</a>'
		);

		?>

	</p>

	<form method="get">
		<input type="hidden" name="page" value="woo-vipps-recurring">

		<?php
		global $wc_vipps_recurring_list_table_failed_charges;

		if ( $wc_vipps_recurring_list_table_failed_charges ) {
			$wc_vipps_recurring_list_table_failed_charges->prepare_items();
			echo $wc_vipps_recurring_list_table_failed_charges->display();
		}
		?>
	</form>
</div>
