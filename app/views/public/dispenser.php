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
// ---- multi-limba ----
$dict = disp_strings();
$enabledLangs = array_values(array_filter(array_map('trim', explode(',', setting('dispenser_langs','ro')))));
if (!in_array('ro',$enabledLangs,true)) array_unshift($enabledLangs,'ro');
$lang = strtolower((string)($_GET['lang'] ?? 'ro'));
if (!in_array($lang, $enabledLangs, true)) $lang = 'ro';
$tr = function(string $key, string $base) use ($dict,$lang){ return $lang==='ro' ? $base : ($dict[$key][$lang] ?? $base); };
$svcName = function(array $s) use ($lang){ if ($lang==='ro'||empty($s['i18n'])) return $s['name'];
    $t=json_decode($s['i18n'],true); return (is_array($t)&&!empty($t[$lang]['name']))?$t[$lang]['name']:$s['name']; };
$svcDesc = function(array $s) use ($lang){ $d=(string)($s['description']??''); if ($lang==='ro'||empty($s['i18n'])) return $d;
    $t=json_decode($s['i18n'],true); return (is_array($t)&&isset($t[$lang]['description'])&&$t[$lang]['description']!=='')?(string)$t[$lang]['description']:$d; };
$title_txt = $tr('title', $title_txt);
// URL-uri pentru bara de limbi + revenire la limba implicita dupa bilet
$__uri = $_SERVER['REQUEST_URI'] ?? ''; $__path = strtok($__uri,'?');
parse_str((string)parse_url($__uri, PHP_URL_QUERY), $__qs); unset($__qs['lang']);
$revertUrl = $__path . ($__qs ? '?'.http_build_query($__qs) : '');
$langHref = function($code) use ($__path,$__qs){ $q=$__qs; $q['lang']=$code; return $__path.'?'.http_build_query($q); };
$langMeta = disp_lang_meta();
// ---- grupare servicii pe dispenser ----
$groupsById = [];
try { foreach (all('SELECT id,name,color,sort_order FROM service_groups WHERE branch_id=? ORDER BY sort_order,name', [$branch['id']]) as $g) $groupsById[(int)$g['id']] = $g; } catch (Throwable $e) {}
$grouped = []; $ungrouped = [];
foreach ($services as $s) { $gid=(int)($s['group_id']??0); if ($gid && isset($groupsById[$gid])) $grouped[$gid][]=$s; else $ungrouped[]=$s; }
$hasGroups = !empty($grouped);
// randeaza un buton de serviciu (folosit si in mod plat, si grupat)
$renderBtn = function(array $s) use ($tr,$gd,$gb,$T,$PU,$svcName,$svcDesc) {
  $open=service_is_open($s); $snm=$svcName($s); $sds=$svcDesc($s); ?>
      <button class="svc-btn<?= $open?'':' closed' ?>" <?= $open?'':'disabled' ?> data-id="<?= (int)$s['id'] ?>" data-color="<?= e($s['color']) ?>" data-priority="<?= $s['allow_priority']?'1':'0' ?>" data-name="<?= e($snm) ?>"
              style="background:linear-gradient(135deg,<?= e($s['color']) ?>,<?= e($s['color']) ?>cc)">
        <span class="pfx"><?= e($s['prefix']) ?></span>
        <span class="nm"><?= e($snm) ?></span>
        <span class="ds"><?= $open ? e($sds ?: $tr('btn_hint',$gd($T,'btn_hint','Apasati pentru bilet'))) : e($tr('closed_hint',$gd($T,'closed_hint','Inchis acum'))) ?></span>
        <?php if(!$open): ?>
          <span class="pill" style="background:rgba(0,0,0,.4);color:#fff;align-self:flex-start;margin-top:.5rem"><?= e($tr('closed_label',$gd($T,'closed_label','🔒 Inchis'))) ?></span>
        <?php elseif($s['allow_priority'] && !$gb($PU,'ask_type',false)): ?>
          <span class="prio-btn pill" style="background:rgba(255,255,255,.25);color:#fff;align-self:flex-start;margin-top:.5rem"><?= e($tr('priority_label',$gd($T,'priority_label','★ Bilet prioritar'))) ?></span>
        <?php endif; ?>
      </button>
<?php };
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
.lang-bar{position:fixed;top:14px;right:16px;display:flex;gap:.4rem;z-index:6;flex-wrap:wrap;justify-content:flex-end;max-width:60vw}
.lang-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .7rem;border-radius:999px;background:rgba(0,0,0,.16);color:#1a1d23;font-weight:800;font-size:.9rem;text-decoration:none}
.lang-pill .fl{font-size:1.15rem;line-height:1}
.lang-pill.on{background:#1a1d23;color:#fff}
.grp-head{font-weight:800;font-size:1.3rem;color:#1a1d23;margin:1.4rem auto .2rem;max-width:1100px;width:100%;padding:.3rem .8rem;background:rgba(0,0,0,.04);border-radius:8px}
.grp-head:first-of-type{margin-top:.4rem}
</style>
<body><div class="kiosk">
  <?php if(count($enabledLangs)>1): ?>
  <div class="lang-bar">
    <?php foreach($enabledLangs as $lc): if(!isset($langMeta[$lc])) continue; ?>
      <a href="<?= e($langHref($lc)) ?>" class="lang-pill<?= $lc===$lang?' on':'' ?>"><span class="fl"><?= $langMeta[$lc][1] ?></span><?= e(strtoupper($lc)) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="kiosk-head">
    <?php if($logo): ?><img class="logo" src="<?= e($logo) ?>" alt=""><?php endif; ?>
    <h1><?= e($title_txt) ?></h1>
    <?php if($gd($T,'subtitle','')): ?><p class="muted"><?= e($gd($T,'subtitle','')) ?></p><?php endif; ?>
  </div>
  <?php if(!$services): ?>
    <div class="svc-grid"><p class="muted" style="text-align:center;grid-column:1/-1"><?= e($tr('no_services',$gd($T,'no_services','Momentan nu sunt servicii disponibile'))) ?></p></div>
  <?php elseif($hasGroups): ?>
    <?php foreach($groupsById as $gid=>$g): if(empty($grouped[$gid])) continue; ?>
      <div class="grp-head" style="border-left:5px solid <?= e($g['color']) ?>"><?= e($g['name']) ?></div>
      <div class="svc-grid"><?php foreach($grouped[$gid] as $s) $renderBtn($s); ?></div>
    <?php endforeach; ?>
    <?php if($ungrouped): ?>
      <div class="grp-head" style="border-left:5px solid #64748b">Alte servicii</div>
      <div class="svc-grid"><?php foreach($ungrouped as $s) $renderBtn($s); ?></div>
    <?php endif; ?>
  <?php else: ?>
    <div class="svc-grid"><?php foreach($services as $s) $renderBtn($s); ?></div>
  <?php endif; ?>
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
  lang:<?= json_encode($lang) ?>, revertUrl:<?= json_encode($revertUrl) ?>,
  autoReturn:<?= (int)$gd($L,'auto_return_sec',7) ?>, screensaver:<?= (int)$gd($L,'screensaver_sec',0) ?>,
  print:{logo:<?= $gb($L,'print_logo',true)?1:0 ?>,service:<?= $gb($L,'print_service',true)?1:0 ?>,position:<?= $gb($L,'print_position',true)?1:0 ?>,datetime:<?= $gb($L,'print_datetime',true)?1:0 ?>,qr:<?= $gb($L,'print_qr',true)?1:0 ?>},
  texts:{popup_title:<?= json_encode($tr('popup_title',$gd($T,'popup_title','Biletul dumneavoastra'))) ?>,ahead:<?= json_encode($tr('ahead_text',$gd($T,'ahead_text','Sunt {n} persoane inaintea dumneavoastra'))) ?>,ahead_first:<?= json_encode($tr('ahead_first',$gd($T,'ahead_first','Sunteti urmatorul la rand'))) ?>,qr_hint:<?= json_encode($tr('qr_hint',$gd($T,'qr_hint','Urmariti pe telefon'))) ?>,done:<?= json_encode($tr('done_btn',$gd($T,'done_btn','Gata'))) ?>},
  popup:{ask_type:<?= $gb($PU,'ask_type',false)?'true':'false' ?>,regular:<?= json_encode($tr('regular_label',$gd($PU,'regular_label','BILET NORMAL'))) ?>,priority:<?= json_encode($tr('priority_label_pu',$gd($PU,'priority_label','BILET PRIORITAR'))) ?>,policy_enabled:<?= $gb($PU,'policy_enabled',false)?'true':'false' ?>,policy_title:<?= json_encode($gd($PU,'policy_title','Politica bilet prioritar')) ?>,policy_text:<?= json_encode($gd($PU,'policy_text','')) ?>,policy_checkbox:<?= json_encode($gd($PU,'policy_checkbox','Accept termenii si conditiile')) ?>,policy_cancel:<?= json_encode($tr('policy_cancel',$gd($PU,'policy_cancel','Anuleaza'))) ?>,policy_ok:<?= json_encode($tr('policy_ok',$gd($PU,'policy_ok','Continua'))) ?>}
};</script>
<script src="<?= e(asset('js/dispenser.js')) ?>?v=2"></script>
</body></html>
