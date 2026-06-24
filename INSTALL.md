# Ghid de instalare pe cPanel

Acest ghid te duce pas cu pas de la zero la o instanță funcțională. Durează ~15 minute.

---

## Ce îți trebuie
- Un cont cPanel cu PHP 8.0+ și MySQL/MariaDB (orice hosting normal are).
- Un domeniu sau subdomeniu (ex: `coada.firma-ta.ro`).

---

## Pasul 1 — Creează baza de date (cPanel → MySQL® Databases)
1. La **Create New Database**, scrie un nume (ex: `queue`) → **Create Database**.
   cPanel îi pune un prefix: rezultă ceva de forma `contulteu_queue`.
2. La **MySQL Users → Add New User**: nume (ex: `queue`) + o parolă tare → **Create User**.
   Rezultă `contulteu_queue`.
3. La **Add User To Database**: alege userul și baza → **Add** → bifează **ALL PRIVILEGES** → **Make Changes**.
4. **Notează**: numele bazei, numele userului și parola. Îți trebuie la Pasul 3.

---

## Pasul 2 — Urcă fișierele
**Varianta A (recomandată) — subdomeniu dedicat:**
1. cPanel → **Subdomains** → creează `coada` → cPanel îi face un folder, de obicei `/home/contulteu/coada`.
2. cPanel → **File Manager** → intră în folderul subdomeniului.
3. Urcă arhiva `queue-system.zip` aici și **Extract**. Mută conținutul astfel încât `index.php` să fie direct în rădăcina subdomeniului (nu într-un subfolder `queue-system/`).

**Varianta B — în public_html:** la fel, dar extragi în `public_html`. Aplicația va fi la `domeniu.ro/` (sau într-un subfolder dacă o pui acolo — merge și așa, rutarea se adaptează automat).

> Structura corectă după extragere: `index.php`, folderele `app/`, `config/`, `database/`, `assets/` să fie toate la același nivel.

---

## Pasul 3 — Configurează
1. În File Manager, deschide `config/config.php` (Edit).
2. Completează datele de la Pasul 1:
   ```php
   'db' => [
       'host' => 'localhost',
       'name' => 'contulteu_queue',
       'user' => 'contulteu_queue',
       'pass' => 'PAROLA_TA',
   ],
   ```
3. **Save**. (Nu mai există `setup_token`/`app_key` — nu trebuie configurate.)
4. (Recomandat) În File Manager, asigură‑te că folderul **`assets/uploads/`** există și e **scriibil** (permisiuni 755). E folosit de **Multimedia** pentru logo/imagini. Dacă lipsește, aplicația încearcă să‑l creeze automat la primul upload.

---

## Pasul 4 — Deschide aplicația (instalare automată)
La prima accesare, aplicația **își creează singură** baza de date (schema + date demo)
și un cont de administrator implicit — nu mai e nevoie de `install.php`.

1. Deschide în browser: `https://coada.firma-ta.ro/`
2. Apare portalul cu **Backoffice** și **Terminal operator**.
3. Intră la **Backoffice** (sau `…/login`) cu contul de administrator implicit.
   Datele inițiale ți le comunică furnizorul / le găsești în `database/seed.sql`.
4. **Schimbarea parolei este obligatorie:** la prima accesare a unei zone de
   administrare, aplicația te redirecționează automat la **Contul meu** și nu te
   lasă să continui până nu setezi o parolă nouă. (Comportamentul este activ în
   producție; în mediul `dev` este dezactivat ca să poată rula testele.)

---

