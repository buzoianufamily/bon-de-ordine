<?php
$title=setting('brand_name','Bon').' · Dispenser'; $kiosk=true; require __DIR__.'/_head.php';
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
// ---- multi-limba: per dozator (din editor) daca e configurat, altfel setarea globala (compat) ----
$dict = disp_strings();
if (!empty($L['lang_menu'])) {
    $enabledLangs = ['ro'];
    foreach (['en','de','fr','hu','it','es'] as $lc) if (!empty($L['lang_'.$lc])) $enabledLangs[] = $lc;
    $defaultLang = (string)$gd($L,'lang_default','ro');
} else {
    $enabledLangs = array_values(array_filter(array_map('trim', explode(',', setting('dispenser_langs','ro')))));
    if (!in_array('ro',$enabledLangs,true)) array_unshift($enabledLangs,'ro');
    $defaultLang = 'ro';
}
$enabledLangs = array_values(array_unique($enabledLangs));
if (!in_array($defaultLang, $enabledLangs, true)) $defaultLang = 'ro';
$lang = strtolower((string)($_GET['lang'] ?? $defaultLang));
if (!in_array($lang, $enabledLangs, true)) $lang = $defaultLang;
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
// (optional) cati asteapta acum la fiecare serviciu — afisat pe butoane si actualizat live
$showWait = $gb($L,'show_waiting',false);
$apptCheckin = $gb($L,'appt_checkin',false) && setting('mod_booking','1')==='1';
$vkbd = $gb($L,'virtual_keyboard',false);
$checkin_btn   = $tr('checkin_btn',   $gd($T,'checkin_btn','Am o programare'));
$checkin_title = $tr('checkin_title', $gd($T,'checkin_title','Check-in programare'));
$checkin_hint  = $tr('checkin_hint',  $gd($T,'checkin_hint','Introdu codul programarii sau numarul de telefon'));
$waitCnt = [];
if ($showWait) {
    try { foreach (all("SELECT service_id, COUNT(*) c FROM tickets WHERE status='waiting' AND branch_id=? GROUP BY service_id", [$branch['id']]) as $r)
        $waitCnt[(int)$r['service_id']] = (int)$r['c']; } catch (Throwable $e) {}
}
$renderBtn = function(array $s) use ($tr,$gd,$gb,$T,$PU,$svcName,$svcDesc,$showWait,$waitCnt) {
  $capped=service_cap_reached($s); $open=service_is_open($s) && !$capped; $snm=$svcName($s); $sds=$svcDesc($s); ?>
      <button class="svc-btn<?= $open?'':' closed' ?>" <?= $open?'':'disabled' ?> data-id="<?= (int)$s['id'] ?>" data-color="<?= e($s['color']) ?>" data-priority="<?= $s['allow_priority']?'1':'0' ?>" data-name="<?= e($snm) ?>" data-desc="<?= e($sds) ?>"
              style="background:linear-gradient(135deg,<?= e($s['color']) ?>,<?= e($s['color']) ?>cc)">
        <?php if($showWait && $open): ?><span class="wbadge" data-wc="<?= (int)$s['id'] ?>" style="<?= empty($waitCnt[(int)$s['id']])?'display:none':'' ?>">👥 <b><?= (int)($waitCnt[(int)$s['id']] ?? 0) ?></b></span><?php endif; ?>
        <span class="pfx"><?= e($s['prefix']) ?></span>
        <span class="nm"><?= e($snm) ?></span>
        <span class="ds"><?php
          if ($open) echo e($sds ?: $tr('btn_hint',$gd($T,'btn_hint','Apasati pentru bilet')));
          elseif ($capped) echo e($tr('cap_hint',$gd($T,'cap_hint','Limita atinsa azi')));
          elseif (!empty($s['paused']) && !empty($s['pause_note'])) echo e($s['pause_note']);
          else {
            $no = service_next_open($s);
            if ($no) { $sameDay = date('Y-m-d',$no) === date('Y-m-d');
              echo e($tr('opens_at',$gd($T,'opens_at','Deschide')).' '.($sameDay ? date('H:i',$no) : date('d.m H:i',$no))); }
            else echo e($tr('closed_hint',$gd($T,'closed_hint','Inchis acum')));
          }
        ?></span>
        <?php if(!$open): ?>
          <span class="pill" style="background:rgba(0,0,0,.4);color:#fff;align-self:flex-start;margin-top:.5rem"><?= $capped ? e($tr('cap_label',$gd($T,'cap_label','⛔ Epuizat azi'))) : e($tr('closed_label',$gd($T,'closed_label','🔒 Inchis'))) ?></span>
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
<?php
// ---- aspect granular (font, asezare, ceas, spatiu antet, textura, aliniere titlu) ----
$fontFam = preg_replace('/[^a-zA-Z0-9 ,\'"\-]/', '', (string)$gd($A,'font_family',''));
$headerGap = $gd($A,'header_gap','');
$bgRepeat = $gd($A,'bg_repeat','cover');
$gridCols = (string)$gd($A,'grid_cols','');
$clock = $gb($A,'clock',false); $clock24 = $gb($A,'clock_24h',true);
$titleAlign = in_array($gd($T,'title_align','center'),['left','center','right'],true) ? $gd($T,'title_align','center') : 'center';
$bgCss = $bgImg
  ? ($bgRepeat==='tile' ? "url('".e($bgImg)."') repeat" : "center/cover url('".e($bgImg)."')")
  : "linear-gradient(180deg,#fff,".e($bgColor).")";
$gridCss = $gridCols==='list' ? 'grid-template-columns:1fr'
  : (in_array($gridCols,['2','3','4'],true) ? "grid-template-columns:repeat($gridCols,1fr)"
  : ($gridCols==='wide' ? 'grid-template-columns:repeat(auto-fit,minmax(340px,1fr))' : ''));
// overlay pe antet
$headerOverlay = (string)$gd($A,'header_overlay','');
$hoAlign = in_array($gd($A,'header_overlay_align','center'),['left','center','right'],true) ? $gd($A,'header_overlay_align','center') : 'center';
$hoH = (int)$gd($A,'header_overlay_height',0);
// distante grila bilete (per latura + gap-uri)
$gridBox = '';
foreach (['grid_pad_top'=>'padding-top','grid_pad_bottom'=>'padding-bottom','grid_pad_left'=>'padding-left','grid_pad_right'=>'padding-right','grid_row_gap'=>'row-gap','grid_col_gap'=>'column-gap'] as $k=>$css)
  if ($gd($A,$k,'')!=='') $gridBox .= $css.':'.(int)$gd($A,$k,0).'px;';
// stil texte (culoare/marime/aliniere) — culorile validate ca hex/rgb
$col = fn($v) => preg_match('/^#[0-9a-fA-F]{3,8}$|^rgba?\([0-9.,\s]+\)$/', (string)$v) ? (string)$v : '';
$subColor=$col($gd($A,'subtitle_color','')); $subSize=$gd($A,'subtitle_size','');
$subAlign=in_array($gd($A,'subtitle_align',''),['left','center','right'],true)?$gd($A,'subtitle_align',''):'';
$btnNameSize=$gd($A,'btn_name_size',''); $btnHintSize=$gd($A,'btn_hint_size','');
$nosvcColor=$col($gd($A,'nosvc_color','')); $nosvcSize=$gd($A,'nosvc_size','');
?>
<style>
.kiosk{background:<?= $bgCss ?>}
<?php if($fontFam): ?>.kiosk,.kiosk button,.kiosk input,.kiosk select,.kiosk textarea{font-family:<?= $fontFam ?>}<?php endif; ?>
.kiosk-head{text-align:<?= e($titleAlign) ?><?= $headerGap!=='' ? ';padding-top:'.(int)$headerGap.'px;padding-bottom:'.(int)$headerGap.'px' : '' ?>}
.kiosk-head h1{color:<?= e($gd($A,'header_color','#1a1d23')) ?>;font-size:<?= (int)$gd($A,'title_size',40) ?>px}
.kiosk-head .logo{max-height:<?= (int)$gd($A,'logo_height',74) ?>px}
/* pozitionare logo (sus/jos · stanga/centru/dreapta); implicit = comportamentul vechi (inline, dupa alinierea antetului) */
.kiosk-head .logo.lp-top-left{display:block;margin:0 auto 0 0}
.kiosk-head .logo.lp-top-center{display:block;margin:0 auto}
.kiosk-head .logo.lp-top-right{display:block;margin:0 0 0 auto}
.kiosk .logo.lp-bottom-left,.kiosk .logo.lp-bottom-center,.kiosk .logo.lp-bottom-right{position:fixed;bottom:18px;z-index:5}
.kiosk .logo.lp-bottom-left{left:18px}
.kiosk .logo.lp-bottom-right{right:18px}
.kiosk .logo.lp-bottom-center{left:50%;transform:translateX(-50%)}
.kiosk-clock{font-weight:800;color:<?= e($gd($A,'header_color','#1a1d23')) ?>;opacity:.85;font-size:1.05rem;margin-top:.2rem;font-variant-numeric:tabular-nums}
<?php if($gridCss): ?>.kiosk .svc-grid{<?= $gridCss ?>}<?php endif; ?>
<?php if($gridBox): ?>.kiosk .svc-grid{<?= $gridBox ?>}<?php endif; ?>
.kiosk-head .ovl{display:block;max-width:90%}
<?php if($subColor||$subSize!==''||$subAlign): ?>.kiosk-head .ksub{<?= $subColor?'color:'.$subColor.';':'' ?><?= $subSize!==''?'font-size:'.(int)$subSize.'px;':'' ?><?= $subAlign?'text-align:'.$subAlign.';':'' ?>}<?php endif; ?>
<?php if($btnNameSize!==''): ?>.kiosk .svc-btn .nm{font-size:<?= (int)$btnNameSize ?>px}<?php endif; ?>
<?php if($btnHintSize!==''): ?>.kiosk .svc-btn .ds{font-size:<?= (int)$btnHintSize ?>px}<?php endif; ?>
<?php if($nosvcColor||$nosvcSize!==''): ?>.kiosk .knosvc{<?= $nosvcColor?'color:'.$nosvcColor.';':'' ?><?= $nosvcSize!==''?'font-size:'.(int)$nosvcSize.'px;':'' ?>}<?php endif; ?>
.svc-btn{border-radius:<?= (int)$gd($A,'btn_radius',22) ?>px;min-height:<?= (int)$gd($A,'btn_height',170) ?>px;color:<?= e($gd($A,'btn_text_color','#ffffff')) ?>}
.svc-btn .pfx{display:<?= $gb($A,'watermark',true)?'block':'none' ?>}
.svc-btn.closed{opacity:.5;filter:grayscale(.7);cursor:not-allowed}
.lang-bar{position:fixed;top:14px;right:16px;display:flex;gap:.4rem;z-index:6;flex-wrap:wrap;justify-content:flex-end;max-width:60vw}
.lang-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .7rem;border-radius:999px;background:rgba(0,0,0,.16);color:#1a1d23;font-weight:800;font-size:.9rem;text-decoration:none}
.lang-pill .fl{font-size:1.15rem;line-height:1}
.lang-pill.on{background:#1a1d23;color:#fff}
.grp-head{font-weight:800;font-size:1.3rem;color:#1a1d23;margin:1.4rem auto .2rem;max-width:1100px;width:100%;padding:.3rem .8rem;background:rgba(0,0,0,.04);border-radius:8px}
.grp-head:first-of-type{margin-top:.4rem}
.wbadge{position:absolute;top:.7rem;right:.8rem;background:rgba(0,0,0,.35);color:#fff;border-radius:999px;padding:.25rem .7rem;font-weight:700;font-size:.95rem;line-height:1.2}
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
    <?php if($logo): $logoPos = in_array($gd($A,'logo_pos',''),['top-left','top-center','top-right','bottom-left','bottom-center','bottom-right'],true) ? $gd($A,'logo_pos','') : ''; ?><img class="logo<?= $logoPos ? ' lp-'.e($logoPos) : '' ?>" src="<?= e($logo) ?>" alt=""><?php endif; ?>
    <h1><?= e($title_txt) ?></h1>
    <?php if($gd($T,'subtitle','')): ?><p class="muted ksub"><?= e($gd($T,'subtitle','')) ?></p><?php endif; ?>
    <?php if($clock): ?><div class="kiosk-clock" id="kClock"></div><?php endif; ?>
    <?php if($headerOverlay): ?><img class="ovl" src="<?= e($headerOverlay) ?>" alt="" style="<?= $hoH?('max-height:'.$hoH.'px;'):'' ?>margin-left:<?= $hoAlign==='left'?'0':'auto' ?>;margin-right:<?= $hoAlign==='right'?'0':'auto' ?>"><?php endif; ?>
  </div>
  <?php $closedToday = branch_closure_reason((int)$branch['id']); if($closedToday !== null): ?>
    <div style="max-width:1100px;margin:.2rem auto 0;width:100%;background:#fee2e2;color:#b91c1c;border-radius:14px;padding:1rem 1.3rem;text-align:center;font-weight:800;font-size:1.15rem">
      🚫 <?= e($tr('closed_today', $gd($T,'closed_today','Închis astăzi'))) ?><?= $closedToday !== '' ? ' · '.e($closedToday) : '' ?>
    </div>
  <?php endif; ?>
  <?php $notice = active_notice(); ?>
  <div id="dspNotice" style="max-width:1100px;margin:.2rem auto 0;width:100%;background:#fef3c7;color:#92400e;border-radius:14px;padding:.8rem 1.2rem;text-align:center;font-weight:700;<?= $notice===''?'display:none':'' ?>"><?= $notice!=='' ? '📢 '.e($notice) : '' ?></div>
  <?php if($apptCheckin): ?>
  <div style="max-width:1100px;margin:.6rem auto 0;width:100%;text-align:center">
    <button class="btn btn-lg" id="btnCheckin" style="background:#fff;border:2px solid var(--accent,#00c375);color:var(--accent,#00c375);font-weight:800">📅 <?= e($checkin_btn) ?></button>
  </div>
  <?php endif; ?>
  <?php if(!$services): ?>
    <div class="svc-grid"><p class="muted knosvc" style="text-align:center;grid-column:1/-1"><?= e($tr('no_services',$gd($T,'no_services','Momentan nu sunt servicii disponibile'))) ?></p></div>
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
    <svg class="tk-check" viewBox="0 0 56 56" aria-hidden="true"><circle cx="28" cy="28" r="26"/><path d="M16 29l8 8 16-17"/></svg>
    <div class="svc" id="tkSvc"></div>
    <div class="big" id="tkNum">—</div>
    <img class="qr" id="tkQr" alt="" style="display:none">
    <div class="muted" id="tkPos"></div>
    <div class="muted" id="tkWait" style="display:none;margin-top:.2rem;font-weight:700"></div>
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
  key:<?= jsenc($dev['connection_key']) ?>, printerMode:<?= jsenc($printerMode) ?>,
  accent:<?= jsenc(setting('accent_color','#00c375')) ?>, brand:<?= jsenc(setting('brand_name','')) ?>,
  branch:<?= jsenc($branch['name']) ?>, footer:<?= jsenc($footer) ?>,
  virtual:<?= setting('virtual_enabled','1')==='1'?'true':'false' ?>, logo:<?= jsenc($logo) ?>,
  branchId:<?= (int)$branch['id'] ?>, showWaiting:<?= $showWait?'true':'false' ?>,
  apptCheckin:<?= $apptCheckin?'true':'false' ?>, virtualKeyboard:<?= $vkbd?'true':'false' ?>,
  forms:<?= jsenc($svcForms, JSON_UNESCAPED_UNICODE) ?>,
  lang:<?= jsenc($lang) ?>, revertUrl:<?= jsenc($revertUrl) ?>,
  autoReturn:<?= (int)$gd($L,'auto_return_sec',7) ?>, screensaver:<?= (int)$gd($L,'screensaver_sec',0) ?>,
  clock:<?= $clock?'true':'false' ?>, clock24:<?= $clock24?'true':'false' ?>,
  print:{logo:<?= $gb($L,'print_logo',true)?1:0 ?>,service:<?= $gb($L,'print_service',true)?1:0 ?>,position:<?= $gb($L,'print_position',true)?1:0 ?>,datetime:<?= $gb($L,'print_datetime',true)?1:0 ?>,qr:<?= $gb($L,'print_qr',true)?1:0 ?>,desc:<?= $gb($L,'print_desc',false)?1:0 ?>,header:<?= jsenc($tr('print_header',$gd($T,'print_header',''))) ?>,ahead:<?= jsenc($tr('print_ahead',$gd($T,'print_ahead',''))) ?>},
  texts:{popup_title:<?= jsenc($tr('popup_title',$gd($T,'popup_title','Biletul dumneavoastra'))) ?>,ahead:<?= jsenc($tr('ahead_text',$gd($T,'ahead_text','Sunt {n} persoane inaintea dumneavoastra'))) ?>,ahead_first:<?= jsenc($tr('ahead_first',$gd($T,'ahead_first','Sunteti urmatorul la rand'))) ?>,qr_hint:<?= jsenc($tr('qr_hint',$gd($T,'qr_hint','Urmariti pe telefon'))) ?>,done:<?= jsenc($tr('done_btn',$gd($T,'done_btn','Gata'))) ?>,wait_est:<?= jsenc($tr('wait_est_text',$gd($T,'wait_est_text','Timp estimat ~{m} min'))) ?>,checkin_title:<?= jsenc($checkin_title) ?>,checkin_hint:<?= jsenc($checkin_hint) ?>,form_submit:<?= jsenc($tr('form_submit',$gd($T,'form_submit','Continua'))) ?>,form_cancel:<?= jsenc($tr('form_cancel',$gd($T,'form_cancel','Anuleaza'))) ?>},
  popup:{ask_type:<?= $gb($PU,'ask_type',false)?'true':'false' ?>,regular:<?= jsenc($tr('regular_label',$gd($PU,'regular_label','BILET NORMAL'))) ?>,priority:<?= jsenc($tr('priority_label_pu',$gd($PU,'priority_label','BILET PRIORITAR'))) ?>,policy_enabled:<?= $gb($PU,'policy_enabled',false)?'true':'false' ?>,policy_title:<?= jsenc($gd($PU,'policy_title','Politica bilet prioritar')) ?>,policy_text:<?= jsenc($gd($PU,'policy_text','')) ?>,policy_checkbox:<?= jsenc($gd($PU,'policy_checkbox','Accept termenii si conditiile')) ?>,policy_cancel:<?= jsenc($tr('policy_cancel',$gd($PU,'policy_cancel','Anuleaza'))) ?>,policy_ok:<?= jsenc($tr('policy_ok',$gd($PU,'policy_ok','Continua'))) ?>}
};</script>
<script src="<?= e(asset('js/dispenser.js')) ?>"></script>
</body></html>
