<?php $title='Grupuri servicii'; $active='groups'; require __DIR__.'/_header.php';
/* arbore: grupuri de nivel 0 + subgrupuri, pe filiala */
$childrenOf = []; $topByBranch = [];
foreach ($rows as $g) { $pid = (int)($g['parent_id'] ?? 0);
  if ($pid) $childrenOf[$pid][] = $g; else $topByBranch[(int)$g['branch_id']][] = $g; }
$groupById = []; foreach ($rows as $g) $groupById[(int)$g['id']] = $g;
$svcByGroup = []; $ungroupedByBranch = [];
foreach ($services as $s) { $gid=(int)($s['group_id']??0); if ($gid) $svcByGroup[$gid][]=$s; else $ungroupedByBranch[(int)$s['branch_id']][]=$s; }
/* optiunile pentru „grup parinte" pe filiala (JSON pentru formular) */
$parentOpts = [];
foreach ($rows as $g) $parentOpts[(int)$g['branch_id']][] = ['id'=>(int)$g['id'],'name'=>$g['name'],'parent_id'=>(int)($g['parent_id']??0)];
function grp_row(array $g, int $depth, array $childrenOf, array $svcByGroup): void {
  $pad = 0.4 + $depth * 1.4; ?>
  <tr data-id="<?= (int)$g['id'] ?>">
    <td style="padding-left:<?= $pad ?>rem">
      <?php if($depth>0): ?><span class="muted" style="margin-right:.3rem">↳</span><?php endif; ?>
      <span class="tag" style="background:<?= e($g['color']) ?>;width:16px;height:16px"></span>
      <strong><?= e($g['name']) ?></strong>
      <span class="muted" style="font-size:.78rem;margin-left:.4rem"><?= (int)$g['svc'] ?> serv.<?= (int)$g['subgroups']>0 ? ' · '.(int)$g['subgroups'].' subgr.' : '' ?></span>
    </td>
    <td class="muted" style="font-size:.82rem"><?php $svcs=$svcByGroup[(int)$g['id']]??[]; echo $svcs ? e(implode(', ', array_map(fn($s)=>$s['prefix'].' '.$s['name'], array_slice($svcs,0,4)))).(count($svcs)>4?'…':'') : '—'; ?></td>
    <td style="text-align:right;white-space:nowrap">
      <a class="lnk" href="#" onclick='gEdit(<?= json_encode(["id"=>(int)$g["id"],"branch_id"=>(int)$g["branch_id"],"name"=>$g["name"],"color"=>$g["color"],"parent_id"=>(int)($g["parent_id"]??0),"sort_order"=>(int)$g["sort_order"]], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>);return false'>Editeaza</a>
      <form method="post" action="<?= e(url('admin/groups/'.$g['id'].'/delete')) ?>" style="display:inline;margin-left:.7rem" data-confirm="Stergi grupul? Serviciile raman (fara grup), iar subgrupurile devin grupuri de nivel 0."><?= csrf_field() ?><button class="lnk del">Sterge</button></form>
    </td>
  </tr>
  <?php foreach (($childrenOf[(int)$g['id']] ?? []) as $child) grp_row($child, $depth+1, $childrenOf, $svcByGroup);
}
?>
<div class="topbar"><h1>Grupuri &amp; subgrupuri servicii</h1></div>
<p class="muted" style="margin-top:-.6rem;max-width:760px">Organizeaza serviciile pe categorii și subcategorii (ex: <strong>Financiar → Casierie, Taxe</strong>). Pe dispenser, cu navigarea „pe categorii" activata (Configurare dispenser → Logic), clientul apasa pe categorie și vede serviciile din ea — util cand ai multe servicii.</p>

