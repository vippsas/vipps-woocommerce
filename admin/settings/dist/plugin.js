(function(m,P){"use strict";var N=document.createElement("style");N.textContent=`.vipps-mobilepay-react-admin-page{color:#161225;font-family:Vipps Text,Roboto,Helvetica,Arial,sans-serif}.vipps-mobilepay-react-loading{display:inline-block;position:relative;width:20px;height:20px}.vipps-mobilepay-react-loading div{box-sizing:border-box;display:block;position:absolute;width:16px;height:16px;margin:2px;border:2px solid #666;border-radius:50%;animation:vipps-mobilepay-react-loading 1.2s cubic-bezier(.5,0,.5,1) infinite;border-color:#666 transparent transparent transparent}.vipps-mobilepay-react-loading div:nth-child(1){animation-delay:-.45s}.vipps-mobilepay-react-loading div:nth-child(2){animation-delay:-.3s}.vipps-mobilepay-react-loading div:nth-child(3){animation-delay:-.15s}@keyframes vipps-mobilepay-react-loading{0%{transform:rotate(0)}to{transform:rotate(360deg)}}.vipps-mobilepay-react-form-field{margin-bottom:2rem;display:flex;gap:2rem}.vipps-mobilepay-react-form-field>label{width:15%}.vipps-mobilepay-react-form-field>div{width:85%}.vipps-mobilepay-react-col{display:flex;flex-direction:column;gap:.5rem}.vipps-mobilepay-react-label{font-weight:600;color:#1d2327;font-size:14px}.vipps-mobilepay-react-tab-description{margin-bottom:1rem}.vipps-mobilepay-react-field-description{color:#666}.vipps-mobilepay-react-button-actions{display:flex;column-gap:1rem}.vipps-mobilepay-react-notification-banner{padding:1rem;min-height:40px;font-size:1rem;display:flex;align-items:center}.vipps-mobilepay-react-notification-banner-error{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb}.vipps-mobilepay-react-notification-banner-warning{background-color:#fff3cd;color:#856404;border:1px solid #ffeeba}.vipps-mobilepay-react-notification-banner-success{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb}
`,document.head.appendChild(N);var S={},O=P;S.createRoot=O.createRoot,S.hydrateRoot=O.hydrateRoot;const f=window;if(!f.VippsMobilePayReactTranslations)throw new Error("VippsMobilePayReactTranslations not found, make sure to include this using wp_localize_script()");if(!f.VippsMobilePayReactOptions)throw new Error("VippsMobilePayReactOptions not found, make sure to include this using wp_localize_script()");if(!f.VippsMobilePayReactMetadata)throw new Error("VippsMobilePayReactMetadata not found, make sure to include this using wp_localize_script()");function a(e){let t=f.VippsMobilePayReactTranslations;const n=e.split(".");for(const i of n)if(typeof t=="object"&&t!==null)t=t[i];else break;return t!==void 0?t:e}function C(e){var t;return((t=f.VippsMobilePayReactMetadata)==null?void 0:t[e])??null}const{VippsMobilePayReactTranslations:ce,VippsMobilePayReactOptions:A,VippsMobilePayReactMetadata:re}=f;function T({tabs:e,onTabChange:t,activeTab:n}){return React.createElement("div",{className:"vippstabholder",id:"vippstabholder"},e.map((i,c)=>React.createElement("h3",{key:c,id:`woocommerce_vipps_${i}_options`,"aria-selected":i===n?"true":"false",className:`wc-settings-sub-title tab ${i===n?"active":""}`,title:i,onClick:()=>t(i),style:{cursor:"pointer"}},i)))}const I=m.createContext(null);function j({children:e}){const[t,n]=m.useState(A);function i(r){const u=t[r],d=a(r+".default");return u??d??""}async function c(r,u){n(d=>({...d,[r]:u??null}))}async function s(r){n(r)}async function p(r){r!=null&&r.forceEnable&&(c("enabled","yes"),t.enabled="yes");const u=document.getElementById("vippsadmin_nonce").value,d=new URLSearchParams({action:"vipps_update_admin_settings",vippsadmin_nonce:u});for(const[y,K]of Object.entries(t)){const E="values["+y+"]";K?d.append(E,K):d.append(E,"")}const v=await fetch(C("admin_url"),{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},credentials:"include",body:d.toString()});return v.ok&&console.log("Response is %j",v),v.json()}return React.createElement(I.Provider,{value:{setOption:c,getOption:i,setOptions:s,submitChanges:p}},e)}function _(){const e=m.useContext(I);if(!e)throw new Error("useWP must be used within a WPOptionsProvider");return e}function h({htmlString:e,className:t=""}){return React.createElement("span",{className:[t].join(" "),dangerouslySetInnerHTML:{__html:e}})}function M(){return React.createElement("div",{className:"vipps-mobilepay-react-loading"},React.createElement("div",null),React.createElement("div",null),React.createElement("div",null),React.createElement("div",null))}function W(e){return React.createElement("input",{...e,className:["input-text regular-input",e.className??""].join(" "),style:{height:30}},e.children)}function L(e){return React.createElement("form",{...e,className:["",e.className??""].join(" ")},e.children)}function k(e){return React.createElement("label",{...e,className:["vipps-mobilepay-react-label",e.className??""].join(" ")},e.children)}function R({variant:e,isLoading:t,disabled:n,...i}){return React.createElement("button",{...i,disabled:t||n,className:[`button-${e}`,i.className??""].join(" ")},React.createElement("div",{style:{display:"flex",alignItems:"center"}},t&&React.createElement(M,null),React.createElement("span",null,i.children)))}function U({id:e,name:t,onChange:n,checked:i,children:c,className:s}){return React.createElement("input",{id:e,name:t,checked:i?B(i):void 0,onChange:p=>n(q(p.target.checked)),type:"checkbox",className:[s??""].join(" ")},c)}function V(e){return React.createElement("select",{...e,className:[e.className??""].join(" ")},e.children)}function z(e){return React.createElement("option",{...e,className:[e.className??""].join(" ")},e.children)}function H(e){return React.createElement("textarea",{...e,className:[e.className??""].join(" ")},e.children)}function x(e){return React.createElement("div",{...e,className:["vipps-mobilepay-react-form-field",e.className??""].join(" ")},e.children)}function B(e){return e==="yes"}function q(e){return e?"yes":"no"}function l({name:e,titleKey:t,labelKey:n,descriptionKey:i}){const{getOption:c,setOption:s}=_();return React.createElement(x,null,React.createElement(k,{htmlFor:e},a(t)),React.createElement("div",{className:"vipps-mobilepay-react-col"},React.createElement("div",{className:"vipps-mobilepay-react-row-center"},React.createElement(U,{id:e,name:e,checked:c(e),onChange:p=>s(e,p)}),n&&React.createElement("label",{htmlFor:e},React.createElement(h,{htmlString:a(n)}))),i&&React.createElement(h,{className:"vipps-mobilepay-react-field-description",htmlString:a(i)})))}function g({name:e,titleKey:t,labelKey:n,descriptionKey:i,options:c}){const{getOption:s,setOption:p}=_();return React.createElement(x,null,React.createElement(k,{htmlFor:e},a(t)),React.createElement("div",{className:"vipps-mobilepay-react-col"},React.createElement(V,{id:e,name:e,onChange:r=>p(e,r.target.value),value:s(e),required:!0},c.map(r=>React.createElement(z,{key:r,value:r},r))),React.createElement("div",null,n&&React.createElement(h,{htmlString:a(n)})),i&&React.createElement(h,{className:"vipps-mobilepay-react-field-description",htmlString:a(i)})))}function o({name:e,titleKey:t,labelKey:n,descriptionKey:i,pattern:c,required:s,asterisk:p,type:r="text"}){const{getOption:u,setOption:d}=_(),[v,y]=m.useState(!1),K=!v&&p;return React.createElement(x,null,React.createElement(k,{htmlFor:e},a(t)),React.createElement("div",{className:"vipps-mobilepay-react-col"},React.createElement(W,{id:e,name:e,onChange:E=>d(e,E.target.value),value:u(e),pattern:c,required:s,onFocus:()=>y(!0),onBlur:()=>y(!1),type:K?"password":r}),React.createElement("div",null,n&&React.createElement(h,{htmlString:a(n)})),i&&React.createElement(h,{className:"vipps-mobilepay-react-field-description",htmlString:a(i)})))}function D({name:e,titleKey:t,labelKey:n,descriptionKey:i,rows:c=5}){const{getOption:s,setOption:p}=_();return React.createElement(x,null,React.createElement(k,{htmlFor:e},a(t)),React.createElement("div",{className:"vipps-mobilepay-react-col"},React.createElement(H,{id:e,name:e,onChange:r=>p(e,r.target.value),value:s(e),rows:c}),React.createElement("div",null,n&&React.createElement(h,{htmlString:a(n)})),i&&React.createElement(h,{className:"vipps-mobilepay-react-field-description",htmlString:a(i)})))}function J(){return React.createElement("div",null,React.createElement("p",{className:"vipps-mobilepay-react-tab-description"}),React.createElement(l,{name:"enabled",titleKey:"enabled.title",labelKey:"enabled.label"}),React.createElement(g,{name:"payment_method_name",titleKey:"payment_method_name.title",descriptionKey:"payment_method_name.description",options:[a("payment_method_name.options.Vipps"),a("payment_method_name.options.MobilePay")]}),React.createElement(o,{name:"orderprefix",titleKey:"orderprefix.title",descriptionKey:"orderprefix.description",pattern:"[a-zA-Z0-9-]+",required:!0}),React.createElement(o,{asterisk:!0,name:"merchantSerialNumber",titleKey:"merchantSerialNumber.title",descriptionKey:"merchantSerialNumber.description"}),React.createElement(o,{asterisk:!0,name:"clientId",titleKey:"clientId.title",descriptionKey:"clientId.description"}),React.createElement(o,{asterisk:!0,name:"secret",titleKey:"secret.title",descriptionKey:"secret.description"}),React.createElement(o,{asterisk:!0,name:"Ocp_Apim_Key_eCommerce",titleKey:"Ocp_Apim_Key_eCommerce.title",descriptionKey:"Ocp_Apim_Key_eCommerce.description"}),React.createElement(g,{name:"result_status",titleKey:"result_status.title",descriptionKey:"result_status.description",options:[a("result_status.options.on-hold"),a("result_status.options.processing")]}),React.createElement(D,{name:"description",titleKey:"description.title",descriptionKey:"description.description",rows:5}),React.createElement(l,{name:"vippsdefault",titleKey:"vippsdefault.title",labelKey:"vippsdefault.label"}))}function Z(){return React.createElement("div",null,React.createElement("p",{className:"vipps-mobilepay-react-tab-description"},a("express_options.description")),React.createElement(l,{name:"cartexpress",titleKey:"cartexpress.title",labelKey:"cartexpress.title",descriptionKey:"cartexpress.description"}),React.createElement(g,{name:"singleproductexpress",titleKey:"singleproductexpress.title",descriptionKey:"singleproductexpress.description",options:[a("singleproductexpress.options.none"),a("singleproductexpress.options.some"),a("singleproductexpress.options.all")]}),React.createElement(l,{name:"singleproductexpressarchives",titleKey:"singleproductexpressarchives.title",labelKey:"singleproductexpressarchives.label",descriptionKey:"singleproductexpressarchives.description"}),React.createElement(l,{name:"expresscheckout_termscheckbox",titleKey:"expresscheckout_termscheckbox.title",labelKey:"expresscheckout_termscheckbox.label",descriptionKey:"expresscheckout_termscheckbox.description"}),React.createElement(l,{name:"expresscheckout_always_address",titleKey:"expresscheckout_always_address.title",labelKey:"expresscheckout_always_address.label",descriptionKey:"expresscheckout_always_address.description"}),React.createElement(l,{name:"enablestaticshipping",titleKey:"enablestaticshipping.title",labelKey:"enablestaticshipping.label",descriptionKey:"enablestaticshipping.description"}),React.createElement(l,{name:"expresscreateuser",titleKey:"expresscreateuser.title",labelKey:"expresscreateuser.label",descriptionKey:"expresscreateuser.description"}),React.createElement(l,{name:"singleproductbuynowcompatmode",titleKey:"singleproductbuynowcompatmode.title",labelKey:"singleproductbuynowcompatmode.label",descriptionKey:"singleproductbuynowcompatmode.description"}),React.createElement(l,{name:"deletefailedexpressorders",titleKey:"deletefailedexpressorders.title",labelKey:"deletefailedexpressorders.label",descriptionKey:"deletefailedexpressorders.description"}))}function G(){const{getOption:e}=_(),t=e("vcs_porterbuddy")==="yes",n=e("vcs_instabox")==="yes",i=e("vcs_helthjem")==="yes";return React.createElement("div",null,React.createElement("p",{className:"vipps-mobilepay-react-tab-description"},React.createElement(h,{htmlString:a("checkout_options.description")})),React.createElement(l,{name:"vipps_checkout_enabled",titleKey:"vipps_checkout_enabled.title",labelKey:"vipps_checkout_enabled.label",descriptionKey:"vipps_checkout_enabled.description"}),React.createElement(l,{name:"checkoutcreateuser",titleKey:"checkoutcreateuser.title",labelKey:"checkoutcreateuser.label",descriptionKey:"checkoutcreateuser.description"}),React.createElement(l,{name:"enablestaticshipping_checkout",titleKey:"enablestaticshipping_checkout.title",labelKey:"enablestaticshipping_checkout.label",descriptionKey:"enablestaticshipping_checkout.description"}),React.createElement(l,{name:"requireUserInfo_checkout",titleKey:"requireUserInfo_checkout.title",labelKey:"requireUserInfo_checkout.label",descriptionKey:"requireUserInfo_checkout.description"}),React.createElement(l,{name:"noAddressFields",titleKey:"noAddressFields.title",labelKey:"noAddressFields.label",descriptionKey:"noAddressFields.description"}),React.createElement(l,{name:"noContactFields",titleKey:"noContactFields.title",labelKey:"noContactFields.label",descriptionKey:"noContactFields.description"}),React.createElement("h3",{className:"vipps-mobilepay-react-tab-description"},a("checkout_shipping.title")),React.createElement("p",null,a("checkout_shipping.description")),React.createElement(l,{name:"vcs_posten",titleKey:"vcs_posten.title",descriptionKey:"vcs_posten.description",labelKey:"vcs_posten.label"}),React.createElement(l,{name:"vcs_postnord",titleKey:"vcs_postnord.title",descriptionKey:"vcs_postnord.description",labelKey:"vcs_postnord.label"}),React.createElement(l,{name:"vcs_porterbuddy",titleKey:"vcs_porterbuddy.title",descriptionKey:"vcs_porterbuddy.description",labelKey:"vcs_porterbuddy.label"}),t&&React.createElement(React.Fragment,null,React.createElement(o,{asterisk:!0,name:"vcs_porterbuddy_publicToken",titleKey:"vcs_porterbuddy_publicToken.title",descriptionKey:"vcs_porterbuddy_publicToken.description"}),React.createElement(o,{asterisk:!0,name:"vcs_porterbuddy_apiKey",titleKey:"vcs_porterbuddy_apiKey.title",descriptionKey:"vcs_porterbuddy_apiKey.description"}),React.createElement(o,{name:"vcs_porterbuddy_phoneNumber",titleKey:"vcs_porterbuddy_phoneNumber.title",descriptionKey:"vcs_porterbuddy_phoneNumber.description"})),React.createElement(l,{name:"vcs_instabox",titleKey:"vcs_instabox.title",descriptionKey:"vcs_instabox.description",labelKey:"vcs_instabox.label"}),n&&React.createElement(React.Fragment,null,React.createElement(o,{asterisk:!0,name:"vcs_instabox_clientId",titleKey:"vcs_instabox_clientId.title",descriptionKey:"vcs_instabox_clientId.description"}),React.createElement(o,{asterisk:!0,name:"vcs_instabox_clientSecret",titleKey:"vcs_instabox_clientSecret.title",descriptionKey:"vcs_instabox_clientSecret.description"})),React.createElement(l,{name:"vcs_helthjem",titleKey:"vcs_helthjem.title",descriptionKey:"vcs_helthjem.description",labelKey:"vcs_helthjem.label"}),i&&React.createElement(React.Fragment,null,React.createElement(o,{type:"number",name:"vcs_helthjem_shopId",titleKey:"vcs_helthjem_shopId.title",descriptionKey:"vcs_helthjem_shopId.description"}),React.createElement(o,{name:"vcs_helthjem_username",titleKey:"vcs_helthjem_username.title",descriptionKey:"vcs_helthjem_username.description"}),React.createElement(o,{asterisk:!0,name:"vcs_helthjem_password",titleKey:"vcs_helthjem_password.title",descriptionKey:"vcs_helthjem_password.description"})))}function Q({onUpload:e}){return{handleImageUpload:()=>{const n=wp.media({library:{type:"image"},button:{},multiple:!1});n.on("select",()=>{const i=n.state().get("selection").first().toJSON();let c="";i.url?c=i.url:i.sizes&&i.sizes.thumbnail&&(c=i.sizes.thumbnail.url),c&&e(i.id,c)}),n.open()}}}function X(){const{getOption:e,setOption:t}=_();function n(){t("receiptimage",""),t("receiptimage_url","")}const{handleImageUpload:i}=Q({onUpload(p,r){t("receiptimage",p),t("receiptimage_url",r)}}),c=e("receiptimage"),s=e("receiptimage_url");return React.createElement("div",null,React.createElement("p",{className:"vipps-mobilepay-react-tab-description"},a("advanced_options.description")),React.createElement(l,{labelKey:"vippsorderattribution.label",name:"vippsorderattribution",titleKey:"vippsorderattribution.title",descriptionKey:"vippsorderattribution.description"}),React.createElement(g,{name:"vippsspecialpagetemplate",titleKey:"vippsspecialpagetemplate.title",descriptionKey:"vippsspecialpagetemplate.description",options:Object.values(a("vippsspecialpagetemplate.options"))}),React.createElement(g,{name:"vippsspecialpageid",titleKey:"vippsspecialpageid.title",descriptionKey:"vippsspecialpageid.description",options:Object.values(a("vippsspecialpageid.options"))}),React.createElement(l,{name:"sendreceipts",titleKey:"sendreceipts.title",labelKey:"sendreceipts.label",descriptionKey:"sendreceipts.description"}),React.createElement(x,null,React.createElement(k,{htmlFor:"woocommerce_vipps_receiptimage"},a("receiptimage.title")),React.createElement("div",{className:"vipps-mobilepay-react-col"},React.createElement("div",null,c?React.createElement(React.Fragment,null,React.createElement("img",{src:s,id:c,style:{width:200}}),React.createElement(R,{type:"button",onClick:n,variant:"link"},a("remove_image"))):React.createElement(R,{type:"button",onClick:i,variant:"link"},a("upload_image")),React.createElement("input",{type:"hidden",name:"woocommerce_vipps_receiptimage",id:"woocommerce_vipps_receiptimage",value:c})),React.createElement("span",{className:"vipps-mobilepay-react-field-description"},a("receiptimage.description")))),React.createElement(l,{name:"use_flock",titleKey:"use_flock.title",descriptionKey:"use_flock.description",labelKey:"use_flock.label"}),React.createElement(l,{name:"developermode",titleKey:"developermode.title",descriptionKey:"developermode.description",labelKey:"developermode.label"}))}function Y(e){const[t,n]=m.useState(()=>window.location.hash),i=m.useCallback(()=>{n(window.location.hash)},[]);m.useEffect(()=>(window.addEventListener("hashchange",i),()=>{window.removeEventListener("hashchange",i)}),[i]);const c=m.useCallback(p=>{p!==t&&(window.location.hash="#"+p)},[t]);return m.useEffect(()=>{!t&&e&&c(e)},[t,e,c]),[decodeURIComponent(t.replace("#","")),c]}function $(){return React.createElement("div",null,React.createElement("p",{className:"vipps-mobilepay-react-tab-description"},a("developertitle.description")),React.createElement(l,{name:"testmode",titleKey:"testmode.title",descriptionKey:"testmode.description",labelKey:"testmode.label"}),React.createElement(o,{name:"merchantSerialNumber_test",titleKey:"merchantSerialNumber_test.title",labelKey:"merchantSerialNumber_test.description"}),React.createElement(o,{asterisk:!0,name:"clientId_test",titleKey:"clientId_test.title",labelKey:"clientId_test.description"}),React.createElement(o,{asterisk:!0,name:"secret_test",titleKey:"secret_test.title",labelKey:"secret_test.description"}),React.createElement(o,{asterisk:!0,name:"Ocp_Apim_Key_eCommerce_test",titleKey:"Ocp_Apim_Key_eCommerce_test.title",labelKey:"Ocp_Apim_Key_eCommerce_test.description"}))}function ee({isLoading:e}){const[t,n]=m.useState("ESSENTIAL");return React.createElement(React.Fragment,null,React.createElement("h3",{className:"vipps-mobilepay-react-tab-description"},a("initial_settings")),t==="ESSENTIAL"&&React.createElement(React.Fragment,null,React.createElement(g,{name:"payment_method_name",titleKey:"payment_method_name.title",descriptionKey:"payment_method_name.description",options:[a("payment_method_name.options.Vipps"),a("payment_method_name.options.MobilePay")]}),React.createElement(o,{asterisk:!0,name:"merchantSerialNumber",titleKey:"merchantSerialNumber.title",descriptionKey:"merchantSerialNumber.description"}),React.createElement(o,{asterisk:!0,name:"clientId",titleKey:"clientId.title",descriptionKey:"clientId.description"}),React.createElement(o,{asterisk:!0,name:"secret",titleKey:"secret.title",descriptionKey:"secret.description"}),React.createElement(o,{asterisk:!0,name:"Ocp_Apim_Key_eCommerce",titleKey:"Ocp_Apim_Key_eCommerce.title",descriptionKey:"Ocp_Apim_Key_eCommerce.description"}),React.createElement(R,{variant:"primary",type:"button",onClick:()=>n("CHECKOUT")},a("next_step"))),t==="CHECKOUT"&&React.createElement(React.Fragment,null,React.createElement(l,{name:"vipps_checkout_enabled",titleKey:"vipps_checkout_enabled.title",labelKey:"vipps_checkout_enabled.label",descriptionKey:"vipps_checkout_enabled.description"}),React.createElement("div",{className:"vipps-mobilepay-react-button-actions"},React.createElement(R,{variant:"secondary",type:"button",onClick:()=>n("ESSENTIAL")},a("previous_step")),React.createElement(R,{variant:"primary",isLoading:e},a("save_changes")))))}function te({variant:e,text:t}){return React.createElement("div",{className:`vipps-mobilepay-react-notification-banner vipps-mobilepay-react-notification-banner-${e}`},Array.isArray(t)?t.map((n,i)=>React.createElement("p",{key:i},n)):React.createElement("p",null,t))}function ie(){const[e,t]=m.useState(!1),[n,i]=m.useState(),{submitChanges:c,getOption:s,setOptions:p}=_(),r=[a("main_options.title"),a("express_options.title"),a("checkout_options.title"),a("advanced_options.title")],u=s("developermode")==="yes";u&&r.push(a("developertitle.title"));const[d,v]=Y(r[0]);function y(w){return w===d}async function K(w){w.preventDefault(),t(!0);try{const b=await c({forceEnable:F});!b.connection_ok||!b.form_ok?i({text:b.connection_msg||b.form_errors,variant:"error"}):(i({text:b.connection_msg,variant:"success"}),p(b.options).then(()=>ne(E())))}catch(b){i({text:b.message,variant:"error"})}finally{t(!1)}}function E(){const w=s("merchantSerialNumber")&&s("clientId")&&s("secret")&&s("Ocp_Apim_Key_eCommerce"),b=s("merchantSerialNumber_test")&&s("clientId_test")&&s("secret_test")&&s("Ocp_Apim_Key_eCommerce_test");return!w&&!b}const[F,ne]=m.useState(()=>E());return React.createElement(React.Fragment,null,n&&React.createElement(te,{variant:n.variant,text:n.text}),React.createElement(L,{onSubmit:K,className:"vippsAdminSettings"},F?React.createElement(ee,{isLoading:e}):React.createElement(React.Fragment,null,React.createElement(T,{tabs:r,onTabChange:v,activeTab:d}),y(r[0])&&React.createElement(J,null),y(r[1])&&React.createElement(Z,null),y(r[2])&&React.createElement(G,null),y(r[3])&&React.createElement(X,null),u&&y(r[4])&&React.createElement($,null),React.createElement(R,{variant:"primary",isLoading:e},a("save_changes")))))}function ae(){const e=C("page")==="admin_settings_page";return React.createElement("div",{className:"vipps-mobilepay-react-admin-page"},e&&React.createElement(j,null,React.createElement(ie,null)))}S.createRoot(document.getElementById("vipps-mobilepay-react-ui")).render(m.createElement(m.StrictMode,null,m.createElement(ae,null)))})(wp.element,wp.element);
