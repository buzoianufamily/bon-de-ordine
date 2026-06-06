<?php $title='Dashboard'; $active=''; require __DIR__.'/_header.php';
function mmss($s){ $s=(int)$s; return sprintf('%02d:%02d', intdiv($s,60), $s%60); }
$totalSvc = array_sum(array_map(fn($p)=>(int)$p['cnt'], $per_service));
$maxSvc   = max(1, max(array_map(fn($p)=>(int)$p['cnt'], $per_service ?: [['cnt'=>0]])));
$maxHour  = max(1, max($per_hour));
$peakHour = array_search($maxHour, $per_hour, true);
// segmente donut (SVG)
$C = 2 * M_PI * 54; $accum = 0; $segs = [];
foreach ($per_service as $p) { $c=(int)$p['cnt']; if($c<=0) continue;
  $len = $c/$totalSvc*$C; $segs[]=['len'=>$len,'off'=>$accum,'color'=>$p['color'],'name'=>$p['name'],'cnt'=>$c]; $accum+=$len; }
?>
<div class="topbar"><h1>Dashboard</h1><span class="muted"><?= date('l, d.m.Y') ?></span></div>

<div class="kpis">
  <div class="card pad kpi"><div class="n"><?= $stats['today'] ?></div><div class="l">Bilete azi</div></div>
  <div class="card pad kpi"><div class="n" style="color:var(--warn)"><?= $stats['waiting'] ?></div><div class="l">La rand acum</div></div>
  <div class="card pad kpi"><div class="n" style="color:var(--accent)"><?= $stats['serving'] ?></div><div class="l">In servire</div></div>
  <div class="card pad kpi"><div class="n" style="color:var(--ok)"><?= $stats['served'] ?></div><div class="l">Servite azi</div></div>
  <div class="card pad kpi"><div class="n" style="color:#94a3b8"><?= $stats['no_show'] ?></div><div class="l">Neprezentate azi</div></div>
  <div class="card pad kpi"><div class="n"><?= mmss($stats['avg_wait']) ?></div><div class="l">Timp mediu asteptare</div></div>
</div>

<div class="row" style="align-items:stretch">
  <div class="card pad" style="flex:1">
    <h3 style="margin-top:0">Distributie pe serviciu (azi)</h3>
    <?php if($totalSvc===0): ?>
      <p class="muted">Niciun bilet emis azi.</p>
    <?php else: ?>
    <div style="display:flex;gap:1.2rem;align-items:center;flex-wrap:wrap">
      <svg viewBox="0 0 120 120" width="150" height="150" style="flex:0 0 auto">
        <circle cx="60" cy="60" r="54" fill="none" stroke="#1c2029" stroke-width="12"/>
        <g transform="rotate(-90 60 60)">
        <?php foreach($segs as $sgm): ?>
          <circle cx="60" cy="60" r="54" fill="none" stroke="<?= e($sgm['color']) ?>" stroke-width="12"
                  stroke-dasharray="<?= round($sgm['len'],2) ?> <?= round($C-$sgm['len'],2) ?>"
                  stroke-dashoffset="<?= round(-$sgm['off'],2) ?>"/>
        <?php endforeach; ?>
        </g>
        <text x="60" y="56" text-anchor="middle" fill="var(--ink)" font-size="20" font-weight="800"><?= $totalSvc ?></text>
        <text x="60" y="72" text-anchor="middle" fill="#7d8696" font-size="9">bilete</text>
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

  <div class="card pad" style="flex:1.4">
    <div style="display:flex;justify-content:space-between;align-items:baseline">
      <h3 style="margin-top:0">Aflux pe ora (azi)</h3>
      <?php if(array_sum($per_hour)>0): ?><span class="muted" style="font-size:.82rem">Varf: <?= sprintf('%02d:00',$peakHour) ?> (<?= $maxHour ?>)</span><?php endif; ?>
    </div>
    <div style="display:flex;align-items:flex-end;gap:3px;height:160px">
      <?php for($h=0;$h<24;$h++): $val=$per_hour[$h]; $pct=(int)($val/$maxHour*100); ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end" title="<?= sprintf('%02d:00',$h) ?> — <?= $val ?> bilete">
          <span style="font-size:.6rem;color:#7d8696;margin-bottom:2px"><?= $val?:'' ?></span>
          <div style="width:100%;background:var(--accent);border-radius:4px 4px 0 0;height:<?= max($val?6:0,$pct) ?>%;opacity:<?= $val?1:.12 ?>"></div>
          <span style="font-size:.58rem;color:#5b6270;margin-top:3px"><?= $h ?></span>
        </div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<div class="row" style="align-items:flex-start">
  <div class="card pad" style="flex:1.2">
    <h3 style="margin-top:0">Bilete pe serviciu (azi)</h3>
    <?php if(!$per_service): ?><p class="muted">Niciun serviciu.</p><?php endif; ?>
    <?php foreach($per_service as $p): ?>
      <div style="display:flex;align-items:center;gap:.7rem;margin:.5rem 0">
        <span class="tag" style="background:<?= e($p['color']) ?>"><?= e(mb_substr($p['name'],0,1)) ?></span>
        <span style="flex:1"><?= e($p['name']) ?></span>
        <div style="flex:2;background:#1c2029;border-radius:6px;height:10px;overflow:hidden">
          <div style="height:100%;width:<?= (int)($p['cnt']/$maxSvc*100) ?>%;background:<?= e($p['color']) ?>"></div></div>
        <strong style="width:34px;text-align:right"><?= (int)$p['cnt'] ?></strong>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="card pad" style="flex:1">
    <h3 style="margin-top:0">Dispozitive</h3>
    <table><tbody>
    <?php foreach($devices as $d): ?>
      <tr><td>
        <span class="pill" style="background:<?= $d['online']?'#dcfce7':'#f1f5f9' ?>;color:<?= $d['online']?'#166534':'#64748b' ?>">
          <?= $d['online']?'● online':'○ offline' ?></span>
        </td><td><strong><?= e($d['name']) ?></strong><br><span class="muted" style="font-size:.8rem"><?= e($d['type']) ?></span></td>
        <td><code><?= e($d['connection_key']) ?></code></td></tr>
    <?php endforeach; ?>
    <?php if(!$devices): ?><tr><td class="muted">Niciun dispozitiv.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<?php require __DIR__.'/_footer.php'; ?>
