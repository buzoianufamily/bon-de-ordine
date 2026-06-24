<?php $title='Servicii'; $active='services'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Servicii</h1><a class="btn btn-primary" href="<?= e(url('admin/services/new')) ?>">+ Serviciu nou</a></div>
<?php if(!empty($branches)): ?>
<details class="card pad" style="margin-bottom:1rem">
  <summary style="cursor:pointer;font-weight:700">⤓ Import / export servicii (CSV)</summary>
  <p style="margin:.6rem 0"><a class="btn" href="<?= e(url('admin/services/export')) ?>">⬆ Exportă serviciile (CSV)</a> <a class="btn btn-ghost" href="<?= e(url('admin/services/export?template=1')) ?>">⬇ Șablon gol</a></p>
  <form method="post" action="<?= e(url('admin/services/import')) ?>" enctype="multipart/form-data" style="margin-top:.8rem">
    <?= csrf_field() ?>
    <div class="row" style="align-items:flex-end">
      <div class="field" style="margin:0"><label>Filiala</label><select name="branch_id"><?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="field" style="margin-top:.5rem"><label>Linii CSV: <code>prefix,nume,culoare</code> (culoarea e opțională)</label>
      <textarea name="csv" rows="5" placeholder="A,Casierie,#2563eb&#10;B,Informații&#10;C,Acte,#16a34a"></textarea></div>
    <div class="field" style="margin-top:.4rem"><label>… sau încarcă un fișier .csv</label>
      <input type="file" name="file" accept=".csv,text/csv"></div>
    <button class="btn btn-primary">Importă</button>
  </form>
</details>
<?php endif; ?>
<?= list_toolbar('Cauta serviciu...') ?>
<p class="muted" style="font-size:.8rem;margin:-.5rem 0 .8rem">Trage de <b>⠿</b> sau folosește <b>▲▼</b> ca sa rearanjezi ordinea serviciilor (pe dispenser si in liste) — se salveaza automat.</p>
<div class="cardgrid" id="svcGrid">
<?php foreach($rows as $r):
  $open=null; if(!empty($r['active_hours']) && ($sc=json_decode($r['active_hours'],true)) && !empty($sc['enabled'])) $open=service_is_open($r); ?>
  <div class="mcard" data-id="<?= (int)$r['id'] ?>" data-name="<?= e(mb_strtolower($r['prefix'].' '.$r['name'].' '.$r['branch_name'])) ?>">
    <div class="mhead">
      <span class="badge" style="background:<?= e($r['color']) ?>"><?= e($r['prefix']) ?></span>
      <div style="flex:1">
        <div class="nm"><?= e($r['name']) ?></div>
        <div class="sub muted"><?= e($r['branch_name']) ?> · interval <?= (int)$r['num_from'] ?>–<?= (int)$r['num_to'] ?><?= $r['allow_priority']?' · prioritar':'' ?></div>
      </div>
      <span class="reorder" style="display:inline-flex;flex-direction:column;line-height:.7;margin-right:.2rem">
        <button type="button" class="mvbtn" data-mv="up" title="Mută mai sus" aria-label="Mută serviciul mai sus" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:.7rem;padding:0 .2rem">▲</button>
        <button type="button" class="mvbtn" data-mv="down" title="Mută mai jos" aria-label="Mută serviciul mai jos" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:.7rem;padding:0 .2rem">▼</button>
      </span>
      <span class="grip" title="Trage pentru a rearanja">⠿</span>
    </div>
    <div class="mbody grow"><?= $r['description']? e($r['description']) : '' ?><?php if(!empty($r['paused'])): ?> <span class="pill" style="background:#fef3c7;color:#92400e">⏸ oprit temporar<?= !empty($r['pause_note']) ? ' · '.e($r['pause_note']) : '' ?></span><?php endif; ?></div>
    <div class="card-foot">
      <span class="st <?= $r['status']==='active'?'on':'' ?>"><span class="d"></span><?= $r['status']==='active'?'Activ':'Inactiv' ?><?php if($open!==null): ?> · <?= $open?'deschis acum':'inchis acum' ?><?php endif; ?></span>
      <span>
        <form method="post" action="<?= e(url('admin/services/'.$r['id'].'/pause')) ?>" style="display:inline" data-pause="<?= empty($r['paused'])?'1':'0' ?>"><?= csrf_field() ?><input type="hidden" name="note" value=""><button class="lnk" style="background:none;border:none;cursor:pointer;color:#d97706;font-weight:700;font:inherit"><?= !empty($r['paused']) ? '▶ Reia' : '⏸ Pauza' ?></button></form>
        <a class="lnk" href="<?= e(url('admin/services/'.$r['id'])) ?>" style="margin-left:.7rem">Editeaza</a>
        <form method="post" action="<?= e(url('admin/services/'.$r['id'].'/delete')) ?>" style="display:inline;margin-left:.7rem" data-confirm="Ștergi serviciul? Se șterg DEFINITIV și toate biletele și statisticile lui. Ca să-l ascunzi fără pierdere de date, setează-l mai bine Inactiv."><?= csrf_field() ?><button class="lnk del">Sterge</button></form>
      </span>
    </div>
  </div>
