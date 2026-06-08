<?php $title='Parerea ta · '.setting('brand_name','Bon de ordine'); require __DIR__.'/_head.php'; ?>
<body class="portalpage"><div class="center"><div class="vt-card" style="max-width:420px;width:92%;text-align:center">
<?php if (!empty($done)): ?>
  <div style="font-size:3rem;margin-bottom:.4rem">✅</div>
  <h2 style="margin:.2rem 0">Multumim!</h2>
  <p class="muted">Parerea ta ne ajuta sa imbunatatim serviciile.</p>
<?php else: ?>
  <div class="muted"><?= e(setting('brand_name','')) ?></div>
  <h2 style="margin:.3rem 0 .1rem">Cum a fost experienta?</h2>
  <p class="muted" style="margin-top:0">Acorda o nota de la 1 la 5.</p>
  <form method="post" action="<?= e(url('feedback')) ?>?branch=<?= (int)($branch ?? 1) ?>" id="fbForm">
    <input type="hidden" name="rating" id="fbRating" value="0">
    <div class="fb-stars" style="display:flex;justify-content:center;gap:.35rem;font-size:2.6rem;margin:.6rem 0;cursor:pointer">
      <?php for($i=1;$i<=5;$i++): ?><span data-v="<?= $i ?>" class="star" style="color:#3a4151;transition:.1s">★</span><?php endfor; ?>
    </div>
    <textarea name="comment" rows="3" maxlength="500" placeholder="Comentariu (optional)" style="margin-bottom:.8rem"></textarea>
    <button class="btn btn-primary btn-lg" style="width:100%" id="fbSend" disabled>Trimite</button>
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
