/* ABAD remote-lab gate (ine-lab.html, net-lab.html): signed-in trainee → email code → server returns this lab's embed URLs
   (api/remote_lab.php). No addresses live in the page. ABAD_RLAB.get(lab) → Promise<[{name,url}]> (rejects if the trainee closes). */
(function(){
var BASE=(document.currentScript&&document.currentScript.src||location.href).replace(/js\/[^\/]*$/,"");
var CSRF="",CACHE={},CDT=null;
function api(p,body){var h={Accept:"application/json"};if(body){h["Content-Type"]="application/json";h["X-CSRF-Token"]=CSRF}
 return fetch(BASE+"api/"+p,{method:body?"POST":"GET",credentials:"same-origin",cache:"no-store",headers:h,body:body?JSON.stringify(body):undefined})
  .then(function(r){return r.json().catch(function(){return {ok:false,error:"http"}})}).then(function(j){if(j&&j.csrf)CSRF=j.csrf;return j})}
function me(){return api("auth.php?action=me").then(function(j){if(j&&j.csrf)CSRF=j.csrf;return j})}
var CSS='.rlg{position:fixed;inset:0;z-index:99;display:grid;place-items:center;background:#071622cc;backdrop-filter:blur(4px);padding:16px;font-family:Cairo,sans-serif;direction:rtl}'+
 '.rlg .bx{width:min(420px,100%);border-radius:18px;background:linear-gradient(160deg,#16405e,#0b2236);border:1px solid #F5BD2D88;box-shadow:0 20px 60px #000a,0 0 40px #3f86b355;padding:24px 20px;text-align:center;color:#fff;position:relative}'+
 '.rlg .bx:before{content:"";position:absolute;inset:0 0 auto;height:3px;border-radius:18px 18px 0 0;background:linear-gradient(90deg,#3f86b3,#F5BD2D)}'+
 '.rlg .ic{font-size:40px;line-height:1}.rlg h3{margin:6px 0 6px;font-size:19px}.rlg p{margin:0 0 12px;font-size:13.5px;opacity:.9;line-height:1.8}.rlg .hn{font-size:12px;opacity:.75;margin:-6px 0 10px}'+
 '.rlg input{font:inherit;font-size:28px;font-weight:800;letter-spacing:10px;text-align:center;direction:ltr;width:100%;box-sizing:border-box;padding:8px 10px 8px 20px;border-radius:12px;border:2px solid #F5BD2D;background:#071622;color:#fff;margin-bottom:10px}'+
 '.rlg .er{color:#ffb4b4;font-size:13px;font-weight:700;min-height:1.2em;margin-bottom:6px}'+
 '.rlg .b{font:inherit;font-weight:800;font-size:14px;border:0;border-radius:10px;padding:10px 18px;cursor:pointer;background:#F5BD2D;color:#10293b;text-decoration:none;display:inline-block;margin:3px}'+
 '.rlg .b.l{background:#ffffff1a;color:#fff;border:1px solid #ffffff33}.rlg .b[disabled]{opacity:.5;cursor:default}'+
 '@media(max-width:420px){.rlg .b{width:100%;margin:4px 0}}';
function css(){if(document.getElementById("rlg-css"))return;var s=document.createElement("style");s.id="rlg-css";s.textContent=CSS;document.head.appendChild(s)}
var ERR={wrong_code:"الرمز غير صحيح. المحاولات المتبقية:",expired:"انتهت صلاحية الرمز — أعد الإرسال.",cooldown:"انتظر قليلًا قبل طلب رمز جديد.",rate_limited:"طلبات كثيرة، انتظر قليلًا ثم أعد المحاولة.",
 not_booked:"هذا المعمل يُفتح بالحجز: يصلك رمز الدخول فقط خلال الفترة التي حجزتها (ويمكن طلبه قبل بدايتها بدقائق).",mail_failed:"تعذّر إرسال البريد الآن — حاول لاحقًا.",not_entitled:"هذا المعمل متاح فقط للمتدربين المسجّلين في هذه الخدمة. تواصل معنا لتفعيلها.",unavailable:"المعمل غير متاح حاليًا.",csrf:"انتهت صلاحية الجلسة — حدّث الصفحة.",
 no_email:"لا يوجد بريد صالح في حسابك.",email_is_sender:"بريد حسابك هو بريد المرسِل الخاص بالمنصة؛ حدِّث بريدك.",reverify:"انتهت صلاحية التحقق (30 دقيقة) — اطلب رمزًا جديدًا."};
function etx(j){var e=j&&j.error;return (ERR[e]||"تعذّر الاتصال، حاول مرة أخرى.")+(e==="wrong_code"&&j.left!=null?" "+j.left:"")}
function box(){css();var o=document.createElement("div");o.className="rlg";o.setAttribute("role","dialog");o.setAttribute("aria-modal","true");o.innerHTML='<div class="bx"></div>';document.body.appendChild(o);return o}
function card(o,ic,t,p,body,btns){var b=o.querySelector(".bx");clearInterval(CDT);
 b.innerHTML='<div class="ic">'+ic+'</div><h3></h3><p></p>'+(body||"")+'<div class="er" role="alert"></div><div class="ac"></div>';b.querySelector("h3").textContent=t;b.querySelector("p").textContent=p;
 var ac=b.querySelector(".ac");btns.forEach(function(x){var e=document.createElement(x.href?"a":"button");e.className="b"+(x.l?" l":"");e.textContent=x.t;if(x.k)e.dataset.k=x.k;if(x.href)e.href=x.href;else{e.type="button";e.onclick=x.f}ac.appendChild(e)});return b}
function get(lab){if(CACHE[lab])return Promise.resolve(CACHE[lab]);
 return new Promise(function(res,rej){var o=box();var close=function(){clearInterval(CDT);o.remove();rej(new Error("closed"))};
  card(o,"⏳","جارٍ التحقق…","",null,[]);
  me().then(function(m){
   if(!m||!m.ok||!m.api){card(o,"⚠️","المعمل غير متاح حاليًا","تعذّر الاتصال بالمنصة. حاول لاحقًا أو تواصل مع الدعم.",null,[{t:"إغلاق",f:close,l:1}]);return}
   if(!m.user){var nx=encodeURIComponent(location.pathname.split("/").pop()+location.search);card(o,"🔐","سجّل الدخول أولًا","هذا المعمل متاح للمتدربين المسجّلين. سجّل الدخول بحسابك في منصة آباد ثم عُد إلى هذه الصفحة.",null,[{t:"تسجيل الدخول",href:"login.html?next="+nx},{t:"إغلاق",f:close,l:1}]);return}
   return api("remote_lab.php?action=status&lab="+lab).then(function(s){
    if(!s.ok||!s.available){card(o,"🛠️","المعمل غير متاح حاليًا","نعمل على تجهيز المعمل. حاول مرة أخرى لاحقًا أو تواصل مع الدعم.",null,[{t:"إغلاق",f:close,l:1}]);return}
    if(!s.allowed&&s.access==="booked"){card(o,"📅","احجز موعدك أولًا",ERR.not_booked,null,[{t:"احجز موعدًا",href:"seat-booking.html?lab="+lab},{t:"إغلاق",f:close,l:1}]);return}
    if(!s.allowed){card(o,"🔒","هذا المعمل لمشتركي الخدمة",ERR.not_entitled,null,[{t:"إغلاق",f:close,l:1}]);return}
    var done=function(items){CACHE[lab]=items;clearInterval(CDT);o.remove();res(items)};
    if(s.unlocked)return api("remote_lab.php",{action:"urls",lab:lab}).then(function(j){if(j.ok&&j.items)done(j.items);else lock(s.email)});
    lock(s.email);
    function lock(em){card(o,"🔐","دخول المعمل بأمان","للحماية، نرسل رمز تحقق من 6 أرقام إلى بريدك المسجّل"+(em?" (\u2066"+em+"\u2069)":"")+" قبل فتح أجهزة المعمل.",null,[{t:"إرسال الرمز",f:send},{t:"إغلاق",f:close,l:1}])}
    function send(){var bt=o.querySelector(".ac .b");if(bt)bt.disabled=true;
     api("remote_lab.php",{action:"request",lab:lab}).then(function(j){
      if(!j.ok&&j.error!=="cooldown"){if(bt)bt.disabled=false;o.querySelector(".er").textContent=etx(j);return}
      var b=card(o,"✉️","أدخل رمز التحقق","أرسلنا رمزًا من 6 أرقام إلى "+(j.sent_to?"\u2066"+j.sent_to+"\u2069":"بريدك المسجّل")+".",'<div class="hn">صالح 10 دقائق · 5 محاولات كحد أقصى.</div><input inputmode="numeric" autocomplete="one-time-code" maxlength="6" aria-label="رمز التحقق" dir="ltr">',
       [{t:"تأكيد وفتح المعمل",f:verify},{t:"إعادة الإرسال",f:send,l:1,k:"re"},{t:"إغلاق",f:close,l:1}]);
      var inp=b.querySelector("input");inp.onkeydown=function(e){if(e.key==="Enter")verify()};setTimeout(function(){inp.focus()},40);
      if(!j.ok)b.querySelector(".er").textContent=etx(j);
      var re=b.querySelector('[data-k="re"]'),left=j.ok?(j.cooldown||60):(j.retry_after||60);
      (function p(){if(left>0){re.disabled=true;re.textContent="إعادة الإرسال بعد "+left+" ث";left--}else{clearInterval(CDT);re.disabled=false;re.textContent="إعادة الإرسال"}p.x||(CDT=setInterval(p,1000),p.x=1)})();
      function verify(){var c=inp.value.replace(/[٠-٩]/g,function(d){return d.charCodeAt(0)-1632}).replace(/\D/g,"");var er=b.querySelector(".er");
       if(c.length!==6){er.textContent="أدخل الرمز المكوّن من 6 أرقام.";return}
       api("remote_lab.php",{action:"verify",lab:lab,code:c}).then(function(v){if(v.ok&&v.items)done(v.items);else er.textContent=etx(v)}).catch(function(){er.textContent=etx(null)})}
     }).catch(function(){if(bt)bt.disabled=false;o.querySelector(".er").textContent=etx(null)})}
   })}).catch(function(){card(o,"⚠️","المعمل غير متاح حاليًا","تعذّر الاتصال بالمنصة. حاول لاحقًا.",null,[{t:"إغلاق",f:close,l:1}])})})}
window.ABAD_RLAB={get:get};
})();
