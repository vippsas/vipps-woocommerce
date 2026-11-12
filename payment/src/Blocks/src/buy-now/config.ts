import { Option } from './types';

export interface VippsBlockConfig {
	BuyNowWithVipps: string;
	vippssmileurl: string;
	vippsbuynowbutton: string;
	vippsbuynowdescription: string;
	variants: Option[];
	verbs: Option[];
	languages: Option[];
	vippsajaxurl: string;
	vippsresturl: string;
	vippsajaxnonce: string;
	paymentMethod: string;
	storeLanguage: string;
	variantMigrationMap: Record<string, Record<string, string|boolean>>;
}

// Injected config from php. LP 27.11.2024
declare const vippsBuyNowBlockConfig: VippsBlockConfig;
export const blockConfig = vippsBuyNowBlockConfig;
