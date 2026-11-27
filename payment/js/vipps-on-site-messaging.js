/* Local country-aware variant of the badge SDK.
 * Brand selects the Vipps or MobilePay wordmark and name.
 * Vipps uses blue branding when country is "DK" or "FI" (case-insensitive).
 * MobilePay retains its existing blue branding; badge variants retain their styling.
 */
(function(){var e=Object.defineProperty,t=(t,n)=>{let r={};for(var i in t)e(r,i,{get:t[i],enumerable:!0});return n||e(r,Symbol.toStringTag,{value:`Module`}),r},n=e=>(t,n)=>{n===void 0?customElements.define(e,t):n.addInitializer(()=>{customElements.define(e,t)})},r=globalThis,i=r.ShadowRoot&&(r.ShadyCSS===void 0||r.ShadyCSS.nativeShadow)&&`adoptedStyleSheets`in Document.prototype&&`replace`in CSSStyleSheet.prototype,a=Symbol(),o=new WeakMap,s=class{constructor(e,t,n){if(this._$cssResult$=!0,n!==a)throw Error("CSSResult is not constructable. Use `unsafeCSS` or `css` instead.");this.cssText=e,this.t=t}get styleSheet(){let e=this.o,t=this.t;if(i&&e===void 0){let n=t!==void 0&&t.length===1;n&&(e=o.get(t)),e===void 0&&((this.o=e=new CSSStyleSheet).replaceSync(this.cssText),n&&o.set(t,e))}return e}toString(){return this.cssText}},c=e=>new s(typeof e==`string`?e:e+``,void 0,a),l=(e,...t)=>new s(e.length===1?e[0]:t.reduce((t,n,r)=>t+(e=>{if(!0===e._$cssResult$)return e.cssText;if(typeof e==`number`)return e;throw Error(`Value passed to 'css' function must be a 'css' function result: `+e+`. Use 'unsafeCSS' to pass non-literal values, but take care to ensure page security.`)})(n)+e[r+1],e[0]),e,a),u=(e,t)=>{if(i)e.adoptedStyleSheets=t.map(e=>e instanceof CSSStyleSheet?e:e.styleSheet);else for(let n of t){let t=document.createElement(`style`),i=r.litNonce;i!==void 0&&t.setAttribute(`nonce`,i),t.textContent=n.cssText,e.appendChild(t)}},d=i?e=>e:e=>e instanceof CSSStyleSheet?(e=>{let t=``;for(let n of e.cssRules)t+=n.cssText;return c(t)})(e):e,f,ee,{is:te,defineProperty:ne,getOwnPropertyDescriptor:re,getOwnPropertyNames:ie,getOwnPropertySymbols:ae,getPrototypeOf:oe}=Object,p=globalThis,se=p.trustedTypes,ce=se?se.emptyScript:``,le=p.reactiveElementPolyfillSupport,m=(e,t)=>e,h={toAttribute(e,t){switch(t){case Boolean:e=e?ce:null;break;case Object:case Array:e=e==null?e:JSON.stringify(e)}return e},fromAttribute(e,t){let n=e;switch(t){case Boolean:n=e!==null;break;case Number:n=e===null?null:Number(e);break;case Object:case Array:try{n=JSON.parse(e)}catch{n=null}}return n}},ue=(e,t)=>!te(e,t),de={attribute:!0,type:String,converter:h,reflect:!1,useDefault:!1,hasChanged:ue};(f=Symbol).metadata!=null||(f.metadata=Symbol(`metadata`)),p.litPropertyMetadata!=null||(p.litPropertyMetadata=new WeakMap);var g=class extends HTMLElement{static addInitializer(e){var t;this._$Ei(),((t=this.l)==null?this.l=[]:t).push(e)}static get observedAttributes(){return this.finalize(),this._$Eh&&[...this._$Eh.keys()]}static createProperty(e,t=de){if(t.state&&(t.attribute=!1),this._$Ei(),this.prototype.hasOwnProperty(e)&&((t=Object.create(t)).wrapped=!0),this.elementProperties.set(e,t),!t.noAccessor){let n=Symbol(),r=this.getPropertyDescriptor(e,n,t);r!==void 0&&ne(this.prototype,e,r)}}static getPropertyDescriptor(e,t,n){var r;let{get:i,set:a}=(r=re(this.prototype,e))==null?{get(){return this[t]},set(e){this[t]=e}}:r;return{get:i,set(t){let r=i==null?void 0:i.call(this);a==null||a.call(this,t),this.requestUpdate(e,r,n)},configurable:!0,enumerable:!0}}static getPropertyOptions(e){var t;return(t=this.elementProperties.get(e))==null?de:t}static _$Ei(){if(this.hasOwnProperty(m(`elementProperties`)))return;let e=oe(this);e.finalize(),e.l!==void 0&&(this.l=[...e.l]),this.elementProperties=new Map(e.elementProperties)}static finalize(){if(this.hasOwnProperty(m(`finalized`)))return;if(this.finalized=!0,this._$Ei(),this.hasOwnProperty(m(`properties`))){let e=this.properties,t=[...ie(e),...ae(e)];for(let n of t)this.createProperty(n,e[n])}let e=this[Symbol.metadata];if(e!==null){let t=litPropertyMetadata.get(e);if(t!==void 0)for(let[e,n]of t)this.elementProperties.set(e,n)}this._$Eh=new Map;for(let[e,t]of this.elementProperties){let n=this._$Eu(e,t);n!==void 0&&this._$Eh.set(n,e)}this.elementStyles=this.finalizeStyles(this.styles)}static finalizeStyles(e){let t=[];if(Array.isArray(e)){let n=new Set(e.flat(1/0).reverse());for(let e of n)t.unshift(d(e))}else e!==void 0&&t.push(d(e));return t}static _$Eu(e,t){let n=t.attribute;return!1===n?void 0:typeof n==`string`?n:typeof e==`string`?e.toLowerCase():void 0}constructor(){super(),this._$Ep=void 0,this.isUpdatePending=!1,this.hasUpdated=!1,this._$Em=null,this._$Ev()}_$Ev(){var e;this._$ES=new Promise(e=>this.enableUpdating=e),this._$AL=new Map,this._$E_(),this.requestUpdate(),(e=this.constructor.l)==null||e.forEach(e=>e(this))}addController(e){var t,n;((t=this._$EO)==null?this._$EO=new Set:t).add(e),this.renderRoot!==void 0&&this.isConnected&&((n=e.hostConnected)==null||n.call(e))}removeController(e){var t;(t=this._$EO)==null||t.delete(e)}_$E_(){let e=new Map,t=this.constructor.elementProperties;for(let n of t.keys())this.hasOwnProperty(n)&&(e.set(n,this[n]),delete this[n]);e.size>0&&(this._$Ep=e)}createRenderRoot(){var e;let t=(e=this.shadowRoot)==null?this.attachShadow(this.constructor.shadowRootOptions):e;return u(t,this.constructor.elementStyles),t}connectedCallback(){var e;this.renderRoot!=null||(this.renderRoot=this.createRenderRoot()),this.enableUpdating(!0),(e=this._$EO)==null||e.forEach(e=>{var t;return(t=e.hostConnected)==null?void 0:t.call(e)})}enableUpdating(e){}disconnectedCallback(){var e;(e=this._$EO)==null||e.forEach(e=>{var t;return(t=e.hostDisconnected)==null?void 0:t.call(e)})}attributeChangedCallback(e,t,n){this._$AK(e,n)}_$ET(e,t){let n=this.constructor.elementProperties.get(e),r=this.constructor._$Eu(e,n);if(r!==void 0&&!0===n.reflect){var i;let a=(((i=n.converter)==null?void 0:i.toAttribute)===void 0?h:n.converter).toAttribute(t,n.type);this._$Em=e,a==null?this.removeAttribute(r):this.setAttribute(r,a),this._$Em=null}}_$AK(e,t){let n=this.constructor,r=n._$Eh.get(e);if(r!==void 0&&this._$Em!==r){var i,a,o;let e=n.getPropertyOptions(r),s=typeof e.converter==`function`?{fromAttribute:e.converter}:((i=e.converter)==null?void 0:i.fromAttribute)===void 0?h:e.converter;this._$Em=r;let c=s.fromAttribute(t,e.type);this[r]=(a=c==null?(o=this._$Ej)==null?void 0:o.get(r):c)==null?c:a,this._$Em=null}}requestUpdate(e,t,n,r=!1,i){if(e!==void 0){var a,o;let s=this.constructor;if(!1===r&&(i=this[e]),n!=null||(n=s.getPropertyOptions(e)),!(((a=n.hasChanged)==null?ue:a)(i,t)||n.useDefault&&n.reflect&&i===((o=this._$Ej)==null?void 0:o.get(e))&&!this.hasAttribute(s._$Eu(e,n))))return;this.C(e,t,n)}!1===this.isUpdatePending&&(this._$ES=this._$EP())}C(e,t,{useDefault:n,reflect:r,wrapped:i},a){var o,s,c;n&&!((o=this._$Ej)==null?this._$Ej=new Map:o).has(e)&&(this._$Ej.set(e,(s=a==null?t:a)==null?this[e]:s),!0!==i||a!==void 0)||(this._$AL.has(e)||(this.hasUpdated||n||(t=void 0),this._$AL.set(e,t)),!0===r&&this._$Em!==e&&((c=this._$Eq)==null?this._$Eq=new Set:c).add(e))}async _$EP(){this.isUpdatePending=!0;try{await this._$ES}catch(e){Promise.reject(e)}let e=this.scheduleUpdate();return e!=null&&await e,!this.isUpdatePending}scheduleUpdate(){return this.performUpdate()}performUpdate(){if(!this.isUpdatePending)return;if(!this.hasUpdated){if(this.renderRoot!=null||(this.renderRoot=this.createRenderRoot()),this._$Ep){for(let[e,t]of this._$Ep)this[e]=t;this._$Ep=void 0}let e=this.constructor.elementProperties;if(e.size>0)for(let[t,n]of e){let{wrapped:e}=n,r=this[t];!0!==e||this._$AL.has(t)||r===void 0||this.C(t,void 0,n,r)}}let e=!1,t=this._$AL;try{var n;e=this.shouldUpdate(t),e?(this.willUpdate(t),(n=this._$EO)==null||n.forEach(e=>{var t;return(t=e.hostUpdate)==null?void 0:t.call(e)}),this.update(t)):this._$EM()}catch(t){throw e=!1,this._$EM(),t}e&&this._$AE(t)}willUpdate(e){}_$AE(e){var t;(t=this._$EO)==null||t.forEach(e=>{var t;return(t=e.hostUpdated)==null?void 0:t.call(e)}),this.hasUpdated||(this.hasUpdated=!0,this.firstUpdated(e)),this.updated(e)}_$EM(){this._$AL=new Map,this.isUpdatePending=!1}get updateComplete(){return this.getUpdateComplete()}getUpdateComplete(){return this._$ES}shouldUpdate(e){return!0}update(e){this._$Eq&&(this._$Eq=this._$Eq.forEach(e=>this._$ET(e,this[e]))),this._$EM()}updated(e){}firstUpdated(e){}};g.elementStyles=[],g.shadowRootOptions={mode:`open`},g[m(`elementProperties`)]=new Map,g[m(`finalized`)]=new Map,le==null||le({ReactiveElement:g}),((ee=p.reactiveElementVersions)==null?p.reactiveElementVersions=[]:ee).push(`2.1.2`);var fe={attribute:!0,type:String,converter:h,reflect:!1,hasChanged:ue},pe=(e=fe,t,n)=>{let{kind:r,metadata:i}=n,a=globalThis.litPropertyMetadata.get(i);if(a===void 0&&globalThis.litPropertyMetadata.set(i,a=new Map),r===`setter`&&((e=Object.create(e)).wrapped=!0),a.set(n.name,e),r===`accessor`){let{name:r}=n;return{set(n){let i=t.get.call(this);t.set.call(this,n),this.requestUpdate(r,i,e,!0,n)},init(t){return t!==void 0&&this.C(r,void 0,e,t),t}}}if(r===`setter`){let{name:r}=n;return function(n){let i=this[r];t.call(this,n),this.requestUpdate(r,i,e,!0,n)}}throw Error(`Unsupported decorator location: `+r)};function _(e){return(t,n)=>typeof n==`object`?pe(e,t,n):((e,t,n)=>{let r=t.hasOwnProperty(n);return t.constructor.createProperty(n,e),r?Object.getOwnPropertyDescriptor(t,n):void 0})(e,t,n)}var me=(e,t,n)=>(n.configurable=!0,n.enumerable=!0,Reflect.decorate&&typeof t!=`object`&&Object.defineProperty(e,t,n),n);function he(e,t){return(n,r,i)=>{let a=t=>{var n,r;return(n=(r=t.renderRoot)==null?void 0:r.querySelector(e))==null?null:n};if(t){let{get:e,set:t}=typeof r==`object`?n:i==null?(()=>{let e=Symbol();return{get(){return this[e]},set(t){this[e]=t}}})():i;return me(n,r,{get(){let n=e.call(this);return n===void 0&&(n=a(this),(n!==null||this.hasUpdated)&&t.call(this,n)),n}})}return me(n,r,{get(){return a(this)}})}}var v=function(e){return e.Payment=`payment`,e.SplitPayment=`splitPayment`,e.ZeroFees=`zeroFees`,e.PayLater=`payLater`,e}({}),ge=[`da`,`en`,`fi`,`sv`],_e=[`da`,`en`,`fi`,`no`,`sv`],ve,y=globalThis,ye=e=>e,b=y.trustedTypes,be=b?b.createPolicy(`lit-html`,{createHTML:e=>e}):void 0,xe=`$lit$`,x=`lit$${Math.random().toFixed(9).slice(2)}$`,Se=`?`+x,Ce=`<${Se}>`,S=document,C=()=>S.createComment(``),w=e=>e===null||typeof e!=`object`&&typeof e!=`function`,we=Array.isArray,Te=e=>we(e)||typeof(e==null?void 0:e[Symbol.iterator])==`function`,Ee=`[ 	
\f\r]`,T=/<(?:(!--|\/[^a-zA-Z])|(\/?[a-zA-Z][^>\s]*)|(\/?$))/g,De=/-->/g,Oe=/>/g,E=RegExp(`>|${Ee}(?:([^\\s"'>=/]+)(${Ee}*=${Ee}*(?:[^ \t\n\f\r"'\`<>=]|("|')|))|$)`,`g`),ke=/'/g,Ae=/"/g,je=/^(?:script|style|textarea|title)$/i,D=(e=>(t,...n)=>({_$litType$:e,strings:t,values:n}))(1),O=Symbol.for(`lit-noChange`),k=Symbol.for(`lit-nothing`),Me=new WeakMap,A=S.createTreeWalker(S,129);function Ne(e,t){if(!we(e)||!e.hasOwnProperty(`raw`))throw Error(`invalid template strings array`);return be===void 0?t:be.createHTML(t)}var Pe=(e,t)=>{let n=e.length-1,r=[],i,a=t===2?`<svg>`:t===3?`<math>`:``,o=T;for(let t=0;t<n;t++){var s;let n=e[t],c,l,u=-1,d=0;for(;d<n.length&&(o.lastIndex=d,l=o.exec(n),l!==null);)d=o.lastIndex,o===T?l[1]===`!--`?o=De:l[1]===void 0?l[2]===void 0?l[3]!==void 0&&(o=E):(je.test(l[2])&&(i=RegExp(`</`+l[2],`g`)),o=E):o=Oe:o===E?l[0]===`>`?(o=(s=i)==null?T:s,u=-1):l[1]===void 0?u=-2:(u=o.lastIndex-l[2].length,c=l[1],o=l[3]===void 0?E:l[3]===`"`?Ae:ke):o===Ae||o===ke?o=E:o===De||o===Oe?o=T:(o=E,i=void 0);let f=o===E&&e[t+1].startsWith(`/>`)?` `:``;a+=o===T?n+Ce:u>=0?(r.push(c),n.slice(0,u)+xe+n.slice(u)+x+f):n+x+(u===-2?t:f)}return[Ne(e,a+(e[n]||`<?>`)+(t===2?`</svg>`:t===3?`</math>`:``)),r]},Fe=class e{constructor({strings:t,_$litType$:n},r){let i;this.parts=[];let a=0,o=0,s=t.length-1,c=this.parts,[l,u]=Pe(t,n);if(this.el=e.createElement(l,r),A.currentNode=this.el.content,n===2||n===3){let e=this.el.content.firstChild;e.replaceWith(...e.childNodes)}for(;(i=A.nextNode())!==null&&c.length<s;){if(i.nodeType===1){if(i.hasAttributes())for(let e of i.getAttributeNames())if(e.endsWith(xe)){let t=u[o++],n=i.getAttribute(e).split(x),r=/([.?@])?(.*)/.exec(t);c.push({type:1,index:a,name:r[2],strings:n,ctor:r[1]===`.`?Le:r[1]===`?`?Re:r[1]===`@`?ze:N}),i.removeAttribute(e)}else e.startsWith(x)&&(c.push({type:6,index:a}),i.removeAttribute(e));if(je.test(i.tagName)){let e=i.textContent.split(x),t=e.length-1;if(t>0){i.textContent=b?b.emptyScript:``;for(let n=0;n<t;n++)i.append(e[n],C()),A.nextNode(),c.push({type:2,index:++a});i.append(e[t],C())}}}else if(i.nodeType===8){if(i.data===Se)c.push({type:2,index:a});else{let e=-1;for(;(e=i.data.indexOf(x,e+1))!==-1;)c.push({type:7,index:a}),e+=x.length-1}}a++}}static createElement(e,t){let n=S.createElement(`template`);return n.innerHTML=e,n}};function j(e,t,n=e,r){var i,a,o;if(t===O)return t;let s=r===void 0?n._$Cl:(i=n._$Co)==null?void 0:i[r],c=w(t)?void 0:t._$litDirective$;return(s==null?void 0:s.constructor)!==c&&(s==null||(a=s._$AO)==null||a.call(s,!1),c===void 0?s=void 0:(s=new c(e),s._$AT(e,n,r)),r===void 0?n._$Cl=s:((o=n._$Co)==null?n._$Co=[]:o)[r]=s),s!==void 0&&(t=j(e,s._$AS(e,t.values),s,r)),t}var Ie=class{constructor(e,t){this._$AV=[],this._$AN=void 0,this._$AD=e,this._$AM=t}get parentNode(){return this._$AM.parentNode}get _$AU(){return this._$AM._$AU}u(e){var t;let{el:{content:n},parts:r}=this._$AD,i=((t=e==null?void 0:e.creationScope)==null?S:t).importNode(n,!0);A.currentNode=i;let a=A.nextNode(),o=0,s=0,c=r[0];for(;c!==void 0;){if(o===c.index){let t;c.type===2?t=new M(a,a.nextSibling,this,e):c.type===1?t=new c.ctor(a,c.name,c.strings,this,e):c.type===6&&(t=new Be(a,this,e)),this._$AV.push(t),c=r[++s]}o!==(c==null?void 0:c.index)&&(a=A.nextNode(),o++)}return A.currentNode=S,i}p(e){let t=0;for(let n of this._$AV)n!==void 0&&(n.strings===void 0?n._$AI(e[t]):(n._$AI(e,n,t),t+=n.strings.length-2)),t++}},M=class e{get _$AU(){var e,t;return(e=(t=this._$AM)==null?void 0:t._$AU)==null?this._$Cv:e}constructor(e,t,n,r){var i;this.type=2,this._$AH=k,this._$AN=void 0,this._$AA=e,this._$AB=t,this._$AM=n,this.options=r,this._$Cv=(i=r==null?void 0:r.isConnected)==null||i}get parentNode(){let e=this._$AA.parentNode,t=this._$AM;return t!==void 0&&(e==null?void 0:e.nodeType)===11&&(e=t.parentNode),e}get startNode(){return this._$AA}get endNode(){return this._$AB}_$AI(e,t=this){e=j(this,e,t),w(e)?e===k||e==null||e===``?(this._$AH!==k&&this._$AR(),this._$AH=k):e!==this._$AH&&e!==O&&this._(e):e._$litType$===void 0?e.nodeType===void 0?Te(e)?this.k(e):this._(e):this.T(e):this.$(e)}O(e){return this._$AA.parentNode.insertBefore(e,this._$AB)}T(e){this._$AH!==e&&(this._$AR(),this._$AH=this.O(e))}_(e){this._$AH!==k&&w(this._$AH)?this._$AA.nextSibling.data=e:this.T(S.createTextNode(e)),this._$AH=e}$(e){var t;let{values:n,_$litType$:r}=e,i=typeof r==`number`?this._$AC(e):(r.el===void 0&&(r.el=Fe.createElement(Ne(r.h,r.h[0]),this.options)),r);if(((t=this._$AH)==null?void 0:t._$AD)===i)this._$AH.p(n);else{let e=new Ie(i,this),t=e.u(this.options);e.p(n),this.T(t),this._$AH=e}}_$AC(e){let t=Me.get(e.strings);return t===void 0&&Me.set(e.strings,t=new Fe(e)),t}k(t){we(this._$AH)||(this._$AH=[],this._$AR());let n=this._$AH,r,i=0;for(let a of t)i===n.length?n.push(r=new e(this.O(C()),this.O(C()),this,this.options)):r=n[i],r._$AI(a),i++;i<n.length&&(this._$AR(r&&r._$AB.nextSibling,i),n.length=i)}_$AR(e=this._$AA.nextSibling,t){var n;for((n=this._$AP)==null||n.call(this,!1,!0,t);e!==this._$AB;){let t=ye(e).nextSibling;ye(e).remove(),e=t}}setConnected(e){var t;this._$AM===void 0&&(this._$Cv=e,(t=this._$AP)==null||t.call(this,e))}},N=class{get tagName(){return this.element.tagName}get _$AU(){return this._$AM._$AU}constructor(e,t,n,r,i){this.type=1,this._$AH=k,this._$AN=void 0,this.element=e,this.name=t,this._$AM=r,this.options=i,n.length>2||n[0]!==``||n[1]!==``?(this._$AH=Array(n.length-1).fill(new String),this.strings=n):this._$AH=k}_$AI(e,t=this,n,r){let i=this.strings,a=!1;if(i===void 0)e=j(this,e,t,0),a=!w(e)||e!==this._$AH&&e!==O,a&&(this._$AH=e);else{var o;let r=e,s,c;for(e=i[0],s=0;s<i.length-1;s++)c=j(this,r[n+s],t,s),c===O&&(c=this._$AH[s]),a||(a=!w(c)||c!==this._$AH[s]),c===k?e=k:e!==k&&(e+=((o=c)==null?``:o)+i[s+1]),this._$AH[s]=c}a&&!r&&this.j(e)}j(e){e===k?this.element.removeAttribute(this.name):this.element.setAttribute(this.name,e==null?``:e)}},Le=class extends N{constructor(){super(...arguments),this.type=3}j(e){this.element[this.name]=e===k?void 0:e}},Re=class extends N{constructor(){super(...arguments),this.type=4}j(e){this.element.toggleAttribute(this.name,!!e&&e!==k)}},ze=class extends N{constructor(e,t,n,r,i){super(e,t,n,r,i),this.type=5}_$AI(e,t=this){var n;if((e=(n=j(this,e,t,0))==null?k:n)===O)return;let r=this._$AH,i=e===k&&r!==k||e.capture!==r.capture||e.once!==r.once||e.passive!==r.passive,a=e!==k&&(r===k||i);i&&this.element.removeEventListener(this.name,this,r),a&&this.element.addEventListener(this.name,this,e),this._$AH=e}handleEvent(e){var t,n;typeof this._$AH==`function`?this._$AH.call((t=(n=this.options)==null?void 0:n.host)==null?this.element:t,e):this._$AH.handleEvent(e)}},Be=class{constructor(e,t,n){this.element=e,this.type=6,this._$AN=void 0,this._$AM=t,this.options=n}get _$AU(){return this._$AM._$AU}_$AI(e){j(this,e)}},Ve={M:xe,P:x,A:Se,C:1,L:Pe,R:Ie,D:Te,V:j,I:M,H:N,N:Re,U:ze,B:Le,F:Be},He=y.litHtmlPolyfillSupport;He==null||He(Fe,M),((ve=y.litHtmlVersions)==null?y.litHtmlVersions=[]:ve).push(`3.3.2`);var Ue=(e,t,n)=>{var r;let i=(r=n==null?void 0:n.renderBefore)==null?t:r,a=i._$litPart$;if(a===void 0){var o;let e=(o=n==null?void 0:n.renderBefore)==null?null:o;i._$litPart$=a=new M(t.insertBefore(C(),e),e,void 0,n==null?{}:n)}return a._$AI(e),a},We,Ge,P=globalThis,F=class extends g{constructor(){super(...arguments),this.renderOptions={host:this},this._$Do=void 0}createRenderRoot(){var e;let t=super.createRenderRoot();return(e=this.renderOptions).renderBefore!=null||(e.renderBefore=t.firstChild),t}update(e){let t=this.render();this.hasUpdated||(this.renderOptions.isConnected=this.isConnected),super.update(e),this._$Do=Ue(t,this.renderRoot,this.renderOptions)}connectedCallback(){var e;super.connectedCallback(),(e=this._$Do)==null||e.setConnected(!0)}disconnectedCallback(){var e;super.disconnectedCallback(),(e=this._$Do)==null||e.setConnected(!1)}render(){return O}};F._$litElement$=!0,F.finalized=!0,(We=P.litElementHydrateSupport)==null||We.call(P,{LitElement:F});var Ke=P.litElementPolyfillSupport;Ke==null||Ke({LitElement:F}),((Ge=P.litElementVersions)==null?P.litElementVersions=[]:Ge).push(`4.2.2`);var I=(e,t,n)=>{for(let n of t)if(n[0]===e)return(0,n[1])();return n==null?void 0:n()},{I:qe}=Ve,Je=e=>e.strings===void 0,Ye={ATTRIBUTE:1,CHILD:2,PROPERTY:3,BOOLEAN_ATTRIBUTE:4,EVENT:5,ELEMENT:6},Xe=e=>(...t)=>({_$litDirective$:e,values:t}),Ze=class{constructor(e){}get _$AU(){return this._$AM._$AU}_$AT(e,t,n){this._$Ct=e,this._$AM=t,this._$Ci=n}_$AS(e,t){return this.update(e,t)}update(e,t){return this.render(...t)}},L=(e,t)=>{var n;let r=e._$AN;if(r===void 0)return!1;for(let e of r)(n=e._$AO)==null||n.call(e,t,!1),L(e,t);return!0},R=e=>{let t,n;do{if((t=e._$AM)===void 0)break;n=t._$AN,n.delete(e),e=t}while((n==null?void 0:n.size)===0)},Qe=e=>{for(let t;t=e._$AM;e=t){let n=t._$AN;if(n===void 0)t._$AN=n=new Set;else if(n.has(e))break;n.add(e),tt(t)}};function $e(e){this._$AN===void 0?this._$AM=e:(R(this),this._$AM=e,Qe(this))}function et(e,t=!1,n=0){let r=this._$AH,i=this._$AN;if(i!==void 0&&i.size!==0){if(t){if(Array.isArray(r))for(let e=n;e<r.length;e++)L(r[e],!1),R(r[e]);else r!=null&&(L(r,!1),R(r))}else L(this,e)}}var tt=e=>{e.type==Ye.CHILD&&(e._$AP!=null||(e._$AP=et),e._$AQ!=null||(e._$AQ=$e))},nt=class extends Ze{constructor(){super(...arguments),this._$AN=void 0}_$AT(e,t,n){super._$AT(e,t,n),Qe(this),this.isConnected=e._$AU}_$AO(e,t=!0){var n,r;e!==this.isConnected&&(this.isConnected=e,e?(n=this.reconnected)==null||n.call(this):(r=this.disconnected)==null||r.call(this)),t&&(L(this,e),R(this))}setValue(e){if(Je(this._$Ct))this._$Ct._$AI(e,this);else{let t=[...this._$Ct._$AH];t[this._$Ci]=e,this._$Ct._$AI(t,this,0)}}disconnected(){}reconnected(){}},rt=new WeakMap,it=Xe(class extends nt{render(e){return k}update(e,[t]){var n;let r=t!==this.G;return r&&this.G!==void 0&&this.rt(void 0),(r||this.lt!==this.ct)&&(this.G=t,this.ht=(n=e.options)==null?void 0:n.host,this.rt(this.ct=e.element)),k}rt(e){if(this.isConnected||(e=void 0),typeof this.G==`function`){var t;let n=(t=this.ht)==null?globalThis:t,r=rt.get(n);r===void 0&&(r=new WeakMap,rt.set(n,r)),r.get(this.G)!==void 0&&this.G.call(this.ht,void 0),r.set(this.G,e),e!==void 0&&this.G.call(this.ht,e)}else this.G.value=e}get lt(){var e,t,n;return typeof this.G==`function`?(e=rt.get((t=this.ht)==null?globalThis:t))==null?void 0:e.get(this.G):(n=this.G)==null?void 0:n.value}disconnected(){this.lt===this.ct&&this.rt(void 0)}reconnected(){this.rt(this.ct)}}),z=`lit-localize-status`,B=(e,...t)=>({strTag:!0,strings:e,values:t}),at=e=>typeof e!=`string`&&`strTag`in e,ot=(e,t,n)=>{let r=e[0];for(let i=1;i<e.length;i++)r+=t[n?n[i-1]:i-1],r+=e[i];return r},st=(e=>at(e)?ot(e.strings,e.values):e),V=st,ct=!1;function lt(e){if(ct)throw Error(`lit-localize can only be configured once`);V=e,ct=!0}var ut=class{constructor(e){this.__litLocalizeEventHandler=e=>{e.detail.status===`ready`&&this.host.requestUpdate()},this.host=e}hostConnected(){window.addEventListener(z,this.__litLocalizeEventHandler)}hostDisconnected(){window.removeEventListener(z,this.__litLocalizeEventHandler)}},dt=e=>e.addController(new ut(e)),ft=()=>(e,t)=>(e.addInitializer(dt),e),pt=class{constructor(){this.settled=!1,this.promise=new Promise((e,t)=>{this._resolve=e,this._reject=t})}resolve(e){this.settled=!0,this._resolve(e)}reject(e){this.settled=!0,this._reject(e)}},H=[];for(let e=0;e<256;e++)H[e]=(e>>4&15).toString(16)+(e&15).toString(16);function mt(e){let t=0,n=8997,r=0,i=33826,a=0,o=40164,s=0,c=52210;for(let l=0;l<e.length;l++)n^=e.charCodeAt(l),t=n*435,r=i*435,a=o*435,s=c*435,a+=n<<8,s+=i<<8,r+=t>>>16,n=t&65535,a+=r>>>16,i=r&65535,c=s+(a>>>16)&65535,o=a&65535;return H[c>>8]+H[c&255]+H[o>>8]+H[o&255]+H[i>>8]+H[i&255]+H[n>>8]+H[n&255]}var ht=`h`,gt=`s`;function _t(e,t){return(t?ht:gt)+mt(typeof e==`string`?e:e.join(``))}var vt=new WeakMap,yt=new Map;function bt(e,t,n){if(e){var r;let i=e[(r=n==null?void 0:n.id)==null?xt(t):r];if(i){if(typeof i==`string`)return i;if(`strTag`in i)return ot(i.strings,t.values,i.values);{let e=vt.get(i);return e===void 0&&(e=i.values,vt.set(i,e)),{...i,values:e.map(e=>t.values[e])}}}}return st(t)}function xt(e){let t=typeof e==`string`?e:e.strings,n=yt.get(t);return n===void 0&&(n=_t(t,typeof e!=`string`&&!(`strTag`in e)),yt.set(t,n)),n}function St(e){window.dispatchEvent(new CustomEvent(z,{detail:e}))}var U=``,Ct,wt,W,Tt,Et,G=new pt;G.resolve();var K=0,Dt=e=>(lt(((e,t)=>bt(Et,e,t))),U=wt=e.sourceLocale,W=new Set(e.targetLocales),W.add(e.sourceLocale),Tt=e.loadLocale,{getLocale:Ot,setLocale:kt}),Ot=()=>U,kt=e=>{var t;if(e===((t=Ct)==null?U:t))return G.promise;if(!W||!Tt)throw Error(`Internal error`);if(!W.has(e))throw Error(`Invalid locale code`);K++;let n=K;return Ct=e,G.settled&&(G=new pt),St({status:`loading`,loadingLocale:e}),(e===wt?Promise.resolve({templates:void 0}):Tt(e)).then(t=>{K===n&&(U=e,Ct=void 0,Et=t.templates,St({status:`ready`,readyLocale:e}),G.resolve())},t=>{K===n&&(St({status:`error`,errorLocale:e,errorMessage:t.toString()}),G.reject(t))}),G.promise},At=l`
  .mobilepay-logo {
    font-family: 'Paytype', 'SF Pro Text', Arial, sans-serif;
    font-weight: 700;
    margin-top: 3px;
    color: var(--vm-osm-logo-color);
  }

  :host {
    color: #161225;
    display: inline-block;
    font-family: 'Vipps', Arial, sans-serif;
    font-style: normal;
    font-weight: 400;
    letter-spacing: normal;
    max-width: 100%;
    min-width: 15em;
    text-rendering: optimizelegibility;
    width: 30em;

    --vm-osm-background-color: #ffffff;
    --vm-osm-foreground-color: #161225;
    --vm-osm-link-color: var(--vm-osm-brand-link-color);
    --vm-osm-logo-color: var(--vm-osm-brand-color);
    --vm-osm-msg-border: 0.125em solid #efeef3;

    --vm-osm-color-orange: #ff5b24;
    --vm-osm-color-white: #ffffff;
    --vm-osm-color-dark: #161225;
    --vm-osm-color-purple: #722ac9;
    --vm-osm-color-orange-light: #fff4ec;
    --vm-osm-color-gray-light: #f6f6f9;
    --vm-osm-color-purple-light: #f1ebff;
    --vm-osm-color-blue-light: #eff2ff;
    --vm-osm-color-blue: #5a78ff;

    --vm-osm-brand-color: var(--vm-osm-color-orange);
    --vm-osm-brand-color-light: var(--vm-osm-color-orange-light);
    --vm-osm-brand-link-color: var(--vm-osm-color-purple);
    --vm-osm-brand-close-bg: var(--vm-osm-color-purple-light);
    --vm-osm-brand-close-fg: var(--vm-osm-color-purple);

    --vm-osm-filled-bg: var(--vm-osm-brand-color);
    --vm-osm-filled-fg: var(--vm-osm-color-white);
    --vm-osm-filled-link: var(--vm-osm-color-white);
    --vm-osm-filled-logo: var(--vm-osm-color-white);
    --vm-osm-filled-border: none;

    --vm-osm-light-bg: var(--vm-osm-brand-color-light);
    --vm-osm-light-fg: var(--vm-osm-color-dark);
    --vm-osm-light-link: var(--vm-osm-brand-link-color);
    --vm-osm-light-logo: var(--vm-osm-brand-color);
    --vm-osm-light-border: none;

    --vm-osm-gray-bg: var(--vm-osm-color-gray-light);
    --vm-osm-gray-fg: var(--vm-osm-color-dark);
    --vm-osm-gray-link: var(--vm-osm-brand-link-color);
    --vm-osm-gray-logo: var(--vm-osm-brand-color);
    --vm-osm-gray-border: none;

    --vm-osm-purple-bg: var(--vm-osm-color-purple-light);
    --vm-osm-purple-fg: var(--vm-osm-color-dark);
    --vm-osm-purple-link: var(--vm-osm-brand-link-color);
    --vm-osm-purple-logo: var(--vm-osm-brand-color);
    --vm-osm-purple-border: none;
  }

  :host([brand='mobilepay']),
  :host(:not([brand])[country='DK' i]),
  :host(:not([brand])[country='FI' i]),
  :host([brand='vipps'][country='DK' i]),
  :host([brand='vipps'][country='FI' i]) {
    --vm-osm-brand-color: var(--vm-osm-color-blue);
    --vm-osm-brand-color-light: var(--vm-osm-color-blue-light);
    --vm-osm-brand-link-color: var(--vm-osm-color-blue);
    --vm-osm-brand-close-bg: var(--vm-osm-color-white);
    --vm-osm-brand-close-fg: var(--vm-osm-color-blue);
  }

  :host([variant='filled']),
  :host([variant='orange']) {
    --vm-osm-background-color: var(--vm-osm-filled-bg);
    --vm-osm-foreground-color: var(--vm-osm-filled-fg);
    --vm-osm-link-color: var(--vm-osm-filled-link);
    --vm-osm-logo-color: var(--vm-osm-filled-logo);
    --vm-osm-msg-border: var(--vm-osm-filled-border);
  }

  :host([variant='light']),
  :host([variant='light-orange']) {
    --vm-osm-background-color: var(--vm-osm-light-bg);
    --vm-osm-foreground-color: var(--vm-osm-light-fg);
    --vm-osm-link-color: var(--vm-osm-light-link);
    --vm-osm-logo-color: var(--vm-osm-light-logo);
    --vm-osm-msg-border: var(--vm-osm-light-border);
  }

  :host([variant='gray']) {
    --vm-osm-background-color: var(--vm-osm-gray-bg);
    --vm-osm-foreground-color: var(--vm-osm-gray-fg);
    --vm-osm-link-color: var(--vm-osm-gray-link);
    --vm-osm-logo-color: var(--vm-osm-gray-logo);
    --vm-osm-msg-border: var(--vm-osm-gray-border);
  }

  :host([variant='purple']) {
    --vm-osm-background-color: var(--vm-osm-purple-bg);
    --vm-osm-foreground-color: var(--vm-osm-purple-fg);
    --vm-osm-link-color: var(--vm-osm-purple-link);
    --vm-osm-logo-color: var(--vm-osm-purple-logo);
    --vm-osm-msg-border: var(--vm-osm-purple-border);
  }

  .message {
    background-color: var(--vm-osm-background-color);
    color: var(--vm-osm-foreground-color);
    border-radius: 0.9375em;
    box-sizing: border-box;
    font-size: 0.875em;
    line-height: 1.5em;
    padding: 0.65em 1em 1em;
    text-align: left;
    border: var(--vm-osm-msg-border);
  }

  .title {
    margin: 0;
    padding: 0;
  }

  .title > * {
    vertical-align: middle;
  }

  .title > b {
    font-weight: 700;
  }

  .description {
    align-items: center;
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: space-between;
    margin: 0;
    padding: 0;
  }

  .read-more-button {
    /** Safari will override font properties for buttons unless specified */
    font-family: 'Vipps', Arial, sans-serif;
    font-size: 1em;
    font-style: normal;
    font-weight: 400;
    line-height: 1.5em;

    background: none;
    border: 0;
    color: var(--vm-osm-link-color);
    cursor: pointer;
    padding: 0;

    /** Safari does not recognize text-decoration correctly, so each have to be specified separately  **/
    text-decoration-style: solid;
    text-decoration-color: currentColor;
    text-decoration-thickness: 0.055em;
    text-decoration-line: underline;
  }

  .message .vipps-logo {
    width: 3.286em;
    height: 1.143em;
    fill: var(--vm-osm-logo-color);
  }

  .link-icon {
    fill: currentColor;
    height: 0.45em;
    margin-left: 0.35em;
    width: 0.45em;
  }

  .popup {
    padding: 0;
    margin: 0;
    width: 100%;
    height: 100%;
    background: var(--vm-osm-brand-color-light);
    color: var(--vm-osm-color-dark);
    font-family: 'Vipps', Arial, sans-serif;
    font-style: normal;
    font-weight: 400;
    letter-spacing: normal;
    text-rendering: optimizelegibility;
  }

  .dialog {
    background: var(--vm-osm-brand-color-light);
    border-radius: 20px;
    border: 0;
    box-sizing: border-box;
    font-size: 16px;
    line-height: 24px;
    margin: 60px auto;
    max-width: 480px;
    min-width: 320px;
    padding: 32px 16px;
    text-align: left;
    width: calc(100% - 32px);
    color: inherit;
  }

  .dialog::backdrop {
    background: #161225;
    opacity: 0.6;
  }

  .dialog > header {
    align-items: center;
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    margin: 0 0 16px 0;
  }

  .dialog > section {
    margin: 16px 0;
  }

  .dialog > footer {
    margin: 16px 0 0 0;
  }

  .dialog p {
    padding: 0;
    margin: 0;
  }

  .dialog-title {
    font-size: 24px;
    font-weight: 500;
    line-height: 32px;
    margin: 0;
    padding: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .dialog-title .mobilepay-logo {
    color: var(--vm-osm-brand-color);
  }

  .vipps-logo {
    fill: var(--vm-osm-brand-color);
    width: 84px;
    height: 32px;
  }

  .list {
    margin: 0;
    padding: 16px;
  }

  .list > li {
    align-items: center;
    column-gap: 8px;
    display: grid;
    grid-template-columns: 1fr 5fr;
    margin-bottom: 8px;
  }

  .list > li:last-of-type {
    margin-bottom: 0;
  }

  .list > li > svg {
    width: 64px;
    height: 64px;
  }

  .close-button {
    align-self: flex-start;
    background: var(--vm-osm-brand-close-bg);
    border-radius: 50%;
    border: 1px solid transparent;
    cursor: pointer;
    height: 30px;
    line-height: 1;
    margin-left: 8px;
    outline: none;
    padding: 6px;
    text-decoration: none;
    transition:
      background 100ms ease-in-out 0s,
      color 100ms ease-in-out 0s,
      box-shadow 100ms ease-in-out 0s;
    width: 30px;
  }

  .close-button:focus {
    box-shadow:
      0 0 0 1px #ffffff,
      0 0 0 4px rgba(67, 47, 255, 0.6);
  }

  .close-icon {
    fill: var(--vm-osm-brand-close-fg);
    height: 16px;
    vertical-align: 2px;
    width: 16px;
  }

  .primary-button {
    /** Safari will override font properties for buttons unless specified */
    font-family: 'Vipps', Arial, sans-serif;
    font-style: normal;
    font-size: 18px;
    font-weight: 500;
    line-height: 22px;

    background: var(--vm-osm-brand-color);
    border-radius: 50px;
    border: 1px solid transparent;
    color: var(--vm-osm-color-white);
    cursor: pointer;
    min-height: 40px;
    outline: none;
    padding: 0 24px;
    text-align: center;
    text-decoration: none;
    transition:
      background 100ms ease-in-out,
      color 100ms ease-in-out,
      box-shadow 100ms ease-in-out;
    width: 100%;
  }

  .primary-button:focus {
    box-shadow:
      0 0 0 1px #ffffff,
      0 0 0 4px rgba(67, 47, 255, 0.6);
  }
`,jt=l`
  @font-face {
    font-family: 'Vipps';
    font-style: normal;
    font-weight: 400;
    src:
      local('Vipps Text-Web'),
      local('Vipps Text-Web'),
      url('https://designsystem.vippsmobilepay.com/fonts/v1/VippsText-Regular-Web.woff2')
        format('woff2'),
      url('https://designsystem.vippsmobilepay.com/fonts/v1/VippsText-Regular-Web.woff')
        format('woff');
  }

  @font-face {
    font-family: 'Vipps';
    font-style: normal;
    font-weight: 500;
    src:
      local('Vipps Text-Web Medium'),
      local('Vipps Text-Web Medium'),
      url('https://designsystem.vippsmobilepay.com/fonts/v1/VippsText-Medium-Web.woff2')
        format('woff2'),
      url('https://designsystem.vippsmobilepay.com/fonts/v1/VippsText-Medium-Web.woff')
        format('woff');
  }

  @font-face {
    font-family: 'Vipps';
    font-style: normal;
    font-weight: 700;
    src:
      local('Vipps Text-Web Bold'),
      local('Vipps Text-Web Bold'),
      url('https://designsystem.vippsmobilepay.com/fonts/v1/VippsText-Bold-Web.woff2')
        format('woff2'),
      url('https://designsystem.vippsmobilepay.com/fonts/v1/VippsText-Bold-Web.woff') format('woff');
  }

  @font-face {
    font-family: 'Paytype';
    font-style: normal;
    font-weight: 500;
    src:
      url('https://designsystem.vippsmobilepay.com/fonts/v1/Paytype-Rg.woff2') format('woff2'),
      url('https://designsystem.vippsmobilepay.com/fonts/v1/Paytype-Rg.woff') format('woff');
  }

  @font-face {
    font-family: 'Paytype';
    font-style: normal;
    font-weight: 700;
    src:
      url('https://designsystem.vippsmobilepay.com/fonts/v1/Paytype-Bd.woff2') format('woff2'),
      url('https://designsystem.vippsmobilepay.com/fonts/v1/Paytype-Bd.woff') format('woff');
  }
`,Mt=e=>`showModal`in e&&typeof e.showModal==`function`&&`close`in e&&typeof e.close==`function`&&`open`in e&&typeof e.open==`boolean`,Nt=e=>{if(e&&!Mt(e)){let t=e;t.hidden=!0}},Pt=e=>{let t=window.open(``,`Betal med Vipps`,`toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=600,height=600`);if(!t)return;let n=document.createElement(`style`);n.innerHTML=jt.cssText,t.document.head.appendChild(n);let r=document.createElement(`style`);r.innerHTML=At.cssText,t.document.head.appendChild(r);let i=document.createElement(`meta`);i.name=`viewport`,i.content=`width=device-width, initial-scale=1.0, maximum-scale=1.0,user-scalable=0`,t.document.head.appendChild(i);let a=document.createElement(`div`);a.className=`dialog`,a.innerHTML=e.innerHTML,t.document.body.className=`popup`,t.document.body.appendChild(a),t.document.querySelectorAll(`button`).forEach(e=>{e.addEventListener(`click`,()=>{t.close()})})},Ft=(e,t=`no`)=>new Intl.NumberFormat(`nb-NO`,{style:`currency`,currency:`NOK`,maximumFractionDigits:0,currencyDisplay:t===`no`?`symbol`:`code`}).format(e),It=e=>{let t=Number(e&&e.replaceAll(/\s/g,``));return Number.isNaN(t)||t>1e8||t<=0?0:t},Lt=(e,t=`no`)=>{if(e===0)return`0`;let n=e/100;return Ft(Math.ceil(n/3),t)},Rt=t({templates:()=>zt}),zt={h0f02c0e3923fbe0c:D`<p>And remember: ${0} charges no fees when you pay online.</p>`,h22543f3c6e7b2044:D`<span>later</span>`,h56211fca924f532e:D`Press <b class="bold">Pay later</b> in ${0}, and choose <b class="bold">Pay in three parts</b>`,s011b96b74c19cec6:`The payment is withdrawn automatically 14 days after the items have shipped`,s21aa0c8945fd66cf:`MobilePay`,s2f5bf8b5091cca98:`Close`,s39ef0b4a9b8d066b:`The payment is withdrawn automatically 14 days after the items have shipped`,s70662079220730d7:`Buy now`,s869c348c846b8e13:`Read more`,s8c6dae885e8690ed:`Get the item before you pay`,s9b094b73b72d5891:`Choose to pay later`,s9dcfc74eed487b15:`Pay in 14 days with`,s9f6b44ba247dc360:B`Three interest-free payments of ${0}`,sa02057b2beef577b:`No fees. No interest.`,sa753c3417caed6bc:`Get the items before you pay. No fees. No interest. No stress.`,sadc6c7cfafb388b7:`Divide the payment in three with`,se1b7d05e8cc6257c:B`${0} charges no fees when you pay online`,seb747f696079fa46:`Choose card to pay with and complete purchase`,sf5ff2a49f8363f08:`Here, you can pay with`,sf85c611c819cdadf:B`Choose to pay with ${0}.`,sfd37f3a73506d203:`How to pay:`,sfffe47e068717efb:`Pay with`},Bt=t({templates:()=>Vt}),Vt={h0f02c0e3923fbe0c:D`<p>Og husk: ${0} tager ingen gebyrer, når du betaler på nettet.</p>`,h22543f3c6e7b2044:D`<span>senere</span>`,h56211fca924f532e:D`Tryk på <b class="bold">Betal senere</b> i ${0}, og vælg
                          <b class="bold">Betal i tre dele</b>`,s011b96b74c19cec6:`Betalingen trækkes automatisk på dag 1, 30 og 60.`,s21aa0c8945fd66cf:`MobilePay`,s2f5bf8b5091cca98:`Lukk`,s39ef0b4a9b8d066b:`Betalingen trækkes automatisk 14 dage efter varerne er sendt`,s70662079220730d7:`Få først`,s869c348c846b8e13:`Læs mere`,s8c6dae885e8690ed:`Få varerne før du betaler`,s9b094b73b72d5891:`Vælg at betale senere`,s9dcfc74eed487b15:`Betal om 14 dage med`,s9f6b44ba247dc360:B`Tre rentefrie betalinger på ${0}`,sa02057b2beef577b:`Ingen gebyrer. Ingen renter.`,sa753c3417caed6bc:`Få varerne før du betaler. Ingen gebyrer. Ingen renter. Ingen stress.`,sadc6c7cfafb388b7:`Delt betalingen op i tre dele med`,se1b7d05e8cc6257c:B`${0} tager ingen gebyrer, når du betaler på nettet`,seb747f696079fa46:`Vælg det kort, du vil betale med, og fuldfør købet`,sf5ff2a49f8363f08:`Her kan du betale med`,sf85c611c819cdadf:B`Vælg at betale med ${0}.`,sfd37f3a73506d203:`Sådan betaler du:`,sfffe47e068717efb:`Betal med`},Ht=t({templates:()=>Ut}),Ut={h0f02c0e3923fbe0c:D`<p>Huom. ${0} ei peri erillisiä kuluja verkkomaksuista.</p>`,h22543f3c6e7b2044:D`<span>myöhemmin</span>`,h56211fca924f532e:D`Paina <b class="bold">Maksa myöhemmin</b> ${0} ja valitse
                          <b class="bold">Maksa kolmessa erässä</b>`,s011b96b74c19cec6:`Maksu veloitetaan automaattisesti päivinä 1, 30 ja 60.`,s21aa0c8945fd66cf:`MobilePaylla`,s2f5bf8b5091cca98:`Sulje`,s39ef0b4a9b8d066b:`Maksu veloitetaan automaattisesti 14 päivän kuluttua tavaroiden lähettämisestä`,s70662079220730d7:`Hanki ensin`,s869c348c846b8e13:`Lue lisää`,s8c6dae885e8690ed:`Saat tavarat ennen maksamista`,s9b094b73b72d5891:`Valitse maksu myöhemmin`,s9dcfc74eed487b15:`Maksa 14 päivän kuluttua`,s9f6b44ba247dc360:B`Kolme korkotonta maksua ${0}`,sa02057b2beef577b:`Ei kuluja. Ei korkoja.`,sa753c3417caed6bc:`Saat tavarat ennen maksamista. Ei kuluja. Ei korkoja. Ei stressiä.`,sadc6c7cfafb388b7:`Jaa maksu kolmeen erään`,se1b7d05e8cc6257c:B`${0} ei peri erillisiä kuluja verkkomaksuista`,seb747f696079fa46:`Valitse haluamasi maksukortti ja suorita ostos`,sf5ff2a49f8363f08:`Täällä voit maksaa`,sf85c611c819cdadf:B`Valitse maksutavaksi ${0}.`,sfd37f3a73506d203:`Näin helppoa se on:`,sfffe47e068717efb:`Maksa`},Wt=t({templates:()=>Gt}),Gt={h0f02c0e3923fbe0c:D`<p>Och kom ihåg: ${0} tar inga avgifter när du betalar online.</p>`,h22543f3c6e7b2044:D`<span>senare</span>`,h56211fca924f532e:D`Tryck på <b class="bold">Betala senare</b> i ${0}, och välj
                          <b class="bold">Betala i tre delar</b>`,s011b96b74c19cec6:`Betalningen sker automatiskt dag 1, 30 och 60.`,s21aa0c8945fd66cf:`MobilePay`,s2f5bf8b5091cca98:`Stäng`,s39ef0b4a9b8d066b:`Betalningen sker automatiskt 14 dagar efter att varorna skickats`,s70662079220730d7:`Få först`,s869c348c846b8e13:`Läs mer`,s8c6dae885e8690ed:`Få varorna innan du betalar`,s9b094b73b72d5891:`Välj att betala senare`,s9dcfc74eed487b15:`Betala om 14 dagar med`,s9f6b44ba247dc360:B`Tre räntefria betalningar på ${0}`,sa02057b2beef577b:`Inga avgifter. Ingen ränta.`,sa753c3417caed6bc:`Få varorna innan du betalar. Inga avgifter. Ingen ränta. Ingen stress.`,sadc6c7cfafb388b7:`Dela betalningen i tre med`,se1b7d05e8cc6257c:B`${0} tar inga avgifter när du betalar online`,seb747f696079fa46:`Välj kortet du vill betala med och slutföra köpet`,sf5ff2a49f8363f08:`Här kan du betala med`,sf85c611c819cdadf:B`Välj att betala med ${0}.`,sfd37f3a73506d203:`Så här betalar du:`,sfffe47e068717efb:`Betala med`},Kt={en:Rt,da:Bt,fi:Ht,sv:Wt},{getLocale:qt,setLocale:Jt}=Dt({sourceLocale:`no`,targetLocales:ge,loadLocale:e=>Promise.resolve(Kt[e])}),Yt=e=>_e.includes(e),Xt=()=>D`<svg
    class="vipps-logo"
    role="img"
    aria-label="Vipps"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 43 16"
  >
    <title>Vipps</title>
    <path
      d="m2.5677 4.9143 1.7831 5.2096L6.099 4.9143H8.162l-3.0768 7.727H3.5467L.4698 4.9143h2.0979ZM12.8121 11.4175c1.2936 0 2.0279-.6293 2.7272-1.5384.3846-.4895.8741-.5944 1.2237-.3147.3496.2798.3846.8042 0 1.2937-1.014 1.3286-2.3076 2.1328-3.9509 2.1328-1.7832 0-3.3565-.979-4.4404-2.6922-.3147-.4545-.2448-.944.1049-1.1888.3496-.2447.874-.1398 1.1887.3497.7692 1.1538 1.8182 1.9579 3.1468 1.9579Zm2.4125-4.3005c0 .6293-.4895 1.0489-1.0489 1.0489-.5594 0-1.0489-.4196-1.0489-1.049 0-.6293.4895-1.0488 1.0489-1.0488.5594 0 1.0489.4545 1.0489 1.0489ZM20.1545 4.9143v1.0489c.5244-.7343 1.3286-1.2587 2.5174-1.2587 1.5034 0 3.2516 1.2587 3.2516 3.9509 0 2.8321-1.6783 4.1956-3.4265 4.1956-.909 0-1.7482-.3496-2.3775-1.2237v3.7062h-1.8881V4.9142h1.9231Zm0 3.846c0 1.5733.909 2.4125 1.923 2.4125.979 0 1.9579-.7692 1.9579-2.4125 0-1.6084-.9789-2.3776-1.9579-2.3776s-1.923.7343-1.923 2.3776ZM29.245 4.9143v1.0489c.5245-.7343 1.3287-1.2587 2.5174-1.2587 1.5035 0 3.2517 1.2587 3.2517 3.9509 0 2.8321-1.6783 4.1956-3.4265 4.1956-.909 0-1.7482-.3496-2.3775-1.2237v3.7062H27.322V4.9142h1.923Zm0 3.846c0 1.5733.9091 2.4125 1.923 2.4125.979 0 1.958-.7692 1.958-2.4125 0-1.6084-.979-2.3776-1.958-2.3776-1.0139 0-1.923.7343-1.923 2.3776ZM39.0349 4.7045c1.5734 0 2.6922.7342 3.1817 2.5523l-1.7132.2797c-.035-.909-.5944-1.2237-1.4335-1.2237-.6294 0-1.1189.2797-1.1189.7342 0 .3497.2448.6993.979.8392l1.2937.2447c1.2587.2448 1.958 1.084 1.958 2.2028 0 1.6782-1.5035 2.5173-2.937 2.5173-1.5035 0-3.1817-.7692-3.4265-2.6572l1.7133-.2797c.1049.979.6992 1.3286 1.6782 1.3286.7343 0 1.2238-.2797 1.2238-.7342 0-.4196-.2448-.7343-1.0489-.8741l-1.1888-.2098c-1.2587-.2448-2.0279-1.1189-2.0279-2.2377.0349-1.7482 1.6083-2.4824 2.867-2.4824Z"
    />
  </svg>`,Zt=e=>e===`vipps`?D`<svg
      role="presentation"
      focusable="false"
      aria-hidden="true"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 64 64"
    >
      <circle cx="31.658" cy="31.9735" r="21.1052" fill="#EFEEF3" />
      <g
        fill-rule="evenodd"
        clip-path="url(#vipps-swipe-illustration__clip-path)"
        clip-rule="evenodd"
      >
        <path
          fill="#551488"
          d="M44.037 14.8783a21.2224 21.2224 0 0 1 5.1653 5.3601H10.5527v-3.3501c0-1.1101.9-2.01 2.0101-2.01H44.037Z"
        />
        <path
          fill="#F6F6F9"
          d="M14.5725 17.5582c0 .5551-.4499 1.005-1.005 1.005-.555 0-1.005-.4499-1.005-1.005 0-.555.45-1.005 1.005-1.005.5551 0 1.005.45 1.005 1.005Zm3.015 0c0 .5551-.4499 1.005-1.005 1.005-.555 0-1.005-.4499-1.005-1.005 0-.555.45-1.005 1.005-1.005.5551 0 1.005.45 1.005 1.005Zm2.01 1.005c.5551 0 1.0051-.4499 1.0051-1.005 0-.555-.45-1.005-1.0051-1.005-.555 0-1.005.45-1.005 1.005 0 .5551.45 1.005 1.005 1.005Z"
        />
        <path
          fill="#C9C6D7"
          d="M44.0651 49.0485c5.2637-3.8314 8.6879-10.0374 8.6981-17.0437v-.0628c-.0063-4.3311-1.3172-8.3563-3.5608-11.7038H10.5527v26.8017c.0008 1.1095.9004 2.0086 2.0101 2.0086h31.5023Z"
        />
      </g>
      <path
        fill="#FF5B24"
        d="M21.2725 33.6485c0-2.2202 1.7998-4.02 4.02-4.02h12.7301c2.2202 0 4.0201 1.7998 4.0201 4.02 0 2.2202-1.7998 4.0201-4.0201 4.0201H25.2925c-2.2202 0-4.02-1.7999-4.02-4.0201Z"
      />
      <path
        fill="#FFD3BB"
        fill-rule="evenodd"
        d="M35.1308 52.7947a2.134 2.134 0 0 0-.358-.4344l-.3836-.3569c-1.7825-1.6585-1.839-4.463-.1247-6.1919l1.192-1.2021v-.9866a28.2807 28.2807 0 0 0-1.2253-8.1846l-.1606-.5278c-.3394-1.1151.2895-2.2942 1.4046-2.6336 1.1151-.3393 2.2942.2895 2.6336 1.4046l.1606.5278a32.5037 32.5037 0 0 1 1.2538 6.2989l.4328-.4364a1.6821 1.6821 0 0 1 1.218-.4995 1.6825 1.6825 0 0 1 1.187.5068 1.6825 1.6825 0 0 1 .4823 1.1815v.6332c0-.9325.7559-1.6885 1.6884-1.6885.9325 0 1.6885.756 1.6885 1.6885v.8442c0-.9325.7559-1.6884 1.6884-1.6884.9325 0 1.6884.7559 1.6884 1.6884v.3596c-3.1419 5.0562-8.3618 8.6865-14.4662 9.6972Z"
        clip-rule="evenodd"
      />
      <defs>
        <clipPath id="vipps-swipe-illustration__clip-path">
          <path fill="#fff" d="M0 0h42.2105v34.1704H0z" transform="translate(10.5527 14.8783)" />
        </clipPath>
      </defs>
    </svg> `:D`
      <svg
        role="presentation"
        focusable="false"
        aria-hidden="true"
        width="64"
        height="64"
        viewBox="0 0 64 64"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          d="M31.6578 53.3562C43.3139 53.3562 52.7631 43.9071 52.7631 32.251C52.7631 20.5949 43.3139 11.1458 31.6578 11.1458C20.0017 11.1458 10.5526 20.5949 10.5526 32.251C10.5526 43.9071 20.0017 53.3562 31.6578 53.3562Z"
          fill="#EAE9EE"
        />
        <mask
          id="mask0_299_51201"
          style="mask-type:luminance"
          maskUnits="userSpaceOnUse"
          x="10"
          y="15"
          width="43"
          height="35"
        >
          <path d="M52.8855 15.2212H10.7827V49.304H52.8855V15.2212Z" fill="white" />
        </mask>
        <g mask="url(#mask0_299_51201)">
          <path
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M44.1817 15.2212C46.1972 16.6838 47.9448 18.4946 49.3335 20.5671H10.7827V17.222C10.7827 16.1161 11.6817 15.2212 12.7877 15.2212H44.1817Z"
            fill="#5A78FF"
          />
          <path
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M14.7927 17.8952C14.7927 18.4482 14.3431 18.8977 13.7902 18.8977C13.2372 18.8977 12.7877 18.4482 12.7877 17.8952C12.7877 17.3422 13.2372 16.8927 13.7902 16.8927C14.3431 16.8927 14.7927 17.3422 14.7927 17.8952ZM17.8002 17.8952C17.8002 18.4482 17.3506 18.8977 16.7977 18.8977C16.2447 18.8977 15.7952 18.4482 15.7952 17.8952C15.7952 17.3422 16.2447 16.8927 16.7977 16.8927C17.3506 16.8927 17.8002 17.3422 17.8002 17.8952ZM19.8051 18.8977C20.3581 18.8977 20.8076 18.4482 20.8076 17.8952C20.8076 17.3422 20.3581 16.8927 19.8051 16.8927C19.2522 16.8927 18.8026 17.3422 18.8026 17.8952C18.8026 18.4482 19.2522 18.8977 19.8051 18.8977Z"
            fill="#F0F0ED"
          />
          <path
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M44.2091 49.304C49.4601 45.4819 52.8749 39.2917 52.8855 32.3038V32.2404C52.8792 27.9202 51.5706 23.906 49.3335 20.5671H10.7827V47.299C10.7827 48.4071 11.6817 49.304 12.7877 49.304H44.2091Z"
            fill="#D1CED6"
          />
        </g>
        <path
          d="M21.4746 33.9436C21.4746 31.7297 23.2706 29.9336 25.4846 29.9336H38.1815C40.3954 29.9336 42.1915 31.7297 42.1915 33.9436C42.1915 36.1576 40.3954 37.9536 38.1815 37.9536H25.4846C23.2706 37.9536 21.4746 36.1576 21.4746 33.9436Z"
          fill="#5A78FF"
        />
        <path
          fill-rule="evenodd"
          clip-rule="evenodd"
          d="M35.2985 53.0396C35.1993 52.8814 35.0811 52.7357 34.9418 52.607L34.5598 52.2503C32.7828 50.5957 32.7258 47.7992 34.4353 46.0749L35.6235 44.8761V43.8926C35.6193 41.1257 35.2077 38.3757 34.4015 35.7291L34.2411 35.2036C33.9034 34.0914 34.5303 32.9158 35.6425 32.576C36.7548 32.2383 37.9303 32.8652 38.2701 33.9774L38.4305 34.5029C39.0552 36.5522 39.4731 38.6585 39.6821 40.7859L40.1105 40.3533C40.1105 40.3533 40.1274 40.3364 40.1358 40.328C40.2899 40.1781 40.4651 40.0641 40.6508 39.9861C40.8534 39.8995 41.075 39.8531 41.3093 39.8531C41.5435 39.8531 41.7651 39.9016 41.9678 39.9861C42.1408 40.0599 42.3033 40.1633 42.449 40.2963C42.4785 40.3237 42.508 40.3533 42.5355 40.3807C42.6748 40.5285 42.7845 40.6973 42.8605 40.8746C42.947 41.0772 42.9956 41.3009 42.9956 41.5352V42.1662C42.9956 41.2355 43.749 40.482 44.6798 40.482C45.6105 40.482 46.364 41.2355 46.364 42.1662V43.0083C46.364 42.0776 47.1174 41.3241 48.0482 41.3241C48.9789 41.3241 49.7324 42.0776 49.7324 43.0083V43.3671C46.5982 48.4113 41.3916 52.0308 35.3027 53.0396H35.2985Z"
          fill="#FFCEB6"
        />
      </svg>
    `,Qt=D`<svg
  xmlns="http://www.w3.org/2000/svg"
  viewBox="0 0 6 7"
  class="link-icon"
  role="presentation"
  focusable="false"
  aria-hidden="true"
>
  <path
    fill-rule="evenodd"
    d="M5.962.8086a.4983.4983 0 0 0-.1106-.1643A.4984.4984 0 0 0 5.5.5h-5a.5.5 0 0 0 0 1h3.7929L.1464 5.6464a.5.5 0 1 0 .7072.7072L5 2.207V6a.5.5 0 0 0 1 0V.997a.5007.5007 0 0 0-.038-.1884Z"
    clip-rule="evenodd"
  />
</svg> `,$t=D`<svg
  xmlns="http://www.w3.org/2000/svg"
  fill="none"
  viewBox="0 0 64 65"
  role="presentation"
  focusable="false"
  aria-hidden="true"
>
  <circle cx="31.658" cy="32.6314" r="21.1052" fill="#EFEEF3" />
  <path fill="#FFD3BB" d="M21.9863 40.3141s2.9067 7.5835 13.8744 8.061l5.6933-9.4348" />
  <circle cx="31.6574" cy="16.9941" r="7.1564" fill="#FFB992" />
  <circle cx="27.1848" cy="23.7033" r="7.1564" fill="#551488" />
  <path
    fill="#722AC9"
    fill-rule="evenodd"
    d="M36.1301 30.8599c3.9524 0 7.1564-3.2041 7.1564-7.1565 0-3.9524-3.204-7.1564-7.1564-7.1564-3.7021 0-6.7476 2.8111-7.1185 6.4151-.9363.3479-1.6034 1.2494-1.6034 2.3068 0 1.3587 1.1013 2.46 2.46 2.46.1116 0 .2216-.0074.3293-.0218 1.2863 1.9023 3.4634 3.1528 5.9326 3.1528Z"
    clip-rule="evenodd"
  />
  <path
    fill="#FFB992"
    fill-rule="evenodd"
    d="M41.9302 29.3992c.9699-2.0743-.1163-4.5771-2.4063-4.5771-7.3971 0-13.3936 5.9965-13.3936 13.3936 0 .5071.0282 1.0077.083 1.5002l4.7786 10.2195c.265.5666 1.0708.5666 1.3357 0l9.6026-20.5362Z"
    clip-rule="evenodd"
  />
  <path
    fill="#FF985F"
    fill-rule="evenodd"
    d="M21.4028 29.3992c-.9699-2.0743.1164-4.5771 2.4063-4.5771 7.3971 0 13.3936 5.9965 13.3936 13.3936 0 .5071-.0282 1.0077-.083 1.5002l-4.7786 10.2195c-.265.5666-1.0708.5666-1.3357 0l-9.6026-20.5362Z"
    clip-rule="evenodd"
  />
  <rect width="1.7891" height="7.3801" x="31.6572" y="22.3616" fill="#722AC9" rx=".8946" />
  <path
    fill="#FFD3BB"
    d="M44.7258 39.1727c-3.9508-4.8702-1.1919-12.3947-1.161-12.479.525-1.3682-.1618-2.8984-1.5272-3.4233-1.3682-.5249-2.9039.162-3.4289 1.5301-.1087.2854-1.6821 4.4999-1.2517 9.3931-1.7022 1.7237-4.6752 5.8976-3.3822 13.1942l-.4796.4796 4.8032 4.8032c4.661-1.5437 8.6018-4.6681 11.1872-8.738l-4.7598-4.7599ZM18.4629 29.8032c0-1.3586 1.1014-2.46 2.46-2.46h4.4728c1.3586 0 2.46 1.1014 2.46 2.46 0 1.3583-1.1008 2.4595-2.459 2.46 1.3582.0006 2.459 1.1018 2.459 2.4601 0 1.3586-1.1014 2.46-2.46 2.46 1.3586 0 2.46 1.1014 2.46 2.46 0 1.3587-1.1014 2.46-2.46 2.46h-4.4728c-1.3586 0-2.46-1.1013-2.46-2.46 0-1.3586 1.1014-2.46 2.46-2.46-1.3586 0-2.46-1.1014-2.46-2.46 0-1.3583 1.1008-2.4595 2.459-2.4601-1.3582-.0005-2.459-1.1017-2.459-2.46Z"
  />
</svg>`,en=D`<svg
  xmlns="http://www.w3.org/2000/svg"
  fill="currentcolor"
  viewBox="0 0 24 24"
  class="close-icon"
  role="presentation"
  focusable="false"
  aria-hidden="true"
>
  <path
    fill-rule="evenodd"
    d="M19.707 5.707a1 1 0 0 0-1.414-1.414L12 10.586 5.707 4.293a1 1 0 0 0-1.414 1.414L10.586 12l-6.293 6.293a1 1 0 1 0 1.414 1.414L12 13.414l6.293 6.293a1 1 0 0 0 1.414-1.414L13.414 12l6.293-6.293z"
    clip-rule="evenodd"
  />
</svg>`,tn=e=>e===`vipps`?D`<svg
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 64 65"
      role="presentation"
      focusable="false"
      aria-hidden="true"
    >
      <circle cx="31.6604" cy="32.6314" r="21.1067" fill="#EFEEF3" />
      <path
        fill="#FFD3BB"
        fill-rule="evenodd"
        d="M26.8057 53.1772V36.6417h16.6742v13.479c-3.3723 2.2836-7.4402 3.6174-11.8197 3.6174-1.6706 0-3.2959-.1941-4.8545-.5609Z"
        clip-rule="evenodd"
      />
      <path
        fill="#FF5B24"
        d="M22.7949 12.369c0-1.3988 1.134-2.5328 2.5328-2.5328h12.664c1.3988 0 2.5328 1.134 2.5328 2.5328v25.7502c0 1.3988-1.134 2.5328-2.5328 2.5328h-12.664c-1.3988 0-2.5328-1.134-2.5328-2.5328V12.369Z"
      />
      <path fill="#FF985F" d="M22.7949 13.8465h17.7296v22.7952H22.7949z" />
      <path
        fill="#fff"
        d="M26.5938 19.9675c0-.9326.7559-1.6886 1.6885-1.6886h6.7541c.9326 0 1.6885.756 1.6885 1.6886v6.7541c0 .9325-.7559 1.6885-1.6885 1.6885h-6.7541c-.9326 0-1.6885-.756-1.6885-1.6885v-6.7541Z"
      />
      <path
        fill="#551488"
        fill-rule="evenodd"
        d="M34.3608 20.7273a.6332.6332 0 0 1 .1267.8865l-3.166 4.2213a.6332.6332 0 0 1-.9543.0679l-1.6886-1.6886a.6332.6332 0 1 1 .8955-.8955l1.1723 1.1723 2.7279-3.6372a.6332.6332 0 0 1 .8865-.1267Z"
        clip-rule="evenodd"
      />
      <path
        fill="#FFD3BB"
        fill-rule="evenodd"
        d="M20.2627 25.033c0-1.2822 1.0395-2.3217 2.3217-2.3217h4.2214c1.2822 0 2.3217 1.0395 2.3217 2.3217 0 1.2823-1.0395 2.3218-2.3217 2.3218 1.2822 0 2.3217 1.0394 2.3217 2.3217 0 1.2823-1.0395 2.3217-2.3217 2.3217 1.2822 0 2.3217 1.0395 2.3217 2.3218 0 1.2822-1.0395 2.3217-2.3217 2.3217h-4.2214c-1.2822 0-2.3217-1.0395-2.3217-2.3217 0-1.2823 1.0395-2.3218 2.3217-2.3218-1.2822 0-2.3217-1.0394-2.3217-2.3217 0-1.2823 1.0395-2.3217 2.3217-2.3217-1.2822 0-2.3217-1.0395-2.3217-2.3218Zm17.9407 9.243c-2.6395 1.4274-4.4324 4.22-4.4324 7.4313h8.4426v-1.6507c.7594-.4632 1.2664-1.2994 1.2664-2.254v-4.3269c0-3.6719-2.9767-6.6486-6.6486-6.6486h-1.3719c-1.4571 0-2.6383 1.1812-2.6383 2.6383s1.1812 2.6384 2.6383 2.6384h1.3719c.7577 0 1.372.6142 1.372 1.3719v.8003Z"
        clip-rule="evenodd"
      />
    </svg>`:D`
      <svg
        width="64"
        height="65"
        viewBox="0 0 64 65"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        role="presentation"
        focusable="false"
        aria-hidden="true"
      >
        <path
          d="M31.66 53.6755C43.3169 53.6755 52.7667 44.2257 52.7667 32.5689C52.7667 20.912 43.3169 11.4622 31.66 11.4622C20.0031 11.4622 10.5533 20.912 10.5533 32.5689C10.5533 44.2257 20.0031 53.6755 31.66 53.6755Z"
          fill="#EAE9EE"
        />
        <path
          fill-rule="evenodd"
          clip-rule="evenodd"
          d="M26.8139 53.1162V36.5812H43.4882V50.06C40.1153 52.3437 36.0481 53.6776 31.6684 53.6776C29.9989 53.6776 28.3716 53.4835 26.8139 53.1162Z"
          fill="#FFCEB6"
        />
        <path
          d="M22.8036 12.3086C22.8036 10.9092 23.9371 9.77576 25.3364 9.77576H38.0004C39.3998 9.77576 40.5332 10.9092 40.5332 12.3086V38.0587C40.5332 39.4581 39.3998 40.5915 38.0004 40.5915H25.3364C23.9371 40.5915 22.8036 39.4581 22.8036 38.0587V12.3086Z"
          fill="#504678"
        />
        <path d="M40.5332 13.786H22.8036V36.5812H40.5332V13.786Z" fill="#5A78FF" />
        <path
          d="M26.6028 19.907C26.6028 18.9741 27.3585 18.2184 28.2914 18.2184H35.0455C35.9784 18.2184 36.734 18.9741 36.734 19.907V26.6611C36.734 27.594 35.9784 28.3496 35.0455 28.3496H28.2914C27.3585 28.3496 26.6028 27.594 26.6028 26.6611V19.907Z"
          fill="#F0F0ED"
        />
        <path
          fill-rule="evenodd"
          clip-rule="evenodd"
          d="M34.3701 20.6668C34.6487 20.8758 34.7057 21.2726 34.4967 21.5533L31.3307 25.7746C31.221 25.9224 31.0521 26.0131 30.8685 26.0258C30.6849 26.0385 30.5055 25.9709 30.3767 25.8422L28.6882 24.1536C28.4412 23.9067 28.4412 23.5057 28.6882 23.2587C28.9351 23.0118 29.3362 23.0118 29.5831 23.2587L30.7545 24.4301L33.4836 20.7934C33.6926 20.5127 34.0915 20.4579 34.3701 20.6668Z"
          fill="#504678"
        />
        <path
          fill-rule="evenodd"
          clip-rule="evenodd"
          d="M20.2708 24.9726C20.2708 23.6914 21.3114 22.6508 22.5926 22.6508H26.8139C28.0951 22.6508 29.1356 23.6914 29.1356 24.9726C29.1356 26.2537 28.0951 27.2943 26.8139 27.2943C28.0951 27.2943 29.1356 28.3348 29.1356 29.616C29.1356 30.8972 28.0951 31.9377 26.8139 31.9377C28.0951 31.9377 29.1356 32.9783 29.1356 34.2595C29.1356 35.5407 28.0951 36.5812 26.8139 36.5812H22.5926C21.3114 36.5812 20.2708 35.5407 20.2708 34.2595C20.2708 32.9783 21.3114 31.9377 22.5926 31.9377C21.3114 31.9377 20.2708 30.8972 20.2708 29.616C20.2708 28.3348 21.3114 27.2943 22.5926 27.2943C21.3114 27.2943 20.2708 26.2537 20.2708 24.9726ZM38.2115 34.2152C35.5711 35.642 33.7791 38.4344 33.7791 41.6468H42.2218V39.9963C42.9816 39.534 43.4882 38.6961 43.4882 37.7421V33.4152C43.4882 29.7427 40.5121 26.7666 36.8396 26.7666H35.4676C34.0113 26.7666 32.8293 27.9486 32.8293 29.4049C32.8293 30.8613 34.0113 32.0433 35.4676 32.0433H36.8396C37.5973 32.0433 38.2115 32.6575 38.2115 33.4152V34.2152Z"
          fill="#FFCEB6"
        />
      </svg>
    `,nn=e=>D`<span class="mobilepay-logo">${e(`MobilePay`)}</span>`;function q(e,t,n,r){var i=arguments.length,a=i<3?t:r===null?r=Object.getOwnPropertyDescriptor(t,n):r,o;if(typeof Reflect==`object`&&typeof Reflect.decorate==`function`)a=Reflect.decorate(e,t,n,r);else for(var s=e.length-1;s>=0;s--)(o=e[s])&&(a=(i<3?o(a):i>3?o(t,n,a):o(t,n))||a);return i>3&&a&&Object.defineProperty(t,n,a),a}function rn(e,t){if(t.has(e))throw TypeError(`Cannot initialize the same private elements twice on an object`)}function J(e,t,n){rn(e,t),t.set(e,n)}function Y(e){"@babel/helpers - typeof";return Y=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},Y(e)}function an(e,t){if(Y(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t||`default`);if(Y(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}function on(e){var t=an(e,`string`);return Y(t)==`symbol`?t:t+``}function sn(e,t,n){return(t=on(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function cn(e,t,n){if(typeof e==`function`?e===t:e.has(t))return arguments.length<3?t:n;throw TypeError(`Private element is not present on this object`)}function X(e,t){return e.get(cn(e,t))}function Z(e,t,n){return e.set(cn(e,t),n),n}var ln,un=new WeakMap,Q=new WeakMap,dn=new WeakMap,fn=new WeakMap,pn=new WeakMap,$=(ln=class extends F{get language(){return X(un,this)}set language(e){Z(un,this,e)}get brand(){return X(Q,this)}set brand(e){Z(Q,this,e)}get amount(){return X(dn,this)}set amount(e){Z(dn,this,e)}get isVippsSenere(){return X(fn,this)}set isVippsSenere(e){Z(fn,this,e)}get dialogElement(){return X(pn,this)}set dialogElement(e){Z(pn,this,e)}get brandLogo(){return this.brand===`vipps`?Xt:nn}get brandName(){return this.brand===`vipps`?`Vipps`:`MobilePay`}get fallbackUrl(){let e=`https://`;return this.language===`no`&&(e+=`vipps.no`),this.language===`da`&&(e+=`mobilepay.dk`),this.language===`fi`&&(e+=`mobilepay.fi`),this.language===`sv`&&(e+=`vipps.se`),this.language===`en`&&(e+=`vippsmobilepay.com`),e}constructor(){if(super(),J(this,un,`no`),J(this,Q,`vipps`),J(this,dn,0),J(this,fn,!1),J(this,pn,null),sn(this,`isVippsSenereEnabled`,!1),!document.head.querySelector(`style[data-vipps-osm-fonts]`)){let e=document.createElement(`style`);e.setAttribute(`data-vipps-osm-fonts`,``),e.innerHTML=jt.cssText,document.head.appendChild(e)}}willUpdate(e){e.has(`language`)&&Yt(this.language)&&Jt(this.language)}get type(){return!this.isVippsSenereEnabled||!this.isVippsSenere?v.Payment:this.amount>=3e5?v.SplitPayment:this.amount>=2e5?v.ZeroFees:v.PayLater}openDialog(){this.dialogElement?Mt(this.dialogElement)?this.dialogElement.showModal():Pt(this.dialogElement):window.open(this.fallbackUrl,`_blank`)}closeDialog(){var e;(e=this.dialogElement)==null||e.close()}render(){return D`
      <dialog class="dialog" role="dialog" ${it(Nt)}>
        <header>
          <h1 class="dialog-title">
            <span>${this.type===v.Payment?V(`Betal med`):V(`Få først`)}</span>
            ${this.brandLogo(V)}
            ${this.type===v.Payment?k:V(D`<span>senere</span>`)}
          </h1>
          <button
            class="close-button"
            type="button"
            aria-label="${V(`Lukk`)}"
            @click="${this.closeDialog}"
          >
            ${en}
          </button>
        </header>
        <section>
          <p>
            ${this.type===v.Payment?V(`Sånn betaler du:`):V(`Få varene før du betaler. Null gebyrer. Null renter. Null stress.`)}
          </p>
          <ul class="list">
            <li>
              ${Zt(this.brand)}
              <span>${V(B`Velg å betale med ${this.brandName}.`)}</span>
            </li>
            <li>
              ${tn(this.brand)}
              <span>
                ${I(this.type,[[v.Payment,()=>V(`Velg kortet du vil betale med og fullfør kjøpet`)],[v.PayLater,()=>V(`Velg å betale senere`)],[v.ZeroFees,()=>V(D`Trykk på <b class="bold">Betal senere</b> i ${this.brandName}, og velg
                          <b class="bold">Betal i tre deler</b>`)],[v.SplitPayment,()=>V(D`Trykk på <b class="bold">Betal senere</b> i ${this.brandName}, og velg
                          <b class="bold">Betal i tre deler</b>`)]])}
              </span>
            </li>
            ${this.type===v.Payment?k:D`<li>
                  ${$t}
                  <span>
                    ${I(this.type,[[v.PayLater,()=>V(`Betalingen trekkes automatisk 14 dager etter varene er sendt`)],[v.ZeroFees,()=>V(`Betalingen trekkes automatisk på dag 1, 30 og 60.`)],[v.SplitPayment,()=>V(`Betalingen trekkes automatisk på dag 1, 30 og 60.`)]])}
                  </span>
                </li>`}
          </ul>
          ${this.type===v.Payment?V(D`<p>Og husk: ${this.brandName} tar ingen gebyrer når du betaler på nett.</p>`):k}
        </section>
        <footer>
          <button class="primary-button" type="button" @click="${this.closeDialog}">
            ${V(`Lukk`)}
          </button>
        </footer>
      </dialog>

      <div class="message">
        <p class="title">
          <b
            >${I(this.type,[[v.Payment,()=>V(`Her kan du betale med`)],[v.PayLater,()=>V(`Betal om 14 dager med`)],[v.ZeroFees,()=>V(`Del betalingen i tre med`)],[v.SplitPayment,()=>V(`Del betalingen i tre med`)]])}</b
          >&nbsp;${this.brandLogo(V)}
        </p>
        <p class="description">
          <span>
            ${I(this.type,[[v.Payment,()=>V(B`${this.brandName} tar ingen gebyrer når du betaler på nett`)],[v.PayLater,()=>V(`Få varene før du betaler`)],[v.ZeroFees,()=>V(`Null gebyrer. Null renter.`)],[v.SplitPayment,()=>V(B`Tre rentefrie betalinger på ${Lt(this.amount,this.language)}`)]])}
          </span>
          <button class="read-more-button" type="button" @click="${this.openDialog}">
            ${V(`Les mer`)}${Qt}
          </button>
        </p>
      </div>
    `}},sn(ln,`styles`,At),ln);q([_()],$.prototype,`language`,null),q([_({type:String})],$.prototype,`brand`,null),q([_({converter:It})],$.prototype,`amount`,null),q([_({attribute:`vipps-senere`,type:Boolean})],$.prototype,`isVippsSenere`,null),q([he(`dialog`)],$.prototype,`dialogElement`,null),$=q([n(`vipps-badge`),ft()],$);var mn=class extends ${};mn=q([n(`vipps-mobilepay-badge`)],mn)})();