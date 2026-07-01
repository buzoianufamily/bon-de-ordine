<?php
/**
 * Configurare aplicatie - Sistem de Bon de Ordine
 * Editeaza DB-ul mai jos si urca fisierele pe server. Schema + adminul implicit
 * se creeaza automat la primul acces — nu e nevoie de install.php.
 */

return [
    // ---- Baza de date (din cPanel > MySQL Databases) ----
    'db' => [
        'host'    => 'localhost',
        'name'    => 'r140100buzo_demobondeordine',      // ex: cont_queue
        'user'    => 'r140100buzo_demobondeordine',       // ex: cont_user
        'pass'    => 'demobondeordine',
        'charset' => 'utf8mb4',
    ],

    // ---- Aplicatie ----
    'app' => [
        'name'      => 'Sistem Bon de Ordine',
        'base_url'  => '',                // lasa gol pt auto-detectare; sau ex: https://client1.domeniu.ro
        'env'       => 'production',      // 'production' sau 'dev' (dev = arata erorile)
        'timezone'  => 'Europe/Bucharest',
        'locale'    => 'ro',
    ],

    // ---- Multi-tenant (optional) ----
    // Parola panoului /landlord (administrarea instantelor clientilor).
    // Gol = panoul e dezactivat. Pune un sir lung si aleatoriu cand il folosesti.
    'landlord_pass' => 'demobondeordine',
];
