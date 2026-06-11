<?php $title='Filiale'; $active='branches'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Filiale</h1><a class="btn btn-primary" href="<?= e(url('admin/branches/new')) ?>">+ Filiala noua</a></div>
<?= list_toolbar('Cauta filiala...') ?>
<div class="cardgrid wide">
<?php foreach($rows as $b): $loc=trim(($b['city']?:'').(($b['city']&&$b['country'])?', ':'').($b['country']?:'')); ?>
  <div class="mcard" data-name="<?= e(mb_strtolower($b['name'].' '.$loc)) ?>">
    <div class="mhead">
      <span class="badge" style="background:#2a2f3a"><?= e(mb_strtoupper(mb_substr($b['name'],0,1))) ?></span>
      <div style="flex:1"><div class="nm"><?= e($b['name']) ?></div>
        <div class="sub muted"><?= e($loc ?: '—') ?></div></div>
    </div>
    <div class="mbody grow" style="display:flex;gap:1.2rem">
      <span><strong style="color:var(--ink);font-size:1.1rem"><?= (int)$b['svc'] ?></strong> servicii</span>
      <span><strong style="color:var(--ink);font-size:1.1rem"><?= (int)$b['cnt'] ?></strong> ghisee</span>
      <span><strong style="color:var(--ink);font-size:1.1rem"><?= (int)$b['dev'] ?></strong> dispozitive</span>
    </div>
    <div class="card-foot">
      <span class="st <?= $b['active']?'on':'' ?>"><span class="d"></span><?= $b['active']?'Activa':'Inactiva' ?></span>
      <span class="acts">
        <a class="lnk" href="<?= e(url('admin/branches/'.$b['id'])) ?>">Deschide</a>
        <a class="lnk" href="<?= e(url('admin/branches/'.$b['id'].'/edit')) ?>">Editeaza</a>
        <form method="post" action="<?= e(url('admin/branches/'.$b['id'].'/duplicate')) ?>" data-confirm="Duplici filiala impreuna cu serviciile, ghiseele si dispozitivele ei? (dispozitivele primesc chei de conectare noi)"><?= csrf_field() ?><button class="lnk dup">Duplica</button></form>
        <form method="post" action="<?= e(url('admin/branches/'.$b['id'].'/delete')) ?>" data-confirm="Stergi filiala SI tot ce contine (servicii, ghisee, dispozitive, bilete)?"><?= csrf_field() ?><button class="lnk del">Sterge</button></form>
      </span>
    </div>
  </div>
<?php endforeach; ?>
<?php if(!$rows): ?><div class="empty"><div class="eic">🏢</div><p>Nicio filiala inca. Fiecare locatie fizica are propriile servicii, ghisee si dispozitive.</p><a class="btn btn-primary" href="<?= e(url('admin/branches/new')) ?>">+ Creeaza prima filiala</a></div><?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
