<?php $title='Coduri QR dispozitive'; $active='devices'; require __DIR__.'/_header.php';
$labels=['dispenser'=>'Dispenser bilete','player'=>'Afisaj TV','widget_player'=>'Afisaj TV (widget)','digital_ticket'=>'Bilet digital QR','launcher'=>'Launcher'];
?>
<style>
@media print{
  .side,.adminbar,.topbar .btn,.noprint{display:none!important}
  .shell,.main,.content{display:block!important;margin:0!important;padding:0!important}
  .qrcard{break-inside:avoid;border:1px solid #ccc!important}
  body{background:#fff!important}
}
.qrgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;margin-top:1rem}
.qrcard{border:1px solid var(--line,#e6e8ec);border-radius:12px;padding:1rem;text-align:center;background:var(--card,#fff)}
.qrcard img{width:180px;height:180px;background:#fff;border-radius:8px}
.qrcard .nm{font-weight:800;margin:.5rem 0 .15rem}
.qrcard code{font-size:.95rem;font-weight:700;letter-spacing:.04em}
.qrcard .lnk{font-size:.7rem;word-break:break-all;color:var(--muted,#64748b);margin-top:.3rem}
</style>
<div class="topbar"><h1>Coduri QR dispozitive</h1>
  <div style="display:flex;gap:.5rem">
    <a class="btn btn-ghost" href="<?= e(url('admin/devices')) ?>">← Dispozitive</a>
    <button class="btn btn-primary noprint" onclick="window.print()">🖨 Printează</button>
  </div>
</div>
<p class="muted noprint" style="margin-top:-.6rem;max-width:720px">Scanează codul cu camera mini‑PC‑ului/tabletei (sau introdu linkul în aplicația Android) ca să deschizi dispozitivul. Pagină optimizată pentru printare — lipește fiecare cod lângă dispozitivul lui.</p>
<div class="qrgrid">
<?php foreach($rows as $d): $u=url('launcher?key='.$d['connection_key']);
  $qr='https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=0&data='.rawurlencode($u); ?>
  <div class="qrcard">
    <img src="<?= e($qr) ?>" alt="QR <?= e($d['name']) ?>">
    <div class="nm"><?= e($d['name']) ?></div>
    <div class="muted" style="font-size:.8rem"><?= e($labels[$d['type']]??$d['type']) ?> · <?= e($d['branch_name']) ?></div>
    <div style="margin-top:.35rem"><code><?= e($d['connection_key']) ?></code></div>
    <div class="lnk"><?= e($u) ?></div>
  </div>
<?php endforeach; ?>
<?php if(!$rows): ?><p class="muted">Niciun dispozitiv. Creează unul întâi.</p><?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
