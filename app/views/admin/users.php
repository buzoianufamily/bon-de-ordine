<?php $title='Utilizatori'; $active='users'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Utilizatori</h1><a class="btn btn-primary" href="<?= e(url('admin/users/new')) ?>">+ Utilizator nou</a></div>
<div class="card pad"><table><thead><tr><th>Nume</th><th>Email</th><th>Rol</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
  <tr><td><strong><?= e($r['name']) ?></strong></td><td class="muted"><?= e($r['email']) ?></td>
    <td><span class="pill" style="background:#eef2ff;color:#3730a3"><?= e($r['role']) ?></span></td>
    <td><?= $r['active']?'<span class="pill" style="background:#dcfce7;color:#166534">Activ</span>':'<span class="muted">Inactiv</span>' ?></td>
    <td style="text-align:right;white-space:nowrap"><a class="btn btn-ghost" href="<?= e(url('admin/users/'.$r['id'])) ?>">Editeaza</a>
    <?php if($r['id']!=current_user()['id']): ?><form method="post" action="<?= e(url('admin/users/'.$r['id'].'/delete')) ?>" style="display:inline" onsubmit="return confirm('Stergi utilizatorul?')"><?= csrf_field() ?><button class="btn btn-ghost" style="color:var(--danger)">Sterge</button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__.'/_footer.php'; ?>
