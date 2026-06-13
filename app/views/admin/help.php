<?php $title='Ajutor'; $active='help'; require __DIR__.'/_header.php';
$ver = defined('APP_SCHEMA_VERSION') ? APP_SCHEMA_VERSION : '—';
?>
<div class="topbar"><h1>Ajutor & referință</h1><span class="muted" style="font-size:.85rem">schema v<?= e($ver) ?></span></div>

<div class="panel-grid">
  <div class="panel">
    <h4>Pagini publice</h4>
    <table>
      <tr><td><strong>Portal</strong></td><td><a href="<?= e(url('/')) ?>" target="_blank"><?= e(url('/')) ?></a></td></tr>
      <tr><td><strong>Terminal operator</strong></td><td><a href="<?= e(url('counter')) ?>" target="_blank"><?= e(url('counter')) ?></a></td></tr>
      <?php if(setting('mod_concierge','1')==='1'): ?><tr><td><strong>Concierge</strong></td><td><a href="<?= e(url('concierge')) ?>" target="_blank"><?= e(url('concierge')) ?></a></td></tr><?php endif; ?>
      <?php if(setting('mod_booking','1')==='1'): ?><tr><td><strong>Programări</strong></td><td><a href="<?= e(url('book')) ?>" target="_blank"><?= e(url('book')) ?></a></td></tr><?php endif; ?>
      <?php if(setting('mod_feedback','1')==='1'): ?><tr><td><strong>Feedback</strong></td><td><a href="<?= e(url('feedback')) ?>" target="_blank"><?= e(url('feedback')) ?></a></td></tr><?php endif; ?>
      <?php if(setting('mod_public_status','0')==='1'): ?><tr><td><strong>Status public</strong></td><td><a href="<?= e(url('status')) ?>" target="_blank"><?= e(url('status')) ?></a></td></tr><?php endif; ?>
      <tr><td><strong>Dispensere / afișaje</strong></td><td>Admin → <a href="<?= e(url('admin/devices')) ?>">Dispozitive</a> (deschide / coduri QR)</td></tr>
      <tr><td><strong>Stare sistem</strong></td><td><a href="<?= e(url('health')) ?>" target="_blank"><?= e(url('health')) ?></a> (monitorizare uptime)</td></tr>
    </table>
  </div>

  <div class="panel">
    <h4>Scurtături</h4>
    <table>
      <tr><td><span class="kbd">Ctrl/⌘ + K</span></td><td>Căutare globală (servicii, ghișee, bilete…)</td></tr>
      <tr><td><span class="kbd">Space / Enter</span></td><td>Terminal: cheamă următorul</td></tr>
      <tr><td><span class="kbd">↑ / ↓</span></td><td>Terminal: navighezi prin coadă</td></tr>
      <tr><td><span class="kbd">R</span> / <span class="kbd">S</span> / <span class="kbd">F</span> / <span class="kbd">N</span></td><td>Recheamă / În servire / Finalizat / Neprezentat</td></tr>
      <tr><td><span class="kbd">Esc</span></td><td>Deselectează biletul</td></tr>
    </table>
    <p class="muted" style="font-size:.8rem;margin-bottom:0">🌙/☀️ din bara de sus comută tema; ☰ ascunde/arată meniul lateral.</p>
  </div>
</div>

<div class="panel">
  <h4>Sarcini frecvente</h4>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:.6rem">
    <a class="card pad" href="<?= e(url('admin/services')) ?>"><strong>Servicii</strong><br><span class="muted" style="font-size:.85rem">prefix, interval, program, pauză temporară, drag&drop</span></a>
    <a class="card pad" href="<?= e(url('admin/branches')) ?>"><strong>Filiale & zile închise</strong><br><span class="muted" style="font-size:.85rem">locații + sărbători/zile fără emitere</span></a>
    <a class="card pad" href="<?= e(url('admin/devices')) ?>"><strong>Dispozitive</strong><br><span class="muted" style="font-size:.85rem">dispensere, afișaje TV, coduri QR de instalare</span></a>
    <a class="card pad" href="<?= e(url('admin/settings')) ?>"><strong>Setări</strong><br><span class="muted" style="font-size:.85rem">brand, bilet, voce, email, anunț, module, backup config</span></a>
    <a class="card pad" href="<?= e(url('admin/statistics')) ?>"><strong>Statistici</strong><br><span class="muted" style="font-size:.85rem">KPI, heatmap, export Excel/CSV, raport printabil</span></a>
    <a class="card pad" href="<?= e(url('admin/api')) ?>"><strong>API & Webhooks</strong><br><span class="muted" style="font-size:.85rem">cheie API, cron, webhooks (inclusiv alerte SLA)</span></a>
    <a class="card pad" href="<?= e(url('admin/security')) ?>"><strong>Securitate</strong><br><span class="muted" style="font-size:.85rem">2FA, schimbare parolă, politici</span></a>
    <a class="card pad" href="<?= e(url('admin/audit')) ?>"><strong>Jurnal audit</strong><br><span class="muted" style="font-size:.85rem">cine ce a modificat; filtrare + export CSV</span></a>
  </div>
</div>

<div class="panel">
  <h4>Sfaturi</h4>
  <ul style="line-height:1.8;margin:0">
    <li><strong>Imprimantă termică:</strong> cel mai simplu = mini‑PC Android + imprimantă USB (vezi aplicația din <code>android/</code>). Pentru rețea, IP imprimantă + port 9100.</li>
    <li><strong>Voce românească pe TV:</strong> folosește Chrome; dă un click pe afișaj o dată după deschidere (browserele cer interacțiune înainte de sunet).</li>
    <li><strong>Anunț rapid pentru clienți:</strong> Setări → Digital & alerte → „Anunț general" apare live pe toate afișajele.</li>
    <li><strong>Coadă peste țintă:</strong> Dashboard arată depășirile SLA; setează alerte pe email/webhook în Setări → Automatizări.</li>
    <li><strong>Backup:</strong> Setări → „Exportă configurația" (branding/texte) și API & Webhooks → „Backup bază de date" (date).</li>
  </ul>
</div>
<?php require __DIR__.'/_footer.php'; ?>
