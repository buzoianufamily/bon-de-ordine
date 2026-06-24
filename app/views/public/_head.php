<?php /* $title asteptat. $kiosk=true => blocheaza zoom (touch kiosk). $pageLang => limba paginii. */
$accent = setting('accent_color', '#2563eb');
$__vp = !empty($kiosk)
    ? 'width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no'   // dispenser pe ecran tactil
    : 'width=device-width,initial-scale=1,viewport-fit=cover';                // restul: permite zoom (accesibilitate)
?>
<!doctype html><html lang="<?= e($pageLang ?? 'ro') ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="<?= $__vp ?>">
<title><?= e($title ?? 'Bon de ordine') ?></title>
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="base" content="<?= e(base_url()) ?>">
<link rel="stylesheet" href="<?= e(asset('fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>}</style>
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<meta name="theme-color" content="<?= e($accent) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('icon-192.png')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<script>if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('<?= e(url('sw.js')) ?>').catch(function(){});});}</script>
</head>
