/* Helperi comuni */
window.QMS = {
  csrf: () => document.querySelector('meta[name="csrf"]')?.content || '',
  base: () => document.querySelector('meta[name="base"]')?.content || '',
  async api(path, data = null, method = 'POST') {
    const opt = { method, headers: { 'X-Requested-With': 'XMLHttpRequest' } };
    if (data) { opt.headers['Content-Type'] = 'application/json'; opt.headers['X-CSRF'] = this.csrf();
      opt.body = JSON.stringify(data); }
    const r = await fetch(this.base() + '/' + path.replace(/^\//, ''), opt);
    return r.json();
  },
  toast(msg, type = '') {
    let w = document.querySelector('.toast-wrap');
    if (!w) { w = document.createElement('div'); w.className = 'toast-wrap'; document.body.appendChild(w); }
    const t = document.createElement('div'); t.className = 'toast ' + type; t.textContent = msg;
    w.appendChild(t); setTimeout(() => t.remove(), 3200);
  }
};