<?php endforeach; ?>
<?php if(!$rows): ?><div class="empty"><div class="eic">◆</div><p>Niciun serviciu inca. Serviciile sunt categoriile pentru care clientii iau bon (ex: Casierie, Acte).</p><a class="btn btn-primary" href="<?= e(url('admin/services/new')) ?>">+ Creeaza primul serviciu</a></div><?php endif; ?>
</div>
<script>
/* reordonare servicii prin drag & drop (porneste doar din manerul ⠿) */
window.addEventListener('load', function(){
  var grid = document.getElementById('svcGrid'); if(!grid) return;
  var dragEl = null;
  function saveOrder(){
    var ids = Array.prototype.map.call(grid.querySelectorAll('.mcard'), function(c){ return +c.dataset.id; });
    QMS.api('admin/services/reorder', {ids: ids}).then(function(r){
      QMS.toast(r && r.ok ? 'Ordine salvata' : 'Eroare la salvare', r && r.ok ? 'ok' : 'error');
    }).catch(function(){ QMS.toast('Eroare la salvare','error'); });
  }
  /* fallback la tastatura / atingere: butoanele ▲▼ (accesibil, fara drag) */
  grid.querySelectorAll('.mvbtn').forEach(function(b){
    b.addEventListener('click', function(){
      var card = b.closest('.mcard'); if(!card) return;
      if(b.dataset.mv === 'up'){ var p = card.previousElementSibling; if(!(p && p.classList.contains('mcard'))) return; p.before(card); }
      else { var n = card.nextElementSibling; if(!(n && n.classList.contains('mcard'))) return; n.after(card); }
      saveOrder(); b.focus();
    });
  });
  grid.querySelectorAll('.mcard').forEach(function(card){
    var grip = card.querySelector('.grip'); if(!grip) return;
    grip.addEventListener('mousedown', function(){ card.draggable = true; });
    grip.addEventListener('touchstart', function(){ card.draggable = true; }, {passive:true});
    card.addEventListener('dragstart', function(e){ dragEl = card; card.classList.add('dragging');
      try{ e.dataTransfer.setData('text/plain',''); e.dataTransfer.effectAllowed='move'; }catch(_){} });
    card.addEventListener('dragend', function(){
      card.classList.remove('dragging'); card.draggable = false;
      if(!dragEl) return; dragEl = null;
      saveOrder();
    });
  });
  grid.addEventListener('dragover', function(e){
    e.preventDefault();
    var t = e.target.closest ? e.target.closest('.mcard') : null;
    if(!t || !dragEl || t === dragEl) return;
    var cards = Array.prototype.slice.call(grid.querySelectorAll('.mcard'));
    if(cards.indexOf(dragEl) < cards.indexOf(t)) t.after(dragEl); else t.before(dragEl);
  });
  /* la oprirea unui serviciu, intreaba (optional) un mesaj pentru clienti */
  document.querySelectorAll('form[data-pause="1"]').forEach(function(f){
    f.addEventListener('submit', function(e){
      if(f.dataset.asked==='1') return;            // a doua oara (dupa prompt) lasam sa treaca
      e.preventDefault();
      QMS.prompt('Mesaj afișat clienților (opțional):', {placeholder:'ex: Revenim la 14:00', ok:'Oprește'}).then(function(v){
        if(v===null) return;                        // anulat
        f.querySelector('input[name="note"]').value = v || '';
        f.dataset.asked='1'; f.submit();
      });
    });
  });
});
</script>
<?php require __DIR__.'/_footer.php'; ?>
