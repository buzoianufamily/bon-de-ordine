<?php $title='Dashboard'; $active=''; require __DIR__.'/_header.php';
function mmss($s){ $s=(int)$s; return sprintf('%02d:%02d', intdiv($s,60), $s%60); } ?>
<div class="topbar"><h1>Dashboard</h1><span class="muted"><?= date('d.m.Y') ?></span></div>
<div class="kpis">
  <div class="card pad kpi"><div class="n"><?= $stats['today'] ?></div><div class="l">Bilete azi</div></div>
  <div class="card pad kpi"><div class="n" style="color:var(--warn)"><?= $stats['waiting'] ?></div><div class="l">La rand acum</div></div>
  <div class="card pad kpi"><div class="n" style="color:var(--accent)"><?= $stats['serving'] ?></div><div class="l">In servire</div></div>
  <div class="card pad kpi"><div class="n" style="color:var(--ok)"><?= $stats['served'] ?></div><div class="l">Servite azi</div></div>
  <div class="card pad kpi"><div class="n"><?= mmss($stats['avg_wait']) ?></div><div class="l">Timp mediu asteptare</div></div>
</div>
<div class="row">
  <div class="card pad" style="flex:1.2">
    <h3 style="margin-top:0">Bilete pe serviciu (azi)</h3>
    <?php if(!$per_service): ?><p class="muted">Niciun serviciu.</p><?php endif; ?>
    <?php foreach($per_service as $p): $max=max(1,array_sum(array_column($per_service,'cnt'))); ?>
      <div style="display:flex;align-items:center;gap:.7rem;margin:.5rem 0">
        <span class="tag" style="background:<?= e($p['color']) ?>"><?= e(mb_substr($p['name'],0,1)) ?></span>
        <span style="flex:1"><?= e($p['name']) ?></span>
        <div style="flex:2;background:#1c2029;border-radius:6px;height:10px;overflow:hidden">
          <div style="height:100%;width:<?= (int)($p['cnt']/$max*100) ?>%;background:<?= e($p['color']) ?>"></div></div>
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
