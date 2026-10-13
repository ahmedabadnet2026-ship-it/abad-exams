/* ABAD — إرسال شهادة الحضور عبر واتساب (PDF يُنشأ في المتصفح: html2canvas + jsPDF من assets/abad-pdf-libs.js) */
(function(){
const T=(a,b)=>typeof tt==="function"?tt(a,b):a, say=m=>typeof toast==="function"?toast(m):0;
const e=s=>String(s==null?"":s).replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[c]);
const MAPS="https://maps.app.goo.gl/bKxZCp2Tsu7Kyneu6";
let LIBS=null;
function libs(){if(window.jspdf&&window.html2canvas&&window.qrcode)return Promise.resolve();if(LIBS)return LIBS;
 LIBS=new Promise((res,rej)=>{const s=document.createElement("script");s.src="assets/abad-pdf-libs.js";s.onload=res;s.onerror=()=>{LIBS=null;rej(new Error("pdf_libs"))};document.head.appendChild(s)});return LIBS}
function phone(s){let d=String(s||"").replace(/[^\d]/g,"").replace(/^00/,"");if(/^9660/.test(d))d="966"+d.slice(4);if(/^05\d{8}$/.test(d))d="966"+d.slice(1);else if(/^5\d{8}$/.test(d))d="966"+d;return d}
const okPhone=d=>/^9665\d{8}$/.test(d);
function fd(s,en){const d=new Date(String(s||"").slice(0,10)+"T12:00:00");return isNaN(d)?String(s||"—"):d.toLocaleDateString(en?"en-GB":"ar-SA-u-ca-gregory-nu-latn",{day:"numeric",month:"long",year:"numeric"})}
const fname=r=>"ABAD-Certificate-"+String(r.id||"").replace(/[^\w-]/g,"")+".pdf";
function fontCSS(){let t="";for(const s of document.querySelectorAll("style"))if(/@font-face/.test(s.textContent))t+=(s.textContent.match(/@font-face\s*{[^}]*}/g)||[]).join("\n");return t}
const CSS='.ct{position:relative;width:1123px;height:794px;background:#fff;font-family:Cairo,sans-serif;color:#10293b;overflow:hidden;direction:rtl}'+
 '.ct .o{position:absolute;inset:18px;border:10px solid #10293b}.ct .i{position:absolute;inset:36px;border:2px solid #F5BD2D}.ct .c1,.ct .c2{position:absolute;width:300px;height:300px;border-radius:50%;background:#3f86b31a}.ct .c1{top:-120px;left:-120px}.ct .c2{bottom:-140px;right:-120px;background:#F5BD2D1f}'+
 '.ct .wm{position:absolute;left:50%;top:52%;width:380px;transform:translate(-50%,-50%);opacity:.05}'+
 '.ct .b{position:absolute;inset:56px 80px;text-align:center;display:flex;flex-direction:column;align-items:center}'+
 '.ct .lg{height:88px}.ct h1{margin:8px 0 0;font-size:46px;font-weight:800;color:#10293b;line-height:1.2}.ct .en{font-size:17px;letter-spacing:4px;color:#3f86b3;font-weight:700;direction:ltr}'+
 '.ct .gl{width:220px;height:4px;background:linear-gradient(90deg,#3f86b3,#F5BD2D);margin:12px auto 14px;border-radius:2px}'+
 '.ct .p{font-size:20px;color:#33536a;margin:2px 0}.ct .nm{font-size:40px;font-weight:800;color:#3f86b3;margin:6px 0 4px;padding:0 30px 4px;border-bottom:2px dashed #F5BD2D}'+
 '.ct .cr{font-size:28px;font-weight:800;color:#10293b;margin:6px 0}.ct .dt{font-size:18px;color:#33536a}.ct .dt b{color:#10293b}'+
 '.ct .ft{position:absolute;bottom:0;left:0;right:0;display:flex;justify-content:space-between;align-items:flex-end}'+
 '.ct .k{font-size:13px;color:#5b7488;text-align:right;line-height:1.7}.ct .k b{color:#10293b;font-size:15px}.ct .sg{text-align:center;font-size:14px;color:#10293b;font-weight:700}.ct .sg i{display:block;width:200px;border-top:2px solid #10293b;margin:0 auto 6px}'+
 '.ct .qr{text-align:center;font-size:10px;color:#5b7488}.ct .qr img{width:86px;height:86px;display:block}';
