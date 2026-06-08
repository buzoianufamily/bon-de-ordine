# Sistem de Bon de Ordine (Queue Management)

Sistem complet de gestionare a cozilor de așteptare — clonă funcțională a Moviik / Q-net, în **PHP + MySQL**, gata de instalat pe **cPanel**. White-label (personalizabil per client), cu anunț vocal în română.

> Aceasta este **prima fază**: o instanță unică, funcțională end-to-end, pe care o testezi pe cPanel. Arhitectura e pregătită pentru multi-tenant (vezi mai jos).

---

## Ce face

| Componentă | Rol | Cum se accesează |
|---|---|---|
| **Dispenser** | Clientul alege serviciul și primește bon (cu QR). Tipărește pe imprimantă termică. | `…/launcher?key=CHEIE` (tabletă/touchscreen la intrare) |
| **Afișaj TV** | Arată bonul curent + ghișeul, ultimele apelate, câți sunt la rând. **Anunță vocal în română.** | `…/launcher?key=CHEIE` (Smart TV / monitor) |
| **Terminal operator** | Operatorul cheamă următorul, recheamă, finalizează, marchează neprezentat, transferă. | `…/counter` |
| **Bilet digital** | Clientul scanează QR-ul și urmărește pe telefon statusul live. | `…/t/{token}` |
| **Administrare** | Servicii, ghișee, dispozitive, utilizatori, bilete, setări, dashboard. | `…/admin` |

### Funcționalități cheie
- **Servicii cu prefix + culoare** (ex: `A`-Casierie, `C`-Depunere acte), interval de numere, reset zilnic automat, bilete prioritare, KPI.
- **Apelare inteligentă**: prioritate apoi vechime; un operator per ghișeu; recall, transfer, no-show.
- **Real-time**: SSE (Server-Sent Events) cu fallback automat pe polling — merge pe orice hosting.
- **Anunț vocal RO**: Web Speech API spune „Bonul A 0 4 5, prezentați-vă la Birou 1", cu sunet (ding) și repetare configurabilă.
- **Connection key per dispozitiv** (ca la Moviik): fiecare tabletă/TV se leagă de configurația lui printr-un cod de 6 caractere.
- **Printare ESC/POS** (Bixolon și orice imprimantă termică): rețea (port 9100), Android (USB/Bluetooth via launcher), sau browser (test).
- **White-label**: nume, logo, culoare, texte — toate per instanță, din Setări.

---

## Cum e construit (arhitectură)

```
index.php            ← front controller (rutează tot)
config/config.php    ← date DB + setări
database/            ← schema.sql (structura) + seed.sql (date demo)
app/core/            ← init, db (PDO), helpers, auth, ticket (logica cozii), printer (ESC/POS)
app/admin_routes.php ← CRUD administrare
app/views/           ← paginile (public/ + admin/)
assets/              ← css + js (dispenser, counter, display)
android/             ← specificația aplicației de tabletă (kiosk + printare)
```

- **Fără framework greu** — PHP simplu cu PDO, ușor de găzduit și de întreținut.
- **Securitate**: sesiuni cu cookie restrâns la host (izolare între subdomenii), protecție CSRF, parole bcrypt, foldere sensibile blocate prin `.htaccess`.
- Vezi **`INSTALL.md`** pentru instalarea pas cu pas.

---

## Hardware — recomandare practică (imprimante Bixolon)

Sunt trei moduri de printare; pentru un client real, ordinea recomandată:

1. **Tabletă Android + imprimantă termică (USB sau Bluetooth)** — cel mai simplu și robust pentru un ghișeu/intrare.
   Tableta rulează aplicația launcher (mod kiosk), deschide dispenserul și trimite bonul la imprimantă. Vezi `android/README.md`.
2. **Imprimantă Bixolon cu Ethernet/WiFi (rețea, port 9100)** — bună când serverul/PC-ul e în aceeași rețea cu imprimanta. Serverul trimite ESC/POS direct.
   ⚠ Dacă aplicația e pe cPanel (server extern), modul rețea cere ca imprimanta să fie accesibilă din internet (IP public/VPN) — de obicei nepractic. De aceea pt cloud se preferă varianta Android.
3. **Browser** — doar pentru test/demo, fără hardware.

