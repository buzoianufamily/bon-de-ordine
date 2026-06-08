<?php
/**
 * Configurare aplicatie - Sistem de Bon de Ordine
 * Editeaza aceste valori dupa ce creezi baza de date in cPanel.
 *
 * IMPORTANT: dupa instalare, schimba APP_SETUP_TOKEN si sterge fisierul install.php
 */

return [
    // ---- Baza de date (din cPanel > MySQL Databases) ----
    'db' => [
        'host'    => 'localhost',
        'name'    => 'r140100buzo_bon',      // ex: cont_queue
        'user'    => 'r140100buzo_bon',       // ex: cont_user
        'pass'    => 'bondeordine',
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

    // Token folosit O SINGURA DATA de install.php pentru a crea primul admin.
    // Schimba-l cu ceva aleatoriu inainte sa rulezi install.php.
    'setup_token' => 'test123',

    // Cheie secreta pentru sesiuni/CSRF (schimb-o cu un string aleatoriu lung)
    'app_key' => 'test123',
];
