<?php $title='Verificare in doi pasi'; require __DIR__.'/_head.php'; ?>
<body class="authpage"><div class="center"><div class="auth">
  <div class="card pad" style="padding:2rem 1.8rem">
    <div class="auth-dot">🛡</div>
    <h1 style="text-align:center;margin:0 0 .2rem;font-size:1.45rem"><?= e(setting('brand_name','Bon de ordine')) ?></h1>
    <p class="muted" style="text-align:center;margin-top:0">Verificare in doi pasi</p>
    <?php foreach (get_flashes() as $f): ?>
      <div class="pill" style="display:block;text-align:center;background:#fee2e2;color:#b91c1c;margin-bottom:.8rem"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <p class="muted" style="text-align:center;font-size:.88rem">Introdu codul de 6 cifre din aplicatia ta de autentificare (Google Authenticator, Authy etc.).</p>
    <form method="post" action="<?= e(url('login/2fa')) ?>">
      <?= csrf_field() ?>
      <div class="field"><input type="text" name="code" autocomplete="one-time-code" maxlength="9" required autofocus
           style="text-align:center;letter-spacing:.4em;font-size:1.5rem;font-weight:800" placeholder="••••••"></div>
      <button class="btn btn-primary btn-lg" style="width:100%">Verifica</button>
    </form>
    <p class="muted" style="text-align:center;font-size:.78rem;margin-top:.8rem">Ai pierdut telefonul? Introdu unul dintre <strong>codurile de recuperare</strong> (format XXXX-XXXX).</p>
  </div>
  <p class="muted" style="text-align:center;margin-top:1rem;font-size:.85rem"><a href="<?= e(url('logout')) ?>">← Anuleaza</a></p>
</div></div></body></html>
