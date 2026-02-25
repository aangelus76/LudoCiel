<?php
require_once 'config.php';

$pdo = new PDO("sqlite:" . DB_PATH);
$now = time();

// force=true → nettoyage total, sinon → seulement expirés
$force = isset($_GET['force']) && $_GET['force'] === 'true';

if ($force) {
    // Nettoyage TOTAL (crash détecté)
    $pdo->exec("DELETE FROM config WHERE key IN ('ws_server_lock', 'ws_server_ip')");
} else {
    // Nettoyage CONDITIONNEL (verrous expirés)
    $pdo->exec("DELETE FROM config WHERE key = 'ws_server_lock' AND ($now - timestamp) > " . WS_LOCK_TIMEOUT);
    $pdo->exec("DELETE FROM config WHERE key = 'ws_server_ip' AND ($now - timestamp) > " . WS_IP_VALIDITY);
}

echo json_encode(['success' => true, 'force' => $force]);