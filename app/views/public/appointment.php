<?php $title='Programarea mea'; require __DIR__.'/_head.php';
$ts=strtotime($a['slot_start']); $endts=strtotime($a['slot_end']??$a['slot_start']);
$zile=['Duminica','Luni','Marti','Miercuri','Joi','Vineri','Sambata'];
$luni=['ianuarie','februarie','martie','aprilie','mai','iunie','iulie','august','septembrie','octombrie','noiembrie','decembrie'];
$dateLabel=$zile[(int)date('w',$ts)].', '.(int)date('j',$ts).' '.$luni[(int)date('n',$ts)-1].' '.date('Y',$ts);
$statusMap=['booked'=>['Confirmata','#17E58F'],'checked_in'=>['Check-in efectuat','#2563eb'],'cancelled'=>['Anulata','#FF3A33'],'no_show'=>['Neprezentat','#A5A5A5']];
$now=time(); $canCheckin = $a['status']==='booked' && $now >= $ts-1800 && $now <= $endts+600;
$st=$statusMap[$a['status']]??['—','#888']; ?>
<body><div class="vt"><div class="vt-card">
  <div class="muted"><?= e($a['branch_name']) ?></div>
  <h2 style="margin:.2rem 0 0"><?= e($a['service_name']) ?></h2>
  <div class="vt-num" style="font-size:2.4rem;color:<?= e($a['color']) ?>"><?= date('H:i',$ts) ?></div>
  <div class="muted"><?= e($dateLabel) ?></div>
  <div class="vt-status" style="background:<?= $st[1] ?>;color:#06210f"><?= $st[0] ?></div>
  <?php foreach(get_flashes() as $f): ?><div style="color:#fca5a5;margin:.5rem 0;font-size:.9rem"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <?php if($a['ticket_token']): ?>
    <a class="btn btn-primary btn-lg" style="margin-top:1rem;width:100%" href="<?= e(url('t/'.$a['ticket_token'])) ?>">Vezi biletul →</a>
  <?php elseif($canCheckin): ?>
    <form method="post" action="<?= e(url('a/'.$a['public_token'].'/checkin')) ?>" style="margin-top:1rem">
      <button class="btn btn-primary btn-lg" style="width:100%">Check-in (am ajuns)</button>
    </form>
  <?php elseif($a['status']==='booked'): ?>
    <p class="muted" style="margin-top:1rem;font-size:.85rem">Check-in-ul devine disponibil cu 30 de minute inainte de ora programata.</p>
  <?php endif; ?>
  <?php if($a['status']==='booked'): ?>
    <form method="post" action="<?= e(url('a/'.$a['public_token'].'/cancel')) ?>" style="margin-top:.6rem" data-confirm="Anulezi programarea? Locul se eliberează pentru alți clienți.">
      <button class="btn btn-ghost" style="width:100%">Anulează programarea</button>
    </form>
  <?php endif; ?>
  <p class="muted" style="font-size:.76rem;margin-top:1.2rem">Pastreaza acest link — il poti redeschide oricand pentru status.</p>
</div></div></body></html>
