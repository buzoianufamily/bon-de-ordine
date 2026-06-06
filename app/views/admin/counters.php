<?php $title='Ghisee'; $active='counters'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Ghisee</h1><a class="btn btn-primary" href="<?= e(url('admin/counters/new')) ?>">+ Ghiseu nou</a></div>
<div class="card pad">
  <table><thead><tr><th>Cod</th><th>Nume</th><th>Servicii</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach($rows as $r): ?>
    <tr><td><strong><?= e($r['code']) ?></strong></td><td><?= e($r['name']) ?> <span class="muted" style="font-size:.74rem">· 🏢 <?= e($r['branch_name']) ?></span></td>
      <td class="muted"><?= $r['all_services']?'Toate':($r['svc_count'].' alocate') ?></td>
      <td><span class="pill" style="background:#eef2ff;color:#3730a3"><?= e($r['status']) ?></span></td>
      <td style="text-align:right;white-space:nowrap">
        <a class="btn btn-ghost" href="<?= e(url('admin/counters/'.$r['id'])) ?>">Editeaza</a>
        <form method="post" action="<?= e(url('admin/counters/'.$r['id'].'/delete')) ?>" style="display:inline" onsubmit="return confirm('Stergi ghiseul?')"><?= csrf_field() ?><button class="btn btn-ghost" style="color:var(--danger)">Sterge</button></form>
      </td></tr>
  <?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="5" class="muted">Niciun ghiseu.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php require __DIR__.'/_footer.php'; ?>
