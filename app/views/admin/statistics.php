<?php $title='Statistici'; $active='statistics'; require __DIR__.'/_header.php';
// timp in format precis HH:MM:SS (ca la Moviik)
function mmss($s){ $s=(int)round((float)$s); return $s<=0?'—':sprintf('%d:%02d:%02d', intdiv($s,3600), intdiv($s%3600,60), $s%60); }
$qs = fn($extra)=>e(url('admin/statistics').'?'.http_build_query(array_merge(['from'=>$from,'to'=>$to,'branch'=>$branch],$extra)));
// buton mic de download CSV pentru un set de date
$dlcsv = fn($ds)=>'<a class="btn btn-ghost" style="padding:.3rem .6rem;font-size:.78rem" href="'.$qs(['export'=>'csv','dataset'=>$ds]).'">⬇ CSV</a>';
// comutator grafic/tabel pentru un panou (ca la Moviik)
$dvtoggle = '<span class="dvtoggle"><button type="button" class="on" data-dv="chart">📊 Grafic</button><button type="button" data-dv="table">▦ Tabel</button></span>';
// pregatire date grafice
$maxDay = 1; foreach($per_day as $r) $maxDay=max($maxDay,(int)$r['c']);
$hours = array_fill(0,24,0); foreach($per_hour as $r) $hours[(int)$r['h']]=(int)$r['c'];
$maxHour = max(1,max($hours));
$maxSvc = 1; foreach($per_service as $r) $maxSvc=max($maxSvc,(int)$r['c']);
$maxCtr = 1; foreach($per_counter as $r) $maxCtr=max($maxCtr,(int)$r['cnt']);
$served=(int)($kpi['served']??0); $noshow=(int)($kpi['no_show']??0); $canc=(int)($kpi['cancelled']??0);
$totFin = max(1,$served+$noshow+$canc);
?>
<div class="topbar">
  <h1>Statistici</h1>
  <div style="display:flex;gap:.5rem">
    <a class="btn btn-primary" href="<?= $qs(['export'=>'xlsx']) ?>">⬇ Export Excel (grafice)</a>
    <a class="btn" href="<?= $qs(['export'=>'csv']) ?>">⬇ Export CSV</a>
  </div>
</div>

<form method="get" action="<?= e(url('admin/statistics')) ?>" class="card pad" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1.2rem">
  <div class="field" style="margin:0"><label>De la</label><input type="date" name="from" value="<?= e($from) ?>"></div>
  <div class="field" style="margin:0"><label>Pana la</label><input type="date" name="to" value="<?= e($to) ?>"></div>
  <div class="field" style="margin:0"><label>Filiala</label><select name="branch">
    <option value="0">Toate</option>
    <?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $branch===(int)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
  </select></div>
  <button class="btn btn-primary">Aplica</button>
  <div style="display:flex;gap:.4rem">
    <a class="btn btn-ghost" href="<?= e(url('admin/statistics?from='.date('Y-m-d').'&to='.date('Y-m-d'))) ?>">Azi</a>
    <a class="btn btn-ghost" href="<?= e(url('admin/statistics?from='.date('Y-m-d',strtotime('-6 days')).'&to='.date('Y-m-d'))) ?>">7 zile</a>
    <a class="btn btn-ghost" href="<?= e(url('admin/statistics?from='.date('Y-m-d',strtotime('-29 days')).'&to='.date('Y-m-d'))) ?>">30 zile</a>
  </div>
</form>

<div class="kpis">
  <div class="card pad kpi"><div class="n"><?= (int)($kpi['total']??0) ?></div><div class="l">Total bilete</div></div>
  <div class="card pad kpi"><div class="n" style="color:var(--ok)"><?= $served ?></div><div class="l">Servite</div></div>
  <div class="card pad kpi"><div class="n" style="color:var(--muted)"><?= $noshow ?></div><div class="l">Neprezentate</div></div>
  <div class="card pad kpi"><div class="n" style="color:var(--danger)"><?= $canc ?></div><div class="l">Anulate</div></div>
  <div class="card pad kpi"><div class="n"><?= mmss($kpi['avg_wait']??0) ?></div><div class="l">Timp mediu asteptare</div></div>
  <div class="card pad kpi"><div class="n"><?= mmss($kpi['avg_service']??0) ?></div><div class="l">Timp mediu servire</div></div>
