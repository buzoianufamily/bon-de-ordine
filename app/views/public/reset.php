<?php $title='Parola noua'; require __DIR__.'/_head.php'; $logo = setting('brand_logo',''); ?>
<body class="authpage"><div class="center"><div class="auth">
  <div class="card pad" style="padding:2rem 1.8rem">
    <?php if($logo): ?><img class="auth-logo" src="<?= e($logo) ?>" alt=""><?php else: ?><div class="auth-dot">🔑</div><?php endif; ?>
    <h1 style="text-align:center;margin:0 0 .2rem;font-size:1.45rem">Setează o parolă nouă</h1>
    <?php if(empty($valid)): ?>
      <p class="muted" style="text-align:center;margin:1rem 0">Linkul de resetare este invalid sau a expirat.</p>
      <a class="btn btn-primary btn-lg" style="width:100%" href="<?= e(url('login/forgot')) ?>">Cere un link nou</a>
    <?php else: ?>
      <p class="muted" style="text-align:center;margin:0 0 1.2rem">Alege o parolă nouă pentru contul tău (minim 6 caractere).</p>
      <?php if(!empty($error)): ?>
        <div class="pill" style="display:block;text-align:center;background:#fee2e2;color:#b91c1c;margin-bottom:.8rem"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= e(url('login/reset')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field"><label>Parolă nouă</label><input type="password" name="password" required minlength="6" autofocus autocomplete="new-password"></div>
        <div class="field"><label>Confirmă parola</label><input type="password" name="password2" required minlength="6" autocomplete="new-password"></div>
        <button class="btn btn-primary btn-lg" style="width:100%">Salvează parola</button>
      </form>
    <?php endif; ?>
  </div>
  <p class="muted" style="text-align:center;margin-top:1rem;font-size:.85rem"><a href="<?= e(url('login')) ?>">← Autentificare</a></p>
</div></div></body></html>
