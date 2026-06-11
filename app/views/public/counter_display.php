<?php $title='Ghiseu '.$counter['code'].' · Afisaj'; require __DIR__.'/_head.php'; ?>
<body style="margin:0;background:#0b0d12;color:#fff;overflow:hidden">
<div style="position:fixed;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:1vh">
  <div style="font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#9aa3b2;font-size:3.2vh"><?= e(setting('brand_name','')) ?></div>
  <div id="cdCode" style="font-family:var(--display);font-weight:800;font-size:14vh;line-height:1;color:var(--accent)"><?= e($counter['code']) ?></div>
  <div style="font-weight:700;color:#cdd3dc;font-size:3.4vh"><?= e($counter['name']) ?></div>
  <div id="cdNum" style="font-family:var(--display);font-weight:800;font-size:34vh;line-height:1;margin-top:1vh">—</div>
  <div id="cdHint" class="muted" style="font-size:2.6vh;color:#7d8696">Asteptam urmatorul bon…</div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
(function(){
  var branch = <?= (int)$counter['branch_id'] ?>, counterId = <?= (int)$counter['id'] ?>;
  var elNum = document.getElementById('cdNum'), elHint = document.getElementById('cdHint');
  var lastLabel = null;
  function ding(){ try{ var ac=new(window.AudioContext||window.webkitAudioContext)(); var o=ac.createOscillator(),g=ac.createGain();
    o.connect(g);g.connect(ac.destination);o.frequency.value=880;g.gain.setValueAtTime(.0001,ac.currentTime);
    g.gain.exponentialRampToValueAtTime(.25,ac.currentTime+.02);g.gain.exponentialRampToValueAtTime(.0001,ac.currentTime+.5);o.start();o.stop(ac.currentTime+.5);}catch(e){} }
  function flash(){ document.body.style.transition='none'; document.body.style.background='var(--accent)';
    setTimeout(function(){ document.body.style.transition='background 1s'; document.body.style.background='#0b0d12'; },140); }
  function render(state){
    var c = (state.counters||[]).find(function(x){ return +x.id === counterId; });
    var label = c && c.current_label ? c.current_label : null;
    elNum.textContent = label || '—';
    elHint.textContent = label ? 'Va rugam prezentati-va la ghiseu' : 'Asteptam urmatorul bon…';
    if(label && label !== lastLabel && lastLabel !== undefined){ if(lastLabel !== null){ flash(); ding(); } }
    lastLabel = label;
  }
  function startSSE(){ try{ var es=new EventSource(QMS.base()+'/api/sse?branch='+branch);
    es.onmessage=function(e){ try{ render(JSON.parse(e.data)); }catch(_){ } };
    es.onerror=function(){ es.close(); startPolling(); }; }catch(e){ startPolling(); } }
  var pt=null; function startPolling(){ if(pt)return;
    var tick=function(){ QMS.api('api/state?branch='+branch,null,'GET').then(function(s){ if(s.ok) render(s); }); };
    tick(); pt=setInterval(tick, 2000); }
  if(window.EventSource) startSSE(); else startPolling();
})();
</script>
</body></html>
