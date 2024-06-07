import { useEffect, useMemo, useState } from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'
import PaymentRedirect from '../components/PaymentRedirectPage/PaymentRedirect'
import PaymentCancelled
	from '../components/PaymentRedirectPage/PaymentCancelled'

export default function PaymentRedirectPage () {
	const { logo, continueShoppingUrl } = window.VippsMobilePaySettings
	const searchParams = new URLSearchParams(window.location.search)

	const [response, setResponse] = useState(null)

	const intervalHandler = setInterval(() => {
		apiFetch({
			path: `/vipps-mobilepay-recurring/v1/orders/status/${searchParams.get(
				'order_id')}?key=${searchParams.get('key')}`, method: 'GET',
		}).then(response => setResponse(response))
	}, 1000)

	useEffect(() => {
		if (!response || response.status === 'PENDING' || cancelled) {
			return
		}

		clearInterval(intervalHandler)

		if (response.redirect_url) {
			window.location.href = response.redirect_url
		}
	}, [response])

	const cancelled = useMemo(() => {
		if (!response) {
			return false
		}

		return ['EXPIRED', 'STOPPED'].includes(response.status)
	}, [response])

	return (!cancelled ? <PaymentRedirect logo={logo}/> : <PaymentCancelled
		logo={logo} continueShoppingUrl={continueShoppingUrl}/>)
}
