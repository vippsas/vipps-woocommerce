import type { BlockEditProps } from '@wordpress/blocks';
import type { BlockAttributes } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

// Injected from Vipps.class.php. LP 27.11.2024
declare var VippsConfig: {
	BuyNowWithVipps: string;
	BuyNowWith: string;
	vippslogourl: string;
};

interface Attributes extends BlockAttributes {
	isInQuery: boolean;
}

export default function Edit({
	context,
	setAttributes,
}: BlockEditProps<Attributes>) {
	if (context['query']) setAttributes({ isInQuery: true });

	return (
		<>
			<div
				{...useBlockProps()}
				className="wp-block-button  wc-block-components-product-button wc-block-button-vipps"
			>
				<a
					className="single-product button vipps-buy-now wp-block-button__link"
					title={VippsConfig.BuyNowWithVipps}
				>
					<span className="vippsbuynow">
						{VippsConfig.BuyNowWith}
					</span>
					<img
						className="inline vipps-logo-negative"
						src={VippsConfig.vippslogourl}
						alt="Vipps"
					/>
				</a>
			</div>
		</>
	);
}
