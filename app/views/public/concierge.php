<?php $title='Concierge · '.setting('brand_name','Bon de ordine'); require __DIR__.'/_head.php'; ?>
<body><div class="counter-wrap">
  <div class="topbar">
    <div><div class="muted">Concierge · <?= e($u['name']) ?></div>
      <h1 style="margin:0">Receptie</h1></div>
    <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
      <?php if(count($branches)>1): ?>
      <form method="get" action="<?= e(url('concierge')) ?>" style="margin:0"><select name="branch" onchange="this.form.submit()" style="width:auto">
        <?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)$branch['id']===(int)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
      </select></form>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= e(url('counter')) ?>">Terminal</a>
      <a class="btn btn-ghost" href="<?= e(url('logout')) ?>">Iesire</a>
    </div>
  </div>
  <p class="muted" style="margin-top:0">Alege un bilet și ghișeul către care îl chemi. Persoana e direcționată la acel birou.</p>
  <div class="row">
    <div class="card pad" style="flex:1.3">
      <div style="display:flex;justify-content:space-between;align-items:center"><strong>La rand</strong><span class="pill" style="background:#eef2ff;color:#3730a3"><span id="waitCount">0</span> bilete</span></div>
      <div class="qlist" id="qList" style="margin-top:.6rem"></div>
    </div>
    <div class="card pad" style="flex:1">
      <strong>Ghisee</strong>
      <div id="ctrList" style="margin-top:.6rem"></div>
      <div id="nsBox" style="display:none;margin-top:1rem;border-top:1px solid var(--line,#e6e8ec);padding-top:.7rem">
        <div class="muted" style="font-size:.8rem;margin-bottom:.4rem">Neprezentați azi — repune la rând:</div>
        <div id="nsList" style="display:flex;flex-wrap:wrap;gap:.4rem"></div>
      </div>
    </div>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
window.CONCIERGE = {
  branch: <?= (int)$branch['id'] ?>,
  accent: <?= json_encode(setting('accent_color','#2563eb')) ?>,
  counters: <?= json_encode(array_map(fn($c)=>['id'=>(int)$c['id'],'code'=>$c['code'],'name'=>$c['name']], $counters), JSON_UNESCAPED_UNICODE) ?>
};
(function(){
  const cfg = window.CONCIERGE;
  const elList = document.getElementById('qList'), elWait = document.getElementById('waitCount'), elCtr = document.getElementById('ctrList');
  const esc = s => String(s==null?'':s).replace(/[<>&"]/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]));
  const ctrOpts = cfg.counters.map(c=>`<option value="${c.id}">${esc(c.code+' · '+c.name)}</option>`).join('');

  async function callTo(ticketId, counterId){
    const r = await QMS.api('api/call-specific', {ticket_id:ticketId, counter_id:counterId});
    if(!r.ok){ QMS.toast(r.error||'Eroare','error'); }
    else { QMS.toast('Bilet chemat','ok'); }
    refresh();
  }
  elList.addEventListener('change', e=>{ const s=e.target.closest('select[data-tid]'); if(!s||!s.value)return;
    const tid=+s.getAttribute('data-tid'), cid=+s.value; const cn=s.options[s.selectedIndex].text;
    QMS.confirm('Chemi biletul la „'+cn+'"?', {ok:'Cheama'}).then(ok=>{ if(ok) callTo(tid,cid); else s.value=''; });
  });

  async function refresh(){
    const res = await QMS.api('api/branch-state?branch='+cfg.branch, null, 'GET');
    if(!res.ok) return;
    elWait.textContent = res.waiting_count;
    elList.innerHTML = (res.waiting||[]).map(t=>`<div class="qrow">
      <span class="tag" style="background:${t.color}">${esc((t.label||'?')[0])}</span>
      <span class="lbl">${esc(t.label)}</span>
      ${t.priority?'<span class="pill" style="background:#fee2e2;color:#b91c1c">PRIORITAR</span>':''}
      <span class="muted" style="margin:0 .5rem 0 auto">${esc(t.service_name||'')}</span>
      <select data-tid="${t.id}" style="width:auto"><option value="">Cheama la…</option>${ctrOpts}</select>
    </div>`).join('') || '<div class="muted" style="padding:1rem">Niciun bilet la rand.</div>';
    elCtr.innerHTML = (res.counters||[]).map(c=>`<div class="qrow">
      <span class="lbl" style="font-size:1rem">${esc(c.code)}</span>
      <span class="muted">${esc(c.name)}</span>
      ${c.status==='paused'?'<span class="pill" style="background:#fef3c7;color:#92400e" title="'+esc(c.pause_note||'')+'">⏸ pauza</span>':''}
      <span style="margin-left:auto;font-family:var(--display);font-weight:800;color:${cfg.accent}">${c.current_label?esc(c.current_label):'—'}</span>
    </div>`).join('') || '<div class="muted" style="padding:1rem">Niciun ghiseu.</div>';
    const nsBox=document.getElementById('nsBox'), nsList=document.getElementById('nsList'), ns=res.no_show||[];
    if(nsBox&&nsList){ if(!ns.length){ nsBox.style.display='none'; nsList.innerHTML=''; }
      else { nsBox.style.display='';
        nsList.innerHTML=ns.map(t=>`<button class="btn btn-ghost" data-requeue="${t.id}" style="font-size:.85rem">↩ <strong style="color:${esc(t.color||cfg.accent)}">${esc(t.label)}</strong> <span class="muted">${esc(t.at||'')}</span></button>`).join(''); } }
  }
  document.getElementById('nsList').addEventListener('click', async e=>{
    const b=e.target.closest('[data-requeue]'); if(!b) return; b.disabled=true;
    const r=await QMS.api('api/requeue',{ticket_id:+b.dataset.requeue});
    if(r.ok){ QMS.toast('Bilet repus la rand','ok'); refresh(); } else { QMS.toast(r.error||'Eroare','error'); b.disabled=false; }
  });
  refresh(); setInterval(refresh, 2000);
})();
</script>
</body></html>
