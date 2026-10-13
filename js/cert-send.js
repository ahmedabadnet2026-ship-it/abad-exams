/* ABAD — إرسال شهادة الحضور عبر واتساب (PDF يُنشأ في المتصفح: html2canvas + jsPDF من assets/abad-pdf-libs.js) */
(function(){
const T=(a,b)=>typeof tt==="function"?tt(a,b):a, say=m=>typeof toast==="function"?toast(m):0;
const C=window.ABADCERT,render=C.render,save=C.save,fname=C.fname;
function phone(s){let d=String(s||"").replace(/[^\d]/g,"").replace(/^00/,"");if(/^9660/.test(d))d="966"+d.slice(4);if(/^05\d{8}$/.test(d))d="966"+d.slice(1);else if(/^5\d{8}$/.test(d))d="966"+d;return d}
const okPhone=d=>/^9665\d{8}$/.test(d);
const MAPS="https://maps.app.goo.gl/bKxZCp2Tsu7Kyneu6";
function text(r,url){const n=String(r.name||"").trim(),c=String(r.course||"").trim();
 if(r.lang==="en")return "Assalamu alaikum "+n+" 🌷\n\nCongratulations on successfully completing the \""+c+"\" course at ABAD Network Training Institute 🎉 You truly earned it!\n\n📄 Your certificate is ready here: "+(url||"…")+"\n(Ref: "+r.id+")\nKeep going — onwards and upwards 🚀\n\nWe'd love your feedback on Google Maps ⭐\n"+MAPS+"\n\nKind regards,\nABAD Network Training Institute\n📍 Riyadh | 🌐 abadnet.com.sa | 📞 +966 56 139 3960";
 return "السلام عليكم ورحمة الله وبركاته،\nأستاذ/ة "+n+"، مبارك عليك الإنجاز والله يوفقك 🎉\n\nيسعدنا في معهد شبكة آباد للتدريب أن نهنّئك بإتمام دورة «"+c+"» بنجاح، وهذا الإنجاز تستاهله عن جدارة 👏\n\n📄 شهادتك جاهزة هنا: "+(url||"…")+"\n(رقم المرجع: "+r.id+")\nنتمنى لك دوام التوفيق، واستمر على هالمستوى المميز… ومنها للأعلى 🚀\n\nويسعدنا نسمع رأيك في تجربتك معنا بتقييم المعهد على خرائط Google ⭐\n"+MAPS+"\n\nمع خالص التحية والتقدير،\nمعهد شبكة آباد للتدريب\n📍 الرياض | 🌐 abadnet.com.sa | 📞 الدعم: 0561393960"}
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
function close(){const m=document.getElementById("csm");if(m)m.hidden=true;CUR=null;PDF=null}
function open(r0){const r=Object.assign({},r0,{issued:r0.issued||new Date().toLocaleDateString("en-CA",{timeZone:"Asia/Riyadh"})});const m=modal(),$=id=>document.getElementById(id);CUR=r;PDF=null;
 $("csm-h").textContent="📲 "+T("إرسال شهادة الحضور واتساب إلى","Send attendance certificate via WhatsApp to")+" "+(r.name||"");
 $("csm-lp").textContent=T("جوال المتدرب","Trainee mobile");$("csm-lx").textContent=T("نص الرسالة (قابل للتعديل)","Message (editable)");
 $("csm-go").textContent="⏳ "+T("جارٍ تجهيز الشهادة…","Preparing certificate…");$("csm-dl").textContent="📄 "+T("تنزيل PDF","Download PDF");$("csm-cp").textContent="📋 "+T("نسخ النص","Copy text");$("csm-c").textContent=T("إغلاق","Close");
 $("csm-ht").hidden=true;$("csm-pv").textContent=T("جارٍ إنشاء معاينة الشهادة…","Rendering certificate preview…");$("csm-p").value=phone(r.mobile);$("csm-x").value=text(r);C.link(r).then(u=>{if(CUR===r){$("csm-x").value=text(r,u);m._cnt()}});$("csm-x").dir=r.lang==="en"?"ltr":"rtl";m.hidden=false;m._cnt();
 render(r).then(o=>{if(CUR!==r)return;PDF=o.blob;$("csm-pv").innerHTML='<img alt="certificate preview" src="'+o.img+'">';$("csm-go").textContent="📲 "+T("إرسال الشهادة + الرسالة واتساب","Send certificate + message on WhatsApp");m._cnt()},
  ()=>{if(CUR!==r)return;$("csm-pv").textContent="⚠ "+T("تعذّر إنشاء ملف PDF","Could not create the PDF");$("csm-go").textContent="⚠ PDF"})}
window.CRSEND={open,text,phone};
})();
