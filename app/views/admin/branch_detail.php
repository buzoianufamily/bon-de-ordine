<?php $title=$branch['name']; $active='branches'; require __DIR__.'/_header.php';
$tabs = ['services'=>'Servicii','counters'=>'Ghisee','devices'=>'Dispozitive'];
$bid = (int)$branch['id'];
$turl = fn($t)=>url('admin/branches/'.$bid.'?tab='.$t);
$labels=['dispenser'=>'Dispenser','player'=>'Afisaj TV','widget_player'=>'Afisaj TV','digital_ticket'=>'Bilet digital','launcher'=>'Launcher']; ?>
<div class="topbar">
  <div>
    <div class="muted" style="font-size:.85rem"><a href="<?= e(url('admin/branches')) ?>">Filiale</a> › <?= e($branch['name']) ?></div>
    <h1 style="margin:.1rem 0"><?= e($branch['name']) ?></h1>
    <span class="muted"><?= e(trim(($branch['city']?:'').(($branch['city']&&$branch['country'])?', ':'').($branch['country']?:''))) ?></span>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('admin/branches/'.$bid.'/edit')) ?>">✎ Editeaza filiala</a>
</div>

<div style="display:flex;gap:.4rem;border-bottom:1px solid var(--line);margin-bottom:1.2rem">
  <?php foreach($tabs as $k=>$lab): $on=$tab===$k; ?>
    <a href="<?= e($turl($k)) ?>" style="padding:.7rem 1.1rem;font-weight:700;border-bottom:3px solid <?= $on?'var(--accent)':'transparent' ?>;color:<?= $on?'var(--accent)':'var(--muted)' ?>"><?= $lab ?></a>
  <?php endforeach; ?>
</div>

<?php if($tab==='services'): ?>
  <div class="topbar" style="margin-bottom:1rem"><h3 style="margin:0">Servicii</h3><a class="btn btn-primary" href="<?= e(url('admin/services/new?branch='.$bid)) ?>">+ Serviciu</a></div>
  <div class="card pad"><table><thead><tr><th></th><th>Prefix</th><th>Nume</th><th>Interval</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach($services as $r): ?>
    <tr><td><span class="tag" style="background:<?= e($r['color']) ?>"><?= e($r['prefix']) ?></span></td><td><strong><?= e($r['prefix']) ?></strong></td>
      <td><?= e($r['name']) ?></td><td class="muted"><?= (int)$r['num_from'] ?>–<?= (int)$r['num_to'] ?></td>
      <td><?= $r['status']==='active'?'<span class="pill" style="background:#dcfce7;color:#166534">Activ</span>':'<span class="muted">Inactiv</span>' ?></td>
      <td style="text-align:right"><a class="btn btn-ghost" href="<?= e(url('admin/services/'.$r['id'])) ?>">Editeaza</a></td></tr>
  <?php endforeach; ?>
  <?php if(!$services): ?><tr><td colspan="6" class="muted">Niciun serviciu in aceasta filiala.</td></tr><?php endif; ?>
  </tbody></table></div>

<?php elseif($tab==='counters'): ?>
  <div class="topbar" style="margin-bottom:1rem"><h3 style="margin:0">Ghisee</h3><a class="btn btn-primary" href="<?= e(url('admin/counters/new?branch='.$bid)) ?>">+ Ghiseu</a></div>
  <div class="card pad"><table><thead><tr><th>Cod</th><th>Nume</th><th>Servicii</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach($counters as $r): ?>
    <tr><td><strong><?= e($r['code']) ?></strong></td><td><?= e($r['name']) ?></td>
      <td class="muted"><?= $r['all_services']?'Toate':($r['svc_count'].' alocate') ?></td>
      <td><span class="pill" style="background:#eef2ff;color:#3730a3"><?= e($r['status']) ?></span></td>
      <td style="text-align:right"><a class="btn btn-ghost" href="<?= e(url('admin/counters/'.$r['id'])) ?>">Editeaza</a></td></tr>
  <?php endforeach; ?>
  <?php if(!$counters): ?><tr><td colspan="5" class="muted">Niciun ghiseu in aceasta filiala.</td></tr><?php endif; ?>
  </tbody></table></div>

<?php else: ?>
  <div class="topbar" style="margin-bottom:1rem"><h3 style="margin:0">Dispozitive</h3><a class="btn btn-primary" href="<?= e(url('admin/devices/new?branch='.$bid)) ?>">+ Dispozitiv</a></div>
  <div class="kpis" style="grid-template-columns:repeat(auto-fill,minmax(290px,1fr))">
  <?php foreach($devices as $d): $du=url('launcher?key='.$d['connection_key']); ?>
    <div class="card pad">
      <div style="display:flex;justify-content:space-between"><div><strong><?= e($d['name']) ?></strong><br><span class="muted" style="font-size:.82rem"><?= e($labels[$d['type']]??$d['type']) ?></span></div>
        <span class="pill" style="background:<?= $d['online']?'#dcfce7':'#f1f5f9' ?>;color:<?= $d['online']?'#166534':'#64748b' ?>"><?= $d['online']?'● online':'○ offline' ?></span></div>
      <div style="margin:.6rem 0"><code style="font-size:1.1rem;font-weight:800"><?= e($d['connection_key']) ?></code></div>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap">
        <a class="btn btn-ghost" target="_blank" href="<?= e($du) ?>">Deschide</a>
        <?php if(in_array($d['type'],['player','widget_player'],true)): ?><a class="btn btn-primary" href="<?= e(url('admin/devices/'.$d['id'].'/player')) ?>">🎨 Design</a><?php endif; ?>
        <?php if(in_array($d['type'],['dispenser','digital_ticket'],true)): ?><a class="btn btn-primary" href="<?= e(url('admin/devices/'.$d['id'].'/dispenser')) ?>">🎨 Config</a><?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('admin/devices/'.$d['id'])) ?>">Editeaza</a>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if(!$devices): ?><p class="muted">Niciun dispozitiv in aceasta filiala.</p><?php endif; ?>
  </div>
<?php endif; ?>
<?php require __DIR__.'/_footer.php'; ?>
