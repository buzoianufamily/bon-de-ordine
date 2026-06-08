<?php
/* $title, $active asteptate. Layout backoffice cu sidebar (aspect Moviik). */
$u = current_user();
$accent = setting('accent_color', '#10b981');
$brandName = setting('brand_name', 'Bon de ordine');
$brandLogo = setting('brand_logo', '');

/* iconuri SVG monocrome (stroke = currentColor) */
function aicon(string $k): string {
    $p = [
        'dashboard'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'statistics'  => '<line x1="5" y1="21" x2="5" y2="12"/><line x1="12" y1="21" x2="12" y2="4"/><line x1="19" y1="21" x2="19" y2="9"/>',
        'tickets'     => '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/>',
        'branches'    => '<path d="M3 21h18M5 21V8l7-4 7 4v13"/><path d="M10 21v-6h4v6"/>',
        'appointments'=> '<rect x="3" y="4" width="18" height="17" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/>',
        'users'       => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.5a3 3 0 0 1 0 5.8M21 20a6 6 0 0 0-4.5-5.5"/>',
        'services'    => '<path d="M3 12V5a2 2 0 0 1 2-2h7l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.4"/>',
        'media'       => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M10 8.5l5 3-5 3z"/>',
        'forms'       => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9z"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/>',
        'counters'    => '<rect x="4" y="4" width="16" height="16" rx="2"/><line x1="4" y1="10" x2="20" y2="10"/><line x1="12" y1="10" x2="12" y2="20"/>',
        'devices'     => '<rect x="3" y="4" width="18" height="12" rx="2"/><line x1="8" y1="20" x2="16" y2="20"/><line x1="12" y1="16" x2="12" y2="20"/>',
        'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'roles'       => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'terminal'    => '<circle cx="12" cy="12" r="9"/><path d="M10 8l5 4-5 4z"/>',
        'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'menu'        => '<line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>',
        'search'      => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/>',
        'grid'        => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'list'        => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3.6" cy="6" r="1"/><circle cx="3.6" cy="12" r="1"/><circle cx="3.6" cy="18" r="1"/>',
    ];
    $inner = $p[$k] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$inner.'</svg>';
}

/* bara cu cautare + comutator grila/lista (ca la Moviik) */
function list_toolbar(string $placeholder = 'Cauta...'): string {
    return '<div class="listhead">'
        . '<div class="search">'.aicon('search').'<input type="text" placeholder="'.e($placeholder).'" data-filter=".cardgrid"></div>'
        . '<div class="viewtoggle">'
        .   '<a class="on" data-view="grid" title="Grila">'.aicon('grid').'</a>'
        .   '<a data-view="list" title="Lista">'.aicon('list').'</a>'
        . '</div></div>';
}

/* grupuri de navigare (aspect Moviik) */
$navGroups = [
  ''        => [ ['', 'Dashboard', 'dashboard'], ['statistics','Statistici','statistics'], ['tickets','Bilete','tickets'], ['branches','Filiale','branches'], ['appointments','Programari','appointments'] ],
  'Continut' => [ ['users','Utilizatori','users'], ['services','Servicii','services'], ['media','Multimedia','media'], ['forms','Formulare','forms'] ],
  'Configurare' => [ ['counters','Ghisee','counters'], ['devices','Dispozitive','devices'] ],
];
?>
<!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? 'Administrare') ?> · <?= e($brandName) ?></title>
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="base" content="<?= e(base_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Manrope:wght@400;600;700;800&display=swap" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Manrope:wght@400;600;700;800&display=swap"></noscript>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>}</style>
</head><body class="admin">
<div class="shell">
  <nav class="side">
    <div class="side-top"><?= aicon('menu') ?></div>
    <?php foreach ($navGroups as $grp => $items): ?>
      <?php if ($grp !== ''): ?><div class="grp"><?= e($grp) ?></div><?php endif; ?>
      <?php foreach ($items as $n): $is = ($active ?? '') === $n[0];
        if ($n[0] !== '' && !can($n[0])) continue; ?>
        <a href="<?= e(url('admin/'.$n[0])) ?>" class="<?= $is?'active':'' ?>"><span class="ic"><?= aicon($n[2]) ?></span><?= e($n[1]) ?></a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if (($u['role'] ?? '') === 'admin'): ?>
      <div class="grp">Acces</div>
      <a href="<?= e(url('admin/settings')) ?>" class="<?= ($active??'')==='settings'?'active':'' ?>"><span class="ic"><?= aicon('settings') ?></span>Setari</a>
      <a href="<?= e(url('admin/roles')) ?>" class="<?= ($active??'')==='roles'?'active':'' ?>"><span class="ic"><?= aicon('roles') ?></span>Roluri</a>
    <?php elseif (can('settings')): ?>
      <div class="grp">Acces</div>
      <a href="<?= e(url('admin/settings')) ?>" class="<?= ($active??'')==='settings'?'active':'' ?>"><span class="ic"><?= aicon('settings') ?></span>Setari</a>
    <?php endif; ?>
    <div class="side-foot">
      <a href="<?= e(url('counter')) ?>"><span class="ic"><?= aicon('terminal') ?></span>Terminal operator</a>
      <a href="<?= e(url('logout')) ?>"><span class="ic"><?= aicon('logout') ?></span>Iesire</a>
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
