<?php $title='Multimedia'; $active='media'; require __DIR__.'/_header.php'; ?>
<div class="topbar">
  <h1>Multimedia</h1>
</div>
<form method="post" action="<?= e(url('admin/media/upload')) ?>" enctype="multipart/form-data" id="upForm">
  <?= csrf_field() ?>
  <input type="file" name="file[]" id="fileInput" multiple style="display:none" onchange="document.getElementById('upForm').submit()">
  <div id="dropzone" class="mediadrop">
    <div style="font-size:1.6rem">⬆</div>
    <div><strong>Trage fisierele aici</strong> sau <button type="button" class="btn btn-primary" style="vertical-align:middle" onclick="document.getElementById('fileInput').click()">alege fisiere</button></div>
    <div class="muted" style="font-size:.82rem;margin-top:.3rem">Poti selecta / trage <strong>mai multe deodata</strong>. Orice tip de fisier (imagini, video, SVG, PDF, documente…). Limita serverului: <code><?= e(bdo_human_size(min(bdo_ini_bytes('upload_max_filesize'), bdo_ini_bytes('post_max_size')) ?: 512*1024*1024)) ?></code>.</div>
  </div>
</form>
<script>
(function(){
  var dz=document.getElementById('dropzone'), inp=document.getElementById('fileInput'), form=document.getElementById('upForm');
  if(!dz) return;
  ['dragenter','dragover'].forEach(function(ev){ dz.addEventListener(ev,function(e){ e.preventDefault(); dz.classList.add('over'); }); });
  ['dragleave','dragend','drop'].forEach(function(ev){ dz.addEventListener(ev,function(e){ e.preventDefault(); dz.classList.remove('over'); }); });
  dz.addEventListener('drop',function(e){ if(e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length){ inp.files=e.dataTransfer.files; form.submit(); } });
})();
</script>

<?php if($rows): ?>
<div id="mediaBulk" style="display:none;align-items:center;gap:.7rem;margin-top:1rem;background:color-mix(in srgb,var(--accent) 12%,transparent);border:1px solid color-mix(in srgb,var(--accent) 30%,transparent);border-radius:12px;padding:.6rem .9rem">
  <b id="mbCount">0</b> <span>selectate</span>
  <span class="muted" style="font-size:.8rem">clic = selecteaza · Ctrl/Shift = mai multe · Ctrl+A = toate · Esc = deselecteaza</span>
  <span style="margin-left:auto;display:flex;gap:.5rem">
    <button type="button" class="btn btn-ghost" id="mbClear">Deselecteaza</button>
    <form method="post" action="<?= e(url('admin/media/delete-bulk')) ?>" id="mbForm" data-confirm="Stergi fisierele selectate?"><?= csrf_field() ?><span id="mbInputs"></span><button class="btn btn-danger">🗑 Sterge selectia</button></form>
  </span>
</div>
<?php endif; ?>
<div class="kpis" id="mediaGrid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));margin-top:1rem">
  <?php foreach($rows as $m): $isVid = str_starts_with($m['mime'],'video'); ?>
    <div class="card mtile" data-id="<?= (int)$m['id'] ?>" style="overflow:hidden;cursor:pointer;position:relative">
      <input type="checkbox" class="mcheck" aria-label="Selecteaza fisierul">
      <div class="mthumb" style="height:140px;background:#0e1117;display:flex;align-items:center;justify-content:center">
        <?php if($isVid): ?>
          <video src="<?= e($m['url']) ?>" style="max-width:100%;max-height:100%" muted></video>
        <?php else: ?>
          <img src="<?= e($m['url']) ?>" style="max-width:100%;max-height:140px;object-fit:contain" alt="">
        <?php endif; ?>
      </div>
      <div class="pad" style="padding:.7rem">
        <div style="font-size:.8rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($m['filename']) ?></div>
        <div style="display:flex;align-items:center;gap:.4rem;margin:.4rem 0">
          <span class="pill" style="background:var(--track);color:#9aa3b2;font-size:.7rem"><?= e(strtoupper(explode('/',$m['mime'])[1] ?? '?')) ?></span>
          <?php if($m['in_use']): ?><span class="pill" style="background:#143524;color:#4ade80;font-size:.7rem">● In uz</span>
          <?php else: ?><span class="pill" style="background:var(--track);color:#7d8696;font-size:.7rem">○ Neutilizat</span><?php endif; ?>
        </div>
        <div style="display:flex;gap:.35rem">
          <button class="btn btn-ghost" style="font-size:.8rem;padding:.4rem .6rem" onclick="copyUrl(this,'<?= e($m['url']) ?>')">⧉ Copiaza URL</button>
          <form method="post" action="<?= e(url('admin/media/'.$m['id'].'/delete')) ?>" style="margin-left:auto" data-confirm="Stergi fisierul?"><?= csrf_field() ?><button class="btn btn-ghost" style="color:var(--danger);font-size:.8rem;padding:.4rem .6rem">🗑</button></form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if(!$rows): ?>
    <div class="card pad" style="grid-column:1/-1;text-align:center;color:var(--muted);padding:3rem">
      Nicio imagine incarcata inca. Apasa „Incarca fisiere".
    </div>
  <?php endif; ?>
</div>
<style>
.mtile .mcheck{position:absolute;top:.5rem;left:.5rem;z-index:3;width:20px;height:20px;margin:0;cursor:pointer;accent-color:var(--accent);
  opacity:.55;transition:opacity .12s;box-shadow:0 0 0 3px rgba(0,0,0,.35);border-radius:4px}
