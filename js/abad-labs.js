/* ABAD lab control: hide disabled labs + send open ping. Silent no-op without PHP (GitHub Pages). */
(function(){
var MAP={"linux-real-lab.html":"linux-real","rhcsa-lab.html":"rhcsa","ccna-lab.html":"ccna","ine-lab.html":"ine","net-lab.html":"net","eve-lab-booking.html":"eve"};
var base=(document.currentScript&&document.currentScript.src||location.href).replace(/js\/abad-labs\.js.*$/,"");
var API=base+"api/labs.php";
function idOf(href){try{var p=new URL(href,location.href).pathname.split("/").pop();return MAP[p]}catch(e){}}
function ping(id){try{if(location.protocol==="file:")return;var f=new FormData();f.append("action","open");f.append("id",id);
 if(navigator.sendBeacon&&navigator.sendBeacon(API,f))return;fetch(API,{method:"POST",body:f,keepalive:true}).catch(function(){})}catch(e){}}
var self=idOf(location.href);if(self)ping(self);
function apply(){var cards=document.querySelectorAll("a[href]");if(!cards.length)return;
 fetch(API,{cache:"no-store",credentials:"same-origin"}).then(function(r){if(!r.ok||!/json/.test(r.headers.get("content-type")||""))throw 0;return r.json()}).then(function(d){
  if(!d||!d.labs)return;cards.forEach(function(a){if(!a.classList.contains("cd")&&!a.hasAttribute("data-lab"))return;var id=a.getAttribute("data-lab")||idOf(a.getAttribute("href"));
   if(id&&d.labs[id]&&d.labs[id].visible===false){a.hidden=true;a.style.display="none"}})}).catch(function(){})}
if(document.querySelector("a.cd,[data-lab]")){if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",apply);else apply()}
})();
