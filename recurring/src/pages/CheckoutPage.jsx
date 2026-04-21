import {
	useCallback,
	useEffect, useRef,
	useState,
} from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'
import { __ } from '@wordpress/i18n'

export default function CheckoutPage () {
	const checkoutData = window.VippsRecurringCheckout
	const { continueShoppingUrl } = window.VippsMobilePaySettings

	const [sessionStatus, setSessionStatus] = useState(null)
	const sessionPollHandler = useRef(null)

	const iframeRef = useRef(null)

	useEffect(() => {
		if (!checkoutData.redirect_url) {
			return
		}

		window.location.href = checkoutData.redirect_url
	}, [checkoutData])

	const pollSessionStatus = useCallback(() => {
		apiFetch({
			path: `/vipps-mobilepay-recurring/v1/checkout/session`,
			method: 'GET',
		}).then(response => {
			setSessionStatus(response)
		})
	}, [])

	useEffect(() => {
		if (!checkoutData.session.token || !checkoutData.success) {
			return
		}

		sessionPollHandler.current = setInterval(pollSessionStatus, 20_000)

		return () => {
			clearInterval(sessionPollHandler.current)
		}
	}, [checkoutData])

	useEffect(() => {
		if (!sessionStatus) {
			return
		}

		if (sessionStatus.status === 'EXPIRED') {
			clearInterval(sessionPollHandler.current)

			return
		}

		if (!sessionStatus.redirect_url) {
			return
		}

		window.location.href = sessionStatus.redirect_url
	}, [sessionStatus])

	const listenToIframe = useCallback((e) => {
		const src = iframeRef.current.getAttribute('src')
		const origin = new URL(src).origin

		if (e.origin !== origin) return

		if (e.data.type === 'resize') {
			iframeRef.current.style.height = `${e.data.frameHeight}px`
		}

		if (e.data.type === 'payment_url') {
			window.location.href = e.data.paymentUrl
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
	}, [iframeRef.current, checkoutData.session.token])

	return <form id="vippsdata" className="woocommerce-checkout">
		<div className={'vipps-recurring-checkout-page'}>
			{(!checkoutData.session.success &&
				checkoutData.session.msg?.length > 0) ? <div
				className={'vipps-recurring-checkout-page__error'}>
				<p>
					{checkoutData.session.msg}
				</p>

				<p>
					<a href={continueShoppingUrl}
					   className={'btn button vipps-recurring-checkout-page__error__action'}>
						{__('Continue shopping',
							'woo-vipps')}
					</a>
				</p>
			</div> : <div className={'vipps-recurring-checkout-page__loading'}>
				{(sessionStatus?.status !== 'EXPIRED') && <>
					{!checkoutData.session.token && <div
						className={'vipps-recurring-checkout-page__loading__spinner'}/>}

					{checkoutData.session.token && (
						<iframe ref={iframeRef}
								src={`${(checkoutData.session.checkoutFrontendUrl ||
									checkoutData.session.src)}?token=${checkoutData.session.token}&lang=${checkoutData.language}`}
								frameBorder="0" width={'100%'}></iframe>
					)}
				</>}

				{sessionStatus?.status === 'EXPIRED' && <div>
					{__('Checkout session expired. Please refresh to start a new session.',
						'woo-vipps')}
				</div>}
			</div>}
		</div>
	</form>
}
