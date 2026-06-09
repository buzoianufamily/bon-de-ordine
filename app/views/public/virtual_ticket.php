<?php $title='Biletul meu · '.$t['label']; require __DIR__.'/_head.php'; ?>
<body><div class="vt"><div class="vt-card">
  <div class="muted"><?= e(setting('brand_name','')) ?></div>
  <div class="muted" id="vSvc"><?= e($t['service_name']) ?></div>
  <div class="vt-num" id="vNum" style="color:<?= e($t['color']) ?>"><?= e($t['label']) ?></div>
  <div class="vt-status" id="vStatus">Se incarca…</div>
  <div class="vt-pos" id="vPos"></div>
  <div class="muted" id="vEst" style="margin-top:.3rem"></div>
  <div class="muted" id="vCtr" style="margin-top:.6rem;font-weight:700"></div>
  <p class="muted" style="font-size:.78rem;margin-top:1.4rem">Pagina se actualizeaza automat. Pastrati-o deschisa.</p>
</div></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
const token = <?= json_encode($token) ?>;
const ALERTS = {
  called: <?= json_encode(setting('alert_called','Este randul dumneavoastra! Va rugam prezentati-va la ghiseu.')) ?>,
  transfer: <?= json_encode(setting('alert_transfer','Biletul dvs. a fost transferat catre alt serviciu.')) ?>,
  delay: <?= (int) setting('alert_delay','0') ?>
};
const labels = {waiting:['La rand','#FFAA00'],called:['Este randul dvs!','#17E58F'],serving:['In curs de servire','#17E58F'],
  served:['Finalizat','#A5A5A5'],no_show:['Neprezentat','#A5A5A5'],cancelled:['Anulat','#FF3A33'],transferred:['Transferat','#0055FF']};
let prevStatus=null;
async function tick(){
  const r = await QMS.api('api/virtual?token='+encodeURIComponent(token), null, 'GET');
  if(!r.ok) return;
  const t=r.ticket, st=labels[t.status]||['—','#888'];
  let txt = st[0];
  if(t.status==='called' && ALERTS.called) txt = ALERTS.called;
  else if(t.status==='transferred' && ALERTS.transfer) txt = ALERTS.transfer;
  const s=document.getElementById('vStatus'); s.textContent=txt; s.style.background=st[1]; s.style.color='#06210f';
  document.getElementById('vPos').textContent = t.status==='waiting' ? ('Sunt '+t.position+' persoane inaintea dvs.') : '';
  var estEl=document.getElementById('vEst');
  if(t.status==='waiting' && t.wait_est>0){ var mn=Math.round(t.wait_est/60); estEl.textContent='Timp estimat: '+(mn<1?'sub 1 minut':('~ '+mn+' min')); }
  else estEl.textContent='';
  document.getElementById('vCtr').textContent = t.counter ? ('Mergeti la: '+t.counter) : '';
  // alerta (vibratie) o singura data la trecerea in "apelat", cu intarzierea configurata
  if(t.status==='called' && prevStatus!=='called'){
    setTimeout(function(){ if(navigator.vibrate) navigator.vibrate([200,100,200]); }, Math.max(0,ALERTS.delay)*1000);
  }
  prevStatus=t.status;
}
tick(); setInterval(tick, 3000);
</script></body></html>
