/* Dispenser kiosk: serviciu -> [tip] -> [politica] -> [formular] -> emite -> afiseaza/printeaza */
(function(){
  const cfg = window.DISPENSER || {};
  cfg.popup = cfg.popup || {}; cfg.texts = cfg.texts || {}; cfg.print = cfg.print || {}; cfg.forms = cfg.forms || {};
  const $ = id => document.getElementById(id);
  const overlay=$('overlay'), modal=$('modal'), modalBox=$('modalBox');
  const esc=s=>String(s==null?'':s).replace(/[<>&"]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
  // pastreaza limba aleasa la dispenser pe pagina biletului digital (telefon)
  const vurl=u=>(cfg.lang && cfg.lang!=='ro' && u) ? (u+(u.indexOf('?')>-1?'&':'?')+'lang='+encodeURIComponent(cfg.lang)) : u;
  let timer=null;

  // delegare pe document: functioneaza si pentru butoanele statice (kiosk clasic),
  // si pentru cele randate dinamic de editorul canvas (widget „Grila servicii")
  document.addEventListener('click', e=>{
    const pr = e.target.closest('.prio-btn');
    if(pr){ const b=pr.closest('.svc-btn'); if(b && !b.disabled){ e.stopPropagation(); startFlow(b,true); } return; }
    const btn = e.target.closest('.svc-btn');
    if(btn && !btn.disabled) startFlow(btn,false);
  });

  function openModal(html){ modalBox.innerHTML=html; modal.classList.add('show'); }
  function closeModal(){ modal.classList.remove('show'); detachKeyboard(); }

  // flux complet, secvential
  async function startFlow(btn, forced){
    let priority = !!forced;
    if(cfg.popup.ask_type && btn.dataset.priority==='1' && !forced){
      const r = await askType(btn); if(r===null) return; priority=r;
    }
    if(priority && cfg.popup.policy_enabled){ const ok = await askPolicy(); if(!ok) return; }
    let formData=null;
    const form = cfg.forms[btn.dataset.id];
    if(form && form.fields && form.fields.length){ formData = await showForm(form); if(formData===null) return; }
    issue(btn, priority, formData);
  }

  function askType(btn){ return new Promise(res=>{
    openModal(`<div class="svc" style="margin-bottom:1.2rem">${esc(btn.dataset.name)}</div>
      <button class="btn btn-primary btn-lg" style="width:100%;margin-bottom:.6rem" id="mReg">${esc(cfg.popup.regular||'BILET NORMAL')}</button>
      <button class="btn btn-lg" style="width:100%" id="mPrio">${esc(cfg.popup.priority||'BILET PRIORITAR')}</button>
      <button class="btn btn-ghost" style="margin-top:.8rem" id="mCancel">${esc(cfg.popup.policy_cancel||'Anuleaza')}</button>`);
    $('mReg').onclick=()=>{closeModal();res(false);};
    $('mPrio').onclick=()=>{closeModal();res(true);};
    $('mCancel').onclick=()=>{closeModal();res(null);};
  });}

  function askPolicy(){ return new Promise(res=>{
    openModal(`<h3 style="margin-top:0">${esc(cfg.popup.policy_title||'')}</h3>
      <p class="muted" style="text-align:left">${esc(cfg.popup.policy_text||'')}</p>
      <label style="display:flex;gap:.5rem;text-align:left;margin:1rem 0"><input type="checkbox" id="mChk" style="width:auto"><span>${esc(cfg.popup.policy_checkbox||'Accept')}</span></label>
      <div style="display:flex;gap:.5rem"><button class="btn" style="flex:1" id="mNo">${esc(cfg.popup.policy_cancel||'Anuleaza')}</button>
      <button class="btn btn-primary" style="flex:1" id="mYes" disabled>${esc(cfg.popup.policy_ok||'Continua')}</button></div>`);
    $('mChk').onchange=e=>$('mYes').disabled=!e.target.checked;
    $('mNo').onclick=()=>{closeModal();res(false);};
    $('mYes').onclick=()=>{closeModal();res(true);};
  });}

  function fieldInput(f,i){
    const id='mf_'+i;
    if(f.type==='textarea') return `<textarea id="${id}" rows="2"></textarea>`;
    if(f.type==='checkbox') return `<label style="display:flex;gap:.5rem;align-items:center"><input type="checkbox" id="${id}" style="width:auto"> ${esc(f.label)}</label>`;
    if(f.type==='select') return `<select id="${id}"><option value="">— alege —</option>${(f.options||[]).map(o=>`<option>${esc(o)}</option>`).join('')}</select>`;
    return `<input type="${['tel','email','number'].includes(f.type)?f.type:'text'}" id="${id}">`;
  }
  function showForm(form){ return new Promise(res=>{
    const html = form.fields.map((f,i)=>{
      if(f.type==='checkbox') return `<div style="margin-bottom:.8rem">${fieldInput(f,i)}</div>`;
      return `<div style="margin-bottom:.8rem"><label style="display:block;font-weight:600;font-size:.85rem;margin-bottom:.3rem;text-align:left">${esc(f.label)}${f.required?' <span style="color:#dc2626">*</span>':''}</label>${fieldInput(f,i)}</div>`;
    }).join('');
    openModal(`<h3 style="margin-top:0">${esc(form.name||'Completati datele')}</h3>
      <div style="text-align:left;max-height:52vh;overflow:auto;padding:.2rem">${html}</div>
      <div style="display:flex;gap:.5rem;margin-top:1rem"><button class="btn" style="flex:1" id="mfCancel">${esc(cfg.texts.form_cancel||'Anuleaza')}</button>
      <button class="btn btn-primary" style="flex:1" id="mfOk">${esc(cfg.texts.form_submit||'Continua')}</button></div>`);
    $('mfOk').onclick=()=>{
      const data=[]; let valid=true;
      form.fields.forEach((f,i)=>{ const el=$('mf_'+i);
        let v = el.type==='checkbox' ? (el.checked?'Da':'Nu') : (el.value||'').trim();
        const empty = el.type==='checkbox' ? !el.checked : v==='';
        if(f.required && empty){ valid=false; el.style.borderColor='#f87171'; } else el.style.borderColor='';
        data.push({label:f.label, value:v});
      });
      if(!valid){ QMS.toast('Completati campurile obligatorii','error'); return; }
      closeModal(); res(data);
    };
    $('mfCancel').onclick=()=>{closeModal();res(null);};
  });}

  async function issue(btn, priority, formData){
    btn.style.pointerEvents='none';
    const payload={ device_key:cfg.key, service_id:+btn.dataset.id, priority, channel:'paper' };
    if(formData) payload.form_data=formData;
    const res = await QMS.api('api/ticket', payload);
    btn.style.pointerEvents='';
    if(!res.ok){ QMS.toast(res.error||'Eroare','error'); return; }
    afterTicket(res, btn.dataset.name, btn.dataset.color, btn.dataset.desc);
  }

  // afisare + printare bilet (folosit si la emitere normala, si la check-in programare)
  function afterTicket(res, name, color, desc){
    showTicket(res, name, color);
    if(cfg.printerMode==='browser') setTimeout(()=>printBrowser(res, name, desc), 350);
    if(cfg.printerMode==='android' && res.escpos_b64 && window.AndroidPrinter && window.AndroidPrinter.printBase64){
      try{ window.AndroidPrinter.printBase64(res.escpos_b64); }catch(e){ QMS.toast('Eroare imprimanta','error'); }
    }
  }

  /* ---- check-in programare la dozator (clientul cu programare isi ia bonul) ---- */
  const btnCheckin=$('btnCheckin');
  if(btnCheckin) btnCheckin.addEventListener('click', openCheckin);
  function openCheckin(){
    openModal(`<h3 style="margin-top:0">${esc(cfg.texts.checkin_title||'Check-in programare')}</h3>
      <p class="muted" style="margin:.2rem 0 .8rem">${esc(cfg.texts.checkin_hint||'Introdu codul programarii sau numarul de telefon')}</p>
      <input id="ciCode" type="text" autocomplete="off" style="width:100%;font-size:1.4rem;text-align:center;padding:.7rem;letter-spacing:.04em">
      <div style="display:flex;gap:.5rem;margin-top:1rem"><button class="btn" style="flex:1" id="ciCancel">${esc(cfg.popup.policy_cancel||'Anuleaza')}</button>
      <button class="btn btn-primary" style="flex:1" id="ciOk">${esc(cfg.popup.policy_ok||'Continua')}</button></div>`);
    const inp=$('ciCode'); if(cfg.virtualKeyboard) attachKeyboard(inp); else inp.focus();
    $('ciCancel').onclick=()=>closeModal();
    $('ciOk').onclick=async ()=>{
      const code=(inp.value||'').trim(); if(!code){ QMS.toast('Introdu codul programarii','error'); return; }
      $('ciOk').disabled=true;
      const res=await QMS.api('api/ticket-checkin',{device_key:cfg.key, code:code});
      $('ciOk').disabled=false;
      if(!res.ok){ QMS.toast(res.error||'Eroare','error'); return; }
      closeModal();
      afterTicket(res, res.service_name||'', res.color||cfg.accent);
    };
  }

  /* ---- tastatura pe ecran (touchscreen) ---- */
  let kbd=null, kbdTarget=null;
  function buildKeyboard(){
    if(kbd) return kbd;
    kbd=document.createElement('div'); kbd.className='vkbd'; kbd.style.display='none';
    const rows=[['1','2','3','4','5','6','7','8','9','0'],['q','w','e','r','t','y','u','i','o','p'],['a','s','d','f','g','h','j','k','l'],['z','x','c','v','b','n','m','@','.']];
    let html=rows.map(r=>'<div class="vk-row">'+r.map(k=>`<button type="button" class="vk" data-k="${k}">${k}</button>`).join('')+'</div>').join('');
    html+='<div class="vk-row"><button type="button" class="vk vk-wide" data-k=" ">spatiu</button><button type="button" class="vk vk-back" data-k="back">⌫</button></div>';
    kbd.innerHTML=html;
    kbd.addEventListener('mousedown', e=>e.preventDefault()); // nu fura focusul din input
    kbd.addEventListener('click', e=>{ const b=e.target.closest('[data-k]'); if(!b||!kbdTarget) return;
      const k=b.getAttribute('data-k');
      if(k==='back') kbdTarget.value=kbdTarget.value.slice(0,-1); else kbdTarget.value+=k;
      kbdTarget.dispatchEvent(new Event('input',{bubbles:true})); kbdTarget.focus();
    });
    document.body.appendChild(kbd); return kbd;
  }
  function attachKeyboard(inp){ if(!cfg.virtualKeyboard) return; buildKeyboard(); kbdTarget=inp; kbd.style.display=''; setTimeout(()=>inp.focus(),0); }
  function detachKeyboard(){ if(kbd) kbd.style.display='none'; kbdTarget=null; }
  if(cfg.virtualKeyboard){
    document.addEventListener('focusin', e=>{ const el=e.target;
      const ok = (el.tagName==='INPUT' && /^(text|tel|email|number|search|)$/.test((el.getAttribute('type')||'text'))) || el.tagName==='TEXTAREA';
      if(ok){ buildKeyboard(); kbdTarget=el; kbd.style.display=''; }
    });
  }

  function showTicket(res, name, color){
    const t=res.ticket; color=color||cfg.accent;
    $('tkSvc').textContent=name||'';
    const big=$('tkNum'); big.textContent=t.label; big.style.color=color;
    $('tkPos').textContent = res.position>0 ? (cfg.texts.ahead||'Sunt {n} inainte').replace('{n}',res.position) : (cfg.texts.ahead_first||'Sunteti urmatorul');
    const tw=$('tkWait'); if(tw){ const m=Math.round((+res.wait_est||0)/60);
      if(m>0){ tw.style.display=''; tw.textContent=(cfg.texts.wait_est||'Timp estimat ~{m} min').replace('{m}',m); } else tw.style.display='none'; }
    $('tkDone').textContent = cfg.texts.done||'Gata';
    const qr=$('tkQr');
    if(cfg.virtual && res.virtual_url){ qr.style.display=''; qr.src=QMS.base()+'/qr?size=160&data='+encodeURIComponent(vurl(res.virtual_url)); } else qr.style.display='none';
    // reporneste animatia bifei la fiecare bon
    const chk=overlay.querySelector('.tk-check'); if(chk){ const c2=chk.cloneNode(true); chk.replaceWith(c2); }
    overlay.classList.add('show');
    let s=Math.max(2,cfg.autoReturn||7); const cd=$('cd'); cd.textContent=s;
    clearInterval(timer); timer=setInterval(()=>{ s--; cd.textContent=s; if(s<=0) closeTicket(); },1000);
  }
  function closeTicket(){ clearInterval(timer); overlay.classList.remove('show');
    // revino la limba implicita dupa bilet (urmatorul vizitator vede limba standard)
    if(cfg.lang && cfg.lang!=='ro' && cfg.revertUrl){ setTimeout(()=>location.replace(cfg.revertUrl), 250); }
  }
  window.qmsCloseTicket=closeTicket;

  // inlocuieste variabilele de sablon din textele bonului tiparit
  function fillVars(s, res, name){
    const t=res.ticket;
    return String(s||'')
      .replace(/\{\{\s*service\s*\}\}/g, name||'')
      .replace(/\{\{\s*ticketLabel\s*\}\}/g, t.label||'')
      .replace(/\{\{\s*ticketCode\s*\}\}/g, t.public_token||'')
      .replace(/\{\{\s*ticketId\s*\}\}/g, t.id||'')
      .replace(/\{\{\s*total\s*\}\}/g, (res.position!=null?res.position:''))
      .replace(/\{\{\s*dateTime\s*\}\}/g, new Date().toLocaleString('ro-RO'));
  }
  function printBrowser(res, name, desc){
    const t=res.ticket, P=cfg.print||{};
    let f=window.frames['printframe']; if(!f){ const fr=document.createElement('iframe'); fr.name='printframe'; fr.style.cssText='position:absolute;width:0;height:0;border:0;left:-9999px'; document.body.appendChild(fr); f=fr; }
    const doc=f.contentDocument; doc.open();
    doc.write(`<html><head><style>@page{size:80mm auto;margin:0}body{width:80mm;font-family:monospace;text-align:center;margin:0;padding:6px 4px}.b{font-size:13px;font-weight:bold}.num{font-size:46px;font-weight:bold;margin:6px 0}.s{font-size:14px}.d{font-size:12px}hr{border:none;border-top:1px dashed #000}img{width:120px;height:120px}</style></head><body>
      ${P.logo&&cfg.logo?`<img src="${cfg.logo}" style="width:auto;max-height:60px">`:''}
      <div class="b">${esc(cfg.brand||'')}</div><div>${esc(cfg.branch||'')}</div>
      ${P.header?`<div class="s">${esc(fillVars(P.header,res,name))}</div>`:''}<hr>
      ${P.service!==0?`<div class="s">${esc(name||'')}</div>`:''}
      ${P.desc&&desc?`<div class="d">${esc(desc)}</div>`:''}
      ${t.priority?'<div class="b">* PRIORITAR *</div>':''}
      <div class="num">${esc(t.label)}</div>
      ${P.position!==0?`<div>${esc(P.ahead?fillVars(P.ahead,res,name):$('tkPos').textContent)}</div>`:''}
      ${P.datetime!==0?`<div class="s">${new Date().toLocaleString('ro-RO')}</div>`:''}
      ${P.qr!==0&&cfg.virtual&&res.virtual_url?`<div>${esc(cfg.texts.qr_hint||'')}</div><img src="${QMS.base()}/qr?size=120&data=${encodeURIComponent(vurl(res.virtual_url))}">`:''}
      <hr><div class="s">${esc(cfg.footer||'')}</div></body></html>`);
    doc.close();
    setTimeout(()=>{ f.contentWindow.focus(); f.contentWindow.print(); },250);
  }

  /* ceas + data pe antet (optional) */
  if(cfg.clock){ const c=$('kClock'); if(c){ const tick=()=>{ const d=new Date();
    const dd=d.toLocaleDateString('ro-RO');
    const tt=d.toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit',hour12:!cfg.clock24});
    c.textContent=dd+' · '+tt; }; tick(); setInterval(tick,1000); } }

  if(cfg.screensaver>0){ const saver=$('saver'); let st=null;
    const reset=()=>{ saver.classList.remove('show'); clearTimeout(st); st=setTimeout(()=>saver.classList.add('show'), cfg.screensaver*1000); };
    ['click','touchstart','keydown'].forEach(ev=>document.addEventListener(ev,reset,{passive:true})); reset();
  }
  /* efect de atingere (ripple) pe butoanele de serviciu */
  document.addEventListener('pointerdown', e=>{
    const b=e.target.closest('.svc-btn'); if(!b || b.disabled) return;
    const r=b.getBoundingClientRect(), d=Math.max(r.width, r.height);
    const s=document.createElement('span'); s.className='rip';
    s.style.width=s.style.height=d+'px';
    s.style.left=(e.clientX-r.left-d/2)+'px'; s.style.top=(e.clientY-r.top-d/2)+'px';
    b.appendChild(s); setTimeout(()=>s.remove(), 600);
  }, {passive:true});

  /* ---- navigare „pe categorii" (drill-down): tile-uri de grup <-> panouri ---- */
  (function(){
    var panels = document.querySelectorAll('.drillpanel'); if(!panels.length) return;
    var byId = {}; panels.forEach(function(p){ byId[p.dataset.panel] = p; });
    var stack = ['root'];
    function show(id){ panels.forEach(function(p){ p.classList.toggle('on', p.dataset.panel===id); }); try{ window.scrollTo(0,0); }catch(e){} }
    document.addEventListener('click', function(e){
      var open = e.target.closest('[data-open]');
      if(open){ var id=open.getAttribute('data-open'); if(byId[id]){ stack.push(id); show(id); } return; }
      var back = e.target.closest('[data-back]');
      if(back){ if(stack.length>1){ stack.pop(); show(stack[stack.length-1]); } }
    });
    // dupa ce se inchide biletul (auto sau „Gata") revenim la ecranul principal, pentru urmatorul client
    var ov=document.getElementById('overlay');
    if(ov && window.MutationObserver){ new MutationObserver(function(){ if(!ov.classList.contains('show')){ stack=['root']; show('root'); } }).observe(ov,{attributes:true,attributeFilter:['class']}); }
  })();

  /* ---- paginare (◀ ▶) cand serviciile nu incap: optiune per dozator ---- */
  if(cfg.overflow==='paginate'){
    var ps = Math.max(1, +cfg.pageSize || 9);
    document.querySelectorAll('.svc-grid').forEach(function(grid){
      var btns = [].slice.call(grid.children).filter(function(c){ return c.classList && c.classList.contains('svc-btn'); });
      if(btns.length <= ps) return;
      var page=0, pages=Math.ceil(btns.length/ps);
      var nav=document.createElement('div'); nav.className='pg-nav';
      nav.innerHTML='<button type="button" class="pg-prev" aria-label="Pagina anterioara">◀</button><span class="pg-ind"></span><button type="button" class="pg-next" aria-label="Pagina urmatoare">▶</button>';
      grid.parentNode.insertBefore(nav, grid.nextSibling);
      var prev=nav.querySelector('.pg-prev'), next=nav.querySelector('.pg-next'), ind=nav.querySelector('.pg-ind');
      function render(){ btns.forEach(function(b,i){ b.style.display=(i>=page*ps && i<(page+1)*ps)?'':'none'; });
        ind.textContent=(page+1)+' / '+pages; prev.disabled=page===0; next.disabled=page>=pages-1; }
      prev.addEventListener('click', function(){ if(page>0){ page--; render(); } });
      next.addEventListener('click', function(){ if(page<pages-1){ page++; render(); } });
      render();
    });
  }

  setInterval(()=> QMS.api('api/heartbeat',{device_key:cfg.key}).catch(()=>{}), 45000);
  QMS.api('api/heartbeat',{device_key:cfg.key}).catch(()=>{});

  /* poll usor (12s): actualizeaza live anuntul general si insignele 👥 "cati asteapta" */
  async function pollState(){
    let r; try{ r = await QMS.api('api/state?branch='+(cfg.branchId||1), null, 'GET'); }catch(e){ return; }
    if(!r || !r.ok) return;
    const nb = $('dspNotice');
    if(nb){ const nt = (typeof r.notice==='string') ? r.notice.trim() : '';
      nb.style.display = nt ? '' : 'none';
      const want = nt ? ('📢 ' + nt) : '';
      if(nb.textContent !== want) nb.textContent = want; }
    if(cfg.showWaiting){
      const byId = {}; (r.waiting||[]).forEach(w=>{ byId[+w.id] = +w.cnt || 0; });
      document.querySelectorAll('.wbadge[data-wc]').forEach(b=>{
        const n = byId[+b.dataset.wc] || 0;
        b.style.display = n > 0 ? '' : 'none';
        const bold = b.querySelector('b'); if(bold && bold.textContent !== String(n)) bold.textContent = n;
      });
    }
  }
  pollState();
  var _ps=false; setInterval(async function(){ if(_ps) return; _ps=true; try{ await pollState(); } finally{ _ps=false; } }, 12000);
})();
