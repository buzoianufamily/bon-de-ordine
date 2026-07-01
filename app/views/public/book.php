<?php $lang = $lang ?? 'ro'; $pageLang = $lang; $L = book_i18n($lang);
$lq = $lang !== 'ro' ? '?lang='.$lang : '';
$title = $L['title']; require __DIR__.'/_head.php';
$selId = (isset($svc) && $svc) ? (int)$svc['id'] : 0;
if ($selId) {
  $prev=date('Y-m-d',strtotime($date.' -1 day')); $next=date('Y-m-d',strtotime($date.' +1 day')); $today=date('Y-m-d');
  $dts=strtotime($date); $dateLabel=$L['days'][(int)date('w',$dts)].', '.date('d.m.Y',$dts);
  $slq = $lang !== 'ro' ? '&lang='.$lang : '';
} ?>
<body class="bookpage"><div class="bookwrap">
  <!-- ============ STANGA: lista de servicii (master) ============ -->
  <aside class="booklist">
    <div class="bl-head">
      <h1><?= e($L['title']) ?></h1>
      <?= public_lang_bar($lang, url('book')) ?>
    </div>
    <div class="bl-search"><span class="si" aria-hidden="true">🔍</span><input type="text" id="svcSearch" placeholder="<?= e($L['search']) ?>" aria-label="<?= e($L['search']) ?>" autocomplete="off"></div>
    <nav class="bl-items" id="svcItems" aria-label="<?= e($L['choose']) ?>">
      <?php foreach($services as $i=>$s): ?>
        <a class="bl-item<?= $selId===(int)$s['id']?' on':'' ?>" href="<?= e(url('book/'.$s['id']).$lq) ?>"
           data-name="<?= e(mb_strtolower($s['name'].' '.$s['branch_name'].' '.$s['prefix'])) ?>">
          <span class="n"><?= sprintf('%02d',$i+1) ?></span>
          <span class="tag" style="background:<?= e($s['color']) ?>"><?= e($s['prefix']) ?></span>
          <span class="nm"><strong><?= e($s['name']) ?></strong><span class="muted"><?= e($s['branch_name']) ?></span></span>
          <span class="chev" aria-hidden="true">›</span>
        </a>
      <?php endforeach; ?>
      <?php if(!$services): ?><p class="muted" style="padding:1rem"><?= e($L['none']) ?></p><?php endif; ?>
      <p class="muted bl-nomatch" id="svcNoMatch" style="display:none;padding:1rem"><?= e($L['no_match']) ?></p>
    </nav>
  </aside>

  <!-- ============ DREAPTA: detaliul serviciului (detail) ============ -->
  <section class="bookdetail">
  <?php if(!$selId): ?>
    <div class="bd-empty">
      <div class="ic" aria-hidden="true">📅</div>
      <h2><?= e($L['choose']) ?></h2>
      <p class="muted"><?= e($L['choose_hint']) ?></p>
    </div>
  <?php else: ?>
    <div class="bd-head">
      <a href="<?= e(url('book').$lq) ?>" class="bd-back muted"><?= e($L['all_services']) ?></a>
      <div class="bd-title"><span class="tag" style="background:<?= e($svc['color']) ?>"><?= e($svc['prefix']) ?></span>
        <div><h1><?= e($svc['name']) ?></h1><p class="muted"><?= e($svc['branch_name']) ?></p></div></div>
    </div>
    <?php foreach(get_flashes() as $f): ?><div class="pill" style="display:block;text-align:center;background:#fee2e2;color:#b91c1c;margin-bottom:.8rem"><?= e($f['msg']) ?></div><?php endforeach; ?>
    <div class="card pad">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
        <?php if($date>$today): ?><a class="btn btn-ghost" href="<?= e(url('book/'.$svc['id'].'?date='.$prev).$slq) ?>">← <?= e($L['day']) ?></a><?php else: ?><span style="width:60px"></span><?php endif; ?>
        <strong style="font-size:1.05rem"><?= e($dateLabel) ?></strong>
        <a class="btn btn-ghost" href="<?= e(url('book/'.$svc['id'].'?date='.$next).$slq) ?>"><?= e($L['day']) ?> →</a>
      </div>
      <?php if(!empty($nextDay)): $ndts=strtotime($nextDay); ?>
        <a href="<?= e(url('book/'.$svc['id'].'?date='.$nextDay).$slq) ?>" style="display:block;text-align:center;background:#dbeafe;color:#1e40af;border-radius:10px;padding:.6rem;margin-bottom:1rem;text-decoration:none;font-weight:700">
          <?= e($L['next_avail']) ?> <?= e($L['days'][(int)date('w',$ndts)].', '.date('d.m.Y',$ndts)) ?> →
        </a>
      <?php endif; ?>
      <?php if(!$slots): ?>
        <?php if(($closed ?? null) !== null): ?>
          <p style="text-align:center;padding:1rem;color:#b91c1c;font-weight:700"><?= e($L['closed_named']) ?><?= $closed !== '' ? ' (' . e($closed) . ')' : '' ?>. <?= e($L['pick_other']) ?></p>
        <?php else: ?>
          <p class="muted" style="text-align:center;padding:1rem"><?= e($L['closed_day']) ?></p>
        <?php endif; ?>
      <?php else: ?>
        <div role="group" aria-label="<?= e($L['day']) ?> <?= e($dateLabel) ?>" class="slotgrid">
          <?php foreach($slots as $sl): $wlSlot=$sl['full'] && !$sl['past'] && !empty($wlOn); $dis=$sl['past'] || ($sl['full'] && !$wlSlot); ?>
            <button type="button" class="btn slot" <?= $dis?'disabled':'' ?> data-slot="<?= e($sl['start']) ?>" data-full="<?= $sl['full']?'1':'0' ?>" aria-label="<?= e($sl['time'].($sl['full']?', '.$L['full']:'')) ?>" title="<?= $sl['full']?e($L['full']):'' ?>" style="<?= $dis?'opacity:.4':($wlSlot?'opacity:.6':'') ?>"><?= e($sl['time']) ?></button>
          <?php endforeach; ?>
        </div>
        <form method="post" action="<?= e(url('book/'.$svc['id']).$lq) ?>" id="bookForm" style="display:none;margin-top:1.2rem">
          <input type="hidden" name="slot_start" id="slotStart">
          <div class="muted" style="margin-bottom:.6rem"><?= e($L['chosen_time']) ?> <strong id="slotLabel" style="color:var(--ink)"></strong></div>
          <div class="field"><label><?= e($L['name']) ?></label><input name="name" required></div>
          <div class="field"><label><?= e($L['phone']) ?></label><input name="phone" type="tel"></div>
          <div class="field"><label><?= e($L['email']) ?></label><input name="email" type="email"></div>
          <label style="display:flex;gap:.5rem;align-items:flex-start;font-size:.84rem;text-align:left;margin:.2rem 0 .9rem"><input type="checkbox" name="consent" value="1" required style="width:auto;margin-top:.2rem"><span><?= e($L['consent']) ?> <a href="<?= e(legal_url('privacy', $lang)) ?>" target="_blank" rel="noopener"><?= e($L['privacy']) ?></a>.</span></label>
          <button class="btn btn-primary btn-lg" style="width:100%"><?= e($L['confirm']) ?></button>
        </form>
        <?php if(!empty($wlOn)): ?>
        <form method="post" action="<?= e(url('book/'.$svc['id'].'/waitlist').$lq) ?>" id="wlForm" style="display:none;margin-top:1.2rem">
          <input type="hidden" name="slot_start" id="wlSlot">
          <div class="muted" style="margin-bottom:.6rem"><?= e($L['wl_for']) ?> <strong id="wlLabel" style="color:var(--ink)"></strong></div>
          <div class="field"><label><?= e($L['name']) ?></label><input name="name"></div>
          <div class="field"><label><?= e($L['wl_email']) ?></label><input name="email" type="email" required></div>
          <button class="btn btn-primary btn-lg" style="width:100%"><?= e($L['wl_submit']) ?></button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  </section>
