<?php $title='Utilizatori'; $active='users'; require __DIR__.'/_header.php';
$roleLabel=['admin'=>'Administrator','manager'=>'Manager','agent'=>'Operator']; ?>
<div class="topbar"><h1>Utilizatori</h1><a class="btn btn-primary" href="<?= e(url('admin/users/new')) ?>">+ Utilizator nou</a></div>
<?= list_toolbar('Cauta utilizator...') ?>
<div class="cardgrid">
<?php foreach($rows as $r): ?>
  <div class="mcard" data-name="<?= e(mb_strtolower($r['name'].' '.$r['email'].' '.$r['role'])) ?>">
    <div class="mhead">
      <span class="badge" style="background:#2a2f3a"><?= e(mb_strtoupper(mb_substr($r['name'],0,1))) ?></span>
      <div style="flex:1">
        <div class="nm"><?= e($r['name']) ?></div>
        <div class="sub muted"><?= e($roleLabel[$r['role']] ?? $r['role']) ?></div>
      </div>
    </div>
    <div class="mbody grow"><?= e($r['email']) ?></div>
    <div class="card-foot">
      <span class="st <?= $r['active']?'on':'' ?>"><span class="d"></span><?= $r['active']?'Activ':'Inactiv' ?></span>
      <span>
        <a class="lnk" href="<?= e(url('admin/users/'.$r['id'])) ?>">Editeaza</a>
        <?php if($r['id']!=current_user()['id']): ?><form method="post" action="<?= e(url('admin/users/'.$r['id'].'/delete')) ?>" style="display:inline;margin-left:.7rem" onsubmit="return confirm('Stergi utilizatorul?')"><?= csrf_field() ?><button class="lnk del">Sterge</button></form><?php endif; ?>
      </span>
    </div>
  </div>
<?php endforeach; ?>
<?php if(!$rows): ?><div class="empty">Niciun utilizator.</div><?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
