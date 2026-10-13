/* ABAD shared Home button: slim bar at top of every page except the landing (index.html). */
(function(){
  if(window.__abadHome)return;window.__abadHome=1;
  function lang(){var l;try{l=localStorage.getItem("abad-lang")}catch(e){}var h=(document.documentElement.lang||"").slice(0,2);return h==="en"||h==="ar"?h:(l||"ar")}
  function mount(){if(document.getElementById("abad-home"))return;var b=document.createElement("nav");b.id="abad-home";b.setAttribute("aria-label","ABAD");
    var a=document.createElement("a");a.href="index.html";var s=document.createElement("span");b.appendChild(a);b.appendChild(s);
    document.body.insertBefore(b,document.body.firstChild);
    function paint(){var en=lang()==="en";b.dir=en?"ltr":"rtl";a.textContent=en?"🏠 Home":"🏠 الرئيسية";a.title=en?"ABAD Digital Platform":"منصة آباد الرقمية";s.textContent=en?"ABAD Digital Platform":"منصة آباد الرقمية"}
    paint();try{new MutationObserver(paint).observe(document.documentElement,{attributes:true,attributeFilter:["lang","dir"]})}catch(e){}
    addEventListener("storage",paint)}
  if(document.body)mount();else document.addEventListener("DOMContentLoaded",mount);
})();
