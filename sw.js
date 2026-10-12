/* ABAD exams service worker — HTML network-first, assets cache-first */
const V="abad-202610122309",PRE=["./","index.html","platform.html","vendor.html","exam.html","booking.html","itil5-exam.html","pmp-exam.html","manifest.webmanifest","icons/icon-192.png","icons/icon-512.png","icons/maskable-192.png","icons/maskable-512.png","icons/apple-touch-icon.png"];
self.addEventListener("install",e=>{e.waitUntil(caches.open(V).then(c=>c.addAll(PRE.map(u=>new Request(u,{cache:"reload"})))).then(()=>self.skipWaiting()))});
self.addEventListener("activate",e=>{e.waitUntil(caches.keys().then(ks=>Promise.all(ks.filter(k=>k.startsWith("abad-")&&k!==V).map(k=>caches.delete(k)))).then(()=>self.clients.claim()))});
self.addEventListener("fetch",e=>{const r=e.request;if(r.method!=="GET")return;const u=new URL(r.url);if(u.origin!==location.origin)return;
 const html=r.mode==="navigate"||/\.html$|\/$/.test(u.pathname);
 if(html){e.respondWith(fetch(r,{cache:"no-store"}).then(res=>{if(res.ok){const k=new Request(u.origin+u.pathname);const cp=res.clone();caches.open(V).then(c=>c.put(k,cp))}return res})
   .catch(()=>caches.match(r,{ignoreSearch:true}).then(m=>m||caches.match(u.pathname.endsWith("/")?"./":u.pathname,{ignoreSearch:true})).then(m=>m||caches.match("index.html"))));return}
 e.respondWith(caches.match(r,{ignoreSearch:true}).then(m=>m||fetch(r).then(res=>{if(res.ok){const cp=res.clone();caches.open(V).then(c=>c.put(r,cp))}return res})))});
