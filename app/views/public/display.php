<?php
$title=setting('brand_name','Bon').' · Afisaj'; $kiosk=true; require __DIR__.'/_head.php';
$logo  = setting('brand_logo');
$layout = $dev['config'] ? json_decode($dev['config'], true) : null;
$hasLayout = $layout && !empty($layout['screens']);
$sound = $hasLayout && !empty($layout['sound']) ? $layout['sound'] : [
  'voice'=>setting('display_voice','ro-RO'),
  'say_number'=>setting('display_say_number','1')==='1',
  'say_counter'=>setting('display_say_counter','1')==='1',
  'repeat'=>(int)setting('display_repeat','2'),
];
?>
<body style="margin:0">
<?php if($hasLayout): ?>
  <!-- Afisaj din layout personalizat (editor canvas) -->
  <div id="stage" style="position:fixed;inset:0;overflow:hidden;background:#0b0d12"></div>
<?php else: ?>
  <!-- Afisaj implicit -->
  <div class="tv">
    <div class="tv-main">
      <div class="tv-brand"><?php if($logo): ?><img src="<?= e($logo) ?>" style="max-height:5vh"><?php endif; ?><?= e(setting('brand_name','')) ?></div>
      <div class="tv-now"><div class="lab">Se serveste bonul</div><div class="num" id="nowNum">—</div><div class="ctr" id="nowCtr"></div></div>
      <div class="tv-list"><h3>Ultimele apelate</h3><div id="callList"></div></div>
    </div>
    <div class="tv-side">
      <div class="tv-list" style="flex:1"><h3>La rand</h3><div class="tv-wait" id="waitList"></div></div>
      <div class="tv-clock" id="clock"></div>
    </div>
  </div>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
window.DISPLAY = {
  branch: <?= (int)$branch['id'] ?>,
  voice: <?= jsenc($sound['voice']) ?>,
  sayNumber: <?= !empty($sound['say_number']) ? 'true':'false' ?>,
  sayCounter: <?= !empty($sound['say_counter']) ? 'true':'false' ?>,
  repeat: <?= (int)($sound['repeat'] ?? 2) ?>,
  useSSE: true,
  accent: <?= jsenc(setting('accent_color','#00c375')) ?>,
  layout: <?= $hasLayout ? jsenc($layout, JSON_UNESCAPED_UNICODE) : 'null' ?>
};
</script>
<script src="<?= e(asset('js/display.js')) ?>"></script>
</body></html>
