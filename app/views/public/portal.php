<?php $title='Portal · '.setting('brand_name','Bon de ordine'); require __DIR__.'/_head.php'; ?>
<body><div class="center"><div class="portal">
  <div style="text-align:center">
    <h1 style="font-size:2.2rem"><?= e(setting('brand_name','Sistem Bon de Ordine')) ?></h1>
    <p class="muted">Sistem de gestionare a cozilor de asteptare</p>
  </div>
  <div class="portal-grid">
    <a href="<?= e(url('admin')) ?>"><div class="tile"><h3>Administrare</h3><p class="muted">Servicii, ghisee, dispozitive, utilizatori, rapoarte.</p></div></a>
    <a href="<?= e(url('counter')) ?>"><div class="tile"><h3>Terminal operator</h3><p class="muted">Apeleaza bilete de la ghiseu.</p></div></a>
    <?php if((int) val('SELECT COUNT(*) FROM services WHERE appt_enabled=1 AND status="active"') > 0): ?>
      <a href="<?= e(url('book')) ?>"><div class="tile"><h3>Programare online</h3><p class="muted">Rezerva o ora pentru un serviciu.</p></div></a>
    <?php endif; ?>
  </div>
  <div class="card pad" style="margin-top:1rem">
    <strong>Dispozitive</strong>
    <p class="muted" style="margin:.4rem 0">Fiecare dispenser / afisaj se deschide cu cheia lui de conectare:</p>
    <code style="display:block;background:#0f1115;color:#7CFFB2;padding:.7rem;border-radius:8px;font-size:.85rem">
      <?= e(url('launcher?key=CHEIE')) ?>
    </code>
    <p class="muted" style="margin-top:.5rem;font-size:.85rem">Vezi cheile in Administrare → Dispozitive.</p>
  </div>
</div></div></body></html>
