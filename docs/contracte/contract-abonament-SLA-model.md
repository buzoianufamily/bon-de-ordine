# Contract de abonament + nivel de serviciu (SLA) — MODEL

> ⚠️ **Model orientativ, NU consultanță juridică.** Adaptează prețurile, termenele
> și nivelurile de serviciu la oferta ta și validează cu un jurist.

Între **Furnizor** `{DENUMIRE_FURNIZOR}` (CUI `{CUI}`) și **Client**
`{DENUMIRE_CLIENT}` (CUI `{CUI}`), pentru utilizarea aplicației „Bon de ordine".

## 1. Obiect
Furnizorul oferă acces, prin abonament, la aplicația de gestionare a cozilor și
programărilor, găzduită la `{SUBDOMENIU}.{DOMENIU}`.

## 2. Pachet și limite
| Pachet | Filiale | Ghișee | Utilizatori | Servicii | Preț/lună |
|--------|---------|--------|-------------|----------|-----------|
| `{PACHET}` | `{N}` | `{N}` | `{N}` | `{N}` | `{PREȚ}` |

Limitele se aplică automat în aplicație (panoul de administrare blochează
crearea peste pachet, fără a afecta datele existente). Trecerea la un pachet
superior se face prin actualizarea limitelor de către Furnizor.

## 3. Durată și facturare
- Durată: `{LUNI}` luni, cu reînnoire automată dacă nu se denunță cu `{ZILE}` zile înainte.
- Facturare: `{lunar/anual}`, în avans. Plata în `{N}` zile de la emiterea facturii.
- Neplata: după expirare + `{ZILE}` zile de grație, accesul se **suspendă automat**
  (clientul vede o pagină de „abonament expirat"); datele se păstrează `{LUNI}` luni.

## 4. Nivel de serviciu (SLA)
- **Disponibilitate țintă:** `{99,x}%` lunar, exceptând mentenanța anunțată.
- **Mentenanță planificată:** anunțată cu `{ORE}` ore înainte, în afara orelor de vârf.
- **Backup:** automat zilnic, cu păstrarea ultimelor `{N}` copii; restaurare la cerere.
- **Suport:** `{email/telefon}`, program `{L–V, 9–17}`, timp de răspuns țintă:
  - incident critic (serviciu indisponibil): `{X}` ore;
  - incident major: `{X}` ore lucrătoare;
  - solicitare obișnuită: `{X}` zile lucrătoare.

## 5. Obligațiile Furnizorului
Menținerea în funcțiune, aplicarea actualizărilor de securitate, backup,
respectarea DPA-ului anexat (prelucrarea datelor).

## 6. Obligațiile Clientului
Utilizare corectă, păstrarea confidențialității conturilor, schimbarea parolei
implicite, desemnarea unui administrator, respectarea legislației aplicabile
(inclusiv informarea propriilor clienți conform Politicii de confidențialitate).

## 7. Protecția datelor
Părțile semnează **Acordul de prelucrare a datelor (DPA)** anexat (Clientul =
operator, Furnizorul = persoană împuternicită).

## 8. Limitarea răspunderii
Serviciul este oferit „ca atare". Răspunderea Furnizorului este limitată la
valoarea abonamentului pe `{N}` luni. Furnizorul nu răspunde pentru pierderi
indirecte. Estimările de timp de așteptare sunt orientative.

## 9. Încetare
Prin acordul părților, la expirare, sau pentru neîndeplinirea obligațiilor cu
notificare de `{ZILE}` zile. La încetare se aplică art. 9 din DPA (ștergere/returnare date).

## 10. Lege aplicabilă
Legislația din România; litigiile se soluționează la instanțele din `{ORAȘ}`.

---
Data: `{DATA}` · Furnizor: `____________` · Client: `____________`
