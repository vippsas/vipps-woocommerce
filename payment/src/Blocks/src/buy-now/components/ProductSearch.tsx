import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, ComboboxControl } from '@wordpress/components';

import { Option, Product } from '../types';
import { EditProps } from '../edit';
import { blockConfig } from '../config';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

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
	const [searchTerm, setSearchTerm] = useState('');
	const [isInitialized, setIsInitialized] = useState(false);
	const [productOptions, setProductOptions] = useState<Option[]>([]);
	const [isLoading, setIsLoading] = useState(false);

	const debounceMs = 300;

	useEffect(() => {
		if (!isInitialized) {
			setIsInitialized(true);
			return;
		}
		if (!searchTerm.trim()) {
			setProductOptions([]);
			return;
		}

		const onError = (error: any) => {
			console.error('Error fetching products:', error);
			setProductOptions([]);
		};

		const path = `${blockConfig.vippsresturl}/express-products`;
		const queryParams = {
			search: searchTerm,
			per_page: '10',
			action: 'woo_vipps_express_checkout_products',
			orderby: 'title',
			order: 'desc',
		};

		const searchTimeout = setTimeout(async () => {
			setIsLoading(true);
			apiFetch<Product[]>({
				path: addQueryArgs(path, queryParams),
				method: 'GET',
			})
				.then((products) => {
					const productOptions: Option[] = products.map(
						(product) => ({
							label: product.name,
							value: product.id.toString(),
						})
					);
					setProductOptions(productOptions);
				})
				.catch(onError)
				.finally(() => setIsLoading(false));
		}, debounceMs);

		return () => clearTimeout(searchTimeout);
	}, [searchTerm]);

	const resetProduct = () => {
		setAttributes({ productId: '', productName: '', productParentId: '' });
		return;
	};

	return (
		<>
			{attributes.productName && (
				<div
					style={{
						marginBottom: '8px',
						fontSize: '14px',
						color: '#666',
					}}
				>
					{__('Selected product', 'woo-vipps') + ': '}{' '}
					<strong>{attributes.productName}</strong>
				</div>
			)}
			<ComboboxControl
				className="vipps-buy-now-button-product-search"
				// Opt into these to-be-made style defaults early to suppress deprectaion warnings. LP 2026-01-20
				__next40pxDefaultSize={true}
				__nextHasNoMarginBottom={true}
				label={__('Search Product', 'woo-vipps')}
				// @ts-ignore: for some reason isLoading is not typed correctly. This shows a spinner when its loading, and its also in the docs: https://developer.wordpress.org/block-editor/reference-guides/components/combobox-control/. LP 2026-01-20
				isLoading={isLoading}
				value={attributes.productId}
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

					setSearchTerm('');
					setAttributes({
						productId: id.toString(),
						productName: name,
					});
				}}
				onFilterValueChange={setSearchTerm}
				options={productOptions}
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
