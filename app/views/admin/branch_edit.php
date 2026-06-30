<?php $title=$row?'Editare filiala':'Filiala noua'; $active='branches'; require __DIR__.'/_header.php'; $v=fn($k,$d='')=>e($row[$k]??$d);
$bsched = $row && !empty($row['open_hours']) ? json_decode($row['open_hours'], true) : null;
$bschEnabled = !empty($bsched['enabled']);
$bdayNames = [1=>'Luni',2=>'Marti',3=>'Miercuri',4=>'Joi',5=>'Vineri',6=>'Sambata',0=>'Duminica'];
$bdayVal = function($d) use ($bsched){ return $bsched['days'][$d] ?? ($bsched['days'][(string)$d] ?? null); }; ?>
<div class="topbar">
  <div>
    <div class="crumb"><a href="<?= e(url('admin/branches')) ?>">Filiale</a> › <?= $row?e($row['name']):'Filiala noua' ?></div>
    <h1 style="margin:.1rem 0"><?= $row?'Editare filiala':'Filiala noua' ?></h1>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('admin/branches')) ?>">← Inapoi</a>
</div>
<form method="post" action="<?= e(url('admin/branches')) ?>"><?= csrf_field() ?>
  <?php if($row): ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><?php endif; ?>
  <div class="card pad" style="max-width:820px">

    <div class="formsec">
      <div class="sech"><span class="ic">🏢</span><div><h3>Date filiala</h3><p>Denumire, locatie si fus orar.</p></div></div>
      <div class="secb">
        <div class="field"><label>Nume filiala</label><input name="name" value="<?= $v('name') ?>" placeholder="Sediu Central" maxlength="120" required></div>
        <div class="row">
          <div class="field"><label>Oras</label><input name="city" value="<?= $v('city') ?>" placeholder="Bucuresti" maxlength="80"></div>
          <div class="field"><label>Tara</label><input name="country" value="<?= $v('country','Romania') ?>" maxlength="80"></div>
        </div>
        <div class="field"><label>Adresa</label><input name="address" value="<?= $v('address') ?>" maxlength="255"></div>
        <div class="field"><label>Fus orar</label><input name="timezone" value="<?= $v('timezone','Europe/Bucharest') ?>"></div>
        <label class="switch" style="margin-top:.3rem"><input type="checkbox" name="active" <?= ($row['active']??1)?'checked':'' ?>><span class="track"></span> Filiala activa</label>
      </div>
    </div>

    <div class="formsec">
      <div class="sech"><span class="ic">🕒</span><div><h3>Orar de functionare</h3><p>Plic peste orarul tuturor serviciilor din filiala. Nebifat = mereu deschisa.</p></div></div>
      <div class="secb">
        <label class="switch"><input type="checkbox" name="bsched_enabled" id="bschEn" <?= $bschEnabled?'checked':'' ?>><span class="track"></span> Activeaza orarul filialei</label>
        <div id="bschBox" style="margin-top:.9rem;<?= $bschEnabled?'':'display:none' ?>">
          <?php foreach($bdayNames as $d=>$nm): $dv=$bdayVal($d); $on=is_array($dv)&&count($dv)>=2; ?>
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.4rem">
              <label style="width:120px;margin:0;font-weight:600"><input type="checkbox" name="bday_<?= $d ?>_open" <?= $on?'checked':'' ?> style="margin-right:.4rem"><?= $nm ?></label>
              <input type="time" name="bday_<?= $d ?>_from" value="<?= e($on?$dv[0]:'09:00') ?>" style="max-width:140px">
              <span class="muted">—</span>
              <input type="time" name="bday_<?= $d ?>_to" value="<?= e($on?$dv[1]:'17:00') ?>" style="max-width:140px">
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <button class="btn btn-primary btn-lg">Salveaza</button>
  </div>
</form>
<script>document.getElementById('bschEn').addEventListener('change',function(e){document.getElementById('bschBox').style.display=e.target.checked?'':'none';});</script>
<?php require __DIR__.'/_footer.php'; ?>
