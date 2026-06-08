<?php
$title=setting('brand_name','Bon').' · Dispenser'; require __DIR__.'/_head.php';
$cfg = $dev['config'] ? json_decode($dev['config'], true) : [];
$L=$cfg['logic']??[]; $A=$cfg['appearance']??[]; $T=$cfg['texts']??[]; $PU=$cfg['popup']??[];
$gd=fn($a,$k,$d)=>(isset($a[$k]) && $a[$k]!=='') ? $a[$k] : $d;
$gb=fn($a,$k,$d)=>array_key_exists($k,$a) ? (bool)$a[$k] : $d;
$logo = $gd($A,'logo_url', setting('brand_logo',''));
$bgImg = $gd($A,'bg_image','');
$bgColor = $gd($A,'bg_color','#eef1f6');
$title_txt = $gd($T,'title', setting('dispenser_title','ALEGE SERVICIUL'));
$footer = $gd($T,'footer', setting('ticket_footer',''));
$printerMode = $dev['type']==='digital_ticket' ? 'none' : $dev['printer_mode'];
// formulare atasate serviciilor afisate
$svcForms = [];
$fids = array_values(array_unique(array_filter(array_map(fn($s)=>(int)($s['form_id']??0), $services))));
if ($fids) {
    $in = implode(',', array_fill(0, count($fids), '?'));
    $byId = [];
    foreach (all("SELECT id,name,fields FROM forms WHERE id IN ($in)", $fids) as $r)
        $byId[(int)$r['id']] = ['name'=>$r['name'], 'fields'=>json_decode($r['fields'] ?: '[]', true) ?: []];
    foreach ($services as $s) { $fid=(int)($s['form_id']??0); if ($fid && isset($byId[$fid])) $svcForms[(int)$s['id']] = $byId[$fid]; }
}
?>
<style>
.kiosk{background:<?= $bgImg ? "center/cover url('".e($bgImg)."')" : "linear-gradient(180deg,#fff,".e($bgColor).")" ?>}
.kiosk-head h1{color:<?= e($gd($A,'header_color','#1a1d23')) ?>;font-size:<?= (int)$gd($A,'title_size',40) ?>px}
.kiosk-head .logo{max-height:<?= (int)$gd($A,'logo_height',74) ?>px}
.svc-btn{border-radius:<?= (int)$gd($A,'btn_radius',22) ?>px;min-height:<?= (int)$gd($A,'btn_height',170) ?>px;color:<?= e($gd($A,'btn_text_color','#ffffff')) ?>}
.svc-btn .pfx{display:<?= $gb($A,'watermark',true)?'block':'none' ?>}
.svc-btn.closed{opacity:.5;filter:grayscale(.7);cursor:not-allowed}
</style>
<body><div class="kiosk">
  <div class="kiosk-head">
    <?php if($logo): ?><img class="logo" src="<?= e($logo) ?>" alt=""><?php endif; ?>
    <h1><?= e($title_txt) ?></h1>
    <?php if($gd($T,'subtitle','')): ?><p class="muted"><?= e($gd($T,'subtitle','')) ?></p><?php endif; ?>
  </div>
  <div class="svc-grid">
    <?php if(!$services): ?><p class="muted" style="text-align:center;grid-column:1/-1"><?= e($gd($T,'no_services','Momentan nu sunt servicii disponibile')) ?></p><?php endif; ?>
    <?php foreach($services as $s): $open = service_is_open($s); ?>
      <button class="svc-btn<?= $open?'':' closed' ?>" <?= $open?'':'disabled' ?> data-id="<?= (int)$s['id'] ?>" data-color="<?= e($s['color']) ?>" data-priority="<?= $s['allow_priority']?'1':'0' ?>" data-name="<?= e($s['name']) ?>"
              style="background:linear-gradient(135deg,<?= e($s['color']) ?>,<?= e($s['color']) ?>cc)">
        <span class="pfx"><?= e($s['prefix']) ?></span>
        <span class="nm"><?= e($s['name']) ?></span>
        <span class="ds"><?= $open ? e($s['description'] ?: $gd($T,'btn_hint','Apasati pentru bilet')) : e($gd($T,'closed_hint','Inchis acum')) ?></span>
        <?php if(!$open): ?>
          <span class="pill" style="background:rgba(0,0,0,.4);color:#fff;align-self:flex-start;margin-top:.5rem"><?= e($gd($T,'closed_label','🔒 Inchis')) ?></span>
        <?php elseif($s['allow_priority'] && !$gb($PU,'ask_type',false)): ?>
          <span class="prio-btn pill" style="background:rgba(255,255,255,.25);color:#fff;align-self:flex-start;margin-top:.5rem"><?= e($gd($T,'priority_label','★ Bilet prioritar')) ?></span>
        <?php endif; ?>
      </button>
    <?php endforeach; ?>
  </div>
