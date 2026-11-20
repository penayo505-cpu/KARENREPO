const assert=require('node:assert/strict');const fs=require('node:fs');
(async()=>{
 const {treatmentBalances}=await import('data:text/javascript;base64,'+Buffer.from(fs.readFileSync('public/home-balances.js')).toString('base64'));
 const d={treatments:[{id:'old',patientId:'a',cost:100},{id:'new',patientId:'a',cost:200,date:'2026-11-20'},{id:'other',patientId:'b',cost:50}],payments:[{patientId:'a',amount:80},{patientId:'a',treatmentId:'new',amount:120},{patientId:'b',amount:90}]};
 let m=treatmentBalances(d);assert.deepEqual(m.rows.get('old'),{paid:80,balance:20});assert.deepEqual(m.rows.get('new'),{paid:120,balance:80});assert.equal(m.credit.get('b'),40);
 d.payments.push({patientId:'a',treatmentId:'new',amount:200});m=treatmentBalances(d);assert.equal(m.rows.get('old').balance,0);assert.equal(m.rows.get('new').balance,0);assert.equal(m.credit.get('a'),100);
 d.treatments=d.treatments.filter(t=>t.id!=='new');m=treatmentBalances(d);assert.equal(m.credit.get('a'),300);assert.equal(m.rows.get('old').balance,0);
 // Unknown legacy dates are not fabricated; array order remains a stable tie breaker.
 const legacy={treatments:[{id:'x',patientId:'a',cost:100},{id:'y',patientId:'a',cost:100}],payments:[{patientId:'a',amount:150}]};m=treatmentBalances(legacy);assert.equal(m.rows.get('x').paid,100);assert.equal(m.rows.get('y').paid,50);assert.equal(legacy.treatments[0].date,undefined);
 // Conservation of all money, including excess, zero-cost records and orphaned links.
 let seed=7;const rand=n=>{seed=(seed*16807)%2147483647;return seed%n;};
 for(let i=0;i<200;i++){
 const data={treatments:[],payments:[]};for(let j=0;j<8;j++)data.treatments.push({id:String(j),patientId:String(j%2),cost:rand(500)});
 for(let j=0;j<12;j++)data.payments.push({patientId:String(j%2),amount:rand(500),treatmentId:String(rand(10))});
 const original=JSON.stringify(data);const result=treatmentBalances(data);assert.equal(JSON.stringify(data),original);
 for(const pid of ['0','1']){const ts=data.treatments.filter(t=>t.patientId===pid);const received=data.payments.filter(p=>p.patientId===pid).reduce((n,p)=>n+p.amount,0);const applied=ts.reduce((n,t)=>n+result.rows.get(t.id).paid,0);assert.equal(applied+(result.credit.get(pid)||0),received);assert.equal(result.credit.get(pid)||0,Math.max(0,received-ts.reduce((n,t)=>n+t.cost,0)));for(const t of ts){const row=result.rows.get(t.id);assert.equal(row.paid+row.balance,t.cost);assert.ok(row.paid>=0&&row.balance>=0);}}
 }
console.log('PASS: legacy/linked payments, FIFO, credit, deleted treatments, patient isolation and 200 balance-conservation scenarios.');
})().catch(e=>{console.error(e);process.exit(1)});
