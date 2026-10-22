/* دوراتي — online course cards for the signed-in trainee. Data only from the session-authenticated api/lectures.php (+ api/results.php). */
(function(){
var BASE=new URL(".",location.href).href,TZ="Asia/Riyadh",LOC="ar-SA-u-ca-gregory-nu-latn",
 GR=["linear-gradient(120deg,#10293b,#1d5479)","linear-gradient(120deg,#2b6d96,#3f86b3)","linear-gradient(120deg,#16405e,#2d7a6b)","linear-gradient(120deg,#3b2f63,#5a4b94)"];
function $(s){return document.querySelector(s)}
function esc(s){return String(s==null?"":s).replace(/[&<>"']/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]})}
var fDay=new Intl.DateTimeFormat(LOC,{timeZone:TZ,day:"numeric",month:"long",year:"numeric"}),fN=new Intl.DateTimeFormat(LOC,{timeZone:TZ,weekday:"long",day:"numeric",month:"long"}),
 fT=new Intl.DateTimeFormat(LOC,{timeZone:TZ,hour:"numeric",minute:"2-digit"});
function ymd(s){if(!s)return null;var p=String(s).split("-");return Date.UTC(+p[0],+p[1]-1,+p[2],9)}
function show(id){["gate","err","loading","app"].forEach(function(k){$("#"+k).hidden=k!==id})}
function card(c,i,now,early){var ss=c.sessions||[],done=ss.filter(function(s){return s.end<=now}).length,recs=ss.filter(function(s){return s.rec_status==="available"}).length,
  tot=c.total||ss.length,pct=tot?Math.round(done/tot*100):0,nx=null;
 for(var k=0;k<ss.length;k++)if(ss[k].end>now){nx=ss[k];break}
 var live=nx&&now>=nx.start-early,first=ss[0],last=ss[ss.length-1],
  from=c.start_date?fDay.format(ymd(c.start_date)):first?fDay.format(first.start):"",to=c.end_date?fDay.format(ymd(c.end_date)):last?fDay.format(last.end):"",
  nxh=nx?(live?'<div class="nx live">🔴 '+(now>=nx.start?"المحاضرة "+nx.no+" مباشرة الآن":"المحاضرة "+nx.no+" تبدأ قريبًا")+' — <b>'+esc(fT.format(nx.start))+'</b></div>'
   :'<div class="nx">⏰ المحاضرة القادمة: <b>'+esc(fN.format(nx.start))+' · '+esc(fT.format(nx.start))+'</b></div>'):'<div class="nx done">✓ اكتملت محاضرات الدورة</div>',
  u="lectures.html?course="+encodeURIComponent(c.id);
 return '<article class="cc"><div class="top" style="background:'+GR[i%GR.length]+'"><span class="lv'+(live?" on":"")+'">'+(live?"● مباشر":"أون لاين")+'</span><h3>'+esc(c.title_ar)+'</h3>'+(c.title_en?'<small dir="auto">'+esc(c.title_en)+'</small>':"")+'</div><div class="bd">'
  +(from?'<div class="row">📅 <span>'+esc(from)+(to?" — "+esc(to):"")+'</span></div>':"")
  +(c.trainer_name?'<div class="row">👤 <span>المدرب: '+esc(c.trainer_name)+'</span></div>':"")
  +'<div><div class="pl"><span>نسبة التقدم</span><b>'+pct+'%</b></div><div class="bar" role="progressbar" aria-valuenow="'+pct+'" aria-valuemin="0" aria-valuemax="100"><i style="width:'+pct+'%"></i></div></div>'
  +'<div class="st"><div><b>'+tot+'</b><small>محاضرة</small></div><div><b>'+recs+'</b><small>تسجيل</small></div><div><b>'+(tot-done)+'</b><small>متبقية</small></div></div>'
  +nxh+'<div class="act"><a class="go" href="'+u+'">دخول الدورة</a><a class="go2" href="'+u+'&amp;view=rec">التسجيلات</a>'+(waOk(c.wa_group_url)?'<a class="wa" href="'+esc(c.wa_group_url)+'" target="_blank" rel="noopener noreferrer">💬 انضم لجروب الدورة</a>':"")+'</div></div></article>'}
function waOk(u){return typeof u==="string"&&/^https:\/\/chat\.whatsapp\.com\/[A-Za-z0-9_-]{10,64}\/?(\?[A-Za-z0-9=&_.-]{0,100})?$/.test(u)}
function load(){show("loading");return fetch(BASE+"api/lectures.php",{credentials:"same-origin",headers:{Accept:"application/json"},cache:"no-store"})
 .then(function(r){if(r.status===401)return {logged_in:false};return r.json()}).then(function(j){
  if(!j||j.logged_in===false){show("gate");return}if(!j.ok)throw 0;
  var now=j.now||Date.now(),early=(j.early_min||15)*60000,cs=j.courses||[];
  $("#n").textContent=cs.length;$("#cg").innerHTML=cs.map(function(c,i){return card(c,i,now,early)}).join("");$("#cg").hidden=!cs.length;$("#empty").hidden=!!cs.length;show("app");
  fetch(BASE+"api/results.php",{credentials:"same-origin",cache:"no-store"}).then(function(r){return r.ok?r.json():null}).then(function(r){if(!r||!r.ok||!r.items||!r.items.length)return;
   $("#rb").innerHTML=r.items.slice(0,30).map(function(x){return '<tr><td dir="ltr">'+esc(String(x.created_at).slice(0,16))+'</td><td dir="ltr">'+esc(x.exam_id)+'</td><td dir="ltr">'+esc(x.score+" / "+x.total)+'</td><td dir="ltr">'+esc(x.percent+"%")+'</td></tr>'}).join("");$("#res").hidden=false}).catch(function(){})
 }).catch(function(){show("err")})}
document.addEventListener("click",function(e){if(e.target.id==="retry")load()});
load();
})();
