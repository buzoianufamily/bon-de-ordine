<?php $title='Alege · '.setting('brand_name','Bon de ordine'); require __DIR__.'/_head.php';
$logo = setting('brand_logo','');
$role = $u['role'] ?? '';
$hasAppt = (int) val('SELECT COUNT(*) FROM services WHERE appt_enabled=1 AND status="active"') > 0;
$tiles = [];
// Backoffice: doar pentru roluri care au acces la admin (nu operatorii simpli)
if (in_array($role, ['admin','manager'], true))
    $tiles[] = ['Backoffice', 'Servicii, ghisee, dispozitive, utilizatori, rapoarte.', url('admin'), '🗂'];
// Terminalul operator: oricine autentificat poate opera un ghiseu
$tiles[] = ['Terminal operator', 'Apeleaza si gestioneaza bilete de la ghiseu.', url('counter'), '🖥'];
if (setting('mod_concierge','1')==='1')
    $tiles[] = ['Concierge', 'Receptie: cheama orice bilet la orice ghiseu.', url('concierge'), '🛎'];
if ($hasAppt && setting('mod_booking','1')==='1')
    $tiles[] = ['Programare online', 'Rezerva o ora pentru un serviciu.', url('book'), '📅'];
if (setting('mod_public_status','0')==='1')
    $tiles[] = ['Status coada', 'Vezi live ce se serveste si cati sunt la rand.', url('status'), '📊'];
?>
<body class="portalpage"><div class="center"><div class="portal">
  <div style="text-align:center;margin-bottom:1.6rem">
    <?php if($logo): ?><img src="<?= e($logo) ?>" alt="" style="max-height:54px;margin-bottom:.6rem"><?php else: ?>
      <div class="plogo"><?= e(setting('brand_name','Sistem Bon de Ordine')) ?></div><?php endif; ?>
    <p class="muted" style="margin:.35rem 0 0">Salut, <strong><?= e($u['name'] ?? '') ?></strong> — unde vrei sa mergi?</p>
  </div>
  <div class="portal-grid">
    <?php foreach($tiles as $t): ?>
      <a href="<?= e($t[2]) ?>"><div class="tile">
        <span class="tileic"><?= $t[3] ?></span>
        <h3><?= e($t[0]) ?></h3>
        <p class="muted" style="font-size:.88rem;flex:1"><?= e($t[1]) ?></p>
        <span class="access">Acceseaza →</span>
      </div></a>
    <?php endforeach; ?>
  </div>
  <div style="text-align:center;margin-top:1.4rem">
    <a class="btn btn-ghost" href="<?= e(url('account')) ?>">Contul meu</a>
    <a class="btn btn-ghost" href="<?= e(url('logout')) ?>">Iesire</a>
  </div>
  <?= public_legal_footer('ro') ?>
</div></div></body></html>
