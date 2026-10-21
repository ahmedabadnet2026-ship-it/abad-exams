/* Welcome card shown once after a successful sign-up (email code verified). Flag: localStorage "abad-welcome-<uid>". */
(function(){
  function first(u){var n=((u&&(u.full_name_ar||u.full_name_en))||"").trim().split(/\s+/)[0]||"";return n}
  function esc(s){return String(s).replace(/[&<>"']/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]})}
  var CSS=".abw{position:fixed;inset:0;z-index:9999;background:rgba(16,41,59,.62);display:flex;align-items:center;justify-content:center;padding:16px;font-family:Cairo,system-ui,sans-serif;direction:rtl;animation:abwf .25s ease}"
   +".abw-c{background:#fff;width:min(480px,100%);max-height:94vh;overflow:auto;border-radius:20px;box-shadow:0 24px 70px rgba(0,0,0,.35);text-align:center}"
   +".abw-h{background:#10293b;border-bottom:4px solid #F5BD2D;padding:20px 22px 18px;color:#fff}.abw-lg{background:#fff;border-radius:14px;display:inline-block;padding:8px 14px;margin-bottom:10px}.abw-lg img{display:block;height:46px;width:auto}"
   +".abw-h small{display:block;color:#9cc7e3;font-size:13px}.abw-h h2{margin:2px 0 0;font-size:1.35rem;line-height:1.6}.abw-h h2 em{font-style:normal;color:#F5BD2D}"
   +".abw-b{padding:18px 22px 22px}.abw-b p{margin:0 0 16px;color:#33475b;line-height:1.9;font-size:15px}"
   +".abw-q{display:grid;gap:10px}.abw-q a{display:flex;align-items:center;gap:12px;text-align:right;text-decoration:none;color:#10293b;border:1.5px solid #dbe6ee;border-radius:14px;padding:11px 14px;font-weight:800;min-height:48px;transition:.2s}"
   +".abw-q a:hover,.abw-q a:focus{border-color:#3f86b3;background:#eef5fa;outline:none}.abw-q i{font-style:normal;width:36px;height:36px;flex:none;border-radius:10px;background:#eef5fa;display:flex;align-items:center;justify-content:center;font-size:18px}"
   +".abw-q span{display:block;font-weight:400;font-size:12.5px;color:#5d7183}"
   +".abw-x{margin-top:16px;width:100%;font:inherit;font-weight:800;font-size:15px;border:0;border-radius:13px;padding:12px;min-height:48px;cursor:pointer;background:#3f86b3;color:#fff}.abw-x:hover{background:#2f6f99}"
   +"@keyframes abwf{from{opacity:0}to{opacity:1}}";
  function show(u,onClose){
    var key="abad-welcome-"+((u&&u.id)||"x");try{if(localStorage.getItem(key)){onClose&&onClose();return false}localStorage.setItem(key,"1")}catch(e){}
    var st=document.createElement("style");st.textContent=CSS;document.head.appendChild(st);
    var n=first(u),w=document.createElement("div");w.className="abw";w.setAttribute("role","dialog");w.setAttribute("aria-modal","true");w.setAttribute("aria-labelledby","abw-t");
    w.innerHTML='<div class="abw-c"><div class="abw-h"><div class="abw-lg"><img src="assets/abad-logo.png" alt="معهد شبكة آباد للتدريب"></div>'
      +'<small>منصة آباد الرقمية</small><h2 id="abw-t">أهلًا بك'+(n?'، <em>'+esc(n)+'</em>':'')+'</h2></div>'
      +'<div class="abw-b"><p>تم إنشاء حسابك بنجاح في منصة آباد الرقمية التابعة لمعهد شبكة آباد للتدريب. يسعدنا انضمامك، وإليك أبرز ما يمكنك البدء به:</p>'
      +'<div class="abw-q"><a href="platform.html"><i>📝</i><div>الاختبارات التجريبية<span>تدرّب على نماذج اختبارات الشهادات المهنية</span></div></a>'
      +'<a href="practical-lab.html"><i>🖥️</i><div>المعامل العملية<span>طبّق ما تعلمته في بيئات محاكاة عملية</span></div></a>'
      +'<a href="my-services.html"><i>🎓</i><div>خدماتي وشهاداتي<span>تابع خدماتك واطّلع على شهاداتك</span></div></a></div>'
      +'<button type="button" class="abw-x">ابدأ الآن</button></div></div>';
    function close(){w.remove();document.removeEventListener("keydown",k);onClose&&onClose()}
    function k(e){if(e.key==="Escape")close()}
    w.querySelector(".abw-x").onclick=close;w.addEventListener("click",function(e){if(e.target===w)close()});document.addEventListener("keydown",k);
    document.body.appendChild(w);setTimeout(function(){w.querySelector(".abw-x").focus()},30);return true}
  window.ABAD_WELCOME={show:show};
})();
