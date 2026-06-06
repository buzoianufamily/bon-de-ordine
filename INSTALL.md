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

> Structura corectă după extragere: `index.php`, `install.php`, folderele `app/`, `config/`, `database/`, `assets/` să fie toate la același nivel.

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
3. Schimbă `setup_token` și `app_key` cu valori aleatorii (orice șiruri lungi). **Save**.

---

## Pasul 4 — Rulează instalatorul
1. Deschide în browser: `https://coada.firma-ta.ro/install.php`
2. Vezi „✔ Conectat la …" → introdu `setup_token` → **Continua**.
3. Apasă **Importa schema + date demo**.
4. Completează **numele, emailul și parola** pentru contul de administrator → **Creeaza administrator**.
5. **IMPORTANT:** șterge `install.php` de pe server (File Manager → Delete) și schimbă `setup_token`.

---

## Pasul 5 — Verifică
- **Admin:** `https://coada.firma-ta.ro/login` → intră cu emailul/parola create.
- **Dispenser de test:** Admin → **Dispozitive** → la „Dispenser intrare" apasă **Deschide** (cheie `DEMO01`).
  Apasă pe un serviciu → se emite un bon și (pe mod „browser") se deschide dialogul de printare.
- **Afișaj TV de test:** Admin → Dispozitive → „TV sala asteptare" → **Deschide** (cheie `DEMO02`).
  Lasă pagina deschisă; când chemi un bon din terminal, îl vei **auzi anunțat în română**.
- **Terminal operator:** `…/counter` → alege „Birou 1" → **CHEAMA URMATORUL**.
- **Bilet pe telefon:** scanează codul QR de pe bon (sau de pe dispenserul digital `DEMO03`).

---

## Pasul 6 — Personalizează pentru client
Admin → **Setari**: nume brand, culoare accent, logo, text bon, voce/anunț.
Admin → **Servicii / Ghisee / Dispozitive / Utilizatori**: configurează ce are clientul.

---

## Imprimanta termică (Bixolon) — când o ai
Sistemul suportă 3 moduri (Admin → Dispozitive → editezi dispenserul → **Mod printare**):
- **Browser** — pentru test, fără hardware (folosește dialogul de print al browserului).
- **Retea** — Bixolon cu Ethernet/WiFi. Pune **IP-ul imprimantei** și portul **9100**. Serverul trimite bonul direct (ESC/POS RAW).
- **Android** — imprimantă USB/Bluetooth legată la tabletă, prin aplicația launcher (vezi `android/README.md`).

> Pe rețea: imprimanta și serverul/tableta trebuie să „se vadă". Pentru cPanel (server extern), modul **rețea** funcționează doar dacă imprimanta are IP public sau ești pe aceeași rețea (VPN). În practică, pentru un magazin, varianta **Android** (tabletă + imprimantă USB/Bluetooth) e cea mai simplă. Detalii și recomandări în `README.md`.

---

## Probleme frecvente
- **„Eroare conexiune baza de date"** → datele din `config.php` nu sunt corecte sau userul nu e adăugat la bază cu ALL PRIVILEGES.
- **Pagini 404 peste tot / linkurile nu merg** → modulul `mod_rewrite` sau `.htaccess` nu e activ. Pe majoritatea hosturilor e activ implicit; dacă nu, întreabă hostingul să activeze `AllowOverride All`.
- **Nu se aude anunțul vocal** → browserele cer o interacțiune înainte de a reda sunet. Dă un click pe pagina afișajului o dată după ce o deschizi (apoi merge automat). Folosește **Chrome** pe TV/tabletă pentru vocea românească.
- **Vocea nu e în română** → instalează un pachet de voce ro-RO în sistemul de operare al dispozitivului care afișează (Android/Windows), sau lasă engleza.
