<?php $title='Programare · '.$svc['name']; require __DIR__.'/_head.php';
$prev=date('Y-m-d',strtotime($date.' -1 day')); $next=date('Y-m-d',strtotime($date.' +1 day')); $today=date('Y-m-d');
$zile=['Duminica','Luni','Marti','Miercuri','Joi','Vineri','Sambata'];
$luni=['ianuarie','februarie','martie','aprilie','mai','iunie','iulie','august','septembrie','octombrie','noiembrie','decembrie'];
$dts=strtotime($date); $dateLabel=$zile[(int)date('w',$dts)].', '.(int)date('j',$dts).' '.$luni[(int)date('n',$dts)-1]; ?>
<body><div class="center"><div class="portal" style="max-width:620px">
  <a href="<?= e(url('book')) ?>" class="muted">← Toate serviciile</a>
  <div style="text-align:center;margin:.4rem 0 1rem"><span class="tag" style="background:<?= e($svc['color']) ?>;display:inline-flex"><?= e($svc['prefix']) ?></span>
    <h1 style="margin:.3rem 0"><?= e($svc['name']) ?></h1><p class="muted" style="margin:0"><?= e($svc['branch_name']) ?></p></div>
  <?php foreach(get_flashes() as $f): ?><div class="pill" style="display:block;text-align:center;background:#fee2e2;color:#b91c1c;margin-bottom:.8rem"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <div class="card pad">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
      <?php if($date>$today): ?><a class="btn btn-ghost" href="<?= e(url('book/'.$svc['id'].'?date='.$prev)) ?>">← Zi</a><?php else: ?><span style="width:60px"></span><?php endif; ?>
      <strong style="font-size:1.05rem"><?= e($dateLabel) ?></strong>
      <a class="btn btn-ghost" href="<?= e(url('book/'.$svc['id'].'?date='.$next)) ?>">Zi →</a>
    </div>
    <?php if(!$slots): ?>
      <p class="muted" style="text-align:center;padding:1rem">Inchis in aceasta zi. Alege alta data.</p>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:.5rem">
        <?php foreach($slots as $sl): $dis=$sl['full']||$sl['past']; ?>
          <button type="button" class="btn slot" <?= $dis?'disabled':'' ?> data-slot="<?= e($sl['start']) ?>" style="<?= $dis?'opacity:.4':'' ?>"><?= e($sl['time']) ?></button>
        <?php endforeach; ?>
      </div>
      <form method="post" action="<?= e(url('book/'.$svc['id'])) ?>" id="bookForm" style="display:none;margin-top:1.2rem">
        <input type="hidden" name="slot_start" id="slotStart">
        <div class="muted" style="margin-bottom:.6rem">Ora aleasa: <strong id="slotLabel" style="color:var(--ink)"></strong></div>
        <div class="field"><label>Nume</label><input name="name" required></div>
        <div class="field"><label>Telefon</label><input name="phone" type="tel"></div>
        <div class="field"><label>Email (optional)</label><input name="email" type="email"></div>
        <button class="btn btn-primary btn-lg" style="width:100%">Confirma programarea</button>
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
