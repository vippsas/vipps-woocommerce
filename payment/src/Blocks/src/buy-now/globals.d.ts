import { Select } from './types';

export {};

export interface VippsBlockConfig {
	BuyNowWithVipps: string;
	logos: Record<string, Record<string, string>>;
	vippssmileurl: string;
	vippsbuynowbutton: string;
	vippsbuynowdescription: string;
	variants: Select[];
	languages: Select[];
}

declare global {
	interface Window {
		vippsBuyNowBlockConfig: VippsBlockConfig;
	}
}
