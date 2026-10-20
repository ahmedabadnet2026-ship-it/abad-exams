/* ABAD exam gate (browse pages): marks "start exam" links as open / locked for the current visitor.
   Needs the platform API (Cloudways); on hosts without it (GitHub Pages) it does nothing. Enforcement is server-side (api/exam.php). */
(function(){
var MAP={"aplus-core1-exam":["aplus-core1","comptia","aplus"],"aplus-core2-exam":["aplus-core2","comptia","aplus"],"network-plus-exam":["network-plus","comptia","network"],
 "security-plus-exam":["security-plus","comptia","security"],"linux-plus-exam":["linux-plus","comptia","linux"],"pmp-exam":["pmp","pmi","pmp"],"itil5-exam":["itil5","peoplecert","itil5"],"ejpt-trial-exam":["ejpt-trial","ine","ejpt"]};
var EN=function(){try{return localStorage.getItem("abad-lang")==="en"}catch(e){return false}};
var T={lock:["يفتح بعد الحجز من خلال المعهد","Opens after booking through the institute"],book:["احجز من خلال المعهد","Book through the institute"],login:["تسجيل الدخول","Log in"],open:["متاح لك","Open for you"]};
function t(k){return T[k][EN()?1:0]}
var CSS=".xg-lock{position:relative}.xg-lock::before{content:'🔒';margin-inline-end:6px}"+
".xg-note{display:flex;flex-wrap:wrap;gap:6px;align-items:center;justify-content:center;margin-top:8px;font:700 12px Cairo,system-ui,sans-serif;color:#10293b;background:#F5BD2D1f;border:1px dashed #F5BD2D;border-radius:10px;padding:5px 9px}"+
".xg-note a{color:#fff;background:#10293b;border-radius:8px;padding:2px 10px;text-decoration:none}.xg-note a.l{background:#3f86b3}"+
".xg-ok{display:inline-block;margin-inline-start:6px;font:800 11px Cairo,system-ui,sans-serif;color:#10293b;background:#3ecf8e;border-radius:99px;padding:1px 9px;vertical-align:middle}";
function key(a){var m=(a.getAttribute("href")||"").match(/([a-z0-9-]+-exam)\.html/i);return m&&MAP[m[1]]?m[1]:null}
function run(st){if(!st)return;window.ABAD_API.req("exam_access.php?action=mine").then(function(j){if(!j||!j.ok)return;
 var s=document.createElement("style");s.textContent=CSS;document.head.appendChild(s);
 document.querySelectorAll("a[href*='-exam.html']").forEach(function(a){var k=key(a);if(!k||a.dataset.xg)return;a.dataset.xg=1;var m=MAP[k];
  if(j.exams&&j.exams[m[0]]){a.insertAdjacentHTML("beforeend",'<span class="xg-ok">✓ '+t("open")+"</span>");return}
  a.classList.add("xg-lock");a.setAttribute("aria-label","🔒 "+a.textContent.trim()+" — "+t("lock"));
  var n=document.createElement("div");n.className="xg-note";
  n.innerHTML="<span>🔒 "+t("lock")+'</span><a href="booking.html#v='+m[1]+"&c="+m[2]+'">'+t("book")+"</a>"+(j.logged_in?"":'<a class="l" href="login.html?next='+k+'.html">'+t("login")+"</a>");
  var host=a.closest(".cacts")||a;if(host.dataset.xgn)return;host.dataset.xgn=1;host.insertAdjacentElement("afterend",n)})}).catch(function(){})}
function go(){if(window.ABAD_API&&ABAD_API.me)ABAD_API.me().then(run);}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);else go();
})();
