<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    validateConfig();
    
    $vbsScript = sys_get_temp_dir() . '/start_ws_server_' . uniqid() . '.vbs';
    
    $serverPath = __DIR__ . '/websocket-server.php';
    $phpPath = PHP_PATH;
    
    $phpPath = str_replace('"', '""', $phpPath);
    $serverPath = str_replace('"', '""', $serverPath);
    
    $vbsContent = <<<VBS
Set WshShell = CreateObject("WScript.Shell")
WshShell.Run """{$phpPath}"" ""{$serverPath}""", 0, False
Set WshShell = Nothing
VBS;
    
    file_put_contents($vbsScript, $vbsContent);
    
    $cmd = 'cscript //nologo "' . $vbsScript . '"';
    exec($cmd . ' > NUL 2>&1 &');
    
    sleep(1);
    
    $pdo = new PDO("sqlite:" . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("
        INSERT OR REPLACE INTO config (key, value, timestamp)
        VALUES ('ws_server_ip', ?, ?)
    ");
    $stmt->execute([getNetworkIP(), time()]);
    
    register_shutdown_function(function() use ($vbsScript) {
        sleep(1);
        @unlink($vbsScript);
    });
    
    echo json_encode([
        'success' => true,
        'message' => 'Serveur démarré',
        'ip' => getNetworkIP(),
        'port' => WS_PORT
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}