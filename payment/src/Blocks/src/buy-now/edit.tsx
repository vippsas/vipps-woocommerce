import type { BlockEditProps } from '@wordpress/blocks';
import type { BlockAttributes } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

// Injected config. LP 27.11.2024
declare const vippsBuyNowBlockConfig: {
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
	// Currently, this block only works within the product collection block, 
	// which sets the 'query' context. To support the button in other contexts, we add an isInQuery attribute which
	// is available in render.php. NB: This is not currently in use; it would be for instance used to add buy-now buttons
	// for arbitrary product ID or on product pages etc. IOK 2026-01-14
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
					title={vippsBuyNowBlockConfig['BuyNowWithVipps']}
				>
					<img
						className="inline vipps-logo-negative"
						src={vippsBuyNowBlockConfig['logoSvgUrl']}
						alt={vippsBuyNowBlockConfig['BuyNowWithVipps']}
					/>
				</a>
			</div>
		</>
	);
}
