<?php
/*
 * PATCH untuk config/auth.php pada server SIMPEL.
 * Tujuan: akun yang login dari aplikasi Android tidak cepat logout karena
 * cookie/session PHP default yang pendek.
 *
 * Terapkan BLOK ini sebelum session_start() pada config/auth.php.
 */
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isSimPelAndroid = stripos($ua, 'SIMPEL-Android/') !== false;

if ($isSimPelAndroid) {
    // 1 tahun. Logout tetap terjadi jika pengguna menekan Keluar atau
    // administrator/server mencabut sesi.
    $sessionLifetime = 31536000;
    ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Lalu biarkan baris auth.php yang sudah ada:
// if(session_status()!==PHP_SESSION_ACTIVE) session_start();
