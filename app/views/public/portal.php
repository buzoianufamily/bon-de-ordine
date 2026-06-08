<?php $title='Portal · '.setting('brand_name','Bon de ordine'); require __DIR__.'/_head.php';
$logo = setting('brand_logo','');
$hasAppt = (int) val('SELECT COUNT(*) FROM services WHERE appt_enabled=1 AND status="active"') > 0;
$tiles = [
  ['Backoffice', 'Servicii, ghisee, dispozitive, utilizatori, rapoarte.', url('admin'), 'M3 13h8V3H3zM13 21h8V11h-8zM13 3v6h8V3zM3 21h8v-6H3z'],
  ['Terminal operator', 'Apeleaza si gestioneaza bilete de la ghiseu.', url('counter'), 'M4 5h16v10H4zM8 19h8M12 15v4'],
];
if ($hasAppt) $tiles[] = ['Programare online', 'Rezerva o ora pentru un serviciu.', url('book'), 'M3 4h18v17H3zM3 9h18M8 2v4M16 2v4'];
?>
<body class="portalpage"><div class="center"><div class="portal">
  <div style="text-align:center;margin-bottom:1.6rem">
    <?php if($logo): ?><img src="<?= e($logo) ?>" alt="" style="max-height:54px;margin-bottom:.6rem"><?php else: ?>
      <div class="plogo"><?= e(setting('brand_name','Sistem Bon de Ordine')) ?></div><?php endif; ?>
    <p class="muted" style="margin:.2rem 0 0">Sistem de gestionare a cozilor de asteptare</p>
  </div>
  <div class="portal-grid">
    <?php foreach($tiles as $t): ?>
      <a href="<?= e($t[2]) ?>"><div class="tile">
        <span class="tileic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $t[3] ?>"/></svg></span>
        <h3><?= e($t[0]) ?></h3>
        <p class="muted" style="font-size:.88rem;flex:1"><?= e($t[1]) ?></p>
        <span class="access">Acceseaza →</span>
      </div></a>
    <?php endforeach; ?>
  </div>
  <div class="card pad" style="margin-top:1.2rem">
    <strong>Dispozitive (dispenser / afisaj / bilet digital)</strong>
    <p class="muted" style="margin:.4rem 0">Fiecare dispozitiv se deschide cu cheia lui de conectare:</p>
    <code style="display:block;background:#0f1115;color:#7CFFB2;padding:.7rem;border-radius:8px;font-size:.85rem"><?= e(url('launcher?key=CHEIE')) ?></code>
    <p class="muted" style="margin-top:.5rem;font-size:.85rem">Vezi cheile in Backoffice → Dispozitive.</p>
  </div>
</div></div></body></html>
