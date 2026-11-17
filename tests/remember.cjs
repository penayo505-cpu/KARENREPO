const {request}=require('playwright');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {createHash}=require('node:crypto');
const base='http://localhost:8090/api/index.php?action=';
const db=action=>execFileSync(process.env.PHP_BIN||'php',['tests/db-fixture.php',action],{env:{...process.env,VITAL_DENT_TESTS:'1'},encoding:'utf8'});
async function status(c){const r=await c.get(base+'status');assert.equal(r.status(),200);return r.json();}
async function post(c,action,data){const s=await status(c);return c.post(base+action,{headers:{'X-CSRF-Token':s.csrf},data});}
async function login(c,remember,password='new-test-password-456'){const r=await post(c,'login',{username:'karen.frutos',password,remember});assert.equal(r.status(),200,await r.text());}
async function cookie(c,name){return (await c.storageState()).cookies.find(c=>c.name===name);}
async function onlyRemember(c){const r=await cookie(c,'vd_remember');return request.newContext({storageState:{cookies:[r],origins:[]}});}
(async()=>{
 // Runs after browser.cjs, which creates the test account and changes its password.
 const regular=await request.newContext();await login(regular,false);
 assert.equal(await cookie(regular,'vd_remember'),undefined);
 assert.equal((await cookie(regular,'vd_session')).expires,-1);
 const remembered=await request.newContext();await login(remembered,true);
 const token=await cookie(remembered,'vd_remember');assert.equal(token.httpOnly,true);assert.equal(token.sameSite,'Strict');assert.ok(Math.abs(token.expires-Date.now()/1000-30*86400)<10);
 const rows=JSON.parse(db('tokens'));assert.ok(rows.some(r=>r.token_hash===createHash('sha256').update(token.value).digest('hex')));assert.ok(rows.every(r=>r.token_hash!==token.value));
 // Retain only the durable cookie: no PHP session file/cookie is required to resume.
 const resumed=await onlyRemember(remembered);assert.equal((await status(resumed)).authenticated,true);
 const s=await status(resumed);const read=await resumed.get(base+'clinic');assert.equal(read.status(),200);
 // Absolute deadline is not extended by restoring or using a session.
 assert.equal((await cookie(remembered,'vd_remember')).expires,token.expires);
 const replay=await onlyRemember(remembered);
 assert.equal((await post(resumed,'logout',{})).status(),200);
 assert.equal((await status(replay)).authenticated,false);
 assert.equal((await status(remembered)).authenticated,false);
 // Opting out on the same device revokes its previous durable credential.
 const optedOut=await request.newContext();await login(optedOut,true);const oldOptIn=await onlyRemember(optedOut);await login(optedOut,false);assert.equal(await cookie(optedOut,'vd_remember'),undefined);assert.equal((await status(oldOptIn)).authenticated,false);
 // A tampered durable cookie never creates an authenticated session.
 const tampered=await request.newContext({storageState:{cookies:[{...token,value:'0'.repeat(64)}],origins:[]}});assert.equal((await status(tampered)).authenticated,false);
 const expired=await request.newContext();await login(expired,true);db('expire');assert.equal((await status(expired)).authenticated,false);
 // Password changes revoke durable cookies on other devices and the current one.
 const device=await request.newContext();await login(device,true);const editor=await request.newContext();await login(editor,true);
 const changed=await post(editor,'password',{current:'new-test-password-456',password:'final-test-password-789'});assert.equal(changed.status(),200);
 assert.equal((await status(device)).authenticated,false);assert.equal(await cookie(editor,'vd_remember'),undefined);assert.equal((await status(editor)).authenticated,true);
 // Invalid CSRF cannot log in, log out or create a durable token.
 const noCsrf=await editor.post(base+'logout',{data:{}});assert.equal(noCsrf.status(),403);assert.equal((await status(editor)).authenticated,true);
 for(const c of [regular,remembered,resumed,replay,tampered,expired,device,editor,optedOut,oldOptIn])await c.dispose();
 console.log('PASS: 30-day HttpOnly cookie, hashed token, resume without PHP session, fixed expiry, normal session opt-out, logout revocation/replay rejection, tamper rejection, expiry and password revocation, CSRF.');
})().catch(e=>{console.error(e);process.exit(1)});
