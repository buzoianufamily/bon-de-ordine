<?php $title='Ghiseu '.$counter['code'].' · Afisaj'; $kiosk=true; require __DIR__.'/_head.php';
/* texte: intai per ghiseu (editare ghiseu), apoi implicit din Setari -> Afisaj, apoi valoarea de baza */
$cdIdle    = trim((string) ($counter['cd_hint_idle'] ?? '')) ?: (trim((string) setting('cd_hint_idle', '')) ?: 'Asteptam urmatorul bon…');
$cdServing = trim((string) ($counter['cd_hint_serving'] ?? '')) ?: (trim((string) setting('cd_hint_serving', '')) ?: 'Va rugam prezentati-va la ghiseu'); ?>
<body style="margin:0;background:#0b0d12;color:#fff;overflow:hidden">
<div style="position:fixed;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:1vh">
  <div style="font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#9aa3b2;font-size:3.2vh"><?= e(setting('brand_name','')) ?></div>
  <div id="cdCode" style="font-family:var(--display);font-weight:800;font-size:14vh;line-height:1;color:var(--accent)"><?= e($counter['code']) ?></div>
  <div style="font-weight:700;color:#cdd3dc;font-size:3.4vh"><?= e($counter['name']) ?></div>
  <div id="cdNum" style="font-family:var(--display);font-weight:800;font-size:34vh;line-height:1;margin-top:1vh">—</div>
  <div id="cdHint" class="muted" style="font-size:2.6vh;color:#7d8696"><?= e($cdIdle) ?></div>
</div>
<?php $notice = active_notice(); ?>
<div id="cdNotice" style="position:fixed;left:0;right:0;bottom:0;background:#3a2f12;color:#f5d98a;padding:1.4vh 2vw;text-align:center;font-size:2.4vh;font-weight:700;<?= $notice===''?'display:none':'' ?>"><?= $notice!=='' ? '📢 '.e($notice) : '' ?></div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
(function(){
  var branch = <?= (int)$counter['branch_id'] ?>, counterId = <?= (int)$counter['id'] ?>;
  var TXT_IDLE = <?= json_encode($cdIdle, JSON_UNESCAPED_UNICODE) ?>, TXT_SERVING = <?= json_encode($cdServing, JSON_UNESCAPED_UNICODE) ?>;
  var elNum = document.getElementById('cdNum'), elHint = document.getElementById('cdHint');
  var lastLabel = null, firstPaint = true;
  function ding(){ try{ var ac=new(window.AudioContext||window.webkitAudioContext)(); var o=ac.createOscillator(),g=ac.createGain();
    o.connect(g);g.connect(ac.destination);o.frequency.value=880;g.gain.setValueAtTime(.0001,ac.currentTime);
    g.gain.exponentialRampToValueAtTime(.25,ac.currentTime+.02);g.gain.exponentialRampToValueAtTime(.0001,ac.currentTime+.5);o.start();o.stop(ac.currentTime+.5);}catch(e){} }
  function flash(){ document.body.style.transition='none'; document.body.style.background='var(--accent)';
    setTimeout(function(){ document.body.style.transition='background 1s'; document.body.style.background='#0b0d12'; },140); }
  function render(state){
    // anuntul general — apare/dispare live
    var nb = document.getElementById('cdNotice');
    if(nb){ var nt = (typeof state.notice==='string') ? state.notice.trim() : '';
      nb.style.display = nt ? '' : 'none';
      var want = nt ? ('📢 ' + nt) : '';
      if(nb.textContent !== want) nb.textContent = want; }
    var first = firstPaint; firstPaint = false;   // nu sunam la prima afisare (bonul deja curent la deschidere)
    var c = (state.counters||[]).find(function(x){ return +x.id === counterId; });
    if(c && c.status === 'paused'){
      elNum.textContent = '⏸';
      elHint.textContent = c.pause_note || 'Ghiseul este in pauza';
      lastLabel = null;
      return;
    }
    var label = c && c.current_label ? c.current_label : null;
    elNum.textContent = label || '—';
    elHint.textContent = label ? TXT_SERVING : TXT_IDLE;
    if(label && label !== lastLabel && !first){ flash(); ding(); }   // suna la orice bon nou (inclusiv din inactiv)
    lastLabel = label;
  }
  function startSSE(){ try{ var es=new EventSource(QMS.base()+'/api/sse?branch='+branch);
    es.onmessage=function(e){ try{ render(JSON.parse(e.data)); }catch(_){ } };
    es.onerror=function(){ es.close(); startPolling(); }; }catch(e){ startPolling(); } }
  var pt=null; function startPolling(){ if(pt)return;
    var busy=false;
    var tick=function(){ if(busy) return; busy=true; QMS.api('api/state?branch='+branch,null,'GET').then(function(s){ if(s.ok) render(s); }).finally(function(){ busy=false; }); };
    tick(); pt=setInterval(tick, 2000); }
  if(window.EventSource) startSSE(); else startPolling();
})();
</script>
</body></html>