</div>

<div class="card pad dvbox" style="margin-bottom:1.2rem">
  <div class="dvhead"><h3>Bilete pe zi</h3><div class="dvctrl"><?= $dvtoggle ?><?= $dlcsv('day') ?></div></div>
  <div class="dv-table dv-hidden">
    <table><thead><tr><th>Data</th><th>Bilete</th><th>Timp mediu asteptare</th></tr></thead><tbody>
    <?php foreach($per_day as $r): ?><tr><td><?= e(date('d.m.Y',strtotime($r['d']))) ?></td><td><?= (int)$r['c'] ?></td><td><?= mmss($r['w']??0) ?></td></tr><?php endforeach; ?>
    <?php if(!$per_day): ?><tr><td colspan="3" class="muted">Fara date.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
  <div class="dv-chart">
  <?php if(array_sum(array_map(fn($r)=>(int)$r['c'],$per_day))===0): ?>
    <p class="muted">Niciun bilet in intervalul selectat.</p>
  <?php else:
    $n=count($per_day); $W=1000; $H=240; $pad=24;
    $pts=[]; foreach($per_day as $i=>$r){ $x=$n>1?($pad+$i/($n-1)*($W-2*$pad)):$W/2; $y=$H-$pad-((int)$r['c']/$maxDay)*($H-2*$pad); $pts[]=[$x,$y,$r]; }
    $line=implode(' ',array_map(fn($p)=>round($p[0],1).','.round($p[1],1),$pts));
    $area="$pad,".($H-$pad).' '.$line.' '.($pts[count($pts)-1][0]).','.($H-$pad);
  ?>
  <svg viewBox="0 0 <?= $W ?> <?= $H+24 ?>" style="width:100%;height:auto" preserveAspectRatio="none">
    <defs><linearGradient id="g1" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="var(--accent)" stop-opacity=".35"/><stop offset="1" stop-color="var(--accent)" stop-opacity="0"/></linearGradient></defs>
    <?php for($g=0;$g<=4;$g++){ $gy=$pad+$g/4*($H-2*$pad); ?><line x1="<?= $pad ?>" y1="<?= $gy ?>" x2="<?= $W-$pad ?>" y2="<?= $gy ?>" stroke="var(--grid)" stroke-width="1"/><?php } ?>
    <polygon points="<?= $area ?>" fill="url(#g1)"/>
    <polyline points="<?= $line ?>" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linejoin="round"/>
    <?php foreach($pts as $p): ?><circle cx="<?= round($p[0],1) ?>" cy="<?= round($p[1],1) ?>" r="3.5" fill="var(--accent)"/><?php endforeach; ?>
    <?php foreach($pts as $i=>$p){ if($n>12 && $i%ceil($n/12)!==0 && $i!==$n-1) continue; ?>
      <text x="<?= round($p[0],1) ?>" y="<?= $H+14 ?>" fill="var(--muted)" font-size="11" text-anchor="middle"><?= date('d.m',strtotime($p[2]['d'])) ?></text>
      <text x="<?= round($p[0],1) ?>" y="<?= max(12,$p[1]-8) ?>" fill="var(--ink)" font-size="11" text-anchor="middle"><?= (int)$p[2]['c'] ?></text>
    <?php } ?>
  </svg>
  <?php endif; ?>
  </div><!-- .dv-chart -->
</div>

