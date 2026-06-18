/* Helperi comuni */
window.QMS = {
  csrf: () => document.querySelector('meta[name="csrf"]')?.content || '',
  base: () => document.querySelector('meta[name="base"]')?.content || '',
  async api(path, data = null, method = 'POST') {
    const opt = { method, headers: { 'X-Requested-With': 'XMLHttpRequest' } };
    if (data) { opt.headers['Content-Type'] = 'application/json'; opt.headers['X-CSRF'] = this.csrf();
      opt.body = JSON.stringify(data); }
    try {
      const r = await fetch(this.base() + '/' + path.replace(/^\//, ''), opt);
      const txt = await r.text();
      try { return JSON.parse(txt); }
      catch (_) { return { ok: false, error: 'Raspuns invalid de la server (' + r.status + ')' }; }
    } catch (_) {
      return { ok: false, error: 'Eroare de retea. Verifica conexiunea.' };
    }
  },
  toast(msg, type = '') {
    let w = document.querySelector('.toast-wrap');
    if (!w) { w = document.createElement('div'); w.className = 'toast-wrap'; w.setAttribute('role','status'); w.setAttribute('aria-live','polite'); document.body.appendChild(w); }
    const t = document.createElement('div'); t.className = 'toast ' + type; t.textContent = msg;
    w.appendChild(t); setTimeout(() => t.remove(), 3200);
  },
  /* modal de confirmare pe brand (inlocuieste confirm() nativ); intoarce Promise<boolean> */
  confirm(msg, opt = {}) {
    return new Promise(resolve => {
      const ov = document.createElement('div'); ov.className = 'qmodal';
      const card = document.createElement('div'); card.className = 'qm-card';
      const p = document.createElement('p'); p.className = 'qm-msg'; p.textContent = msg;
      const btns = document.createElement('div'); btns.className = 'qm-btns';
      const bNo = document.createElement('button'); bNo.type = 'button'; bNo.className = 'btn'; bNo.textContent = opt.cancel || 'Anuleaza';
      const bYes = document.createElement('button'); bYes.type = 'button'; bYes.className = 'btn ' + (opt.danger ? 'btn-danger' : 'btn-primary'); bYes.textContent = opt.ok || 'Confirma';
      const done = v => { ov.remove(); document.removeEventListener('keydown', esc); resolve(v); };
      const esc = e => { if (e.key === 'Escape') done(false); };
      bNo.onclick = () => done(false); bYes.onclick = () => done(true);
      ov.onclick = e => { if (e.target === ov) done(false); };
      document.addEventListener('keydown', esc);
      btns.append(bNo, bYes); card.append(p, btns); ov.append(card); document.body.append(ov);
      bYes.focus();
    });
  },
  /* modal cu camp text; intoarce Promise<string|null> (null = anulat) */
  prompt(msg, opt = {}) {
    return new Promise(resolve => {
      const ov = document.createElement('div'); ov.className = 'qmodal';
      const card = document.createElement('div'); card.className = 'qm-card';
      const p = document.createElement('p'); p.className = 'qm-msg'; p.textContent = msg;
      const inp = document.createElement('input'); inp.type = 'text'; inp.placeholder = opt.placeholder || '';
      inp.value = opt.value || ''; inp.style.marginBottom = '1rem';
      const btns = document.createElement('div'); btns.className = 'qm-btns';
      const bNo = document.createElement('button'); bNo.type = 'button'; bNo.className = 'btn'; bNo.textContent = opt.cancel || 'Anuleaza';
      const bYes = document.createElement('button'); bYes.type = 'button'; bYes.className = 'btn btn-primary'; bYes.textContent = opt.ok || 'OK';
      const done = v => { ov.remove(); document.removeEventListener('keydown', esc); resolve(v); };
      const esc = e => { if (e.key === 'Escape') done(null); };
      bNo.onclick = () => done(null); bYes.onclick = () => done(inp.value);
      inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); done(inp.value); } });
      ov.onclick = e => { if (e.target === ov) done(null); };
      document.addEventListener('keydown', esc);
      btns.append(bNo, bYes); card.append(p, inp, btns); ov.append(card); document.body.append(ov);
      inp.focus();
    });
  }
};

/* formularele cu data-confirm="mesaj" primesc automat modalul de confirmare */
document.addEventListener('submit', function (e) {
  const f = e.target.closest ? e.target.closest('form[data-confirm]') : null;
  if (!f || f.__qok) return;
  e.preventDefault();
  QMS.confirm(f.getAttribute('data-confirm'), { danger: !!f.querySelector('.btn-danger,.del'), ok: 'Da, continua' })
    .then(ok => { if (ok) { f.__qok = 1; f.requestSubmit ? f.requestSubmit() : f.submit(); } });
}, true);
