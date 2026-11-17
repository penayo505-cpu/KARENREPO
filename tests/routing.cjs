const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
(async()=>{
 const {canonicalUrl}=await import('data:text/javascript;base64,'+Buffer.from(fs.readFileSync('public/routing.js')).toString('base64'));
 const good='https://vdnt.bmimportexport.com.py';
 for(const [from,to]of [
  ['https://www.vdnt.bmimportexport.com.py/',good+'/'],
  ['https://WWW.VDNT.BMIMPORTEXPORT.COM.PY/',good+'/'],
  ['http://www.vdnt.bmimportexport.com.py/',good+'/'],
  ['https://www.vdnt.bmimportexport.com.py:443/?from=home#agenda',good+'/?from=home#agenda'],
  ['https://www.vdnt.bmimportexport.com.py/index.html',good+'/index.html'],
  [good+'/',null],['http://localhost:8090/',null],
  ['https://www.vdnt.bmimportexport.com.py.evil.example/',null],
  ['https://www.bmimportexport.com.py/',null],['file:///index.html',null]
 ])assert.equal(canonicalUrl(from),to);
 const browser=await chromium.launch({...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{}),headless:true,args:['--no-sandbox']});
 const context=await browser.newContext();const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));let aliasApiCalls=0;
 await page.route('https://cdn.tailwindcss.com/**',r=>r.abort());
 await page.route('https://www.vdnt.bmimportexport.com.py/**',async route=>{
  const url=new URL(route.request().url());if(url.pathname.startsWith('/api/'))aliasApiCalls++;
  const relative=url.pathname==='/'?'public/index.html':'public'+url.pathname;
  if(!fs.existsSync(relative)){await route.abort();return;}
  const type=relative.endsWith('.js')?'text/javascript':relative.endsWith('.css')?'text/css':relative.endsWith('.svg')?'image/svg+xml':'text/html';
  await route.fulfill({status:200,contentType:type,body:fs.readFileSync(relative)});
 });
 await page.route('https://vdnt.bmimportexport.com.py/**',r=>r.fulfill({contentType:'text/html; charset=utf-8',body:'<h1>Dirección autorizada</h1>'}));
 await page.goto('https://www.vdnt.bmimportexport.com.py/?from=bookmark#inicio');
 await page.getByRole('heading',{name:'Dirección autorizada'}).waitFor();assert.equal(page.url(),good+'/?from=bookmark#inicio');assert.equal(aliasApiCalls,0);assert.deepEqual(errors,[]);
 await browser.close();console.log('PASS: canonical-host mapping, no redirect loops, path/query/fragment preservation, unrelated-host rejection and browser www redirect before API calls.');
})().catch(e=>{console.error(e);process.exit(1)});
