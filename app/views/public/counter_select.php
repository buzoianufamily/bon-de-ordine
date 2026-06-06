<?php $title='Alege ghiseul'; require __DIR__.'/_head.php'; ?>
<body><div class="center"><div class="portal">
  <div style="text-align:center"><h1>Alege ghiseul</h1><p class="muted">Bun venit, <?= e($u['name']) ?> · <a href="<?= e(url('logout')) ?>">iesire</a></p></div>
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
