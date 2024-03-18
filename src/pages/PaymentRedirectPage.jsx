import { useEffect, useState } from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'
import { __ } from '@wordpress/i18n'

export default function PaymentRedirectPage () {
	const { logo } = window.VippsMobilePaySettings
	const searchParams = new URLSearchParams(window.location.search)

	const [response, setResponse] = useState(null)

	const intervalHandler = setInterval(() => {
		apiFetch({
			path: `/vipps-mobilepay-recurring/v1/orders/status/${searchParams.get(
				'order_id')}?key=${searchParams.get('key')}`, method: 'GET',
		}).then(response => setResponse(response))
	}, 1000)

	useEffect(() => {
		if (!response || response.status === 'PENDING') {
			return
		}

		clearInterval(intervalHandler)
		window.location.href = response.redirect_url
	}, [response])

	return (<div className={'vipps-recurring-payment-redirect-page'}>
			<div className={'vipps-recurring-payment-redirect-page__container'}>
				<div
					className={'vipps-recurring-payment-redirect-page__container__content'}>
					<div
						className={'vipps-recurring-payment-redirect-page__container__content__logo'}>
						<img src={logo} alt="Logo"/>
					</div>
					<div
						className={'vipps-recurring-payment-redirect-page__container__content__loading'}>
						<div
							className={'vipps-recurring-payment-redirect-page__container__content__loading__spinner'}/>
					</div>
					<div
						className={'vipps-recurring-payment-redirect-page__container__content__text'}>
						<p>
							{__('Verifying your payment. Please wait.',
								'vipps-recurring-payments-gateway-for-woocommerce')}
						</p>

						<p>
							{__('You will be redirected shortly.',
								'vipps-recurring-payments-gateway-for-woocommerce')}
						</p>
					</div>
				</div>
			</div>
		</div>)
}
