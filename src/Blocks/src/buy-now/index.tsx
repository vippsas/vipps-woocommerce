import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import metadata from './block.json';

// Injected config. LP 27.11.2024
declare const VippsConfig: {
	vippssmileurl: string;
	vippsbuynowbutton: string;
	vippsbuynowdescription: string;
};

// @ts-ignore
registerBlockType(metadata.name, {
	edit: Edit,

	// Override metadata. LP 29.11.2024
	title: VippsConfig['vippsbuynowbutton'],
	description: VippsConfig['vippsbuynowdescription'],
	icon: (
		<img
			className={'block-editor-block-icon has-colors vipps-smile vipps-component-cion'}
			src={VippsConfig['vippssmileurl']}
		/>
	),
});