</div>

<!-- overlay bilet -->
<div class="kiosk-overlay" id="overlay">
  <div class="ticket-card">
    <div class="svc" id="tkSvc"></div>
    <div class="big" id="tkNum">—</div>
    <img class="qr" id="tkQr" alt="" style="display:none">
    <div class="muted" id="tkPos"></div>
    <div class="countdown">Se inchide in <span id="cd">7</span>s</div>
    <button class="btn" style="margin-top:1rem" onclick="qmsCloseTicket()" id="tkDone">Gata</button>
  </div>
</div>

<!-- overlay alegere tip bilet / politica (popup) -->
<div class="kiosk-overlay" id="modal"><div class="ticket-card" id="modalBox"></div></div>

<!-- screensaver -->
<div class="kiosk-overlay" id="saver" style="background:#000"><img id="saverLogo" src="<?= e($logo) ?>" style="max-width:60%;max-height:60%;opacity:.85"></div>

<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>window.DISPENSER={
  key:<?= json_encode($dev['connection_key']) ?>, printerMode:<?= json_encode($printerMode) ?>,
  accent:<?= json_encode(setting('accent_color','#2563eb')) ?>, brand:<?= json_encode(setting('brand_name','')) ?>,
  branch:<?= json_encode($branch['name']) ?>, footer:<?= json_encode($footer) ?>,
  virtual:<?= setting('virtual_enabled','1')==='1'?'true':'false' ?>, logo:<?= json_encode($logo) ?>,
  forms:<?= json_encode($svcForms, JSON_UNESCAPED_UNICODE) ?>,
  autoReturn:<?= (int)$gd($L,'auto_return_sec',7) ?>, screensaver:<?= (int)$gd($L,'screensaver_sec',0) ?>,
  print:{logo:<?= $gb($L,'print_logo',true)?1:0 ?>,service:<?= $gb($L,'print_service',true)?1:0 ?>,position:<?= $gb($L,'print_position',true)?1:0 ?>,datetime:<?= $gb($L,'print_datetime',true)?1:0 ?>,qr:<?= $gb($L,'print_qr',true)?1:0 ?>},
  texts:{popup_title:<?= json_encode($gd($T,'popup_title','Biletul dumneavoastra')) ?>,ahead:<?= json_encode($gd($T,'ahead_text','Sunt {n} persoane inaintea dumneavoastra')) ?>,ahead_first:<?= json_encode($gd($T,'ahead_first','Sunteti urmatorul la rand')) ?>,qr_hint:<?= json_encode($gd($T,'qr_hint','Urmariti pe telefon')) ?>,done:<?= json_encode($gd($T,'done_btn','Gata')) ?>},
  popup:{ask_type:<?= $gb($PU,'ask_type',false)?'true':'false' ?>,regular:<?= json_encode($gd($PU,'regular_label','BILET NORMAL')) ?>,priority:<?= json_encode($gd($PU,'priority_label','BILET PRIORITAR')) ?>,policy_enabled:<?= $gb($PU,'policy_enabled',false)?'true':'false' ?>,policy_title:<?= json_encode($gd($PU,'policy_title','Politica bilet prioritar')) ?>,policy_text:<?= json_encode($gd($PU,'policy_text','')) ?>,policy_checkbox:<?= json_encode($gd($PU,'policy_checkbox','Accept termenii si conditiile')) ?>,policy_cancel:<?= json_encode($gd($PU,'policy_cancel','Anuleaza')) ?>,policy_ok:<?= json_encode($gd($PU,'policy_ok','Continua')) ?>}
};</script>
<script src="<?= e(asset('js/dispenser.js')) ?>"></script>
</body></html>
