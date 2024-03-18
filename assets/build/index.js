/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/lib/create-app.js":
/*!*******************************!*\
  !*** ./src/lib/create-app.js ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ createApp)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);

function createApp(element, component) {
  if (_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createRoot) {
    const root = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createRoot)(element);
    root.render(component);
  } else {
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.render)(component, element);
  }
}

/***/ }),

/***/ "./src/pages/PaymentRedirectPage.jsx":
/*!*******************************************!*\
  !*** ./src/pages/PaymentRedirectPage.jsx ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ PaymentRedirectPage)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);




function PaymentRedirectPage() {
  const {
    logo
  } = window.VippsMobilePaySettings;
  const searchParams = new URLSearchParams(window.location.search);
  const [response, setResponse] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const intervalHandler = setInterval(() => {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
      path: `/vipps-mobilepay-recurring/v1/orders/status/${searchParams.get('order_id')}?key=${searchParams.get('key')}`,
      method: 'GET'
    }).then(response => setResponse(response));
  }, 1000);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (!response || response.status === 'PENDING') {
      return;
    }
    clearInterval(intervalHandler);
    window.location.href = response.redirect_url;
  }, [response]);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: 'vipps-recurring-payment-redirect-page'
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: 'vipps-recurring-payment-redirect-page__container'
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: 'vipps-recurring-payment-redirect-page__container__content'
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: 'vipps-recurring-payment-redirect-page__container__content__logo'
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
    src: logo,
    alt: "Logo"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: 'vipps-recurring-payment-redirect-page__container__content__loading'
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: 'vipps-recurring-payment-redirect-page__container__content__loading__spinner'
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: 'vipps-recurring-payment-redirect-page__container__content__text'
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Verifying your payment. Please wait.', 'vipps-recurring-payments-gateway-for-woocommerce')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('You will be redirected shortly.', 'vipps-recurring-payments-gateway-for-woocommerce'))))));
}

/***/ }),

/***/ "./src/main.scss":
/*!***********************!*\
  !*** ./src/main.scss ***!
  \***********************/
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

/***/ "@wordpress/api-fetch":
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["apiFetch"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

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
/*!**********************!*\
  !*** ./src/index.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _main_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./main.scss */ "./src/main.scss");
/* harmony import */ var _lib_create_app__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./lib/create-app */ "./src/lib/create-app.js");
/* harmony import */ var _pages_PaymentRedirectPage__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./pages/PaymentRedirectPage */ "./src/pages/PaymentRedirectPage.jsx");




if (document.getElementById("vipps-mobilepay-recurring-app")) {
  (0,_lib_create_app__WEBPACK_IMPORTED_MODULE_2__["default"])(document.querySelector("#vipps-mobilepay-recurring-app"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_pages_PaymentRedirectPage__WEBPACK_IMPORTED_MODULE_3__["default"], null));
}
})();

/******/ })()
;
//# sourceMappingURL=index.js.map