function html(r){const logo=(document.querySelector("header img")||{}).src||"";let qr="";try{const q=qrcode(0,"M");q.addData("ABAD-CERT "+(r.id||""));q.make();qr=q.createDataURL(6,1)}catch(_){}
 const now=new Date().toLocaleDateString("ar-SA-u-ca-gregory-nu-latn",{timeZone:"Asia/Riyadh",day:"numeric",month:"long",year:"numeric"});
 return '<div class="ct"><div class="c1"></div><div class="c2"></div><div class="o"></div><div class="i"></div>'+(logo?'<img class="wm" src="'+logo+'">':'')+'<div class="b">'+
  (logo?'<img class="lg" src="'+logo+'">':'')+'<h1>شهادة حضور</h1><div class="en">CERTIFICATE OF ATTENDANCE</div><div class="gl"></div>'+
  '<div class="p">يشهد معهد شبكة آباد للتدريب بأن المتدرب/ة</div><div class="nm">'+e(r.name)+'</div>'+
  '<div class="p">قد أتمّ/ت حضور الدورة التدريبية</div><div class="cr">'+e(r.course)+'</div>'+
  '<div class="dt">خلال الفترة من <b>'+e(fd(r.start))+'</b> إلى <b>'+e(fd(r.end))+'</b>'+(r.training_type?' · نوع التدريب: <b>'+e(r.training_type)+'</b>':'')+'</div>'+
  '<div class="ft"><div class="k">رقم المرجع · Ref<br><b dir="ltr">'+e(r.id)+'</b><br>تاريخ الإصدار · Issued<br><b>'+e(now)+'</b></div>'+
  '<div class="sg"><i></i>إدارة معهد شبكة آباد للتدريب<br><span style="font-weight:400;color:#5b7488;font-size:12px">ABAD Network Training Institute — Riyadh</span></div>'+
  '<div class="qr">'+(qr?'<img src="'+qr+'">':'')+'REF QR</div></div></div></div>'}
async function render(r){await libs();
 const f=document.createElement("iframe");f.setAttribute("aria-hidden","true");f.style.cssText="position:fixed;left:-14000px;top:0;width:1123px;height:794px;border:0;visibility:hidden";document.body.appendChild(f);
 try{const d=f.contentDocument;d.open();d.write('<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><style>'+fontCSS()+'html,body{margin:0;background:#fff}*{box-sizing:border-box}'+CSS+'</style></head><body>'+html(r)+'</body></html>');d.close();
  await Promise.all([...d.images].map(i=>i.complete?0:new Promise(z=>{i.onload=i.onerror=z})));
  if(d.fonts){try{await d.fonts.load('800 16px Cairo');await d.fonts.load('700 16px Cairo');await d.fonts.load('400 16px Cairo');await d.fonts.ready}catch(_){}}
  const c=await html2canvas(d.body.firstChild,{scale:2.5,backgroundColor:"#ffffff",useCORS:true,logging:false,windowWidth:1123,windowHeight:794});
  const img=c.toDataURL("image/jpeg",.93),pdf=new jspdf.jsPDF({unit:"mm",format:"a4",orientation:"landscape",compress:true});
  pdf.setProperties({title:"ABAD Certificate "+r.id,author:"ABAD Network Training Institute",subject:"Certificate of Attendance"});pdf.addImage(img,"JPEG",0,0,297,210);
  return{blob:pdf.output("blob"),img}}finally{f.remove()}}
