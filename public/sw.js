const CACHE='vital-dent-server-shell-v5';
const ASSETS=['./','./index.html','./styles.css','./app.js','./manifest.json','./icons/icon.svg','./icons/icon-192.png','./icons/icon-512.png'];
self.addEventListener('install',e=>e.waitUntil(caches.open(CACHE).then(c=>c.addAll(ASSETS))));
self.addEventListener('activate',e=>e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('vital-dent-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim())));
self.addEventListener('fetch',e=>{
 const url=new URL(e.request.url);
 // API, records, authentication and attachments are NEVER cached.
 if(e.request.method!=='GET'||url.origin!==self.location.origin||url.pathname.includes('/api/'))return;
 const allowed=ASSETS.map(p=>new URL(p,self.registration.scope).pathname);
 if(e.request.mode!=='navigate'&&!allowed.includes(url.pathname))return;
 e.respondWith(caches.open(CACHE).then(async c=>{try{return await fetch(e.request);}catch{return await c.match(e.request,{ignoreSearch:true})||await c.match('./index.html');}}));
});
