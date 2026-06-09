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

  /* ---- Notificari in browser (opt-in din setarile utilizatorului) ---- */
  let knownWaiting = null; // set cu biletele deja vazute la rand (null = prima incarcare)
  if (cfg.notify && 'Notification' in window && Notification.permission === 'default') {
    // cerem permisiunea la prima interactiune cu pagina
    const ask = () => { Notification.requestPermission(); document.removeEventListener('click', ask); };
    document.addEventListener('click', ask);
  }
  function notifyNew(list){
    if (!cfg.notify) return;
    const ids = list.map(t => t.id);
    if (knownWaiting === null) { knownWaiting = new Set(ids); return; } // nu anunta la prima incarcare
    list.forEach(t => {
      if (!knownWaiting.has(t.id)) {
        if ('Notification' in window && Notification.permission === 'granted') {
          try { new Notification('Bilet nou la rand: ' + t.label, { body: t.service_name + (t.priority ? ' · PRIORITAR' : ''), tag: 'qms-'+t.id }); } catch(e){}
        }
        QMS.toast('Bilet nou: ' + t.label + ' — ' + t.service_name, 'ok');
      }
    });
    knownWaiting = new Set(ids);
  }

  document.getElementById('btnCall').addEventListener('click', callNext);
  bind('btnRecall', async ()=>{ await act('api/recall'); if(currentId) announce({label:elCur.textContent}); });
  bind('btnServing', ()=> act('api/serving'));
  bind('btnFinish', ()=> act('api/finish', true));
  bind('btnNoShow', ()=> act('api/no-show', true));
  function bind(id, fn){ const b=document.getElementById(id); if(b) b.addEventListener('click', fn); }

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

  async function callNext(){
    const res = await QMS.api('api/call-next', { counter_id: cfg.counterId });
    if(!res.ok){ QMS.toast(res.error||'Eroare','error'); return; }
    if(!res.ticket){ QMS.toast('Coada este goala','' ); }
    else announce(res.ticket);
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

  /* apeleaza un bilet ANUME (ales de operator din lista) */
  async function callSpecific(id){
    const res = await QMS.api('api/call-specific', { ticket_id: id, counter_id: cfg.counterId });
    if(!res.ok){ QMS.toast(res.error||'Eroare','error'); refresh(); return; }
    if(res.ticket) announce(res.ticket);
    refresh();
  }
  // delegare: click pe un rand din coada => cheama acel bilet
  if(elList) elList.addEventListener('click', function(e){
    const row = e.target.closest('.qrow'); if(!row || !row.dataset.id) return;
    callSpecific(+row.dataset.id);
  });

  /* status operator (prezenta) */
  async function setStatus(s){ const r=await QMS.api('api/user-status',{status:s}); if(r&&r.ok){ const sel=document.getElementById('opStatus'); if(sel)sel.value=s; } }
  window.qmsStatus = setStatus;
  // la deschiderea terminalului, daca era offline, devine Disponibil
  if(cfg.myStatus==='offline'){ setStatus('available'); }

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
    notifyNew(res.waiting || []);
    elList.innerHTML = res.waiting.map(t=>`<div class="qrow" data-id="${t.id}" title="Apasa pentru a chema acest bilet">
      <span class="tag" style="background:${t.color}">${t.label[0]}</span>
      <span class="lbl">${t.label}</span>
      ${t.priority? '<span class="pill" style="background:#fee2e2;color:#b91c1c">PRIORITAR</span>':''}
      <span class="muted" style="margin:0 .6rem 0 auto">${esc(t.service_name)}</span>
      <span class="callbtn">Cheama →</span></div>`).join('')
      || '<div class="muted" style="padding:1rem">Coada este goala.</div>';
  }
  refresh();
  setInterval(refresh, 2000);
})();
