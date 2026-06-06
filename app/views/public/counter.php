<?php $title='Ghiseu '.$counter['code']; require __DIR__.'/_head.php';
$services = all('SELECT id,prefix,name,color FROM services WHERE branch_id=? AND status="active" ORDER BY sort_order',[$counter['branch_id']]); ?>
<body><div class="counter-wrap">
  <div class="topbar">
    <div><div class="muted">Operator: <?= e($u['name']) ?></div>
      <h1 style="margin:0"><?= e($counter['name']) ?> <span class="muted" style="font-weight:600">(<?= e($counter['code']) ?>)</span></h1></div>
    <div><a class="btn btn-ghost" href="<?= e(url('counter')) ?>">Schimba ghiseu</a> <a class="btn btn-ghost" href="<?= e(url('logout')) ?>">Iesire</a></div>
  </div>
  <div class="row">
    <div class="card pad" style="flex:1.3">
      <div class="muted">Bilet in lucru</div>
      <div class="current-ticket" id="curTicket">—</div>
      <div id="curSvc" class="muted" style="font-weight:700;margin-bottom:1rem">Niciun bilet in lucru</div>
      <div id="curForm"></div>
      <button class="callnext" id="btnCall">CHEAMA URMATORUL</button>
      <div class="qbtns">
        <button class="btn" id="btnRecall">🔁 Recheama</button>
        <button class="btn" id="btnServing">▶️ In servire</button>
        <button class="btn btn-primary" id="btnFinish">✔️ Finalizat</button>
        <button class="btn btn-danger" id="btnNoShow">✖️ Neprezentat</button>
      </div>
      <div class="field" style="margin-top:1rem">
        <label>Transfera catre serviciu</label>
        <select onchange="if(this.value){qmsTransfer(+this.value);this.value=''}">
          <option value="">— alege serviciu —</option>
          <?php foreach($services as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['prefix'].' · '.$s['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="card pad" style="flex:1">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <strong>La rand</strong><span class="pill" style="background:#eef2ff;color:#3730a3"><span id="waitCount">0</span> bilete</span>
      </div>
      <div class="qlist" id="qList" style="margin-top:.6rem"></div>
    </div>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>window.COUNTER={counterId:<?= (int)$counter['id'] ?>,accent:<?= json_encode(setting('accent_color','#2563eb')) ?>};</script>
<script src="<?= e(asset('js/counter.js')) ?>"></script>
</body></html>
