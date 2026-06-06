<?php $title='Servicii'; $active='services'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Servicii</h1><a class="btn btn-primary" href="<?= e(url('admin/services/new')) ?>">+ Serviciu nou</a></div>
<div class="card pad">
  <table><thead><tr><th></th><th>Prefix</th><th>Nume</th><th>Interval</th><th>Prioritar</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach($rows as $r): ?>
    <tr>
      <td><span class="tag" style="background:<?= e($r['color']) ?>"><?= e($r['prefix']) ?></span></td>
      <td><strong><?= e($r['prefix']) ?></strong></td>
      <td><?= e($r['name']) ?> <span class="muted" style="font-size:.74rem">· 🏢 <?= e($r['branch_name']) ?></span><?= $r['description']? '<br><span class="muted" style="font-size:.8rem">'.e($r['description']).'</span>':'' ?></td>
      <td class="muted"><?= (int)$r['num_from'] ?>–<?= (int)$r['num_to'] ?></td>
      <td><?= $r['allow_priority']?'<span class="pill" style="background:#fef3c7;color:#92400e">Da</span>':'<span class="muted">Nu</span>' ?></td>
      <td><?= $r['status']==='active'?'<span class="pill" style="background:#dcfce7;color:#166534">Activ</span>':'<span class="pill" style="background:#f1f5f9;color:#64748b">Inactiv</span>' ?>
        <?php if(!empty($r['active_hours']) && ($sc=json_decode($r['active_hours'],true)) && !empty($sc['enabled'])): $op=service_is_open($r); ?>
          <br><span class="pill" style="background:<?= $op?'#143524':'#3a1d1d' ?>;color:<?= $op?'#4ade80':'#fca5a5' ?>;font-size:.68rem;margin-top:.25rem;display:inline-block"><?= $op?'● deschis acum':'○ inchis acum' ?></span>
        <?php endif; ?>
      </td>
      <td style="text-align:right;white-space:nowrap">
        <a class="btn btn-ghost" href="<?= e(url('admin/services/'.$r['id'])) ?>">Editeaza</a>
        <form method="post" action="<?= e(url('admin/services/'.$r['id'].'/delete')) ?>" style="display:inline" onsubmit="return confirm('Stergi serviciul?')"><?= csrf_field() ?><button class="btn btn-ghost" style="color:var(--danger)">Sterge</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="7" class="muted">Niciun serviciu. Creeaza primul.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php require __DIR__.'/_footer.php'; ?>
