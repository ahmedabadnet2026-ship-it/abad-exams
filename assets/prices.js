/* ABAD Institute exam prices (SAR) — institute price, NOT the vendor's official fee.
   To add a price: add a line "vendor/certId": 1234  (ids = booking.html#v=<vendor>&c=<certId>). */
window.ABAD_PRICES = {
  "peoplecert/itil5": 2550
};
(function(){
var P=window.ABAD_PRICES,CONTACT=false; /* set CONTACT=true to show a subtle "تواصل معنا للسعر" when no price */
function en(){try{return (window.LANG||new URLSearchParams(location.search).get("lang")||(location.hash.match(/lang=(en|ar)/)||[])[1]||localStorage.getItem("abad-lang"))==="en"}catch(e){return false}}
var SAR_SVG='<svg class="sar-sym" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1124.14 1256.39" fill="currentColor" role="img" aria-label="SAR"><path d="M699.62,1113.02h0c-20.06,44.48-33.32,92.75-38.4,143.37l424.51-90.24c20.06-44.47,33.31-92.75,38.4-143.37l-424.51,90.24Z"/><path d="M1085.73,895.8c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.33v-135.2l292.27-62.11c20.06-44.47,33.32-92.75,38.4-143.37l-330.68,70.27V66.13c-50.67,28.45-95.67,66.32-132.25,110.99v403.35l-132.25,28.11V0c-50.67,28.44-95.67,66.32-132.25,110.99v525.69l-295.91,62.88c-20.06,44.47-33.33,92.75-38.42,143.37l334.33-71.05v170.26l-358.3,76.14c-20.06,44.47-33.32,92.75-38.4,143.37l375.04-79.7c30.53-6.35,56.77-24.4,73.83-49.24l68.78-101.97v-.02c7.14-10.55,11.3-23.27,11.3-36.97v-149.98l132.25-28.11v270.4l424.53-90.28Z"/></svg>';
function amt(n){return '<span class="sar-amt" dir="ltr">'+SAR_SVG+'<span>'+fmt(n)+'</span></span>'}
function fmt(n){return Number(n).toLocaleString("en-US")}
function get(v,c){var k=(v||"")+"/"+(c||"");return Object.prototype.hasOwnProperty.call(P,k)?P[k]:null}
function css(){if(document.getElementById("abad-price-css"))return;var s=document.createElement("style");s.id="abad-price-css";
s.textContent='.abad-price{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#10293b,#1b3f58);color:#fff;border:2px solid #F5BD2D;border-radius:999px;padding:5px 14px 5px 6px;font-family:"Cairo",Tahoma,sans-serif;font-weight:700;font-size:14px;line-height:1.4;margin:8px 0;box-shadow:0 2px 8px rgba(16,41,59,.18);max-width:100%;flex-wrap:wrap;justify-content:center}.cert .abad-price-wrap{text-align:center}.cert .abad-price small{display:none}.abad-price .ic .sar-sym{height:13px}.abad-price .ic{background:#F5BD2D;color:#10293b;border-radius:999px;width:24px;height:24px;display:grid;place-items:center;font-size:13px;font-weight:900}.abad-price b{color:#F5BD2D;font-size:16px;font-weight:900}.abad-price small{opacity:.75;font-weight:600;font-size:11px}.sar-amt{display:inline-flex;align-items:center;gap:.22em;direction:ltr;unicode-bidi:isolate;white-space:nowrap}.sar-sym{height:.9em;width:auto;flex:none;fill:currentColor}.abad-price.none{background:none;border:1px dashed #9fb3c3;color:#5B6B79;box-shadow:none;padding:4px 12px;font-size:12.5px}[dir=ltr] .abad-price,.lang-en .abad-price{direction:ltr}';
document.head.appendChild(s)}
function badge(v,c){var p=get(v,c),E=en();if(p==null){if(!CONTACT)return null;var x=document.createElement("span");x.className="abad-price none";x.textContent=E?"Contact us for price":"تواصل معنا للسعر";return x}
 css();var b=document.createElement("span");b.className="abad-price";b.setAttribute("data-abad-price",v+"/"+c);
 b.innerHTML='<span class="ic">'+SAR_SVG+'</span><span>'+(E?'Exam price: ':'سعر الاختبار: ')+'<b>'+amt(p)+'</b></span><small>'+(E?'ABAD price':'سعر معهد آباد')+'</small>';
 b.title=E?"ABAD Institute price (not the vendor's official fee)":"سعر معهد شبكة آباد للتدريب (وليس السعر الرسمي للجهة المانحة)";return b}
function place(el,v,c){if(!el)return;var o=el.querySelector(":scope > .abad-price-wrap");if(o)o.remove();var b=badge(v,c);if(!b)return;var w=document.createElement("div");w.className="abad-price-wrap";w.appendChild(b);el.appendChild(w)}
/* [data-price="vendor/cert"] placeholders */
function slots(){document.querySelectorAll("[data-price]").forEach(function(el){var k=el.getAttribute("data-price").split("/");el.innerHTML="";var b=badge(k[0],k[1]);if(b)el.appendChild(b)})}
/* vendor cards: detect booking link #v=..&c=.. inside .cert */
function cards(){document.querySelectorAll(".cert").forEach(function(card){if(card.querySelector(".abad-price"))return;var a=card.querySelector('a[href*="booking.html"]');if(!a)return;var m=(a.getAttribute("href")||"").match(/[#&?]v=([^&]+)&c=([^&#]+)/);if(!m)return;var b=badge(m[1],m[2]);if(!b)return;var acts=card.querySelector(".cacts");var w=document.createElement("div");w.className="abad-price-wrap";w.appendChild(b);acts?card.insertBefore(w,acts):card.appendChild(w)})}
/* booking form */
function booking(){var v=document.getElementById("vendor"),c=document.getElementById("certSel");if(!v||!c)return;var host=c.closest(".fld");
 var up=function(){place(host,v.value,c.value)};v.addEventListener("change",function(){setTimeout(up,0)});c.addEventListener("change",up);up()}
window.ABAD_PRICE={amt:amt,sar:SAR_SVG,get:get,badge:badge,refresh:function(){slots();cards()}};
function run(){slots();cards();booking()}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",run);else run();
})();
