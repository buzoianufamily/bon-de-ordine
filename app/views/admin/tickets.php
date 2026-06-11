<?php $title='Bilete'; $active='tickets'; require __DIR__.'/_header.php';
$st=['waiting'=>['La rand','#fef3c7','#92400e'],'called'=>['Apelat','#dbeafe','#1e40af'],'serving'=>['In servire','#dbeafe','#1e40af'],
  'served'=>['Servit','#dcfce7','#166534'],'no_show'=>['Neprezentat','#f1f5f9','#64748b'],'cancelled'=>['Anulat','#fee2e2','#b91c1c'],'transferred'=>['Transferat','#e0e7ff','#3730a3']]; ?>
<div class="topbar"><h1>Bilete</h1>
  <form method="post" action="<?= e(url('admin/tickets/reset')) ?>" data-confirm="Resetezi bonurile? Se STERG TOATE biletele (coada + istoric + statistici) si numerotarea reincepe de la 0. Actiunea NU poate fi anulata." style="display:flex;gap:.4rem;align-items:center">
    <?= csrf_field() ?>
    <?php if(count($branches)>1): ?><select name="branch" style="width:auto"><option value="0">Toate filialele</option><?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <button class="btn btn-danger">↺ Reset bonuri</button>
  </form>
</div>
<form method="get" id="tkFilter" action="<?= e(url('admin/tickets')) ?>" class="card pad" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1.2rem">
  <div class="field" style="margin:0"><label>Data</label><input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()"></div>
  <div class="field" style="margin:0"><label>Status</label><select name="status" onchange="this.form.submit()" style="width:auto">
    <option value="">Toate</option>
    <?php foreach($st as $k=>$v): ?><option value="<?= e($k) ?>" <?= ($status??'')===$k?'selected':'' ?>><?= e($v[0]) ?></option><?php endforeach; ?>
  </select></div>
  <div class="field" style="margin:0"><label>Serviciu</label><select name="service" onchange="this.form.submit()" style="width:auto">
    <option value="0">Toate</option>
    <?php foreach(($services??[]) as $sv): ?><option value="<?= (int)$sv['id'] ?>" <?= (int)($service??0)===(int)$sv['id']?'selected':'' ?>><?= e($sv['prefix'].' · '.$sv['name']) ?></option><?php endforeach; ?>
  </select></div>
  <?php if(count($branches)>1): ?>
  <div class="field" style="margin:0"><label>Filiala</label><select name="fbranch" onchange="this.form.submit()" style="width:auto">
    <option value="0">Toate</option>
    <?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)($fbranch??0)===(int)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
  </select></div>
  <?php endif; ?>
  <div class="field" style="margin:0;flex:1;min-width:160px"><label>Cauta bon</label><input type="text" name="q" value="<?= e($qstr??'') ?>" placeholder="ex: A012"></div>
  <button class="btn btn-primary">Aplica</button>
  <a class="btn btn-ghost" href="<?= e(url('admin/tickets')) ?>" id="tkClear">Reset filtre</a>
</form>
<div class="card pad">
  <div class="muted" style="font-size:.85rem;margin-bottom:.6rem"><?= count($rows) ?> bilete<?= count($rows)>=500?' (primele 500)':'' ?></div>
  <table><thead><tr><th>Bon</th><th>Serviciu</th><th>Status</th><th>Ghiseu</th><th>Emis</th><th>Apelat</th><th>Canal</th></tr></thead><tbody>
  <?php foreach($rows as $t): $s=$st[$t['status']]??['?','#eee','#555']; ?>
    <tr><td><span class="tag" style="background:<?= e($t['color']) ?>"><?= e($t['label'][0]) ?></span> <strong><?= e($t['label']) ?></strong><?= $t['priority']?' ★':'' ?><?php if(!empty($t['form_data']) && ($fd=json_decode($t['form_data'],true)) && is_array($fd)): ?> <span title="<?= e(implode(' · ', array_map(fn($x)=>($x['label']??'').': '.($x['value']??''), $fd))) ?>" style="cursor:help">📋</span><?php endif; ?></td>
      <td><?= e($t['service_name']) ?></td>
      <td><span class="pill" style="background:<?= $s[1] ?>;color:<?= $s[2] ?>"><?= $s[0] ?></span></td>
      <td><?= e($t['counter_code']??'—') ?></td>
      <td class="muted"><?= date('H:i',strtotime($t['issued_at'])) ?></td>
      <td class="muted"><?= $t['called_at']?date('H:i',strtotime($t['called_at'])):'—' ?></td>
      <td class="muted"><?= e($t['channel']) ?></td></tr>
  <?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="7" class="muted">Niciun bilet cu filtrele selectate.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<script>
/* filtrele Bilete se retin per utilizator (localStorage) */
(function(){
  var f=document.getElementById('tkFilter'); if(!f)return;
  var search=location.search.replace(/^\?/,'');
  try{
    if(!search){ var saved=localStorage.getItem('tickets_filter'); if(saved){ location.replace(location.pathname+'?'+saved); return; } }
    else { localStorage.setItem('tickets_filter', search); }
  }catch(e){}
  var clr=document.getElementById('tkClear'); if(clr) clr.addEventListener('click',function(){ try{localStorage.removeItem('tickets_filter');}catch(e){} });
})();
</script>
<?php require __DIR__.'/_footer.php'; ?>
