import type { BlockAttributes } from '@wordpress/blocks';

interface Select {
	label: string;
	value: string;
}

export interface VippsBadgeBlockAttributes extends BlockAttributes {
	align: string;
	variant: string;
	language: string;
	later: boolean;
	amount: string;
}

export interface VippsBadgeBlockConfig {
	title: string;
	iconSrc: string;
	brand: string;
	defaultVariant: string;
	variants: Select[];
	languages: Select[];
}
