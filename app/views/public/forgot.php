<?php $lang = $lang ?? 'ro'; $pageLang = $lang; $L = auth_i18n($lang); $lq = $lang !== 'ro' ? '?lang='.$lang : '';
$title=$L['fp_title']; require __DIR__.'/_head.php'; $logo = setting('brand_logo',''); ?>
<body class="authpage"><div class="center"><div class="auth">
  <div class="card pad" style="padding:2rem 1.8rem">
    <?php if($logo): ?><img class="auth-logo" src="<?= e($logo) ?>" alt=""><?php else: ?><div class="auth-dot">🔑</div><?php endif; ?>
    <h1 style="text-align:center;margin:0 0 .2rem;font-size:1.45rem"><?= e($L['fp_title']) ?></h1>
    <?php if(!empty($sent)): ?>
      <p class="muted" style="text-align:center;margin:0 0 1.2rem"><?= e($L['fp_sent']) ?></p>
      <a class="btn btn-primary btn-lg" style="width:100%" href="<?= e(url('login').$lq) ?>"><?= e($L['fp_back']) ?></a>
    <?php else: ?>
      <p class="muted" style="text-align:center;margin:0 0 1.2rem"><?= e($L['fp_intro']) ?></p>
      <?php foreach (get_flashes() as $f): ?>
        <div class="pill" style="display:block;text-align:center;background:#fee2e2;color:#b91c1c;margin-bottom:.8rem"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
      <form method="post" action="<?= e(url('login/forgot').$lq) ?>">
        <?= csrf_field() ?>
        <div class="field"><label><?= e($L['email']) ?></label><input type="email" name="email" required autofocus autocomplete="username"></div>
        <button class="btn btn-primary btn-lg" style="width:100%"><?= e($L['fp_send']) ?></button>
      </form>
    <?php endif; ?>
  </div>
  <?= public_lang_bar($lang, url('login/forgot')) ?>
  <p class="muted" style="text-align:center;margin-top:1rem;font-size:.85rem"><a href="<?= e(url('login').$lq) ?>"><?= e($L['back_login']) ?></a></p>
</div></div></body></html>
