<?php $title='Bilete'; $active='tickets'; require __DIR__.'/_header.php';
$st=['waiting'=>['La rand','#fef3c7','#92400e'],'called'=>['Apelat','#dbeafe','#1e40af'],'serving'=>['In servire','#dbeafe','#1e40af'],
  'served'=>['Servit','#dcfce7','#166534'],'no_show'=>['Neprezentat','#f1f5f9','#64748b'],'cancelled'=>['Anulat','#fee2e2','#b91c1c'],'transferred'=>['Transferat','#e0e7ff','#3730a3']]; ?>
<div class="topbar"><h1>Bilete</h1>
  <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
    <form method="get" style="display:flex;gap:.5rem;align-items:center"><label style="margin:0">Data</label><input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()"></form>
    <form method="post" action="<?= e(url('admin/tickets/reset')) ?>" onsubmit="return confirm('Resetezi bonurile? Se STERG TOATE biletele (coada + istoric + statistici) si numerotarea reincepe de la 0. Actiunea NU poate fi anulata.')" style="display:flex;gap:.4rem;align-items:center">
      <?= csrf_field() ?>
      <?php if(count($branches)>1): ?><select name="branch" style="width:auto"><option value="0">Toate filialele</option><?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
      <button class="btn btn-danger">↺ Reset bonuri</button>
    </form>
  </div>
</div>
<div class="card pad">
  <table><thead><tr><th>Bon</th><th>Serviciu</th><th>Status</th><th>Ghiseu</th><th>Emis</th><th>Apelat</th><th>Canal</th></tr></thead><tbody>
  <?php foreach($rows as $t): $s=$st[$t['status']]??['?','#eee','#555']; ?>
    <tr><td><span class="tag" style="background:<?= e($t['color']) ?>"><?= e($t['label'][0]) ?></span> <strong><?= e($t['label']) ?></strong><?= $t['priority']?' ★':'' ?><?php if(!empty($t['form_data']) && ($fd=json_decode($t['form_data'],true)) && is_array($fd)): ?> <span title="<?= e(implode(' · ', array_map(fn($x)=>($x['label']??'').': '.($x['value']??''), $fd))) ?>" style="cursor:help">📋</span><?php endif; ?></td>
      <td><?= e($t['service_name']) ?></td>
      <td><span class="pill" style="background:<?= $s[1] ?>;color:<?= $s[2] ?>"><?= $s[0] ?></span></td>
      <td><?= e($t['counter_code']??'—') ?></td>
      <td class="muted"><?= date('H:i',strtotime($t['issued_at'])) ?></td>
      <td class="muted"><?= $t['called_at']?date('H:i',strtotime($t['called_at'])):'—' ?></td>
      <td class="muted"><?= e($t['channel']) ?></td></tr>
  <?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="7" class="muted">Niciun bilet in ziua selectata.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php require __DIR__.'/_footer.php'; ?>
