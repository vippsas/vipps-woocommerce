import type { BlockAttributes } from '@wordpress/blocks';

interface Variant {
	label: string,
	value: string,
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
	defaultVariant: string;
	variants: Variant[];
}