<div class="row" style="align-items:flex-start">
  <form method="post" action="<?= e(url('admin/groups')) ?>" class="card pad" style="flex:0 0 320px"><?= csrf_field() ?>
    <input type="hidden" name="id" id="g_id" value="">
    <h3 style="margin-top:0" id="g_title">Grup nou</h3>
    <div class="field"><label>Filiala</label><select name="branch_id" id="g_branch" onchange="gParents()">
      <?php foreach($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><label>Nume grup</label><input name="name" id="g_name" required></div>
    <div class="field"><label>Grup parinte <span class="muted" style="font-weight:400">(optional — creeaza un subgrup)</span></label>
      <select name="parent_id" id="g_parent"><option value="0">— fara (grup de nivel 0)</option></select></div>
    <div class="row">
      <div class="field"><label>Culoare</label><input type="color" name="color" id="g_color" value="#64748b"></div>
      <div class="field"><label>Ordine</label><input type="number" name="sort_order" id="g_sort" value="0"></div>
    </div>
    <button class="btn btn-primary">Salveaza</button>
    <a class="btn btn-ghost" href="<?= e(url('admin/groups')) ?>" id="g_reset" style="display:none">Anuleaza</a>
  </form>

  <div class="card pad" style="flex:1;min-width:340px">
    <?php foreach($branches as $b): $bid=(int)$b['id']; $tops=$topByBranch[$bid]??[]; if(!$tops && empty($ungroupedByBranch[$bid])) continue; ?>
      <h3 style="margin:.2rem 0 .6rem"><?= e($b['name']) ?></h3>
      <table style="margin-bottom:1rem"><thead><tr><th>Grup / subgrup</th><th>Servicii</th><th></th></tr></thead><tbody>
        <?php foreach($tops as $g) grp_row($g, 0, $childrenOf, $svcByGroup); ?>
        <?php if(!$tops): ?><tr><td colspan="3" class="muted">Niciun grup in aceasta filiala.</td></tr><?php endif; ?>
      </tbody></table>
    <?php endforeach; ?>
    <?php if(!$rows): ?><p class="muted">Niciun grup. Creeaza primul in stanga.</p><?php endif; ?>
  </div>
</div>

<div class="card pad" style="margin-top:1.2rem">
  <h3 style="margin-top:0">Atribuie servicii la grupuri</h3>
  <p class="muted" style="font-size:.82rem;margin-top:0">Muta fiecare serviciu intr-un grup (sau subgrup). Poti face asta si din pagina serviciului.</p>
  <div style="overflow-x:auto"><table style="min-width:520px"><thead><tr><th>Serviciu</th><th>Filiala</th><th style="width:260px">Grup</th></tr></thead><tbody>
    <?php foreach($services as $s): $bid=(int)$s['branch_id']; ?>
      <tr>
        <td><span class="tag" style="background:<?= e($s['color']) ?>;width:16px;height:16px"></span> <strong><?= e($s['prefix']) ?></strong> · <?= e($s['name']) ?></td>
        <td class="muted"><?php $bn=''; foreach($branches as $b) if((int)$b['id']===$bid) $bn=$b['name']; echo e($bn); ?></td>
        <td>
          <form method="post" action="<?= e(url('admin/groups/assign')) ?>" style="display:flex;gap:.4rem"><?= csrf_field() ?>
            <input type="hidden" name="service_id" value="<?= (int)$s['id'] ?>">
            <select name="group_id" onchange="this.form.submit()" style="width:auto;flex:1">
              <option value="0">— fara grup —</option>
              <?php foreach($rows as $g): if((int)$g['branch_id']!==$bid) continue; $isSub=(int)($g['parent_id']??0)>0; ?>
                <option value="<?= (int)$g['id'] ?>" <?= (int)($s['group_id']??0)===(int)$g['id']?'selected':'' ?>><?= $isSub?'   ↳ ':'' ?><?= e($g['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$services): ?><tr><td colspan="3" class="muted">Niciun serviciu activ.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<script>
var G_PARENTS = <?= json_encode($parentOpts, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>;
var G_EDIT_ID = 0;
function gParents(){
  var branch = +document.getElementById('g_branch').value, sel = document.getElementById('g_parent'), cur = +sel.value||0;
  var list = G_PARENTS[branch] || [];
  sel.innerHTML = '<option value="0">— fara (grup de nivel 0)</option>' + list
    .filter(function(g){ return g.id !== G_EDIT_ID; })   // nu poate fi propriul parinte
    .map(function(g){ return '<option value="'+g.id+'"'+(g.id===cur?' selected':'')+'>'+(g.parent_id?'   ↳ ':'')+g.name.replace(/[<>&]/g,'')+'</option>'; }).join('');
}
function gEdit(g){
  G_EDIT_ID = g.id;
  document.getElementById('g_id').value=g.id; document.getElementById('g_branch').value=g.branch_id;
  document.getElementById('g_name').value=g.name; document.getElementById('g_color').value=g.color||'#64748b';
  document.getElementById('g_sort').value=g.sort_order; document.getElementById('g_title').textContent='Editare grup';
  gParents(); document.getElementById('g_parent').value = g.parent_id||0;
  document.getElementById('g_reset').style.display=''; window.scrollTo({top:0,behavior:'smooth'});
}
gParents();
</script>
<?php require __DIR__.'/_footer.php'; ?>
