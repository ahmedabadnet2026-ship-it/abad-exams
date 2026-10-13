/* ABAD CCNA lab engine — topology builder + mini IOS (static/OSPF/ACL/DHCP/Telnet/SSH).
   Shared by the CCNA labs: a page defines window.LAB={key,title,lead,tasks:function(API){return [...]}} and loads this file.
   Generated from the OSPF lab engine (ccna-ospf-lab.html) + security extension. */
(function(CFG){
var L=(function(){try{return localStorage.getItem("abad-lang")}catch(e){}})()||"ar";
var KEY=CFG.key;
var S={inst:["معهد شبكة آباد للتدريب","ABAD Network Training Institute"],sec:["منصة المحاكاة العملية","Practical Simulation Platform"],back:["→ مختبر CCNA","← CCNA Lab"],
kick:["🌐 CCNA 200-301 — مختبر عملي","🌐 CCNA 200-301 — hands-on lab"],title:[CFG.title,CFG.title],
lead:CFG.lead||["ابنِ الشبكة كما في المخطط وفعّل بروتوكول OSPF: أضف الأجهزة، صِلها بالكابلات الصحيحة، ثم اضغط على أي جهاز لفتح سطر الأوامر وتهيئته. اضغط «تحقق» لتقييم عملك.","Build the network from the diagram and run OSPF: add devices, connect them with the right cables, then click any device to open its CLI and configure it. Press “Check” to grade your work."],
add:["إضافة:","Add:"],router:["راوتر","Router"],switch:["سويتش","Switch"],tools:["الأدوات:","Tools:"],select:["تحريك/فتح","Move/Open"],straight:["كابل مستقيم","Straight-through"],cross:["كابل متقاطع","Crossover"],del:["حذف","Delete"],
topo:["المخطط","Diagram"],reset:["إعادة","Reset"],tasks:["المهام","Tasks"],check:["✔ تحقق من العمل","✔ Check my work"],hint:["تلميح ▾","Hint ▾"],
empty:["ابدأ بإضافة راوترين وسويتشين وجهازي PC من شريط الأدوات ↑","Start by adding 2 routers, 2 switches and 2 PCs from the toolbar ↑"],
ipcfg:["إعدادات IP","IP Configuration"],cmdp:["موجه الأوامر","Command Prompt"],save:["حفظ","Save"],
m_select:["اسحب الأجهزة لتحريكها، واضغط على جهاز لفتح سطر الأوامر","Drag devices to move; tap a device to open its CLI"],
m_straight:["كابل مستقيم: اضغط الجهاز الأول ثم الثاني واختر المنافذ","Straight-through: tap first device, then second, and pick ports"],
m_cross:["كابل متقاطع: اضغط الجهاز الأول ثم الثاني واختر المنافذ","Crossover: tap first device, then second, and pick ports"],
m_delete:["اضغط على جهاز أو كابل لحذفه","Tap a device or cable to delete it"],
pick:["اختر المنفذ — ","Choose port — "],score:["أنجزت %a من %b","Completed %a of %b"],notyet:["اضغط «تحقق» لتقييم المهام","Press “Check” to grade the tasks"],
all:["🎉 أحسنت! أكملت المختبر بنجاح","🎉 Well done! Lab completed"],resetq:["مسح الشبكة وكل الإعدادات؟","Clear the topology and all configuration?"],
leg:["<b>الكابلات:</b><br><i style='background:#9fd3ff'></i>مستقيم: راوتر↔سويتش، سويتش↔PC<br><i style='background:repeating-linear-gradient(90deg,#ffb36b 0 5px,transparent 5px 8px)'></i>متقاطع: راوتر↔راوتر، سويتش↔سويتش، PC↔راوتر<br><b>نقاط المنافذ:</b> <span style='color:#3ecf8e'>●</span> يعمل &nbsp;<span style='color:#ff6b6b'>●</span> متوقف (shutdown أو كابل خاطئ)",
"<b>Cables:</b><br><i style='background:#9fd3ff'></i>Straight: router↔switch, switch↔PC<br><i style='background:repeating-linear-gradient(90deg,#ffb36b 0 5px,transparent 5px 8px)'></i>Crossover: router↔router, switch↔switch, PC↔router<br><b>Port dots:</b> <span style='color:#3ecf8e'>●</span> up &nbsp;<span style='color:#ff6b6b'>●</span> down (shutdown or wrong cable)"]};
function s(k){return S[k][L=="ar"?0:1]}
var PORTS={router:["g0/0","g0/1","g0/2"],switch:["g0/0","g0/1","g0/2","g0/3"],pc:["eth0"]};
function full(p){return p=="eth0"?"eth0":"GigabitEthernet"+p.slice(1)}
function short(p){return p=="eth0"?"eth0":"Gi"+p.slice(1)}
var st,cur=null,mode="select",pend=null;
function fresh(){return{devs:{},links:[],res:null,cnt:{router:0,switch:0,pc:0}}}
function load(){try{st=JSON.parse(localStorage.getItem(KEY))}catch(e){}if(!st||!st.devs)st=fresh()}
function save(){try{localStorage.setItem(KEY,JSON.stringify(st))}catch(e){}}
function newDev(type,x,y){st.cnt[type]++;var pre={router:"R",switch:"SW",pc:"PC"}[type],id=pre+st.cnt[type];while(st.devs[id]){st.cnt[type]++;id=pre+st.cnt[type]}
var d={id:id,type:type,x:x,y:y,log:[],hist:[]};
if(type=="pc"){d.ip="";d.mask="";d.gw="";d.ifs={eth0:{}}}else{d.host=type=="router"?"Router":"Switch";d.mode="user";d.ifs={};PORTS[type].forEach(function(p){d.ifs[p]={ip:"",mask:"",shut:type=="router"}});d.routes=[];d.saved=false}
st.devs[id]=d;return d}
/* ---------- IP helpers ---------- */
function ip2n(a){var m=/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/.exec(a||"");if(!m)return null;for(var i=1;i<5;i++)if(+m[i]>255)return null;return((+m[1]<<24)>>>0)+(+m[2]<<16)+(+m[3]<<8)+(+m[4])}
function n2ip(n){return[n>>>24,(n>>>16)&255,(n>>>8)&255,n&255].join(".")}
function maskOk(m){var n=ip2n(m);if(n===null)return false;var inv=(~n)>>>0;return((inv+1)&inv)===0}
function plen(m){var n=ip2n(m),c=0;while(n&0x80000000){c++;n=(n<<1)>>>0}return c}
function net(ip,m){return(ip2n(ip)&ip2n(m))>>>0}
/* ---------- link / L2 model ---------- */
function linkAt(d,p){for(var i=0;i<st.links.length;i++){var l=st.links[i];if(l.a.d==d&&l.a.p==p)return{l:l,peer:l.b};if(l.b.d==d&&l.b.p==p)return{l:l,peer:l.a}}return null}
function needCable(t1,t2){var k=[t1,t2].sort().join("-");return({"router-switch":"straight","pc-switch":"straight","router-router":"cross","switch-switch":"cross","pc-router":"cross","pc-pc":"cross"})[k]}
function cableOk(l){return st.devs[l.a.d]&&st.devs[l.b.d]&&needCable(st.devs[l.a.d].type,st.devs[l.b.d].type)==l.cable}
function admin(d,p){var D=st.devs[d];return D&&!(D.type=="router"&&D.ifs[p].shut)}
function portUp(d,p){var x=linkAt(d,p);return !!(x&&admin(d,p)&&admin(x.peer.d,x.peer.p)&&cableOk(x.l))}
function segment(d,p){var res=[],seen={},q=[[d,p]];if(!portUp(d,p))return res;
while(q.length){var e=q.shift(),k=e[0]+":"+e[1];if(seen[k])continue;seen[k]=1;var D=st.devs[e[0]];
if(D.type=="switch"){Object.keys(D.ifs).forEach(function(pp){var kk=e[0]+":"+pp;if(!seen[kk]&&portUp(e[0],pp)){seen[kk]=1;var x=linkAt(e[0],pp);q.push([x.peer.d,x.peer.p])}})}
else{res.push(e);if(e[0]!=d||e[1]!=p)continue;var x=linkAt(e[0],e[1]);q.push([x.peer.d,x.peer.p])}}
return res.filter(function(e){return!(e[0]==d&&e[1]==p)})}
function epIp(e){var D=st.devs[e[0]];return D.type=="pc"?D.ip:D.ifs[e[1]].ip}
function epMask(e){var D=st.devs[e[0]];return D.type=="pc"?D.mask:D.ifs[e[1]].mask}
function onSeg(d,p,ip){var sg=segment(d,p);for(var i=0;i<sg.length;i++)if(epIp(sg[i])==ip)return sg[i];return null}
function connected(D){var r=[];if(D.type!="router")return r;Object.keys(D.ifs).forEach(function(p){var f=D.ifs[p];if(f.ip&&portUp(D.id,p))r.push({net:net(f.ip,f.mask),len:plen(f.mask),mask:f.mask,p:p,ip:f.ip})});return r}
function lookup(D,dst){var dn=ip2n(dst),best=null,c=connected(D);
c.forEach(function(r){if(((dn&ip2n(r.mask))>>>0)==r.net&&(!best||r.len>best.len))best={len:r.len,p:r.p,c:1}});
D.routes.forEach(function(r){if(((dn&ip2n(r.mask))>>>0)==ip2n(r.net)&&(!best||r.len>best.len)){var nh=null;c.forEach(function(cc){if(((ip2n(r.nh)&ip2n(cc.mask))>>>0)==cc.net)nh=cc});if(nh&&(!best||plen(r.mask)>best.len))best={len:plen(r.mask),p:nh.p,nh:r.nh}}});return best}
function owns(D,ip){if(D.type=="pc")return D.ip==ip&&portUp(D.id,"eth0");if(D.type!="router")return false;for(var p in D.ifs)if(D.ifs[p].ip==ip&&portUp(D.id,p))return true;return false}
/* one-way delivery: returns device id reached or null */
function deliver(id,dst,ttl){var D=st.devs[id];if(ttl<0)return null;if(owns(D,dst))return id;
if(D.type=="pc"){if(!D.ip||!maskOk(D.mask)||!portUp(id,"eth0"))return null;
 if(net(D.ip,D.mask)==net(dst,D.mask)){var e=onSeg(id,"eth0",dst);return e?e[0]:null}
 if(!D.gw)return null;var g=onSeg(id,"eth0",D.gw);if(!g||st.devs[g[0]].type!="router")return null;return deliver(g[0],dst,ttl-1)}
if(D.type=="router"){var r=lookup(D,dst);if(!r)return null;var tgt=r.nh||dst,e=onSeg(id,r.p,tgt);if(!e)return null;if(!r.nh)return e[0];if(st.devs[e[0]].type!="router")return null;return deliver(e[0],dst,ttl-1)}
return null}
function srcIp(id,dst){var D=st.devs[id];if(D.type=="pc")return D.ip;var r=lookup(D,dst);return r?D.ifs[r.p].ip:null}
function ping(id,dst){var to=deliver(id,dst,16);if(!to)return false;var sip=srcIp(id,dst);if(!sip)return false;return deliver(to,sip,16)==id||(st.devs[id].type=="router"&&owns(st.devs[id],sip)&&!!deliver(to,sip,16))}
/* ---------- rendering ---------- */
var stage=document.getElementById("stage"),svg=document.getElementById("links"),sw=document.getElementById("sw"),scale=1;
var ICON={router:'<svg viewBox="0 0 62 50"><ellipse cx="31" cy="34" rx="27" ry="11" fill="#1d5a80"/><rect x="4" y="18" width="54" height="16" fill="#2a77a8"/><ellipse cx="31" cy="18" rx="27" ry="11" fill="#4ea3d6"/><path d="M19 15h9l-3-3M43 21h-9l3 3M31 9v6l3-3M31 27v-6l-3 3" stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round"/></svg>',
switch:'<svg viewBox="0 0 62 50"><path d="M4 18l10-10h44l-10 10z" fill="#4ea3d6"/><path d="M48 18l10-10v26l-10 10z" fill="#1d5a80"/><rect x="4" y="18" width="44" height="26" fill="#2a77a8"/><path d="M12 26h12l-3-3M40 36H28l3 3M12 36h12l-3 3M40 26H28l3-3" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round"/></svg>',
pc:'<svg viewBox="0 0 62 50"><rect x="9" y="4" width="44" height="30" rx="3" fill="#2a77a8"/><rect x="13" y="8" width="36" height="22" fill="#bfe3f7"/><path d="M26 34h10l2 6H24z" fill="#1d5a80"/><rect x="14" y="40" width="34" height="5" rx="2" fill="#4ea3d6"/></svg>'};
function sub(D){if(D.type=="pc")return D.ip||"";if(D.type=="router")return D.host!=D.id?D.host:"";return""}
function draw(){stage.querySelectorAll(".dev").forEach(function(e){e.remove()});var h="";
st.links.forEach(function(l,i){var A=st.devs[l.a.d],B=st.devs[l.b.d];if(!A||!B)return;var ok=cableOk(l);
 h+='<line x1="'+A.x+'" y1="'+A.y+'" x2="'+B.x+'" y2="'+B.y+'" stroke="'+(l.cable=="cross"?"#ffb36b":"#9fd3ff")+'"'+(l.cable=="cross"?' stroke-dasharray="9 5"':'')+'/>';
 [[l.a,A,B],[l.b,B,A]].forEach(function(z){var t=.34,x=z[1].x+(z[2].x-z[1].x)*t,y=z[1].y+(z[2].y-z[1].y)*t,up=portUp(z[0].d,z[0].p);
  h+='<circle cx="'+x+'" cy="'+y+'" r="5" fill="'+(up?"#3ecf8e":"#ff6b6b")+'"/><text x="'+(x+8)+'" y="'+(y-7)+'">'+short(z[0].p)+'</text>'});
 h+='<line class="hit" data-l="'+i+'" x1="'+A.x+'" y1="'+A.y+'" x2="'+B.x+'" y2="'+B.y+'"/>'});
svg.innerHTML=h;
Object.keys(st.devs).forEach(function(k){var D=st.devs[k],e=document.createElement("div");e.className="dev"+(pend&&pend.d==k?" pick":"");e.dataset.id=k;e.style.left=D.x+"px";e.style.top=D.y+"px";
 e.innerHTML=ICON[D.type]+"<b>"+k+"</b><small>"+sub(D)+"</small>";stage.appendChild(e);drag(e)});
document.getElementById("empty").style.display=Object.keys(st.devs).length?"none":"grid";
document.getElementById("mode").textContent=s("m_"+(mode=="straight"||mode=="cross"?mode:mode));
document.querySelectorAll("[data-m]").forEach(function(b){b.setAttribute("aria-pressed",b.dataset.m==mode)})}
function fit(){var w=sw.clientWidth,hgt=sw.clientHeight;scale=Math.min(1,Math.max(.5,Math.min(w/1000,hgt/600)));stage.style.transform="scale("+scale+")";stage.style.width="1000px"}
function drag(e){var id=e.dataset.id,sx,sy,ox,oy,moved=false,dn=false;
e.addEventListener("pointerdown",function(ev){dn=true;moved=false;sx=ev.clientX;sy=ev.clientY;ox=st.devs[id].x;oy=st.devs[id].y;e.setPointerCapture(ev.pointerId);ev.preventDefault()});
e.addEventListener("pointermove",function(ev){if(!dn||mode!="select")return;var dx=(ev.clientX-sx)/scale,dy=(ev.clientY-sy)/scale;if(Math.abs(dx)+Math.abs(dy)>4)moved=true;if(!moved)return;
 var D=st.devs[id];D.x=Math.max(45,Math.min(955,ox+dx));D.y=Math.max(40,Math.min(560,oy+dy));e.style.left=D.x+"px";e.style.top=D.y+"px";redrawLinks()});
e.addEventListener("pointerup",function(ev){if(!dn)return;dn=false;if(moved){save();draw();return}devClick(id,ev)})}
var rl=0;function redrawLinks(){if(rl)return;rl=requestAnimationFrame(function(){rl=0;var keep=[].slice.call(stage.querySelectorAll(".dev"));draw()})}
function devClick(id,ev){if(mode=="select")return openCli(id);
if(mode=="delete"){st.links=st.links.filter(function(l){return l.a.d!=id&&l.b.d!=id});delete st.devs[id];if(cur==id)closeCli();save();draw();return}
portMenu(id,ev,function(p){if(!pend){pend={d:id,p:p};draw();return}
 if(pend.d==id){pend=null;draw();return}st.links.push({a:pend,b:{d:id,p:p},cable:mode});pend=null;save();draw()})}
function portMenu(id,ev,cb){var m=document.getElementById("menu"),D=st.devs[id];m.innerHTML="<div>"+s("pick")+id+"</div>";
PORTS[D.type].forEach(function(p){var b=document.createElement("button");b.type="button";b.textContent=full(p);if(linkAt(id,p)){b.disabled=true;b.textContent+="  ✓"}b.onclick=function(){m.hidden=true;cb(p)};m.appendChild(b)});
m.hidden=false;var x=Math.min(ev.clientX,innerWidth-190),y=Math.min(ev.clientY,innerHeight-m.offsetHeight-10);m.style.left=x+"px";m.style.top=y+"px"}
document.addEventListener("pointerdown",function(ev){var m=document.getElementById("menu");if(!m.hidden&&!m.contains(ev.target)&&!ev.target.closest(".dev"))m.hidden=true},true);
svg.addEventListener("click",function(ev){var i=ev.target.dataset.l;if(i!=null&&mode=="delete"){st.links.splice(+i,1);save();draw()}});
document.querySelectorAll("[data-add]").forEach(function(b){b.onclick=function(){var t=b.dataset.add,n=Object.keys(st.devs).length,base={router:[180,110],switch:[180,300],pc:[180,480]}[t],c=0;
 for(var k in st.devs)if(st.devs[k].type==t)c++;newDev(t,Math.min(900,base[0]+c*640),base[1]);setMode("select");save();draw()}});
document.querySelectorAll("[data-m]").forEach(function(b){b.onclick=function(){setMode(b.dataset.m)}});
function setMode(m){mode=m;pend=null;document.getElementById("menu").hidden=true;draw()}
/* ---------- CLI ---------- */
var out=document.getElementById("out"),inp=document.getElementById("inp"),pr=document.getElementById("pr"),hi=-1;
function D(){return st.devs[cur]}
function prompt(){var d=D();if(d.type=="pc")return"C:\\>";return d.host+({user:">",priv:"#",config:"(config)#","config-if":"(config-if)#"}[d.mode])}
function print(t,c){var d=REDIR||D();String(t).split("\n").forEach(function(x){d.log.push([x,c||""])});if(d.log.length>500)d.log.splice(0,d.log.length-500);paintLog()}
function paintLog(){var d=D(),f=document.createDocumentFragment();out.innerHTML="";d.log.forEach(function(z){var e=document.createElement("div");if(z[1])e.className=z[1];e.textContent=z[0]||"\u00a0";f.appendChild(e)});out.appendChild(f);out.scrollTop=out.scrollHeight;pr.textContent=prompt()}
function openCli(id){cur=id;var d=D();document.getElementById("cli").classList.add("on");document.getElementById("cliT").textContent=id+(d.type=="pc"?" — PC":d.type=="router"?" — Cisco 2911":" — Cisco Catalyst 2960");
document.getElementById("pctabs").style.display=d.type=="pc"?"flex":"none";
if(!d.log.length){if(d.type=="pc")d.log.push(["Packet Tracer PC Command Line 1.0",""]);else{d.log.push([d.type=="router"?"Cisco IOS Software, C2900 Software (C2900-UNIVERSALK9-M), Version 15.2(4)M3":"Cisco IOS Software, C2960 Software (C2960-LANBASEK9-M), Version 15.0(2)SE4","sy"]);d.log.push(["","" ]);d.log.push(["Press RETURN to get started!",""])}}
if(d.type=="pc"){document.getElementById("pip").value=d.ip;document.getElementById("pmask").value=d.mask;document.getElementById("pgw").value=d.gw;pcTab(d.ip?"cmd":"cfg")}else document.getElementById("pcf").classList.remove("on");
paintLog();setTimeout(function(){inp.focus({preventScroll:true})},30)}
function closeCli(){document.getElementById("cli").classList.remove("on");cur=null;draw()}
document.getElementById("cliX").onclick=closeCli;
function pcTab(t){document.querySelectorAll("[data-pt]").forEach(function(b){b.setAttribute("aria-pressed",b.dataset.pt==t)});document.getElementById("pcf").classList.toggle("on",t=="cfg")}
document.querySelectorAll("[data-pt]").forEach(function(b){b.onclick=function(){pcTab(b.dataset.pt)}});
document.getElementById("psave").onclick=function(){var d=D(),ip=pip.value.trim(),m=pmask.value.trim(),g=pgw.value.trim();
if(ip&&ip2n(ip)===null)return print("Invalid IPv4 address: "+ip,"er");if(m&&!maskOk(m))return print("Invalid subnet mask: "+m,"er");if(!m&&ip){var f=ip2n(ip)>>>24;m=f<128?"255.0.0.0":f<192?"255.255.0.0":"255.255.255.0";pmask.value=m}
if(g&&ip2n(g)===null)return print("Invalid gateway: "+g,"er");d.ip=ip;d.mask=m;d.gw=g;print("IP configuration saved: "+ip+" "+m+"  gw "+(g||"-"),"ok");save();draw()};
var pip=document.getElementById("pip"),pmask=document.getElementById("pmask"),pgw=document.getElementById("pgw");
/* IOS command table: [mode, words, handler, help]; words: string keyword or {a:"name"} arg */
function A(n){return{a:n}}
var C=[
["user",["enable"],function(d){d.mode="priv"}],["user",["exit"],function(d){print("\n"+d.host+" con0 is now available\n\nPress RETURN to get started.")}],
["priv",["disable"],function(d){d.mode="user"}],["priv",["exit"],function(d){d.mode="user"}],
["priv",["configure","terminal"],function(d){print("Enter configuration commands, one per line.  End with CNTL/Z.");d.mode="config"}],
["priv",["copy","running-config","startup-config"],function(d){print("Destination filename [startup-config]? \nBuilding configuration...\n[OK]");d.saved=true}],
["priv",["write","memory"],function(d){print("Building configuration...\n[OK]");d.saved=true}],["priv",["write"],function(d){print("Building configuration...\n[OK]");d.saved=true}],
["user",["ping",A("ip")],pingR],["priv",["ping",A("ip")],pingR],
["user",["show","ip","interface","brief"],showBrief],["priv",["show","ip","interface","brief"],showBrief],
["priv",["show","ip","route"],showRoute,"r"],["user",["show","ip","route"],showRoute,"r"],
["priv",["show","running-config"],showRun],["priv",["show","version"],function(d){print(d.type=="router"?"Cisco IOS Software, C2900 Software (C2900-UNIVERSALK9-M), Version 15.2(4)M3\ncisco CISCO2911/K9 (revision 1.0) with 491520K/32768K bytes of memory.\n3 Gigabit Ethernet interfaces":"Cisco IOS Software, C2960 Software (C2960-LANBASEK9-M), Version 15.0(2)SE4\ncisco WS-C2960-24TT-L (PowerPC405) processor\n4 Gigabit Ethernet interfaces")}],
["config",["hostname",A("name")],function(d,a){d.host=a.name.slice(0,63);draw()}],
["config",["interface",A("if")],enterIf],["config-if",["interface",A("if")],enterIf],
["config",["ip","route",A("net"),A("mask"),A("nh")],ipRoute,"r"],["config",["no","ip","route",A("net"),A("mask"),A("nh")],noRoute,"r"],
["config",["exit"],function(d){d.mode="priv"}],["config-if",["exit"],function(d){d.mode="config";d.cur=null}],
["config",["end"],endF],["config-if",["end"],endF],
["config-if",["ip","address",A("ip"),A("mask")],ipAddr,"r"],["config-if",["no","ip","address"],function(d){var f=d.ifs[d.cur];f.ip="";f.mask="";draw()},"r"],
["config-if",["no","shutdown"],noShut],["config-if",["shutdown"],shut],["config-if",["description",A("text")],function(){}],
["config",["no","hostname"],function(d){d.host=d.type=="router"?"Router":"Switch"}],["config",["enable","secret",A("pw")],function(){}],["config",["banner","motd",A("text")],function(){}],["config",["no","ip","domain-lookup"],function(){}]
];
function endF(d){d.mode="priv";d.cur=null;print("%SYS-5-CONFIG_I: Configured from console by console","sy")}
function normIf(t,n){var x=(t||"").toLowerCase(),m=/^(g|gi|gig|giga|gigabit|gigabitethernet)(\d+\/\d+)?$/.exec(x);if(!m)return null;var num=m[2];var used=1;if(!num){if(/^\d+\/\d+$/.test(n||"")){num=n;used=2}else return null}return{p:"g"+num,used:used}}
function enterIf(d,a){d.mode="config-if";d.cur=a.if}
function ipAddr(d,a){if(ip2n(a.ip)===null||!maskOk(a.mask))return print("% Invalid input detected at '^' marker.","er");var nn=net(a.ip,a.mask);if(ip2n(a.ip)==nn&&plen(a.mask)<31)return print("% "+a.ip+" "+a.mask+" is not a valid host address","er");
for(var p in d.ifs)if(p!=d.cur&&d.ifs[p].ip&&net(d.ifs[p].ip,d.ifs[p].mask)==net(a.ip,d.ifs[p].mask))return print("% "+n2ip(nn)+" overlaps with "+full(p),"er");
d.ifs[d.cur].ip=a.ip;d.ifs[d.cur].mask=a.mask;draw()}
function stateMsg(d,p){var up=portUp(d.id,p),x=linkAt(d.id,p);print("\n%LINK-5-CHANGED: Interface "+full(p)+", changed state to "+(d.ifs[p].shut?"administratively down":up?"up":"down"),"sy");if(up)print("\n%LINEPROTO-5-UPDOWN: Line protocol on Interface "+full(p)+", changed state to up","sy");
if(x){var P=st.devs[x.peer.d];if(P.type!="router"||!P.ifs[x.peer.p].shut){}}}
function noShut(d){if(d.type!="router"){return}var was=portUp(d.id,d.cur);d.ifs[d.cur].shut=false;stateMsg(d,d.cur);draw()}
function shut(d){if(d.type!="router")return;d.ifs[d.cur].shut=true;stateMsg(d,d.cur);draw()}
function ipRoute(d,a){if(ip2n(a.net)===null||!maskOk(a.mask)||ip2n(a.nh)===null)return print("% Invalid input detected at '^' marker.","er");if(net(a.net,a.mask)!=ip2n(a.net))return print("%Inconsistent address and mask","er");
if(!d.routes.some(function(r){return r.net==a.net&&r.mask==a.mask&&r.nh==a.nh}))d.routes.push({net:a.net,mask:a.mask,nh:a.nh})}
function noRoute(d,a){d.routes=d.routes.filter(function(r){return!(r.net==a.net&&r.mask==a.mask&&r.nh==a.nh)})}
function pad(x,n){x=String(x);while(x.length<n)x+=" ";return x}
function showBrief(d){var o=[pad("Interface",27)+pad("IP-Address",16)+pad("OK?",4)+pad("Method",7)+pad("Status",22)+"Protocol"];
PORTS[d.type].forEach(function(p){var f=d.ifs[p],up=portUp(d.id,p),stt=d.type=="router"&&f.shut?"administratively down":up?"up":"down";o.push(pad(full(p),27)+pad(f.ip||"unassigned",16)+pad("YES",4)+pad(f.ip?"manual":"unset",7)+pad(stt,22)+(up?"up":"down"))});
if(d.type=="switch")o.push(pad("Vlan1",27)+pad("unassigned",16)+pad("YES",4)+pad("unset",7)+pad("administratively down",22)+"down");print(o.join("\n"))}
function showRoute(d){var o=["Codes: L - local, C - connected, S - static, R - RIP, M - mobile, B - BGP","       D - EIGRP, EX - EIGRP external, O - OSPF, IA - OSPF inter area","       * - candidate default, U - per-user static route, o - ODR","","Gateway of last resort is not set",""];
var c=connected(d),nets={};c.forEach(function(r){var k=n2ip(r.net).split(".")[0];});
var rows=[];c.forEach(function(r){rows.push([r.net,"C        "+n2ip(r.net)+"/"+r.len+" is directly connected, "+full(r.p)]);rows.push([ip2n(r.ip),"L        "+r.ip+"/32 is directly connected, "+full(r.p)])});
d.routes.forEach(function(r){var ok=c.some(function(cc){return((ip2n(r.nh)&ip2n(cc.mask))>>>0)==cc.net});if(ok)rows.push([ip2n(r.net),"S        "+r.net+"/"+plen(r.mask)+" [1/0] via "+r.nh])});
rows.sort(function(a,b){return a[0]-b[0]});rows.forEach(function(r){o.push("     "+r[1].replace(/^(\w)\s+/,"$1     "))});print(o.join("\n"))}
function showRun(d){var o=["Building configuration...","","Current configuration : 1024 bytes","!","version 15.2","!","hostname "+d.host,"!"];
PORTS[d.type].forEach(function(p){var f=d.ifs[p];o.push("interface "+full(p));if(f.ip)o.push(" ip address "+f.ip+" "+f.mask);else if(d.type=="router")o.push(" no ip address");if(d.type=="router"&&f.shut)o.push(" shutdown");o.push(" duplex auto"," speed auto","!")});
if(d.type=="switch")o.push("interface Vlan1"," no ip address"," shutdown","!");
o.push(d.type=="router"?"ip classless":"!");d.routes.forEach(function(r){o.push("ip route "+r.net+" "+r.mask+" "+r.nh)});o.push("!","line con 0","!","line vty 0 4"," login","!","end");print(o.join("\n"))}
function pingR(d,a){if(ip2n(a.ip)===null)return print("% Unrecognized host or address, or protocol not running.","er");var ok=ping(d.id,a.ip);
print("Type escape sequence to abort.\nSending 5, 100-byte ICMP Echos to "+a.ip+", timeout is 2 seconds:\n"+(ok?"!!!!!\nSuccess rate is 100 percent (5/5), round-trip min/avg/max = 0/0/1 ms":".....\nSuccess rate is 0 percent (0/5)"),ok?"ok":"er");if(ok)d.pinged=(d.pinged||{}),d.pinged[a.ip]=1}
var KW={};C.forEach(function(c){c[1].forEach(function(w){if(typeof w=="string")KW[w]=1})});
function cmdsFor(d,mode){return C.filter(function(c){return c[0]==mode&&!(c[3]=="r"&&d.type!="router")})}
function tryMatch(d,mode,toks){var res=[];cmdsFor(d,mode).forEach(function(c){var a={},ti=0,ok=true,w=c[1];
 for(var i=0;i<w.length;i++){var t=toks[ti];if(t===undefined){ok=false;break}
  if(typeof w[i]=="string"){if(!(w[i].indexOf(t.toLowerCase())==0))return ok=false;ti++}
  else if(w[i].a=="if"){var n=normIf(t,toks[ti+1]);if(!n||PORTS[d.type].indexOf(n.p)<0){ok=false;break}a.if=n.p;ti+=n.used}
  else if(w[i].a=="text"){a.text=toks.slice(ti).join(" ");ti=toks.length}
  else{a[w[i].a]=t;ti++}}
 if(ok&&ti==toks.length)res.push({c:c,a:a,n:w.length})});
/* resolve ambiguity: prefer exact keyword matches */
if(res.length>1){var ex=res.filter(function(r){var j=0;return r.c[1].every(function(w,i){return typeof w!="string"||true})});res.sort(function(x,y){return x.n-y.n})
 var first=toks[0].toLowerCase(),words={};res.forEach(function(r){words[r.c[1][0]]=1});if(Object.keys(words).length>1&&!words[first])return"amb"}
return res[0]||null}
function exec(line){var d=D(),toks=line.trim().split(/\s+/).filter(Boolean);if(!toks.length)return;
if(d.type=="pc")return pcExec(line,toks);
var md=d.mode;if(md.indexOf("config")==0&&toks[0].toLowerCase()=="do"){toks=toks.slice(1);md="priv"}
var m=tryMatch(d,md,toks);if(m=="amb")return print("% Ambiguous command: \""+line.trim()+"\"","er");
if(!m){var any=cmdsFor(d,md).some(function(c){return typeof c[1][0]=="string"&&c[1][0].indexOf(toks[0].toLowerCase())==0});
 if(any&&tryPrefix(d,md,toks))return print("% Incomplete command.","er");return print((md=="user"||md=="priv")&&!any?"Translating \""+toks[0]+"\"...domain server (255.255.255.255)\n% Unknown command or computer name, or unable to find computer address":"% Invalid input detected at '^' marker.","er")}
var keep=d.mode;if(md!=d.mode){var sv=d.mode;d.mode="priv";m.c[2](d,m.a);if(d.mode=="priv")d.mode=sv}else m.c[2](d,m.a)}
function tryPrefix(d,md,toks){return cmdsFor(d,md).some(function(c){if(c[1].length<=toks.length)return false;for(var i=0;i<toks.length;i++){var w=c[1][i];if(typeof w=="string"&&w.indexOf(toks[i].toLowerCase())!=0)return false}return true})}
function help(line){var d=D();if(d.type=="pc")return print("Available: ping <ip>, ipconfig, ipconfig <ip> <mask> <gateway>, cls");var md=d.mode,body=line.replace(/\?$/,""),toks=body.trim().split(/\s+/).filter(Boolean),sp=/\s$/.test(body)||!toks.length;
if(toks[0]&&toks[0].toLowerCase()=="do"&&md.indexOf("config")==0){toks.shift();md="priv"}
var n=sp?toks.length:toks.length-1,set={};cmdsFor(d,md).forEach(function(c){var w=c[1];for(var i=0;i<n;i++){var t=toks[i];if(typeof w[i]=="string"&&w[i].indexOf((t||"").toLowerCase())!=0)return;}var x=w[n];if(x===undefined){set["<cr>"]=1;return}var k=typeof x=="string"?x:"<"+({ip:"A.B.C.D",wc:"A.B.C.D",rid:"A.B.C.D",pid:"<1-65535>",area:"<0-4294967295>",ad:"<1-255>",mask:"A.B.C.D",net:"A.B.C.D",nh:"A.B.C.D",if:"GigabitEthernet0/x",name:"WORD",text:"LINE",pw:"WORD"}[x.a]||"WORD")+">";if(sp||k.indexOf(toks[n]||"")==0)set[k]=1});
print(prompt()+line);print(Object.keys(set).sort().map(function(k){return"  "+k}).join("\n")||"% Unrecognized command","sy")}
function complete(){var d=D();if(d.type=="pc")return;var v=inp.value,toks=v.split(/\s+/),last=toks[toks.length-1];if(!last)return;var n=toks.length-1,md=d.mode,off=0;
if(toks[0].toLowerCase()=="do"&&md.indexOf("config")==0){md="priv";off=1}var c=[];cmdsFor(d,md).forEach(function(cc){var w=cc[1];for(var i=off;i<n;i++)if(typeof w[i-off]=="string"&&w[i-off].indexOf(toks[i].toLowerCase())!=0)return;var x=w[n-off];if(typeof x=="string"&&x.indexOf(last.toLowerCase())==0&&c.indexOf(x)<0)c.push(x)});
if(c.length==1){toks[n]=c[0];inp.value=toks.join(" ")+" "}}
function pcExec(line,t){var d=D(),c=t[0].toLowerCase();print("C:\\>"+line);
if(c=="ipconfig"){if(t.length>=3){if(ip2n(t[1])===null||!maskOk(t[2]))return print("Invalid parameters","er");d.ip=t[1];d.mask=t[2];d.gw=t[3]||"";save();draw();return print("IP configured.","ok")}
 return print("\nFastEthernet0 Connection:(default port)\n\n   Connection-specific DNS Suffix..: \n   IPv4 Address....................: "+(d.ip||"0.0.0.0")+"\n   Subnet Mask.....................: "+(d.mask||"0.0.0.0")+"\n   Default Gateway.................: "+(d.gw||"0.0.0.0")+"\n")}
if(c=="ping"){var ip=t[1];if(ip2n(ip)===null)return print("Ping request could not find host "+(ip||"")+". Please check the name and try again.","er");var ok=ping(d.id,ip);
 var o=["","Pinging "+ip+" with 32 bytes of data:",""];for(var i=0;i<4;i++)o.push(ok?"Reply from "+ip+": bytes=32 time<1ms TTL="+(ttlTo(d.id,ip)):"Request timed out.");
 o.push("","Ping statistics for "+ip+":","    Packets: Sent = 4, Received = "+(ok?4:0)+", Lost = "+(ok?0:4)+" ("+(ok?0:100)+"% loss),");print(o.join("\n"),ok?"ok":"er");if(ok){d.pinged=d.pinged||{};d.pinged[ip]=1}return}
if(c=="cls"||c=="clear"){d.log=[];return}
print("Invalid Command.","er")}
function ttlTo(id,ip){var to=deliver(id,ip,16);if(!to)return 128;var hops=0,D=st.devs[id];/* rough: count routers on path */var cnt=0,x=id;
var t=st.devs[to];return t.type=="router"?255-hopCount(id,ip):128-hopCount(id,ip)}
function hopCount(id,ip){var D=st.devs[id],h=0,cur2=id,g;for(var k=0;k<16;k++){var X=st.devs[cur2];if(owns(X,ip))return Math.max(0,h-1);
 if(X.type=="pc"){if(net(X.ip,X.mask)==net(ip,X.mask))return 0;g=onSeg(cur2,"eth0",X.gw);if(!g)return 0;cur2=g[0];h++;continue}
 var r=lookup(X,ip);if(!r)return h;if(!r.nh)return h;g=onSeg(cur2,r.p,r.nh);if(!g)return h;cur2=g[0];h++}return h}
function submit(){var v=inp.value,d=D();if(/\?$/.test(v)){help(v);inp.value=v.replace(/\?$/,"");return}
if(d.type!="pc")print(prompt()+v);if(v.trim()){d.hist.push(v);if(d.hist.length>50)d.hist.shift()}hi=d.hist.length;inp.value="";exec(v);save();paintLog();live()}
inp.addEventListener("keydown",function(e){if(e.key=="Enter"){e.preventDefault();submit()}else if(e.key=="Tab"){e.preventDefault();complete()}else if(e.key=="ArrowUp"){e.preventDefault();hist(-1)}else if(e.key=="ArrowDown"){e.preventDefault();hist(1)}else if(e.key=="?"){e.preventDefault();help(inp.value+"?")}
else if(e.ctrlKey&&(e.key=="z"||e.key=="c")){e.preventDefault();var d=D();if(d.mode&&d.mode.indexOf("config")==0){print(prompt()+"^Z");endF(d);paintLog()}}});
function hist(k){var d=D();if(!d.hist.length)return;hi=Math.max(0,Math.min(d.hist.length,hi+k));inp.value=d.hist[hi]||""}
document.getElementById("ktab").onclick=function(){complete();inp.focus()};document.getElementById("kq").onclick=function(){help(inp.value+"?");inp.focus()};document.getElementById("kup").onclick=function(){hist(-1);inp.focus()};
out.onclick=function(){inp.focus({preventScroll:true})};
/* ---------- tasks (from the CCNA Static Routing lab file) ---------- */
function byType(t){return Object.keys(st.devs).filter(function(k){return st.devs[k].type==t})}
function L2(a,ap,b,bp){var x=linkAt(a,ap);return!!(x&&x.peer.d==b&&(bp==null||x.peer.p==bp))}
function linkInfo(a,ap,b,bp){var x=linkAt(a,ap);if(!x||x.peer.d!=b||(bp&&x.peer.p!=bp))return"nolink";return cableOk(x.l)?"ok":"cable"}
function R(id){return st.devs[id]&&st.devs[id].type=="router"?st.devs[id]:null}
function ifOk(id,p,ip,m){var d=R(id);if(!d)return 0;var f=d.ifs[p];return f.ip==ip&&f.mask==m&&!f.shut}
function hasRoute(id,n,m,nh){var d=R(id);return!!(d&&d.routes.some(function(r){return r.net==n&&r.mask==m&&r.nh==nh}))}
var why={nolink:["الوصلة غير موجودة أو على منافذ خاطئة","link missing or on wrong ports"],cable:["نوع الكابل غير صحيح","wrong cable type"]};
function W(k){return why[k][L=="ar"?0:1]}
var BASE=[
{ar:"أضف الأجهزة: <code>R1</code>، <code>R2</code>، <code>SW1</code>، <code>SW2</code>، <code>PC1</code>، <code>PC2</code>",en:"Add the devices: <code>R1</code>, <code>R2</code>, <code>SW1</code>, <code>SW2</code>, <code>PC1</code>, <code>PC2</code>",
 h:"Toolbar → Router ×2, Switch ×2, PC ×2",c:function(){var ok=["R1","R2"].every(function(k){return st.devs[k]&&st.devs[k].type=="router"})&&["SW1","SW2"].every(function(k){return st.devs[k]&&st.devs[k].type=="switch"})&&["PC1","PC2"].every(function(k){return st.devs[k]&&st.devs[k].type=="pc"});return ok||["missing devices","أجهزة ناقصة"][L=="ar"?1:0]}},
{ar:"صِل <code>R1 Gi0/1</code> ↔ <code>R2 Gi0/1</code> (راوتر↔راوتر)",en:"Connect <code>R1 Gi0/1</code> ↔ <code>R2 Gi0/1</code> (router↔router)",h:"Crossover cable: R1 → GigabitEthernet0/1, R2 → GigabitEthernet0/1",c:function(){var r=linkInfo("R1","g0/1","R2","g0/1");return r=="ok"||W(r)}},
{ar:"صِل <code>R1 Gi0/0</code> ↔ <code>SW1 Gi0/0</code> و <code>SW1 Gi0/1</code> ↔ <code>PC1 eth0</code>",en:"Connect <code>R1 Gi0/0</code> ↔ <code>SW1 Gi0/0</code> and <code>SW1 Gi0/1</code> ↔ <code>PC1 eth0</code>",h:"Straight-through cables:\nR1 Gi0/0  — SW1 Gi0/0\nSW1 Gi0/1 — PC1 eth0",c:function(){var a=linkInfo("R1","g0/0","SW1","g0/0"),b=linkInfo("SW1","g0/1","PC1","eth0");return a!="ok"?W(a):b!="ok"?W(b):true}},
{ar:"صِل <code>R2 Gi0/0</code> ↔ <code>SW2 Gi0/0</code> و <code>SW2 Gi0/1</code> ↔ <code>PC2 eth0</code>",en:"Connect <code>R2 Gi0/0</code> ↔ <code>SW2 Gi0/0</code> and <code>SW2 Gi0/1</code> ↔ <code>PC2 eth0</code>",h:"Straight-through cables:\nR2 Gi0/0  — SW2 Gi0/0\nSW2 Gi0/1 — PC2 eth0",c:function(){var a=linkInfo("R2","g0/0","SW2","g0/0"),b=linkInfo("SW2","g0/1","PC2","eth0");return a!="ok"?W(a):b!="ok"?W(b):true}},
{ar:"على R1: الاسم <code>R1</code>، <code>Gi0/0 = 192.168.1.1/24</code>، <code>Gi0/1 = 12.0.0.1/30</code> وتشغيل المنافذ",en:"On R1: hostname <code>R1</code>, <code>Gi0/0 = 192.168.1.1/24</code>, <code>Gi0/1 = 12.0.0.1/30</code>, interfaces up",
 h:"enable\nconfig t\nhostname R1\ninterface g0/0\n ip address 192.168.1.1 255.255.255.0\n no shutdown\n exit\ninterface g0/1\n ip address 12.0.0.1 255.255.255.252\n no shutdown\n exit",
 c:function(){var d=R("R1");if(!d)return"R1?";if(d.host!="R1")return"hostname";if(!ifOk("R1","g0/0","192.168.1.1","255.255.255.0"))return"Gi0/0";if(!ifOk("R1","g0/1","12.0.0.1","255.255.255.252"))return"Gi0/1";return true}},
{ar:"على R2: الاسم <code>R2</code>، <code>Gi0/0 = 192.168.2.1/24</code>، <code>Gi0/1 = 12.0.0.2/30</code> وتشغيل المنافذ",en:"On R2: hostname <code>R2</code>, <code>Gi0/0 = 192.168.2.1/24</code>, <code>Gi0/1 = 12.0.0.2/30</code>, interfaces up",
 h:"enable\nconfig t\nhostname R2\ninterface g0/0\n ip address 192.168.2.1 255.255.255.0\n no shutdown\n exit\ninterface g0/1\n ip address 12.0.0.2 255.255.255.252\n no shutdown\n exit",
 c:function(){var d=R("R2");if(!d)return"R2?";if(d.host!="R2")return"hostname";if(!ifOk("R2","g0/0","192.168.2.1","255.255.255.0"))return"Gi0/0";if(!ifOk("R2","g0/1","12.0.0.2","255.255.255.252"))return"Gi0/1";return true}},
{ar:"أضف المسارات الثابتة: على R1 إلى <code>192.168.2.0/24</code> عبر <code>12.0.0.2</code>، وعلى R2 إلى <code>192.168.1.0/24</code> عبر <code>12.0.0.1</code>",en:"Static routes: on R1 to <code>192.168.2.0/24</code> via <code>12.0.0.2</code>; on R2 to <code>192.168.1.0/24</code> via <code>12.0.0.1</code>",
 h:"R1(config)# ip route 192.168.2.0 255.255.255.0 12.0.0.2\nR2(config)# ip route 192.168.1.0 255.255.255.0 12.0.0.1",c:function(){if(!hasRoute("R1","192.168.2.0","255.255.255.0","12.0.0.2"))return"R1";if(!hasRoute("R2","192.168.1.0","255.255.255.0","12.0.0.1"))return"R2";return true}},
{ar:"اضبط PC1: <code>192.168.1.10/24</code> بوابة <code>192.168.1.1</code>، و PC2: <code>192.168.2.20/24</code> بوابة <code>192.168.2.1</code>",en:"Configure PC1: <code>192.168.1.10/24</code> gw <code>192.168.1.1</code>; PC2: <code>192.168.2.20/24</code> gw <code>192.168.2.1</code>",
 h:"PC1 → IP Configuration: 192.168.1.10 / 255.255.255.0 / 192.168.1.1\nPC2 → IP Configuration: 192.168.2.20 / 255.255.255.0 / 192.168.2.1\n(or: ipconfig 192.168.1.10 255.255.255.0 192.168.1.1)",
 c:function(){var a=st.devs.PC1,b=st.devs.PC2;if(!a||a.ip!="192.168.1.10"||a.mask!="255.255.255.0"||a.gw!="192.168.1.1")return"PC1";if(!b||b.ip!="192.168.2.20"||b.mask!="255.255.255.0"||b.gw!="192.168.2.1")return"PC2";return true}},
{ar:"تحقق من الاتصال من طرف لطرف: <code>PC1&gt;ping 192.168.2.20</code> و <code>PC2&gt;ping 192.168.1.10</code> ناجحة",en:"End-to-end connectivity: <code>PC1&gt;ping 192.168.2.20</code> and <code>PC2&gt;ping 192.168.1.10</code> succeed",
 h:"PC1> ping 192.168.2.20\nPC2> ping 192.168.1.10\nR1# show ip interface brief\nR1# show ip route\nR1# show running-config",
 c:function(){if(!st.devs.PC1||!st.devs.PC2)return"PC?";if(!ping("PC1","192.168.2.20"))return L=="ar"?"الـ ping من PC1 إلى PC2 يفشل":"ping PC1 → PC2 fails";if(!ping("PC2","192.168.1.10"))return L=="ar"?"الـ ping من PC2 إلى PC1 يفشل":"ping PC2 → PC1 fails";return true}}
];
function grade(){st.res=T.map(function(t){var r=t.c();return r===true?1:String(r)});save();paint();window.__graded=st.res}
function live(){if(st.res)grade()}
document.getElementById("check").onclick=grade;
function paint(){document.documentElement.lang=L;document.documentElement.dir=L=="ar"?"rtl":"ltr";document.getElementById("lang").textContent=L=="ar"?"English":"العربية";
document.querySelectorAll("[data-t]").forEach(function(e){e.textContent=s(e.dataset.t)});document.title=CFG.title+" — "+s("inst");document.getElementById("legend").innerHTML=s("leg");
var h="",n=0,r=st.res;T.forEach(function(t,i){var v=r?r[i]:null;if(v===1)n++;h+='<div class="tk'+(v===1?" pass":v?" fail":"")+'"><div class="h"><span class="n">'+(v===1?"✓":v?"✗":i+1)+'</span><div>'+t[L]+(v&&v!==1?'<div class="why">✗ '+v.replace(/</g,"&lt;")+'</div>':'')+'<div><button class="hb2" type="button" data-h="'+i+'">'+s("hint")+'</button></div><div class="hint" id="h'+i+'"></div></div></div></div>'});
document.getElementById("list").innerHTML=h;T.forEach(function(t,i){document.getElementById("h"+i).textContent=t.h});
document.getElementById("score").textContent=r?(n==T.length?s("all"):s("score").replace("%a",n).replace("%b",T.length)):s("notyet");draw()}
document.getElementById("list").onclick=function(e){var b=e.target.closest("[data-h]");if(b)document.getElementById("h"+b.dataset.h).classList.toggle("on")};
document.getElementById("lang").onclick=function(){L=L=="ar"?"en":"ar";try{localStorage.setItem("abad-lang",L)}catch(e){}paint()};
document.getElementById("reset").onclick=function(){if(!confirm(s("resetq")))return;st=fresh();closeCli();save();paint()};
document.getElementById("topo").onclick=function(){document.getElementById("modal").classList.add("on")};
document.getElementById("mx").onclick=document.getElementById("modal").onclick=function(e){if(e.target.tagName!="IMG")document.getElementById("modal").classList.remove("on")};
/* ===== OSPF extension (overrides Static Lab engine functions) ===== */
function wcOk(w){var n=ip2n(w);if(n===null)return false;var m=(~n)>>>0;return((((~m)>>>0)+1)&((~m)>>>0))===0}
function wc2mask(w){return n2ip((~ip2n(w))>>>0)}
function ospfIf(D,p){var o=D.ospf,f=D.ifs[p];if(!o||!f||!f.ip||!portUp(D.id,p))return null;var ipn=ip2n(f.ip);
 for(var i=0;i<o.nets.length;i++){var n=o.nets[i],wm=(~ip2n(n.wc))>>>0;if(((ipn&wm)>>>0)==((ip2n(n.ip)&wm)>>>0))return n.area}return null}
function rid(D){if(!D.ospf)return null;if(D.ospf.rid)return D.ospf.rid;var b=null;for(var p in D.ifs){var f=D.ifs[p];if(f.ip&&portUp(D.id,p)&&(!b||ip2n(f.ip)>ip2n(b)))b=f.ip}return b}
function nbrs(D){var r=[];if(!D||D.type!="router"||!D.ospf)return r;var me=rid(D);if(!me)return r;
 Object.keys(D.ifs).forEach(function(p){var a=ospfIf(D,p);if(a===null||D.ospf.passive.indexOf(p)>=0)return;var f=D.ifs[p];
  segment(D.id,p).forEach(function(e){var Q=st.devs[e[0]];if(Q.type!="router"||!Q.ospf)return;var qa=ospfIf(Q,e[1]);if(qa===null||qa!=a||Q.ospf.passive.indexOf(e[1])>=0)return;
   var qf=Q.ifs[e[1]];if(net(qf.ip,qf.mask)!=net(f.ip,f.mask)||qf.mask!=f.mask)return;var qr=rid(Q);if(!qr||qr==me)return;r.push({id:Q.id,rid:qr,ip:qf.ip,p:p,area:a,dr:ip2n(qr)>ip2n(me)})})});return r}
function ospfRoutes(D){var out=[];if(!D.ospf||!rid(D))return out;var dist={},hop={},q=[D.id];dist[D.id]=0;
 while(q.length){q.sort(function(a,b){return dist[a]-dist[b]});var u=q.shift();nbrs(st.devs[u]).forEach(function(n){var nd=dist[u]+1;if(dist[n.id]===undefined||nd<dist[n.id]){
   /* adjacency must be bidirectional */ if(!nbrs(st.devs[n.id]).some(function(b){return b.id==u}))return;dist[n.id]=nd;hop[n.id]=u==D.id?{p:n.p,nh:n.ip}:hop[u];q.push(n.id)}})}
 var mine={};connected(D).forEach(function(c){mine[c.net+"/"+c.len]=1});var best={};
 Object.keys(dist).forEach(function(id){if(id==D.id)return;var X=st.devs[id];Object.keys(X.ifs).forEach(function(p){if(ospfIf(X,p)===null)return;var f=X.ifs[p],k=net(f.ip,f.mask)+"/"+plen(f.mask);if(mine[k])return;
  var m=dist[id]+1;if(!best[k]||m<best[k].metric)best[k]={net:net(f.ip,f.mask),len:plen(f.mask),mask:f.mask,p:hop[id].p,nh:hop[id].nh,ad:110,metric:m,code:"O"}})});
 for(var k in best)out.push(best[k]);return out}
function table(D){var c=connected(D),rows=[];c.forEach(function(r){rows.push({net:r.net,len:r.len,mask:r.mask,p:r.p,ad:0,metric:0,code:"C"})});
 (D.routes||[]).forEach(function(r){var nh=null;c.forEach(function(cc){if(((ip2n(r.nh)&ip2n(cc.mask))>>>0)==cc.net)nh=cc});if(nh)rows.push({net:ip2n(r.net),len:plen(r.mask),mask:r.mask,p:nh.p,nh:r.nh,ad:r.ad||1,metric:0,code:"S"})});
 ospfRoutes(D).forEach(function(r){rows.push(r)});var sel={};rows.forEach(function(r){var k=r.net+"/"+r.len;if(!sel[k]||r.ad<sel[k].ad)sel[k]=r});return Object.keys(sel).map(function(k){return sel[k]})}
function lookup(D,dst){var dn=ip2n(dst),best=null;table(D).forEach(function(r){if(((dn&ip2n(r.mask))>>>0)==r.net&&(!best||r.len>best.len))best=r});return best?{len:best.len,p:best.p,nh:best.nh}:null}
function showRoute(d){var o=["Codes: L - local, C - connected, S - static, R - RIP, M - mobile, B - BGP","       D - EIGRP, EX - EIGRP external, O - OSPF, IA - OSPF inter area","       N1 - OSPF NSSA external type 1, N2 - OSPF NSSA external type 2","       E1 - OSPF external type 1, E2 - OSPF external type 2","       * - candidate default, U - per-user static route, o - ODR","","Gateway of last resort is not set",""];
 var rows=[];table(d).forEach(function(r){var pre=n2ip(r.net)+"/"+r.len;if(r.code=="C"){rows.push([r.net,1,"C        "+pre+" is directly connected, "+full(r.p)]);var ip=d.ifs[r.p].ip;rows.push([ip2n(ip),2,"L        "+ip+"/32 is directly connected, "+full(r.p)])}
  else if(r.code=="S")rows.push([r.net,1,"S        "+pre+" ["+r.ad+"/0] via "+r.nh]);else rows.push([r.net,1,"O        "+pre+" [110/"+r.metric+"] via "+r.nh+", 00:0"+(1+(r.net%8))+":"+(10+(r.net%49))+", "+full(r.p)])});
 rows.sort(function(a,b){return a[0]-b[0]||a[1]-b[1]});rows.forEach(function(r){o.push("     "+r[2].replace(/^(\w)\s+/,"$1     "))});print(o.join("\n"))}
function showNbr(d){if(!d.ospf)return print("");var n=nbrs(d);var o=["",pad("Neighbor ID",17)+pad("Pri",6)+pad("State",16)+pad("Dead Time",12)+pad("Address",16)+"Interface"];
 n.forEach(function(x){if(!nbrs(st.devs[x.id]).some(function(b){return b.id==d.id}))return;o.push(pad(x.rid,17)+pad("1",6)+pad("FULL/"+(x.dr?"DR":"BDR"),16)+pad("00:00:3"+(x.rid.length%10),12)+pad(x.ip,16)+full(x.p))});print(o.join("\n"))}
function showProto(d){if(!d.ospf)return print("");var o=d.ospf,n=nbrs(d);var L2=['Routing Protocol is "ospf '+o.pid+'"',"  Outgoing update filter list for all interfaces is not set","  Incoming update filter list for all interfaces is not set","  Router ID "+(rid(d)||"0.0.0.0"),"  Number of areas in this router is 1. 1 normal 0 stub 0 nssa","  Maximum path: 4","  Routing for Networks:"];
 o.nets.forEach(function(x){L2.push("    "+x.ip+" "+x.wc+" area "+x.area)});if(o.passive.length){L2.push("  Passive Interface(s):");o.passive.forEach(function(p){L2.push("    "+full(p))})}
 L2.push("  Routing Information Sources:","    Gateway         Distance      Last Update");n.forEach(function(x){L2.push("    "+pad(x.rid,16)+pad("110",14)+"00:00:42")});L2.push("  Distance: (default is 110)");print(L2.join("\n"))}
function showOspfIfB(d){if(!d.ospf)return print("");var o=[pad("Interface",10)+pad("PID",6)+pad("Area",9)+pad("IP Address/Mask",20)+pad("Cost",6)+pad("State",6)+"Nbrs F/C"],n=nbrs(d);
 Object.keys(d.ifs).forEach(function(p){var a=ospfIf(d,p);if(a===null)return;var f=d.ifs[p],c=n.filter(function(x){return x.p==p}).length;o.push(pad(short(p),10)+pad(d.ospf.pid,6)+pad(a,9)+pad(f.ip+"/"+plen(f.mask),20)+pad("1",6)+pad(c?"DR":"DR",6)+c+"/"+c)});print(o.join("\n"))}
function routerOspf(d,a){if(!/^\d+$/.test(a.pid)||+a.pid<1||+a.pid>65535)return print("% Invalid input detected at '^' marker.","er");if(!d.ospf||d.ospf.pid!=a.pid){if(d.ospf&&d.ospf.pid!=a.pid)print("% Only one OSPF process supported in this lab; replacing process "+d.ospf.pid,"er");d.ospf={pid:a.pid,rid:"",nets:[],passive:[]}}d.mode="config-router"}
function noRouterOspf(d,a){if(d.ospf&&d.ospf.pid==a.pid)d.ospf=null}
function setRid(d,a){if(ip2n(a.rid)===null)return print("% Invalid input detected at '^' marker.","er");var had=nbrs(d).length;d.ospf.rid=a.rid;if(had)print("% OSPF: Reload or use \"clear ip ospf process\" command, for this to take effect","sy")}
function netStmt(d,a){if(ip2n(a.ip)===null||!wcOk(a.wc)||!/^\d+$/.test(a.area))return print("% Invalid input detected at '^' marker.","er");if(!d.ospf.nets.some(function(n){return n.ip==a.ip&&n.wc==a.wc&&n.area==a.area}))d.ospf.nets.push({ip:a.ip,wc:a.wc,area:a.area})}
function noNet(d,a){d.ospf.nets=d.ospf.nets.filter(function(n){return!(n.ip==a.ip&&n.wc==a.wc&&n.area==a.area)})}
function passive(d,a){if(d.ospf.passive.indexOf(a.if)<0)d.ospf.passive.push(a.if)}function noPassive(d,a){d.ospf.passive=d.ospf.passive.filter(function(p){return p!=a.if})}
function ipRouteAd(d,a){var ad=+a.ad;if(!(ad>=1&&ad<=255))return print("% Invalid input detected at '^' marker.","er");var n=d.routes.length;ipRoute(d,a);var r=d.routes.filter(function(r){return r.net==a.net&&r.mask==a.mask&&r.nh==a.nh})[0];if(r)r.ad=ad}
function ipRoute(d,a){if(ip2n(a.net)===null||!maskOk(a.mask)||ip2n(a.nh)===null)return print("% Invalid input detected at '^' marker.","er");if(net(a.net,a.mask)!=ip2n(a.net))return print("%Inconsistent address and mask","er");
 var ex=d.routes.filter(function(r){return r.net==a.net&&r.mask==a.mask&&r.nh==a.nh})[0];if(ex)ex.ad=1;else d.routes.push({net:a.net,mask:a.mask,nh:a.nh,ad:1})}
function noRoute(d,a){d.routes=d.routes.filter(function(r){return!(r.net==a.net&&r.mask==a.mask&&r.nh==a.nh)})}
function prompt(){var d=D();if(d.type=="pc")return"C:\\>";return d.host+({user:">",priv:"#",config:"(config)#","config-if":"(config-if)#","config-router":"(config-router)#"}[d.mode])}
function showRun(d){var o=["Building configuration...","","Current configuration : 1180 bytes","!","version 15.2","!","hostname "+d.host,"!"];
 PORTS[d.type].forEach(function(p){var f=d.ifs[p];o.push("interface "+full(p));if(f.ip)o.push(" ip address "+f.ip+" "+f.mask);else if(d.type=="router")o.push(" no ip address");if(d.type=="router"&&f.shut)o.push(" shutdown");o.push(" duplex auto"," speed auto","!")});
 if(d.type=="switch")o.push("interface Vlan1"," no ip address"," shutdown","!");
 if(d.ospf){o.push("router ospf "+d.ospf.pid);if(d.ospf.rid)o.push(" router-id "+d.ospf.rid);o.push(" log-adjacency-changes");d.ospf.passive.forEach(function(p){o.push(" passive-interface "+full(p))});d.ospf.nets.forEach(function(n){o.push(" network "+n.ip+" "+n.wc+" area "+n.area)});o.push("!")}
 o.push(d.type=="router"?"ip classless":"!");d.routes.forEach(function(r){o.push("ip route "+r.net+" "+r.mask+" "+r.nh+(r.ad&&r.ad!=1?" "+r.ad:""))});o.push("!","line con 0","!","line vty 0 4"," login","!","end");print(o.join("\n"))}
C.push(
 ["config",["router","ospf",A("pid")],routerOspf,"r"],["config-router",["router","ospf",A("pid")],routerOspf,"r"],["config",["no","router","ospf",A("pid")],noRouterOspf,"r"],
 ["config-router",["router-id",A("rid")],setRid,"r"],["config-router",["no","router-id"],function(d){d.ospf.rid=""},"r"],
 ["config-router",["network",A("ip"),A("wc"),"area",A("area")],netStmt,"r"],["config-router",["no","network",A("ip"),A("wc"),"area",A("area")],noNet,"r"],
 ["config-router",["passive-interface",A("if")],passive,"r"],["config-router",["no","passive-interface",A("if")],noPassive,"r"],["config-router",["log-adjacency-changes"],function(){},"r"],
 ["config-router",["exit"],function(d){d.mode="config"}],["config-router",["end"],endF],["config-router",["interface",A("if")],enterIf],
 ["config",["ip","route",A("net"),A("mask"),A("nh"),A("ad")],ipRouteAd,"r"],
 ["priv",["show","ip","ospf","neighbor"],showNbr,"r"],["user",["show","ip","ospf","neighbor"],showNbr,"r"],["priv",["show","ip","protocols"],showProto,"r"],["priv",["show","ip","ospf","interface","brief"],showOspfIfB,"r"],
 ["priv",["clear","ip","ospf","process"],function(){print("Reset ALL OSPF processes? [no]: yes")},"r"]);
/* adjacency change messages */
var _sub=submit;submit=function(){var before={};for(var k in st.devs){var X=st.devs[k];if(X.type=="router")before[k]=nbrs(X).filter(function(n){return nbrs(st.devs[n.id]).some(function(b){return b.id==k})}).map(function(n){return n.rid+"|"+n.p})}
 _sub();var d=D();if(!d||d.type!="router")return;var now=nbrs(d).filter(function(n){return nbrs(st.devs[n.id]).some(function(b){return b.id==d.id})}),was=before[d.id]||[],add=[];
 now.forEach(function(n){if(was.indexOf(n.rid+"|"+n.p)<0)print("%OSPF-5-ADJCHG: Process "+d.ospf.pid+", Nbr "+n.rid+" on "+full(n.p)+" from LOADING to FULL, Loading Done","sy")});
 was.forEach(function(w){if(!now.some(function(n){return n.rid+"|"+n.p==w})){var z=w.split("|");print("%OSPF-5-ADJCHG: Process "+(d.ospf?d.ospf.pid:1)+", Nbr "+z[0]+" on "+full(z[1])+" from FULL to DOWN, Neighbor Down: Interface down or detached","sy")}});save()};
/* ---------- OSPF lab tasks (from the CCNA OSPF lab file) ---------- */
function ospfOk(id,pid,r,nets){var d=R(id);if(!d)return id+"?";if(!d.ospf||d.ospf.pid!=pid)return"router ospf "+pid;if(d.ospf.rid!=r)return"router-id "+r;
 for(var i=0;i<nets.length;i++){var x=nets[i];if(!d.ospf.nets.some(function(n){return n.ip==x[0]&&n.wc==x[1]&&n.area==x[2]}))return"network "+x.join(" ").replace(/ (\S+)$/," area $1")}return true}
function hasAdj(a,b){var A1=R(a),B1=R(b);return!!(A1&&B1&&nbrs(A1).some(function(n){return n.id==b})&&nbrs(B1).some(function(n){return n.id==a}))}
function rtCode(id,pre){var d=R(id);if(!d)return null;var r=table(d).filter(function(x){return n2ip(x.net)+"/"+x.len==pre})[0];return r?r.code:null}
function floatOk(id,n,m,nh){var d=R(id);return!!(d&&d.routes.some(function(r){return r.net==n&&r.mask==m&&r.nh==nh&&r.ad==135}))}
/* ===== Security / ACL / DHCP / Telnet-SSH extension ===== */
var REDIR=null,COUNT=false;
function sec(d){if(!d.sec)d.sec={users:{},secret:"",domain:"",rsa:0,sshv:0,vty:{login:"",pw:"",tr:"all"}};return d.sec}
function dh(d){if(!d.dhcp)d.dhcp={excl:[],pools:{},bind:{}};return d.dhcp}
function acls(d){if(!d.acl)d.acl={};return d.acl}
function clone(o){return JSON.parse(JSON.stringify(o||null))}
/* --- ACL parsing & matching --- */
var PORTN={telnet:23,ssh:22,www:80,http:80,https:443,ftp:21,smtp:25,domain:53,tftp:69};
function spec(t,i){var x=(t[i]||"").toLowerCase();if(x=="any")return{s:{ip:"0.0.0.0",wc:"255.255.255.255",txt:"any"},n:1};
 if(x=="host"){if(ip2n(t[i+1])===null)return null;return{s:{ip:t[i+1],wc:"0.0.0.0",txt:"host "+t[i+1]},n:2}}
 if(ip2n(t[i])===null)return null;if(t[i+1]&&ip2n(t[i+1])!==null){if(!wcOk(t[i+1]))return null;return{s:{ip:t[i],wc:t[i+1],txt:t[i]+" "+t[i+1]},n:2}}
 return{s:{ip:t[i],wc:"0.0.0.0",txt:"host "+t[i]},n:1,std1:1}}
function aclCmd(d,a){var t=a.text.split(/\s+/),n=+t[0],act=(t[1]||"").toLowerCase(),bad=function(){print("% Invalid input detected at '^' marker.","er")};
 if(!(n>=1&&n<=199)||(act!="permit"&&act!="deny"&&act!="remark"))return bad();if(act=="remark")return;
 var e={act:act,hits:0};
 if(n<100){var s1=spec(t,2);if(!s1||t.length!=2+s1.n)return bad();e.proto="ip";e.src=s1.s;e.std=1;e.txt=act+" "+(s1.s.txt=="any"?"any":s1.s.txt.replace(/^host /,""))+(s1.s.wc!="0.0.0.0"&&s1.s.txt!="any"?", wildcard bits "+s1.s.wc:"")}
 else{var pr=(t[2]||"").toLowerCase();if(["ip","tcp","udp","icmp"].indexOf(pr)<0)return bad();var s1=spec(t,3);if(!s1||s1.std1)return bad();var s2=spec(t,3+s1.n);if(!s2||s2.std1)return bad();var k=3+s1.n+s2.n;
  e.proto=pr;e.src=s1.s;e.dst=s2.s;if(t[k]){if((t[k]||"").toLowerCase()!="eq"||(pr!="tcp"&&pr!="udp"))return bad();var p=/^\d+$/.test(t[k+1])?+t[k+1]:PORTN[(t[k+1]||"").toLowerCase()];if(!p||t.length!=k+2)return bad();e.port=p}
  e.txt=act+" "+pr+" "+s1.s.txt+" "+s2.s.txt+(e.port?" eq "+(({22:"22",23:"telnet",80:"www"})[e.port]||e.port):"")}
 e.cfg=t.slice(1).join(" ");var A=acls(d);(A[n]=A[n]||[]).push(e)}
function wm(s,ip){var w=(~ip2n(s.wc))>>>0;return((ip2n(ip)&w)>>>0)==((ip2n(s.ip)&w)>>>0)}
function aclMatch(e,p){if(!wm(e.src,p.src))return false;if(e.std)return true;if(!wm(e.dst,p.dst))return false;if(e.proto=="ip")return true;if(e.proto!=p.proto)return false;if(e.port&&e.port!=p.dport)return false;return true}
function aclPass(Dv,port,dir,p){var n=Dv.ifs[port]&&Dv.ifs[port]["ac"+dir];if(!n)return true;var Lst=Dv.acl&&Dv.acl[n];if(!Lst||!Lst.length)return true;
 for(var i=0;i<Lst.length;i++)if(aclMatch(Lst[i],p)){if(COUNT)Lst[i].hits++;return Lst[i].act=="permit"}return false}
/* --- ACL-aware forwarding --- */
function fwd(id,p,inP,ttl){var Dv=st.devs[id];if(ttl<0)return null;
 if(Dv.type=="router"&&inP&&!aclPass(Dv,inP,"in",p))return null;
 if(owns(Dv,p.dst))return id;
 if(Dv.type=="pc"){if(inP)return null;if(!Dv.ip||!maskOk(Dv.mask)||!portUp(id,"eth0"))return null;
  if(net(Dv.ip,Dv.mask)==net(p.dst,Dv.mask)){var e=onSeg(id,"eth0",p.dst);return e?fwd(e[0],p,e[1],ttl-1):null}
  if(!Dv.gw)return null;var g=onSeg(id,"eth0",Dv.gw);if(!g||st.devs[g[0]].type!="router")return null;return fwd(g[0],p,g[1],ttl-1)}
 if(Dv.type=="router"){var r=lookup(Dv,p.dst);if(!r)return null;if(inP&&!aclPass(Dv,r.p,"out",p))return null;var e=onSeg(id,r.p,r.nh||p.dst);if(!e)return null;return fwd(e[0],p,e[1],ttl-1)}
 return null}
function flow(id,dst,proto,port){var sip=srcIp(id,dst);if(!sip||ip2n(dst)===null)return false;var to=fwd(id,{src:sip,dst:dst,proto:proto,dport:port},null,16);if(!to)return false;
 var back=fwd(to,{src:dst,dst:sip,proto:proto,sport:port,dport:proto=="icmp"?undefined:1025},null,16);return back==id?to:false}
ping=function(id,dst){return !!flow(id,dst,"icmp")};
function snap(){var o={};for(var k in st.devs){var X=st.devs[k];if(X.type!="router")continue;var g={};for(var p in X.ifs){if(X.ifs[p].acin)g[p+" in"]=X.ifs[p].acin;if(X.ifs[p].acout)g[p+" out"]=X.ifs[p].acout}o[k]={acl:clone(X.acl||{}),ag:g,sec:clone(X.sec||null)}}return o}
function ev(o){st.ev=st.ev||[];o.snap=snap();st.ev.push(o);if(st.ev.length>200)st.ev.shift()}
/* --- DHCP --- */
var _pcSave=document.getElementById("psave").onclick;
function mac(id){var h=0;for(var i=0;i<id.length;i++)h=(h*31+id.charCodeAt(i))>>>0;var x=("0000"+(h&0xffff).toString(16)).slice(-4);return"0060."+x.slice(0,2)+"3e."+x.slice(2)+"01"}
function dhcpReq(pc){var sg=segment(pc.id,"eth0");for(var i=0;i<sg.length;i++){var R1=st.devs[sg[i][0]];if(R1.type!="router"||!R1.dhcp)continue;var f=R1.ifs[sg[i][1]];if(!f.ip)continue;
  for(var nm in R1.dhcp.pools){var P=R1.dhcp.pools[nm];if(!P.net||net(f.ip,f.mask)!=net(P.net,P.mask))continue;var m=mac(pc.id),have=R1.dhcp.bind[m];
   var used={};for(var b in R1.dhcp.bind)if(b!=m)used[R1.dhcp.bind[b]]=1;for(var q in R1.ifs)if(R1.ifs[q].ip)used[R1.ifs[q].ip]=1;
   var ex=function(ip){var n=ip2n(ip);return R1.dhcp.excl.some(function(x){return n>=ip2n(x[0])&&n<=ip2n(x[1]||x[0])})};
   var ip=have&&!ex(have)&&net(have,P.mask)==ip2n(P.net)?have:null,base=ip2n(P.net),sz=(~ip2n(P.mask))>>>0;
   for(var k=1;!ip&&k<sz;k++){var c=n2ip((base+k)>>>0);if(!used[c]&&!ex(c))ip=c}if(!ip)continue;
   R1.dhcp.bind[m]=ip;pc.ip=ip;pc.mask=P.mask;pc.gw=P.gw||"";pc.dns=P.dns||"";pc.dhcpSrv=R1.id;return true}}return false}
var pdh=document.getElementById("pdhcp");
function pcDhcpUi(d){if(!pdh)return;pdh.checked=!!d.dhcp;["pip","pmask","pgw"].forEach(function(k){document.getElementById(k).readOnly=!!d.dhcp})}
if(pdh)pdh.onchange=function(){var d=D();d.dhcp=pdh.checked;if(d.dhcp){var ok=dhcpReq(d);if(ok)print("DHCP request successful.\nIP: "+d.ip+"  Mask: "+d.mask+"  Gateway: "+d.gw+"  DNS: "+(d.dns||"-"),"ok");else{print("DHCP failed. APIPA is being used.","er");d.ip="169.254."+(mac(d.id).charCodeAt(6)%254+1)+"."+(mac(d.id).charCodeAt(8)%254+1);d.mask="255.255.0.0";d.gw=""}}
 else{d.dns=""}pip.value=d.ip;pmask.value=d.mask;pgw.value=d.gw;pcDhcpUi(d);save();draw();live()};
var _open=openCli;openCli=function(id){_open(id);var d=D();if(d.type=="pc")pcDhcpUi(d);inp.type="text"};
document.getElementById("psave").onclick=function(){var d=D();if(d.dhcp)return;_pcSave()};
/* --- router config commands --- */
function exclAdd(d,a){if(ip2n(a.a)===null||(a.b&&ip2n(a.b)===null))return print("% Invalid input detected at '^' marker.","er");var X=dh(d);if(!X.excl.some(function(x){return x[0]==a.a&&x[1]==(a.b||"")}))X.excl.push([a.a,a.b||""])}
function poolEnter(d,a){var X=dh(d);X.pools[a.name]=X.pools[a.name]||{net:"",mask:"",gw:"",dns:""};d.pool=a.name;d.mode="dhcp-config"}
function P(d){return d.dhcp.pools[d.pool]}
function secretF(d,a){sec(d).secret=a.pw}
function userF(d,a){var t=a.text.split(/\s+/),u={priv:1};if(!t[0])return print("% Incomplete command.","er");var i=1;
 if((t[1]||"").toLowerCase()=="privilege"){if(!(+t[2]>=0&&+t[2]<=15))return print("% Invalid input detected at '^' marker.","er");u.priv=+t[2];i=3}
 var k=(t[i]||"").toLowerCase();if(k!="password"&&k!="secret")return print("% Invalid input detected at '^' marker.","er");var pw=t.slice(i+1).join(" ");if(/^[057] /.test(pw))pw=pw.slice(2);if(!pw)return print("% Incomplete command.","er");u.pw=pw;u.kind=k;sec(d).users[t[0]]=u}
function cryptoF(d,a){var bits=+(a.bits||512);if(d.host=="Router"||d.host=="Switch")return print("% Please define a hostname other than Router.","er");if(!sec(d).domain)return print("% Please define a domain-name first.","er");
 if([360,512,768,1024,2048,4096].indexOf(bits)<0&&!(bits>=360&&bits<=4096))return print("% Invalid input detected at '^' marker.","er");
 print("The name for the keys will be: "+d.host+"."+sec(d).domain+"\nChoose the size of the key modulus in the range of 360 to 4096 for your\n  General Purpose Keys. Choosing a key modulus greater than 512 may take\n  a few minutes.\n\nHow many bits in the modulus [512]: "+bits+"\n% Generating "+bits+" bit RSA keys, keys will be non-exportable...[OK]","sy");
 sec(d).rsa=bits;print("*Mar 1 00:12:45.123: %SSH-5-ENABLED: SSH 1.99 has been enabled","sy")}
function sshVer(d,a){if(!sec(d).rsa)return print("Please create RSA keys to enable SSH (and of atleast 768 bits for SSH v2).","er");if(a.v=="2"&&sec(d).rsa<768)return print("Please create RSA keys (of atleast 768 bits size) to enable SSH v2.","er");if(a.v!="1"&&a.v!="2")return print("% Invalid input detected at '^' marker.","er");sec(d).sshv=+a.v}
function lineVty(d,a){if(!/^\d+$/.test(a.a)||+a.a>15||(a.b&&(!/^\d+$/.test(a.b)||+a.b>15)))return print("% Invalid input detected at '^' marker.","er");sec(d);d.mode="config-line"}
function transF(d,a){var t=a.text.toLowerCase().split(/\s+/),ok=t.every(function(x){return["telnet","ssh","all","none"].indexOf(x)>=0});if(!ok||!t.length)return print("% Invalid input detected at '^' marker.","er");sec(d).vty.tr=t.indexOf("all")>=0?"all":t.indexOf("none")>=0?"none":t.sort().join(" ")}
function agF(d,a,no){var dir=(a.dir||"").toLowerCase();if(dir!="in"&&dir!="out"||!/^\d+$/.test(a.n))return print("% Invalid input detected at '^' marker.","er");var f=d.ifs[d.cur];if(no){if(f["ac"+dir]==a.n)delete f["ac"+dir]}else f["ac"+dir]=a.n}
function showAcl(d){var A=acls(d),o=[];Object.keys(A).sort(function(a,b){return a-b}).forEach(function(n){if(!A[n].length)return;o.push((+n<100?"Standard":"Extended")+" IP access list "+n);A[n].forEach(function(e,i){o.push("    "+((i+1)*10)+" "+e.txt+(e.hits?" ("+e.hits+" match(es))":""))})});print(o.join("\n"));ev({t:"show",dev:d.id})}
function showBind(d){var X=d.dhcp||{bind:{}},o=["IP address       Client-ID/              Lease expiration        Type","                 Hardware address"];for(var m in X.bind)o.push(pad(X.bind[m],17)+pad(m,24)+pad("--",24)+"Automatic");print(o.join("\n"))}
function showPool(d){var X=d.dhcp||{pools:{}},o=[];for(var n in X.pools){var p=X.pools[n],c=0;for(var m in X.bind)if(p.net&&net(X.bind[m],p.mask)==ip2n(p.net))c++;o.push("","Pool "+n+" :"," Utilization mark (high/low)    : 100 / 0"," Subnet size (first/next)       : 0 / 0"," Total addresses                : "+(p.mask?((~ip2n(p.mask))>>>0)-1:0)," Leased addresses               : "+c," Excluded addresses             : "+X.excl.length," Pending event                  : none"," 1 subnet is currently in the pool"," Current index        IP address range                    Leased/Excluded/Total"," "+pad(p.net||"-",21)+(p.net||"-")+" - "+(p.net?n2ip((ip2n(p.net)+((~ip2n(p.mask))>>>0))>>>0):"-")+"     "+c)}print(o.join("\n"))}
function showSsh(d){var s=sec(d);print(s.rsa?"SSH Enabled - version "+(s.sshv==2?"2.0":"1.99")+"\nAuthentication timeout: 120 secs; Authentication retries: 3":"%SSH has not been enabled")}
C=C.filter(function(c){var k=c[1].join(" ");return k!="enable secret [object Object]"});
C.push(
 ["priv",["enable"],function(){}],["config",["enable","secret",A("pw")],secretF],["config",["enable","password",A("pw")],secretF],
 ["config",["username",A("text")],userF],["config",["no","username",A("name")],function(d,a){delete sec(d).users[a.name]}],
 ["config",["ip","domain-name",A("name")],function(d,a){sec(d).domain=a.name}],["config",["ip","domain","name",A("name")],function(d,a){sec(d).domain=a.name}],
 ["config",["crypto","key","generate","rsa"],cryptoF],["config",["crypto","key","generate","rsa","modulus",A("bits")],cryptoF],["config",["crypto","key","generate","rsa","general-keys","modulus",A("bits")],cryptoF],
 ["config",["ip","ssh","version",A("v")],sshVer],
 ["config",["line","vty",A("a")],lineVty],["config",["line","vty",A("a"),A("b")],lineVty],["config",["line","console",A("a")],function(d){d.mode="config-line";d.con=1}],
 ["config-line",["login","local"],function(d){if(d.con)return;sec(d).vty.login="local"}],["config-line",["login"],function(d){if(d.con)return;sec(d).vty.login="line"}],["config-line",["no","login"],function(d){if(!d.con)sec(d).vty.login="none"}],
 ["config-line",["password",A("pw")],function(d,a){if(!d.con)sec(d).vty.pw=a.pw}],["config-line",["transport","input",A("text")],function(d,a){if(!d.con)transF(d,a)}],
 ["config-line",["exit"],function(d){d.mode="config";d.con=0}],["config-line",["end"],function(d){d.con=0;endF(d)}],["config-line",["line","vty",A("a")],lineVty],["config-line",["line","vty",A("a"),A("b")],lineVty],["config-line",["exec-timeout",A("text")],function(){}],["config-line",["logging","synchronous"],function(){}],
 ["config",["access-list",A("text")],aclCmd,"r"],["config",["no","access-list",A("n")],function(d,a){delete acls(d)[a.n]},"r"],
 ["config-if",["ip","access-group",A("n"),A("dir")],function(d,a){agF(d,a)},"r"],["config-if",["no","ip","access-group",A("n"),A("dir")],function(d,a){agF(d,a,1)},"r"],
 ["config",["ip","dhcp","excluded-address",A("a")],exclAdd,"r"],["config",["ip","dhcp","excluded-address",A("a"),A("b")],exclAdd,"r"],
 ["config",["no","ip","dhcp","excluded-address",A("a")],function(d,a){dh(d).excl=dh(d).excl.filter(function(x){return x[0]!=a.a})},"r"],
 ["config",["ip","dhcp","pool",A("name")],poolEnter,"r"],["config",["no","ip","dhcp","pool",A("name")],function(d,a){delete dh(d).pools[a.name]},"r"],
 ["dhcp-config",["network",A("ip"),A("mask")],function(d,a){if(ip2n(a.ip)===null||!maskOk(a.mask))return print("% Invalid input detected at '^' marker.","er");P(d).net=n2ip(net(a.ip,a.mask));P(d).mask=a.mask},"r"],
 ["dhcp-config",["default-router",A("ip")],function(d,a){if(ip2n(a.ip)===null)return print("% Invalid input detected at '^' marker.","er");P(d).gw=a.ip},"r"],
 ["dhcp-config",["dns-server",A("ip")],function(d,a){if(ip2n(a.ip)===null)return print("% Invalid input detected at '^' marker.","er");P(d).dns=a.ip},"r"],
 ["dhcp-config",["exit"],function(d){d.mode="config";d.pool=null}],["dhcp-config",["end"],function(d){d.pool=null;endF(d)}],
 ["priv",["show","access-lists"],showAcl,"r"],["user",["show","access-lists"],showAcl,"r"],["priv",["show","ip","access-lists"],showAcl,"r"],
 ["priv",["show","ip","dhcp","binding"],showBind,"r"],["priv",["show","ip","dhcp","pool"],showPool,"r"],["priv",["show","ip","ssh"],showSsh,"r"],
 ["priv",["telnet",A("ip")],function(d,a){print(flow(d.id,a.ip,"tcp",23)?"Trying "+a.ip+" ... Open\n[Connection closed — nested sessions are not supported in this lab]":"Trying "+a.ip+" ...\n% Connection timed out; remote host not responding",flow(d.id,a.ip,"tcp",23)?"sy":"er")}]
);
var _prompt=prompt;prompt=function(){var d=D();if(d.type=="pc"&&d.sess){var s=d.sess,T1=st.devs[s.to];if(s.stage=="user")return"Username: ";if(s.stage=="pass"||s.stage=="en")return"Password: ";var sv=T1.mode;T1.mode=s.mode;var c2=cur;cur=T1.id;var r=_prompt();cur=c2;T1.mode=sv;return r}
 if(d.type!="pc"){var X={"dhcp-config":"(dhcp-config)#","config-line":"(config-line)#"}[d.mode];if(X)return d.host+X}return _prompt()};
/* show running-config with the new sections */
var _run=showRun;showRun=function(d){var buf=[],sv=print;print=function(t){buf.push(t)};_run(d);print=sv;var o=buf.join("\n").split("\n"),s=d.sec||{users:{},vty:{}},X=d.dhcp,A2=d.acl||{};
 var hi=o.indexOf("hostname "+d.host)+1,add=["!"];if(s.secret)add.push("enable secret 5 $1$mERr$"+btoa(s.secret).replace(/=/g,"").slice(0,22));
 if(X){X.excl.forEach(function(x){add.push("ip dhcp excluded-address "+x[0]+(x[1]?" "+x[1]:""))});for(var n in X.pools){var p=X.pools[n];add.push("!","ip dhcp pool "+n);if(p.net)add.push(" network "+p.net+" "+p.mask);if(p.gw)add.push(" default-router "+p.gw);if(p.dns)add.push(" dns-server "+p.dns)}add.push("!")}
 if(s.domain)add.push("ip domain-name "+s.domain);if(s.sshv)add.push("ip ssh version "+s.sshv);Object.keys(s.users||{}).forEach(function(u){var x=s.users[u];add.push("username "+u+(x.priv!=1?" privilege "+x.priv:"")+" "+(x.kind||"password")+" "+(x.kind=="secret"?"5 $1$mERr$"+btoa(x.pw).replace(/=/g,""):"0 "+x.pw))});
 o.splice(hi,0,...add);
 o=o.map(function(l,i){var m=/^interface (GigabitEthernet\S+)/.exec(l);return l});
 var out2=[];o.forEach(function(l,i){out2.push(l);var m=/^interface GigabitEthernet(\S+)/.exec(l);if(m){var f=d.ifs["g"+m[1]];if(f&&f.acin)out2.push(" ip access-group "+f.acin+" in");if(f&&f.acout)out2.push(" ip access-group "+f.acout+" out")}});o=out2;
 var li=o.indexOf("line con 0"),acl=[];Object.keys(A2).sort(function(a,b){return a-b}).forEach(function(n){A2[n].forEach(function(e){acl.push("access-list "+n+" "+e.cfg)})});if(acl.length)o.splice(li,0,...acl,"!");
 var vi=o.indexOf("line vty 0 4");if(vi>=0&&d.sec){var v=[];if(s.vty.pw)v.push(" password "+s.vty.pw);if(s.vty.login=="local")v.push(" login local");else if(s.vty.login!="none")v.push(" login");if(s.vty.tr&&s.vty.tr!="all")v.push(" transport input "+s.vty.tr);else v.push(" transport input all");o.splice(vi+1,1,...v)}
 print(o.join("\n"))};
/* --- PC: telnet / ssh client (Putty-like) --- */
function vtyAllows(T1,proto){var tr=sec(T1).vty.tr;return tr=="all"||tr.split(" ").indexOf(proto)>=0}
function remote(d,proto,ip,user){var tgt=flow(d.id,ip,"tcp",proto=="ssh"?22:23);
 if(!tgt||st.devs[tgt].type!="router"){print(proto=="ssh"?"% Connection timed out; remote host not responding":"Trying "+ip+" ...\n% Connection timed out; remote host not responding","er");ev({t:"login",proto:proto,from:d.id,ip:ip,ok:0});return}
 var T1=st.devs[tgt],s=sec(T1);
 if(!vtyAllows(T1,proto)||(proto=="ssh"&&!s.rsa)){print(proto=="ssh"?"% Connection refused by remote host":"Trying "+ip+" ...\n% Connection refused by remote host","er");ev({t:"login",proto:proto,from:d.id,ip:ip,ok:0});return}
 var hasU=Object.keys(s.users).length>0,how=s.vty.login=="line"&&!hasU?"line":s.vty.login=="none"?"none":hasU?"local":"";
 if(proto=="telnet")print("Trying "+ip+" ...Open\n","sy");
 if(!how){print("\n[Connection to "+ip+" closed by foreign host]\nPassword required, but none set","er");return}
 d.sess={to:tgt,ip:ip,proto:proto,user:user||"",mode:"user",how:how,tries:0,stage:how=="none"?"on":proto=="ssh"||how=="line"?"pass":"user"};
 if(proto=="ssh"&&how=="line"){}
 if(d.sess.stage=="on")loginOk(d,1);else if(d.sess.stage=="pass")inp.type="password";
 if(proto=="telnet")print("\nUser Access Verification\n")}
function loginOk(d,priv){var s=d.sess;s.stage="on";s.mode=priv>=15?"priv":"user";s.priv=priv;inp.type="text";ev({t:"login",proto:s.proto,from:d.id,ip:s.ip,to:s.to,user:s.user,ok:1})}
function closeSess(d,msg){var s=d.sess;d.sess=null;inp.type="text";print("\n[Connection to "+s.ip+" closed by "+(msg||"foreign host")+"]","sy")}
function sessIn(d,v){var s=d.sess,T1=st.devs[s.to],sc=sec(T1);
 if(s.stage=="user"){print("Username: "+v);s.user=v.trim();s.stage="pass";inp.type="password";return}
 if(s.stage=="pass"){print("Password: ");var u=sc.users[s.user],ok=s.how=="line"?v==sc.vty.pw:!!(u&&u.pw==v);
  if(ok)return loginOk(d,s.how=="line"?1:u.priv);s.tries++;print(s.proto=="ssh"?"% Authentication failed":"% Login invalid\n","er");if(s.tries>=3||s.proto=="ssh"){closeSess(d);return}s.stage=s.how=="local"?"user":"pass";inp.type=s.stage=="pass"?"password":"text";return}
 if(s.stage=="en"){print("Password: ");inp.type="text";s.stage="on";if(sc.secret&&v==sc.secret){s.mode="priv";return}s.enTries=(s.enTries||0)+1;print("% Access denied","er");return}
 var tk=v.trim().toLowerCase().split(/\s+/);print(prompt()+v);
 if((s.mode=="user"||s.mode=="priv")&&(/^(exit|logout|quit)$/.test(tk[0])||(tk[0]=="disable"&&s.mode=="user"))){if(tk[0]!="disable")return closeSess(d)}
 if(s.mode=="user"&&"enable".indexOf(tk[0])==0&&tk[0].length>=2&&tk.length==1){if(!sc.secret)return print("% No password set","er");s.stage="en";inp.type="password";return}
 var pc=d.id,sv=T1.mode;REDIR=d;COUNT=true;cur=T1.id;T1.mode=s.mode;try{exec(v)}finally{s.mode=T1.mode;T1.mode=sv;cur=pc;REDIR=null;COUNT=false}}
var _pcExec=pcExec;pcExec=function(line,t){var d=D(),c=t[0].toLowerCase();
 if(c=="telnet"){print("C:\\>"+line);if(ip2n(t[1])===null)return print("% Unknown command or computer name","er");COUNT=true;remote(d,"telnet",t[1]);COUNT=false;return}
 if(c=="ssh"){print("C:\\>"+line);var u="",ip="";for(var i=1;i<t.length;i++){if(t[i]=="-l"){u=t[i+1];i++}else ip=t[i]}if(!u||ip2n(ip)===null)return print("Usage: SSH -l username target","er");COUNT=true;remote(d,"ssh",ip,u);COUNT=false;return}
 if(c=="ipconfig"&&/^\/(renew|release|all)$/i.test(t[1]||"")){print("C:\\>"+line);var o=t[1].toLowerCase();
  if(o=="/renew"){if(!d.dhcp)return print("DHCP is not enabled on this adapter. Enable DHCP in IP Configuration first.","er");var ok=dhcpReq(d);save();draw();return print(ok?"\n   IP Address......................: "+d.ip+"\n   Subnet Mask.....................: "+d.mask+"\n   Default Gateway.................: "+d.gw+"\n   DNS Server......................: "+d.dns:"DHCP request failed.",ok?"ok":"er")}
  if(o=="/release"){if(d.dhcp&&d.dhcpSrv&&st.devs[d.dhcpSrv]&&st.devs[d.dhcpSrv].dhcp)delete st.devs[d.dhcpSrv].dhcp.bind[mac(d.id)];d.ip="";d.mask="";d.gw="";d.dns="";save();draw();return print("IP address released.","ok")}
  return print("\nFastEthernet0 Connection:(default port)\n\n   Physical Address................: "+mac(d.id).replace(/\./g,"").toUpperCase().replace(/(..)(?=.)/g,"$1-")+"\n   DHCP Enabled....................: "+(d.dhcp?"Yes":"No")+"\n   IPv4 Address....................: "+(d.ip||"0.0.0.0")+"\n   Subnet Mask.....................: "+(d.mask||"0.0.0.0")+"\n   Default Gateway.................: "+(d.gw||"0.0.0.0")+"\n   DNS Servers.....................: "+(d.dns||"0.0.0.0")+"\n")}
 if(c=="ping"){COUNT=true;var ip=t[1];_pcExec(line,t);COUNT=false;if(ip2n(ip)!==null)ev({t:"ping",from:d.id,dst:ip,ok:ping(d.id,ip)?1:0});return}
 return _pcExec(line,t)};
var _submit=submit;submit=function(){var d=D();if(d.type=="pc"&&d.sess){var v=inp.value;inp.value="";if(/\?$/.test(v)&&d.sess.stage=="on"){var T1=st.devs[d.sess.to],sv=T1.mode,pc=cur;REDIR=d;cur=T1.id;T1.mode=d.sess.mode;help(v);T1.mode=sv;cur=pc;REDIR=null;inp.value=v.replace(/\?$/,"");paintLog();return}
  if(d.sess.stage=="on"&&v.trim()){d.hist.push(v);hi=d.hist.length}sessIn(d,v);save();paintLog();live();return}
 if(d.type!="pc"){COUNT=true;try{_submit()}finally{COUNT=false}}else _submit()};
var _pingR=pingR;
/* helpers for lab task checks */
function aclIs(snapR,n,lines){var Lst=snapR&&snapR.acl&&snapR.acl[n];if(!Lst||Lst.length!=lines.length)return false;return lines.every(function(x,i){return Lst[i].cfg==x.replace(/\s+/g," ").trim()})}
function agIs(snapR,p,dir,n){return!!(snapR&&snapR.ag&&snapR.ag[p+" "+dir]==n)}
function evFind(f){return(st.ev||[]).some(f)}
function pool(id,name){var d=R(id);return d&&d.dhcp&&d.dhcp.pools[name]}
var API={st:function(){return st},R:R,ping:ping,flow:flow,BASE:BASE,linkInfo:linkInfo,W:W,ifOk:ifOk,hasRoute:hasRoute,lang:function(){return L},snap:snap,aclIs:aclIs,agIs:agIs,evFind:evFind,pool:pool,sec:sec,mac:mac,ip2n:ip2n,net:net};
var T=CFG.tasks(API);
CFG.L=function(){return L};
var _grade=grade;grade=function(){var c=COUNT;COUNT=false;try{_grade()}finally{COUNT=c}};document.getElementById("check").onclick=function(){grade()};
addEventListener("resize",fit);load();fit();paint();
window.__lab={st:function(){return st},exec:function(id,cmds){openCli(id);cmds.forEach(function(c){inp.value=c;submit()})},link:function(a,ap,b,bp,c){st.links.push({a:{d:a,p:ap},b:{d:b,p:bp},cable:c});save();draw()},add:newDev,draw:draw,setMode:setMode,grade:grade,ping:ping,flow:flow,open:openCli,close:closeCli};

})(window.LAB);
