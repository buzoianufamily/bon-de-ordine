<?php $lang = $lang ?? 'ro'; $pageLang = $lang; $L = auth_i18n($lang); $lq = $lang !== 'ro' ? '?lang='.$lang : '';
$title=$L['ac_title']; require __DIR__.'/_head.php';
$home = ($u['role'] ?? '') === 'agent' ? 'counter' : 'admin'; ?>
<body class="authpage"><div class="center"><div class="auth">
  <div class="card pad" style="padding:2rem 1.8rem">
    <div class="auth-dot">👤</div>
    <h1 style="text-align:center;margin:0 0 .2rem;font-size:1.45rem"><?= e($L['ac_title']) ?></h1>
    <p class="muted" style="text-align:center;margin:0 0 1.2rem"><?= e($u['name'] ?? '') ?> · <?= e($u['email'] ?? '') ?></p>
    <?php foreach (get_flashes() as $f): ?>
      <div class="pill" style="display:block;text-align:center;background:<?= $f['type']==='error'?'#fee2e2':'#dcfce7' ?>;color:<?= $f['type']==='error'?'#b91c1c':'#166534' ?>;margin-bottom:.8rem"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('account').$lq) ?>">
      <?= csrf_field() ?>
      <h3 style="margin:0 0 .6rem;font-size:1rem"><?= e($L['ac_change']) ?></h3>
      <div class="field"><label><?= e($L['ac_cur']) ?></label><input type="password" name="cur_pass" required autocomplete="current-password"></div>
      <div class="field"><label><?= e($L['ac_newmin']) ?></label><input type="password" name="new_pass" required minlength="6" autocomplete="new-password"></div>
      <div class="field"><label><?= e($L['ac_confirm2']) ?></label><input type="password" name="new_pass2" required minlength="6" autocomplete="new-password"></div>
      <button class="btn btn-primary btn-lg" style="width:100%"><?= e($L['ac_change']) ?></button>
    </form>
  </div>
  <?= public_lang_bar($lang, url('account')) ?>
  <p class="muted" style="text-align:center;margin-top:1rem;font-size:.85rem"><a href="<?= e(url($home)) ?>"><?= e($L['ac_back']) ?></a> · <a href="<?= e(url('logout')) ?>"><?= e($L['ac_logout']) ?></a></p>
</div></div></body></html>
