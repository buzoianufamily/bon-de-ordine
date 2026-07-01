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
<?php if (!empty($publicTheme)): /* comutator tema dark/light pe paginile publice care il cer */ ?>
<script>try{if(localStorage.getItem('bdo_ptheme')==='dark')document.documentElement.classList.add('pdark');}catch(e){}</script>
<script>document.addEventListener('DOMContentLoaded',function(){
  var b=document.createElement('button');b.className='ptheme-btn';b.type='button';
  b.setAttribute('aria-label','Comuta tema deschisa sau inchisa');b.title='Comuta tema deschisa/inchisa';
  function ic(){b.textContent=document.documentElement.classList.contains('pdark')?'☀️':'🌙';}
  ic();b.addEventListener('click',function(){var d=document.documentElement.classList.toggle('pdark');
    try{localStorage.setItem('bdo_ptheme',d?'dark':'light');}catch(e){}ic();});
  document.body.appendChild(b);
});</script>
<?php endif; ?>
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<meta name="theme-color" content="<?= e($accent) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('icon-192.png')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<script>if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('<?= e(url('sw.js')) ?>').catch(function(){});});}</script>
</head>
