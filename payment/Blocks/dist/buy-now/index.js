/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@wordpress/icons/build-module/library/pencil.js"
/*!**********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/pencil.js ***!
  \**********************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__);

/**
 * WordPress dependencies
 */

const pencil = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__.SVG, {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24"
}, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__.Path, {
  d: "m19 7-3-3-8.5 8.5-1 4 4-1L19 7Zm-7 11.5H5V20h7v-1.5Z"
}));
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (pencil);
//# sourceMappingURL=pencil.js.map

/***/ },

/***/ "./src/buy-now/components/ProductSearch.tsx"
/*!**************************************************!*\
  !*** ./src/buy-now/components/ProductSearch.tsx ***!
  \**************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ProductSearch)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _config__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../config */ "./src/buy-now/config.ts");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);







function ProductSearch({
  attributes,
  setAttributes,
  hideCallback
}) {
  const [searchTerm, setSearchTerm] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [isInitialized, setIsInitialized] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [productOptions, setProductOptions] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [isLoading, setIsLoading] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const debounceMs = 300;
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (!isInitialized) {
      setIsInitialized(true);
      return;
    }
    if (!searchTerm.trim()) {
      setProductOptions([]);
      return;
    }
    const onError = error => {
      console.error('Error fetching products:', error);
      setProductOptions([]);
    };
    const path = `${_config__WEBPACK_IMPORTED_MODULE_3__.blockConfig.vippsresturl}/express-products`;
    const queryParams = {
      search: searchTerm,
      per_page: '10',
      action: 'woo_vipps_express_checkout_products',
      orderby: 'title',
      order: 'desc'
    };
    const searchTimeout = setTimeout(async () => {
      setIsLoading(true);
      _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_5__.addQueryArgs)(path, queryParams),
        method: 'GET'
      }).then(products => {
        const productOptions = products.map(product => ({
          label: product.name,
          value: product.id.toString()
        }));
        setProductOptions(productOptions);
      }).catch(onError).finally(() => setIsLoading(false));
    }, debounceMs);
    return () => clearTimeout(searchTimeout);
  }, [searchTerm]);
  const resetProduct = () => {
    setAttributes({
      productId: '',
      productName: '',
      productParentId: ''
    });
    return;
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
    children: [attributes.productName && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      style: {
        marginBottom: '8px',
        fontSize: '14px',
        color: '#666'
      },
      children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Selected product', 'woo-vipps') + ': ', ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
        children: attributes.productName
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.ComboboxControl, {
      className: "vipps-buy-now-button-product-search"
      // Opt into these to-be-made style defaults early to suppress deprectaion warnings. LP 2026-01-20
      ,
      __next40pxDefaultSize: true,
      __nextHasNoMarginBottom: true,
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Search Product', 'woo-vipps')
      // @ts-ignore: for some reason isLoading is not typed correctly. This shows a spinner when its loading, and its also in the docs: https://developer.wordpress.org/block-editor/reference-guides/components/combobox-control/. LP 2026-01-20
      ,
      isLoading: isLoading,
      value: attributes.productId,
      onChange: value => {
        const id = parseInt(value !== null && value !== void 0 ? value : '');
        if (isNaN(id)) {
          resetProduct();
          return;
        }
        const selectedOption = productOptions.find(opt => opt.value === value);
        if (!selectedOption) {
          resetProduct();
          return;
        }
        const name = selectedOption.label;
        setSearchTerm('');
        setAttributes({
          productId: id.toString(),
          productName: name
        });
      },
      onFilterValueChange: setSearchTerm,
      options: productOptions
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
      variant: "primary",
      onClick: () => {
        if (!attributes.productId || !attributes.productId) return;
        hideCallback();
      },
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Confirm', 'woo-vipps')
    })]
  });
}

/***/ },

/***/ "./src/buy-now/components/VippsSmile.tsx"
/*!***********************************************!*\
  !*** ./src/buy-now/components/VippsSmile.tsx ***!
  \***********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ VippsSmile)
/* harmony export */ });
/* harmony import */ var _config__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../config */ "./src/buy-now/config.ts");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);


