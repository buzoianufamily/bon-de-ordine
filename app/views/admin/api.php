<?php $title='API & Webhooks'; $active='api'; require __DIR__.'/_header.php';
$key = setting('api_key','');
$base = rtrim(base_url(),'/').'/api/v1';
$wurl = setting('webhook_url','');
$wsec = setting('webhook_secret','');
$wev  = array_filter(array_map('trim', explode(',', setting('webhook_events',''))));
$allEv = ['ticket.created'=>'Bon emis','ticket.called'=>'Bon apelat','ticket.serving'=>'In servire','ticket.served'=>'Finalizat','ticket.no_show'=>'Neprezentat','ticket.cancelled'=>'Anulat','ticket.transferred'=>'Transferat','ticket.recalled'=>'Rechemat','appointment.created'=>'Programare creata','appointment.cancelled'=>'Programare anulata','appointment.checked_in'=>'Check-in programare','appointment.rescheduled'=>'Programare mutata','appointment.no_show'=>'Programare neprezentata','sla.breach'=>'Alerta SLA (cozi peste tinta)','feedback.low'=>'Feedback cu nota mica'];
?>
<div class="topbar"><h1>API & Webhooks</h1></div>

<div class="row" style="align-items:flex-start">
  <div class="card pad" style="flex:1;min-width:320px">
    <h3 style="margin-top:0">Cheie API</h3>
    <p class="muted" style="margin-top:0;font-size:.85rem">Trimite-o in antetul <code>X-Api-Key</code> (sau <code>?key=</code>) la fiecare cerere.</p>
    <div class="field"><input readonly value="<?= e($key) ?>" onclick="this.select()" style="font-family:monospace"></div>
    <div style="display:flex;gap:.5rem;align-items:center">
      <form method="post" action="<?= e(url('admin/api')) ?>" data-confirm="Regenerezi cheia? Integrarile existente vor trebui actualizate."><?= csrf_field() ?>
        <input type="hidden" name="regen" value="1"><button class="btn">↻ Regenereaza cheia</button></form>
    </div>
    <p class="muted" style="font-size:.82rem;margin-bottom:0">URL de baza: <code><?= e($base) ?></code> · Limita: <strong>120 cereri/minut</strong> (antete <code>X-RateLimit-*</code>, raspuns <code>429</code> la depasire).</p>
  </div>

  <form method="post" action="<?= e(url('admin/api')) ?>" class="card pad" style="flex:1;min-width:320px"><?= csrf_field() ?>
    <h3 style="margin-top:0">Webhook</h3>
    <p class="muted" style="margin-top:0;font-size:.85rem">La fiecare eveniment, trimitem un <strong>POST JSON</strong> catre acest URL. Daca pui un secret, semnam corpul cu HMAC-SHA256 in antetul <code>X-Signature</code>.</p>
    <div class="field"><label>URL webhook (https)</label><input name="webhook_url" value="<?= e($wurl) ?>" placeholder="https://exemplu.ro/webhook"></div>
    <div class="field"><label>Secret (optional, pentru semnatura)</label><input name="webhook_secret" value="<?= e($wsec) ?>"></div>
    <div class="field"><label>Evenimente trimise</label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.3rem">
        <?php foreach($allEv as $code=>$lab): ?>
          <label style="display:flex;align-items:center;gap:.4rem;margin:0;font-weight:500"><input type="checkbox" name="webhook_events[]" value="<?= e($code) ?>" <?= (in_array($code,$wev,true)||!$wev)?'checked':'' ?> style="width:auto"><?= e($lab) ?> <span class="muted" style="font-size:.72rem"><?= e($code) ?></span></label>
        <?php endforeach; ?>
      </div>
      <span class="muted" style="font-size:.78rem">Niciun eveniment bifat = se trimit toate.</span>
    </div>
    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
      <button class="btn btn-primary">Salveaza webhook</button>
      <button type="button" class="btn btn-ghost" id="whTest">↗ Trimite webhook de test</button>
      <span id="whTestRes" class="muted" style="font-size:.82rem"></span>
    </div>
    <p class="muted" style="font-size:.78rem;margin:.5rem 0 0">Testul trimite un eveniment <code>ping</code> către URL-ul <strong>salvat</strong> (semnat HMAC dacă ai pus un secret). Salvează întâi, apoi testează.</p>
  </form>
</div>
<script>
document.getElementById('whTest').addEventListener('click', async function(){
  var res = document.getElementById('whTestRes');
  res.textContent = 'Se trimite…'; res.style.color = 'var(--muted)';
  var r = await QMS.api('admin/api/test-webhook', {});
  if (r && r.ok) { res.style.color = 'var(--ok)'; res.textContent = '✓ Livrat (cod ' + r.status + (r.signed ? ', semnat' : '') + ')'; }
  else { res.style.color = 'var(--danger)'; res.textContent = '✗ ' + ((r && r.error) ? r.error : 'Eroare necunoscuta'); }
});
</script>

