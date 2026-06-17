<?php $title='Ghiseu '.$counter['code']; require __DIR__.'/_head.php';
$services = all('SELECT id,prefix,name,color FROM services WHERE branch_id=? AND status="active" ORDER BY sort_order',[$counter['branch_id']]); ?>
<body><div class="counter-wrap">
  <div class="topbar">
    <div><div class="muted">Operator: <?= e($u['name']) ?> <span id="myStats" class="pill" style="background:#eef2ff;color:#3730a3;display:none;margin-left:.4rem"></span></div>
      <h1 style="margin:0"><?= e($counter['name']) ?> <span class="muted" style="font-weight:600">(<?= e($counter['code']) ?>)</span></h1></div>
    <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
      <?php $myStatus = (string)(val('SELECT work_status FROM users WHERE id=?', [$u['id']]) ?: 'offline');
        $stOpts=['available'=>'🟢 Disponibil','busy'=>'🔴 Ocupat','paused'=>'🟡 Pauza','offline'=>'⚪ Indisponibil']; ?>
      <select id="opStatus" onchange="qmsStatus(this.value)" style="width:auto">
        <?php foreach($stOpts as $k=>$lab): ?><option value="<?= $k ?>" <?= $myStatus===$k?'selected':'' ?>><?= $lab ?></option><?php endforeach; ?>
      </select>
      <?php if($counter['status']==='paused'): ?>
        <button class="btn" id="btnResumeC" title="<?= e($counter['pause_note'] ?? '') ?>">▶ Reia ghiseul</button>
      <?php else: ?>
        <button class="btn" id="btnPauseC">⏸ Pauza ghiseu</button>
      <?php endif; ?>
      <button class="btn btn-ghost" id="btnPinSwitch" type="button">👤 Schimba operator</button>
      <a class="btn btn-ghost" href="<?= e(url('counter')) ?>">Schimba ghiseu</a> <a class="btn btn-ghost" href="<?= e(url('account')) ?>">Cont</a> <a class="btn btn-ghost" href="<?= e(url('logout')) ?>">Iesire</a></div>
  </div>
  <?php if(($notice = active_notice()) !== ''): ?>
  <div class="card pad" style="background:#fef3c7;color:#92400e;font-weight:700;margin-bottom:1rem">📢 <?= e($notice) ?></div>
  <?php endif; ?>
  <div class="row">
    <div class="card pad" style="flex:1.1">
      <div class="muted">Bilet in lucru</div>
      <div class="current-ticket" id="curTicket">—</div>
      <div id="curSvc" class="muted" style="font-weight:700;margin-bottom:1rem">Niciun bilet in lucru</div>
      <div id="curForm"></div>
      <button class="callnext" id="btnCall">CHEAMA URMATORUL</button>
      <?php if(count($services)>1): ?>
      <div class="field" style="margin-top:.7rem">
        <label>Cheama urmatorul dintr-un serviciu anume</label>
        <select id="callSvc"><option value="">— alege serviciu —</option>
          <?php foreach($services as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['prefix'].' · '.$s['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="muted" style="font-size:.76rem;margin-top:.6rem">Scurtaturi: <b>Space/Enter</b> = cheama urmatorul · <b>↑/↓</b> = navighezi · <b>R</b> recheama · <b>S</b> in servire · <b>F</b> finalizat · <b>N</b> neprezentat · <b>Esc</b> deselecteaza</div>
    </div>
    <div class="card pad" style="flex:1.2">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <strong>Bilete</strong><span class="pill" style="background:#eef2ff;color:#3730a3"><span id="waitCount">0</span> la rand</span>
      </div>
      <div class="muted" style="font-size:.8rem;margin-top:.3rem">Selecteaza un bilet (un singur bilet) ca sa apara optiunile: chemare / recheamare / in servire / finalizat / neprezentat.</div>
      <div id="selBar" class="selbar" style="display:none"></div>
      <div class="qlist" id="qList" style="margin-top:.6rem"></div>
      <div id="nsBox" style="display:none;margin-top:1rem;border-top:1px solid var(--line,#e6e8ec);padding-top:.7rem">
        <div class="muted" style="font-size:.8rem;margin-bottom:.4rem">Neprezentați azi — repune-i la rând dacă au ajuns:</div>
        <div id="nsList" style="display:flex;flex-wrap:wrap;gap:.4rem"></div>
      </div>
    </div>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php $notify = (int) (val('SELECT notify_browser FROM users WHERE id=?', [$u['id']]) ?? 0); ?>
<script>window.COUNTER={
  counterId:<?= (int)$counter['id'] ?>,
  counterName:<?= jsenc($counter['name'] ?: ('ghiseul '.$counter['code'])) ?>,
  accent:<?= jsenc(setting('accent_color','#2563eb')) ?>,
  notify:<?= $notify?'true':'false' ?>,
  voiceOn:<?= setting('counter_voice','0')==='1'?'true':'false' ?>,
  voice:<?= jsenc(setting('display_voice','ro-RO')) ?>,
  sayNumber:<?= setting('display_say_number','1')==='1'?'true':'false' ?>,
  sayCounter:<?= setting('display_say_counter','1')==='1'?'true':'false' ?>,
  repeat:<?= (int)setting('display_repeat','2') ?>,
  myStatus:<?= jsenc($myStatus) ?>,
  services:<?= jsenc(array_map(fn($s)=>['id'=>(int)$s['id'],'prefix'=>$s['prefix'],'name'=>$s['name']], $services), JSON_UNESCAPED_UNICODE) ?>,
  counters:<?php $otherCounters = all('SELECT id, code, name FROM counters WHERE branch_id=? AND id<>? ORDER BY code', [$counter['branch_id'], $counter['id']]);
    echo jsenc(array_map(fn($c)=>['id'=>(int)$c['id'],'code'=>$c['code'],'name'=>$c['name']], $otherCounters), JSON_UNESCAPED_UNICODE); ?>
};</script>
<script>
/* schimbare rapida operator prin PIN */
(function(){ var b=document.getElementById('btnPinSwitch'); if(!b||!window.QMS) return;
  b.addEventListener('click', async function(){
    var pin = await QMS.prompt('PIN operator:', {placeholder:'introdu PIN-ul', ok:'Schimbă'});
    if(pin===null || !pin.trim()) return;
    var r = await QMS.api('api/pin-switch', {pin:pin.trim()});
    if(r && r.ok){ QMS.toast('Operator: '+r.name, 'ok'); setTimeout(function(){ location.reload(); }, 600); }
    else { QMS.toast((r&&r.error)||'PIN invalid', 'error'); }
  });
})();
</script>
<script src="<?= e(asset('js/counter.js')) ?>"></script>
</body></html>
