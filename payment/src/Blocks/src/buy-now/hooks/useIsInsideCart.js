import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

const CART_BLOCKNAME = 'woocommerce/cart';
const MINICART_BLOCKNAME = 'woocommerce/mini-cart';
const VALID_ANCESTORS = new Set([CART_BLOCKNAME, MINICART_BLOCKNAME]);

/** Whether the client is a descendent of a cart or mini-cart block. LP 2026-01-29 */
const useIsInsideCart = (clientId) =>
	useSelect(
		(select) => {
			const { getBlockParents, getBlockName } = select(blockEditorStore);

			const parents = getBlockParents(clientId);
			return parents.some((id) => VALID_ANCESTORS.has(getBlockName(id)));
		},
		[clientId]
	);

export default useIsInsideCart;