<div class="card pad" style="margin-top:1.2rem">
  <h3 style="margin-top:0">Endpoint-uri (v1)</h3>
  <table>
    <thead><tr><th>Metoda</th><th>Cale</th><th>Descriere</th></tr></thead>
    <tbody style="font-size:.9rem">
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/state?branch=1</code></td><td>Starea cozii (apelate, la rand, ghisee)</td></tr>
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/branches</code></td><td>Filialele active</td></tr>
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/services?branch=1</code></td><td>Serviciile active</td></tr>
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/counters?branch=1</code></td><td>Ghiseele</td></tr>
      <tr><td><span class="pill" style="background:#dbeafe;color:#1e40af">POST</span></td><td><code>/api/v1/tickets</code></td><td>Emite bon: <code>{service_id, priority?, channel?}</code></td></tr>
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/tickets/{token}</code></td><td>Starea unui bon (pozitie, timp estimat)</td></tr>
      <tr><td><span class="pill" style="background:#fee2e2;color:#b91c1c">DELETE</span></td><td><code>/api/v1/tickets/{token}</code></td><td>Anuleaza un bon (in asteptare/chemat)</td></tr>
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/slots?service_id=&date=</code></td><td>Sloturi disponibile pentru programare</td></tr>
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/appointments?date=&service_id=</code></td><td>Lista programarilor dintr-o zi</td></tr>
      <tr><td><span class="pill" style="background:#dbeafe;color:#1e40af">POST</span></td><td><code>/api/v1/appointments</code></td><td>Rezerva: <code>{service_id, slot_start, name?, phone?, email?}</code></td></tr>
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/appointments/{token}</code></td><td>Starea unei programari</td></tr>
      <tr><td><span class="pill" style="background:#dbeafe;color:#1e40af">POST</span></td><td><code>/api/v1/appointments/{token}/checkin</code></td><td>Check-in: genereaza bonul</td></tr>
      <tr><td><span class="pill" style="background:#dbeafe;color:#1e40af">POST</span></td><td><code>/api/v1/appointments/{token}/reschedule</code></td><td>Muta in alt slot: <code>{slot_start}</code></td></tr>
      <tr><td><span class="pill" style="background:#fee2e2;color:#b91c1c">DELETE</span></td><td><code>/api/v1/appointments/{token}</code></td><td>Anuleaza o programare (rezervata)</td></tr>
      <tr><td><span class="pill" style="background:#dbeafe;color:#1e40af">POST</span></td><td><code>/api/v1/feedback</code></td><td>Evaluare: <code>{rating:1-5, comment?, ticket_token?}</code></td></tr>
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/stats?from=&to=&branch=</code></td><td>Rezumat KPI pe interval</td></tr>
    </tbody>
  </table>
  <h4 style="margin:1.1rem 0 .4rem">Exemplu — emitere bon</h4>
  <pre style="background:#0f1115;color:#cde3ff;padding:.9rem;border-radius:10px;overflow:auto;font-size:.82rem">curl -X POST <?= e($base) ?>/tickets \
  -H "X-Api-Key: <?= e($key) ?>" \
  -H "Content-Type: application/json" \
  -d '{"service_id":1,"priority":false}'</pre>
  <h4 style="margin:1.1rem 0 .4rem">Exemplu — payload webhook</h4>
  <pre style="background:#0f1115;color:#cde3ff;padding:.9rem;border-radius:10px;overflow:auto;font-size:.82rem">{
  "event": "ticket.called",
  "ts": 1733829000,
  "data": { "id": 42, "label": "A012", "status": "called",
            "branch_id": 1, "service_id": 1, "counter_id": 3,
            "public_token": "…", "called_at": "2026-06-10 10:00:00" }
}</pre>
</div>

<?php $whLog = all('SELECT * FROM webhook_log ORDER BY id DESC LIMIT 15'); ?>
<div class="card pad" style="margin-top:1.2rem">
  <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
    <h3 style="margin:0">Jurnal livrări webhook</h3>
    <span class="muted" style="font-size:.82rem">ultimele <?= count($whLog) ?> încercări</span>
    <?php if($whLog): ?>
    <a class="btn btn-ghost" href="<?= e(url('admin/api/webhook-log-export')) ?>" style="margin-left:auto;padding:.25rem .6rem;font-size:.78rem;text-transform:none;letter-spacing:0">⬇ CSV</a>
    <form method="post" action="<?= e(url('admin/api/clear-webhook-log')) ?>" data-confirm="Golești jurnalul de livrări webhook?"><?= csrf_field() ?><button class="btn btn-ghost" style="padding:.25rem .6rem;font-size:.78rem;text-transform:none;letter-spacing:0">Golește</button></form>
    <?php endif; ?>
  </div>
  <?php if($whLog): ?>
  <table style="margin-top:.6rem">
    <thead><tr><th>Când</th><th>Eveniment</th><th>Rezultat</th><th>URL</th></tr></thead>
    <tbody style="font-size:.88rem">
    <?php foreach($whLog as $w): ?>
      <tr>
        <td style="white-space:nowrap"><?= e(date('d.m H:i:s', strtotime($w['created_at']))) ?></td>
        <td><code><?= e($w['event']) ?></code></td>
        <td><?php if($w['ok']): ?><span class="pill" style="background:#dcfce7;color:#166534">✓ <?= (int)$w['status_code'] ?></span><?php else: ?><span class="pill" style="background:#fee2e2;color:#b91c1c">✗ <?= $w['status_code']!==null ? (int)$w['status_code'] : (e($w['error']) ?: 'eroare') ?></span><?php endif; ?></td>
        <td class="muted" style="font-size:.78rem;word-break:break-all"><?= e($w['url']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="muted" style="margin:.6rem 0 0;font-size:.86rem">Nicio livrare încă. Apasă „Trimite webhook de test" sau emite un bon cu webhook activ.</p>
  <?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
