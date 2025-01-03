(function(y,z){"use strict";var F=document.createElement("style");F.textContent=`.vipps-mobilepay-react-admin-page{color:#161225;font-family:Vipps Text,Roboto,Helvetica,Arial,sans-serif}.vipps-mobilepay-react-loading{display:inline-block;position:relative;width:20px;height:20px}.vipps-mobilepay-react-loading div{box-sizing:border-box;display:block;position:absolute;width:16px;height:16px;margin:2px;border:2px solid #666;border-radius:50%;animation:vipps-mobilepay-react-loading 1.2s cubic-bezier(.5,0,.5,1) infinite;border-color:#666 transparent transparent transparent}.vipps-mobilepay-react-loading div:nth-child(1){animation-delay:-.45s}.vipps-mobilepay-react-loading div:nth-child(2){animation-delay:-.3s}.vipps-mobilepay-react-loading div:nth-child(3){animation-delay:-.15s}@keyframes vipps-mobilepay-react-loading{0%{transform:rotate(0)}to{transform:rotate(360deg)}}.vipps-mobilepay-react-form-field{margin-bottom:2rem;display:flex;gap:2rem}.vipps-mobilepay-react-form-field>label{width:15%}.vipps-mobilepay-react-form-field>div{width:85%}.vipps-mobilepay-react-col{display:flex;flex-direction:column;gap:.5rem}.vipps-mobilepay-react-label{font-weight:600;color:#1d2327;font-size:14px}.vipps-mobilepay-react-tab-description{margin-bottom:1rem}.vipps-mobilepay-react-field-description{color:#666}.vipps-mobilepay-react-button-actions{display:flex;column-gap:1rem}.vipps-mobilepay-react-notification-banner{padding:1rem;min-height:40px;font-size:1rem;display:flex;align-items:center}.vipps-mobilepay-react-notification-banner-error{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb}.vipps-mobilepay-react-notification-banner-warning{background-color:#fff3cd;color:#856404;border:1px solid #ffeeba}.vipps-mobilepay-react-notification-banner-success{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb}.vipps-mobilepay-react-save-section{display:flex;align-items:center;gap:1rem}.vipps-mobilepay-react-save-confirmation{display:flex;align-items:center;gap:.5rem;color:#2a8947;font-weight:500}.vipps-mobilepay-react-save-confirmation .dashicons{font-size:1.2rem;width:auto;height:auto}.vipps-mobilepay-form-container{display:flex;flex-direction:row;align-items:flex-start;gap:12%}.vipps-mobilepay-react-form-field{display:flex;flex-direction:row;justify-content:flex-start}.vipps-mobilepay-form-help-box{background-color:#fff;border-color:#8c8f94;border-radius:4px;border:1px solid #8c8f94;padding:30px;width:13rem}.vipps-mobilepay-form-help-box a{text-wrap:nowrap}.vipps-mobilepay-react-checkout-confirm .body{display:flex;flex-direction:row;justify-content:space-between;gap:12%}.vipps-mobilepay-react-checkout-confirm .title{margin-bottom:1em;font-weight:700}.vipps-mobilepay-react-checkout-confirm li{list-style:inside;margin-left:1em}.vipps-mobilepay-react-checkout-confirm img{width:30rem;margin-right:50px;display:flex;align-items:center;justify-content:center}.vipps-mobilepay-react-checkout .title{margin-bottom:1em;font-weight:700}
`,document.head.appendChild(F);var S={},I=z;S.createRoot=I.createRoot,S.hydrateRoot=I.hydrateRoot;const K=window;if(!K.VippsMobilePayReactTranslations)throw new Error("VippsMobilePayReactTranslations not found, make sure to include this using wp_localize_script()");if(!K.VippsMobilePayReactOptions)throw new Error("VippsMobilePayReactOptions not found, make sure to include this using wp_localize_script()");if(!K.VippsMobilePayReactMetadata)throw new Error("VippsMobilePayReactMetadata not found, make sure to include this using wp_localize_script()");function t(e){let a=K.VippsMobilePayReactTranslations;const n=e.split(".");for(const i of n)if(typeof a=="object"&&a!==null)a=a[i];else break;return a!==void 0?a:e}function j(e){var a;return((a=K.VippsMobilePayReactMetadata)==null?void 0:a[e])??null}const{VippsMobilePayReactTranslations:pe,VippsMobilePayReactOptions:H,VippsMobilePayReactMetadata:me}=K;function L({tabs:e,onTabChange:a,activeTab:n}){return React.createElement("div",{className:"vippstabholder",id:"vippstabholder"},e.map((i,c)=>React.createElement("h3",{key:c,id:`woocommerce_vipps_${i}_options`,"aria-selected":i===n?"true":"false",className:`wc-settings-sub-title tab ${i===n?"active":""}`,title:i,onClick:()=>a(i),style:{cursor:"pointer"}},i)))}function P(e){switch(e){case"NO":return"Vipps";case"FI":return"MobilePay";case"DK":return"MobilePay"}return"MobilePay"}const T=y.createContext(null);function q({children:e}){const[a,n]=y.useState(H);function i(o){const s=a[o],m=o+".default",_=t(m),h=_!=m?_:null;return s??h??""}function c(o){const s=o+".default";return t(s)!=s}async function r(o,s){console.log("setOption called:",{key:o,value:s}),n(m=>({...m,[o]:s??null}))}async function u(o){n(o)}async function p(o){console.log("submitChanges - Current values:",a),o!=null&&o.forceEnable&&(r("enabled","yes"),a.enabled="yes");const s=document.getElementById("vippsadmin_nonce").value,m=new URLSearchParams({action:"vipps_update_admin_settings",vippsadmin_nonce:s});for(const[b,N]of Object.entries(a)){const O="values["+b+"]";N?m.append(O,N):m.append(O,"")}const h=await(await fetch(j("admin_url"),{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},credentials:"include",body:m.toString()})).json();return console.log("submitChanges - Server response:",h),h}return React.createElement(T.Provider,{value:{setOption:r,getOption:i,hasOption:c,setOptions:u,submitChanges:p}},e)}function E(){const e=y.useContext(T);if(!e)throw new Error("useWP must be used within a WPOptionsProvider");return e}function f({htmlString:e,className:a=""}){return React.createElement("span",{className:[a].join(" "),dangerouslySetInnerHTML:{__html:e}})}function D(){return React.createElement("div",{className:"vipps-mobilepay-react-loading"},React.createElement("div",null),React.createElement("div",null),React.createElement("div",null),React.createElement("div",null))}function B(e){return React.createElement("input",{...e,className:["input-text regular-input",e.className??""].join(" "),style:{height:30}},e.children)}function Z(e){return React.createElement("form",{...e,className:["",e.className??""].join(" ")},e.children)}function k(e){return React.createElement("label",{...e,className:["vipps-mobilepay-react-label",e.className??""].join(" ")},e.children)}function R({variant:e,isLoading:a,disabled:n,...i}){return React.createElement("button",{...i,disabled:a||n,className:[`button-${e}`,i.className??""].join(" ")},React.createElement("div",{style:{display:"flex",alignItems:"center"}},a&&React.createElement(D,null),React.createElement("span",null,i.children)))}function J({id:e,name:a,onChange:n,checked:i,children:c,className:r}){return React.createElement("input",{id:e,name:a,checked:i?C(i):void 0,onChange:u=>n(M(u.target.checked)),type:"checkbox",className:[r??""].join(" ")},c)}function G(e){return React.createElement("select",{...e,className:[e.className??""].join(" ")},e.children)}function A(e){return React.createElement("option",{...e,className:[e.className??""].join(" ")},e.children)}function Q(e){return React.createElement("textarea",{...e,className:[e.className??""].join(" ")},e.children)}function x(e){return React.createElement("div",{...e,className:["vipps-mobilepay-react-form-field",e.className??""].join(" ")},e.children)}function C(e){return e==="yes"}function M(e){return e?"yes":"no"}function V(e){return C(e)?"no":"yes"}function l({name:e,titleKey:a,labelKey:n,descriptionKey:i,inverted:c=!1}){const{getOption:r,setOption:u}=E();return React.createElement(x,null,React.createElement(k,{htmlFor:e},t(a)),React.createElement("div",{className:"vipps-mobilepay-react-col"},React.createElement("div",{className:"vipps-mobilepay-react-row-center"},React.createElement(J,{id:e,name:e,checked:c?V(r(e)):r(e),onChange:p=>c?u(e,V(p)):u(e,p)}),n&&React.createElement("label",{htmlFor:e},React.createElement(f,{htmlString:t(n)}))),i&&React.createElement(f,{className:"vipps-mobilepay-react-field-description",htmlString:t(i)})))}function g({name:e,titleKey:a,labelKey:n,descriptionKey:i,options:c,onChange:r,required:u=!1,includeEmptyOption:p=!1}){const{getOption:o,setOption:s}=E();return React.createElement(x,null,React.createElement(k,{htmlFor:e},t(a)),React.createElement("div",{className:"vipps-mobilepay-react-col"},React.createElement(G,{id:e,name:e,onChange:m=>{s(e,m.target.value),r&&r(m)},required:u,value:o(e)},p&&React.createElement(A,{value:""}),c.map(m=>React.createElement(A,{key:m.value,value:m.value},m.label))),React.createElement("div",null,n&&React.createElement(f,{htmlString:t(n)})),i&&React.createElement(f,{className:"vipps-mobilepay-react-field-description",htmlString:t(i)})))}function d({name:e,titleKey:a,labelKey:n,descriptionKey:i,pattern:c,required:r,asterisk:u,type:p="text"}){const{getOption:o,setOption:s}=E(),[m,_]=y.useState(!1),h=!m&&u;return React.createElement(x,null,React.createElement(k,{htmlFor:e},t(a)),React.createElement("div",{className:"vipps-mobilepay-react-col"},React.createElement(B,{id:e,name:e,onChange:b=>s(e,b.target.value),value:o(e),pattern:c,required:r,onFocus:()=>_(!0),onBlur:()=>_(!1),type:h?"password":p}),React.createElement("div",null,n&&React.createElement(f,{htmlString:t(n)})),i&&React.createElement(f,{className:"vipps-mobilepay-react-field-description",htmlString:t(i)})))}function X({name:e,titleKey:a,labelKey:n,descriptionKey:i,rows:c=5}){const{getOption:r,setOption:u}=E();return React.createElement(x,null,React.createElement(k,{htmlFor:e},t(a)),React.createElement("div",{className:"vipps-mobilepay-react-col"},React.createElement(Q,{id:e,name:e,onChange:p=>u(e,p.target.value),value:r(e),rows:c}),React.createElement("div",null,n&&React.createElement(f,{htmlString:t(n)})),i&&React.createElement(f,{className:"vipps-mobilepay-react-field-description",htmlString:t(i)})))}function Y(){const{setOption:e,getOption:a}=E();return React.createElement("div",null,React.createElement("p",{className:"vipps-mobilepay-react-tab-description"}),React.createElement(l,{name:"enabled",titleKey:"enabled.title",labelKey:"enabled.label"}),React.createElement(g,{name:"country",titleKey:"country.title",descriptionKey:"country.description",includeEmptyOption:!1,required:!0,onChange:n=>{const i=P(n.target.value);a("payment_method_name")||e("payment_method_name",i)},options:[{label:t("country.options.NO"),value:"NO"},{label:t("country.options.FI"),value:"FI"},{label:t("country.options.DK"),value:"DK"}]}),React.createElement(g,{name:"payment_method_name",titleKey:"payment_method_name.title",descriptionKey:"payment_method_name.description",includeEmptyOption:!1,required:!0,options:[{label:t("payment_method_name.options.Vipps"),value:"Vipps"},{label:t("payment_method_name.options.MobilePay"),value:"MobilePay"}]}),React.createElement(d,{name:"orderprefix",titleKey:"orderprefix.title",descriptionKey:"orderprefix.description",pattern:"[a-zA-Z0-9-]+",required:!0}),React.createElement(d,{asterisk:!0,name:"merchantSerialNumber",titleKey:"merchantSerialNumber.title",descriptionKey:"merchantSerialNumber.description"}),React.createElement(d,{asterisk:!0,name:"clientId",titleKey:"clientId.title",descriptionKey:"clientId.description"}),React.createElement(d,{asterisk:!0,name:"secret",titleKey:"secret.title",descriptionKey:"secret.description"}),React.createElement(d,{asterisk:!0,name:"Ocp_Apim_Key_eCommerce",titleKey:"Ocp_Apim_Key_eCommerce.title",descriptionKey:"Ocp_Apim_Key_eCommerce.description"}),React.createElement(g,{name:"result_status",titleKey:"result_status.title",descriptionKey:"result_status.description",options:[{label:t("result_status.options.on-hold"),value:"on-hold"},{label:t("result_status.options.processing"),value:"processing"}]}),React.createElement(X,{name:"description",titleKey:"description.title",descriptionKey:"description.description",rows:5}),React.createElement(l,{name:"vippsdefault",titleKey:"vippsdefault.title",labelKey:"vippsdefault.label"}))}function $(){return React.createElement("div",null,React.createElement("p",{className:"vipps-mobilepay-react-tab-description"},t("express_options.description")),React.createElement(l,{name:"cartexpress",titleKey:"cartexpress.title",labelKey:"cartexpress.title",descriptionKey:"cartexpress.description"}),React.createElement(g,{name:"singleproductexpress",titleKey:"singleproductexpress.title",descriptionKey:"singleproductexpress.description",options:[{value:"none",label:t("singleproductexpress.options.none")},{value:"some",label:t("singleproductexpress.options.some")},{value:"all",label:t("singleproductexpress.options.all")}]}),React.createElement(l,{name:"singleproductexpressarchives",titleKey:"singleproductexpressarchives.title",labelKey:"singleproductexpressarchives.label",descriptionKey:"singleproductexpressarchives.description"}),React.createElement(l,{name:"expresscheckout_termscheckbox",titleKey:"expresscheckout_termscheckbox.title",labelKey:"expresscheckout_termscheckbox.label",descriptionKey:"expresscheckout_termscheckbox.description"}),React.createElement(l,{name:"expresscheckout_always_address",titleKey:"expresscheckout_always_address.title",labelKey:"expresscheckout_always_address.label",descriptionKey:"expresscheckout_always_address.description"}),React.createElement(l,{name:"enablestaticshipping",titleKey:"enablestaticshipping.title",labelKey:"enablestaticshipping.label",descriptionKey:"enablestaticshipping.description"}),React.createElement(l,{name:"expresscreateuser",titleKey:"expresscreateuser.title",labelKey:"expresscreateuser.label",descriptionKey:"expresscreateuser.description"}),React.createElement(l,{name:"singleproductbuynowcompatmode",titleKey:"singleproductbuynowcompatmode.title",labelKey:"singleproductbuynowcompatmode.label",descriptionKey:"singleproductbuynowcompatmode.description"}),React.createElement(l,{name:"deletefailedexpressorders",titleKey:"deletefailedexpressorders.title",labelKey:"deletefailedexpressorders.label",descriptionKey:"deletefailedexpressorders.description"}))}function ee(){const{getOption:e,hasOption:a}=E(),n=e("vcs_porterbuddy")==="yes",i=e("vcs_helthjem")==="yes",c=a("checkout_external_payments_klarna"),r=c;return React.createElement("div",null,React.createElement("p",{className:"vipps-mobilepay-react-tab-description"},React.createElement(f,{htmlString:t("checkout_options.description")})),React.createElement(l,{name:"vipps_checkout_enabled",titleKey:"vipps_checkout_enabled.title",labelKey:"vipps_checkout_enabled.label",descriptionKey:"vipps_checkout_enabled.description"}),React.createElement(l,{name:"checkoutcreateuser",titleKey:"checkoutcreateuser.title",labelKey:"checkoutcreateuser.label",descriptionKey:"checkoutcreateuser.description"}),React.createElement(l,{name:"enablestaticshipping_checkout",titleKey:"enablestaticshipping_checkout.title",labelKey:"enablestaticshipping_checkout.label",descriptionKey:"enablestaticshipping_checkout.description"}),React.createElement(l,{name:"requireUserInfo_checkout",titleKey:"requireUserInfo_checkout.title",labelKey:"requireUserInfo_checkout.label",descriptionKey:"requireUserInfo_checkout.description"}),React.createElement(l,{name:"noAddressFields",titleKey:"noAddressFields.title",labelKey:"noAddressFields.label",descriptionKey:"noAddressFields.description"}),React.createElement(l,{name:"noContactFields",titleKey:"noContactFields.title",labelKey:"noContactFields.label",descriptionKey:"noContactFields.description"}),r&&React.createElement(React.Fragment,null,React.createElement("h3",{className:"vipps-mobilepay-react-trab-description"},t("checkout_external_payment_title.title")),React.createElement("p",null,t("checkout_external_payment_title.description")),c&&React.createElement(l,{name:"checkout_external_payments_klarna",titleKey:"checkout_external_payments_klarna.title",labelKey:"checkout_external_payments_klarna.label",descriptionKey:"checkout_external_payments_klarna.description"})),React.createElement("h3",{className:"vipps-mobilepay-react-tab-description"},t("checkout_shipping.title")),React.createElement("p",null,t("checkout_shipping.description")),React.createElement(l,{name:"vcs_posten",titleKey:"vcs_posten.title",descriptionKey:"vcs_posten.description",labelKey:"vcs_posten.label"}),React.createElement(l,{name:"vcs_postnord",titleKey:"vcs_postnord.title",descriptionKey:"vcs_postnord.description",labelKey:"vcs_postnord.label"}),React.createElement(l,{name:"vcs_porterbuddy",titleKey:"vcs_porterbuddy.title",descriptionKey:"vcs_porterbuddy.description",labelKey:"vcs_porterbuddy.label"}),n&&React.createElement(React.Fragment,null,React.createElement(d,{asterisk:!0,name:"vcs_porterbuddy_publicToken",titleKey:"vcs_porterbuddy_publicToken.title",descriptionKey:"vcs_porterbuddy_publicToken.description"}),React.createElement(d,{asterisk:!0,name:"vcs_porterbuddy_apiKey",titleKey:"vcs_porterbuddy_apiKey.title",descriptionKey:"vcs_porterbuddy_apiKey.description"}),React.createElement(d,{name:"vcs_porterbuddy_phoneNumber",titleKey:"vcs_porterbuddy_phoneNumber.title",descriptionKey:"vcs_porterbuddy_phoneNumber.description"})),React.createElement(l,{name:"vcs_helthjem",titleKey:"vcs_helthjem.title",descriptionKey:"vcs_helthjem.description",labelKey:"vcs_helthjem.label"}),i&&React.createElement(React.Fragment,null,React.createElement(d,{type:"number",name:"vcs_helthjem_shopId",titleKey:"vcs_helthjem_shopId.title",descriptionKey:"vcs_helthjem_shopId.description"}),React.createElement(d,{name:"vcs_helthjem_username",titleKey:"vcs_helthjem_username.title",descriptionKey:"vcs_helthjem_username.description"}),React.createElement(d,{asterisk:!0,name:"vcs_helthjem_password",titleKey:"vcs_helthjem_password.title",descriptionKey:"vcs_helthjem_password.description"})))}function te({onUpload:e}){return{handleImageUpload:()=>{const n=wp.media({library:{type:"image"},button:{},multiple:!1});n.on("select",()=>{const i=n.state().get("selection").first().toJSON();let c="";i.url?c=i.url:i.sizes&&i.sizes.thumbnail&&(c=i.sizes.thumbnail.url),c&&e(i.id,c)}),n.open()}}}function U({variant:e,text:a}){return React.createElement("div",{className:`vipps-mobilepay-react-notification-banner vipps-mobilepay-react-notification-banner-${e}`},Array.isArray(a)?a.map((n,i)=>React.createElement("p",{key:i},n)):React.createElement("p",null,a))}function ae(){const{getOption:e,setOption:a}=E(),[n,i]=y.useState(null);function c(){a("receiptimage",""),a("receiptimage_url",""),i(null)}const{handleImageUpload:r}=te({onUpload(o,s){const m=new Image;m.src=s,m.onload=()=>{if(m.height<167){i(t("receipt_image_error"));return}i(null),console.log("Image Upload - Setting values:",{id:o,url:s}),a("receiptimage",o),a("receiptimage_url",s)}}}),u=e("receiptimage"),p=e("receiptimage_url");return React.createElement("div",null,React.createElement("p",{className:"vipps-mobilepay-react-tab-description"},t("advanced_options.description")),React.createElement(l,{labelKey:"vippsorderattribution.label",name:"vippsorderattribution",titleKey:"vippsorderattribution.title",descriptionKey:"vippsorderattribution.description"}),React.createElement(g,{name:"vippsspecialpagetemplate",titleKey:"vippsspecialpagetemplate.title",descriptionKey:"vippsspecialpagetemplate.description",options:Object.entries(t("vippsspecialpagetemplate.options")).map(([o,s])=>({label:s,value:o||""}))}),React.createElement(g,{name:"vippsspecialpageid",titleKey:"vippsspecialpageid.title",descriptionKey:"vippsspecialpageid.description",options:Object.entries(t("vippsspecialpageid.options")).map(([o,s])=>({label:s,value:o||""}))}),React.createElement(l,{name:"sendreceipts",titleKey:"sendreceipts.title",labelKey:"sendreceipts.label",descriptionKey:"sendreceipts.description"}),n&&React.createElement(U,{variant:"error",text:n}),React.createElement(x,null,React.createElement(k,{htmlFor:"woocommerce_vipps_receiptimage"},t("receiptimage.title")),React.createElement("div",{className:"vipps-mobilepay-react-col"},React.createElement("div",null,u?React.createElement(React.Fragment,null,React.createElement("img",{src:p,id:u,style:{width:200}}),React.createElement(R,{type:"button",onClick:c,variant:"link"},t("remove_image"))):React.createElement(R,{type:"button",onClick:r,variant:"link"},t("upload_image")),React.createElement("input",{type:"hidden",name:"woocommerce_vipps_receiptimage",id:"woocommerce_vipps_receiptimage",value:u})),React.createElement("span",{className:"vipps-mobilepay-react-field-description"},t("receiptimage.description"),React.createElement("br",null),React.createElement("small",null,t("receipt_image_size_requirement"))))),React.createElement(l,{name:"use_flock",titleKey:"use_flock.title",descriptionKey:"use_flock.description",labelKey:"use_flock.label"}),React.createElement(l,{name:"developermode",titleKey:"developermode.title",descriptionKey:"developermode.description",labelKey:"developermode.label"}))}function ie(e){const[a,n]=y.useState(()=>window.location.hash),i=y.useCallback(()=>{n(window.location.hash)},[]);y.useEffect(()=>(window.addEventListener("hashchange",i),()=>{window.removeEventListener("hashchange",i)}),[i]);const c=y.useCallback(u=>{u!==a&&(window.location.hash="#"+u)},[a]);return y.useEffect(()=>{!a&&e&&c(e)},[a,e,c]),[decodeURIComponent(a.replace("#","")),c]}function ne(){return React.createElement("div",null,React.createElement("p",{className:"vipps-mobilepay-react-tab-description"},t("developertitle.description")),React.createElement(l,{name:"testmode",titleKey:"testmode.title",descriptionKey:"testmode.description",labelKey:"testmode.label"}),React.createElement(d,{name:"merchantSerialNumber_test",titleKey:"merchantSerialNumber_test.title",labelKey:"merchantSerialNumber_test.description"}),React.createElement(d,{asterisk:!0,name:"clientId_test",titleKey:"clientId_test.title",labelKey:"clientId_test.description"}),React.createElement(d,{asterisk:!0,name:"secret_test",titleKey:"secret_test.title",labelKey:"secret_test.description"}),React.createElement(d,{asterisk:!0,name:"Ocp_Apim_Key_eCommerce_test",titleKey:"Ocp_Apim_Key_eCommerce_test.title",labelKey:"Ocp_Apim_Key_eCommerce_test.description"}))}function ce({isLoading:e}){const{getOption:a,setOption:n,hasOption:i}=E(),[c,r]=y.useState("CHECKOUT"),u=a("vcs_porterbuddy")==="yes",p=a("vcs_helthjem")==="yes",o=i("checkout_external_payments_klarna"),s=o,m=a("payment_method_name")==="Vipps",_=h=>m?h:h.replace("vipps","mobilepay");return React.createElement(React.Fragment,null,c==="ESSENTIAL"&&React.createElement(React.Fragment,null,React.createElement("h3",{className:"vipps-mobilepay-react-tab-description"},t("initial_settings")),React.createElement("div",{className:"vipps-mobilepay-form-container"},React.createElement("div",{className:"vipps-mobilepay-form-col"},React.createElement(g,{name:"country",titleKey:"country.title",descriptionKey:"country.description",onChange:h=>{const b=P(h.target.value);n("payment_method_name",b)},required:!0,includeEmptyOption:!1,options:[{label:t("country.options.NO"),value:"NO"},{label:t("country.options.FI"),value:"FI"},{label:t("country.options.DK"),value:"DK"}]}),React.createElement(g,{name:"payment_method_name",titleKey:"payment_method_name.title",descriptionKey:"payment_method_name.description",required:!0,includeEmptyOption:!1,options:[{label:t("payment_method_name.options.Vipps"),value:"Vipps"},{label:t("payment_method_name.options.MobilePay"),value:"MobilePay"}]}),React.createElement(d,{asterisk:!0,name:"merchantSerialNumber",titleKey:"merchantSerialNumber.title",descriptionKey:"merchantSerialNumber.description",required:!0}),React.createElement(d,{asterisk:!0,name:"clientId",titleKey:"clientId.title",descriptionKey:"clientId.description",required:!0}),React.createElement(d,{asterisk:!0,name:"secret",titleKey:"secret.title",descriptionKey:"secret.description",required:!0}),React.createElement(d,{asterisk:!0,name:"Ocp_Apim_Key_eCommerce",titleKey:"Ocp_Apim_Key_eCommerce.title",descriptionKey:"Ocp_Apim_Key_eCommerce.description",required:!0}),React.createElement(l,{name:"vipps_checkout_enabled",titleKey:"vipps_checkout_enabled_simple.title",labelKey:"vipps_checkout_enabled_simple.label",descriptionKey:"vipps_checkout_enabled_simple.description"}),React.createElement(x,null,React.createElement(k,null),React.createElement("div",{className:"vipps-mobilepay-react-col"},React.createElement(R,{style:{alignSelf:"flex-start"},variant:"primary",type:"button",onClick:h=>{h.preventDefault();const b=h.currentTarget.closest("form");if(!b)throw new Error("Form not found");b.reportValidity()&&(C(a("vipps_checkout_enabled"))?r("CHECKOUT"):r("CHECKOUT_CONFIRM"))}},t("next_step"))))),React.createElement("div",{className:"vipps-mobilepay-form-col vipps-mobilepay-form-help-box"},React.createElement("div",null,React.createElement("strong",{className:"title"},t("help_box.get_started")),React.createElement("br",null),React.createElement("a",{href:"https://wordpress.org/plugins/woo-vipps/"},t("help_box.documentation")),React.createElement("br",null),React.createElement("a",{href:"https://portal.vippsmobilepay.com"},t("help_box.portal"))),React.createElement("br",null),React.createElement("div",null,React.createElement("strong",{className:"title"},t("help_box.support.title")),React.createElement("br",null),React.createElement(f,{htmlString:t("help_box.support.description")}))))),c==="CHECKOUT_CONFIRM"&&React.createElement(React.Fragment,null,React.createElement("div",{className:"vipps-mobilepay-react-checkout-confirm"},React.createElement("h1",{className:"title"},a("payment_method_name")==="Vipps"?t("checkout_confirm.title.vipps"):t("checkout_confirm.title.mobilepay")),React.createElement("div",{className:"body"},React.createElement("div",{className:"list"},React.createElement("strong",null,a("payment_method_name")==="Vipps"?t("checkout_confirm.paragraph1.header.vipps"):t("checkout_confirm.paragraph1.header.mobilepay")),React.createElement("ul",null,React.createElement("li",null,t("checkout_confirm.paragraph1.first")),React.createElement("li",null,t("checkout_confirm.paragraph1.second")),React.createElement("li",null,t("checkout_confirm.paragraph1.third"))),React.createElement("strong",null,t("checkout_confirm.paragraph2.header")),React.createElement("ul",null,React.createElement("li",null,t("checkout_confirm.paragraph2.first")),React.createElement("li",null,t("checkout_confirm.paragraph2.second")))),React.createElement("img",{src:a("payment_method_name")=="Vipps"?t("checkout_confirm.img.vipps.src"):t("checkout_confirm.img.mobilepay.src"),alt:a("payment_method_name")=="Vipps"?t("checkout_confirm.img.vipps.alt"):t("checkout_confirm.img.mobilepay.alt")})),React.createElement("div",{className:"vipps-mobilepay-react-button-actions"},React.createElement(R,{variant:"primary",isLoading:e,onClick:()=>{n("vipps_checkout_enabled",M(!0)),r("CHECKOUT")}},t("checkout_confirm.accept")),React.createElement(R,{variant:"secondary"},t("checkout_confirm.skip"))))),c==="CHECKOUT"&&React.createElement(React.Fragment,null,React.createElement("div",{className:"vipps-mobilepay-react-checkout-confirm"},React.createElement("h1",{className:"vipps-mobilepay-react-tab-description title"},t(_("checkout_options_simple_vipps.title"))),React.createElement("p",null,t(_("checkout_options_simple_vipps.description"))),React.createElement(l,{name:"checkoutcreateuser",titleKey:_("checkoutcreateuser_simple_vipps.title"),labelKey:_("checkoutcreateuser_simple_vipps.label"),descriptionKey:_("checkoutcreateuser_simple_vipps.description")}),React.createElement(l,{name:"enablestaticshipping_checkout",titleKey:"enablestaticshipping_checkout_simple.title",labelKey:"enablestaticshipping_checkout_simple.label",descriptionKey:"enablestaticshipping_checkout_simple.description",inverted:!0}),React.createElement(l,{name:"noAddressFields",titleKey:"noAddressFields_simple.title",labelKey:"noAddressFields_simple.label",descriptionKey:"noAddressFields_simple.description",inverted:!0}),React.createElement("h3",{className:"vipps-mobilepay-react-tab-description"},t(_("checkout_shipping_simple_vipps.title"))),React.createElement("p",null,t(_("checkout_shipping_simple_vipps.description"))),React.createElement(l,{name:"vcs_posten",titleKey:"vcs_posten_simple.title",labelKey:"vcs_posten_simple.label",descriptionKey:"vcs_posten_simple.description"}),React.createElement(l,{name:"vcs_postnord",titleKey:"vcs_postnord_simple.title",labelKey:"vcs_postnord_simple.label",descriptionKey:"vcs_postnord_simple.description"}),React.createElement(l,{name:"vcs_porterbuddy",titleKey:"vcs_porterbuddy_simple.title",labelKey:"vcs_porterbuddy_simple.label",descriptionKey:"vcs_porterbuddy_simple.description"}),u&&React.createElement(React.Fragment,null,React.createElement(d,{asterisk:!0,name:"vcs_porterbuddy_publicToken",titleKey:"vcs_porterbuddy_publicToken_simple.title",descriptionKey:"vcs_porterbuddy_publicToken_simple.description"}),React.createElement(d,{asterisk:!0,name:"vcs_porterbuddy_apiKey",titleKey:"vcs_porterbuddy_apiKey_simple.title",descriptionKey:"vcs_porterbuddy_apiKey_simple.description"}),React.createElement(d,{name:"vcs_porterbuddy_phoneNumber",titleKey:"vcs_porterbuddy_phoneNumber_simple.title",descriptionKey:"vcs_porterbuddy_phoneNumber_simple.description"})),React.createElement(l,{name:"vcs_helthjem",titleKey:"vcs_helthjem_simple.title",labelKey:"vcs_helthjem_simple.label",descriptionKey:"vcs_helthjem_simple.description"}),p&&React.createElement(React.Fragment,null,React.createElement(d,{type:"number",name:"vcs_helthjem_shopId",titleKey:"vcs_helthjem_shopId_simple.title",descriptionKey:"vcs_helthjem_shopId_simple.description"}),React.createElement(d,{name:"vcs_helthjem_username",titleKey:"vcs_helthjem_username_simple.title",descriptionKey:"vcs_helthjem_username_simple.description"}),React.createElement(d,{asterisk:!0,name:"vcs_helthjem_password",titleKey:"vcs_helthjem_password_simple.title",descriptionKey:"vcs_helthjem_password_simple.description"})),s&&React.createElement(React.Fragment,null,React.createElement("h3",{className:"vipps-mobilepay-react-trab-description"},t("checkout_external_payment_title_simple.title")),React.createElement("p",null,t("checkout_external_payment_title_simple.description")),o&&React.createElement(l,{name:"checkout_external_payments_klarna",titleKey:"checkout_external_payments_klarna_simple.title",labelKey:"checkout_external_payments_klarna_simple.label",descriptionKey:"checkout_external_payments_klarna_simple.description"})),React.createElement("div",{className:"vipps-mobilepay-react-button-actions"},React.createElement(R,{variant:"secondary",type:"button",onClick:()=>r("ESSENTIAL")},t("previous_step")),React.createElement(R,{variant:"primary",isLoading:e},t("save_changes"))))))}const le=!0;function re(){const[e,a]=y.useState(!1),[n,i]=y.useState(),[c,r]=y.useState(!1),{submitChanges:u,getOption:p,setOptions:o}=E(),s=[t("main_options.title"),t("express_options.title"),t("checkout_options.title"),t("advanced_options.title")],m=p("developermode")==="yes";m&&s.push(t("developertitle.title"));const[_,h]=ie(s[0]);function b(w){return w===_}async function N(w){w.preventDefault(),a(!0),r(!1);try{const v=await u({forceEnable:W});console.log("handleSaveSettings - Response data:",v),!v.connection_ok||!v.form_ok?(i({text:v.connection_msg||v.form_errors,variant:"error"}),r(!1)):(i({text:v.connection_msg,variant:"success"}),r(!0),console.log("handleSaveSettings - Setting new options:",v.options),await o(v.options),oe(O()),setTimeout(()=>{r(!1)},2e3))}catch(v){console.error("handleSaveSettings - Error:",v),i({text:v.message,variant:"error"}),r(!1)}finally{a(!1)}}function O(){const w=p("merchantSerialNumber")&&p("clientId")&&p("secret")&&p("Ocp_Apim_Key_eCommerce")&&p("country")&&p("payment_method_name"),v=p("merchantSerialNumber_test")&&p("clientId_test")&&p("secret_test")&&p("Ocp_Apim_Key_eCommerce_test")&&p("payment_method_name")&&p("country");return!w&&!v}const[W,oe]=y.useState(()=>le);return React.createElement(React.Fragment,null,n&&React.createElement(U,{variant:n.variant,text:n.text}),React.createElement(Z,{onSubmit:N,className:"vippsAdminSettings"},W?React.createElement(ce,{isLoading:e}):React.createElement(React.Fragment,null,React.createElement(L,{tabs:s,onTabChange:h,activeTab:_}),b(s[0])&&React.createElement(Y,null),b(s[1])&&React.createElement($,null),b(s[2])&&React.createElement(ee,null),b(s[3])&&React.createElement(ae,null),m&&b(s[4])&&React.createElement(ne,null),React.createElement("div",{className:"vipps-mobilepay-react-save-section"},React.createElement(R,{variant:"primary",isLoading:e},t("save_changes")),c&&React.createElement("span",{className:"vipps-mobilepay-react-save-confirmation"},React.createElement("span",{className:"dashicons dashicons-yes"}),t("settings_saved"))))))}function se(){const e=j("page")==="admin_settings_page";return React.createElement("div",{className:"vipps-mobilepay-react-admin-page"},e&&React.createElement(q,null,React.createElement(re,null)))}S.createRoot(document.getElementById("vipps-mobilepay-react-ui")).render(y.createElement(y.StrictMode,null,y.createElement(se,null)))})(wp.element,wp.element);
