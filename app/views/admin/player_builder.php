<?php
/* Editor canvas pentru afisaj (player). Layout salvat in devices.config (JSON). */
$cfg = $dev['config'] ? json_decode($dev['config'], true) : null;
$accent = setting('accent_color', '#2563eb');
?>
<!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Editor afisaj · <?= e($dev['name']) ?></title>
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="base" content="<?= e(base_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Manrope:wght@400;600;700;800&display=swap" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Manrope:wght@400;600;700;800&display=swap"></noscript>
<style>
:root{--accent:<?= e($accent) ?>}
*{box-sizing:border-box}
body{margin:0;font-family:"Manrope",system-ui,sans-serif;background:#0b0d12;color:#e6e8ec;height:100vh;overflow:hidden}
.pb-top{height:56px;display:flex;align-items:center;gap:1rem;padding:0 1rem;background:#11141b;border-bottom:1px solid #1c2029}
.pb-top .ttl{font-family:"Bricolage Grotesque",sans-serif;font-weight:800;font-size:1.05rem}
.pb-top .sp{flex:1}
.pb-btn{display:inline-flex;align-items:center;gap:.4rem;border:1px solid #2a2f3a;background:#1a1d23;color:#e6e8ec;padding:.5rem .9rem;border-radius:9px;font-weight:700;cursor:pointer;font-size:.9rem;text-decoration:none}
.pb-btn:hover{border-color:#3a4151}
.pb-btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}
.pb-wrap{display:grid;grid-template-columns:240px 1fr 300px;height:calc(100vh - 56px)}
.pb-pane{overflow:auto;padding:.9rem}
.pb-left{background:#0e1117;border-right:1px solid #1c2029}
.pb-right{background:#0e1117;border-left:1px solid #1c2029}
.pb-grp{font-size:.68rem;text-transform:uppercase;letter-spacing:.08em;color:#6b7280;margin:.6rem .2rem .4rem}
.pb-pal{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}
.pb-pal button{display:flex;flex-direction:column;align-items:center;gap:.3rem;padding:.7rem .3rem;background:#1a1d23;border:1px solid #2a2f3a;border-radius:10px;color:#cdd2da;cursor:pointer;font-size:.72rem;font-weight:600}
.pb-pal button:hover{border-color:var(--accent);color:#fff}
.pb-pal button .ic{font-size:1.3rem}
.pb-screens{display:flex;flex-direction:column;gap:.35rem}
.pb-screen{display:flex;align-items:center;gap:.4rem;padding:.5rem .6rem;background:#1a1d23;border:1px solid #2a2f3a;border-radius:9px;cursor:pointer;font-weight:600;font-size:.85rem}
.pb-screen.active{border-color:var(--accent);background:#172033}
.pb-screen .x{margin-left:auto;opacity:.5;cursor:pointer}
.pb-screen .x:hover{opacity:1;color:#ff6b6b}
.pb-stagewrap{display:flex;align-items:center;justify-content:center;padding:2rem;overflow:auto;background:#0b0d12 radial-gradient(circle at 50% 0,#141925,#0b0d12)}
.pb-stage{position:relative;width:960px;height:540px;background:#0b0d12;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,.5);overflow:hidden;outline:1px solid #1c2029}
.pb-w{position:absolute;border:1.5px solid transparent;border-radius:10px;overflow:hidden;cursor:move;user-select:none;touch-action:none}
.pb-w.sel{border-color:var(--accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 30%,transparent)}
.pb-w .rsz{position:absolute;right:-1px;bottom:-1px;width:16px;height:16px;background:var(--accent);border-radius:3px 0 8px 0;cursor:nwse-resize}
.field{margin-bottom:.8rem}
.field label{display:block;font-size:.78rem;font-weight:700;color:#aab1bd;margin-bottom:.3rem}
.field input,.field select,.field textarea{width:100%;padding:.5rem;border:1px solid #2a2f3a;background:#11141b;color:#e6e8ec;border-radius:8px;font-size:.85rem;font-family:inherit}
.field.row2{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}
.chk{display:flex;align-items:center;gap:.5rem;font-size:.85rem;margin:.4rem 0;cursor:pointer}
.chk input{width:auto}
.muted{color:#6b7280;font-size:.8rem}
hr{border:none;border-top:1px solid #1c2029;margin:1rem 0}
/* preview content (in editor) */
.wv{width:100%;height:100%;padding:6px;display:flex;flex-direction:column;color:#fff;font-size:12px;overflow:hidden}
.wv-now{align-items:center;justify-content:center;text-align:center;background:linear-gradient(135deg,var(--accent),#0008);border-radius:8px}
.wv-now .l{opacity:.8;font-size:.8em;text-transform:uppercase}
.wv-now .n{font-family:"Bricolage Grotesque";font-weight:800;font-size:3.4em;line-height:1}
.wv-list{background:#11141b;border-radius:8px}
.wv h4{margin:.1em 0 .4em;color:#7d8696;font-size:.85em;text-transform:uppercase}
.wv .li{display:flex;justify-content:space-between;padding:.18em .1em;border-bottom:1px solid #1c2029}
.wv .li b{font-family:"Bricolage Grotesque"}
.wv-clock{align-items:center;justify-content:center;font-family:"Bricolage Grotesque";font-weight:700}
.wv-text{align-items:center;justify-content:center;text-align:center;font-weight:700}
.wv-img{padding:0}.wv-img img{width:100%;height:100%;object-fit:cover}
</style></head><body>
<div class="pb-top">
  <a class="pb-btn" href="<?= e(url('admin/devices')) ?>">← Dispozitive</a>
  <span class="ttl">Editor afisaj · <?= e($dev['name']) ?></span>
  <span class="muted">cheie <?= e($dev['connection_key']) ?></span>
  <span class="sp"></span>
  <a class="pb-btn" target="_blank" href="<?= e(url('launcher?key='.$dev['connection_key'])) ?>">▶ Deschide afisaj</a>
  <button class="pb-btn primary" id="btnSave">💾 Salveaza</button>
</div>
<div class="pb-wrap">
  <div class="pb-pane pb-left">
    <div class="pb-grp">Sabloane ecran</div>
    <div class="pb-pal" id="presets"></div>
    <div class="pb-grp" style="margin-top:1.2rem">Adauga widget</div>
    <div class="pb-pal" id="palette"></div>
    <div class="pb-grp" style="margin-top:1.2rem">Ecrane</div>
    <div class="pb-screens" id="screens"></div>
    <button class="pb-btn" id="addScreen" style="width:100%;margin-top:.5rem;justify-content:center">+ Ecran nou</button>
  </div>
  <div class="pb-stagewrap">
    <div class="pb-stage" id="stage"></div>
  </div>
  <div class="pb-pane pb-right" id="inspector"></div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
window.PLAYER = {
  deviceId: <?= (int)$dev['id'] ?>,
  saveUrl: <?= json_encode('admin/devices/'.$dev['id'].'/player') ?>,
  accent: <?= json_encode($accent) ?>,
  config: <?= $cfg ? json_encode($cfg, JSON_UNESCAPED_UNICODE) : 'null' ?>
};
</script>
<script src="<?= e(asset('js/player_builder.js')) ?>"></script>
</body></html>
