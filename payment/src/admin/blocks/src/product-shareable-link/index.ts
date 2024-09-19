/**
 * External dependencies
 */
import { registerProductEditorBlockType } from '@woocommerce/product-editor';
/**
 * Internal dependencies
 */
import './editor.scss'; // see https://www.npmjs.com/package/@wordpress/scripts#using-css

/**
 * Internal dependencies
 */
import { Edit } from './edit';
import metadata from './block.json';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */

registerProductEditorBlockType({
	metadata: metadata as never,
	settings: {
		edit:Edit
	}
} );
