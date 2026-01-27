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
	ToolbarGroup,
	ToolbarButton,
} from '@wordpress/components';
import { pencil } from '@wordpress/icons';

import ProductSearch from './components/ProductSearch';
import VippsSmile from './components/VippsSmile';
import { blockConfig } from './config';

export interface EditAttributes extends BlockAttributes {
	align: string;
	variant: string;
	language: string;
	/** Only set with manual product selection, i.e when not in a product context. LP 2026-01-23 */
	productId: string;
	productName: string;
	/** If this block is in a product context. LP 2026-01-23 */
	hasProductContext: boolean;
}

export type EditProps = BlockEditProps<EditAttributes>;

export default function Edit({
	context,
	attributes,
	setAttributes,
}: EditProps) {
	// If this block is a child of a product context. e.g. when this block is inserted into the blocks Product collection, Single product. LP 2026-01-23
	const hasProductContext = context['postType'] === 'product';
	if (hasProductContext) {
		setAttributes({ hasProductContext });
	}

	const langLogos =
		blockConfig.logos[attributes.language] ?? blockConfig.logos['en'];
	const logoSrc = langLogos[attributes.variant] ?? 'default';

	const showEditButton = !attributes.hasProductContext;

	// only show product selection if we are not in a product context and we don't have a product id. LP 2026-01-19
	const [showProductSelection, setShowProductSelection] = useState(
		!hasProductContext && !attributes.productId
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
					// Product selection mode. LP 2026-01-19
					<div className="vipps-buy-now-block-edit-container">
						<div className="vipps-buy-now-block-edit-header">
							<VippsSmile />
							{blockConfig['vippsbuynowbutton']}
						</div>

						<ProductSearch
							attributes={attributes}
							setAttributes={setAttributes}
							hideCallback={() => setShowProductSelection(false)}
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

						{/* Toolbar button to switch to product selection mode. LP 2026-01-19 */}
						{showEditButton && (
							<BlockControls>
								<ToolbarGroup>
									<ToolbarButton
										icon={pencil}
										label={__(
											'Edit selected product',
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
