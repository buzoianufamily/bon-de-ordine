<?php $title='Dashboard'; $active=''; require __DIR__.'/_header.php';
function mmss($s){ $s=(int)$s; return sprintf('%02d:%02d', intdiv($s,60), $s%60); }
$totalSvc = array_sum(array_map(fn($p)=>(int)$p['cnt'], $per_service));
$maxSvc   = max(1, max(array_map(fn($p)=>(int)$p['cnt'], $per_service ?: [['cnt'=>0]])));
$maxHour  = max(1, max($per_hour));
$peakHour = array_search($maxHour, $per_hour, true);
$onlineDev = count(array_filter($devices, fn($d)=>$d['online']));
// segmente donut (SVG)
$C = 2 * M_PI * 54; $accum = 0; $segs = [];
foreach ($per_service as $p) { $c=(int)$p['cnt']; if($c<=0) continue;
  $len = $c/$totalSvc*$C; $segs[]=['len'=>$len,'off'=>$accum,'color'=>$p['color'],'name'=>$p['name'],'cnt'=>$c]; $accum+=$len; }
?>
<div class="topbar">
  <h1>Dashboard</h1>
  <div style="display:flex;align-items:center;gap:.8rem;flex-wrap:wrap">
    <?php if(count($branches)>1): ?>
    <form method="get" action="<?= e(url('admin')) ?>" style="margin:0">
      <select name="branch" onchange="this.form.submit()" style="width:auto">
        <option value="0">Toate filialele</option>
        <?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $branch===(int)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
    <span class="muted" style="font-size:.85rem"><?= date('l, d.m.Y') ?></span>
  </div>
</div>

