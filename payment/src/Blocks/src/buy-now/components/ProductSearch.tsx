import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, ComboboxControl } from '@wordpress/components';

import { Option, Product } from '../types';
import { EditProps } from '../edit';

export type ProductSearchProps = {
	attributes: EditProps['attributes'];
	setAttributes: EditProps['setAttributes'];
	hideCallback: () => void;
};

export default function ProductSearch({
	attributes,
	setAttributes,
	hideCallback,
}: ProductSearchProps) {
	const [searchTerm, setSearchTerm] = useState(attributes.productName);
	const [productOptions, setProductOptions] = useState<Option[]>([]);
	const [isLoading, setIsLoading] = useState(false);

	const resultsPerFetch = 10;

	useEffect(() => {
		if (searchTerm.length < 3) {
			setProductOptions([]);
			return;
		}

		const searchTimeout = setTimeout(async () => {
			setIsLoading(true);
			try {
				const products: Product[] = await apiFetch({
					path: `/wc/store/products?search=${encodeURIComponent(searchTerm)}&per_page=${resultsPerFetch}`,
				});

				const productOptions: Option[] = products.map((product) => ({
					label: product.name,
					value: product.id.toString(),
				}));

				setProductOptions(productOptions);
			} catch (error) {
				console.error('Error fetching products:', error);
				setProductOptions([]);
			} finally {
				setIsLoading(false);
			}
		}, 300);

		return () => clearTimeout(searchTimeout);
	}, [searchTerm]);

	const resetProduct = () => {
		setAttributes({
			productId: undefined,
			productName: undefined,
		});
		return;
	};

	return (
		<>
			<ComboboxControl
				className='vipps-buy-now-button-product-search'
				// Opt into these to-be-made style defaults early to suppress deprectaion warnings. LP 2026-01-20
				__next40pxDefaultSize={true}
				__nextHasNoMarginBottom={true}

				label={__('Select Product', 'woo-vipps')}
				// @ts-ignore: for some reason isLoading is not typed correctly. This shows a spinner when its loading, and its also in the docs: https://developer.wordpress.org/block-editor/reference-guides/components/combobox-control/. LP 2026-01-20
				isLoading={isLoading}
				value={
					attributes.productId ? attributes.productId.toString() : ''
				}
				onChange={(value) => {
					const id = parseInt(value ?? '');
					if (isNaN(id)) {
						resetProduct();
						return;
					}

					const selectedOption = productOptions.find(
						(opt) => opt.value === value
					);
					if (!selectedOption) {
						resetProduct();
						return;
					}
					const name = selectedOption.label;

					setSearchTerm(name);
					setAttributes({
						productId: id,
						productName: name,
					});
				}}
				onFilterValueChange={setSearchTerm}
				options={productOptions}
				help={__(
					'Type at least 3 characters to search products',
					'woo-vipps'
				)}
			/>
			<Button
				variant="primary"
				onClick={() => {
					if (!attributes.productId || !attributes.productId) return;
					hideCallback();
				}}
			>
				{__('Confirm', 'woo-vipps')}
			</Button>
		</>
	);
}
