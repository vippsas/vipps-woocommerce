import type { BlockEditProps } from '@wordpress/blocks';
import type { BlockAttributes } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

// Injected config. LP 27.11.2024
declare const VippsConfig: {
	BuyNowWithVipps: string;
	logoSvgUrl: string;
};

interface Attributes extends BlockAttributes {
	isInQuery: boolean;
}

export default function Edit({
	context,
	setAttributes,
}: BlockEditProps<Attributes>) {
	// Planned feature. LP 29.11.2024
	if (context['query']) setAttributes({ isInQuery: true });

	return (
		<>
			<div
				{...useBlockProps({
					className:
						'wp-block-button wc-block-components-product-button wc-block-button-vipps',
				})}
			>
				<a
					className="single-product button vipps-buy-now wp-block-button__link"
					title={VippsConfig['BuyNowWithVipps']}
				>
					<img
						className="inline vipps-logo-negative"
						src={VippsConfig['logoSvgUrl']}
						alt={VippsConfig['BuyNowWithVipps']}
					/>
				</a>
			</div>
		</>
	);
}
