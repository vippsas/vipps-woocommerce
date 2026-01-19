import type { BlockEditProps } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	BlockControls,
} from '@wordpress/block-editor';
import {
	SelectControl,
	PanelBody,
	TextControl,
	Button,
	ToolbarGroup,
	ToolbarButton,
} from '@wordpress/components';
import { pencil } from '@wordpress/icons';

import { VippsBlockAttributes, VippsBlockConfig } from './types';
import { useState } from 'react';

// Injected config. LP 27.11.2024
declare const vippsBuyNowBlockConfig: VippsBlockConfig;

export default function Edit({
	context,
	attributes,
	setAttributes,
}: BlockEditProps<VippsBlockAttributes>) {
	const [productIdText, setProductIdText] = useState(
		String(attributes.productId)
	);

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

	const showEditButton = !attributes.hasProductContext;

	// only show product selection if we are not in a product context and we don't have a product id. LP 2026-01-19
	const [showProductSelection, setShowProductSelection] = useState(
		!(attributes.hasProductContext || attributes.productId)
	);

	return (
		<>
			<div
				{...useBlockProps({
					className:
						'wp-block-button wc-block-components-product-button wc-block-button-vipps',
				})}
			>
				{showProductSelection ? (
					// Product selection with text input for product id. LP 2026-01-19
					<div>
						<TextControl
							label={vippsBuyNowBlockConfig['vippsbuynowbutton']}
							help={__(
								'Enter the post id of the product this button should buy',
								'woo-vipps'
							)}
							value={productIdText}
							onChange={(newProductId) =>
								setProductIdText(newProductId)
							}
							placeholder={__('Product ID', 'woo-vipps')}
						/>
						<Button
							variant="primary"
							onClick={() => {
								let text = productIdText.trim();
								if (!text || isNaN(Number(text))) return;
								setAttributes({ productId: text });
								setShowProductSelection(false);
							}}
						>
							{__('Confirm', 'woo-vipps')}
						</Button>
					</div>
				) : (
					// The WYSIWYG buy-now button. LP 2026-01-19
					<>
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

						{/* Toolbar button to start editing product id selection. LP 2026-01-19 */}
						{showEditButton && (
							<BlockControls>
								<ToolbarGroup>
									<ToolbarButton
										icon={pencil}
										label={__(
											'Edit product ID',
											'woo-vipps'
										)}
										onClick={() =>
											setShowProductSelection(true)
										}
									/>
								</ToolbarGroup>
							</BlockControls>
						)}
					</>
				)}
			</div>

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
						setAttributes({ productId: newProductId })
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
