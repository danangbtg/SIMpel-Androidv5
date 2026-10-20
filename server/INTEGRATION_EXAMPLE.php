<?php
// Contoh integrasi pada modul SIMPEL yang sudah memiliki $pdo dan $userId:
require_once __DIR__.'/lib/fcm.php';

simpel_fcm_send_to_user(
    $pdo,
    (int)$userId,
    'Notifikasi SIMPEL',
    'Ada informasi baru yang perlu Anda periksa.',
    '/notifikasi.php'
);
