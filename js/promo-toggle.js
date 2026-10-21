/* «اشترك في العروض والخصومات الحصرية» — account toggle for marketing e-mails (Brevo).
   Mounts into every [data-promo-toggle] for a signed-in user. State comes from the user record (auth.php me → user.marketing_opt_in);
   changes POST auth.php {action:"marketing", on} with the session + X-CSRF-Token (via ABAD_API). Server saves first, Brevo best effort. */
(function(){
var CSS='.ptg{font-family:Cairo,sans-serif;display:flex;align-items:flex-start;gap:14px;background:#fff;border:1px solid #dbe6ee;border-inline-start:5px solid #F5BD2D;border-radius:16px;padding:14px 16px;margin:14px 0;color:#10293b;box-shadow:0 4px 14px #10293b0d;text-align:start}'+
 '.ptg .tx{flex:1;min-width:0}.ptg .tt{display:block;font-weight:800;font-size:15.5px;line-height:1.6}.ptg .hn{display:block;font-size:13px;color:#5b7488;line-height:1.8;margin-top:2px}'+
 '.ptg .sw{position:relative;flex:none;width:54px;height:30px;margin-top:2px;cursor:pointer}.ptg .sw input{position:absolute;opacity:0;width:100%;height:100%;margin:0;cursor:pointer;z-index:1}'+
 '.ptg .tr{position:absolute;inset:0;border-radius:999px;background:#c9d5df;transition:background .2s}.ptg .kn{position:absolute;top:3px;inset-inline-start:3px;width:24px;height:24px;border-radius:50%;background:#fff;box-shadow:0 2px 6px #10293b40;transition:transform .2s}'+
 '.ptg input:checked~.tr{background:#3f86b3}.ptg input:checked~.kn{transform:translateX(24px)}[dir=rtl] .ptg input:checked~.kn,.ptg[dir=rtl] input:checked~.kn{transform:translateX(-24px)}'+
 '.ptg input:focus-visible~.tr{outline:3px solid #F5BD2D;outline-offset:2px}.ptg input:disabled~.tr{opacity:.6}'+
 '.ptg .st{display:inline-block;font-size:12px;font-weight:800;border-radius:999px;padding:0 10px;margin-top:6px}.ptg .st.on{background:#e6f6ec;color:#11683c}.ptg .st.off{background:#eef3f7;color:#5b7488}'+
 '.ptg .ms{display:none;font-size:13px;font-weight:600;border-radius:10px;padding:7px 10px;margin-top:8px;line-height:1.7}.ptg .ms.ok{display:block;background:#e8f6ee;color:#11622f}.ptg .ms.wr{display:block;background:#fff8e6;color:#7a5200;border:1px solid #f5d77f}.ptg .ms.er{display:block;background:#fdecea;color:#8e2418}'+
 '@media(max-width:480px){.ptg{padding:12px}.ptg .tt{font-size:14.5px}}';
var TT="اشترك في العروض والخصومات الحصرية",HN="كن أول من يعرف بأحدث العروض والخصومات على دوراتنا، وتصلك على بريدك الإلكتروني. يمكنك إلغاء الاشتراك في أي وقت.";
function css(){if(document.getElementById("ptg-css"))return;var s=document.createElement("style");s.id="ptg-css";s.textContent=CSS;document.head.appendChild(s)}
function mount(box,user){if(box.dataset.ready)return;box.dataset.ready="1";css();var id="ptg-"+Math.random().toString(36).slice(2,7);
 box.innerHTML='<div class="ptg" dir="rtl"><span class="tx"><label class="tt" for="'+id+'">'+TT+'</label><span class="hn" id="'+id+'-h">'+HN+'</span><span class="st"></span><span class="ms" role="status" aria-live="polite"></span></span>'+
  '<span class="sw"><input type="checkbox" role="switch" id="'+id+'" aria-describedby="'+id+'-h"><span class="tr"></span><span class="kn"></span></span></div>';
 var cb=box.querySelector("input"),st=box.querySelector(".st"),ms=box.querySelector(".ms");
 function paint(on){cb.checked=!!on;cb.setAttribute("aria-checked",on?"true":"false");st.className="st "+(on?"on":"off");st.textContent=on?"مفعّل ✓":"غير مفعّل"}
 function say(c,t){ms.className="ms "+c;ms.textContent=t}
 paint(user&&user.marketing_opt_in);
 cb.onchange=function(){var want=cb.checked;cb.disabled=true;say("","");
  ABAD_API.req("auth.php",{method:"POST",body:{action:"marketing",on:want}}).then(function(j){
   if(!j||!j.ok){paint(!want);say("er",j&&j.error==="rate_limited"?"محاولات كثيرة — انتظر قليلًا ثم أعد المحاولة.":j&&j.error==="csrf"?"انتهت صلاحية الجلسة — حدّث الصفحة.":"تعذّر حفظ اختيارك الآن — أعد المحاولة.");return}
   paint(j.marketing_opt_in);if(ABAD_API.state.user)ABAD_API.state.user.marketing_opt_in=j.marketing_opt_in;
   if(j.sync==="unsubscribed")say("wr","سبق أن ألغيت اشتراكك عبر رابط إلغاء الاشتراك في إحدى رسائلنا، لذلك لا نعيد إضافتك تلقائيًا. لإعادة الاشتراك تواصل معنا وسنساعدك.");
   else if(j.sync==="queued")say("wr","تم حفظ اختيارك ✓ — سنحدّث قائمة المراسلات خلال وقت قصير.");
   else say("ok",j.marketing_opt_in?"تم الاشتراك ✓ ستصلك أحدث العروض والخصومات على بريدك.":"تم إلغاء الاشتراك ✓ لن تصلك الرسائل الدعائية بعد الآن.")})
  .catch(function(){paint(!want);say("er","تعذّر الاتصال — تحقّق من الإنترنت وأعد المحاولة.")}).then(function(){cb.disabled=false})}}
function run(){if(!window.ABAD_API)return;ABAD_API.me().then(function(s){var u=s&&s.user;document.querySelectorAll("[data-promo-toggle]").forEach(function(b){if(u)mount(b,u);b.hidden=!u})})}
document.addEventListener("abad-auth",function(e){var u=e.detail&&e.detail.user;document.querySelectorAll("[data-promo-toggle]").forEach(function(b){if(u)mount(b,u);b.hidden=!u})});
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",run);else run();
})();
