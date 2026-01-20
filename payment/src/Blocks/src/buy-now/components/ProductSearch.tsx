import { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { ComboboxControl } from '@wordpress/components';

import { Product } from '../types';
import { EditProps } from '../edit';

type Option = {
	value: string;
	label: string;
};

export type ProductSearchProps = {
	attributes: EditProps['attributes'];
	setAttributes: EditProps['setAttributes'];
};

export default function ProductSearch({
	attributes,
	setAttributes,
}: ProductSearchProps) {
	const [searchTerm, setSearchTerm] = useState('');
	const [productOptions, setProductOptions] = useState<Option[]>([]);
	const [isLoading, setIsLoading] = useState(false);

	const resultsPerFetch = 10;

	useEffect(() => {
		if (searchTerm.length < 3) {
			setProductOptions([]);
			return;
		}

		const delaySearch = setTimeout(async () => {
			setIsLoading(true);
			try {
				const products: Product[] = await apiFetch({
					path: `/wc/store/products?search=${encodeURIComponent(searchTerm)}&per_page=${resultsPerFetch}`,
				});

				const productOptions: Option[] = products.map((product) => ({
					label: product.name,
					value: product.id.toString(),
				}));
				console.log('LP productOptions: ', productOptions);

				setProductOptions(productOptions);
			} catch (error) {
				console.error('Error fetching products:', error);
				setProductOptions([]);
			} finally {
				setIsLoading(false);
			}
		}, 300);

		return () => clearTimeout(delaySearch);
	}, [searchTerm]);

	return (
		<ComboboxControl
			label={__('Select Product', 'woo-vipps')}
			value={attributes.productId ? attributes.productId.toString() : ''}
			onChange={(value) => {
				const id = parseInt(value ?? '');
				if (isNaN(id)) return;

				const selectedOption = productOptions.find(
					(opt) => opt.value === value
				);
				if (!selectedOption) return;

				setAttributes({
					productId: id.toString(),
					productName: selectedOption.label,
				});
			}}
			onFilterValueChange={setSearchTerm}
			options={productOptions}
			help={
				isLoading
					? __('Searching...', 'woo-vipps')
					: __('Type at least 3 characters to search', 'woo-vipps')
			}
		/>
	);
}
