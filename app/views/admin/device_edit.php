<?php $title=$row?'Editare dispozitiv':'Dispozitiv nou'; $active='devices'; require __DIR__.'/_header.php'; $v=fn($k,$d='')=>e($row[$k]??$d); ?>
<div class="topbar"><h1><?= $row?'Editare dispozitiv':'Dispozitiv nou' ?></h1><a class="btn btn-ghost" href="<?= e(url('admin/devices')) ?>">← Inapoi</a></div>
<form method="post" action="<?= e(url('admin/devices')) ?>"><?= csrf_field() ?>
  <?php if($row): ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><?php endif; ?>
  <div class="card pad" style="max-width:680px">
    <div class="field"><label>Filiala</label><select name="branch_id">
      <?php $bp=(int)($row['branch_id']??($_GET['branch']??1)); foreach($branches as $bx): ?>
        <option value="<?= (int)$bx['id'] ?>" <?= $bp===(int)$bx['id']?'selected':'' ?>><?= e($bx['name']) ?></option>
      <?php endforeach; ?></select></div>
    <?php if($row): ?><p class="muted">Cheie conectare: <code style="font-size:1.1rem;font-weight:800"><?= e($row['connection_key']) ?></code> (generata automat)</p><?php endif; ?>
    <div class="row">
      <div class="field"><label>Tip</label><select name="type">
        <?php foreach(['dispenser'=>'Dispenser bilete','player'=>'Afisaj TV','digital_ticket'=>'Bilet digital QR'] as $k=>$lab): ?>
          <option value="<?= $k ?>" <?= ($row['type']??'dispenser')===$k?'selected':'' ?>><?= $lab ?></option><?php endforeach; ?></select></div>
      <div class="field" style="flex:2"><label>Nume</label><input name="name" value="<?= $v('name') ?>" placeholder="Dispenser intrare" required></div>
    </div>
    <h3 style="margin:.5rem 0 .2rem;font-size:1rem">Printare (doar pt dispenser)</h3>
    <div class="row">
      <div class="field"><label>Mod printare</label><select name="printer_mode" id="pmode">
        <?php foreach(['browser'=>'Browser (test, fara hardware)','network'=>'Retea (Bixolon Ethernet/WiFi)','android'=>'Android (USB/Bluetooth)','none'=>'Fara printare'] as $k=>$lab): ?>
          <option value="<?= $k ?>" <?= ($row['printer_mode']??'browser')===$k?'selected':'' ?>><?= $lab ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>IP imprimanta</label><input name="printer_ip" value="<?= $v('printer_ip') ?>" placeholder="192.168.1.50"></div>
      <div class="field" style="flex:0 0 120px"><label>Port</label><input type="number" name="printer_port" value="<?= $v('printer_port','9100') ?>"></div>
    </div>
    <label style="margin:.5rem 0"><input type="checkbox" name="all_services" id="allsvc" <?= ($row['all_services']??1)?'checked':'' ?> style="width:auto"> Toate serviciile</label>
    <div class="field" id="svcbox" style="<?= ($row['all_services']??1)?'display:none':'' ?>"><label>Servicii afisate</label>
      <div style="display:flex;flex-wrap:wrap;gap:.5rem">
        <?php foreach($services as $s): ?><label class="pill" style="background:#f1f5f9;cursor:pointer"><input type="checkbox" name="services[]" value="<?= (int)$s['id'] ?>" <?= in_array($s['id'],$selected)?'checked':'' ?> style="width:auto;margin-right:.3rem"><?= e($s['prefix'].' '.$s['name']) ?></label><?php endforeach; ?>
      </div>
    </div>
    <button class="btn btn-primary btn-lg">Salveaza</button>
  </div>
</form>
<script>document.getElementById('allsvc').addEventListener('change',e=>{document.getElementById('svcbox').style.display=e.target.checked?'none':''});</script>
<?php require __DIR__.'/_footer.php'; ?>