<div class="row" style="align-items:flex-start">
  <div class="card pad" style="flex:1">
    <div style="display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0 0 .6rem">Bilete pe serviciu</h3><?= $dlcsv('service') ?></div>
    <?php foreach($per_service as $r): ?>
      <div style="display:flex;align-items:center;gap:.7rem;margin:.5rem 0">
        <span class="tag" style="background:<?= e($r['color']) ?>"><?= e(mb_substr($r['name'],0,1)) ?></span>
        <span style="flex:1;min-width:90px"><?= e($r['name']) ?></span>
        <div style="flex:2;background:var(--track);border-radius:6px;height:10px;overflow:hidden"><div style="height:100%;width:<?= (int)((int)$r['c']/$maxSvc*100) ?>%;background:<?= e($r['color']) ?>"></div></div>
        <strong style="width:38px;text-align:right"><?= (int)$r['c'] ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if(!$per_service): ?><p class="muted">Fara date.</p><?php endif; ?>
  </div>
  <div class="card pad" style="flex:1">
    <div style="display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0 0 .6rem">Bilete pe ghiseu</h3><?= $dlcsv('counter') ?></div>
    <?php foreach($per_counter as $r): if((int)$r['cnt']===0) continue; ?>
      <div style="display:flex;align-items:center;gap:.7rem;margin:.5rem 0">
        <span style="flex:1"><strong><?= e($r['code']) ?></strong> <span class="muted"><?= e($r['name']) ?></span></span>
        <div style="flex:2;background:var(--track);border-radius:6px;height:10px;overflow:hidden"><div style="height:100%;width:<?= (int)((int)$r['cnt']/$maxCtr*100) ?>%;background:var(--accent)"></div></div>
        <strong style="width:38px;text-align:right"><?= (int)$r['cnt'] ?></strong>
      </div>
    <?php endforeach; ?>
    <?php if(array_sum(array_map(fn($r)=>(int)$r['cnt'],$per_counter))===0): ?><p class="muted">Fara date.</p><?php endif; ?>
  </div>
</div>

<div class="card pad dvbox" style="margin:1.2rem 0">
  <div class="dvhead"><h3>Aflux pe ora din zi</h3><div class="dvctrl"><?= $dvtoggle ?><?= $dlcsv('hour') ?></div></div>
  <div class="dv-table dv-hidden">
    <table><thead><tr><th>Ora</th><th>Bilete</th></tr></thead><tbody>
    <?php for($h=0;$h<24;$h++): if($hours[$h]<=0) continue; ?><tr><td><?= sprintf('%02d:00',$h) ?></td><td><?= (int)$hours[$h] ?></td></tr><?php endfor; ?>
    <?php if(array_sum($hours)===0): ?><tr><td colspan="2" class="muted">Fara date.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
  <div class="dv-chart" style="display:flex;align-items:flex-end;gap:3px;height:150px">
    <?php for($h=0;$h<24;$h++): $val=$hours[$h]; $pct=(int)($val/$maxHour*100); ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end" title="<?= $h ?>:00 — <?= $val ?> bilete">
        <span style="font-size:.62rem;color:var(--muted);margin-bottom:2px"><?= $val?:'' ?></span>
        <div style="width:100%;background:var(--accent);border-radius:4px 4px 0 0;height:<?= max($val?6:0,$pct) ?>%;opacity:<?= $val?1:.15 ?>"></div>
        <span style="font-size:.6rem;color:var(--muted);margin-top:3px"><?= $h ?></span>
      </div>
    <?php endfor; ?>
  </div>
</div>

<div class="card pad">
  <div style="display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0 0 .6rem">Detaliu pe serviciu</h3><?= $dlcsv('service') ?></div>
  <table><thead><tr><th>Serviciu</th><th>Total</th><th>Servite</th><th>Timp mediu asteptare</th><th>Timp mediu servire</th></tr></thead><tbody>
  <?php foreach($per_service as $r): ?>
    <tr><td><span class="tag" style="background:<?= e($r['color']) ?>;width:20px;height:20px;font-size:.7rem"><?= e(mb_substr($r['name'],0,1)) ?></span> <?= e($r['name']) ?></td>
      <td><?= (int)$r['c'] ?></td><td><?= (int)$r['served'] ?></td><td><?= mmss($r['w']??0) ?></td><td><?= mmss($r['sv']??0) ?></td></tr>
  <?php endforeach; ?>
  <?php if(!$per_service): ?><tr><td colspan="5" class="muted">Fara date.</td></tr><?php endif; ?>
  </tbody></table>
  <p class="muted" style="margin-top:.8rem;font-size:.82rem">Defalcare status in interval: <strong style="color:var(--ok)"><?= round($served/$totFin*100) ?>%</strong> servite · <?= round($noshow/$totFin*100) ?>% neprezentate · <?= round($canc/$totFin*100) ?>% anulate</p>