function text(r){const n=String(r.name||"").trim(),c=String(r.course||"").trim();
 if(r.lang==="en")return "Assalamu alaikum "+n+" 🌷\n\nCongratulations on successfully completing the \""+c+"\" course at ABAD Network Training Institute 🎉 You truly earned it!\n\nPlease find your attendance certificate attached (Ref: "+r.id+").\nKeep going — onwards and upwards 🚀\n\nWe'd love your feedback on Google Maps ⭐\n"+MAPS+"\n\nKind regards,\nABAD Network Training Institute\n📍 Riyadh | 🌐 abadnet.com.sa | 📞 +966 56 139 3960";
 return "السلام عليكم ورحمة الله وبركاته،\nأستاذ/ة "+n+"، ألف مبروك 🎉🌷\n\nيسعدنا في معهد شبكة آباد للتدريب أن نهنّئك بإتمام دورة «"+c+"» بنجاح، وهذا الإنجاز تستاهله عن جدارة 👏\n\nمرفق لك شهادة الحضور (رقم المرجع: "+r.id+").\nنتمنى لك دوام التوفيق، واستمر على هالمستوى المميز… ومنها للأعلى 🚀\n\nويسعدنا نسمع رأيك في تجربتك معنا بتقييم المعهد على خرائط Google ⭐\n"+MAPS+"\n\nمع خالص التحية والتقدير،\nمعهد شبكة آباد للتدريب\n📍 الرياض | 🌐 abadnet.com.sa | 📞 الدعم: 0561393960"}
let CUR=null,PDF=null;
function modal(){let m=document.getElementById("csm");if(m)return m;
 const st=document.createElement("style");st.textContent='#csm{position:fixed;inset:0;z-index:9999;background:#06121bcc;display:flex;align-items:center;justify-content:center;padding:14px;font-family:Cairo,sans-serif}#csm[hidden]{display:none}'+
  '#csm .bx{background:#fff;color:#10293b;border-radius:16px;max-width:760px;width:100%;max-height:94vh;overflow:auto;padding:16px 18px;box-shadow:0 20px 50px #0008;border-top:5px solid #F5BD2D}'+
  '#csm h3{margin:0 0 10px;font-size:17px}#csm .pv{background:#eef3f7;border-radius:10px;min-height:120px;display:flex;align-items:center;justify-content:center;color:#5b7488;font-size:14px}#csm .pv img{width:100%;display:block;border-radius:8px;box-shadow:0 2px 10px #0002}'+
  '#csm label{display:block;font-size:13px;font-weight:700;margin:10px 0 4px}#csm input,#csm textarea{width:100%;font:inherit;font-size:14px;border:1px solid #c9d6e0;border-radius:8px;padding:8px 10px;box-sizing:border-box}#csm textarea{height:190px;line-height:1.7}'+
  '#csm .n{font-size:12px;color:#5b7488;margin-top:4px}#csm .ac{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}#csm .ac button{font:inherit;font-weight:700;font-size:14px;border-radius:9px;padding:9px 14px;border:1px solid #c9d6e0;background:#fff;color:#10293b;cursor:pointer}'+
  '#csm .ac .pri{background:#25D366;border-color:#25D366;color:#fff;flex:1 1 220px}#csm .ac button:disabled{opacity:.5;cursor:default}#csm .ht{margin:10px 0 0;padding:9px 12px;border-radius:9px;background:#fff7df;border:1px solid #F5BD2D;font-size:13px;line-height:1.7}';
 document.head.appendChild(st);m=document.createElement("div");m.id="csm";m.hidden=true;m.setAttribute("role","dialog");m.setAttribute("aria-modal","true");
 m.innerHTML='<div class="bx"><h3 id="csm-h"></h3><div class="pv" id="csm-pv"></div><label id="csm-lp"></label><input id="csm-p" dir="ltr" inputmode="tel"><div class="n" id="csm-n"></div><label id="csm-lx"></label><textarea id="csm-x"></textarea>'+
  '<div class="ac"><button type="button" class="pri" id="csm-go"></button><button type="button" id="csm-dl"></button><button type="button" id="csm-cp"></button><button type="button" id="csm-c"></button></div><p class="ht" id="csm-ht" hidden></p></div>';
 document.body.appendChild(m);const $=id=>document.getElementById(id);
 m.addEventListener("click",ev=>{if(ev.target===m)close()});document.addEventListener("keydown",ev=>{if(ev.key==="Escape"&&!m.hidden)close()});
 const cnt=()=>{const p=phone($("csm-p").value);$("csm-n").textContent=(okPhone(p)?"✓ +"+p:"⚠ "+T("رقم جوال غير صالح (يلزم رقم سعودي 05xxxxxxxx)","Invalid Saudi mobile"))+" · "+$("csm-x").value.length+" "+T("حرف","chars");$("csm-go").disabled=!PDF||!$("csm-x").value.trim();$("csm-dl").disabled=!PDF};
 m._cnt=cnt;$("csm-p").oninput=cnt;$("csm-x").oninput=cnt;$("csm-c").onclick=close;
 $("csm-cp").onclick=async()=>{try{await navigator.clipboard.writeText($("csm-x").value);say(T("تم نسخ النص","Text copied"))}catch(_){$("csm-x").select()}};
 $("csm-dl").onclick=()=>{if(PDF)save(PDF,fname(CUR))};
 $("csm-go").onclick=async()=>{const r=CUR,txt=$("csm-x").value,p=phone($("csm-p").value);if(!PDF||!r)return;const file=new File([PDF],fname(r),{type:"application/pdf"});
  let can=false;try{can=!!(navigator.canShare&&navigator.share&&navigator.canShare({files:[file]}))&&/Android|iPhone|iPad|Mobile/i.test(navigator.userAgent)}catch(_){}
  if(can){try{await navigator.share({files:[file],text:txt,title:fname(r)});say(T("تمت المشاركة ✓","Shared ✓"));close()}catch(x){if(x&&x.name!=="AbortError")say(T("تعذّرت المشاركة","Sharing failed"))}return}
  if(!okPhone(p)){say(T("رقم جوال غير صالح","Invalid mobile number"));return}
  const w=window.open("about:blank","_blank");save(PDF,fname(r));const u="https://wa.me/"+p+"?text="+encodeURIComponent(txt);if(w){try{w.opener=null}catch(_){}w.location.href=u}else location.href=u;
  $("csm-ht").textContent="📎 "+T("تم تنزيل ملف الشهادة ("+fname(r)+") وفتح واتساب — أرفق الملف المنزَّل في المحادثة (📎 أو اسحبه) ثم اضغط إرسال.","Certificate downloaded ("+fname(r)+") and WhatsApp opened — attach the downloaded file in the chat, then send.");$("csm-ht").hidden=false};
 return m}
