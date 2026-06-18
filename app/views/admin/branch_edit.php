<?php $title=$row?'Editare filiala':'Filiala noua'; $active='branches'; require __DIR__.'/_header.php'; $v=fn($k,$d='')=>e($row[$k]??$d);
$bsched = $row && !empty($row['open_hours']) ? json_decode($row['open_hours'], true) : null;
$bschEnabled = !empty($bsched['enabled']);
$bdayNames = [1=>'Luni',2=>'Marti',3=>'Miercuri',4=>'Joi',5=>'Vineri',6=>'Sambata',0=>'Duminica'];
$bdayVal = function($d) use ($bsched){ return $bsched['days'][$d] ?? ($bsched['days'][(string)$d] ?? null); }; ?>
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
    <hr style="border:none;border-top:1px solid var(--line);margin:1.2rem 0">
    <label style="font-weight:700"><input type="checkbox" name="bsched_enabled" id="bschEn" <?= $bschEnabled?'checked':'' ?> style="width:auto;margin-right:.4rem">Orar de functionare al filialei <span class="muted" style="font-weight:400">(nebifat = mereu deschisa)</span></label>
    <p class="muted" style="font-size:.78rem;margin:.3rem 0 .6rem">Limiteaza orarul tuturor serviciilor din filiala (bilete si programari). Un serviciu cu orar propriu mai larg e restrans la acest interval.</p>
    <div id="bschBox" style="margin-top:.4rem;<?= $bschEnabled?'':'display:none' ?>">
      <?php foreach($bdayNames as $d=>$nm): $dv=$bdayVal($d); $on=is_array($dv)&&count($dv)>=2; ?>
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.4rem">
          <label style="width:120px;margin:0;font-weight:600"><input type="checkbox" name="bday_<?= $d ?>_open" <?= $on?'checked':'' ?> style="width:auto;margin-right:.4rem"><?= $nm ?></label>
          <input type="time" name="bday_<?= $d ?>_from" value="<?= e($on?$dv[0]:'09:00') ?>" style="max-width:140px">
          <span class="muted">—</span>
          <input type="time" name="bday_<?= $d ?>_to" value="<?= e($on?$dv[1]:'17:00') ?>" style="max-width:140px">
        </div>
      <?php endforeach; ?>
    </div>
    <script>document.getElementById('bschEn').addEventListener('change',function(e){document.getElementById('bschBox').style.display=e.target.checked?'':'none';});</script>
    <div style="margin-top:1.2rem"><button class="btn btn-primary btn-lg">Salveaza</button></div>
  </div>
</form>
<?php require __DIR__.'/_footer.php'; ?>
