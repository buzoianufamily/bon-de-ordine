/* Consola operator: call next, recall, finish, no-show, transfer + polling */
(function(){
  const cfg = window.COUNTER || {};
  const elCur = document.getElementById('curTicket');
  const elSvc = document.getElementById('curSvc');
  const elWaitCount = document.getElementById('waitCount');
  const elList = document.getElementById('qList');
  const elForm = document.getElementById('curForm');
  const esc = s => String(s==null?'':s).replace(/[<>&"]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
  let currentId = null;

  document.getElementById('btnCall').addEventListener('click', callNext);
  bind('btnRecall', ()=> act('api/recall'));
  bind('btnServing', ()=> act('api/serving'));
  bind('btnFinish', ()=> act('api/finish', true));
  bind('btnNoShow', ()=> act('api/no-show', true));
  function bind(id, fn){ const b=document.getElementById(id); if(b) b.addEventListener('click', fn); }

  async function callNext(){
    const res = await QMS.api('api/call-next', { counter_id: cfg.counterId });
    if(!res.ok){ QMS.toast(res.error||'Eroare','error'); return; }
    if(!res.ticket){ QMS.toast('Coada este goala','' ); }
    refresh();
  }
  async function act(path, clearAfter){
    if(!currentId){ QMS.toast('Niciun bilet activ',''); return; }
    const res = await QMS.api(path, { ticket_id: currentId });
    if(!res.ok){ QMS.toast(res.error||'Eroare','error'); return; }
    refresh();
  }
  async function transfer(serviceId){
    if(!currentId) return;
    const res = await QMS.api('api/transfer', { ticket_id: currentId, service_id: serviceId });
    if(res.ok){ QMS.toast('Bilet transferat','ok'); refresh(); }
  }
  window.qmsTransfer = transfer;

  async function refresh(){
    const res = await QMS.api('api/counter-state?counter_id='+cfg.counterId, null, 'GET');
    if(!res.ok) return;
    const c = res.current;
    currentId = c ? c.id : null;
    elCur.textContent = c ? c.label : '—';
    elCur.style.color = c ? (c.color||cfg.accent) : '#cbd1da';
    elSvc.textContent = c ? c.service_name : 'Niciun bilet in lucru';
    if(elForm){ let html='';
      if(c && c.form_data){ try{ const d=JSON.parse(c.form_data);
        if(Array.isArray(d) && d.length){ html='<div style="background:#f8fafc;border:1px solid #e6e8ec;border-radius:10px;padding:.7rem;margin:.2rem 0 1rem;text-align:left">'+
          '<div class="muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem">Date client</div>'+
          d.map(x=>`<div style="display:flex;justify-content:space-between;gap:1rem;padding:.18rem 0;border-bottom:1px solid #eef1f5"><span class="muted">${esc(x.label)}</span><strong>${esc(x.value||'—')}</strong></div>`).join('')+'</div>'; }
      }catch(e){} }
      elForm.innerHTML=html;
    }
    elWaitCount.textContent = res.waiting_count;
    elList.innerHTML = res.waiting.map(t=>`<div class="qrow">
      <span class="tag" style="background:${t.color}">${t.label[0]}</span>
      <span class="lbl">${t.label}</span>
      ${t.priority? '<span class="pill" style="background:#fee2e2;color:#b91c1c">PRIORITAR</span>':''}
      <span class="muted" style="margin-left:auto">${t.service_name}</span></div>`).join('')
      || '<div class="muted" style="padding:1rem">Coada este goala.</div>';
  }
  refresh();
  setInterval(refresh, 2000);
})();
