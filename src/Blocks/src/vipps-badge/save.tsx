import type { BlockSaveProps } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

import type { VippsBadgeBlockAttributes } from './types';
import { blockConfig } from './blockConfig';

export default function save({
	attributes,
}: BlockSaveProps<VippsBadgeBlockAttributes>) {
	// Let the user choose the variant. If the current one isn't in the list, add it (though we don't know the label then. IOK 2020-12-18
	const variantOptions = blockConfig.variants;
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

	const attrs = {
		className: attributes.className,
		variant: current,
		language: language,
		brand: blockConfig.brand,
	};

	let extraclass =
		attributes.className && attributes.className !== 'undefined'
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
				{...useBlockProps.save({
					className: 'vipps-badge-wrapper ' + extraclass,
				})}
			>
				{/* <vipps-badge> is a web component that is enqueued with slug vipps-onsite-messageing. LP 18.11.2024 */}
				{/* @ts-ignore */}
				<vipps-mobilepay-badge {...attrs} />
			</div>
		</>
	);
}
