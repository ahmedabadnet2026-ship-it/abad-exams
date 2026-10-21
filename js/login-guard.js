/* ABAD login guard (client mirror of the server gate in api/page.php): every page except login.html requires a session.
   Hides the page until auth.php confirms a signed-in user; signed out → login.html?next=<this page>. Re-checks on bfcache restore.
   Network/API failure fails open here on purpose — the server gate (.htaccess → api/page.php) is the real enforcement. */
(function(){
var pg=(location.pathname.split("/").pop()||"index.html");if(/^login\.html$/i.test(pg))return;
if(!/\.html$/i.test(pg))pg="index.html";
var de=document.documentElement;de.style.visibility="hidden";
function show(){de.style.visibility=""}
function out(){location.replace("login.html?next="+encodeURIComponent(pg+location.search))}
function check(){var x;try{x=fetch("api/auth.php?action=me",{credentials:"same-origin",cache:"no-store",headers:{Accept:"application/json"}})}catch(e){show();return}
 x.then(function(r){return r.json()}).then(function(j){if(j&&j.ok&&!j.user){out();return}window.ABAD_OPEN_ALL=!!(j&&j.open_all);show()}).catch(show)}
check();window.addEventListener("pageshow",function(e){if(e.persisted){de.style.visibility="hidden";check()}});
})();
