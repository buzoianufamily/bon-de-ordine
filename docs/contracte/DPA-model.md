# Acord de prelucrare a datelor (DPA) — MODEL

> ⚠️ **Model orientativ, NU consultanță juridică.** Adaptează-l la situația ta și
> validează-l cu un jurist înainte de semnare. Se încheie între furnizorul
> platformei (tu) și fiecare client.

Încheiat conform art. 28 din Regulamentul (UE) 2016/679 (GDPR), între:

- **Operatorul de date** (Clientul): `{DENUMIRE_CLIENT}`, `{ADRESA}`, CUI `{CUI}`, reprezentat de `{REPREZENTANT}` — denumit „Operatorul";
- **Persoana împuternicită** (Furnizorul): `{DENUMIRE_FURNIZOR}`, `{ADRESA}`, CUI `{CUI}` — denumit „Împuternicitul",

care administrează aplicația de gestionare a cozilor „Bon de ordine".

## 1. Obiectul prelucrării
Împuternicitul prelucrează date personale **exclusiv în numele și la instrucțiunile**
Operatorului, pentru a furniza serviciul de gestionare a cozilor și a programărilor.

## 2. Durata
Pe durata contractului de abonament. La încetare, se aplică art. 9.

## 3. Natura și scopul prelucrării
Găzduire, stocare, afișare și procesare a datelor introduse în aplicație
(bilete de ordine, programări, feedback, conturi de operatori), în scopul
funcționării serviciului.

## 4. Categorii de persoane vizate
Clienții/cetățenii care iau bilet sau se programează; operatorii Operatorului.

## 5. Categorii de date
Nume, telefon, email (la programări), conținut feedback, date tehnice minime
(IP, jurnale). **Nu** se prelucrează categorii speciale de date prin proiectare.

## 6. Obligațiile Împuternicitului
a) prelucrează datele doar pe baza instrucțiunilor documentate ale Operatorului;
b) asigură confidențialitatea persoanelor autorizate să prelucreze datele;
c) ia măsuri tehnice și organizatorice adecvate (art. 32): HTTPS, parole
   stocate ca hash, control acces pe roluri, 2FA disponibil, backup, jurnal de
   audit, izolarea datelor între clienți (baze de date separate);
d) nu apelează la un alt subîmputernicit fără autorizarea Operatorului (art. 8);
e) asistă Operatorul la răspunsul către persoanele vizate (există unelte de
   export și ștergere a datelor după email/telefon în aplicație);
f) asistă Operatorul privind securitatea, notificarea breșelor și DPIA;
g) notifică Operatorul fără întârziere nejustificată la o breșă de securitate;
h) pune la dispoziția Operatorului informațiile necesare pentru a demonstra
   conformitatea și permite audituri.

## 7. Măsuri de securitate (rezumat tehnic)
- Transmisie criptată (HTTPS/TLS).
- Parole stocate doar ca hash (bcrypt/Argon prin `password_hash`).
- Autentificare în doi pași (TOTP) disponibilă și impunabilă pentru admini.
- Schimbarea obligatorie a parolei implicite la prima utilizare.
- Backup automat (cu retenție) și jurnal de audit.
- Minimizare: ștergere automată a datelor mai vechi de perioada de retenție.
- Fonturi și resurse găzduite local (fără transfer de date către terți).

## 8. Subîmputerniciți
Eventualul furnizor de găzduire (`{HOSTING}`) acționează ca subîmputernicit,
strict pentru găzduirea infrastructurii. Operatorul este informat despre orice
schimbare.

## 9. Ștergerea/returnarea datelor la încetare
La încetarea contractului, la alegerea Operatorului, Împuternicitul **șterge**
sau **returnează** toate datele (ex: export SQL) și elimină copiile existente,
cu excepția celor impuse de lege.

## 10. Transfer internațional
Datele se păstrează în UE/SEE. Niciun transfer în afara SEE fără garanțiile art. 46.

---
Data: `{DATA}` · Operator: `____________` · Împuternicit: `____________`
