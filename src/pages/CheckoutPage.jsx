export default function CheckoutPage () {
	const { pendingOrderId, data } = window.VippsRecurringCheckout

	return <form id="vippsdata" class="woocommerce-checkout">
		<div className={'vipps-recurring-checkout-page'}>
			<div className={'vipps-recurring-checkout-page__loading'}>
				<div
					className={'vipps-recurring-checkout-page__loading__spinner'}/>
			</div>
		</div>
	</form>
}
