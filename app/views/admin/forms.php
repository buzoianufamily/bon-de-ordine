<?php $title='Formulare'; $active='forms'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Formulare</h1><a class="btn btn-primary" href="<?= e(url('admin/forms/new')) ?>">+ Formular nou</a></div>
<p class="muted" style="margin-top:-.6rem;margin-bottom:1rem">Colectezi date de la client la emiterea bonului (nume, telefon, motiv etc.). Atasezi formularul unui serviciu din pagina serviciului.</p>
<?= list_toolbar('Cauta formular...') ?>
<div class="cardgrid">
<?php foreach($rows as $f): ?>
  <div class="mcard" data-name="<?= e(mb_strtolower($f['name'])) ?>">
    <div class="mhead">
      <span class="badge" style="background:#2a2f3a"><?= e(mb_strtoupper(mb_substr($f['name'],0,1))) ?></span>
      <div style="flex:1"><div class="nm"><?= e($f['name']) ?></div>
        <div class="sub muted"><strong style="color:var(--ink)"><?= (int)$f['count'] ?></strong> campuri · <strong style="color:var(--ink)"><?= (int)$f['used'] ?></strong> servicii</div></div>
    </div>
    <div class="mbody grow"></div>
    <div class="card-foot">
      <span class="st"><span class="d" style="background:var(--accent)"></span><?= (int)$f['used'] ?> in uz</span>
      <span>
        <a class="lnk" href="<?= e(url('admin/forms/'.$f['id'])) ?>">Editeaza</a>
        <form method="post" action="<?= e(url('admin/forms/'.$f['id'].'/delete')) ?>" style="display:inline;margin-left:.7rem" data-confirm="Stergi formularul?"><?= csrf_field() ?><button class="lnk del">Sterge</button></form>
      </span>
    </div>
  </div>
<?php endforeach; ?>
<?php if(!$rows): ?><div class="empty">Niciun formular. Creeaza primul.</div><?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
