<?php $lang = $lang ?? 'ro'; $pageLang = $lang; $L = fb_i18n($lang);
$title='Parerea ta · '.setting('brand_name','Bon de ordine'); require __DIR__.'/_head.php'; ?>
<body class="portalpage"><div class="center"><div class="vt-card" style="max-width:420px;width:92%;text-align:center">
<?php if (!empty($done)): ?>
  <div style="font-size:3rem;margin-bottom:.4rem">✅</div>
  <h2 style="margin:.2rem 0"><?= e($L['thanks_title']) ?></h2>
  <p class="muted"><?= e($L['thanks_sub']) ?></p>
<?php else: ?>
  <?= public_lang_bar($lang, url('feedback').'?branch='.(int)($branch ?? 1)) ?>
  <div class="muted"><?= e(setting('brand_name','')) ?></div>
  <h2 style="margin:.3rem 0 .1rem"><?= e($L['q_title']) ?></h2>
  <p class="muted" style="margin-top:0"><?= e($L['q_sub']) ?></p>
  <form method="post" action="<?= e(url('feedback')) ?>?branch=<?= (int)($branch ?? 1) ?>&amp;lang=<?= e($lang) ?>" id="fbForm">
    <input type="hidden" name="rating" id="fbRating" value="0">
    <div class="fb-stars" style="display:flex;justify-content:center;gap:.35rem;font-size:2.6rem;margin:.6rem 0;cursor:pointer">
      <?php for($i=1;$i<=5;$i++): ?><span data-v="<?= $i ?>" class="star" style="color:#3a4151;transition:.1s">★</span><?php endfor; ?>
    </div>
    <textarea name="comment" rows="3" maxlength="500" placeholder="<?= e($L['comment_ph']) ?>" style="margin-bottom:.8rem"></textarea>
    <button class="btn btn-primary btn-lg" style="width:100%" id="fbSend" disabled><?= e($L['send']) ?></button>
  </form>
  <script>
  (function(){
    var rating=0, stars=[].slice.call(document.querySelectorAll('.star'));
    function paint(n){ stars.forEach(function(s,i){ s.style.color = i<n ? '#f5b301' : '#3a4151'; }); }
    stars.forEach(function(s){
      s.addEventListener('mouseenter',function(){ paint(+s.dataset.v); });
      s.addEventListener('click',function(){ rating=+s.dataset.v; document.getElementById('fbRating').value=rating; document.getElementById('fbSend').disabled=false; paint(rating); });
    });
    document.querySelector('.fb-stars').addEventListener('mouseleave',function(){ paint(rating); });
  })();
  </script>
<?php endif; ?>
</div></div></body></html>
