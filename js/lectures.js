/* محاضرات الزوم — trainee view. Data (incl. Zoom join URLs) comes only from api/lectures.php (session-authenticated);
   the server releases a join URL only from 15 min before start until the end, so the page refetches when a window opens. */
(function(){
var BASE=new URL(".",location.href).href,TZ="Asia/Riyadh",LOC="ar-SA-u-ca-gregory-nu-latn",EARLY=15*60000,
 off=0,data=null,cur=null,sig="",lastFetch=0;
function $(s){return document.querySelector(s)}
function esc(s){return String(s==null?"":s).replace(/[&<>"']/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]})}
function now(){return Date.now()+off}
var fD=new Intl.DateTimeFormat(LOC,{timeZone:TZ,weekday:"long",day:"numeric",month:"long",year:"numeric"}),
 fW=new Intl.DateTimeFormat(LOC,{timeZone:TZ,weekday:"long"}),fS=new Intl.DateTimeFormat(LOC,{timeZone:TZ,day:"numeric",month:"long"}),
 fT=new Intl.DateTimeFormat(LOC,{timeZone:TZ,hour:"numeric",minute:"2-digit"});
function tr(s){return fT.format(s.start)+" – "+fT.format(s.end)}
function state(s,t){t=t||now();return t>=s.end?"done":t>=s.start?"live":t>=s.start-EARLY?"soon":"up"}
function okUrl(u){try{var x=new URL(u);return x.protocol==="https:"&&/(^|\.)zoom\.us$/i.test(x.hostname)}catch(e){return false}}
function pad(n){return (n<10?"0":"")+n}
function cdHtml(ms){ms=Math.max(0,ms);var s=Math.floor(ms/1000),d=Math.floor(s/86400),h=Math.floor(s%86400/3600),m=Math.floor(s%3600/60),x=s%60,
 u=[[d,"يوم"],[h,"ساعة"],[m,"دقيقة"],[x,"ثانية"]];if(!d)u.shift();
 return u.map(function(p){return "<div><b>"+pad(p[0])+"</b><small>"+p[1]+"</small></div>"}).join("")}
var ICON='<span class="zi" aria-hidden="true"></span>';
function all(){var a=[];(data.courses||[]).forEach(function(c){c.sessions.forEach(function(s){a.push({c:c,s:s})})});return a.sort(function(x,y){return x.s.start-y.s.start})}
function pick(){var t=now(),a=all(),l=a.filter(function(x){return state(x.s,t)==="live"||state(x.s,t)==="soon"});
 if(l.length)return l[0];for(var i=0;i<a.length;i++)if(a[i].s.end>t)return a[i];return null}
function nextOf(c){var t=now();for(var i=0;i<c.sessions.length;i++)if(c.sessions[i].end>t)return c.sessions[i];return null}
function hero(){var el=$("#next"),h=pick(),t=now();
 if(!h){el.className="nx done";el.innerHTML='<div><div class="lbl"><span class="chip">✓ اكتملت المحاضرات</span></div><h2>انتهت جميع محاضرات دوراتك</h2><div class="meta mut">نتمنى لك التوفيق في اختبارك القادم.</div></div><div class="side"><span class="join">'+ICON+'انتهت</span></div>';return}
 var s=h.s,c=h.c,st=state(s,t),open=st==="soon"||st==="live",url=s.join_url&&okUrl(s.join_url)?s.join_url:null;
 if(open&&s.has_link&&!url&&Date.now()-lastFetch>20000)load();
 el.className="nx "+st;
 var lbl=st==="live"?'<span class="badge-live"><i></i>مباشر الآن</span>':st==="soon"?'<span class="chip c">⏰ تبدأ قريبًا — الدخول متاح</span>':'<span class="chip">المحاضرة القادمة</span>';
 var btn=open&&url?'<a class="join on" href="'+esc(url)+'" target="_blank" rel="noopener noreferrer">'+ICON+'دخول المحاضرة</a>'
  :'<button class="join" disabled>'+ICON+'دخول المحاضرة</button>';
 var hint=st==="up"?"يُتاح الدخول قبل 15 دقيقة من موعد البداية":open&&!s.has_link?"سيُضاف رابط المحاضرة قريبًا":st==="live"?"المحاضرة جارية الآن — انضم مباشرة":"افتح الرابط وانتظر قبول المدرّب";
 var cdl=st==="live"?"تنتهي خلال":"تبدأ خلال",cdv=st==="live"?s.end-t:s.start-t;
 el.innerHTML='<div><div class="lbl">'+lbl+'<span class="chip">'+esc(c.title_ar)+'</span></div>'
  +'<h2>المحاضرة '+s.no+' <small>من '+c.total+'</small>'+(s.title?'<br><small>'+esc(s.title)+'</small>':"")+'</h2>'
  +'<div class="meta"><span>📅 '+esc(fD.format(s.start))+'</span><span>🕓 '+esc(tr(s))+' <small class="mut">(بتوقيت الرياض)</small></span></div></div>'
  +'<div class="side"><div class="hint">'+cdl+'</div><div class="cd" id="cd">'+cdHtml(cdv)+'</div>'
  +(st==="live"?'<div class="lp"><i id="lp" style="width:'+Math.min(100,(t-s.start)/(s.end-s.start)*100).toFixed(1)+'%"></i></div>':"")
  +btn+(open&&url&&s.passcode?'<div class="pc">رمز الدخول: <code>'+esc(s.passcode)+'</code><button type="button" data-copy="'+esc(s.passcode)+'">نسخ</button></div>':"")
  +'<div class="hint">'+hint+'</div></div>';
 if(!cur)cur=c.id}
function timeline(){var cs=data.courses,c=cs.filter(function(x){return x.id===cur})[0]||cs[0];cur=c.id;
 var tb=$("#tabs");tb.hidden=cs.length<2;tb.innerHTML=cs.map(function(x){return '<button role="tab" data-c="'+x.id+'" aria-selected="'+(x.id===cur)+'">'+esc(x.title_ar)+"</button>"}).join("");
 var t=now(),n=nextOf(c),done=c.sessions.filter(function(s){return state(s,t)==="done"}).length,pct=c.total?Math.round(done/c.total*100):0;
 var L={done:"✓ تمّت",live:"● مباشر الآن",soon:"تبدأ قريبًا",up:"قادمة"};
 $("#tl").innerHTML='<div class="tlh"><h3>'+ICON.replace('"zi"','"zi" style="vertical-align:middle;width:24px;height:24px"')+' جدول محاضرات '+esc(c.title_ar)+'</h3><span class="mut">'+c.total+' محاضرة</span></div>'
  +'<div class="prog"><div class="bar"><i style="width:'+pct+'%"></i></div><b>'+done+' / '+c.total+' مكتملة ('+pct+'%)</b></div>'
  +'<ol class="tl">'+c.sessions.map(function(s){var st=state(s,t),cls=st==="soon"?"live next":st==="live"?"live":st==="done"?"done":(n&&n.id===s.id?"next up":"up"),
   lab=n&&n.id===s.id&&st==="up"?"★ التالية":L[st],open=st==="soon"||st==="live",url=s.join_url&&okUrl(s.join_url)?s.join_url:null;
   var act=open&&url?'<a class="mini" href="'+esc(url)+'" target="_blank" rel="noopener noreferrer">دخول المحاضرة</a>':st==="done"?'<span class="st" style="background:#eef1f4;color:#5d7183">انتهت</span>':"";
   return '<li class="'+cls+'"><div class="dot">'+(st==="done"?"✓":s.no)+'</div><div class="row"><b>المحاضرة '+s.no+'</b><span>'+esc(fW.format(s.start))+'، '+esc(fS.format(s.start))+'</span><span dir="rtl">'+esc(tr(s))+'</span>'
    +(s.title?'<span>'+esc(s.title)+'</span>':"")+'<span class="st">'+lab+'</span></div><div class="act">'+act+'</div></li>'}).join("")+"</ol>"}
function render(){if(!data)return;var t=now(),a=all();sig=a.map(function(x){return state(x.s,t)+(x.s.join_url?1:0)}).join("")+"|"+cur;hero();timeline()}
function tick(){if(!data)return;var t=now(),a=all(),ns=a.map(function(x){return state(x.s,t)+(x.s.join_url?1:0)}).join("")+"|"+cur;
 if(ns!==sig){render();return}var h=pick();if(!h)return;var st=state(h.s,t),cd=$("#cd");if(cd)cd.innerHTML=cdHtml(st==="live"?h.s.end-t:h.s.start-t);
 var lp=$("#lp");if(lp)lp.style.width=Math.min(100,(t-h.s.start)/(h.s.end-h.s.start)*100).toFixed(1)+"%"}
function show(id){["gate","err","loading","empty","app"].forEach(function(k){$("#"+k).hidden=k!==id})}
function load(){lastFetch=Date.now();return fetch(BASE+"api/lectures.php",{credentials:"same-origin",headers:{Accept:"application/json"},cache:"no-store"})
 .then(function(r){if(r.status===401)return {logged_in:false};return r.json()}).then(function(j){
  if(!j||j.logged_in===false){show("gate");return}if(!j.ok)throw 0;
  off=(j.now||Date.now())-Date.now();if(j.early_min)EARLY=j.early_min*60000;data=j;
  if(!j.courses.length){show("empty");return}show("app");render()}).catch(function(){if(!data)show("err")})}
document.addEventListener("click",function(e){var b=e.target.closest("[data-c]");if(b){cur=+b.getAttribute("data-c");render();return}
 var cp=e.target.closest("[data-copy]");if(cp&&navigator.clipboard){navigator.clipboard.writeText(cp.getAttribute("data-copy"));cp.textContent="تم النسخ ✓"}});
document.addEventListener("DOMContentLoaded",function(){$("#retry").onclick=load;load();setInterval(tick,1000);
 setInterval(function(){if(!document.hidden)load()},5*60000);document.addEventListener("visibilitychange",function(){if(!document.hidden&&Date.now()-lastFetch>60000)load()})});
})();
