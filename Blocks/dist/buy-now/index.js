(()=>{"use strict";const s=window.wp.blocks,i=window.wp.blockEditor,o=window.ReactJSXRuntime,t=JSON.parse('{"UU":"woo-vipps/buy-now"}');(0,s.registerBlockType)(t.UU,{edit:function({context:s,setAttributes:t}){return s.query&&t({isInQuery:!0}),(0,o.jsx)(o.Fragment,{children:(0,o.jsx)("div",{...(0,i.useBlockProps)({className:"wp-block-button wc-block-components-product-button wc-block-button-vipps"}),children:(0,o.jsxs)("a",{className:"single-product button vipps-buy-now wp-block-button__link",title:VippsConfig.BuyNowWithVipps,children:[(0,o.jsx)("span",{className:"vippsbuynow",children:VippsConfig.BuyNowWith}),(0,o.jsx)("img",{className:"inline vipps-logo-negative",src:VippsConfig.vippslogourl,alt:"Vipps"})]})})})}})})();