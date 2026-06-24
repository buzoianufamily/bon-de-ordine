<?php $lang = $lang ?? 'ro'; $pageLang = $lang; $L = appt_i18n($lang); $days = book_i18n($lang)['days']; $lq = $lang !== 'ro' ? '?lang='.$lang : '';
$title='Programarea mea'; require __DIR__.'/_head.php';
$ts=strtotime($a['slot_start']); $endts=strtotime($a['slot_end']??$a['slot_start']);
$dateLabel=$days[(int)date('w',$ts)].', '.date('d.m.Y',$ts);
$statusColor=['booked'=>'#17E58F','checked_in'=>'#2563eb','cancelled'=>'#FF3A33','no_show'=>'#A5A5A5'];
$now=time(); $canCheckin = $a['status']==='booked' && $now >= $ts-1800 && $now <= $endts+600;
$stLabel=$L['st_'.$a['status']] ?? '—'; $stCol=$statusColor[$a['status']] ?? '#888'; ?>
<body><div class="vt"><div class="vt-card">
  <?= public_lang_bar($lang, url('a/'.$a['public_token'])) ?>
  <div class="muted"><?= e($a['branch_name']) ?></div>
  <h2 style="margin:.2rem 0 0"><?= e($a['service_name']) ?></h2>
  <div class="vt-num" style="font-size:2.4rem;color:<?= e($a['color']) ?>"><?= date('H:i',$ts) ?></div>
  <div class="muted"><?= e($dateLabel) ?></div>
  <div class="vt-status" style="background:<?= $stCol ?>;color:#06210f"><?= e($stLabel) ?></div>
  <?php if($a['status']==='booked' && $ts > $now): ?>
    <div class="muted" id="apCountdown" style="margin-top:.5rem;font-weight:600"></div>
  <?php endif; ?>
  <?php foreach(get_flashes() as $f): ?><div style="color:#fca5a5;margin:.5rem 0;font-size:.9rem"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <?php if($a['ticket_token']): ?>
    <a class="btn btn-primary btn-lg" style="margin-top:1rem;width:100%" href="<?= e(url('t/'.$a['ticket_token']).$lq) ?>"><?= e($L['view_ticket']) ?></a>
  <?php elseif($canCheckin): ?>
    <form method="post" action="<?= e(url('a/'.$a['public_token'].'/checkin').$lq) ?>" style="margin-top:1rem">
      <button class="btn btn-primary btn-lg" style="width:100%"><?= e($L['checkin']) ?></button>
    </form>
  <?php elseif($a['status']==='booked'): ?>
    <p class="muted" style="margin-top:1rem;font-size:.85rem"><?= e($L['checkin_note']) ?></p>
  <?php endif; ?>
  <?php if($a['status']==='booked'): ?>
    <form method="post" action="<?= e(url('a/'.$a['public_token'].'/cancel').$lq) ?>" style="margin-top:.6rem" data-confirm="<?= e($L['cancel_confirm']) ?>">
      <button class="btn btn-ghost" style="width:100%"><?= e($L['cancel']) ?></button>
    </form>
  <?php endif; ?>
  <?php if(in_array($a['status'],['booked','checked_in'],true)): ?>
    <a class="btn btn-ghost" style="margin-top:.6rem;width:100%" href="<?= e(url('a/'.$a['public_token'].'/ics')) ?>"><?= e($L['add_cal']) ?></a>
  <?php endif; ?>
  <p class="muted" style="font-size:.76rem;margin-top:1.2rem"><?= e($L['keep']) ?></p>
</div><?= public_legal_footer($lang) ?></div>
<?php if(in_array($a['status'],['booked'],true)): ?>
<script>
(function(){
  var token = <?= jsenc($a['public_token']) ?>;
  var initStatus = <?= jsenc($a['status']) ?>, initTicket = <?= jsenc((string)($a['ticket_token'] ?? '')) ?>;
  var slotTs = <?= (int)$ts ?>, skew = <?= (int)$now ?> - Math.floor(Date.now()/1000);
  var checkinOpenTs = slotTs - 1800;            // check-in se deschide cu 30 min inainte
  var startsIn = <?= jsenc($L['starts_in']) ?>;
  var cd = document.getElementById('apCountdown');
  function nowS(){ return Math.floor(Date.now()/1000) + skew; }
  function fmt(s){ if(s<=0) return ''; var h=Math.floor(s/3600), m=Math.floor((s%3600)/60); return (h>0?h+'h ':'')+m+'min'; }
  function tickCountdown(){ if(!cd) return; var s = slotTs - nowS(); cd.textContent = s>0 ? (startsIn+' '+fmt(s)) : ''; }
  tickCountdown(); setInterval(tickCountdown, 30000);
  // reincarca exact cand se deschide fereastra de check-in (ca sa apara butonul fara reload manual)
  var toOpen = (checkinOpenTs - nowS());
  if (toOpen > 0 && toOpen < 6*3600) setTimeout(function(){ location.reload(); }, toOpen*1000 + 500);
  // polling de stare: daca s-a schimbat statusul sau a aparut un bon (check-in din admin), reincarca
  setInterval(function(){
    fetch('<?= e(url('api/appt')) ?>?token='+encodeURIComponent(token), {headers:{'Accept':'application/json'}})
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(d){ if(!d||!d.ok||!d.appt) return;
        if (d.appt.status !== initStatus || (d.appt.ticket_token||'') !== initTicket) location.reload();
        if (typeof d.appt.now_ts==='number' && typeof d.appt.slot_ts==='number'){ skew = d.appt.now_ts - Math.floor(Date.now()/1000); slotTs = d.appt.slot_ts; }
      }).catch(function(){});
  }, 20000);
})();
</script>
<?php endif; ?>
</body></html>
