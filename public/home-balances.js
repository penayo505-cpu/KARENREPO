/** Money already received is applied once: linked payments first, then oldest debts.
 * Legacy/unassigned payments and excess amounts stay available to that patient.
 * This is a view calculation; no existing payment is rewritten or duplicated.
 */
export function treatmentBalances(data) {
  const rows=new Map(data.treatments.map(t=>[t.id,{paid:0,balance:t.cost}]));
  const treatments=new Map(data.treatments.map(t=>[t.id,t]));
  const pools=new Map();
  for(const p of data.payments){
    let available=p.amount;
    const target=treatments.get(p.treatmentId);
    if(target&&target.patientId===p.patientId){
      const row=rows.get(target.id);const applied=Math.min(row.balance,available);
      row.paid+=applied;row.balance-=applied;available-=applied;
    }
    pools.set(p.patientId,(pools.get(p.patientId)||0)+available);
  }
  const ordered=data.treatments.map((t,index)=>({t,index})).sort((a,b)=>(a.t.date||'').localeCompare(b.t.date||'')||a.index-b.index);
  for(const {t} of ordered){
    const row=rows.get(t.id);const applied=Math.min(row.balance,pools.get(t.patientId)||0);
    row.paid+=applied;row.balance-=applied;pools.set(t.patientId,(pools.get(t.patientId)||0)-applied);
  }
  return {rows,credit:pools};
}
