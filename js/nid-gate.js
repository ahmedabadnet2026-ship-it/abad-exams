/* ABAD — رقم الهوية / الإقامة مطلوب قبل طلب أو تحميل الشهادة.
   Signed-in trainee WITH a stored ID → window.ABAD_NID resolves to it (pages prefill/lock their field).
   Signed-in trainee WITHOUT one (accounts created before the field was required) → a modal asks for it and saves it
   via auth.php {action:"set_national_id"}; the page's certificate action waits on ABAD_NID.need(). Login is never blocked.
   API unreachable (offline copy / file://) → fails open so the static pages still work. */
(function(){
var BASE=(document.currentScript&&document.currentScript.src||location.href).replace(/js\/[^\/]*$/,"");
function EN(){return document.documentElement.lang==="en"}function T(a,b){return EN()?b:a}
function digits(s){return String(s||"").replace(/[٠-٩]/g,function(c){return c.charCodeAt(0)-1632}).replace(/[۰-۹]/g,function(c){return c.charCodeAt(0)-1776}).replace(/\s/g,"")}
/* "ok" | "shape" (not 10 digits starting 1/2) | "check" (Saudi ID checksum fails) */
function check(s){s=digits(s);if(!/^[12]\d{9}$/.test(s))return "shape";var t=0;for(var i=0;i<10;i++){var d=+s[i];if(i%2===0){d*=2;d=Math.floor(d/10)+d%10}t+=d}return t%10===0?"ok":"check"}
function msg(k){return k==="check"?T("رقم الهوية / الإقامة غير صحيح — تحقّق من الأرقام كما في بطاقتك.","National ID / Iqama number is not valid — check the digits on your card.")
 :T("رقم الهوية / الإقامة مطلوب ويجب أن يتكون من 10 أرقام ويبدأ بـ 1 (هوية وطنية) أو 2 (إقامة).","National ID / Iqama is required: exactly 10 digits starting with 1 (national ID) or 2 (Iqama).")}
var S={user:null,csrf:"",api:false};
var ready=fetch(BASE+"api/auth.php?action=me",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}})
 .then(function(r){return r.json()}).then(function(j){if(j&&j.ok&&j.api){S.api=true;S.user=j.user||null;S.csrf=j.csrf||""}return S}).catch(function(){return S});
var CSS='.nidm{position:fixed;inset:0;z-index:9999;background:#0a1c29cc;display:flex;align-items:center;justify-content:center;padding:16px;font-family:Cairo,sans-serif}'+
 '.nidm .bx{background:#fff;color:#10293b;border-radius:18px;max-width:440px;width:100%;padding:22px;box-shadow:0 20px 50px #0008;border-top:6px solid #F5BD2D;text-align:start}'+
 '.nidm h3{margin:0 0 6px;font-size:20px;color:#10293b}.nidm p{margin:0 0 14px;font-size:14px;color:#33536a;line-height:1.8}'+
 '.nidm label{font-weight:700;font-size:14px;display:block;margin-bottom:6px}.nidm input{width:100%;font:inherit;font-size:18px;letter-spacing:2px;padding:10px 12px;border:2px solid #cfdbe5;border-radius:12px;direction:ltr;text-align:left;box-sizing:border-box}'+
 '.nidm input:focus{outline:0;border-color:#3f86b3}.nidm input[aria-invalid=true]{border-color:#c0392b;background:#fff6f5}'+
 '.nidm .er{color:#8e2418;background:#fdecea;border:1px solid #f5c2bc;border-radius:10px;padding:8px 10px;font-size:13px;font-weight:600;margin-top:8px;display:none}.nidm .er.on{display:block}'+
 '.nidm .ac{display:flex;gap:8px;margin-top:16px}.nidm button{font:inherit;font-weight:700;border-radius:11px;padding:10px 16px;border:0;cursor:pointer}'+
 '.nidm .ok{background:#10293b;color:#fff;flex:1}.nidm .ok:disabled{opacity:.6}.nidm .no{background:#eef3f7;color:#10293b}';
function modal(){return new Promise(function(res){
 if(!document.getElementById("nidm-css")){var st=document.createElement("style");st.id="nidm-css";st.textContent=CSS;document.head.appendChild(st)}
 var m=document.createElement("div");m.className="nidm";m.setAttribute("role","dialog");m.setAttribute("aria-modal","true");m.setAttribute("aria-labelledby","nidm-h");m.dir=EN()?"ltr":"rtl";
 m.innerHTML='<form class="bx" novalidate><h3 id="nidm-h">🪪 '+T("أضف رقم الهوية / الإقامة","Add your national ID / Iqama")+'</h3><p>'+
  T("نحتاج رقم هويتك الوطنية أو إقامتك لطباعته على شهادتك. يُحفظ في حسابك مرة واحدة فقط — لتعديله لاحقاً تواصل مع المعهد.","We need your national ID or Iqama number to print it on your certificate. It is saved to your account once — to change it later, contact the institute.")+
  '</p><label for="nidm-i">'+T("رقم الهوية / الإقامة","National ID / Iqama")+' <b style="color:#c0392b">*</b></label><input id="nidm-i" inputmode="numeric" maxlength="10" autocomplete="off" placeholder="1xxxxxxxxx"><div class="er" id="nidm-e" role="alert"></div>'+
  '<div class="ac"><button class="ok" type="submit">'+T("حفظ ومتابعة","Save and continue")+'</button><button class="no" type="button">'+T("لاحقاً","Later")+'</button></div></form>';
 document.body.appendChild(m);var f=m.querySelector("form"),i=m.querySelector("input"),e=m.querySelector(".er"),ok=m.querySelector(".ok");setTimeout(function(){i.focus()},30);
 function err(t){e.textContent=t;e.classList.add("on");i.setAttribute("aria-invalid","true")}
 i.oninput=function(){e.classList.remove("on");i.removeAttribute("aria-invalid")};
 m.querySelector(".no").onclick=function(){m.remove();res(null)};
 f.onsubmit=function(ev){ev.preventDefault();var v=digits(i.value);i.value=v;var k=check(v);if(k!=="ok"){err(msg(k));return}ok.disabled=true;
  fetch(BASE+"api/auth.php",{method:"POST",credentials:"same-origin",cache:"no-store",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-Token":S.csrf},body:JSON.stringify({action:"set_national_id",national_id:v})})
   .then(function(r){return r.json()}).then(function(j){
     if(j&&j.ok){S.user=j.user;m.remove();res(j.user.national_id);return}
     if(j&&j.error==="invalid")err(msg((j.fields||[])[0]==="national_id_check"?"check":"shape"));
     else if(j&&j.error==="national_id_set")err(T("يوجد رقم هوية محفوظ في حسابك مسبقاً — حدّث الصفحة.","An ID is already saved on your account — refresh the page."));
     else err(T("تعذّر الحفظ — أعد المحاولة.","Could not save — please try again."))})
   .catch(function(){err(T("تعذّر الاتصال — تحقّق من الإنترنت.","Connection failed — check your internet."))}).then(function(){ok.disabled=false})}})}
window.ABAD_NID={check:check,digits:digits,msg:msg,ready:ready,
 /* resolves to the stored ID, prompting once if missing; null = user dismissed; "" = no API/session (fail open) */
 need:function(){return ready.then(function(s){if(!s.api||!s.user)return "";if(s.user.national_id)return s.user.national_id;return modal()})}};
})();
