<?php $title=$row?'Editare ghiseu':'Ghiseu nou'; $active='counters'; require __DIR__.'/_header.php'; $v=fn($k,$d='')=>e($row[$k]??$d); ?>
<div class="topbar">
  <div>
    <div class="crumb"><a href="<?= e(url('admin/counters')) ?>">Ghisee</a> › <?= $row?e($row['name']):'Ghiseu nou' ?></div>
    <h1 style="margin:.1rem 0"><?= $row?'Editare ghiseu':'Ghiseu nou' ?></h1>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('admin/counters')) ?>">← Inapoi</a>
</div>
<form method="post" action="<?= e(url('admin/counters')) ?>"><?= csrf_field() ?>
  <?php if($row): ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><?php endif; ?>
  <div class="card pad">

    <div class="formsec">
      <div class="sech"><span class="ic">▤</span><div><h3>Date ghiseu</h3><p>Identificarea si starea ghiseului.</p></div></div>
      <div class="secb">
        <div class="field"><label>Filiala</label><select name="branch_id">
          <?php $bp=(int)($row['branch_id']??($_GET['branch']??1)); foreach($branches as $bx): ?>
            <option value="<?= (int)$bx['id'] ?>" <?= $bp===(int)$bx['id']?'selected':'' ?>><?= e($bx['name']) ?></option>
          <?php endforeach; ?></select></div>
        <div class="row">
          <div class="field" style="flex:0 0 130px"><label>Cod</label><input name="code" value="<?= $v('code') ?>" placeholder="B1" maxlength="16" required></div>
          <div class="field" style="flex:2"><label>Nume</label><input name="name" value="<?= $v('name') ?>" placeholder="Birou 1" maxlength="50" required></div>
        </div>
        <div class="row">
          <div class="field"><label>Status</label><select name="status">
            <?php foreach(['closed'=>'Inchis','open'=>'Deschis','paused'=>'Pauza'] as $k=>$lab): ?>
              <option value="<?= $k ?>" <?= ($row['status']??'closed')===$k?'selected':'' ?>><?= $lab ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Prioritate ghiseu</label><input type="number" name="priority" value="<?= $v('priority','0') ?>"></div>
        </div>
      </div>
    </div>

    <div class="formsec">
      <div class="sech"><span class="ic">◆</span><div><h3>Servicii</h3><p>Ce servicii deserveste acest ghiseu.</p></div></div>
      <div class="secb">
        <label class="switch"><input type="checkbox" name="all_services" id="allsvc" <?= ($row['all_services']??1)?'checked':'' ?>><span class="track"></span> Deserveste toate serviciile</label>
        <div class="field" id="svcbox" style="margin-top:.9rem;<?= ($row['all_services']??1)?'display:none':'' ?>">
          <label>Servicii alocate</label>
          <div style="display:flex;flex-wrap:wrap;gap:.5rem">
            <?php foreach($services as $s): ?>
              <label class="pill" style="background:color-mix(in srgb,var(--ink) 8%,transparent);cursor:pointer;font-weight:600"><input type="checkbox" name="services[]" value="<?= (int)$s['id'] ?>" <?= in_array($s['id'],$selected)?'checked':'' ?> style="margin-right:.3rem"><?= e($s['prefix'].' '.$s['name']) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <button class="btn btn-primary btn-lg">Salveaza</button>
  </div>
</form>
<script>document.getElementById('allsvc').addEventListener('change',e=>{document.getElementById('svcbox').style.display=e.target.checked?'none':''});</script>
<?php require __DIR__.'/_footer.php'; ?>
