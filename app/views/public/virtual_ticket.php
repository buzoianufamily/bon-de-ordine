<?php $title='Biletul meu · '.$t['label']; $lang = $lang ?? 'ro'; $L = vt_i18n($lang); $pageLang = $lang;
require __DIR__.'/_head.php'; ?>
<body><div class="vt"><div class="vt-card">
  <div class="muted"><?= e(setting('brand_name','')) ?></div>
  <div class="muted" id="vSvc"><?= e($t['service_name']) ?></div>
  <div class="vt-num" id="vNum" style="color:<?= e($t['color']) ?>"><?= e($t['label']) ?></div>
  <div class="vt-status" id="vStatus" role="status" aria-live="assertive"><?= e($L['loading']) ?></div>
  <div class="vt-pos" id="vPos" aria-live="polite"></div>
  <div class="muted" id="vEst" style="margin-top:.3rem"></div>
  <div class="muted" id="vCtr" style="margin-top:.6rem;font-weight:700"></div>
  <?php if (setting('mod_feedback','1')==='1'): ?>
  <a id="vRate" href="<?= e(url('feedback')) ?>?branch=<?= (int)$t['branch_id'] ?>&amp;lang=<?= e($lang) ?>" class="btn btn-primary" style="display:none;margin-top:1rem">⭐ <?= e($L['rate']) ?></a>
  <?php endif; ?>
  <button id="vCancel" class="btn btn-ghost" style="display:none;margin-top:1rem"><?= e($L['cancel']) ?></button>
  <p class="muted" style="font-size:.78rem;margin-top:1.4rem"><?= e($L['auto']) ?></p>
</div></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
const token = <?= jsenc($token) ?>;
const LANG = <?= jsenc($lang) ?>;
const T = <?= jsenc($L, JSON_UNESCAPED_UNICODE) ?>;
const ALERTS = {
  called: <?= jsenc(setting('alert_called','Este randul dumneavoastra! Va rugam prezentati-va la ghiseu.')) ?>,
  transfer: <?= jsenc(setting('alert_transfer','Biletul dvs. a fost transferat catre alt serviciu.')) ?>,
  delay: <?= (int) setting('alert_delay','0') ?>,
  nearTurn: <?= (int) setting('near_turn_alert','2') ?>
};
// culori fixe pe status; textul vine din traduceri (T)
const COLORS = {waiting:'#FFAA00',called:'#17E58F',serving:'#17E58F',served:'#A5A5A5',no_show:'#A5A5A5',cancelled:'#FF3A33',transferred:'#0055FF'};
const stText = s => T['st_'+s] || s;
let prevStatus=null, prevPos=null, nearAlerted=false;
async function tick(){
  const r = await QMS.api('api/virtual?token='+encodeURIComponent(token), null, 'GET');
  if(!r.ok) return;
  const t=r.ticket, color=COLORS[t.status]||'#888';
  let txt = stText(t.status);
  // pentru romana folosim mesajele personalizate din setari; pentru alte limbi, textul tradus
  if(t.status==='called' && LANG==='ro' && ALERTS.called) txt = ALERTS.called;
  else if(t.status==='transferred' && LANG==='ro' && ALERTS.transfer) txt = ALERTS.transfer;
  const s=document.getElementById('vStatus'); s.textContent=txt; s.style.background=color; s.style.color='#06210f';
  const rate=document.getElementById('vRate'); if(rate) rate.style.display = (t.status==='served') ? 'inline-flex' : 'none';
  const cb=document.getElementById('vCancel'); if(cb) cb.style.display = (t.status==='waiting'||t.status==='called') ? 'inline-flex' : 'none';
  document.getElementById('vPos').textContent = t.status==='waiting' ? T.ahead.replace('{n}', t.position) : '';
  var estEl=document.getElementById('vEst');
  if(t.status==='waiting' && t.wait_est>0){ var mn=Math.round(t.wait_est/60); estEl.textContent=T.est_label+' '+(mn<1?T.est_under1:T.est_min.replace('{m}',mn)); }
  else estEl.textContent='';
  document.getElementById('vCtr').textContent = t.counter ? T.goto.replace('{c}', t.counter) : '';
  // alerta (vibratie) o singura data la trecerea in "apelat", cu intarzierea configurata
  if(t.status==='called' && prevStatus!=='called'){
    setTimeout(function(){ if(navigator.vibrate) navigator.vibrate([200,100,200]); }, Math.max(0,ALERTS.delay)*1000);
  }
  // alerta "aproape la rand": cand pozitia scade sub prag (o singura data), vibreaza + evidentiaza
  if(t.status==='waiting' && ALERTS.nearTurn>0 && t.position>0 && t.position<=ALERTS.nearTurn && !nearAlerted){
    nearAlerted=true;
    if(navigator.vibrate) navigator.vibrate([120,80,120]);
    var pe=document.getElementById('vPos');
    if(pe){ pe.style.transition='none'; pe.style.color='#FFAA00'; pe.style.fontWeight='800';
      setTimeout(function(){ pe.style.transition='color 1.2s'; pe.style.color=''; }, 200); }
  }
  if(t.status!=='waiting') nearAlerted=false;   // reset daca iese din asteptare (ex: transfer)
  prevStatus=t.status; prevPos=t.position;
}
tick(); setInterval(tick, 3000);
document.getElementById('vCancel').addEventListener('click', async function(){
  if(!await QMS.confirm(T.cancel_confirm, {ok:T.cancel})) return;
  const r = await QMS.api('api/virtual-cancel', {token:token});
  if(r.ok){ QMS.toast('OK','ok'); tick(); } else { QMS.toast(r.error||'Eroare','error'); }
});
</script></body></html>
