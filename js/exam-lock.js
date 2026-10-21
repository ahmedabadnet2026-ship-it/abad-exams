/* ABAD exam lock (public copy): first exam free trial, others locked; sign-in uses the single platform login (login.html). */
(function(){
/* Prefill booking form with this exam's vendor/cert (booking.html reads #v=&c=). */
var MAP={'aplus-core1-exam':'comptia&c=aplus','aplus-core2-exam':'comptia&c=aplus','linux-plus-exam':'comptia&c=linux','network-plus-exam':'comptia&c=network','security-plus-exam':'comptia&c=security','pmp-exam':'pmi&c=pmp','itil5-exam':'peoplecert&c=itil5','ccna-exam':'cisco&c=ccna','cobit-exam':'isaca&c=cobit','cysa-exam':'comptia&c=cysa','server-plus-exam':'comptia&c=server','data-plus-exam':'comptia&c=data','pl300-exam':'microsoft&c=pl300','ceh-exam':'eccouncil&c=ceh','mos-word-exam':'microsoft&c=mos-word','mos-excel-exam':'microsoft&c=mos-excel','mos-ppt-exam':'microsoft&c=mos-ppt'};
var pg=(location.pathname.split('/').pop()||'').replace(/\.html$/,'');var BK='booking.html'+(MAP[pg]?'#v='+MAP[pg]:'');
var CSS='.el-free{display:inline-block;margin:4px 6px;font-size:12px;font-weight:800;border-radius:99px;padding:1px 10px;background:#F5BD2D;color:#10293b;vertical-align:middle}'+
'.el-locked{opacity:.75;background:#f4f6f9!important;position:relative}.el-lockb{font-size:18px;margin-inline-start:6px}'+
'#el-modal{position:fixed;inset:0;z-index:300;display:none;align-items:center;justify-content:center;background:rgba(16,41,59,.55);padding:16px;font-family:Cairo,system-ui,sans-serif}'+
'#el-modal.on{display:flex}#el-modal .lk{background:#fff;border-radius:18px;max-width:400px;width:100%;padding:22px;border-top:6px solid #F5BD2D;text-align:center;color:#10293b;position:relative}'+
'#el-modal h3{margin:6px 0 12px;font-size:19px}#el-modal input{width:100%;box-sizing:border-box;margin:5px 0;padding:10px;border:1px solid #dbe5ee;border-radius:10px;font-family:inherit;background:#f4f6f9}'+
'#el-modal .note{font-size:13px;color:#3f86b3;font-weight:800;margin:6px 0 12px}#el-modal .bk{display:block;background:#10293b;color:#fff;text-decoration:none;border-radius:12px;padding:11px;font-weight:800}'+
'#el-modal .lk-x{position:absolute;top:4px;inset-inline-end:6px;min-width:44px;min-height:44px;border:0;background:none;font-size:22px;cursor:pointer;color:#10293b}';
var HTML='<div class="lk"><button type="button" class="lk-x" aria-label="إغلاق">×</button><div style="font-size:34px">🔒</div>'+
'<h3 id="el-t">سجّل دخولك لفتح اختباراتك</h3><div class="note">حساب واحد على منصة آباد — تُفتح الاختبارات التي حجزتها من خلال المعهد تلقائيًا بعد تسجيل الدخول.</div>'+
'<a class="bk" style="background:#3f86b3;margin-bottom:8px" href="login.html?next='+encodeURIComponent(pg+'.html')+'">تسجيل الدخول</a>'+
'<a class="bk" href="'+BK+'">احجز الآن عبر صفحة الحجز</a></div>';
function ensure(){var m=document.getElementById('el-modal');if(m)return m;
 var st=document.createElement('style');st.textContent=CSS;document.head.appendChild(st);
 m=document.createElement('div');m.id='el-modal';m.setAttribute('role','dialog');m.setAttribute('aria-modal','true');m.setAttribute('aria-labelledby','el-t');m.innerHTML=HTML;
 m.onclick=function(e){if(e.target===m)close();};m.querySelector('.lk-x').onclick=close;document.body.appendChild(m);
 document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});return m;}
function open(){var m=ensure();m.classList.add('on');m.querySelector('.lk-x').focus();}
function close(){var m=document.getElementById('el-modal');if(m)m.classList.remove('on');}
function decorate(tiles){if(window.ABAD_EXAM_ENTITLED)return;ensure();Array.prototype.forEach.call(tiles,function(t,k){if(t.dataset.el)return;t.dataset.el='1';var h=t.querySelector('h3');
 if(k===0){if(h)h.insertAdjacentHTML('beforeend','<span class="el-free">تجربة مجانية</span>');return;}
 t.classList.add('el-locked');if(h)h.insertAdjacentHTML('beforeend','<span class="el-lockb" aria-label="مقفل">🔒</span>');
 t.querySelectorAll('button').forEach(function(b){b.disabled=false;b.removeAttribute('onclick');b.textContent='🔒 '+b.textContent.trim();b.onclick=function(e){e.preventDefault();open();};});});}
/* For the shared simulator template (S, EXAMS, begin, resumeRun, home, save, app as globals). */
function runTemplate(){
 if(window.ABAD_EXAM_ENTITLED)return; /* served by api/exam.php to an entitled trainee: every practice exam is open */
 if(typeof EXAMS==='undefined'||EXAMS.length<2)return;
 var cleared=false;if(S.run&&S.run.exam!==0){S.run=null;S.exam=0;save();cleared=true;}
 var ob=begin;begin=function(i){if(i!==0){open();return;}return ob(i);};
 var orr=resumeRun;resumeRun=function(){if(S.run&&S.run.exam!==0){S.run=null;save();home();open();return;}return orr();};
 var oh=home;home=function(){var r=oh.apply(this,arguments);decorate(app.querySelectorAll('.exam-tile'));return r;};
 if(cleared)home();else decorate(app.querySelectorAll('.exam-tile'));
}
window.ExamLock={open:open,close:close,decorate:decorate,runTemplate:runTemplate};
})();
