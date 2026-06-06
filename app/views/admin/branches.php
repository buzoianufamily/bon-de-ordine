<?php $title='Filiale'; $active='branches'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Filiale</h1><a class="btn btn-primary" href="<?= e(url('admin/branches/new')) ?>">+ Filiala noua</a></div>
<div class="kpis" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
  <?php foreach($rows as $b): ?>
    <div class="card pad">
      <div style="display:flex;justify-content:space-between;align-items:start">
        <div><h3 style="margin:0"><?= e($b['name']) ?></h3>
          <span class="muted" style="font-size:.85rem"><?= e(trim(($b['city']?:'').(($b['city']&&$b['country'])?', ':'').($b['country']?:''))) ?: '—' ?></span></div>
        <span class="pill" style="background:<?= $b['active']?'#dcfce7':'#f1f5f9' ?>;color:<?= $b['active']?'#166534':'#64748b' ?>"><?= $b['active']?'Activa':'Inactiva' ?></span>
      </div>
      <div style="display:flex;gap:1.2rem;margin:.9rem 0;color:var(--muted);font-size:.85rem">
        <span><strong style="color:var(--ink);font-size:1.1rem"><?= (int)$b['svc'] ?></strong> servicii</span>
        <span><strong style="color:var(--ink);font-size:1.1rem"><?= (int)$b['cnt'] ?></strong> ghisee</span>
        <span><strong style="color:var(--ink);font-size:1.1rem"><?= (int)$b['dev'] ?></strong> dispozitive</span>
      </div>
      <div style="display:flex;gap:.4rem">
        <a class="btn btn-primary" href="<?= e(url('admin/branches/'.$b['id'])) ?>">Deschide</a>
        <a class="btn btn-ghost" href="<?= e(url('admin/branches/'.$b['id'].'/edit')) ?>">Editeaza</a>
        <form method="post" action="<?= e(url('admin/branches/'.$b['id'].'/delete')) ?>" style="display:inline" onsubmit="return confirm('Stergi filiala SI tot ce contine (servicii, ghisee, dispozitive, bilete)?')"><?= csrf_field() ?><button class="btn btn-ghost" style="color:var(--danger)">Sterge</button></form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
