<?php
/**
 * Retourne l'IP du serveur WebSocket actif
 * Retourne JSON: {ip: string|null}
 */

require_once 'config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO("sqlite:" . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	    $stmt = $pdo->prepare("DELETE FROM config WHERE key = 'ws_server_lock' AND (? - timestamp) > ?");
    $stmt->execute([time(), WS_LOCK_TIMEOUT]);
    
    $stmt = $pdo->prepare("
        SELECT value, timestamp 
        FROM config 
        WHERE key = 'ws_server_ip'
    ");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo json_encode(['ip' => null]);
        exit;
    }
    
    $timestamp = $row['timestamp'];
    $ip = $row['value'];
    $now = time();
    
    // Vérifier validité (< WS_IP_VALIDITY secondes)
    if (($now - $timestamp) > WS_IP_VALIDITY) {
        echo json_encode(['ip' => null]);
        exit;
    }
    
    echo json_encode(['ip' => $ip]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}