.mtile:hover .mcheck,.mtile.sel .mcheck{opacity:1}
.mtile.sel{outline:3px solid var(--accent);outline-offset:-1px}
.mtile .mthumb{cursor:zoom-in}
/* lightbox: previzualizare marita la clic pe imagine */
#mLightbox{position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,.88);display:none;align-items:center;justify-content:center;padding:2rem}
#mLightbox.on{display:flex}
#mLightbox img,#mLightbox video{max-width:96vw;max-height:88vh;object-fit:contain;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.6)}
#mLightbox .lbname{position:absolute;bottom:1rem;left:0;right:0;text-align:center;color:#fff;font-size:.85rem;font-weight:600;opacity:.9}
#mLightbox .lbx{position:absolute;top:1rem;right:1.4rem;color:#fff;font-size:2rem;line-height:1;cursor:pointer;opacity:.8}
#mLightbox .lbx:hover{opacity:1}
</style>
<script>
function copyUrl(btn,u){ navigator.clipboard?.writeText(u).then(()=>{const t=btn.textContent;btn.textContent='✔ Copiat';setTimeout(()=>btn.textContent=t,1500);}); }
/* selectie multipla fisiere: clic / Ctrl / Shift / Ctrl+A / Esc */
(function(){
  var grid=document.getElementById('mediaGrid'); if(!grid) return;
  var tiles=[].slice.call(grid.querySelectorAll('.mtile'));
  var bar=document.getElementById('mediaBulk'), cnt=document.getElementById('mbCount'), inputs=document.getElementById('mbInputs');
  var sel=new Set(), last=-1;
  function paint(){ tiles.forEach(function(t){ var on=sel.has(t.dataset.id); t.classList.toggle('sel', on);
      var c=t.querySelector('.mcheck'); if(c) c.checked=on; });
    cnt.textContent=sel.size; if(bar) bar.style.display=sel.size?'flex':'none';
    inputs.innerHTML=[...sel].map(function(id){ return '<input type="hidden" name="ids[]" value="'+id+'">'; }).join(''); }
  function setSel(id,on){ if(on) sel.add(id); else sel.delete(id); }
  // bifa = selectie explicita (cu Shift = interval)
  tiles.forEach(function(t,i){
    var cb=t.querySelector('.mcheck'); if(!cb) return;
    cb.addEventListener('click', function(e){
      e.stopPropagation();
      var id=t.dataset.id;
      if(e.shiftKey && last>=0){ var a=Math.min(last,i), b=Math.max(last,i); for(var k=a;k<=b;k++) sel.add(tiles[k].dataset.id); }
      else { setSel(id, cb.checked); last=i; }
      paint();
    });
  });
  // clic pe previzualizare = deschide imaginea mare
  tiles.forEach(function(t){
    var th=t.querySelector('.mthumb'); if(!th) return;
    th.addEventListener('click', function(e){ e.stopPropagation(); openLightbox(t); });
  });
  tiles.forEach(function(t,i){ t.addEventListener('click', function(e){
    // clic pe butoane/linkuri/formulare/bifa/previzualizare = actiune normala, nu selectie
    if(e.target.closest('button,a,form,input,video,.mthumb')) return;
    var id=t.dataset.id;
    if(e.shiftKey && last>=0){ var a=Math.min(last,i), b=Math.max(last,i); for(var k=a;k<=b;k++) sel.add(tiles[k].dataset.id); }
    else if(e.ctrlKey||e.metaKey){ if(sel.has(id)) sel.delete(id); else sel.add(id); last=i; }
    else { if(sel.size===1 && sel.has(id)){ sel.clear(); } else { sel.clear(); sel.add(id); } last=i; }
    paint();
  }); });
  document.addEventListener('keydown', function(e){
    if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='a' && tiles.length){ // Ctrl+A = toate (doar cand nu scrii intr-un camp)
      if(/input|textarea|select/i.test((document.activeElement||{}).tagName||'')) return;
      e.preventDefault(); tiles.forEach(function(t){ sel.add(t.dataset.id); }); paint(); }
    else if(e.key==='Escape'){ sel.clear(); paint(); }
  });
  var clr=document.getElementById('mbClear'); if(clr) clr.addEventListener('click', function(){ sel.clear(); paint(); });

  /* lightbox */
  var lb=document.createElement('div'); lb.id='mLightbox';
  lb.innerHTML='<span class="lbx" aria-label="Inchide">&times;</span><div class="lbbody"></div><div class="lbname"></div>';
  document.body.appendChild(lb);
  var lbBody=lb.querySelector('.lbbody'), lbName=lb.querySelector('.lbname');
  window.openLightbox=function(tile){
    var img=tile.querySelector('.mthumb img'), vid=tile.querySelector('.mthumb video');
    var nameEl=tile.querySelector('.pad div'); lbBody.innerHTML='';
    if(img){ var i=document.createElement('img'); i.src=img.src; lbBody.appendChild(i); }
    else if(vid){ var v=document.createElement('video'); v.src=vid.src; v.controls=true; v.autoplay=true; lbBody.appendChild(v); }
    lbName.textContent = nameEl ? nameEl.textContent : '';
    lb.classList.add('on');
  };
  function closeLb(){ lb.classList.remove('on'); lbBody.innerHTML=''; }
  lb.addEventListener('click', function(e){ if(e.target===lb || e.target.classList.contains('lbx')) closeLb(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape' && lb.classList.contains('on')) closeLb(); });
})();
</script>
<?php require __DIR__.'/_footer.php'; ?>
