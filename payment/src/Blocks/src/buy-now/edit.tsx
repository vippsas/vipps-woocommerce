import { useState, useEffect } from 'react';
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
import useIsInsideCart from './hooks/useIsInsideCart';

export interface EditAttributes extends BlockAttributes {
	align: string;
	variant: string;
	language: string;
	/** Only set with manual product selection, i.e when not in a product context. LP 2026-01-23 */
	productId: string;
	/** Only set with manual product selection, i.e when not in a product context. LP 2026-01-23 */
	productName: string;
	/** Whether this block inherently has a product context. e.g. a child of the block Product collection/Single product. LP 2026-01-23 */
	hasProductContext: boolean;
	/** In cart mode the button should instead buy the whole cart context, otherwise it buys a single product. LP 2026-01-29 */
	isCartMode: boolean;
}

export type EditProps = BlockEditProps<EditAttributes>;

export default function Edit({
	context,
	attributes,
	setAttributes,
	clientId,
}: EditProps) {
	const isCartMode = useIsInsideCart(clientId);

	const hasProductContext = context['postType'] === 'product';

	// Sync to attributes
	useEffect(() => {
		if (attributes.isCartMode !== isCartMode) {
			setAttributes({ isCartMode });
		}
	}, [isCartMode]);
	useEffect(() => {
		if (attributes.hasProductContext !== hasProductContext) {
			setAttributes({ hasProductContext });
		}
	}, [hasProductContext]);

	const langLogos =
		blockConfig.logos[attributes.language] ?? blockConfig.logos['en'];
	const logoSrc = langLogos[attributes.variant] ?? 'default';

	// Whether to show the block edit mode "select product". LP 2026-01-29
	const [showProductSelection, setShowProductSelection] = useState(
		!isCartMode && !hasProductContext && !attributes.productId
	);

	// Whether to show the button that switches to the block edit mode "select product". LP 2026-01-29
	const showEditButton = !isCartMode && !attributes.hasProductContext;

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
			{!isCartMode && (
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
			)}
		</>
	);
}
