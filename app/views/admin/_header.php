<?php
/* $title, $active asteptate. Layout backoffice cu sidebar (aspect Moviik). */
$u = current_user();
$accent = setting('accent_color', '#2563eb');
$brandName = setting('brand_name', 'Bon de ordine');
$brandLogo = setting('brand_logo', '');

/* grupuri de navigare cu iconite simple (emoji) langa nume — ca la inceput */
$navGroups = [
  ''           => [ ['', 'Dashboard', '◧'], ['statistics','Statistici','📊'], ['tickets','Bilete','🎫'], ['branches','Filiale','🏢'], ['appointments','Programari','📅'] ],
  'Continut'   => [ ['users','Utilizatori','◉'], ['services','Servicii','◆'], ['media','Multimedia','▦'], ['forms','Formulare','🗒'] ],
  'Configurare'=> [ ['counters','Ghisee','▤'], ['devices','Dispozitive','▭'] ],
];

/* bara cu cautare + comutator grila/lista (ca la Moviik), cu iconite text */
function list_toolbar(string $placeholder = 'Cauta...'): string {
    return '<div class="listhead">'
        . '<div class="search"><span class="si">🔍</span><input type="text" placeholder="'.e($placeholder).'" data-filter=".cardgrid"></div>'
        . '<div class="viewtoggle">'
        .   '<a class="on" data-view="grid" title="Grila">▦</a>'
        .   '<a data-view="list" title="Lista">☰</a>'
        . '</div></div>';
}
?>
<!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? 'Administrare') ?> · <?= e($brandName) ?></title>
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="base" content="<?= e(base_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Manrope:wght@400;600;700;800&display=swap" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Manrope:wght@400;600;700;800&display=swap"></noscript>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=4">
<style>:root{--accent:<?= e($accent) ?>}</style>
</head><body class="admin">
<div class="shell">
  <nav class="side">
    <button class="side-top" id="side-toggle" title="Ascunde/Arata bara laterala">☰</button>
    <?php foreach ($navGroups as $grp => $items): ?>
      <?php if ($grp !== ''): ?><div class="grp"><?= e($grp) ?></div><?php endif; ?>
      <?php foreach ($items as $n): $is = ($active ?? '') === $n[0];
        if ($n[0] !== '' && !can($n[0])) continue; ?>
        <a href="<?= e(url('admin/'.$n[0])) ?>" class="<?= $is?'active':'' ?>"><span class="ic"><?= $n[2] ?></span><?= e($n[1]) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if (($u['role'] ?? '') === 'admin'): ?>
      <div class="grp">Acces</div>
      <a href="<?= e(url('admin/settings')) ?>" class="<?= ($active??'')==='settings'?'active':'' ?>"><span class="ic">⚙</span>Setari</a>
      <a href="<?= e(url('admin/roles')) ?>" class="<?= ($active??'')==='roles'?'active':'' ?>"><span class="ic">🔑</span>Roluri</a>
    <?php elseif (can('settings')): ?>
      <div class="grp">Acces</div>
      <a href="<?= e(url('admin/settings')) ?>" class="<?= ($active??'')==='settings'?'active':'' ?>"><span class="ic">⚙</span>Setari</a>
    <?php endif; ?>
    <div class="side-foot">
      <a href="<?= e(url('counter')) ?>"><span class="ic">▶</span>Terminal operator</a>
      <a href="<?= e(url('logout')) ?>"><span class="ic">⇥</span>Iesire</a>
    </div>
  </nav>
  <main class="main">
    <div class="adminbar">
      <div class="brand">
        <?php if ($brandLogo): ?><img src="<?= e($brandLogo) ?>" alt="<?= e($brandName) ?>"><?php else: ?><span class="dot"></span><?= e($brandName) ?><?php endif; ?>
      </div>
      <div class="right">
        <span class="uchip"><span class="av"><?= e(mb_strtoupper(mb_substr($u['name'] ?? '?',0,1))) ?></span><?= e($u['name'] ?? '') ?></span>
      </div>
    </div>
    <div class="content">
    <?php foreach (get_flashes() as $f): ?>
      <div class="toast <?= $f['type']==='error'?'error':'ok' ?>" style="position:static;margin-bottom:1rem;display:inline-block"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
