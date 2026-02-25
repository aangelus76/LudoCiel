<?php
/**
 * Tente d'acquérir le verrou serveur WebSocket
 * Retourne JSON: {acquired: bool, message: string}
 */

require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['client_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'client_id requis']);
    exit;
}

$clientId = $_GET['client_id'];

try {
    $pdo = new PDO("sqlite:" . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->beginTransaction();
    
    // Nettoyer les vieux verrous (> WS_LOCK_TIMEOUT secondes)
    $stmt = $pdo->prepare("
        DELETE FROM config 
        WHERE key = 'ws_server_lock' 
        AND (? - timestamp) > ?
    ");
    $stmt->execute([time(), WS_LOCK_TIMEOUT]);
    
    // Vérifier si un verrou existe
    $stmt = $pdo->prepare("
        SELECT value, timestamp 
        FROM config 
        WHERE key = 'ws_server_lock'
    ");
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $pdo->commit();
        echo json_encode([
            'acquired' => false,
            'message' => 'Verrou détenu par ' . $existing['value']
        ]);
        exit;
    }
    
    // Tenter d'acquérir le verrou
    $stmt = $pdo->prepare("
        INSERT INTO config (key, value, timestamp)
        VALUES ('ws_server_lock', ?, ?)
    ");
    $stmt->execute([getNetworkIP(), time()]);
    
    $pdo->commit();
    
    echo json_encode([
        'acquired' => true,
        'message' => 'Verrou acquis par ' . getNetworkIP()
    ]);
    
} catch(Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}