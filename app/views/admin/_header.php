<?php
/* $title, $active asteptate. Layout backoffice cu sidebar. */
$u = current_user();
$accent = setting('accent_color', '#2563eb');
$nav = [
  ['', 'Dashboard', '◧'],
  ['statistics', 'Statistici', '📊'],
  ['branches', 'Filiale', '🏢'],
  ['services', 'Servicii', '◆'],
  ['counters', 'Ghisee', '▤'],
  ['devices', 'Dispozitive', '▭'],
  ['media', 'Multimedia', '▦'],
  ['forms', 'Formulare', '🗒'],
  ['users', 'Utilizatori', '◉'],
  ['tickets', 'Bilete', '🎫'],
  ['appointments', 'Programari', '📅'],
  ['settings', 'Setari', '⚙'],
];
?>
<!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? 'Administrare') ?> · <?= e(setting('brand_name','Bon de ordine')) ?></title>
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="base" content="<?= e(base_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Manrope:wght@400;600;700;800&display=swap" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Manrope:wght@400;600;700;800&display=swap"></noscript>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>}</style>
</head><body class="admin">
<div class="shell">
  <nav class="side">
    <div class="brand"><span class="dot"></span><?= e(setting('brand_name','Bon de ordine')) ?></div>
    <?php foreach($nav as $n): $is = ($active ?? '') === $n[0];
      if ($n[0] !== '' && !can($n[0])) continue; // ascunde sectiunile nepermise ?>
      <a href="<?= e(url('admin/'.$n[0])) ?>" class="<?= $is?'active':'' ?>"><span style="width:18px;text-align:center"><?= $n[2] ?></span><?= e($n[1]) ?></a>
    <?php endforeach; ?>
    <?php if (($u['role'] ?? '') === 'admin'): ?>
      <a href="<?= e(url('admin/roles')) ?>" class="<?= ($active??'')==='roles'?'active':'' ?>"><span style="width:18px;text-align:center">🔑</span>Roluri</a>
    <?php endif; ?>
    <div class="grp">Cont</div>
    <a href="<?= e(url('counter')) ?>"><span style="width:18px;text-align:center">▶</span>Terminal operator</a>
    <a href="<?= e(url('logout')) ?>"><span style="width:18px;text-align:center">⇥</span>Iesire (<?= e($u['name']) ?>)</a>
  </nav>
  <main class="main">
    <?php foreach (get_flashes() as $f): ?>
      <div class="toast <?= $f['type']==='error'?'error':'ok' ?>" style="position:static;margin-bottom:1rem;display:inline-block"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
