# Sistem de Bon de Ordine (Queue Management)

Sistem complet de gestionare a cozilor de așteptare — clonă funcțională a Moviik / Q‑net, în **PHP + MySQL**, gata de instalat pe **cPanel**. White‑label (personalizabil per client), **multi‑tenant** (un panou „landlord" pentru toți clienții tăi), cu anunț vocal în română, dispenser multilingv, 2FA, API + webhooks și aplicație Android pentru imprimante USB.

> **Instalare fără pași manuali:** completezi datele bazei de date în `config/config.php`, urci fișierele și deschizi site‑ul. Schema, datele demo și un cont de admin se creează **automat** la prima accesare (nu există fișier `install.php`).

---

## Ce face

| Componentă | Rol | Cum se accesează |
|---|---|---|
| **Portal** | Pagina de intrare: alegi Backoffice / Terminal / Concierge. | `…/` |
| **Dispenser** | Clientul alege serviciul (grupate pe categorii, multilingv) și primește bon (cu QR). Tipărește pe imprimantă termică. | `…/launcher?key=CHEIE` |
| **Afișaj TV** | Editor de widget‑uri pe canvas: grilă bilete, liste, ceas, QR, vreme, playlist, iframe, ticker, formular feedback; **anunț vocal RO**; șabloane gata făcute. | `…/launcher?key=CHEIE` |
| **Terminal operator** | Selectezi un bilet → meniu de acțiuni (cheamă/recheamă/servire/finalizat/neprezentat/transfer la serviciu sau **alt birou**); „cheamă următorul" (global sau pe serviciu); scurtături tastatură; status operator; **pauză ghișeu cu mesaj**. | `…/counter` |
| **Concierge** | Recepția vede toată coada și cheamă orice bilet la orice ghișeu. | `…/concierge` |
| **Afișaj de ghișeu** | Tabletă la birou: codul ghișeului + bonul curent, live (sau mesajul de pauză). | `…/cd/{id}` |
| **Bilet digital** | Clientul urmărește pe telefon: status, poziție, **timp estimat**, ghișeu, alerte configurabile, sondaj la final. Instalabil ca PWA. | `…/t/{token}` |
| **Programări online** | Rezervare pe sloturi + confirmare/reminder pe email + check‑in cu bon automat. | `…/book` |
| **Feedback** | Pagină publică de evaluare (1–5 stele) prin QR de pe afișaj sau de pe biletul digital. | `…/feedback` |
| **Status public** | Pagină live (opțională) cu „la ghișee acum" + cozile pe serviciu, fără cheie de dispozitiv — de pus pe site‑ul clientului. | `…/status?branch=ID` |
| **Administrare** | Dashboard live (sparkline, operatori, filiale), statistici (heatmap, KPI, comparație perioade, Excel cu grafice), bilete cu filtre, programări cu calendar, grupuri, feedback, module, API & webhooks, jurnal audit, securitate 2FA, backup. Căutare globală **Ctrl+K**. | `…/admin` |
| **Landlord** | Panoul TĂU multi‑tenant: instanțele tuturor clienților, cu **health‑check** (funcționează / eroare / suspendată), adăugare/suspendare clienți. | `…/landlord` |

### Funcționalități cheie
- **Servicii** cu prefix + culoare, interval de numere, reset zilnic automat, bilete prioritare, KPI, **program de funcționare** (orar pe zile, cu mesaj „închis" configurabil), **zile închise / sărbători** (per filială sau globale), **pauză temporară** per serviciu (oprește emiterea fără a schimba programul), **formular** la emitere, **programări online**, **traduceri** nume/descriere, **grupuri**, ordonare prin **drag & drop**.
- **Apelare inteligentă**: prioritate apoi vechime; un operator per ghișeu; recall, transfer la serviciu sau la **alt birou**, no‑show; operatori **atribuiți pe ghișee**.
- **Real‑time**: SSE cu fallback pe polling; **dashboard live** cu sparkline 7 zile, abandon %, vârf de zi, comparație filiale, prezență operatori și dispozitive online, plus **monitorizare SLA** (servicii/bilete care depășesc ținta de așteptare, acum). Operatorul vede în terminal biletele „⏱ peste timp".
- **Anunț vocal RO** pe afișaj (Web Speech) + opțional la terminalul operatorului; texte multilingve alternante pe TV (`Text RO | Text EN`).
- **Status operator** (Disponibil/Ocupat/Pauză/Offline) cu prezență live și **istoric (timp pe status)** în Statistici și în Excel.
- **Dispenser multilingv** (RO/EN/DE/FR/HU/IT/ES) cu bară de steaguri; revine la limba implicită după fiecare bon; efecte de atingere; opțional **insigne „👥 câți așteaptă"** live pe fiecare buton.
- **Anunț general live** (📢): mesaj ad‑hoc cu expirare, afișat și actualizat **în timp real** (fără reîncărcare) pe dispenser, afișaje TV, afișaje de ghișeu, pagina de status și terminalul operatorului.
- **Statistici operator live** chiar în terminal (bilete servite azi + timp mediu pe bon).
- **Module activabile**: bilet digital QR, programări, feedback, concierge — pornite/oprite din Setări.
- **Printare ESC/POS** (Bixolon și orice imprimantă termică): rețea (port 9100), **Android USB** (aplicația din `android/`), sau browser (test). Conținutul bonului e configurabil, cu **preview live** în Setări. **Foaie printabilă cu coduri QR** pentru instalarea rapidă a dispozitivelor (Admin → Dispozitive → Coduri QR).
- **Email integrat** (SMTP propriu sau `mail()` de pe cPanel): confirmări + remindere programări, raport zilnic, **alerte SLA** (când cozile depășesc ținta, cu prag + pauză anti‑spam); **cron** inclus (curățare automată a biletelor vechi + închiderea automată a biletelor uitate „în servire"/„chemat").
- **Statistici** complete: KPI cu țintă per serviciu, **heatmap zi×oră**, comparație cu perioada precedentă, pe serviciu/ghișeu/utilizator/oră/zi, satisfacție clienți, toggle grafic↔tabel, **export Excel `.xlsx` cu grafice native** + CSV per set; pagina **Bilete** are **export CSV** al listei filtrate.
- **Securitate**: **2FA (TOTP)** cu coduri de recuperare și politică „obligatoriu pentru admini", throttle la login, **schimbarea propriei parole** și **„am uitat parola"** (link pe email, token unic, expiră în 60 min), **jurnal de audit** (cu filtrare + export CSV), **backup SQL** dintr‑un click, API cu cheie + rate‑limit, webhooks semnate HMAC.
- **API REST v1 + webhooks** pentru integrări (emitere bon, stare coadă, statistici) — documentate în Admin → API & Webhooks. Evenimente webhook pentru tot ciclul biletului + **`sla.breach`** (cozi peste țintă). Endpoint **`/health`** (JSON) pentru monitorizare uptime.
- **Multi‑tenant**: subdomeniu + bază de date per client, panou **landlord** cu health‑check și suspendare instanțe.
- **Temă deschisă/închisă** (cu auto după sistemul de operare), admin **responsive pe mobil**, căutare globală **Ctrl+K**, checklist de onboarding.
- **Anunț general**: banner ad‑hoc (📢) cu expirare opțională, afișat pe dispenser, pagina de status, afișajele de ghișeu și terminalul operatorului — pentru mesaje rapide („azi program redus").
- **White‑label**: nume, logo, culoare, texte — din Setări (pe taburi).

---

## Cum e construit (arhitectură)

```
index.php             ← front controller (rutează tot: public, API, cron, PWA, landlord)
config/config.php     ← date DB + parola landlord
config/tenants.json   ← registrul instanțelor (multi-tenant; creat de panoul landlord)
database/             ← schema.sql (structura) + seed.sql (date demo)
app/core/             ← init (rezolvare tenant + migrări), db (PDO), helpers,
                        auth, totp (2FA), ticket (logica cozii), appointments,
                        printer (ESC/POS), mailer (SMTP), xlsx (Excel cu grafice)
app/admin_routes.php  ← rutare + CRUD administrare
app/api_v1.php        ← API public REST v1 (cheie + rate-limit)
app/cron.php          ← sarcini programate (remindere, raport zilnic, curățare)
app/landlord.php      ← panoul multi-tenant (health-check clienți)
app/views/            ← paginile (public/ + admin/ + landlord/)
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

## Multi‑tenant: mai mulți clienți pe aceeași instalare
Implementat complet: fiecare client primește un **subdomeniu** (`client1.domeniu.ro`) și o **bază de date proprie** (izolare totală), pe **același cod** — un singur upload actualizează toți clienții.

**Cum funcționează:** la fiecare cerere, aplicația se uită la host și alege baza de date din `config/tenants.json`. Fără fișier, totul merge ca o instanță unică (nimic nu se schimbă).

**Panoul landlord** (`…/landlord`, parola din `config/config.php → landlord_pass`):
- vede TOATE instanțele cu **health‑check live**: conexiune DB, versiunea schemei (marcată „veche" dacă nu e la zi), bilete azi, ultimul bon, dispozitive online, utilizatori — știi imediat dacă „li s‑a stricat ceva" unui client;
- adaugi/editezi instanțe (host + datele bazei), cu test de conexiune la salvare;
- **suspenzi/reactivezi** un client dintr‑un click (clientul vede o pagină „instanță suspendată"; nimic nu se șterge);
- e independent de bazele de date — funcționează chiar dacă una dintre instanțe e picată.

**Onboarding client nou (3 minute):** creezi subdomeniul + baza de date în cPanel → înregistrezi instanța în landlord → prima accesare a subdomeniului instalează automat schema + adminul implicit. Pași detaliați în `INSTALL.md`.

---

## Actualizare (instalare existentă)
1. Dezarhivează noua versiune **peste** fișierele vechi (suprascrie tot).
2. Deschide aplicația o dată în browser — schema bazei se **actualizează singură**.
3. Asigură‑te că `assets/uploads/` e scriibil (pentru Multimedia) — vezi `INSTALL.md`.

> CSS/JS sunt **versionate automat** (după data modificării fișierului), deci după un update clienții primesc imediat versiunea nouă — fără să golească memoria cache.
