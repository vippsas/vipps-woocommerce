import {__} from "@wordpress/i18n";

export default function PaymentRedirect ({ logo }) {
	return <div className={'vipps-recurring-payment-redirect-page'}>
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
							'woo-vipps')}
					</p>

					<p>
						{__('You will be redirected shortly.',
							'woo-vipps')}
					</p>
				</div>
			</div>
		</div>
	</div>
}
