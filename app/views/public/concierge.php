<?php $title='Concierge · '.setting('brand_name','Bon de ordine'); $publicTheme=true; require __DIR__.'/_head.php';
$apptServices = array_values(array_filter($services, fn($s)=>(int)$s['allow_priority']>=0 && (int)($s['appt_enabled']??0)===1)); ?>
<body class="cgpage"><div class="counter-wrap">
  <div class="topbar">
    <div><div class="muted">Concierge / Receptie · <?= e($u['name']) ?></div>
      <h1 style="margin:0">Receptie</h1></div>
    <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
      <?php if(count($branches)>1): ?>
      <form method="get" action="<?= e(url('concierge')) ?>" style="margin:0"><select name="branch" onchange="this.form.submit()" style="width:auto">
        <?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)$branch['id']===(int)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
      </select></form>
      <?php endif; ?>
      <a class="btn btn-ghost" href="<?= e(url('counter')) ?>">Terminal operator</a>
      <a class="btn btn-ghost" href="<?= e(url('account')) ?>">Cont</a>
      <a class="btn btn-ghost" href="<?= e(url('logout')) ?>">Iesire</a>
    </div>
  </div>

  <div class="tabs" id="cgTabs" role="tablist">
    <a data-tab="new"  class="on" role="tab"><span class="ic">🎫</span> Bon nou</a>
    <a data-tab="call" role="tab"><span class="ic">📣</span> Chemare <span class="pill cg-badge" id="cgWaitBadge" style="display:none"></span></a>
    <a data-tab="appt" role="tab"><span class="ic">📅</span> Programari</a>
  </div>

  <!-- ===================== TAB: BON NOU (walk-in) ===================== -->
  <div class="tabpane on" id="pane-new">
    <div class="card pad">
      <strong>Emite bon nou (walk-in)</strong>
      <div class="muted" style="font-size:.82rem;margin:.2rem 0 .8rem">Pentru clientii care nu folosesc dozatorul. Bonul intra la rand si apare pe afisaj. Alege serviciul:</div>
      <?php if(!empty($services)): ?>
      <div class="cg-svcgrid">
        <?php foreach($services as $sv): ?>
          <button class="cg-svcbtn" data-issue="<?= (int)$sv['id'] ?>" data-prio="<?= (int)$sv['allow_priority'] ?>">
            <span class="b" style="background:<?= e($sv['color']) ?>"><?= e(($sv['prefix']!==''?$sv['prefix']:'?')[0]) ?></span>
            <span class="t"><strong><?= e($sv['prefix']) ?></strong> · <?= e($sv['name']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
      <div id="issuedMsg" class="cg-issued" style="display:none"></div>
      <?php else: ?>
        <div class="muted">Niciun serviciu activ pe aceasta filiala.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===================== TAB: CHEMARE ===================== -->
  <div class="tabpane" id="pane-call">
    <!-- ultimul bilet chemat (ca la Moviik) -->
    <div class="card pad cg-lastcard">
      <div class="muted" style="font-size:.82rem">Ultimul bilet chemat</div>
      <div class="cg-last" id="cgLast">—</div>
      <div class="cg-lastmeta muted" id="cgLastMeta" style="display:none"></div>
      <div class="cg-lastacts" id="cgLastActs" style="display:none">
        <button class="btn btn-danger" data-last="cancel">✖ Anuleaza</button>
        <button class="btn btn-primary" data-last="finish">✔ Finalizat</button>
        <select id="cgLastTransfer" style="width:auto"><option value="">⇆ Transfera la ghiseu…</option>
          <?php foreach($counters as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['code'].' · '.$c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="card pad" style="flex:1.5">
        <div class="listhead" style="margin-bottom:.6rem">
          <div class="search"><span class="si">🔍</span><input type="text" id="cgSearch" placeholder="Cauta bilet sau serviciu..."></div>
          <div class="filterpills" id="cgFilter" role="group" aria-label="Filtreaza biletele">
            <button class="on" data-f="all">Toate</button>
            <button data-f="waiting">La rand</button>
            <button data-f="called">Chemate</button>
            <button data-f="cancelled">Anulate</button>
          </div>
        </div>
        <div class="cg-tablewrap">
          <table class="cg-table" id="cgTable">
            <thead><tr><th>Nr.</th><th>Serviciu</th><th>Date client</th><th>Ora</th><th>Prioritate</th><th></th></tr></thead>
            <tbody id="cgRows"></tbody>
          </table>
        </div>
      </div>
      <div class="card pad" style="flex:1">
        <strong>Ghisee</strong>
        <div id="ctrList" style="margin-top:.6rem"></div>
        <div id="nsBox" style="display:none;margin-top:1rem;border-top:1px solid var(--line,#e6e8ec);padding-top:.7rem">
          <div class="muted" style="font-size:.8rem;margin-bottom:.4rem">Neprezentati azi — repune la rand:</div>
          <div id="nsList" style="display:flex;flex-wrap:wrap;gap:.4rem"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================== TAB: PROGRAMARI ===================== -->
  <div class="tabpane" id="pane-appt">
    <div class="card pad">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:.6rem;flex-wrap:wrap">
        <strong>Programari</strong>
        <button class="btn btn-primary" id="cgApptNewBtn">+ Programare noua</button>
      </div>
      <div class="cg-apptfilters">
        <div class="field"><label>Data</label><input type="date" id="cgApptDate" value="<?= e(date('Y-m-d')) ?>"></div>
        <div class="field"><label>Serviciu</label><select id="cgApptSvc"><option value="">Toate serviciile</option>
          <?php foreach($apptServices as $sv): ?><option value="<?= (int)$sv['id'] ?>"><?= e($sv['prefix'].' · '.$sv['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field" style="flex:1"><label>Cauta</label><input type="text" id="cgApptSearch" placeholder="nume client, cod, serviciu..."></div>
      </div>

      <!-- formular programare noua (ascuns implicit) -->
      <div class="cg-apptform" id="cgApptForm" style="display:none">
        <div class="formgrid">
          <div class="field"><label>Serviciu *</label><select id="naSvc">
            <?php if(!$apptServices): ?><option value="">— niciun serviciu cu programari —</option><?php endif; ?>
            <?php foreach($apptServices as $sv): ?><option value="<?= (int)$sv['id'] ?>"><?= e($sv['prefix'].' · '.$sv['name']) ?></option><?php endforeach; ?>
          </select></div>
          <div class="field"><label>Data *</label><input type="date" id="naDate" value="<?= e(date('Y-m-d')) ?>"></div>
          <div class="field"><label>Ora *</label><input type="time" id="naTime" value="09:00"></div>
          <div class="field"><label>Nume client</label><input type="text" id="naName" maxlength="120" placeholder="optional"></div>
          <div class="field"><label>Telefon</label><input type="text" id="naPhone" maxlength="40" placeholder="optional"></div>
          <div class="field"><label>Email</label><input type="email" id="naEmail" maxlength="160" placeholder="optional"></div>
        </div>
        <div style="display:flex;gap:.5rem;margin-top:.6rem">
          <button class="btn btn-primary" id="naSave">Salveaza programarea</button>
          <button class="btn btn-ghost" id="naCancel">Renunta</button>
        </div>
        <div id="naMsg" style="display:none;margin-top:.5rem;font-weight:700"></div>
      </div>

      <div class="cg-tablewrap" style="margin-top:.8rem">
        <table class="cg-table" id="cgApptTable">
          <thead><tr><th>Ora</th><th>Serviciu</th><th>Client</th><th>Cod</th><th>Status</th><th></th></tr></thead>
          <tbody id="cgApptRows"><tr><td colspan="6" class="muted" style="padding:1rem">Se incarca…</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script>
window.CONCIERGE = {
  branch: <?= (int)$branch['id'] ?>,
  accent: <?= jsenc(setting('accent_color','#00c375')) ?>,
  counters: <?= jsenc(array_map(fn($c)=>['id'=>(int)$c['id'],'code'=>$c['code'],'name'=>$c['name']], $counters), JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= e(asset('js/concierge.js')) ?>"></script>
<?= idle_logout_script() ?>
</body></html>
