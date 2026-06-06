<?php $title='Setari'; $active='settings'; require __DIR__.'/_header.php'; $s=fn($k,$d='')=>e(setting($k,$d)); ?>
<div class="topbar"><h1>Setari</h1></div>
<form method="post" action="<?= e(url('admin/settings')) ?>"><?= csrf_field() ?>
  <div class="row" style="align-items:flex-start">
    <div class="card pad" style="flex:1">
      <h3 style="margin-top:0">Brand</h3>
      <div class="field"><label>Nume companie / brand</label><input name="brand_name" value="<?= $s('brand_name') ?>"></div>
      <div class="field"><label>Nume organizatie (pe bon)</label><input name="org_name" value="<?= $s('org_name') ?>"></div>
      <div class="field"><label>Culoare accent</label><input type="color" name="accent_color" value="<?= $s('accent_color','#2563eb') ?>"></div>
      <div class="field"><label>Logo (optional)</label>
        <input name="brand_logo" id="brandLogo" value="<?= $s('brand_logo') ?>" placeholder="https://... sau alege din Multimedia">
        <button type="button" class="btn btn-ghost" id="logoPick" style="margin-top:.4rem;font-size:.8rem">📁 Alege din Multimedia</button>
        <div id="logoGrid" style="display:none;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:.5rem"></div>
      </div>
      <div class="field"><label>Titlu dispenser</label><input name="dispenser_title" value="<?= $s('dispenser_title','ALEGE SERVICIUL') ?>"></div>
      <div class="field"><label>Text subsol bon</label><input name="ticket_footer" value="<?= $s('ticket_footer') ?>"></div>
    </div>
    <div class="card pad" style="flex:1">
      <h3 style="margin-top:0">Afisaj &amp; anunt vocal</h3>
      <div class="field"><label>Voce (limba TTS)</label><select name="display_voice">
        <?php foreach(['ro-RO'=>'Romana','en-US'=>'Engleza','de-DE'=>'Germana','fr-FR'=>'Franceza'] as $k=>$lab): ?>
          <option value="<?= $k ?>" <?= setting('display_voice','ro-RO')===$k?'selected':'' ?>><?= $lab ?></option><?php endforeach; ?></select></div>
      <label style="margin:.5rem 0"><input type="checkbox" name="display_say_number" <?= setting('display_say_number','1')==='1'?'checked':'' ?> style="width:auto"> Anunta numarul bonului</label>
      <label style="margin:.5rem 0"><input type="checkbox" name="display_say_counter" <?= setting('display_say_counter','1')==='1'?'checked':'' ?> style="width:auto"> Anunta ghiseul</label>
      <div class="field"><label>Repetari anunt</label><input type="number" name="display_repeat" value="<?= $s('display_repeat','2') ?>" min="1" max="5"></div>
      <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">
      <h3 style="margin-top:0">Bilet digital (QR)</h3>
      <label style="margin:.5rem 0"><input type="checkbox" name="virtual_enabled" <?= setting('virtual_enabled','1')==='1'?'checked':'' ?> style="width:auto"> Activeaza biletul digital pe telefon</label>
      <div class="field"><label>Limba interfata</label><select name="language"><option value="ro" selected>Romana</option></select></div>
    </div>
  </div>
  <button class="btn btn-primary btn-lg">Salveaza setarile</button>
</form>
<script>
document.getElementById('logoPick').onclick=async()=>{ const g=document.getElementById('logoGrid');
  const r=await QMS.api('admin/media?format=json',null,'GET'); if(!r||!r.ok)return;
  g.style.display='grid';
  g.innerHTML=(r.media||[]).filter(m=>m.mime&&m.mime.startsWith('image')).map(m=>`<img src="${m.url}" data-u="${m.url}" style="width:100%;height:50px;object-fit:contain;background:#0e1117;border-radius:6px;cursor:pointer;padding:3px;border:1px solid #2a2f3a">`).join('')||'<div class="muted" style="grid-column:1/-1">Galerie goala — incarca in Multimedia.</div>';
  g.querySelectorAll('img').forEach(im=>im.onclick=()=>{ document.getElementById('brandLogo').value=im.dataset.u; });
};
</script>
<?php require __DIR__.'/_footer.php'; ?>
