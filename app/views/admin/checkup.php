<?php $title='Verificare producție'; $active='checkup'; require __DIR__.'/_header.php';
$crit = array_filter($checks, fn($c) => $c['level']==='crit');
$warn = array_filter($checks, fn($c) => $c['level']==='warn');
$ok   = array_filter($checks, fn($c) => $c['level']==='ok');
$ic = ['ok'=>'✅','warn'=>'⚠️','crit'=>'⛔'];
$col = ['ok'=>['#dcfce7','#166534'],'warn'=>['#fef3c7','#92400e'],'crit'=>['#fee2e2','#b91c1c']];
?>
<div class="topbar"><h1>Verificare producție</h1></div>
<div class="card pad" style="max-width:760px">
  <p class="muted" style="margin-top:0">Verificări automate de configurare, ca să prinzi problemele <strong>înainte</strong> să devină reclamații. Reverifică oricând după ce schimbi setări.</p>
  <p style="margin:.4rem 0 1rem">
    <span class="badge" style="background:<?= $col['crit'][0] ?>;color:<?= $col['crit'][1] ?>"><?= count($crit) ?> critice</span>
    <span class="badge" style="background:<?= $col['warn'][0] ?>;color:<?= $col['warn'][1] ?>;margin-left:.4rem"><?= count($warn) ?> avertismente</span>
    <span class="badge" style="background:<?= $col['ok'][0] ?>;color:<?= $col['ok'][1] ?>;margin-left:.4rem"><?= count($ok) ?> ok</span>
  </p>
  <?php foreach (array_merge($crit, $warn, $ok) as $c): ?>
    <div style="display:flex;gap:.7rem;align-items:flex-start;padding:.7rem 0;border-top:1px solid var(--line)">
      <span style="font-size:1.2rem;line-height:1.2"><?= $ic[$c['level']] ?></span>
      <div>
        <div style="font-weight:700"><?= e($c['title']) ?></div>
        <div class="muted" style="font-size:.86rem;line-height:1.5"><?= e($c['detail']) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
  <p style="margin-top:1rem"><a class="btn" href="<?= e(url('admin/checkup')) ?>">↻ Reverifică</a></p>
</div>
<?php require __DIR__.'/_footer.php'; ?>
