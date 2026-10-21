/* ABAD single login + exam gate (platform / vendor / exam pages).
   - One account button in the top header (platform session cookie wordpress_abadsid via api/auth.php): sign in / name + logout.
   - Logged out: every exam link is locked with a "sign in" prompt (browsing stays open).
   - Logged in: entitled items unlock automatically, the rest stay locked with "book through the institute".
   - Single source: api/my_services.php (abd_entitlements + abd_exam_access + booked bookings + abd_lab_seats), the same data
     my-services.html shows, so the "خدماتي" page and every platform section always agree.
       exams     : a[href*="-exam.html"]          → api/exam.php?id=…
       materials : [data-abad-material="<id>"]    → api/material.php?id=…
       labs      : [data-abad-lab]                → eve-classroom.html (assigned seat)
   Needs the platform API (Cloudways); on hosts without it (GitHub Pages) it does nothing. Enforcement is server-side (api/exam.php). */
(function(){
var MAP={"aplus-core1-exam":["aplus-core1","comptia","aplus"],"aplus-core2-exam":["aplus-core2","comptia","aplus"],"network-plus-exam":["network-plus","comptia","network"],
 "security-plus-exam":["security-plus","comptia","security"],"linux-plus-exam":["linux-plus","comptia","linux"],"pmp-exam":["pmp","pmi","pmp"],"itil5-exam":["itil5","peoplecert","itil5"],"ejpt-trial-exam":["ejpt-trial","ine","ejpt"],"ccna-exam":["ccna","cisco","ccna"],"cobit-exam":["cobit","isaca","cobit"],"cysa-exam":["cysa","comptia","cysa"]};
var EN=function(){try{return localStorage.getItem("abad-lang")==="en"}catch(e){return false}};
var T={lock:["يفتح بعد الحجز من خلال المعهد","Opens after booking through the institute"],book:["احجز من خلال المعهد","Book through the institute"],
 login:["تسجيل الدخول","Sign in"],logout:["خروج","Log out"],open:["متاح لك","Open for you"],needLogin:["سجّل الدخول لفتح اختباراتك","Sign in to unlock your exams"],needLoginS:["سجّل الدخول لفتح خدماتك","Sign in to unlock your services"],mlock:["تُفتح الماتريال بعد التسجيل في الدورة من خلال المعهد","Material opens after enrolling through the institute"],llock:["لم يُعيَّن لك مقعد بعد — احجز مقعدًا من خلال المعهد","No seat assigned yet — book a seat through the institute"],seat:["مقعدك","Your seat"],
 email:["البريد الإلكتروني","Email"],pw:["كلمة المرور","Password"],go:["دخول","Sign in"],reg:["ليس لديك حساب؟ أنشئ حسابًا","No account? Create one"],close:["إغلاق","Close"],
 lt:["تسجيل الدخول إلى منصة آباد","Sign in to the Abad platform"],lp:["حساب واحد للاختبارات والمعمل. تُفتح اختباراتك المحجوزة تلقائيًا.","One account for exams and the lab. Your booked exams unlock automatically."],
 bad:["البريد أو كلمة المرور غير صحيحة.","Wrong email or password."],rate:["محاولات كثيرة، انتظر قليلًا.","Too many attempts, please wait."],net:["تعذّر الاتصال، حاول مرة أخرى.","Connection failed, try again."]};
function t(k){return T[k][EN()?1:0]}
function esc(s){return String(s==null?"":s).replace(/[&<>"']/g,function(c){return "&#"+c.charCodeAt(0)+";"})}
var CSS=".xg-lock{position:relative;opacity:.8}.xg-lock::before{content:'🔒';margin-inline-end:6px}.cd.xg-lock::before{content:'';margin:0}.cd.xg-lock{opacity:.85;cursor:pointer}.cd .xg-note{background:#F5BD2D;border-style:solid;margin-top:auto}.cd .xg-ok{align-self:flex-start;margin:0}"+
".xg-note{display:flex;flex-wrap:wrap;gap:6px;align-items:center;justify-content:center;margin-top:8px;font:700 12px Cairo,system-ui,sans-serif;color:#10293b;background:#F5BD2D1f;border:1px dashed #F5BD2D;border-radius:10px;padding:5px 9px}"+
".xg-note a,.xg-note button{font:inherit;cursor:pointer;border:0;color:#fff;background:#10293b;border-radius:8px;padding:2px 10px;text-decoration:none}.xg-note .l{background:#3f86b3}"+
".xg-ok{display:inline-block;margin-inline-start:6px;font:800 11px Cairo,system-ui,sans-serif;color:#10293b;background:#3ecf8e;border-radius:99px;padding:1px 9px;vertical-align:middle}"+
".xg-acct{display:inline-flex;align-items:center;gap:6px;font-family:Cairo,system-ui,sans-serif}.xg-acct .xg-in{font:800 14px Cairo,system-ui,sans-serif;cursor:pointer;border:0;border-radius:10px;padding:7px 14px;background:#F5BD2D;color:#10293b}"+
".xg-acct .xg-u{text-decoration:none;font:800 13px Cairo,system-ui,sans-serif;color:#10293b;background:#eef4f9;border:1px solid #3f86b355;border-radius:99px;padding:5px 12px;white-space:nowrap;max-width:190px;overflow:hidden;text-overflow:ellipsis}"+
".xg-acct .xg-out{font:700 12px Cairo,system-ui,sans-serif;cursor:pointer;border:1px solid #3f86b3;background:none;color:#3f86b3;border-radius:10px;padding:5px 10px}"+
"#xg-md{position:fixed;inset:0;z-index:400;display:none;place-items:center;background:rgba(16,41,59,.6);padding:16px;font-family:Cairo,system-ui,sans-serif}#xg-md.on{display:grid}"+
"#xg-md .bx{width:min(400px,100%);background:#fff;border-radius:18px;border-top:6px solid #F5BD2D;padding:22px;color:#10293b;text-align:center;position:relative}"+
"#xg-md h3{margin:4px 0 6px;font-size:19px}#xg-md p{margin:0 0 12px;font-size:13px;color:#3f86b3;font-weight:700;line-height:1.8}"+
"#xg-md input{width:100%;box-sizing:border-box;margin:5px 0;padding:10px;border:1px solid #dbe5ee;border-radius:10px;font:inherit;background:#f4f6f9}"+
"#xg-md .go{width:100%;margin-top:8px;font:800 15px Cairo,system-ui,sans-serif;border:0;border-radius:12px;padding:11px;background:#10293b;color:#fff;cursor:pointer}#xg-md .go[disabled]{opacity:.6}"+
"#xg-md .er{min-height:1.4em;color:#c0392b;font-size:13px;font-weight:700;margin-top:6px}#xg-md .rg{display:block;margin-top:8px;font-size:13px;color:#3f86b3}"+
"#xg-md .x{position:absolute;top:4px;inset-inline-end:6px;min-width:44px;min-height:44px;border:0;background:none;font-size:22px;cursor:pointer;color:#10293b}";
var ST=null,ENT=null,PG=(location.pathname.split("/").pop()||"platform.html");
function css(){if(document.getElementById("xg-css"))return;var s=document.createElement("style");s.id="xg-css";s.textContent=CSS;document.head.appendChild(s)}
/* ---------- header account button ---------- */
function header(){var nav=document.querySelector(".hdr .nav")||document.querySelector("header");if(!nav)return;
 var b=document.getElementById("xg-acct");if(!b){b=document.createElement("span");b.id="xg-acct";b.className="xg-acct";nav.appendChild(b)}
 var u=ST&&ST.user;
 if(!u){b.innerHTML='<button type="button" class="xg-in">👤 '+t("login")+"</button>";b.firstChild.onclick=openLogin;return}
 var n=(EN()?u.full_name_en||u.full_name_ar:u.full_name_ar||u.full_name_en)||u.email;
 b.innerHTML='<a class="xg-u" href="my-services.html" title="'+esc(u.email)+' — خدماتي">👤 '+esc(n)+' · خدماتي</a><button type="button" class="xg-out">'+t("logout")+"</button>";
 b.querySelector(".xg-out").onclick=function(){ABAD_API.logout().then(refresh)}}
function openLogin(){css();var m=document.getElementById("xg-md");
 if(!m){m=document.createElement("div");m.id="xg-md";m.setAttribute("role","dialog");m.setAttribute("aria-modal","true");m.setAttribute("aria-labelledby","xg-t");
  m.innerHTML='<form class="bx" novalidate><button type="button" class="x" aria-label="'+t("close")+'">×</button><div style="font-size:30px">🔐</div><h3 id="xg-t">'+t("lt")+"</h3><p>"+t("lp")+
   '</p><input type="email" name="e" autocomplete="username" placeholder="'+t("email")+'" required><input type="password" name="p" autocomplete="current-password" placeholder="'+t("pw")+'" required>'+
   '<button class="go" type="submit">'+t("go")+'</button><div class="er" aria-live="polite"></div><a class="rg" href="login.html?next='+encodeURIComponent(PG)+'">'+t("reg")+"</a></form>";
  document.body.appendChild(m);m.onclick=function(e){if(e.target===m)closeLogin()};m.querySelector(".x").onclick=closeLogin;
  document.addEventListener("keydown",function(e){if(e.key==="Escape")closeLogin()});
  var f=m.querySelector("form");f.onsubmit=function(e){e.preventDefault();var g=f.querySelector(".go"),er=f.querySelector(".er");er.textContent="";g.disabled=true;
   ABAD_API.login(f.e.value.trim(),f.p.value).then(function(j){g.disabled=false;if(j&&j.error==="unverified"){location.href="login.html?next="+encodeURIComponent(PG);return}if(j&&j.ok){f.p.value="";closeLogin();refresh();return}
    er.textContent=t(j&&j.error==="invalid_credentials"?"bad":j&&j.error==="rate_limited"?"rate":"net")})}}
 m.classList.add("on");setTimeout(function(){m.querySelector("input").focus()},30)}
function closeLogin(){var m=document.getElementById("xg-md");if(m)m.classList.remove("on")}
/* ---------- exam links ---------- */
function key(a){var m=(a.getAttribute("data-xg-href")||a.getAttribute("href")||"").match(/([a-z0-9-]+-exam)\.html/i);return m&&MAP[m[1]]?m[1]:null}
function reset(){document.querySelectorAll(".xg-note,.xg-ok").forEach(function(n){n.remove()});
 document.querySelectorAll("[data-abad-material],[data-abad-lab]").forEach(function(a){a.classList.remove("xg-lock");
  if(a.dataset.xgHref!==undefined){if(a.dataset.xgHref)a.setAttribute("href",a.dataset.xgHref);else a.removeAttribute("href");delete a.dataset.xgHref}});
 document.querySelectorAll("[data-xgn]").forEach(function(h){delete h.dataset.xgn});
 document.querySelectorAll("a[data-xg]").forEach(function(a){a.classList.remove("xg-lock");a.removeAttribute("aria-label");delete a.dataset.xg;
  if(a.dataset.xgHref){a.setAttribute("href",a.dataset.xgHref);delete a.dataset.xgHref}})}
function mark(){css();reset();var logged=!!(ST&&ST.user),ex=(ENT&&ENT.exams)||{};
 document.querySelectorAll("a[href*='-exam.html']").forEach(function(a){var k=key(a);if(!k)return;a.dataset.xg=1;var m=MAP[k];
  if(logged&&ex[m[0]]){a.dataset.xgHref=a.getAttribute("href");a.setAttribute("href","api/exam.php?id="+encodeURIComponent(m[0]));
   a.insertAdjacentHTML("beforeend",'<span class="xg-ok">✓ '+t("open")+"</span>");return}
  var msg=logged?t("lock"):t("needLogin");
  a.classList.add("xg-lock");a.setAttribute("aria-label","🔒 "+a.textContent.trim()+" — "+msg);
  var host=a.closest(".cacts")||a;if(host.dataset.xgn)return;host.dataset.xgn=1;
  var n=document.createElement("div");n.className="xg-note";
  n.innerHTML="<span>🔒 "+msg+"</span>"+(logged?"":'<button type="button" class="l">'+t("login")+"</button>")+'<a href="booking.html#v='+m[1]+"&c="+m[2]+'">'+t("book")+"</a>";
  var lb=n.querySelector("button");if(lb)lb.onclick=openLogin;host.insertAdjacentElement("afterend",n)})}
/* capture phase: beats page-level click/transition handlers on exam buttons */
document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a.xg-lock");if(!a)return;var k=key(a);if(!k)return;
 e.preventDefault();e.stopImmediatePropagation();var m=MAP[k];if(!(ST&&ST.user))openLogin();else location.href="booking.html#v="+m[1]+"&c="+m[2]},true);
/* ---------- materials + labs ---------- */
function note(host,msg,logged,bookHref){var n=document.createElement("div");n.className="xg-note";
 n.innerHTML="<span>🔒 "+msg+"</span>"+(logged?"":'<button type="button" class="l">'+t("login")+"</button>")+'<a href="'+bookHref+'">'+t("book")+"</a>";
 var lb=n.querySelector("button");if(lb)lb.onclick=function(e){e.preventDefault();e.stopPropagation();openLogin()};
 n.querySelectorAll("a").forEach(function(x){x.onclick=function(e){e.stopPropagation()}});
 if(/^(A|BUTTON)$/.test(host.tagName)&&host.classList.contains("cd"))host.appendChild(n);else host.insertAdjacentElement("afterend",n)}
function markSvc(){var logged=!!(ST&&ST.user),mt=(ENT&&ENT.materials)||{},lb=(ENT&&ENT.labs)||[];
 document.querySelectorAll("[data-abad-material]").forEach(function(a){var id=a.getAttribute("data-abad-material");a.dataset.xg=1;
  if(a.dataset.xgHref===undefined)a.dataset.xgHref=a.getAttribute("href")||"";
  if(logged&&mt[id]){a.setAttribute("href",mt[id].url);a.setAttribute("target","_blank");a.setAttribute("rel","noopener");
   a.insertAdjacentHTML("beforeend",'<span class="xg-ok">✓ '+t("open")+"</span>");return}
  a.removeAttribute("target");a.classList.add("xg-lock");a.setAttribute("aria-label","🔒 "+a.textContent.trim());note(a,logged?t("mlock"):t("needLoginS"),logged,"booking.html")});
 document.querySelectorAll("[data-abad-lab]").forEach(function(a){a.dataset.xg=1;if(a.dataset.xgHref===undefined)a.dataset.xgHref=a.getAttribute("href")||"";
  if(logged&&(lb.length||(ENT&&ENT.staff))){a.setAttribute("href",lb.length?lb[0].url:"eve-classroom.html");
   a.insertAdjacentHTML("beforeend",'<span class="xg-ok">✓ '+(lb.length?t("seat")+" "+lb[0].seat:t("open"))+"</span>");return}
  if(logged){a.setAttribute("href","eve-classroom.html");note(a,t("llock"),true,"eve-lab-booking.html");return}   /* no seat: the lab page explains + offers booking */
  a.classList.add("xg-lock");a.setAttribute("aria-label","🔒 "+a.textContent.trim());note(a,t("needLoginS"),false,"eve-lab-booking.html")})}
document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest(".xg-lock[data-abad-material],.xg-lock[data-abad-lab]");if(!a||e.target.closest(".xg-note"))return;
 e.preventDefault();e.stopImmediatePropagation();if(!(ST&&ST.user))openLogin();else location.href=a.hasAttribute("data-abad-lab")?"eve-lab-booking.html":"booking.html"},true);
/* ---------- single source: api/my_services.php ---------- */
function toEnt(j){var e={exams:{},materials:{},labs:[],staff:!!(j&&j.staff)},sv=(j&&j.ok&&j.services)||{};
 (sv.exams||[]).forEach(function(x){e.exams[x.id]=true});(sv.materials||[]).forEach(function(x){e.materials[x.id]=x});e.labs=sv.labs||[];e.raw=j;return e}
function refresh(){return ABAD_API.me(true).then(function(st){if(!st)return;ST=st;css();header();
  return (st.user?ABAD_API.req("my_services.php"):Promise.resolve({ok:true,services:{}})).then(function(j){ENT=toEnt(j);mark();markSvc();
   window.AbadGate.services=ENT;try{document.dispatchEvent(new CustomEvent("abad-services",{detail:ENT}))}catch(x){}})}).catch(function(){})}
function go(){if(window.ABAD_API&&ABAD_API.me)refresh()}
window.AbadGate={refresh:refresh,login:openLogin};
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);else go();
})();
