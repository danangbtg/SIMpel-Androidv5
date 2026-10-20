<?php
declare(strict_types=1);

require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    $u = function_exists('user') ? user() : null;
    if (!$u || empty($u['id'])) {
        http_response_code(401);
        echo json_encode(['ok'=>false,'message'=>'Silakan login ke SIMPEL terlebih dahulu.']);
        exit;
    }

    $raw=file_get_contents('php://input');
    $data=json_decode($raw ?: '', true);
    $token=trim((string)($data['token']??''));
    $platform=trim((string)($data['platform']??'android'));

    if($token==='' || strlen($token)>512){
        http_response_code(422);
        echo json_encode(['ok'=>false,'message'=>'Token FCM tidak valid.']);
        exit;
    }

    $uid=(int)$u['id'];

    $pdo->prepare("UPDATE device_tokens SET active=0 WHERE token=? AND user_id<>?")
        ->execute([$token,$uid]);

    $st=$pdo->prepare("SELECT id FROM device_tokens WHERE token=? LIMIT 1");
    $st->execute([$token]);
    $id=(int)($st->fetchColumn()?:0);

    if($id){
        $pdo->prepare("UPDATE device_tokens SET user_id=?,platform=?,active=1,last_seen_at=NOW() WHERE id=?")
            ->execute([$uid,$platform,$id]);
    }else{
        $pdo->prepare("INSERT INTO device_tokens(user_id,token,platform,active,last_seen_at) VALUES(?,?,?,?,NOW())")
            ->execute([$uid,$token,$platform,1]);
    }

    echo json_encode(['ok'=>true]);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
