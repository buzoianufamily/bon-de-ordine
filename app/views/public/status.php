<?php $title=setting('brand_name','Bon de ordine').' · Status coada'; require __DIR__.'/_head.php';
$logo = setting('brand_logo','');
$mmss = function($s){ $s=(int)$s; return $s>0 ? sprintf('%d:%02d', intdiv($s,60), $s%60) : ''; };
?>
<body class="statuspage">
<style>
.statuspage{background:var(--bg,#0b0d12);color:#e8eaef;min-height:100vh;margin:0}
.stwrap{max-width:980px;margin:0 auto;padding:1.4rem 1rem 3rem}
.sthead{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;margin-bottom:1.2rem}
.sthead img{max-height:40px}
.sthead h1{margin:0;font-size:1.3rem}
.stbranch{color:#9aa3b2;font-weight:600}
.stgrid{display:grid;grid-template-columns:1fr;gap:1.2rem}
@media(min-width:720px){.stgrid{grid-template-columns:1.3fr 1fr}}
.stcard{background:#15181f;border:1px solid #232733;border-radius:16px;padding:1.1rem 1.2rem}
.stcard h2{margin:0 0 .8rem;font-size:.95rem;text-transform:uppercase;letter-spacing:.06em;color:#9aa3b2}
.ctr-row{display:flex;align-items:center;gap:.8rem;padding:.6rem 0;border-bottom:1px solid #1e222c}
.ctr-row:last-child{border-bottom:none}
.ctr-code{font-weight:800;min-width:64px;color:#cbd3df}
.ctr-now{margin-left:auto;font-size:1.6rem;font-weight:800;letter-spacing:.02em}
.ctr-free{margin-left:auto;color:#6b7280;font-weight:600}
.ctr-pause{margin-left:auto;color:#d97706;font-weight:700;font-size:.85rem}
.wait-row{display:flex;align-items:center;gap:.7rem;padding:.5rem 0;border-bottom:1px solid #1e222c}
.wait-row:last-child{border-bottom:none}
.wait-tag{width:30px;height:30px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;color:#fff;flex:0 0 auto}
.wait-cnt{margin-left:auto;font-weight:800;font-size:1.2rem}
.stfoot{margin-top:1.4rem;color:#6b7280;font-size:.8rem;text-align:center}
.stdot{display:inline-block;width:8px;height:8px;border-radius:999px;background:#16a34a;margin-right:.3rem;animation:stpulse 2s infinite}
@keyframes stpulse{0%,100%{opacity:1}50%{opacity:.3}}
</style>
<div class="stwrap">
  <div class="sthead">
    <?php if($logo): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
    <h1><?= e(setting('brand_name','Bon de ordine')) ?></h1>
    <span class="stbranch"><?= e($branch['name']) ?></span>
    <span style="margin-left:auto;font-size:.8rem;color:#9aa3b2"><span class="stdot"></span>live</span>
  </div>
  <?php $notice = active_notice(); ?>
  <div id="stNotice" style="background:#3a2f12;color:#f5d98a;border:1px solid #5a4a1a;border-radius:12px;padding:.7rem 1.1rem;margin-bottom:1rem;font-weight:600;<?= $notice===''?'display:none':'' ?>"><?= $notice!=='' ? '📢 '.e($notice) : '' ?></div>
  <div class="stgrid">
    <div class="stcard">
      <h2>La ghisee acum</h2>
      <div id="ctrList">
        <?php foreach($counters as $c): ?>
          <div class="ctr-row">
            <span class="ctr-code"><?= e($c['code']) ?></span>
            <span style="color:#9aa3b2"><?= e($c['name']) ?></span>
            <?php if($c['status']==='paused'): ?>
              <span class="ctr-pause">⏸ pauza</span>
            <?php elseif(!empty($c['current_label'])): ?>
              <span class="ctr-now"><?= e($c['current_label']) ?></span>
            <?php else: ?>
              <span class="ctr-free">liber</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if(!$counters): ?><div style="color:#6b7280">Niciun ghiseu.</div><?php endif; ?>
      </div>
    </div>
    <div class="stcard">
      <h2>La rand</h2>
      <div id="waitList">
        <?php $estMin = fn($s) => ($s>0 ? '~'.max(1,(int)round($s/60)).' min' : ''); ?>
        <?php foreach($waiting as $w): if((int)$w['cnt']<=0) continue; ?>
          <div class="wait-row">
            <span class="wait-tag" style="background:<?= e($w['color']) ?>"><?= e($w['prefix']) ?></span>
            <span style="flex:1"><?= e($w['name']) ?><?php if(!empty($w['est'])): ?><br><span class="muted" style="font-size:.78rem;color:#9aa3b2"><?= e($estMin($w['est'])) ?> așteptare</span><?php endif; ?></span>
            <span class="wait-cnt"><?= (int)$w['cnt'] ?></span>
          </div>
        <?php endforeach; ?>
        <?php if(!array_filter($waiting, fn($w)=>(int)$w['cnt']>0)): ?><div id="waitEmpty" style="color:#6b7280">Nicio coada de asteptare.</div><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="stfoot">Se actualizeaza automat · <?= e(date('d.m.Y H:i')) ?></div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
(function(){
  var branch = <?= (int)$branch['id'] ?>;
  function esc(s){return String(s==null?'':s).replace(/[<>&"]/g,function(c){return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c];});}
  async function refresh(){
    if(!window.QMS) return;
    var r; try{ r=await QMS.api('api/state?est=1&branch='+branch,null,'GET'); }catch(e){ return; }
    if(!r||!r.ok) return;
    var nb=document.getElementById('stNotice');
    if(nb){ var nt=(typeof r.notice==='string')?r.notice.trim():'';
      nb.style.display=nt?'':'none';
      var want=nt?('📢 '+nt):''; if(nb.textContent!==want) nb.textContent=want; }
    var cl=document.getElementById('ctrList');
    if(cl){ cl.innerHTML=(r.counters||[]).map(function(c){
      var right = c.status==='paused' ? '<span class="ctr-pause">⏸ pauza</span>'
        : (c.current_label ? '<span class="ctr-now">'+esc(c.current_label)+'</span>' : '<span class="ctr-free">liber</span>');
      return '<div class="ctr-row"><span class="ctr-code">'+esc(c.code)+'</span><span style="color:#9aa3b2">'+esc(c.name)+'</span>'+right+'</div>';
    }).join('')||'<div style="color:#6b7280">Niciun ghiseu.</div>'; }
    var wl=document.getElementById('waitList');
    if(wl){ var rows=(r.waiting||[]).filter(function(w){return (+w.cnt)>0;});
      var em=function(s){ return (+s>0)?('~'+Math.max(1,Math.round(+s/60))+' min'):''; };
      wl.innerHTML=rows.map(function(w){ var e2=em(w.est);
        return '<div class="wait-row"><span class="wait-tag" style="background:'+esc(w.color)+'">'+esc(w.prefix)+'</span>'
          +'<span style="flex:1">'+esc(w.name)+(e2?'<br><span class="muted" style="font-size:.78rem;color:#9aa3b2">'+e2+' așteptare</span>':'')+'</span>'
          +'<span class="wait-cnt">'+(+w.cnt)+'</span></div>';
      }).join('')||'<div style="color:#6b7280">Nicio coada de asteptare.</div>'; }
  }
  setInterval(refresh, 5000);
})();
</script>
</body></html>
