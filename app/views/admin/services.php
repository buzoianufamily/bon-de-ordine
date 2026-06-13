<?php $title='Servicii'; $active='services'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Servicii</h1><a class="btn btn-primary" href="<?= e(url('admin/services/new')) ?>">+ Serviciu nou</a></div>
<?= list_toolbar('Cauta serviciu...') ?>
<p class="muted" style="font-size:.8rem;margin:-.5rem 0 .8rem">Trage de <b>⠿</b> ca sa rearanjezi ordinea serviciilor (pe dispenser si in liste) — se salveaza automat.</p>
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
      <span class="grip" title="Trage pentru a rearanja">⠿</span>
    </div>
    <div class="mbody grow"><?= $r['description']? e($r['description']) : '' ?><?php if(!empty($r['paused'])): ?> <span class="pill" style="background:#fef3c7;color:#92400e">⏸ oprit temporar</span><?php endif; ?></div>
    <div class="card-foot">
      <span class="st <?= $r['status']==='active'?'on':'' ?>"><span class="d"></span><?= $r['status']==='active'?'Activ':'Inactiv' ?><?php if($open!==null): ?> · <?= $open?'deschis acum':'inchis acum' ?><?php endif; ?></span>
      <span>
        <form method="post" action="<?= e(url('admin/services/'.$r['id'].'/pause')) ?>" style="display:inline"><?= csrf_field() ?><button class="lnk" style="background:none;border:none;cursor:pointer;color:#d97706;font-weight:700;font:inherit"><?= !empty($r['paused']) ? '▶ Reia' : '⏸ Pauza' ?></button></form>
        <a class="lnk" href="<?= e(url('admin/services/'.$r['id'])) ?>" style="margin-left:.7rem">Editeaza</a>
        <form method="post" action="<?= e(url('admin/services/'.$r['id'].'/delete')) ?>" style="display:inline;margin-left:.7rem" data-confirm="Stergi serviciul?"><?= csrf_field() ?><button class="lnk del">Sterge</button></form>
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
  grid.querySelectorAll('.mcard').forEach(function(card){
    var grip = card.querySelector('.grip'); if(!grip) return;
    grip.addEventListener('mousedown', function(){ card.draggable = true; });
    grip.addEventListener('touchstart', function(){ card.draggable = true; }, {passive:true});
    card.addEventListener('dragstart', function(e){ dragEl = card; card.classList.add('dragging');
      try{ e.dataTransfer.setData('text/plain',''); e.dataTransfer.effectAllowed='move'; }catch(_){} });
    card.addEventListener('dragend', function(){
      card.classList.remove('dragging'); card.draggable = false;
      if(!dragEl) return; dragEl = null;
      var ids = Array.prototype.map.call(grid.querySelectorAll('.mcard'), function(c){ return +c.dataset.id; });
      QMS.api('admin/services/reorder', {ids: ids}).then(function(r){
        QMS.toast(r && r.ok ? 'Ordine salvata' : 'Eroare la salvare', r && r.ok ? 'ok' : 'error');
      }).catch(function(){ QMS.toast('Eroare la salvare','error'); });
    });
  });
  grid.addEventListener('dragover', function(e){
    e.preventDefault();
    var t = e.target.closest ? e.target.closest('.mcard') : null;
    if(!t || !dragEl || t === dragEl) return;
    var cards = Array.prototype.slice.call(grid.querySelectorAll('.mcard'));
    if(cards.indexOf(dragEl) < cards.indexOf(t)) t.after(dragEl); else t.before(dragEl);
  });
});
</script>
<?php require __DIR__.'/_footer.php'; ?>
