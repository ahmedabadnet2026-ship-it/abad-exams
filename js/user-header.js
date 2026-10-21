/* ABAD signed-in trainee header bar (uses ABAD_API.me / ABAD_API.logout from js/abad-api.js, CSRF handled there). */
(function(){if(window.__abadUH)return;window.__abadUH=1;
var EN=document.documentElement.lang==="en",T=function(a,e){return EN?e:a};
function esc(s){return String(s==null?"":s).replace(/[&<>"']/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]})}
var css=".abad-uh{position:fixed;top:0;inset-inline:0;z-index:70;height:56px;display:flex;align-items:center;gap:10px;padding:0 16px;font-family:Cairo,system-ui,sans-serif;color:#fff;background:linear-gradient(180deg,#0a1c29d9,#10293bb3);backdrop-filter:blur(12px) saturate(140%);-webkit-backdrop-filter:blur(12px) saturate(140%);border-bottom:1px solid #ffffff1f;box-shadow:0 6px 24px #0004}"+
".abad-uh .uh-sp{flex:1}.abad-uh .uh-brand{font-weight:800;font-size:.95rem;color:#fff;text-decoration:none;white-space:nowrap}.abad-uh .uh-brand b{color:#F5BD2D}"+
".abad-uh .uh-user{display:inline-flex;align-items:center;gap:8px;color:#fff;text-decoration:none;font-weight:700;font-size:.92rem;padding:4px 10px 4px 4px;border-radius:999px;background:#ffffff12;border:1px solid #ffffff22;max-width:46vw;transition:.2s}html[dir=rtl] .abad-uh .uh-user{padding:4px 4px 4px 12px}.abad-uh .uh-user:hover{background:#ffffff24}"+
".abad-uh .uh-av{flex:none;width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,#3f86b3,#1d5479);color:#fff;font-weight:800;font-size:.9rem;border:2px solid #F5BD2D}"+
".abad-uh .uh-nm{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}"+
".abad-uh .uh-btn{font:inherit;font-weight:700;font-size:.85rem;cursor:pointer;border-radius:999px;padding:7px 16px;white-space:nowrap;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:.2s}"+
".abad-uh .uh-out{background:transparent;color:#fff;border:1.5px solid #ffffff55}.abad-uh .uh-out:hover{background:#fff;color:#10293b}"+
".abad-uh .uh-in{background:#F5BD2D;color:#10293b;border:0}.abad-uh .uh-in:hover{background:#ffd056}"+
".abad-uh .langbtn{position:static!important;margin:0!important;background:#ffffff1a;color:#fff;border:1.5px solid #ffffff55;padding:5px 12px!important;border-radius:999px}.abad-uh .langbtn span{background:#F5BD2D;color:#10293b;border-radius:999px;padding:0 6px}.abad-uh .langbtn:hover{background:#fff;color:#10293b}"+
"body.has-uh{padding-top:56px}"+
"@media(max-width:600px){.abad-uh{height:52px;padding:0 10px;gap:6px}body.has-uh{padding-top:52px}.abad-uh .uh-brand{display:none}.abad-uh .uh-btn{padding:6px 11px;font-size:.8rem}.abad-uh .langbtn i{display:none}.abad-uh .uh-user{max-width:40vw}}";
function build(){var st=document.createElement("style");st.textContent=css;document.head.appendChild(st);
 var bar=document.createElement("header");bar.className="abad-uh";bar.setAttribute("role","banner");
 bar.innerHTML='<a class="uh-brand" href="index.html">'+T("منصة <b>آباد</b> الرقمية","<b>ABAD</b> Digital Platform")+'</a><span class="uh-sp"></span><span class="uh-lang"></span><span class="uh-acct"></span>';
 document.body.insertBefore(bar,document.body.firstChild);document.body.classList.add("has-uh");
 grabLang(bar);if(!bar.querySelector(".langbtn")){var n=0,iv=setInterval(function(){if(grabLang(bar)||++n>40)clearInterval(iv)},100)}
 return bar}
function grabLang(bar){var b=document.querySelector(".langbtn.float");if(!b)return false;b.classList.remove("float");bar.querySelector(".uh-lang").appendChild(b);return true}
function render(bar,u){var a=bar.querySelector(".uh-acct");
 if(u){var full=u.name||u.full_name||u.first_name||(u.email||"").split("@")[0]||"",first=String(full).trim().split(/\s+/)[0]||full,ini=(first.charAt(0)||"؟").toUpperCase();
  a.innerHTML='<span style="display:inline-flex;gap:8px;align-items:center"><a class="uh-user" href="my-services.html" title="'+esc(T("خدماتي","My services"))+'"><span class="uh-av" aria-hidden="true">'+esc(ini)+'</span><span class="uh-nm">'+esc(T("مرحبًا، ","Hi, ")+first)+'</span></a><button type="button" class="uh-btn uh-out">'+T("تسجيل الخروج","Log out")+'</button></span>';
  a.querySelector(".uh-out").onclick=function(){this.disabled=true;window.ABAD_API.logout().then(function(){render(bar,null)},function(){render(bar,null)})}}
 else{a.innerHTML='<a class="uh-btn uh-in" href="login.html">'+T("تسجيل الدخول","Sign in")+'</a>'}}
function init(){var bar=build();if(!window.ABAD_API){render(bar,null);return}
 window.ABAD_API.me().then(function(s){render(bar,s&&s.user||null)},function(){render(bar,null)})}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init);else init();
})();
