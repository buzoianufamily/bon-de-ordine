<?php $title='Biletul meu · '.$t['label']; require __DIR__.'/_head.php'; ?>
<body><div class="vt"><div class="vt-card">
  <div class="muted"><?= e(setting('brand_name','')) ?></div>
  <div class="muted" id="vSvc"><?= e($t['service_name']) ?></div>
  <div class="vt-num" id="vNum" style="color:<?= e($t['color']) ?>"><?= e($t['label']) ?></div>
  <div class="vt-status" id="vStatus">Se incarca…</div>
  <div class="vt-pos" id="vPos"></div>
  <div class="muted" id="vCtr" style="margin-top:.6rem;font-weight:700"></div>
  <p class="muted" style="font-size:.78rem;margin-top:1.4rem">Pagina se actualizeaza automat. Pastrati-o deschisa.</p>
</div></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
const token = <?= json_encode($token) ?>;
const labels = {waiting:['La rand','#FFAA00'],called:['Este randul dvs!','#17E58F'],serving:['In curs de servire','#17E58F'],
  served:['Finalizat','#A5A5A5'],no_show:['Neprezentat','#A5A5A5'],cancelled:['Anulat','#FF3A33'],transferred:['Transferat','#0055FF']};
async function tick(){
  const r = await QMS.api('api/virtual?token='+encodeURIComponent(token), null, 'GET');
  if(!r.ok) return;
  const t=r.ticket, st=labels[t.status]||['—','#888'];
  const s=document.getElementById('vStatus'); s.textContent=st[0]; s.style.background=st[1]; s.style.color='#06210f';
  document.getElementById('vPos').textContent = t.status==='waiting' ? ('Sunt '+t.position+' persoane inaintea dvs.') : '';
  document.getElementById('vCtr').textContent = t.counter ? ('Mergeti la: '+t.counter) : '';
  if(t.status==='called' && navigator.vibrate) navigator.vibrate([200,100,200]);
}
tick(); setInterval(tick, 3000);
</script></body></html>
