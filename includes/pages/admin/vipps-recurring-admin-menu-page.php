<?php

defined( 'ABSPATH' ) || exit;

?>

<div class="wrap">
	<h1>
		<?php echo __( 'Vipps Recurring Payments', 'woo-vipps-recurring' ); ?>
	</h1>

	<?php
	/* translators: link to the plugin's settings page */
	echo sprintf( __( "This area is for special actions that aren't settings. If you are looking for the plugin's settings, click <a href='%s'>here</a>.", 'woo-vipps-recurring' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=vipps_recurring' ) );
	?>

	<div class="card">
		<p>
			<?php echo __( 'If you have a lot of Vipps subscription orders that are currently on-hold you might want to force check the status of all the orders instead of waiting for the cron-job to do it\'s job.', 'woo-vipps-recurring' ); ?>
		</p>

		<button
			class="button button-primary"
			type="submit"
			id="check_charge_statuses_now"
		>
			<?php echo __( 'Check status of all Vipps subscription orders now', 'woo-vipps-recurring' ); ?>
		</button>
	</div>

	<h2>
		<?php echo __( 'Pending Charges', 'woo-vipps-recurring' ); ?>
	</h2>

	<form method="get">
		<input type="hidden" name="page" value="woo-vipps-recurring">

		<?php
		global $wc_vipps_recurring_list_table_pending_charges;
		$wc_vipps_recurring_list_table_pending_charges->prepare_items();
		echo $wc_vipps_recurring_list_table_pending_charges->display();
		?>
	</form>

	<h2>
		<?php echo __( 'Failed Charges', 'woo-vipps-recurring' ); ?>
	</h2>

	<p>
		<?php

		echo sprintf(
		/* translators: %s: link to possible failure reasons */
			__( 'A list of possible failure reasons and what they mean can be found %s', 'woo-vipps-recurring' ),
			'<a href="https://www.vipps.no/developers-documentation/recurring/documentation/#charge-failure-reasons" target="_blank" rel="noreferrer">' . __( 'here', 'woo-vipps-recurring' ) . '</a>'
		);

		?>

	</p>

	<form method="get">
		<input type="hidden" name="page" value="woo-vipps-recurring">

		<?php
		global $wc_vipps_recurring_list_table_failed_charges;
		$wc_vipps_recurring_list_table_failed_charges->prepare_items();
		echo $wc_vipps_recurring_list_table_failed_charges->display();
		?>
	</form>
</div>
