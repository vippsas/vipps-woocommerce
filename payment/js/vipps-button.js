"use strict";(()=>{var dt=Object.defineProperty;var Ke=Object.getOwnPropertyDescriptor;var jt=s=>{throw TypeError(s)};var Ze=(s,t,e)=>t in s?dt(s,t,{enumerable:!0,configurable:!0,writable:!0,value:e}):s[t]=e;var et=(s,t)=>{for(var e in t)dt(s,e,{get:t[e],enumerable:!0})};var u=(s,t,e,o)=>{for(var r=o>1?void 0:o?Ke(t,e):t,i=s.length-1,n;i>=0;i--)(n=s[i])&&(r=(o?n(t,e,r):n(r))||r);return o&&r&&dt(t,e,r),r};var j=(s,t,e)=>Ze(s,typeof t!="symbol"?t+"":t,e),Ft=(s,t,e)=>t.has(s)||jt("Cannot "+e);var g=(s,t,e)=>(Ft(s,t,"read from private field"),e?e.call(s):t.get(s)),b=(s,t,e)=>t.has(s)?jt("Cannot add the same private member more than once"):t instanceof WeakSet?t.add(s):t.set(s,e),$=(s,t,e,o)=>(Ft(s,t,"write to private field"),o?o.call(s,e):t.set(s,e),e);var st=globalThis,ot=st.ShadowRoot&&(st.ShadyCSS===void 0||st.ShadyCSS.nativeShadow)&&"adoptedStyleSheets"in Document.prototype&&"replace"in CSSStyleSheet.prototype,mt=Symbol(),It=new WeakMap,F=class{constructor(t,e,o){if(this._$cssResult$=!0,o!==mt)throw Error("CSSResult is not constructable. Use `unsafeCSS` or `css` instead.");this.cssText=t,this.t=e}get styleSheet(){let t=this.o,e=this.t;if(ot&&t===void 0){let o=e!==void 0&&e.length===1;o&&(t=It.get(e)),t===void 0&&((this.o=t=new CSSStyleSheet).replaceSync(this.cssText),o&&It.set(e,t))}return t}toString(){return this.cssText}},Vt=s=>new F(typeof s=="string"?s:s+"",void 0,mt),P=(s,...t)=>{let e=s.length===1?s[0]:t.reduce((o,r,i)=>o+(n=>{if(n._$cssResult$===!0)return n.cssText;if(typeof n=="number")return n;throw Error("Value passed to 'css' function must be a 'css' function result: "+n+". Use 'unsafeCSS' to pass non-literal values, but take care to ensure page security.")})(r)+s[i+1],s[0]);return new F(e,s,mt)},Dt=(s,t)=>{if(ot)s.adoptedStyleSheets=t.map(e=>e instanceof CSSStyleSheet?e:e.styleSheet);else for(let e of t){let o=document.createElement("style"),r=st.litNonce;r!==void 0&&o.setAttribute("nonce",r),o.textContent=e.cssText,s.appendChild(o)}},ht=ot?s=>s:s=>s instanceof CSSStyleSheet?(t=>{let e="";for(let o of t.cssRules)e+=o.cssText;return Vt(e)})(s):s;var{is:Je,defineProperty:Xe,getOwnPropertyDescriptor:Ge,getOwnPropertyNames:Qe,getOwnPropertySymbols:Ye,getPrototypeOf:ts}=Object,L=globalThis,qt=L.trustedTypes,es=qt?qt.emptyScript:"",ft=L.reactiveElementPolyfillSupport,I=(s,t)=>s,V={toAttribute(s,t){switch(t){case Boolean:s=s?es:null;break;case Object:case Array:s=s==null?s:JSON.stringify(s)}return s},fromAttribute(s,t){let e=s;switch(t){case Boolean:e=s!==null;break;case Number:e=s===null?null:Number(s);break;case Object:case Array:try{e=JSON.parse(s)}catch{e=null}}return e}},rt=(s,t)=>!Je(s,t),zt={attribute:!0,type:String,converter:V,reflect:!1,useDefault:!1,hasChanged:rt},Wt,Kt;(Wt=Symbol.metadata)!=null||(Symbol.metadata=Symbol("metadata")),(Kt=L.litPropertyMetadata)!=null||(L.litPropertyMetadata=new WeakMap);var w=class extends HTMLElement{static addInitializer(t){var e;this._$Ei(),((e=this.l)!=null?e:this.l=[]).push(t)}static get observedAttributes(){return this.finalize(),this._$Eh&&[...this._$Eh.keys()]}static createProperty(t,e=zt){if(e.state&&(e.attribute=!1),this._$Ei(),this.prototype.hasOwnProperty(t)&&((e=Object.create(e)).wrapped=!0),this.elementProperties.set(t,e),!e.noAccessor){let o=Symbol(),r=this.getPropertyDescriptor(t,o,e);r!==void 0&&Xe(this.prototype,t,r)}}static getPropertyDescriptor(t,e,o){var n;let{get:r,set:i}=(n=Ge(this.prototype,t))!=null?n:{get(){return this[e]},set(c){this[e]=c}};return{get:r,set(c){let l=r==null?void 0:r.call(this);i==null||i.call(this,c),this.requestUpdate(t,l,o)},configurable:!0,enumerable:!0}}static getPropertyOptions(t){var e;return(e=this.elementProperties.get(t))!=null?e:zt}static _$Ei(){if(this.hasOwnProperty(I("elementProperties")))return;let t=ts(this);t.finalize(),t.l!==void 0&&(this.l=[...t.l]),this.elementProperties=new Map(t.elementProperties)}static finalize(){if(this.hasOwnProperty(I("finalized")))return;if(this.finalized=!0,this._$Ei(),this.hasOwnProperty(I("properties"))){let e=this.properties,o=[...Qe(e),...Ye(e)];for(let r of o)this.createProperty(r,e[r])}let t=this[Symbol.metadata];if(t!==null){let e=litPropertyMetadata.get(t);if(e!==void 0)for(let[o,r]of e)this.elementProperties.set(o,r)}this._$Eh=new Map;for(let[e,o]of this.elementProperties){let r=this._$Eu(e,o);r!==void 0&&this._$Eh.set(r,e)}this.elementStyles=this.finalizeStyles(this.styles)}static finalizeStyles(t){let e=[];if(Array.isArray(t)){let o=new Set(t.flat(1/0).reverse());for(let r of o)e.unshift(ht(r))}else t!==void 0&&e.push(ht(t));return e}static _$Eu(t,e){let o=e.attribute;return o===!1?void 0:typeof o=="string"?o:typeof t=="string"?t.toLowerCase():void 0}constructor(){super(),this._$Ep=void 0,this.isUpdatePending=!1,this.hasUpdated=!1,this._$Em=null,this._$Ev()}_$Ev(){var t;this._$ES=new Promise(e=>this.enableUpdating=e),this._$AL=new Map,this._$E_(),this.requestUpdate(),(t=this.constructor.l)==null||t.forEach(e=>e(this))}addController(t){var e,o;((e=this._$EO)!=null?e:this._$EO=new Set).add(t),this.renderRoot!==void 0&&this.isConnected&&((o=t.hostConnected)==null||o.call(t))}removeController(t){var e;(e=this._$EO)==null||e.delete(t)}_$E_(){let t=new Map,e=this.constructor.elementProperties;for(let o of e.keys())this.hasOwnProperty(o)&&(t.set(o,this[o]),delete this[o]);t.size>0&&(this._$Ep=t)}createRenderRoot(){var e;let t=(e=this.shadowRoot)!=null?e:this.attachShadow(this.constructor.shadowRootOptions);return Dt(t,this.constructor.elementStyles),t}connectedCallback(){var t,e;(t=this.renderRoot)!=null||(this.renderRoot=this.createRenderRoot()),this.enableUpdating(!0),(e=this._$EO)==null||e.forEach(o=>{var r;return(r=o.hostConnected)==null?void 0:r.call(o)})}enableUpdating(t){}disconnectedCallback(){var t;(t=this._$EO)==null||t.forEach(e=>{var o;return(o=e.hostDisconnected)==null?void 0:o.call(e)})}attributeChangedCallback(t,e,o){this._$AK(t,o)}_$ET(t,e){var i;let o=this.constructor.elementProperties.get(t),r=this.constructor._$Eu(t,o);if(r!==void 0&&o.reflect===!0){let n=(((i=o.converter)==null?void 0:i.toAttribute)!==void 0?o.converter:V).toAttribute(e,o.type);this._$Em=t,n==null?this.removeAttribute(r):this.setAttribute(r,n),this._$Em=null}}_$AK(t,e){var i,n,c;let o=this.constructor,r=o._$Eh.get(t);if(r!==void 0&&this._$Em!==r){let l=o.getPropertyOptions(r),d=typeof l.converter=="function"?{fromAttribute:l.converter}:((i=l.converter)==null?void 0:i.fromAttribute)!==void 0?l.converter:V;this._$Em=r;let m=d.fromAttribute(e,l.type);this[r]=(c=m!=null?m:(n=this._$Ej)==null?void 0:n.get(r))!=null?c:m,this._$Em=null}}requestUpdate(t,e,o,r=!1,i){var n,c;if(t!==void 0){let l=this.constructor;if(r===!1&&(i=this[t]),o!=null||(o=l.getPropertyOptions(t)),!(((n=o.hasChanged)!=null?n:rt)(i,e)||o.useDefault&&o.reflect&&i===((c=this._$Ej)==null?void 0:c.get(t))&&!this.hasAttribute(l._$Eu(t,o))))return;this.C(t,e,o)}this.isUpdatePending===!1&&(this._$ES=this._$EP())}C(t,e,{useDefault:o,reflect:r,wrapped:i},n){var c,l,d;o&&!((c=this._$Ej)!=null?c:this._$Ej=new Map).has(t)&&(this._$Ej.set(t,(l=n!=null?n:e)!=null?l:this[t]),i!==!0||n!==void 0)||(this._$AL.has(t)||(this.hasUpdated||o||(e=void 0),this._$AL.set(t,e)),r===!0&&this._$Em!==t&&((d=this._$Eq)!=null?d:this._$Eq=new Set).add(t))}async _$EP(){this.isUpdatePending=!0;try{await this._$ES}catch(e){Promise.reject(e)}let t=this.scheduleUpdate();return t!=null&&await t,!this.isUpdatePending}scheduleUpdate(){return this.performUpdate()}performUpdate(){var o,r;if(!this.isUpdatePending)return;if(!this.hasUpdated){if((o=this.renderRoot)!=null||(this.renderRoot=this.createRenderRoot()),this._$Ep){for(let[n,c]of this._$Ep)this[n]=c;this._$Ep=void 0}let i=this.constructor.elementProperties;if(i.size>0)for(let[n,c]of i){let{wrapped:l}=c,d=this[n];l!==!0||this._$AL.has(n)||d===void 0||this.C(n,void 0,c,d)}}let t=!1,e=this._$AL;try{t=this.shouldUpdate(e),t?(this.willUpdate(e),(r=this._$EO)==null||r.forEach(i=>{var n;return(n=i.hostUpdate)==null?void 0:n.call(i)}),this.update(e)):this._$EM()}catch(i){throw t=!1,this._$EM(),i}t&&this._$AE(e)}willUpdate(t){}_$AE(t){var e;(e=this._$EO)==null||e.forEach(o=>{var r;return(r=o.hostUpdated)==null?void 0:r.call(o)}),this.hasUpdated||(this.hasUpdated=!0,this.firstUpdated(t)),this.updated(t)}_$EM(){this._$AL=new Map,this.isUpdatePending=!1}get updateComplete(){return this.getUpdateComplete()}getUpdateComplete(){return this._$ES}shouldUpdate(t){return!0}update(t){this._$Eq&&(this._$Eq=this._$Eq.forEach(e=>this._$ET(e,this[e]))),this._$EM()}updated(t){}firstUpdated(t){}},Zt;w.elementStyles=[],w.shadowRootOptions={mode:"open"},w[I("elementProperties")]=new Map,w[I("finalized")]=new Map,ft==null||ft({ReactiveElement:w}),((Zt=L.reactiveElementVersions)!=null?Zt:L.reactiveElementVersions=[]).push("2.1.2");var q=globalThis,Jt=s=>s,at=q.trustedTypes,Xt=at?at.createPolicy("lit-html",{createHTML:s=>s}):void 0,oe="$lit$",E=`lit$${Math.random().toFixed(9).slice(2)}$`,re="?"+E,ss=`<${re}>`,M=document,z=()=>M.createComment(""),W=s=>s===null||typeof s!="object"&&typeof s!="function",_t=Array.isArray,os=s=>_t(s)||typeof(s==null?void 0:s[Symbol.iterator])=="function",vt=`[ 	
\f\r]`,D=/<(?:(!--|\/[^a-zA-Z])|(\/?[a-zA-Z][^>\s]*)|(\/?$))/g,Gt=/-->/g,Qt=/>/g,k=RegExp(`>|${vt}(?:([^\\s"'>=/]+)(${vt}*=${vt}*(?:[^ 	
\f\r"'\`<>=]|("|')|))|$)`,"g"),Yt=/'/g,te=/"/g,ae=/^(?:script|style|textarea|title)$/i,wt=s=>(t,...e)=>({_$litType$:s,strings:t,values:e}),a=wt(1),Es=wt(2),Ss=wt(3),R=Symbol.for("lit-noChange"),v=Symbol.for("lit-nothing"),ee=new WeakMap,T=M.createTreeWalker(M,129);function ne(s,t){if(!_t(s)||!s.hasOwnProperty("raw"))throw Error("invalid template strings array");return Xt!==void 0?Xt.createHTML(t):t}var rs=(s,t)=>{let e=s.length-1,o=[],r,i=t===2?"<svg>":t===3?"<math>":"",n=D;for(let c=0;c<e;c++){let l=s[c],d,m,h=-1,_=0;for(;_<l.length&&(n.lastIndex=_,m=n.exec(l),m!==null);)_=n.lastIndex,n===D?m[1]==="!--"?n=Gt:m[1]!==void 0?n=Qt:m[2]!==void 0?(ae.test(m[2])&&(r=RegExp("</"+m[2],"g")),n=k):m[3]!==void 0&&(n=k):n===k?m[0]===">"?(n=r!=null?r:D,h=-1):m[1]===void 0?h=-2:(h=n.lastIndex-m[2].length,d=m[1],n=m[3]===void 0?k:m[3]==='"'?te:Yt):n===te||n===Yt?n=k:n===Gt||n===Qt?n=D:(n=k,r=void 0);let A=n===k&&s[c+1].startsWith("/>")?" ":"";i+=n===D?l+ss:h>=0?(o.push(d),l.slice(0,h)+oe+l.slice(h)+E+A):l+E+(h===-2?c:A)}return[ne(s,i+(s[e]||"<?>")+(t===2?"</svg>":t===3?"</math>":"")),o]},K=class s{constructor({strings:t,_$litType$:e},o){let r;this.parts=[];let i=0,n=0,c=t.length-1,l=this.parts,[d,m]=rs(t,e);if(this.el=s.createElement(d,o),T.currentNode=this.el.content,e===2||e===3){let h=this.el.content.firstChild;h.replaceWith(...h.childNodes)}for(;(r=T.nextNode())!==null&&l.length<c;){if(r.nodeType===1){if(r.hasAttributes())for(let h of r.getAttributeNames())if(h.endsWith(oe)){let _=m[n++],A=r.getAttribute(h).split(E),tt=/([.?@])?(.*)/.exec(_);l.push({type:1,index:i,name:tt[2],strings:A,ctor:tt[1]==="."?bt:tt[1]==="?"?$t:tt[1]==="@"?yt:H}),r.removeAttribute(h)}else h.startsWith(E)&&(l.push({type:6,index:i}),r.removeAttribute(h));if(ae.test(r.tagName)){let h=r.textContent.split(E),_=h.length-1;if(_>0){r.textContent=at?at.emptyScript:"";for(let A=0;A<_;A++)r.append(h[A],z()),T.nextNode(),l.push({type:2,index:++i});r.append(h[_],z())}}}else if(r.nodeType===8)if(r.data===re)l.push({type:2,index:i});else{let h=-1;for(;(h=r.data.indexOf(E,h+1))!==-1;)l.push({type:7,index:i}),h+=E.length-1}i++}}static createElement(t,e){let o=M.createElement("template");return o.innerHTML=t,o}};function O(s,t,e=s,o){var n,c,l;if(t===R)return t;let r=o!==void 0?(n=e._$Co)==null?void 0:n[o]:e._$Cl,i=W(t)?void 0:t._$litDirective$;return(r==null?void 0:r.constructor)!==i&&((c=r==null?void 0:r._$AO)==null||c.call(r,!1),i===void 0?r=void 0:(r=new i(s),r._$AT(s,e,o)),o!==void 0?((l=e._$Co)!=null?l:e._$Co=[])[o]=r:e._$Cl=r),r!==void 0&&(t=O(s,r._$AS(s,t.values),r,o)),t}var gt=class{constructor(t,e){this._$AV=[],this._$AN=void 0,this._$AD=t,this._$AM=e}get parentNode(){return this._$AM.parentNode}get _$AU(){return this._$AM._$AU}u(t){var d;let{el:{content:e},parts:o}=this._$AD,r=((d=t==null?void 0:t.creationScope)!=null?d:M).importNode(e,!0);T.currentNode=r;let i=T.nextNode(),n=0,c=0,l=o[0];for(;l!==void 0;){if(n===l.index){let m;l.type===2?m=new Z(i,i.nextSibling,this,t):l.type===1?m=new l.ctor(i,l.name,l.strings,this,t):l.type===6&&(m=new xt(i,this,t)),this._$AV.push(m),l=o[++c]}n!==(l==null?void 0:l.index)&&(i=T.nextNode(),n++)}return T.currentNode=M,r}p(t){let e=0;for(let o of this._$AV)o!==void 0&&(o.strings!==void 0?(o._$AI(t,o,e),e+=o.strings.length-2):o._$AI(t[e])),e++}},Z=class s{get _$AU(){var t,e;return(e=(t=this._$AM)==null?void 0:t._$AU)!=null?e:this._$Cv}constructor(t,e,o,r){var i;this.type=2,this._$AH=v,this._$AN=void 0,this._$AA=t,this._$AB=e,this._$AM=o,this.options=r,this._$Cv=(i=r==null?void 0:r.isConnected)!=null?i:!0}get parentNode(){let t=this._$AA.parentNode,e=this._$AM;return e!==void 0&&(t==null?void 0:t.nodeType)===11&&(t=e.parentNode),t}get startNode(){return this._$AA}get endNode(){return this._$AB}_$AI(t,e=this){t=O(this,t,e),W(t)?t===v||t==null||t===""?(this._$AH!==v&&this._$AR(),this._$AH=v):t!==this._$AH&&t!==R&&this._(t):t._$litType$!==void 0?this.$(t):t.nodeType!==void 0?this.T(t):os(t)?this.k(t):this._(t)}O(t){return this._$AA.parentNode.insertBefore(t,this._$AB)}T(t){this._$AH!==t&&(this._$AR(),this._$AH=this.O(t))}_(t){this._$AH!==v&&W(this._$AH)?this._$AA.nextSibling.data=t:this.T(M.createTextNode(t)),this._$AH=t}$(t){var i;let{values:e,_$litType$:o}=t,r=typeof o=="number"?this._$AC(t):(o.el===void 0&&(o.el=K.createElement(ne(o.h,o.h[0]),this.options)),o);if(((i=this._$AH)==null?void 0:i._$AD)===r)this._$AH.p(e);else{let n=new gt(r,this),c=n.u(this.options);n.p(e),this.T(c),this._$AH=n}}_$AC(t){let e=ee.get(t.strings);return e===void 0&&ee.set(t.strings,e=new K(t)),e}k(t){_t(this._$AH)||(this._$AH=[],this._$AR());let e=this._$AH,o,r=0;for(let i of t)r===e.length?e.push(o=new s(this.O(z()),this.O(z()),this,this.options)):o=e[r],o._$AI(i),r++;r<e.length&&(this._$AR(o&&o._$AB.nextSibling,r),e.length=r)}_$AR(t=this._$AA.nextSibling,e){var o;for((o=this._$AP)==null?void 0:o.call(this,!1,!0,e);t!==this._$AB;){let r=Jt(t).nextSibling;Jt(t).remove(),t=r}}setConnected(t){var e;this._$AM===void 0&&(this._$Cv=t,(e=this._$AP)==null||e.call(this,t))}},H=class{get tagName(){return this.element.tagName}get _$AU(){return this._$AM._$AU}constructor(t,e,o,r,i){this.type=1,this._$AH=v,this._$AN=void 0,this.element=t,this.name=e,this._$AM=r,this.options=i,o.length>2||o[0]!==""||o[1]!==""?(this._$AH=Array(o.length-1).fill(new String),this.strings=o):this._$AH=v}_$AI(t,e=this,o,r){let i=this.strings,n=!1;if(i===void 0)t=O(this,t,e,0),n=!W(t)||t!==this._$AH&&t!==R,n&&(this._$AH=t);else{let c=t,l,d;for(t=i[0],l=0;l<i.length-1;l++)d=O(this,c[o+l],e,l),d===R&&(d=this._$AH[l]),n||(n=!W(d)||d!==this._$AH[l]),d===v?t=v:t!==v&&(t+=(d!=null?d:"")+i[l+1]),this._$AH[l]=d}n&&!r&&this.j(t)}j(t){t===v?this.element.removeAttribute(this.name):this.element.setAttribute(this.name,t!=null?t:"")}},bt=class extends H{constructor(){super(...arguments),this.type=3}j(t){this.element[this.name]=t===v?void 0:t}},$t=class extends H{constructor(){super(...arguments),this.type=4}j(t){this.element.toggleAttribute(this.name,!!t&&t!==v)}},yt=class extends H{constructor(t,e,o,r,i){super(t,e,o,r,i),this.type=5}_$AI(t,e=this){var n;if((t=(n=O(this,t,e,0))!=null?n:v)===R)return;let o=this._$AH,r=t===v&&o!==v||t.capture!==o.capture||t.once!==o.once||t.passive!==o.passive,i=t!==v&&(o===v||r);r&&this.element.removeEventListener(this.name,this,o),i&&this.element.addEventListener(this.name,this,t),this._$AH=t}handleEvent(t){var e,o;typeof this._$AH=="function"?this._$AH.call((o=(e=this.options)==null?void 0:e.host)!=null?o:this.element,t):this._$AH.handleEvent(t)}},xt=class{constructor(t,e,o){this.element=t,this.type=6,this._$AN=void 0,this._$AM=e,this.options=o}get _$AU(){return this._$AM._$AU}_$AI(t){O(this,t)}};var ut=q.litHtmlPolyfillSupport,se;ut==null||ut(K,Z),((se=q.litHtmlVersions)!=null?se:q.litHtmlVersions=[]).push("3.3.2");var ie=(s,t,e)=>{var i,n;let o=(i=e==null?void 0:e.renderBefore)!=null?i:t,r=o._$litPart$;if(r===void 0){let c=(n=e==null?void 0:e.renderBefore)!=null?n:null;o._$litPart$=r=new Z(t.insertBefore(z(),c),c,void 0,e!=null?e:{})}return r._$AI(s),r};var U=globalThis,S=class extends w{constructor(){super(...arguments),this.renderOptions={host:this},this._$Do=void 0}createRenderRoot(){var e,o;let t=super.createRenderRoot();return(o=(e=this.renderOptions).renderBefore)!=null||(e.renderBefore=t.firstChild),t}update(t){let e=this.render();this.hasUpdated||(this.renderOptions.isConnected=this.isConnected),super.update(t),this._$Do=ie(e,this.renderRoot,this.renderOptions)}connectedCallback(){var t;super.connectedCallback(),(t=this._$Do)==null||t.setConnected(!0)}disconnectedCallback(){var t;super.disconnectedCallback(),(t=this._$Do)==null||t.setConnected(!1)}render(){return R}},le;S._$litElement$=!0,S.finalized=!0,(le=U.litElementHydrateSupport)==null||le.call(U,{LitElement:S});var Ct=U.litElementPolyfillSupport;Ct==null||Ct({LitElement:S});var ce;((ce=U.litElementVersions)!=null?ce:U.litElementVersions=[]).push("4.2.2");var pe=s=>(t,e)=>{e!==void 0?e.addInitializer(()=>{customElements.define(s,t)}):customElements.define(s,t)};var as={attribute:!0,type:String,converter:V,reflect:!1,hasChanged:rt},ns=(s=as,t,e)=>{let{kind:o,metadata:r}=e,i=globalThis.litPropertyMetadata.get(r);if(i===void 0&&globalThis.litPropertyMetadata.set(r,i=new Map),o==="setter"&&((s=Object.create(s)).wrapped=!0),i.set(e.name,s),o==="accessor"){let{name:n}=e;return{set(c){let l=t.get.call(this);t.set.call(this,c),this.requestUpdate(n,l,s,!0,c)},init(c){return c!==void 0&&this.C(n,void 0,s,c),c}}}if(o==="setter"){let{name:n}=e;return function(c){let l=this[n];t.call(this,c),this.requestUpdate(n,l,s,!0,c)}}throw Error("Unsupported decorator location: "+o)};function y(s){return(t,e)=>typeof e=="object"?ns(s,t,e):((o,r,i)=>{let n=r.hasOwnProperty(i);return r.constructor.createProperty(i,o),n?Object.getOwnPropertyDescriptor(r,i):void 0})(s,t,e)}function N(s){return y({...s,state:!0,attribute:!1})}var J="lit-localize-status";var is=(s,...t)=>({strTag:!0,strings:s,values:t}),x=is,de=s=>typeof s!="string"&&"strTag"in s,it=(s,t,e)=>{let o=s[0];for(let r=1;r<s.length;r++)o+=t[e?e[r-1]:r-1],o+=s[r];return o};var X=(s=>de(s)?it(s.strings,s.values):s);var p=X,me=!1;function At(s){if(me)throw new Error("lit-localize can only be configured once");p=s,me=!0}var Lt=class{constructor(t){this.__litLocalizeEventHandler=e=>{e.detail.status==="ready"&&this.host.requestUpdate()},this.host=t}hostConnected(){window.addEventListener(J,this.__litLocalizeEventHandler)}hostDisconnected(){window.removeEventListener(J,this.__litLocalizeEventHandler)}},ls=s=>s.addController(new Lt(s)),he=ls;var fe=()=>(s,t)=>(s.addInitializer(he),s);var G=class{constructor(){this.settled=!1,this.promise=new Promise((t,e)=>{this._resolve=t,this._reject=e})}resolve(t){this.settled=!0,this._resolve(t)}reject(t){this.settled=!0,this._reject(t)}};var C=[];for(let s=0;s<256;s++)C[s]=(s>>4&15).toString(16)+(s&15).toString(16);function ve(s){let t=0,e=8997,o=0,r=33826,i=0,n=40164,c=0,l=52210;for(let d=0;d<s.length;d++)e^=s.charCodeAt(d),t=e*435,o=r*435,i=n*435,c=l*435,i+=e<<8,c+=r<<8,o+=t>>>16,e=t&65535,i+=o>>>16,r=o&65535,l=c+(i>>>16)&65535,n=i&65535;return C[l>>8]+C[l&255]+C[n>>8]+C[n&255]+C[r>>8]+C[r&255]+C[e>>8]+C[e&255]}var cs="",ps="h",ds="s";function ue(s,t){return(t?ps:ds)+ve(typeof s=="string"?s:s.join(cs))}var ge=new WeakMap,be=new Map;function $e(s,t,e){var o;if(s){let r=(o=e==null?void 0:e.id)!=null?o:ms(t),i=s[r];if(i){if(typeof i=="string")return i;if("strTag"in i)return it(i.strings,t.values,i.values);{let n=ge.get(i);return n===void 0&&(n=i.values,ge.set(i,n)),{...i,values:n.map(c=>t.values[c])}}}}return X(t)}function ms(s){let t=typeof s=="string"?s:s.strings,e=be.get(t);return e===void 0&&(e=ue(t,typeof s!="string"&&!("strTag"in s)),be.set(t,e)),e}function Et(s){window.dispatchEvent(new CustomEvent(J,{detail:s}))}var ct="",Q,ye,pt,St,xe,B=new G;B.resolve();var lt=0,_e=s=>(At(((t,e)=>$e(xe,t,e))),ct=ye=s.sourceLocale,pt=new Set(s.targetLocales),pt.add(s.sourceLocale),St=s.loadLocale,{getLocale:hs,setLocale:fs}),hs=()=>ct,fs=s=>{if(s===(Q!=null?Q:ct))return B.promise;if(!pt||!St)throw new Error("Internal error");if(!pt.has(s))throw new Error("Invalid locale code");lt++;let t=lt;return Q=s,B.settled&&(B=new G),Et({status:"loading",loadingLocale:s}),(s===ye?Promise.resolve({templates:void 0}):St(s)).then(o=>{lt===t&&(ct=s,Q=void 0,xe=o.templates,Et({status:"ready",readyLocale:s}),B.resolve())},o=>{lt===t&&(Et({status:"error",errorLocale:s,errorMessage:o.toString()}),B.reject(o))}),B.promise};var we=["da","en","fi","sv"],Ce=["da","en","fi","no","sv"];var Pt={};et(Pt,{templates:()=>vs});var vs={h016c5411387adb7b:a`${0} <span>Confirm</span>`,h0b45fb4dd22412db:a`Continue as ${0}`,h182158bd2ab6fee2:a`${0}
                <span>Continue as ${1}</span>`,h1fac0cb9c13eef5e:a`${0} <span>Buy now</span>`,h27fa5bc1830c24fe:a`${0} <span>Log in</span>`,h35f5881498b838de:a`<span>Buy now with </span>${0}`,h3cd66d895363ecf8:a`${0} <span>Continue as ${1}</span>`,h5b8dfcf8b674f52a:a`<span>Donate with </span>${0}`,h776546c02f5d686e:a`${0} <span>Donate</span>`,h7ddf1f684b62d846:a`Confirm`,h845f0251306f0133:a`<span>Confirm with </span>${0}`,h8a809d54ecbeeb11:a`<span>Register with </span>${0}`,h915a470a464b727a:a`<span>Pay with </span>${0}`,ha5401929e733e3f5:a`Donate`,ha909607a61f6aad1:a`${0} <span>Register</span>`,hc028d0ea5b6ec27a:a`<span>Log in with </span>${0}`,hc1e276dd54f4ca70:a`Continue`,hc920712031fd1d41:a`${0} <span>Continue</span>`,hcf842888f1c5bcf4:a`${0} <span>Express</span>`,hf0e9543eeebb73a9:a`<span>Continue with </span>${0}`,hf17cd8a7564d05e2:a`${0} <span>Pay</span>`,s1792f48147e210a5:"Buy now",s21aa0c8945fd66cf:"MobilePay",s485ead2f3d011f25:"Log in",s6d348e4eb36cd5ed:x`Buy now with ${0} Express`,s88573d262f479e00:"Register",scacb8dc5183f0b51:"Pay",sfa4fda26baa247af:"Express"};var kt={};et(kt,{templates:()=>us});var us={h016c5411387adb7b:a`${0} <span>Vahvista</span>`,h0b45fb4dd22412db:a`Jatka nimellä ${0}`,h182158bd2ab6fee2:a`${0}
                <span>Jatka nimellä ${1}</span>`,h1fac0cb9c13eef5e:a`${0} <span>Osta nyt</span>`,h27fa5bc1830c24fe:a`${0} <span>Kirjaudu sisään</span>`,h35f5881498b838de:a`<span>Osta nyt </span>${0}`,h3cd66d895363ecf8:a`${0} <span>Jatka nimellä ${1}</span>`,h5b8dfcf8b674f52a:a`<span>Lahjoita </span>${0}`,h776546c02f5d686e:a`${0} <span>Lahjoita</span>`,h7ddf1f684b62d846:a`Vahvista`,h845f0251306f0133:a`<span>Vahvista </span>${0}`,h8a809d54ecbeeb11:a`<span>Rekisteröidy </span>${0}`,h915a470a464b727a:a`<span>Maksa </span>${0}`,ha5401929e733e3f5:a`Lahjoita`,ha909607a61f6aad1:a`${0} <span>Rekisteröidy</span>`,hc028d0ea5b6ec27a:a`<span>Kirjaudu sisään </span>${0}`,hc1e276dd54f4ca70:a`Jatka`,hc920712031fd1d41:a`${0} <span>Jatka</span>`,hcf842888f1c5bcf4:a`${0} <span>Express</span>`,hf0e9543eeebb73a9:a`<span>Jatka </span>${0}`,hf17cd8a7564d05e2:a`${0} <span>Maksa</span>`,s1792f48147e210a5:"Osta nyt",s21aa0c8945fd66cf:"MobilePaylla",s485ead2f3d011f25:"Kirjaudu sis\xE4\xE4n",s6d348e4eb36cd5ed:x`Osta nyt ${0} Express`,s88573d262f479e00:"Rekister\xF6idy",scacb8dc5183f0b51:"Maksa",sfa4fda26baa247af:"Express"};var Tt={};et(Tt,{templates:()=>gs});var gs={h016c5411387adb7b:a`${0} <span>Bekræft</span>`,h0b45fb4dd22412db:a`Fortsæt som ${0}`,h182158bd2ab6fee2:a`${0}
                <span>Fortsæt som ${1}</span>`,h1fac0cb9c13eef5e:a`${0} <span>Køb nu</span>`,h27fa5bc1830c24fe:a`${0} <span>Log ind</span>`,h35f5881498b838de:a`<span>Køb nu med </span>${0}`,h3cd66d895363ecf8:a`${0} <span>Fortsæt som ${1}</span>`,h5b8dfcf8b674f52a:a`<span>Doner med </span>${0}`,h776546c02f5d686e:a`${0} <span>Doner</span>`,h7ddf1f684b62d846:a`Bekræft`,h845f0251306f0133:a`<span>Bekræft med </span>${0}`,h8a809d54ecbeeb11:a`<span>Registrer med </span>${0}`,h915a470a464b727a:a`<span>Betal med </span>${0}`,ha5401929e733e3f5:a`Doner`,ha909607a61f6aad1:a`${0} <span>Registrer</span>`,hc028d0ea5b6ec27a:a`<span>Log ind med </span>${0}`,hc1e276dd54f4ca70:a`Fortsæt`,hc920712031fd1d41:a`${0} <span>Fortsæt</span>`,hcf842888f1c5bcf4:a`${0} <span>Express</span>`,hf0e9543eeebb73a9:a`<span>Fortsæt med </span>${0}`,hf17cd8a7564d05e2:a`${0} <span>Betal</span>`,s1792f48147e210a5:"K\xF8b nu",s21aa0c8945fd66cf:"MobilePay",s485ead2f3d011f25:"Log ind",s6d348e4eb36cd5ed:x`Køb nu med ${0} Express`,s88573d262f479e00:"Registrer",scacb8dc5183f0b51:"Betal",sfa4fda26baa247af:"Express"};var Mt={};et(Mt,{templates:()=>bs});var bs={h016c5411387adb7b:a`${0} <span>Bekräfta</span>`,h0b45fb4dd22412db:a`Fortsätt som ${0}`,h182158bd2ab6fee2:a`${0}
                <span>Fortsätt som ${1}</span>`,h1fac0cb9c13eef5e:a`${0} <span>Köp nu</span>`,h27fa5bc1830c24fe:a`${0} <span>Logga in</span>`,h35f5881498b838de:a`<span>Köp nu med </span>${0}`,h3cd66d895363ecf8:a`${0} <span>Fortsätt som ${1}</span>`,h5b8dfcf8b674f52a:a`<span>Bidra med </span>${0}`,h776546c02f5d686e:a`${0} <span>Bidra</span>`,h7ddf1f684b62d846:a`Bekräfta`,h845f0251306f0133:a`<span>Bekräfta med </span>${0}`,h8a809d54ecbeeb11:a`<span>Registrera med </span>${0}`,h915a470a464b727a:a`<span>Betala med </span>${0}`,ha5401929e733e3f5:a`Bidra`,ha909607a61f6aad1:a`${0} <span>Registrera</span>`,hc028d0ea5b6ec27a:a`<span>Logga in med </span>${0}`,hc1e276dd54f4ca70:a`Fortsätt`,hc920712031fd1d41:a`${0} <span>Fortsätt</span>`,hcf842888f1c5bcf4:a`${0} <span>Express</span>`,hf0e9543eeebb73a9:a`<span>Fortsätt med </span>${0}`,hf17cd8a7564d05e2:a`${0} <span>Betala</span>`,s1792f48147e210a5:"K\xF6p nu",s21aa0c8945fd66cf:"MobilePay",s485ead2f3d011f25:"Logga in",s6d348e4eb36cd5ed:x`Köp nu med ${0} Express`,s88573d262f479e00:"Registrera",scacb8dc5183f0b51:"Betala",sfa4fda26baa247af:"Express"};var $s={en:Pt,da:Tt,fi:kt,sv:Mt},{setLocale:Ae}=_e({sourceLocale:"no",targetLocales:we,loadLocale:s=>Promise.resolve($s[s])}),Le=s=>Ce.includes(s);var Ut=()=>a`<svg
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
  </svg> `;var Ee=s=>a`<span class="mobilepay-logo">${s("MobilePay")}</span>`;var Bt=a`<svg
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
</svg>`;var Se=a`
  <svg width="15" height="16" viewBox="0 0 15 16" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path
      id="Logo"
      fill-rule="evenodd"
      clip-rule="evenodd"
      d="M7.72036 1.44983L8.35393 2.98698C8.35393 2.98698 8.11306 3.08681 7.95378 3.15064C7.7945 3.21447 7.49814 3.35908 7.49814 3.35908L6.85775 1.80537C6.75897 1.56571 6.48579 1.45101 6.24758 1.54919L1.87412 3.3518C1.63592 3.44998 1.5229 3.72386 1.62168 3.96352L5.83868 14.1947C5.93746 14.4344 6.21064 14.5491 6.44884 14.4509L10.8223 12.6483C11.0605 12.5501 11.1735 12.2762 11.0747 12.0366L10.3945 10.3862C10.3945 10.3862 10.7176 10.3827 10.8922 10.3873C11.0576 10.3915 11.4145 10.4126 11.4145 10.4126L11.9374 11.681C12.2337 12.4 11.8946 13.2216 11.18 13.5162L6.80656 15.3188C6.09195 15.6133 5.27241 15.2692 4.97607 14.5503L0.759068 4.31906C0.462724 3.60008 0.801796 2.77845 1.51641 2.48391L5.88986 0.681302C6.60447 0.386762 7.42401 0.730841 7.72036 1.44983ZM5.17174 5.70043L6.78035 9.52946V5.85984C6.78035 5.85984 8.05165 5.17907 9.47208 4.98728C10.8925 4.79549 12.7012 5.04912 12.7012 5.04912L11.8263 2.95879C11.8263 2.95879 9.92507 2.83601 8.20402 3.57138C6.48298 4.30674 5.17174 5.70043 5.17174 5.70043ZM7.28939 10.4171C7.28939 10.4171 9.24445 9.82126 10.5974 9.8179C12.8343 9.81236 14.3482 10.7059 14.3482 10.7059V6.37386C14.3482 6.37386 12.8266 5.58593 10.9096 5.53265C8.99267 5.47938 7.28939 6.31345 7.28939 6.31345V10.4171Z"
      fill="currentColor"
    />
  </svg>
`;var Ot=a`<svg
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
</svg>`;var Pe=a`<svg
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
</svg>`;var Ht=a`<svg
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
</svg>`;var Nt=P`
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
`;var ke=P`
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
`;var Y=s=>s/16,Te=P`
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

    --vm-text-md: ${Y(18.5)}rem;
    --vm-line-height: ${Y(12)}rem;

    --vm-content-height: max(var(--vm-line-height), var(--vm-logo-space));

    --vm-font-medium: 500;
    --vm-font-semibold: 700;
    --vm-font-vipps: Vipps, 'SF Pro Text', Arial, sans-serif;
    --vm-font-mp: 'Paytype', 'SF Pro Text', Arial, sans-serif;
    --vm-rounded-sm: ${Y(5)}rem;
    --vm-rounded-full: 99999px;
    --vm-padding-y: calc((44px - var(--vm-content-height)) / 2);
    --vm-padding-x: 24px;
    --vm-text-kerning: -0.2px;

    --vm-btn-radius: var(--vm-rounded-sm);
    --vm-btn-weight: var(--vm-font-semibold);
    --vm-btn-font: var(--vm-font-vipps);
    --vm-btn-column-gap: ${Y(6)}rem;
    --vm-btn-row-gap: ${Y(6)}rem;

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
`;var ys=["orange","purple","stroked"],Me,Re,Ue,Be,Oe,He,Ne,je,Fe,Ie,Ve,De,qe,ze,We,f=class extends S{constructor(){super();j(this,"internals");j(this,"isSupportElementInternals",!1);b(this,Me,Ut);b(this,Re,Bt);b(this,Ue,Ot);b(this,Be,"");b(this,Oe,null);b(this,He,"no");b(this,Ne,!0);b(this,je,!1);b(this,Fe,"button");b(this,Ie,!1);b(this,Ve,"vipps");b(this,De,"buy");b(this,qe,"");b(this,ze,"primary");b(this,We,!1);if(this.attachInternals&&(this.internals=this.attachInternals(),this.isSupportElementInternals=!0),!document.head.querySelector("style[data-vipps-mobilepay-button-fonts]")){let e=document.createElement("style");e.setAttribute("data-vipps-mobilepay-button-fonts",""),e.innerHTML=ke.cssText,document.head.appendChild(e)}this.rejectVippsForFinland(),this.updateLogo(),this.textMessage=this.getTextContent(),this.buttonAriaLabel=this.getButtonAriaLabel()}get brandLogo(){return g(this,Me)}set brandLogo(e){$(this,Me,e)}get miniBrandLogo(){return g(this,Re)}set miniBrandLogo(e){$(this,Re,e)}get compactBrandLogo(){return g(this,Ue)}set compactBrandLogo(e){$(this,Ue,e)}get textMessage(){return g(this,Be)}set textMessage(e){$(this,Be,e)}get buttonAriaLabel(){return g(this,Oe)}set buttonAriaLabel(e){$(this,Oe,e)}get language(){return g(this,He)}set language(e){$(this,He,e)}get branded(){return g(this,Ne)}set branded(e){$(this,Ne,e)}get compact(){return g(this,je)}set compact(e){$(this,je,e)}get type(){return g(this,Fe)}set type(e){$(this,Fe,e)}get disabled(){return g(this,Ie)}set disabled(e){$(this,Ie,e)}get brand(){return g(this,Ve)}set brand(e){$(this,Ve,e)}get verb(){return g(this,De)}set verb(e){$(this,De,e)}get vmpContinueAsFirstName(){return g(this,qe)}set vmpContinueAsFirstName(e){$(this,qe,e)}get variant(){return g(this,ze)}set variant(e){$(this,ze,e)}get loading(){return g(this,We)}set loading(e){$(this,We,e)}rejectVippsForFinland(){this.language.toLowerCase()==="fi"&&this.brand.toLowerCase()==="vipps"&&(console.info(`[Vipps MobilePay Button]
Language ${this.language} is not supported when using brand Vipps.`),this.language="en")}updateLogo(){let e=this.brand.toLowerCase()==="mobilepay";this.brandLogo=e?Ee:Ut,this.miniBrandLogo=e?Se:Bt,this.compactBrandLogo=e?Pe:Ot}getTextContent(){switch(this.verb.toLowerCase()){case"pay":return this.compact?p(a`${this.compactBrandLogo} <span>Betal</span>`):this.branded?p(a`<span>Betal med </span>${this.brandLogo(p)}`):p("Betal");case"login":return this.compact?p(a`${this.compactBrandLogo} <span>Logg inn</span>`):this.branded?p(a`<span>Logg inn med </span>${this.brandLogo(p)}`):p("Logg inn");case"register":return this.compact?p(a`${this.compactBrandLogo} <span>Registrer</span>`):this.branded?p(a`<span>Registrer med </span>${this.brandLogo(p)}`):p("Registrer");case"continue":return this.vmpContinueAsFirstName?this.compact?p(a`${this.compactBrandLogo}
                <span>Fortsett som ${this.vmpContinueAsFirstName}</span>`):this.branded?p(a`${this.miniBrandLogo} <span>Fortsett som ${this.vmpContinueAsFirstName}</span>`):p(a`Fortsett som ${this.vmpContinueAsFirstName}`):this.compact?p(a`${this.compactBrandLogo} <span>Fortsett</span>`):this.branded?p(a`<span>Fortsett med </span>${this.brandLogo(p)}`):p(a`Fortsett`);case"confirm":return this.compact?p(a`${this.compactBrandLogo} <span>Bekreft</span>`):this.branded?p(a`<span>Bekreft med </span>${this.brandLogo(p)}`):p(a`Bekreft`);case"donate":return this.compact?p(a`${this.compactBrandLogo} <span>Bidra</span>`):this.branded?p(a`<span>Bidra med </span>${this.brandLogo(p)}`):p(a`Bidra`);case"express":if(this.compact)return p(a`${this.compactBrandLogo} <span>Express</span>`);if(this.branded){let e=this.brand.toLowerCase()==="mobilepay"?a`<span class="mobilepay-logo">MobilePay</span>`:this.brandLogo(p);return a`${e}<span> Express</span>`}return p("Express");default:return this.compact?p(a`${this.compactBrandLogo} <span>Kjøp nå</span>`):this.branded?p(a`<span>Kjøp nå med </span>${this.brandLogo(p)}`):p("Kj\xF8p n\xE5")}}getButtonAriaLabel(){if(this.verb.toLowerCase()!=="express")return null;let e=this.brand.toLowerCase()==="mobilepay"?"MobilePay":"Vipps";return p(x`Kjøp nå med ${e} Express`)}getForm(){var e;return this.isSupportElementInternals?(e=this.internals)==null?void 0:e.form:this.closest("form")}willUpdate(e){this.rejectVippsForFinland(),e.has("brand")&&this.updateLogo(),this.textMessage=this.getTextContent(),this.buttonAriaLabel=this.getButtonAriaLabel(),ys.includes(this.variant)&&console.warn(`[Vipps MobilePay Button]
Variant "${this.variant}" is marked as deprecated and will be removed in the future.
See https://developer.vippsmobilepay.com/docs/knowledge-base/design-guidelines/buttons/ for more information.`),e.has("language")&&Le(this.language)&&Ae(this.language)}click(){let e=this.getForm();if(this.type!=="submit"||!e)return;if(e.requestSubmit){e.requestSubmit();return}let o=document.createElement("input");o.type="submit",o.style.display="none",e.appendChild(o),o.click(),e.removeChild(o)}connectedCallback(){super.connectedCallback();let e=this.getForm();this.type!=="submit"||!e||(this.addEventListener("click",()=>{this.click()}),e.addEventListener("keypress",o=>{o.code==="Enter"&&this.click()}))}render(){var e;return a` <button
      type="${this.type}"
      ?disabled=${this.disabled}
      aria-busy="${this.loading}"
      aria-label=${(e=this.buttonAriaLabel)!=null?e:v}
    >
      ${this.loading?Ht:v} ${this.textMessage}
    </button>`}};Me=new WeakMap,Re=new WeakMap,Ue=new WeakMap,Be=new WeakMap,Oe=new WeakMap,He=new WeakMap,Ne=new WeakMap,je=new WeakMap,Fe=new WeakMap,Ie=new WeakMap,Ve=new WeakMap,De=new WeakMap,qe=new WeakMap,ze=new WeakMap,We=new WeakMap,j(f,"styles",[Te,Nt]),j(f,"formAssociated",!0),u([N()],f.prototype,"brandLogo",1),u([N()],f.prototype,"miniBrandLogo",1),u([N()],f.prototype,"compactBrandLogo",1),u([N()],f.prototype,"textMessage",1),u([N()],f.prototype,"buttonAriaLabel",1),u([y({type:String})],f.prototype,"language",1),u([y({converter:e=>e==="true"||e===""})],f.prototype,"branded",1),u([y({converter:e=>e==="true"||e===""})],f.prototype,"compact",1),u([y({type:String})],f.prototype,"type",1),u([y({converter:e=>e==="true"||e===""})],f.prototype,"disabled",1),u([y({type:String})],f.prototype,"brand",1),u([y({type:String})],f.prototype,"verb",1),u([y({type:String})],f.prototype,"vmpContinueAsFirstName",1),u([y({type:String})],f.prototype,"variant",1),u([y({converter:e=>e==="true"||e===""})],f.prototype,"loading",1),f=u([pe("vipps-mobilepay-button"),fe()],f);var xs=f;})();
/*! Bundled license information:

@lit/reactive-element/css-tag.js:
  (**
   * @license
   * Copyright 2019 Google LLC
   * SPDX-License-Identifier: BSD-3-Clause
   *)

@lit/reactive-element/reactive-element.js:
lit-html/lit-html.js:
lit-element/lit-element.js:
@lit/reactive-element/decorators/custom-element.js:
@lit/reactive-element/decorators/property.js:
@lit/reactive-element/decorators/state.js:
@lit/reactive-element/decorators/event-options.js:
@lit/reactive-element/decorators/base.js:
@lit/reactive-element/decorators/query.js:
@lit/reactive-element/decorators/query-all.js:
@lit/reactive-element/decorators/query-async.js:
@lit/reactive-element/decorators/query-assigned-nodes.js:
  (**
   * @license
   * Copyright 2017 Google LLC
   * SPDX-License-Identifier: BSD-3-Clause
   *)

lit-html/is-server.js:
  (**
   * @license
   * Copyright 2022 Google LLC
   * SPDX-License-Identifier: BSD-3-Clause
   *)

@lit/reactive-element/decorators/query-assigned-elements.js:
@lit/localize/internal/locale-status-event.js:
@lit/localize/internal/str-tag.js:
@lit/localize/internal/types.js:
@lit/localize/internal/default-msg.js:
@lit/localize/internal/localized-controller.js:
@lit/localize/internal/localized-decorator.js:
@lit/localize/internal/runtime-msg.js:
@lit/localize/init/runtime.js:
@lit/localize/init/transform.js:
  (**
   * @license
   * Copyright 2021 Google LLC
   * SPDX-License-Identifier: BSD-3-Clause
   *)

@lit/localize/internal/deferred.js:
@lit/localize/internal/id-generation.js:
@lit/localize/lit-localize.js:
  (**
   * @license
   * Copyright 2020 Google LLC
   * SPDX-License-Identifier: BSD-3-Clause
   *)

@lit/localize/internal/fnv1a64.js:
  (**
   * @license
   * Copyright 2014 Travis Webb
   * SPDX-License-Identifier: MIT
   *)
*/
