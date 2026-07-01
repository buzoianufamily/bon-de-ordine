<?php $title='Roluri'; $active='roles'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Roluri si permisiuni</h1></div>
<p class="muted" style="margin-top:-.6rem">Adminul are acces complet (necofigurabil). Bifeaza ce poate accesa un <strong>Manager</strong>. Operatorul are acces doar la terminalul de ghiseu.</p>
<form method="post" action="<?= e(url('admin/roles')) ?>"><?= csrf_field() ?>
  <div class="card pad">
    <table><thead><tr><th>Sectiune</th><th style="text-align:center">Admin</th><th style="text-align:center">Manager</th><th style="text-align:center">Operator</th></tr></thead><tbody>
    <?php foreach($areas as $key=>$label): ?>
      <tr>
        <td><strong><?= e($label) ?></strong></td>
        <td style="text-align:center;color:var(--ok);font-weight:800">✓</td>
        <td style="text-align:center"><input type="checkbox" name="manager[<?= e($key) ?>]" <?= $managerCan[$key]?'checked':'' ?> style="width:auto;transform:scale(1.2)"></td>
        <td style="text-align:center;color:#5b6270">—</td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
    <button class="btn btn-primary btn-lg" style="margin-top:1.2rem">Salveaza permisiunile</button>
  </div>
</form>
<?php require __DIR__.'/_footer.php'; ?>
