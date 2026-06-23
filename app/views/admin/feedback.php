<?php $title='Feedback'; $active='feedback'; require __DIR__.'/_header.php';
$pages = max(1, (int)ceil($total / $per));
$avg = $stat['avg']!==null ? round((float)$stat['avg'],2) : null;
$qs = fn($p)=>e(url('admin/feedback').'?'.http_build_query(['rating'=>$rating,'p'=>$p])); ?>
<div class="topbar"><h1>Feedback client</h1>
  <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
    <form method="get" action="<?= e(url('admin/feedback')) ?>" style="display:flex;gap:.5rem;align-items:center">
      <label style="margin:0">Nota</label>
      <select name="rating" onchange="this.form.submit()" style="width:auto">
        <option value="0">Toate</option>
        <?php for($i=5;$i>=1;$i--): ?><option value="<?= $i ?>" <?= $rating===$i?'selected':'' ?>><?= $i ?> stele</option><?php endfor; ?>
      </select>
    </form>
    <a class="btn" href="<?= e(url('admin/feedback/export').($rating?('?rating='.$rating):'')) ?>">⤓ Export CSV</a>
  </div>
</div>

<div class="statcards">
  <div class="statcard"><div class="t">Total raspunsuri</div><div class="s">Toate timpurile</div><div class="v"><?= (int)$stat['n'] ?></div></div>
  <div class="statcard"><div class="t">Nota medie</div><div class="s">din 5</div><div class="v"><?= $avg!==null?e(number_format($avg,2)):'—' ?> <span style="color:#f5b301;font-size:1rem"><?php if($avg!==null) for($i=1;$i<=5;$i++) echo $i<=round($avg)?'★':'☆'; ?></span></div></div>
</div>

<div class="card pad">
  <table><thead><tr><th>Data</th><th>Nota</th><th>Comentariu</th><th>Filiala</th><th>Bon</th><th></th></tr></thead><tbody>
  <?php foreach($rows as $r): ?>
    <tr>
      <td class="muted" style="white-space:nowrap"><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
      <td style="color:#f5b301;white-space:nowrap"><?php for($i=1;$i<=5;$i++) echo $i<=(int)$r['rating']?'★':'☆'; ?></td>
      <td><?= $r['comment']!==null && $r['comment']!=='' ? e($r['comment']) : '<span class="muted">—</span>' ?></td>
      <td class="muted"><?= e($r['branch_name'] ?? '—') ?></td>
      <td class="muted"><?= e($r['ticket_label'] ?? '—') ?><?= !empty($r['service_name']) ? '<br><span style="font-size:.78rem">'.e($r['service_name']).'</span>' : '' ?></td>
      <td style="text-align:right"><form method="post" action="<?= e(url('admin/feedback/'.$r['id'].'/delete')) ?>" data-confirm="Stergi acest feedback?" style="display:inline"><?= csrf_field() ?><button class="lnk del">Sterge</button></form></td>
    </tr>
  <?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="6" class="muted">Niciun feedback<?= $rating?' cu aceasta nota':'' ?>. Adauga widget-ul „Formular feedback" pe afisaj sau partajeaza linkul <code><?= e(url('feedback')) ?></code>.</td></tr><?php endif; ?>
  </tbody></table>
  <?php if($pages>1): ?>
    <div style="display:flex;gap:.4rem;align-items:center;margin-top:1rem">
      <?php if($page>1): ?><a class="btn btn-ghost" href="<?= $qs($page-1) ?>">← Anterior</a><?php endif; ?>
      <span class="muted" style="font-size:.85rem">Pagina <?= $page ?> din <?= $pages ?></span>
      <?php if($page<$pages): ?><a class="btn btn-ghost" href="<?= $qs($page+1) ?>">Urmator →</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__.'/_footer.php'; ?>
