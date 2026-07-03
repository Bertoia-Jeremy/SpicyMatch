/**
 * Bundled by jsDelivr using Rollup v4.62.2 and esbuild v0.28.1.
 * Original file: /npm/altcha@3.1.0/dist/main/altcha.js
 *
 * Do NOT use SRI with dynamically generated files! More information: https://www.jsdelivr.com/using-sri-with-dynamic-files
 */
var Ti={},$i;function Ds(){if($i)return Ti;$i=1;const Vr=!1;var jr=Array.isArray,Ai=Array.prototype.indexOf,It=Array.prototype.includes,Ri=Array.from,dn=Object.keys,zt=Object.defineProperty,Ot=Object.getOwnPropertyDescriptor,Ii=Object.getOwnPropertyDescriptors,Oi=Object.prototype,Pi=Array.prototype,Br=Object.getPrototypeOf,zr=Object.isExtensible;const ct=()=>{};function Li(e){for(var t=0;t<e.length;t++)e[t]()}function Hr(){var e,t,n=new Promise((r,a)=>{e=r,t=a});return{promise:n,resolve:e,reject:t}}const fe=2,Pt=4,vn=8,Vn=1<<24,Ue=16,ze=32,tt=64,jn=128,Re=512,oe=1024,ue=2048,He=4096,Ie=8192,Fe=16384,ut=32768,Bn=1<<25,ft=65536,pn=1<<17,Mi=1<<18,mt=1<<19,Di=1<<20,yt=65536,gn=1<<21,Lt=1<<22,ht=1<<23,Mt=Symbol("$state"),Ni=Symbol("legacy props"),Ui=Symbol(""),Kr=Symbol("attributes"),zn=Symbol("class"),Hn=Symbol("style"),Kn=Symbol("text"),Ht=Symbol("form reset"),bn=new class extends Error{name="StaleReactionError";message="The reaction that called `getAbortSignal()` was re-run or destroyed"},Kt=!!globalThis.document?.contentType&&globalThis.document.contentType.includes("xml"),Yt=3,Gt=8;function Yr(e){return e===this.v}function Gr(e,t){return e!=e?t==t:e!==t||e!==null&&typeof e=="object"||typeof e=="function"}function Fi(e){return!Gr(e,this.v)}function Vi(e){throw new Error("https://svelte.dev/e/lifecycle_outside_component")}function ji(){throw new Error("https://svelte.dev/e/async_derived_orphan")}function Bi(e){throw new Error("https://svelte.dev/e/effect_in_teardown")}function zi(){throw new Error("https://svelte.dev/e/effect_in_unowned_derived")}function Hi(e){throw new Error("https://svelte.dev/e/effect_orphan")}function Ki(){throw new Error("https://svelte.dev/e/effect_update_depth_exceeded")}function Yi(){throw new Error("https://svelte.dev/e/hydration_failed")}function Gi(){throw new Error("https://svelte.dev/e/state_descriptors_fixed")}function qi(){throw new Error("https://svelte.dev/e/state_prototype_fixed")}function Wi(){throw new Error("https://svelte.dev/e/state_unsafe_mutation")}function Zi(){throw new Error("https://svelte.dev/e/svelte_boundary_reset_onerror")}let Ji=!1;const Xi=1,Qi=2,Yn="[",qr="[!",Wr="[?",Zr="]",wt={},ne=Symbol("uninitialized"),Jr="http://www.w3.org/1999/xhtml",eo="http://www.w3.org/2000/svg",to="http://www.w3.org/1998/Math/MathML",no="@attach";let ye=null;function Dt(e){ye=e}function nt(e,t=!1,n){ye={p:ye,i:!1,c:null,e:null,s:e,x:null,r:A,l:null}}function rt(e){var t=ye,n=t.e;if(n!==null){t.e=null;for(var r of n)Pa(r)}return e!==void 0&&(t.x=e),t.i=!0,ye=t.p,e??{}}function Xr(){return!0}let _t=[];function Qr(){var e=_t;_t=[],Li(e)}function at(e){if(_t.length===0&&!Zt){var t=_t;queueMicrotask(()=>{t===_t&&Qr()})}_t.push(e)}function ro(){for(;_t.length>0;)Qr()}function ao(){console.warn("https://svelte.dev/e/derived_inert")}function qt(e){console.warn("https://svelte.dev/e/hydration_mismatch")}function io(){console.warn("https://svelte.dev/e/select_multiple_invalid_value")}function oo(){console.warn("https://svelte.dev/e/svelte_boundary_reset_noop")}let I=!1;function Ke(e){I=e}let M;function ge(e){if(e===null)throw qt(),wt;return M=e}function kt(){return ge(Ge(M))}function Y(e){if(I){if(Ge(M)!==null)throw qt(),wt;M=e}}function Gn(e=1){if(I){for(var t=e,n=M;t--;)n=Ge(n);M=n}}function qn(e=!0){for(var t=0,n=M;;){if(n.nodeType===Gt){var r=n.data;if(r===Zr){if(t===0)return n;t-=1}else(r===Yn||r===qr||r[0]==="["&&!isNaN(Number(r.slice(1))))&&(t+=1)}var a=Ge(n);e&&n.remove(),n=a}}function ea(e){if(!e||e.nodeType!==Gt)throw qt(),wt;return e.data}function it(e){if(typeof e!="object"||e===null||Mt in e)return e;const t=Br(e);if(t!==Oi&&t!==Pi)return e;var n=new Map,r=jr(e),a=P(0),o=St,l=s=>{if(St===o)return s();var u=N,c=St;Oe(null),Ta(o);var h=s();return Oe(u),Ta(c),h};return r&&n.set("length",P(e.length)),new Proxy(e,{defineProperty(s,u,c){(!("value"in c)||c.configurable===!1||c.enumerable===!1||c.writable===!1)&&Gi();var h=n.get(u);return h===void 0?l(()=>{var v=P(c.value);return n.set(u,v),v}):w(h,c.value,!0),!0},deleteProperty(s,u){var c=n.get(u);if(c===void 0){if(u in s){const h=l(()=>P(ne));n.set(u,h),Xt(a)}}else w(c,ne),Xt(a);return!0},get(s,u,c){if(u===Mt)return e;var h=n.get(u),v=u in s;if(h===void 0&&(!v||Ot(s,u)?.writable)&&(h=l(()=>{var b=it(v?s[u]:ne),g=P(b);return g}),n.set(u,h)),h!==void 0){var d=i(h);return d===ne?void 0:d}return Reflect.get(s,u,c)},getOwnPropertyDescriptor(s,u){var c=Reflect.getOwnPropertyDescriptor(s,u);if(c&&"value"in c){var h=n.get(u);h&&(c.value=i(h))}else if(c===void 0){var v=n.get(u),d=v?.v;if(v!==void 0&&d!==ne)return{enumerable:!0,configurable:!0,value:d,writable:!0}}return c},has(s,u){if(u===Mt)return!0;var c=n.get(u),h=c!==void 0&&c.v!==ne||Reflect.has(s,u);if(c!==void 0||A!==null&&(!h||Ot(s,u)?.writable)){c===void 0&&(c=l(()=>{var d=h?it(s[u]):ne,b=P(d);return b}),n.set(u,c));var v=i(c);if(v===ne)return!1}return h},set(s,u,c,h){var v=n.get(u),d=u in s;if(r&&u==="length")for(var b=c;b<v.v;b+=1){var g=n.get(b+"");g!==void 0?w(g,ne):b in s&&(g=l(()=>P(ne)),n.set(b+"",g))}if(v===void 0)(!d||Ot(s,u)?.writable)&&(v=l(()=>P(void 0)),w(v,it(c)),n.set(u,v));else{d=v.v!==ne;var x=l(()=>it(c));w(v,x)}var $=Reflect.getOwnPropertyDescriptor(s,u);if($?.set&&$.set.call(h,c),!d){if(r&&typeof u=="string"){var L=n.get("length"),ce=Number(u);Number.isInteger(ce)&&ce>=L.v&&w(L,ce+1)}Xt(a)}return!0},ownKeys(s){i(a);var u=Reflect.ownKeys(s).filter(v=>{var d=n.get(v);return d===void 0||d.v!==ne});for(var[c,h]of n)h.v!==ne&&!(c in s)&&u.push(c);return u},setPrototypeOf(){qi()}})}function ta(e){try{if(e!==null&&typeof e=="object"&&Mt in e)return e[Mt]}catch{}return e}function lo(e,t){return Object.is(ta(e),ta(t))}var xt,Wn,na,ra,aa;function Zn(){if(xt===void 0){xt=window,Wn=document,na=/Firefox/.test(navigator.userAgent);var e=Element.prototype,t=Node.prototype,n=Text.prototype;ra=Ot(t,"firstChild").get,aa=Ot(t,"nextSibling").get,zr(e)&&(e[zn]=void 0,e[Kr]=null,e[Hn]=void 0,e.__e=void 0),zr(n)&&(n[Kn]=void 0)}}function Ye(e=""){return document.createTextNode(e)}function xe(e){return ra.call(e)}function Ge(e){return aa.call(e)}function X(e,t){if(!I)return xe(e);var n=xe(M);if(n===null)n=M.appendChild(Ye());else if(t&&n.nodeType!==Yt){var r=Ye();return n?.before(r),ge(r),r}return t&&mn(n),ge(n),n}function Nt(e,t=!1){if(!I){var n=xe(e);return n instanceof Comment&&n.data===""?Ge(n):n}if(t){if(M?.nodeType!==Yt){var r=Ye();return M?.before(r),ge(r),r}mn(M)}return M}function W(e,t=1,n=!1){let r=I?M:e;for(var a;t--;)a=r,r=Ge(r);if(!I)return r;if(n){if(r?.nodeType!==Yt){var o=Ye();return r===null?a?.after(o):r.before(o),ge(o),o}mn(r)}return ge(r),r}function so(e){e.textContent=""}function Jn(e,t,n){return document.createElementNS(t??Jr,e,void 0)}function mn(e){if(e.nodeValue.length<65536)return;let t=e.nextSibling;for(;t!==null&&t.nodeType===Yt;)t.remove(),e.nodeValue+=t.nodeValue,t=e.nextSibling}function ia(e){var t=A;if(t===null)return N.f|=ht,e;if((t.f&ut)===0&&(t.f&Pt)===0)throw e;dt(e,t)}function dt(e,t){for(;t!==null;){if((t.f&jn)!==0){if((t.f&ut)===0)throw e;try{t.b.error(e);return}catch(n){e=n}}t=t.parent}throw e}const co=-7169;function ee(e,t){e.f=e.f&co|t}function Xn(e){(e.f&Re)!==0||e.deps===null?ee(e,oe):ee(e,He)}function oa(e){if(e!==null)for(const t of e)(t.f&fe)===0||(t.f&yt)===0||(t.f^=yt,oa(t.deps))}function la(e,t,n){(e.f&ue)!==0?t.add(e):(e.f&He)!==0&&n.add(e),oa(e.deps),ee(e,oe)}function sa(e,t,n){if(e==null)return t(void 0),ct;const r=tn(()=>e.subscribe(t,n));return r.unsubscribe?()=>r.unsubscribe():r}const Ut=[];function uo(e,t=ct){let n=null;const r=new Set;function a(s){if(Gr(e,s)&&(e=s,n)){const u=!Ut.length;for(const c of r)c[1](),Ut.push(c,e);if(u){for(let c=0;c<Ut.length;c+=2)Ut[c][0](Ut[c+1]);Ut.length=0}}}function o(s){a(s(e))}function l(s,u=ct){const c=[s,u];return r.add(c),r.size===1&&(n=t(a,o)||ct),s(e),()=>{r.delete(c),r.size===0&&n&&(n(),n=null)}}return{set:a,update:o,subscribe:l}}function Wt(e){let t;return sa(e,n=>t=n)(),t}let Qn=Symbol("unmounted");function ca(e,t,n){const r=n[t]??={store:null,source:wa(void 0),unsubscribe:ct};if(r.store!==e&&!(Qn in n))if(r.unsubscribe(),r.store=e??null,e==null)r.source.v=void 0,r.unsubscribe=ct;else{var a=!0;r.unsubscribe=sa(e,o=>{a?r.source.v=o:w(r.source,o)}),a=!1}return e&&Qn in n?Wt(e):i(r.source)}function fo(){const e={};function t(){Sn(()=>{for(var n in e)e[n].unsubscribe();zt(e,Qn,{enumerable:!1,value:!0})})}return[e,t]}let er=null,Ft=null,O=null,tr=null,Ve=null,nr=null,Zt=!1,rr=!1,Vt=null,yn=null;var ua=0;let ho=1;class ot{id=ho++;#e=!1;linked=!0;#t=null;#n=null;async_deriveds=new Map;current=new Map;previous=new Map;unblocked=new Set;#s=new Set;#r=new Set;#o=new Set;#a=0;#i=new Map;#h=null;#l=[];#v=[];#d=new Set;#c=new Set;#u=new Map;#f=new Set;is_fork=!1;#b=!1;#_(){if(this.is_fork)return!0;for(const r of this.#i.keys()){for(var t=r,n=!1;t.parent!==null;){if(this.#u.has(t)){n=!0;break}t=t.parent}if(!n)return!0}return!1}skip_effect(t){this.#u.has(t)||this.#u.set(t,{d:[],m:[]}),this.#f.delete(t)}unskip_effect(t,n=r=>this.schedule(r)){var r=this.#u.get(t);if(r){this.#u.delete(t);for(var a of r.d)ee(a,ue),n(a);for(a of r.m)ee(a,He),n(a)}this.#f.add(t)}#g(){if(this.#e=!0,ua++>1e3&&(this.#w(),vo()),!this.#_()){for(const u of this.#d)this.#c.delete(u),ee(u,ue),this.schedule(u);for(const u of this.#c)ee(u,He),this.schedule(u)}const t=this.#l;this.#l=[],this.apply();var n=Vt=[],r=[],a=yn=[];for(const u of t)try{this.#k(u,n,r)}catch(c){throw va(u),c}if(O=null,a.length>0){var o=ot.ensure();for(const u of a)o.schedule(u)}if(Vt=null,yn=null,this.#_()){this.#p(r),this.#p(n);for(const[u,c]of this.#u)da(u,c);a.length>0&&O.#g();return}const l=this.#x();if(l){l.#m(this);return}this.#d.clear(),this.#c.clear();for(const u of this.#s)u(this);this.#s.clear(),tr=this,fa(r),fa(n),tr=null,this.#h?.resolve();var s=O;if(this.linked&&this.#a===0&&this.#w(),this.#l.length>0){s===null&&(s=this,this.#y());const u=s;u.#l.push(...this.#l.filter(c=>!u.#l.includes(c)))}s!==null&&s.#g()}#k(t,n,r){t.f^=oe;for(var a=t.first;a!==null;){var o=a.f,l=(o&(ze|tt))!==0,s=l&&(o&oe)!==0,u=s||(o&Ie)!==0||this.#u.has(a);if(!u&&a.fn!==null){l?a.f^=oe:(o&Pt)!==0?n.push(a):Qt(a)&&((o&Ue)!==0&&this.#c.add(a),jt(a));var c=a.first;if(c!==null){a=c;continue}}for(;a!==null;){var h=a.next;if(h!==null){a=h;break}a=a.parent}}}#x(){for(var t=this.#t;t!==null;){if(!t.is_fork){for(const[n,[,r]]of this.current)if(t.current.has(n)&&!r)return t}t=t.#t}return null}#m(t){for(const[r,a]of t.current)!this.previous.has(r)&&t.previous.has(r)&&this.previous.set(r,t.previous.get(r)),this.current.set(r,a);for(const[r,a]of t.async_deriveds){const o=this.async_deriveds.get(r);o&&a.promise.then(o.resolve)}const n=r=>{var a=r.reactions;if(a!==null)for(const s of a){var o=s.f;if((o&fe)!==0)n(s);else{var l=s;o&(Lt|Ue)&&!this.async_deriveds.has(l)&&(this.#c.delete(l),ee(l,ue),this.schedule(l))}}};for(const r of this.current.keys())n(r);this.oncommit(()=>t.discard()),t.#w(),O=this,this.#g()}#p(t){for(var n=0;n<t.length;n+=1)la(t[n],this.#d,this.#c)}capture(t,n,r=!1){t.v!==ne&&!this.previous.has(t)&&this.previous.set(t,t.v),(t.f&ht)===0&&(this.current.set(t,[n,r]),Ve?.set(t,n)),this.is_fork||(t.v=n)}activate(){O=this}deactivate(){O=null,Ve=null}flush(){try{rr=!0,O=this,this.#g()}finally{ua=0,nr=null,Vt=null,yn=null,rr=!1,O=null,Ve=null,Et.clear()}}discard(){for(const t of this.#r)t(this);this.#r.clear(),this.#o.clear(),this.#w()}register_created_effect(t){this.#v.push(t)}#E(){this.#w();for(let h=er;h!==null;h=h.#n){var t=h.id<this.id,n=[];for(const[v,[d,b]]of this.current){if(h.current.has(v)){var r=h.current.get(v)[0];if(t&&d!==r)h.current.set(v,[d,b]);else continue}n.push(v)}if(t)for(const[v,d]of this.async_deriveds){const b=h.async_deriveds.get(v);b&&d.promise.then(b.resolve)}if(h.#e){var a=[...h.current.keys()].filter(v=>!this.current.has(v));if(a.length===0)t&&h.discard();else if(n.length>0){if(t)for(const v of this.#f)h.unskip_effect(v,d=>{(d.f&(Ue|Lt))!==0?h.schedule(d):h.#p([d])});h.activate();var o=new Set,l=new Map;for(var s of n)ha(s,a,o,l);l=new Map;var u=[...h.current.keys()].filter(v=>this.current.has(v)?this.current.get(v)[0]!==v.v:!0);if(u.length>0)for(const v of this.#v)(v.f&(Fe|Ie|pn))===0&&ar(v,u,l)&&((v.f&(Lt|Ue))!==0?(ee(v,ue),h.schedule(v)):h.#d.add(v));if(h.#l.length>0){h.apply();for(var c of h.#l)h.#k(c,[],[]);h.#l=[]}h.deactivate()}}}}increment(t,n){if(this.#a+=1,t){let r=this.#i.get(n)??0;this.#i.set(n,r+1)}}decrement(t,n){if(this.#a-=1,t){let r=this.#i.get(n)??0;r===1?this.#i.delete(n):this.#i.set(n,r-1)}this.#b||(this.#b=!0,at(()=>{this.#b=!1,this.linked&&this.flush()}))}transfer_effects(t,n){for(const r of t)this.#d.add(r);for(const r of n)this.#c.add(r);t.clear(),n.clear()}oncommit(t){this.#s.add(t)}ondiscard(t){this.#r.add(t)}on_fork_commit(t){this.#o.add(t)}run_fork_commit_callbacks(){for(const t of this.#o)t(this);this.#o.clear()}settled(){return(this.#h??=Hr()).promise}static ensure(){if(O===null){const t=O=new ot;t.#y(),!rr&&!Zt&&at(()=>{t.#e||t.flush()})}return O}apply(){{Ve=null;return}}schedule(t){if(nr=t,t.b?.is_pending&&(t.f&(Pt|vn|Vn))!==0&&(t.f&ut)===0){t.b.defer_effect(t);return}for(var n=t;n.parent!==null;){n=n.parent;var r=n.f;if(Vt!==null&&n===A&&(N===null||(N.f&fe)===0))return;if((r&(tt|ze))!==0){if((r&oe)===0)return;n.f^=oe}}this.#l.push(n)}#y(){Ft===null?er=Ft=this:(Ft.#n=this,this.#t=Ft),Ft=this}#w(){var t=this.#t,n=this.#n;t===null?er=n:t.#n=n,n===null?Ft=t:n.#t=t,this.linked=!1}}function H(e){var t=Zt;Zt=!0;try{for(var n;;){if(ro(),O===null)return n;O.flush()}}finally{Zt=t}}function vo(){try{Ki()}catch(e){dt(e,nr)}}let lt=null;function fa(e){var t=e.length;if(t!==0){for(var n=0;n<t;){var r=e[n++];if((r.f&(Fe|Ie))===0&&Qt(r)&&(lt=new Set,jt(r),r.deps===null&&r.first===null&&r.nodes===null&&r.teardown===null&&r.ac===null&&Na(r),lt?.size>0)){Et.clear();for(const a of lt){if((a.f&(Fe|Ie))!==0)continue;const o=[a];let l=a.parent;for(;l!==null;)lt.has(l)&&(lt.delete(l),o.push(l)),l=l.parent;for(let s=o.length-1;s>=0;s--){const u=o[s];(u.f&(Fe|Ie))===0&&jt(u)}}lt.clear()}}lt=null}}function ha(e,t,n,r){if(!n.has(e)&&(n.add(e),e.reactions!==null))for(const a of e.reactions){const o=a.f;(o&fe)!==0?ha(a,t,n,r):(o&(Lt|Ue))!==0&&(o&ue)===0&&ar(a,t,r)&&(ee(a,ue),ir(a))}}function ar(e,t,n){const r=n.get(e);if(r!==void 0)return r;if(e.deps!==null)for(const a of e.deps){if(It.call(t,a))return!0;if((a.f&fe)!==0&&ar(a,t,n))return n.set(a,!0),!0}return n.set(e,!1),!1}function ir(e){O.schedule(e)}function da(e,t){if(!((e.f&ze)!==0&&(e.f&oe)!==0)){(e.f&ue)!==0?t.d.push(e):(e.f&He)!==0&&t.m.push(e),ee(e,oe);for(var n=e.first;n!==null;)da(n,t),n=n.next}}function va(e){ee(e,oe);for(var t=e.first;t!==null;)va(t),t=t.next}function po(e){let t=0,n=Jt(0),r;return()=>{sr()&&(i(n),Tn(()=>(t===0&&(r=tn(()=>e(()=>Xt(n)))),t+=1,()=>{at(()=>{t-=1,t===0&&(r?.(),r=void 0,Xt(n))})})))}}var go=ft|mt;function bo(e,t,n,r){new mo(e,t,n,r)}class mo{parent;is_pending=!1;transform_error;#e;#t=I?M:null;#n;#s;#r;#o=null;#a=null;#i=null;#h=null;#l=0;#v=0;#d=!1;#c=new Set;#u=new Set;#f=null;#b=po(()=>(this.#f=Jt(this.#l),()=>{this.#f=null}));constructor(t,n,r,a){this.#e=t,this.#n=n,this.#s=o=>{var l=A;l.b=this,l.f|=jn,r(o)},this.parent=A.b,this.transform_error=a??this.parent?.transform_error??(o=>o),this.#r=nn(()=>{if(I){const o=this.#t;kt();const l=o.data===qr;if(o.data.startsWith(Wr)){const u=JSON.parse(o.data.slice(Wr.length));this.#g(u)}else l?this.#k():this.#_()}else this.#x()},go),I&&(this.#e=M)}#_(){try{this.#o=We(()=>this.#s(this.#e))}catch(t){this.error(t)}}#g(t){const n=this.#n.failed;n&&(this.#i=We(()=>{n(this.#e,()=>t,()=>()=>{})}))}#k(){const t=this.#n.pending;t&&(this.is_pending=!0,this.#a=We(()=>t(this.#e)),at(()=>{var n=this.#h=document.createDocumentFragment(),r=Ye();n.append(r),this.#o=this.#p(()=>We(()=>this.#s(r))),this.#v===0&&(this.#e.before(n),this.#h=null,rn(this.#a,()=>{this.#a=null}),this.#m(O))}))}#x(){try{if(this.is_pending=this.has_pending_snippet(),this.#v=0,this.#l=0,this.#o=We(()=>{this.#s(this.#e)}),this.#v>0){var t=this.#h=document.createDocumentFragment();Va(this.#o,t);const n=this.#n.pending;this.#a=We(()=>n(this.#e))}else this.#m(O)}catch(n){this.error(n)}}#m(t){this.is_pending=!1,t.transfer_effects(this.#c,this.#u)}defer_effect(t){la(t,this.#c,this.#u)}is_rendered(){return!this.is_pending&&(!this.parent||this.parent.is_rendered())}has_pending_snippet(){return!!this.#n.pending}#p(t){var n=A,r=N,a=ye;qe(this.#r),Oe(this.#r),Dt(this.#r.ctx);try{return ot.ensure(),t()}catch(o){return ia(o),null}finally{qe(n),Oe(r),Dt(a)}}#E(t,n){if(!this.has_pending_snippet()){this.parent&&this.parent.#E(t,n);return}this.#v+=t,this.#v===0&&(this.#m(n),this.#a&&rn(this.#a,()=>{this.#a=null}),this.#h&&(this.#e.before(this.#h),this.#h=null))}update_pending_count(t,n){this.#E(t,n),this.#l+=t,!(!this.#f||this.#d)&&(this.#d=!0,at(()=>{this.#d=!1,this.#f&&xn(this.#f,this.#l)}))}get_effect_pending(){return this.#b(),i(this.#f)}error(t){if(!this.#n.onerror&&!this.#n.failed)throw t;O?.is_fork?(this.#o&&O.skip_effect(this.#o),this.#a&&O.skip_effect(this.#a),this.#i&&O.skip_effect(this.#i),O.on_fork_commit(()=>{this.#y(t)})):this.#y(t)}#y(t){this.#o&&(he(this.#o),this.#o=null),this.#a&&(he(this.#a),this.#a=null),this.#i&&(he(this.#i),this.#i=null),I&&(ge(this.#t),Gn(),ge(qn()));var n=this.#n.onerror;let r=this.#n.failed;var a=!1,o=!1;const l=()=>{if(a){oo();return}a=!0,o&&Zi(),this.#i!==null&&rn(this.#i,()=>{this.#i=null}),this.#p(()=>{this.#x()})},s=u=>{try{o=!0,n?.(u,l),o=!1}catch(c){dt(c,this.#r&&this.#r.parent)}r&&(this.#i=this.#p(()=>{try{return We(()=>{var c=A;c.b=this,c.f|=jn,r(this.#e,()=>u,()=>l)})}catch(c){return dt(c,this.#r.parent),null}}))};at(()=>{var u;try{u=this.transform_error(t)}catch(c){dt(c,this.#r&&this.#r.parent);return}u!==null&&typeof u=="object"&&typeof u.then=="function"?u.then(s,c=>dt(c,this.#r&&this.#r.parent)):s(u)})}}function pa(e,t,n,r){const a=or;var o=e.filter(d=>!d.settled);if(n.length===0&&o.length===0){r(t.map(a));return}var l=A,s=yo(),u=o.length===1?o[0].promise:o.length>1?Promise.all(o.map(d=>d.promise)):null;function c(d){if((l.f&Fe)===0){s();try{r(d)}catch(b){dt(b,l)}wn()}}var h=ga();if(n.length===0){u.then(()=>c(t.map(a))).finally(h);return}function v(){Promise.all(n.map(d=>wo(d))).then(d=>c([...t.map(a),...d])).catch(d=>dt(d,l)).finally(h)}u?u.then(()=>{s(),v(),wn()}):v()}function yo(){var e=A,t=N,n=ye,r=O;return function(o=!0){qe(e),Oe(t),Dt(n),o&&(e.f&Fe)===0&&(r?.activate(),r?.apply())}}function wn(e=!0){qe(null),Oe(null),Dt(null),e&&O?.deactivate()}function ga(){var e=A,t=e.b,n=O,r=t.is_rendered();return t.update_pending_count(1,n),n.increment(r,e),()=>{t.update_pending_count(-1,n),n.decrement(r,e)}}function or(e){var t=fe|ue;return A!==null&&(A.f|=mt),{ctx:ye,deps:null,effects:null,equals:Yr,f:t,fn:e,reactions:null,rv:0,v:ne,wv:0,parent:A,ac:null}}const _n=Symbol("obsolete");function wo(e,t,n){let r=A;r===null&&ji();var a=void 0,o=Jt(ne),l=!N,s=new Set;return Oo(()=>{var u=A,c=Hr();a=c.promise;try{Promise.resolve(e()).then(c.resolve,b=>{b!==bn&&c.reject(b)}).finally(wn)}catch(b){c.reject(b),wn()}var h=O;if(l){if((u.f&ut)!==0)var v=ga();if(r.b.is_rendered())h.async_deriveds.get(u)?.reject(_n);else for(const b of s.values())b.reject(_n);s.add(c),h.async_deriveds.set(u,c)}const d=(b,g=void 0)=>{v?.(),s.delete(c),g!==_n&&(h.activate(),g?(o.f|=ht,xn(o,g)):((o.f&ht)!==0&&(o.f^=ht),xn(o,b)),h.deactivate())};c.promise.then(d,b=>d(null,b||"unknown"))}),Sn(()=>{for(const u of s)u.reject(_n)}),new Promise(u=>{function c(h){function v(){h===a?u(o):c(a)}h.then(v,v)}c(a)})}function be(e){const t=or(e);return Ca(t),t}function _o(e){var t=e.effects;if(t!==null){e.effects=null;for(var n=0;n<t.length;n+=1)he(t[n])}}function lr(e){var t,n=A,r=e.parent;if(!st&&r!==null&&e.v!==ne&&(r.f&(Fe|Ie))!==0)return ao(),e.v;qe(r);try{e.f&=~yt,_o(e),t=Ra(e)}finally{qe(n)}return t}function ba(e){var t=lr(e);if(!e.equals(t)&&(e.wv=$a(),(!O?.is_fork||e.deps===null)&&(O!==null?(O.capture(e,t,!0),tr?.capture(e,t,!0)):e.v=t,e.deps===null))){ee(e,oe);return}st||(Ve!==null?(sr()||O?.is_fork)&&Ve.set(e,t):Xn(e))}function ko(e){if(e.effects!==null)for(const t of e.effects)(t.teardown||t.ac)&&(t.teardown?.(),t.ac?.abort(bn),t.fn!==null&&(t.teardown=ct),t.ac=null,en(t,0),ur(t))}function ma(e){if(e.effects!==null)for(const t of e.effects)t.teardown&&t.fn!==null&&jt(t)}let kn=new Set;const Et=new Map;let ya=!1;function Jt(e,t){var n={f:0,v:e,reactions:null,equals:Yr,rv:0,wv:0};return n}function P(e,t){const n=Jt(e);return Ca(n),n}function wa(e,t=!1,n=!0){const r=Jt(e);return t||(r.equals=Fi),r}function w(e,t,n=!1){N!==null&&(!je||(N.f&pn)!==0)&&Xr()&&(N.f&(fe|Ue|Lt|pn))!==0&&(Pe===null||!It.call(Pe,e))&&Wi();let r=n?it(t):t;return xn(e,r,yn)}function xn(e,t,n=null){if(!e.equals(t)){Et.set(e,st?t:e.v);var r=ot.ensure();if(r.capture(e,t),(e.f&fe)!==0){const a=e;(e.f&ue)!==0&&lr(a),Ve===null&&Xn(a)}e.wv=$a(),_a(e,ue,n),A!==null&&(A.f&oe)!==0&&(A.f&(ze|tt))===0&&(Le===null?So([e]):Le.push(e)),!r.is_fork&&kn.size>0&&!ya&&xo()}return t}function xo(){ya=!1;for(const e of kn){(e.f&oe)!==0&&ee(e,He);let t;try{t=Qt(e)}catch{t=!0}t&&jt(e)}kn.clear()}function Xt(e){w(e,e.v+1)}function _a(e,t,n){var r=e.reactions;if(r!==null)for(var a=r.length,o=0;o<a;o++){var l=r[o],s=l.f,u=(s&ue)===0;if(u&&ee(l,t),(s&pn)!==0)kn.add(l);else if((s&fe)!==0){var c=l;Ve?.delete(c),(s&yt)===0&&(s&Re&&(A===null||(A.f&gn)===0)&&(l.f|=yt),_a(c,He,n))}else if(u){var h=l;(s&Ue)!==0&&lt!==null&&lt.add(h),n!==null?n.push(h):ir(h)}}}function Eo(e,t){if(t){const n=document.body;e.autofocus=!0,at(()=>{document.activeElement===n&&e.focus()})}}let ka=!1;function xa(){ka||(ka=!0,document.addEventListener("reset",e=>{Promise.resolve().then(()=>{if(!e.defaultPrevented)for(const t of e.target.elements)t[Ht]?.()})},{capture:!0}))}function En(e){var t=N,n=A;Oe(null),qe(null);try{return e()}finally{Oe(t),qe(n)}}function Co(e,t,n,r=n){e.addEventListener(t,()=>En(n));const a=e[Ht];a?e[Ht]=()=>{a(),r(!0)}:e[Ht]=()=>r(!0),xa()}let Cn=!1,st=!1;function Ea(e){st=e}let N=null,je=!1;function Oe(e){N=e}let A=null;function qe(e){A=e}let Pe=null;function Ca(e){N!==null&&(Pe===null?Pe=[e]:Pe.push(e))}let we=null,Ee=0,Le=null;function So(e){Le=e}let Sa=1,Ct=0,St=Ct;function Ta(e){St=e}function $a(){return++Sa}function Qt(e){var t=e.f;if((t&ue)!==0)return!0;if(t&fe&&(e.f&=~yt),(t&He)!==0){for(var n=e.deps,r=n.length,a=0;a<r;a++){var o=n[a];if(Qt(o)&&ba(o),o.wv>e.wv)return!0}(t&Re)!==0&&Ve===null&&ee(e,oe)}return!1}function Aa(e,t,n=!0){var r=e.reactions;if(r!==null&&!(Pe!==null&&It.call(Pe,e)))for(var a=0;a<r.length;a++){var o=r[a];(o.f&fe)!==0?Aa(o,t,!1):t===o&&(n?ee(o,ue):(o.f&oe)!==0&&ee(o,He),ir(o))}}function Ra(e){var t=we,n=Ee,r=Le,a=N,o=Pe,l=ye,s=je,u=St,c=e.f;we=null,Ee=0,Le=null,N=(c&(ze|tt))===0?e:null,Pe=null,Dt(e.ctx),je=!1,St=++Ct,e.ac!==null&&(En(()=>{e.ac.abort(bn)}),e.ac=null);try{e.f|=gn;var h=e.fn,v=h();e.f|=ut;var d=e.deps,b=O?.is_fork;if(we!==null){var g;if(b||en(e,Ee),d!==null&&Ee>0)for(d.length=Ee+we.length,g=0;g<we.length;g++)d[Ee+g]=we[g];else e.deps=d=we;if(sr()&&(e.f&Re)!==0)for(g=Ee;g<d.length;g++)(d[g].reactions??=[]).push(e)}else!b&&d!==null&&Ee<d.length&&(en(e,Ee),d.length=Ee);if(Xr()&&Le!==null&&!je&&d!==null&&(e.f&(fe|He|ue))===0)for(g=0;g<Le.length;g++)Aa(Le[g],e);if(a!==null&&a!==e){if(Ct++,a.deps!==null)for(let x=0;x<n;x+=1)a.deps[x].rv=Ct;if(t!==null)for(const x of t)x.rv=Ct;Le!==null&&(r===null?r=Le:r.push(...Le))}return(e.f&ht)!==0&&(e.f^=ht),v}catch(x){return ia(x)}finally{e.f^=gn,we=t,Ee=n,Le=r,N=a,Pe=o,Dt(l),je=s,St=u}}function To(e,t){let n=t.reactions;if(n!==null){var r=Ai.call(n,e);if(r!==-1){var a=n.length-1;a===0?n=t.reactions=null:(n[r]=n[a],n.pop())}}if(n===null&&(t.f&fe)!==0&&(we===null||!It.call(we,t))){var o=t;(o.f&Re)!==0&&(o.f^=Re,o.f&=~yt),o.v!==ne&&Xn(o),ko(o),en(o,0)}}function en(e,t){var n=e.deps;if(n!==null)for(var r=t;r<n.length;r++)To(e,n[r])}function jt(e){var t=e.f;if((t&Fe)===0){ee(e,oe);var n=A,r=Cn;A=e,Cn=!0;try{(t&(Ue|Vn))!==0?Po(e):ur(e),Ma(e);var a=Ra(e);e.teardown=typeof a=="function"?a:null,e.wv=Sa;var o;Vr&&Ji&&(e.f&ue)!==0&&e.deps}finally{Cn=r,A=n}}}async function Tt(){await Promise.resolve(),H()}function i(e){var t=e.f,n=(t&fe)!==0;if(N!==null&&!je){var r=A!==null&&(A.f&Fe)!==0;if(!r&&(Pe===null||!It.call(Pe,e))){var a=N.deps;if((N.f&gn)!==0)e.rv<Ct&&(e.rv=Ct,we===null&&a!==null&&a[Ee]===e?Ee++:we===null?we=[e]:we.push(e));else{(N.deps??=[]).push(e);var o=e.reactions;o===null?e.reactions=[N]:It.call(o,N)||o.push(N)}}}if(st&&Et.has(e))return Et.get(e);if(n){var l=e;if(st){var s=l.v;return((l.f&oe)===0&&l.reactions!==null||Oa(l))&&(s=lr(l)),Et.set(l,s),s}var u=(l.f&Re)===0&&!je&&N!==null&&(Cn||(N.f&Re)!==0),c=(l.f&ut)===0;Qt(l)&&(u&&(l.f|=Re),ba(l)),u&&!c&&(ma(l),Ia(l))}if(Ve?.has(e))return Ve.get(e);if((e.f&ht)!==0)throw e.v;return e.v}function Ia(e){if(e.f|=Re,e.deps!==null)for(const t of e.deps)(t.reactions??=[]).push(e),(t.f&fe)!==0&&(t.f&Re)===0&&(ma(t),Ia(t))}function Oa(e){if(e.v===ne)return!0;if(e.deps===null)return!1;for(const t of e.deps)if(Et.has(t)||(t.f&fe)!==0&&Oa(t))return!0;return!1}function tn(e){var t=je;try{return je=!0,e()}finally{je=t}}function $o(e){A===null&&(N===null&&Hi(),zi()),st&&Bi()}function Ao(e,t){var n=t.last;n===null?t.last=t.first=e:(n.next=e,e.prev=n,t.last=e)}function Be(e,t){var n=A;n!==null&&(n.f&Ie)!==0&&(e|=Ie);var r={ctx:ye,deps:null,nodes:null,f:e|ue|Re,first:null,fn:t,last:null,next:null,parent:n,b:n&&n.b,prev:null,teardown:null,wv:0,ac:null};O?.register_created_effect(r);var a=r;if((e&Pt)!==0)Vt!==null?Vt.push(r):ot.ensure().schedule(r);else if(t!==null){try{jt(r)}catch(l){throw he(r),l}a.deps===null&&a.teardown===null&&a.nodes===null&&a.first===a.last&&(a.f&mt)===0&&(a=a.first,(e&Ue)!==0&&(e&ft)!==0&&a!==null&&(a.f|=ft))}if(a!==null&&(a.parent=n,n!==null&&Ao(a,n),N!==null&&(N.f&fe)!==0&&(e&tt)===0)){var o=N;(o.effects??=[]).push(a)}return r}function sr(){return N!==null&&!je}function Sn(e){const t=Be(vn,null);return ee(t,oe),t.teardown=e,t}function Ce(e){$o();var t=A.f,n=!N&&(t&ze)!==0&&(t&ut)===0;if(n){var r=ye;(r.e??=[]).push(e)}else return Pa(e)}function Pa(e){return Be(Pt|Di,e)}function Ro(e){ot.ensure();const t=Be(tt|mt,e);return()=>{he(t)}}function Io(e){ot.ensure();const t=Be(tt|mt,e);return(n={})=>new Promise(r=>{n.outro?rn(t,()=>{he(t),r(void 0)}):(he(t),r(void 0))})}function cr(e){return Be(Pt,e)}function Oo(e){return Be(Lt|mt,e)}function Tn(e,t=0){return Be(vn|t,e)}function ve(e,t=[],n=[],r=[]){pa(r,t,n,a=>{Be(vn,()=>e(...a.map(i)))})}function nn(e,t=0){var n=Be(Ue|t,e);return n}function La(e,t=0){var n=Be(Vn|t,e);return n}function We(e){return Be(ze|mt,e)}function Ma(e){var t=e.teardown;if(t!==null){const n=st,r=N;Ea(!0),Oe(null);try{t.call(null)}finally{Ea(n),Oe(r)}}}function ur(e,t=!1){var n=e.first;for(e.first=e.last=null;n!==null;){const a=n.ac;a!==null&&En(()=>{a.abort(bn)});var r=n.next;(n.f&tt)!==0?n.parent=null:he(n,t),n=r}}function Po(e){for(var t=e.first;t!==null;){var n=t.next;(t.f&ze)===0&&he(t),t=n}}function he(e,t=!0){var n=!1;(t||(e.f&Mi)!==0)&&e.nodes!==null&&e.nodes.end!==null&&(Da(e.nodes.start,e.nodes.end),n=!0),ee(e,Bn),ur(e,t&&!n),en(e,0);var r=e.nodes&&e.nodes.t;if(r!==null)for(const o of r)o.stop();Ma(e),e.f^=Bn,e.f|=Fe;var a=e.parent;a!==null&&a.first!==null&&Na(e),e.next=e.prev=e.teardown=e.ctx=e.deps=e.fn=e.nodes=e.ac=e.b=null}function Da(e,t){for(;e!==null;){var n=e===t?null:Ge(e);e.remove(),e=n}}function Na(e){var t=e.parent,n=e.prev,r=e.next;n!==null&&(n.next=r),r!==null&&(r.prev=n),t!==null&&(t.first===e&&(t.first=r),t.last===e&&(t.last=n))}function rn(e,t,n=!0){var r=[];Ua(e,r,!0);var a=()=>{n&&he(e),t&&t()},o=r.length;if(o>0){var l=()=>--o||a();for(var s of r)s.out(l)}else a()}function Ua(e,t,n){if((e.f&Ie)===0){e.f^=Ie;var r=e.nodes&&e.nodes.t;if(r!==null)for(const s of r)(s.is_global||n)&&t.push(s);for(var a=e.first;a!==null;){var o=a.next;if((a.f&tt)===0){var l=(a.f&ft)!==0||(a.f&ze)!==0&&(e.f&Ue)!==0;Ua(a,t,l?n:!1)}a=o}}}function Lo(e){Fa(e,!0)}function Fa(e,t){if((e.f&Ie)!==0){e.f^=Ie,(e.f&oe)===0&&(ee(e,ue),ot.ensure().schedule(e));for(var n=e.first;n!==null;){var r=n.next,a=(n.f&ft)!==0||(n.f&ze)!==0;Fa(n,a?t:!1),n=r}var o=e.nodes&&e.nodes.t;if(o!==null)for(const l of o)(l.is_global||t)&&l.in()}}function Va(e,t){if(e.nodes)for(var n=e.nodes.start,r=e.nodes.end;n!==null;){var a=n===r?null:Ge(n);t.append(n),n=a}}function ja(e){const t={get:n=>Wt(t.store)[n],set:(n,r)=>{typeof n=="string"?Object.assign(Wt(t.store),{[n]:r}):Object.assign(Wt(t.store),n),t.store.set(Wt(t.store))},store:uo(e)};return t}globalThis.$altcha=globalThis.$altcha||{algorithms:new Map,defaults:ja({}),i18n:ja({}),instances:new Set,plugins:new Set};const Mo={ariaLinkLabel:"Altcha (official website)",cancel:"Cancel",enterCode:"Enter code",enterCodeAria:"Enter code you hear. Press Space to play audio.",enterCodeFromImage:"To proceed, please enter the code from the image below.",error:"Verification failed. Try again later.",expired:"Verification expired. Try again.",footer:'Protected by <a href="https://altcha.org/" tabindex="-1" target="_blank" aria-label="Altcha (official website)">ALTCHA</a>',getAudioChallenge:"Get an audio challenge",label:"I'm not a robot",loading:"Loading...",reload:"Reload",verify:"Verify",verificationRequired:"Verification required!",verified:"Verified",verifying:"Verifying...",waitAlert:"Verifying... please wait."};"$altcha"in globalThis&&globalThis.$altcha.i18n.set("en",Mo);const Do="5";typeof window<"u"&&((window.__svelte??={}).v??=new Set).add(Do);const an=Symbol("events"),Ba=new Set,fr=new Set;function za(e,t,n,r={}){function a(o){if(r.capture||hr.call(t,o),!o.cancelBubble)return En(()=>n?.call(this,o))}return e.startsWith("pointer")||e.startsWith("touch")||e==="wheel"?at(()=>{t.addEventListener(e,a,r)}):t.addEventListener(e,a,r),a}function le(e,t,n,r,a){var o={capture:r,passive:a},l=za(e,t,n,o);(t===document.body||t===window||t===document||t instanceof HTMLMediaElement)&&Sn(()=>{t.removeEventListener(e,l,o)})}function $n(e,t,n){(t[an]??={})[e]=n}function An(e){for(var t=0;t<e.length;t++)Ba.add(e[t]);for(var n of fr)n(e)}let Ha=null;function hr(e){var t=this,n=t.ownerDocument,r=e.type,a=e.composedPath?.()||[],o=a[0]||e.target;Ha=e;var l=0,s=Ha===e&&e[an];if(s){var u=a.indexOf(s);if(u!==-1&&(t===document||t===window)){e[an]=t;return}var c=a.indexOf(t);if(c===-1)return;u<=c&&(l=u)}if(o=a[l]||e.target,o!==t){zt(e,"currentTarget",{configurable:!0,get(){return o||n}});var h=N,v=A;Oe(null),qe(null);try{for(var d,b=[];o!==null;){var g=o.assignedSlot||o.parentNode||o.host||null;try{var x=o[an]?.[r];x!=null&&(!o.disabled||e.target===o)&&x.call(o,e)}catch($){d?b.push($):d=$}if(e.cancelBubble||g===t||g===null)break;o=g}if(d){for(let $ of b)queueMicrotask(()=>{throw $});throw d}}finally{e[an]=t,delete e.currentTarget,Oe(h),qe(v)}}}const No=globalThis?.window?.trustedTypes&&globalThis.window.trustedTypes.createPolicy("svelte-trusted-html",{createHTML:e=>e});function Uo(e){return No?.createHTML(e)??e}function Ka(e){var t=Jn("template");return t.innerHTML=Uo(e.replaceAll("<!>","<!---->")),t.content}function Se(e,t){var n=A;n.nodes===null&&(n.nodes={start:e,end:t,a:null,t:null})}function Z(e,t){var n=(t&Xi)!==0,r=(t&Qi)!==0,a,o=!e.startsWith("<!>");return()=>{if(I)return Se(M,null),M;a===void 0&&(a=Ka(o?e:"<!>"+e),n||(a=xe(a)));var l=r||na?document.importNode(a,!0):a.cloneNode(!0);if(n){var s=xe(l),u=l.lastChild;Se(s,u)}else Se(l,l);return l}}function Fo(e,t,n="svg"){var r=!e.startsWith("<!>"),a=`<${n}>${r?e:"<!>"+e}</${n}>`,o;return()=>{if(I)return Se(M,null),M;if(!o){var l=Ka(a),s=xe(l);o=xe(s)}var u=o.cloneNode(!0);return Se(u,u),u}}function dr(e,t){return Fo(e,t,"svg")}function Rn(e=""){if(!I){var t=Ye(e+"");return Se(t,t),t}var n=M;return n.nodeType!==Yt?(n.before(n=Ye()),ge(n)):mn(n),Se(n,n),n}function Ya(){if(I)return Se(M,null),M;var e=document.createDocumentFragment(),t=document.createComment(""),n=Ye();return e.append(t,n),Se(t,n),e}function D(e,t){if(I){var n=A;((n.f&ut)===0||n.nodes.end===null)&&(n.nodes.end=M),kt();return}e!==null&&e.before(t)}function Vo(e){return e.endsWith("capture")&&e!=="gotpointercapture"&&e!=="lostpointercapture"}const jo=["beforeinput","click","change","dblclick","contextmenu","focusin","focusout","input","keydown","keyup","mousedown","mousemove","mouseout","mouseover","mouseup","pointerdown","pointermove","pointerout","pointerover","pointerup","touchend","touchmove","touchstart"];function Bo(e){return jo.includes(e)}const zo={formnovalidate:"formNoValidate",ismap:"isMap",nomodule:"noModule",playsinline:"playsInline",readonly:"readOnly",defaultvalue:"defaultValue",defaultchecked:"defaultChecked",srcobject:"srcObject",novalidate:"noValidate",allowfullscreen:"allowFullscreen",disablepictureinpicture:"disablePictureInPicture",disableremoteplayback:"disableRemotePlayback"};function Ho(e){return e=e.toLowerCase(),zo[e]??e}const Ko=["touchstart","touchmove"];function Yo(e){return Ko.includes(e)}function Ze(e,t){var n=t==null?"":typeof t=="object"?`${t}`:t;n!==(e[Kn]??=e.nodeValue)&&(e[Kn]=n,e.nodeValue=`${n}`)}function Ga(e,t){return qa(e,t)}function Go(e,t){Zn(),t.intro=t.intro??!1;const n=t.target,r=I,a=M;try{for(var o=xe(n);o&&(o.nodeType!==Gt||o.data!==Yn);)o=Ge(o);if(!o)throw wt;Ke(!0),ge(o);const l=qa(e,{...t,anchor:o});return Ke(!1),l}catch(l){if(l instanceof Error&&l.message.split(`
`).some(s=>s.startsWith("https://svelte.dev/e/")))throw l;return l!==wt&&console.warn("Failed to hydrate: ",l),t.recover===!1&&Yi(),Zn(),so(n),Ke(!1),Ga(e,t)}finally{Ke(r),ge(a)}}const In=new Map;function qa(e,{target:t,anchor:n,props:r={},events:a,context:o,intro:l=!0,transformError:s}){Zn();var u=void 0,c=Io(()=>{var h=n??t.appendChild(Ye());bo(h,{pending:()=>{}},b=>{nt({});var g=ye;if(o&&(g.c=o),a&&(r.$$events=a),I&&Se(b,null),u=e(b,r)||{},I&&(A.nodes.end=M,M===null||M.nodeType!==Gt||M.data!==Zr))throw qt(),wt;rt()},s);var v=new Set,d=b=>{for(var g=0;g<b.length;g++){var x=b[g];if(!v.has(x)){v.add(x);var $=Yo(x);for(const re of[t,document]){var L=In.get(re);L===void 0&&(L=new Map,In.set(re,L));var ce=L.get(x);ce===void 0?(re.addEventListener(x,hr,{passive:$}),L.set(x,1)):L.set(x,ce+1)}}}};return d(Ri(Ba)),fr.add(d),()=>{for(var b of v)for(const $ of[t,document]){var g=In.get($),x=g.get(b);--x==0?($.removeEventListener(b,hr),g.delete(b),g.size===0&&In.delete($)):g.set(b,x)}fr.delete(d),h!==n&&h.parentNode?.removeChild(h)}});return vr.set(u,c),u}let vr=new WeakMap;function qo(e,t){const n=vr.get(e);return n?(vr.delete(e),n(t)):Promise.resolve()}class On{anchor;#e=new Map;#t=new Map;#n=new Map;#s=new Set;#r=!0;constructor(t,n=!0){this.anchor=t,this.#r=n}#o=t=>{if(this.#e.has(t)){var n=this.#e.get(t),r=this.#t.get(n);if(r)Lo(r),this.#s.delete(n);else{var a=this.#n.get(n);a&&(this.#t.set(n,a.effect),this.#n.delete(n),a.fragment.lastChild.remove(),this.anchor.before(a.fragment),r=a.effect)}for(const[o,l]of this.#e){if(this.#e.delete(o),o===t)break;const s=this.#n.get(l);s&&(he(s.effect),this.#n.delete(l))}for(const[o,l]of this.#t){if(o===n||this.#s.has(o))continue;const s=()=>{if(Array.from(this.#e.values()).includes(o)){var c=document.createDocumentFragment();Va(l,c),c.append(Ye()),this.#n.set(o,{effect:l,fragment:c})}else he(l);this.#s.delete(o),this.#t.delete(o)};this.#r||!r?(this.#s.add(o),rn(l,s,!1)):s()}}};#a=t=>{this.#e.delete(t);const n=Array.from(this.#e.values());for(const[r,a]of this.#n)n.includes(r)||(he(a.effect),this.#n.delete(r))};ensure(t,n){var r=O;n&&!this.#t.has(t)&&!this.#n.has(t)&&this.#t.set(t,We(()=>n(this.anchor))),this.#e.set(r,t),I&&(this.anchor=M),this.#o(r)}}function Wo(e,t,...n){var r=new On(e);nn(()=>{const a=t()??null;r.ensure(a,a&&(o=>a(o,...n)))},ft)}function pr(e){ye===null&&Vi(),Ce(()=>{const t=tn(e);if(typeof t=="function")return t})}function se(e,t,n=!1){var r;I&&(r=M,kt());var a=new On(e),o=n?ft:0;function l(s,u){if(I){var c=ea(r);if(s!==parseInt(c.substring(1))){var h=qn();ge(h),a.anchor=h,Ke(!1),a.ensure(s,u),Ke(!0);return}}a.ensure(s,u)}nn(()=>{var s=!1;t((u,c=0)=>{s=!0,l(c,u)}),s||l(-1,null)},o)}const Zo=Symbol("NaN");function Jo(e,t,n){I&&kt();var r=new On(e);nn(()=>{var a=t();a!==a&&(a=Zo),r.ensure(a,n)})}function Wa(e,t,n=!1,r=!1,a=!1,o=!1){var l=e,s="";if(n){var u=e;I&&(l=ge(xe(u)))}ve(()=>{var c=A;if(s===(s=t()??"")){I&&kt();return}if(n&&!I){c.nodes=null,u.innerHTML=s,s!==""&&Se(xe(u),u.lastChild);return}if(c.nodes!==null&&(Da(c.nodes.start,c.nodes.end),c.nodes=null),s!==""){if(I){M.data;for(var h=kt(),v=h;h!==null&&(h.nodeType!==Gt||h.data!=="");)v=h,h=Ge(h);if(h===null)throw qt(),wt;Se(M,v),l=ge(h);return}var d=r?eo:a?to:void 0,b=Jn(r?"svg":a?"math":"template",d);b.innerHTML=s;var g=r||a?b:b.content;if(Se(xe(g),g.lastChild),r||a)for(;xe(g);)l.before(xe(g));else l.before(g)}})}function Xo(e,t,n){var r;I&&(r=M,kt());var a=new On(e);nn(()=>{var o=t()??null;if(I){var l=ea(r),s=l===Yn,u=o!==null;if(s!==u){var c=qn();ge(c),a.anchor=c,Ke(!1),a.ensure(o,o&&(h=>n(h,o))),Ke(!0);return}}a.ensure(o,o&&(h=>n(h,o)))},ft)}function Qo(e,t){var n=void 0,r;La(()=>{n!==(n=t())&&(r&&(he(r),r=null),n&&(r=We(()=>{cr(()=>n(e))})))})}function Za(e){var t,n,r="";if(typeof e=="string"||typeof e=="number")r+=e;else if(typeof e=="object")if(Array.isArray(e)){var a=e.length;for(t=0;t<a;t++)e[t]&&(n=Za(e[t]))&&(r&&(r+=" "),r+=n)}else for(n in e)e[n]&&(r&&(r+=" "),r+=n);return r}function el(){for(var e,t,n=0,r="",a=arguments.length;n<a;n++)(e=arguments[n])&&(t=Za(e))&&(r&&(r+=" "),r+=t);return r}function tl(e){return typeof e=="object"?el(e):e??""}const Ja=[...` 	
\r\f\xA0\v\uFEFF`];function nl(e,t,n){var r=e==null?"":""+e;if(n){for(var a of Object.keys(n))if(n[a])r=r?r+" "+a:a;else if(r.length)for(var o=a.length,l=0;(l=r.indexOf(a,l))>=0;){var s=l+o;(l===0||Ja.includes(r[l-1]))&&(s===r.length||Ja.includes(r[s]))?r=(l===0?"":r.substring(0,l))+r.substring(s+1):l=s}}return r===""?null:r}function Xa(e,t=!1){var n=t?" !important;":";",r="";for(var a of Object.keys(e)){var o=e[a];o!=null&&o!==""&&(r+=" "+a+": "+o+n)}return r}function gr(e){return e[0]!=="-"||e[1]!=="-"?e.toLowerCase():e}function rl(e,t){if(t){var n="",r,a;if(Array.isArray(t)?(r=t[0],a=t[1]):r=t,e){e=String(e).replaceAll(/\s*\/\*.*?\*\/\s*/g,"").trim();var o=!1,l=0,s=!1,u=[];r&&u.push(...Object.keys(r).map(gr)),a&&u.push(...Object.keys(a).map(gr));var c=0,h=-1;const x=e.length;for(var v=0;v<x;v++){var d=e[v];if(s?d==="/"&&e[v-1]==="*"&&(s=!1):o?o===d&&(o=!1):d==="/"&&e[v+1]==="*"?s=!0:d==='"'||d==="'"?o=d:d==="("?l++:d===")"&&l--,!s&&o===!1&&l===0){if(d===":"&&h===-1)h=v;else if(d===";"||v===x-1){if(h!==-1){var b=gr(e.substring(c,h).trim());if(!u.includes(b)){d!==";"&&v++;var g=e.substring(c,v).trim();n+=" "+g+";"}}c=v+1,h=-1}}}}return r&&(n+=Xa(r)),a&&(n+=Xa(a,!0)),n=n.trim(),n===""?null:n}return e==null?null:String(e)}function al(e,t,n,r,a,o){var l=e[zn];if(I||l!==n||l===void 0){var s=nl(n,r,o);(!I||s!==e.getAttribute("class"))&&(s==null?e.removeAttribute("class"):t?e.className=s:e.setAttribute("class",s)),e[zn]=n}else if(o&&a!==o)for(var u in o){var c=!!o[u];(a==null||c!==!!a[u])&&e.classList.toggle(u,c)}return o}function br(e,t={},n,r){for(var a in n){var o=n[a];t[a]!==o&&(n[a]==null?e.style.removeProperty(a):e.style.setProperty(a,o,r))}}function il(e,t,n,r){var a=e[Hn];if(I||a!==t){var o=rl(t,r);(!I||o!==e.getAttribute("style"))&&(o==null?e.removeAttribute("style"):e.style.cssText=o),e[Hn]=t}else r&&(Array.isArray(r)?(br(e,n?.[0],r[0]),br(e,n?.[1],r[1],"important")):br(e,n,r));return r}function mr(e,t,n=!1){if(e.multiple){if(t==null)return;if(!jr(t))return io();for(var r of e.options)r.selected=t.includes(Qa(r));return}for(r of e.options){var a=Qa(r);if(lo(a,t)){r.selected=!0;return}}(!n||t!==void 0)&&(e.selectedIndex=-1)}function ol(e){var t=new MutationObserver(()=>{mr(e,e.__value)});t.observe(e,{childList:!0,subtree:!0,attributes:!0,attributeFilter:["value"]}),Sn(()=>{t.disconnect()})}function Qa(e){return"__value"in e?e.__value:e.value}const on=Symbol("class"),ln=Symbol("style"),ei=Symbol("is custom element"),ti=Symbol("is html"),ll=Kt?"link":"LINK",sl=Kt?"input":"INPUT",cl=Kt?"option":"OPTION",ul=Kt?"select":"SELECT",fl=Kt?"progress":"PROGRESS";function yr(e){if(I){var t=!1,n=()=>{if(!t){if(t=!0,e.hasAttribute("value")){var r=e.value;j(e,"value",null),e.value=r}if(e.hasAttribute("checked")){var a=e.checked;j(e,"checked",null),e.checked=a}}};e[Ht]=n,at(n),xa()}}function hl(e,t){var n=wr(e);n.value===(n.value=t??void 0)||e.value===t&&(t!==0||e.nodeName!==fl)||(e.value=t??"")}function dl(e,t){t?e.hasAttribute("selected")||e.setAttribute("selected",""):e.removeAttribute("selected")}function j(e,t,n,r){var a=wr(e);I&&(a[t]=e.getAttribute(t),t==="src"||t==="srcset"||t==="href"&&e.nodeName===ll)||a[t]!==(a[t]=n)&&(t==="loading"&&(e[Ui]=n),n==null?e.removeAttribute(t):typeof n!="string"&&ri(e).includes(t)?e[t]=n:e.setAttribute(t,n))}function vl(e,t,n,r,a=!1,o=!1){if(I&&a&&e.nodeName===sl){var l=e,s=l.type==="checkbox"?"defaultChecked":"defaultValue";s in n||yr(l)}var u=wr(e),c=u[ei],h=!u[ti];let v=I&&c;v&&Ke(!1);var d=t||{},b=e.nodeName===cl;for(var g in t)g in n||(n[g]=null);n.class?n.class=tl(n.class):n[on]&&(n.class=null),n[ln]&&(n.style??=null);var x=ri(e);for(const F in n){let C=n[F];if(b&&F==="value"&&C==null){e.value=e.__value="",d[F]=C;continue}if(F==="class"){var $=e.namespaceURI==="http://www.w3.org/1999/xhtml";al(e,$,C,r,t?.[on],n[on]),d[F]=C,d[on]=n[on];continue}if(F==="style"){il(e,C,t?.[ln],n[ln]),d[F]=C,d[ln]=n[ln];continue}var L=d[F];if(!(C===L&&!(C===void 0&&e.hasAttribute(F)))){d[F]=C;var ce=F[0]+F[1];if(ce!=="$$")if(ce==="on"){const G={},me="$$"+F;let R=F.slice(2);var re=Bo(R);if(Vo(R)&&(R=R.slice(0,-7),G.capture=!0),!re&&L){if(C!=null)continue;e.removeEventListener(R,d[me],G),d[me]=null}if(re)$n(R,e,C),An([R]);else if(C!=null){let ae=function(Je){d[F].call(this,Je)};d[me]=za(R,e,ae,G)}}else if(F==="style")j(e,F,C);else if(F==="autofocus")Eo(e,!!C);else if(!c&&(F==="__value"||F==="value"&&C!=null))e.value=e.__value=C;else if(F==="selected"&&b)dl(e,C);else{var U=F;h||(U=Ho(U));var pe=U==="defaultValue"||U==="defaultChecked";if(C==null&&!c&&!pe)if(u[F]=null,U==="value"||U==="checked"){let G=e;const me=t===void 0;if(U==="value"){let R=G.defaultValue;G.removeAttribute(U),G.defaultValue=R,G.value=G.__value=me?R:null}else{let R=G.defaultChecked;G.removeAttribute(U),G.defaultChecked=R,G.checked=me?R:!1}}else e.removeAttribute(F);else pe||x.includes(U)&&(c||typeof C!="string")?(e[U]=C,U in u&&(u[U]=ne)):typeof C!="function"&&j(e,U,C)}}}return v&&Ke(!0),d}function Pn(e,t,n=[],r=[],a=[],o,l=!1,s=!1){pa(a,n,r,u=>{var c=void 0,h={},v=e.nodeName===ul,d=!1;if(La(()=>{var g=t(...u.map(i)),x=vl(e,c,g,o,l,s);d&&v&&"value"in g&&mr(e,g.value);for(let L of Object.getOwnPropertySymbols(h))g[L]||he(h[L]);for(let L of Object.getOwnPropertySymbols(g)){var $=g[L];L.description===no&&(!c||$!==c[L])&&(h[L]&&he(h[L]),h[L]=We(()=>Qo(e,()=>$))),x[L]=$}c=x}),v){var b=e;cr(()=>{mr(b,c.value,!0),ol(b)})}d=!0})}function wr(e){return e[Kr]??={[ei]:e.nodeName.includes("-"),[ti]:e.namespaceURI===Jr}}var ni=new Map;function ri(e){var t=e.getAttribute("is")||e.nodeName,n=ni.get(t);if(n)return n;ni.set(t,n=[]);for(var r,a=e,o=Element.prototype;o!==a;){r=Ii(a);for(var l in r)r[l].set&&l!=="innerHTML"&&l!=="textContent"&&l!=="innerText"&&n.push(l);a=Br(a)}return n}function pl(e,t,n=t){var r=new WeakSet;Co(e,"input",async a=>{var o=a?e.defaultValue:e.value;if(o=_r(e)?kr(o):o,n(o),O!==null&&r.add(O),await Tt(),o!==(o=t())){var l=e.selectionStart,s=e.selectionEnd,u=e.value.length;if(e.value=o??"",s!==null){var c=e.value.length;l===s&&s===u&&c>u?(e.selectionStart=c,e.selectionEnd=c):(e.selectionStart=l,e.selectionEnd=Math.min(s,c))}}}),(I&&e.defaultValue!==e.value||tn(t)==null&&e.value)&&(n(_r(e)?kr(e.value):e.value),O!==null&&r.add(O)),Tn(()=>{var a=t();if(e===document.activeElement){var o=O;if(r.has(o))return}_r(e)&&a===kr(e.value)||e.type==="date"&&!a&&!e.value||a!==e.value&&(e.value=a??"")})}function _r(e){var t=e.type;return t==="number"||t==="range"}function kr(e){return e===""?null:+e}function xr(e,t){return e===t||e?.[Mt]===t}function vt(e={},t,n,r){var a=ye.r,o=A;return cr(()=>{var l,s;return Tn(()=>{l=s,s=[],tn(()=>{xr(n(...s),e)||(t(e,...s),l&&xr(n(...l),e)&&t(null,...l))})}),()=>{let u=o;for(;u!==a&&u.parent!==null&&u.parent.f&Bn;)u=u.parent;const c=()=>{s&&xr(n(...s),e)&&t(null,...s)},h=u.teardown;u.teardown=()=>{c(),h?.()}}}),e}const gl={get(e,t){if(!e.exclude.includes(t))return e.props[t]},set(e,t){return!1},getOwnPropertyDescriptor(e,t){if(!e.exclude.includes(t)&&t in e.props)return{enumerable:!0,configurable:!0,value:e.props[t]}},has(e,t){return e.exclude.includes(t)?!1:t in e.props},ownKeys(e){return Reflect.ownKeys(e.props).filter(t=>!e.exclude.includes(t))}};function Ln(e,t,n){return new Proxy({props:e,exclude:t},gl)}function J(e,t,n,r){var a=r,o=!0,l=()=>(o&&(o=!1,a=r),a),s;s=e[t],s===void 0&&r!==void 0&&(s=l());var u;u=()=>{var d=e[t];return d===void 0?l():(o=!0,d)};var c=!1,h=or(()=>(c=!1,u())),v=A;return(function(d,b){if(arguments.length>0){const g=b?i(h):d;return w(h,g),c=!0,a!==void 0&&(a=g),d}return st&&c||(v.f&Fe)!==0?h.v:i(h)})}function bl(e){return new ml(e)}class ml{#e;#t;constructor(t){var n=new Map,r=(o,l)=>{var s=wa(l,!1,!1);return n.set(o,s),s};const a=new Proxy({...t.props||{},$$events:{}},{get(o,l){return i(n.get(l)??r(l,Reflect.get(o,l)))},has(o,l){return l===Ni?!0:(i(n.get(l)??r(l,Reflect.get(o,l))),Reflect.has(o,l))},set(o,l,s){return w(n.get(l)??r(l,s),s),Reflect.set(o,l,s)}});this.#t=(t.hydrate?Go:Ga)(t.component,{target:t.target,anchor:t.anchor,props:a,context:t.context,intro:t.intro??!1,recover:t.recover,transformError:t.transformError}),(!t?.props?.$$host||t.sync===!1)&&H(),this.#e=a.$$events;for(const o of Object.keys(this.#t))o==="$set"||o==="$destroy"||o==="$on"||zt(this,o,{get(){return this.#t[o]},set(l){this.#t[o]=l},enumerable:!0});this.#t.$set=o=>{Object.assign(a,o)},this.#t.$destroy=()=>{qo(this.#t)}}$set(t){this.#t.$set(t)}$on(t,n){this.#e[t]=this.#e[t]||[];const r=(...a)=>n.call(this,...a);return this.#e[t].push(r),()=>{this.#e[t]=this.#e[t].filter(a=>a!==r)}}$destroy(){this.#t.$destroy()}}let ai=class{};typeof HTMLElement=="function"&&(ai=class extends HTMLElement{$$ctor;$$s;$$c;$$cn=!1;$$d={};$$r=!1;$$p_d={};$$l={};$$l_u=new Map;$$me;$$shadowRoot=null;constructor(e,t,n){super(),this.$$ctor=e,this.$$s=t,n&&(this.$$shadowRoot=this.attachShadow(n))}addEventListener(e,t,n){if(this.$$l[e]=this.$$l[e]||[],this.$$l[e].push(t),this.$$c){const r=this.$$c.$on(e,t);this.$$l_u.set(t,r)}super.addEventListener(e,t,n)}removeEventListener(e,t,n){if(super.removeEventListener(e,t,n),this.$$c){const r=this.$$l_u.get(t);r&&(r(),this.$$l_u.delete(t))}}async connectedCallback(){if(this.$$cn=!0,!this.$$c){let e=function(r){return a=>{const o=Jn("slot");r!=="default"&&(o.name=r),D(a,o)}};if(await Promise.resolve(),!this.$$cn||this.$$c)return;const t={},n=yl(this);for(const r of this.$$s)r in n&&(r==="default"&&!this.$$d.children?(this.$$d.children=e(r),t.default=!0):t[r]=e(r));for(const r of this.attributes){const a=this.$$g_p(r.name);a in this.$$d||(this.$$d[a]=Mn(a,r.value,this.$$p_d,"toProp"))}for(const r in this.$$p_d)!(r in this.$$d)&&this[r]!==void 0&&(this.$$d[r]=this[r],delete this[r]);this.$$c=bl({component:this.$$ctor,target:this.$$shadowRoot||this,props:{...this.$$d,$$slots:t,$$host:this}}),this.$$me=Ro(()=>{Tn(()=>{this.$$r=!0;for(const r of dn(this.$$c)){if(!this.$$p_d[r]?.reflect)continue;this.$$d[r]=this.$$c[r];const a=Mn(r,this.$$d[r],this.$$p_d,"toAttribute");a==null?this.removeAttribute(this.$$p_d[r].attribute||r):this.setAttribute(this.$$p_d[r].attribute||r,a)}this.$$r=!1})});for(const r in this.$$l)for(const a of this.$$l[r]){const o=this.$$c.$on(r,a);this.$$l_u.set(a,o)}this.$$l={}}}attributeChangedCallback(e,t,n){this.$$r||(e=this.$$g_p(e),this.$$d[e]=Mn(e,n,this.$$p_d,"toProp"),this.$$c?.$set({[e]:this.$$d[e]}))}disconnectedCallback(){this.$$cn=!1,Promise.resolve().then(()=>{!this.$$cn&&this.$$c&&(this.$$c.$destroy(),this.$$me(),this.$$c=void 0)})}$$g_p(e){return dn(this.$$p_d).find(t=>this.$$p_d[t].attribute===e||!this.$$p_d[t].attribute&&t.toLowerCase()===e)||e}});function Mn(e,t,n,r){const a=n[e]?.type;if(t=a==="Boolean"&&typeof t!="boolean"?t!=null:t,!r||!n[e])return t;if(r==="toAttribute")switch(a){case"Object":case"Array":return t==null?null:JSON.stringify(t);case"Boolean":return t?"":null;case"Number":return t??null;default:return t}else switch(a){case"Object":case"Array":return t&&JSON.parse(t);case"Boolean":return t;case"Number":return t!=null?+t:t;default:return t}}function yl(e){const t={};return e.childNodes.forEach(n=>{t[n.slot||"default"]=!0}),t}function pt(e,t,n,r,a,o){let l=class extends ai{constructor(){super(e,n,a),this.$$p_d=t}static get observedAttributes(){return dn(t).map(s=>(t[s].attribute||s).toLowerCase())}};return dn(t).forEach(s=>{zt(l.prototype,s,{get(){return this.$$c&&s in this.$$c?this.$$c[s]:this.$$d[s]},set(u){u=Mn(s,u,t),this.$$d[s]=u;var c=this.$$c;if(c){var h=Ot(c,s)?.get;h?c[s]=u:c.$set({[s]:u})}}})}),r.forEach(s=>{zt(l.prototype,s,{get(){return this.$$c?.[s]}})}),e.element=l,l}var wl=Z('<div class="altcha-checkbox"><input/> <svg aria-hidden="true" width="12" height="9" viewBox="0 0 12 9"><polyline points="1 5 4 8 11 1"></polyline></svg> <div class="altcha-spinner altcha-checkbox-spinner" aria-hidden="true"></div></div>');function ii(e,t){nt(t,!0);let n=J(t,"loading"),r=Ln(t,["$$slots","$$events","$$legacy","$$host","loading"]),a;function o(){a?.click()}var l={get loading(){return n()},set loading(h){n(h),H()}},s=wl(),u=X(s);Pn(u,()=>({type:"checkbox",...r}),void 0,void 0,void 0,void 0,!0),vt(u,h=>a=h,()=>a);var c=W(u,2);return Gn(2),Y(s),ve(()=>j(s,"data-loading",n())),$n("click",c,o),D(e,s),rt(l)}An(["click"]),pt(ii,{loading:{}},[],[],{mode:"open"});var _l=Z('<div class="altcha-checkbox-native"><input/> <div class="altcha-spinner altcha-checkbox-native-spinner"></div></div>');function oi(e,t){nt(t,!0);let n=J(t,"loading"),r=Ln(t,["$$slots","$$events","$$legacy","$$host","loading"]);var a={get loading(){return n()},set loading(s){n(s),H()}},o=_l(),l=X(o);return Pn(l,()=>({type:"checkbox",...r}),void 0,void 0,void 0,void 0,!0),Gn(2),Y(o),ve(()=>j(o,"data-loading",n())),D(e,o),rt(a)}pt(oi,{loading:{}},[],[],{mode:"open"});var kl=Z('<div><a target="_blank" class="altcha-logo" aria-hidden="true" tabindex="-1"><svg width="22" height="22" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.33955 16.4279C5.88954 20.6586 12.1971 21.2105 16.4279 17.6604C18.4699 15.947 19.6548 13.5911 19.9352 11.1365L17.9886 10.4279C17.8738 12.5624 16.909 14.6459 15.1423 16.1284C11.7577 18.9684 6.71167 18.5269 3.87164 15.1423C1.03163 11.7577 1.4731 6.71166 4.8577 3.87164C8.24231 1.03162 13.2883 1.4731 16.1284 4.8577C16.9767 5.86872 17.5322 7.02798 17.804 8.2324L19.9522 9.01429C19.7622 7.07737 19.0059 5.17558 17.6604 3.57212C14.1104 -0.658624 7.80283 -1.21043 3.57212 2.33956C-0.658625 5.88958 -1.21046 12.1971 2.33955 16.4279Z" fill="currentColor"></path><path d="M3.57212 2.33956C1.65755 3.94607 0.496389 6.11731 0.12782 8.40523L2.04639 9.13961C2.26047 7.15832 3.21057 5.25375 4.8577 3.87164C8.24231 1.03162 13.2883 1.4731 16.1284 4.8577L13.8302 6.78606L19.9633 9.13364C19.7929 7.15555 19.0335 5.20847 17.6604 3.57212C14.1104 -0.658624 7.80283 -1.21043 3.57212 2.33956Z" fill="currentColor"></path><path d="M7 10H5C5 12.7614 7.23858 15 10 15C12.7614 15 15 12.7614 15 10H13C13 11.6569 11.6569 13 10 13C8.3431 13 7 11.6569 7 10Z" fill="currentColor"></path></svg></a></div>');function Er(e,t){nt(t,!0);let n=J(t,"strings");const r="https://altcha.org";var a={get strings(){return n()},set strings(s){n(s),H()}},o=kl(),l=X(o);return j(l,"href",r),Y(o),ve(()=>j(l,"aria-label",n().ariaLinkLabel)),D(e,o),rt(a)}pt(Er,{strings:{}},[],[],{mode:"open"});var xl=Z('<div class="altcha-footer"><p></p> <!></div>');function Cr(e,t){nt(t,!0);let n=J(t,"logo"),r=J(t,"strings");var a={get logo(){return n()},set logo(c){n(c),H()},get strings(){return r()},set strings(c){r(c),H()}},o=xl(),l=X(o);Wa(l,()=>r().footer,!0),Y(l);var s=W(l,2);{var u=c=>{Er(c,{get strings(){return r()}})};se(s,c=>{n()&&c(u)})}return Y(o),D(e,o),rt(a)}pt(Cr,{logo:{},strings:{}},[],[],{mode:"open"});var El=Z('<div class="altcha-switch"><input/>  <div class="altcha-switch-toggle"><div class="altcha-spinner altcha-switch-spinner"></div></div></div>');function li(e,t){nt(t,!0);let n=J(t,"loading"),r=Ln(t,["$$slots","$$events","$$legacy","$$host","loading"]),a;function o(){a?.click()}var l={get loading(){return n()},set loading(h){n(h),H()}},s=El(),u=X(s);Pn(u,()=>({type:"checkbox",...r}),void 0,void 0,void 0,void 0,!0),vt(u,h=>a=h,()=>a);var c=W(u,2);return Y(s),ve(()=>j(s,"data-loading",n())),$n("click",c,o),D(e,s),rt(l)}An(["click"]),pt(li,{loading:{}},[],[],{mode:"open"});var de=(e=>(e.ERROR="error",e.LOADING="loading",e.PLAYING="playing",e.PAUSED="paused",e.READY="ready",e))(de||{}),V=(e=>(e.CODE="code",e.ERROR="error",e.VERIFIED="verified",e.VERIFYING="verifying",e.UNVERIFIED="unverified",e.EXPIRED="expired",e))(V||{}),Cl=Z('<div class="altcha-code-challenge-title"> </div>'),Sl=Z('<div class="altcha-spinner"></div>'),Tl=dr('<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12.8659 3.00017L22.3922 19.5002C22.6684 19.9785 22.5045 20.5901 22.0262 20.8662C21.8742 20.954 21.7017 21.0002 21.5262 21.0002H2.47363C1.92135 21.0002 1.47363 20.5525 1.47363 20.0002C1.47363 19.8246 1.51984 19.6522 1.60761 19.5002L11.1339 3.00017C11.41 2.52187 12.0216 2.358 12.4999 2.63414C12.6519 2.72191 12.7782 2.84815 12.8659 3.00017ZM10.9999 16.0002V18.0002H12.9999V16.0002H10.9999ZM10.9999 9.00017V14.0002H12.9999V9.00017H10.9999Z"></path></svg>'),$l=dr('<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M15 7C15 6.44772 15.4477 6 16 6C16.5523 6 17 6.44772 17 7V17C17 17.5523 16.5523 18 16 18C15.4477 18 15 17.5523 15 17V7ZM7 7C7 6.44772 7.44772 6 8 6C8.55228 6 9 6.44772 9 7V17C9 17.5523 8.55228 18 8 18C7.44772 18 7 17.5523 7 17V7Z"></path></svg>'),Al=dr('<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 12H7C8.10457 12 9 12.8954 9 14V19C9 20.1046 8.10457 21 7 21H4C2.89543 21 2 20.1046 2 19V12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12V19C22 20.1046 21.1046 21 20 21H17C15.8954 21 15 20.1046 15 19V14C15 12.8954 15.8954 12 17 12H20C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12Z"></path></svg>'),Rl=Z('<button type="button" class="altcha-button altcha-button-secondary"><!></button>'),Il=Z('<audio hidden="" autoplay=""></audio>'),Ol=Z('<div class="altcha-code-challenge"><form data-code-challenge="true"><!> <div class="altcha-code-challenge-text"> </div> <img class="altcha-code-challenge-image" alt=""/> <div class="altcha-code-challenge-row"><input type="text" class="altcha-input" autocomplete="off" name="" required=""/> <!> <button type="button" class="altcha-button altcha-button-secondary"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2V4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20C7.58172 20 4 16.4183 4 12C4 9.25022 5.38734 6.82447 7.50024 5.38451L7.5 8H9.5V2L3.5 2V4L5.99918 3.99989C3.57075 5.82434 2 8.72873 2 12Z"></path></svg></button></div> <div class="altcha-code-challenge-buttons"><button type="submit" class="altcha-button"> </button> <button type="button" class="altcha-button altcha-button-secondary"> </button></div></form> <!></div>');function si(e,t){nt(t,!0);let n=J(t,"audioUrl"),r=J(t,"codeChallenge"),a=J(t,"config"),o=J(t,"imageUrl"),l=J(t,"onCancel"),s=J(t,"onReload"),u=J(t,"onSubmit"),c=J(t,"strings"),h=P(void 0),v=P(void 0),d=P(void 0),b=P(!1),g=P(""),x=P(!1);pr(()=>(a().disableAutoFocus||Tt().then(()=>{i(d)?.focus()}),()=>{i(v)&&(i(v).pause(),w(v,void 0))}));function $(){w(h,de.PAUSED,!0)}function L(y){w(h,de.ERROR,!0)}function ce(){w(h,de.READY,!0)}function re(){w(h,de.LOADING,!0)}function U(){w(h,de.PLAYING,!0)}function pe(){w(h,de.PAUSED,!0)}function F(y){y.code==="Space"?(y.preventDefault(),y.stopPropagation(),G()):y.code==="Escape"&&(y.preventDefault(),y.stopPropagation(),l()?.())}function C(y){y.preventDefault(),y.stopPropagation(),u()?.(i(g))}function G(){i(v)?i(h)===de.LOADING||(i(v).paused?(n()&&i(v).src!==n()&&(i(v).src=n()),i(v).currentTime=0,i(v).play()):i(v).pause()):(w(x,!0),requestAnimationFrame(()=>{i(v)&&n()&&(i(v).src=n(),i(v).play())}))}var me={get audioUrl(){return n()},set audioUrl(y){n(y),H()},get codeChallenge(){return r()},set codeChallenge(y){r(y),H()},get config(){return a()},set config(y){a(y),H()},get imageUrl(){return o()},set imageUrl(y){o(y),H()},get onCancel(){return l()},set onCancel(y){l(y),H()},get onReload(){return s()},set onReload(y){s(y),H()},get onSubmit(){return u()},set onSubmit(y){u(y),H()},get strings(){return c()},set strings(y){c(y),H()}},R=Ol(),ae=X(R),Je=X(ae);{var Te=y=>{var te=Cl(),Rt=X(te,!0);Y(te),ve(()=>Ze(Rt,c().verificationRequired)),D(y,te)};se(Je,y=>{a().codeChallengeDisplay!=="standard"&&y(Te)})}var _e=W(Je,2),Q=X(_e,!0);Y(_e);var Xe=W(_e,2),E=W(Xe,2),z=X(E);yr(z),z.disabled=i(b),vt(z,y=>w(d,y),()=>i(d));var $e=W(z,2);{var m=y=>{var te=Rl(),Rt=X(te);{var Rr=ke=>{var Qe=Sl();D(ke,Qe)},fn=ke=>{var Qe=Tl();D(ke,Qe)},Ir=ke=>{var Qe=$l();D(ke,Qe)},Or=ke=>{var Qe=Al();D(ke,Qe)};se(Rt,ke=>{i(h)===de.LOADING?ke(Rr):i(h)===de.ERROR?ke(fn,1):i(h)===de.PLAYING?ke(Ir,2):ke(Or,-1)})}Y(te),ve(()=>{j(te,"title",c().getAudioChallenge),te.disabled=i(h)===de.LOADING||i(h)===de.ERROR,j(te,"aria-label",i(h)===de.LOADING?c().loading:c().getAudioChallenge)}),le("click",te,()=>G(),!0),D(y,te)};se($e,y=>{r().audio&&y(m)})}var $t=W($e,2);Y(E);var Dn=W(E,2),Me=X(Dn),Ar=X(Me,!0);Y(Me);var At=W(Me,2),sn=X(At,!0);Y(At),Y(Dn),Y(ae);var cn=W(ae,2);{var un=y=>{var te=Il();vt(te,Rt=>w(v,Rt),()=>i(v)),le("error",te,L),le("loadstart",te,re),le("canplay",te,ce),le("pause",te,pe),le("playing",te,U),le("ended",te,$),D(y,te)};se(cn,y=>{i(x)&&y(un)})}return Y(R),ve(()=>{Ze(Q,c().enterCodeFromImage),j(Xe,"src",o()),j(z,"minlength",r().length||1),j(z,"maxlength",r().length),j(z,"placeholder",c().enterCode),j(z,"aria-label",i(h)===de.LOADING?c().loading:i(h)===de.PLAYING?"":c().enterCodeAria),j(z,"aria-live",i(h)?"assertive":"polite"),j(z,"aria-busy",i(h)===de.LOADING),j($t,"title",c().reload),j($t,"aria-label",c().reload),j(Me,"aria-label",c().verify),Ze(Ar,c().verify),j(At,"aria-label",c().cancel),Ze(sn,c().cancel)}),le("submit",ae,C,!0),$n("keydown",z,F),pl(z,()=>i(g),y=>w(g,y)),le("click",$t,()=>s()?.(),!0),le("click",At,()=>l()?.(),!0),D(e,R),rt(me)}An(["keydown"]),pt(si,{audioUrl:{},codeChallenge:{},config:{},imageUrl:{},onCancel:{},onReload:{},onSubmit:{},strings:{}},[],[],{mode:"open"});var Pl=Z('<div class="altcha-popover-backdrop" data-backdrop=""></div>'),Ll=Z('<div class="altcha-popover-arrow"></div>'),Ml=Z('<div role="button" class="altcha-popover-close">&times;</div>'),Dl=Z('<!> <div><!> <!> <div class="altcha-popover-content"><!></div></div>',1);function Sr(e,t){nt(t,!0);let n=J(t,"anchor"),r=J(t,"children"),a=J(t,"display",7,"standard"),o=J(t,"backdrop",7,!1),l=J(t,"onClickOutside"),s=J(t,"onClickOutsideDelay",7,600),u=J(t,"onClose"),c=J(t,"placement",7,"auto"),h=J(t,"updateUISignal"),v=J(t,"variant",7,"neutral"),d=Ln(t,["$$slots","$$events","$$legacy","$$host","anchor","children","display","backdrop","onClickOutside","onClickOutsideDelay","onClose","placement","updateUISignal","variant"]),b=P(void 0),g=P(void 0),x=P(!1),$=P(0);Ce(()=>{c()!=="auto"&&w(x,c()==="top")}),Ce(()=>{h()&&pe()}),pr(()=>{const E=a()==="bottomsheet"||a()==="overlay";return E&&(i(g)&&document.body.append(i(g)),i(b)&&document.body.append(i(b))),pe(),Tt().then(()=>{w($,Date.now(),!0)}),()=>{E&&(i(g)&&document.body.removeChild(i(g)),i(b)&&document.body.removeChild(i(b)))}});function L(){u()?.()}function ce(E){const z=E.target;!i(b)?.contains(z)&&(!s()||i($)+s()<Date.now())&&l()?.()}function re(){pe()}function U(){pe()}function pe(){if(n()&&c()==="auto"&&i(b)){const E=n().getBoundingClientRect(),$e=document.documentElement.clientHeight-(E.top+E.height)<i(b).clientHeight;i(x)!==$e&&w(x,$e)}}var F={get anchor(){return n()},set anchor(E){n(E),H()},get children(){return r()},set children(E){r(E),H()},get display(){return a()},set display(E="standard"){a(E),H()},get backdrop(){return o()},set backdrop(E=!1){o(E),H()},get onClickOutside(){return l()},set onClickOutside(E){l(E),H()},get onClickOutsideDelay(){return s()},set onClickOutsideDelay(E=600){s(E),H()},get onClose(){return u()},set onClose(E){u(E),H()},get placement(){return c()},set placement(E="auto"){c(E),H()},get updateUISignal(){return h()},set updateUISignal(E){h(E),H()},get variant(){return v()},set variant(E="neutral"){v(E),H()}},C=Dl();le("click",xt,ce,!0),le("resize",xt,re),le("scroll",xt,U);var G=Nt(C);{var me=E=>{var z=Pl();vt(z,$e=>w(g,$e),()=>i(g)),D(E,z)};se(G,E=>{o()&&E(me)})}var R=W(G,2);Pn(R,()=>({...d,class:`altcha-popover ${(t.class||"")??""}`,"data-popover":!0,"data-variant":v(),"data-top":i(x),"data-display":a()}));var ae=X(R);{var Je=E=>{var z=Ll();D(E,z)};se(ae,E=>{a()==="standard"&&E(Je)})}var Te=W(ae,2);{var _e=E=>{var z=Ml();le("click",z,L,!0),D(E,z)};se(Te,E=>{a()!=="standard"&&E(_e)})}var Q=W(Te,2),Xe=X(Q);return Wo(Xe,()=>r()??ct),Y(Q),Y(R),vt(R,E=>w(b,E),()=>i(b)),D(e,C),rt(F)}pt(Sr,{anchor:{},children:{},display:{},backdrop:{},onClickOutside:{},onClickOutsideDelay:{},onClose:{},placement:{},updateUISignal:{},variant:{}},[],[],{mode:"open"});function Nl(e){return Array.from(new Uint8Array(e)).map(t=>t.toString(16).padStart(2,"0")).join("")}function Ul(e,t="altcha-css",n){if(typeof document<"u"&&document&&!document.getElementById(t)){const r=document.createElement("style");r.id=t,r.textContent=e;const a=document.currentScript?.nonce??document.querySelector('meta[name="csp-nonce"]')?.content;a&&(r.nonce=a),document.head.appendChild(r)}}async function ci(e){const{challenge:t,concurrency:n=navigator.hardwareConcurrency,controller:r=new AbortController,createWorker:a,onOutOfMemory:o=d=>d>1?Math.floor(d/2):0,counterMode:l,timeout:s=9e4}=e,u=Math.min(16,Math.max(1,n)),c=[],h=()=>{for(const d of c)d.terminate()};for(let d=0;d<u;d++)c.push(await a(t.parameters.algorithm));let v=null;try{v=await Promise.race(c.map((d,b)=>(r.signal.addEventListener("abort",()=>{d.postMessage({type:"abort"})}),new Promise((g,x)=>{d.addEventListener("error",$=>{x($)}),d.addEventListener("message",$=>{if($.data){for(const L of c)L!==d&&L.postMessage({type:"abort"});if($.data.error)return x(new Error($.data.error))}g($.data)}),d.postMessage({challenge:t,counterMode:l,counterStart:b,counterStep:u,timeout:s,type:"work"})}))))}catch(d){if(d instanceof Error&&!!d?.message?.includes("Out of memory")&&o){h();const g=o(u);if(g)return ci({...e,challenge:t,controller:r,concurrency:g,createWorker:a})}throw d}finally{h()}return r.signal.aborted?null:v||null}class Fl{TAG_CODES={INPUT:1,TEXTAREA:2,SELECT:3,BUTTON:4,A:5,DETAILS:6,SUMMARY:7,IFRAME:8,VIDEO:9,AUDIO:10};maxSamples;sampleInterval;target;focusStartTime=0;focusInteraction=0;focusInteractionTimer=null;lastPointerSample=0;lastTouchSample=0;lastScrollSample=0;pendingPointer=null;pendingTouch=null;focus=[];pointer=[];scroll=[];touch=[];constructor(t={}){const{maxSamples:n=60,sampleInterval:r=50,target:a=window}=t;this.maxSamples=n,this.sampleInterval=r,this.target=a,this.attach()}destroy(){const t={capture:!0};this.target.removeEventListener("focusin",this.onFocus,t),this.target.removeEventListener("keydown",this.onInteraction,t),this.target.removeEventListener("pointerdown",this.onInteraction,t),this.target.removeEventListener("pointermove",this.onPointer,t),this.target.removeEventListener("scroll",this.onScroll,t),this.target.removeEventListener("touchmove",this.onTouchMove,t)}export(){return{focus:this.focus,maxTouchPoints:navigator.maxTouchPoints||0,pointer:this.pointer,scroll:this.scroll,time:Date.now(),touch:this.touch}}attach(){const t={passive:!0,capture:!0};this.target.addEventListener("focusin",this.onFocus,t),this.target.addEventListener("keydown",this.onInteraction,t),this.target.addEventListener("pointerdown",this.onInteraction,t),this.target.addEventListener("pointermove",this.onPointer,t),this.target.addEventListener("scroll",this.onScroll,t),this.target.addEventListener("touchmove",this.onTouchMove,t)}evict(t){t.length>this.maxSamples&&t.splice(0,t.length-this.maxSamples)}onFocus=t=>{if(this.focusInteraction===2)return;const n=t.target;if(!(n instanceof Element))return;const r=performance.now();this.focusStartTime===0&&(this.focusStartTime=r),this.focus.push([Math.round(r-this.focusStartTime),n.tabIndex,this.TAG_CODES[n.tagName]??0,this.focusInteraction?1:0]),this.evict(this.focus)};onInteraction=t=>{this.focusInteraction="keyCode"in t?1:2,this.focusInteractionTimer&&clearTimeout(this.focusInteractionTimer),this.focusInteractionTimer=setTimeout(()=>{this.focusInteraction=0},100)};onPointer=t=>{if(t.pointerType==="touch")return;const n=t.timeStamp||performance.now();this.pendingPointer=[Math.round(t.clientX),Math.round(t.clientY),Math.round(n)],n-this.lastPointerSample>=this.sampleInterval&&(this.pointer.push(this.pendingPointer),this.lastPointerSample=n,this.pendingPointer=null,this.evict(this.pointer))};onScroll=()=>{const t=performance.now();t-this.lastScrollSample<this.sampleInterval||(this.scroll.push([Math.round(window.scrollY),Math.round(t)]),this.lastScrollSample=t,this.evict(this.scroll))};onTouchMove=t=>{const n=t.timeStamp||performance.now(),r=t.touches[0];r&&(this.pendingTouch=[Math.round(r.clientX),Math.round(r.clientY),Math.round(n),Math.round(r.force*1e3)/1e3,Math.round(r.radiusX||0),Math.round(r.radiusY||0)],n-this.lastTouchSample>=this.sampleInterval&&(this.touch.push(this.pendingTouch),this.lastTouchSample=n,this.pendingTouch=null,this.evict(this.touch)))}}var Vl=Z('<div class="altcha-overlay-backdrop" data-backdrop=""></div>'),jl=Z('<div class="altcha-overlay-content"></div>'),Bl=Z('<div role="button" class="altcha-overlay-close">&times;</div> <!>',1),zl=Z('<div class="altcha-floating-arrow"></div>'),Hl=Z('<input type="hidden"/>'),Kl=Z('<div class="altcha-error">Secure context (HTTPS) required.</div>'),Yl=Z('<div class="altcha-error"> </div>'),Gl=Z('<div class="altcha-error"> </div>'),ql=Z("<!> <!>",1),Wl=Z('<!> <div class="altcha"><!> <div class="altcha-main"><div><div class="altcha-checkbox-wrap"><!> <label><!></label></div> <!></div> <!> <!> <!></div> <!></div>',1);function Zl(e,t){nt(t,!0);const n=()=>ca(h,"$altchaDefaults",a),r=()=>ca(g,"$altchaI18nStore",a),[a,o]=fo(),l='input[type="text"]:not([data-no-spamfilter]), textarea:not([data-no-spamfilter])',s='input[type="submit"], button[type="submit"], button:not([type="button"]):not([type="reset"])',u=["ar","fa","he","ur"],{isSecureContext:c}=globalThis,{store:h}=globalThis.$altcha.defaults,v=navigator.hardwareConcurrency||2,d=navigator.deviceMemory||0,b=d&&d<=4?Math.min(4,v):v,g=globalThis.$altcha.i18n.store,x=t.$$host,$=(f,p)=>{Tt().then(()=>{x?.dispatchEvent(new CustomEvent(f,{detail:p}))})};let L=null,ce=P(it(new URL(location.origin))),re=P(!1),U=P(null),pe=P(null),F=P(null),C=P(it(V.UNVERIFIED)),G=P(void 0),me=P(void 0),R=P(null),ae=P(void 0),Je=P(null),Te=P(null),_e=P(null),Q=P(null),Xe=P(it([])),E=P(0),z=P(it({})),$e=P(!0);const m=be(()=>({fetch:(f,p)=>fetch(f,p),audioChallengeLanguage:"",auto:"off",barPlacement:"bottom",challenge:"",codeChallenge:null,codeChallengeDisplay:"standard",credentials:null,debug:!1,disableAutoFocus:!1,display:"standard",floatingAnchor:"",floatingOffset:8,floatingPersist:!1,floatingPlacement:"auto",hideFooter:!1,hideLogo:!1,humanInteractionSignature:!0,language:"",mockError:!1,minDuration:500,overlayContent:"",name:"altcha",popoverPlacement:"auto",retryOnOutOfMemoryError:!0,setCookie:null,serverVerificationFields:!1,serverVerificationTimeZone:!1,test:!1,timeout:9e4,type:"checkbox",validationMessage:"",verifyFunction:null,verifyUrl:"",workers:b,...n(),...i(z)})),$t=be(()=>`altcha-checkbox-${t.id||Math.floor(Math.random()*1e12).toString(16)}`),Dn=be(()=>Ql(i(m).type)),Me=be(()=>i(m).auto),Ar=be(()=>i(C)===V.VERIFYING),At=be(()=>!i(m).hideFooter),sn=be(()=>!i(m).hideLogo&&i(m).display!=="bar"),cn=be(()=>es(r(),[i(m).language,document.documentElement.lang,...navigator.languages])),un=be(()=>u.includes(i(cn).language)?"rtl":void 0),y=be(()=>({...i(cn).strings})),te=be(()=>i(U)?.audio?.match(/^(https?:)?\//)?Nn(i(U).audio,i(ce),{language:i(m).audioChallengeLanguage||i(cn).language}).toString():i(U)?.audio),Rt=be(()=>i(U)?.image?.match(/^(https?:)?\//)?Nn(i(U).image,i(ce)):i(U)?.image);Ce(()=>{hn({auto:t.auto,challenge:t.challenge,display:t.display,language:t.language,name:t.name,type:t.type,workers:t.workers})}),Ce(()=>{if(t.configuration)try{hn(JSON.parse(t.configuration))}catch{K("unable to parse the `configuration` attribute (JSON expected)")}}),Ce(()=>{i(F)!==i(m).display&&Un(i(m).display)}),Ce(()=>{i(re)&&i(C)===V.VERIFYING&&w(re,!1)}),Ce(()=>{!i(re)&&i(C)===V.VERIFIED&&w(re,!0)}),Ce(()=>{if(!i(re)){const f=Pr();f&&f.checked&&(f.checked=!1)}}),Ce(()=>{i(C)===V.VERIFIED&&Pr()?.setCustomValidity("")}),Ce(()=>{if(i(Me)==="onload"){const f=setTimeout(()=>{Bt()},1);return()=>{f&&clearTimeout(f)}}}),Ce(()=>{i(Te)&&K("error:",i(Te))}),Ce(()=>{i(Q)&&i(m).setCookie&&vs(i(Q),i(m).setCookie)}),pr(()=>(K("mounted","3.1.0"),x&&globalThis.$altcha.instances.add(x),w(R,i(ae)?.closest("form"),!0),i(R)?.addEventListener("reset",gi),i(R)?.addEventListener("submit",bi,{capture:!0}),i(R)?.addEventListener("focusin",pi),Rr(),i(m).humanInteractionSignature&&(K("human interaction signature enabled"),L=new Fl),$("load"),c||K("secure context (HTTPS) required"),()=>{Ir(),x&&globalThis.$altcha.instances.delete(x),i(_e)&&clearTimeout(i(_e)),i(R)?.removeEventListener("reset",gi),i(R)?.removeEventListener("submit",bi,{capture:!0}),i(R)?.removeEventListener("focusin",pi),L?.destroy()}));function Rr(){w(Xe,[...globalThis.$altcha.plugins].map(f=>new f(x)),!0),K("activating plugins",i(Xe).map(f=>f.constructor.name));for(const f of i(Xe))f.activate()}async function fn(f,...p){let _;for(const k of i(Xe))_=await k[f].call(k,...p);return _}function Ir(){for(const f of i(Xe))f.destroy()}function Or(f){const[p,_]=f.salt.split("?"),k={};if(_)try{Object.assign(k,Object.fromEntries(new URLSearchParams(_).entries()))}catch{}const T={codeChallenge:f.codeChallenge,parameters:{algorithm:f.algorithm,cost:1,data:k,expiresAt:k?.expires?parseInt(k.expires,10):void 0,keyLength:f.algorithm==="SHA-512"?64:f.algorithm==="SHA-384"?48:32,nonce:Nl(new TextEncoder().encode(f.salt)),keyPrefix:f.challenge,salt:""},signature:f.signature};return Object.defineProperties(T,{_originalSalt:{enumerable:!1,value:f.salt,writable:!1},_version:{enumerable:!1,value:1,writable:!1}}),T}function ke(f,p){return{algorithm:f.parameters.algorithm,challenge:f.parameters.keyPrefix,number:p.counter,salt:"_originalSalt"in f?f._originalSalt:f.parameters.nonce,signature:f.signature,took:p.time||0}}async function Qe(f){await new Promise(p=>setTimeout(p,f))}async function vi(f=i(m).challenge,p){const _=await fn("onFetchChallenge",f);let k=null;if(_!==void 0)return _;if(typeof f=="string")if(f.startsWith("{")){K("parsing JSON challenge");try{k=JSON.parse(f)}catch{throw new Error("Unable to parse JSON challenge.")}}else{K("fetching challenge from",p?.method||"GET",f),w(ce,new URL(f,location.origin),!0);const T=await i(m).fetch(f,{credentials:i(m).credentials||void 0,...p});await yi(T);const S=T.headers.get("x-altcha-config");S&&fs(S);const B=await T.json();if(B&&"his"in B&&B.his){if(K("requested HIS"),!L)throw new Error("Server requested HIS data but collector is disabled.");return vi(Nn(B.his.url,i(ce)),{body:JSON.stringify({his:L.export()}),headers:{"content-type":"application/json"},method:"POST"})}B&&"hisResult"in B&&B.hisResult&&K("HIS result",B.hisResult),k=B}else if(f&&typeof f=="object")try{k=JSON.parse(JSON.stringify(f))}catch{throw new Error("Unable to parse JSON challenge.")}if(Jl(k)&&(k=Or(k)),!Xl(k))throw new Error("Challenge validation failed.");return k}function Jl(f){return typeof f=="object"&&"challenge"in f}function Xl(f){return!!f&&typeof f=="object"&&"parameters"in f&&!!f.parameters&&typeof f.parameters=="object"&&"algorithm"in f.parameters&&"nonce"in f.parameters&&"salt"in f.parameters&&"keyPrefix"in f.parameters}function Pr(){return document.getElementById(i($t))}function Ql(f){switch(f){case"checkbox":return ii;case"switch":return li;default:return oi}}function es(f,p){const _=Object.keys(f).map(T=>T.toLowerCase());let k=p.reduce((T,S)=>(S=S.toLowerCase(),T||(f[S]?S:null)||_.find(B=>S.split("-")[0]===B.split("-")[0])||null),null);return f[k||""]||(k="en"),{language:k,strings:f[k]}}function ts(f){switch(f){case"bar":return i(m).barPlacement||"bottom";case"floating":return i(m).floatingPlacement||"auto";default:return}}function ns(f){return[...i(R)?.querySelectorAll(l)||[]].reduce((_,k)=>{const T=k.name,S=k.value;return T&&S&&(_[T]=/\n/.test(S)?S.replace(new RegExp("(?<!\\r)\\n","g"),`\r
`):S),_},{})}function rs(){try{return Intl.DateTimeFormat().resolvedOptions().timeZone}catch{}}function Nn(f,p,_){const k=new URL(f,p);if(k.search||(k.search=p.search),_)for(const T in _)_[T]!==void 0&&_[T]!==null&&k.searchParams.set(T,_[T]);return k.toString()}function as(f){!i(re)&&f.currentTarget.checked?(f.preventDefault(),f.currentTarget.checked=!1,i(C)!==V.VERIFYING&&Bt()):f.currentTarget.checked||(f.preventDefault(),De())}function is(f){i(C)===V.VERIFYING?f.currentTarget.setCustomValidity(i(y).waitAlert):i(m).validationMessage&&f.currentTarget.setCustomValidity(i(m).validationMessage)}function os(){Un(i(m).display),De()}function ls(){Fn()}function ss(f){const p=f.target;i(m).display==="floating"&&p&&!x?.contains(p)&&!p.hasAttribute("data-backdrop")&&!p.closest("[data-popover]")&&i(C)!==V.VERIFIED&&!i(m).floatingPersist&&Lr()}function pi(f){i(Me)==="onfocus"&&i(C)===V.UNVERIFIED&&Bt()}function gi(){Un(i(m).display),De()}function bi(f){f.target?.getAttribute("data-code-challenge")!=="true"&&i(Me)==="onsubmit"&&i(C)===V.UNVERIFIED&&(f.preventDefault(),f.stopPropagation(),w(Je,f.submitter,!0),Mr(),Bt().then(_=>{_&&!i(U)&&Tt().then(()=>{mi(i(Je))})}))}function cs(f){f.persisted&&(Un(i(m).display),De())}function us(){Fn()}function fs(f){try{const p=JSON.parse(f);p&&typeof p=="object"&&hn({serverVerificationFields:p?.sentinel?.fields,serverVerificationTimeZone:p?.sentinel?.timeZone,verifyUrl:p.verifyurl,...p})}catch(p){K("unable to configure from x-altcha-config header",p)}}function hs(f=20){if(!i(ae))return;const p=i(m).floatingPlacement;if(!i(me)&&(w(me,(i(m).floatingAnchor instanceof HTMLElement?i(m).floatingAnchor:i(m).floatingAnchor?document.querySelector(i(m).floatingAnchor):i(R)?.querySelector(s))||i(R),!0),!i(me))){K("unable to find floating anchor element");return}const _=parseInt(i(m).floatingOffset,10)||12,k=i(me).getBoundingClientRect(),T=i(ae).getBoundingClientRect(),S=document.documentElement.clientHeight,B=document.documentElement.clientWidth,Ae=!p||p==="auto"?k.bottom+T.height+_+f>S:p==="top",q=Math.max(f,Math.min(B-f-T.width,k.left+k.width/2-T.width/2));if(i(ae).style.setProperty("--altcha-floating-left",`${q}px`),i(ae).style.setProperty("--altcha-floating-top",Ae?`${k.top-(T.height+_)}px`:`${k.bottom+_}px`),i(ae).setAttribute("data-floating-position",Ae?"top":"bottom"),i(G)){const ie=i(G).getBoundingClientRect();i(G).style.left=k.left-q+k.width/2-ie.width/2+"px"}}async function ds(f,p){const _=await fn("onRequestServerVerification",f,p);if(_!==void 0)return _;if(K("requesting server verification from",i(m).verifyUrl),!i(m).verifyUrl)throw new Error("Parameter verifyUrl must be set for server verification.");const k=await i(m).fetch(Nn(i(m).verifyUrl,i(ce)),{body:JSON.stringify({code:p,fields:i(m).serverVerificationFields?ns():void 0,payload:f,timeZone:i(m).serverVerificationTimeZone?rs():void 0}),credentials:i(m).credentials||void 0,headers:{"Content-Type":"application/json"},method:"POST"});await yi(k);const T=await k.json();return T&&typeof T=="object"&&"payload"in T&&T.payload&&$("serververification",T),T}function mi(f){i(R)&&"requestSubmit"in i(R)?i(R).requestSubmit(f):i(R)?.reportValidity()&&(f?f.click():i(R).submit())}function vs(f,p={}){const{domain:_,name:k=i(m).name,maxAge:T,path:S,sameSite:B,secure:Ae}=p;let q=`${encodeURIComponent(k)}=${encodeURIComponent(f)}`;_&&(q+=`; Domain=${_}`),T!=null&&(q+=`; Max-Age=${T}`),S&&(q+=`; Path=${S}`),B&&(q+=`; SameSite=${B}`),Ae&&(q+="; Secure"),document.cookie=q}function Un(f){switch(f){case"bar":case"floating":case"overlay":Lr(),(!i(Me)||i(Me)==="off")&&(i(z).auto="onsubmit");break;case"standard":Mr()}i(F)!==f&&w(F,f,!0)}function ps(f){i(_e)&&clearTimeout(i(_e));const p=()=>{i(C)!==V.UNVERIFIED?(w(re,!1),Ne(V.EXPIRED)):De(),$("expired")},_=f*1e3-Date.now();_>=1?w(_e,setTimeout(p,_),!0):p()}async function yi(f){if(f.status>=400){if(f.headers.get("content-type")?.includes("/json")){let _;try{_=await f.json()}catch{}if(_&&"error"in _)throw new Error(`Server responded with ${f.status} - ${_.error}`)}throw new Error(`Server responded with ${f.status}.`)}const p=f.headers.get("content-type");if(!p||!p.includes("/json"))throw new Error(`Server responded with invalid content-type. Expected application/json, received ${p}.`)}async function wi(f){if(!i(Q)){Ne(V.ERROR,"Cannot verify code challenge without PoW payload.");return}Ne(V.VERIFYING);let p=null;if(i(m).verifyUrl)p=await ds(i(Q),f);else if(i(m).verifyFunction)p=await i(m).verifyFunction(i(Q),f);else{Ne(V.ERROR,"Parameter verifyUrl is required for code challenge verification.");return}p?.payload&&(w(Q,p.payload,!0),K("server payload",i(Q))),p?.verified===!0?(K("verified"),Ne(V.VERIFIED),$("verified",{payload:i(Q)}),i(Me)==="onsubmit"&&Tt().then(()=>{mi(i(Je))})):Ne(V.ERROR,p?.reason||"Verification failed."),i(m).disableAutoFocus||Pr()?.focus()}function hn(f){Object.assign(i(z),{...Object.fromEntries(Object.entries(f).filter(([p,_])=>_!==void 0))})}function gs(){return{...i(m)}}function bs(){return i(C)}function Lr(){w($e,!1)}function K(...f){(i(m).debug||f.some(p=>p instanceof Error))&&console[f[0]instanceof Error?"error":"log"]("ALTCHA",`[name=${i(m).name}]`,...f)}function De(f=V.UNVERIFIED,p=null){w(re,!1),w(Te,p,!0),w(Q,null),i(pe)&&i(pe).abort(),i(_e)&&(clearTimeout(i(_e)),w(_e,null)),Ne(f)}function Ne(f,p=null){w(C,f,!0),w(Te,p,!0),$("statechange",{payload:i(Q),state:i(C)})}function Mr(){w($e,!0),Tt().then(()=>{Fn()})}function Fn(){if(i(m).display==="floating")return hs();w(E,i(E)+1)}async function Bt(f={}){const{concurrency:p=Math.max(1,i(m).workers),controller:_=new AbortController,minDuration:k=i(m).minDuration}=f,T=performance.now();let S=null,B=null,Ae=!1;const q=await fn("onVerify",f);if(q!==void 0)return q;De(V.VERIFYING),w(pe,_,!0);try{if(!c)throw new Error("Secure context (HTTPS) required.");if(i(m).mockError)throw new Error("Mock error.");if(i(m).test)return K("running test mode with null challenge"),await Qe(Math.max(0,k-(performance.now()-T))),i(pe)?.signal.aborted?(De(),null):(w(Q,btoa(JSON.stringify({challenge:null,solution:null,test:!0})),!0),K("verified"),Ne(V.VERIFIED),$("verified",{payload:i(Q)}),{payload:i(Q)});if(S=await vi(),!S)throw new Error("Failed to fetch challenge.");K("challenge",S),"configuration"in S&&(K("re-configuring from challenge",S.configuration),hn(S.configuration)),S.parameters.expiresAt&&ps(S.parameters.expiresAt),Ae="_version"in S&&S._version===1;const ie=globalThis.$altcha.algorithms.get(S.parameters.algorithm);if(!ie)throw new Error(`Unsupported algorithm ${S.parameters.algorithm}.`);if(B=await ci({challenge:S,concurrency:p,controller:_,createWorker:ie,counterMode:Ae?"string":"uint32",onOutOfMemory:gt=>{if(K("out of memory error received"),$("outofmemory"),i(m).retryOnOutOfMemoryError&&gt>1){const bt=Math.floor(gt/2);return K(`retrying with ${bt} workers...`),bt}},timeout:i(m).timeout}),i(pe)?.signal.aborted)return De(),null;if(!B)throw new Error("Failed to find solution.");K("solution",B),await Qe(Math.max(0,k-(performance.now()-T))),w(U,S.codeChallenge||i(m).codeChallenge||null,!0),Ae?w(Q,btoa(JSON.stringify(ke(S,B))),!0):w(Q,btoa(JSON.stringify({challenge:{parameters:S.parameters,signature:S.signature},solution:B})),!0),i(U)?(K("requesting code verification"),Ne(V.CODE),$("codechallenge",{codeChallenge:i(U)})):i(m).verifyUrl?await wi():(K("verified"),Ne(V.VERIFIED),$("verified",{payload:i(Q)}))}catch(ie){return K("verification failed",ie),Ne(V.ERROR,String(ie)),null}finally{w(pe,null)}return{challenge:S,payload:i(Q),solution:B}}var ms={configure:hn,getConfiguration:gs,getState:bs,hide:Lr,log:K,reset:De,setState:Ne,show:Mr,updateUI:Fn,verify:Bt},_i=Wl();le("scroll",Wn,ls),le("click",Wn,ss),le("pageshow",xt,cs),le("resize",xt,us);var ki=Nt(_i);{var ys=f=>{var p=Vl();D(f,p)};se(ki,f=>{i(m).display==="overlay"&&i($e)&&f(ys)})}var et=W(ki,2),xi=X(et);{var ws=f=>{var p=Bl(),_=Nt(p),k=W(_,2);{var T=S=>{var B=jl();Wa(B,()=>document.querySelector(i(m).overlayContent)?.innerHTML,!0),Y(B),D(S,B)};se(k,S=>{i(m).overlayContent&&S(T)})}le("click",_,os,!0),D(f,p)};se(xi,f=>{i(m).display==="overlay"&&i($e)&&f(ws)})}var Dr=W(xi,2),Nr=X(Dr),Ur=X(Nr),Ei=X(Ur);{let f=be(()=>i(m).display==="standard"&&i(Me)!=="onsubmit"||i(C)===V.VERIFYING);Xo(Ei,()=>i(Dn),(p,_)=>{_(p,{get id(){return i($t)},name:"",get required(){return i(f)},get loading(){return i(Ar)},get checked(){return i(re)},onchange:as,oninvalid:is})})}var Fr=W(Ei,2),_s=X(Fr);{var ks=f=>{var p=Rn();ve(()=>Ze(p,i(y).verificationRequired)),D(f,p)},xs=f=>{var p=Rn();ve(()=>Ze(p,i(y).verifying)),D(f,p)},Es=f=>{var p=Rn();ve(()=>Ze(p,i(y).verified)),D(f,p)},Cs=f=>{var p=Rn();ve(()=>Ze(p,i(y).label)),D(f,p)};se(_s,f=>{i(C)===V.CODE&&i(U)?f(ks):i(C)===V.VERIFYING?f(xs,1):i(C)===V.VERIFIED?f(Es,2):f(Cs,-1)})}Y(Fr),Y(Ur);var Ss=W(Ur,2);{var Ts=f=>{Er(f,{get strings(){return i(y)}})};se(Ss,f=>{i(sn)&&f(Ts)})}Y(Nr);var Ci=W(Nr,2);{var $s=f=>{{let p=be(()=>i(m).display==="bar"&&i(sn));Cr(f,{get logo(){return i(p)},get strings(){return i(y)}})}};se(Ci,f=>{i(At)&&f($s)})}var Si=W(Ci,2);{var As=f=>{var p=zl();vt(p,_=>w(G,_),()=>i(G)),D(f,p)};se(Si,f=>{i(m).display==="floating"&&f(As)})}var Rs=W(Si,2);{var Is=f=>{var p=Hl();yr(p),ve(()=>{j(p,"name",i(m).name),hl(p,i(Q))}),D(f,p)};se(Rs,f=>{i(m).setCookie||f(Is)})}Y(Dr);var Os=W(Dr,2);{var Ps=f=>{Sr(f,{get anchor(){return i(ae)},onClickOutside:()=>{c&&De()},get placement(){return i(m).popoverPlacement},role:"alert",variant:"error",get dir(){return i(un)},get updateUISignal(){return i(E)},children:(p,_)=>{var k=Ya(),T=Nt(k);{var S=q=>{var ie=Kl();D(q,ie)},B=q=>{var ie=Yl(),gt=X(ie,!0);Y(ie),ve(()=>Ze(gt,i(y).expired)),D(q,ie)},Ae=q=>{var ie=Gl(),gt=X(ie,!0);Y(ie),ve(()=>{j(ie,"title",i(Te)),Ze(gt,i(y).error)}),D(q,ie)};se(T,q=>{!i(Te)&&!c?q(S):!i(Te)&&i(C)===V.EXPIRED?q(B,1):q(Ae,-1)})}D(p,k)},$$slots:{default:!0}})},Ls=f=>{var p=Ya(),_=Nt(p);Jo(_,()=>i(U),k=>{{let T=be(()=>i(m).codeChallengeDisplay!=="standard");Sr(k,{get anchor(){return i(ae)},get backdrop(){return i(T)},get display(){return i(m).codeChallengeDisplay},onClose:()=>{De()},get placement(){return i(m).popoverPlacement},role:"dialog",get"aria-label"(){return i(y).verificationRequired},get dir(){return i(un)},get updateUISignal(){return i(E)},children:(S,B)=>{var Ae=ql(),q=Nt(Ae);si(q,{get audioUrl(){return i(te)},get imageUrl(){return i(Rt)},onCancel:()=>De(),onReload:()=>Bt(),onSubmit:bt=>wi(bt),get codeChallenge(){return i(U)},get config(){return i(m)},get strings(){return i(y)}});var ie=W(q,2);{var gt=bt=>{Cr(bt,{get logo(){return i(sn)},get strings(){return i(y)}})};se(ie,bt=>{i(At)&&i(m).codeChallengeDisplay!=="standard"&&bt(gt)})}D(S,Ae)},$$slots:{default:!0}})}}),D(f,p)};se(Os,f=>{i(Te)||i(C)===V.EXPIRED||!c?f(Ps):i(U)&&i(C)===V.CODE&&f(Ls,1)})}Y(et),vt(et,f=>w(ae,f),()=>i(ae)),ve(f=>{j(et,"data-state",i(C)),j(et,"data-display",i(m).display||void 0),j(et,"data-placement",f),j(et,"data-visible",i($e)||void 0),j(et,"dir",i(un)),j(Fr,"for",i($t)),et.dir=et.dir},[()=>ts(i(m).display)]),D(e,_i);var Ms=rt(ms);return o(),Ms}typeof window<"u"&&window.customElements&&!customElements.get("altcha-widget")&&customElements.define("altcha-widget",pt(Zl,{auto:{type:"String"},challenge:{type:"String"},configuration:{type:"String"},display:{type:"String"},language:{type:"String"},name:{type:"String"},theme:{type:"String"},type:{type:"String"},workers:{type:"Number"}},[],["configure","getConfiguration","getState","hide","log","reset","setState","show","updateUI","verify"]));const ui=`(function() {
  "use strict";
  function bufferStartsWith(buffer, prefix) {
    if (prefix.length > buffer.length) {
      return false;
    }
    for (let i = 0; i < prefix.length; i++) {
      if (buffer[i] !== prefix[i]) {
        return false;
      }
    }
    return true;
  }
  function bufferToHex(buffer) {
    return Array.from(new Uint8Array(buffer)).map((b) => b.toString(16).padStart(2, "0")).join("");
  }
  function concatBuffers(a, b) {
    const out = new Uint8Array(a.length + b.length);
    out.set(a, 0);
    out.set(b, a.length);
    return out;
  }
  function hexToBuffer(hex) {
    if (hex.length % 2 !== 0) {
      throw new Error(\`Hex string must have an even length. Got: \${hex}\`);
    }
    const buffer = new ArrayBuffer(hex.length / 2);
    const view = new DataView(buffer);
    for (let i = 0; i < hex.length; i += 2) {
      const byteString = hex.substring(i, i + 2);
      const byteValue = parseInt(byteString, 16);
      view.setUint8(i / 2, byteValue);
    }
    return new Uint8Array(buffer);
  }
  async function delay(ms) {
    await new Promise((resolve) => setTimeout(resolve, ms));
  }
  function timeDuration(start) {
    return Math.floor((performance.now() - start) * 10) / 10;
  }
  class PasswordBuffer {
    constructor(nonce, mode = "uint32") {
      this.nonce = nonce;
      this.mode = mode;
      this.buffer = new Uint8Array(this.nonce.length + this.COUNTER_BYTES);
      this.buffer.set(this.nonce, 0);
      this.dataView = new DataView(this.buffer.buffer);
    }
    COUNTER_BYTES = 4;
    buffer;
    dataView;
    encoder = new TextEncoder();
    /**
     * Appends the counter to the nonce buffer.
     * In 'string' mode, encodes the counter as a UTF-8 string.
     * In 'uint32' mode, writes the counter as a big-endian 32-bit integer.
     */
    setCounter(n) {
      if (this.mode === "string") {
        return concatBuffers(this.nonce, this.encoder.encode(n.toString()));
      }
      this.dataView.setUint32(this.nonce.length, n, false);
      return this.buffer;
    }
  }
  async function solveChallenge(options) {
    const {
      challenge,
      controller,
      counterMode = "uint32",
      counterStart = 0,
      counterStep = 1,
      deriveKey: deriveKey2,
      timeout = 9e4
    } = options;
    const { nonce, keyPrefix, salt } = challenge.parameters;
    const nonceBuf = hexToBuffer(nonce);
    const saltBuf = hexToBuffer(salt);
    const keyPrefixBuf = keyPrefix.length % 2 === 0 ? hexToBuffer(keyPrefix) : null;
    const password = new PasswordBuffer(nonceBuf, counterMode);
    const start = performance.now();
    let counter = counterStart;
    let iterations = 0;
    let derivedKeyHex = "";
    let lastYield = start;
    while (true) {
      if (controller?.signal.aborted || timeout && iterations % 10 === 0 && performance.now() - start > timeout) {
        return null;
      }
      const { derivedKey } = await deriveKey2(
        challenge.parameters,
        saltBuf,
        password.setCounter(counter)
      );
      if (iterations % 10 === 0 && performance.now() - lastYield > 200) {
        await delay(0);
        lastYield = performance.now();
      }
      if (keyPrefixBuf ? bufferStartsWith(derivedKey, keyPrefixBuf) : bufferToHex(derivedKey).startsWith(keyPrefix)) {
        derivedKeyHex = bufferToHex(derivedKey);
        break;
      }
      counter = counter + counterStep;
      iterations = iterations + 1;
    }
    return {
      counter,
      derivedKey: derivedKeyHex,
      time: timeDuration(start)
    };
  }
  function handler(options) {
    const { deriveKey: deriveKey2 } = options;
    let controller = void 0;
    self.onmessage = async (message) => {
      const { challenge, counterMode, counterStart, counterStep, timeout, type } = message.data;
      if (type === "abort") {
        controller?.abort();
      } else if (type === "work") {
        controller = new AbortController();
        let solution;
        try {
          solution = await solveChallenge({
            challenge,
            controller,
            counterStart,
            counterStep,
            deriveKey: deriveKey2,
            counterMode,
            timeout
          });
        } catch (err) {
          return self.postMessage({ error: err });
        }
        self.postMessage(solution);
      }
    };
  }
  function getDigest(algorithm) {
    switch (algorithm) {
      case "PBKDF2/SHA-512":
        return "SHA-512";
      case "PBKDF2/SHA-384":
        return "SHA-384";
      case "PBKDF2/SHA-256":
      default:
        return "SHA-256";
    }
  }
  async function deriveKey(parameters, salt, password) {
    const { algorithm, cost, keyLength = 32 } = parameters;
    const passwordKey = await crypto.subtle.importKey(
      "raw",
      password,
      { name: "PBKDF2" },
      false,
      ["deriveKey"]
    );
    const derivedKey = await crypto.subtle.deriveKey(
      {
        name: "PBKDF2",
        salt,
        iterations: cost,
        hash: getDigest(algorithm)
      },
      passwordKey,
      { name: "AES-GCM", length: keyLength * 8 },
      true,
      ["encrypt"]
    );
    return {
      derivedKey: new Uint8Array(await crypto.subtle.exportKey("raw", derivedKey))
    };
  }
  handler({
    deriveKey
  });
})();
`,fi=typeof self<"u"&&self.Blob&&new Blob(["(self.URL || self.webkitURL).revokeObjectURL(self.location.href);",ui],{type:"text/javascript;charset=utf-8"});function Tr(e){let t;try{if(t=fi&&(self.URL||self.webkitURL).createObjectURL(fi),!t)throw"";const n=new Worker(t,{name:e?.name});return n.addEventListener("error",()=>{(self.URL||self.webkitURL).revokeObjectURL(t)}),n}catch{return new Worker("data:text/javascript;charset=utf-8,"+encodeURIComponent(ui),{name:e?.name})}}const hi=`(function() {
  "use strict";
  function bufferStartsWith(buffer, prefix) {
    if (prefix.length > buffer.length) {
      return false;
    }
    for (let i = 0; i < prefix.length; i++) {
      if (buffer[i] !== prefix[i]) {
        return false;
      }
    }
    return true;
  }
  function bufferToHex(buffer) {
    return Array.from(new Uint8Array(buffer)).map((b) => b.toString(16).padStart(2, "0")).join("");
  }
  function concatBuffers(a, b) {
    const out = new Uint8Array(a.length + b.length);
    out.set(a, 0);
    out.set(b, a.length);
    return out;
  }
  function hexToBuffer(hex) {
    if (hex.length % 2 !== 0) {
      throw new Error(\`Hex string must have an even length. Got: \${hex}\`);
    }
    const buffer = new ArrayBuffer(hex.length / 2);
    const view = new DataView(buffer);
    for (let i = 0; i < hex.length; i += 2) {
      const byteString = hex.substring(i, i + 2);
      const byteValue = parseInt(byteString, 16);
      view.setUint8(i / 2, byteValue);
    }
    return new Uint8Array(buffer);
  }
  async function delay(ms) {
    await new Promise((resolve) => setTimeout(resolve, ms));
  }
  function timeDuration(start) {
    return Math.floor((performance.now() - start) * 10) / 10;
  }
  class PasswordBuffer {
    constructor(nonce, mode = "uint32") {
      this.nonce = nonce;
      this.mode = mode;
      this.buffer = new Uint8Array(this.nonce.length + this.COUNTER_BYTES);
      this.buffer.set(this.nonce, 0);
      this.dataView = new DataView(this.buffer.buffer);
    }
    COUNTER_BYTES = 4;
    buffer;
    dataView;
    encoder = new TextEncoder();
    /**
     * Appends the counter to the nonce buffer.
     * In 'string' mode, encodes the counter as a UTF-8 string.
     * In 'uint32' mode, writes the counter as a big-endian 32-bit integer.
     */
    setCounter(n) {
      if (this.mode === "string") {
        return concatBuffers(this.nonce, this.encoder.encode(n.toString()));
      }
      this.dataView.setUint32(this.nonce.length, n, false);
      return this.buffer;
    }
  }
  async function solveChallenge(options) {
    const {
      challenge,
      controller,
      counterMode = "uint32",
      counterStart = 0,
      counterStep = 1,
      deriveKey: deriveKey2,
      timeout = 9e4
    } = options;
    const { nonce, keyPrefix, salt } = challenge.parameters;
    const nonceBuf = hexToBuffer(nonce);
    const saltBuf = hexToBuffer(salt);
    const keyPrefixBuf = keyPrefix.length % 2 === 0 ? hexToBuffer(keyPrefix) : null;
    const password = new PasswordBuffer(nonceBuf, counterMode);
    const start = performance.now();
    let counter = counterStart;
    let iterations = 0;
    let derivedKeyHex = "";
    let lastYield = start;
    while (true) {
      if (controller?.signal.aborted || timeout && iterations % 10 === 0 && performance.now() - start > timeout) {
        return null;
      }
      const { derivedKey } = await deriveKey2(
        challenge.parameters,
        saltBuf,
        password.setCounter(counter)
      );
      if (iterations % 10 === 0 && performance.now() - lastYield > 200) {
        await delay(0);
        lastYield = performance.now();
      }
      if (keyPrefixBuf ? bufferStartsWith(derivedKey, keyPrefixBuf) : bufferToHex(derivedKey).startsWith(keyPrefix)) {
        derivedKeyHex = bufferToHex(derivedKey);
        break;
      }
      counter = counter + counterStep;
      iterations = iterations + 1;
    }
    return {
      counter,
      derivedKey: derivedKeyHex,
      time: timeDuration(start)
    };
  }
  function handler(options) {
    const { deriveKey: deriveKey2 } = options;
    let controller = void 0;
    self.onmessage = async (message) => {
      const { challenge, counterMode, counterStart, counterStep, timeout, type } = message.data;
      if (type === "abort") {
        controller?.abort();
      } else if (type === "work") {
        controller = new AbortController();
        let solution;
        try {
          solution = await solveChallenge({
            challenge,
            controller,
            counterStart,
            counterStep,
            deriveKey: deriveKey2,
            counterMode,
            timeout
          });
        } catch (err) {
          return self.postMessage({ error: err });
        }
        self.postMessage(solution);
      }
    };
  }
  async function deriveKey(parameters, salt, password) {
    const { algorithm, keyLength = 32 } = parameters;
    const iterations = Math.max(1, parameters.cost);
    let data = void 0;
    let derivedKey = void 0;
    for (let i = 0; i < iterations; i++) {
      if (i === 0) {
        data = concatBuffers(salt, password);
      } else {
        data = derivedKey;
      }
      derivedKey = new Uint8Array(
        (await crypto.subtle.digest(algorithm, data)).slice(0, keyLength)
      );
    }
    return {
      parameters: {},
      derivedKey
    };
  }
  handler({
    deriveKey
  });
})();
`,di=typeof self<"u"&&self.Blob&&new Blob(["(self.URL || self.webkitURL).revokeObjectURL(self.location.href);",hi],{type:"text/javascript;charset=utf-8"});function $r(e){let t;try{if(t=di&&(self.URL||self.webkitURL).createObjectURL(di),!t)throw"";const n=new Worker(t,{name:e?.name});return n.addEventListener("error",()=>{(self.URL||self.webkitURL).revokeObjectURL(t)}),n}catch{return new Worker("data:text/javascript;charset=utf-8,"+encodeURIComponent(hi),{name:e?.name})}}return Ul(`:root {
  --altcha-border-color: var(--altcha-color-neutral);
  --altcha-border-width: 1px;
  --altcha-border-radius: 6px;
  --altcha-color-base: light-dark(oklch(100% 0.00011 271.152), oklch(20.904% 0.00002 271.152));
  --altcha-color-base-content: light-dark(
  	oklch(20.904% 0.00002 271.152),
  	oklch(100% 0.00011 271.152)
  );
  --altcha-color-error: oklch(51.284% 0.20527 28.678);
  --altcha-color-error-content: oklch(100% 0.00011 271.152);
  --altcha-color-neutral: light-dark(oklch(83.591% 0.0001 271.152), oklch(46.04% 0.00005 271.152));
  --altcha-color-neutral-content: light-dark(
  	oklch(46.76% 0.00005 271.152),
  	oklch(100% 0.00011 271.152)
  );
  --altcha-color-primary: oklch(40.279% 0.2449 268.131);
  --altcha-color-primary-content: oklch(100% 0.00011 271.152);
  --altcha-color-success: oklch(55.748% 0.18968 142.511);
  --altcha-color-success-content: oklch(100% 0.00011 271.152);
  --altcha-checkbox-border-color: light-dark(
  	oklch(66.494% 0.00233 15.434),
  	oklch(51.028% 0.00006 271.152)
  );
  --altcha-checkbox-border-radius: 5px;
  --altcha-checkbox-border-width: var(--altcha-border-width);
  --altcha-checkbox-outline: 2px solid var(--altcha-checkbox-outline-color);
  --altcha-checkbox-outline-color: -webkit-focus-ring-color;
  --altcha-checkbox-outline-offset: 2px;
  --altcha-checkbox-size: 22px;
  --altcha-checkbox-transition-duration: var(--altcha-transition-duration);
  --altcha-input-background-color: var(--altcha-color-base);
  --altcha-input-border-radius: 3px;
  --altcha-input-border-width: 1px;
  --altcha-input-color: var(--altcha-color-base-content);
  --altcha-max-width: 320px;
  --altcha-padding: 0.75rem;
  --altcha-popover-arrow-size: 6px;
  --altcha-popover-color: var(--altcha-border-color);
  --altcha-shadow: drop-shadow(3px 3px 6px oklch(0% 0 0 / 0.2));
  --altcha-spinner-color: var(--altcha-color-base-content);
  --altcha-switch-background-color: var(--altcha-color-neutral);
  --altcha-switch-border-radius: calc(infinity * 1px);
  --altcha-switch-height: var(--altcha-checkbox-size);
  --altcha-switch-padding: 0.25rem;
  --altcha-switch-width: calc(var(--altcha-checkbox-size) * 1.75);
  --altcha-switch-toggle-border-radius: 100%;
  --altcha-switch-toggle-color: var(--altcha-color-neutral-content);
  --altcha-switch-toggle-size: calc(
  	var(--altcha-switch-height) - calc(var(--altcha-switch-padding) * 2)
  );
  --altcha-transition-duration: 0.6s;
  --altcha-z-index: 99999999;
  --altcha-z-index-popover: 999999999;
}

@supports (-moz-appearance: none) {
  :root {
    --altcha-checkbox-outline-color: var(--altcha-color-primary);
  }
}
.altcha {
  all: revert-layer;
  display: none;
  font-family: inherit;
  font-size: inherit;
  position: relative;
}
.altcha[data-visible] {
  display: block;
}
.altcha-popover, .altcha-popover * {
  all: revert-layer;
  box-sizing: border-box;
  font-family: inherit;
  font-size: inherit;
  line-height: 1.25;
}
.altcha * {
  all: revert-layer;
  box-sizing: border-box;
  font-family: inherit;
  font-size: inherit;
  line-height: 1.25;
}
.altcha a, .altcha-popover a {
  color: currentColor;
  text-decoration: none;
}
.altcha a:hover, .altcha-popover a:hover {
  color: currentColor;
}
.altcha-main {
  align-items: start;
  background-color: var(--altcha-color-base);
  border: var(--altcha-border-width, 1px) solid var(--altcha-border-color);
  border-radius: var(--altcha-border-radius, 0);
  color: var(--altcha-color-base-content);
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  justify-content: space-between;
  padding: var(--altcha-padding);
  max-width: var(--altcha-max-width, 100%);
}
.altcha-main > * {
  display: flex;
  width: 100%;
}
.altcha-main > *:first-child {
  flex-grow: 1;
}
.altcha-checkbox-wrap {
  align-items: center;
  display: flex;
  flex-direction: row;
  flex-grow: 1;
  gap: 0.5rem;
}
.altcha-checkbox-wrap > * {
  display: flex;
}
.altcha-logo {
  opacity: 0.7;
}
.altcha-footer {
  align-items: center;
  display: flex;
  flex-grow: 1;
  gap: 0.5rem;
  justify-content: flex-end;
  font-size: 0.7rem;
  opacity: 0.7;
}
.altcha-footer p {
  margin: 0;
  padding: 0;
}
.altcha-error {
  font-size: 0.85rem;
}
.altcha-button {
  align-items: center;
  background: var(--altcha-color-primary);
  border: var(--altcha-input-border-width) solid var(--altcha-color-primary);
  border-radius: var(--altcha-input-border-radius);
  color: var(--altcha-color-primary-content);
  cursor: pointer;
  display: flex;
  font-size: 0.9rem;
  gap: 0.5rem;
  padding: 0.35rem;
}
.altcha-button:focus {
  border-color: var(--altcha-color-primary);
  outline: var(--altcha-checkbox-outline);
  outline-offset: var(--altcha-checkbox-outline-offset);
}
.altcha-button > .altcha-spinner, .altcha-button > svg {
  height: 20px;
  width: 20px;
}
.altcha-button-secondary {
  background: transparent;
  border-color: var(--altcha-color-neutral);
  color: var(--altcha-color-neutral-content);
}
.altcha-input {
  background: var(--altcha-input-background-color);
  border: var(--altcha-input-border-width) solid var(--altcha-color-neutral);
  border-radius: var(--altcha-input-border-radius);
  color: var(--altcha-input-color);
  flex-grow: 1;
  font-size: 1rem;
  min-width: 0;
  padding: 0.25rem;
  width: auto;
}
.altcha-input:focus {
  border-color: var(--altcha-color-primary);
  outline: var(--altcha-checkbox-outline);
  outline-offset: var(--altcha-checkbox-outline-offset);
}
.altcha-spinner {
  animation: altcha-rotate 0.6s linear infinite;
  border-radius: 100%;
  border: var(--altcha-checkbox-border-width) solid var(--altcha-spinner-color);
  border-bottom-color: transparent;
  border-right-color: transparent;
  opacity: 0.7;
}
.altcha-popover {
  background-color: var(--altcha-color-base);
  border: var(--altcha-border-width) solid var(--altcha-border-color);
  border-radius: var(--altcha-border-radius);
  color: var(--altcha-color-base-content);
  filter: var(--altcha-shadow);
  position: absolute;
  left: calc(var(--altcha-padding) / 2);
  max-width: calc(var(--altcha-max-width) - var(--altcha-padding));
  top: calc(var(--altcha-padding) + var(--altcha-checkbox-size) + var(--altcha-popover-arrow-size));
  z-index: var(--altcha-z-index-popover);
}
.altcha-popover-arrow {
  border: var(--altcha-popover-arrow-size) solid transparent;
  border-bottom-color: var(--altcha-popover-color);
  content: "";
  height: 0;
  left: calc(var(--altcha-checkbox-size) / 2);
  position: absolute;
  top: calc(var(--altcha-popover-arrow-size) * -2);
  width: 0;
}
.altcha-popover-content {
  max-height: 100dvh;
  overflow: auto;
  padding: var(--altcha-padding);
}
.altcha-popover[data-top=true][data-display=standard] {
  bottom: calc(100% - (var(--altcha-padding) - var(--altcha-popover-arrow-size)));
  top: auto;
}
.altcha-popover[data-top=true][data-display=standard] .altcha-popover-arrow {
  border-bottom-color: transparent;
  border-top-color: var(--altcha-popover-color);
  bottom: calc(var(--altcha-popover-arrow-size) * -2);
  top: auto;
}
.altcha-popover[data-variant=error] {
  --altcha-popover-color: var(--altcha-color-error);
  background-color: var(--altcha-color-error);
  border-color: var(--altcha-color-error);
  color: var(--altcha-color-error-content);
}
.altcha-popover[data-variant=error] .altcha-popover-content {
  padding: calc(var(--altcha-padding) / 1.5) var(--altcha-padding);
}
.altcha-popover[data-display=overlay] {
  animation: altcha-overlay-slidein 0.5s forwards;
  left: 50%;
  position: fixed;
  top: 45%;
  transform: translate(-50%, -50%);
  width: var(--altcha-max-width);
  z-index: var(--altcha-z-index);
}
.altcha-popover[data-display=bottomsheet] {
  animation: altcha-bottomsheet-slideup 0.5s forwards;
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
  border-bottom: 0;
  bottom: -100%;
  left: 50%;
  position: fixed;
  top: auto;
  transform: translate(-50%, 0);
  width: var(--altcha-max-width);
  z-index: var(--altcha-z-index);
}
.altcha-popover[data-display=bottomsheet] .altcha-popover-content {
  padding-bottom: calc(var(--altcha-padding) * 2);
}
.altcha-popover-backdrop {
  background: var(--altcha-color-base-content);
  bottom: 0;
  left: 0;
  opacity: 0.1;
  position: fixed;
  right: 0;
  top: 0;
  transition: opacity 0.5s;
  z-index: var(--altcha-z-index);
}
.altcha-popover-close {
  color: var(--altcha-color-base-content);
  cursor: pointer;
  display: inline-block;
  font-size: 1rem;
  height: 1.25rem;
  line-height: 0.95;
  position: absolute;
  right: 0;
  text-align: center;
  text-shadow: 0 0 1px var(--altcha-color-base);
  top: -1.5rem;
  width: 1.25rem;
  z-index: var(--altcha-z-index);
}
[dir=rtl] .altcha-popover {
  left: auto;
  right: calc(var(--altcha-padding) / 2);
}
[dir=rtl] .altcha-popover-arrow {
  left: auto;
  right: calc(var(--altcha-checkbox-size) / 2);
}
[dir=rtl] .altcha-popover-close {
  left: 0;
  right: auto;
}
.altcha-popover[data-display=bottomsheet] .altcha-footer, .altcha-popover[data-display=overlay] .altcha-footer {
  align-items: center;
  justify-content: center;
  padding-top: 1rem;
  gap: 0.5rem;
}
.altcha-popover[data-display=bottomsheet] .altcha-footer svg, .altcha-popover[data-display=overlay] .altcha-footer svg {
  height: 18px;
  width: 18px;
  vertical-align: middle;
}
.altcha-code-challenge > form {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.altcha-code-challenge-title {
  font-weight: 600;
}
.altcha-code-challenge-text {
  font-size: 0.85rem;
}
.altcha-code-challenge-image {
  background: white;
  border: var(--altcha-input-border-width) solid var(--altcha-color-neutral);
  border-radius: var(--altcha-input-border-radius);
  object-fit: contain;
  height: 50px;
}
.altcha-code-challenge-row {
  display: flex;
  gap: 0.5rem;
}
.altcha-code-challenge-buttons {
  align-items: center;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: var(--altcha-padding);
  justify-content: space-between;
}
.altcha-code-challenge-buttons button {
  justify-content: center;
  width: 100%;
}
.altcha-checkbox {
  cursor: pointer;
  height: var(--altcha-checkbox-size);
  position: relative;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox input {
  appearance: none;
  background: var(--altcha-input-background-color);
  border: var(--altcha-checkbox-border-width, 2px) solid var(--altcha-checkbox-border-color);
  border-radius: var(--altcha-checkbox-border-radius);
  cursor: pointer;
  height: var(--altcha-checkbox-size);
  left: 0;
  margin: 0;
  padding: 0;
  position: absolute;
  top: 0;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox input:before {
  border-radius: var(--altcha-checkbox-border-radius);
  content: "";
  width: 100%;
  height: 100%;
  background: var(--altcha-color-neutral);
  display: block;
  transform: scale(0);
}
.altcha-checkbox input:checked {
  background-color: var(--altcha-color-success);
  border-color: var(--altcha-color-success);
}
.altcha-checkbox input:checked::before {
  background-color: var(--altcha-color-success);
  opacity: 0;
  transform: scale(2.2);
  transition: all var(--altcha-checkbox-transition-duration) ease;
  transition-delay: 0.1s;
}
.altcha-checkbox svg {
  --altcha-radio-svg-size: calc(var(--altcha-checkbox-size) * 0.5);
  --altcha-radio-svg-offset: calc(var(--altcha-checkbox-size) * 0.25);
  fill: none;
  left: var(--altcha-radio-svg-offset);
  height: var(--altcha-radio-svg-size);
  opacity: 0;
  position: absolute;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-dasharray: 16px;
  stroke-dashoffset: 16px;
  top: var(--altcha-radio-svg-offset);
  transform: translate3d(0, 0, 0);
  width: var(--altcha-radio-svg-size);
}
.altcha-checkbox input:checked + svg {
  color: var(--altcha-color-success-content);
  opacity: 1;
  stroke-dashoffset: 0;
  transition: all var(--altcha-checkbox-transition-duration) ease;
  transition-delay: 0.1s;
}
.altcha-checkbox-spinner {
  display: none;
  left: 0;
  height: var(--altcha-checkbox-size);
  position: absolute;
  top: 0;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox[data-loading=true] input {
  appearance: none;
  opacity: 0;
  pointer-events: none;
}
.altcha-checkbox[data-loading=true] .altcha-checkbox-spinner {
  display: block;
}
.altcha-checkbox-native {
  height: var(--altcha-checkbox-size);
  position: relative;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox-native input {
  height: var(--altcha-checkbox-size);
  margin: 0;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox-native-spinner {
  display: none;
  left: 0;
  height: var(--altcha-checkbox-size);
  position: absolute;
  top: 0;
  width: var(--altcha-checkbox-size);
}
.altcha-checkbox-native[data-loading=true] input {
  appearance: none;
  opacity: 0;
  pointer-events: none;
}
.altcha-checkbox-native[data-loading=true] .altcha-checkbox-native-spinner {
  display: block;
}
.altcha-switch {
  align-items: center;
  border-radius: var(--altcha-switch-border-radius);
  background-color: var(--altcha-switch-background-color);
  display: flex;
  height: var(--altcha-switch-height);
  padding: var(--altcha-switch-padding);
  position: relative;
  width: var(--altcha-switch-width);
}
.altcha-switch:focus-within {
  outline: var(--altcha-checkbox-outline);
  outline-offset: var(--altcha-checkbox-outline-offset);
}
.altcha-switch input {
  appearance: none;
  cursor: pointer;
  height: 100%;
  left: 0;
  opacity: 0;
  position: absolute;
  top: 0;
  width: 100%;
}
.altcha-switch-toggle {
  align-items: center;
  background-color: var(--altcha-switch-toggle-color);
  border-radius: var(--altcha-switch-toggle-border-radius);
  cursor: pointer;
  display: flex;
  height: var(--altcha-switch-toggle-size);
  justify-content: center;
  left: var(--altcha-switch-padding);
  position: absolute;
  transition: width 150ms ease-out, left 150ms ease-out;
  width: var(--altcha-switch-toggle-size);
}
.altcha-switch-spinner {
  display: none;
  height: var(--altcha-switch-toggle-size);
  width: var(--altcha-switch-toggle-size);
}
.altcha-switch[data-loading=true] {
  pointer-events: none;
}
.altcha-switch[data-loading=true] .altcha-switch-spinner {
  display: block;
}
.altcha-switch[data-loading=true] .altcha-switch-toggle {
  background-color: transparent;
  left: calc(50% - var(--altcha-switch-toggle-size) / 2);
}
[data-state=verified] .altcha-switch {
  --altcha-switch-background-color: var(--altcha-color-success);
}
[data-state=verified] .altcha-switch-toggle {
  background-color: var(--altcha-color-success-content);
  left: calc(100% - var(--altcha-switch-height) + var(--altcha-switch-padding));
}
[dir=rtl] .altcha-switch-toggle {
  left: calc(100% - var(--altcha-switch-height) + var(--altcha-switch-padding));
}
[dir=rtl][data-state=verified] .altcha-switch-toggle {
  left: var(--altcha-switch-padding);
}
.altcha-floating-arrow {
  border: 6px solid transparent;
  border-bottom-color: var(--altcha-border-color);
  content: "";
  height: 0;
  left: 12px;
  position: absolute;
  top: -12px;
  width: 0;
}
.altcha-overlay-backdrop {
  bottom: 0;
  left: 0;
  position: fixed;
  right: 0;
  top: 0;
  transition: opacity var(--altcha-transition-duration);
  z-index: var(--altcha-z-index);
}
.altcha-overlay-close {
  display: inline-block;
  color: currentColor;
  cursor: pointer;
  font-size: 1rem;
  height: 1rem;
  line-height: 0.85;
  position: absolute;
  right: 0;
  text-align: center;
  text-shadow: 0 0 1px var(--altcha-color-base);
  top: -1.5rem;
  width: 1rem;
  z-index: var(--altcha-z-index);
}
.altcha[data-display=overlay] {
  animation: altcha-overlay-slidein var(--altcha-transition-duration) forwards;
  filter: var(--altcha-shadow);
  left: 50%;
  opacity: 0;
  position: fixed;
  top: 45%;
  transform: translate(-50%, -50%);
  z-index: var(--altcha-z-index);
}
.altcha[data-display=overlay] .altcha-main {
  width: var(--altcha-max-width);
}
.altcha[data-display=floating] {
  display: none;
  filter: var(--altcha-shadow);
  left: var(--altcha-floating-left, -100%);
  position: fixed;
  top: var(--altcha-floating-top, -100%);
  z-index: var(--altcha-z-index);
}
.altcha[data-display=floating] .altcha-main {
  width: var(--altcha-max-width);
}
.altcha[data-display=floating][data-floating-position=top] .altcha-floating-arrow {
  border-bottom-color: transparent;
  border-top-color: var(--altcha-border-color);
  bottom: -12px;
  top: auto;
}
.altcha[data-display=floating][data-visible] {
  display: flex;
}
.altcha[data-display=bar] {
  bottom: -100%;
  filter: var(--altcha-shadow);
  left: 0;
  position: fixed;
  right: 0;
  transition: bottom var(--altcha-transition-duration), top var(--altcha-transition-duration);
  z-index: var(--altcha-z-index);
}
.altcha[data-display=bar] .altcha-main {
  align-items: center;
  border-radius: 0;
  border-width: var(--altcha-border-width) 0 0 0;
  flex-direction: row;
  max-width: 100% !important;
}
.altcha[data-display=bar] .altcha-main > * {
  width: auto;
}
.altcha[data-display=bar][data-placement=top] {
  bottom: auto;
  top: -100%;
}
.altcha[data-display=bar][data-placement=top] .altcha-main {
  border-width: 0 0 var(--altcha-border-width) 0;
}
.altcha[data-display=bar][data-placement=bottom]:not([data-state=unverified]) {
  bottom: 0;
}
.altcha[data-display=bar][data-placement=top]:not([data-state=unverified]) {
  top: 0;
}
.altcha[data-display=invisible] {
  display: none;
}

@keyframes altcha-rotate {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
@keyframes altcha-bottomsheet-slideup {
  100% {
    bottom: 0;
  }
}
@keyframes altcha-overlay-slidein {
  100% {
    opacity: 1;
    top: 50%;
  }
}`),$altcha.algorithms.set("SHA-256",()=>new $r),$altcha.algorithms.set("SHA-384",()=>new $r),$altcha.algorithms.set("SHA-512",()=>new $r),$altcha.algorithms.set("PBKDF2/SHA-256",()=>new Tr),$altcha.algorithms.set("PBKDF2/SHA-384",()=>new Tr),$altcha.algorithms.set("PBKDF2/SHA-512",()=>new Tr),Ti}var Ns=Ds();export{Ns as default};
