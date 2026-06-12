<?php $title='Zile inchise'; $active='branches'; require __DIR__.'/_header.php';
$today = date('Y-m-d');
$zileRo = ['Duminică','Luni','Marți','Miercuri','Joi','Vineri','Sâmbătă'];
$dowRo = fn($d) => $zileRo[(int)date('w', strtotime($d))];
?>
<div class="topbar"><h1>Zile închise / sărbători</h1><a class="btn btn-ghost" href="<?= e(url('admin/branches')) ?>">← Filiale</a></div>
<p class="muted" style="margin-top:-.6rem;max-width:720px">În zilele marcate aici nu se mai emit bonuri (dispenserul afișează serviciile ca „închis"). O zi setată „toate filialele" acoperă întreaga instanță. Programul săptămânal normal se setează per serviciu.</p>

<div class="row" style="align-items:flex-start">
  <form method="post" action="<?= e(url('admin/closures')) ?>" class="card pad" style="flex:1;min-width:300px;max-width:380px"><?= csrf_field() ?>
    <h3 style="margin-top:0">Adaugă o zi închisă</h3>
    <div class="field"><label>Data</label><input type="date" name="closed_date" min="<?= e($today) ?>" required></div>
    <div class="field"><label>Filiala</label>
      <select name="branch_id">
        <option value="0">Toate filialele</option>
        <?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Motiv (opțional)</label><input name="reason" maxlength="120" placeholder="ex: Ziua Națională, Inventar"></div>
    <button class="btn btn-primary">Adaugă</button>
  </form>

  <div class="card pad" style="flex:1.4;min-width:320px">
    <h3 style="margin-top:0">Zile programate</h3>
    <table><thead><tr><th>Data</th><th>Filiala</th><th>Motiv</th><th></th></tr></thead><tbody>
    <?php foreach($rows as $r): $past = $r['closed_date'] < $today; ?>
      <tr style="<?= $past?'opacity:.55':'' ?>">
        <td style="white-space:nowrap"><strong><?= e(date('d.m.Y', strtotime($r['closed_date']))) ?></strong>
          <span class="muted" style="font-size:.78rem"><?= e($dowRo($r['closed_date'])) ?></span>
          <?php if($r['closed_date']===$today): ?><span class="pill" style="background:#fee2e2;color:#b91c1c;font-size:.66rem">AZI</span><?php endif; ?>
        </td>
        <td><?= $r['branch_id'] ? e($r['branch_name']) : '<span class="pill" style="background:#e0e7ff;color:#3730a3">Toate</span>' ?></td>
        <td class="muted"><?= e($r['reason'] ?? '—') ?></td>
        <td style="text-align:right">
          <form method="post" action="<?= e(url('admin/closures/'.$r['id'].'/delete')) ?>" data-confirm="Stergi aceasta zi inchisa?"><?= csrf_field() ?>
            <button class="lnk del" style="background:none;border:none;cursor:pointer;color:var(--danger);font-weight:700">Șterge</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$rows): ?><tr><td colspan="4" class="muted">Nicio zi închisă programată.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<?php require __DIR__.'/_footer.php'; ?>