</div>
<?= public_legal_footer($lang) ?>
<script>
/* cautare live in lista de servicii (panoul stang) */
(function(){
  var box=document.getElementById('svcSearch'), wrap=document.getElementById('svcItems'), nm=document.getElementById('svcNoMatch');
  if(box&&wrap){ box.addEventListener('input', function(){
    var q=this.value.trim().toLowerCase(), shown=0;
    wrap.querySelectorAll('.bl-item').forEach(function(a){
      var ok=!q||a.getAttribute('data-name').indexOf(q)>=0; a.style.display=ok?'':'none'; if(ok)shown++;
    });
    if(nm) nm.style.display=shown?'none':'';
  }); }
})();
/* selectare slot -> formular (rezervare sau lista de asteptare) */
document.querySelectorAll('.slot').forEach(function(b){ b.addEventListener('click',function(){
  document.querySelectorAll('.slot').forEach(function(x){ x.classList.remove('btn-primary'); });
  b.classList.add('btn-primary');
  var full = b.dataset.full === '1';
  var bf = document.getElementById('bookForm'), wf = document.getElementById('wlForm');
  if(full && wf){
    wf.querySelector('#wlSlot').value = b.dataset.slot;
    document.getElementById('wlLabel').textContent = b.textContent;
    wf.style.display=''; if(bf) bf.style.display='none'; wf.scrollIntoView({behavior:'smooth'});
  } else if(bf){
    document.getElementById('slotStart').value = b.dataset.slot;
    document.getElementById('slotLabel').textContent = b.textContent;
    bf.style.display=''; if(wf) wf.style.display='none'; bf.scrollIntoView({behavior:'smooth'});
  }
}); });
</script>
</body></html>