function save(b,n){const a=document.createElement("a");a.href=URL.createObjectURL(b);a.download=n;document.body.appendChild(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove()},1500)}
function close(){const m=document.getElementById("csm");if(m)m.hidden=true;CUR=null;PDF=null}
function open(r){const m=modal(),$=id=>document.getElementById(id);CUR=r;PDF=null;
 $("csm-h").textContent="📲 "+T("إرسال شهادة الحضور واتساب إلى","Send attendance certificate via WhatsApp to")+" "+(r.name||"");
 $("csm-lp").textContent=T("جوال المتدرب","Trainee mobile");$("csm-lx").textContent=T("نص الرسالة (قابل للتعديل)","Message (editable)");
 $("csm-go").textContent="⏳ "+T("جارٍ تجهيز الشهادة…","Preparing certificate…");$("csm-dl").textContent="📄 "+T("تنزيل PDF","Download PDF");$("csm-cp").textContent="📋 "+T("نسخ النص","Copy text");$("csm-c").textContent=T("إغلاق","Close");
 $("csm-ht").hidden=true;$("csm-pv").textContent=T("جارٍ إنشاء معاينة الشهادة…","Rendering certificate preview…");$("csm-p").value=phone(r.mobile);$("csm-x").value=text(r);$("csm-x").dir=r.lang==="en"?"ltr":"rtl";m.hidden=false;m._cnt();
 render(r).then(o=>{if(CUR!==r)return;PDF=o.blob;$("csm-pv").innerHTML='<img alt="certificate preview" src="'+o.img+'">';$("csm-go").textContent="📲 "+T("إرسال الشهادة + الرسالة واتساب","Send certificate + message on WhatsApp");m._cnt()},
  ()=>{if(CUR!==r)return;$("csm-pv").textContent="⚠ "+T("تعذّر إنشاء ملف PDF","Could not create the PDF");$("csm-go").textContent="⚠ PDF"})}
window.CRSEND={open,text,render,html,phone};
})();
