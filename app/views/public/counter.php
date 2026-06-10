<?php $title='Ghiseu '.$counter['code']; require __DIR__.'/_head.php';
$services = all('SELECT id,prefix,name,color FROM services WHERE branch_id=? AND status="active" ORDER BY sort_order',[$counter['branch_id']]); ?>
<body><div class="counter-wrap">
  <div class="topbar">
    <div><div class="muted">Operator: <?= e($u['name']) ?></div>
      <h1 style="margin:0"><?= e($counter['name']) ?> <span class="muted" style="font-weight:600">(<?= e($counter['code']) ?>)</span></h1></div>
    <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
      <?php $myStatus = (string)(val('SELECT work_status FROM users WHERE id=?', [$u['id']]) ?: 'offline');
        $stOpts=['available'=>'🟢 Disponibil','busy'=>'🔴 Ocupat','paused'=>'🟡 Pauza','offline'=>'⚪ Indisponibil']; ?>
      <select id="opStatus" onchange="qmsStatus(this.value)" style="width:auto">
        <?php foreach($stOpts as $k=>$lab): ?><option value="<?= $k ?>" <?= $myStatus===$k?'selected':'' ?>><?= $lab ?></option><?php endforeach; ?>
      </select>
      <a class="btn btn-ghost" href="<?= e(url('counter')) ?>">Schimba ghiseu</a> <a class="btn btn-ghost" href="<?= e(url('logout')) ?>">Iesire</a></div>
  </div>
  <div class="row">
    <div class="card pad" style="flex:1.1">
      <div class="muted">Bilet in lucru</div>
      <div class="current-ticket" id="curTicket">—</div>
      <div id="curSvc" class="muted" style="font-weight:700;margin-bottom:1rem">Niciun bilet in lucru</div>
      <div id="curForm"></div>
      <button class="callnext" id="btnCall">CHEAMA URMATORUL</button>
    </div>
    <div class="card pad" style="flex:1.2">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <strong>Bilete</strong><span class="pill" style="background:#eef2ff;color:#3730a3"><span id="waitCount">0</span> la rand</span>
      </div>
      <div class="muted" style="font-size:.8rem;margin-top:.3rem">Selecteaza un bilet (un singur bilet) ca sa apara optiunile: chemare / recheamare / in servire / finalizat / neprezentat.</div>
      <div id="selBar" class="selbar" style="display:none"></div>
      <div class="qlist" id="qList" style="margin-top:.6rem"></div>
    </div>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php $notify = (int) (val('SELECT notify_browser FROM users WHERE id=?', [$u['id']]) ?? 0); ?>
<script>window.COUNTER={
  counterId:<?= (int)$counter['id'] ?>,
  counterName:<?= json_encode($counter['name'] ?: ('ghiseul '.$counter['code'])) ?>,
  accent:<?= json_encode(setting('accent_color','#2563eb')) ?>,
  notify:<?= $notify?'true':'false' ?>,
  voiceOn:<?= setting('counter_voice','0')==='1'?'true':'false' ?>,
  voice:<?= json_encode(setting('display_voice','ro-RO')) ?>,
  sayNumber:<?= setting('display_say_number','1')==='1'?'true':'false' ?>,
  sayCounter:<?= setting('display_say_counter','1')==='1'?'true':'false' ?>,
  repeat:<?= (int)setting('display_repeat','2') ?>,
  myStatus:<?= json_encode($myStatus) ?>,
  services:<?= json_encode(array_map(fn($s)=>['id'=>(int)$s['id'],'prefix'=>$s['prefix'],'name'=>$s['name']], $services), JSON_UNESCAPED_UNICODE) ?>,
  counters:<?php $otherCounters = all('SELECT id, code, name FROM counters WHERE branch_id=? AND id<>? ORDER BY code', [$counter['branch_id'], $counter['id']]);
    echo json_encode(array_map(fn($c)=>['id'=>(int)$c['id'],'code'=>$c['code'],'name'=>$c['name']], $otherCounters), JSON_UNESCAPED_UNICODE); ?>
};</script>
<script src="<?= e(asset('js/counter.js')) ?>?v=8"></script>
</body></html>
