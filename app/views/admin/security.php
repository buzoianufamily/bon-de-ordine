<?php $title='Securitate'; $active='security'; require __DIR__.'/_header.php';
require_once __DIR__.'/../../core/qr.php';
$brand = setting('brand_name','Bon de ordine');
$uri = $enabled ? '' : totp_uri($secret, $u['email'] ?? 'cont', $brand);
// codul QR e generat LOCAL (SVG) — secretul 2FA nu mai pleaca catre un serviciu extern
$qrSvg = $uri ? QR::svg($uri, 200) : '';
?>
<div class="topbar"><h1>Securitate</h1></div>

<?php if(!empty($newCodes)): ?>
<div class="card pad" style="margin-bottom:1.2rem;border:1px solid color-mix(in srgb,var(--warn) 55%,var(--line))">
  <h3 style="margin-top:0">⚠ Coduri de recuperare — salveaza-le ACUM</h3>
  <p class="muted" style="font-size:.85rem;margin-top:0">Fiecare cod functioneaza <strong>o singura data</strong> in locul codului din aplicatie (daca pierzi telefonul). Se afiseaza <strong>doar acum</strong> — noteaza-le sau printeaza-le.</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.5rem;font-family:monospace;font-size:1.05rem;font-weight:700">
    <?php foreach($newCodes as $c): ?><code style="padding:.45rem .6rem;text-align:center;user-select:all"><?= e($c) ?></code><?php endforeach; ?>
  </div>
  <button class="btn" style="margin-top:.9rem" onclick="window.print()">🖨 Printeaza</button>
</div>
<?php endif; ?>

<div class="card pad">
  <h3 style="margin-top:0">Autentificare in doi pasi (2FA)</h3>
  <p class="muted" style="margin-top:0;font-size:.88rem">Adauga un pas suplimentar la autentificare: pe langa parola, un cod de 6 cifre generat de o aplicatie pe telefon (Google Authenticator, Authy, Microsoft Authenticator).</p>

  <?php if($enabled): ?>
    <p class="pill" style="display:inline-block;background:color-mix(in srgb,var(--ok) 22%,transparent);color:var(--ok)">✔ 2FA este activ pentru contul tau</p>
    <p class="muted" style="font-size:.85rem">Coduri de recuperare ramase: <strong><?= (int)$backupLeft ?></strong><?= $backupLeft<=2?' — recomandam sa generezi altele noi':'' ?>.</p>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.4rem">
      <form method="post" action="<?= e(url('admin/security')) ?>" data-confirm="Generezi coduri de recuperare noi? Cele vechi nu vor mai functiona.">
        <?= csrf_field() ?><input type="hidden" name="act" value="regen_codes">
        <button class="btn">↻ Coduri de recuperare noi</button>
      </form>
      <form method="post" action="<?= e(url('admin/security')) ?>" data-confirm="Dezactivezi autentificarea in doi pasi?">
        <?= csrf_field() ?><input type="hidden" name="act" value="disable">
        <button class="btn btn-danger">Dezactiveaza 2FA</button>
      </form>
    </div>
  <?php else: ?>
    <ol style="font-size:.92rem;line-height:1.7">
      <li>Deschide aplicatia de autentificare si scaneaza codul QR de mai jos.</li>
      <li>Daca nu poti scana, introdu manual cheia: <code style="user-select:all"><?= e($secret) ?></code></li>
      <li>Introdu codul de 6 cifre afisat de aplicatie ca sa confirmi.</li>
    </ol>
    <div style="display:flex;gap:1.4rem;align-items:center;flex-wrap:wrap;margin:.4rem 0 1rem">
      <div role="img" aria-label="Cod QR pentru configurarea 2FA" style="background:#fff;border-radius:10px;padding:8px;border:1px solid var(--line);width:200px;height:200px"><?= $qrSvg ?></div>
      <form method="post" action="<?= e(url('admin/security')) ?>" style="flex:1;min-width:220px">
        <?= csrf_field() ?><input type="hidden" name="act" value="enable">
        <div class="field"><label>Cod din aplicatie</label>
          <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autocomplete="one-time-code"
                 style="text-align:center;letter-spacing:.4em;font-size:1.4rem;font-weight:800" placeholder="••••••"></div>
        <button class="btn btn-primary">Activeaza 2FA</button>
      </form>
    </div>
    <p class="muted" style="font-size:.82rem;margin-bottom:0">La activare primesti si <strong>coduri de recuperare</strong> (de unica folosinta) pentru cazul in care pierzi telefonul. Un alt administrator iti poate reseta 2FA din pagina Utilizatori.</p>
  <?php endif; ?>
</div>

<form method="post" action="<?= e(url('admin/security')) ?>" class="card pad" style="margin-top:1.2rem">
  <?= csrf_field() ?><input type="hidden" name="act" value="password">
  <h3 style="margin-top:0">Schimba parola</h3>
  <p class="muted" style="margin-top:0;font-size:.88rem">Schimba parola contului tau (<?= e($u['email'] ?? '') ?>). Minim 6 caractere.</p>
  <div class="field"><label>Parola curenta</label><input type="password" name="cur_pass" required autocomplete="current-password"></div>
  <div class="row">
    <div class="field"><label>Parola noua</label><input type="password" name="new_pass" required minlength="6" autocomplete="new-password"></div>
    <div class="field"><label>Confirma parola noua</label><input type="password" name="new_pass2" required minlength="6" autocomplete="new-password"></div>
  </div>
  <button class="btn btn-primary" style="margin-top:.4rem">Schimba parola</button>
</form>

<?php if(($u['role'] ?? '') === 'admin'): ?>
<form method="post" action="<?= e(url('admin/security')) ?>" class="card pad" style="margin-top:1.2rem">
  <?= csrf_field() ?><input type="hidden" name="act" value="policy">
  <h3 style="margin-top:0">Politica de securitate (toata instanta)</h3>
  <label style="display:block;margin:.4rem 0"><input type="checkbox" name="force_2fa_admin" <?= !empty($force2fa)?'checked':'' ?> style="width:auto"> Obliga toti <strong>administratorii</strong> sa foloseasca 2FA (fara 2FA activ, nu pot accesa backoffice-ul, doar pagina aceasta)</label>
  <button class="btn btn-primary" style="margin-top:.4rem">Salveaza politica</button>
</form>
<?php endif; ?>
<?php require __DIR__.'/_footer.php'; ?>
