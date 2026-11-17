const {request}=require('playwright');
const {execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const base='http://localhost:8090/api/index.php?action=';
const allowed='http://localhost:8090';
(async()=>{
 process.stdout.write(execFileSync(process.env.PHP_BIN||'php',['tests/origin.php'],{encoding:'utf8'}));
 const c=await request.newContext();
 for(const headers of [{Origin:'http://LOCALHOST:8090'},{Origin:allowed},{Origin:'null',Referer:allowed+'/?from=home','Sec-Fetch-Site':'same-origin'},{Referer:allowed+'/index.html'},{}]){
  const r=await c.get(base+'status',{headers});assert.equal(r.status(),200,await r.text());
 }
 for(const headers of [{Origin:'https://evil.example'},{Origin:'http://localhost:8091'},{Origin:'null'},{Origin:'null',Referer:'https://evil.example/'},{Origin:allowed,'Sec-Fetch-Site':'cross-site'},{Referer:'https://evil.example/'},{Host:'evil.example',Origin:'https://evil.example'}]){
  const r=await c.get(base+'status',{headers});assert.equal(r.status(),403);const error=await r.json();assert.equal(error.code,'origin_not_allowed');assert.ok(error.error.includes(allowed));
 }
 const s=await(await c.get(base+'status')).json();
 const missingCsrf=await c.post(base+'login',{headers:{Origin:'null',Referer:allowed+'/'},data:{username:'karen.frutos',password:'final-test-password-789'}});assert.equal(missingCsrf.status(),403);
 const login=await c.post(base+'login',{headers:{Origin:'null',Referer:allowed+'/index.html','Sec-Fetch-Site':'same-origin','X-CSRF-Token':s.csrf},data:{username:'karen.frutos',password:'final-test-password-789',remember:true}});assert.equal(login.status(),200,await login.text());
 assert.equal((await c.get(base+'clinic',{headers:{Origin:'http://LOCALHOST:8090'}})).status(),200);
 assert.equal((await c.get(base+'clinic',{headers:{Origin:'https://evil.example'}})).status(),403);
 await c.dispose();console.log('PASS: API canonical origin, WebKit null + trusted Referer, normal access, foreign origin/port/Host rejection and CSRF still enforced.');
})().catch(e=>{console.error(e);process.exit(1)});