Modele Bixolon uzuale: seria **SRP-330/350** (USB/serial/Ethernet), **SRP-Q300/Q200** (compacte). Toate vorbesc ESC/POS, deci sunt compatibile.

---

## Conformitate România (de reținut)
- Bonul de ordine **nu** e bon fiscal — nu intră sub incidența casei de marcat. E doar tichet de prioritate.
- Pentru **abonamentele lunare** facturate clienților (modelul tău cu ordin de plată): emiți factură normală cu **TVA 21%** (cota standard din 2025, Legea 141/2025). Dacă ești plătitor de TVA și clientul e firmă, se aplică **e-Factura** (RO e-Factura) — dar asta ține de facturarea ta, nu de aplicația de cozi.

---

## Drumul către multi-tenant (faza 2)

Acum ai o instanță. Pentru „fiecare client la `client1/client2.domeniu.ro`, izolați complet":

1. **DNS wildcard**: `*.domeniu.ro` → IP-ul serverului (un singur A record).
2. **Subdomenii wildcard în cPanel**: creează un subdomeniu wildcard `*` cu document root comun, SAU provisionezi câte un folder per client.
3. **O bază de date per client** (izolare maximă, recomandat): la onboarding rulezi `schema.sql`+`seed.sql` într-o bază nouă și pui datele în config-ul instanței.
4. **Rezolvare tenant după host**: în `init.php` adaugi un pas care, în funcție de `HTTP_HOST` (subdomeniul), alege baza de date corectă (un mic „landlord" — tabel cu maparea subdomeniu → bază/credentiale).
5. **Panou „landlord"** (al tău): creezi/suspenzi clienți, vezi abonamente, generezi facturi.

Codul e deja pregătit: conexiunea DB e centralizată (un singur loc de schimbat), setările sunt per-instanță în tabelul `settings`, iar cookie-ul de sesiune e legat de host (clienții nu se „văd" între ei).

---

## Următorii pași sugerați (opțional)
- Generator QR local (acum se folosește un serviciu extern pentru QR-ul de pe ecran; pe bonul termic QR-ul e nativ ESC/POS).
- ✅ Rapoarte/statistici extinse: **export Excel (.xlsx) cu grafice native** (linie, bare, coloane, plăcintă) + export CSV, grafice pe interval.
- Programări (appointments) și formulare la emiterea bonului (există în Moviik).
- SMS la apropierea rândului (bilet prin SMS).
- Aplicația Android launcher (cod sursă) — specificația completă e în `android/README.md`.

---

## Module incluse (stare curenta — aproape 1:1 cu Moviik)

Backoffice (temă dark): **Dashboard** (KPI + donut distribuție pe serviciu + aflux orar live), **Statistici** (KPI + grafice + **export Excel `.xlsx` cu grafice native** și export CSV), **Filiale** (cu taburi Servicii/Ghișee/Dispozitive), **Servicii** (prefix/culoare/interval/prioritar/KPI + **program de funcționare** + **formular** + **programări**), **Ghișee**, **Dispozitive** (cu **editor afișaj TV pe canvas** și **editor dispenser cu taburi** Logic/Aspect/Texte/Popup), **Multimedia** (galerie cu upload + alegere în logo/widget), **Formulare** (builder câmpuri dinamice), **Utilizatori**, **Bilete**, **Programări** (admin), **Setări**, **Roluri** (permisiuni pentru manager).

Public / dispozitive: **dispenser** configurabil (cu popup tip/politică și formular), **afișaj TV** (layout din canvas + anunț vocal RO), **bilet digital** (QR, status live), **terminal operator** (cu date formular), **programare online** (`/book`) + **pagină programare** cu check-in (`/a/{token}`).

## Actualizare (instalare existentă)

Sistemul are **migrare automată a bazei**. Pentru a actualiza o instalare existentă pe cPanel:
1. Dezarhivează noua versiune **peste** fișierele vechi (suprascrie tot).
2. Deschide aplicația o dată în browser — la prima accesare, schema bazei se actualizează singură (adaugă tabele/coloane noi precum `forms`, `appointments`, `services.form_id`, `tickets.form_data` etc.) **fără pierderi de date** și fără SQL manual.
3. Gata. (Folderul `assets/uploads/` trebuie să fie scriibil pentru Multimedia — vezi INSTALL.md.)
