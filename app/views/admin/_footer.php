    </div><!-- .content -->
  </main>
</div><!-- .shell -->
<!-- fundal drawer mobil: in afara grilei + ascuns inline => nu poate strica layout-ul indiferent de CSS -->
<div id="navbk" style="display:none;position:fixed;inset:0;background:rgba(8,10,14,.55);z-index:94"></div>
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
  document.querySelectorAll('[data-view]').forEach(function(b){var on=b===t;b.classList.toggle('on',on);b.setAttribute('aria-pressed',on?'true':'false');});
});
/* comutator grafic/tabel pe panourile de statistici */
document.addEventListener('click',function(e){
  var b=e.target.closest('.dvtoggle [data-dv]'); if(!b)return;
  var box=b.closest('.dvbox'); if(!box)return;
  var mode=b.getAttribute('data-dv');
  box.querySelectorAll('.dvtoggle [data-dv]').forEach(function(x){var on=x===b;x.classList.toggle('on',on);x.setAttribute('aria-pressed',on?'true':'false');});
  var ch=box.querySelector('.dv-chart'), tb=box.querySelector('.dv-table');
  if(ch) ch.classList.toggle('dv-hidden', mode!=='chart');
  if(tb) tb.classList.toggle('dv-hidden', mode!=='table');
});
/* comutator tema deschisa/inchisa — persista in localStorage; fara preferinta, urmeaza sistemul */
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
  // tema implicita e inchisa (ca la Moviik); nu mai urmarim automat tema sistemului
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
/* contoare de caractere pe campurile cu limita (ca la Moviik: 0/50) */
(function(){
  document.querySelectorAll('.content input[maxlength], .content textarea[maxlength]').forEach(function(el){
    var max=parseInt(el.getAttribute('maxlength'),10); if(!max||max>2000) return;
    var cc=document.createElement('span'); cc.className='cc';
    function upd(){ cc.textContent=el.value.length+'/'+max; }
    upd(); el.addEventListener('input',upd);
    if(el.nextSibling) el.parentNode.insertBefore(cc, el.nextSibling); else el.parentNode.appendChild(cc);
  });
})();
/* drawer mobil pentru bara laterala (fundalul e controlat doar din JS, prin stil inline) */
(function(){
  var shell=document.querySelector('.shell'), btn=document.getElementById('nav-open'), bk=document.getElementById('navbk');
  if(!shell||!btn)return;
  function openNav(){ shell.classList.add('nav-open'); if(bk) bk.style.display='block'; }
  function closeNav(){ shell.classList.remove('nav-open'); if(bk) bk.style.display='none'; }
  btn.addEventListener('click', openNav);
  if(bk) bk.addEventListener('click', closeNav);
  document.querySelectorAll('.side a').forEach(function(a){ a.addEventListener('click', closeNav); });
})();
/* meniul contului (chip dreapta-sus): editare profil + iesire */
(function(){
  var wrap=document.getElementById('usermenu'); if(!wrap)return;
  var btn=document.getElementById('uchip-btn'), pop=document.getElementById('umpop');
  if(!btn||!pop)return;
  function openM(){ pop.hidden=false; btn.setAttribute('aria-expanded','true'); }
  function closeM(){ pop.hidden=true; btn.setAttribute('aria-expanded','false'); }
  btn.addEventListener('click',function(e){ e.stopPropagation(); pop.hidden?openM():closeM(); });
  document.addEventListener('click',function(e){ if(!wrap.contains(e.target)) closeM(); });
  document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeM(); });
})();
/* cautare globala Ctrl+K / Cmd+K */
(function(){
  var pages=[]; document.querySelectorAll('.side a[href]').forEach(function(a){
    var lbl=(a.querySelector('.lbl')||a).textContent.trim(); if(lbl) pages.push({group:'Pagini',label:lbl,url:a.href,sub:''});
  });
  var ov=null, idx=0, items=[], tdebounce=null;
  function close(){ if(ov){ ov.remove(); ov=null; } }
  function go(){ var it=items[idx]; if(it) location.href=it.url; }
  function renderList(box, list){
    items=list; idx=0;
    if(!list.length){ box.innerHTML='<div class="ck-empty">Niciun rezultat.</div>'; return; }
    var html='', grp='';
    list.forEach(function(it,i){
      if(it.group!==grp){ grp=it.group; html+='<div class="ck-grp">'+grp+'</div>'; }
      html+='<a class="ck-it'+(i===0?' on':'')+'" data-i="'+i+'" href="'+it.url+'">'+it.label.replace(/[<>&]/g,'')+(it.sub?'<span class="sub">'+String(it.sub).replace(/[<>&]/g,'')+'</span>':'')+'</a>';
    });
    box.innerHTML=html;
    box.querySelectorAll('.ck-it').forEach(function(el){ el.addEventListener('mousemove',function(){ mark(+el.dataset.i); }); });
  }
  function mark(i){ idx=i; if(ov) ov.querySelectorAll('.ck-it').forEach(function(el,j){ el.classList.toggle('on', j===i); }); }
  function open(){
    if(ov) return;
    ov=document.createElement('div'); ov.className='cmdk';
    ov.innerHTML='<div class="ck-card"><input type="text" placeholder="Cauta pagini, servicii, ghisee, bilete…">'+
      '<div class="ck-list"></div><div class="ck-hint"><span><span class="kbd">↑↓</span> navighezi</span><span><span class="kbd">Enter</span> deschizi</span><span><span class="kbd">Esc</span> inchizi</span></div></div>';
    document.body.appendChild(ov);
    var inp=ov.querySelector('input'), box=ov.querySelector('.ck-list');
    ov.addEventListener('click',function(e){ if(e.target===ov) close(); });
    renderList(box, pages.slice(0,12));
    inp.addEventListener('input',function(){
      var q=inp.value.trim().toLowerCase();
      var local=pages.filter(function(p){ return p.label.toLowerCase().indexOf(q)>-1; }).slice(0,6);
      renderList(box, local);
      clearTimeout(tdebounce);
      if(q.length>=2) tdebounce=setTimeout(function(){
        QMS.api('admin/search?q='+encodeURIComponent(q),null,'GET').then(function(r){
          if(!r||!r.ok||!ov||inp.value.trim().toLowerCase()!==q) return;
          renderList(box, local.concat(r.results||[]));
        }).catch(function(){});
      },180);
    });
    inp.addEventListener('keydown',function(e){
      if(e.key==='ArrowDown'){ e.preventDefault(); mark(Math.min(items.length-1,idx+1)); }
      else if(e.key==='ArrowUp'){ e.preventDefault(); mark(Math.max(0,idx-1)); }
      else if(e.key==='Enter'){ e.preventDefault(); go(); }
      else if(e.key==='Escape'){ close(); }
    });
    inp.focus();
  }
  document.addEventListener('keydown',function(e){
    if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='k'){ e.preventDefault(); open(); }
    else if(e.key==='Escape' && ov) close();
  });
})();
</script>
<?= idle_logout_script() ?>
</body></html>
