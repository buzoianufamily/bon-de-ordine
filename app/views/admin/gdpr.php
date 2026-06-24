<?php $title='GDPR · Date personale'; $active='gdpr'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>GDPR — drepturile persoanei vizate</h1></div>

<div class="card pad" style="max-width:760px">
  <p class="muted" style="margin-top:0">Caută toate datele personale legate de o adresă de email și/sau un număr de telefon (programări, listă de așteptare, bilete), apoi <strong>exportă-le</strong> (dreptul de acces) sau <strong>anonimizează-le</strong> (dreptul de a fi uitat). Acțiunile sunt înregistrate în jurnalul de audit.</p>
  <form method="get" action="<?= e(url('admin/gdpr')) ?>">
    <div class="row">
      <div class="field"><label>Email</label><input name="q_email" type="email" value="<?= e($email) ?>" placeholder="client@exemplu.ro"></div>
      <div class="field"><label>Telefon</label><input name="q_phone" value="<?= e($phone) ?>" placeholder="07xxxxxxxx"></div>
    </div>
    <button class="btn btn-primary">Caută</button>
  </form>
</div>

<?php if ($found !== null):
  $tot = count($found['appointments']) + count($found['waitlist']) + count($found['tickets']); ?>
  <div class="card pad" style="max-width:760px;margin-top:1rem">
    <h3 style="margin-top:0">Rezultate (<?= (int)$tot ?>)</h3>
    <?php if ($tot === 0): ?>
      <p class="muted">Nicio înregistrare găsită pentru aceste date.</p>
    <?php else: ?>
      <ul style="line-height:1.9">
        <li><strong><?= count($found['appointments']) ?></strong> programări</li>
        <li><strong><?= count($found['waitlist']) ?></strong> intrări în lista de așteptare</li>
        <li><strong><?= count($found['tickets']) ?></strong> bilete (după telefon)</li>
      </ul>

      <?php if ($found['appointments']): ?>
      <details style="margin:.6rem 0"><summary style="cursor:pointer;font-weight:700">Programări</summary>
        <div style="overflow-x:auto"><table style="min-width:560px;margin-top:.5rem">
          <thead><tr><th>ID</th><th>Nume</th><th>Telefon</th><th>Email</th><th>Interval</th><th>Stare</th></tr></thead>
          <tbody><?php foreach ($found['appointments'] as $r): ?>
            <tr><td><?= (int)$r['id'] ?></td><td><?= e((string)$r['customer_name']) ?></td><td><?= e((string)$r['customer_phone']) ?></td><td><?= e((string)$r['customer_email']) ?></td><td class="muted"><?= e((string)$r['slot_start']) ?></td><td><?= e((string)$r['status']) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      </details>
      <?php endif; ?>
      <?php if ($found['tickets']): ?>
      <details style="margin:.6rem 0"><summary style="cursor:pointer;font-weight:700">Bilete</summary>
        <div style="overflow-x:auto"><table style="min-width:420px;margin-top:.5rem">
          <thead><tr><th>ID</th><th>Bon</th><th>Telefon</th><th>Stare</th><th>Emis</th></tr></thead>
          <tbody><?php foreach ($found['tickets'] as $r): ?>
            <tr><td><?= (int)$r['id'] ?></td><td><?= e((string)$r['label']) ?></td><td><?= e((string)$r['customer_phone']) ?></td><td><?= e((string)$r['status']) ?></td><td class="muted"><?= e((string)$r['issued_at']) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      </details>
      <?php endif; ?>

      <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem">
        <form method="post" action="<?= e(url('admin/gdpr/export')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="q_email" value="<?= e($email) ?>"><input type="hidden" name="q_phone" value="<?= e($phone) ?>">
          <button class="btn">⬇ Exportă (JSON)</button>
        </form>
        <form method="post" action="<?= e(url('admin/gdpr/erase')) ?>" data-confirm="Anonimizezi DEFINITIV datele personale (nume/telefon/email) pentru aceste înregistrări? Statisticile rămân, dar datele de identificare se șterg ireversibil."><?= csrf_field() ?>
          <input type="hidden" name="q_email" value="<?= e($email) ?>"><input type="hidden" name="q_phone" value="<?= e($phone) ?>">
          <button class="btn btn-danger">🗑 Anonimizează (dreptul de a fi uitat)</button>
        </form>
      </div>
      <p class="muted" style="font-size:.78rem;margin-bottom:0;margin-top:.8rem">Notă: comentariile de feedback sunt text liber și nu sunt indexate după email/telefon — dacă un comentariu conține date personale, șterge-l din Feedback.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php require __DIR__.'/_footer.php'; ?>
