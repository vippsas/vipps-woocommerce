import {
        useBlockProps
} from '@wordpress/block-editor';

import { blockConfig } from './config';

export default function Edit() {
	return (
		<div
			{...useBlockProps({
				className:
					'wp-block-button wc-block-components-product-button wc-block-button-vipps',
			})}
		>
			BUYNOW
			{/* The buy-now button. LP 2026-01-19 */}
			<a
				className="single-product button vipps-buy-now wp-block-button__link"
				title={blockConfig['BuyNowWithVipps']}
			>
				<img
					className="inline vipps-logo-negative"
					src="#" // TODO:
					alt={blockConfig['BuyNowWithVipps']}
				/>
			</a>
		</div>
	);
}
