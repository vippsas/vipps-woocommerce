import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import save from './save';
import metadata from './block.json';
import { blockConfig } from './blockConfig';

// @ts-ignore
registerBlockType(metadata.name, {
	// Override metadata. LP 15.11.2024
	title: blockConfig.title,
	icon: (
		<img
			className={
				'block-editor-block-icon has-colors vipps-smile vipps-component-icon'
			}
			src={blockConfig.iconSrc}
			alt={blockConfig.title + ' icon'}
		/>
	),

	// Set attribute defaults. LP 15.11.2024
	attributes: {
		variant: { default: blockConfig.defaultVariant },
		language: { default: blockConfig.defaultLanguage },
	},

	edit: Edit,
	save,
});
