import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import metadata from './block.json';
import { VippsBlockConfig } from './types';

// Injected config. LP 27.11.2024
declare const vippsBuyNowBlockConfig: VippsBlockConfig;

// @ts-ignore
registerBlockType(metadata.name, {
	edit: Edit,

	// Override metadata. LP 29.11.2024
	title: vippsBuyNowBlockConfig['vippsbuynowbutton'],
	description: vippsBuyNowBlockConfig['vippsbuynowdescription'],
	icon: (
		<img
			className={'block-editor-block-icon has-colors vipps-smile vipps-component-cion'}
			src={vippsBuyNowBlockConfig['vippssmileurl']}
		/>
	),
});
