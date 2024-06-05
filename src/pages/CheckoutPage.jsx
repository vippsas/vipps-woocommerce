import {
	isEmptyElement,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'

export default function CheckoutPage () {
	const { pendingOrderId, data: initialData } = window.VippsRecurringCheckout

	const [loaded, setLoaded] = useState(!!initialData.session)
	const [session, setSession] = useState(initialData.session)

	useEffect(() => {
		if (loaded) {
			return
		}

		fetchSession()

		setLoaded(true)
	}, [session, loaded])

	// useEffect(() => {
	// 	if (!data.redirect) {
	// 		return;
	// 	}
	//
	// 	window.location.href = data.redirect;
	// }, [data.redirect])

	const fetchSession = useCallback(() => {
		apiFetch({
			path: `/vipps-mobilepay-recurring/v1/checkout/session`,
			method: 'POST',
		}).then(response => setSession(response))
	}, [pendingOrderId])

	console.log('[Vipps/MobilePay Recurring Checkout]: ', pendingOrderId,
		session)

	return <form id="vippsdata" className="woocommerce-checkout">
		<div className={'vipps-recurring-checkout-page'}>
			<div className={'vipps-recurring-checkout-page__loading'}>
				{!session.src && <div
					className={'vipps-recurring-checkout-page__loading__spinner'}/>}

				{session.src && (
					<iframe src={session.src} frameborder="0"></iframe>
				)}
			</div>
		</div>
	</form>
}
