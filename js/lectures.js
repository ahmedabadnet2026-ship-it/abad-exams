/* محاضرات الزوم — trainee view. Data (incl. Zoom join URLs) comes only from api/lectures.php (session-authenticated);
   the server releases a join URL only from 15 min before start until the end, so the page refetches when a window opens. */
(function(){
var BASE=new URL(".",location.href).href,TZ="Asia/Riyadh",LOC="ar-SA-u-ca-gregory-nu-latn",EARLY=15*60000,
 off=0,data=null,cur=null,sig="",lastFetch=0,view="lec",lastFocus=null;
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
var PLAY='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z" fill="currentColor"/></svg>',
 KIND={youtube:"YouTube",drive:"Google Drive",zoom:"Zoom"};
function okEmbed(u){try{var x=new URL(u);return x.protocol==="https:"&&(x.hostname==="www.youtube-nocookie.com"||x.hostname==="drive.google.com")}catch(e){return false}}
function recOk(r){return r&&((r.kind==="zoom"&&r.url&&okUrl(r.url))||(r.kind!=="zoom"&&r.embed&&okEmbed(r.embed)))}
function pcHtml(p){return p?'<div class="pc">رمز التسجيل: <code>'+esc(p)+'</code><button type="button" data-copy="'+esc(p)+'">نسخ</button></div>':""}
function recBtn(c,s,big){var r=s.rec;if(!recOk(r))return "";
 if(r.kind==="zoom")return '<a class="rb z" href="'+esc(r.url)+'" target="_blank" rel="noopener noreferrer">'+PLAY+'مشاهدة التسجيل ↗</a>';
 return '<button type="button" class="rb" data-rec="'+c.id+':'+s.id+'">'+PLAY+'مشاهدة التسجيل</button>'}
function recAct(c,s){if(s.rec_status==="available"&&recOk(s.rec))return recBtn(c,s)+pcHtml(s.rec.passcode);
 if(s.rec_status==="expired")return '<span class="soon x">انتهت إتاحة التسجيل</span>';return '<span class="soon">⏳ التسجيل قريباً</span>'}
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
 var tb=$("#tabs");tb.innerHTML=cs.map(function(x){return '<button role="tab" data-c="'+x.id+'" aria-selected="'+(x.id===cur)+'">'+esc(x.title_ar)+"</button>"}).join("");
 var t=now(),n=nextOf(c),done=c.sessions.filter(function(s){return state(s,t)==="done"}).length,pct=c.total?Math.round(done/c.total*100):0;
 var L={done:"✓ تمّت",live:"● مباشر الآن",soon:"تبدأ قريبًا",up:"قادمة"};
 $("#tl").innerHTML='<div class="tlh"><h3>'+ICON.replace('"zi"','"zi" style="vertical-align:middle;width:24px;height:24px"')+' جدول محاضرات '+esc(c.title_ar)+'</h3><span class="mut">'+c.total+' محاضرة</span></div>'
  +'<div class="prog"><div class="bar"><i style="width:'+pct+'%"></i></div><b>'+done+' / '+c.total+' مكتملة ('+pct+'%)</b></div>'
  +'<ol class="tl">'+c.sessions.map(function(s){var st=state(s,t),cls=st==="soon"?"live next":st==="live"?"live":st==="done"?"done":(n&&n.id===s.id?"next up":"up"),
   lab=n&&n.id===s.id&&st==="up"?"★ التالية":L[st],open=st==="soon"||st==="live",url=s.join_url&&okUrl(s.join_url)?s.join_url:null;
   var act=open&&url?'<a class="mini" href="'+esc(url)+'" target="_blank" rel="noopener noreferrer">دخول المحاضرة</a>':st==="done"?recAct(c,s):"";
   return '<li class="'+cls+'"><div class="dot">'+(st==="done"?"✓":s.no)+'</div><div class="row"><b>المحاضرة '+s.no+'</b><span>'+esc(fW.format(s.start))+'، '+esc(fS.format(s.start))+'</span><span dir="rtl">'+esc(tr(s))+'</span>'
    +(s.title?'<span class="tt">📝 '+esc(s.title)+'</span>':"")+'<span class="st">'+lab+'</span></div><div class="act">'+act+'</div></li>'}).join("")+"</ol>"}
function recList(){var a=all().filter(function(x){return x.s.rec_status==="available"&&recOk(x.s.rec)});return a}
function recs(){var a=recList(),el=$("#recs");$("#rc").textContent=a.length;
 el.innerHTML='<div class="tlh"><h3>▶ تسجيلات المحاضرات</h3><span class="mut">'+a.length+' تسجيل متاح</span></div><p class="mut" style="margin:4px 0 14px">تظهر تسجيلات المحاضرات هنا بعد انتهائها، مرتبة حسب ترتيب المحاضرات.</p>'
  +(a.length?'<div class="rg">'+a.map(function(x){var c=x.c,s=x.s,r=s.rec,z=r.kind==="zoom",
   th=z?'<a class="th" href="'+esc(r.url)+'" target="_blank" rel="noopener noreferrer" aria-label="مشاهدة التسجيل">':'<button type="button" class="th" data-rec="'+c.id+':'+s.id+'" aria-label="مشاهدة التسجيل">';
   return '<div class="rcard">'+th+'<span class="n">المحاضرة '+s.no+'</span><span class="pl" style="color:#10293b">'+PLAY+'</span><span class="k">'+KIND[r.kind]+(z?' ↗':'')+'</span>'+(z?'</a>':'</button>')
    +'<div class="bd"><b>'+esc(s.title||("المحاضرة "+s.no))+'</b><small>'+esc(c.title_ar)+'</small><small>'+esc(fD.format(s.start))+'</small>'
    +(r.until?'<small>متاح حتى '+esc(r.until)+'</small>':"")+pcHtml(r.passcode)+'</div></div>'}).join("")+'</div>'
   :'<div class="empty" style="text-align:center;color:var(--mut);padding:24px">لا توجد تسجيلات متاحة بعد. يُضاف تسجيل كل محاضرة بعد انتهائها.</div>')}
function setView(v){view=v;document.querySelectorAll("[data-v]").forEach(function(b){b.setAttribute("aria-selected",String(b.getAttribute("data-v")===v))});
 $("#tl").hidden=v!=="lec";$("#recs").hidden=v!=="rec";$("#tabs").hidden=v!=="lec"||(data&&data.courses.length<2)}
function openRec(key){var p=key.split(":"),x=all().filter(function(y){return String(y.c.id)===p[0]&&String(y.s.id)===p[1]})[0];if(!x||!recOk(x.s.rec)||x.s.rec.kind==="zoom")return;
 lastFocus=document.activeElement;$("#mdt").textContent=x.c.title_ar+" — المحاضرة "+x.s.no+(x.s.title?": "+x.s.title:"");
 var f=document.createElement("iframe");f.src=x.s.rec.embed;f.title="تسجيل المحاضرة "+x.s.no;f.setAttribute("allow","fullscreen; encrypted-media; picture-in-picture");f.setAttribute("allowfullscreen","");
 f.setAttribute("referrerpolicy","strict-origin-when-cross-origin");$("#vw").innerHTML="";$("#vw").appendChild(f);
 $("#mdf").innerHTML='<span>'+KIND[x.s.rec.kind]+" · "+esc(fD.format(x.s.start))+'</span>'+pcHtml(x.s.rec.passcode);$("#md").hidden=false;document.body.style.overflow="hidden";$("#md .x").focus()}
function closeRec(){if($("#md").hidden)return;$("#md").hidden=true;$("#vw").innerHTML="";document.body.style.overflow="";if(lastFocus)lastFocus.focus()}
function render(){if(!data)return;var t=now(),a=all();sig=a.map(function(x){return state(x.s,t)+(x.s.join_url?1:0)}).join("")+"|"+cur;hero();timeline();recs();setView(view)}
function tick(){if(!data)return;var t=now(),a=all(),ns=a.map(function(x){return state(x.s,t)+(x.s.join_url?1:0)}).join("")+"|"+cur;
 if(ns!==sig){render();return}var h=pick();if(!h)return;var st=state(h.s,t),cd=$("#cd");if(cd)cd.innerHTML=cdHtml(st==="live"?h.s.end-t:h.s.start-t);
 var lp=$("#lp");if(lp)lp.style.width=Math.min(100,(t-h.s.start)/(h.s.end-h.s.start)*100).toFixed(1)+"%"}
function show(id){["gate","err","loading","empty","app"].forEach(function(k){$("#"+k).hidden=k!==id})}
function load(){lastFetch=Date.now();return fetch(BASE+"api/lectures.php",{credentials:"same-origin",headers:{Accept:"application/json"},cache:"no-store"})
 .then(function(r){if(r.status===401)return {logged_in:false};return r.json()}).then(function(j){
  if(!j||j.logged_in===false){show("gate");return}if(!j.ok)throw 0;
  off=(j.now||Date.now())-Date.now();if(j.early_min)EARLY=j.early_min*60000;data=j;
  if(!j.courses.length){show("empty");return}show("app");render()}).catch(function(){if(!data)show("err")})}
document.addEventListener("keydown",function(e){if(e.key==="Escape")closeRec()});
document.addEventListener("click",function(e){if(e.target.closest("[data-close]")||e.target.id==="md"){closeRec();return}
 var rv=e.target.closest("[data-rec]");if(rv){openRec(rv.getAttribute("data-rec"));return}
 var vb=e.target.closest("[data-v]");if(vb){setView(vb.getAttribute("data-v"));return}
 var b=e.target.closest("[data-c]");if(b){cur=+b.getAttribute("data-c");render();return}
 var cp=e.target.closest("[data-copy]");if(cp&&navigator.clipboard){navigator.clipboard.writeText(cp.getAttribute("data-copy"));cp.textContent="تم النسخ ✓"}});
document.addEventListener("DOMContentLoaded",function(){$("#retry").onclick=load;load();setInterval(tick,1000);
 setInterval(function(){if(!document.hidden)load()},5*60000);document.addEventListener("visibilitychange",function(){if(!document.hidden&&Date.now()-lastFetch>60000)load()})});
})();
