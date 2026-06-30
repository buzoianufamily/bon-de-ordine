<?php /* Factura/proforma printabila (Print -> Save as PDF). Tema deschisa pentru hartie. */
$b = $billing; $t = landlord_invoice_total($inv);
$cur = $inv['currency'] ?? 'RON';
$label = landlord_invoice_label($inv);
$kind = !empty($inv['proforma']) ? 'PROFORMĂ' : 'FACTURĂ';
$period = ($inv['period_from'] ?? '') && ($inv['period_to'] ?? '') ? ($inv['period_from'] . ' — ' . $inv['period_to']) : '';
?>
<!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($kind . ' ' . $label) ?></title>
<style>
*{box-sizing:border-box}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#111;background:#f3f4f6;margin:0;padding:1.5rem}
.sheet{max-width:800px;margin:0 auto;background:#fff;padding:2.4rem;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.1)}
.toolbar{max-width:800px;margin:0 auto .9rem;display:flex;gap:.5rem;justify-content:flex-end}
.btn{font:inherit;padding:.5rem .9rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#111;cursor:pointer;text-decoration:none}
.btn-primary{background:#00c375;color:#fff;border-color:#00c375}
h1{font-size:1.5rem;margin:0 0 .2rem}
.row{display:flex;gap:2rem;flex-wrap:wrap;justify-content:space-between}
.box{flex:1;min-width:220px}
.box h3{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin:0 0 .35rem}
.box p{margin:.1rem 0;line-height:1.5}
table{width:100%;border-collapse:collapse;margin:1.6rem 0}
th,td{text-align:left;padding:.6rem;border-bottom:1px solid #e5e7eb}
th{font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:#6b7280}
td.r,th.r{text-align:right}
.totals{margin-left:auto;width:280px}
.totals td{border:none;padding:.3rem .6rem}
.totals .grand td{border-top:2px solid #111;font-weight:800;font-size:1.1rem}
.paid{display:inline-block;margin-top:.6rem;color:#166534;font-weight:800;border:2px solid #166534;padding:.2rem .6rem;border-radius:6px;transform:rotate(-4deg)}
.muted{color:#6b7280}
@media print{body{background:#fff;padding:0}.sheet{box-shadow:none;border-radius:0;max-width:none}.toolbar{display:none}}
</style></head><body>
<div class="toolbar">
  <a class="btn" href="<?= e(url('landlord/billing')) ?>">← Înapoi</a>
  <button class="btn btn-primary" onclick="window.print()">🖨 Printează / Salvează PDF</button>
</div>
<div class="sheet">
  <div class="row" style="align-items:flex-start;margin-bottom:1.6rem">
    <div class="box">
      <h1><?= e($kind) ?></h1>
      <p class="muted">Seria/Nr: <strong style="color:#111"><?= e($label) ?></strong></p>
      <p class="muted">Data: <?= e($inv['date'] ?? '') ?> · Scadență: <?= e($inv['due_date'] ?? '') ?></p>
    </div>
  </div>
  <div class="row" style="margin-bottom:1rem">
    <div class="box">
      <h3>Furnizor</h3>
      <p><strong><?= e($b['name'] ?? '—') ?></strong></p>
      <?php if (!empty($b['cui'])): ?><p>CUI: <?= e($b['cui']) ?><?= !empty($b['regcom']) ? ' · '.e($b['regcom']) : '' ?></p><?php endif; ?>
      <?php if (!empty($b['address'])): ?><p><?= e($b['address']) ?></p><?php endif; ?>
      <?php if (!empty($b['iban'])): ?><p>IBAN: <?= e($b['iban']) ?><?= !empty($b['bank']) ? ' ('.e($b['bank']).')' : '' ?></p><?php endif; ?>
      <?php if (!empty($b['email'])): ?><p><?= e($b['email']) ?></p><?php endif; ?>
    </div>
    <div class="box">
      <h3>Client</h3>
      <p><strong><?= e($inv['client_name'] ?? '—') ?></strong></p>
      <?php if (!empty($inv['client_cui'])): ?><p>CUI: <?= e($inv['client_cui']) ?></p><?php endif; ?>
      <?php if (!empty($inv['client_address'])): ?><p><?= e($inv['client_address']) ?></p><?php endif; ?>
      <?php if (!empty($inv['host'])): ?><p class="muted"><?= e($inv['host']) ?></p><?php endif; ?>
    </div>
  </div>

  <table>
    <thead><tr><th>Descriere</th><th class="r">Net</th><th class="r">TVA (<?= (int)($inv['vat_percent'] ?? 0) ?>%)</th><th class="r">Total</th></tr></thead>
    <tbody><tr>
      <td><?= e($inv['description'] ?? '') ?><?= $period ? '<br><span class="muted" style="font-size:.85rem">Perioada: '.e($period).'</span>' : '' ?></td>
      <td class="r"><?= e(number_format($t['net'], 2)) ?></td>
      <td class="r"><?= e(number_format($t['vat'], 2)) ?></td>
      <td class="r"><?= e(number_format($t['total'], 2)) ?></td>
    </tr></tbody>
  </table>

  <table class="totals">
    <tr><td>Subtotal</td><td class="r"><?= e(number_format($t['net'], 2)) ?> <?= e($cur) ?></td></tr>
    <tr><td>TVA</td><td class="r"><?= e(number_format($t['vat'], 2)) ?> <?= e($cur) ?></td></tr>
    <tr class="grand"><td>Total</td><td class="r"><?= e(number_format($t['total'], 2)) ?> <?= e($cur) ?></td></tr>
  </table>

  <?php if (!empty($inv['paid_at'])): ?><div class="paid">PLĂTITĂ · <?= e($inv['paid_at']) ?></div><?php endif; ?>
  <?php if (!empty($inv['note'])): ?><p class="muted" style="margin-top:1.4rem"><?= e($inv['note']) ?></p><?php endif; ?>
  <?php if (!empty($inv['proforma'])): ?><p class="muted" style="margin-top:1.4rem;font-size:.85rem">Document proformă — nu reprezintă factură fiscală.</p><?php endif; ?>
</div>
</body></html>
