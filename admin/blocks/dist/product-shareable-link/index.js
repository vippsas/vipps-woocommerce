/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/product-shareable-link/edit.tsx":
/*!*********************************************!*\
  !*** ./src/product-shareable-link/edit.tsx ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Edit: () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _woocommerce_block_templates__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @woocommerce/block-templates */ "@woocommerce/block-templates");
/* harmony import */ var _woocommerce_block_templates__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_block_templates__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _woocommerce_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @woocommerce/components */ "@woocommerce/components");
/* harmony import */ var _woocommerce_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _woocommerce_product_editor__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @woocommerce/product-editor */ "@woocommerce/product-editor");
/* harmony import */ var _woocommerce_product_editor__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_product_editor__WEBPACK_IMPORTED_MODULE_5__);

/**
 * External dependencies
 */








// Extend the window object to include some WP global variables

/**
 * The edit function describes the structure of the woo-vipps/product-shareable-link block in the editor.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 */
function Edit({
  attributes
}) {
  // Get variations
  const [variations = []] = (0,_woocommerce_product_editor__WEBPACK_IMPORTED_MODULE_5__.__experimentalUseProductEntityProp)('variations');
  const shouldEnableVariations = variations.length > 0;
  const [productId] = (0,_woocommerce_product_editor__WEBPACK_IMPORTED_MODULE_5__.__experimentalUseProductEntityProp)('id');
  const blockProps = (0,_woocommerce_block_templates__WEBPACK_IMPORTED_MODULE_1__.useWooBlockProps)(attributes);
  const [metadata = [], setMetadata] = (0,_woocommerce_product_editor__WEBPACK_IMPORTED_MODULE_5__.__experimentalUseProductEntityProp)('meta_data');
  const [shareLinkNonce] = (0,_woocommerce_product_editor__WEBPACK_IMPORTED_MODULE_5__.__experimentalUseProductEntityProp)('share_link_nonce');

  // Because of they way meta_data is stored, we need to filter any metadata that starts with _vipps_shareable_links
  const links = metadata.filter(meta =>
  // Keep only the metadata that starts with _vipps_shareable_links
  meta.key.startsWith('_vipps_shareable_links') &&
  // Keep only the metadata that has a value, if the value is undefined, it means it was just deleted
  meta.value !== undefined);

  // Some state to manage the "loading/copying" state of the copy button, this is initialized as null, and set to the URL being copied when the button is clicked
  const [isCopyingURLValue, setIsCopyingURLValue] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  // State to manage the selected variant
  const [variant, setVariant] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  // State to keep track of loading state of new shareable link creation
  const [isLoading, setIsLoading] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);

  /**
   * Copies the given URL to the clipboard.
   *
   * @param url - The URL to be copied.
   */
  async function copyToClipboard(url) {
    setIsCopyingURLValue(url);
    // Use the Clipboard API if available
    if (navigator.clipboard) {
      navigator.clipboard.writeText(url);
    } else {
      // Fallback to using deprecated document.execCommand
      const textArea = document.createElement('textarea');
      textArea.value = url;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
    }
    // Wait for 2.5 seconds before resetting the state
    await new Promise(resolve => setTimeout(resolve, 2500));
    setIsCopyingURLValue(null);
  }
  async function createShareableLink() {
    try {
      setIsLoading(true);
      const params = new URLSearchParams({
        action: 'vipps_create_shareable_link',
        vipps_share_sec: shareLinkNonce,
        prodid: productId,
        varid: variant?.toString() || '0'
      });
      const response = await fetch(window.ajaxurl, {
        method: 'POST',
        body: params.toString(),
        credentials: 'include',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        }
      });
      // Throw early if the response is not ok
      if (!response.ok) {
        throw new Error();
      }

      // Parse the response as JSON
      const result = await response.json();
      // Append this new shareable link to the list of existing links
      if (result.ok) {
        setMetadata([...metadata, {
          key: '_vipps_shareable_links',
          value: {
            product_id: productId,
            variation_id: variant,
            key: result.key,
            url: result.url,
            variant: result.variant
          },
          id: -1
        }]);
      } else {
        throw new Error(result.msg);
      }
    } catch (error) {
      if (error instanceof Error) {
        // TODO: use toast or similar instead of alert
        alert((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Error creating shareable link.', 'woo-vipps') + error.message);
      }
    } finally {
      setIsLoading(false);
    }
  }

  /**
   * Removes a shareable link from the metadata.
   *
   * @param key - The key of the shareable link to remove.
   */
  function removeShareableLink(key) {
    const newMetadata = metadata.map(meta => {
      // Since we keep 2 separate keys for every 1 shareable link, we need to check for both and remove them
      // Key 1
      const isShareableLinksMeta = meta.key.startsWith('_vipps_shareable_links') && meta.value?.key == key;

      // Key 2
      const isShareableLinkSpecificKeyMeta = meta.key === '_vipps_shareable_links' && meta?.value?.key == key;

      // Remove the value if it matches the key, this will cause the meta to be deleted
      if (isShareableLinksMeta || isShareableLinkSpecificKeyMeta) {
        return {
          ...meta,
          value: undefined
        };
      }
      return meta;
    });
    // Update the metadata
    setMetadata(newMetadata);
  }
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    ...blockProps
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, attributes.title), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "create-link-section"
  }, shouldEnableVariations && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
    className: "vipps-sharelink-variant",
    value: variant?.toString(),
    onChange: value => setVariant(parseInt(value, 10)),
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Variation', 'woo-vipps'),
    options: variations.map(variation => {
      return {
        label: variation.toString(),
        value: variation.toString()
      };
    })
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
    variant: "secondary",
    disabled: isLoading,
    type: "button",
    onClick: createShareableLink
  }, isLoading && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Spinner, null), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Create shareable link', 'woo-vipps'))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_components__WEBPACK_IMPORTED_MODULE_2__.Table, {
    headers: [{
      key: 'variant',
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Variation', 'woo-vipps'),
      visible: shouldEnableVariations
    }, {
      key: 'link',
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Link', 'woo-vipps')
    }, {
      key: 'actions',
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Actions', 'woo-vipps'),
      cellClassName: 'table-actions-col'
    }].filter(header => header.visible !== false) // Filter out any headers that are not visible
    ,
    rows: links.map(item => {
      return [{
        key: 'variant',
        display: item.value.variant,
        visible: shouldEnableVariations
      }, {
        key: 'link',
        display: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
          href: item.value.url,
          variant: "link",
          target: "_blank"
        }, item.value.url)
      }, {
        key: 'actions',
        display: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
          disabled:
          // Disable copy button if any url is being copied
          isCopyingURLValue !== null,
          onClick: () => copyToClipboard(item.value.url)
        }, isCopyingURLValue === item.value.url ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Copied!', 'woo-vipps') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Copy', 'woo-vipps')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
          onClick: () => removeShareableLink(item.value.key)
        }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Delete', 'woo-vipps')))
      }].filter(row => row.visible !== false); // Filter out any rows that are not visible
    })
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    dangerouslySetInnerHTML: {
      __html: attributes.message
    }
  }));
}

