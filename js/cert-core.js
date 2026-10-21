/* ABAD — قالب شهادة الحضور المشترك (لوحة التحكم + certificate-view.html) */
(function(){
const BASE=(document.currentScript&&document.currentScript.src||location.href).replace(/js\/[^\/]*$/,"").replace(/[^\/]*$/,"");
const e=s=>String(s==null?"":s).replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[c]);
let LIBS=null;
function libs(){if(window.jspdf&&window.html2canvas&&window.qrcode)return Promise.resolve();if(LIBS)return LIBS;
 LIBS=new Promise((res,rej)=>{const s=document.createElement("script");s.src=BASE+"assets/abad-pdf-libs.js";s.onload=res;s.onerror=()=>{LIBS=null;rej(new Error("pdf_libs"))};document.head.appendChild(s)});return LIBS}
function fd(s,en){const d=new Date(String(s||"").slice(0,10)+"T12:00:00");return isNaN(d)?String(s||"—"):d.toLocaleDateString(en?"en-GB":"ar-SA-u-ca-gregory-nu-latn",{day:"numeric",month:"long",year:"numeric"})}
const fname=r=>"ABAD-Certificate-"+String(r.id||"").replace(/[^\w-]/g,"")+".pdf";
function fontCSS(){let t="";if(!document.querySelector("style")||![...document.querySelectorAll("style")].some(s=>/@font-face/.test(s.textContent)))return "@font-face{font-family:Cairo;src:url('"+new URL(BASE+"assets/cairo.ttf",location.href).href+"') format('truetype')}";for(const s of document.querySelectorAll("style"))if(/@font-face/.test(s.textContent))t+=(s.textContent.match(/@font-face\s*{[^}]*}/g)||[]).join("\n");return t}
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
function html(r){const logo=(document.querySelector("header img[src^='data:']")||{}).src||new URL(BASE+"assets/abad-logo.png",location.href).href;let qr="";try{const q=qrcode(0,"M");q.addData("ABAD-CERT "+(r.id||""));q.make();qr=q.createDataURL(6,1)}catch(_){}
 const now=r.issued?fd(r.issued):new Date().toLocaleDateString("ar-SA-u-ca-gregory-nu-latn",{timeZone:"Asia/Riyadh",day:"numeric",month:"long",year:"numeric"});
 return '<div class="ct"><div class="c1"></div><div class="c2"></div><div class="o"></div><div class="i"></div>'+(logo?'<img class="wm" src="'+logo+'">':'')+'<div class="b">'+
  (logo?'<img class="lg" src="'+logo+'">':'')+'<h1>شهادة حضور</h1><div class="en">CERTIFICATE OF ATTENDANCE</div><div class="gl"></div>'+
  '<div class="p">يشهد معهد شبكة آباد للتدريب بأن المتدرب/ة</div><div class="nm">'+e(r.name)+'</div>'+
  (r.national_id?'<div class="dt" style="margin-bottom:4px">رقم الهوية / الإقامة · ID: <b dir="ltr">'+e(r.national_id)+'</b></div>':'')+
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

function save(b,n){const a=document.createElement("a");a.href=URL.createObjectURL(b);a.download=n;document.body.appendChild(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove()},1500)}
/* رابط الشهادة: البيانات في الـ hash (لا تُرسل للخادم) + بصمة SHA-256 مختصرة لكشف التعديل العرضي */
const SALT="ABAD-CERT-v1|";
const b64u={enc:s=>btoa(String.fromCharCode(...new TextEncoder().encode(s))).replace(/\+/g,"-").replace(/\//g,"_").replace(/=+$/,""),
 dec:s=>new TextDecoder().decode(Uint8Array.from(atob(s.replace(/-/g,"+").replace(/_/g,"/")),c=>c.charCodeAt(0)))};
async function sig(j){if(!(window.crypto&&crypto.subtle))return "";const h=await crypto.subtle.digest("SHA-256",new TextEncoder().encode(SALT+j));return [...new Uint8Array(h)].slice(0,6).map(x=>x.toString(16).padStart(2,"0")).join("")}
const KEYS={n:"name",d:"national_id",c:"course",s:"start",e:"end",t:"training_type",r:"id",i:"issued",l:"lang"};
function today(){return new Date().toLocaleDateString("en-CA",{timeZone:"Asia/Riyadh"})}
async function link(r){const o={};for(const k in KEYS){const v=k==="i"?(r.issued||today()):r[KEYS[k]];if(v!=null&&v!=="")o[k]=String(v)}
 const j=JSON.stringify(o);return new URL(BASE+"certificate-view.html",location.href).href+"#"+b64u.enc(j)+"."+await sig(j)}
async function parse(hash){const h=String(hash||"").replace(/^#/,""),i=h.lastIndexOf(".");if(i<1)throw new Error("bad");const j=b64u.dec(h.slice(0,i)),o=JSON.parse(j),r={};
 for(const k in KEYS)if(o[k]!=null)r[KEYS[k]]=o[k];const s=await sig(j);return{r,ok:!!s&&s===h.slice(i+1),checked:!!s}}
window.ABADCERT={libs,render,html,fname,fd,save,link,parse};
})();
