import { Option } from './types';

export interface VippsBlockConfig {
	BuyNowWithVipps: string;
	logos: Record<string, Record<string, string>>;
	vippssmileurl: string;
	vippsbuynowbutton: string;
	vippsbuynowdescription: string;
	variants: Option[];
	languages: Option[];
	vippsajaxurl: string;
	vippsresturl: string;
	vippsajaxnonce: string;
}

// Injected config from php. LP 27.11.2024
declare const vippsBuyNowBlockConfig: VippsBlockConfig;
export const blockConfig = vippsBuyNowBlockConfig;
