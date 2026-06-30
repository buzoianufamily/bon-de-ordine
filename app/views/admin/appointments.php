<?php $title='Programari'; $active='appointments'; require __DIR__.'/_header.php';
$isWeek = ($viewMode ?? 'day') === 'week';
$step = $isWeek ? 7 : 1;
$prev = date('Y-m-d', strtotime($date.' -'.$step.' day')); $next = date('Y-m-d', strtotime($date.' +'.$step.' day'));
$qs = fn($d, $v) => e(url('admin/appointments').'?'.http_build_query(['date'=>$d,'branch'=>$branch,'view'=>$v]));
$statusMap=['booked'=>['Confirmata','#14342433','#4ade80'],'checked_in'=>['Check-in','#172033','#7da2ff'],'cancelled'=>['Anulata','#3a1d1d','#fca5a5'],'no_show'=>['Neprezentat','#1c2029','#9aa3b2']]; ?>
<div class="topbar">
  <h1>Programari</h1>
  <div style="display:flex;gap:.5rem">
    <a class="btn" href="<?= e(url('admin/appointments/export').'?'.http_build_query(array_filter(['date'=>$date,'branch'=>$branch,'view'=>$viewMode]))) ?>">⤓ Export CSV</a>
    <a class="btn" target="_blank" href="<?= e(url('book')) ?>">🔗 Pagina publica de programare</a>
  </div>
</div>

<form method="get" action="<?= e(url('admin/appointments')) ?>" class="card pad" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1.2rem">
  <input type="hidden" name="view" value="<?= e($viewMode ?? 'day') ?>">
  <a class="btn btn-ghost" href="<?= $qs($prev, $viewMode ?? 'day') ?>">← <?= $isWeek?'Saptamana':'Zi' ?></a>
  <div class="field" style="margin:0"><label>Data</label><input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()"></div>
  <a class="btn btn-ghost" href="<?= $qs($next, $viewMode ?? 'day') ?>"><?= $isWeek?'Saptamana':'Zi' ?> →</a>
  <div class="field" style="margin:0"><label>Filiala</label><select name="branch" onchange="this.form.submit()">
    <option value="0">Toate</option>
    <?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $branch===(int)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
  </select></div>
  <span class="dvtoggle" style="margin-left:auto">
    <a href="<?= $qs($date,'day') ?>" class="<?= $isWeek?'':'on' ?>" style="padding:.32rem .65rem;font-size:.78rem;font-weight:700;text-decoration:none;display:inline-block;color:<?= $isWeek?'var(--muted)':'var(--accent)' ?>">📋 Zi</a>
    <a href="<?= $qs($date,'week') ?>" class="<?= $isWeek?'on':'' ?>" style="padding:.32rem .65rem;font-size:.78rem;font-weight:700;text-decoration:none;display:inline-block;color:<?= $isWeek?'var(--accent)':'var(--muted)' ?>">📅 Saptamana</a>
  </span>
</form>

<?php if($isWeek):
  // ---- vedere saptamanala: coloane = zile, randuri = ore ----
  $days = []; for($i=0;$i<7;$i++) $days[] = date('Y-m-d', strtotime($weekStart.' +'.$i.' days'));
  $dayNames = ['Luni','Marti','Miercuri','Joi','Vineri','Sambata','Duminica'];
  $byCell = []; $minH = 8; $maxH = 17;
  foreach($rows as $a){
    $d = substr($a['slot_start'],0,10); $h = (int)date('G', strtotime($a['slot_start']));
    $byCell[$d][$h][] = $a; $minH = min($minH,$h); $maxH = max($maxH,$h);
  }
  $today = date('Y-m-d');
?>
<div class="card pad" style="overflow-x:auto">
  <h3 style="margin-top:0"><?= e(date('d.m', strtotime($weekStart))) ?> – <?= e(date('d.m.Y', strtotime($weekEnd))) ?> · <?= count($rows) ?> programari</h3>
  <table style="table-layout:fixed;min-width:880px">
    <thead><tr><th style="width:54px"></th>
      <?php foreach($days as $i=>$d): ?>
        <th style="text-align:center;<?= $d===$today?'color:var(--accent)':'' ?>">
          <a href="<?= $qs($d,'day') ?>" style="color:inherit"><?= e($dayNames[$i]) ?><br><span style="font-weight:400"><?= e(date('d.m', strtotime($d))) ?></span></a>
        </th>
      <?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php for($h=$minH;$h<=$maxH;$h++): ?>
      <tr>
        <td class="muted" style="vertical-align:top;font-size:.78rem;white-space:nowrap"><?= sprintf('%02d:00',$h) ?></td>
        <?php foreach($days as $d): ?>
          <td style="vertical-align:top;padding:.3rem .25rem;<?= $d===$today?'background:color-mix(in srgb,var(--accent) 6%,transparent);':'' ?>">
            <?php foreach(($byCell[$d][$h] ?? []) as $a): $st=$statusMap[$a['status']]??['—','#1c2029','#9aa3b2']; ?>
              <a href="<?= $qs($d,'day') ?>" title="<?= e(($a['customer_name']?:'(fara nume)').' · '.$a['service_name'].' · '.$st[0]) ?>"
                 style="display:block;margin:0 0 3px;padding:.25rem .45rem;border-radius:7px;border-left:3px solid <?= e($a['color']) ?>;background:color-mix(in srgb,<?= e($a['color']) ?> 14%,transparent);font-size:.74rem;line-height:1.25;color:var(--ink);<?= $a['status']==='cancelled'?'opacity:.45;text-decoration:line-through;':'' ?>">
                <strong><?= date('H:i',strtotime($a['slot_start'])) ?></strong> <?= e($a['customer_name'] ?: $a['service_name']) ?>
              </a>
            <?php endforeach; ?>
          </td>
        <?php endforeach; ?>
      </tr>
    <?php endfor; ?>
    </tbody>
  </table>
  <?php if(!$rows): ?><p class="muted">Nicio programare in saptamana selectata.</p><?php endif; ?>
