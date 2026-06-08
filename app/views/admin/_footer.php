    </div><!-- .content -->
  </main>
</div><!-- .shell -->
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
/* cautare + comutator grila/lista pentru paginile de liste (no-op daca lipsesc) */
document.addEventListener('input',function(e){
  var i=e.target.closest('[data-filter]'); if(!i)return;
  var grid=document.querySelector(i.getAttribute('data-filter')||'.cardgrid'); if(!grid)return;
  var q=i.value.trim().toLowerCase();
  grid.querySelectorAll('[data-name]').forEach(function(c){
    c.style.display=(c.getAttribute('data-name')||'').indexOf(q)>-1?'':'none';
  });
});
document.addEventListener('click',function(e){
  var t=e.target.closest('[data-view]'); if(!t)return; e.preventDefault();
  var grid=document.querySelector('.cardgrid'); if(!grid)return;
  grid.classList.toggle('aslist',t.getAttribute('data-view')==='list');
  document.querySelectorAll('[data-view]').forEach(function(b){b.classList.toggle('on',b===t);});
});
/* comutator tema deschisa/inchisa — persista in localStorage */
(function(){
  var btn=document.getElementById('theme-toggle');
  if(!btn)return;
  var setIcon=function(){ btn.textContent=document.body.classList.contains('light')?'☀️':'🌙'; };
  setIcon();
  btn.addEventListener('click',function(){
    var light=document.body.classList.toggle('light');
    try{ localStorage.setItem('admin_theme', light?'light':'dark'); }catch(e){}
    setIcon();
  });
})();
/* sidebar toggle — persista starea in localStorage */
(function(){
  var shell=document.querySelector('.shell');
  if(!shell)return;
  if(localStorage.getItem('nav_collapsed')==='1') shell.classList.add('nav-collapsed');
  var btn=document.getElementById('side-toggle');
  if(btn) btn.addEventListener('click',function(){
    shell.classList.toggle('nav-collapsed');
    localStorage.setItem('nav_collapsed', shell.classList.contains('nav-collapsed')?'1':'0');
  });
})();
</script>
</body></html>
