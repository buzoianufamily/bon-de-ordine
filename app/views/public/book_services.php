<?php $lang = $lang ?? 'ro'; $pageLang = $lang; $L = book_i18n($lang);
$lq = $lang !== 'ro' ? '?lang='.$lang : '';
$title='Programari'; require __DIR__.'/_head.php'; ?>
<body><div class="center"><div class="portal">
  <?= public_lang_bar($lang, url('book')) ?>
  <div style="text-align:center"><h1><?= e($L['title']) ?></h1><p class="muted"><?= e($L['choose']) ?></p></div>
  <div class="portal-grid">
    <?php foreach($services as $s): ?>
      <a href="<?= e(url('book/'.$s['id']).$lq) ?>"><div class="tile">
        <div style="display:flex;align-items:center;gap:.6rem"><span class="tag" style="background:<?= e($s['color']) ?>"><?= e($s['prefix']) ?></span><h3 style="margin:0"><?= e($s['name']) ?></h3></div>
        <p class="muted" style="margin:.4rem 0 0"><?= e($s['branch_name']) ?></p>
      </div></a>
    <?php endforeach; ?>
    <?php if(!$services): ?><p class="muted" style="text-align:center;grid-column:1/-1"><?= e($L['none']) ?></p><?php endif; ?>
  </div>
  <?= public_legal_footer($lang) ?>
</div></div></body></html>
