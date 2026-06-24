<?php
/**
 * Pagini de eroare prietenoase + prinderea erorilor fatale/neprinse.
 * Nu depinde de baza de date (functioneaza si cand DB e picata) si nu scurge
 * detalii in productie. Inclus foarte devreme din init.php.
 */

/** Randeaza o pagina de eroare auto-continuta (fara DB) si OPRESTE executia. */
function fail_page(int $code, string $title, string $msg, ?string $detail = null): void {
    $dev = (($GLOBALS['__config']['app']['env'] ?? 'production') === 'dev');
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
    }
    // raspuns JSON pentru clientii de API/AJAX
    if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
        || stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        echo json_encode(['ok' => false, 'error' => $msg]);
        exit;
    }
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="ro"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $h($title) . '</title>'
       . '<body style="margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0b0d12;color:#e8eaef">'
       . '<div style="text-align:center;padding:2rem;max-width:34rem">'
       . '<div style="font-size:3rem">' . ($code >= 500 ? '⚠️' : '🔍') . '</div>'
       . '<h1 style="margin:.3em 0;font-size:1.4rem">' . $h($title) . '</h1>'
       . '<p style="color:#8a93a3;line-height:1.55">' . $h($msg) . '</p>'
       . ($dev && $detail ? '<pre style="text-align:left;white-space:pre-wrap;background:#11141b;color:#f87171;padding:1rem;border-radius:8px;overflow:auto;font-size:.78rem;margin-top:1rem">' . $h($detail) . '</pre>' : '')
       . '<p style="margin-top:1.5rem"><a href="/" style="color:#7da2ff;text-decoration:none">← Acasă</a></p>'
       . '</div></body></html>';
    exit;
}

/** Inregistreaza handlerele globale (fatal + exceptii neprinse) si porneste buffering-ul. */
function bdo_install_error_handlers(): void {
    // tampon de iesire: la o eroare fatala in mijlocul randarii putem arunca iesirea partiala
    // si afisa o pagina curata. SSE/descarcarile inchid bufferul explicit cand au nevoie.
    if (function_exists('ob_start') && ob_get_level() === 0) ob_start();

    set_exception_handler(function (\Throwable $ex): void {
        @error_log('[bon-de-ordine] UNCAUGHT ' . get_class($ex) . ': ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
        if (!headers_sent()) { while (ob_get_level() > 0) @ob_end_clean(); }
        fail_page(500, 'Eroare internă', 'A apărut o eroare neașteptată. Te rugăm reîncearcă în câteva momente.', $ex->getMessage() . "\n\n" . $ex->getTraceAsString());
    });

    register_shutdown_function(function (): void {
        $e = error_get_last();
        if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
        @error_log('[bon-de-ordine] FATAL: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
        if (!headers_sent()) { while (ob_get_level() > 0) @ob_end_clean(); }
        fail_page(500, 'Eroare internă', 'A apărut o eroare neașteptată. Te rugăm reîncearcă în câteva momente.', $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
    });
}
