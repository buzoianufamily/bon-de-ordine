<?php $title='API & Webhooks'; $active='api'; require __DIR__.'/_header.php';
$key = setting('api_key','');
$base = rtrim(base_url(),'/').'/api/v1';
$wurl = setting('webhook_url','');
$wsec = setting('webhook_secret','');
$wev  = array_filter(array_map('trim', explode(',', setting('webhook_events',''))));
$allEv = ['ticket.created'=>'Bon emis','ticket.called'=>'Bon apelat','ticket.serving'=>'In servire','ticket.served'=>'Finalizat','ticket.no_show'=>'Neprezentat','ticket.cancelled'=>'Anulat','ticket.transferred'=>'Transferat','ticket.recalled'=>'Rechemat','sla.breach'=>'Alerta SLA (cozi peste tinta)'];
$cron = setting('cron_token','');
$cronUrl = rtrim(base_url(),'/').'/cron?key='.$cron;
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

  <div class="card pad" style="flex:1;min-width:320px">
    <h3 style="margin-top:0">Sarcini programate (cron)</h3>
    <p class="muted" style="margin-top:0;font-size:.85rem">Pentru remindere de programari si raportul zilnic, configureaza in cPanel un <strong>Cron Job</strong> care deschide linkul de mai jos la fiecare 15 minute.</p>
    <div class="field"><label>URL cron</label><input readonly value="<?= e($cronUrl) ?>" onclick="this.select()" style="font-family:monospace;font-size:.78rem"></div>
    <p class="muted" style="font-size:.82rem">Comanda cPanel (interval */15):</p>
    <pre style="background:#0f1115;color:#cde3ff;padding:.8rem;border-radius:10px;overflow:auto;font-size:.78rem">*/15 * * * * curl -s "<?= e($cronUrl) ?>" >/dev/null 2>&1</pre>
    <p class="muted" style="font-size:.8rem;margin-bottom:0">Activeaza remindere/raport din <a href="<?= e(url('admin/settings')) ?>">Setari → Email</a>.</p>
    <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">
    <h3 style="margin-top:0">Backup baza de date</h3>
    <p class="muted" style="font-size:.85rem;margin-top:0">Descarca un fisier <code>.sql</code> cu toata baza de date (structura + date). Pastreaza-l intr-un loc sigur.</p>
    <form method="post" action="<?= e(url('admin/backup')) ?>"><?= csrf_field() ?>
      <button class="btn btn-primary">⬇ Descarca backup SQL</button>
    </form>
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
    <button class="btn btn-primary">Salveaza webhook</button>
  </form>
</div>

<div class="card pad" style="margin-top:1.2rem">
  <h3 style="margin-top:0">Endpoint-uri (v1)</h3>
  <table>
    <thead><tr><th>Metoda</th><th>Cale</th><th>Descriere</th></tr></thead>
    <tbody style="font-size:.9rem">
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/state?branch=1</code></td><td>Starea cozii (apelate, la rand, ghisee)</td></tr>
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/services?branch=1</code></td><td>Serviciile active</td></tr>
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/counters?branch=1</code></td><td>Ghiseele</td></tr>
      <tr><td><span class="pill" style="background:#dbeafe;color:#1e40af">POST</span></td><td><code>/api/v1/tickets</code></td><td>Emite bon: <code>{service_id, priority?, channel?}</code></td></tr>
      <tr><td><span class="pill" style="background:#dcfce7;color:#166534">GET</span></td><td><code>/api/v1/tickets/{token}</code></td><td>Starea unui bon (pozitie, timp estimat)</td></tr>
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
<?php require __DIR__.'/_footer.php'; ?>
