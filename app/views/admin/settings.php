<?php $title='Setari'; $active='settings'; require __DIR__.'/_header.php'; $s=fn($k,$d='')=>e(setting($k,$d)); ?>
<div class="topbar"><h1>Setari</h1></div>
<div class="tabs" id="setTabs">
  <a class="on" data-tab="general"><span class="ic">⚙</span>General</a>
  <a data-tab="ticket"><span class="ic">🎫</span>Bilet</a>
  <a data-tab="display"><span class="ic">📺</span>Afisaj &amp; voce</a>
  <a data-tab="digital"><span class="ic">📱</span>Digital &amp; alerte</a>
  <a data-tab="email"><span class="ic">✉️</span>Email</a>
  <a data-tab="auto"><span class="ic">⏱</span>Automatizări</a>
  <a data-tab="module"><span class="ic">🧩</span>Module</a>
  <a data-tab="legal"><span class="ic">⚖️</span>Legal &amp; GDPR</a>
</div>
<form method="post" action="<?= e(url('admin/settings')) ?>"><?= csrf_field() ?>

  <div class="settab" data-pane="general">
    <div class="card pad" style="max-width:640px">
      <h3 style="margin-top:0">Brand</h3>
      <div class="field"><label>Nume companie / brand</label><input name="brand_name" value="<?= $s('brand_name') ?>"></div>
      <div class="field"><label>Nume organizatie (pe bon)</label><input name="org_name" value="<?= $s('org_name') ?>"></div>
      <div class="field"><label>Culoare accent</label><input type="color" name="accent_color" value="<?= $s('accent_color','#2563eb') ?>"></div>
      <div class="field"><label>Logo (optional)</label>
        <input name="brand_logo" id="brandLogo" value="<?= $s('brand_logo') ?>" placeholder="https://... sau alege din Multimedia">
        <button type="button" class="btn btn-ghost" id="logoPick" style="margin-top:.4rem;font-size:.8rem">📁 Alege din Multimedia</button>
        <div id="logoGrid" style="display:none;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:.5rem"></div>
      </div>
      <div class="field"><label>Limba interfata</label><select name="language"><option value="ro" selected>Romana</option></select></div>
      <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">
      <h3 style="margin-top:0">Limbi la dispenser</h3>
      <p class="muted" style="font-size:.82rem;margin-top:0">Bifeaza limbile oferite pe ecranul de bilete. Daca alegi mai multe, apare o bara de limbi (steaguri). Romana e mereu disponibila.</p>
      <?php $dl = array_filter(array_map('trim', explode(',', setting('dispenser_langs','ro')))); ?>
      <div style="display:flex;flex-wrap:wrap;gap:.8rem">
        <?php foreach(disp_lang_meta() as $code=>$m): ?>
          <label style="display:flex;align-items:center;gap:.4rem;margin:0"><input type="checkbox" name="dispenser_langs[]" value="<?= e($code) ?>" <?= ($code==='ro' || in_array($code,$dl,true))?'checked':'' ?> <?= $code==='ro'?'disabled':'' ?> style="width:auto"><?= $m[1] ?> <?= e($m[0]) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="settab dv-hidden" data-pane="ticket">
    <div class="row" style="align-items:flex-start">
    <div class="card pad" style="flex:1;min-width:300px;max-width:640px">
      <h3 style="margin-top:0">Comportament coada</h3>
      <div class="field"><label>Escaladare prioritate după … minute de așteptare (0 = oprit)</label><input type="number" name="priority_escalate_min" min="0" max="600" value="<?= $s('priority_escalate_min','0') ?>">
        <p class="muted" style="font-size:.78rem;margin-top:.3rem">Anti-„înfometare": biletele normale care așteaptă peste acest prag sunt chemate înaintea celor noi (ca biletele prioritare), în ordinea vechimii.</p></div>
      <label style="margin:.4rem 0;display:block"><input type="checkbox" name="release_on_pause" <?= setting('release_on_pause','1')==='1'?'checked':'' ?> style="width:auto"> Când un ghișeu intră în pauză, eliberează biletele direcționate către el înapoi în coada generală (evită biletele blocate)</label>
      <div class="field"><label>Marchează automat „neprezentat" după … rechemări (0 = oprit)</label><input type="number" name="max_recalls" min="0" max="20" value="<?= $s('max_recalls','0') ?>">
        <p class="muted" style="font-size:.78rem;margin-top:.3rem">Dacă operatorul recheamă un bilet de mai multe ori și clientul nu se prezintă, biletul devine automat „neprezentat" și coada avansează.</p></div>
      <div class="field"><label>Auto-delogare la inactivitate după … minute (0 = oprit)</label><input type="number" name="admin_idle_min" min="0" max="240" value="<?= $s('admin_idle_min','0') ?>">
        <p class="muted" style="font-size:.78rem;margin-top:.3rem">Securitate pentru PC-uri partajate (backoffice + terminal operator): după atâtea minute fără activitate (mouse/tastatură), sesiunea se închide automat. Recomandat 15–30 la ghișee publice.</p></div>
      <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">
      <h3 style="margin-top:0">Bilet tiparit &amp; dispenser</h3>
      <div class="field"><label>Titlu dispenser</label><input name="dispenser_title" value="<?= $s('dispenser_title','ALEGE SERVICIUL') ?>"></div>
      <div class="field"><label>Text antet bon (sub numele filialei)</label><input name="ticket_header" value="<?= $s('ticket_header') ?>" placeholder="ex: Va uram bun venit!"></div>
      <div class="field"><label>Text subsol bon</label><input name="ticket_footer" value="<?= $s('ticket_footer') ?>"></div>
      <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">
      <h3 style="margin-top:0">Continut bon tiparit</h3>
      <div class="field"><label>Marime numar bon (2 = mic … 6 = foarte mare)</label><input type="number" name="ticket_num_size" min="2" max="6" value="<?= $s('ticket_num_size','4') ?>"></div>
      <label style="margin:.4rem 0;display:block"><input type="checkbox" name="ticket_show_position" <?= setting('ticket_show_position','1')==='1'?'checked':'' ?> style="width:auto"> Arata pozitia in coada (cati sunt inainte)</label>
      <label style="margin:.4rem 0;display:block"><input type="checkbox" name="ticket_show_datetime" <?= setting('ticket_show_datetime','1')==='1'?'checked':'' ?> style="width:auto"> Arata data si ora emiterii</label>
      <label style="margin:.4rem 0;display:block"><input type="checkbox" name="ticket_show_qr" <?= setting('ticket_show_qr','1')==='1'?'checked':'' ?> style="width:auto"> Arata codul QR (bilet digital)</label>
      <p class="muted" style="font-size:.82rem">Antetul si subsolul apar pe bonul tiparit (ESC/POS). Codul QR apare doar daca biletul digital e activ si optiunea de mai sus e bifata.</p>
    </div>
    <div style="flex:0 0 250px">
      <p class="muted" style="font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;margin:.2rem 0 .5rem">Previzualizare bon</p>
      <div class="tkpv" id="tkpv">
        <div class="b" id="pvBrand"><?= $s('brand_name','Compania Mea') ?></div>
        <div id="pvBranch">Sediu Central</div>
        <div id="pvHeader"></div>
        <div class="ln">--------------------------------</div>
        <div id="pvSvc">Casierie</div>
        <div class="num" id="pvNum">A012</div>
        <div id="pvPos">Inainte: 3 persoane</div>
        <div id="pvDt"><?= date('d.m.Y H:i') ?></div>
        <div id="pvQr" style="margin:.4rem 0">Urmariti pe telefon:<br><span class="qrbox">▩</span></div>
        <div class="ln">--------------------------------</div>
        <div id="pvFooter"></div>
      </div>
    </div>
    </div>
  </div>

  <div class="settab dv-hidden" data-pane="display">
    <div class="card pad" style="max-width:640px">
      <h3 style="margin-top:0">Afisaj &amp; anunt vocal</h3>
      <div class="field"><label>Voce (limba TTS)</label><select name="display_voice">
        <?php foreach(['ro-RO'=>'Romana','en-US'=>'Engleza','de-DE'=>'Germana','fr-FR'=>'Franceza'] as $k=>$lab): ?>
          <option value="<?= $k ?>" <?= setting('display_voice','ro-RO')===$k?'selected':'' ?>><?= $lab ?></option><?php endforeach; ?></select></div>
      <label style="margin:.5rem 0;display:block"><input type="checkbox" name="display_say_number" <?= setting('display_say_number','1')==='1'?'checked':'' ?> style="width:auto"> Anunta numarul bonului</label>
      <label style="margin:.5rem 0;display:block"><input type="checkbox" name="display_say_counter" <?= setting('display_say_counter','1')==='1'?'checked':'' ?> style="width:auto"> Anunta ghiseul</label>
      <div class="field"><label>Repetari anunt</label><input type="number" name="display_repeat" value="<?= $s('display_repeat','2') ?>" min="1" max="5"></div>
      <label style="margin:.5rem 0;display:block"><input type="checkbox" name="counter_voice" <?= setting('counter_voice','0')==='1'?'checked':'' ?> style="width:auto"> Anunt vocal si la terminalul operatorului (la „Cheama urmatorul")</label>
      <p class="muted" style="font-size:.82rem">Aceste valori sunt folosite pe afisajele fara layout personalizat. Afisajele cu layout (editor canvas) au setarile lor de sunet.</p>
    </div>
  </div>

  <div class="settab dv-hidden" data-pane="digital">
    <div class="card pad" style="max-width:640px">
      <p class="muted" style="font-size:.82rem;margin-top:0">Biletul digital se activeaza/dezactiveaza din tabul <strong>Module</strong>.</p>
      <h3 style="margin-top:0">Anunț general (banner public)</h3>
      <p class="muted" style="font-size:.82rem;margin-top:0">Mesaj informativ afișat pe dispenser, pe pagina de status și pe afișajele de ghișeu (ex: „Azi program redus până la 14:00"). Lasă textul gol ca să-l ascunzi.</p>
      <div class="field"><label>Text anunț (max 200)</label><input name="notice_text" maxlength="200" value="<?= $s('notice_text') ?>" placeholder="ex: Astăzi casieria se închide la ora 14:00"></div>
      <div class="field"><label>Activ până la (opțional)</label><input type="date" name="notice_until" value="<?= $s('notice_until') ?>">
        <p class="muted" style="font-size:.82rem;margin-top:.3rem">După această dată anunțul dispare automat. Gol = rămâne cât timp există text.</p></div>
      <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">
      <h3 style="margin-top:0">Alerte client</h3>
      <div class="field"><label>Mesaj cand este apelat (max 250)</label><textarea name="alert_called" rows="2" maxlength="250"><?= $s('alert_called','Este randul dumneavoastra! Va rugam prezentati-va la ghiseu.') ?></textarea></div>
      <div class="field"><label>Mesaj la transfer (max 250)</label><textarea name="alert_transfer" rows="2" maxlength="250"><?= $s('alert_transfer','Biletul dvs. a fost transferat catre alt serviciu.') ?></textarea></div>
      <div class="field"><label>Intarziere alerta pe telefon (secunde)</label><input type="number" name="alert_delay" value="<?= $s('alert_delay','0') ?>" min="0" max="120">
        <p class="muted" style="font-size:.82rem;margin-top:.3rem">Dupa apelare, telefonul vibreaza/atentioneaza dupa acest numar de secunde (0 = imediat).</p></div>
      <div class="field"><label>Alertă „aproape la rând" când mai sunt … înaintea clientului (0 = oprit)</label><input type="number" name="near_turn_alert" value="<?= $s('near_turn_alert','2') ?>" min="0" max="20">
        <p class="muted" style="font-size:.82rem;margin-top:.3rem">Pe biletul digital, telefonul vibrează și evidențiază poziția când coada scade sub acest prag — un preaviz înainte de apelare.</p></div>
    </div>
  </div>

  <div class="settab dv-hidden" data-pane="email">
    <div class="card pad" style="max-width:640px">
      <h3 style="margin-top:0">Trimitere email</h3>
      <p class="muted" style="font-size:.82rem;margin-top:0">Folosit pentru confirmari/anulari de programari. Fara SMTP configurat, se foloseste functia <code>mail()</code> a serverului (merge pe cPanel). Pentru livrare sigura, completeaza SMTP-ul contului tau de email.</p>
      <label style="margin:.5rem 0;display:block"><input type="checkbox" name="mail_enabled" <?= setting('mail_enabled','0')==='1'?'checked':'' ?> style="width:auto"> Activeaza trimiterea de emailuri</label>
      <div class="row">
        <div class="field"><label>Nume expeditor</label><input name="mail_from_name" value="<?= $s('mail_from_name') ?>" placeholder="<?= $s('brand_name','Bon de ordine') ?>"></div>
        <div class="field"><label>Email expeditor</label><input type="email" name="mail_from" value="<?= $s('mail_from') ?>" placeholder="no-reply@domeniul-tau.ro"></div>
      </div>
      <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">
      <h3 style="margin-top:0">SMTP (optional, recomandat)</h3>
      <div class="row">
        <div class="field" style="flex:2"><label>Host SMTP</label><input name="smtp_host" value="<?= $s('smtp_host') ?>" placeholder="mail.domeniul-tau.ro"></div>
        <div class="field" style="flex:0 0 110px"><label>Port</label><input type="number" name="smtp_port" value="<?= $s('smtp_port','587') ?>"></div>
        <div class="field" style="flex:0 0 150px"><label>Securitate</label><select name="smtp_secure">
          <?php foreach(['tls'=>'STARTTLS (587)','ssl'=>'SSL (465)','none'=>'Fara'] as $k=>$lab): ?>
            <option value="<?= $k ?>" <?= setting('smtp_secure','tls')===$k?'selected':'' ?>><?= $lab ?></option><?php endforeach; ?>
        </select></div>
      </div>
      <div class="row">
        <div class="field"><label>Utilizator SMTP</label><input name="smtp_user" value="<?= $s('smtp_user') ?>" autocomplete="off"></div>
        <div class="field"><label>Parola SMTP</label><input type="password" name="smtp_pass" value="<?= $s('smtp_pass') ?>" autocomplete="new-password"></div>
      </div>
      <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">
      <div class="row" style="align-items:flex-end">
        <div class="field" style="flex:2"><label>Trimite un email de test catre</label><input type="email" name="mail_test_to" placeholder="adresa@ta.ro"></div>
        <div class="field"><button class="btn" name="mail_test" value="1">✉️ Salveaza si testeaza</button></div>
      </div>
    </div>
  </div>

  <div class="settab dv-hidden" data-pane="auto">
    <div class="card pad" style="max-width:640px">
      <h3 style="margin-top:0">Automatizări (necesită cron)</h3>
      <p class="muted" style="font-size:.82rem;margin-top:0">Aceste sarcini rulează prin job-ul cron — configurează-l în <a href="<?= e(url('admin/api')) ?>">API &amp; Webhooks</a>. Remindere și raportul zilnic necesită și email activ (tabul <strong>Email</strong>).</p>
      <label style="margin:.4rem 0;display:block"><input type="checkbox" name="reminder_enabled" <?= setting('reminder_enabled','0')==='1'?'checked':'' ?> style="width:auto"> Trimite <strong>reminder</strong> pe email cu ~24h inainte de programare</label>
      <label style="margin:.4rem 0;display:block"><input type="checkbox" name="daily_report_enabled" <?= setting('daily_report_enabled','0')==='1'?'checked':'' ?> style="width:auto"> Trimite <strong>raport zilnic</strong> pe email (despre ziua precedenta)</label>
      <div class="field"><label>Destinatari raport zilnic (gol = toti adminii)</label><input name="daily_report_to" value="<?= $s('daily_report_to') ?>" placeholder="a@x.ro, b@y.ro"></div>
      <label style="margin:.4rem 0;display:block"><input type="checkbox" name="sla_alert_enabled" <?= setting('sla_alert_enabled','0')==='1'?'checked':'' ?> style="width:auto"> Trimite <strong>alerta SLA</strong> pe email cand sunt cozi peste tinta de asteptare</label>
      <div class="row">
        <div class="field" style="margin:0"><label>Destinatari alerta SLA (gol = raport zilnic / adminii)</label><input name="sla_alert_to" value="<?= $s('sla_alert_to') ?>" placeholder="manager@x.ro"></div>
        <div class="field" style="margin:0"><label>Prag (min. bilete peste tinta)</label><input type="number" name="sla_alert_min" min="1" max="999" value="<?= $s('sla_alert_min','1') ?>"></div>
        <div class="field" style="margin:0"><label>Pauza intre alerte (min)</label><input type="number" name="sla_alert_cooldown_min" min="5" max="1440" value="<?= $s('sla_alert_cooldown_min','30') ?>"></div>
      </div>
      <p class="muted" style="font-size:.78rem;margin-top:.3rem">Alerta se trimite cel mult o data la „pauza" minute, doar daca numarul de bilete peste tinta atinge pragul. Tinta per serviciu = „timp asteptare" din editarea serviciului.</p>
      <div class="field"><label>Alertă webhook la notă mică de feedback (≤ … stele; 0 = oprit)</label><input type="number" name="feedback_alert_rating" min="0" max="5" value="<?= $s('feedback_alert_rating','2') ?>">
        <p class="muted" style="font-size:.78rem;margin-top:.3rem">Când un client lasă o notă sub acest prag, se trimite evenimentul webhook <code>feedback.low</code> (cu serviciul/operatorul bonului, dacă evaluarea e legată de un bon) și — dacă emailul e activ — o alertă pe email către managerii de la alerta SLA / raportul zilnic. Configurează URL-ul în <a href="<?= e(url('admin/api')) ?>">API &amp; Webhooks</a>.</p></div>
      <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">
      <h3 style="margin-top:0">Ciclu de viață date</h3>
      <div class="field"><label>Sterge automat biletele mai vechi de … luni (0 = pastreaza tot)</label><input type="number" name="retention_months" min="0" max="120" value="<?= $s('retention_months','0') ?>">
        <p class="muted" style="font-size:.78rem;margin-top:.3rem">Curatarea ruleaza prin cron (nu necesita email activ). Statisticile pentru perioadele sterse dispar — fa un backup inainte.</p></div>
      <div class="field"><label>Închide automat biletele uitate după … minute (0 = oprit)</label><input type="number" name="auto_close_min" min="0" max="1440" value="<?= $s('auto_close_min','0') ?>">
        <p class="muted" style="font-size:.78rem;margin-top:.3rem">Prin cron: biletele rămase „în servire" devin <strong>finalizate</strong>, iar cele „chemate" neprezentate devin <strong>neprezentate</strong>. Pune o valoare generoasă (ex: 30–60) ca să nu închizi servirile lungi.</p></div>
      <div class="field"><label>Marchează operatorii inactivi drept „offline" după … minute</label><input type="number" name="auto_offline_min" min="2" max="240" value="<?= $s('auto_offline_min','10') ?>">
        <p class="muted" style="font-size:.78rem;margin-top:.3rem">Prin cron: dacă un operator a închis browserul fără să se delogheze, statusul lui devine <strong>offline</strong> (altfel rămâne „disponibil" și strică statisticile de prezență).</p></div>
      <div class="field"><label>Marchează programările neonorate drept „neprezentat" după … minute (0 = oprit)</label><input type="number" name="appt_noshow_min" min="0" max="1440" value="<?= $s('appt_noshow_min','0') ?>">
        <p class="muted" style="font-size:.78rem;margin-top:.3rem">Prin cron: o programare <strong>rezervată</strong> al cărei interval a trecut de atâtea minute și care n-a făcut check-in devine <strong>neprezentat</strong> (pentru rata de neprezentare corectă). Pune o valoare generoasă (ex: 60).</p></div>
    </div>
  </div>

  <div class="settab dv-hidden" data-pane="module">
    <div class="card pad" style="max-width:640px">
      <h3 style="margin-top:0">Aplicatii optionale</h3>
      <p class="muted" style="font-size:.82rem;margin-top:0">Activeaza sau dezactiveaza modulele instantei. Modulele oprite nu mai sunt accesibile public (linkurile dispar, paginile raspund cu „modul dezactivat").</p>
      <label class="switch" style="margin:.7rem 0;align-items:flex-start"><input type="checkbox" name="virtual_enabled" <?= setting('virtual_enabled','1')==='1'?'checked':'' ?>><span class="track"></span> <span><strong>Bilet digital (QR)</strong> — urmarire bon pe telefon, QR pe bonul tiparit</span></label>
      <label class="switch" style="margin:.7rem 0;align-items:flex-start"><input type="checkbox" name="mod_booking" <?= setting('mod_booking','1')==='1'?'checked':'' ?>><span class="track"></span> <span><strong>Programari online</strong> — pagina publica <code><?= e(url('book')) ?></code></span></label>
      <label class="switch" style="margin:.7rem 0;align-items:flex-start"><input type="checkbox" name="mod_feedback" <?= setting('mod_feedback','1')==='1'?'checked':'' ?>><span class="track"></span> <span><strong>Feedback clienti</strong> — pagina publica <code><?= e(url('feedback')) ?></code> + sondaj pe biletul digital</span></label>
      <label class="switch" style="margin:.7rem 0;align-items:flex-start"><input type="checkbox" name="mod_concierge" <?= setting('mod_concierge','1')==='1'?'checked':'' ?>><span class="track"></span> <span><strong>Concierge</strong> — receptia cheama orice bilet la orice ghiseu</span></label>
      <label class="switch" style="margin:.7rem 0;align-items:flex-start"><input type="checkbox" name="mod_public_status" <?= setting('mod_public_status','0')==='1'?'checked':'' ?>><span class="track"></span> <span><strong>Status public coada</strong> — pagina <code><?= e(url('status')) ?></code> (fara cheie), de pus pe site-ul clientului</span></label>
      <p class="muted" style="font-size:.8rem;margin-bottom:0">Module care necesita conturi externe (SMS / WhatsApp / Telegram) nu sunt incluse; pot fi integrate prin <a href="<?= e(url('admin/api')) ?>">API &amp; Webhooks</a>.</p>
    </div>
  </div>

  <div class="settab dv-hidden" data-pane="legal">
    <div class="card pad" style="max-width:640px">
      <h3 style="margin-top:0">Date operator (pentru paginile legale)</h3>
      <p class="muted" style="font-size:.82rem;margin-top:0">Aceste date completeaza paginile publice <a href="<?= e(url('legal/privacy')) ?>" target="_blank">Confidențialitate</a> și <a href="<?= e(url('legal/terms')) ?>" target="_blank">Termeni</a> (linkuri afișate în subsolul paginilor publice). Necesare pentru conformitatea GDPR atunci când colectezi date (programări, feedback).</p>
      <div class="field"><label>Operator / entitate juridică (controlor de date)</label><input name="legal_operator" value="<?= $s('legal_operator') ?>" placeholder="ex: Primăria Orașului X / SC Firma SRL"><p class="muted" style="font-size:.78rem;margin-top:.3rem">Gol = se folosește numele de brand.</p></div>
      <div class="field"><label>Adresă</label><input name="legal_address" value="<?= $s('legal_address') ?>" placeholder="Str. Exemplu nr. 1, Oraș, Județ"></div>
      <div class="field"><label>Email de contact pentru cereri privind datele</label><input name="legal_email" type="email" value="<?= $s('legal_email') ?>" placeholder="date@exemplu.ro"><p class="muted" style="font-size:.78rem;margin-top:.3rem">Gol = se folosește adresa „expeditor" de la Email.</p></div>
      <div class="field"><label>Text suplimentar (opțional, apare la finalul ambelor pagini)</label><textarea name="legal_extra" rows="3" placeholder="ex: Responsabil cu protecția datelor (DPO): ..."><?= $s('legal_extra') ?></textarea></div>
      <hr style="border:none;border-top:1px solid var(--line);margin:1rem 0">
      <h3 style="margin-top:0">Politici proprii (opțional)</h3>
      <p class="muted" style="font-size:.82rem;margin-top:0">Dacă ai deja politici publicate pe site-ul tău, pune aici adresele lor — paginile legale vor redirecționa acolo în loc să afișeze șablonul implicit.</p>
      <div class="field"><label>URL Politică de confidențialitate</label><input name="privacy_url" type="url" value="<?= $s('privacy_url') ?>" placeholder="https://site-ul-tau.ro/confidentialitate"></div>
      <div class="field"><label>URL Termeni și condiții</label><input name="terms_url" type="url" value="<?= $s('terms_url') ?>" placeholder="https://site-ul-tau.ro/termeni"></div>
    </div>
  </div>

  <button class="btn btn-primary btn-lg" style="margin-top:1.2rem">Salveaza setarile</button>
</form>

<div class="card pad" style="max-width:640px;margin-top:1.4rem">
  <h3 style="margin-top:0">Backup / clonare configurație</h3>
  <p class="muted" style="font-size:.82rem;margin-top:0">Descarcă toate setările (branding, texte, alerte, module, automatizări) ca fișier JSON — pentru backup sau pentru a configura rapid o altă instanță. Cheile sensibile (chei API, token cron) <strong>nu</strong> se exportă.</p>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
    <a class="btn" href="<?= e(url('admin/settings/export')) ?>">⬇ Exportă configurația</a>
  </div>
  <form method="post" action="<?= e(url('admin/settings/import')) ?>" enctype="multipart/form-data" style="margin-top:1rem" data-confirm="Imporți configurația? Setările curente vor fi suprascrise (fără chei API/cron).">
    <?= csrf_field() ?>
    <div class="field"><label>Importă dintr-un fișier .json</label><input type="file" name="file" accept="application/json,.json"></div>
    <button class="btn btn-danger">⬆ Importă configurația</button>
  </form>
  <hr style="border:none;border-top:1px solid var(--line);margin:1.1rem 0">
  <h3 style="margin-top:0">Backup bază de date</h3>
  <p class="muted" style="font-size:.82rem;margin-top:0">Descarcă un fișier <code>.sql</code> cu toată baza de date (structură + date: filiale, servicii, bilete, programări…). Păstrează-l într-un loc sigur — conține datele clienților.</p>
  <form method="post" action="<?= e(url('admin/backup')) ?>"><?= csrf_field() ?>
    <button class="btn btn-primary">⬇ Descarcă backup SQL</button>
  </form>
  <hr style="border:none;border-top:1px solid var(--line);margin:1.1rem 0">
  <h3 style="margin-top:0">Backup automat (pe server)</h3>
  <p class="muted" style="font-size:.82rem;margin-top:0">Prin cron, o dată pe zi, sistemul scrie automat un backup în folderul <code>backups/</code> (protejat de acces web) și păstrează ultimele câteva copii. Descarcă-le periodic în afara serverului.</p>
  <form method="post" action="<?= e(url('admin/settings')) ?>"><?= csrf_field() ?>
    <label style="display:block;margin:.4rem 0"><input type="checkbox" name="backup_auto_enabled" <?= setting('backup_auto_enabled','0')==='1'?'checked':'' ?> style="width:auto"> Activează backup-ul automat zilnic (necesită cron)</label>
    <div class="field"><label>Câte copii păstrez</label><input type="number" name="backup_keep" min="1" max="365" value="<?= $s('backup_keep','14') ?>" style="max-width:120px"></div>
    <button class="btn">Salvează</button>
  </form>
  <form method="post" action="<?= e(url('admin/backup/run')) ?>" style="margin-top:.6rem"><?= csrf_field() ?>
    <button class="btn">⟳ Rulează un backup acum</button>
  </form>
  <?php $bks = function_exists('backup_list') ? backup_list() : []; if ($bks): ?>
    <div style="overflow-x:auto;margin-top:1rem"><table style="min-width:420px">
      <thead><tr><th>Fișier</th><th>Dimensiune</th><th>Data</th><th></th></tr></thead>
      <tbody><?php foreach ($bks as $bk): ?>
        <tr>
          <td><code><?= e($bk['name']) ?></code></td>
          <td><?= number_format($bk['size']/1024, 0) ?> KB</td>
          <td class="muted"><?= e(date('d.m.Y H:i', $bk['mtime'])) ?></td>
          <td style="text-align:right;white-space:nowrap">
            <a class="lnk" href="<?= e(url('admin/backup/download').'?file='.rawurlencode($bk['name'])) ?>">⬇ Descarcă</a>
            <form method="post" action="<?= e(url('admin/backup/delete')) ?>" style="display:inline;margin-left:.6rem" data-confirm="Ștergi acest backup?"><?= csrf_field() ?><input type="hidden" name="file" value="<?= e($bk['name']) ?>"><button class="lnk del" style="background:none;border:none;cursor:pointer;color:#dc2626;font:inherit">Șterge</button></form>
          </td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
</div>

<?php if ((current_user()['role'] ?? '') === 'admin'): ?>
<div class="card pad" style="max-width:640px;margin-top:1.4rem;border-color:#dc2626">
  <h3 style="margin-top:0;color:#dc2626">⚠ Pregătire pentru producție</h3>
  <p class="muted" style="font-size:.82rem;margin-top:0">După ce ai terminat de testat, șterge <strong>datele de test</strong> (bilete, programări, feedback, sesiuni, jurnale de prezență/webhook) ca să pornești cu o evidență curată. <strong>Configurația rămâne intactă</strong> (filiale, servicii, ghișee, utilizatori, dispozitive, setări). Se face automat un <strong>backup de siguranță</strong> înainte. Acțiunea este ireversibilă (în afară de restaurarea backup-ului).</p>
  <form method="post" action="<?= e(url('admin/reset')) ?>" data-confirm="Sigur ștergi TOATE datele operaționale (bilete/programări/feedback)? Configurația rămâne. Se face backup automat înainte."><?= csrf_field() ?>
    <div class="field" style="max-width:280px"><label>Scrie <code>STERGE</code> pentru confirmare</label><input name="confirm" autocomplete="off" placeholder="STERGE"></div>
    <button class="btn btn-danger">Șterge datele de test</button>
  </form>
</div>
<?php endif; ?>
<script>
/* tab-uri setari */
(function(){
  var tabs=document.querySelectorAll('#setTabs [data-tab]');
  tabs.forEach(function(t){ t.addEventListener('click',function(e){ e.preventDefault();
    var name=t.getAttribute('data-tab');
    tabs.forEach(function(x){x.classList.toggle('on',x===t);});
    document.querySelectorAll('.settab').forEach(function(p){ p.classList.toggle('dv-hidden', p.getAttribute('data-pane')!==name); });
  }); });
})();
/* previzualizare live a bonului (tabul Bilet) */
(function(){
  var f = document.querySelector('form[action*="admin/settings"]'); if(!f) return;
  function el(n){ return f.querySelector('[name="'+n+'"]'); }
  function upd(){
    var hdr=el('ticket_header'), ftr=el('ticket_footer'), num=el('ticket_num_size'), brand=el('brand_name');
    var sp=el('ticket_show_position'), sd=el('ticket_show_datetime'), sq=el('ticket_show_qr'), dig=el('virtual_enabled');
    var g=function(id){ return document.getElementById(id); };
    if(!g('tkpv')) return;
    if(brand) g('pvBrand').textContent = brand.value || 'Compania Mea';
    g('pvHeader').textContent = hdr ? hdr.value : '';
    g('pvHeader').style.display = (hdr && hdr.value) ? '' : 'none';
    g('pvFooter').textContent = ftr ? ftr.value : '';
    g('pvFooter').style.display = (ftr && ftr.value) ? '' : 'none';
    var sz = num ? Math.max(2, Math.min(6, +num.value||4)) : 4;
    g('pvNum').style.fontSize = (0.55 + sz*0.28) + 'rem';
    g('pvPos').style.display = (sp && sp.checked) ? '' : 'none';
    g('pvDt').style.display  = (sd && sd.checked) ? '' : 'none';
    g('pvQr').style.display  = (sq && sq.checked && (!dig || dig.checked)) ? '' : 'none';
  }
  f.addEventListener('input', upd); f.addEventListener('change', upd); upd();
})();
/* selector logo din galerie */
document.getElementById('logoPick').onclick=async()=>{ const g=document.getElementById('logoGrid');
  const r=await QMS.api('admin/media?format=json',null,'GET'); if(!r||!r.ok)return;
  g.style.display='grid';
  g.innerHTML=(r.media||[]).filter(m=>m.mime&&m.mime.startsWith('image')).map(m=>`<img src="${m.url}" data-u="${m.url}" style="width:100%;height:50px;object-fit:contain;background:#0e1117;border-radius:6px;cursor:pointer;padding:3px;border:1px solid #2a2f3a">`).join('')||'<div class="muted" style="grid-column:1/-1">Galerie goala — incarca in Multimedia.</div>';
  g.querySelectorAll('img').forEach(im=>im.onclick=()=>{ document.getElementById('brandLogo').value=im.dataset.u; });
};
</script>
<?php require __DIR__.'/_footer.php'; ?>
