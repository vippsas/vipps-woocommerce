import type { BlockEditProps } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

import type { VippsBadgeBlockAttributes } from './types';
import { blockConfig } from './blockConfig';
import './editor.css';

export default function Edit({
	attributes,
	setAttributes,
}: BlockEditProps<VippsBadgeBlockAttributes>) {
	// Let the user choose the variant. If the current one isn't in the list, add it (though we don't know the label then. IOK 2020-12-18
	const variantOptions = blockConfig['variants'];
	const current = attributes.variant;
	let found = false;
	for (let i = 0; i < variantOptions.length; i++) {
		if (current == variantOptions[i].value) {
			found = true;
			break;
		}
	}
	if (!found) variantOptions.push({ label: current, value: current });

	let language: string;
	if (attributes.language !== 'default') {
		language = attributes.language;
	} else {
		language = '';
	}

	let attrs: {
		className: string;
		variant: string;
		language: string;
		'vipps-senere'?: boolean;
		amount?: string;
	} = {
		className: attributes.className,
		variant: current,
		language: language,
	};

	if (attributes.later) {
		attrs['vipps-senere'] = true;
	}
	if (attributes.amount) {
		let am = parseInt(attributes.amount);
		if (!isNaN(am)) {
			attrs['amount'] = attributes.amount;
		}
	}

	let extraclass =
		attributes.className && attributes.className != 'undefined'
			? attributes.className
			: '';
	switch (attributes.align) {
		case 'center':
			extraclass += ' aligncenter';
			break;
		case 'left':
			extraclass += ' alignleft';
			break;
		case 'right':
			extraclass += ' alignright';
			break;
	}

	return (
		<>
			<div
				{...useBlockProps({
					className: 'vipps-badge-wrapper ' + extraclass,
				})}
			>
				{/* <vipps-badge> gets defined in https://checkout.vipps.no/on-site-messaging/v1/vipps-osm.js, I think... LP 15.11.2024 */}
				{/* @ts-ignore */}
				{/* <vipps-mobilepay-badge {...attrs} /> */}
				{/* <script src="https://checkout.vipps.no/on-site-messaging/v1/vipps-osm.js"></script> */}
				{/* <script async type="text/javascript" src="https://checkout.vipps.no/on-site-messaging/v1/vipps-osm.js"></script> */}
				{/* @ts-ignore */}
				{/* <vipps-mobilepay-badge
					brand="vipps"
					language="en"
					variant="white"
					/> */}
				{/* @ts-ignore */}
				<vipps-mobilepay-badge
					brand="vipps"
					language="en"
					variant="white"
					amount="500"
				/>
			</div>
		</>
	);
}