## Pasul 5 — Verifică
- **Admin:** `https://coada.firma-ta.ro/login` → intră cu contul de administrator implicit (vezi `database/seed.sql`); la prima logare ești obligat să schimbi parola.
- **Dispenser de test:** Admin → **Dispozitive** → la „Dispenser intrare" apasă **Deschide** (cheie `DEMO01`).
  Apasă pe un serviciu → se emite un bon și (pe mod „browser") se deschide dialogul de printare.
- **Afișaj TV de test:** Admin → Dispozitive → „TV sala asteptare" → **Deschide** (cheie `DEMO02`).
  Lasă pagina deschisă; când chemi un bon din terminal, îl vei **auzi anunțat în română**.
- **Terminal operator:** `…/counter` → alege „Birou 1" → **CHEAMA URMATORUL**.
- **Bilet pe telefon:** scanează codul QR de pe bon (sau de pe dispenserul digital `DEMO03`).

---

## Pasul 6 — Personalizează pentru client
- Admin → **Setări** (pe taburi): **General** (nume brand, culoare accent, logo, **limbi dispenser**), **Bilet** (antet/subsol + conținut bon tipărit), **Afișaj & voce** (voce TTS, anunț la terminal), **Digital & alerte** (bilet digital + mesaje de alertă client).
- Admin → **Servicii** (prefix/culoare/interval, program de funcționare, formular, programări, **traduceri**, **grup**), **Grupuri** (categorii pe dispenser), **Ghișee**, **Dispozitive**, **Utilizatori** (cu notificări browser per operator).
- **Provizionare rapidă din CSV (opțional):** fiecare pagină — Filiale, Servicii, Ghișee, Utilizatori, Zile închise — are un panou „⤓ Import / export (CSV)". Descarci un **șablon gol**, îl completezi în Excel și îl încarci (sau lipești liniile direct). Util când configurezi de la zero un client cu multe servicii/ghișee/operatori; reimportul sare peste rândurile deja existente.
- Admin → **Filiale → 📅 Zile închise:** marchează sărbătorile/zilele de inventar (per filială sau „toate filialele"). În acele zile dispenserul afișează „Închis astăzi" și nu se emit bonuri — programul săptămânal normal rămâne pe fiecare serviciu.
- Admin → **Dispozitive**: pentru afișajele TV folosește **Configurează** (editor de widget‑uri: grilă bilete, listă, ceas, QR, vreme, playlist, iframe, ticker, formular feedback etc.); pentru dispensere, **Configurează** (Logic/Aspect/Texte/Popup).
- **Temă deschisă/închisă:** comutatorul 🌙/☀️ din bara de sus a backoffice‑ului.
- **Feedback client:** adaugă widget‑ul „Formular feedback" pe afișaj (QR către `…/feedback`); răspunsurile apar în **Feedback** și în **Statistici**.

---

## Imprimanta termică (Bixolon) — când o ai
Sistemul suportă 3 moduri (Admin → Dispozitive → editezi dispenserul → **Mod printare**):
- **Browser** — pentru test, fără hardware (folosește dialogul de print al browserului).
- **Retea** — Bixolon cu Ethernet/WiFi. Pune **IP-ul imprimantei** și portul **9100**. Serverul trimite bonul direct (ESC/POS RAW).
- **Android** — imprimantă USB legată la un mini‑PC/tabletă Android, prin aplicația din `android/` (vezi mai jos).

> Pe rețea: imprimanta și serverul/tableta trebuie să „se vadă". Pentru cPanel (server extern), modul **rețea** funcționează doar dacă imprimanta are IP public sau ești pe aceeași rețea (VPN). În practică, varianta **Android** (mini‑PC + imprimantă USB) e cea mai simplă. Detalii și recomandări în `README.md`.

---

## Aplicația Android (mini‑PC la intrare + imprimantă Bixolon USB)
Pentru a tipări automat bonul când clientul apasă pe ecran, pe un mini‑PC cu Android:

1. **Obține APK‑ul:** pe GitHub, tab **Actions → „Build Android APK" → Run workflow**, apoi descarcă artifact‑ul `bon-de-ordine-launcher-apk` (sau compilează din Android Studio folderul `android/launcher`).
2. **Instalează** APK‑ul pe mini‑PC (permite „Surse necunoscute").
3. **Configurează modul de printare:** Admin → **Dispozitive** → editează dispenserul → **Mod printare = Android** → Salvează.
4. **Pornește aplicația** și lipește **linkul dispenserului** (Admin → Dispozitive → Deschide → `…/launcher?key=CHEIE`).
5. **Conectează imprimanta Bixolon** (ex: BK3‑31ZC/BEG) pe USB și acordă permisiunea USB când e cerută.
6. Testează din **butonul Înapoi → Test imprimantă**. De acum, la fiecare bon emis pe ecran, se tipărește automat.

Ghid complet (build, kiosk, depanare): **`android/README.md`**.

---

## Multi‑tenant — mai mulți clienți pe aceeași instalare
Cu o singură instalare poți deservi oricâți clienți, fiecare pe subdomeniul lui și cu baza lui de date (izolare completă).

### Activare (o singură dată)
1. În `config/config.php` setează o parolă lungă la `'landlord_pass' => '…'`.
2. Deschide `https://domeniul-tau.ro/landlord` și autentifică‑te cu acea parolă.

### Adaugi un client nou (~3 minute)
1. **Subdomeniu:** cPanel → **Domains** → creează `client1.domeniul-tau.ro` cu **același document root** ca aplicația (sau creează o singură dată un subdomeniu wildcard `*`).
2. **Bază de date:** cPanel → **MySQL Databases** → creează o bază + un utilizator noi, cu **ALL PRIVILEGES**.
3. **Înregistrare:** în panoul `/landlord`, completează formularul (host + datele bazei) → **Salvează** (îți confirmă pe loc dacă conexiunea DB merge).
4. **Prima accesare** a subdomeniului instalează automat schema, datele demo și adminul implicit — la prima logare clientul este obligat să schimbe parola implicită.
5. Dacă clientul folosește emailuri (remindere/raport): adaugă în cPanel câte un **Cron Job** per instanță, cu URL‑ul de cron din **API & Webhooks** al acelei instanțe.

### Operare zilnică
- Tabelul din `/landlord` arată pentru fiecare instanță: **Funcționează / EROARE** (cu mesajul erorii), versiunea schemei (marcată „veche" dacă instanța n‑a fost accesată după un update — se actualizează singură la prima accesare), bilete azi, ultimul bon, dispozitive online, utilizatori.
- **Suspendă** un client (neplată etc.) dintr‑un click — subdomeniul lui afișează „instanță suspendată"; **Activează** îl repune instant. **Șterge** doar scoate instanța din registru (baza de date rămâne neatinsă).
- Un singur upload de fișiere actualizează **toți** clienții (migrările de schemă rulează automat per instanță).

---

## Vânzare pe abonament (pentru furnizor)
- **Verificare „pregătit de producție":** Admin → **Verificare** (`/admin/checkup`) — listă de control automată (parolă implicită, HTTPS, email, backup, cron, 2FA, retenție, date legale). Rezolvă avertismentele înainte de a preda instanța clientului.
- **Limite de plan:** în `/landlord`, la fiecare client poți seta limite (filiale/ghișee/utilizatori/servicii) — aplicația le impune automat. `0 = nelimitat`.
- **Documente contractuale (modele):** vezi `docs/contracte/` — `DPA-model.md` (acord de prelucrare a datelor, GDPR art. 28) și `contract-abonament-SLA-model.md`. **Sunt modele orientative — validează‑le cu un jurist.**

## Monitorizare & parole
- **Monitorizare uptime:** configurează serviciul de monitorizare (UptimeRobot, BetterStack etc.) pe `https://coada.firma-ta.ro/health`. Răspunde cu JSON `{"ok":true,"db":"up",…}` și cod **200** când totul e funcțional, sau **503** dacă baza de date e picată.
- **Alerte SLA pe email:** Admin → Setări → tab **Automatizări** → bifează „alertă SLA". Când există bilete care așteaptă peste ținta serviciului (Servicii → „timp așteptare"), managerii primesc un email (cu prag minim și pauză configurabilă între alerte). Necesită cron + email configurat. Tot aici se activează reminderele de programări, raportul zilnic, curățarea automată a biletelor vechi și închiderea biletelor uitate.
- **Status public al cozii:** Admin → Setări → **Module** → activează „Status public coadă". Apoi pune linkul `…/status?branch=ID` pe site‑ul clientului — vizitatorii văd live ce se servește la ghișee și câți sunt la rând, fără cont.
- **Backup automat:** Admin → Setări → **Backup bază de date** → activează „backup automat zilnic". Prin cron, sistemul scrie zilnic un `.sql` în folderul `backups/` (protejat de acces web prin `.htaccess`) și păstrează ultimele N copii. Descarcă‑le periodic în afara serverului. Manual: butonul „Rulează un backup acum" sau „Descarcă backup SQL".
- **Schimbarea parolei:** orice utilizator backoffice își poate schimba parola din **Securitate → Schimbă parola**; operatorii (care nu intră în backoffice) o schimbă din **Terminal → Cont** (`…/account`).
- **„Am uitat parola":** linkul de pe pagina de autentificare trimite un email cu un link de resetare (valabil 60 de minute). **Necesită modulul Email configurat** (Admin → Setări → Email — SMTP propriu sau `mail()` de pe cPanel). Fără email configurat, linkul nu poate fi trimis; un alt administrator poate reseta parola din **Utilizatori**.

### Livrabilitate email (SPF / DKIM / DMARC)
Pentru ca reminderele, rapoartele și emailurile de resetare să **nu ajungă la spam**,
configurează în zona DNS a domeniului (cPanel → **Zone Editor** / **Email Deliverability**):
- **SPF** (înregistrare TXT pe domeniu): autorizează serverul care trimite. Exemplu pentru trimitere de pe serverul de hosting: `v=spf1 +mx +a include:_spf.<hostingul-tau> ~all`. Dacă folosești un SMTP extern (relay), include domeniul lui în loc.
- **DKIM**: în cPanel → **Email Deliverability** apasă **Manage / Repair** ca să genereze cheia DKIM și înregistrarea TXT corespunzătoare — semnează criptografic mesajele.
- **DMARC** (TXT pe `_dmarc.domeniu`): definește politica. Început prudent: `v=DMARC1; p=none; rua=mailto:postmaster@domeniul-tau.ro` (doar monitorizare); strânge la `p=quarantine` apoi `p=reject` după ce confirmi că SPF+DKIM trec.
- Setează **„Expeditor" (`mail_from`)** pe o adresă **de pe domeniul tău** (ex: `coada@domeniul-tau.ro`), nu pe un gmail/yahoo — altfel DMARC respinge. Testează cu butonul „Trimite email de test" din Setări → Email.

---

## Probleme frecvente
- **„Eroare conexiune baza de date"** → datele din `config.php` nu sunt corecte sau userul nu e adăugat la bază cu ALL PRIVILEGES.
- **Pagini 404 peste tot / linkurile nu merg** → modulul `mod_rewrite` sau `.htaccess` nu e activ. Pe majoritatea hosturilor e activ implicit; dacă nu, întreabă hostingul să activeze `AllowOverride All`.
- **Nu se aude anunțul vocal** → browserele cer o interacțiune înainte de a reda sunet. Dă un click pe pagina afișajului o dată după ce o deschizi (apoi merge automat). Folosește **Chrome** pe TV/tabletă pentru vocea românească.
- **Vocea nu e în română** → instalează un pachet de voce ro-RO în sistemul de operare al dispozitivului care afișează (Android/Windows), sau lasă engleza.