</div>

<?php else:
  // ---- vedere zi: master-detail (servicii=filtru in stanga, programari+cautare in dreapta) ----
  $svcCount = []; foreach($rows as $a){ $sid=(int)$a['service_id']; $svcCount[$sid]=($svcCount[$sid]??0)+1; }
?>
<div class="row" style="align-items:flex-start">
  <div class="card pad" style="flex:1;min-width:230px">
    <h3 style="margin-top:0">Servicii</h3>
    <div class="apptsvcs" id="apptSvcs">
      <button type="button" class="apptsvc on" data-svc="all"><span class="nm">Toate serviciile</span><span class="ct"><?= count($rows) ?></span></button>
      <?php foreach($services as $s): $sid=(int)$s['id']; ?>
        <button type="button" class="apptsvc" data-svc="<?= $sid ?>"><span class="tag" style="background:<?= e($s['color']) ?>;width:20px;height:20px;font-size:.7rem"><?= e($s['prefix']) ?></span><span class="nm"><?= e($s['name']) ?></span><span class="ct"><?= (int)($svcCount[$sid]??0) ?></span></button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card pad" style="flex:2.4;min-width:340px">
    <div class="listhead" style="margin-bottom:.7rem">
      <div class="search"><span class="si">🔍</span><input type="text" id="apptSearch" placeholder="Cauta client, telefon, email, bon, serviciu..."></div>
      <button class="btn btn-primary" id="apptNewBtn" type="button">+ Programare noua</button>
    </div>
    <div id="apptNewForm" style="display:none;margin-bottom:1rem;padding:.9rem 1rem;border:1px dashed var(--line);border-radius:12px">
      <?php if(!$services): ?>
        <p class="muted" style="margin:0">Niciun serviciu cu programari activate. Activeaza din Servicii → editare → sectiunea Programari.</p>
      <?php else: ?>
      <form method="post" action="<?= e(url('admin/appointments')) ?>"><?= csrf_field() ?>
        <div class="formgrid">
          <div class="field"><label>Serviciu</label><select name="service_id" required>
            <?php foreach($services as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['prefix'].' · '.$s['name']) ?></option><?php endforeach; ?>
          </select></div>
          <div class="field"><label>Data</label><input type="date" name="date" value="<?= e($date) ?>" required></div>
          <div class="field"><label>Ora</label><input type="time" name="time" required></div>
          <div class="field"><label>Nume client</label><input name="name"></div>
          <div class="field"><label>Telefon</label><input name="phone" type="tel"></div>
          <div class="field"><label>Email (optional — primeste confirmarea)</label><input name="email" type="email"></div>
        </div>
        <button class="btn btn-primary" style="margin-top:.7rem">Adauga programare</button>
      </form>
      <?php endif; ?>
    </div>
    <h3 style="margin:0 0 .6rem">Programarile zilei (<span id="apptShown"><?= count($rows) ?></span>)</h3>
    <table><thead><tr><th>Ora</th><th>Serviciu</th><th>Client</th><th>Status</th><th>Bilet</th><th></th></tr></thead><tbody id="apptRows">
    <?php foreach($rows as $a): $st=$statusMap[$a['status']]??['—','#1c2029','#9aa3b2'];
      $hay=mb_strtolower(($a['customer_name']??'').' '.($a['customer_phone']??'').' '.($a['customer_email']??'').' '.$a['service_name'].' '.($a['ticket_label']??'')); ?>
      <tr data-svc="<?= (int)$a['service_id'] ?>" data-search="<?= e($hay) ?>">
        <td><strong><?= date('H:i',strtotime($a['slot_start'])) ?></strong></td>
        <td><span class="tag" style="background:<?= e($a['color']) ?>;width:20px;height:20px;font-size:.7rem"><?= e($a['prefix']) ?></span> <?= e($a['service_name']) ?></td>
        <td><?= e($a['customer_name'] ?: '—') ?><?= $a['customer_phone']?'<br><span class="muted" style="font-size:.78rem">'.e($a['customer_phone']).'</span>':'' ?><?= $a['customer_email']?'<br><span class="muted" style="font-size:.78rem">'.e($a['customer_email']).'</span>':'' ?></td>
        <td><span class="pill" style="background:<?= $st[1] ?>;color:<?= $st[2] ?>"><?= $st[0] ?></span></td>
        <td><?= $a['ticket_token']?'<a href="'.e(url('t/'.$a['ticket_token'])).'" target="_blank"><strong>'.e($a['ticket_label']).'</strong></a>':'<span class="muted">—</span>' ?></td>
        <td style="text-align:right;white-space:nowrap">
          <?php if($a['status']==='booked'): ?>
            <form method="post" action="<?= e(url('admin/appointments/'.$a['id'].'/checkin')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-ghost" style="color:var(--accent)">Check-in</button></form>
            <form method="post" action="<?= e(url('admin/appointments/'.$a['id'].'/cancel')) ?>" style="display:inline" data-confirm="Anulezi programarea?"><?= csrf_field() ?><button class="btn btn-ghost" style="color:var(--danger)">Anuleaza</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$rows): ?><tr class="apptempty"><td colspan="6" class="muted">Nicio programare in ziua selectata.</td></tr><?php endif; ?>
    </tbody></table>
    <p class="muted" id="apptNoMatch" style="display:none;padding:.6rem 0 0">Nicio programare pentru filtrele alese.</p>
  </div>
