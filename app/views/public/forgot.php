<?php $title='Resetare parola'; require __DIR__.'/_head.php'; $logo = setting('brand_logo',''); ?>
<body class="authpage"><div class="center"><div class="auth">
  <div class="card pad" style="padding:2rem 1.8rem">
    <?php if($logo): ?><img class="auth-logo" src="<?= e($logo) ?>" alt=""><?php else: ?><div class="auth-dot">🔑</div><?php endif; ?>
    <h1 style="text-align:center;margin:0 0 .2rem;font-size:1.45rem">Resetare parola</h1>
    <?php if(!empty($sent)): ?>
      <p class="muted" style="text-align:center;margin:0 0 1.2rem">Daca adresa exista in sistem, ti-am trimis un email cu un link de resetare. Verifica si folderul Spam. Linkul expira in 60 de minute.</p>
      <a class="btn btn-primary btn-lg" style="width:100%" href="<?= e(url('login')) ?>">Inapoi la autentificare</a>
    <?php else: ?>
      <p class="muted" style="text-align:center;margin:0 0 1.2rem">Introdu emailul contului. Iti trimitem un link pentru a-ti seta o parola noua.</p>
      <?php foreach (get_flashes() as $f): ?>
        <div class="pill" style="display:block;text-align:center;background:#fee2e2;color:#b91c1c;margin-bottom:.8rem"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
      <form method="post" action="<?= e(url('login/forgot')) ?>">
        <?= csrf_field() ?>
        <div class="field"><label>Email</label><input type="email" name="email" required autofocus autocomplete="username"></div>
        <button class="btn btn-primary btn-lg" style="width:100%">Trimite linkul de resetare</button>
      </form>
    <?php endif; ?>
  </div>
  <p class="muted" style="text-align:center;margin-top:1rem;font-size:.85rem"><a href="<?= e(url('login')) ?>">← Autentificare</a></p>
</div></div></body></html>
