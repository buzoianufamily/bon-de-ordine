# Securitate — Bon de ordine

Rezumatul măsurilor de securitate și recomandări de hardening.

## Implementat în aplicație
- **Parole**: bcrypt (`password_hash`), re-hash automat la schimbarea algoritmului. Nu se stochează parole în clar.
- **Sesiuni**: cookie `HttpOnly`, `SameSite=Lax`, `Secure` pe HTTPS, legat de host (izolare între subdomenii/tenanti), `session_regenerate_id` la login.
- **CSRF**: token verificat (`hash_equals`) pe toate acțiunile POST din admin și pe API-ul intern al operatorului.
- **SQL**: exclusiv prepared statements (PDO, `EMULATE_PREPARES=false`). Fără concatenare de input în SQL.
- **XSS**: tot ce se afișează trece prin `e()` (`htmlspecialchars`). În JS se folosește escaping la randare.
- **Brute-force login**: max 10 încercări eșuate / IP în 10 minute (din `audit_log`), apoi cooldown.
- **Audit log**: cine/ce/când a modificat în admin (creare/modificare/ștergere/reset/regenerare cheie). Vezi Admin → Jurnal audit.
- **Antete de securitate**: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `HSTS` pe HTTPS — setate în `.htaccess` (mod_headers) și, ca fallback, la nivel PHP în `app/core/init.php`.
- **Foldere/fișiere sensibile blocate** prin `.htaccess`: `app/`, `config/`, `database/` și fișierele `*.sql|*.md|*.log|*.ini`. `Options -Indexes`.
- **Upload media**: validare MIME (finfo), extensii permise, limită 25MB, nume de fișier igienizat.
- **API public** (`/api/v1`): autentificat cu cheie (`X-Api-Key`), comparație `hash_equals`. Cheia se poate regenera.
- **Webhooks**: semnătură `HMAC-SHA256` opțională (`X-Signature`), timeout scurt; URL configurat de admin (fără SSRF din input neîncrezut).
- **Erori**: `display_errors=0` în producție (`app.env=production`).

## Recomandări la instalare
1. **HTTPS obligatoriu** (Let's Encrypt din cPanel). Activează redirect HTTP→HTTPS.
2. **Schimbă contul implicit** `admin@example.ro` / `123456` imediat, din Admin → Utilizatori.
3. Ține `app.env = production` în `config/config.php` (ascunde erorile).
4. Acordă userului MySQL doar privilegii pe baza proprie.
5. Fă backup periodic la baza de date.
6. Pentru API/webhooks: folosește un secret pentru webhook și păstrează cheia API secretă; regenereaz-o dacă a fost expusă.

## Posibile întăriri viitoare (opțional)
- Rate-limiting și pe API-ul public (pe cheie/IP).
- 2FA pentru conturile de admin.
- `Content-Security-Policy` strict (atenție la scripturile inline și la widget-ul iframe de pe afișaj).
- Rotația periodică a cheii API.