</div>

<div class="card pad" style="margin-top:1.2rem">
  <div style="display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0 0 .6rem">Bilete pe utilizator</h3><?= $dlcsv('user') ?></div>
  <table><thead><tr><th>Utilizator</th><th>Total</th><th>Servite</th><th>Timp mediu asteptare</th><th>Timp mediu servire</th></tr></thead><tbody>
  <?php foreach($per_user as $r): ?>
    <tr><td><span class="badge" style="background:#2a2f3a;width:22px;height:22px;font-size:.7rem"><?= e(mb_strtoupper(mb_substr($r['name'],0,1))) ?></span> <?= e($r['name']) ?></td>
      <td><?= (int)$r['c'] ?></td><td><?= (int)$r['served'] ?></td><td><?= mmss($r['w']??0) ?></td><td><?= mmss($r['sv']??0) ?></td></tr>
  <?php endforeach; ?>
  <?php if(!$per_user): ?><tr><td colspan="5" class="muted">Niciun bilet servit de un operator in interval.</td></tr><?php endif; ?>
  </tbody></table>
</div>

<?php $fbN=(int)($feedback['n']??0); $fbAvg=$feedback['avg']!==null?round((float)$feedback['avg'],2):null;
  $dist=array_fill(1,5,0); foreach(($fb_dist??[]) as $d) $dist[(int)$d['rating']]=(int)$d['c']; ?>
<div class="card pad" style="margin-top:1.2rem">
  <h3 style="margin:0 0 .6rem">Satisfactie client (feedback)</h3>
  <?php if($fbN===0): ?>
    <p class="muted">Niciun feedback in interval. Adauga widget-ul „Formular feedback" pe afisaj sau partajeaza linkul <code><?= e(url('feedback')) ?></code>.</p>
  <?php else: ?>
    <div style="display:flex;gap:2rem;align-items:center;flex-wrap:wrap">
      <div style="text-align:center">
        <div style="font-family:var(--display);font-size:2.6rem;font-weight:800;line-height:1"><?= e(number_format($fbAvg,2)) ?></div>
        <div style="color:#f5b301;font-size:1.2rem"><?php for($i=1;$i<=5;$i++) echo $i<=round($fbAvg)?'★':'☆'; ?></div>
        <div class="muted" style="font-size:.82rem"><?= $fbN ?> raspunsuri</div>
      </div>
      <div style="flex:1;min-width:220px">
        <?php for($r=5;$r>=1;$r--): $pct=$fbN?round($dist[$r]/$fbN*100):0; ?>
          <div style="display:flex;align-items:center;gap:.6rem;margin:.25rem 0">
            <span style="width:42px;color:#f5b301">★ <?= $r ?></span>
            <div style="flex:1;background:var(--track);border-radius:6px;height:9px;overflow:hidden"><div style="height:100%;width:<?= $pct ?>%;background:#f5b301"></div></div>
            <strong style="width:34px;text-align:right"><?= $dist[$r] ?></strong>
          </div>
        <?php endfor; ?>
      </div>
    </div>
    <?php if(!empty($fb_recent)): ?>
      <div style="margin-top:1rem">
        <div class="muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem">Comentarii recente</div>
        <?php foreach($fb_recent as $c): ?>
          <div style="border-top:1px solid var(--line);padding:.5rem 0;display:flex;gap:.7rem;align-items:flex-start">
            <span style="color:#f5b301;white-space:nowrap"><?php for($i=1;$i<=5;$i++) echo $i<=(int)$c['rating']?'★':'☆'; ?></span>
            <span style="flex:1"><?= e($c['comment']) ?></span>
            <span class="muted" style="font-size:.78rem;white-space:nowrap"><?= e(date('d.m H:i',strtotime($c['created_at']))) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
