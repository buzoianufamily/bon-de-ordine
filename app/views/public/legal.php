<?php
/* Pagini legale publice (GDPR): confidentialitate + termeni.
   Continutul implicit este un sablon in romana, completat din setarile operatorului.
   Operatorul poate inlocui complet aceste pagini setand privacy_url / terms_url. */
$kind = ($kind ?? 'privacy') === 'terms' ? 'terms' : 'privacy';
$lang = $lang ?? 'ro';
$pageLang = $lang;
$brand = setting('brand_name', 'Bon de ordine');
$op    = trim((string) setting('legal_operator', '')) ?: $brand;          // operator (controlor de date)
$addr  = trim((string) setting('legal_address', ''));
$email = trim((string) setting('legal_email', '')) ?: trim((string) setting('mail_from', ''));
$extra = trim((string) setting('legal_extra', ''));
$ret   = (int) setting('retention_months', 6); if ($ret <= 0) $ret = 6;
$updated = date('d.m.Y');
$title = ($kind === 'terms' ? 'Termeni si conditii' : 'Politica de confidentialitate') . ' · ' . $brand;
require __DIR__ . '/_head.php';
?>
<body><div class="center"><div class="portal" style="max-width:720px">
  <a href="<?= e(url('') . ($lang !== 'ro' ? '?lang='.$lang : '')) ?>" class="muted">← <?= e($brand) ?></a>
  <div class="card pad" style="margin-top:.8rem;line-height:1.7">
  <?php if ($kind === 'privacy'): ?>
    <h1 style="margin-top:0">Politica de confidentialitate</h1>
    <p class="muted">Ultima actualizare: <?= e($updated) ?></p>

    <h3>1. Cine prelucreaza datele (operatorul)</h3>
    <p><strong><?= e($op) ?></strong><?= $addr !== '' ? ', ' . e($addr) : '' ?>, in calitate de operator de date cu caracter personal.
       <?php if ($email !== ''): ?>Pentru orice solicitare privind datele tale ne poti contacta la <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.<?php endif; ?></p>

    <h3>2. Ce date colectam si in ce scop</h3>
    <ul>
      <li><strong>Bilete de ordine</strong>: serviciul ales, ora emiterii si starea biletului — pentru gestionarea cozii. Nu cerem date de identificare pentru un bilet simplu.</li>
      <li><strong>Programari online</strong>: nume, telefon si/sau email — pentru a confirma si gestiona programarea si, daca este cazul, pentru a te anunta cand se elibereaza un loc.</li>
      <li><strong>Feedback</strong>: nota acordata si comentariul (optional) — pentru imbunatatirea serviciilor. Nu introduce date personale in comentariu.</li>
      <li><strong>Date tehnice minime</strong>: adresa IP si jurnale de acces — pentru securitate, prevenirea abuzului si functionarea sistemului.</li>
    </ul>

    <h3>3. Temeiul legal</h3>
    <p>Prelucram datele pe baza <strong>consimtamantului</strong> tau (de ex. la programare sau feedback), a <strong>interesului legitim</strong> de a oferi si securiza serviciul, si, dupa caz, pentru <strong>indeplinirea unei sarcini de interes public</strong> in cazul institutiilor publice (Regulamentul UE 2016/679 — GDPR, art. 6).</p>

    <h3>4. Cat timp pastram datele</h3>
    <p>Pastram datele doar atat cat este necesar scopului: datele operationale (bilete, programari, feedback, jurnale) sunt sterse sau anonimizate automat dupa aproximativ <strong><?= (int)$ret ?> luni</strong>, daca nu exista o obligatie legala de pastrare mai indelungata.</p>

    <h3>5. Cui dezvaluim datele</h3>
    <p>Nu vindem datele. Le pot accesa doar personalul autorizat al operatorului si furnizorul de gazduire/mentenanta, strict pentru functionarea serviciului. Sistemul nu trimite date catre servicii externe de tip publicitate sau analiza.</p>

    <h3>6. Drepturile tale</h3>
    <p>Conform GDPR ai dreptul de acces, rectificare, stergere („dreptul de a fi uitat"), restrictionare, portabilitate si opozitie, precum si dreptul de a-ti retrage consimtamantul oricand.
       <?php if ($email !== ''): ?>Iti poti exercita drepturile scriind la <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.<?php endif; ?>
       Ai, de asemenea, dreptul de a depune o plangere la <strong>Autoritatea Nationala de Supraveghere a Prelucrarii Datelor cu Caracter Personal (ANSPDCP)</strong>, <a href="https://www.dataprotection.ro" target="_blank" rel="noopener">dataprotection.ro</a>.</p>

    <h3>7. Decizii automate</h3>
    <p>Ordinea in coada este stabilita dupa reguli simple (ordinea sosirii si, optional, prioritati). Nu luam decizii cu efecte juridice pe baza unei prelucrari exclusiv automate si nu realizam profilare.</p>

    <h3>8. Cookie-uri</h3>
    <p>Folosim un singur cookie tehnic, necesar pentru sesiune si securitate (CSRF). Nu folosim cookie-uri de marketing sau de urmarire.</p>

  <?php else: ?>
    <h1 style="margin-top:0">Termeni si conditii</h1>
    <p class="muted">Ultima actualizare: <?= e($updated) ?></p>

    <h3>1. Despre serviciu</h3>
    <p><strong><?= e($brand) ?></strong> este un sistem de gestionare a cozilor de asteptare care permite emiterea de bilete de ordine, programari online si afisarea starii cozii. Serviciul este pus la dispozitie de <strong><?= e($op) ?></strong><?= $addr !== '' ? ', ' . e($addr) : '' ?>.</p>

    <h3>2. Utilizare corecta</h3>
    <p>Te angajezi sa folosesti serviciul cu buna-credinta: sa nu emiti bilete sau programari false ori in mod abuziv, sa nu incerci sa perturbi functionarea sistemului si sa respecti instructiunile afisate la fata locului.</p>

    <h3>3. Programari</h3>
    <p>O programare confirmata nu garanteaza o ora fixa de servire, ci un interval estimativ. Te rugam sa ajungi din timp. Daca nu te poti prezenta, anuleaza programarea pentru a elibera locul. Programarile neonorate pot fi marcate automat ca neprezentate.</p>

    <h3>4. Disponibilitate si acuratete</h3>
    <p>Estimarile de timp de asteptare si pozitia in coada sunt orientative si pot varia. Depunem eforturi pentru disponibilitatea continua a serviciului, dar acesta poate fi indisponibil temporar pentru mentenanta sau din motive tehnice.</p>

    <h3>5. Raspundere</h3>
    <p>Serviciul este oferit „ca atare". In limitele permise de lege, operatorul nu raspunde pentru intarzieri, indisponibilitati temporare sau pierderi rezultate din utilizarea sistemului.</p>

    <h3>6. Date personale</h3>
    <p>Prelucrarea datelor tale este descrisa in <a href="<?= e(url('legal/privacy') . ($lang !== 'ro' ? '?lang='.$lang : '')) ?>">Politica de confidentialitate</a>.</p>

    <h3>7. Modificari si lege aplicabila</h3>
    <p>Putem actualiza acesti termeni; versiunea curenta este cea publicata pe aceasta pagina. Termenilor li se aplica legislatia din Romania.</p>
  <?php endif; ?>

  <?php if ($extra !== ''): ?><hr style="border:none;border-top:1px solid var(--line,#e5e7eb);margin:1.2rem 0"><div><?= nl2br(e($extra)) ?></div><?php endif; ?>
  </div>
  <?= public_legal_footer($lang) ?>
</div></div></body></html>
