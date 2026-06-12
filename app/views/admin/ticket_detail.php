<?php $title='Bilet '.$t['label']; $active='tickets'; require __DIR__.'/_header.php';
$st=['waiting'=>['La rand','#fef3c7','#92400e'],'called'=>['Apelat','#dbeafe','#1e40af'],'serving'=>['In servire','#dbeafe','#1e40af'],
  'served'=>['Servit','#dcfce7','#166534'],'no_show'=>['Neprezentat','#f1f5f9','#64748b'],'cancelled'=>['Anulat','#fee2e2','#b91c1c'],'transferred'=>['Transferat','#e0e7ff','#3730a3']];
$s = $st[$t['status']] ?? ['?','#eee','#555'];
$fmt = fn($d) => $d ? date('d.m.Y H:i:s', strtotime($d)) : null;
$dur = fn($a,$b) => ($a && $b) ? max(0, strtotime($b)-strtotime($a)) : null;
$mmss = function($x){ if($x===null) return '—'; return sprintf('%d:%02d', intdiv($x,60), $x%60); };
$waitSec = $dur($t['issued_at'], $t['called_at']);
$serveSec = $dur($t['called_at'], $t['served_at'] ?: $t['finished_at']);
// linia de timp
$tl = [];
$tl[] = ['Emis', $t['issued_at'], '🎫', 'Bon emis prin canalul „'.$t['channel'].'"'];
if ($t['called_at'])  $tl[] = ['Chemat', $t['called_at'], '📣', $t['counter_code'] ? ('La '.$t['counter_code'].($t['counter_name']?' · '.$t['counter_name']:'')) : ''];
if ($t['served_at'])  $tl[] = ['In servire', $t['served_at'], '▶️', ''];
if ($t['finished_at'])$tl[] = [ucfirst($s[0]), $t['finished_at'], '✔️', $t['agent_name'] ? ('Operator: '.$t['agent_name']) : ''];
$form = $t['form_data'] ? json_decode($t['form_data'], true) : null;
?>
<div class="topbar"><h1>Bilet <span class="tag" style="background:<?= e($t['color']) ?>"><?= e($t['prefix']) ?></span> <?= e($t['label']) ?></h1>
  <a class="btn btn-ghost" href="<?= e(url('admin/tickets').'?date='.substr($t['issued_at'],0,10)) ?>">← Bilete</a>
</div>

<div class="row" style="align-items:flex-start">
  <div class="card pad" style="flex:1;min-width:300px">
    <h3 style="margin-top:0">Detalii</h3>
    <table>
      <tr><td class="muted">Status</td><td><span class="pill" style="background:<?= $s[1] ?>;color:<?= $s[2] ?>"><?= e($s[0]) ?></span><?= $t['priority']?' <span class="pill" style="background:#fee2e2;color:#b91c1c">PRIORITAR</span>':'' ?></td></tr>
      <tr><td class="muted">Serviciu</td><td><?= e($t['service_name']) ?></td></tr>
      <tr><td class="muted">Filiala</td><td><?= e($t['branch_name'] ?? '—') ?></td></tr>
      <tr><td class="muted">Ghiseu</td><td><?= $t['counter_code'] ? e($t['counter_code'].($t['counter_name']?' · '.$t['counter_name']:'')) : '—' ?></td></tr>
      <?php if($t['target_code']): ?><tr><td class="muted">Directionat catre</td><td>⇆ <?= e($t['target_code']) ?></td></tr><?php endif; ?>
      <tr><td class="muted">Operator</td><td><?= e($t['agent_name'] ?? '—') ?></td></tr>
      <tr><td class="muted">Canal</td><td><?= e($t['channel']) ?></td></tr>
      <tr><td class="muted">Rechemari</td><td><?= (int)$t['recall_count'] ?></td></tr>
      <tr><td class="muted">Timp asteptare</td><td><?= $mmss($waitSec) ?> <?php if($waitSec!==null && (int)$t['kpi_wait_sec']>0 && $waitSec>(int)$t['kpi_wait_sec']): ?><span class="pill" style="background:#fee2e2;color:#b91c1c">peste tinta (<?= $mmss((int)$t['kpi_wait_sec']) ?>)</span><?php endif; ?></td></tr>
      <tr><td class="muted">Timp servire</td><td><?= $mmss($serveSec) ?></td></tr>
    </table>
    <?php if(is_array($form) && $form): ?>
      <h3>Date din formular</h3>
      <table>
        <?php foreach($form as $fd): ?><tr><td class="muted"><?= e($fd['label'] ?? '') ?></td><td><strong><?= e($fd['value'] ?? '—') ?></strong></td></tr><?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>

  <div class="card pad" style="flex:1;min-width:300px">
    <h3 style="margin-top:0">Cronologie</h3>
    <div style="position:relative;padding-left:1.2rem">
      <?php foreach($tl as $i=>$ev): ?>
        <div style="position:relative;padding:0 0 1.1rem .4rem;border-left:2px solid var(--line)">
          <span style="position:absolute;left:-11px;top:0;width:20px;height:20px;border-radius:999px;background:var(--card);border:2px solid var(--line);display:flex;align-items:center;justify-content:center;font-size:.7rem"><?= $ev[2] ?></span>
          <div style="font-weight:700"><?= e($ev[0]) ?></div>
          <div class="muted" style="font-size:.84rem"><?= e($fmt($ev[1])) ?></div>
          <?php if($ev[3]): ?><div class="muted" style="font-size:.82rem"><?= e($ev[3]) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if($t['public_token']): ?>
      <p class="muted" style="font-size:.82rem;margin-bottom:0">Bilet digital: <a href="<?= e(url('t/'.$t['public_token'])) ?>" target="_blank"><?= e(url('t/'.$t['public_token'])) ?></a></p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__.'/_footer.php'; ?>
