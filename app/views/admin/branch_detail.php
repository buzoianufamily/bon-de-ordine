<?php $title=$branch['name']; $active='branches'; require __DIR__.'/_header.php';
$tabs = ['services'=>['Servicii','services'],'counters'=>['Ghisee','counters'],'devices'=>['Dispozitive','devices']];
$bid = (int)$branch['id'];
$turl = fn($t)=>url('admin/branches/'.$bid.'?tab='.$t);
$labels=['dispenser'=>'Dispenser','player'=>'Afisaj TV','widget_player'=>'Afisaj TV','digital_ticket'=>'Bilet digital','launcher'=>'Launcher'];
$badge=['dispenser'=>'D','player'=>'TV','widget_player'=>'TV','digital_ticket'=>'QR','launcher'=>'L']; ?>
<div class="topbar">
  <div>
    <div class="crumb"><a href="<?= e(url('admin/branches')) ?>">Filiale</a> › <?= e($branch['name']) ?></div>
    <h1 style="margin:.1rem 0"><?= e($branch['name']) ?></h1>
    <span class="muted"><?= e(trim(($branch['city']?:'').(($branch['city']&&$branch['country'])?', ':'').($branch['country']?:''))) ?: '—' ?></span>
  </div>
  <a class="btn" href="<?= e(url('admin/branches/'.$bid.'/edit')) ?>">Editeaza filiala</a>
</div>

<div class="tabs">
  <?php foreach($tabs as $k=>$t): $on=$tab===$k; ?>
    <a href="<?= e($turl($k)) ?>" class="<?= $on?'on':'' ?>"><span class="ic"><?= aicon($t[1]) ?></span><?= e($t[0]) ?></a>
  <?php endforeach; ?>
</div>

<?php if($tab==='services'): ?>
  <div class="topbar" style="margin-bottom:1rem"><h3 style="margin:0">Servicii</h3><a class="btn btn-primary" href="<?= e(url('admin/services/new?branch='.$bid)) ?>">+ Serviciu</a></div>
  <div class="cardgrid">
  <?php foreach($services as $r): ?>
    <div class="mcard">
      <div class="mhead"><span class="badge" style="background:<?= e($r['color']) ?>"><?= e($r['prefix']) ?></span>
        <div style="flex:1"><div class="nm"><?= e($r['name']) ?></div><div class="sub muted">interval <?= (int)$r['num_from'] ?>–<?= (int)$r['num_to'] ?></div></div></div>
      <div class="mbody grow"><?= $r['description']? e($r['description']):'' ?></div>
      <div class="card-foot"><span class="st <?= $r['status']==='active'?'on':'' ?>"><span class="d"></span><?= $r['status']==='active'?'Activ':'Inactiv' ?></span>
        <a class="lnk" href="<?= e(url('admin/services/'.$r['id'])) ?>">Editeaza</a></div>
    </div>
  <?php endforeach; ?>
  <?php if(!$services): ?><div class="empty">Niciun serviciu in aceasta filiala.</div><?php endif; ?>
  </div>

<?php elseif($tab==='counters'): ?>
  <div class="topbar" style="margin-bottom:1rem"><h3 style="margin:0">Ghisee</h3><a class="btn btn-primary" href="<?= e(url('admin/counters/new?branch='.$bid)) ?>">+ Ghiseu</a></div>
  <div class="cardgrid">
  <?php foreach($counters as $r): ?>
    <div class="mcard">
      <div class="mhead"><span class="badge" style="background:#2a2f3a"><?= e($r['code']) ?></span>
        <div style="flex:1"><div class="nm"><?= e($r['name']) ?></div><div class="sub muted"><?= $r['all_services']?'toate serviciile':((int)$r['svc_count'].' alocate') ?></div></div></div>
      <div class="mbody grow"></div>
      <div class="card-foot"><span class="st <?= $r['status']==='active'?'on':'' ?>"><span class="d"></span><?= e(ucfirst($r['status'])) ?></span>
        <a class="lnk" href="<?= e(url('admin/counters/'.$r['id'])) ?>">Editeaza</a></div>
    </div>
  <?php endforeach; ?>
  <?php if(!$counters): ?><div class="empty">Niciun ghiseu in aceasta filiala.</div><?php endif; ?>
  </div>

<?php else: ?>
  <div class="topbar" style="margin-bottom:1rem"><h3 style="margin:0">Dispozitive</h3><a class="btn btn-primary" href="<?= e(url('admin/devices/new?branch='.$bid)) ?>">+ Dispozitiv</a></div>
  <div class="cardgrid">
  <?php foreach($devices as $d): $du=url('launcher?key='.$d['connection_key']); ?>
    <div class="mcard">
      <div class="mhead"><span class="badge" style="background:#2a2f3a;font-size:.78rem"><?= e($badge[$d['type']]??'?') ?></span>
        <div style="flex:1"><div class="nm"><?= e($d['name']) ?></div><div class="sub muted"><?= e($labels[$d['type']]??$d['type']) ?></div></div></div>
      <div class="mbody"><code style="font-size:1.1rem;font-weight:800"><?= e($d['connection_key']) ?></code>
        <div style="margin-top:.6rem;display:flex;gap:.4rem;flex-wrap:wrap">
          <a class="btn btn-ghost" target="_blank" href="<?= e($du) ?>" style="padding:.4rem .7rem">Deschide</a>
          <?php if(in_array($d['type'],['player','widget_player'],true)): ?><a class="btn btn-primary" href="<?= e(url('admin/devices/'.$d['id'].'/player')) ?>" style="padding:.4rem .7rem">Design</a><?php endif; ?>
          <?php if(in_array($d['type'],['dispenser','digital_ticket'],true)): ?><a class="btn btn-primary" href="<?= e(url('admin/devices/'.$d['id'].'/dispenser')) ?>" style="padding:.4rem .7rem">Config</a><?php endif; ?>
        </div>
      </div>
      <div class="card-foot"><span class="st <?= $d['online']?'on':'' ?>"><span class="d"></span><?= $d['online']?'Online':'Offline' ?></span>
        <a class="lnk" href="<?= e(url('admin/devices/'.$d['id'])) ?>">Editeaza</a></div>
    </div>
  <?php endforeach; ?>
  <?php if(!$devices): ?><div class="empty">Niciun dispozitiv in aceasta filiala.</div><?php endif; ?>
  </div>
<?php endif; ?>
<?php require __DIR__.'/_footer.php'; ?>
