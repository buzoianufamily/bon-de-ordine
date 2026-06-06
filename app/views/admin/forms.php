<?php $title='Formulare'; $active='forms'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Formulare</h1><a class="btn btn-primary" href="<?= e(url('admin/forms/new')) ?>">+ Formular nou</a></div>
<p class="muted" style="margin-top:-.6rem">Colectezi date de la client la emiterea bonului (nume, telefon, motiv etc.). Atasezi formularul unui serviciu din pagina serviciului.</p>
<div class="kpis" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr));margin-top:1rem">
  <?php foreach($rows as $f): ?>
    <div class="card pad">
      <h3 style="margin:.1rem 0"><?= e($f['name']) ?></h3>
      <div style="display:flex;gap:1rem;color:var(--muted);font-size:.85rem;margin:.5rem 0">
        <span><strong style="color:var(--ink)"><?= (int)$f['count'] ?></strong> campuri</span>
        <span><strong style="color:var(--ink)"><?= (int)$f['used'] ?></strong> servicii</span>
      </div>
      <div style="display:flex;gap:.4rem">
        <a class="btn btn-primary" href="<?= e(url('admin/forms/'.$f['id'])) ?>">Editeaza</a>
        <form method="post" action="<?= e(url('admin/forms/'.$f['id'].'/delete')) ?>" style="margin-left:auto" onsubmit="return confirm('Stergi formularul?')"><?= csrf_field() ?><button class="btn btn-ghost" style="color:var(--danger)">Sterge</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if(!$rows): ?><div class="card pad" style="grid-column:1/-1;text-align:center;color:var(--muted);padding:2.5rem">Niciun formular. Creeaza primul.</div><?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
