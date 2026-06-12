<?php /* $title asteptat */ $accent = setting('accent_color', '#2563eb'); ?>
<!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?= e($title ?? 'Bon de ordine') ?></title>
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="base" content="<?= e(base_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Manrope:wght@400;600;700;800&display=swap" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Manrope:wght@400;600;700;800&display=swap"></noscript>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>}</style>
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<meta name="theme-color" content="<?= e($accent) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('icon-192.png')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<script>if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('<?= e(url('sw.js')) ?>').catch(function(){});});}</script>
</head>
