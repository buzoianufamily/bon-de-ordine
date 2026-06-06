<?php $title='Autentificare'; require __DIR__.'/_head.php'; ?>
<body><div class="center"><div class="auth">
  <div class="card pad">
    <h1 style="text-align:center;margin-bottom:.2rem"><?= e(setting('brand_name','Bon de ordine')) ?></h1>
    <p class="muted" style="text-align:center;margin-top:0">Autentificare</p>
    <?php foreach (get_flashes() as $f): ?>
      <div class="pill" style="display:block;text-align:center;background:#fee2e2;color:#b91c1c;margin-bottom:.8rem"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('login')) ?>">
      <?= csrf_field() ?>
      <div class="field"><label>Email</label><input type="email" name="email" required autofocus></div>
      <div class="field"><label>Parola</label><input type="password" name="password" required></div>
      <button class="btn btn-primary btn-lg" style="width:100%">Intra in cont</button>
    </form>
  </div>
  <p class="muted" style="text-align:center;margin-top:1rem;font-size:.85rem"><a href="<?= e(url('/')) ?>">← Portal</a></p>
</div></div></body></html>
