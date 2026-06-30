<?php $title='Dispozitive'; $active='devices'; require __DIR__.'/_header.php';
$labels=['dispenser'=>'Dispenser bilete','player'=>'Afisaj TV','widget_player'=>'Afisaj TV (widget)','digital_ticket'=>'Bilet digital QR','launcher'=>'Launcher'];
$badge=['dispenser'=>'D','player'=>'TV','widget_player'=>'TV','digital_ticket'=>'QR','launcher'=>'L'];
function dev_url($d){ return url('launcher?key='.$d['connection_key']); } ?>
<div class="topbar"><h1>Dispozitive</h1><div style="display:flex;gap:.5rem"><a class="btn btn-ghost" href="<?= e(url('admin/devices/qr')) ?>">🔳 Coduri QR</a><a class="btn btn-primary" href="<?= e(url('admin/devices/new')) ?>">+ Dispozitiv nou</a></div></div>
<?= list_toolbar('Cauta dispozitiv...') ?>
<div class="filterpills" id="devfilter" role="group" aria-label="Filtreaza dupa tip">
  <button class="on" data-dtype="all">Toate</button>
  <button data-dtype="dispenser">Dispensere</button>
  <button data-dtype="player">Afisaje TV</button>
  <button data-dtype="digital_ticket">Bilete digitale</button>
  <button data-dtype="launcher">Launchere</button>
</div>
<div class="cardgrid" id="devgrid">
<?php foreach($rows as $d): $u=dev_url($d); $dt=in_array($d['type'],['player','widget_player'],true)?'player':$d['type']; ?>
  <div class="mcard" data-dtype="<?= e($dt) ?>" data-name="<?= e(mb_strtolower($d['name'].' '.($labels[$d['type']]??$d['type']).' '.$d['branch_name'].' '.$d['connection_key'])) ?>">
    <div class="mhead">
      <span class="badge" style="background:#2a2f3a;font-size:.78rem"><?= e($badge[$d['type']]??'?') ?></span>
      <div style="flex:1"><div class="nm"><?= e($d['name']) ?></div>
        <div class="sub muted"><?= e($labels[$d['type']]??$d['type']) ?> · <?= e($d['branch_name']) ?></div></div>
    </div>
    <div class="mbody">
      <div class="muted" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em">Cheie conectare</div>
      <code style="font-size:1.15rem;font-weight:800;letter-spacing:.05em"><?= e($d['connection_key']) ?></code>
      <input readonly value="<?= e($u) ?>" onclick="this.select()" style="font-size:.76rem;padding:.4rem;margin-top:.5rem">
      <div style="margin-top:.7rem;display:flex;gap:.4rem;flex-wrap:wrap">
        <a class="btn btn-ghost" target="_blank" href="<?= e($u) ?>" style="padding:.45rem .7rem">Deschide</a>
        <?php if(in_array($d['type'],['player','widget_player'],true)): ?>
          <a class="btn btn-primary" href="<?= e(url('admin/devices/'.$d['id'].'/player')) ?>" style="padding:.45rem .8rem">Configureaza</a>
        <?php elseif(in_array($d['type'],['dispenser','digital_ticket'],true)): ?>
          <a class="btn btn-primary" href="<?= e(url('admin/devices/'.$d['id'].'/dispenser')) ?>" style="padding:.45rem .8rem">Configureaza</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-foot">
      <span class="st <?= $d['online']?'on':'' ?>"><span class="d"></span><?= $d['online']?'Online':'Offline' ?></span>
      <span>
        <a class="lnk" href="<?= e(url('admin/devices/'.$d['id'])) ?>">Editeaza</a>
        <form method="post" action="<?= e(url('admin/devices/'.$d['id'].'/delete')) ?>" style="display:inline;margin-left:.7rem" data-confirm="Stergi dispozitivul?"><?= csrf_field() ?><button class="lnk del">Sterge</button></form>
      </span>
    </div>
  </div>
<?php endforeach; ?>
<?php if(!$rows): ?><div class="empty"><div class="eic">▭</div><p>Niciun dispozitiv inca. Creeaza un dispenser (emitere bonuri) sau un afisaj TV.</p><a class="btn btn-primary" href="<?= e(url('admin/devices/new')) ?>">+ Creeaza primul dispozitiv</a></div><?php endif; ?>
</div>
<script>(function(){
  var bar=document.getElementById('devfilter'), grid=document.getElementById('devgrid');
  if(!bar||!grid)return;
  bar.addEventListener('click',function(e){
    var b=e.target.closest('[data-dtype]'); if(!b)return;
    var t=b.getAttribute('data-dtype');
    bar.querySelectorAll('button').forEach(function(x){x.classList.toggle('on',x===b);});
    grid.querySelectorAll('.mcard').forEach(function(c){
      c.style.display=(t==='all'||c.getAttribute('data-dtype')===t)?'':'none';
    });
  });
})();</script>
<?php require __DIR__.'/_footer.php'; ?>