</div>
<script>
(function(){
  var svcs=document.getElementById('apptSvcs'), search=document.getElementById('apptSearch'),
      rows=Array.prototype.slice.call(document.querySelectorAll('#apptRows tr')),
      shown=document.getElementById('apptShown'), nm=document.getElementById('apptNoMatch'),
      nb=document.getElementById('apptNewBtn'), nf=document.getElementById('apptNewForm');
  var fSvc='all', fQ='';
  function apply(){ var n=0; rows.forEach(function(tr){ if(tr.classList.contains('apptempty'))return;
    var okS=(fSvc==='all')||(tr.getAttribute('data-svc')===fSvc);
    var okQ=!fQ||((tr.getAttribute('data-search')||'').indexOf(fQ)>=0);
    var ok=okS&&okQ; tr.style.display=ok?'':'none'; if(ok)n++; });
    if(shown)shown.textContent=n; if(nm)nm.style.display=(n===0&&rows.length)?'':'none'; }
  if(svcs)svcs.addEventListener('click',function(e){var b=e.target.closest('[data-svc]');if(!b)return;
    fSvc=b.getAttribute('data-svc'); svcs.querySelectorAll('.apptsvc').forEach(function(x){x.classList.toggle('on',x===b);}); apply();});
  if(search)search.addEventListener('input',function(){fQ=this.value.trim().toLowerCase();apply();});
  if(nb)nb.addEventListener('click',function(){nf.style.display=(nf.style.display==='none')?'':'none';});
})();
</script>
<?php endif; ?>

<div class="card pad" style="margin-top:1.2rem">
  <h3 style="margin-top:0">Listă de așteptare <span class="muted" style="font-size:.85rem">(<?= count($waitlist ?? []) ?> în așteptare)</span></h3>
  <?php if(empty($waitlist)): ?>
    <p class="muted" style="margin:0">Nimeni pe lista de așteptare pentru sloturi viitoare. Clienții se pot înscrie când un slot e plin (necesită email activ).</p>
  <?php else: ?>
    <table><thead><tr><th>Slot</th><th>Serviciu</th><th>Client</th><th>Email</th><th></th></tr></thead><tbody>
    <?php foreach($waitlist as $w): ?>
      <tr>
        <td style="white-space:nowrap"><strong><?= e(date('d.m.Y H:i', strtotime($w['slot_start']))) ?></strong></td>
        <td><span class="tag" style="background:<?= e($w['color']) ?>"><?= e($w['prefix']) ?></span> <?= e($w['service_name']) ?><?= $w['branch_name'] ? ' · <span class="muted">'.e($w['branch_name']).'</span>' : '' ?></td>
        <td><?= e($w['customer_name'] ?: '—') ?></td>
        <td class="muted" style="font-size:.85rem"><?= e($w['customer_email']) ?></td>
        <td style="text-align:right"><form method="post" action="<?= e(url('admin/appointments/waitlist-del/'.$w['id'])) ?>" data-confirm="Stergi aceasta intrare din lista de asteptare?"><?= csrf_field() ?><button class="lnk del" style="background:none;border:none;cursor:pointer;color:var(--danger);font-weight:700">Șterge</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
