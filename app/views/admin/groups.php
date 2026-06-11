<?php $title='Grupuri servicii'; $active='groups'; require __DIR__.'/_header.php'; ?>
<div class="topbar"><h1>Grupuri servicii</h1></div>
<p class="muted" style="margin-top:-.6rem;max-width:680px">Grupeaza serviciile pe categorii (ex: „Acte", „Plati"). Pe dispenser, serviciile apar sub antetul grupului. Asociezi un serviciu unui grup din pagina serviciului.</p>

<div class="row" style="align-items:flex-start">
  <form method="post" action="<?= e(url('admin/groups')) ?>" class="card pad" style="flex:0 0 320px"><?= csrf_field() ?>
    <input type="hidden" name="id" id="g_id" value="">
    <h3 style="margin-top:0" id="g_title">Grup nou</h3>
    <div class="field"><label>Filiala</label><select name="branch_id" id="g_branch">
      <?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><label>Nume grup</label><input name="name" id="g_name" required></div>
    <div class="row">
      <div class="field"><label>Culoare</label><input type="color" name="color" id="g_color" value="#64748b"></div>
      <div class="field"><label>Ordine</label><input type="number" name="sort_order" id="g_sort" value="0"></div>
    </div>
    <button class="btn btn-primary">Salveaza</button>
    <a class="btn btn-ghost" href="<?= e(url('admin/groups')) ?>" id="g_reset" style="display:none">Anuleaza</a>
  </form>

  <div class="card pad" style="flex:1">
    <table><thead><tr><th>Grup</th><th>Filiala</th><th>Servicii</th><th>Ordine</th><th></th></tr></thead><tbody>
    <?php foreach($rows as $g): ?>
      <tr>
        <td><span class="tag" style="background:<?= e($g['color']) ?>;width:18px;height:18px"></span> <strong><?= e($g['name']) ?></strong></td>
        <td class="muted"><?= e($g['branch_name']) ?></td>
        <td><?= (int)$g['svc'] ?></td>
        <td class="muted"><?= (int)$g['sort_order'] ?></td>
        <td style="text-align:right;white-space:nowrap">
          <a class="lnk" href="#" onclick='gEdit(<?= json_encode(["id"=>(int)$g["id"],"branch_id"=>(int)$g["branch_id"],"name"=>$g["name"],"color"=>$g["color"],"sort_order"=>(int)$g["sort_order"]], JSON_UNESCAPED_UNICODE) ?>);return false'>Editeaza</a>
          <form method="post" action="<?= e(url('admin/groups/'.$g['id'].'/delete')) ?>" style="display:inline;margin-left:.7rem" data-confirm="Stergi grupul? Serviciile raman, dar fara grup."><?= csrf_field() ?><button class="lnk del">Sterge</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$rows): ?><tr><td colspan="5" class="muted">Niciun grup. Creeaza primul in stanga.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<script>
function gEdit(g){
  document.getElementById('g_id').value=g.id; document.getElementById('g_branch').value=g.branch_id;
  document.getElementById('g_name').value=g.name; document.getElementById('g_color').value=g.color||'#64748b';
  document.getElementById('g_sort').value=g.sort_order; document.getElementById('g_title').textContent='Editare grup';
  document.getElementById('g_reset').style.display=''; window.scrollTo({top:0,behavior:'smooth'});
}
</script>
<?php require __DIR__.'/_footer.php'; ?>