function VippsSmile() {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("img", {
    className: 'block-editor-block-icon has-colors vipps-smile vipps-component-cion',
    src: _config__WEBPACK_IMPORTED_MODULE_0__.blockConfig['vippssmileurl']
  });
}

/***/ },

/***/ "./src/buy-now/config.ts"
/*!*******************************!*\
  !*** ./src/buy-now/config.ts ***!
  \*******************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   blockConfig: () => (/* binding */ blockConfig)
/* harmony export */ });
// Injected config from php. LP 27.11.2024

const blockConfig = vippsBuyNowBlockConfig;

/***/ },

/***/ "./src/buy-now/edit.tsx"
/*!******************************!*\
  !*** ./src/buy-now/edit.tsx ***!
  \******************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/pencil.js");
/* harmony import */ var _components_ProductSearch__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./components/ProductSearch */ "./src/buy-now/components/ProductSearch.tsx");
/* harmony import */ var _components_VippsSmile__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./components/VippsSmile */ "./src/buy-now/components/VippsSmile.tsx");
/* harmony import */ var _config__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./config */ "./src/buy-now/config.ts");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__);









function Edit({
  context,
  attributes,
  setAttributes
}) {
  // Migrate some attributes if variant is one of the old ones. LP 2026-07-01
  const newConfig = _config__WEBPACK_IMPORTED_MODULE_7__.blockConfig.variantMigrationMap[attributes.variant];
  if (typeof newConfig === "object") {
    setAttributes({
      ...attributes,
      ...newConfig
    });
  }

  // If this block is a child of a product context. e.g. when this block is inserted into the blocks Product collection, Single product. LP 2026-01-23
  const hasProductContext = context['postType'] === 'product';
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (attributes.hasProductContext !== hasProductContext) {
      setAttributes({
        hasProductContext
      });
    }
  }, []);
  const showEditButton = !attributes.hasProductContext;

  // only show product selection if we are not in a product context and we don't have a product id. LP 2026-01-19
  const [showProductSelection, setShowProductSelection] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(!hasProductContext && !attributes.productId);
  const language = 'store' === attributes.language ? _config__WEBPACK_IMPORTED_MODULE_7__.blockConfig.storeLanguage : attributes.language;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("div", {
      ...(0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)({
        className: 'wp-block-button wc-block-components-product-button wc-block-button-vipps'
      }),
      children: showProductSelection ?
      /*#__PURE__*/
      // Product selection mode. LP 2026-01-19
      (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
        className: "vipps-buy-now-block-edit-container",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
          className: "vipps-buy-now-block-edit-header",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_components_VippsSmile__WEBPACK_IMPORTED_MODULE_6__["default"], {}), _config__WEBPACK_IMPORTED_MODULE_7__.blockConfig['vippsbuynowbutton']]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_components_ProductSearch__WEBPACK_IMPORTED_MODULE_5__["default"], {
          attributes: attributes,
          setAttributes: setAttributes,
          hideCallback: () => setShowProductSelection(false)
        })]
      }) :
      /*#__PURE__*/
      // The WYSIWYG buy-now button. LP 2026-01-19
      (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("a", {
          className: "single-product button vipps-buy-now wp-block-button__link",
          title: _config__WEBPACK_IMPORTED_MODULE_7__.blockConfig['vippsbuynowbutton'],
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("vipps-mobilepay-button", {
            brand: _config__WEBPACK_IMPORTED_MODULE_7__.blockConfig.paymentMethod.toLowerCase(),
            language: language,
            variant: attributes.variant,
            rounded: attributes.rounded,
            verb: attributes.verb,
            compact: attributes.compact
            // @ts-ignore
          })
        }), showEditButton && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.BlockControls, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToolbarGroup, {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToolbarButton, {
              icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
              label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Edit selected product', 'woo-vipps'),
              onClick: () => setShowProductSelection(true)
            })
          })
        })]
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelBody, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
          onChange: variant => setAttributes({
            variant
          }),
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Variant', 'woo-vipps'),
          value: attributes.variant,
          options: _config__WEBPACK_IMPORTED_MODULE_7__.blockConfig.variants,
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Choose the button variant with the perfect fit for your site', 'woo-vipps')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
          onChange: language => setAttributes({
            language
          }),
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Language', 'woo-vipps'),
          value: attributes.language,
          options: _config__WEBPACK_IMPORTED_MODULE_7__.blockConfig.languages,
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Choose language', 'woo-vipps')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
          onChange: verb => setAttributes({
            verb
          }),
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Verb', 'woo-vipps'),
          value: attributes.verb,
          options: _config__WEBPACK_IMPORTED_MODULE_7__.blockConfig.verbs,
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Choose verb', 'woo-vipps')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
          onChange: rounded => setAttributes({
            rounded
          }),
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Rounded', 'woo-vipps'),
          checked: attributes.rounded
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ToggleControl, {
          onChange: compact => setAttributes({
            compact
          }),
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Compact', 'woo-vipps'),
          checked: attributes.compact
        })]
      })
    })]
  });
}

