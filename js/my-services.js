/* خدماتي — renders the logged-in trainee's open services from api/my_services.php */
(function(){
var BASE=new URL(".",location.href).href,L={exams:"فتح الاختبار",materials:"فتح الماتريال",labs:"دخول اللاب"},
 SUB={exams:function(i){return (i.vendor||"").toUpperCase()},materials:function(){return "ملف محمي — يفتح بعد التحقق من حسابك"},labs:function(i){return "eve"+i.seat+".abad-labs.com"}};
function esc(s){return String(s==null?"":s).replace(/[&<>"']/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]})}
function $(s){return document.querySelector(s)}
function render(j){var lg=j&&j.logged_in;$("#gate").hidden=!!lg;$("#svc").hidden=!lg;if(!lg)return;
 var u=j.user||{};$("#hi").textContent="أهلًا "+(u.full_name_ar||u.full_name_en||u.email||"")+" — هذه الخدمات المفتوحة لحسابك.";
 ["exams","materials","labs"].forEach(function(k){var a=(j.services&&j.services[k])||[],g=$('[data-k="'+k+'"]');
  g.innerHTML=a.map(function(i){return '<div class="it"><b>'+esc(i.title)+'</b><small dir="ltr">'+esc(SUB[k](i))+'</small><a class="go" href="'+esc(i.url)+'"'+(k==="materials"?' target="_blank" rel="noopener"':"")+">"+L[k]+"</a></div>"}).join("");
  g.hidden=!a.length;$('[data-e="'+k+'"]').hidden=!!a.length;$('[data-n="'+k+'"]').textContent=a.length})}
function load(){return fetch(BASE+"api/my_services.php",{credentials:"same-origin",headers:{Accept:"application/json"},cache:"no-store"})
 .then(function(r){return r.json()}).then(render).catch(function(){render({logged_in:false})})}
document.addEventListener("abad-auth",load);
document.addEventListener("DOMContentLoaded",function(){$("#gin").onclick=function(){var b=document.querySelector("#xg-acct .xg-in");if(b)b.click();else location.href="login.html?next=my-services.html"};load()});
})();
