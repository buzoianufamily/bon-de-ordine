# Securitate — Bon de ordine

Rezumatul măsurilor de securitate și recomandări de hardening.

## Implementat în aplicație
- **Parole**: bcrypt (`password_hash`), re-hash automat la schimbarea algoritmului. Nu se stochează parole în clar.
- **Sesiuni**: cookie `HttpOnly`, `SameSite=Lax`, `Secure` pe HTTPS, legat de host (izolare între subdomenii/tenanti), `session_regenerate_id` la login.
- **CSRF**: token verificat (`hash_equals`) pe toate acțiunile POST din admin și pe API-ul intern al operatorului.
- **Control acces**: backoffice-ul cere rol (`admin`/`manager`); restul rutelor cer autentificare. Operatorii legați de anumite ghișee (`allowed_counters`) sunt restricționați **și la nivel de API** (call-next / call-specific / pauză / deschidere ghișeu returnează `403` pentru un ghișeu nepermis) — restricția din interfață nu poate fi ocolită prin apeluri directe.
- **SQL**: exclusiv prepared statements (PDO, `EMULATE_PREPARES=false`). Fără concatenare de input în SQL.
- **XSS**: tot ce se afișează trece prin `e()` (`htmlspecialchars`). Datele incluse în blocuri `<script>`/atribute `on*` sunt encodate cu `jsenc()` (`JSON_HEX_*`), deci textele editabile din admin nu pot ieși din context. În JS se folosește escaping la randare.
- **Brute-force**: login — max 10 încercări eșuate / IP în 10 minute (din `audit_log`), apoi cooldown. **Pasul 2FA** aplică același prag (codul de 6 cifre nu poate fi forțat chiar dacă parola e cunoscută). Schimbarea rapidă de operator prin **PIN** este limitată la 10 încercări / 5 minute.
- **Limitare cereri publice (anti-spam/DoS)**: feedback (3 / 10 min / IP), lista de așteptare (6 / 10 min / IP) și **rezervările online** (10 / 10 min / IP). Emiterea de bonuri prin `POST /api/ticket` cere o **cheie de dispozitiv validă** (altfel `403`) — nu se poate inunda coada din exterior.
- **2FA (TOTP)**: autentificare în doi pași opțională pentru conturile de backoffice (Google Authenticator/Authy etc.), implementată în PHP pur (RFC 6238), cu **coduri de recuperare**, **coduri de unică folosință** (un cod TOTP acceptat nu mai poate fi reutilizat — anti-replay, RFC 6238 §5.2) și politică „obligatoriu pentru admini". Codul QR de configurare este generat **local** (SVG, fără servicii externe) — secretul 2FA nu părăsește serverul. Se activează din Admin → Securitate; un alt admin poate reseta 2FA din Utilizatori.
- **Coduri QR fără servicii externe**: 2FA, cheile dispozitivelor și biletele digitale folosesc un generator QR propriu (`app/core/qr.php`) — funcționează offline și nu expun date unor terți.
- **Audit log**: cine/ce/când a modificat în admin (creare/modificare/ștergere/reset/regenerare cheie). Vezi Admin → Jurnal audit.
- **Antete de securitate**: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `HSTS` pe HTTPS — setate în `.htaccess` (mod_headers) și, ca fallback, la nivel PHP în `app/core/init.php`.
- **Content-Security-Policy**: setată la nivel PHP (toate resursele sunt locale — fonturi incluse, fără CDN-uri). `default-src 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'self'`; blochează scripturile/stilurile/imaginile externe injectate (clasă comună de XSS). `'unsafe-inline'` rămâne necesar pentru scripturile/stilurile inline din interfață. Dezactivabilă cu `$config['app']['csp'] = false`.
- **Fără indexare în motoarele de căutare**: `X-Robots-Tag: noindex, nofollow` pe toate răspunsurile + `robots.txt` cu `Disallow: /` — paginile cu jetoane personale (bilet/programare) nu ajung în Google.
- **Foldere/fișiere sensibile blocate** prin `.htaccess`: `app/`, `config/`, `database/` și fișierele `*.sql|*.md|*.log|*.ini|*.json|*.env` (inclusiv `tenants.json`). `Options -Indexes`.
- **Upload media**: validare MIME (finfo), extensii permise, limită 25MB, nume de fișier igienizat.
- **Export CSV/Excel**: protecție anti-injecție de formule — celulele CSV care încep cu `=`, `+`, `-`, `@` (date introduse de public: comentarii feedback, nume/telefon clienți) sunt prefixate cu apostrof, deci nu se execută ca formule la deschiderea în Excel/Sheets; exportul Excel scrie valorile ca șiruri „inline" (niciodată formule). Importul CSV ignoră BOM-ul UTF-8 (fișiere Excel), deci antetul nu ajunge date.
- **API public** (`/api/v1`): autentificat cu cheie (`X-Api-Key`), comparație `hash_equals`. Cheia se poate regenera.
- **Webhooks**: semnătură `HMAC-SHA256` opțională (`X-Signature`), timeout scurt; URL configurat de admin (fără SSRF din input neîncrezut). Jurnal de livrări (ultimele 100) pentru depanare, în Admin → API & Webhooks.
- **Email (SMTP)**: verificare hostname certificat (`verify_peer` + `verify_peer_name`) la conexiunea TLS — protecție MITM.
- **Printare în rețea**: IP-ul imprimantei (mod „rețea") trebuie să fie un IP valid — împiedică folosirea conexiunii de printare pentru SSRF.
- **Erori**: `display_errors=0` în producție (`app.env=production`).

## Recomandări la instalare
1. **HTTPS obligatoriu** (Let's Encrypt din cPanel). Activează redirect HTTP→HTTPS.
2. **Parola implicită** a contului de administrator trebuie schimbată — în producție aplicația o cere automat la prima logare (redirect la „Contul meu", blocând zonele de administrare până la schimbare). Emailul îl poți schimba ulterior din Admin → Utilizatori.
3. Ține `app.env = production` în `config/config.php` (ascunde erorile).
4. Acordă userului MySQL doar privilegii pe baza proprie.
5. Fă backup periodic la baza de date.
6. Pentru API/webhooks: folosește un secret pentru webhook și păstrează cheia API secretă; regenereaz-o dacă a fost expusă.

## Posibile întăriri viitoare (opțional)
- `Content-Security-Policy` cu **nonce** (eliminarea lui `'unsafe-inline'`) — necesită adăugarea unui nonce la fiecare script/stil inline. Acum CSP e activă cu `'unsafe-inline'`. Widget-ul iframe de pe afișaj rulează `sandbox` fără `allow-same-origin` și acceptă doar URL-uri `http(s)`.
- Rotația periodică a cheii API.
- Pe Nginx, replică regulile `.htaccess` (blocarea `config/`, `database/`, `app/`, `*.sql|*.json|*.env`) în configurația serverului.