/***/ },

/***/ "react"
/*!************************!*\
  !*** external "React" ***!
  \************************/
(module) {

module.exports = window["React"];

/***/ },

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

module.exports = window["ReactJSXRuntime"];

/***/ },

/***/ "@wordpress/api-fetch"
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
(module) {

module.exports = window["wp"]["apiFetch"];

/***/ },

/***/ "@wordpress/block-editor"
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
(module) {

module.exports = window["wp"]["blockEditor"];

/***/ },

/***/ "@wordpress/blocks"
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
(module) {

module.exports = window["wp"]["blocks"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ },

/***/ "@wordpress/primitives"
/*!************************************!*\
  !*** external ["wp","primitives"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["primitives"];

/***/ },

/***/ "@wordpress/url"
/*!*****************************!*\
  !*** external ["wp","url"] ***!
  \*****************************/
(module) {

module.exports = window["wp"]["url"];

/***/ },

/***/ "./src/buy-now/block.json"
/*!********************************!*\
  !*** ./src/buy-now/block.json ***!
  \********************************/
(module) {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"woo-vipps/buy-now","version":"4.0.0","title":"Vipps MobilePay Buy Now","attributes":{"hasProductContext":{"type":"boolean","default":false},"productId":{"type":"string"},"productName":{"type":"string"},"showProductSelection":{"type":"boolean","default":true},"variant":{"type":"string","default":"primary"},"language":{"type":"string","default":"store"},"verb":{"type":"string","default":"buy"},"rounded":{"type":"boolean","default":false},"compact":{"type":"boolean","default":false},"stretched":{"type":"boolean","default":false}},"keywords":["WooCommerce","woo-gutenberg-products-block","Vipps","Vipps Express","Express","buy-now"],"category":"woocommerce","usesContext":["postId","postType"],"supports":{"html":false},"textdomain":"woo-vipps","editorScript":"file:./index.js","render":"file:./render.php"}');

/***/ }

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
/******/ 		// Check if module exists (development only)
/******/ 		if (__webpack_modules__[moduleId] === undefined) {
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
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
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!*******************************!*\
  !*** ./src/buy-now/index.tsx ***!
  \*******************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./edit */ "./src/buy-now/edit.tsx");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./block.json */ "./src/buy-now/block.json");
/* harmony import */ var _components_VippsSmile__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./components/VippsSmile */ "./src/buy-now/components/VippsSmile.tsx");
/* harmony import */ var _config__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./config */ "./src/buy-now/config.ts");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);






// @ts-ignore

(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_2__.name, {
  edit: _edit__WEBPACK_IMPORTED_MODULE_1__["default"],
  // Override metadata. LP 29.11.2024
  title: _config__WEBPACK_IMPORTED_MODULE_4__.blockConfig['vippsbuynowbutton'],
  description: _config__WEBPACK_IMPORTED_MODULE_4__.blockConfig['vippsbuynowdescription'],
  icon: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_components_VippsSmile__WEBPACK_IMPORTED_MODULE_3__["default"], {})
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map