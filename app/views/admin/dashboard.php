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
  <span class="muted" style="font-size:.85rem">Interval actualizare: la reincarcare · <?= date('l, d.m.Y') ?></span>
</div>

<div class="statcards">
  <div class="statcard"><div class="t">Bilete azi</div><div class="s">Total emise</div><div class="v"><?= $stats['today'] ?></div></div>
  <div class="statcard"><div class="t">La rand acum</div><div class="s">In asteptare</div><div class="v" style="color:var(--warn)"><?= $stats['waiting'] ?></div></div>
  <div class="statcard"><div class="t">In servire</div><div class="s">Chemate / la ghiseu</div><div class="v" style="color:var(--accent)"><?= $stats['serving'] ?></div></div>
  <div class="statcard"><div class="t">Servite azi</div><div class="s">Finalizate</div><div class="v" style="color:var(--ok)"><?= $stats['served'] ?></div></div>
  <div class="statcard"><div class="t">Timp mediu asteptare</div><div class="s">mm:ss · azi</div><div class="v"><?= mmss($stats['avg_wait']) ?></div></div>
</div>

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
    <h4>Dispozitive <span class="live"><?= $onlineDev ?>/<?= count($devices) ?> online</span></h4>
    <table><tbody>
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
</div>
<?php require __DIR__.'/_footer.php'; ?>
