/* ABAD shared Home bar: slim bar at top of every page except the landing (index.html).
   Also hosts the shared language toggle (localStorage "abad-lang") on pages without their own .langbtn,
   and keeps floating widgets (WhatsApp / install bar) off form fields on mobile. */
(function(){
  if(window.__abadHome)return;window.__abadHome=1;
  function stored(){try{return localStorage.getItem("abad-lang")}catch(e){return null}}
  function lang(){if(window.ABAD_LANG)return window.ABAD_LANG;var h=(document.documentElement.lang||"").slice(0,2);if(document.documentElement.hasAttribute("data-app-lang"))return stored()==="en"?"en":"ar";return h==="en"||h==="ar"?h:(stored()||"ar")}
  function toggle(){if(window.abadToggleLang)return window.abadToggleLang();var n=lang()==="en"?"ar":"en";try{localStorage.setItem("abad-lang",n)}catch(e){}
    try{var u=new URL(location.href);u.searchParams.set("lang",n);location.href=u.toString()}catch(e){location.reload()}}
  function mount(){if(document.getElementById("abad-home"))return;var b=document.createElement("nav");b.id="abad-home";b.setAttribute("aria-label","ABAD");
    var a=document.createElement("a");a.href="index.html";var s=document.createElement("span");b.appendChild(a);b.appendChild(s);
    var t=null;if(!document.querySelector(".langbtn,#lang,[data-own-langbtn]")){t=document.createElement("button");t.type="button";t.className="abad-lt";t.onclick=toggle;b.appendChild(t)}
    document.body.insertBefore(b,document.body.firstChild);
    function paint(){var en=lang()==="en";b.dir=en?"ltr":"rtl";b.lang=en?"en":"ar";a.textContent=en?"🏠 Home":"🏠 الرئيسية";a.title=en?"ABAD Digital Platform":"منصة آباد الرقمية";s.textContent=en?"ABAD Digital Platform":"منصة آباد الرقمية";
      if(t){t.innerHTML=en?'<b>ع</b><i>العربية</i>':'<b>EN</b><i>English</i>';t.setAttribute("aria-label",en?"التبديل إلى العربية":"Switch to English");t.lang=en?"ar":"en"}}
    paint();try{new MutationObserver(paint).observe(document.documentElement,{attributes:true,attributeFilter:["lang","dir"]})}catch(e){}
    addEventListener("storage",paint)}
  /* hide floating widgets while typing in a form field (mobile keyboards) */
  function fld(e){return e&&e.matches&&e.matches("input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]),select,textarea")}
  document.addEventListener("focusin",function(e){if(fld(e.target))document.documentElement.classList.add("abad-typing")});
  document.addEventListener("focusout",function(e){if(fld(e.target))setTimeout(function(){if(!fld(document.activeElement))document.documentElement.classList.remove("abad-typing")},50)});
  if(document.body)mount();else document.addEventListener("DOMContentLoaded",mount);
})();
