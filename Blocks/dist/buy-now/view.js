import*as t from"@wordpress/interactivity";var o={d:(t,e)=>{for(var n in e)o.o(e,n)&&!o.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:e[n]})},o:(t,o)=>Object.prototype.hasOwnProperty.call(t,o)};const e=(n={getContext:()=>t.getContext,store:()=>t.store},r={},o.d(r,n),r);var n,r;(0,e.store)("woo-vipps",{callbacks:{init:()=>{const{pid:t}=(0,e.getContext)();console.log(" init called for "+t),document.body.dispatchEvent(new Event("vippsInit"))},watch:()=>{const{pid:t}=(0,e.getContext)();console.log(" watch called for "+t)}}});