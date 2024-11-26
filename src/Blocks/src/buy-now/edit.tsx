import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

declare var VippsConfig: {
	BuyNowWithVipps: string;
	BuyNowWith: string;
	vippslogourl: string;
};

export default function Edit() {
	return (
		<>
			<div
				{...useBlockProps()}
				className="wp-block-button  wc-block-components-product-button wc-block-button-vipps"
			>
				<a
					className="single-product button vipps-buy-now wp-block-button__link"
					href="javascript: void(0);"
					title={VippsConfig.BuyNowWithVipps}
				>
					<span className="vippsbuynow">
						{VippsConfig.BuyNowWith}
					</span>
					<img
						className="inline vipps-logo-negative"
						src={VippsConfig.vippslogourl}
						alt="Vipps"
						// @ts-ignore - ?
						border={0}
					/>
				</a>
			</div>
		</>
	);
}
