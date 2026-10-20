<?php
declare(strict_types=1);

function simpel_fcm_base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function simpel_fcm_service_account(): array {
    require_once __DIR__.'/../config/fcm.php';
    static $sa = null;
    if ($sa !== null) return $sa;

    if (!is_readable(SIMPEL_FCM_SERVICE_ACCOUNT)) {
        throw new RuntimeException('Firebase service-account JSON tidak ditemukan.');
    }

    $sa = json_decode((string)file_get_contents(SIMPEL_FCM_SERVICE_ACCOUNT), true);
    if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key'])) {
        throw new RuntimeException('Firebase service-account JSON tidak valid.');
    }
    return $sa;
}

function simpel_fcm_access_token(): string {
    $sa = simpel_fcm_service_account();
    $now = time();

    $header = simpel_fcm_base64url(json_encode([
        'alg'=>'RS256',
        'typ'=>'JWT'
    ], JSON_UNESCAPED_SLASHES));

    $claim = simpel_fcm_base64url(json_encode([
        'iss'=>$sa['client_email'],
        'scope'=>'https://www.googleapis.com/auth/firebase.messaging',
        'aud'=>'https://oauth2.googleapis.com/token',
        'iat'=>$now,
        'exp'=>$now+3600
    ], JSON_UNESCAPED_SLASHES));

    $unsigned = $header.'.'.$claim;
    $signature = '';
    if (!openssl_sign($unsigned, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Gagal membuat JWT Firebase.');
    }

    $jwt = $unsigned.'.'.simpel_fcm_base64url($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST=>true,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>20,
        CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS=>http_build_query([
            'grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'=>$jwt
        ])
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if($raw===false || $code<200 || $code>=300) {
        throw new RuntimeException('Gagal mendapatkan token OAuth Firebase: '.$error.' '.$raw);
    }

    $data=json_decode($raw,true);
    if(empty($data['access_token'])) throw new RuntimeException('Access token Firebase tidak tersedia.');
    return (string)$data['access_token'];
}

function simpel_fcm_send_token(string $token, string $title, string $body, string $url='/notifikasi.php'): array {
    require_once __DIR__.'/../config/fcm.php';

    $access = simpel_fcm_access_token();
    $endpoint = 'https://fcm.googleapis.com/v1/projects/'.rawurlencode(SIMPEL_FCM_PROJECT_ID).'/messages:send';

    $payload = [
        'message'=>[
            'token'=>$token,
            'notification'=>[
                'title'=>$title,
                'body'=>$body
            ],
            'data'=>[
                'title'=>$title,
                'body'=>$body,
                'url'=>$url
            ],
            'android'=>[
                'priority'=>'high',
                'notification'=>[
                    'channel_id'=>'simpel_default',
                    'sound'=>'default'
                ]
            ]
        ]
    ];

    $ch=curl_init($endpoint);
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>20,
        CURLOPT_HTTPHEADER=>[
            'Authorization: Bearer '.$access,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
    ]);
    $raw=curl_exec($ch);
    $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $error=curl_error($ch);
    curl_close($ch);

    if($raw===false) throw new RuntimeException('FCM curl error: '.$error);

    return [
        'ok'=>$code>=200 && $code<300,
        'http_code'=>$code,
        'response'=>$raw
    ];
}

function simpel_fcm_send_to_user(PDO $pdo, int $userId, string $title, string $body, string $url='/notifikasi.php'): int {
    $st=$pdo->prepare("SELECT id,token FROM device_tokens WHERE user_id=? AND active=1");
    $st->execute([$userId]);
    $sent=0;

    foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row){
        try{
            $res=simpel_fcm_send_token((string)$row['token'],$title,$body,$url);
            if($res['ok']){
                $sent++;
                $pdo->prepare("UPDATE device_tokens SET last_seen_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
            }else{
                $decoded=json_decode((string)$res['response'],true);
                $message=json_encode($decoded,JSON_UNESCAPED_UNICODE);
                if(stripos($message,'UNREGISTERED')!==false || stripos($message,'INVALID_ARGUMENT')!==false){
                    $pdo->prepare("UPDATE device_tokens SET active=0 WHERE id=?")->execute([(int)$row['id']]);
                }
            }
        }catch(Throwable $e){
            // Jangan menggagalkan transaksi utama SIMPEL hanya karena push gagal.
        }
    }
    return $sent;
}
