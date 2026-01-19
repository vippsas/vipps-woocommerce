import type { BlockEditProps } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { SelectControl, PanelBody } from '@wordpress/components';

import { VippsBlockAttributes, VippsBlockConfig } from './types';

// Injected config. LP 27.11.2024
declare const vippsBuyNowBlockConfig: VippsBlockConfig;

export default function Edit({
	context,
	attributes,
	setAttributes,
}: BlockEditProps<VippsBlockAttributes>) {
	// Currently, this block only works within the product collection block,
	// which sets the 'query' context. To support the button in other contexts, we add an isInQuery attribute which
	// is available in render.php. NB: This is not currently in use; it would be for instance used to add buy-now buttons
	// for arbitrary product ID or on product pages etc. IOK 2026-01-14
	if (context['query']) setAttributes({ isInQuery: true });

	const langLogos = vippsBuyNowBlockConfig.logos[attributes.language] ?? vippsBuyNowBlockConfig.logos['en'];
	const logoSrc = langLogos[attributes.variant] ?? 'default';

	return (
		<>
			{/* The actual block. LP 2026-01-16 */}
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
						src={logoSrc}
						alt={vippsBuyNowBlockConfig['BuyNowWithVipps']}
					/>
				</a>
			</div>

			{/* The block controls on the right side-panel. LP 2026-01-16 */}
			<InspectorControls>
				<PanelBody>
					<SelectControl
						onChange={(newVariant) =>
							setAttributes({ variant: newVariant })
						}
						label={__('Variant', 'woo-vipps')}
						value={attributes.variant}
						options={vippsBuyNowBlockConfig.variants}
						help={__(
							'Choose the button variant with the perfect fit for your site',
							'woo-vipps'
						)}
					/>
					<SelectControl
						onChange={(newLanguage) =>
							setAttributes({ language: newLanguage })
						}
						label={__('Language', 'woo-vipps')}
						value={attributes.language}
						options={vippsBuyNowBlockConfig.languages}
						help={__('Choose language', 'woo-vipps')}
					/>
				</PanelBody>
			</InspectorControls>
		</>
	);
}
