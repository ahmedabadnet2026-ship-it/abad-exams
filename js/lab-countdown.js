/* ABAD shared lab countdown: 5..1 overlay, then fades out. Include right after <body>. */
(function(){
  if(window.__abadCd)return;window.__abadCd=1;
  var lang=(function(){try{return localStorage.getItem("abad-lang")}catch(e){}})()||(document.documentElement.lang||"ar").slice(0,2);
  var en=lang==="en",N=5,reduce=window.matchMedia&&matchMedia("(prefers-reduced-motion: reduce)").matches;
  var title=(document.title||"").split(/ [—|-] /)[0];if(en&&/[\u0600-\u06FF]/.test(title))title="";
  var o=document.createElement("div");o.id="abad-cd";o.setAttribute("role","dialog");o.setAttribute("aria-modal","true");o.setAttribute("aria-live","assertive");o.dir=en?"ltr":"rtl";o.lang=en?"en":"ar";
  o.innerHTML='<img class="cd-logo" src="assets/abad-logo.png" alt="ABAD"><div class="cd-lab"></div><p class="cd-t">'+(en?"The lab starts in":"يبدأ اللاب خلال")+'</p>'+
   '<div class="cd-ring"><svg viewBox="0 0 100 100"><defs><linearGradient id="abadcdg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3f86b3"/><stop offset="1" stop-color="#F5BD2D"/></linearGradient></defs><circle class="cd-bg" cx="50" cy="50" r="45"/><circle class="cd-fg" cx="50" cy="50" r="45"/></svg><span class="cd-n"></span></div>'+
   '<div class="cd-s">'+(en?"Get ready… ABAD Network Training Institute":"استعد… معهد شبكة آباد للتدريب")+'</div>';
  o.querySelector(".cd-lab").textContent=title;
  (document.body||document.documentElement).appendChild(o);
  var num=o.querySelector(".cd-n"),fg=o.querySelector(".cd-fg"),k=N,prevOverflow=document.documentElement.style.overflow;
  document.documentElement.style.overflow="hidden";
  function show(){num.textContent=en?String(k):String(k).replace(/\d/g,function(d){return"٠١٢٣٤٥٦٧٨٩"[d]});
    if(!reduce){num.classList.remove("pop");void num.offsetWidth;num.classList.add("pop")}
    fg.style.strokeDashoffset=String(283*(N-k+1)/N)}
  function done(){o.classList.add("out");document.documentElement.style.overflow=prevOverflow;window.__labCountdownDone=true;
    try{document.dispatchEvent(new CustomEvent("abad-lab-start"))}catch(e){}
    setTimeout(function(){o.remove()},reduce?0:650)}
  fg.style.strokeDashoffset="0";show();
  var t=setInterval(function(){k--;if(k<1){clearInterval(t);done()}else show()},1000);
})();
