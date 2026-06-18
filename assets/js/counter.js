/* Consola operator: selectezi UN bilet din lista -> apar optiunile pentru el.
   Refresh ne-distructiv (nu reconstruieste bara/lista decat la schimbari reale),
   selectare/deselectare cu click, transfer cu confirmare, anunt vocal RO corect. */
(function(){
  const cfg = window.COUNTER || {};
  const elCur  = document.getElementById('curTicket');
  const elSvc  = document.getElementById('curSvc');
  const elForm = document.getElementById('curForm');
  const elWait = document.getElementById('waitCount');
  const elList = document.getElementById('qList');
  const elBar  = document.getElementById('selBar');
  const esc = s => String(s==null?'':s).replace(/[<>&"]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
  const mmss = s => { s=Math.max(0,s|0); return ('0'+((s/60|0)%100)).slice(-2)+':'+('0'+(s%60)).slice(-2); };
  let selId = null, items = [], lastSig = '', lastBarKey = '';

  /* ---- notificari browser (opt-in) ---- */
  let knownWaiting = null;
  if (cfg.notify && 'Notification' in window && Notification.permission === 'default') {
    const ask = () => { Notification.requestPermission(); document.removeEventListener('click', ask); };
    document.addEventListener('click', ask);
  }
  function notifyNew(list){
    if (!cfg.notify) return; const ids = list.map(t => t.id);
    if (knownWaiting === null) { knownWaiting = new Set(ids); return; }
    list.forEach(t => { if (!knownWaiting.has(t.id)) {
      if ('Notification' in window && Notification.permission === 'granted')
        try { new Notification('Bilet nou la rand: ' + t.label, { body: t.service_name||'', tag: 'qms-'+t.id }); } catch(e){}
      QMS.toast('Bilet nou: ' + t.label + ' — ' + (t.service_name||''), 'ok');
    }});
    knownWaiting = new Set(ids);
  }

  /* ---- anunt vocal (optional) ---- */
  let voices=[]; function loadVoices(){ voices = window.speechSynthesis ? speechSynthesis.getVoices() : []; }
  if(window.speechSynthesis){ loadVoices(); speechSynthesis.onvoiceschanged=loadVoices; }
  function pickVoice(){ const lang=(cfg.voice||'ro-RO'), p=lang.slice(0,2).toLowerCase();
    return voices.find(v=>v.lang&&v.lang.toLowerCase()===lang.toLowerCase())
        || voices.find(v=>v.lang&&v.lang.toLowerCase().startsWith(p)) || null; }
  function ding(){ if(!cfg.voiceOn)return; try{ const ac=new(window.AudioContext||window.webkitAudioContext)(); const o=ac.createOscillator(),g=ac.createGain();
    o.connect(g);g.connect(ac.destination);o.frequency.value=880;g.gain.setValueAtTime(.0001,ac.currentTime);
    g.gain.exponentialRampToValueAtTime(.22,ac.currentTime+.02);g.gain.exponentialRampToValueAtTime(.0001,ac.currentTime+.5);o.start();o.stop(ac.currentTime+.5);}catch(e){} }
  function announce(t){
    if(!cfg.voiceOn||!t||!window.speechSynthesis) return;
    let phrase='';
    if(cfg.sayNumber) phrase += 'Bonul ' + String(t.label||'').split('').join(' ') + '. ';
    if(cfg.sayCounter && cfg.counterName) phrase += 'Vă rugăm să vă prezentați la ' + cfg.counterName + '.';
    if(!phrase.trim()) return;
    try{ speechSynthesis.cancel(); }catch(e){}   // o singura curatare, la inceput
    ding();
    const rep = Math.max(1, +(cfg.repeat||1)); let i = 0; const v = pickVoice();
    // repetam DOAR dupa ce s-a terminat citirea precedenta (fara sa o taiem la mijloc)
    const fire = () => {
      if(i >= rep) return; i++;
      const u = new SpeechSynthesisUtterance(phrase);
      u.lang = cfg.voice||'ro-RO'; if(v) u.voice = v; u.rate = .95;
      u.onend = () => { if(i < rep) setTimeout(fire, 700); };
      u.onerror = () => { if(i < rep) setTimeout(fire, 700); };
      try{ speechSynthesis.speak(u); }catch(e){}
    };
    setTimeout(fire, 350);
  }

  /* ---- pauza ghiseu (cu mesaj afisat clientilor) ---- */
  (function(){
    var bp=document.getElementById('btnPauseC'), br=document.getElementById('btnResumeC');
    if(bp) bp.addEventListener('click', function(){
      QMS.prompt('Mesaj afisat clientilor cat timp ghiseul e in pauza (optional):', {placeholder:'ex: Revenim in 10 minute', ok:'Pune in pauza'})
        .then(function(v){ if(v===null) return;
          QMS.api('api/counter-pause',{counter_id:cfg.counterId, note:v}).then(function(r){ if(r&&r.ok) location.reload(); });
        });
    });
    if(br) br.addEventListener('click', function(){
      QMS.api('api/counter-open',{counter_id:cfg.counterId}).then(function(r){ if(r&&r.ok) location.reload(); });
    });
  })();

  /* ---- status operator ---- */
  async function setStatus(s){ const r=await QMS.api('api/user-status',{status:s}); if(r&&r.ok){ const sel=document.getElementById('opStatus'); if(sel)sel.value=s; } }
  window.qmsStatus = setStatus;
  if(cfg.myStatus==='offline') setStatus('available');

  /* ---- CHEAMA URMATORUL (auto, optional dintr-un serviciu anume) ---- */
  async function callNext(serviceId){
    const body = { counter_id: cfg.counterId }; if(serviceId) body.service_id = serviceId;
    const res = await QMS.api('api/call-next', body);
    if(!res.ok){ QMS.toast(res.error||'Eroare','error'); return; }
    if(!res.ticket){ QMS.toast(serviceId?'Niciun bilet la rand pentru acest serviciu':'Coada este goala',''); }
    else { selId=res.ticket.id; announce(res.ticket); }
    refresh(true);
  }
  document.getElementById('btnCall').addEventListener('click', ()=>callNext(0));
  const elCallSvc = document.getElementById('callSvc');
  if(elCallSvc) elCallSvc.addEventListener('change', ()=>{ const sid=+elCallSvc.value; elCallSvc.value=''; if(sid) callNext(sid); });

  /* ---- scurtaturi tastatura ---- */
  function moveSel(dir){
    if(!items.length) return;
    let idx = items.findIndex(x=>x.id===selId);
    idx = idx<0 ? (dir>0?0:items.length-1) : Math.max(0, Math.min(items.length-1, idx+dir));
    selId = items[idx].id; syncUI(true);
    const row = elList.querySelector('.qrow.sel'); if(row && row.scrollIntoView) row.scrollIntoView({block:'nearest'});
  }
  document.addEventListener('keydown', e=>{
    if(/^(INPUT|SELECT|TEXTAREA)$/.test(e.target.tagName)) return;
    const k = e.key.toLowerCase();
    if(k===' ' || k==='enter'){ e.preventDefault(); callNext(0); }
    else if(k==='escape'){ if(selId){ selId=null; syncUI(true); } }
    else if(k==='arrowdown'){ e.preventDefault(); moveSel(1); }
    else if(k==='arrowup'){ e.preventDefault(); moveSel(-1); }
    else if(selId){
      if(k==='f') doMenu('finish');
      else if(k==='n') doMenu('noshow');
      else if(k==='r') doMenu('recall');
      else if(k==='s') doMenu('serving');
    }
  });

  /* ---- actiuni pe biletul SELECTAT (acelasi meniu pentru orice bilet) ----
     Daca biletul e „la rand", il chemam intai (il asignam ghiseului), apoi aplicam actiunea. */
  async function doMenu(action){
    if(!selId){ QMS.toast('Selecteaza un bilet',''); return; }
    const it = items.find(x=>x.id===selId);
    const wasWaiting = it && it.status==='waiting';
    const willAnnounce = (action==='recall' || action==='serving');
    if(wasWaiting){
      const cr = await QMS.api('api/call-specific', {ticket_id:selId, counter_id:cfg.counterId});
      if(!cr.ok){ QMS.toast(cr.error||'Eroare','error'); refresh(true); return; }
      if(cr.ticket) selId = cr.ticket.id;
      if(willAnnounce) announce(cr.ticket);
    }
    if(action==='recall'){
      if(!wasWaiting){ const r = await QMS.api('api/recall', {ticket_id:selId}); if(r&&r.ok) announce(items.find(x=>x.id===selId)); }
      refresh(true); return;
    }
    const map = {serving:'api/serving', finish:'api/finish', noshow:'api/no-show'};
    if(!map[action]){ refresh(true); return; }
    const r = await QMS.api(map[action], {ticket_id:selId});
    if(!r.ok){ QMS.toast(r.error||'Eroare','error'); }
    if(action==='finish' || action==='noshow') selId = null;
    refresh(true);
  }
  async function doTransfer(serviceId){
    if(!selId || !serviceId) return;
    const r = await QMS.api('api/transfer', {ticket_id:selId, service_id:serviceId});
    if(!r.ok){ QMS.toast(r.error||'Eroare','error'); refresh(true); return; }
    selId = null; refresh(true);
  }
  async function doTransferCounter(counterId){
    if(!selId || !counterId) return;
    const r = await QMS.api('api/transfer-counter', {ticket_id:selId, target_counter:counterId});
    if(!r.ok){ QMS.toast(r.error||'Eroare','error'); refresh(true); return; }
    QMS.toast('Bilet transferat la alt birou','ok'); selId = null; refresh(true);
  }
  elBar.addEventListener('click', e=>{ const b=e.target.closest('button[data-a]'); if(!b) return; const a=b.getAttribute('data-a');
    if(a==='deselect'){ selId=null; syncUI(true); }
    else if(a==='save-note'){ if(selId==null) return; const inp=elBar.querySelector('[data-note]'); const note=inp?inp.value:'';
      QMS.api('api/ticket-note',{ticket_id:selId, note:note}).then(r=>{
        QMS.toast(r&&r.ok?'Notă salvată':'Eroare', r&&r.ok?'ok':'error');
        const it=items.find(x=>x.id===selId); if(it) it.note=note;   // pastreaza local ca sa nu se piarda la refresh
      }).catch(()=>QMS.toast('Eroare','error')); }
    else doMenu(a);
  });
  elBar.addEventListener('change', e=>{
    const ts=e.target.closest('select[data-a="transfer"]');
    if(ts&&ts.value){ const name=ts.options[ts.selectedIndex].text, v=+ts.value;
      QMS.confirm('Transferi biletul către serviciul „'+name+'"?', {ok:'Transferă'})
        .then(ok=>{ if(ok) doTransfer(v); else ts.value=''; }); return; }
    const tc=e.target.closest('select[data-a="transfer-counter"]');
    if(tc&&tc.value){ const name=tc.options[tc.selectedIndex].text, v=+tc.value;
      QMS.confirm('Transferi biletul la biroul „'+name+'"?', {ok:'Transferă'})
        .then(ok=>{ if(ok) doTransferCounter(v); else tc.value=''; }); }
  });

  /* ---- selectare / DESELECTARE cu click (un singur bilet) ---- */
  elList.addEventListener('click', e=>{ const row=e.target.closest('.qrow'); if(!row) return;
    const id=+row.dataset.id; selId=(selId===id)?null:id; syncUI(true);
  });

  function renderList(){
    const stLab={waiting:'la rand',called:'apelat',serving:'in servire'};
    elList.innerHTML = items.map(t=>`<label class="qrow${t.id===selId?' sel':''}" data-id="${t.id}">
      <input type="radio" name="tk" value="${t.id}" ${t.id===selId?'checked':''} tabindex="-1">
      <span class="tag" style="background:${t.color}">${esc((t.label||'?')[0])}</span>
      <span class="lbl">${esc(t.label)}</span>
      ${t.priority? '<span class="pill" style="background:#fee2e2;color:#b91c1c">PRIORITAR</span>':''}
      ${t.targeted? '<span class="pill" style="background:#e0e7ff;color:#3730a3">⇆ transferat aici</span>':''}
      ${t.over? `<span class="pill" style="background:#fee2e2;color:#b91c1c" title="Asteapta de ${mmss(t.waited)} (tinta ${mmss(t.target)})">⏱ peste timp</span>`:''}
      <span class="pill st-${t.status||'waiting'}">${stLab[t.status]||'la rand'}</span>
      <span class="muted" style="margin-left:auto">${esc(t.service_name||'')}</span></label>`).join('')
      || '<div class="muted" style="padding:1rem">Niciun bilet.</div>';
  }
  function renderBar(){
    const it = items.find(x=>x.id===selId);
    if(!it){ elBar.style.display='none'; elBar.innerHTML=''; return; }
    let h = `<div class="selbar-head"><button class="selbar-x" data-a="deselect" title="Deselecteaza biletul">✕</button>Selectat: <strong style="color:${it.color||cfg.accent}">${esc(it.label)}</strong> <span class="muted">${esc(it.service_name||'')}</span></div><div class="selbar-btns">`;
    h += `<button class="btn" data-a="recall">🔁 Recheama</button>`;
    h += `<button class="btn" data-a="serving">▶️ In servire</button>`;
    h += `<button class="btn btn-primary" data-a="finish">✔️ Finalizat</button>`;
    h += `<button class="btn btn-danger" data-a="noshow">✖️ Neprezentat</button>`;
    h += `</div>`;
    if(cfg.services && cfg.services.length){
      h += `<div class="field" style="margin:.6rem 0 0"><label>Transfera catre serviciu</label><select data-a="transfer"><option value="">— alege serviciu —</option>`+
        cfg.services.map(s=>`<option value="${s.id}">${esc(s.prefix+' · '+s.name)}</option>`).join('')+`</select></div>`;
    }
    if(cfg.counters && cfg.counters.length){
      h += `<div class="field" style="margin:.5rem 0 0"><label>Transfera la alt birou (ghiseu)</label><select data-a="transfer-counter"><option value="">— alege birou —</option>`+
        cfg.counters.map(c=>`<option value="${c.id}">${esc(c.code+' · '+c.name)}</option>`).join('')+`</select></div>`;
    }
    if(it.form_data){ try{ const d=JSON.parse(it.form_data);
      if(Array.isArray(d)&&d.length) h+='<div class="selform">'+d.map(x=>`<div><span class="muted">${esc(x.label||'')}</span> <strong>${esc(x.value||'—')}</strong></div>`).join('')+'</div>';
    }catch(e){} }
    h += `<div class="field" style="margin:.6rem 0 0"><label>Notă pe bilet</label>`+
      `<div style="display:flex;gap:.4rem"><input type="text" data-note maxlength="255" value="${esc(it.note||'')}" placeholder="ex: revine cu acte">`+
      `<button class="btn" data-a="save-note" style="white-space:nowrap">💾 Salvează</button></div></div>`;
    elBar.innerHTML=h; elBar.style.display='';
  }
  function renderCurForm(c){
    let html='';
    if(c && c.form_data){ try{ const d=JSON.parse(c.form_data);
      if(Array.isArray(d) && d.length){ html='<div style="background:#f8fafc;border:1px solid #e6e8ec;border-radius:10px;padding:.7rem;margin:.2rem 0 1rem;text-align:left">'+
        '<div class="muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem">Date client</div>'+
        d.map(x=>`<div style="display:flex;justify-content:space-between;gap:1rem;padding:.18rem 0;border-bottom:1px solid #eef1f5"><span class="muted">${esc(x.label)}</span><strong>${esc(x.value||'—')}</strong></div>`).join('')+'</div>'; }
    }catch(e){} }
    if(elForm) elForm.innerHTML=html;
  }

  /* re-deseneaza DOAR ce s-a schimbat (ca sa nu inchida dropdown-ul de transfer la refresh) */
  function syncUI(force){
    const sig = items.map(t=>t.id+','+(t.status||'w')+','+(t.priority?1:0)+','+(t.targeted?1:0)+','+(t.over?1:0)).join('|')+'#'+selId;
    if(force || sig!==lastSig){ renderList(); lastSig=sig; }
    // meniul e identic indiferent de status -> reconstruim doar la schimbarea selectiei
    const barKey = selId===null ? '' : String(selId);
    if(force || barKey!==lastBarKey){ renderBar(); lastBarKey=barKey; }
  }

  function renderNoShow(list){
    const box=document.getElementById('nsBox'), wrap=document.getElementById('nsList');
    if(!box||!wrap) return;
    if(!list.length){ box.style.display='none'; wrap.innerHTML=''; return; }
    box.style.display='';
    wrap.innerHTML=list.map(t=>`<button class="btn btn-ghost" data-requeue="${t.id}" title="Repune ${esc(t.label)} la rand" style="font-size:.85rem">
      ↩ <strong style="color:${esc(t.color||cfg.accent)}">${esc(t.label)}</strong> <span class="muted">${esc(t.at||'')}</span></button>`).join('');
  }
  document.getElementById('nsList') && document.getElementById('nsList').addEventListener('click', async e=>{
    const b=e.target.closest('[data-requeue]'); if(!b) return;
    const id=+b.dataset.requeue; b.disabled=true;
    const r=await QMS.api('api/requeue',{ticket_id:id});
    if(r.ok){ QMS.toast('Bilet repus la rand','ok'); refresh(true); }
    else { QMS.toast(r.error||'Eroare','error'); b.disabled=false; }
  });

  async function refresh(force){
    const res = await QMS.api('api/counter-state?counter_id='+cfg.counterId, null, 'GET');
    if(!res.ok) return;
    const c = res.current;
    elCur.textContent = c ? c.label : '—';
    elCur.style.color = c ? (c.color||cfg.accent) : '#cbd1da';
    elSvc.textContent = c ? c.service_name : 'Niciun bilet in lucru';
    renderCurForm(c);

    const ni = [];
    if(c) ni.push({id:c.id,label:c.label,service_name:c.service_name,color:c.color,status:c.status,priority:c.priority,form_data:c.form_data,note:c.note});
    (res.waiting||[]).forEach(w=> ni.push({id:w.id,label:w.label,service_name:w.service_name,color:w.color,status:'waiting',priority:w.priority,form_data:w.form_data,note:w.note,targeted:!!w.target_counter_id,
      waited:+w.waited||0,target:+w.kpi_wait_sec||0,over:((+w.kpi_wait_sec||0)>0 && (+w.waited||0)>(+w.kpi_wait_sec||0))}));
    elWait.textContent = res.waiting_count;
    if(res.me){ const ms=document.getElementById('myStats');
      if(ms){ ms.style.display=''; ms.textContent='✔ '+res.me.served+' azi'+(res.me.avg_handle>0?' · '+mmss(res.me.avg_handle)+'/bon':''); } }
    renderNoShow(res.no_show||[]);
    notifyNew(res.waiting||[]);
    items = ni;
    if(selId && !items.find(x=>x.id===selId)) selId=null;
    syncUI(!!force);
  }

  refresh(true);
  var _busy=false;  // anti-suprapunere: nu porni un refresh nou cat timp altul e in curs (retele lente)
  setInterval(async function(){ if(_busy) return; _busy=true; try{ await refresh(false); } finally{ _busy=false; } }, 2000);
})();
