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
			{/* The buy-now button. LP 2026-01-19 */}
			<a
				className="single-product button vipps-buy-now wp-block-button__link minicart"
				title={blockConfig['vippsbuynowbutton']}
				dangerouslySetInnerHTML={{__html: blockConfig['buttonHtml']}}
			>
			</a>
		</div>
	);
}
