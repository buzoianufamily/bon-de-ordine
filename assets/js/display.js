/* Afisaj TV: randare din layout (editor canvas) SAU implicit + anunt vocal RO */
(function(){
  const cfg = window.DISPLAY || {};
  const hasLayout = !!(cfg.layout && cfg.layout.screens && cfg.layout.screens.length);
  const stage = document.getElementById('stage');
  let lastState = {called:[],waiting:[],counters:[],last:null};
  let lastCalledSig = null, curScreen = 0, rotTimer = null;

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
  function announce(t){ ding(); let phrase='';
    if(cfg.sayNumber)phrase+='Bonul '+spellLabel(t.label)+'. ';
    if(cfg.sayCounter&&(t.counter_code||t.counter_name))phrase+='Va rugam prezentati-va la '+(t.counter_name||('ghiseul '+t.counter_code))+'.';
    const rep=+(cfg.repeat||1); let i=0; const fire=()=>{speak(phrase); if(++i<rep)setTimeout(fire,2600);}; setTimeout(fire,450); }
  function announceIfNew(state){ const last=state.last; if(!last)return;
    const sig=last.id+':'+(last.recall_count||0)+':'+last.called_at;
    if(sig!==lastCalledSig){ lastCalledSig=sig; flashNow(); announce(last); } }
  function flashNow(){ document.querySelectorAll('.lw-now,.tv-now').forEach(n=>{n.style.transition='none';n.style.boxShadow='0 0 0 6px #fff';setTimeout(()=>{n.style.transition='box-shadow .6s';n.style.boxShadow='none';},120);}); }

  function clockStr(mode){ const d=new Date(); const t=d.toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit'});
    const dt=d.toLocaleDateString('ro-RO',{weekday:'long',day:'numeric',month:'long'});
    return mode==='time'?t:mode==='date'?dt:{t,dt}; }

  /* ======================= MOD LAYOUT ======================= */
  function buildScreen(i){
    curScreen=i; const s=cfg.layout.screens[i]; stage.style.background=s.bg||'#0b0d12'; stage.innerHTML='';
    (s.widgets||[]).forEach(w=>{ const el=document.createElement('div');
      el.className='lw lw-'+w.type;
      el.style.cssText=`position:absolute;left:${w.x}%;top:${w.y}%;width:${w.w}%;height:${w.h}%;overflow:hidden;color:#fff;border-radius:14px`;
      if(w.props&&w.props.bg)el.style.background=w.props.bg;
      el.__w=w; stage.appendChild(el); paintWidget(el,w,lastState); });
  }
  function renderLayout(state){ lastState=state; stage.querySelectorAll('.lw').forEach(el=>paintWidget(el,el.__w,state)); }

  function paintWidget(el,w,state){
    const H=el.clientHeight||100, p=w.props||{}, accent=cfg.accent||'#2563eb';
    switch(w.type){
      case 'now_serving': {
        const last=state.last; el.style.background=p.bg||`linear-gradient(135deg,${accent},${accent}66)`;
        el.style.display='flex';el.style.flexDirection='column';el.style.alignItems='center';el.style.justifyContent='center';el.style.textAlign='center';
        el.innerHTML=`<div style="opacity:.85;font-weight:700;text-transform:uppercase;letter-spacing:.06em;font-size:${Math.max(11,H*.09)}px">${esc(p.label||'Se serveste bonul')}</div>
          <div style="font-family:'Bricolage Grotesque',sans-serif;font-weight:800;line-height:.95;font-size:${H*.46}px">${last?esc(last.label):'—'}</div>
          <div style="font-weight:800;font-size:${H*.14}px">${last?esc(last.counter_name||last.counter_code||''):''}</div>`;
        break; }
      case 'called_list': {
        const rows=(state.called||[]).slice(0,+(p.rows||6)); const rh=Math.max(16,(H-30)/Math.max(1,(p.rows||6)));
        el.style.background=p.bg||'#11141b';el.style.padding='10px';el.style.display='block';
        el.innerHTML=`<div style="color:#7d8696;text-transform:uppercase;letter-spacing:.06em;font-size:${Math.max(11,H*.08)}px;margin-bottom:6px">${esc(p.title||'Ultimele apelate')}</div>`+
          rows.map(c=>`<div style="display:flex;justify-content:space-between;align-items:center;height:${rh}px;border-bottom:1px solid #1c2029">
            <b style="font-family:'Bricolage Grotesque';font-size:${rh*.55}px">${esc(c.label)}</b>
            <span style="color:${accent};font-weight:800;font-size:${rh*.42}px">${esc(c.counter_name||c.counter_code||'')}</span></div>`).join('');
        break; }
      case 'waiting_list': {
        const rows=state.waiting||[]; const rh=Math.max(18,(H-30)/Math.max(1,rows.length||1));
        el.style.background=p.bg||'#11141b';el.style.padding='10px';el.style.display='block';
        el.innerHTML=`<div style="color:#7d8696;text-transform:uppercase;letter-spacing:.06em;font-size:${Math.max(11,H*.07)}px;margin-bottom:6px">${esc(p.title||'La rand')}</div>`+
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
        if(p.marquee){ el.style.justifyContent='flex-start'; el.innerHTML=`<div style="white-space:nowrap;animation:lwm 12s linear infinite;font-size:${fs}px">${esc(p.text||'')}</div>`; ensureMarquee(); }
        else el.innerHTML=`<div style="font-size:${fs}px">${esc(p.text||'')}</div>`;
        break; }
      case 'image': {
        el.style.padding='0';
        el.innerHTML=p.url?`<img src="${esc(p.url)}" style="width:100%;height:100%;object-fit:${p.fit||'cover'}">`:'';
        break; }
    }
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
  function render(state){ if(hasLayout)renderLayout(state); else renderLegacy(state); announceIfNew(state); }

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
  if(hasLayout){ buildScreen(0); window.addEventListener('resize',()=>renderLayout(lastState)); startRotation(); }
  if(cfg.useSSE && !!window.EventSource) startSSE(); else startPolling();
})();
