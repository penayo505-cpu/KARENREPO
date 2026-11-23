const assert=require('node:assert/strict');const fs=require('node:fs');
(async()=>{
 const {sessionsFor,appendCourseSession}=await import('data:text/javascript;base64,'+Buffer.from(fs.readFileSync('public/courses.js')).toString('base64'));
 let data={patients:[],treatments:[{id:'ortho',patientId:'a',cost:2400000,status:'Pendiente',description:'Ortodoncia'},{id:'endo',patientId:'a',cost:500000,status:'En Curso'},{id:'other',patientId:'b',cost:100,status:'En Curso'}],sessions:[],payments:[],appointments:[{id:'visit',patientId:'a',treatmentId:'ortho',status:'Programada'}]};
 for(let i=0;i<65;i++){
  const original=JSON.stringify(data);const session={id:'s'+i,patientId:'a',date:i%2?'2026-11-22':'2026-11-23',procedure:'Control '+i,notes:'Nota única '+i,stage:'Etapa '+i,files:[{id:'file'+i}]};
  const next=appendCourseSession(data,'ortho',session,i<2?{id:'p'+i,patientId:'a',amount:50000}:null,i===0?'visit':null);assert.equal(JSON.stringify(data),original);data=next;
 }
 assert.equal(data.treatments.length,3);assert.equal(data.treatments[0].cost,2400000);assert.equal(data.treatments[0].status,'En Curso');assert.equal(data.treatments[0].stage,'Etapa 64');assert.equal(data.payments.length,2);assert.equal(data.payments[0].sessionId,'s0');assert.equal(data.appointments[0].status,'Atendida');assert.equal(data.sessions[0].notes,'Nota única 0');assert.equal(data.sessions[0].stage,'Etapa 0');assert.equal(data.sessions[0].files[0].id,'file0');assert.equal(sessionsFor(data,'ortho').length,65);assert.equal(sessionsFor(data,'endo').length,0);
 const ordered=sessionsFor(data,'ortho');assert.ok(ordered[0].date<=ordered.at(-1).date);assert.equal(new Set(ordered.map(s=>s.id)).size,65);
 assert.throws(()=>appendCourseSession(data,'ortho',{id:'s0',patientId:'a'}));assert.throws(()=>appendCourseSession(data,'ortho',{id:'new',patientId:'b'}));assert.throws(()=>appendCourseSession(data,'ortho',{id:'new',patientId:'a'},{id:'px',patientId:'b'}));assert.throws(()=>appendCourseSession(data,'ortho',{id:'new',patientId:'a'},null,'visit'));
 data.treatments[0].status='Completado';assert.throws(()=>appendCourseSession(data,'ortho',{id:'last',patientId:'a'}));assert.equal(data.treatments[1].status,'En Curso');
 console.log('PASS: 65 independent sessions, immutable prior notes/files/stages, fixed budget, linked payments/appointment, parallel treatment isolation and closed/duplicate/cross-patient rejection.');
})().catch(e=>{console.error(e);process.exit(1)});
