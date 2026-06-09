/* Consola operator: selectezi UN bilet din lista -> apar optiunile pentru el.
   + CHEAMA URMATORUL (auto), anunt vocal optional, status operator, notificari. */
(function(){
  const cfg = window.COUNTER || {};
  const elCur  = document.getElementById('curTicket');
  const elSvc  = document.getElementById('curSvc');
  const elForm = document.getElementById('curForm');
  const elWait = document.getElementById('waitCount');
  const elList = document.getElementById('qList');
  const elBar  = document.getElementById('selBar');
  const esc = s => String(s==null?'':s).replace(/[<>&"]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
  let selId = null;      // biletul selectat (un singur bilet)
  let items = [];        // bilete curente (in lucru + la rand)

  /* ---- notificari in browser (opt-in) ---- */
  let knownWaiting = null;
  if (cfg.notify && 'Notification' in window && Notification.permission === 'default') {
    const ask = () => { Notification.requestPermission(); document.removeEventListener('click', ask); };
    document.addEventListener('click', ask);
  }
  function notifyNew(list){
    if (!cfg.notify) return;
    const ids = list.map(t => t.id);
    if (knownWaiting === null) { knownWaiting = new Set(ids); return; }
    list.forEach(t => { if (!knownWaiting.has(t.id)) {
      if ('Notification' in window && Notification.permission === 'granted')
        try { new Notification('Bilet nou la rand: ' + t.label, { body: t.service_name||'', tag: 'qms-'+t.id }); } catch(e){}
      QMS.toast('Bilet nou: ' + t.label + ' — ' + (t.service_name||''), 'ok');
    }});
    knownWaiting = new Set(ids);
  }

  /* ---- anunt vocal optional la terminal ---- */
  let voices=[]; function loadVoices(){ voices = window.speechSynthesis ? speechSynthesis.getVoices() : []; }
  if(window.speechSynthesis){ loadVoices(); speechSynthesis.onvoiceschanged=loadVoices; }
  function speak(text){ if(!cfg.voiceOn||!window.speechSynthesis)return; const u=new SpeechSynthesisUtterance(text);
    u.lang=cfg.voice||'ro-RO'; const v=voices.find(v=>v.lang&&v.lang.toLowerCase().startsWith((cfg.voice||'ro').slice(0,2).toLowerCase())); if(v)u.voice=v; u.rate=.95;
    try{speechSynthesis.cancel();speechSynthesis.speak(u);}catch(e){} }
  function ding(){ if(!cfg.voiceOn)return; try{ const ac=new(window.AudioContext||window.webkitAudioContext)(); const o=ac.createOscillator(),g=ac.createGain();
    o.connect(g);g.connect(ac.destination);o.frequency.value=880;g.gain.setValueAtTime(.0001,ac.currentTime);
    g.gain.exponentialRampToValueAtTime(.22,ac.currentTime+.02);g.gain.exponentialRampToValueAtTime(.0001,ac.currentTime+.5);o.start();o.stop(ac.currentTime+.5);}catch(e){} }
  function announce(t){ if(!cfg.voiceOn||!t)return; ding(); let phrase='';
    if(cfg.sayNumber)phrase+='Bonul '+String(t.label||'').split('').join(' ')+'. ';
    if(cfg.sayCounter&&cfg.counterName)phrase+='Va rugam prezentati-va la '+cfg.counterName+'.';
    const rep=Math.max(1,+(cfg.repeat||1)); let i=0; const fire=()=>{speak(phrase); if(++i<rep)setTimeout(fire,2600);}; setTimeout(fire,300); }

  /* ---- status operator ---- */
  async function setStatus(s){ const r=await QMS.api('api/user-status',{status:s}); if(r&&r.ok){ const sel=document.getElementById('opStatus'); if(sel)sel.value=s; } }
  window.qmsStatus = setStatus;
  if(cfg.myStatus==='offline'){ setStatus('available'); }

  /* ---- CHEAMA URMATORUL (auto, dupa prioritate/vechime) ---- */
  document.getElementById('btnCall').addEventListener('click', async ()=>{
    const res = await QMS.api('api/call-next', { counter_id: cfg.counterId });
    if(!res.ok){ QMS.toast(res.error||'Eroare','error'); return; }
    if(!res.ticket){ QMS.toast('Coada este goala',''); } else { selId=res.ticket.id; announce(res.ticket); }
    refresh();
  });

  /* ---- actiuni pe biletul SELECTAT ---- */
  async function act(path, extra, opt){
    if(!selId){ QMS.toast('Selecteaza un bilet',''); return; }
    const res = await QMS.api(path, Object.assign({ticket_id:selId}, extra||{}));
    if(!res.ok){ QMS.toast(res.error||'Eroare','error'); refresh(); return; }
    if(opt&&opt.announce){ const it=items.find(x=>x.id===selId)||res.ticket; announce(res.ticket||it); }
    if(opt&&opt.clear) selId=null;
    refresh();
  }
  elBar.addEventListener('click', e=>{ const b=e.target.closest('button[data-a]'); if(!b)return; const a=b.getAttribute('data-a');
    if(a==='call')    act('api/call-specific',{counter_id:cfg.counterId},{announce:true});
    else if(a==='recall')  act('api/recall',null,{announce:true});
    else if(a==='serving') act('api/serving');
    else if(a==='finish')  act('api/finish',null,{clear:true});
    else if(a==='noshow')  act('api/no-show',null,{clear:true});
  });
  elBar.addEventListener('change', e=>{ const s=e.target.closest('select[data-a="transfer"]'); if(s&&s.value){ act('api/transfer',{service_id:+s.value},{clear:true}); } });

  /* selectie din lista (un singur bilet) */
  elList.addEventListener('change', e=>{ const r=e.target.closest('input[name="tk"]'); if(r){ selId=+r.value; renderBar(); markSel(); } });

  function markSel(){ elList.querySelectorAll('.qrow').forEach(row=>row.classList.toggle('sel', +row.dataset.id===selId)); }

  function renderBar(){
    const it = items.find(x=>x.id===selId);
    if(!it){ elBar.style.display='none'; elBar.innerHTML=''; return; }
    const st = it.status || 'waiting';
    let h = `<div class="selbar-head">Selectat: <strong style="color:${it.color||cfg.accent}">${esc(it.label)}</strong> <span class="muted">${esc(it.service_name||'')}</span></div>`;
    h += `<div class="selbar-btns">`;
    if(st==='waiting'){
      h += `<button class="btn btn-primary" data-a="call">📣 Cheama</button>`;
    } else {
      h += `<button class="btn" data-a="recall">🔁 Recheama</button>`;
      if(st==='called') h += `<button class="btn" data-a="serving">▶️ In servire</button>`;
      h += `<button class="btn btn-primary" data-a="finish">✔️ Finalizat</button>`;
      h += `<button class="btn btn-danger" data-a="noshow">✖️ Neprezentat</button>`;
    }
    h += `</div>`;
    if(st!=='waiting' && cfg.services && cfg.services.length){
      h += `<div class="field" style="margin:.6rem 0 0"><label>Transfera catre serviciu</label><select data-a="transfer"><option value="">— alege serviciu —</option>`+
        cfg.services.map(s=>`<option value="${s.id}">${esc(s.prefix+' · '+s.name)}</option>`).join('')+`</select></div>`;
    }
    if(it.form_data){ try{ const d=JSON.parse(it.form_data);
      if(Array.isArray(d)&&d.length){ h+='<div class="selform">'+d.map(x=>`<div><span class="muted">${esc(x.label||'')}</span> <strong>${esc(x.value||'—')}</strong></div>`).join('')+'</div>'; }
    }catch(e){} }
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

  async function refresh(){
    const res = await QMS.api('api/counter-state?counter_id='+cfg.counterId, null, 'GET');
    if(!res.ok) return;
    const c = res.current;
    elCur.textContent = c ? c.label : '—';
    elCur.style.color = c ? (c.color||cfg.accent) : '#cbd1da';
    elSvc.textContent = c ? c.service_name : 'Niciun bilet in lucru';
    renderCurForm(c);

    items = [];
    if(c) items.push({id:c.id,label:c.label,service_name:c.service_name,color:c.color,status:c.status,priority:c.priority,form_data:c.form_data});
    (res.waiting||[]).forEach(w=> items.push({id:w.id,label:w.label,service_name:w.service_name,color:w.color,status:'waiting',priority:w.priority,form_data:w.form_data}));

    elWait.textContent = res.waiting_count;
    notifyNew(res.waiting||[]);
    if(selId && !items.find(x=>x.id===selId)) selId=null;

    const stLab={waiting:'la rand',called:'apelat',serving:'in servire'};
    elList.innerHTML = items.map(t=>`<label class="qrow${t.id===selId?' sel':''}" data-id="${t.id}">
      <input type="radio" name="tk" value="${t.id}" ${t.id===selId?'checked':''}>
      <span class="tag" style="background:${t.color}">${esc((t.label||'?')[0])}</span>
      <span class="lbl">${esc(t.label)}</span>
      ${t.priority? '<span class="pill" style="background:#fee2e2;color:#b91c1c">PRIORITAR</span>':''}
      <span class="pill st-${t.status||'waiting'}">${stLab[t.status]||'la rand'}</span>
      <span class="muted" style="margin-left:auto">${esc(t.service_name||'')}</span></label>`).join('')
      || '<div class="muted" style="padding:1rem">Niciun bilet.</div>';
    renderBar();
  }

  refresh();
  setInterval(refresh, 2000);
})();