<?php if(!empty($onboarding)): $doneCnt=count(array_filter($onboarding,fn($s)=>$s['done'])); ?>
<div class="panel" style="margin-bottom:1.3rem;border-left:3px solid var(--accent)">
  <h4 style="display:flex;align-items:center;gap:.6rem">Primii pasi · <?= $doneCnt ?>/<?= count($onboarding) ?> finalizati
    <form method="post" action="<?= e(url('admin/dashboard/dismiss-onboarding')) ?>" style="margin-left:auto"><?= csrf_field() ?>
      <button class="btn btn-ghost" style="padding:.2rem .55rem;font-size:.75rem;text-transform:none;letter-spacing:0">Ascunde</button></form>
  </h4>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.4rem">
    <?php foreach($onboarding as $s): ?>
      <a href="<?= e($s['url']) ?>" style="display:flex;align-items:center;gap:.55rem;padding:.45rem .6rem;border-radius:9px;color:<?= $s['done']?'var(--muted)':'var(--ink)' ?>;<?= $s['done']?'text-decoration:line-through;':'' ?>">
        <span style="width:20px;height:20px;border-radius:999px;flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;
          background:<?= $s['done']?'color-mix(in srgb,var(--ok) 25%,transparent)':'var(--track)' ?>;color:<?= $s['done']?'var(--ok)':'var(--muted)' ?>"><?= $s['done']?'✓':'○' ?></span>
        <?= e($s['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="statcards">
  <div class="statcard"><div class="t">Bilete azi</div><div class="s">Total emise</div><div class="v" id="sv-today"><?= $stats['today'] ?></div></div>
  <div class="statcard"><div class="t">La rand acum</div><div class="s">In asteptare</div><div class="v" id="sv-waiting" style="color:var(--warn)"><?= $stats['waiting'] ?></div></div>
  <div class="statcard"><div class="t">In servire</div><div class="s">Chemate / la ghiseu</div><div class="v" id="sv-serving" style="color:var(--accent)"><?= $stats['serving'] ?></div></div>
  <div class="statcard"><div class="t">Servite azi</div><div class="s">Finalizate</div><div class="v" id="sv-served" style="color:var(--ok)"><?= $stats['served'] ?></div></div>
  <div class="statcard"><div class="t">Abandon</div><div class="s">neprez. + anulate</div><div class="v" id="sv-abandon" style="color:var(--danger)"><?= $stats['abandon'] ?>%</div></div>
  <div class="statcard"><div class="t">Timp mediu asteptare</div><div class="s">mm:ss · azi</div><div class="v" id="sv-avg"><?= mmss($stats['avg_wait']) ?></div></div>
  <div class="statcard"><div class="t">Varf de zi</div><div class="s">ora cu cele mai multe</div><div class="v" id="sv-peak"><?= e($stats['peak']) ?></div></div>
</div>

<?php if(!empty($branch_cmp)): ?>
<div class="panel" style="margin-bottom:1.3rem">
  <h4>Comparatie filiale (azi)</h4>
  <table><thead><tr><th>Filiala</th><th>Total</th><th>La rand</th><th>Servite</th><th>Timp mediu asteptare</th></tr></thead><tbody>
  <?php foreach($branch_cmp as $b): ?>
    <tr><td><strong><?= e($b['name']) ?></strong></td><td><?= (int)$b['total'] ?></td><td><?= (int)$b['waiting'] ?></td><td><?= (int)$b['served'] ?></td><td class="muted"><?= mmss((int)($b['avg_wait']??0)) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php endif; ?>

<div class="panel-grid">
  <div class="panel">
    <h4>Influx pe serviciu (azi)</h4>
    <?php if($totalSvc===0): ?>
      <p class="muted">Niciun bilet emis azi.</p>
    <?php else: ?>
    <div style="display:flex;gap:1.2rem;align-items:center;flex-wrap:wrap">
      <svg viewBox="0 0 120 120" width="150" height="150" style="flex:0 0 auto">
        <circle cx="60" cy="60" r="54" fill="none" stroke="var(--track)" stroke-width="12"/>
        <g transform="rotate(-90 60 60)">
        <?php foreach($segs as $sgm): ?>
          <circle cx="60" cy="60" r="54" fill="none" stroke="<?= e($sgm['color']) ?>" stroke-width="12"
                  stroke-dasharray="<?= round($sgm['len'],2) ?> <?= round($C-$sgm['len'],2) ?>"
                  stroke-dashoffset="<?= round(-$sgm['off'],2) ?>"/>
        <?php endforeach; ?>
        </g>
        <text x="60" y="56" text-anchor="middle" fill="var(--ink)" font-size="20" font-weight="800"><?= $totalSvc ?></text>
        <text x="60" y="72" text-anchor="middle" fill="var(--muted)" font-size="9">bilete</text>
      </svg>
      <div style="flex:1;min-width:160px">
        <?php foreach($per_service as $p): if((int)$p['cnt']===0) continue; ?>
          <div style="display:flex;align-items:center;gap:.55rem;margin:.35rem 0">
            <span style="width:11px;height:11px;border-radius:3px;background:<?= e($p['color']) ?>;flex:0 0 auto"></span>
            <span style="flex:1"><?= e($p['name']) ?></span>
            <strong><?= (int)$p['cnt'] ?></strong>
            <span class="muted" style="width:42px;text-align:right"><?= round($p['cnt']/$totalSvc*100) ?>%</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h4>Timp / aflux pe ora (azi) <?php if(array_sum($per_hour)>0): ?><span class="muted" style="font-weight:600;text-transform:none;letter-spacing:0">varf <?= sprintf('%02d:00',$peakHour) ?> · <?= $maxHour ?></span><?php endif; ?></h4>
    <div style="display:flex;align-items:flex-end;gap:3px;height:170px">
      <?php for($h=0;$h<24;$h++): $val=$per_hour[$h]; $pct=(int)($val/$maxHour*100); ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end" title="<?= sprintf('%02d:00',$h) ?> — <?= $val ?> bilete">
          <span style="font-size:.6rem;color:var(--muted);margin-bottom:2px"><?= $val?:'' ?></span>
          <div style="width:100%;background:var(--accent);border-radius:4px 4px 0 0;height:<?= max($val?6:0,$pct) ?>%;opacity:<?= $val?1:.12 ?>"></div>
          <span style="font-size:.58rem;color:var(--muted);margin-top:3px"><?= $h ?></span>
        </div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<div class="panel-grid">
  <div class="panel dvbox">
    <h4>Bilete pe serviciu (azi) <span class="dvtoggle"><button type="button" class="on" data-dv="chart">📊</button><button type="button" data-dv="table">▦</button></span></h4>
    <?php if(!$per_service): ?><p class="muted">Niciun serviciu.</p><?php endif; ?>
    <div class="dv-table dv-hidden">
      <table><thead><tr><th>Serviciu</th><th>Bilete</th><th>%</th></tr></thead><tbody>
      <?php foreach($per_service as $p): ?><tr>
        <td><span class="tag" style="background:<?= e($p['color']) ?>;width:18px;height:18px;font-size:.65rem"><?= e(mb_substr($p['name'],0,1)) ?></span> <?= e($p['name']) ?></td>
        <td><?= (int)$p['cnt'] ?></td><td><?= $totalSvc?round($p['cnt']/$totalSvc*100):0 ?>%</td></tr><?php endforeach; ?>
      </tbody></table>
    </div>
    <div class="dv-chart">
    <?php foreach($per_service as $p): ?>
      <div style="display:flex;align-items:center;gap:.7rem;margin:.55rem 0">
        <span class="tag" style="background:<?= e($p['color']) ?>"><?= e(mb_substr($p['name'],0,1)) ?></span>
        <span style="flex:1"><?= e($p['name']) ?></span>
        <div style="flex:2;background:var(--track);border-radius:6px;height:10px;overflow:hidden">
          <div style="height:100%;width:<?= (int)($p['cnt']/$maxSvc*100) ?>%;background:<?= e($p['color']) ?>"></div></div>
        <strong style="width:34px;text-align:right"><?= (int)$p['cnt'] ?></strong>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <h4>Dispozitive <span class="live" id="devActive"><?= $onlineDev ?>/<?= count($devices) ?> online</span></h4>
    <table><tbody id="devRows">
    <?php foreach($devices as $d): ?>
      <tr><td style="width:1%">
        <span class="pill" style="background:<?= $d['online']?'color-mix(in srgb,var(--ok) 22%,transparent)':'var(--track)' ?>;color:<?= $d['online']?'var(--ok)':'var(--muted)' ?>">
          <?= $d['online']?'● online':'○ offline' ?></span>
        </td><td><strong><?= e($d['name']) ?></strong><br><span class="muted" style="font-size:.8rem"><?= e($d['type']) ?></span></td>
        <td style="text-align:right"><code><?= e($d['connection_key']) ?></code></td></tr>
    <?php endforeach; ?>
    <?php if(!$devices): ?><tr><td class="muted">Niciun dispozitiv.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
  <div class="panel">
    <?php $stMeta=['available'=>['Disponibil','#16a34a'],'busy'=>['Ocupat','#dc2626'],'paused'=>['Pauza','#d97706'],'offline'=>['Indisponibil','#6b7280']];
      $onCnt=count(array_filter($operators??[], fn($o)=>$o['online'])); ?>
    <h4>Operatori <span class="live" id="opActive"><?= $onCnt ?> activi</span></h4>
    <table><tbody id="opRows">
    <?php foreach(($operators??[]) as $o): $eff=$o['online']?$o['work_status']:'offline'; $m=$stMeta[$eff]??$stMeta['offline']; ?>
      <tr>
        <td style="width:1%"><span class="pill" style="background:color-mix(in srgb,<?= $m[1] ?> 22%,transparent);color:<?= $m[1] ?>;white-space:nowrap"><?= e($m[0]) ?></span></td>
        <td><strong><?= e($o['name']) ?></strong><br><span class="muted" style="font-size:.8rem"><?= e($o['role']) ?></span></td>
        <td style="text-align:right" class="muted"><?= $o['last_seen']?e(date('H:i',strtotime($o['last_seen']))):'—' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if(empty($operators)): ?><tr><td class="muted">Niciun operator.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<script>
/* dashboard live — actualizeaza statcards, operatori si dispozitive periodic */
window.addEventListener('load', function(){
  if(!window.QMS) return;
  var stMeta={available:['Disponibil','#16a34a'],busy:['Ocupat','#dc2626'],paused:['Pauza','#d97706'],offline:['Indisponibil','#6b7280']};
  function mmss(s){s=Math.max(0,s|0);return ('0'+((s/60|0)%100)).slice(-2)+':'+('0'+(s%60)).slice(-2);}
  function esc(s){return String(s==null?'':s).replace(/[<>&"]/g,function(c){return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c];});}
  function set(id,v){var el=document.getElementById(id); if(el)el.textContent=v;}
  var dashBranch = <?= (int)$branch ?>;
  async function refresh(){
    var r; try{ r=await QMS.api('admin/dashboard?format=json&branch='+dashBranch,null,'GET'); }catch(e){ return; }
    if(!r||!r.ok)return;
    set('sv-today',r.stats.today); set('sv-waiting',r.stats.waiting); set('sv-serving',r.stats.serving);
    set('sv-served',r.stats.served); set('sv-avg',mmss(r.stats.avg_wait));
    set('sv-abandon',(r.stats.abandon||0)+'%'); set('sv-peak',r.stats.peak||'—');
    var op=document.getElementById('opRows');
    if(op){ op.innerHTML=(r.operators||[]).map(function(o){var m=stMeta[o.status]||stMeta.offline;
      return '<tr><td style="width:1%"><span class="pill" style="background:color-mix(in srgb,'+m[1]+' 22%,transparent);color:'+m[1]+';white-space:nowrap">'+esc(m[0])+'</span></td><td><strong>'+esc(o.name)+'</strong><br><span class="muted" style="font-size:.8rem">'+esc(o.role)+'</span></td><td style="text-align:right" class="muted">'+esc(o.last_seen)+'</td></tr>';
    }).join('')||'<tr><td class="muted">Niciun operator.</td></tr>';
      set('opActive',(r.operators||[]).filter(function(o){return o.online;}).length+' activi'); }
    var dv=document.getElementById('devRows');
    if(dv){ dv.innerHTML=(r.devices||[]).map(function(d){
      return '<tr><td style="width:1%"><span class="pill" style="background:'+(d.online?'color-mix(in srgb,var(--ok) 22%,transparent)':'var(--track)')+';color:'+(d.online?'var(--ok)':'var(--muted)')+'">'+(d.online?'● online':'○ offline')+'</span></td><td><strong>'+esc(d.name)+'</strong><br><span class="muted" style="font-size:.8rem">'+esc(d.type)+'</span></td><td style="text-align:right"><code>'+esc(d.key)+'</code></td></tr>';
    }).join('')||'<tr><td class="muted">Niciun dispozitiv.</td></tr>';
      set('devActive',(r.devices||[]).filter(function(d){return d.online;}).length+'/'+(r.devices||[]).length+' online'); }
  }
  setInterval(refresh, 7000);
});
</script>
<?php require __DIR__.'/_footer.php'; ?>