/***/ }),

/***/ "./src/product-shareable-link/editor.scss":
/*!************************************************!*\
  !*** ./src/product-shareable-link/editor.scss ***!
  \************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "@woocommerce/block-templates":
/*!****************************************!*\
  !*** external ["wc","blockTemplates"] ***!
  \****************************************/
/***/ ((module) => {

module.exports = window["wc"]["blockTemplates"];

/***/ }),

/***/ "@woocommerce/components":
/*!************************************!*\
  !*** external ["wc","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wc"]["components"];

/***/ }),

/***/ "@woocommerce/product-editor":
/*!***************************************!*\
  !*** external ["wc","productEditor"] ***!
  \***************************************/
/***/ ((module) => {

module.exports = window["wc"]["productEditor"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "./src/product-shareable-link/block.json":
/*!***********************************************!*\
  !*** ./src/product-shareable-link/block.json ***!
  \***********************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"woo-vipps/product-shareable-link","version":"0.1.0","title":"Blocks","category":"widgets","icon":"flag","description":"A block that handles Vipps MobilePay shareable links for the new WooCommerce product editor.","attributes":{"message":{"type":"string","__experimentalRole":"content","source":"text","selector":"div"},"title":{"type":"string"}},"supports":{"html":false,"inserter":false},"textdomain":"woo-vipps","editorScript":"file:./index.js","editorStyle":"file:./index.css","style":"file:./index.css"}');

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!*********************************************!*\
  !*** ./src/product-shareable-link/index.ts ***!
  \*********************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _woocommerce_product_editor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @woocommerce/product-editor */ "@woocommerce/product-editor");
/* harmony import */ var _woocommerce_product_editor__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_product_editor__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./editor.scss */ "./src/product-shareable-link/editor.scss");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/product-shareable-link/edit.tsx");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./block.json */ "./src/product-shareable-link/block.json");
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
 // see https://www.npmjs.com/package/@wordpress/scripts#using-css

/**
 * Internal dependencies
 */



/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/#registering-a-block
 */

(0,_woocommerce_product_editor__WEBPACK_IMPORTED_MODULE_0__.registerProductEditorBlockType)({
  metadata: _block_json__WEBPACK_IMPORTED_MODULE_3__,
  settings: {
    edit: _edit__WEBPACK_IMPORTED_MODULE_2__.Edit
  }
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map