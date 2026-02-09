export interface VippsBlockConfig {
	BuyNowWithVipps: string;
	vippsbuynowbutton: string;
	vippsbuynowdescription: string;
}

// Injected config from php. LP 27.11.2024
declare const vippsBuyNowBlockConfig: VippsBlockConfig;
export const blockConfig = vippsBuyNowBlockConfig;
