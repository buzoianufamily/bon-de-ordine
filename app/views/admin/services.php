<?php $title='Servicii'; $active='services'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Servicii</h1><a class="btn btn-primary" href="<?= e(url('admin/services/new')) ?>">+ Serviciu nou</a></div>
<?= list_toolbar('Cauta serviciu...') ?>
<div class="cardgrid">
<?php foreach($rows as $r):
  $open=null; if(!empty($r['active_hours']) && ($sc=json_decode($r['active_hours'],true)) && !empty($sc['enabled'])) $open=service_is_open($r); ?>
  <div class="mcard" data-name="<?= e(mb_strtolower($r['prefix'].' '.$r['name'].' '.$r['branch_name'])) ?>">
    <div class="mhead">
      <span class="badge" style="background:<?= e($r['color']) ?>"><?= e($r['prefix']) ?></span>
      <div style="flex:1">
        <div class="nm"><?= e($r['name']) ?></div>
        <div class="sub muted"><?= e($r['branch_name']) ?> · interval <?= (int)$r['num_from'] ?>–<?= (int)$r['num_to'] ?><?= $r['allow_priority']?' · prioritar':'' ?></div>
      </div>
    </div>
    <div class="mbody grow"><?= $r['description']? e($r['description']) : '' ?></div>
    <div class="card-foot">
      <span class="st <?= $r['status']==='active'?'on':'' ?>"><span class="d"></span><?= $r['status']==='active'?'Activ':'Inactiv' ?><?php if($open!==null): ?> · <?= $open?'deschis acum':'inchis acum' ?><?php endif; ?></span>
      <span>
        <a class="lnk" href="<?= e(url('admin/services/'.$r['id'])) ?>">Editeaza</a>
        <form method="post" action="<?= e(url('admin/services/'.$r['id'].'/delete')) ?>" style="display:inline;margin-left:.7rem" onsubmit="return confirm('Stergi serviciul?')"><?= csrf_field() ?><button class="lnk del">Sterge</button></form>
      </span>
    </div>
  </div>
<?php endforeach; ?>
<?php if(!$rows): ?><div class="empty">Niciun serviciu. Creeaza primul.</div><?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
