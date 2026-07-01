# Istoric versiuni — Bon de ordine

Sistem de gestionare a cozilor (multi-tenant, PHP 8 + MySQL/MariaDB), pregătit pentru
vânzare pe abonament. Versiunea schemei bazei de date este urcată automat la fiecare
accesare (`schema_version`); mai jos, capacitățile grupate pe teme.

## Schema curentă: v33

---

## Dispenser cu editor canvas (ca afisorul TV)
- **Editor canvas pentru dispenser** (opt-in): acelasi motor ca afisorul TV — ecrane multiple,
  orientare/aspect, fundal per ecran, widgeturi (ceas, vreme, imagine, logo, text, QR, ticker, liste bilete…),
  plus un widget interactiv nou **„Grila servicii"** (butoanele care emit bonul).
- Editorul clasic (form) ramane **implicit** si e pastrat ca fallback — dispozitivele existente NU se strica;
  layoutul canvas se foloseste doar cand e construit si activat (`config.canvas.enabled`).
- Fluxul de emitere (tip/politica/formular → bon → print/QR/check-in) e reutilizat integral prin delegare de
  evenimente, deci functioneaza identic si pe butoanele randate de canvas.
- Deschizi editorul din Dispozitive → „🎨 Canvas" sau din editorul clasic → „🎨 Editor canvas (ca la TV)".

## Cont, profil & separarea furnizor/client
- **Meniul contului** (chip dreapta-sus): editare profil (nume, email, **telefon**, parolă) + **Iesire**.
- Butonul **„Portal"** în bara laterală (înlocuiește „Iesire") — duce la pagina publică de start.
- **Verificare „pregătit de producție"** mutată din adminul clientului în **`/landlord`** —
  verificări **per instanță** citite din baza de date a fiecărui client (parolă implicită, 2FA,
  email, backup, cron, retenție GDPR, date legale).
- **Cron** scos din pagina API a clientului → afișat în `/landlord` (linkul de cron per instanță).
- **Media:** se acceptă orice tip de fișier (limita = a serverului PHP); SVG-ul este **curățat**
  de scripturi la încărcare (anti-XSS), extensiile executabile pe server rămân blocate.
- **Afișaj de ghișeu** (`/cd/…`): texte editabile din Setări → Afișaj (mesaj „în așteptare" / „bon chemat").
- Pagini admin pe **toată lățimea** (editare utilizator/serviciu/ghișeu/dispozitiv, roluri, GDPR, securitate, backup).

## Maturizare pentru vânzare pe abonament

### Fiabilitate („fără ecrane albe")
- Pagini de eroare prietenoase + handler global de erori/excepții (fără scurgeri în producție).
- Sondă de uptime `/health` (JSON: stare DB + versiune schemă), pentru monitorizare externă.
- **Verificare „pregătit de producție"** (parolă implicită, email, backup, cron, 2FA, retenție,
  date legale, permisiuni, plan) — disponibilă **per instanță** în panoul furnizorului `/landlord`.
- Fus orar aliniat între PHP și sesiunea MySQL.

### Ciclu de abonament & monetizare
- Stare instanță: activ / **suspendat** / **abonament expirat** (cu perioadă de grație),
  cu suspendare automată și pagini branded (503 / 402), fără ecran alb.
- **Limite de plan per client** (filiale / ghișee / utilizatori / servicii), aplicate automat
  inclusiv la import CSV și duplicare — `0 = nelimitat`. Configurabile în panoul landlord.
- **Facturare în landlord**: proformă/factură printabilă (Print → PDF, fără dependențe),
  numerotare fără goluri pe serie+an (proformele au pool separat, fără reutilizare la ștergere),
  evidența plăților, scriere concurentă protejată prin lock.
- **Pregătire pentru producție**: ștergerea datelor de test păstrând configurația, cu backup
  automat de siguranță înainte și confirmare.

### Conformitate legală & GDPR
- Pagini publice **Confidențialitate** + **Termeni** (șablon RO, ANSPDCP), completate din
  setările operatorului; redirect către politici proprii dacă sunt configurate.
