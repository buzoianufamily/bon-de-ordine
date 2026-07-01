<?php $title=$row?'Editare utilizator':'Utilizator nou'; $active='users'; require __DIR__.'/_header.php'; $v=fn($k,$d='')=>e($row[$k]??$d); ?>
<div class="topbar">
  <div>
    <div class="crumb"><a href="<?= e(url('admin/users')) ?>">Utilizatori</a> › <?= $row?e($row['name']):'Utilizator nou' ?></div>
    <h1 style="margin:.1rem 0"><?= $row?'Editare utilizator':'Utilizator nou' ?></h1>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('admin/users')) ?>">← Inapoi</a>
</div>
<form method="post" action="<?= e(url('admin/users')) ?>"><?= csrf_field() ?>
  <?php if($row): ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><?php endif; ?>
  <div class="card pad">

    <div class="formsec">
      <div class="sech"><span class="ic">◉</span><div><h3>Date utilizator</h3><p>Cont, rol si parola.</p></div></div>
      <div class="secb">
        <div class="field"><label>Nume</label><input name="name" value="<?= $v('name') ?>" maxlength="120" required></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="<?= $v('email') ?>" maxlength="160" required></div>
        <div class="row">
          <div class="field"><label>Rol</label><select name="role">
            <?php foreach(['agent'=>'Operator','manager'=>'Manager','admin'=>'Administrator'] as $k=>$lab): ?>
              <option value="<?= $k ?>" <?= ($row['role']??'agent')===$k?'selected':'' ?>><?= $lab ?></option><?php endforeach; ?></select></div>
          <div class="field" style="display:flex;align-items:flex-end"><label class="switch" style="margin:0 0 .6rem"><input type="checkbox" name="active" <?= ($row['active']??1)?'checked':'' ?>><span class="track"></span> Activ</label></div>
        </div>
        <div class="field"><label>Parola <?= $row?'(lasa gol = nu schimba)':'' ?></label><input type="password" name="password" autocomplete="new-password" <?= $row?'':'required' ?>></div>
      </div>
    </div>

    <div class="formsec">
      <div class="sech"><span class="ic">🔑</span><div><h3>Acces & securitate</h3><p>Notificari, ghisee permise si 2FA.</p></div></div>
      <div class="secb">
        <label class="switch"><input type="checkbox" name="notify_browser" <?= ($row['notify_browser']??0)?'checked':'' ?>><span class="track"></span> Notificari in browser la terminal (bilete noi / transferate)</label>
        <?php if(!empty($counters)): $allowed = array_filter(array_map('intval', explode(',', (string)($row['allowed_counters'] ?? '')))); ?>
        <div class="field" style="margin-top:.9rem"><label>Ghisee permise <span class="muted" style="font-weight:400">(nimic bifat = poate deschide orice ghiseu)</span></label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.25rem;margin-top:.3rem">
            <?php foreach($counters as $c): ?>
              <label style="display:flex;align-items:center;gap:.4rem;margin:0;font-weight:500"><input type="checkbox" name="allowed_counters[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'],$allowed,true)?'checked':'' ?>><?= e($c['code'].' · '.$c['name']) ?> <span class="muted" style="font-size:.72rem"><?= e($c['branch_name']) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if($row && ($row['totp_enabled']??0)): ?>
          <label class="switch" style="margin-top:.9rem"><input type="checkbox" name="reset_2fa"><span class="track"></span> 🛡 Reseteaza 2FA (utilizatorul are 2FA activ; bifeaza daca a pierdut accesul)</label>
        <?php elseif($row): ?>
          <p class="muted" style="font-size:.82rem;margin-top:.9rem">2FA: dezactivat pentru acest utilizator.</p>
        <?php endif; ?>
      </div>
    </div>

    <button class="btn btn-primary btn-lg">Salveaza</button>
  </div>
</form>
<?php require __DIR__.'/_footer.php'; ?>
