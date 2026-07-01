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
      <span class="mtick" aria-hidden="true">✓</span>
      <div style="height:140px;background:#0e1117;display:flex;align-items:center;justify-content:center">
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
.mtile .mtick{position:absolute;top:.5rem;left:.5rem;z-index:2;width:24px;height:24px;border-radius:7px;background:rgba(0,0,0,.45);border:2px solid #fff;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:.85rem;opacity:0;transform:scale(.7);transition:.12s}
.mtile.sel{outline:3px solid var(--accent);outline-offset:-1px}
.mtile.sel .mtick{opacity:1;transform:scale(1);background:var(--accent);border-color:var(--accent)}
</style>
<script>
function copyUrl(btn,u){ navigator.clipboard?.writeText(u).then(()=>{const t=btn.textContent;btn.textContent='✔ Copiat';setTimeout(()=>btn.textContent=t,1500);}); }
/* selectie multipla fisiere: clic / Ctrl / Shift / Ctrl+A / Esc */
(function(){
  var grid=document.getElementById('mediaGrid'); if(!grid) return;
  var tiles=[].slice.call(grid.querySelectorAll('.mtile'));
  var bar=document.getElementById('mediaBulk'), cnt=document.getElementById('mbCount'), inputs=document.getElementById('mbInputs');
  var sel=new Set(), last=-1;
  function paint(){ tiles.forEach(function(t){ t.classList.toggle('sel', sel.has(t.dataset.id)); });
    cnt.textContent=sel.size; if(bar) bar.style.display=sel.size?'flex':'none';
    inputs.innerHTML=[...sel].map(function(id){ return '<input type="hidden" name="ids[]" value="'+id+'">'; }).join(''); }
  tiles.forEach(function(t,i){ t.addEventListener('click', function(e){
    // clic pe butoane/linkuri/formulare din card = actiune normala, nu selectie
    if(e.target.closest('button,a,form,input,video')) return;
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
})();
</script>
<?php require __DIR__.'/_footer.php'; ?>
