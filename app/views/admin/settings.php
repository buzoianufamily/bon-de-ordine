<?php $title='Setari'; $active='settings'; require __DIR__.'/_header.php'; $s=fn($k,$d='')=>e(setting($k,$d)); ?>
<div class="topbar"><h1>Setari</h1></div>
<div class="tabs" id="setTabs">
  <a class="on" data-tab="general"><span class="ic">⚙</span>General</a>
  <a data-tab="ticket"><span class="ic">🎫</span>Bilet</a>
  <a data-tab="display"><span class="ic">📺</span>Afisaj &amp; voce</a>
  <a data-tab="digital"><span class="ic">📱</span>Digital &amp; alerte</a>
</div>
<form method="post" action="<?= e(url('admin/settings')) ?>"><?= csrf_field() ?>

  <div class="settab" data-pane="general">
    <div class="card pad" style="max-width:640px">
      <h3 style="margin-top:0">Brand</h3>
      <div class="field"><label>Nume companie / brand</label><input name="brand_name" value="<?= $s('brand_name') ?>"></div>
      <div class="field"><label>Nume organizatie (pe bon)</label><input name="org_name" value="<?= $s('org_name') ?>"></div>
      <div class="field"><label>Culoare accent</label><input type="color" name="accent_color" value="<?= $s('accent_color','#2563eb') ?>"></div>
      <div class="field"><label>Logo (optional)</label>
        <input name="brand_logo" id="brandLogo" value="<?= $s('brand_logo') ?>" placeholder="https://... sau alege din Multimedia">
        <button type="button" class="btn btn-ghost" id="logoPick" style="margin-top:.4rem;font-size:.8rem">📁 Alege din Multimedia</button>
        <div id="logoGrid" style="display:none;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:.5rem"></div>
      </div>
      <div class="field"><label>Limba interfata</label><select name="language"><option value="ro" selected>Romana</option></select></div>
    </div>
  </div>

  <div class="settab dv-hidden" data-pane="ticket">
    <div class="card pad" style="max-width:640px">
      <h3 style="margin-top:0">Bilet tiparit &amp; dispenser</h3>
      <div class="field"><label>Titlu dispenser</label><input name="dispenser_title" value="<?= $s('dispenser_title','ALEGE SERVICIUL') ?>"></div>
      <div class="field"><label>Text antet bon (sub numele filialei)</label><input name="ticket_header" value="<?= $s('ticket_header') ?>" placeholder="ex: Va uram bun venit!"></div>
      <div class="field"><label>Text subsol bon</label><input name="ticket_footer" value="<?= $s('ticket_footer') ?>"></div>
      <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">
      <h3 style="margin-top:0">Continut bon tiparit</h3>
      <div class="field"><label>Marime numar bon (2 = mic … 6 = foarte mare)</label><input type="number" name="ticket_num_size" min="2" max="6" value="<?= $s('ticket_num_size','4') ?>"></div>
      <label style="margin:.4rem 0;display:block"><input type="checkbox" name="ticket_show_position" <?= setting('ticket_show_position','1')==='1'?'checked':'' ?> style="width:auto"> Arata pozitia in coada (cati sunt inainte)</label>
      <label style="margin:.4rem 0;display:block"><input type="checkbox" name="ticket_show_datetime" <?= setting('ticket_show_datetime','1')==='1'?'checked':'' ?> style="width:auto"> Arata data si ora emiterii</label>
      <label style="margin:.4rem 0;display:block"><input type="checkbox" name="ticket_show_qr" <?= setting('ticket_show_qr','1')==='1'?'checked':'' ?> style="width:auto"> Arata codul QR (bilet digital)</label>
      <p class="muted" style="font-size:.82rem">Antetul si subsolul apar pe bonul tiparit (ESC/POS). Codul QR apare doar daca biletul digital e activ si optiunea de mai sus e bifata.</p>
    </div>
  </div>

  <div class="settab dv-hidden" data-pane="display">
    <div class="card pad" style="max-width:640px">
      <h3 style="margin-top:0">Afisaj &amp; anunt vocal</h3>
      <div class="field"><label>Voce (limba TTS)</label><select name="display_voice">
        <?php foreach(['ro-RO'=>'Romana','en-US'=>'Engleza','de-DE'=>'Germana','fr-FR'=>'Franceza'] as $k=>$lab): ?>
          <option value="<?= $k ?>" <?= setting('display_voice','ro-RO')===$k?'selected':'' ?>><?= $lab ?></option><?php endforeach; ?></select></div>
      <label style="margin:.5rem 0;display:block"><input type="checkbox" name="display_say_number" <?= setting('display_say_number','1')==='1'?'checked':'' ?> style="width:auto"> Anunta numarul bonului</label>
      <label style="margin:.5rem 0;display:block"><input type="checkbox" name="display_say_counter" <?= setting('display_say_counter','1')==='1'?'checked':'' ?> style="width:auto"> Anunta ghiseul</label>
      <div class="field"><label>Repetari anunt</label><input type="number" name="display_repeat" value="<?= $s('display_repeat','2') ?>" min="1" max="5"></div>
      <label style="margin:.5rem 0;display:block"><input type="checkbox" name="counter_voice" <?= setting('counter_voice','0')==='1'?'checked':'' ?> style="width:auto"> Anunt vocal si la terminalul operatorului (la „Cheama urmatorul")</label>
      <p class="muted" style="font-size:.82rem">Aceste valori sunt folosite pe afisajele fara layout personalizat. Afisajele cu layout (editor canvas) au setarile lor de sunet.</p>
    </div>
  </div>

  <div class="settab dv-hidden" data-pane="digital">
    <div class="card pad" style="max-width:640px">
      <h3 style="margin-top:0">Bilet digital (QR pe telefon)</h3>
      <label style="margin:.5rem 0;display:block"><input type="checkbox" name="virtual_enabled" <?= setting('virtual_enabled','1')==='1'?'checked':'' ?> style="width:auto"> Activeaza biletul digital pe telefon</label>
      <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">
      <h3 style="margin-top:0">Alerte client</h3>
      <div class="field"><label>Mesaj cand este apelat (max 250)</label><textarea name="alert_called" rows="2" maxlength="250"><?= $s('alert_called','Este randul dumneavoastra! Va rugam prezentati-va la ghiseu.') ?></textarea></div>
      <div class="field"><label>Mesaj la transfer (max 250)</label><textarea name="alert_transfer" rows="2" maxlength="250"><?= $s('alert_transfer','Biletul dvs. a fost transferat catre alt serviciu.') ?></textarea></div>
      <div class="field"><label>Intarziere alerta pe telefon (secunde)</label><input type="number" name="alert_delay" value="<?= $s('alert_delay','0') ?>" min="0" max="120">
        <p class="muted" style="font-size:.82rem;margin-top:.3rem">Dupa apelare, telefonul vibreaza/atentioneaza dupa acest numar de secunde (0 = imediat).</p></div>
    </div>
  </div>

  <button class="btn btn-primary btn-lg" style="margin-top:1.2rem">Salveaza setarile</button>
</form>
<script>
/* tab-uri setari */
(function(){
  var tabs=document.querySelectorAll('#setTabs [data-tab]');
  tabs.forEach(function(t){ t.addEventListener('click',function(e){ e.preventDefault();
    var name=t.getAttribute('data-tab');
    tabs.forEach(function(x){x.classList.toggle('on',x===t);});
    document.querySelectorAll('.settab').forEach(function(p){ p.classList.toggle('dv-hidden', p.getAttribute('data-pane')!==name); });
  }); });
})();
/* selector logo din galerie */
document.getElementById('logoPick').onclick=async()=>{ const g=document.getElementById('logoGrid');
  const r=await QMS.api('admin/media?format=json',null,'GET'); if(!r||!r.ok)return;
  g.style.display='grid';
  g.innerHTML=(r.media||[]).filter(m=>m.mime&&m.mime.startsWith('image')).map(m=>`<img src="${m.url}" data-u="${m.url}" style="width:100%;height:50px;object-fit:contain;background:#0e1117;border-radius:6px;cursor:pointer;padding:3px;border:1px solid #2a2f3a">`).join('')||'<div class="muted" style="grid-column:1/-1">Galerie goala — incarca in Multimedia.</div>';
  g.querySelectorAll('img').forEach(im=>im.onclick=()=>{ document.getElementById('brandLogo').value=im.dataset.u; });
};
</script>
<?php require __DIR__.'/_footer.php'; ?>
