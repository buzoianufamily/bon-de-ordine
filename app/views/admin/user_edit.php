<?php $title=$row?'Editare utilizator':'Utilizator nou'; $active='users'; require __DIR__.'/_header.php'; $v=fn($k,$d='')=>e($row[$k]??$d); ?>
<div class="topbar"><h1><?= $row?'Editare utilizator':'Utilizator nou' ?></h1><a class="btn btn-ghost" href="<?= e(url('admin/users')) ?>">← Inapoi</a></div>
<form method="post" action="<?= e(url('admin/users')) ?>"><?= csrf_field() ?>
  <?php if($row): ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><?php endif; ?>
  <div class="card pad" style="max-width:560px">
    <div class="field"><label>Nume</label><input name="name" value="<?= $v('name') ?>" required></div>
    <div class="field"><label>Email</label><input type="email" name="email" value="<?= $v('email') ?>" required></div>
    <div class="row">
      <div class="field"><label>Rol</label><select name="role">
        <?php foreach(['agent'=>'Operator','manager'=>'Manager','admin'=>'Administrator'] as $k=>$lab): ?>
          <option value="<?= $k ?>" <?= ($row['role']??'agent')===$k?'selected':'' ?>><?= $lab ?></option><?php endforeach; ?></select></div>
      <div class="field" style="display:flex;align-items:flex-end"><label style="margin:0"><input type="checkbox" name="active" <?= ($row['active']??1)?'checked':'' ?> style="width:auto"> Activ</label></div>
    </div>
    <div class="field"><label>Parola <?= $row?'(lasa gol = nu schimba)':'' ?></label><input type="password" name="password" <?= $row?'':'required' ?>></div>
    <div class="field"><label>PIN terminal (opțional, doar cifre)</label><input type="text" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="12" value="<?= e($row['pin']??'') ?>" placeholder="ex: 1234">
      <p class="muted" style="font-size:.78rem;margin-top:.3rem">Permite schimbarea rapidă a operatorului la terminal („Schimbă operator"). Recomandat doar pentru operatori, pe dispozitive de încredere.</p></div>
    <div class="field"><label style="margin:0"><input type="checkbox" name="notify_browser" <?= ($row['notify_browser']??0)?'checked':'' ?> style="width:auto"> Notificari in browser la terminalul operatorului (anunta biletele noi / transferate)</label></div>
    <?php if(!empty($counters)): $allowed = array_filter(array_map('intval', explode(',', (string)($row['allowed_counters'] ?? '')))); ?>
    <div class="field"><label>Ghisee permise <span class="muted" style="font-weight:400">(nimic bifat = poate deschide orice ghiseu)</span></label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.25rem;margin-top:.3rem">
        <?php foreach($counters as $c): ?>
          <label style="display:flex;align-items:center;gap:.4rem;margin:0;font-weight:500"><input type="checkbox" name="allowed_counters[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'],$allowed,true)?'checked':'' ?> style="width:auto"><?= e($c['code'].' · '.$c['name']) ?> <span class="muted" style="font-size:.72rem"><?= e($c['branch_name']) ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php if($row && ($row['totp_enabled']??0)): ?>
      <div class="field"><label style="margin:0"><input type="checkbox" name="reset_2fa" style="width:auto"> 🛡 Reseteaza 2FA (utilizatorul are 2FA activ; bifeaza daca a pierdut accesul la aplicatie)</label></div>
    <?php elseif($row): ?>
      <p class="muted" style="font-size:.82rem">2FA: dezactivat pentru acest utilizator.</p>
    <?php endif; ?>
    <button class="btn btn-primary btn-lg">Salveaza</button>
  </div>
</form>
<?php require __DIR__.'/_footer.php'; ?>
