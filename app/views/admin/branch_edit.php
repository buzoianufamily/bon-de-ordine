<?php $title=$row?'Editare filiala':'Filiala noua'; $active='branches'; require __DIR__.'/_header.php'; $v=fn($k,$d='')=>e($row[$k]??$d); ?>
<div class="topbar"><h1><?= $row?'Editare filiala':'Filiala noua' ?></h1><a class="btn btn-ghost" href="<?= e(url('admin/branches')) ?>">← Inapoi</a></div>
<form method="post" action="<?= e(url('admin/branches')) ?>"><?= csrf_field() ?>
  <?php if($row): ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><?php endif; ?>
  <div class="card pad" style="max-width:620px">
    <div class="field"><label>Nume filiala</label><input name="name" value="<?= $v('name') ?>" placeholder="Sediu Central" required></div>
    <div class="row">
      <div class="field"><label>Oras</label><input name="city" value="<?= $v('city') ?>" placeholder="Bucuresti"></div>
      <div class="field"><label>Tara</label><input name="country" value="<?= $v('country','Romania') ?>"></div>
    </div>
    <div class="field"><label>Adresa</label><input name="address" value="<?= $v('address') ?>"></div>
    <div class="row">
      <div class="field"><label>Fus orar</label><input name="timezone" value="<?= $v('timezone','Europe/Bucharest') ?>"></div>
      <div class="field" style="display:flex;align-items:flex-end"><label style="margin:0"><input type="checkbox" name="active" <?= ($row['active']??1)?'checked':'' ?> style="width:auto"> Activa</label></div>
    </div>
    <button class="btn btn-primary btn-lg">Salveaza</button>
  </div>
</form>
<?php require __DIR__.'/_footer.php'; ?>
