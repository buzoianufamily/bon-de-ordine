<?php $title='Multimedia'; $active='media'; require __DIR__.'/_header.php'; ?>
<div class="topbar">
  <h1>Multimedia</h1>
  <form method="post" action="<?= e(url('admin/media/upload')) ?>" enctype="multipart/form-data" id="upForm" style="display:flex;gap:.5rem;align-items:center">
    <?= csrf_field() ?>
    <input type="file" name="file[]" id="fileInput" accept="image/*,video/mp4,video/webm" multiple style="display:none" onchange="document.getElementById('upForm').submit()">
    <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">⬆ Incarca fisiere</button>
  </form>
</div>
<p class="muted" style="margin-top:-.6rem">Imagini (JPG, PNG, GIF, WEBP, SVG) si video (MP4, WEBM), max 25MB. Le folosesti ca logo sau in widget-ul „Imagine" de pe afisaj.</p>

<div class="kpis" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));margin-top:1rem">
  <?php foreach($rows as $m): $isVid = str_starts_with($m['mime'],'video'); ?>
    <div class="card" style="overflow:hidden">
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
<script>
function copyUrl(btn,u){ navigator.clipboard?.writeText(u).then(()=>{const t=btn.textContent;btn.textContent='✔ Copiat';setTimeout(()=>btn.textContent=t,1500);}); }
</script>
<?php require __DIR__.'/_footer.php'; ?>
