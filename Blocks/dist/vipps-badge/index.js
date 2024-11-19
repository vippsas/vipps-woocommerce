(()=>{"use strict";const e=window.wp.blocks,a=window.wp.i18n,s=window.wp.blockEditor,t=window.wp.components,n=injectedBlockConfig,l=window.ReactJSXRuntime,i=JSON.parse('{"UU":"woo-vipps/vipps-badge"}');(0,e.registerBlockType)(i.UU,{title:n.title,icon:(0,l.jsx)("img",{className:"vipps-smile vipps-component-icon",src:n.iconSrc,alt:n.title+" icon"}),attributes:{variant:{default:n.defaultVariant}},edit:function({attributes:e,setAttributes:i}){const r=n.variants,o=e.variant;let c,g=!1;for(let e=0;e<r.length;e++)if(o==r[e].value){g=!0;break}g||r.push({label:o,value:o}),c="default"!==e.language?e.language:"";const p={className:e.className,variant:o,language:c,brand:n.brand};let u=e.className&&"undefined"!==e.className?e.className:"";switch(e.align){case"center":u+=" aligncenter";break;case"left":u+=" alignleft";break;case"right":u+=" alignright"}return(0,l.jsxs)(l.Fragment,{children:[(0,l.jsx)("div",{...(0,s.useBlockProps)({className:"vipps-badge-wrapper "+u}),children:(0,l.jsx)("vipps-mobilepay-badge",{...p})}),(0,l.jsx)(s.InspectorControls,{children:(0,l.jsxs)(t.PanelBody,{children:[(0,l.jsx)(t.SelectControl,{onChange:e=>i({variant:e}),label:(0,a.__)("Variant","woo-vipps"),value:e.variant,options:r,help:(0,a.__)("Choose the badge variant with the perfect colors for your site","woo-vipps")}),(0,l.jsx)(t.SelectControl,{onChange:e=>i({language:e}),label:(0,a.__)("Language","woo-vipps"),value:e.language,options:n.languages,help:(0,a.__)("Choose language, or use the default","woo-vipps")})]})})]})},save:function({attributes:e}){const a=n.variants,t=e.variant;let i,r=!1;for(let e=0;e<a.length;e++)if(t==a[e].value){r=!0;break}r||a.push({label:t,value:t}),i="default"!==e.language?e.language:"";const o={className:e.className,variant:t,language:i,brand:n.brand};let c=e.className&&"undefined"!==e.className?e.className:"";switch(e.align){case"center":c+=" aligncenter";break;case"left":c+=" alignleft";break;case"right":c+=" alignright"}return(0,l.jsx)(l.Fragment,{children:(0,l.jsx)("div",{...s.useBlockProps.save({className:"vipps-badge-wrapper "+c}),children:(0,l.jsx)("vipps-mobilepay-badge",{...o})})})}})})();