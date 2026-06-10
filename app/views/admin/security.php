<?php $title='Securitate'; $active='security'; require __DIR__.'/_header.php';
$brand = setting('brand_name','Bon de ordine');
$uri = $enabled ? '' : totp_uri($secret, $u['email'] ?? 'cont', $brand);
$qr = $uri ? 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=0&data='.rawurlencode($uri) : '';
?>
<div class="topbar"><h1>Securitate</h1></div>

<div class="card pad" style="max-width:620px">
  <h3 style="margin-top:0">Autentificare in doi pasi (2FA)</h3>
  <p class="muted" style="margin-top:0;font-size:.88rem">Adauga un pas suplimentar la autentificare: pe langa parola, un cod de 6 cifre generat de o aplicatie pe telefon (Google Authenticator, Authy, Microsoft Authenticator).</p>

  <?php if($enabled): ?>
    <p class="pill" style="display:inline-block;background:color-mix(in srgb,var(--ok) 22%,transparent);color:var(--ok)">✔ 2FA este activ pentru contul tau</p>
    <form method="post" action="<?= e(url('admin/security')) ?>" onsubmit="return confirm('Dezactivezi autentificarea in doi pasi?')" style="margin-top:.8rem">
      <?= csrf_field() ?><input type="hidden" name="act" value="disable">
      <button class="btn btn-danger">Dezactiveaza 2FA</button>
    </form>
  <?php else: ?>
    <ol style="font-size:.92rem;line-height:1.7">
      <li>Deschide aplicatia de autentificare si scaneaza codul QR de mai jos.</li>
      <li>Daca nu poti scana, introdu manual cheia: <code style="user-select:all"><?= e($secret) ?></code></li>
      <li>Introdu codul de 6 cifre afisat de aplicatie ca sa confirmi.</li>
    </ol>
    <div style="display:flex;gap:1.4rem;align-items:center;flex-wrap:wrap;margin:.4rem 0 1rem">
      <img src="<?= e($qr) ?>" alt="QR 2FA" width="200" height="200" style="background:#fff;border-radius:10px;padding:8px;border:1px solid var(--line)">
      <form method="post" action="<?= e(url('admin/security')) ?>" style="flex:1;min-width:220px">
        <?= csrf_field() ?><input type="hidden" name="act" value="enable">
        <div class="field"><label>Cod din aplicatie</label>
          <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autocomplete="one-time-code"
                 style="text-align:center;letter-spacing:.4em;font-size:1.4rem;font-weight:800" placeholder="••••••"></div>
        <button class="btn btn-primary">Activeaza 2FA</button>
      </form>
    </div>
    <p class="muted" style="font-size:.82rem;margin-bottom:0">Pastreaza cheia intr-un loc sigur. Daca pierzi accesul la aplicatie, un alt administrator iti poate reseta 2FA din pagina Utilizatori.</p>
  <?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
