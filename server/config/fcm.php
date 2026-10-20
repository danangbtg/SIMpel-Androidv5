<?php
declare(strict_types=1);

// Firebase project SIMPEL SMPN 8 Bontang.
const SIMPEL_FCM_PROJECT_ID = 'simpel-smpn-8-bontang';

// Lebih aman memakai environment variable. Jika tidak tersedia, gunakan
// lokasi private hosting yang sudah disiapkan. File JSON JANGAN diletakkan
// di public_html/public.
$SIMPEL_FCM_SERVICE_ACCOUNT_PATH = getenv('SIMPEL_FCM_SERVICE_ACCOUNT') ?: '/home2/smpnbon1/private/firebase-service-account.json';
if (!defined('SIMPEL_FCM_SERVICE_ACCOUNT')) {
    define('SIMPEL_FCM_SERVICE_ACCOUNT', $SIMPEL_FCM_SERVICE_ACCOUNT_PATH);
}
