/* Afisaj TV: randare din layout (editor canvas) SAU implicit + anunt vocal RO */
(function(){
  const cfg = window.DISPLAY || {};
  const hasLayout = !!(cfg.layout && cfg.layout.screens && cfg.layout.screens.length);
  const stage = document.getElementById('stage');
  let lastState = {called:[],waiting:[],counters:[],last:null};
  let lastCalledSig = null, curScreen = 0, rotTimer = null;
  // widget-uri ce depind de starea cozii (se redeseneaza la fiecare update); restul o singura data
  const LIVE = {now_serving:1, last_called:1, called_list:1, waiting_list:1, tickets_grid:1};
  let wTimers = []; // timere per-ecran (playlist/vreme), curatate la schimbarea ecranului

  /* ---------- voce / sunet ---------- */
  let voices=[]; function loadVoices(){ voices = window.speechSynthesis ? speechSynthesis.getVoices() : []; }
  if(window.speechSynthesis){ loadVoices(); speechSynthesis.onvoiceschanged=loadVoices; }
  function speak(text){ if(!window.speechSynthesis)return; const u=new SpeechSynthesisUtterance(text);
    u.lang=cfg.voice||'ro-RO'; const v=voices.find(v=>v.lang&&v.lang.toLowerCase().startsWith((cfg.voice||'ro').slice(0,2).toLowerCase())); if(v)u.voice=v; u.rate=.95;
    try{speechSynthesis.cancel();speechSynthesis.speak(u);}catch(e){} }
  function ding(){ try{ const ac=new(window.AudioContext||window.webkitAudioContext)(); const o=ac.createOscillator(),g=ac.createGain();
    o.connect(g);g.connect(ac.destination);o.frequency.value=880;g.gain.setValueAtTime(.0001,ac.currentTime);
    g.gain.exponentialRampToValueAtTime(.25,ac.currentTime+.02);g.gain.exponentialRampToValueAtTime(.0001,ac.currentTime+.5);o.start();o.stop(ac.currentTime+.5);}catch(e){} }
  const spellLabel=l=>String(l||'').split('').join(' ');
  function announce(t){
    let phrase='';
    if(cfg.sayNumber)phrase+='Bonul '+spellLabel(t.label)+'. ';
    if(cfg.sayCounter&&(t.counter_code||t.counter_name))phrase+='Vă rugăm să vă prezentați la '+(t.counter_name||('ghiseul '+t.counter_code))+'.';
    if(!phrase.trim()||!window.speechSynthesis){ ding(); return; }
    try{ speechSynthesis.cancel(); }catch(e){}   // o singura curatare, la inceput
    ding();
    const rep=Math.max(1,+(cfg.repeat||1)); let i=0;
    const v=voices.find(v=>v.lang&&v.lang.toLowerCase().startsWith((cfg.voice||'ro').slice(0,2).toLowerCase()));
    // repeta DOAR dupa ce s-a terminat citirea precedenta (fara sa o taie la mijloc)
    const fire=()=>{ if(i>=rep)return; i++;
      const u=new SpeechSynthesisUtterance(phrase); u.lang=cfg.voice||'ro-RO'; if(v)u.voice=v; u.rate=.95;
      u.onend=()=>{ if(i<rep) setTimeout(fire,700); }; u.onerror=()=>{ if(i<rep) setTimeout(fire,700); };
      try{ speechSynthesis.speak(u); }catch(e){} };
    setTimeout(fire,450);
  }
  function announceIfNew(state){ const last=state.last; if(!last)return;
    const sig=last.id+':'+(last.recall_count||0)+':'+last.called_at;
    if(sig!==lastCalledSig){ lastCalledSig=sig; flashNow(); announce(last); } }
  function flashNow(){ document.querySelectorAll('.lw-now,.tv-now').forEach(n=>{n.style.transition='none';n.style.boxShadow='0 0 0 6px #fff';setTimeout(()=>{n.style.transition='box-shadow .6s';n.style.boxShadow='none';},120);}); }

  function clockStr(mode){ const d=new Date(); const t=d.toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit'});
    const dt=d.toLocaleDateString('ro-RO',{weekday:'long',day:'numeric',month:'long'});
    return mode==='time'?t:mode==='date'?dt:{t,dt}; }

  /* ======================= MOD LAYOUT ======================= */
  function buildScreen(i){
    wTimers.forEach(t=>clearInterval(t)); wTimers=[]; // opreste timerele ecranului precedent
    curScreen=i; const s=cfg.layout.screens[i]; stage.style.background=s.bg||'#0b0d12'; stage.innerHTML='';
    (s.widgets||[]).forEach(w=>{ const el=document.createElement('div');
      el.className='lw lw-'+w.type;
      el.style.cssText=`position:absolute;left:${w.x}%;top:${w.y}%;width:${w.w}%;height:${w.h}%;overflow:hidden;color:#fff;border-radius:14px`;
      if(w.props&&w.props.bg)el.style.background=w.props.bg;
      el.__w=w; stage.appendChild(el); paintWidget(el,w,lastState); });
  }
  // la update de stare redesenam DOAR widget-urile live (restul raman asezate o data)
  function renderLayout(state){ lastState=state; stage.querySelectorAll('.lw').forEach(el=>{ if(LIVE[el.__w.type]) paintWidget(el,el.__w,state); }); }

  /* multi-limba pe etichete: scrii "Text RO | Text EN" si textele alterneaza la 6s */
  let langCycle = 0;
  const MLKEYS = ['label','title','text','caption'];
  const pickML = s => { if(typeof s!=='string' || s.indexOf('|')===-1) return s;
    const parts = s.split('|').map(x=>x.trim()).filter(Boolean);
    return parts.length ? parts[langCycle % parts.length] : s; };
  function startLangCycle(){
    setInterval(()=>{
      const els = Array.prototype.filter.call(stage.querySelectorAll('.lw'), el=>{
        const p = (el.__w && el.__w.props) || {};
        return MLKEYS.some(k => typeof p[k]==='string' && p[k].indexOf('|')!==-1);
      });
      if(!els.length) return;
      langCycle++;
      els.forEach(el=>paintWidget(el, el.__w, lastState));
    }, 6000);
  }

  function paintWidget(el,w,state){
    const H=el.clientHeight||100, p=w.props||{}, accent=cfg.accent||'#2563eb';
    switch(w.type){
      case 'last_called':
      case 'now_serving': {
        const last=state.last; el.style.background=p.bg||`linear-gradient(135deg,${accent},${accent}66)`;
        el.style.display='flex';el.style.flexDirection='column';el.style.alignItems='center';el.style.justifyContent='center';el.style.textAlign='center';
        el.innerHTML=`<div style="opacity:.85;font-weight:700;text-transform:uppercase;letter-spacing:.06em;font-size:${Math.max(11,H*.09)}px">${esc(pickML(p.label||'Se serveste bonul'))}</div>
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:800;line-height:.95;font-size:${H*.46}px">${last?esc(last.label):'—'}</div>
          <div style="font-weight:800;font-size:${H*.14}px">${last?esc(last.counter_name||last.counter_code||''):''}</div>`;
        break; }
      case 'called_list': {
        const rows=(state.called||[]).slice(0,+(p.rows||6)); const rh=Math.max(16,(H-30)/Math.max(1,(p.rows||6)));
        el.style.background=p.bg||'#11141b';el.style.padding='10px';el.style.display='block';
        el.innerHTML=`<div style="color:#7d8696;text-transform:uppercase;letter-spacing:.06em;font-size:${Math.max(11,H*.08)}px;margin-bottom:6px">${esc(pickML(p.title||'Ultimele apelate'))}</div>`+
          rows.map(c=>`<div style="display:flex;justify-content:space-between;align-items:center;height:${rh}px;border-bottom:1px solid #1c2029">
            <b style="font-family:'Bricolage Grotesque';font-size:${rh*.55}px">${esc(c.label)}</b>
            <span style="color:${accent};font-weight:800;font-size:${rh*.42}px">${esc(c.counter_name||c.counter_code||'')}</span></div>`).join('');
        break; }
      case 'waiting_list': {
        const rows=state.waiting||[]; const rh=Math.max(18,(H-30)/Math.max(1,rows.length||1));
        el.style.background=p.bg||'#11141b';el.style.padding='10px';el.style.display='block';
        el.innerHTML=`<div style="color:#7d8696;text-transform:uppercase;letter-spacing:.06em;font-size:${Math.max(11,H*.07)}px;margin-bottom:6px">${esc(pickML(p.title||'La rand'))}</div>`+
          rows.map(wi=>`<div style="display:flex;align-items:center;gap:8px;height:${rh}px;border-bottom:1px solid #1c2029">
            <span style="background:${esc(wi.color)};width:${rh*.6}px;height:${rh*.6}px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:${rh*.34}px">${esc(wi.prefix)}</span>
            <span style="flex:1;font-size:${rh*.36}px">${esc(wi.name)}</span>
            <b style="font-family:'Bricolage Grotesque';font-size:${rh*.5}px">${wi.cnt}</b></div>`).join('');
        break; }
      case 'clock': {
        el.style.display='flex';el.style.alignItems='center';el.style.justifyContent='center';el.style.flexDirection='column';
        el.style.fontFamily="'Bricolage Grotesque',sans-serif";el.style.fontWeight='700';
        const c=clockStr(p.mode||'full');
        if(typeof c==='string'){ el.innerHTML=`<div style="font-size:${H*.5}px">${c}</div>`; }
        else { el.innerHTML=`<div style="font-size:${H*.42}px">${c.t}</div><div style="font-size:${H*.13}px;opacity:.7;text-transform:capitalize">${c.dt}</div>`; }
        break; }
      case 'text': {
        el.style.display='flex';el.style.alignItems='center';el.style.justifyContent='center';el.style.textAlign='center';el.style.fontWeight='700';
        const fs=(p.size||24);
        if(p.marquee){ el.style.justifyContent='flex-start'; el.innerHTML=`<div style="white-space:nowrap;animation:lwm 12s linear infinite;font-size:${fs}px">${esc(pickML(p.text||''))}</div>`; ensureMarquee(); }
        else el.innerHTML=`<div style="font-size:${fs}px">${esc(pickML(p.text||''))}</div>`;
        break; }
      case 'image': {
        el.style.padding='0';
        el.innerHTML=p.url?`<img src="${esc(p.url)}" style="width:100%;height:100%;object-fit:${p.fit||'cover'}">`:'';
        break; }
      case 'tickets_grid': {
        el.style.background=p.bg||'transparent';el.style.padding='6px';el.style.display='flex';el.style.flexDirection='column';el.style.gap=Math.max(3,H*.012)+'px';
        const svcs=(state.waiting||[]).slice(0,+(p.rows||6));
        const byPrefix={}; (state.called||[]).forEach(c=>{ if(!byPrefix[c.prefix]) byPrefix[c.prefix]=c; });
        const rh=Math.max(26,(H-(p.title?26:8))/Math.max(1,svcs.length||1)-(H*.012));
        el.innerHTML=(p.title?`<div style="color:#9aa3b2;text-transform:uppercase;letter-spacing:.06em;font-size:${Math.max(11,H*.05)}px;margin-bottom:4px">${esc(pickML(p.title))}</div>`:'')+
          (svcs.length?svcs.map(s=>{ const c=byPrefix[s.prefix]; const lab=c?c.label:'—'; const ctr=c?(c.counter_code||c.counter_name||''):'';
            return `<div style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.05);border-left:5px solid ${esc(s.color)};border-radius:8px;padding:0 12px;height:${rh}px">
              <span style="opacity:.45;font-family:'Bricolage Grotesque';font-weight:800;font-size:${rh*.5}px;min-width:${rh*.8}px;text-align:center">${s.cnt}</span>
              ${p.show_counter!==false?`<span style="opacity:.7;font-weight:700;font-size:${rh*.3}px;min-width:${rh}px">${esc(ctr)}</span>`:''}
              <span style="flex:1;font-weight:700;font-size:${rh*.4}px;line-height:1.05">${esc(s.name)}</span>
              <b style="color:${esc(s.color)};font-family:'Bricolage Grotesque';font-size:${rh*.6}px">${esc(lab)}</b></div>`; }).join('')
            :'<div style="opacity:.4;padding:1rem">Niciun serviciu activ.</div>');
        break; }
      case 'people_counting': {
        el.style.display='flex';el.style.flexDirection='column';el.style.alignItems='center';el.style.justifyContent='center';el.style.textAlign='center';el.style.background=p.bg||'#11141b';
        el.innerHTML=`<div style="opacity:.7;text-transform:uppercase;letter-spacing:.05em;font-size:${Math.max(11,H*.1)}px">${esc(pickML(p.label||'Persoane inauntru'))}</div>
          <div style="font-family:'Bricolage Grotesque';font-weight:800;font-size:${H*.4}px;line-height:1.05">${(+p.inside||0)} <span style="opacity:.45">/ ${(+p.capacity||0)}</span></div>`;
        break; }
      case 'qr_code': {
        el.style.display='flex';el.style.flexDirection='column';el.style.alignItems='center';el.style.justifyContent='center';el.style.background=p.bg||'#ffffff';el.style.padding='8px';
        const data=encodeURIComponent(p.url||location.origin);
        const sz=Math.max(60,Math.min(el.clientWidth,el.clientHeight)-(p.caption?34:14));
        el.innerHTML=`<img alt="QR" src="${QMS.base()}/qr?size=${sz}&data=${data}" style="width:${sz}px;height:${sz}px">`+
          (p.caption?`<div style="color:#111;font-weight:700;margin-top:6px;text-align:center;font-size:${Math.max(11,H*.07)}px">${esc(pickML(p.caption))}</div>`:'');
        break; }
      case 'iframe': {
        el.style.padding='0';
        el.innerHTML=p.url?`<iframe src="${esc(p.url)}" style="width:100%;height:100%;border:0" referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-popups"></iframe>`
          :'<div style="color:#5b6270;display:flex;align-items:center;justify-content:center;height:100%">Adauga un URL</div>';
        break; }
      case 'ticker': {
        el.style.background=p.bg||'#000';el.style.display='flex';el.style.alignItems='center';el.style.overflow='hidden';
        const sp=Math.max(5,+(p.speed||18));
        el.innerHTML=`<div style="white-space:nowrap;font-weight:700;font-size:${Math.max(14,H*.5)}px;animation:lwm ${sp}s linear infinite">${esc(pickML(p.text||''))}</div>`;
        ensureMarquee(); break; }
      case 'form': {
        el.style.display='flex';el.style.flexDirection='column';el.style.alignItems='center';el.style.justifyContent='center';el.style.textAlign='center';el.style.background=p.bg||'#11141b';el.style.padding='10px';
        const link=p.url||(QMS.base()+'/feedback?branch='+(cfg.branch||1));
        const sz=Math.max(60,Math.min(el.clientWidth,el.clientHeight)-70);
        el.innerHTML=`<div style="font-weight:700;margin-bottom:8px;font-size:${Math.max(12,H*.09)}px">${esc(pickML(p.title||'Spune-ne parerea ta'))}</div>
          <img alt="QR" src="${QMS.base()}/qr?size=${sz}&data=${encodeURIComponent(link)}" style="width:${sz}px;height:${sz}px;background:#fff;border-radius:6px;padding:4px">
          <div style="margin-top:8px;color:#f5b301;font-size:${Math.max(13,H*.1)}px">★★★★★</div>
          <div style="margin-top:2px;opacity:.75;font-size:${Math.max(10,H*.06)}px">Scaneaza pentru a evalua</div>`;
        break; }
      case 'weather': { paintWeather(el,p,H); break; }
      case 'playlist': { paintPlaylist(el,p); break; }
    }
  }
  function paintWeather(el,p,H){
    el.style.display='flex';el.style.flexDirection='column';el.style.alignItems='center';el.style.justifyContent='center';el.style.textAlign='center';el.style.background=p.bg||'#11141b';
    el.innerHTML=`<div style="opacity:.7;font-size:${Math.max(11,H*.12)}px">${esc(p.city||'')}</div><div class="wx" style="font-family:'Bricolage Grotesque';font-weight:800;font-size:${H*.34}px;line-height:1.1">…</div>`;
    const wx=el.querySelector('.wx');
    const load=()=>{ const url=`https://api.open-meteo.com/v1/forecast?latitude=${encodeURIComponent(p.lat||'44.43')}&longitude=${encodeURIComponent(p.lon||'26.10')}&current=temperature_2m&daily=temperature_2m_max,temperature_2m_min&timezone=auto`;
      fetch(url).then(r=>r.json()).then(d=>{ const t=Math.round(d&&d.current&&d.current.temperature_2m);
        const mx=Math.round(d&&d.daily&&d.daily.temperature_2m_max&&d.daily.temperature_2m_max[0]);
        const mn=Math.round(d&&d.daily&&d.daily.temperature_2m_min&&d.daily.temperature_2m_min[0]);
        wx.innerHTML=`${isNaN(t)?'--':t}°C`+(isNaN(mx)?'':` <span style="font-size:.45em;opacity:.7">${mx}° / ${mn}°</span>`); })
        .catch(()=>{ wx.textContent='--°C'; }); };
    load(); wTimers.push(setInterval(load, 30*60*1000));
  }
  function paintPlaylist(el,p){
    el.style.padding='0'; el.style.background='#000';
    const items=String(p.items||'').split(/[\n,]+/).map(s=>s.trim()).filter(Boolean);
    if(!items.length){ el.innerHTML='<div style="color:#5b6270;display:flex;align-items:center;justify-content:center;height:100%">Playlist gol</div>'; return; }
    let i=0; const fit=p.fit||'cover';
    const show=()=>{ el.innerHTML=`<img src="${esc(items[i])}" style="width:100%;height:100%;object-fit:${fit}">`; i=(i+1)%items.length; };
    show(); if(items.length>1) wTimers.push(setInterval(show, Math.max(2,+(p.interval||6))*1000));
  }
  let marqueeAdded=false; function ensureMarquee(){ if(marqueeAdded)return; marqueeAdded=true;
    const s=document.createElement('style'); s.textContent='@keyframes lwm{from{transform:translateX(100%)}to{transform:translateX(-100%)}}'; document.head.appendChild(s); }
  function startRotation(){ if(cfg.layout.screens.length<2)return;
    const next=()=>{ const dur=(cfg.layout.screens[curScreen].duration||15)*1000;
      rotTimer=setTimeout(()=>{ buildScreen((curScreen+1)%cfg.layout.screens.length); renderLayout(lastState); next(); },dur); }; next(); }

  /* ======================= MOD IMPLICIT ======================= */
  function renderLegacy(state){
    const last=state.last, el=id=>document.getElementById(id);
    if(last){ el('nowNum').textContent=last.label; el('nowCtr').textContent=last.counter_name||(last.counter_code?('Ghiseu '+last.counter_code):''); }
    else { el('nowNum').textContent='—'; el('nowCtr').textContent=''; }
    el('callList').innerHTML=(state.called||[]).map(c=>`<div class="tv-call"><span class="l">${esc(c.label)}</span><span class="c">${esc(c.counter_name||c.counter_code||'')}</span></div>`).join('');
    el('waitList').innerHTML=(state.waiting||[]).map(w=>`<div class="wrow"><span class="tag" style="background:${esc(w.color)}">${esc(w.prefix)}</span><span>${esc(w.name)}</span><span class="cnt">${w.cnt}</span></div>`).join('');
  }

  const esc=s=>String(s==null?'':s).replace(/[<>&"]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));

  /* anunt general (banner 📢 in josul ecranului) — apare/dispare live odata cu setarea */
  function renderNotice(state){
    const txt = (state && typeof state.notice === 'string') ? state.notice.trim() : '';
    let bar = document.getElementById('tvNotice');
    if(!txt){ if(bar) bar.remove(); return; }
    if(!bar){
      bar = document.createElement('div'); bar.id = 'tvNotice';
      bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:50;background:#3a2f12;color:#f5d98a;'
        + 'padding:1.2vh 2vw;text-align:center;font-weight:700;font-size:2.4vh;border-top:1px solid #5a4a1a';
      document.body.appendChild(bar);
    }
    const want = '📢 ' + txt;
    if(bar.textContent !== want) bar.textContent = want;
  }

  function render(state){ if(hasLayout)renderLayout(state); else renderLegacy(state); renderNotice(state); announceIfNew(state); }

  /* clock tick */
  setInterval(()=>{ if(hasLayout){ stage.querySelectorAll('.lw-clock').forEach(el=>paintWidget(el,el.__w,lastState)); }
    else { const c=document.getElementById('clock'); if(c){const s=clockStr('full'); c.textContent=s.t+'  ·  '+s.dt;} } },1000);

  /* conexiune date */
  function startSSE(){ try{ const es=new EventSource(QMS.base()+'/api/sse?branch='+cfg.branch);
    es.onmessage=e=>{try{render(JSON.parse(e.data));}catch(_){}}; es.onerror=()=>{es.close();startPolling();}; }catch(e){startPolling();} }
  let pollTimer=null; function startPolling(){ if(pollTimer)return;
    const tick=async()=>{const s=await QMS.api('api/state?branch='+cfg.branch,null,'GET'); if(s.ok)render(s);}; tick(); pollTimer=setInterval(tick,2000); }

  document.body.addEventListener('click',()=>{ if(window.speechSynthesis)speak(' '); },{once:true});

  /* init */
  if(hasLayout){ buildScreen(0); window.addEventListener('resize',()=>{ buildScreen(curScreen); renderLayout(lastState); }); startRotation(); startLangCycle(); }
  if(cfg.useSSE && !!window.EventSource) startSSE(); else startPolling();
})();
