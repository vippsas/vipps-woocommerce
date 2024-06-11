(()=>{"use strict";var e={n:r=>{var t=r&&r.__esModule?()=>r.default:()=>r;return e.d(t,{a:t}),t},d:(r,t)=>{for(var n in t)e.o(t,n)&&!e.o(r,n)&&Object.defineProperty(r,n,{enumerable:!0,get:t[n]})},o:(e,r)=>Object.prototype.hasOwnProperty.call(e,r)};const r=window.React,t=window.wp.element,n=window.wp.apiFetch;var a=e.n(n);const c=window.wp.i18n;function i({logo:e}){return(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page"},(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container"},(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container__content"},(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container__content__logo"},(0,r.createElement)("img",{src:e,alt:"Logo"})),(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container__content__loading"},(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container__content__loading__spinner"})),(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container__content__text"},(0,r.createElement)("p",null,(0,c.__)("Verifying your payment. Please wait.","vipps-recurring-payments-gateway-for-woocommerce")),(0,r.createElement)("p",null,(0,c.__)("You will be redirected shortly.","vipps-recurring-payments-gateway-for-woocommerce"))))))}function o({continueShoppingUrl:e,logo:t}){return(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page"},(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container"},(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container__content"},(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container__content__logo"},(0,r.createElement)("img",{src:t,alt:"Logo"})),(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container__content__text"},(0,r.createElement)("p",null,(0,r.createElement)("h1",{className:"vipps-recurring-payment-redirect-page__container__content__text__heading"},(0,c.__)("Order cancelled","vipps-recurring-payments-gateway-for-woocommerce"))),(0,r.createElement)("p",null,(0,c.__)("Your payment has been cancelled.","vipps-recurring-payments-gateway-for-woocommerce")),(0,r.createElement)("p",null,(0,r.createElement)("a",{href:e,className:"btn button vipps-recurring-payment-redirect-page__container__content__text__action"},(0,c.__)("Continue shopping","vipps-recurring-payments-gateway-for-woocommerce")))))))}function p({continueShoppingUrl:e,logo:t}){return(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page"},(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container"},(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container__content"},(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container__content__logo"},(0,r.createElement)("img",{src:t,alt:"Logo"})),(0,r.createElement)("div",{className:"vipps-recurring-payment-redirect-page__container__content__text"},(0,r.createElement)("p",null,(0,r.createElement)("h1",{className:"vipps-recurring-payment-redirect-page__container__content__text__heading"},(0,c.__)("An error occurred","vipps-recurring-payments-gateway-for-woocommerce"))),(0,r.createElement)("p",null,(0,c.__)("An unknown error has occurred.","vipps-recurring-payments-gateway-for-woocommerce")),(0,r.createElement)("p",null,(0,r.createElement)("a",{href:e,className:"btn button vipps-recurring-payment-redirect-page__container__content__text__action"},(0,c.__)("Continue shopping","vipps-recurring-payments-gateway-for-woocommerce")))))))}var l,m;document.getElementById("vipps-mobilepay-recurring-app")&&(l=document.querySelector("#vipps-mobilepay-recurring-app"),m=(0,r.createElement)((function(){const{logo:e,continueShoppingUrl:n}=window.VippsMobilePaySettings,c=new URLSearchParams(window.location.search),[l,m]=(0,t.useState)(null),[s,_]=(0,t.useState)(!1),[g,u]=(0,t.useState)(0),d=(0,t.useRef)(null);(0,t.useEffect)((()=>(d.current=setInterval((()=>{a()({path:`/vipps-mobilepay-recurring/v1/orders/status/${c.get("order_id")}?key=${c.get("key")}`,method:"GET"}).then((e=>m(e))).catch((()=>{u((e=>e+1))}))}),2500),()=>clearInterval(d.current))),[]),(0,t.useEffect)((()=>{_(g>=4)}),[g]),(0,t.useEffect)((()=>{s?clearInterval(d.current):l&&"PENDING"!==l.status&&!v&&(clearInterval(d.current),l.redirect_url&&(window.location.href=l.redirect_url))}),[l,s]);const v=(0,t.useMemo)((()=>!!l&&["EXPIRED","STOPPED"].includes(l.status)),[l]);return(0,r.createElement)(r.Fragment,null,!s&&(0,r.createElement)(r.Fragment,null,v?(0,r.createElement)(o,{logo:e,continueShoppingUrl:n}):(0,r.createElement)(i,{logo:e})),s&&(0,r.createElement)(p,{logo:e,continueShoppingUrl:n}))}),null),t.createRoot?(0,t.createRoot)(l).render(m):(0,t.render)(m,l))})();