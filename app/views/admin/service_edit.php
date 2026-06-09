<?php $title=($row?'Editare':'Serviciu nou'); $active='services'; require __DIR__.'/_header.php';
$v=fn($k,$d='')=>e($row[$k]??$d);
$sched = $row && !empty($row['active_hours']) ? json_decode($row['active_hours'], true) : null;
$schEnabled = !empty($sched['enabled']);
$dayNames = [1=>'Luni',2=>'Marti',3=>'Miercuri',4=>'Joi',5=>'Vineri',6=>'Sambata',0=>'Duminica'];
$dayVal = function($d) use ($sched){ return $sched['days'][$d] ?? ($sched['days'][(string)$d] ?? null); };
$i18nText='';
if ($row && !empty($row['i18n'])) { $ti=json_decode($row['i18n'],true);
  if (is_array($ti)) foreach ($ti as $lg=>$vv) $i18nText .= $lg.' | '.($vv['name']??'').' | '.($vv['description']??'')."\n"; } ?>
<div class="topbar"><h1><?= $row?'Editare serviciu':'Serviciu nou' ?></h1><a class="btn btn-ghost" href="<?= e(url('admin/services')) ?>">← Inapoi</a></div>
<form method="post" action="<?= e(url('admin/services')) ?>"><?= csrf_field() ?>
  <?php if($row): ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><?php endif; ?>
  <div class="card pad" style="max-width:760px">
    <div class="field"><label>Filiala</label><select name="branch_id">
      <?php $bp=(int)($row['branch_id']??($_GET['branch']??1)); foreach($branches as $bx): ?>
        <option value="<?= (int)$bx['id'] ?>" <?= $bp===(int)$bx['id']?'selected':'' ?>><?= e($bx['name']) ?></option>
      <?php endforeach; ?></select></div>
    <div class="row">
      <div class="field" style="flex:0 0 110px"><label>Prefix</label><input name="prefix" maxlength="3" value="<?= $v('prefix','A') ?>" required></div>
      <div class="field" style="flex:2"><label>Nume serviciu</label><input name="name" value="<?= $v('name') ?>" required></div>
      <div class="field" style="flex:0 0 130px"><label>Culoare</label><input type="color" name="color" value="<?= $v('color','#2563eb') ?>"></div>
    </div>
    <div class="field"><label>Descriere (optional)</label><input name="description" value="<?= $v('description') ?>"></div>
    <div class="row">
      <div class="field"><label>De la nr.</label><input type="number" name="num_from" value="<?= $v('num_from','1') ?>"></div>
      <div class="field"><label>Pana la nr.</label><input type="number" name="num_to" value="<?= $v('num_to','999') ?>"></div>
      <div class="field"><label>Cifre (001=3)</label><input type="number" name="pad_length" value="<?= $v('pad_length','3') ?>"></div>
      <div class="field"><label>Status</label><select name="status"><option value="active" <?= ($row['status']??'active')==='active'?'selected':'' ?>>Activ</option><option value="inactive" <?= ($row['status']??'')==='inactive'?'selected':'' ?>>Inactiv</option></select></div>
    </div>
    <div class="row" style="align-items:center">
      <label style="flex:1"><input type="checkbox" name="include_zeros" <?= ($row['include_zeros']??1)?'checked':'' ?> style="width:auto"> Include zerouri (C001)</label>
      <label style="flex:1"><input type="checkbox" name="allow_priority" <?= ($row['allow_priority']??0)?'checked':'' ?> style="width:auto"> Permite bilet prioritar</label>
      <label style="flex:1"><input type="checkbox" name="terminate_on_call" <?= ($row['terminate_on_call']??0)?'checked':'' ?> style="width:auto"> Inchide automat la apel</label>
    </div>
    <div class="row">
      <div class="field"><label>Max. la rand (0=nelimitat)</label><input type="number" name="max_queued" value="<?= $v('max_queued','0') ?>"></div>
      <div class="field"><label>Ordine afisare</label><input type="number" name="sort_order" value="<?= $v('sort_order','0') ?>"></div>
      <div class="field"><label>Tinta asteptare (sec)</label><input type="number" name="kpi_wait_sec" value="<?= $v('kpi_wait_sec','600') ?>"></div>
      <div class="field"><label>Tinta servire (sec)</label><input type="number" name="kpi_service_sec" value="<?= $v('kpi_service_sec','300') ?>"></div>
    </div>
    <hr style="border:none;border-top:1px solid var(--line);margin:1.2rem 0">
    <div class="field"><label>Grup (optional, pentru dispenser)</label>
      <select name="group_id"><option value="0">— fara grup —</option>
        <?php foreach(($groups??[]) as $gr): ?><option value="<?= (int)$gr['id'] ?>" <?= (int)($row['group_id']??0)===(int)$gr['id']?'selected':'' ?>><?= e($gr['branch_name'].' — '.$gr['name']) ?></option><?php endforeach; ?>
      </select>
      <span class="muted" style="font-size:.78rem;display:block;margin-top:.3rem">Creezi grupuri in meniul „Grupuri".</span>
    </div>
    <div class="field"><label>Formular la emitere bilet (optional)</label>
      <select name="form_id"><option value="0">— fara formular —</option>
        <?php foreach(($forms??[]) as $fo): ?><option value="<?= (int)$fo['id'] ?>" <?= (int)($row['form_id']??0)===(int)$fo['id']?'selected':'' ?>><?= e($fo['name']) ?></option><?php endforeach; ?>
      </select>
      <span class="muted" style="font-size:.78rem;display:block;margin-top:.3rem">Daca alegi un formular, clientul completeaza datele inainte de a primi bonul. Creezi formulare in meniul „Formulare".</span>
    </div>
    <hr style="border:none;border-top:1px solid var(--line);margin:1.2rem 0">
    <div class="field"><label>Traduceri nume serviciu (optional, pentru dispenser multi-limba)</label>
      <textarea name="svc_i18n" rows="3" placeholder="en | Cashier | Pay here&#10;de | Kasse | Hier bezahlen"><?= e($i18nText) ?></textarea>
      <span class="muted" style="font-size:.78rem;display:block;margin-top:.3rem">Cate o linie per limba, in formatul <code>cod | nume | descriere</code> (descrierea e optionala). Limbile se activeaza din Setari → General.</span>
    </div>
    <hr style="border:none;border-top:1px solid var(--line);margin:1.2rem 0">
    <label style="font-weight:700"><input type="checkbox" name="sched_enabled" id="schEn" <?= $schEnabled?'checked':'' ?> style="width:auto;margin-right:.4rem">Program de functionare <span class="muted" style="font-weight:400">(nebifat = mereu deschis)</span></label>
    <div id="schBox" style="margin-top:.8rem;<?= $schEnabled?'':'display:none' ?>">
      <?php foreach($dayNames as $d=>$nm): $dv=$dayVal($d); $on=is_array($dv)&&count($dv)>=2; ?>
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.4rem">
          <label style="width:120px;margin:0;font-weight:600"><input type="checkbox" name="day_<?= $d ?>_open" <?= $on?'checked':'' ?> style="width:auto;margin-right:.4rem"><?= $nm ?></label>
          <input type="time" name="day_<?= $d ?>_from" value="<?= e($on?$dv[0]:'09:00') ?>" style="max-width:140px">
          <span class="muted">—</span>
          <input type="time" name="day_<?= $d ?>_to" value="<?= e($on?$dv[1]:'17:00') ?>" style="max-width:140px">
        </div>
      <?php endforeach; ?>
    </div>
    <script>document.getElementById('schEn').addEventListener('change',function(e){document.getElementById('schBox').style.display=e.target.checked?'':'none';});</script>
    <hr style="border:none;border-top:1px solid var(--line);margin:1.2rem 0">
    <label style="font-weight:700"><input type="checkbox" name="appt_enabled" id="apptEn" <?= !empty($row['appt_enabled'])?'checked':'' ?> style="width:auto;margin-right:.4rem">Programari online <span class="muted" style="font-weight:400">(clientul poate rezerva o ora)</span></label>
    <div id="apptBox" class="row" style="margin-top:.8rem;<?= !empty($row['appt_enabled'])?'':'display:none' ?>">
      <div class="field"><label>Durata slot (minute)</label><input type="number" name="appt_slot_min" value="<?= (int)($row['appt_slot_min']??15) ?>" min="5" step="5"></div>
      <div class="field"><label>Locuri per slot</label><input type="number" name="appt_capacity" value="<?= (int)($row['appt_capacity']??1) ?>" min="1"></div>
    </div>
    <p class="muted" id="apptHint" style="font-size:.78rem;<?= !empty($row['appt_enabled'])?'':'display:none' ?>">Intervalele se genereaza din programul de functionare (sau 09:00–17:00 implicit). Pagina publica: <code><?= e(url('book')) ?></code></p>
    <script>document.getElementById('apptEn').addEventListener('change',function(e){document.getElementById('apptBox').style.display=e.target.checked?'flex':'none';document.getElementById('apptHint').style.display=e.target.checked?'':'none';});</script>

    <div style="margin-top:1.2rem"><button class="btn btn-primary btn-lg">Salveaza</button></div>
  </div>
</form>
<?php require __DIR__.'/_footer.php'; ?>
