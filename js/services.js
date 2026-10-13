(function(){
var EN=(window.LANG||document.documentElement.lang)==="en",R="?r=202610131456";
var S=document.getElementById("services");if(!S)return;
var I={
exam:'<rect class="d b" x="5" y="3" width="14" height="18" rx="2" style="--len:70"/><path class="d" d="M9 8h6M9 12h6M9 16h3" style="--len:20"/><circle class="p" cx="17" cy="17" r="0"/>',
chart:'<path class="d b" d="M4 20h16" style="--len:20"/><path class="d" d="M7 16v-4M12 16V7M17 16v-7" style="--len:20"/><path class="d" d="M6 9l5-4 6 3" style="--len:30"/>',
lab:'<rect class="d b" x="3" y="4" width="18" height="12" rx="2" style="--len:70"/><path class="d" d="M8 20h8M12 16v4M7 9l2 2-2 2M11 13h4" style="--len:30"/>',
book:'<rect class="d b" x="3" y="5" width="18" height="16" rx="2" style="--len:70"/><path class="d" d="M3 10h18M8 3v4M16 3v4" style="--len:30"/><path class="d" d="M9 15l2 2 4-4" style="--len:15"/>',
cert:'<rect class="d b" x="3" y="4" width="18" height="12" rx="2" style="--len:70"/><circle class="d p" cx="12" cy="16" r="3" style="--len:20"/><path class="d" d="M10 19l-1 3 3-1 3 1-1-3M7 8h10" style="--len:30"/>',
lang:'<circle class="d b" cx="12" cy="12" r="9" style="--len:60"/><path class="d" d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18" style="--len:60"/>',
app:'<rect class="d b" x="7" y="2" width="10" height="20" rx="2" style="--len:70"/><path class="d" d="M12 7v7M9 11l3 3 3-3" style="--len:20"/><circle class="p" cx="12" cy="19" r=".6"/>'};
var C=[
["exam","platform.html"+R,"محاكيات اختبارات لـ 20 جهة مانحة","Exam simulators for 20 vendors","اختبارات تفاعلية تحاكي الامتحانات الرسمية لشهادات Cisco، CompTIA، Microsoft، AWS، Red Hat وغيرها.","Interactive exams that mirror official tests for Cisco, CompTIA, Microsoft, AWS, Red Hat and more."],
["chart",null,"تحليل الأداء حسب المحاور","Per-domain performance analysis","بعد كل اختبار تعرف نقاط قوتك وضعفك في كل محور من محاور الشهادة.","After each exam, see your strengths and weak spots in every exam domain."],
["lab","ccna-lab-demo.html"+R,"معامل عملية","Hands-on labs","طبّق عملياً في معمل CCNA تجريبي داخل المتصفح.","Practice for real in an in-browser CCNA demo lab."],
["book","booking.html"+R,"حجز الاختبارات الدولية","Book international exams","احجز موعد اختبارك الدولي الرسمي عن طريق المعهد بسهولة.","Book your official international exam through the institute."],
["cert","certificate.html"+R,"طلب شهادة حضور","Attendance certificates","اطلب شهادة حضور دورتك مع تقييم الدورة.","Request your course attendance certificate along with a course evaluation."],
["lang",null,"عربي / English","Arabic / English","المنصة كاملة بالعربية والإنجليزية، بدّل اللغة بضغطة واحدة.","The whole platform in Arabic and English — switch with one tap."],
["app",null,"ثبّتها كتطبيق","Install as an app","أضف المنصة لشاشة جوالك أو جهازك وافتحها كتطبيق (PWA).","Add the platform to your phone or desktop home screen and open it like an app (PWA)."]];
var t=function(a,e){return EN?e:a};
var h='<div class="svc-head"><small>'+t("خدمات المنصة","Platform services")+'</small><h2>'+t("كل ما تحتاجه للاستعداد في مكان واحد","Everything you need to prepare, in one place")+'</h2><p>'+t("من التدريب والمحاكاة حتى حجز الاختبار الرسمي وشهادة الحضور.","From practice and simulation to booking the official exam and getting your certificate.")+'</p></div>';
h+='<div class="svc-stats"><div class="svc-stat"><b data-n="20">0</b><span>'+t("جهة مانحة","vendors")+'</span></div><div class="svc-stat"><b data-n="2">0</b><span>'+t("لغتان","languages")+'</span></div></div><div class="svc-grid">';
C.forEach(function(c){var tag=c[1]?'a href="'+c[1]+'"':'div';
 h+='<'+tag+' class="svc-card"><div class="svc-ico" aria-hidden="true"><svg viewBox="0 0 24 24">'+I[c[0]]+'</svg></div><h3>'+t(c[2],c[3])+'</h3><p>'+t(c[4],c[5])+'</p>'+(c[1]?'<span class="more">'+t("افتح ←","Open →")+'</span>':'')+'</'+(c[1]?'a':'div')+'>'});
S.innerHTML=h+'</div>';
var RM=matchMedia("(prefers-reduced-motion: reduce)").matches;
function count(b){var n=+b.dataset.n;if(RM){b.textContent=n;return}var s=null;function f(ts){s=s||ts;var p=Math.min((ts-s)/1200,1);b.textContent=Math.round(n*(1-Math.pow(1-p,3)));if(p<1)requestAnimationFrame(f)}requestAnimationFrame(f)}
var els=S.querySelectorAll(".svc-head,.svc-stat,.svc-card");
if(RM||!("IntersectionObserver" in window)){els.forEach(function(e){e.classList.add("shown")});S.querySelectorAll("[data-n]").forEach(function(b){b.textContent=b.dataset.n});return}
S.classList.add("js");
var io=new IntersectionObserver(function(en){var k=0;en.forEach(function(e){if(!e.isIntersecting)return;var el=e.target;io.unobserve(el);
 setTimeout(function(){el.classList.add("shown");var b=el.querySelector("[data-n]");if(b)count(b)},k++*120)})},{threshold:.15,rootMargin:"0px 0px -40px 0px"});
els.forEach(function(e){io.observe(e)});
})();
