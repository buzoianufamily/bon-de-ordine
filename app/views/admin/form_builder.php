<?php
$fields = $row && !empty($row['fields']) ? $row['fields'] : '[]';
$accent = setting('accent_color', '#2563eb');
?>
<!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $row?'Editare formular':'Formular nou' ?></title>
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="base" content="<?= e(base_url()) ?>">
<link rel="stylesheet" href="<?= e(asset('fonts.css')) ?>">
<style>
:root{--accent:<?= e($accent) ?>}*{box-sizing:border-box}
body{margin:0;font-family:"Manrope",system-ui,sans-serif;background:#0b0d12;color:#e6e8ec;height:100vh;overflow:hidden}
.top{height:56px;display:flex;align-items:center;gap:1rem;padding:0 1rem;background:#11141b;border-bottom:1px solid #1c2029}
.top .ttl{font-family:"Bricolage Grotesque";font-weight:800;font-size:1.05rem}.top .sp{flex:1}.muted{color:#7d8696;font-size:.82rem}
.btn{display:inline-flex;align-items:center;gap:.4rem;border:1px solid #2a2f3a;background:#1a1d23;color:#e6e8ec;padding:.5rem .9rem;border-radius:9px;font-weight:700;cursor:pointer;font-size:.9rem;text-decoration:none}
.btn:hover{border-color:#3a4151}.btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}
.wrap{display:grid;grid-template-columns:1fr 460px;height:calc(100vh - 56px)}
.left{overflow:auto;padding:1.2rem}.right{overflow:auto;border-left:1px solid #1c2029;padding:1.2rem;background:#0e1117}
input,select,textarea{width:100%;padding:.55rem;border:1px solid #2a2f3a;background:#0e1117;color:#e6e8ec;border-radius:8px;font-size:.9rem;font-family:inherit}
.right input,.right select,.right textarea{background:#11141b}
label{display:block;font-size:.8rem;font-weight:700;color:#aab1bd;margin-bottom:.3rem}
.fld{background:#11141b;border:1px solid #1c2029;border-radius:12px;padding:.9rem;margin-bottom:.7rem}
.fld .hd{display:flex;gap:.5rem;align-items:center;margin-bottom:.6rem}
.fld .hd .h{font-family:"Bricolage Grotesque";font-weight:800;color:#7d8696}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}
.chk{display:flex;align-items:center;gap:.4rem;font-size:.85rem;cursor:pointer;white-space:nowrap}.chk input{width:auto}
.iconbtn{background:#1a1d23;border:1px solid #2a2f3a;color:#cdd2da;border-radius:7px;padding:.3rem .5rem;cursor:pointer;font-weight:700}
.iconbtn:hover{border-color:#3a4151}.iconbtn.del:hover{color:#ff8a8a;border-color:#5b2330}
.pv-card{background:#11141b;border-radius:14px;padding:1.2rem}
.pv-card h3{font-family:"Bricolage Grotesque";margin:.2rem 0 1rem}
.pv-f{margin-bottom:.8rem}
</style></head><body>
<div class="top">
  <a class="btn" href="<?= e(url('admin/forms')) ?>">← Formulare</a>
  <span class="ttl"><?= $row?'Editare formular':'Formular nou' ?></span><span class="sp"></span>
  <button class="btn primary" id="btnSave">💾 Salveaza</button>
</div>
<div class="wrap">
  <div class="left">
    <div style="max-width:680px">
      <div style="margin-bottom:1rem"><label>Nume formular</label><input id="formName" value="<?= e($row['name'] ?? '') ?>" placeholder="ex: Date contact"></div>
      <div id="fields"></div>
      <button class="btn" id="addField" style="width:100%;justify-content:center;margin-top:.4rem">+ Adauga camp</button>
    </div>
  </div>
  <div class="right">
    <div class="muted" style="margin-bottom:.6rem">Previzualizare (cum apare pe dispenser)</div>
    <div class="pv-card"><h3 id="pvName">Formular</h3><div id="pvFields"></div>
      <button class="btn primary" style="width:100%;justify-content:center;margin-top:.5rem" disabled>Continua</button></div>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
window.FORM = { id:<?= $row ? (int)$row['id'] : 'null' ?>, saveUrl:'admin/forms', fields:<?= json_encode(json_decode($fields ?: '[]', true) ?: [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?> };
</script>
<script src="<?= e(asset('js/form_builder.js')) ?>"></script>
</body></html>
