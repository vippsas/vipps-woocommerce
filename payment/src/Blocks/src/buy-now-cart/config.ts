export interface VippsBlockConfig {
	BuyNowWithVipps: string;
	vippsbuynowbutton: string;
	vippsbuynowdescription: string;
	buttonHtml: string;
}

// Injected config from php. LP 27.11.2024
declare const vippsBuyNowCartBlockConfig: VippsBlockConfig;
export const blockConfig = vippsBuyNowCartBlockConfig;
