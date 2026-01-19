import type { BlockEditProps } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { SelectControl, PanelBody, TextControl } from '@wordpress/components';

import { VippsBlockAttributes, VippsBlockConfig } from './types';

// Injected config. LP 27.11.2024
declare const vippsBuyNowBlockConfig: VippsBlockConfig;

export default function Edit({
	context,
	attributes,
	setAttributes,
}: BlockEditProps<VippsBlockAttributes>) {
	// If we have a product context inherited from parent block, which as of now
	// is the product template block used in the product collection block. LP 2026-01-19
	if (context['query']) setAttributes({ hasProductContext: true });

	const langLogos =
		vippsBuyNowBlockConfig.logos[attributes.language] ??
		vippsBuyNowBlockConfig.logos['en'];
	const logoSrc = langLogos[attributes.variant] ?? 'default';

	console.log(attributes);
	console.log(!!attributes.hasProductContext);
	console.log(!!attributes.productId);

	return (
		<>
			{/* The actual block: show the buy-now button if we have a product, else show the product search field. LP 2026-01-19 */}
			{attributes.hasProductContext || attributes.productId ? (
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
			) 
			: (
				<TextControl
					label={__('Product id', 'woo-vipps')}
					help={__(
						'Enter the post id of the product this button should buy',
						'woo-vipps'
					)}
					value={attributes.productId}
					onChange={(newProductId) =>
						setAttributes({ productId: Number(newProductId) })
					}
					placeholder={__('Product post ID', 'woo-vipps')}
				/>
			)}

			{/* The block controls on the right side-panel. LP 2026-01-16 */}
			<InspectorControls>

				<TextControl
					label={__('Product id', 'woo-vipps')}
					help={__(
						'Enter the post id of the product this button should buy',
						'woo-vipps'
					)}
					value={attributes.productId}
					onChange={(newProductId) =>
						setAttributes({ productId: Number(newProductId) })
					}
					placeholder={__('Product post ID', 'woo-vipps')}
				/>
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
