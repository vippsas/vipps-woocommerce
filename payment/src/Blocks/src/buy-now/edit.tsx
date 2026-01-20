import { useState } from 'react';
import type { BlockAttributes, BlockEditProps } from '@wordpress/blocks';
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
	ToolbarGroup,
	ToolbarButton,
} from '@wordpress/components';
import { pencil } from '@wordpress/icons';


import ProductSearch from './components/ProductSearch';
import { blockConfig } from './config';

export interface EditAttributes extends BlockAttributes {
	align: string;
	variant: string;
	language: string;
	productId: string;
	productName: string;

	// This is when the block has the context 'query' which is passed from parent block Product Template inside Product Collection. LP 2026-01-19
	hasProductContext: boolean;
}

export type EditProps = BlockEditProps<EditAttributes>;

export default function Edit({
	context,
	attributes,
	setAttributes,
}: EditProps) {
	// If we have a product context inherited from parent block, which as of now
	// is the product template block used in the product collection block. LP 2026-01-19
	if (context['query']) setAttributes({ hasProductContext: true });

	const langLogos =
		blockConfig.logos[attributes.language] ??
		blockConfig.logos['en'];
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
					// Product selection combobox. LP 2026-01-19
					<div>
						{blockConfig['vippsbuynowbutton']}
						<ProductSearch
							attributes={attributes}
							setAttributes={setAttributes}
						/>
					</div>
				) : (
					// The WYSIWYG buy-now button. LP 2026-01-19
					<>
						<a
							className="single-product button vipps-buy-now wp-block-button__link"
							title={blockConfig['BuyNowWithVipps']}
						>
							<img
								className="inline vipps-logo-negative"
								src={logoSrc}
								alt={blockConfig['BuyNowWithVipps']}
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
						options={blockConfig.variants}
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
						options={blockConfig.languages}
						help={__('Choose language', 'woo-vipps')}
					/>
				</PanelBody>
			</InspectorControls>
		</>
	);
}
