<?php $title='Jurnal audit'; $active='audit'; require __DIR__.'/_header.php';
$pages = max(1, (int)ceil($total / $per));
$actLab = ['create'=>['Creare','#dcfce7','#166534'],'update'=>['Modificare','#dbeafe','#1e40af'],'delete'=>['Stergere','#fee2e2','#b91c1c'],
  'duplicate'=>['Duplicare','#e0e7ff','#3730a3'],'reset'=>['Resetare','#fef3c7','#92400e'],'regenerate'=>['Regenerare','#fef3c7','#92400e']];
?>
<div class="topbar"><h1>Jurnal audit</h1><span class="muted" style="font-size:.85rem"><?= (int)$total ?> inregistrari</span></div>
<p class="muted" style="margin-top:-.6rem;max-width:680px">Cine, ce și când a modificat în administrare (creare/modificare/ștergere). Util pentru securitate și depanare.</p>
<div class="card pad">
  <table><thead><tr><th>Data/ora</th><th>Utilizator</th><th>Actiune</th><th>Obiect</th><th>ID</th><th>Detalii</th><th>IP</th></tr></thead><tbody>
  <?php foreach($rows as $r): $a=$actLab[$r['action']]??[ucfirst($r['action']),'#f1f5f9','#475569']; ?>
    <tr>
      <td class="muted" style="white-space:nowrap"><?= e(date('d.m.Y H:i:s', strtotime($r['created_at']))) ?></td>
      <td><?= e($r['user_name'] ?? '—') ?></td>
      <td><span class="pill" style="background:<?= $a[1] ?>;color:<?= $a[2] ?>"><?= e($a[0]) ?></span></td>
      <td><?= e($r['entity'] ?? '—') ?></td>
      <td class="muted"><?= e($r['entity_id'] ?? '—') ?></td>
      <td class="muted"><?= e($r['details'] ?? '') ?></td>
      <td class="muted" style="font-size:.8rem"><?= e($r['ip'] ?? '—') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="7" class="muted">Nicio inregistrare inca.</td></tr><?php endif; ?>
  </tbody></table>
  <?php if($pages>1): ?>
    <div style="display:flex;gap:.4rem;align-items:center;margin-top:1rem">
      <?php if($page>1): ?><a class="btn btn-ghost" href="<?= e(url('admin/audit').'?p='.($page-1)) ?>">← Anterior</a><?php endif; ?>
      <span class="muted" style="font-size:.85rem">Pagina <?= $page ?> din <?= $pages ?></span>
      <?php if($page<$pages): ?><a class="btn btn-ghost" href="<?= e(url('admin/audit').'?p='.($page+1)) ?>">Urmator →</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
