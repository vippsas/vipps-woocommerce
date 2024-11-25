import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import save from './save';
import metadata from './block.json';

// @ts-ignore
registerBlockType(metadata.name, {
	edit: Edit,
	save,
});

// @ts-ignore
// const { registerBlockComponent } = wc.wcBlocksRegistry;
// const mainBlock = 'woocommerce/product-template';

// registerBlockComponent( {
//         main: mainBlock,
//         blockName:'vipps/buy-now',
//         component: Edit,
//         context: mainBlock,
// });
