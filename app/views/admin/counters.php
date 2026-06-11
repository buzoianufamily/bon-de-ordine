<?php $title='Ghisee'; $active='counters'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Ghisee</h1><a class="btn btn-primary" href="<?= e(url('admin/counters/new')) ?>">+ Ghiseu nou</a></div>
<?= list_toolbar('Cauta ghiseu...') ?>
<div class="cardgrid">
<?php foreach($rows as $r): ?>
  <div class="mcard" data-name="<?= e(mb_strtolower($r['code'].' '.$r['name'].' '.$r['branch_name'])) ?>">
    <div class="mhead">
      <span class="badge" style="background:#2a2f3a"><?= e($r['code']) ?></span>
      <div style="flex:1">
        <div class="nm"><?= e($r['name']) ?></div>
        <div class="sub muted"><?= e($r['branch_name']) ?></div>
      </div>
    </div>
    <div class="mbody grow"><span class="muted">Servicii:</span> <?= $r['all_services']?'toate din filiala':((int)$r['svc_count'].' alocate') ?>
      <div class="muted" style="font-size:.72rem;margin-top:.5rem;text-transform:uppercase;letter-spacing:.05em">Afisaj birou (tableta)</div>
      <input readonly value="<?= e(url('cd/'.$r['id'])) ?>" onclick="this.select()" style="font-size:.76rem;padding:.4rem;margin-top:.25rem">
    </div>
    <div class="card-foot">
      <span class="st <?= $r['status']==='active'?'on':'' ?>"><span class="d"></span><?= e(ucfirst($r['status'])) ?></span>
      <span>
        <a class="lnk" target="_blank" href="<?= e(url('cd/'.$r['id'])) ?>">Afisaj</a>
        <a class="lnk" href="<?= e(url('admin/counters/'.$r['id'])) ?>" style="margin-left:.7rem">Editeaza</a>
        <form method="post" action="<?= e(url('admin/counters/'.$r['id'].'/delete')) ?>" style="display:inline;margin-left:.7rem" data-confirm="Stergi ghiseul?"><?= csrf_field() ?><button class="lnk del">Sterge</button></form>
      </span>
    </div>
  </div>
<?php endforeach; ?>
<?php if(!$rows): ?><div class="empty">Niciun ghiseu. Creeaza primul.</div><?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
