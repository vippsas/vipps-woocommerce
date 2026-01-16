import type { BlockAttributes } from '@wordpress/blocks';

type Select = {
	label: string;
	value: string;
};

export interface VippsBlockAttributes extends BlockAttributes {
	align: string;
	variant: string;
	language: string;

	// Currently, this block only works within the product collection block,
	// which sets the 'query' context. To support the button in other contexts, we add an isInQuery attribute which
	// is available in render.php. NB: This is not currently in use; it would be for instance used to add buy-now buttons
	// for arbitrary product ID or on product pages etc. IOK 2026-01-14
	isInQuery: boolean;
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