- **Consimțământ** obligatoriu la programare (cu dovadă: dată + IP, inclusă în export și
  ștearsă la anonimizare) + notă la feedback.
- **Drepturile persoanei vizate**: unealtă admin de **export** și **anonimizare** date după
  email/telefon (programări, listă de așteptare, bilete + bilete legate).
- Retenție automată completă (bilete în loturi, programări, listă de așteptare, jurnale).
- **Fonturi găzduite local** (fără Google Fonts → niciun IP de vizitator la terți).
- Modele contractuale: `docs/contracte/` (DPA conform art. 28 GDPR + contract abonament/SLA).

### Securitate
- Schimbarea **obligatorie** a parolei implicite la prima logare (în producție).
- 2FA (TOTP) opțional/obligatoriu pentru admini, cu coduri de recuperare și anti-replay
  (inclusiv consumarea codului de la activare).
- Auto-delogare la inactivitate (PC-uri partajate la ghișee).
- **Content-Security-Policy** + `X-Robots-Tag: noindex` + `robots.txt` (unealtă internă).
- Anti-brute-force la login; CSRF pe toate mutațiile; acțiunile de operator cer POST.
- Upload media fără SVG (anti-XSS stocat) + `.htaccess` restrictiv pe `assets/uploads/`.
- Backup bază de date: manual + **automat zilnic** (retenție, descărcare securizată).
- Documentație livrabilitate email (SPF/DKIM/DMARC).

---

## Funcționalități de bază

- **Bilete de ordine**: dispenser (ecran tactil), emitere manuală, prioritate, plafoane
  zilnice/coadă, anti-înfometare (escaladare prioritate), auto-neprezentat după rechemări.
- **Terminal operator**: apelare, servire, finalizare, neprezentat, repunere, transfer
  (serviciu / ghișeu), note, schimbare operator prin PIN, autorizare pe ghișee.
- **Afișaj TV** (SSE live) + **afișaj de ghișeu** (tabletă) + **anunț vocal**.
- **Bilet digital** (QR pe bon → urmărire pe telefon, notificări în browser).
- **Programări online** (sloturi, capacitate, listă de așteptare, reminder, check-in → bon,
  reprogramare, anulare, „adaugă în calendar" .ics).
- **Feedback** clienți (CSAT) + alerte la note mici.
- **Multi-limbă** pe paginile publice (ro/en/de/fr/hu/it/es).
- **Statistici & rapoarte** (KPI, pe serviciu/operator/oră, export Excel/CSV cu neutralizarea
  injecției de formule), raport zilnic pe email.
- **Import/export CSV** (servicii, ghișee, filiale, utilizatori, zile închise) cu suport pentru
  ghilimele/virgule (RFC 4180) și BOM.
- **Multi-tenant**: un singur cod servește mai mulți clienți (subdomeniu → bază de date),
  cu panou landlord (health-check, suspendare, abonament, limite, facturare) care
  funcționează chiar și când o bază de date a unui client e picată.
- **API v1** (autentificat cu cheie) + **webhooks** semnate (HMAC) pentru integratori.
- **Cod QR local** (SVG, fără servicii externe) și **generator ESC/POS** pentru imprimante termice.
- **PWA** (manifest + service worker, funcționare offline parțială).
- **Instalare automată** la prima accesare (schemă + date demo + admin), migrări idempotente.

---

## Calitate

Acoperit de trei suite de teste (rulate în CI la fiecare push):
- **integrare** (~266 aserțiuni, logică reală pe MySQL),
- **HTTP end-to-end** (~166 aserțiuni, server PHP integrat),
- **blocare pe zile închise**.

Plus verificare în browser (Chromium) a fluxurilor reale, și trei runde de audit adversarial
de securitate (API, email, generatoare, realtime/JS, utilitare, handlere admin, GDPR/backup,
facturare/multi-tenant) — bug-urile reale identificate au fost remediate.
