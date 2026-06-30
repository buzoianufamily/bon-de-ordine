<?php
/**
 * SABLON de configurare — copiaza acest fisier ca `config/config.php` si completeaza
 * datele reale. `config/config.php` NU se urca in git (contine secrete) — vezi .gitignore.
 *
 * Schema bazei + contul admin implicit (admin@example.ro / 123456) se creeaza AUTOMAT
 * la prima accesare a site-ului — nu e nevoie de install.php.
 */

return [
    // ---- Baza de date (din cPanel > MySQL Databases) ----
    'db' => [
        'host'    => 'localhost',
        'name'    => 'CONT_numebaza',     // numele bazei din cPanel
        'user'    => 'CONT_utilizator',   // utilizatorul bazei
        'pass'    => 'PAROLA_BAZEI',      // parola bazei
        'charset' => 'utf8mb4',
    ],

    // ---- Aplicatie ----
    'app' => [
        'name'      => 'Sistem Bon de Ordine',
        'base_url'  => '',                // gol = auto-detectare; sau ex: https://client.domeniu.ro
        'env'       => 'production',      // 'production' sau 'dev' (dev = arata erorile)
        'timezone'  => 'Europe/Bucharest',
        'locale'    => 'ro',
        // optionale (pentru SaaS multi-tenant):
        // 'support_email' => 'contact@domeniu.ro',  // afisat clientilor suspendati/expirati
        // 'renew_url'     => 'https://domeniu.ro/reinnoire',
    ],

    // ---- Multi-tenant (panoul landlord) ----
    // Parola panoului /landlord (administrarea instantelor clientilor tai).
    // Gol = panoul e dezactivat. Pune un sir LUNG si aleatoriu cand il folosesti.
    'landlord_pass' => '',
];
