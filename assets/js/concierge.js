/* Concierge / Receptie — terminal virtual cu 3 tab-uri (ca la Moviik), pastrand
   functiile noastre: emitere walk-in, chemare la orice ghiseu, panou ghisee live,
   repunere neprezentati; plus „ultimul bilet chemat" cu finalizare/anulare/transfer,
   filtre + cautare si tab de Programari (check-in / anulare / programare noua). */
(function(){
  const cfg = window.CONCIERGE || {};
  const $ = id => document.getElementById(id);
  const esc = s => String(s==null?'':s).replace(/[<>&"]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
  const ctrOpts = (cfg.counters||[]).map(c=>`<option value="${c.id}">${esc(c.code+' · '+c.name)}</option>`).join('');

  /* ---------- TAB-uri ---------- */
  let activeTab = 'new';
  const tabs = document.querySelectorAll('#cgTabs a[data-tab]');
  function showTab(name){
    activeTab = name;
    tabs.forEach(a=>a.classList.toggle('on', a.getAttribute('data-tab')===name));
    document.querySelectorAll('.tabpane').forEach(p=>p.classList.toggle('on', p.id==='pane-'+name));
    if(name==='appt') loadAppts();
  }
  tabs.forEach(a=>a.addEventListener('click', ()=>showTab(a.getAttribute('data-tab'))));

  /* ---------- TAB BON NOU: emitere walk-in ---------- */
  document.querySelectorAll('[data-issue]').forEach(function(b){
    b.addEventListener('click', async function(){
      var prio=false;
      if(b.dataset.prio==='1'){ prio = await QMS.confirm('Bon prioritar?', {ok:'Prioritar', cancel:'Normal'}); }
      b.disabled=true;
      var r = await QMS.api('api/issue-manual', {service_id:+b.dataset.issue, priority:prio});
      b.disabled=false;
      var m=$('issuedMsg');
      if(r.ok){ m.style.display=''; m.className='cg-issued ok';
        m.textContent='✔ Bon emis: '+r.ticket.label+(r.position>0?(' · '+r.position+' inainte'):' · urmeaza'); refresh(); }
      else { m.style.display=''; m.className='cg-issued err'; m.textContent='✖ '+(r.error||'Eroare'); }
    });
  });

  /* ---------- TAB CHEMARE ---------- */
  let allItems = [], lastCalled = null, filter = 'all', search = '';

  async function callTo(ticketId, counterId){
    const r = await QMS.api('api/call-specific', {ticket_id:ticketId, counter_id:counterId});
    if(!r.ok){ QMS.toast(r.error||'Eroare','error'); } else { QMS.toast('Bilet chemat','ok'); }
    refresh();
  }
  // delegare click/change pe tabelul de chemare
  $('cgRows').addEventListener('change', e=>{
    const s=e.target.closest('select[data-tid]'); if(!s||!s.value) return;
    const tid=+s.getAttribute('data-tid'), cid=+s.value, cn=s.options[s.selectedIndex].text;
    QMS.confirm('Chemi biletul la „'+cn+'"?', {ok:'Cheama'}).then(ok=>{ if(ok) callTo(tid,cid); else s.value=''; });
  });
  $('cgRows').addEventListener('click', e=>{
    const b=e.target.closest('[data-requeue-row]'); if(!b) return;
    const id=+b.getAttribute('data-requeue-row');
    QMS.api('api/requeue',{ticket_id:id}).then(r=>{ if(r.ok){QMS.toast('Bilet repus la rand','ok');refresh();} else QMS.toast(r.error||'Eroare','error'); });
  });

  // filtre + cautare
  document.getElementById('cgFilter').addEventListener('click', e=>{
    const b=e.target.closest('[data-f]'); if(!b) return;
    filter=b.getAttribute('data-f');
    document.querySelectorAll('#cgFilter button').forEach(x=>x.classList.toggle('on', x===b));
    renderRows();
  });
  $('cgSearch').addEventListener('input', e=>{ search=e.target.value.trim().toLowerCase(); renderRows(); });

  // actiuni pe ultimul bilet chemat
  $('cgLastActs').addEventListener('click', async e=>{
    const b=e.target.closest('[data-last]'); if(!b||!lastCalled) return;
    const act=b.getAttribute('data-last');
    if(act==='finish'){ const r=await QMS.api('api/finish',{ticket_id:lastCalled.id}); if(r.ok){QMS.toast('Bilet finalizat','ok');refresh();} else QMS.toast(r.error||'Eroare','error'); }
    else if(act==='cancel'){ if(await QMS.confirm('Anulezi biletul '+lastCalled.label+'?',{ok:'Anuleaza'})){ const r=await QMS.api('api/cancel',{ticket_id:lastCalled.id}); if(r.ok){QMS.toast('Bilet anulat','ok');refresh();} else QMS.toast(r.error||'Eroare','error'); } }
  });
  $('cgLastTransfer').addEventListener('change', async e=>{
    if(!e.target.value||!lastCalled) return;
    const cid=+e.target.value, cn=e.target.options[e.target.selectedIndex].text;
    if(await QMS.confirm('Transferi biletul la „'+cn+'"?',{ok:'Transfera'})){
      const r=await QMS.api('api/transfer-counter',{ticket_id:lastCalled.id, target_counter:cid});
      if(r.ok){QMS.toast('Bilet transferat','ok');refresh();} else QMS.toast(r.error||'Eroare','error');
    }
    e.target.value='';
  });

  function clientFormSummary(fd){
    if(!fd) return '';
    try{ const d=JSON.parse(fd); if(Array.isArray(d)&&d.length) return d.map(x=>esc((x.value||'').toString())).filter(Boolean).join(', '); }catch(e){}
    return '';
  }
  const stLab={waiting:'la rand',called:'apelat',serving:'in servire',cancelled:'anulat',no_show:'neprezentat'};

  function renderRows(){
    let rows = allItems.filter(t=>{
      if(filter==='waiting'  && t.kind!=='waiting')  return false;
      if(filter==='called'   && t.kind!=='called')   return false;
      if(filter==='cancelled'&& t.kind!=='cancelled')return false;
      if(search){ const hay=((t.label||'')+' '+(t.service_name||'')).toLowerCase(); if(hay.indexOf(search)<0) return false; }
      return true;
    });
    $('cgRows').innerHTML = rows.map(t=>{
      const cf=clientFormSummary(t.form_data);
      let action='';
      if(t.kind==='waiting'){
        action=`<select data-tid="${t.id}" style="width:auto"><option value="">📣 Cheama la…</option>${ctrOpts}</select>`;
      } else if(t.kind==='cancelled' && t.status==='no_show'){
        action=`<button class="btn btn-ghost" data-requeue-row="${t.id}" style="font-size:.82rem">↩ Repune</button>`;
      } else if(t.kind==='called'){
        action=`<span class="muted" style="font-size:.82rem">${t.counter_code?('@ '+esc(t.counter_code)):''}</span>`;
      }
      return `<tr class="cg-row ${t.kind}">
        <td><span class="tag" style="background:${esc(t.color)}">${esc((t.label||'?')[0])}</span> <strong>${esc(t.label)}</strong></td>
        <td>${esc(t.service_name||'')}</td>
        <td class="muted">${cf||'—'}</td>
        <td class="muted">${esc(t.hm||'')}</td>
        <td>${t.priority?'<span class="pill" style="background:#fee2e2;color:#b91c1c">PRIORITAR</span>':'<span class="pill st-'+(t.status||'waiting')+'">'+(stLab[t.status]||'la rand')+'</span>'}</td>
        <td style="text-align:right">${action}</td>
      </tr>`;
    }).join('') || '<tr><td colspan="6" class="muted" style="padding:1rem">Niciun bilet.</td></tr>';
  }

  function renderCounters(counters){
    $('ctrList').innerHTML = (counters||[]).map(c=>`<div class="qrow">
      <span class="lbl" style="font-size:1rem">${esc(c.code)}</span>
      <span class="muted">${esc(c.name)}</span>
      ${c.status==='paused'?'<span class="pill" style="background:#fef3c7;color:#92400e" title="'+esc(c.pause_note||'')+'">⏸ pauza</span>':''}
      <span style="margin-left:auto;font-family:var(--display);font-weight:800;color:${cfg.accent}">${c.current_label?esc(c.current_label):'—'}</span>
    </div>`).join('') || '<div class="muted" style="padding:1rem">Niciun ghiseu.</div>';
  }
  function renderNoShow(ns){
    const box=$('nsBox'), wrap=$('nsList');
    if(!box||!wrap) return;
    if(!ns.length){ box.style.display='none'; wrap.innerHTML=''; return; }
    box.style.display='';
    wrap.innerHTML=ns.map(t=>`<button class="btn btn-ghost" data-requeue="${t.id}" style="font-size:.85rem">↩ <strong style="color:${esc(t.color||cfg.accent)}">${esc(t.label)}</strong> <span class="muted">${esc(t.at||'')}</span></button>`).join('');
  }
  $('nsList').addEventListener('click', async e=>{
    const b=e.target.closest('[data-requeue]'); if(!b) return; b.disabled=true;
    const r=await QMS.api('api/requeue',{ticket_id:+b.dataset.requeue});
    if(r.ok){ QMS.toast('Bilet repus la rand','ok'); refresh(); } else { QMS.toast(r.error||'Eroare','error'); b.disabled=false; }
  });

  function renderLast(){
    const el=$('cgLast'), meta=$('cgLastMeta'), acts=$('cgLastActs');
    if(!lastCalled){ el.textContent='—'; el.style.color=''; meta.style.display='none'; acts.style.display='none'; return; }
    el.textContent=lastCalled.label; el.style.color=lastCalled.color||cfg.accent;
    meta.style.display=''; meta.textContent=(lastCalled.service_name||'')+(lastCalled.counter_code?(' · ghiseu '+lastCalled.counter_code):'')+(lastCalled.called_hm?(' · '+lastCalled.called_hm):'');
    acts.style.display='';
  }

  async function refresh(){
    const res = await QMS.api('api/branch-state?branch='+cfg.branch, null, 'GET');
    if(!res.ok) return;
    const items=[];
    (res.waiting||[]).forEach(t=>items.push(Object.assign({kind:'waiting',status:'waiting'},t)));
    (res.called||[]).forEach(t=>items.push(Object.assign({kind:'called'},t)));
    (res.cancelled||[]).forEach(t=>items.push(Object.assign({kind:'cancelled'},t)));
    allItems=items;
    lastCalled=(res.called&&res.called.length)?res.called[0]:null;
    const badge=$('cgWaitBadge'); if(badge){ if(res.waiting_count>0){ badge.style.display=''; badge.textContent=res.waiting_count; } else badge.style.display='none'; }
    renderLast(); renderRows(); renderCounters(res.counters||[]); renderNoShow(res.no_show||[]);
  }

  /* ---------- TAB PROGRAMARI ---------- */
  let apptCache=[], apptSearch='';
  const apptStLab={booked:'Confirmata',checked_in:'Check-in',cancelled:'Anulata',no_show:'Neprezentat'};
  async function loadAppts(){
    const d=$('cgApptDate').value || '', sv=$('cgApptSvc').value || '';
    const res=await QMS.api('api/concierge-appointments?branch='+cfg.branch+'&date='+encodeURIComponent(d)+(sv?('&service_id='+sv):''), null, 'GET');
    apptCache=(res&&res.ok)?(res.appointments||[]):[];
    renderAppts();
  }
  function renderAppts(){
    let rows=apptCache;
    if(apptSearch){ rows=rows.filter(a=>((a.customer_name||'')+' '+(a.service_name||'')+' '+(a.public_token||'')+' '+(a.ticket_label||'')).toLowerCase().indexOf(apptSearch)>=0); }
    $('cgApptRows').innerHTML = rows.map(a=>{
      let act='';
      if(a.status==='booked') act=`<button class="btn btn-primary" data-checkin="${a.id}" style="font-size:.82rem;padding:.4rem .7rem">Check-in</button> <button class="btn btn-ghost" data-apptcancel="${a.id}" style="font-size:.82rem;padding:.4rem .7rem">Anuleaza</button>`;
      else if(a.status==='checked_in') act=`<span class="pill st-serving">bon ${esc(a.ticket_label||'')}</span>`;
      return `<tr>
        <td><strong>${esc(a.hm||'')}</strong></td>
        <td><span class="tag" style="background:${esc(a.color)}">${esc((a.prefix||'?')[0])}</span> ${esc(a.service_name||'')}</td>
        <td>${esc(a.customer_name||'—')}${a.customer_phone?(' <span class="muted">'+esc(a.customer_phone)+'</span>'):''}</td>
        <td class="muted">${esc((a.public_token||'').slice(0,8))}</td>
        <td><span class="pill st-${a.status}">${apptStLab[a.status]||a.status}</span></td>
        <td style="text-align:right">${act}</td>
      </tr>`;
    }).join('') || '<tr><td colspan="6" class="muted" style="padding:1rem">Nicio programare pentru filtrele alese.</td></tr>';
  }
  $('cgApptDate').addEventListener('change', loadAppts);
  $('cgApptSvc').addEventListener('change', loadAppts);
  $('cgApptSearch').addEventListener('input', e=>{ apptSearch=e.target.value.trim().toLowerCase(); renderAppts(); });
  $('cgApptRows').addEventListener('click', async e=>{
    const ci=e.target.closest('[data-checkin]'), cc=e.target.closest('[data-apptcancel]');
    if(ci){ ci.disabled=true; const r=await QMS.api('api/appt-checkin',{appt_id:+ci.getAttribute('data-checkin')});
      if(r.ok){ QMS.toast('Check-in OK · bon '+r.ticket.label,'ok'); loadAppts(); if(activeTab==='call')refresh(); } else { QMS.toast(r.error||'Eroare','error'); ci.disabled=false; } }
    else if(cc){ if(await QMS.confirm('Anulezi programarea?',{ok:'Anuleaza'})){ const r=await QMS.api('api/appt-cancel',{appt_id:+cc.getAttribute('data-apptcancel')});
      if(r.ok){ QMS.toast('Programare anulata','ok'); loadAppts(); } else QMS.toast(r.error||'Eroare','error'); } }
  });
  // formular programare noua
  $('cgApptNewBtn').addEventListener('click', ()=>{ const f=$('cgApptForm'); f.style.display=(f.style.display==='none')?'':'none'; });
  $('naCancel').addEventListener('click', ()=>{ $('cgApptForm').style.display='none'; });
  $('naSave').addEventListener('click', async ()=>{
    const m=$('naMsg'); const sid=+($('naSvc').value||0);
    if(!sid){ m.style.display=''; m.style.color='var(--danger,#dc2626)'; m.textContent='Alege un serviciu cu programari.'; return; }
    $('naSave').disabled=true;
    const r=await QMS.api('api/appt-create',{service_id:sid, date:$('naDate').value, time:$('naTime').value,
      name:$('naName').value, phone:$('naPhone').value, email:$('naEmail').value});
    $('naSave').disabled=false;
    if(r.ok){ m.style.display=''; m.style.color='var(--ok,#16a34a)'; m.textContent='✔ Programare creata.';
      $('naName').value=$('naPhone').value=$('naEmail').value=''; $('cgApptForm').style.display='none'; loadAppts(); }
    else { m.style.display=''; m.style.color='var(--danger,#dc2626)'; m.textContent='✖ '+(r.error||'Eroare'); }
  });

  /* ---------- pornire ---------- */
  refresh();
  var _busy=false;  // anti-suprapunere pe retele lente
  setInterval(async function(){ if(_busy) return; _busy=true; try{ await refresh(); } finally{ _busy=false; } }, 2000);
})();
