<?php /* Dashboard landlord — pagina autonoma (nu foloseste baza de date a vreunei instante). */
$schemaNow = defined('APP_SCHEMA_VERSION') ? APP_SCHEMA_VERSION : 0;
$okCnt = 0; $errCnt = 0; $suspCnt = 0;
foreach ($rows as $r) { if (!$r['active']) { $suspCnt++; continue; } if (!empty($r['health']['ok'])) $okCnt++; else $errCnt++; }
?>
<!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Landlord · Instante</title>
<meta name="csrf" content="<?= e(csrf_token()) ?>"><meta name="base" content="<?= e(base_url()) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>?v=20">
<style>:root{--accent:#2563eb}
body{background:#0a0b0d;color:#e8eaef}
.llwrap{max-width:1180px;margin:0 auto;padding:1.4rem}
h1,h2,h3{color:#f3f5f8}
.card{background:#15171c;border-color:#1e2128}
input,select,textarea{background:#101216;color:#e8eaef;border-color:#272c36}
label{color:#aab1bd}
th{color:#838b98} td,th{border-color:#1e2128}
.muted{color:#838b98}
.btn{background:#1b1e25;color:#e8eaef;border-color:#2a2f3a}
.btn-primary{background:var(--accent);color:#fff;border-color:var(--accent)}
.btn-danger{background:#dc2626;color:#fff;border-color:#dc2626}
.hb{display:inline-flex;align-items:center;gap:.35rem;font-weight:700;font-size:.8rem}
.hb .d{width:8px;height:8px;border-radius:999px}
code{background:#101216;color:#7CFFB2;padding:.12rem .4rem;border-radius:5px}
</style></head>
<body>
<div class="llwrap">
  <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
    <h1 style="margin:0">🏢 Landlord</h1>
    <span class="muted">instante: <?= count($rows) ?> · <span style="color:#16a34a">✔ <?= $okCnt ?> ok</span><?= $errCnt ? ' · <span style="color:#dc2626">✖ '.$errCnt.' cu erori</span>' : '' ?><?= $suspCnt ? ' · ⏸ '.$suspCnt.' suspendate' : '' ?></span>
    <span style="margin-left:auto;display:flex;gap:.5rem">
      <a class="btn" href="<?= e(url('landlord')) ?>">↻ Reverifica</a>
      <a class="btn" href="<?= e(url('landlord/logout')) ?>">Iesire</a>
    </span>
  </div>

  <?php foreach (get_flashes() as $f): ?>
    <div class="pill" style="display:block;padding:.6rem .9rem;margin-bottom:.8rem;background:<?= $f['type']==='error'?'#fee2e2':'#dcfce7' ?>;color:<?= $f['type']==='error'?'#b91c1c':'#166534' ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>

  <?php if (empty($writable)): ?>
    <div class="pill" style="display:block;padding:.6rem .9rem;margin-bottom:.8rem;background:#fef3c7;color:#92400e">⚠ Folderul <code>config/</code> nu pare scriibil — salvarea instantelor va esua. Seteaza permisiuni 755/775 pe folder.</div>
  <?php endif; ?>

  <div class="card pad" style="margin-bottom:1.2rem;overflow-x:auto">
    <h3 style="margin-top:0">Instante & stare</h3>
    <table style="min-width:920px">
      <thead><tr><th>Host</th><th>Client</th><th>Stare</th><th>Schema</th><th>Bilete azi</th><th>Ultimul bon</th><th>Dispozitive</th><th>Utilizatori</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $h = $r['health']; ?>
        <tr>
          <td><a href="https://<?= e($r['host']) ?>" target="_blank" style="color:var(--accent);font-weight:700"><?= e($r['host']) ?></a>
            <?php if ($r['main']): ?><span class="muted" style="font-size:.74rem"> (principala)</span><?php endif; ?></td>
          <td><?= e($r['name'] ?: ($h['brand'] ?? '') ?: '—') ?><?= $r['note'] ? '<br><span class="muted" style="font-size:.76rem">'.e($r['note']).'</span>' : '' ?></td>
          <td>
            <?php if (!$r['active']): ?>
              <span class="hb" style="color:#d97706"><span class="d" style="background:#d97706"></span>Suspendata</span>
            <?php elseif (!empty($h['ok'])): ?>
              <span class="hb" style="color:#16a34a"><span class="d" style="background:#16a34a"></span>Functioneaza</span>
            <?php else: ?>
              <span class="hb" style="color:#dc2626"><span class="d" style="background:#dc2626"></span>EROARE</span>
              <div class="muted" style="font-size:.72rem;max-width:240px"><?= e(mb_substr((string)($h['error'] ?? ''), 0, 120)) ?></div>
            <?php endif; ?>
          </td>
          <td><?php if (!empty($h['ok'])): ?>
            v<?= (int)$h['schema'] ?><?= ((int)$h['schema'] < $schemaNow) ? ' <span class="pill" style="background:#fef3c7;color:#92400e;font-size:.66rem">veche</span>' : '' ?>
          <?php else: ?><span class="muted">—</span><?php endif; ?></td>
          <td><?= !empty($h['ok']) ? (int)$h['tickets_today'] : '—' ?></td>
          <td class="muted" style="font-size:.8rem"><?= !empty($h['ok']) && $h['last_ticket'] ? e(date('d.m H:i', strtotime($h['last_ticket']))) : '—' ?></td>
          <td><?= !empty($h['ok']) ? ((int)$h['dev_online'].' / '.(int)$h['dev_total'].' online') : '—' ?></td>
          <td><?= !empty($h['ok']) ? (int)$h['users'] : '—' ?></td>
          <td style="text-align:right;white-space:nowrap">
            <?php if (!$r['main']): ?>
              <a class="lnk" style="color:var(--accent);font-weight:700" href="<?= e(url('landlord')) ?>?edit=<?= e(rawurlencode($r['host'])) ?>#frm">Editeaza</a>
              <form method="post" action="<?= e(url('landlord/toggle')) ?>" style="display:inline;margin-left:.6rem"><?= csrf_field() ?>
                <input type="hidden" name="host" value="<?= e($r['host']) ?>">
                <button class="lnk" style="background:none;border:none;cursor:pointer;color:#d97706;font-weight:700;font:inherit"><?= $r['active'] ? '⏸ Suspenda' : '▶ Activeaza' ?></button></form>
              <form method="post" action="<?= e(url('landlord/delete')) ?>" style="display:inline;margin-left:.6rem" data-confirm="Scoti instanta din registru? Baza de date NU se sterge, dar subdomeniul nu va mai functiona."><?= csrf_field() ?>
                <input type="hidden" name="host" value="<?= e($r['host']) ?>">
                <button class="lnk del" style="background:none;border:none;cursor:pointer;color:#dc2626;font-weight:700;font:inherit">Sterge</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="row" style="align-items:flex-start">
    <form method="post" action="<?= e(url('landlord/save')) ?>" class="card pad" style="flex:1;min-width:320px" id="frm"><?= csrf_field() ?>
      <h3 style="margin-top:0"><?= $edit ? 'Editare instanta' : 'Instanta noua' ?></h3>
      <input type="hidden" name="orig" value="<?= e($edit['host'] ?? '') ?>">
      <div class="field"><label>Host (subdomeniu)</label><input name="host" value="<?= e($edit['host'] ?? '') ?>" placeholder="client1.domeniul-tau.ro" required></div>
      <div class="field"><label>Nume client (pentru tine)</label><input name="name" value="<?= e($edit['name'] ?? '') ?>" placeholder="Primaria X"></div>
      <div class="row">
        <div class="field"><label>Host DB</label><input name="db_host" value="<?= e($edit['db']['host'] ?? 'localhost') ?>"></div>
        <div class="field"><label>Nume baza de date</label><input name="db_name" value="<?= e($edit['db']['name'] ?? '') ?>" required></div>
      </div>
      <div class="row">
        <div class="field"><label>Utilizator DB</label><input name="db_user" value="<?= e($edit['db']['user'] ?? '') ?>" required></div>
        <div class="field"><label>Parola DB</label><input type="password" name="db_pass" value="<?= e($edit['db']['pass'] ?? '') ?>" autocomplete="new-password"></div>
      </div>
      <div class="field"><label>Notite (optional)</label><input name="note" value="<?= e($edit['note'] ?? '') ?>" placeholder="ex: contract pana in 2027, contact Ion 07xx"></div>
      <label style="display:block;margin:.4rem 0"><input type="checkbox" name="active" <?= ($edit === null || !empty($edit['active'])) ? 'checked' : '' ?> style="width:auto"> Activa</label>
      <div style="display:flex;gap:.5rem;margin-top:.6rem">
        <button class="btn btn-primary">Salveaza instanta</button>
        <?php if ($edit): ?><a class="btn" href="<?= e(url('landlord')) ?>">Renunta</a><?php endif; ?>
      </div>
    </form>

    <div class="card pad" style="flex:1;min-width:320px">
      <h3 style="margin-top:0">Cum adaugi un client nou (cPanel)</h3>
      <ol style="line-height:1.8;font-size:.92rem;margin:0;padding-left:1.2rem">
        <li><strong>Subdomeniu:</strong> cPanel → Domains → creeaza <code>client1.domeniul-tau.ro</code> cu <strong>acelasi document root</strong> ca aplicatia (sau un wildcard <code>*</code> o singura data).</li>
        <li><strong>Baza de date:</strong> cPanel → MySQL Databases → creeaza o baza + un utilizator noi, cu ALL PRIVILEGES.</li>
        <li><strong>Inregistreaza aici:</strong> completeaza formularul din stanga cu host + datele bazei.</li>
        <li><strong>Prima accesare</strong> a subdomeniului instaleaza automat schema, datele demo si adminul implicit (<code>admin@example.ro</code> / <code>123456</code>) — schimba-l imediat.</li>
        <li>Pentru remindere/rapoarte pe email: configureaza cate un <strong>cron</strong> per client (URL-ul de cron din API &amp; Webhooks al fiecarei instante).</li>
      </ol>
      <p class="muted" style="font-size:.8rem;margin-bottom:0">Suspendarea opreste instant accesul clientului (pagina „instanta suspendata"), fara sa stearga nimic. Versiunea curenta a schemei: <strong>v<?= (int)$schemaNow ?></strong> — instantele marcate „veche" se actualizeaza singure la prima accesare.</p>
    </div>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>?v=3"></script>
</body></html>
