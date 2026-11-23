/** Each course owns its visits; adding a visit never creates another treatment charge. */
export function sessionsFor(data,treatmentId){
 return data.sessions.map((s,index)=>({s,index})).filter(x=>x.s.treatmentId===treatmentId)
  .sort((a,b)=>a.s.date.localeCompare(b.s.date)||a.index-b.index).map(x=>x.s);
}
export function appendCourseSession(data,treatmentId,session,payment=null,appointmentId=null){
 const treatment=data.treatments.find(t=>t.id===treatmentId);
 if(!treatment)throw Error('El tratamiento ya no está disponible.');
 if(treatment.status==='Completado')throw Error('Este tratamiento está finalizado. Cambiá su estado a En Curso para continuarlo.');
 if(session.patientId!==treatment.patientId||data.sessions.some(s=>s.id===session.id))throw Error('La sesión no corresponde a este tratamiento.');
 const appointment=appointmentId?data.appointments.find(a=>a.id===appointmentId):null;
 if(appointmentId&&(!appointment||appointment.patientId!==treatment.patientId||(appointment.treatmentId&&appointment.treatmentId!==treatmentId)||['Cancelada','Atendida'].includes(appointment.status)))throw Error('La cita ya cambió. Actualizá los datos antes de continuar.');
 if(payment&&(payment.patientId!==treatment.patientId||data.payments.some(p=>p.id===payment.id)))throw Error('El pago no corresponde a este paciente.');
 const next=structuredClone(data);const target=next.treatments.find(t=>t.id===treatmentId);
 target.longTerm=true;target.status='En Curso';if(typeof session.stage==='string')target.stage=session.stage;
 next.sessions.push({...session,treatmentId,...(appointment?{appointmentId:appointment.id}:{})});
 if(payment)next.payments.push({...payment,treatmentId,sessionId:session.id});
 if(appointment){const a=next.appointments.find(a=>a.id===appointment.id);a.status='Atendida';a.treatmentId=treatmentId;}
 return next;
}
