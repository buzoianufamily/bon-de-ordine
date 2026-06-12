<?php $title='Jurnal audit'; $active='audit'; require __DIR__.'/_header.php';
$pages = max(1, (int)ceil($total / $per));
$actLab = ['create'=>['Creare','#dcfce7','#166534'],'update'=>['Modificare','#dbeafe','#1e40af'],'delete'=>['Stergere','#fee2e2','#b91c1c'],
  'duplicate'=>['Duplicare','#e0e7ff','#3730a3'],'reset'=>['Resetare','#fef3c7','#92400e'],'regenerate'=>['Regenerare','#fef3c7','#92400e']];
?>
<?php
$actLabels = ['create'=>'Creare','update'=>'Modificare','delete'=>'Stergere','duplicate'=>'Duplicare',
  'reset'=>'Resetare','regenerate'=>'Regenerare','export'=>'Export','backup'=>'Backup','login'=>'Autentificare',
  'login_failed'=>'Login esuat','login_failed_2fa'=>'2FA esuat','password_change'=>'Schimbare parola',
  'pwreset_request'=>'Cerere resetare','pwreset_done'=>'Resetare parola','2fa_enabled'=>'2FA activat',
  '2fa_disabled'=>'2FA dezactivat','2fa_codes_regen'=>'Coduri 2FA noi','2fa_backup_used'=>'Cod recuperare folosit'];
// pastreaza filtrele in linkurile de paginare
$qs = http_build_query(array_filter(['action'=>$action,'q'=>$qstr,'from'=>$from,'to'=>$to], fn($v)=>$v!==''));
$pageUrl = fn($p) => url('admin/audit').'?'.($qs?$qs.'&':'').'p='.$p;
?>
<div class="topbar"><h1>Jurnal audit</h1><span class="muted" style="font-size:.85rem"><?= (int)$total ?> inregistrari</span></div>
<p class="muted" style="margin-top:-.6rem;max-width:680px">Cine, ce și când a modificat în administrare (creare/modificare/ștergere). Util pentru securitate și depanare.</p>
<form method="get" action="<?= e(url('admin/audit')) ?>" class="card pad" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1.2rem">
  <div class="field" style="margin:0"><label>Actiune</label><select name="action" style="width:auto">
    <option value="">Toate</option>
    <?php foreach(($actions??[]) as $ac): ?><option value="<?= e($ac) ?>" <?= ($action??'')===$ac?'selected':'' ?>><?= e($actLabels[$ac] ?? $ac) ?></option><?php endforeach; ?>
  </select></div>
  <div class="field" style="margin:0"><label>De la</label><input type="date" name="from" value="<?= e($from??'') ?>"></div>
  <div class="field" style="margin:0"><label>Pana la</label><input type="date" name="to" value="<?= e($to??'') ?>"></div>
  <div class="field" style="margin:0;flex:1;min-width:160px"><label>Cauta (utilizator/detalii/obiect)</label><input type="text" name="q" value="<?= e($qstr??'') ?>" placeholder="ex: Ana, service…"></div>
  <button class="btn btn-primary">Filtreaza</button>
  <button class="btn" formaction="<?= e(url('admin/audit/export')) ?>" formnovalidate>⤓ Export CSV</button>
  <a class="btn btn-ghost" href="<?= e(url('admin/audit')) ?>">Reset</a>
</form>
<div class="card pad">
  <table><thead><tr><th>Data/ora</th><th>Utilizator</th><th>Actiune</th><th>Obiect</th><th>ID</th><th>Detalii</th><th>IP</th></tr></thead><tbody>
  <?php foreach($rows as $r): $a=$actLab[$r['action']]??[ucfirst($r['action']),'#f1f5f9','#475569']; ?>
    <tr>
      <td class="muted" style="white-space:nowrap"><?= e(date('d.m.Y H:i:s', strtotime($r['created_at']))) ?></td>
      <td><?= e($r['user_name'] ?? '—') ?></td>
      <td><span class="pill" style="background:<?= $a[1] ?>;color:<?= $a[2] ?>"><?= e($a[0]) ?></span></td>
      <td><?= e($r['entity'] ?? '—') ?></td>
      <td class="muted"><?= e($r['entity_id'] ?? '—') ?></td>
      <td class="muted"><?= e($r['details'] ?? '') ?></td>
      <td class="muted" style="font-size:.8rem"><?= e($r['ip'] ?? '—') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="7" class="muted">Nicio inregistrare inca.</td></tr><?php endif; ?>
  </tbody></table>
  <?php if($pages>1): ?>
    <div style="display:flex;gap:.4rem;align-items:center;margin-top:1rem">
      <?php if($page>1): ?><a class="btn btn-ghost" href="<?= e($pageUrl($page-1)) ?>">← Anterior</a><?php endif; ?>
      <span class="muted" style="font-size:.85rem">Pagina <?= $page ?> din <?= $pages ?></span>
      <?php if($page<$pages): ?><a class="btn btn-ghost" href="<?= e($pageUrl($page+1)) ?>">Urmator →</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
