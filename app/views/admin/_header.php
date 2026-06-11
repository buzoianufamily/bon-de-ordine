<?php
/* $title, $active asteptate. Layout backoffice cu sidebar (aspect Moviik). */
$u = current_user();
$accent = setting('accent_color', '#2563eb');
$brandName = setting('brand_name', 'Bon de ordine');
$brandLogo = setting('brand_logo', '');

/* grupuri de navigare cu iconite simple (emoji) langa nume — ca la inceput */
$navGroups = [
  ''           => [ ['', 'Dashboard', '◧'], ['statistics','Statistici','📊'], ['tickets','Bilete','🎫'], ['branches','Filiale','🏢'], ['appointments','Programari','📅'], ['feedback','Feedback','⭐'] ],
  'Continut'   => [ ['users','Utilizatori','◉'], ['services','Servicii','◆'], ['groups','Grupuri','🗂'], ['media','Multimedia','▦'], ['forms','Formulare','🗒'] ],
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
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=11">
<style>:root{--accent:<?= e($accent) ?>}</style>
</head><body class="admin">
<script>(function(){try{if(localStorage.getItem('admin_theme')==='light')document.body.classList.add('light');}catch(e){}})();</script>
<div class="shell">
  <nav class="side">
    <button class="side-top" id="side-toggle" title="Ascunde/Arata bara laterala"><span class="ic">☰</span></button>
    <?php foreach ($navGroups as $grp => $items): ?>
      <?php if ($grp !== ''): ?><div class="grp"><?= e($grp) ?></div><?php endif; ?>
      <?php foreach ($items as $n): $is = ($active ?? '') === $n[0];
        if ($n[0] !== '' && !can($n[0])) continue; ?>
        <a href="<?= e(url('admin/'.$n[0])) ?>" class="<?= $is?'active':'' ?>"><span class="ic"><?= $n[2] ?></span><span class="lbl"><?= e($n[1]) ?></span></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if (($u['role'] ?? '') === 'admin'): ?>
      <div class="grp">Acces</div>
      <a href="<?= e(url('admin/settings')) ?>" class="<?= ($active??'')==='settings'?'active':'' ?>"><span class="ic">⚙</span><span class="lbl">Setari</span></a>
      <a href="<?= e(url('admin/roles')) ?>" class="<?= ($active??'')==='roles'?'active':'' ?>"><span class="ic">🔑</span><span class="lbl">Roluri</span></a>
      <a href="<?= e(url('admin/api')) ?>" class="<?= ($active??'')==='api'?'active':'' ?>"><span class="ic">🔌</span><span class="lbl">API & Webhooks</span></a>
      <a href="<?= e(url('admin/audit')) ?>" class="<?= ($active??'')==='audit'?'active':'' ?>"><span class="ic">📜</span><span class="lbl">Jurnal audit</span></a>
    <?php elseif (can('settings')): ?>
      <div class="grp">Acces</div>
      <a href="<?= e(url('admin/settings')) ?>" class="<?= ($active??'')==='settings'?'active':'' ?>"><span class="ic">⚙</span><span class="lbl">Setari</span></a>
    <?php endif; ?>
    <div class="side-foot">
      <a href="<?= e(url('admin/security')) ?>" class="<?= ($active??'')==='security'?'active':'' ?>"><span class="ic">🛡</span><span class="lbl">Securitate (2FA)</span></a>
      <a href="<?= e(url('counter')) ?>"><span class="ic">▶</span><span class="lbl">Terminal operator</span></a>
      <?php if (setting('mod_concierge','1')==='1'): ?><a href="<?= e(url('concierge')) ?>"><span class="ic">🛎</span><span class="lbl">Concierge</span></a><?php endif; ?>
      <a href="<?= e(url('logout')) ?>"><span class="ic">⇥</span><span class="lbl">Iesire</span></a>
    </div>
  </nav>
  <main class="main">
    <div class="adminbar">
      <div class="brand">
        <?php if ($brandLogo): ?><img src="<?= e($brandLogo) ?>" alt="<?= e($brandName) ?>"><?php else: ?><span class="dot"></span><?= e($brandName) ?><?php endif; ?>
      </div>
      <div class="right">
        <button class="themebtn" id="theme-toggle" title="Comuta tema deschisa/inchisa" aria-label="Comuta tema">🌙</button>
        <span class="uchip"><span class="av"><?= e(mb_strtoupper(mb_substr($u['name'] ?? '?',0,1))) ?></span><?= e($u['name'] ?? '') ?></span>
      </div>
    </div>
    <div class="content">
    <?php foreach (get_flashes() as $f): ?>
      <div class="toast <?= $f['type']==='error'?'error':'ok' ?>" style="position:static;margin-bottom:1rem;display:inline-block"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
