<?php $lang = $lang ?? 'ro'; $pageLang = $lang; $L = auth_i18n($lang); $lq = $lang !== 'ro' ? '?lang='.$lang : '';
$title=$L['rp_title']; require __DIR__.'/_head.php'; $logo = setting('brand_logo',''); ?>
<body class="authpage"><div class="center"><div class="auth">
  <div class="card pad" style="padding:2rem 1.8rem">
    <?php if($logo): ?><img class="auth-logo" src="<?= e($logo) ?>" alt=""><?php else: ?><div class="auth-dot">🔑</div><?php endif; ?>
    <h1 style="text-align:center;margin:0 0 .2rem;font-size:1.45rem"><?= e($L['rp_h1']) ?></h1>
    <?php if(empty($valid)): ?>
      <p class="muted" style="text-align:center;margin:1rem 0"><?= e($L['rp_invalid']) ?></p>
      <a class="btn btn-primary btn-lg" style="width:100%" href="<?= e(url('login/forgot').$lq) ?>"><?= e($L['rp_newlink']) ?></a>
    <?php else: ?>
      <p class="muted" style="text-align:center;margin:0 0 1.2rem"><?= e($L['rp_intro']) ?></p>
      <?php if(!empty($error)): ?>
        <div class="pill" style="display:block;text-align:center;background:#fee2e2;color:#b91c1c;margin-bottom:.8rem"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= e(url('login/reset').$lq) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field"><label><?= e($L['rp_new']) ?></label><input type="password" name="password" required minlength="6" autofocus autocomplete="new-password"></div>
        <div class="field"><label><?= e($L['rp_confirm']) ?></label><input type="password" name="password2" required minlength="6" autocomplete="new-password"></div>
        <button class="btn btn-primary btn-lg" style="width:100%"><?= e($L['rp_save']) ?></button>
      </form>
    <?php endif; ?>
  </div>
  <?= public_lang_bar($lang, url('login/reset').($token!==''?'?token='.urlencode($token):'')) ?>
  <p class="muted" style="text-align:center;margin-top:1rem;font-size:.85rem"><a href="<?= e(url('login').$lq) ?>"><?= e($L['back_login']) ?></a></p>
</div></div></body></html>
