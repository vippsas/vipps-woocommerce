import type { VippsBadgeBlockConfig } from './types';

// const injectedVippsBadgeBlockConfig gets injected from <pluginRoot>/Blocks/woo-vipps-blocks.php It should follow the interface VippsBadgeBlockConfig. LP 08.11.2024
// @ts-ignore
export const blockConfig: VippsBadgeBlockConfig = injectedVippsBadgeBlockConfig;
