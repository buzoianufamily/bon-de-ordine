<?php $title='Dispozitive'; $active='devices'; require __DIR__.'/_header.php';
$labels=['dispenser'=>'Dispenser bilete','player'=>'Afisaj TV','widget_player'=>'Afisaj TV','digital_ticket'=>'Bilet digital QR','launcher'=>'Launcher'];
function dev_url($d){ return $d['type']==='digital_ticket' ? url('launcher?key='.$d['connection_key']) : url('launcher?key='.$d['connection_key']); } ?>
<div class="topbar"><h1>Dispozitive</h1><a class="btn btn-primary" href="<?= e(url('admin/devices/new')) ?>">+ Dispozitiv nou</a></div>
<div class="kpis" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
  <?php foreach($rows as $d): $u=dev_url($d); ?>
    <div class="card pad">
      <div style="display:flex;justify-content:space-between;align-items:start">
        <div><strong><?= e($d['name']) ?></strong><br><span class="muted" style="font-size:.82rem"><?= e($labels[$d['type']]??$d['type']) ?> · 🏢 <?= e($d['branch_name']) ?></span></div>
        <span class="pill" style="background:<?= $d['online']?'#dcfce7':'#f1f5f9' ?>;color:<?= $d['online']?'#166534':'#64748b' ?>"><?= $d['online']?'● online':'○ offline' ?></span>
      </div>
      <div style="margin:.7rem 0">
        <div class="muted" style="font-size:.72rem;text-transform:uppercase">Cheie conectare</div>
        <code style="font-size:1.2rem;font-weight:800;letter-spacing:.05em"><?= e($d['connection_key']) ?></code>
      </div>
      <div class="muted" style="font-size:.72rem;text-transform:uppercase">Link deschidere</div>
      <input readonly value="<?= e($u) ?>" onclick="this.select()" style="font-size:.78rem;padding:.4rem">
      <div style="margin-top:.7rem;display:flex;gap:.4rem;flex-wrap:wrap">
        <a class="btn btn-ghost" target="_blank" href="<?= e($u) ?>">Deschide</a>
        <?php if(in_array($d['type'],['player','widget_player'],true)): ?>
          <a class="btn btn-primary" href="<?= e(url('admin/devices/'.$d['id'].'/player')) ?>">🎨 Design afisaj</a>
        <?php endif; ?>
        <?php if(in_array($d['type'],['dispenser','digital_ticket'],true)): ?>
          <a class="btn btn-primary" href="<?= e(url('admin/devices/'.$d['id'].'/dispenser')) ?>">🎨 Configureaza</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('admin/devices/'.$d['id'])) ?>">Editeaza</a>
        <form method="post" action="<?= e(url('admin/devices/'.$d['id'].'/delete')) ?>" style="display:inline" onsubmit="return confirm('Stergi dispozitivul?')"><?= csrf_field() ?><button class="btn btn-ghost" style="color:var(--danger)">Sterge</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if(!$rows): ?><p class="muted">Niciun dispozitiv. Creeaza primul (dispenser/afisaj).</p><?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
