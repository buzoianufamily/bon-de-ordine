/* Editor configurare dispenser: taburi + preview live + salvare */
(function(){
  const D = window.DBUILD || {};
  const $ = id => document.getElementById(id);
  const fields = () => Array.from(document.querySelectorAll('[id^="f__"]'));

  // ---- taburi ----
  document.querySelectorAll('.tab').forEach(t=> t.onclick=()=>{
    document.querySelectorAll('.tab').forEach(x=>x.classList.toggle('active',x===t));
    document.querySelectorAll('.panel').forEach(p=>p.classList.toggle('active',p.dataset.p===t.dataset.p));
  });

  // ---- colecteaza config din inputuri ----
  function collect(){
    const cfg={logic:{},appearance:{},texts:{},popup:{}};
    fields().forEach(el=>{
      const parts=el.id.split('__'); // f, sectiune, cheie
      const sec=parts[1], key=parts.slice(2).join('_');
      let v;
      if(el.type==='checkbox') v=el.checked;
      else if(el.type==='number') v=el.value===''?'':+el.value;
      else v=el.value;
      (cfg[sec]||(cfg[sec]={}))[key]=v;
    });
    return cfg;
  }

  // ---- preview live ----
  const sampleSvc=[['A','Casierie'],['B','Informatii'],['C','Depunere acte']];
  function val(id,d){ const el=$(id); if(!el)return d; return el.type==='checkbox'?el.checked:el.value; }
  function updatePreview(){
    const bgColor=val('f__appearance__bg_color','#eef1f6'), bgImg=val('f__appearance__bg_image','');
    const pv=$('pv');
    pv.style.background = bgImg ? `center/cover url("${bgImg}")` : `linear-gradient(180deg,#fff,${bgColor})`;
    const title=$('pvTitle'); title.textContent=val('f__texts__title','ALEGE SERVICIUL');
    title.style.color=val('f__appearance__header_color','#1a1d23');
    title.style.fontSize=Math.max(16,Math.min(48,+val('f__appearance__title_size',40)*.7))+'px';
    const sub=$('pvSub'); sub.textContent=val('f__texts__subtitle','');
    const logo=$('pvLogo'); const lu=val('f__appearance__logo_url','')||D.globalLogo||'';
    if(lu){ logo.src=lu; logo.style.display='block'; logo.style.maxHeight=Math.min(70,+val('f__appearance__logo_height',74))+'px'; } else logo.style.display='none';
    const rad=+val('f__appearance__btn_radius',22), h=Math.min(110,+val('f__appearance__btn_height',170));
    const tcol=val('f__appearance__btn_text_color','#ffffff'), wm=val('f__appearance__watermark',true);
    const hint=val('f__texts__btn_hint','Apasati pentru bilet');
    const colors=[D.accent,'#16a34a','#d97706'];
    $('pvGrid').innerHTML=sampleSvc.map((s,i)=>`<button class="pv-btn" style="background:linear-gradient(135deg,${colors[i]},${colors[i]}cc);border-radius:${rad}px;min-height:${h}px;color:${tcol}">
      ${wm?`<span class="pf">${s[0]}</span>`:''}<span class="nm">${esc(s[1])}</span><span class="ds">${esc(hint)}</span></button>`).join('');
  }
  const esc=s=>String(s==null?'':s).replace(/[<>&"]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
  fields().forEach(el=> el.addEventListener('input', updatePreview));

  // ---- pickers galerie ----
  document.querySelectorAll('[data-pick]').forEach(btn=>{
    const target=btn.dataset.pick; const grid=document.querySelector(`[data-grid="${target}"]`);
    btn.onclick=async()=>{
      if(grid.style.display==='grid'){ grid.style.display='none'; return; }
      const r=await QMS.api('admin/media?format=json',null,'GET'); if(!r||!r.ok)return;
      grid.style.display='grid';
      grid.innerHTML=(r.media||[]).filter(m=>m.mime&&m.mime.startsWith('image')).map(m=>`<img src="${m.url}" data-u="${m.url}">`).join('')||'<div class="muted" style="grid-column:1/-1">Galerie goala — incarca in Multimedia.</div>';
      grid.querySelectorAll('img').forEach(im=>im.onclick=()=>{ $(target).value=im.dataset.u; grid.style.display='none'; updatePreview(); });
    };
  });

  // ---- salvare ----
  $('btnSave').onclick=async()=>{
    const b=$('btnSave'); b.textContent='Se salveaza…'; b.disabled=true;
    const res=await QMS.api(D.saveUrl, collect());
    b.disabled=false; b.textContent='💾 Salveaza';
    QMS.toast(res.ok?'Configurare salvata ✔':(res.error||'Eroare'), res.ok?'ok':'error');
  };

  updatePreview();
})();
