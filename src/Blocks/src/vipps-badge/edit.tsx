import type { BlockEditProps } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { SelectControl, PanelBody } from '@wordpress/components';

import type { VippsBadgeBlockAttributes } from './types';
import { blockConfig } from './blockConfig';
import './editor.css';

export default function Edit({
	attributes,
	setAttributes,
}: BlockEditProps<VippsBadgeBlockAttributes>) {
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

	const attrs = {
		variant: current,
		language: attributes.language,
		brand: blockConfig.brand,
	};

	let extraclass =
		attributes.className && attributes.className !== 'undefined'
			? attributes.className
			: '';

	return (
		<>
			{/* The block itself. LP 18.11.2024 */}
			<div
				{...useBlockProps({
					className: 'vipps-badge-wrapper ' + extraclass,
				})}
			>
				{/* <vipps-badge> is a web component that is enqueued with slug vipps-onsite-messageing. LP 18.11.2024 */}
				{/* @ts-ignore */}
				<vipps-mobilepay-badge {...attrs} />
			</div>

			{/* The block controls on the right side-panel. LP 18.11.2024 */}
			<InspectorControls>
				<PanelBody>
					<SelectControl
						onChange={(newVariant) =>
							setAttributes({ variant: newVariant })
						}
						label={__('Variant', 'woo-vipps')}
						value={attributes.variant}
						options={variantOptions}
						help={__(
							'Choose the badge variant with the perfect colors for your site',
							'woo-vipps'
						)}
					/>
					<SelectControl
						onChange={(newLanguage) =>
							setAttributes({ language: newLanguage })
						}
						label={__('Language', 'woo-vipps')}
						value={attributes.language}
						options={blockConfig.languages}
						help={__(
							'Choose language, or use the default',
							'woo-vipps'
						)}
					/>
				</PanelBody>
			</InspectorControls>
		</>
	);
}
