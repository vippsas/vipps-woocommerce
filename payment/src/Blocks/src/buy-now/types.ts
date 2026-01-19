import type { BlockAttributes } from '@wordpress/blocks';

type Select = {
	label: string;
	value: string;
};

export interface VippsBlockAttributes extends BlockAttributes {
	align: string;
	variant: string;
	language: string;
	productId: string;

	// This is when the block has the context 'query' which is passed from parent block Product Template inside Product Collection. LP 2026-01-19
	hasProductContext: boolean;
}

export interface VippsBlockConfig {
	BuyNowWithVipps: string;
	logos: Record<string, Record<string, string>>;
	vippssmileurl: string;
	vippsbuynowbutton: string;
	vippsbuynowdescription: string;
	variants: Select[];
	languages: Select[];
}
