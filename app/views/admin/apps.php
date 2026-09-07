<?php $title='Aplicatii'; $active='apps'; require __DIR__.'/_header.php';
/* Lansator de aplicatii (ca pagina „Apps" din Moviik). Fiecare card trimite catre
   rutele/builderele care EXISTA deja — e un hub, nu o duplicare de functii. */
$modConcierge = setting('mod_concierge','1')==='1';
$modBooking   = setting('mod_booking','1')==='1';
$modFeedback  = setting('mod_feedback','1')==='1';

/* helper: deseneaza un card de aplicatie.
   $a = ['icon','name','desc','tag', 'open'=>['url','label','blank'], 'cfg'=>['url','label'], 'off'=>bool, 'offhint'=>str] */
function app_tile(array $a): void {
    $off = !empty($a['off']); ?>
  <div class="apptile<?= $off?' off':'' ?>">
    <div class="ico"><?= $a['icon'] ?></div>
    <div style="flex:1;min-width:0">
      <div class="nm"><?= e($a['name']) ?></div>
      <?php if(!empty($a['tag'])): ?><div class="apsub"><?= e($a['tag']) ?></div><?php endif; ?>
      <div class="ds"><?= e($a['desc']) ?></div>
      <div class="acts">
        <?php if($off): ?>
          <span class="apt-off">⚪ Dezactivat</span>
          <?php if(!empty($a['offhint'])): ?><a class="btn btn-ghost btn-sm" href="<?= e($a['offhint']) ?>">Activeaza</a><?php endif; ?>
        <?php else: ?>
          <?php $o=$a['open']; ?>
          <a class="btn btn-primary btn-sm" href="<?= e($o['url']) ?>"<?= !empty($o['blank'])?' target="_blank" rel="noopener"':'' ?>><?= e($o['label'] ?? 'Deschide') ?></a>
        <?php endif; ?>
        <?php if(!empty($a['cfg'])): ?><a class="btn btn-ghost btn-sm" href="<?= e($a['cfg']['url']) ?>"><?= e($a['cfg']['label'] ?? 'Configureaza') ?></a><?php endif; ?>
      </div>
    </div>
  </div>
<?php }
?>
<div class="topbar"><h1>Aplicatii</h1><span class="muted" style="font-size:.85rem">Lanseaza si configureaza modulele platformei</span></div>
<div class="muted" style="margin:-.3rem 0 1rem;font-size:.88rem">Toate aplicatiile sistemului intr-un singur loc — deschide-le sau mergi direct la configurarea fiecareia.</div>

<div class="appgrid">
<?php
/* 1. Terminal operator (Counter) */
app_tile([
  'icon'=>'🖥️', 'name'=>'Terminal operator', 'tag'=>$counts['counters'].' '.($counts['counters']==1?'ghiseu':'ghisee'),
  'desc'=>'Ecranul operatorului de la ghiseu: cheama urmatorul, serveste si finalizeaza bonurile, cu cronometru si transfer.',
  'open'=>['url'=>url('counter'), 'label'=>'Deschide', 'blank'=>true],
  'cfg'=>['url'=>url('backoffice/counters'), 'label'=>'Ghisee'],
]);

/* 2. Concierge (receptie) */
app_tile([
  'icon'=>'🛎️', 'name'=>'Concierge', 'tag'=>'receptie',
  'desc'=>'Receptia cheama orice bon la orice ghiseu — triere si indrumare la intrare.',
  'off'=>!$modConcierge, 'offhint'=>url('backoffice/settings'),
  'open'=>['url'=>url('concierge'), 'label'=>'Deschide', 'blank'=>true],
  'cfg'=>['url'=>url('backoffice/settings'), 'label'=>'Setari'],
]);

/* 3. Programari (Appointments) */
app_tile([
  'icon'=>'📅', 'name'=>'Programari', 'tag'=>$counts['appt'].' '.($counts['appt']==1?'serviciu':'servicii'),
  'desc'=>'Clientii isi rezerva online o ora; la sosire fac check-in si primesc bon automat.',
  'off'=>!$modBooking, 'offhint'=>url('backoffice/settings'),
  'open'=>['url'=>url('book'), 'label'=>'Deschide', 'blank'=>true],
  'cfg'=>['url'=>url('backoffice/appointments'), 'label'=>'Programari'],
]);

/* 4. Dozator bilete (Ticket Dispenser / kiosk) */
if($apps['dispenser']){
  app_tile([
    'icon'=>'🎟️', 'name'=>'Dozator bilete', 'tag'=>e($apps['dispenser']['name']),
    'desc'=>'Chioscul de la intrare de unde clientii isi iau bon de ordine pe ecran tactil.',
    'open'=>['url'=>device_url($apps['dispenser']), 'label'=>'Deschide', 'blank'=>true],
    'cfg'=>['url'=>url('backoffice/devices/'.$apps['dispenser']['id'].'/dispenser'), 'label'=>'Configureaza'],
  ]);
} else {
  app_tile([
    'icon'=>'🎟️', 'name'=>'Dozator bilete', 'tag'=>'niciun dispozitiv',
    'desc'=>'Chioscul de la intrare de unde clientii isi iau bon de ordine pe ecran tactil.',
    'off'=>true, 'offhint'=>url('backoffice/devices/new'),
    'cfg'=>['url'=>url('backoffice/devices'), 'label'=>'Dispozitive'],
  ]);
}

/* 5. Afisaj TV (Player) */
if($apps['player']){
  app_tile([
    'icon'=>'📺', 'name'=>'Afisaj TV', 'tag'=>e($apps['player']['name']),
    'desc'=>'Ecranul din sala de asteptare cu bonul curent, urmatoarele la rand si continut media.',
    'open'=>['url'=>device_url($apps['player']), 'label'=>'Deschide', 'blank'=>true],
    'cfg'=>['url'=>url('backoffice/devices/'.$apps['player']['id'].'/player'), 'label'=>'Editor afisaj'],
  ]);
} else {
  app_tile([
    'icon'=>'📺', 'name'=>'Afisaj TV', 'tag'=>'niciun dispozitiv',
    'desc'=>'Ecranul din sala de asteptare cu bonul curent, urmatoarele la rand si continut media.',
    'off'=>true, 'offhint'=>url('backoffice/devices/new'),
    'cfg'=>['url'=>url('backoffice/devices'), 'label'=>'Dispozitive'],
  ]);
}

/* 6. Bilet digital QR (Digital Ticket) */
if($apps['digital_ticket']){
  app_tile([
    'icon'=>'📱', 'name'=>'Bilet digital', 'tag'=>e($apps['digital_ticket']['name']),
    'desc'=>'Clientul scaneaza un cod QR si primeste bonul pe telefon, urmarind randul live.',
    'open'=>['url'=>device_url($apps['digital_ticket']), 'label'=>'Deschide', 'blank'=>true],
    'cfg'=>['url'=>url('backoffice/devices/'.$apps['digital_ticket']['id'].'/dispenser'), 'label'=>'Configureaza'],
  ]);
} else {
  app_tile([
    'icon'=>'📱', 'name'=>'Bilet digital', 'tag'=>'niciun dispozitiv',
    'desc'=>'Clientul scaneaza un cod QR si primeste bonul pe telefon, urmarind randul live.',
    'off'=>true, 'offhint'=>url('backoffice/devices/new'),
    'cfg'=>['url'=>url('backoffice/devices'), 'label'=>'Dispozitive'],
  ]);
}
?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
