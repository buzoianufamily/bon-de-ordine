/* Editor canvas afisaj — drag & drop widget-uri, ecrane multiple, salvare layout */
(function(){
  const P = window.PLAYER || {};
  const stage = document.getElementById('stage');
  const uid = () => 'w' + Math.random().toString(36).slice(2,8);

  // ---- definitii widget-uri (eticheta, icon, marime default, props, campuri inspector, preview) ----
  const WIDGETS = {
    now_serving: { label:'Bon curent', icon:'🔢', w:44,h:46, props:{label:'Se serveste bonul'},
      fields:[['label','Eticheta','text']],
      preview:p=>`<div class="wv wv-now"><div class="l">${esc(p.label||'Se serveste bonul')}</div><div class="n">A001</div><div>Birou 1</div></div>` },
    called_list: { label:'Ultimele apelate', icon:'📋', w:44,h:42, props:{title:'Ultimele apelate',rows:5},
      fields:[['title','Titlu','text'],['rows','Nr. randuri','number']],
      preview:p=>`<div class="wv wv-list"><h4>${esc(p.title||'Ultimele apelate')}</h4>${sample(['A001·B1','C014·B2','A002·B1']).map(s=>{const[a,b]=s.split('·');return `<div class="li"><b>${a}</b><span style="color:var(--accent)">${b}</span></div>`}).join('')}</div>` },
    waiting_list: { label:'La rand', icon:'⏳', w:47,h:62, props:{title:'La rand'},
      fields:[['title','Titlu','text']],
      preview:p=>`<div class="wv wv-list"><h4>${esc(p.title||'La rand')}</h4>${[['A','Casierie',4],['C','Depunere',2]].map(([t,n,c])=>`<div class="li"><span><b style="color:var(--accent)">${t}</b> ${n}</span><b>${c}</b></div>`).join('')}</div>` },
    clock: { label:'Ceas / data', icon:'🕐', w:47,h:26, props:{mode:'full'},
      fields:[['mode','Afisare',['full','time','date']]],
      preview:p=>`<div class="wv wv-clock" style="font-size:${p.mode==='time'?'2.2em':'1.4em'}">${clockSample(p.mode)}</div>` },
    text: { label:'Text / mesaj', icon:'🅣', w:60,h:14, props:{text:'Bun venit!',marquee:false,size:24},
      fields:[['text','Text','textarea'],['size','Marime (px)','number'],['marquee','Defilare (marquee)','bool']],
      preview:p=>`<div class="wv wv-text" style="font-size:${Math.max(10,(p.size||24)/2)}px">${esc(p.text||'Text')}</div>` },
    image: { label:'Imagine / logo', icon:'🖼️', w:30,h:20, props:{url:'',fit:'cover'},
      fields:[['url','URL imagine','text'],['fit','Incadrare',['cover','contain']]],
      preview:p=>`<div class="wv wv-img">${p.url?`<img style="object-fit:${p.fit||'cover'}" src="${esc(p.url)}">`:'<div class="wv-text" style="color:#5b6270">Imagine</div>'}</div>` },
    tickets_grid: { label:'Grila bilete', icon:'▦', w:62,h:64, props:{title:'',rows:6,show_counter:true},
      fields:[['title','Titlu (optional)','text'],['rows','Nr. servicii afisate','number'],['show_counter','Arata ghiseul','bool']],
      preview:p=>`<div class="wv" style="gap:3px">${[['Cable TV','A001','5'],['5G Internet','B037','12'],['Smart Phones','C024','2']].map(([nm,lb,c])=>`<div style="display:flex;align-items:center;gap:6px;background:#0008;border-left:4px solid var(--accent);border-radius:6px;padding:3px 6px"><span style="opacity:.45;font-weight:800">${c}</span><span style="flex:1">${esc(nm)}</span><b style="color:var(--accent)">${lb}</b></div>`).join('')}</div>` },
    last_called: { label:'Ultimul apelat', icon:'📣', w:44,h:30, props:{label:'Ultimul bon apelat'},
      fields:[['label','Eticheta','text']],
      preview:p=>`<div class="wv wv-now"><div class="l">${esc(p.label||'Ultimul bon apelat')}</div><div class="n">A001</div><div>Birou 1</div></div>` },
    people_counting: { label:'Numarare persoane', icon:'👥', w:30,h:22, props:{label:'Persoane inauntru',inside:10,capacity:87},
      fields:[['label','Eticheta','text'],['inside','Persoane inauntru','number'],['capacity','Capacitate maxima','number']],
      preview:p=>`<div class="wv" style="align-items:center;justify-content:center;text-align:center"><div style="font-size:.78em;opacity:.7">${esc(p.label||'Persoane inauntru')}</div><div style="font-family:'Bricolage Grotesque';font-weight:800;font-size:1.9em">${p.inside??0} <span style="opacity:.5">/ ${p.capacity??0}</span></div></div>` },
    qr_code: { label:'Cod QR', icon:'🔳', w:22,h:32, props:{url:'',caption:'Scaneaza pentru bilet digital'},
      fields:[['url','URL (gol = adresa site)','text'],['caption','Text sub cod','text']],
      preview:p=>`<div class="wv" style="align-items:center;justify-content:center;text-align:center"><div style="width:54px;height:54px;background:#fff;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#000;font-size:2em">🔳</div><div style="font-size:.72em;margin-top:4px;opacity:.85">${esc(p.caption||'')}</div></div>` },
    playlist: { label:'Playlist imagini', icon:'🎞️', w:40,h:42, props:{items:'',interval:6,fit:'cover'},
      fields:[['items','Imagini (un URL pe linie)','textarea'],['interval','Schimba la (sec)','number'],['fit','Incadrare',['cover','contain']]],
      preview:p=>`<div class="wv wv-img"><div class="wv-text" style="color:#5b6270">🎞️ Playlist ${String(p.items||'').split(/[\n,]+/).filter(Boolean).length||0} img</div></div>` },
    weather: { label:'Vremea', icon:'⛅', w:26,h:24, props:{city:'Bucuresti',lat:'44.43',lon:'26.10'},
      fields:[['city','Oras (eticheta)','text'],['lat','Latitudine','text'],['lon','Longitudine','text']],
      preview:p=>`<div class="wv" style="align-items:center;justify-content:center;text-align:center"><div style="font-size:1.8em;font-weight:800">⛅ 27°C</div><div style="font-size:.78em;opacity:.7">${esc(p.city||'')}</div></div>` },
    iframe: { label:'Pagina web (iframe)', icon:'🌐', w:42,h:42, props:{url:''},
      fields:[['url','URL pagina (https)','text']],
      preview:p=>`<div class="wv" style="align-items:center;justify-content:center;text-align:center;color:#5b6270">🌐 ${esc(p.url||'iframe')}</div>` },
    ticker: { label:'Banda mesaj (ticker)', icon:'📰', w:100,h:9, props:{text:'Bun venit! Va rugam asteptati apelarea bonului. ',speed:18},
      fields:[['text','Mesaj','textarea'],['speed','Viteza (sec/ciclu)','number']],
      preview:p=>`<div class="wv wv-text" style="justify-content:flex-start;white-space:nowrap;overflow:hidden">${esc((p.text||'Mesaj').slice(0,46))}</div>` },
    form: { label:'Formular feedback', icon:'📝', w:24,h:36, props:{title:'Spune-ne parerea ta',url:''},
      fields:[['title','Titlu','text'],['url','URL formular (gol = pagina feedback)','text']],
      preview:p=>`<div class="wv" style="align-items:center;justify-content:center;text-align:center"><div style="font-size:.82em;margin-bottom:4px">${esc(p.title||'Feedback')}</div><div style="width:48px;height:48px;background:#fff;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#000;font-size:1.5em">📝</div><div style="font-size:.85em;margin-top:5px;color:#f5b301">★★★★★</div></div>` },
  };
  const esc = s => String(s==null?'':s).replace(/[<>&"]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
  const sample = a => a;
  function clockSample(m){ const d=new Date(); const t=d.toLocaleTimeString('ro-RO',{hour:'2-digit',minute:'2-digit'});
    const dt=d.toLocaleDateString('ro-RO',{weekday:'long',day:'numeric',month:'long'});
    return m==='time'?t : m==='date'?dt : (t+'<div style="font-size:.5em;opacity:.7">'+dt+'</div>'); }

  // ---- stare ----
  let doc = P.config && P.config.screens ? P.config : defaultDoc();
  if (!doc.sound) doc.sound = {voice:'ro-RO',say_number:true,say_counter:true,repeat:2};
  let cur = 0, sel = null;

  function defaultDoc(){
    return { screens:[{ id:uid(), name:'Ecran 1', duration:15, bg:'#0b0d12', widgets:[
      {id:uid(),type:'now_serving',x:3,y:4,w:44,h:46,props:{label:'Se serveste bonul'}},
      {id:uid(),type:'called_list',x:3,y:54,w:44,h:42,props:{title:'Ultimele apelate',rows:5}},
      {id:uid(),type:'waiting_list',x:50,y:4,w:47,h:62,props:{title:'La rand'}},
      {id:uid(),type:'clock',x:50,y:70,w:47,h:26,props:{mode:'full'}},
    ]}], sound:{voice:'ro-RO',say_number:true,say_counter:true,repeat:2} };
  }
  const screen = () => doc.screens[cur];

  // ---- sabloane de ecran (preset-uri gata facute) ----
  const PRESETS = {
    clasic: { label:'Clasic', icon:'🖥', widgets:()=>[
      {id:uid(),type:'now_serving',x:3,y:4,w:44,h:46,props:{label:'Se serveste bonul'}},
      {id:uid(),type:'called_list',x:3,y:54,w:44,h:42,props:{title:'Ultimele apelate',rows:5}},
      {id:uid(),type:'waiting_list',x:50,y:4,w:47,h:62,props:{title:'La rand'}},
      {id:uid(),type:'clock',x:50,y:70,w:47,h:26,props:{mode:'full'}},
    ]},
    grila: { label:'Grila servicii', icon:'▦', widgets:()=>[
      {id:uid(),type:'tickets_grid',x:3,y:4,w:64,h:84,props:{title:'',rows:6,show_counter:true}},
      {id:uid(),type:'now_serving',x:70,y:4,w:27,h:38,props:{label:'Ultimul apelat'}},
      {id:uid(),type:'clock',x:70,y:46,w:27,h:20,props:{mode:'full'}},
      {id:uid(),type:'qr_code',x:70,y:68,w:27,h:20,props:{url:'',caption:'Scaneaza pentru bilet digital'}},
      {id:uid(),type:'ticker',x:0,y:91,w:100,h:9,props:{text:'Bun venit! Va rugam asteptati apelarea bonului. ',speed:18}},
    ]},
    minimal: { label:'Minimal', icon:'◻', widgets:()=>[
      {id:uid(),type:'now_serving',x:14,y:14,w:72,h:58,props:{label:'Se serveste bonul'}},
      {id:uid(),type:'clock',x:30,y:76,w:40,h:18,props:{mode:'time'}},
    ]},
  };
  const presetsEl = document.getElementById('presets');
  if (presetsEl) {
    presetsEl.innerHTML = Object.entries(PRESETS).map(([k,p])=>`<button data-pr="${k}"><span class="ic">${p.icon}</span>${p.label}</button>`).join('');
    presetsEl.querySelectorAll('button').forEach(b=> b.onclick=()=>{
      const apply = ()=>{ screen().widgets = PRESETS[b.dataset.pr].widgets(); sel=null; renderStage(); renderInspector(); };
      if (!screen().widgets.length) { apply(); return; }
      (window.QMS && QMS.confirm ? QMS.confirm('Inlocuiesti widget-urile ecranului curent cu sablonul „'+PRESETS[b.dataset.pr].label+'"?', {ok:'Aplica'}) : Promise.resolve(confirm('Inlocuiesti widget-urile?')))
        .then(ok=>{ if(ok) apply(); });
    });
  }

  // ---- paleta ----
  const pal = document.getElementById('palette');
  pal.innerHTML = Object.entries(WIDGETS).map(([t,w])=>`<button data-t="${t}"><span class="ic">${w.icon}</span>${w.label}</button>`).join('');
  pal.querySelectorAll('button').forEach(b=> b.onclick=()=>addWidget(b.dataset.t));

  function addWidget(type){
    const def = WIDGETS[type];
    const w = {id:uid(),type,x:8,y:8,w:def.w,h:def.h,props:Object.assign({},def.props)};
    screen().widgets.push(w); sel=w.id; renderStage(); renderInspector();
  }

  // ---- ecrane ----
  const screensEl = document.getElementById('screens');
  function renderScreens(){
    screensEl.innerHTML = doc.screens.map((s,i)=>`<div class="pb-screen ${i===cur?'active':''}" data-i="${i}">
      <span style="flex:1">${esc(s.name)}</span>${doc.screens.length>1?'<span class="x" data-del="'+i+'">✕</span>':''}</div>`).join('');
    screensEl.querySelectorAll('.pb-screen').forEach(el=>{
      el.onclick=e=>{ if(e.target.dataset.del!==undefined){ doc.screens.splice(+e.target.dataset.del,1); if(cur>=doc.screens.length)cur=0; sel=null; renderAll(); return;} cur=+el.dataset.i; sel=null; renderAll(); };
    });
  }
  document.getElementById('addScreen').onclick=()=>{ doc.screens.push({id:uid(),name:'Ecran '+(doc.screens.length+1),duration:15,bg:'#0b0d12',widgets:[]}); cur=doc.screens.length-1; sel=null; renderAll(); };

  // ---- stage ----
  function renderStage(){
    const s = screen();
    stage.style.background = s.bg || '#0b0d12';
    stage.innerHTML = '';
    s.widgets.forEach(w=>{
      const def = WIDGETS[w.type]; if(!def) return;
      const el = document.createElement('div');
      el.className = 'pb-w' + (w.id===sel?' sel':'');
      el.style.left=w.x+'%'; el.style.top=w.y+'%'; el.style.width=w.w+'%'; el.style.height=w.h+'%';
      if(w.props.bg) el.style.background=w.props.bg;
      el.innerHTML = def.preview(w.props) + '<div class="rsz"></div>';
      el.onpointerdown = ev => startDrag(ev, w, el);
      el.querySelector('.rsz').onpointerdown = ev => { ev.stopPropagation(); startResize(ev, w, el); };
      stage.appendChild(el);
    });
  }

  function startDrag(ev, w, el){
    if(ev.button) return; ev.preventDefault();
    sel=w.id; renderInspector(); markSel();
    const r=stage.getBoundingClientRect(), sx=ev.clientX, sy=ev.clientY, ox=w.x, oy=w.y; let raf=null;
    try{ el.setPointerCapture(ev.pointerId); }catch(e){}
    const mv=e=>{ w.x=clamp(ox+(e.clientX-sx)/r.width*100,0,100-w.w); w.y=clamp(oy+(e.clientY-sy)/r.height*100,0,100-w.h);
      if(!raf) raf=requestAnimationFrame(()=>{ el.style.left=w.x+'%'; el.style.top=w.y+'%'; raf=null; }); };
    const up=()=>{ el.removeEventListener('pointermove',mv); el.removeEventListener('pointerup',up); el.removeEventListener('pointercancel',up); if(raf)cancelAnimationFrame(raf); syncPos(); };
    el.addEventListener('pointermove',mv); el.addEventListener('pointerup',up); el.addEventListener('pointercancel',up);
  }
  function startResize(ev, w, el){
    if(ev.button) return; ev.preventDefault();
    sel=w.id; renderInspector(); markSel();
    const rz=el.querySelector('.rsz'), r=stage.getBoundingClientRect(), sx=ev.clientX, sy=ev.clientY, ow=w.w, oh=w.h; let raf=null;
    try{ rz.setPointerCapture(ev.pointerId); }catch(e){}
    const mv=e=>{ w.w=clamp(ow+(e.clientX-sx)/r.width*100,6,100-w.x); w.h=clamp(oh+(e.clientY-sy)/r.height*100,6,100-w.y);
      if(!raf) raf=requestAnimationFrame(()=>{ el.style.width=w.w+'%'; el.style.height=w.h+'%'; raf=null; }); };
    const up=()=>{ rz.removeEventListener('pointermove',mv); rz.removeEventListener('pointerup',up); rz.removeEventListener('pointercancel',up); if(raf)cancelAnimationFrame(raf); syncPos(); };
    rz.addEventListener('pointermove',mv); rz.addEventListener('pointerup',up); rz.addEventListener('pointercancel',up);
  }
  const clamp=(v,a,b)=>Math.max(a,Math.min(b,Math.round(v)));
  function markSel(){ stage.querySelectorAll('.pb-w').forEach((el,i)=>el.classList.toggle('sel', screen().widgets[i].id===sel)); }
  function syncPos(){ const w=screen().widgets.find(x=>x.id===sel); if(!w)return;
    ['x','y','w','h'].forEach(k=>{const i=document.getElementById('f_'+k); if(i)i.value=w[k];}); }

  // ---- inspector ----
  const insp = document.getElementById('inspector');
  function renderInspector(){
    const w = screen().widgets.find(x=>x.id===sel);
    if(!w){ renderScreenSettings(); return; }
    const def = WIDGETS[w.type];
    let h = `<div class="pb-grp">Widget: ${def.label}</div>`;
    def.fields.forEach(([key,lab,type])=>{
      h += fieldHtml('p_'+key, lab, type, w.props[key]);
    });
    if(w.type==='image'){ h += `<button class="pb-btn" id="pickMedia" style="width:100%;justify-content:center;margin:.2rem 0 .5rem">📁 Alege din galerie</button><div id="mediaGrid" style="display:none;grid-template-columns:repeat(3,1fr);gap:4px;margin-bottom:.6rem"></div>`; }
    h += `<hr><div class="pb-grp">Stil</div>`;
    h += fieldHtml('p_bg','Fundal widget','color', w.props.bg||'');
    h += `<hr><div class="pb-grp">Pozitie & marime (%)</div><div class="field row2">
      ${miniNum('f_x','X',w.x)}${miniNum('f_y','Y',w.y)}</div><div class="field row2">
      ${miniNum('f_w','Latime',w.w)}${miniNum('f_h','Inaltime',w.h)}</div>`;
    h += `<p class="muted" style="font-size:.72rem;margin:.5rem 0 0">Sfat multi-limba: la texte/titluri poti scrie <b>"Text RO | Text EN"</b> — pe afisaj alterneaza la 6 secunde.</p>`;
    h += `<button class="pb-btn" id="delW" style="width:100%;justify-content:center;border-color:#5b2330;color:#ff8a8a;margin-top:.5rem">🗑 Sterge widget</button>`;
    insp.innerHTML = h;
    def.fields.forEach(([key,,type])=>{
      const el=document.getElementById('p_'+key); if(!el)return;
      const ev = type==='bool'?'change':'input';
      el.addEventListener(ev,()=>{ w.props[key] = type==='bool'?el.checked:(type==='number'?+el.value:el.value); renderStage(); });
    });
    const bg=document.getElementById('p_bg'); if(bg) bg.addEventListener('input',()=>{w.props.bg=bg.value; renderStage();});
    ['x','y','w','h'].forEach(k=>{const i=document.getElementById('f_'+k); if(i)i.addEventListener('input',()=>{w[k]=clamp(+i.value,0,100); renderStage();});});
    document.getElementById('delW').onclick=()=>{ screen().widgets=screen().widgets.filter(x=>x.id!==sel); sel=null; renderStage(); renderInspector(); };
    if(w.type==='image'){ const pm=document.getElementById('pickMedia'), grid=document.getElementById('mediaGrid');
      pm.onclick=async()=>{ const r=await QMS.api('admin/media?format=json',null,'GET'); if(!r||!r.ok)return;
        grid.style.display='grid';
        grid.innerHTML=(r.media||[]).filter(m=>m.mime&&m.mime.startsWith('image')).map(m=>`<img src="${m.url}" data-u="${m.url}" style="width:100%;height:46px;object-fit:cover;border-radius:6px;cursor:pointer;border:1px solid #2a2f3a">`).join('')||'<div class="muted" style="grid-column:1/-1">Galerie goala — incarca in Multimedia.</div>';
        grid.querySelectorAll('img').forEach(im=>im.onclick=()=>{ w.props.url=im.dataset.u; const u=document.getElementById('p_url'); if(u)u.value=im.dataset.u; renderStage(); }); };
    }
  }
  function renderScreenSettings(){
    const s = screen();
    insp.innerHTML = `<div class="pb-grp">Ecran</div>
      ${fieldHtml('s_name','Nume ecran','text',s.name)}
      ${fieldHtml('s_dur','Durata afisare (sec)','number',s.duration)}
      ${fieldHtml('s_bg','Culoare fundal','color',s.bg||'#0b0d12')}
      <hr><div class="pb-grp">Sunet la apel (anunt vocal)</div>
      ${fieldHtml('snd_voice','Voce','select_voice',doc.sound.voice)}
      <label class="chk"><input type="checkbox" id="snd_num" ${doc.sound.say_number?'checked':''}> Anunta numarul bonului</label>
      <label class="chk"><input type="checkbox" id="snd_ctr" ${doc.sound.say_counter?'checked':''}> Anunta ghiseul</label>
      ${fieldHtml('snd_rep','Repetari anunt','number',doc.sound.repeat)}
      <hr><p class="muted">Apasa pe un widget ca sa-i editezi proprietatile. Trage de el ca sa-l muti, de coltul colorat ca sa-l redimensionezi.</p>`;
    bind('s_name','input',v=>s.name=v, true);
    bind('s_dur','input',v=>s.duration=+v);
    document.getElementById('s_bg').addEventListener('input',e=>{s.bg=e.target.value; renderStage();});
    document.getElementById('snd_voice').addEventListener('change',e=>doc.sound.voice=e.target.value);
    document.getElementById('snd_num').addEventListener('change',e=>doc.sound.say_number=e.target.checked);
    document.getElementById('snd_ctr').addEventListener('change',e=>doc.sound.say_counter=e.target.checked);
    document.getElementById('snd_rep').addEventListener('input',e=>doc.sound.repeat=+e.target.value);
    function bind(id,ev,fn,rerenderScreens){ document.getElementById(id).addEventListener(ev,e=>{fn(e.target.value); if(rerenderScreens)renderScreens();}); }
  }

  function fieldHtml(id,lab,type,val){
    if(type==='bool') return `<label class="chk"><input type="checkbox" id="${id}" ${val?'checked':''}> ${lab}</label>`;
    if(type==='textarea') return `<div class="field"><label>${lab}</label><textarea id="${id}" rows="2">${esc(val||'')}</textarea></div>`;
    if(type==='color') return `<div class="field"><label>${lab}</label><input type="color" id="${id}" value="${val||'#0b0d12'}"></div>`;
    if(Array.isArray(type)) return `<div class="field"><label>${lab}</label><select id="${id}">${type.map(o=>`<option ${o===val?'selected':''}>${o}</option>`).join('')}</select></div>`;
    if(type==='select_voice'){ const vs=[['ro-RO','Romana'],['en-US','Engleza'],['de-DE','Germana'],['fr-FR','Franceza']];
      return `<div class="field"><label>${lab}</label><select id="${id}">${vs.map(([v,n])=>`<option value="${v}" ${v===val?'selected':''}>${n}</option>`).join('')}</select></div>`; }
    return `<div class="field"><label>${lab}</label><input id="${id}" type="${type}" value="${esc(val||'')}"></div>`;
  }
  const miniNum=(id,lab,val)=>`<div><label style="font-size:.7rem">${lab}</label><input id="${id}" type="number" value="${val}"></div>`;

  // ---- click pe stage gol = deselecteaza ----
  stage.addEventListener('pointerdown',e=>{ if(e.target===stage){ sel=null; renderStage(); renderInspector(); }});

  // ---- salvare ----
  document.getElementById('btnSave').onclick = async ()=>{
    const btn=document.getElementById('btnSave'); btn.textContent='Se salveaza…'; btn.disabled=true;
    const res = await QMS.api(P.saveUrl, doc);
    btn.disabled=false; btn.textContent='💾 Salveaza';
    QMS.toast(res.ok?'Afisaj salvat ✔':(res.error||'Eroare'), res.ok?'ok':'error');
  };

  function renderAll(){ renderScreens(); renderStage(); renderInspector(); }
  renderAll();
})();
