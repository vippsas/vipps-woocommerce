import {__} from "@wordpress/i18n";

export default function PaymentError ({ continueShoppingUrl, logo }) {
	return <div className={'vipps-recurring-payment-redirect-page'}>
		<div className={'vipps-recurring-payment-redirect-page__container'}>
			<div
				className={'vipps-recurring-payment-redirect-page__container__content'}>
				<div
					className={'vipps-recurring-payment-redirect-page__container__content__logo'}>
					<img src={logo} alt="Logo"/>
				</div>
				<div
					className={'vipps-recurring-payment-redirect-page__container__content__text'}>
					<p>
						<h1 className={'vipps-recurring-payment-redirect-page__container__content__text__heading'}>
							{__('An error occurred',
								'woo-vipps')}
						</h1>
					</p>

					<p>
						{__('An unknown error has occurred.',
							'woo-vipps')}
					</p>

					<p>
						<a href={continueShoppingUrl} className={"btn button vipps-recurring-payment-redirect-page__container__content__text__action"}>
						{__('Continue shopping',
							'woo-vipps')}
						</a>
					</p>
				</div>
			</div>
		</div>
	</div>
}
