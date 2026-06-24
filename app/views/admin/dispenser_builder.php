<?php
/* Editor configurare dispenser (Logic / Aspect / Texte / Popup) cu preview live. */
$cfg = $dev['config'] ? json_decode($dev['config'], true) : [];
$L=$cfg['logic']??[]; $A=$cfg['appearance']??[]; $T=$cfg['texts']??[]; $PU=$cfg['popup']??[];
$accent = setting('accent_color', '#2563eb');
$gd = fn($a,$k,$d)=>(isset($a[$k]) && $a[$k]!=='') ? $a[$k] : $d;     // valoare sau default
$gb = fn($a,$k,$d)=>array_key_exists($k,$a) ? (bool)$a[$k] : $d;       // bool sau default
$cb = fn($checked)=>$checked?'checked':'';
?>
<!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configurare dispenser · <?= e($dev['name']) ?></title>
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="base" content="<?= e(base_url()) ?>">
<link rel="stylesheet" href="<?= e(asset('fonts.css')) ?>">
<style>
:root{--accent:<?= e($accent) ?>}
*{box-sizing:border-box}
body{margin:0;font-family:"Manrope",system-ui,sans-serif;background:#0b0d12;color:#e6e8ec;height:100vh;overflow:hidden}
.top{height:56px;display:flex;align-items:center;gap:1rem;padding:0 1rem;background:#11141b;border-bottom:1px solid #1c2029}
.top .ttl{font-family:"Bricolage Grotesque",sans-serif;font-weight:800;font-size:1.05rem}
.top .sp{flex:1}.muted{color:#7d8696;font-size:.82rem}
.btn{display:inline-flex;align-items:center;gap:.4rem;border:1px solid #2a2f3a;background:#1a1d23;color:#e6e8ec;padding:.5rem .9rem;border-radius:9px;font-weight:700;cursor:pointer;font-size:.9rem;text-decoration:none}
.btn:hover{border-color:#3a4151}.btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}
.wrap{display:grid;grid-template-columns:1fr 1fr;height:calc(100vh - 56px)}
.left{overflow:auto;border-right:1px solid #1c2029}
.tabs{display:flex;gap:.3rem;padding:.8rem 1rem 0;position:sticky;top:0;background:#0b0d12;z-index:2}
.tab{padding:.55rem .9rem;border-radius:9px 9px 0 0;font-weight:700;font-size:.88rem;cursor:pointer;color:#7d8696;border:1px solid transparent;border-bottom:none}
.tab.active{background:#11141b;color:#fff;border-color:#1c2029}
.panel{display:none;padding:1.2rem}
.panel.active{display:block}
.field{margin-bottom:.85rem}
.field label{display:block;font-size:.8rem;font-weight:700;color:#aab1bd;margin-bottom:.3rem}
.field input,.field textarea,.field select{width:100%;padding:.55rem;border:1px solid #2a2f3a;background:#0e1117;color:#e6e8ec;border-radius:8px;font-size:.88rem;font-family:inherit}
.field input[type=color]{height:40px;padding:3px}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}
.chk{display:flex;align-items:center;gap:.5rem;font-size:.88rem;margin:.5rem 0;cursor:pointer}
.chk input{width:auto}
.hint{font-size:.76rem;color:#6b7280;margin-top:.2rem}
.pickrow{display:flex;gap:.4rem}.pickrow .btn{white-space:nowrap}
.mgrid{display:none;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:.5rem}
.mgrid img{width:100%;height:48px;object-fit:contain;background:#0e1117;border-radius:6px;cursor:pointer;border:1px solid #2a2f3a;padding:2px}
/* preview */
.pv-wrap{background:#0b0d12 radial-gradient(circle at 50% 0,#141925,#0b0d12);display:flex;align-items:center;justify-content:center;padding:1.5rem;overflow:auto}
.pv{width:100%;max-width:520px;aspect-ratio:9/14;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.5);display:flex;flex-direction:column}
.pv-head{padding:1.4rem 1rem .6rem;text-align:center}
.pv-head img{max-height:54px;margin-bottom:.4rem;display:none}
.pv-head h1{font-family:"Bricolage Grotesque";margin:.2rem 0;line-height:1.05}
.pv-grid{flex:1;display:grid;grid-template-columns:1fr 1fr;gap:.7rem;padding:1rem;align-content:center}
.pv-btn{position:relative;border:none;color:#fff;text-align:left;padding:1rem;min-height:90px;display:flex;flex-direction:column;justify-content:flex-end;overflow:hidden}
.pv-btn .pf{position:absolute;right:.4rem;top:.1rem;font-family:"Bricolage Grotesque";font-weight:800;font-size:2.4rem;opacity:.35}
.pv-btn .nm{font-family:"Bricolage Grotesque";font-weight:800;font-size:1.1rem;z-index:1}
.pv-btn .ds{font-size:.75rem;opacity:.9;z-index:1}
</style></head><body>
<div class="top">
  <a class="btn" href="<?= e(url('admin/devices')) ?>">← Dispozitive</a>
  <span class="ttl">Configurare dispenser · <?= e($dev['name']) ?></span>
  <span class="muted">cheie <?= e($dev['connection_key']) ?></span>
  <span class="sp"></span>
  <a class="btn" target="_blank" href="<?= e(url('launcher?key='.$dev['connection_key'])) ?>">▶ Deschide</a>
  <button class="btn primary" id="btnSave">💾 Salveaza</button>
</div>
<div class="wrap">
  <div class="left">
    <div class="tabs">
      <div class="tab active" data-p="logic">Logic</div>
      <div class="tab" data-p="aspect">Aspect</div>
      <div class="tab" data-p="texte">Texte</div>
      <div class="tab" data-p="popup">Popup</div>
    </div>

    <!-- LOGIC -->
    <div class="panel active" data-p="logic">
      <div class="row2">
        <div class="field"><label>Inchidere automata bilet (sec)</label><input type="number" id="f__logic__auto_return_sec" value="<?= e($gd($L,'auto_return_sec',7)) ?>"></div>
        <div class="field"><label>Screensaver dupa (sec, 0=oprit)</label><input type="number" id="f__logic__screensaver_sec" value="<?= e($gd($L,'screensaver_sec',0)) ?>"></div>
      </div>
      <label class="chk"><input type="checkbox" id="f__logic__show_waiting" <?= $cb($gb($L,'show_waiting',false)) ?>> Arata pe butoane cati asteapta la fiecare serviciu (👥, live)</label>
      <div class="field"><label>Ce se tipareste pe bon</label>
        <label class="chk"><input type="checkbox" id="f__logic__print_logo" <?= $cb($gb($L,'print_logo',true)) ?>> Logo</label>
        <label class="chk"><input type="checkbox" id="f__logic__print_service" <?= $cb($gb($L,'print_service',true)) ?>> Numele serviciului</label>
        <label class="chk"><input type="checkbox" id="f__logic__print_position" <?= $cb($gb($L,'print_position',true)) ?>> Cati sunt inainte</label>
        <label class="chk"><input type="checkbox" id="f__logic__print_datetime" <?= $cb($gb($L,'print_datetime',true)) ?>> Data si ora</label>
        <label class="chk"><input type="checkbox" id="f__logic__print_qr" <?= $cb($gb($L,'print_qr',true)) ?>> Cod QR (urmarire pe telefon)</label>
      </div>
      <p class="hint">Serviciile afisate si modul de printare se seteaza din pagina dispozitivului (Editeaza).</p>
    </div>

    <!-- ASPECT -->
    <div class="panel" data-p="aspect">
      <div class="row2">
        <div class="field"><label>Culoare fundal</label><input type="color" id="f__appearance__bg_color" value="<?= e($gd($A,'bg_color','#eef1f6')) ?>"></div>
        <div class="field"><label>Culoare titlu</label><input type="color" id="f__appearance__header_color" value="<?= e($gd($A,'header_color','#1a1d23')) ?>"></div>
      </div>
      <div class="field"><label>Imagine fundal (optional)</label>
        <div class="pickrow"><input type="text" id="f__appearance__bg_image" value="<?= e($gd($A,'bg_image','')) ?>" placeholder="URL imagine"><button type="button" class="btn" data-pick="f__appearance__bg_image">📁</button></div>
        <div class="mgrid" data-grid="f__appearance__bg_image"></div>
      </div>
      <div class="field"><label>Logo (override; gol = logo global)</label>
        <div class="pickrow"><input type="text" id="f__appearance__logo_url" value="<?= e($gd($A,'logo_url','')) ?>" placeholder="URL logo"><button type="button" class="btn" data-pick="f__appearance__logo_url">📁</button></div>
        <div class="mgrid" data-grid="f__appearance__logo_url"></div>
      </div>
      <div class="row2">
        <div class="field"><label>Marime titlu (px)</label><input type="number" id="f__appearance__title_size" value="<?= e($gd($A,'title_size',40)) ?>"></div>
        <div class="field"><label>Inaltime logo (px)</label><input type="number" id="f__appearance__logo_height" value="<?= e($gd($A,'logo_height',74)) ?>"></div>
      </div>
      <div class="row2">
        <div class="field"><label>Rotunjire butoane (px)</label><input type="number" id="f__appearance__btn_radius" value="<?= e($gd($A,'btn_radius',22)) ?>"></div>
        <div class="field"><label>Inaltime butoane (px)</label><input type="number" id="f__appearance__btn_height" value="<?= e($gd($A,'btn_height',170)) ?>"></div>
      </div>
      <div class="field"><label>Culoare text butoane</label><input type="color" id="f__appearance__btn_text_color" value="<?= e($gd($A,'btn_text_color','#ffffff')) ?>"></div>
      <label class="chk"><input type="checkbox" id="f__appearance__watermark" <?= $cb($gb($A,'watermark',true)) ?>> Litera prefix mare in fundalul butonului</label>
    </div>

    <!-- TEXTE -->
    <div class="panel" data-p="texte">
      <div class="field"><label>Titlu</label><input id="f__texts__title" value="<?= e($gd($T,'title', setting('dispenser_title','ALEGE SERVICIUL'))) ?>"></div>
      <div class="field"><label>Subtitlu (optional)</label><input id="f__texts__subtitle" value="<?= e($gd($T,'subtitle','')) ?>"></div>
      <div class="field"><label>Text pe buton (sub nume)</label><input id="f__texts__btn_hint" value="<?= e($gd($T,'btn_hint','Apasati pentru bilet')) ?>"></div>
      <div class="field"><label>Mesaj cand nu sunt servicii</label><input id="f__texts__no_services" value="<?= e($gd($T,'no_services','Momentan nu sunt servicii disponibile')) ?>"></div>
      <div class="field"><label>Eticheta buton prioritar</label><input id="f__texts__priority_label" value="<?= e($gd($T,'priority_label','★ Bilet prioritar')) ?>"></div>
      <div class="field"><label>Text serviciu inchis (sub nume)</label><input id="f__texts__closed_hint" value="<?= e($gd($T,'closed_hint','Inchis acum')) ?>"></div>
      <div class="field"><label>Eticheta „inchis" (insigna)</label><input id="f__texts__closed_label" value="<?= e($gd($T,'closed_label','🔒 Inchis')) ?>"></div>
      <hr style="border:none;border-top:1px solid #1c2029;margin:1rem 0">
      <div class="field"><label>Titlu fereastra bilet</label><input id="f__texts__popup_title" value="<?= e($gd($T,'popup_title','Biletul dumneavoastra')) ?>"></div>
      <div class="field"><label>Text „X inainte" (foloseste {n})</label><input id="f__texts__ahead_text" value="<?= e($gd($T,'ahead_text','Sunt {n} persoane inaintea dumneavoastra')) ?>"></div>
      <div class="field"><label>Text cand e primul la rand</label><input id="f__texts__ahead_first" value="<?= e($gd($T,'ahead_first','Sunteti urmatorul la rand')) ?>"></div>
      <div class="field"><label>Text timp estimat (foloseste {m} = minute)</label><input id="f__texts__wait_est_text" value="<?= e($gd($T,'wait_est_text','Timp estimat ~{m} min')) ?>"></div>
      <div class="field"><label>Text langa codul QR</label><input id="f__texts__qr_hint" value="<?= e($gd($T,'qr_hint','Urmariti pe telefon')) ?>"></div>
      <div class="row2">
        <div class="field"><label>Buton inchidere</label><input id="f__texts__done_btn" value="<?= e($gd($T,'done_btn','Gata')) ?>"></div>
        <div class="field"><label>Subsol bon (gol = global)</label><input id="f__texts__footer" value="<?= e($gd($T,'footer','')) ?>"></div>
      </div>
    </div>

    <!-- POPUP -->
    <div class="panel" data-p="popup">
      <label class="chk"><input type="checkbox" id="f__popup__ask_type" <?= $cb($gb($PU,'ask_type',false)) ?>> Intreaba tipul biletului (Normal / Prioritar)</label>
      <p class="hint">Apare doar la serviciile care permit bilete prioritare.</p>
      <div class="row2">
        <div class="field"><label>Eticheta „Normal"</label><input id="f__popup__regular_label" value="<?= e($gd($PU,'regular_label','BILET NORMAL')) ?>"></div>
        <div class="field"><label>Eticheta „Prioritar"</label><input id="f__popup__priority_label" value="<?= e($gd($PU,'priority_label','BILET PRIORITAR')) ?>"></div>
      </div>
      <hr style="border:none;border-top:1px solid #1c2029;margin:1rem 0">
      <label class="chk"><input type="checkbox" id="f__popup__policy_enabled" <?= $cb($gb($PU,'policy_enabled',false)) ?>> Cere acceptarea termenilor la bilet prioritar</label>
      <div class="field"><label>Titlu politica</label><input id="f__popup__policy_title" value="<?= e($gd($PU,'policy_title','Politica bilet prioritar')) ?>"></div>
      <div class="field"><label>Text politica</label><textarea id="f__popup__policy_text" rows="3"><?= e($gd($PU,'policy_text','Biletele prioritare sunt destinate persoanelor cu dizabilitati, varstnicilor, femeilor insarcinate si parintilor cu copii mici.')) ?></textarea></div>
      <div class="field"><label>Text casuta acceptare</label><input id="f__popup__policy_checkbox" value="<?= e($gd($PU,'policy_checkbox','Accept termenii si conditiile')) ?>"></div>
      <div class="row2">
        <div class="field"><label>Buton anulare</label><input id="f__popup__policy_cancel" value="<?= e($gd($PU,'policy_cancel','Anuleaza')) ?>"></div>
        <div class="field"><label>Buton confirmare</label><input id="f__popup__policy_ok" value="<?= e($gd($PU,'policy_ok','Continua')) ?>"></div>
      </div>
    </div>
  </div>

  <!-- PREVIEW -->
  <div class="pv-wrap">
    <div class="pv" id="pv">
      <div class="pv-head"><img id="pvLogo" src="" alt=""><h1 id="pvTitle">ALEGE SERVICIUL</h1><div class="muted" id="pvSub"></div></div>
      <div class="pv-grid" id="pvGrid"></div>
    </div>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
window.DBUILD = {
  saveUrl: <?= json_encode('admin/devices/'.$dev['id'].'/dispenser') ?>,
  accent: <?= json_encode($accent) ?>,
  globalLogo: <?= json_encode(setting('brand_logo','')) ?>
};
</script>
<script src="<?= e(asset('js/dispenser_builder.js')) ?>"></script>
</body></html>
