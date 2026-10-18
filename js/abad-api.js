/* ABAD backend client (PHP + MySQL on the Cloudways deployment).
   Degrades gracefully: on GitHub Pages / file:// / any host without the PHP API every call resolves to null
   and pages keep their existing static behaviour. Never throws into page code. */
(function(){
  if(window.ABAD_API)return;
  var cs=document.currentScript&&document.currentScript.src;
  var BASE=cs?cs.replace(/js\/abad-api\.js(\?.*)?$/,""):new URL(".",location.href).href;
  var OFF=location.protocol==="file:"||/(^|\.)github\.io$/.test(location.hostname);
  var S={available:false,csrf:null,user:null},meP=null;
  function req(path,o){o=o||{};var h={"Accept":"application/json"};if(o.body!==undefined)h["Content-Type"]="application/json";if(S.csrf)h["X-CSRF-Token"]=S.csrf;
    var ac=window.AbortController&&!o.keepalive?new AbortController():null,tm=ac?setTimeout(function(){ac.abort()},o.timeout||12000):0;
    return fetch(BASE+"api/"+path,{method:o.method||"GET",headers:h,credentials:"same-origin",cache:"no-store",keepalive:!!o.keepalive,signal:ac?ac.signal:undefined,
      body:o.body!==undefined?JSON.stringify(o.body):undefined})
      .then(function(r){clearTimeout(tm);var ct=r.headers.get("content-type")||"";if(ct.indexOf("application/json")<0)throw new Error("noapi");
        return r.json().then(function(j){j=j||{};j.status=r.status;return j})},function(e){clearTimeout(tm);throw e})}
  function emit(){try{document.dispatchEvent(new CustomEvent("abad-auth",{detail:S}))}catch(e){}}
  /** Resolves to state {available,csrf,user} or null when there is no backend. */
  function me(force){if(OFF)return Promise.resolve(null);if(meP&&!force)return meP;
    meP=req("auth.php?action=me").then(function(j){if(!j.ok||!j.api)throw 0;S.available=true;S.csrf=j.csrf;S.user=j.user||null;emit();return S})
      .catch(function(){S.available=false;S.user=null;return null});return meP}
  function authCall(action,body){return me().then(function(s){if(!s)return {ok:false,error:"unavailable"};
    body=Object.assign({},body||{},{action:action});
    return req("auth.php",{method:"POST",body:body}).then(function(j){if(j.ok){if(j.csrf)S.csrf=j.csrf;S.user=action==="logout"?null:(j.user||null);emit()}return j})})
    .catch(function(){return {ok:false,error:"network"}})}
  /** Silent, fire-and-forget exam result. o = {exam_id, score, total, domains?, duration_sec?} */
  function result(o){try{return me().then(function(s){if(!s)return null;
      return req("results.php",{method:"POST",keepalive:true,body:Object.assign({page:location.pathname.split("/").pop()},o)})}).catch(function(){return null})}catch(e){return Promise.resolve(null)}}
  /** Store a booking (booking.html). Resolves {ok,ref} or null when unavailable / failed. */
  function booking(d){return me().then(function(s){if(!s)return null;return req("bookings.php",{method:"POST",body:d,timeout:15000})})
    .then(function(j){return j&&j.ok?j:null}).catch(function(){return null})}
  window.ABAD_API={base:BASE,state:S,me:me,req:req,result:result,booking:booking,
    login:function(email,password){return authCall("login",{email:email,password:password})},
    register:function(d){return authCall("register",d)},
    logout:function(){return authCall("logout",{})}};
  /* results queued by exam pages before this script loaded */
  var q=window.__abadRQ;window.__abadRQ=null;window.abadResult=result;if(q&&q.length)q.forEach(result);
})();
