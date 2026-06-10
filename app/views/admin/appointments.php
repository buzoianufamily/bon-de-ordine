<?php $title='Programari'; $active='appointments'; require __DIR__.'/_header.php';
$isWeek = ($viewMode ?? 'day') === 'week';
$step = $isWeek ? 7 : 1;
$prev = date('Y-m-d', strtotime($date.' -'.$step.' day')); $next = date('Y-m-d', strtotime($date.' +'.$step.' day'));
$qs = fn($d, $v) => e(url('admin/appointments').'?'.http_build_query(['date'=>$d,'branch'=>$branch,'view'=>$v]));
$statusMap=['booked'=>['Confirmata','#14342433','#4ade80'],'checked_in'=>['Check-in','#172033','#7da2ff'],'cancelled'=>['Anulata','#3a1d1d','#fca5a5'],'no_show'=>['Neprezentat','#1c2029','#9aa3b2']]; ?>
<div class="topbar">
  <h1>Programari</h1>
  <a class="btn" target="_blank" href="<?= e(url('book')) ?>">🔗 Pagina publica de programare</a>
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

<?php else: ?>
<div class="row" style="align-items:flex-start">
  <div class="card pad" style="flex:2;min-width:340px">
    <h3 style="margin-top:0">Programarile zilei (<?= count($rows) ?>)</h3>
    <table><thead><tr><th>Ora</th><th>Serviciu</th><th>Client</th><th>Status</th><th>Bilet</th><th></th></tr></thead><tbody>
    <?php foreach($rows as $a): $st=$statusMap[$a['status']]??['—','#1c2029','#9aa3b2']; ?>
      <tr>
        <td><strong><?= date('H:i',strtotime($a['slot_start'])) ?></strong></td>
        <td><span class="tag" style="background:<?= e($a['color']) ?>;width:20px;height:20px;font-size:.7rem"><?= e($a['prefix']) ?></span> <?= e($a['service_name']) ?></td>
        <td><?= e($a['customer_name'] ?: '—') ?><?= $a['customer_phone']?'<br><span class="muted" style="font-size:.78rem">'.e($a['customer_phone']).'</span>':'' ?><?= $a['customer_email']?'<br><span class="muted" style="font-size:.78rem">'.e($a['customer_email']).'</span>':'' ?></td>
        <td><span class="pill" style="background:<?= $st[1] ?>;color:<?= $st[2] ?>"><?= $st[0] ?></span></td>
        <td><?= $a['ticket_token']?'<a href="'.e(url('t/'.$a['ticket_token'])).'" target="_blank"><strong>'.e($a['ticket_label']).'</strong></a>':'<span class="muted">—</span>' ?></td>
        <td style="text-align:right;white-space:nowrap">
          <?php if($a['status']==='booked'): ?>
            <form method="post" action="<?= e(url('admin/appointments/'.$a['id'].'/checkin')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-ghost" style="color:var(--accent)">Check-in</button></form>
            <form method="post" action="<?= e(url('admin/appointments/'.$a['id'].'/cancel')) ?>" style="display:inline" onsubmit="return confirm('Anulezi programarea?')"><?= csrf_field() ?><button class="btn btn-ghost" style="color:var(--danger)">Anuleaza</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$rows): ?><tr><td colspan="6" class="muted">Nicio programare in ziua selectata.</td></tr><?php endif; ?>
    </tbody></table>
  </div>

  <div class="card pad" style="flex:1;min-width:260px">
    <h3 style="margin-top:0">Programare noua (manuala)</h3>
    <?php if(!$services): ?>
      <p class="muted">Niciun serviciu cu programari activate. Activeaza din Servicii → editare → sectiunea Programari.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('admin/appointments')) ?>"><?= csrf_field() ?>
      <div class="field"><label>Serviciu</label><select name="service_id" required>
        <?php foreach($services as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['prefix'].' · '.$s['name']) ?></option><?php endforeach; ?>
      </select></div>
      <div class="row"><div class="field"><label>Data</label><input type="date" name="date" value="<?= e($date) ?>" required></div>
        <div class="field"><label>Ora</label><input type="time" name="time" required></div></div>
      <div class="field"><label>Nume client</label><input name="name"></div>
      <div class="field"><label>Telefon</label><input name="phone" type="tel"></div>
      <div class="field"><label>Email (optional — primeste confirmarea)</label><input name="email" type="email"></div>
      <button class="btn btn-primary" style="width:100%">Adauga programare</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__.'/_footer.php'; ?>
