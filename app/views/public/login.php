<?php $lang = $lang ?? 'ro'; $pageLang = $lang; $L = auth_i18n($lang); $lq = $lang !== 'ro' ? '?lang='.$lang : '';
$title=$L['title']; require __DIR__.'/_head.php'; $logo = setting('brand_logo',''); ?>
<body class="authpage"><div class="center"><div class="auth">
  <div class="card pad" style="padding:2rem 1.8rem">
    <?php if($logo): ?><img class="auth-logo" src="<?= e($logo) ?>" alt=""><?php else: ?><div class="auth-dot">🎫</div><?php endif; ?>
    <h1 style="text-align:center;margin:0 0 .2rem;font-size:1.45rem"><?= e(setting('brand_name','Bon de ordine')) ?></h1>
    <p class="muted" style="text-align:center;margin:0 0 1.2rem"><?= e($L['subtitle']) ?></p>
    <?php foreach (get_flashes() as $f): ?>
      <div class="pill" style="display:block;text-align:center;background:#fee2e2;color:#b91c1c;margin-bottom:.8rem"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('login').$lq) ?>">
      <?= csrf_field() ?>
      <div class="field"><label><?= e($L['email']) ?></label><input type="email" name="email" required autofocus autocomplete="username"></div>
      <div class="field"><label><?= e($L['password']) ?></label><input type="password" name="password" required autocomplete="current-password"></div>
      <button class="btn btn-primary btn-lg" style="width:100%"><?= e($L['signin']) ?></button>
    </form>
    <p style="text-align:center;margin:.9rem 0 0;font-size:.85rem"><a href="<?= e(url('login/forgot').$lq) ?>" class="muted"><?= e($L['forgot']) ?></a></p>
  </div>
  <?= public_lang_bar($lang, url('login')) ?>
  <p class="muted" style="text-align:center;margin-top:1rem;font-size:.85rem"><a href="<?= e(url('/')) ?>"><?= e($L['portal']) ?></a></p>
</div></div></body></html>
