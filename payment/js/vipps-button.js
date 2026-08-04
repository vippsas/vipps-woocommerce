(function(){var e=Object.defineProperty,t=(t,n)=>{let r={};for(var i in t)e(r,i,{get:t[i],enumerable:!0});return n||e(r,Symbol.toStringTag,{value:`Module`}),r},n=globalThis,r=n.ShadowRoot&&(n.ShadyCSS===void 0||n.ShadyCSS.nativeShadow)&&`adoptedStyleSheets`in Document.prototype&&`replace`in CSSStyleSheet.prototype,i=Symbol(),a=new WeakMap,o=class{constructor(e,t,n){if(this._$cssResult$=!0,n!==i)throw Error("CSSResult is not constructable. Use `unsafeCSS` or `css` instead.");this.cssText=e,this.t=t}get styleSheet(){let e=this.o,t=this.t;if(r&&e===void 0){let n=t!==void 0&&t.length===1;n&&(e=a.get(t)),e===void 0&&((this.o=e=new CSSStyleSheet).replaceSync(this.cssText),n&&a.set(t,e))}return e}toString(){return this.cssText}},s=e=>new o(typeof e==`string`?e:e+``,void 0,i),c=(e,...t)=>new o(e.length===1?e[0]:t.reduce((t,n,r)=>t+(e=>{if(!0===e._$cssResult$)return e.cssText;if(typeof e==`number`)return e;throw Error(`Value passed to 'css' function must be a 'css' function result: `+e+`. Use 'unsafeCSS' to pass non-literal values, but take care to ensure page security.`)})(n)+e[r+1],e[0]),e,i),l=(e,t)=>{if(r)e.adoptedStyleSheets=t.map(e=>e instanceof CSSStyleSheet?e:e.styleSheet);else for(let r of t){let t=document.createElement(`style`),i=n.litNonce;i!==void 0&&t.setAttribute(`nonce`,i),t.textContent=r.cssText,e.appendChild(t)}},u=r?e=>e:e=>e instanceof CSSStyleSheet?(e=>{let t=``;for(let n of e.cssRules)t+=n.cssText;return s(t)})(e):e,d,f,{is:ee,defineProperty:te,getOwnPropertyDescriptor:ne,getOwnPropertyNames:re,getOwnPropertySymbols:ie,getPrototypeOf:ae}=Object,p=globalThis,oe=p.trustedTypes,se=oe?oe.emptyScript:``,ce=p.reactiveElementPolyfillSupport,m=(e,t)=>e,h={toAttribute(e,t){switch(t){case Boolean:e=e?se:null;break;case Object:case Array:e=e==null?e:JSON.stringify(e)}return e},fromAttribute(e,t){let n=e;switch(t){case Boolean:n=e!==null;break;case Number:n=e===null?null:Number(e);break;case Object:case Array:try{n=JSON.parse(e)}catch{n=null}}return n}},le=(e,t)=>!ee(e,t),ue={attribute:!0,type:String,converter:h,reflect:!1,useDefault:!1,hasChanged:le};(d=Symbol).metadata!=null||(d.metadata=Symbol(`metadata`)),p.litPropertyMetadata!=null||(p.litPropertyMetadata=new WeakMap);var g=class extends HTMLElement{static addInitializer(e){var t;this._$Ei(),((t=this.l)==null?this.l=[]:t).push(e)}static get observedAttributes(){return this.finalize(),this._$Eh&&[...this._$Eh.keys()]}static createProperty(e,t=ue){if(t.state&&(t.attribute=!1),this._$Ei(),this.prototype.hasOwnProperty(e)&&((t=Object.create(t)).wrapped=!0),this.elementProperties.set(e,t),!t.noAccessor){let n=Symbol(),r=this.getPropertyDescriptor(e,n,t);r!==void 0&&te(this.prototype,e,r)}}static getPropertyDescriptor(e,t,n){var r;let{get:i,set:a}=(r=ne(this.prototype,e))==null?{get(){return this[t]},set(e){this[t]=e}}:r;return{get:i,set(t){let r=i==null?void 0:i.call(this);a==null||a.call(this,t),this.requestUpdate(e,r,n)},configurable:!0,enumerable:!0}}static getPropertyOptions(e){var t;return(t=this.elementProperties.get(e))==null?ue:t}static _$Ei(){if(this.hasOwnProperty(m(`elementProperties`)))return;let e=ae(this);e.finalize(),e.l!==void 0&&(this.l=[...e.l]),this.elementProperties=new Map(e.elementProperties)}static finalize(){if(this.hasOwnProperty(m(`finalized`)))return;if(this.finalized=!0,this._$Ei(),this.hasOwnProperty(m(`properties`))){let e=this.properties,t=[...re(e),...ie(e)];for(let n of t)this.createProperty(n,e[n])}let e=this[Symbol.metadata];if(e!==null){let t=litPropertyMetadata.get(e);if(t!==void 0)for(let[e,n]of t)this.elementProperties.set(e,n)}this._$Eh=new Map;for(let[e,t]of this.elementProperties){let n=this._$Eu(e,t);n!==void 0&&this._$Eh.set(n,e)}this.elementStyles=this.finalizeStyles(this.styles)}static finalizeStyles(e){let t=[];if(Array.isArray(e)){let n=new Set(e.flat(1/0).reverse());for(let e of n)t.unshift(u(e))}else e!==void 0&&t.push(u(e));return t}static _$Eu(e,t){let n=t.attribute;return!1===n?void 0:typeof n==`string`?n:typeof e==`string`?e.toLowerCase():void 0}constructor(){super(),this._$Ep=void 0,this.isUpdatePending=!1,this.hasUpdated=!1,this._$Em=null,this._$Ev()}_$Ev(){var e;this._$ES=new Promise(e=>this.enableUpdating=e),this._$AL=new Map,this._$E_(),this.requestUpdate(),(e=this.constructor.l)==null||e.forEach(e=>e(this))}addController(e){var t,n;((t=this._$EO)==null?this._$EO=new Set:t).add(e),this.renderRoot!==void 0&&this.isConnected&&((n=e.hostConnected)==null||n.call(e))}removeController(e){var t;(t=this._$EO)==null||t.delete(e)}_$E_(){let e=new Map,t=this.constructor.elementProperties;for(let n of t.keys())this.hasOwnProperty(n)&&(e.set(n,this[n]),delete this[n]);e.size>0&&(this._$Ep=e)}createRenderRoot(){var e;let t=(e=this.shadowRoot)==null?this.attachShadow(this.constructor.shadowRootOptions):e;return l(t,this.constructor.elementStyles),t}connectedCallback(){var e;this.renderRoot!=null||(this.renderRoot=this.createRenderRoot()),this.enableUpdating(!0),(e=this._$EO)==null||e.forEach(e=>{var t;return(t=e.hostConnected)==null?void 0:t.call(e)})}enableUpdating(e){}disconnectedCallback(){var e;(e=this._$EO)==null||e.forEach(e=>{var t;return(t=e.hostDisconnected)==null?void 0:t.call(e)})}attributeChangedCallback(e,t,n){this._$AK(e,n)}_$ET(e,t){let n=this.constructor.elementProperties.get(e),r=this.constructor._$Eu(e,n);if(r!==void 0&&!0===n.reflect){var i;let a=(((i=n.converter)==null?void 0:i.toAttribute)===void 0?h:n.converter).toAttribute(t,n.type);this._$Em=e,a==null?this.removeAttribute(r):this.setAttribute(r,a),this._$Em=null}}_$AK(e,t){let n=this.constructor,r=n._$Eh.get(e);if(r!==void 0&&this._$Em!==r){var i,a,o;let e=n.getPropertyOptions(r),s=typeof e.converter==`function`?{fromAttribute:e.converter}:((i=e.converter)==null?void 0:i.fromAttribute)===void 0?h:e.converter;this._$Em=r;let c=s.fromAttribute(t,e.type);this[r]=(a=c==null?(o=this._$Ej)==null?void 0:o.get(r):c)==null?c:a,this._$Em=null}}requestUpdate(e,t,n,r=!1,i){if(e!==void 0){var a,o;let s=this.constructor;if(!1===r&&(i=this[e]),n!=null||(n=s.getPropertyOptions(e)),!(((a=n.hasChanged)==null?le:a)(i,t)||n.useDefault&&n.reflect&&i===((o=this._$Ej)==null?void 0:o.get(e))&&!this.hasAttribute(s._$Eu(e,n))))return;this.C(e,t,n)}!1===this.isUpdatePending&&(this._$ES=this._$EP())}C(e,t,{useDefault:n,reflect:r,wrapped:i},a){var o,s,c;n&&!((o=this._$Ej)==null?this._$Ej=new Map:o).has(e)&&(this._$Ej.set(e,(s=a==null?t:a)==null?this[e]:s),!0!==i||a!==void 0)||(this._$AL.has(e)||(this.hasUpdated||n||(t=void 0),this._$AL.set(e,t)),!0===r&&this._$Em!==e&&((c=this._$Eq)==null?this._$Eq=new Set:c).add(e))}async _$EP(){this.isUpdatePending=!0;try{await this._$ES}catch(e){Promise.reject(e)}let e=this.scheduleUpdate();return e!=null&&await e,!this.isUpdatePending}scheduleUpdate(){return this.performUpdate()}performUpdate(){if(!this.isUpdatePending)return;if(!this.hasUpdated){if(this.renderRoot!=null||(this.renderRoot=this.createRenderRoot()),this._$Ep){for(let[e,t]of this._$Ep)this[e]=t;this._$Ep=void 0}let e=this.constructor.elementProperties;if(e.size>0)for(let[t,n]of e){let{wrapped:e}=n,r=this[t];!0!==e||this._$AL.has(t)||r===void 0||this.C(t,void 0,n,r)}}let e=!1,t=this._$AL;try{var n;e=this.shouldUpdate(t),e?(this.willUpdate(t),(n=this._$EO)==null||n.forEach(e=>{var t;return(t=e.hostUpdate)==null?void 0:t.call(e)}),this.update(t)):this._$EM()}catch(t){throw e=!1,this._$EM(),t}e&&this._$AE(t)}willUpdate(e){}_$AE(e){var t;(t=this._$EO)==null||t.forEach(e=>{var t;return(t=e.hostUpdated)==null?void 0:t.call(e)}),this.hasUpdated||(this.hasUpdated=!0,this.firstUpdated(e)),this.updated(e)}_$EM(){this._$AL=new Map,this.isUpdatePending=!1}get updateComplete(){return this.getUpdateComplete()}getUpdateComplete(){return this._$ES}shouldUpdate(e){return!0}update(e){this._$Eq&&(this._$Eq=this._$Eq.forEach(e=>this._$ET(e,this[e]))),this._$EM()}updated(e){}firstUpdated(e){}};g.elementStyles=[],g.shadowRootOptions={mode:`open`},g[m(`elementProperties`)]=new Map,g[m(`finalized`)]=new Map,ce==null||ce({ReactiveElement:g}),((f=p.reactiveElementVersions)==null?p.reactiveElementVersions=[]:f).push(`2.1.2`);var de,_=globalThis,fe=e=>e,v=_.trustedTypes,pe=v?v.createPolicy(`lit-html`,{createHTML:e=>e}):void 0,me=`$lit$`,y=`lit$${Math.random().toFixed(9).slice(2)}$`,he=`?`+y,ge=`<${he}>`,b=document,x=()=>b.createComment(``),S=e=>e===null||typeof e!=`object`&&typeof e!=`function`,_e=Array.isArray,ve=e=>_e(e)||typeof(e==null?void 0:e[Symbol.iterator])==`function`,ye=`[ 	
\f\r]`,C=/<(?:(!--|\/[^a-zA-Z])|(\/?[a-zA-Z][^>\s]*)|(\/?$))/g,be=/-->/g,xe=/>/g,w=RegExp(`>|${ye}(?:([^\\s"'>=/]+)(${ye}*=${ye}*(?:[^ \t\n\f\r"'\`<>=]|("|')|))|$)`,`g`),Se=/'/g,Ce=/"/g,we=/^(?:script|style|textarea|title)$/i,T=(e=>(t,...n)=>({_$litType$:e,strings:t,values:n}))(1),E=Symbol.for(`lit-noChange`),D=Symbol.for(`lit-nothing`),Te=new WeakMap,O=b.createTreeWalker(b,129);function Ee(e,t){if(!_e(e)||!e.hasOwnProperty(`raw`))throw Error(`invalid template strings array`);return pe===void 0?t:pe.createHTML(t)}var De=(e,t)=>{let n=e.length-1,r=[],i,a=t===2?`<svg>`:t===3?`<math>`:``,o=C;for(let t=0;t<n;t++){var s;let n=e[t],c,l,u=-1,d=0;for(;d<n.length&&(o.lastIndex=d,l=o.exec(n),l!==null);)d=o.lastIndex,o===C?l[1]===`!--`?o=be:l[1]===void 0?l[2]===void 0?l[3]!==void 0&&(o=w):(we.test(l[2])&&(i=RegExp(`</`+l[2],`g`)),o=w):o=xe:o===w?l[0]===`>`?(o=(s=i)==null?C:s,u=-1):l[1]===void 0?u=-2:(u=o.lastIndex-l[2].length,c=l[1],o=l[3]===void 0?w:l[3]===`"`?Ce:Se):o===Ce||o===Se?o=w:o===be||o===xe?o=C:(o=w,i=void 0);let f=o===w&&e[t+1].startsWith(`/>`)?` `:``;a+=o===C?n+ge:u>=0?(r.push(c),n.slice(0,u)+me+n.slice(u)+y+f):n+y+(u===-2?t:f)}return[Ee(e,a+(e[n]||`<?>`)+(t===2?`</svg>`:t===3?`</math>`:``)),r]},Oe=class e{constructor({strings:t,_$litType$:n},r){let i;this.parts=[];let a=0,o=0,s=t.length-1,c=this.parts,[l,u]=De(t,n);if(this.el=e.createElement(l,r),O.currentNode=this.el.content,n===2||n===3){let e=this.el.content.firstChild;e.replaceWith(...e.childNodes)}for(;(i=O.nextNode())!==null&&c.length<s;){if(i.nodeType===1){if(i.hasAttributes())for(let e of i.getAttributeNames())if(e.endsWith(me)){let t=u[o++],n=i.getAttribute(e).split(y),r=/([.?@])?(.*)/.exec(t);c.push({type:1,index:a,name:r[2],strings:n,ctor:r[1]===`.`?je:r[1]===`?`?Me:r[1]===`@`?Ne:A}),i.removeAttribute(e)}else e.startsWith(y)&&(c.push({type:6,index:a}),i.removeAttribute(e));if(we.test(i.tagName)){let e=i.textContent.split(y),t=e.length-1;if(t>0){i.textContent=v?v.emptyScript:``;for(let n=0;n<t;n++)i.append(e[n],x()),O.nextNode(),c.push({type:2,index:++a});i.append(e[t],x())}}}else if(i.nodeType===8)if(i.data===he)c.push({type:2,index:a});else{let e=-1;for(;(e=i.data.indexOf(y,e+1))!==-1;)c.push({type:7,index:a}),e+=y.length-1}a++}}static createElement(e,t){let n=b.createElement(`template`);return n.innerHTML=e,n}};function k(e,t,n=e,r){var i,a,o;if(t===E)return t;let s=r===void 0?n._$Cl:(i=n._$Co)==null?void 0:i[r],c=S(t)?void 0:t._$litDirective$;return(s==null?void 0:s.constructor)!==c&&(s==null||(a=s._$AO)==null||a.call(s,!1),c===void 0?s=void 0:(s=new c(e),s._$AT(e,n,r)),r===void 0?n._$Cl=s:((o=n._$Co)==null?n._$Co=[]:o)[r]=s),s!==void 0&&(t=k(e,s._$AS(e,t.values),s,r)),t}var ke=class{constructor(e,t){this._$AV=[],this._$AN=void 0,this._$AD=e,this._$AM=t}get parentNode(){return this._$AM.parentNode}get _$AU(){return this._$AM._$AU}u(e){var t;let{el:{content:n},parts:r}=this._$AD,i=((t=e==null?void 0:e.creationScope)==null?b:t).importNode(n,!0);O.currentNode=i;let a=O.nextNode(),o=0,s=0,c=r[0];for(;c!==void 0;){if(o===c.index){let t;c.type===2?t=new Ae(a,a.nextSibling,this,e):c.type===1?t=new c.ctor(a,c.name,c.strings,this,e):c.type===6&&(t=new Pe(a,this,e)),this._$AV.push(t),c=r[++s]}o!==(c==null?void 0:c.index)&&(a=O.nextNode(),o++)}return O.currentNode=b,i}p(e){let t=0;for(let n of this._$AV)n!==void 0&&(n.strings===void 0?n._$AI(e[t]):(n._$AI(e,n,t),t+=n.strings.length-2)),t++}},Ae=class e{get _$AU(){var e,t;return(e=(t=this._$AM)==null?void 0:t._$AU)==null?this._$Cv:e}constructor(e,t,n,r){var i;this.type=2,this._$AH=D,this._$AN=void 0,this._$AA=e,this._$AB=t,this._$AM=n,this.options=r,this._$Cv=(i=r==null?void 0:r.isConnected)==null||i}get parentNode(){let e=this._$AA.parentNode,t=this._$AM;return t!==void 0&&(e==null?void 0:e.nodeType)===11&&(e=t.parentNode),e}get startNode(){return this._$AA}get endNode(){return this._$AB}_$AI(e,t=this){e=k(this,e,t),S(e)?e===D||e==null||e===``?(this._$AH!==D&&this._$AR(),this._$AH=D):e!==this._$AH&&e!==E&&this._(e):e._$litType$===void 0?e.nodeType===void 0?ve(e)?this.k(e):this._(e):this.T(e):this.$(e)}O(e){return this._$AA.parentNode.insertBefore(e,this._$AB)}T(e){this._$AH!==e&&(this._$AR(),this._$AH=this.O(e))}_(e){this._$AH!==D&&S(this._$AH)?this._$AA.nextSibling.data=e:this.T(b.createTextNode(e)),this._$AH=e}$(e){var t;let{values:n,_$litType$:r}=e,i=typeof r==`number`?this._$AC(e):(r.el===void 0&&(r.el=Oe.createElement(Ee(r.h,r.h[0]),this.options)),r);if(((t=this._$AH)==null?void 0:t._$AD)===i)this._$AH.p(n);else{let e=new ke(i,this),t=e.u(this.options);e.p(n),this.T(t),this._$AH=e}}_$AC(e){let t=Te.get(e.strings);return t===void 0&&Te.set(e.strings,t=new Oe(e)),t}k(t){_e(this._$AH)||(this._$AH=[],this._$AR());let n=this._$AH,r,i=0;for(let a of t)i===n.length?n.push(r=new e(this.O(x()),this.O(x()),this,this.options)):r=n[i],r._$AI(a),i++;i<n.length&&(this._$AR(r&&r._$AB.nextSibling,i),n.length=i)}_$AR(e=this._$AA.nextSibling,t){var n;for((n=this._$AP)==null||n.call(this,!1,!0,t);e!==this._$AB;){let t=fe(e).nextSibling;fe(e).remove(),e=t}}setConnected(e){var t;this._$AM===void 0&&(this._$Cv=e,(t=this._$AP)==null||t.call(this,e))}},A=class{get tagName(){return this.element.tagName}get _$AU(){return this._$AM._$AU}constructor(e,t,n,r,i){this.type=1,this._$AH=D,this._$AN=void 0,this.element=e,this.name=t,this._$AM=r,this.options=i,n.length>2||n[0]!==``||n[1]!==``?(this._$AH=Array(n.length-1).fill(new String),this.strings=n):this._$AH=D}_$AI(e,t=this,n,r){let i=this.strings,a=!1;if(i===void 0)e=k(this,e,t,0),a=!S(e)||e!==this._$AH&&e!==E,a&&(this._$AH=e);else{var o;let r=e,s,c;for(e=i[0],s=0;s<i.length-1;s++)c=k(this,r[n+s],t,s),c===E&&(c=this._$AH[s]),a||(a=!S(c)||c!==this._$AH[s]),c===D?e=D:e!==D&&(e+=((o=c)==null?``:o)+i[s+1]),this._$AH[s]=c}a&&!r&&this.j(e)}j(e){e===D?this.element.removeAttribute(this.name):this.element.setAttribute(this.name,e==null?``:e)}},je=class extends A{constructor(){super(...arguments),this.type=3}j(e){this.element[this.name]=e===D?void 0:e}},Me=class extends A{constructor(){super(...arguments),this.type=4}j(e){this.element.toggleAttribute(this.name,!!e&&e!==D)}},Ne=class extends A{constructor(e,t,n,r,i){super(e,t,n,r,i),this.type=5}_$AI(e,t=this){var n;if((e=(n=k(this,e,t,0))==null?D:n)===E)return;let r=this._$AH,i=e===D&&r!==D||e.capture!==r.capture||e.once!==r.once||e.passive!==r.passive,a=e!==D&&(r===D||i);i&&this.element.removeEventListener(this.name,this,r),a&&this.element.addEventListener(this.name,this,e),this._$AH=e}handleEvent(e){var t,n;typeof this._$AH==`function`?this._$AH.call((t=(n=this.options)==null?void 0:n.host)==null?this.element:t,e):this._$AH.handleEvent(e)}},Pe=class{constructor(e,t,n){this.element=e,this.type=6,this._$AN=void 0,this._$AM=t,this.options=n}get _$AU(){return this._$AM._$AU}_$AI(e){k(this,e)}},Fe=_.litHtmlPolyfillSupport;Fe==null||Fe(Oe,Ae),((de=_.litHtmlVersions)==null?_.litHtmlVersions=[]:de).push(`3.3.2`);var Ie=(e,t,n)=>{var r;let i=(r=n==null?void 0:n.renderBefore)==null?t:r,a=i._$litPart$;if(a===void 0){var o;let e=(o=n==null?void 0:n.renderBefore)==null?null:o;i._$litPart$=a=new Ae(t.insertBefore(x(),e),e,void 0,n==null?{}:n)}return a._$AI(e),a},Le,Re,j=globalThis,M=class extends g{constructor(){super(...arguments),this.renderOptions={host:this},this._$Do=void 0}createRenderRoot(){var e;let t=super.createRenderRoot();return(e=this.renderOptions).renderBefore!=null||(e.renderBefore=t.firstChild),t}update(e){let t=this.render();this.hasUpdated||(this.renderOptions.isConnected=this.isConnected),super.update(e),this._$Do=Ie(t,this.renderRoot,this.renderOptions)}connectedCallback(){var e;super.connectedCallback(),(e=this._$Do)==null||e.setConnected(!0)}disconnectedCallback(){var e;super.disconnectedCallback(),(e=this._$Do)==null||e.setConnected(!1)}render(){return E}};M._$litElement$=!0,M.finalized=!0,(Le=j.litElementHydrateSupport)==null||Le.call(j,{LitElement:M});var ze=j.litElementPolyfillSupport;ze==null||ze({LitElement:M}),((Re=j.litElementVersions)==null?j.litElementVersions=[]:Re).push(`4.2.2`);var Be=e=>(t,n)=>{n===void 0?customElements.define(e,t):n.addInitializer(()=>{customElements.define(e,t)})},Ve={attribute:!0,type:String,converter:h,reflect:!1,hasChanged:le},He=(e=Ve,t,n)=>{let{kind:r,metadata:i}=n,a=globalThis.litPropertyMetadata.get(i);if(a===void 0&&globalThis.litPropertyMetadata.set(i,a=new Map),r===`setter`&&((e=Object.create(e)).wrapped=!0),a.set(n.name,e),r===`accessor`){let{name:r}=n;return{set(n){let i=t.get.call(this);t.set.call(this,n),this.requestUpdate(r,i,e,!0,n)},init(t){return t!==void 0&&this.C(r,void 0,e,t),t}}}if(r===`setter`){let{name:r}=n;return function(n){let i=this[r];t.call(this,n),this.requestUpdate(r,i,e,!0,n)}}throw Error(`Unsupported decorator location: `+r)};function N(e){return(t,n)=>typeof n==`object`?He(e,t,n):((e,t,n)=>{let r=t.hasOwnProperty(n);return t.constructor.createProperty(n,e),r?Object.getOwnPropertyDescriptor(t,n):void 0})(e,t,n)}function P(e){return N({...e,state:!0,attribute:!1})}var Ue=`lit-localize-status`,F=(e,...t)=>({strTag:!0,strings:e,values:t}),We=e=>typeof e!=`string`&&`strTag`in e,Ge=(e,t,n)=>{let r=e[0];for(let i=1;i<e.length;i++)r+=t[n?n[i-1]:i-1],r+=e[i];return r},Ke=(e=>We(e)?Ge(e.strings,e.values):e),I=Ke,qe=!1;function Je(e){if(qe)throw Error(`lit-localize can only be configured once`);I=e,qe=!0}var Ye=class{constructor(e){this.__litLocalizeEventHandler=e=>{e.detail.status===`ready`&&this.host.requestUpdate()},this.host=e}hostConnected(){window.addEventListener(Ue,this.__litLocalizeEventHandler)}hostDisconnected(){window.removeEventListener(Ue,this.__litLocalizeEventHandler)}},Xe=e=>e.addController(new Ye(e)),Ze=()=>(e,t)=>(e.addInitializer(Xe),e),Qe=class{constructor(){this.settled=!1,this.promise=new Promise((e,t)=>{this._resolve=e,this._reject=t})}resolve(e){this.settled=!0,this._resolve(e)}reject(e){this.settled=!0,this._reject(e)}},L=[];for(let e=0;e<256;e++)L[e]=(e>>4&15).toString(16)+(e&15).toString(16);function $e(e){let t=0,n=8997,r=0,i=33826,a=0,o=40164,s=0,c=52210;for(let l=0;l<e.length;l++)n^=e.charCodeAt(l),t=n*435,r=i*435,a=o*435,s=c*435,a+=n<<8,s+=i<<8,r+=t>>>16,n=t&65535,a+=r>>>16,i=r&65535,c=s+(a>>>16)&65535,o=a&65535;return L[c>>8]+L[c&255]+L[o>>8]+L[o&255]+L[i>>8]+L[i&255]+L[n>>8]+L[n&255]}var et=`h`,tt=`s`;function nt(e,t){return(t?et:tt)+$e(typeof e==`string`?e:e.join(``))}var rt=new WeakMap,it=new Map;function at(e,t,n){if(e){var r;let i=e[(r=n==null?void 0:n.id)==null?ot(t):r];if(i){if(typeof i==`string`)return i;if(`strTag`in i)return Ge(i.strings,t.values,i.values);{let e=rt.get(i);return e===void 0&&(e=i.values,rt.set(i,e)),{...i,values:e.map(e=>t.values[e])}}}}return Ke(t)}function ot(e){let t=typeof e==`string`?e:e.strings,n=it.get(t);return n===void 0&&(n=nt(t,typeof e!=`string`&&!(`strTag`in e)),it.set(t,n)),n}function st(e){window.dispatchEvent(new CustomEvent(Ue,{detail:e}))}var R=``,ct,lt,z,ut,dt,B=new Qe;B.resolve();var V=0,ft=e=>(Je(((e,t)=>at(dt,e,t))),R=lt=e.sourceLocale,z=new Set(e.targetLocales),z.add(e.sourceLocale),ut=e.loadLocale,{getLocale:pt,setLocale:mt}),pt=()=>R,mt=e=>{var t;if(e===((t=ct)==null?R:t))return B.promise;if(!z||!ut)throw Error(`Internal error`);if(!z.has(e))throw Error(`Invalid locale code`);V++;let n=V;return ct=e,B.settled&&(B=new Qe),st({status:`loading`,loadingLocale:e}),(e===lt?Promise.resolve({templates:void 0}):ut(e)).then(t=>{V===n&&(R=e,ct=void 0,dt=t.templates,st({status:`ready`,readyLocale:e}),B.resolve())},t=>{V===n&&(st({status:`error`,errorLocale:e,errorMessage:t.toString()}),B.reject(t))}),B.promise},ht=`no`,gt=[`da`,`en`,`fi`,`sv`],_t=[`da`,`en`,`fi`,`no`,`sv`],vt=t({templates:()=>yt}),yt={h016c5411387adb7b:T`${0} <span>Confirm</span>`,h0b45fb4dd22412db:T`Continue as ${0}`,h182158bd2ab6fee2:T`${0}
                <span>Continue as ${1}</span>`,h1fac0cb9c13eef5e:T`${0} <span>Buy now</span>`,h27fa5bc1830c24fe:T`${0} <span>Log in</span>`,h35f5881498b838de:T`<span>Buy now with </span>${0}`,h3cd66d895363ecf8:T`${0} <span>Continue as ${1}</span>`,h5b8dfcf8b674f52a:T`<span>Donate with </span>${0}`,h776546c02f5d686e:T`${0} <span>Donate</span>`,h7ddf1f684b62d846:T`Confirm`,h845f0251306f0133:T`<span>Confirm with </span>${0}`,h8a809d54ecbeeb11:T`<span>Register with </span>${0}`,h915a470a464b727a:T`<span>Pay with </span>${0}`,ha5401929e733e3f5:T`Donate`,ha909607a61f6aad1:T`${0} <span>Register</span>`,hc028d0ea5b6ec27a:T`<span>Log in with </span>${0}`,hc1e276dd54f4ca70:T`Continue`,hc920712031fd1d41:T`${0} <span>Continue</span>`,hcf842888f1c5bcf4:T`${0} <span>Express</span>`,hf0e9543eeebb73a9:T`<span>Continue with </span>${0}`,hf17cd8a7564d05e2:T`${0} <span>Pay</span>`,s1792f48147e210a5:`Buy now`,s21aa0c8945fd66cf:`MobilePay`,s485ead2f3d011f25:`Log in`,s6d348e4eb36cd5ed:F`Buy now with ${0} Express`,s88573d262f479e00:`Register`,scacb8dc5183f0b51:`Pay`,sfa4fda26baa247af:`Express`},bt=t({templates:()=>xt}),xt={h016c5411387adb7b:T`${0} <span>Vahvista</span>`,h0b45fb4dd22412db:T`Jatka nimellä ${0}`,h182158bd2ab6fee2:T`${0}
                <span>Jatka nimellä ${1}</span>`,h1fac0cb9c13eef5e:T`${0} <span>Osta nyt</span>`,h27fa5bc1830c24fe:T`${0} <span>Kirjaudu sisään</span>`,h35f5881498b838de:T`<span>Osta nyt </span>${0}`,h3cd66d895363ecf8:T`${0} <span>Jatka nimellä ${1}</span>`,h5b8dfcf8b674f52a:T`<span>Lahjoita </span>${0}`,h776546c02f5d686e:T`${0} <span>Lahjoita</span>`,h7ddf1f684b62d846:T`Vahvista`,h845f0251306f0133:T`<span>Vahvista </span>${0}`,h8a809d54ecbeeb11:T`<span>Rekisteröidy </span>${0}`,h915a470a464b727a:T`<span>Maksa </span>${0}`,ha5401929e733e3f5:T`Lahjoita`,ha909607a61f6aad1:T`${0} <span>Rekisteröidy</span>`,hc028d0ea5b6ec27a:T`<span>Kirjaudu sisään </span>${0}`,hc1e276dd54f4ca70:T`Jatka`,hc920712031fd1d41:T`${0} <span>Jatka</span>`,hcf842888f1c5bcf4:T`${0} <span>Express</span>`,hf0e9543eeebb73a9:T`<span>Jatka </span>${0}`,hf17cd8a7564d05e2:T`${0} <span>Maksa</span>`,s1792f48147e210a5:`Osta nyt`,s21aa0c8945fd66cf:`MobilePaylla`,s485ead2f3d011f25:`Kirjaudu sisään`,s6d348e4eb36cd5ed:F`Osta nyt ${0} Express`,s88573d262f479e00:`Rekisteröidy`,scacb8dc5183f0b51:`Maksa`,sfa4fda26baa247af:`Express`},St=t({templates:()=>Ct}),Ct={h016c5411387adb7b:T`${0} <span>Bekræft</span>`,h0b45fb4dd22412db:T`Fortsæt som ${0}`,h182158bd2ab6fee2:T`${0}
                <span>Fortsæt som ${1}</span>`,h1fac0cb9c13eef5e:T`${0} <span>Køb nu</span>`,h27fa5bc1830c24fe:T`${0} <span>Log ind</span>`,h35f5881498b838de:T`<span>Køb nu med </span>${0}`,h3cd66d895363ecf8:T`${0} <span>Fortsæt som ${1}</span>`,h5b8dfcf8b674f52a:T`<span>Doner med </span>${0}`,h776546c02f5d686e:T`${0} <span>Doner</span>`,h7ddf1f684b62d846:T`Bekræft`,h845f0251306f0133:T`<span>Bekræft med </span>${0}`,h8a809d54ecbeeb11:T`<span>Registrer med </span>${0}`,h915a470a464b727a:T`<span>Betal med </span>${0}`,ha5401929e733e3f5:T`Doner`,ha909607a61f6aad1:T`${0} <span>Registrer</span>`,hc028d0ea5b6ec27a:T`<span>Log ind med </span>${0}`,hc1e276dd54f4ca70:T`Fortsæt`,hc920712031fd1d41:T`${0} <span>Fortsæt</span>`,hcf842888f1c5bcf4:T`${0} <span>Express</span>`,hf0e9543eeebb73a9:T`<span>Fortsæt med </span>${0}`,hf17cd8a7564d05e2:T`${0} <span>Betal</span>`,s1792f48147e210a5:`Køb nu`,s21aa0c8945fd66cf:`MobilePay`,s485ead2f3d011f25:`Log ind`,s6d348e4eb36cd5ed:F`Køb nu med ${0} Express`,s88573d262f479e00:`Registrer`,scacb8dc5183f0b51:`Betal`,sfa4fda26baa247af:`Express`},wt=t({templates:()=>Tt}),Tt={h016c5411387adb7b:T`${0} <span>Bekräfta</span>`,h0b45fb4dd22412db:T`Fortsätt som ${0}`,h182158bd2ab6fee2:T`${0}
                <span>Fortsätt som ${1}</span>`,h1fac0cb9c13eef5e:T`${0} <span>Köp nu</span>`,h27fa5bc1830c24fe:T`${0} <span>Logga in</span>`,h35f5881498b838de:T`<span>Köp nu med </span>${0}`,h3cd66d895363ecf8:T`${0} <span>Fortsätt som ${1}</span>`,h5b8dfcf8b674f52a:T`<span>Bidra med </span>${0}`,h776546c02f5d686e:T`${0} <span>Bidra</span>`,h7ddf1f684b62d846:T`Bekräfta`,h845f0251306f0133:T`<span>Bekräfta med </span>${0}`,h8a809d54ecbeeb11:T`<span>Registrera med </span>${0}`,h915a470a464b727a:T`<span>Betala med </span>${0}`,ha5401929e733e3f5:T`Bidra`,ha909607a61f6aad1:T`${0} <span>Registrera</span>`,hc028d0ea5b6ec27a:T`<span>Logga in med </span>${0}`,hc1e276dd54f4ca70:T`Fortsätt`,hc920712031fd1d41:T`${0} <span>Fortsätt</span>`,hcf842888f1c5bcf4:T`${0} <span>Express</span>`,hf0e9543eeebb73a9:T`<span>Fortsätt med </span>${0}`,hf17cd8a7564d05e2:T`${0} <span>Betala</span>`,s1792f48147e210a5:`Köp nu`,s21aa0c8945fd66cf:`MobilePay`,s485ead2f3d011f25:`Logga in`,s6d348e4eb36cd5ed:F`Köp nu med ${0} Express`,s88573d262f479e00:`Registrera`,scacb8dc5183f0b51:`Betala`,sfa4fda26baa247af:`Express`},Et={en:vt,da:St,fi:bt,sv:wt},{setLocale:Dt}=ft({sourceLocale:ht,targetLocales:gt,loadLocale:e=>Promise.resolve(Et[e])}),Ot=e=>_t.includes(e),kt=()=>T`<svg
    aria-label="Vipps"
    class="vipps-logo"
    role="img"
    width="64"
    height="18"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
  >
    <path
      fill-rule="evenodd"
      clip-rule="evenodd"
      d="M64 5.38c-.72-2.75-2.47-3.84-4.86-3.84-1.93 0-4.36 1.1-4.36 3.72 0 1.7 1.18 3.03 3.09 3.37l1.81.32c1.23.23 1.58.7 1.58 1.32 0 .7-.76 1.1-1.89 1.1-1.48 0-2.4-.52-2.55-2l-2.61.41c.4 2.85 2.96 4.02 5.26 4.02 2.18 0 4.5-1.25 4.5-3.78 0-1.71-1.04-2.96-3-3.33l-1.99-.36c-1.11-.2-1.48-.75-1.48-1.27 0-.67.72-1.1 1.7-1.1 1.26 0 2.15.43 2.19 1.82l2.61-.4ZM5.92 9.7l2.72-7.86h3.19L7.1 13.5H4.73L0 1.84h3.19L5.92 9.7Zm16.69-4.52c0 .93-.74 1.57-1.6 1.57-.87 0-1.61-.64-1.61-1.57S20.14 3.6 21 3.6c.87 0 1.6.65 1.6 1.58Zm.5 4.12c-1.08 1.37-2.2 2.32-4.2 2.32-2.04 0-3.63-1.21-4.86-2.99-.5-.73-1.25-.89-1.81-.5-.51.36-.64 1.13-.16 1.8 1.7 2.56 4.07 4.05 6.83 4.05 2.53 0 4.5-1.2 6.04-3.23.58-.75.56-1.51 0-1.94-.51-.4-1.27-.26-1.85.49Zm7.09-1.66c0 2.38 1.4 3.64 2.96 3.64 1.48 0 3-1.17 3-3.64 0-2.42-1.52-3.6-2.98-3.6-1.58 0-2.98 1.12-2.98 3.6Zm0-4.18v-1.6h-2.9v15.68h2.9v-5.58a4.33 4.33 0 0 0 3.64 1.84c2.65 0 5.25-2.06 5.25-6.3 0-4.06-2.7-5.96-5-5.96-1.83 0-3.09.83-3.89 1.92Zm13.93 4.18c0 2.38 1.4 3.64 2.96 3.64 1.48 0 3-1.17 3-3.64 0-2.42-1.52-3.6-2.98-3.6-1.58 0-2.98 1.12-2.98 3.6Zm0-4.18v-1.6h-2.9v15.68h2.9v-5.58a4.33 4.33 0 0 0 3.64 1.84c2.65 0 5.24-2.06 5.24-6.3 0-4.06-2.7-5.96-5-5.96-1.83 0-3.08.83-3.88 1.92Z"
      fill="currentColor"
    />
  </svg> `,At=e=>T`<span class="mobilepay-logo">${e(`MobilePay`)}</span>`,jt=T`<svg
  width="16"
  height="14"
  viewBox="0 0 16 14"
  fill="none"
  xmlns="http://www.w3.org/2000/svg"
>
  <path
    fill-rule="evenodd"
    clip-rule="evenodd"
    d="M10.8534 4.59892C11.8702 4.59892 12.7416 3.83825 12.7416 2.74489H12.7419C12.7419 1.65128 11.8702 0.890869 10.8534 0.890869C9.8368 0.890869 8.96564 1.65128 8.96564 2.74489C8.96564 3.83825 9.8368 4.59892 10.8534 4.59892ZM13.3225 7.59445C12.0635 9.21049 10.7323 10.3278 8.38428 10.3279C5.98851 10.3279 4.12419 8.90154 2.6719 6.80984C2.09078 5.9539 1.19517 5.76386 0.541469 6.21552C-0.0635844 6.64349 -0.208475 7.54682 0.347935 8.33143C2.35689 11.3504 5.1405 13.1091 8.38402 13.1091C11.3617 13.1091 13.6856 11.6831 15.5008 9.30582C16.1784 8.42645 16.1542 7.52313 15.5008 7.02383C14.8955 6.54796 13.9999 6.71508 13.3225 7.59445Z"
    fill="currentColor"
  />
</svg>`,Mt=T`
  <svg width="15" height="16" viewBox="0 0 15 16" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path
      id="Logo"
      fill-rule="evenodd"
      clip-rule="evenodd"
      d="M7.72036 1.44983L8.35393 2.98698C8.35393 2.98698 8.11306 3.08681 7.95378 3.15064C7.7945 3.21447 7.49814 3.35908 7.49814 3.35908L6.85775 1.80537C6.75897 1.56571 6.48579 1.45101 6.24758 1.54919L1.87412 3.3518C1.63592 3.44998 1.5229 3.72386 1.62168 3.96352L5.83868 14.1947C5.93746 14.4344 6.21064 14.5491 6.44884 14.4509L10.8223 12.6483C11.0605 12.5501 11.1735 12.2762 11.0747 12.0366L10.3945 10.3862C10.3945 10.3862 10.7176 10.3827 10.8922 10.3873C11.0576 10.3915 11.4145 10.4126 11.4145 10.4126L11.9374 11.681C12.2337 12.4 11.8946 13.2216 11.18 13.5162L6.80656 15.3188C6.09195 15.6133 5.27241 15.2692 4.97607 14.5503L0.759068 4.31906C0.462724 3.60008 0.801796 2.77845 1.51641 2.48391L5.88986 0.681302C6.60447 0.386762 7.42401 0.730841 7.72036 1.44983ZM5.17174 5.70043L6.78035 9.52946V5.85984C6.78035 5.85984 8.05165 5.17907 9.47208 4.98728C10.8925 4.79549 12.7012 5.04912 12.7012 5.04912L11.8263 2.95879C11.8263 2.95879 9.92507 2.83601 8.20402 3.57138C6.48298 4.30674 5.17174 5.70043 5.17174 5.70043ZM7.28939 10.4171C7.28939 10.4171 9.24445 9.82126 10.5974 9.8179C12.8343 9.81236 14.3482 10.7059 14.3482 10.7059V6.37386C14.3482 6.37386 12.8266 5.58593 10.9096 5.53265C8.99267 5.47938 7.28939 6.31345 7.28939 6.31345V10.4171Z"
      fill="currentColor"
    />
  </svg>
`,Nt=T`<svg
  class="vipps-compact-logo"
  width="16"
  height="13"
  viewBox="0 0 16 13"
  fill="none"
  xmlns="http://www.w3.org/2000/svg"
>
  <path
    d="M0.521484 5.25293C1.15169 4.80704 2.01549 4.99454 2.57617 5.83887C3.97668 7.90259 5.82162 9.30942 8.08398 9.30957C10.3466 9.30957 11.6316 8.20702 12.8457 6.61328H12.8467V6.61133C13.499 5.74388 14.3645 5.57977 14.9473 6.04883C15.5772 6.54105 15.6004 7.43248 14.9473 8.2998C13.1957 10.6452 10.9547 12.0527 8.08398 12.0527C4.95627 12.0526 2.27187 10.3175 0.334961 7.33984C-0.201165 6.56632 -0.0609931 5.67568 0.521484 5.25293ZM10.4648 0C11.446 0 12.2861 0.750845 12.2861 1.8291C12.2861 2.90741 11.446 3.6582 10.4648 3.6582C9.48373 3.65811 8.64355 2.90735 8.64355 1.8291C8.64361 0.750909 9.48376 8.89995e-05 10.4648 0Z"
    fill="currentColor"
  />
</svg>`,Pt=T`<svg
  class="mobilepay-compact-logo"
  width="14"
  height="16"
  viewBox="0 0 14 16"
  fill="none"
  xmlns="http://www.w3.org/2000/svg"
>
  <path
    d="M5.23717 0.10537C5.95178 -0.189171 6.77188 0.15494 7.06823 0.873925C7.07761 0.896685 7.49322 1.90445 7.70202 2.41103C7.70202 2.41103 7.46091 2.51029 7.30162 2.57412C7.14234 2.63795 6.84557 2.7831 6.84557 2.7831L6.20592 1.22939C6.10719 0.989843 5.8337 0.87462 5.59557 0.972558L1.22155 2.77529C0.983478 2.87354 0.870839 3.148 0.969593 3.3876L5.18639 13.6181C5.28517 13.8577 5.55854 13.9731 5.79674 13.8749L10.1698 12.0722C10.408 11.974 10.5215 11.6995 10.4227 11.4599C10.4142 11.4391 9.89822 10.1884 9.74205 9.80947C9.74205 9.80947 10.0655 9.8069 10.2401 9.81142C10.4055 9.81572 10.7626 9.83584 10.7626 9.83584C10.8835 10.1293 11.277 11.0848 11.285 11.1044C11.5813 11.8233 11.2427 12.6457 10.5282 12.9403L6.15416 14.7421C5.43955 15.0366 4.62043 14.6925 4.32409 13.9735L0.107288 3.74307C-0.189055 3.02408 0.149515 2.20167 0.864124 1.90713L5.23717 0.10537ZM10.2577 4.95596C12.1745 5.0093 13.6962 5.79775 13.6962 5.79775V10.1298C13.6962 10.1298 12.182 9.23558 9.94518 9.24111C8.60362 9.24444 6.67007 9.83084 6.63756 9.84072V5.73721C6.66459 5.72404 8.35611 4.90311 10.2577 4.95596ZM11.1737 2.38271L12.0487 4.47256C12.0255 4.46933 10.2314 4.22057 8.82018 4.41103C7.39974 4.60283 6.1278 5.2831 6.1278 5.2831V8.95303L4.5194 5.12392C4.52988 5.1128 5.83754 3.72744 7.55162 2.99502C9.26023 2.26497 11.1465 2.381 11.1737 2.38271Z"
    fill="currentColor"
  />
</svg>`,Ft=T`<svg
  aria-busy="true"
  role="status"
  xmlns="http://www.w3.org/2000/svg"
  viewBox="0 0 50 20"
  class="loading-container"
  fill="var(--vm-btn-text)"
>
  <circle cx="6" cy="5" r="5" class="loading-dot"></circle>
  <circle cx="25" cy="5" r="5" class="loading-dot delay-200"></circle>
  <circle cx="44" cy="5" r="5" class="loading-dot delay-400"></circle>
</svg>`,It=c`
  .loading-container {
    animation: 250ms ease 200ms forwards fade-in;
    left: calc(50% - 1rem);
    opacity: 0;
    position: absolute;
    width: 1.875rem;
  }

  .loading-dot {
    animation: 450ms ease-in-out infinite alternate backwards;
    animation-name: bounce, fade-in;
  }

  @media (prefers-reduced-motion) {
    .loading-dot {
      animation-duration: 1000ms;
      transform: translate3D(0, 0.32rem, 0);
      animation-name: fade-in;
    }
  }

  .delay-200 {
    animation-delay: 200ms;
  }

  .delay-400 {
    animation-delay: 400ms;
  }

  @keyframes fade-in {
    from {
      opacity: 0.2;
    }

    to {
      opacity: 1;
    }
  }

  @keyframes bounce {
    from {
      transform: translate3D(0, 0.625rem, 0);
    }

    to {
      transform: translate3D(0, 0, 0);
    }
  }
`,Lt=c`
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
`,H=e=>e/16,Rt=c`
  :host([brand='vipps'][variant='primary']) button,
  :host([variant='orange']) button {
    --vm-btn-text: var(--vm-vipps-text);
    --vm-btn-text-disabled: var(--vm-vipps-text-disabled);
    --vm-btn-bg: var(--vm-vipps-bg);
    --vm-btn-bg-hover: var(--vm-vipps-bg-hover);
    --vm-btn-bg-active: var(--vm-vipps-bg-active);
    --vm-btn-bg-focus: var(--vm-vipps-bg-focus);
    --vm-btn-bg-loading: var(--vm-vipps-bg-loading);
    --vm-btn-bg-disabled: var(--vm-vipps-bg-disabled);
  }

  :host([brand='mobilepay']) button {
    --vm-btn-text: var(--vm-mp-text);
    --vm-btn-text-disabled: var(--vm-mp-text-disabled);
    --vm-btn-bg: var(--vm-mp-bg);
    --vm-btn-bg-hover: var(--vm-mp-bg-hover);
    --vm-btn-bg-active: var(--vm-mp-bg-active);
    --vm-btn-bg-focus: var(--vm-mp-bg-focus);
    --vm-btn-bg-loading: var(--vm-mp-bg-loading);
    --vm-btn-bg-disabled: var(--vm-mp-bg-disabled);
    --vm-btn-row-gap: 0.5625rem;
  }

  :host([variant='purple']) button,
  :host([variant='dark']) button {
    --vm-btn-text: var(--vm-dark-text);
    --vm-btn-text-disabled: var(--vm-dark-text-disabled);
    --vm-btn-bg: var(--vm-dark-bg);
    --vm-btn-bg-hover: var(--vm-dark-bg-hover);
    --vm-btn-bg-active: var(--vm-dark-bg-active);
    --vm-btn-bg-focus: var(--vm-dark-bg-focus);
    --vm-btn-bg-loading: var(--vm-dark-bg-loading);
    --vm-btn-bg-disabled: var(--vm-dark-bg-disabled);
  }

  :host([variant='light']) button,
  :host([variant='stroked']) button {
    --vm-btn-text: var(--vm-light-text);
    --vm-btn-text-disabled: var(--vm-light-text-disabled);
    --vm-btn-bg: var(--vm-light-bg);
    --vm-btn-bg-hover: var(--vm-light-bg-hover);
    --vm-btn-bg-active: var(--vm-light-bg-active);
    --vm-btn-bg-focus: var(--vm-light-bg-focus);
    --vm-btn-bg-loading: var(--vm-light-bg-loading);
    --vm-btn-bg-disabled: var(--vm-light-bg-disabled);
  }

  :host([branded='false']) button {
    --vm-logo-height: 12px;
  }

  :host([compact='true']) button {
    --vm-logo-height: 15px;
  }

  button {
    --vm-colors-orange: #ff5b24;
    --vm-colors-orange-light: #ff985f;
    --vm-colors-orange-dark: #db460f;
    --vm-colors-mpblue: #5a78ff;
    --vm-colors-mpblue-light: #7b93ff;
    --vm-colors-mpblue-dark: #4961cd;
    --vm-colors-black: #000000;
    --vm-colors-gray-80: #433f58;
    --vm-colors-gray-90: #2c283e;
    --vm-colors-gray-30: #c9c6d7;
    --vm-colors-gray-10: #efeef3;
    --vm-colors-gray-5: #f6f6f9;
    --vm-colors-white: #ffffff;
    --vm-colors-blue-outline: #432fff99;

    --vm-vipps-text: var(--vm-colors-white);
    --vm-vipps-text-disabled: var(--vm-colors-white);
    --vm-vipps-bg: var(--vm-colors-orange);
    --vm-vipps-bg-hover: var(--vm-colors-orange-light);
    --vm-vipps-bg-active: var(--vm-colors-orange-dark);
    --vm-vipps-bg-focus: var(--vm-colors-orange);
    --vm-vipps-bg-loading: var(--vm-colors-orange-dark);
    --vm-vipps-bg-disabled: var(--vm-colors-gray-30);

    --vm-mp-text: var(--vm-colors-white);
    --vm-mp-text-disabled: var(--vm-colors-white);
    --vm-mp-bg: var(--vm-colors-mpblue);
    --vm-mp-bg-hover: var(--vm-colors-mpblue-light);
    --vm-mp-bg-active: var(--vm-colors-mpblue-dark);
    --vm-mp-bg-focus: var(--vm-colors-mpblue);
    --vm-mp-bg-loading: var(--vm-colors-mpblue-dark);
    --vm-mp-bg-disabled: var(--vm-colors-gray-30);

    --vm-dark-text: var(--vm-colors-white);
    --vm-dark-text-disabled: var(--vm-colors-white);
    --vm-dark-bg: var(--vm-colors-black);
    --vm-dark-bg-hover: var(--vm-colors-gray-80);
    --vm-dark-bg-active: var(--vm-colors-gray-90);
    --vm-dark-bg-focus: var(--vm-colors-black);
    --vm-dark-bg-loading: var(--vm-colors-black);
    --vm-dark-bg-disabled: var(--vm-colors-gray-30);

    --vm-light-text: var(--vm-colors-black);
    --vm-light-text-disabled: var(--vm-colors-white);
    --vm-light-bg: var(--vm-colors-white);
    --vm-light-bg-hover: var(--vm-colors-gray-5);
    --vm-light-bg-active: var(--vm-colors-gray-10);
    --vm-light-bg-focus: var(--vm-colors-white);
    --vm-light-bg-loading: var(--vm-colors-white);
    --vm-light-bg-disabled: var(--vm-colors-gray-30);

    --vm-logo-translate: -6px;
    --vm-logo-height: 18px;
    --vm-logo-space: calc(var(--vm-logo-height) + var(--vm-logo-translate));

    --vm-text-md: ${H(18.5)}rem;
    --vm-line-height: ${H(12)}rem;

    --vm-content-height: max(var(--vm-line-height), var(--vm-logo-space));

    --vm-font-medium: 500;
    --vm-font-semibold: 700;
    --vm-font-vipps: Vipps, 'SF Pro Text', Arial, sans-serif;
    --vm-font-mp: 'Paytype', 'SF Pro Text', Arial, sans-serif;
    --vm-rounded-sm: ${H(5)}rem;
    --vm-rounded-full: 99999px;
    --vm-padding-y: calc((44px - var(--vm-content-height)) / 2);
    --vm-padding-x: 24px;
    --vm-text-kerning: -0.2px;

    --vm-btn-radius: var(--vm-rounded-sm);
    --vm-btn-weight: var(--vm-font-semibold);
    --vm-btn-font: var(--vm-font-vipps);
    --vm-btn-column-gap: ${H(6)}rem;
    --vm-btn-row-gap: ${H(6)}rem;

    --vm-btn-text: var(--vm-vipps-text);
    --vm-btn-text-disabled: var(--vm-vipps-text-disabled);
    --vm-btn-bg: var(--vm-vipps-bg);
    --vm-btn-bg-hover: var(--vm-vipps-bg-hover);
    --vm-btn-bg-active: var(--vm-vipps-bg-active);
    --vm-btn-bg-focus: var(--vm-vipps-bg-focus);
    --vm-btn-bg-loading: var(--vm-vipps-bg-loading);
    --vm-btn-bg-disabled: var(--vm-vipps-bg-disabled);
    --vm-btn-outline-opacity: 60%;
    --vm-btn-outline: color-mix(
      in srgb,
      var(--vm-colors-blue-outline) var(--vm-btn-outline-opacity),
      white
    );

    display: inline-flex;
    row-gap: var(--vm-btn-row-gap);
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;

    position: relative;
    padding: var(--vm-padding-y) var(--vm-padding-x);

    background: var(--vm-btn-bg);
    color: var(--vm-btn-text);

    border: none;
    border-radius: var(--vm-btn-radius);

    font-family: var(--vm-btn-font);
    font-size: var(--vm-text-md);
    line-height: var(--vm-line-height);
    font-style: normal;
    font-weight: var(--vm-btn-weight);
    text-decoration: none;
    letter-spacing: var(--vm-text-kerning);
    text-wrap: nowrap;

    cursor: pointer;
    transition: ease 0.2s;
    transition-property: color, background;
  }

  :host([rounded='true']) button,
  :host([rounded='']) button {
    border-radius: var(--vm-rounded-full);
  }

  button:hover {
    background: var(--vm-btn-bg-hover);
  }

  button:active {
    background: var(--vm-btn-bg-active);
  }

  button:focus-visible {
    background: var(--vm-btn-bg-focus);
    outline: 4px solid var(--vm-colors-blue-outline);
    outline-offset: 1px;
  }

  :host([stretched='true']) button,
  :host([stretched='']) button {
    width: 100%;
  }

  button[aria-busy='true'] {
    background: var(--vm-btn-bg-loading);
    color: transparent;
    cursor: progress;
  }

  button[disabled] {
    background: var(--vm-btn-bg-disabled) !important;
    cursor: not-allowed;
  }

  button[disabled]:not([aria-busy='true']) {
    color: var(--vm-btn-text-disabled) !important;
  }

  button {
    column-gap: var(--vm-btn-column-gap);
  }

  .vipps-logo {
    --vm-logo-translate: -6px;
    margin-bottom: var(--vm-logo-translate);
  }

  .mobilepay-logo {
    font-family: var(--vm-font-mp);
    font-weight: var(--vm-font-semibold);
    --vm-logo-translate: -2px;
    margin-bottom: var(--vm-logo-translate);
  }

  .vipps-compact-logo {
    --vm-logo-translate: -6px;
    --vm-logo-height: 13px;
    margin-bottom: var(--vm-logo-translate);
  }

  .mobilepay-compact-logo {
    --vm-logo-translate: -4px;
    --vm-logo-height: 16px;
    margin-bottom: var(--vm-logo-translate);
  }
`;function U(e,t,n,r){var i=arguments.length,a=i<3?t:r===null?r=Object.getOwnPropertyDescriptor(t,n):r,o;if(typeof Reflect==`object`&&typeof Reflect.decorate==`function`)a=Reflect.decorate(e,t,n,r);else for(var s=e.length-1;s>=0;s--)(o=e[s])&&(a=(i<3?o(a):i>3?o(t,n,a):o(t,n))||a);return i>3&&a&&Object.defineProperty(t,n,a),a}function W(e){"@babel/helpers - typeof";return W=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},W(e)}function zt(e,t){if(W(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t||`default`);if(W(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}function Bt(e){var t=zt(e,`string`);return W(t)==`symbol`?t:t+``}function Vt(e,t,n){return(t=Bt(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function Ht(e,t){if(t.has(e))throw TypeError(`Cannot initialize the same private elements twice on an object`)}function G(e,t,n){Ht(e,t),t.set(e,n)}function Ut(e,t,n){if(typeof e==`function`?e===t:e.has(t))return arguments.length<3?t:n;throw TypeError(`Private element is not present on this object`)}function K(e,t){return e.get(Ut(e,t))}function q(e,t,n){return e.set(Ut(e,t),n),n}var J,Y,X,Z,Q,Wt,Gt,Kt,qt,Jt,Yt,Xt,Zt,Qt,$t,en,tn=[`orange`,`purple`,`stroked`],$=(Y=new WeakMap,X=new WeakMap,Z=new WeakMap,Q=new WeakMap,Wt=new WeakMap,Gt=new WeakMap,Kt=new WeakMap,qt=new WeakMap,Jt=new WeakMap,Yt=new WeakMap,Xt=new WeakMap,Zt=new WeakMap,Qt=new WeakMap,$t=new WeakMap,en=new WeakMap,J=class extends M{get brandLogo(){return K(Y,this)}set brandLogo(e){q(Y,this,e)}get miniBrandLogo(){return K(X,this)}set miniBrandLogo(e){q(X,this,e)}get compactBrandLogo(){return K(Z,this)}set compactBrandLogo(e){q(Z,this,e)}get textMessage(){return K(Q,this)}set textMessage(e){q(Q,this,e)}get buttonAriaLabel(){return K(Wt,this)}set buttonAriaLabel(e){q(Wt,this,e)}get language(){return K(Gt,this)}set language(e){q(Gt,this,e)}get branded(){return K(Kt,this)}set branded(e){q(Kt,this,e)}get compact(){return K(qt,this)}set compact(e){q(qt,this,e)}get type(){return K(Jt,this)}set type(e){q(Jt,this,e)}get disabled(){return K(Yt,this)}set disabled(e){q(Yt,this,e)}get brand(){return K(Xt,this)}set brand(e){q(Xt,this,e)}get verb(){return K(Zt,this)}set verb(e){q(Zt,this,e)}get vmpContinueAsFirstName(){return K(Qt,this)}set vmpContinueAsFirstName(e){q(Qt,this,e)}get variant(){return K($t,this)}set variant(e){q($t,this,e)}get loading(){return K(en,this)}set loading(e){q(en,this,e)}constructor(){if(super(),Vt(this,`internals`,void 0),Vt(this,`isSupportElementInternals`,!1),G(this,Y,kt),G(this,X,jt),G(this,Z,Nt),G(this,Q,``),G(this,Wt,null),G(this,Gt,ht),G(this,Kt,!0),G(this,qt,!1),G(this,Jt,`button`),G(this,Yt,!1),G(this,Xt,`vipps`),G(this,Zt,`buy`),G(this,Qt,``),G(this,$t,`primary`),G(this,en,!1),this.attachInternals&&(this.internals=this.attachInternals(),this.isSupportElementInternals=!0),!document.head.querySelector(`style[data-vipps-mobilepay-button-fonts]`)){let e=document.createElement(`style`);e.setAttribute(`data-vipps-mobilepay-button-fonts`,``),e.innerHTML=Lt.cssText,document.head.appendChild(e)}this.rejectVippsForFinland(),this.updateLogo(),this.textMessage=this.getTextContent(),this.buttonAriaLabel=this.getButtonAriaLabel()}rejectVippsForFinland(){this.language.toLowerCase()===`fi`&&this.brand.toLowerCase()===`vipps`&&(console.info(`[Vipps MobilePay Button]
Language ${this.language} is not supported when using brand Vipps.`),this.language=`en`)}updateLogo(){let e=this.brand.toLowerCase()===`mobilepay`;this.brandLogo=e?At:kt,this.miniBrandLogo=e?Mt:jt,this.compactBrandLogo=e?Pt:Nt}getTextContent(){switch(this.verb.toLowerCase()){case`pay`:return this.compact?I(T`${this.compactBrandLogo} <span>Betal</span>`):this.branded?I(T`<span>Betal med </span>${this.brandLogo(I)}`):I(`Betal`);case`login`:return this.compact?I(T`${this.compactBrandLogo} <span>Logg inn</span>`):this.branded?I(T`<span>Logg inn med </span>${this.brandLogo(I)}`):I(`Logg inn`);case`register`:return this.compact?I(T`${this.compactBrandLogo} <span>Registrer</span>`):this.branded?I(T`<span>Registrer med </span>${this.brandLogo(I)}`):I(`Registrer`);case`continue`:return this.vmpContinueAsFirstName?this.compact?I(T`${this.compactBrandLogo}
                <span>Fortsett som ${this.vmpContinueAsFirstName}</span>`):this.branded?I(T`${this.miniBrandLogo} <span>Fortsett som ${this.vmpContinueAsFirstName}</span>`):I(T`Fortsett som ${this.vmpContinueAsFirstName}`):this.compact?I(T`${this.compactBrandLogo} <span>Fortsett</span>`):this.branded?I(T`<span>Fortsett med </span>${this.brandLogo(I)}`):I(T`Fortsett`);case`confirm`:return this.compact?I(T`${this.compactBrandLogo} <span>Bekreft</span>`):this.branded?I(T`<span>Bekreft med </span>${this.brandLogo(I)}`):I(T`Bekreft`);case`donate`:return this.compact?I(T`${this.compactBrandLogo} <span>Bidra</span>`):this.branded?I(T`<span>Bidra med </span>${this.brandLogo(I)}`):I(T`Bidra`);case`express`:return this.compact?I(T`${this.compactBrandLogo} <span>Express</span>`):this.branded?T`${this.brand.toLowerCase()===`mobilepay`?T`<span class="mobilepay-logo">MobilePay</span>`:this.brandLogo(I)}<span> Express</span>`:I(`Express`);default:return this.compact?I(T`${this.compactBrandLogo} <span>Kjøp nå</span>`):this.branded?I(T`<span>Kjøp nå med </span>${this.brandLogo(I)}`):I(`Kjøp nå`)}}getButtonAriaLabel(){return this.verb.toLowerCase()===`express`?I(F`Kjøp nå med ${this.brand.toLowerCase()===`mobilepay`?`MobilePay`:`Vipps`} Express`):null}getForm(){var e;return this.isSupportElementInternals?(e=this.internals)==null?void 0:e.form:this.closest(`form`)}willUpdate(e){this.rejectVippsForFinland(),e.has(`brand`)&&this.updateLogo(),this.textMessage=this.getTextContent(),this.buttonAriaLabel=this.getButtonAriaLabel(),tn.includes(this.variant)&&console.warn(`[Vipps MobilePay Button]
Variant "${this.variant}" is marked as deprecated and will be removed in the future.
See https://developer.vippsmobilepay.com/docs/knowledge-base/design-guidelines/buttons/ for more information.`),e.has(`language`)&&Ot(this.language)&&Dt(this.language)}click(){let e=this.getForm();if(this.type!==`submit`||!e)return;if(e.requestSubmit){e.requestSubmit();return}let t=document.createElement(`input`);t.type=`submit`,t.style.display=`none`,e.appendChild(t),t.click(),e.removeChild(t)}connectedCallback(){super.connectedCallback();let e=this.getForm();this.type!==`submit`||!e||(this.addEventListener(`click`,()=>{this.click()}),e.addEventListener(`keypress`,e=>{e.code===`Enter`&&this.click()}))}render(){var e;return T` <button
      type="${this.type}"
      ?disabled=${this.disabled}
      aria-busy="${this.loading}"
      aria-label=${(e=this.buttonAriaLabel)==null?D:e}
    >
      ${this.loading?Ft:D} ${this.textMessage}
    </button>`}},Vt(J,`styles`,[Rt,It]),Vt(J,`formAssociated`,!0),J);U([P()],$.prototype,`brandLogo`,null),U([P()],$.prototype,`miniBrandLogo`,null),U([P()],$.prototype,`compactBrandLogo`,null),U([P()],$.prototype,`textMessage`,null),U([P()],$.prototype,`buttonAriaLabel`,null),U([N({type:String})],$.prototype,`language`,null),U([N({converter:e=>e===`true`||e===``})],$.prototype,`branded`,null),U([N({converter:e=>e===`true`||e===``})],$.prototype,`compact`,null),U([N({type:String})],$.prototype,`type`,null),U([N({converter:e=>e===`true`||e===``})],$.prototype,`disabled`,null),U([N({type:String})],$.prototype,`brand`,null),U([N({type:String})],$.prototype,`verb`,null),U([N({type:String})],$.prototype,`vmpContinueAsFirstName`,null),U([N({type:String})],$.prototype,`variant`,null),U([N({converter:e=>e===`true`||e===``})],$.prototype,`loading`,null),$=U([Be(`vipps-mobilepay-button`),Ze()],$)})();