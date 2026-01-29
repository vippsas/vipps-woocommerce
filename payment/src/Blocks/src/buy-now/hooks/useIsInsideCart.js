import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

const CART_MINICART_BLOCKNAMES = new Set(
	[
	"woocommerce/cart",
	"woocommerce/cart-contents",
	"woocommerce/mini-cart",
	"woocommerce/mini-cart-contents",
]);

/** Whether the client is a descendent of a cart or mini-cart block. LP 2026-01-29 */
const useIsInsideCart = (clientId) =>
	useSelect(
		(select) => {
			const { getBlockParents, getBlockName } = select(blockEditorStore);
			const parents = getBlockParents(clientId);
			return parents.some((id) => CART_MINICART_BLOCKNAMES.has(getBlockName(id)));
		},
		[clientId]
	);

export default useIsInsideCart;
