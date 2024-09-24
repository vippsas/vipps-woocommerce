import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'
import PaymentRedirect from '../components/PaymentRedirectPage/PaymentRedirect'
import PaymentCancelled
	from '../components/PaymentRedirectPage/PaymentCancelled'
import PaymentError from '../components/PaymentRedirectPage/PaymentError'

export default function PaymentRedirectPage () {
	const { logo, continueShoppingUrl } = window.VippsMobilePaySettings
	const searchParams = new URLSearchParams(window.location.search)

	const [response, setResponse] = useState(null)
	const [error, setError] = useState(false)
	const [errorCounter, setErrorCounter] = useState(0)
	const intervalHandlerRef = useRef(null)

	const checkStatus = useCallback(() => {
		apiFetch({
			path: `/vipps-mobilepay-recurring/v1/orders/status/${searchParams.get(
				'order_id')}?key=${searchParams.get('key')}`, method: 'GET',
		}).then(response => setResponse(response)).catch(() => {
			setErrorCounter((value) => value += 1)
		})
	}, [])

	useEffect(() => {
		checkStatus();
		intervalHandlerRef.current = setInterval(checkStatus, 10_000)

		return () => clearInterval(intervalHandlerRef.current)
	}, [])

	useEffect(() => {
		setError(errorCounter >= 4)
	}, [errorCounter])

	useEffect(() => {
		if (error) {
			clearInterval(intervalHandlerRef.current)

			return
		}

		if (!response || response.status === 'PENDING' || cancelled) {
			return
		}

		clearInterval(intervalHandlerRef.current)

		if (response.redirect_url) {
			window.location.href = response.redirect_url
		}
	}, [response, error])

	const cancelled = useMemo(() => {
		if (!response) {
			return false
		}

		return ['EXPIRED', 'STOPPED'].includes(response.status)
	}, [response])

	return <>
		{!error && <>
			{!cancelled
				? <PaymentRedirect logo={logo}/>
				: <PaymentCancelled logo={logo}
									continueShoppingUrl={continueShoppingUrl}/>}
		</>}

		{error && <PaymentError logo={logo}
								continueShoppingUrl={continueShoppingUrl}/>}
	</>
}
