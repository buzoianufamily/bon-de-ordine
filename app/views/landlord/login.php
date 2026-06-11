<?php /* Login landlord — pagina autonoma, NU foloseste baza de date. */ ?>
<!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Landlord · Administrare instante</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=20">
<style>:root{--accent:#2563eb}</style>
</head>
<body class="authpage"><div class="center"><div class="auth">
  <div class="card pad" style="padding:2rem 1.8rem">
    <div class="auth-dot">🏢</div>
    <h1 style="text-align:center;margin:0 0 .2rem;font-size:1.45rem">Landlord</h1>
    <p class="muted" style="text-align:center;margin:0 0 1.2rem">Administrarea instantelor clientilor</p>
    <?php foreach (get_flashes() as $f): ?>
      <div class="pill" style="display:block;text-align:center;background:#fee2e2;color:#b91c1c;margin-bottom:.8rem"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('landlord')) ?>">
      <?= csrf_field() ?>
      <div class="field"><label>Parola landlord</label><input type="password" name="password" required autofocus></div>
      <button class="btn btn-primary btn-lg" style="width:100%">Intra</button>
    </form>
  </div>
  <p class="muted" style="text-align:center;margin-top:1rem;font-size:.8rem">Parola se seteaza in <code>config/config.php</code> → <code>landlord_pass</code></p>
</div></div></body></html>
