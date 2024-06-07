import {
	useCallback,
	useEffect, useRef,
	useState,
} from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'
import { __ } from '@wordpress/i18n'

export default function CheckoutPage () {
	const { pendingOrderId, data: initialData } = window.VippsRecurringCheckout

	const [loaded, setLoaded] = useState(!!initialData.session)
	const [session, setSession] = useState(initialData.session)
	const [sessionStatus, setSessionStatus] = useState(null)

	const iframeRef = useRef(null)

	useEffect(() => {
		if (loaded) {
			return
		}

		fetchOrCreateSession()

		setLoaded(true)
	}, [session, loaded])

	useEffect(() => {
		if (!initialData.redirect_url) {
			return
		}

		window.location.href = initialData.redirect_url
	}, [initialData])

	const fetchOrCreateSession = useCallback(() => {
		apiFetch({
			path: `/vipps-mobilepay-recurring/v1/checkout/session`,
			method: 'POST',
		}).then(response => setSession(response))
	}, [pendingOrderId])

	const pollSessionStatus = useCallback(() => {
		apiFetch({
			path: `/vipps-mobilepay-recurring/v1/checkout/session`,
			method: 'GET',
		}).then(response => setSessionStatus(response))
	}, [])

	useEffect(() => {
		if (!session.token) {
			return
		}

		const intervalId = setInterval(pollSessionStatus, 10_000)

		return () => {
			clearInterval(intervalId)
		}
	}, [session])

	useEffect(() => {
		if (!sessionStatus || !sessionStatus.redirect_url) {
			return
		}

		window.location.href = sessionStatus.redirect_url
	}, [sessionStatus])

	const listenToIframe = useCallback((e) => {
		const src = iframeRef.current.getAttribute('src')
		const origin = new URL(src).origin

		if (e.origin !== origin) return

		console.log(e.data)

		if (e.data.type === 'resize') {
			iframeRef.current.style.height = `${e.data.frameHeight}px`
		}

		if (e.data.type === 'payment_url') {
			window.location.href = e.data.paymentUrl;
		}
	}, [iframeRef.current])

	useEffect(() => {
		if (!iframeRef.current) {
			return
		}

		window.addEventListener('message', listenToIframe)

		return () => {
			window.removeEventListener('message', listenToIframe)
		}
	}, [iframeRef.current])

	return <form id="vippsdata" className="woocommerce-checkout">
		<div className={'vipps-recurring-checkout-page'}>
			<div className={'vipps-recurring-checkout-page__loading'}>
				{sessionStatus?.status !== 'EXPIRED' && <>
					{!session.token && <div
						className={'vipps-recurring-checkout-page__loading__spinner'}/>}

					{session.token && (
						<iframe ref={iframeRef}
								src={`${(session.checkoutFrontendUrl ||
									session.src)}?token=${session.token}`}
								frameborder="0" width={'100%'}></iframe>
					)}
				</>}

				{sessionStatus?.status === 'EXPIRED' && <div>
					{__('Checkout session expired. Please refresh to start a new session.',
						'vipps-recurring-payments-gateway-for-woocommerce')}
				</div>}
			</div>
		</div>
	</form>
}
