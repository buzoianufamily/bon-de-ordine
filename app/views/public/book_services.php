<?php $title='Programari'; require __DIR__.'/_head.php'; ?>
<body><div class="center"><div class="portal">
  <div style="text-align:center"><h1>Programare online</h1><p class="muted">Alege serviciul pentru care vrei sa te programezi</p></div>
  <div class="portal-grid">
    <?php foreach($services as $s): ?>
      <a href="<?= e(url('book/'.$s['id'])) ?>"><div class="tile">
        <div style="display:flex;align-items:center;gap:.6rem"><span class="tag" style="background:<?= e($s['color']) ?>"><?= e($s['prefix']) ?></span><h3 style="margin:0"><?= e($s['name']) ?></h3></div>
        <p class="muted" style="margin:.4rem 0 0"><?= e($s['branch_name']) ?></p>
      </div></a>
    <?php endforeach; ?>
    <?php if(!$services): ?><p class="muted" style="text-align:center;grid-column:1/-1">Momentan nu sunt servicii disponibile pentru programare.</p><?php endif; ?>
  </div>
</div></div></body></html>
