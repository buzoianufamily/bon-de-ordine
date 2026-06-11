<?php $title='Alege ghiseul'; require __DIR__.'/_head.php'; ?>
<body><div class="center"><div class="portal">
  <div style="text-align:center"><h1>Alege ghiseul</h1><p class="muted">Bun venit, <?= e($u['name']) ?> · <?php if(setting('mod_concierge','1')==='1'): ?><a href="<?= e(url('concierge')) ?>">🛎 Concierge</a> · <?php endif; ?><a href="<?= e(url('logout')) ?>">iesire</a></p></div>
  <?php foreach (get_flashes() as $f): ?>
    <div class="pill" style="display:block;text-align:center;background:#fee2e2;color:#b91c1c;margin-bottom:.8rem"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
  <div class="portal-grid">
    <?php foreach($counters as $c): ?>
      <a href="<?= e(url('counter/'.$c['id'])) ?>"><div class="tile">
        <h3><?= e($c['code']) ?> · <?= e($c['name']) ?></h3>
        <p class="muted">Status: <?= e($c['status']) ?></p>
      </div></a>
    <?php endforeach; ?>
    <?php if(!$counters): ?><p class="muted">Niciun ghiseu. Creeaza unul in Administrare → Ghisee.</p><?php endif; ?>
  </div>
</div></div></body></html>
