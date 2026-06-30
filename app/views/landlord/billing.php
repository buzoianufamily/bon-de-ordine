<?php /* Facturare landlord — date emitent + emitere facturi + evidenta plati. Fara DB. */
$b = $billing; $cur = $b['currency'] ?? 'RON';
// pre-completare client din tenantul selectat
$pf = null;
if (!empty($prefillHost)) foreach ($tenants as $t) if (strtolower((string)$t['host']) === $prefillHost) { $pf = $t; break; }
?>
<!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Landlord · Facturare</title>
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="base" content="<?= e(base_url()) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>:root{--accent:#00c375}
body{background:#0a0b0d;color:#e8eaef}.llwrap{max-width:1080px;margin:0 auto;padding:1.4rem}
h1,h2,h3{color:#f3f5f8}.card{background:#15171c;border-color:#1e2128}
input,select,textarea{background:#101216;color:#e8eaef;border-color:#272c36}label{color:#aab1bd}
th{color:#838b98}td,th{border-color:#1e2128}.muted{color:#838b98}
.btn{background:#1b1e25;color:#e8eaef;border-color:#2a2f3a}.btn-primary{background:var(--accent);color:#fff;border-color:var(--accent)}
code{background:#101216;color:#7CFFB2;padding:.12rem .4rem;border-radius:5px}
</style></head><body><div class="llwrap">
  <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
    <h1 style="margin:0">🧾 Facturare</h1>
    <span style="margin-left:auto;display:flex;gap:.5rem">
      <a class="btn" href="<?= e(url('landlord')) ?>">← Instanțe</a>
      <a class="btn" href="<?= e(url('landlord/logout')) ?>">Ieșire</a>
    </span>
  </div>
  <?php foreach (get_flashes() as $f): ?>
    <div class="pill" style="display:block;padding:.6rem .9rem;margin-bottom:.8rem;background:<?= $f['type']==='error'?'#fee2e2':'#dcfce7' ?>;color:<?= $f['type']==='error'?'#b91c1c':'#166534' ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>

  <div class="row" style="align-items:flex-start">
    <form method="post" action="<?= e(url('landlord/invoice-save')) ?>" class="card pad" style="flex:1;min-width:340px"><?= csrf_field() ?>
      <h3 style="margin-top:0">Factură nouă</h3>
      <?php if (empty($b['name'])): ?><div class="pill" style="display:block;background:#fef3c7;color:#92400e;padding:.5rem .8rem;margin-bottom:.6rem">Completează întâi datele emitentului (dreapta).</div><?php endif; ?>
      <div class="field"><label>Client (instanță)</label>
        <select name="host" onchange="this.form.submit_disabled">
          <option value="">— alege / completează manual —</option>
          <?php foreach ($tenants as $t): ?><option value="<?= e($t['host']) ?>" <?= ($pf && $pf['host']===$t['host'])?'selected':'' ?>><?= e(($t['name']??'') ?: $t['host']) ?> (<?= e($t['host']) ?>)</option><?php endforeach; ?>
        </select>
        <span class="muted" style="font-size:.74rem">Selectează apoi reîncarcă pentru pre-completare, sau completează manual mai jos.</span>
      </div>
      <div class="field"><label>Nume client (pe factură)</label><input name="client_name" value="<?= e($pf['name'] ?? '') ?>" required></div>
      <div class="row">
        <div class="field"><label>CUI client</label><input name="client_cui" value=""></div>
        <div class="field"><label>Adresă client</label><input name="client_address" value=""></div>
      </div>
      <div class="field"><label>Descriere</label><input name="description" value="Abonament Bon de ordine" required></div>
      <div class="row">
        <div class="field"><label>Perioada de la</label><input type="date" name="period_from" value="<?= e(date('Y-m-01')) ?>"></div>
        <div class="field"><label>Perioada până la</label><input type="date" name="period_to" value="<?= e(date('Y-m-t')) ?>"></div>
      </div>
      <div class="row">
        <div class="field"><label>Sumă netă (<?= e($cur) ?>)</label><input name="amount" inputmode="decimal" value="" required placeholder="ex: 49"></div>
        <div class="field"><label>TVA %</label><input type="number" name="vat_percent" min="0" max="100" value="<?= e((string)($b['vat_percent'] ?? 0)) ?>"></div>
      </div>
      <div class="row">
        <div class="field"><label>Scadență</label><input type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+15 days'))) ?>"></div>
        <div class="field"><label style="display:block">&nbsp;</label><label style="font-weight:400"><input type="checkbox" name="proforma" style="width:auto"> Proformă (nu factură fiscală)</label></div>
      </div>
      <div class="field"><label>Notă (opțional)</label><input name="inv_note" value=""></div>
      <button class="btn btn-primary">Emite factura</button>
    </form>

    <form method="post" action="<?= e(url('landlord/billing-settings')) ?>" class="card pad" style="flex:1;min-width:340px"><?= csrf_field() ?>
      <h3 style="margin-top:0">Datele emitentului (firma ta)</h3>
      <div class="field"><label>Denumire</label><input name="b_name" value="<?= e($b['name'] ?? '') ?>" required></div>
      <div class="row">
        <div class="field"><label>CUI</label><input name="b_cui" value="<?= e($b['cui'] ?? '') ?>"></div>
        <div class="field"><label>Reg. Com.</label><input name="b_regcom" value="<?= e($b['regcom'] ?? '') ?>"></div>
      </div>
      <div class="field"><label>Adresă</label><input name="b_address" value="<?= e($b['address'] ?? '') ?>"></div>
      <div class="row">
        <div class="field"><label>IBAN</label><input name="b_iban" value="<?= e($b['iban'] ?? '') ?>"></div>
        <div class="field"><label>Bancă</label><input name="b_bank" value="<?= e($b['bank'] ?? '') ?>"></div>
      </div>
      <div class="field"><label>Email</label><input name="b_email" type="email" value="<?= e($b['email'] ?? '') ?>"></div>
      <div class="row">
        <div class="field"><label>Serie facturi</label><input name="b_series" value="<?= e($b['series'] ?? 'BDO') ?>"></div>
        <div class="field"><label>Monedă</label><input name="b_currency" value="<?= e($cur) ?>"></div>
        <div class="field"><label>TVA % implicit</label><input type="number" name="b_vat" min="0" max="100" value="<?= e((string)($b['vat_percent'] ?? 0)) ?>"></div>
      </div>
      <button class="btn btn-primary">Salvează datele</button>
    </form>
  </div>

  <div class="card pad" style="margin-top:1.2rem;overflow-x:auto">
    <h3 style="margin-top:0">Facturi emise (<?= count($invoices) ?>)</h3>
    <?php if (!$invoices): ?><p class="muted">Nicio factură încă.</p><?php else: ?>
    <table style="min-width:820px"><thead><tr><th>Număr</th><th>Data</th><th>Client</th><th>Total</th><th>Scadență</th><th>Stare</th><th></th></tr></thead><tbody>
      <?php foreach (array_reverse($invoices) as $iv): $tt = landlord_invoice_total($iv); ?>
        <tr>
          <td><code><?= e(landlord_invoice_label($iv)) ?></code></td>
          <td class="muted"><?= e($iv['date'] ?? '') ?></td>
          <td><?= e($iv['client_name'] ?? '') ?></td>
          <td><strong><?= e(number_format($tt['total'], 2)) ?></strong> <?= e($iv['currency'] ?? '') ?></td>
          <td class="muted"><?= e($iv['due_date'] ?? '') ?></td>
          <td><?php if (!empty($iv['paid_at'])): ?><span style="color:#16a34a;font-weight:700">✓ plătită</span><br><span class="muted" style="font-size:.72rem"><?= e($iv['paid_at']) ?></span><?php else: ?><span style="color:#d97706;font-weight:700">neîncasată</span><?php endif; ?></td>
          <td style="text-align:right;white-space:nowrap">
            <a class="lnk" style="color:var(--accent);font-weight:700" target="_blank" href="<?= e(url('landlord/invoice').'?id='.rawurlencode($iv['id'])) ?>">Vezi / printează</a>
            <form method="post" action="<?= e(url('landlord/invoice-paid')) ?>" style="display:inline;margin-left:.6rem"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($iv['id']) ?>"><button class="lnk" style="background:none;border:none;cursor:pointer;color:#16a34a;font:inherit;font-weight:700"><?= !empty($iv['paid_at'])?'Marchează neîncasată':'Marchează plătită' ?></button></form>
            <form method="post" action="<?= e(url('landlord/invoice-delete')) ?>" style="display:inline;margin-left:.6rem" data-confirm="Ștergi factura din evidență?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($iv['id']) ?>"><button class="lnk del" style="background:none;border:none;cursor:pointer;color:#dc2626;font:inherit;font-weight:700">Șterge</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body></html>
