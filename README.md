# Sistem de Bon de Ordine (Queue Management)

Sistem complet de gestionare a cozilor de așteptare — clonă funcțională a Moviik / Q‑net, în **PHP + MySQL**, gata de instalat pe **cPanel**. White‑label (personalizabil per client), cu anunț vocal în română, temă deschisă/închisă, dispenser multilingv și aplicație Android pentru imprimante USB.

> **Instalare fără pași manuali:** completezi datele bazei de date în `config/config.php`, urci fișierele și deschizi site‑ul. Schema, datele demo și un cont de admin se creează **automat** la prima accesare (nu există fișier `install.php`).

---

## Ce face

| Componentă | Rol | Cum se accesează |
|---|---|---|
| **Portal** | Pagina de intrare: alegi Backoffice sau Terminal operator. | `…/` |
| **Dispenser** | Clientul alege serviciul și primește bon (cu QR). Tipărește pe imprimantă termică. | `…/launcher?key=CHEIE` |
| **Afișaj TV** | Bon curent + ghișeu, ultimele apelate, câți sunt la rând; **anunț vocal RO**. Editor de widget‑uri pe canvas. | `…/launcher?key=CHEIE` |
| **Terminal operator** | Cheamă următorul, recheamă, finalizează, neprezentat, transfer; status operator; anunț vocal opțional. | `…/counter` |
| **Bilet digital** | Clientul scanează QR‑ul și urmărește pe telefon: status, poziție, **timp estimat**, ghișeu. | `…/t/{token}` |
| **Feedback** | Pagină publică de evaluare (1–5 stele) prin QR de pe afișaj. | `…/feedback` |
| **Administrare** | Dashboard live, statistici, filiale, servicii, grupuri, ghișee, dispozitive, multimedia, formulare, utilizatori, bilete, programări, feedback, setări, roluri. | `…/admin` |

### Funcționalități cheie
- **Servicii** cu prefix + culoare, interval de numere, reset zilnic automat, bilete prioritare, KPI, **program de funcționare** (orar pe zile, cu mesaj „închis"), **formular** la emitere, **programări online**, **traduceri** nume/descriere, **grupuri**.
- **Apelare inteligentă**: prioritate apoi vechime; un operator per ghișeu; recall, transfer, no‑show.
- **Real‑time**: SSE cu fallback pe polling; **dashboard live** (statcards, operatori, dispozitive se actualizează singure).
- **Anunț vocal RO** pe afișaj (Web Speech) + opțional la terminalul operatorului.
- **Status operator** (Disponibil/Ocupat/Pauză/Offline) cu prezență live pe dashboard și **istoric (timp pe status)** în Statistici.
- **Dispenser multilingv** (RO/EN/DE/FR/HU/IT/ES) cu bară de steaguri; revine la limba implicită după fiecare bon.
- **Connection key per dispozitiv**: fiecare tabletă/TV se leagă de configurația lui printr‑un cod de 6 caractere.
- **Printare ESC/POS** (Bixolon și orice imprimantă termică): rețea (port 9100), **Android USB** (aplicația din `android/`), sau browser (test). Conținutul bonului e configurabil (mărime număr, poziție, dată/oră, QR, antet/subsol).
- **Feedback client** (surveys‑lite): pagină publică + widget QR pe afișaj + sumar și listă în admin.
- **Statistici** complete: KPI, timp în format `HH:MM:SS`, pe serviciu / ghișeu / utilizator / oră / zi, toggle grafic↔tabel, **export Excel `.xlsx` cu grafice native** + CSV per set de date.
- **Temă deschisă/închisă** în backoffice (comutator în bară), bară laterală pliabilă.
- **White‑label**: nume, logo, culoare, texte — din Setări (pe taburi).

---

## Cum e construit (arhitectură)

```
index.php             ← front controller (rutează tot)
config/config.php     ← date DB + setări de bază
database/             ← schema.sql (structura) + seed.sql (date demo)
app/core/             ← init, db (PDO), helpers (migrări + i18n dispenser),
                        auth, ticket (logica cozii), appointments, printer (ESC/POS), xlsx
app/admin_routes.php  ← rutare + CRUD administrare
app/views/            ← paginile (public/ + admin/)
assets/               ← css + js (dispenser, counter, display, player builder, app)
android/launcher/     ← aplicația Android (kiosk WebView + printare USB ESC/POS)
.github/workflows/    ← build automat al APK-ului
```

- **Fără framework greu** — PHP simplu cu PDO, ușor de găzduit și întreținut.
- **Migrare automată a schemei**: la fiecare acces, baza se aduce la zi (adaugă tabele/coloane noi) fără SQL manual și fără pierderi de date.
- **Securitate**: sesiuni cu cookie restrâns la host (izolare între subdomenii), protecție CSRF, parole bcrypt, foldere sensibile blocate prin `.htaccess`.
- Vezi **`INSTALL.md`** pentru instalarea pas cu pas.

---

## Cont implicit (după prima accesare)
- **Backoffice:** `…/login` → email `admin@example.ro`, parolă `123456`.
- **Important:** schimbă imediat emailul/parola din **Administrare → Utilizatori**.

---

## Hardware — printare (Bixolon și compatibile ESC/POS)
Ordinea recomandată pentru un client real:

1. **Mini‑PC/tabletă Android + imprimantă termică USB** — cel mai simplu și robust la intrare.
   Rulează aplicația din `android/` (kiosk), deschide dispenserul și tipărește bonul pe USB. Vezi `android/README.md`. Testat conceptual cu **Bixolon BK3‑31ZC/BEG**.
2. **Imprimantă cu Ethernet/WiFi (rețea, port 9100)** — când serverul/PC‑ul e în aceeași rețea. Serverul trimite ESC/POS direct.
   ⚠ Pe cPanel (server extern) modul rețea cere imprimanta accesibilă din internet (IP public/VPN) — de obicei nepractic; preferă varianta Android.
3. **Browser** — doar pentru test/demo, fără hardware.

Octeții bonului se construiesc o singură dată pe server (`app/core/printer.php`), deci bonul arată identic pe toate canalele.

---

## Aplicația Android (mini‑PC la intrare)
Cod sursă complet în **`android/launcher/`**. Pe scurt:
1. Obține APK‑ul: tab **Actions → „Build Android APK" → Run workflow** (descarci artifact‑ul) sau din Android Studio.
2. Instalează pe mini‑PC, introdu **linkul dispenserului** (`…/launcher?key=CHEIE`).
3. În **Dispozitive**, setează dispenserului **Mod printare = Android**.
4. Conectează imprimanta Bixolon pe USB → la apăsarea pe ecran, bonul se tipărește.

Detalii și depanare: `android/README.md`.

---

## Drumul către multi‑tenant (opțional)
Acum ai o instanță. Pentru „fiecare client la `client1.domeniu.ro`, izolați":
1. **DNS wildcard** `*.domeniu.ro` → IP server.
2. **Subdomenii wildcard în cPanel** (document root comun) sau folder per client.
3. **O bază de date per client** (rulezi schema automat la onboarding).
4. **Rezolvare tenant după host** în `init.php` (mapare subdomeniu → bază).

Conexiunea DB e centralizată, setările sunt per‑instanță în tabelul `settings`, iar cookie‑ul de sesiune e legat de host.

---

## Actualizare (instalare existentă)
1. Dezarhivează noua versiune **peste** fișierele vechi (suprascrie tot).
2. Deschide aplicația o dată în browser — schema bazei se **actualizează singură**.
3. Asigură‑te că `assets/uploads/` e scriibil (pentru Multimedia) — vezi `INSTALL.md`.
