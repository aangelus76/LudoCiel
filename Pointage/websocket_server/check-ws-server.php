<?php
/**
 * Vérifie si un serveur WebSocket est actif
 * Retourne JSON: {active: bool, ip: string|null}
 */

require_once 'config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO("sqlite:" . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Lire le verrou serveur
    $stmt = $pdo->prepare("
        SELECT value, timestamp 
        FROM config 
        WHERE key = 'ws_server_lock'
    ");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo json_encode(['active' => false, 'ip' => null]);
        exit;
    }
    
    $timestamp = $row['timestamp'];
    $ip = $row['value'];
    $now = time();
    
    // Vérifier que le verrou date de moins de WS_IP_VALIDITY secondes
    if (($now - $timestamp) > WS_IP_VALIDITY) {
        echo json_encode(['active' => false, 'ip' => null]);
        exit;
    }
    
    echo json_encode(['active' => true, 'ip' => $ip]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}