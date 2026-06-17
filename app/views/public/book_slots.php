<?php $lang = $lang ?? 'ro'; $pageLang = $lang; $L = book_i18n($lang); $lq = $lang !== 'ro' ? '&lang='.$lang : '';
$title='Programare · '.$svc['name']; require __DIR__.'/_head.php';
$prev=date('Y-m-d',strtotime($date.' -1 day')); $next=date('Y-m-d',strtotime($date.' +1 day')); $today=date('Y-m-d');
$dts=strtotime($date); $dateLabel=$L['days'][(int)date('w',$dts)].', '.date('d.m.Y',$dts); ?>
<body><div class="center"><div class="portal" style="max-width:620px">
  <a href="<?= e(url('book').($lang!=='ro'?'?lang='.$lang:'')) ?>" class="muted"><?= e($L['all_services']) ?></a>
  <div style="text-align:center;margin:.4rem 0 1rem"><span class="tag" style="background:<?= e($svc['color']) ?>;display:inline-flex"><?= e($svc['prefix']) ?></span>
    <h1 style="margin:.3rem 0"><?= e($svc['name']) ?></h1><p class="muted" style="margin:0"><?= e($svc['branch_name']) ?></p></div>
  <?php foreach(get_flashes() as $f): ?><div class="pill" style="display:block;text-align:center;background:#fee2e2;color:#b91c1c;margin-bottom:.8rem"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <div class="card pad">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
      <?php if($date>$today): ?><a class="btn btn-ghost" href="<?= e(url('book/'.$svc['id'].'?date='.$prev).$lq) ?>">← <?= e($L['day']) ?></a><?php else: ?><span style="width:60px"></span><?php endif; ?>
      <strong style="font-size:1.05rem"><?= e($dateLabel) ?></strong>
      <a class="btn btn-ghost" href="<?= e(url('book/'.$svc['id'].'?date='.$next).$lq) ?>"><?= e($L['day']) ?> →</a>
    </div>
    <?php if(!$slots): ?>
      <?php if(($closed ?? null) !== null): ?>
        <p style="text-align:center;padding:1rem;color:#b91c1c;font-weight:700"><?= e($L['closed_named']) ?><?= $closed !== '' ? ' (' . e($closed) . ')' : '' ?>. <?= e($L['pick_other']) ?></p>
      <?php else: ?>
        <p class="muted" style="text-align:center;padding:1rem"><?= e($L['closed_day']) ?></p>
      <?php endif; ?>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:.5rem">
        <?php foreach($slots as $sl): $dis=$sl['full']||$sl['past']; ?>
          <button type="button" class="btn slot" <?= $dis?'disabled':'' ?> data-slot="<?= e($sl['start']) ?>" style="<?= $dis?'opacity:.4':'' ?>"><?= e($sl['time']) ?></button>
        <?php endforeach; ?>
      </div>
      <form method="post" action="<?= e(url('book/'.$svc['id']).($lang!=='ro'?'?lang='.$lang:'')) ?>" id="bookForm" style="display:none;margin-top:1.2rem">
        <input type="hidden" name="slot_start" id="slotStart">
        <div class="muted" style="margin-bottom:.6rem"><?= e($L['chosen_time']) ?> <strong id="slotLabel" style="color:var(--ink)"></strong></div>
        <div class="field"><label><?= e($L['name']) ?></label><input name="name" required></div>
        <div class="field"><label><?= e($L['phone']) ?></label><input name="phone" type="tel"></div>
        <div class="field"><label><?= e($L['email']) ?></label><input name="email" type="email"></div>
        <button class="btn btn-primary btn-lg" style="width:100%"><?= e($L['confirm']) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div></div>
<script>
document.querySelectorAll('.slot').forEach(function(b){ b.addEventListener('click',function(){
  document.querySelectorAll('.slot').forEach(x=>x.classList.remove('btn-primary'));
  b.classList.add('btn-primary');
  document.getElementById('slotStart').value=b.dataset.slot;
  document.getElementById('slotLabel').textContent=b.textContent;
  var f=document.getElementById('bookForm'); f.style.display=''; f.scrollIntoView({behavior:'smooth'});
}); });
</script>
</body></html>
