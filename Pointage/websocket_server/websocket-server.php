<?php
require_once 'config.php';
require_once '../ws-handlers/pointage-handler.php';
require_once '../ws-handlers/animation-handler.php';
ob_implicit_flush(true);

class WebSocketServer {
    private $socket;
    private $clients = [];
    private $pdo;
    private $lastPing;
    private $lockRefreshTime;
    private $isServerStarted = false;
    
    // Handlers
    private $pointageHandler;
    private $animationHandler;
    
    public function __construct() {
        echo "[" . date('H:i:s') . "] Démarrage serveur WebSocket...\n";
        
        register_shutdown_function(array($this, 'cleanup'));
        
        $this->pdo = new PDO("sqlite:" . DB_PATH);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Initialiser handler pointage
        $this->pointageHandler = new PointageHandler(
            $this->pdo,
            function($date, $message) {
                $this->broadcastToDate($date, $message);
            },
            function($projet, $message) {
                $this->broadcastToProjet($projet, $message);
            },
            function($message) {
                $this->broadcastToAll($message);
            }
        );
        
        // Initialiser handler animations
        $this->animationHandler = new AnimationHandler(
            $this->pdo,
            function($message) {
                $this->broadcastToProjet('animations', $message);
            }
        );
        
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        
        if (!$this->socket) {
            $error = socket_last_error();
            $this->logError("SOCKET_CREATE FAILED [" . $error . "]: " . socket_strerror($error));
            exit(1);
        }
        
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        }
        
        if (!@socket_bind($this->socket, '0.0.0.0', WS_PORT)) {
            $error = socket_last_error($this->socket);
            $this->logError("BIND FAILED [" . $error . "]: " . socket_strerror($error) . " - Port " . WS_PORT . " déjà utilisé");
            $this->cleanup();
            exit(1);
        }
        
        if (!@socket_listen($this->socket)) {
            $error = socket_last_error($this->socket);
            $this->logError("LISTEN FAILED [" . $error . "]: " . socket_strerror($error));
            $this->cleanup();
            exit(1);
        }
        
        socket_set_nonblock($this->socket);
        
        $this->isServerStarted = true;
        
        echo "[" . date('H:i:s') . "] Écoute sur " . getNetworkIP() . ":" . WS_PORT . "\n";
        
        $this->lastPing = time();
        $this->lockRefreshTime = time();
    }
    
    public function run() {
        while(true) {
            if (time() - $this->lockRefreshTime >= WS_LOCK_REFRESH) {
                $this->refreshLock();
                $this->lockRefreshTime = time();
            }
            
            if (time() - $this->lastPing >= WS_PING_INTERVAL) {
                $this->sendPingToAll();
                $this->lastPing = time();
            }
            
            $newSocket = @socket_accept($this->socket);
            if ($newSocket) {
                $this->handleNewConnection($newSocket);
            }
            
            foreach($this->clients as $clientId => $client) {
                $buffer = @socket_read($client['socket'], 2048);
                
                if ($buffer === false) {
                    $error = socket_last_error($client['socket']);
                    socket_clear_error($client['socket']);
                    
                    if ($error !== 0 && $error !== 11 && $error !== 10035) {
                        $this->disconnectClient($clientId);
                    }
                    continue;
                }
                
                if ($buffer === '') continue;
                
                if (!$client['handshake']) {
                    $this->doHandshake($clientId, $buffer);
                } else {
                    $this->handleMessage($clientId, $buffer);
                }
            }
            
            usleep(10000);
        }
    }
    
    public function cleanup() {
        echo "[" . date('H:i:s') . "] Nettoyage du serveur...\n";
        
        if ($this->socket) {
            @socket_close($this->socket);
            $this->socket = null;
        }
        
        foreach($this->clients as $client) {
            if (isset($client['socket'])) {
                @socket_close($client['socket']);
            }
        }
        $this->clients = [];
        
        if ($this->isServerStarted) {
            try {
                if ($this->pdo) {
                    $this->pdo->exec("DELETE FROM config WHERE key IN ('ws_server_lock', 'ws_server_ip')");
                    echo "[" . date('H:i:s') . "] Verrous nettoyés\n";
                }
            } catch(Exception $e) {
                echo "[" . date('H:i:s') . "] Erreur nettoyage BDD : " . $e->getMessage() . "\n";
            }
        }
    }
    
    private function logError($message) {
        $logFile = __DIR__ . '/websocket_errors.log';
        $timestamp = date('[Y-m-d H:i:s]');
        @file_put_contents($logFile, "$timestamp $message\n", FILE_APPEND);
        echo $timestamp . " " . $message . "\n";
    }
    
    private function handleNewConnection($socket) {
        $clientId = uniqid('client_', true);
        $this->clients[$clientId] = [
            'socket' => $socket,
            'handshake' => false,
            'id' => $clientId,
            'date' => null,
            'projet' => null
        ];
        echo "[" . date('H:i:s') . "] Connexion: $clientId\n";
    }
    
    private function doHandshake($clientId, $buffer) {
        $headers = [];
        foreach(explode("\n", $buffer) as $line) {
            $line = trim($line);
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        if (!isset($headers['Sec-WebSocket-Key'])) return;
        
        $acceptKey = base64_encode(sha1($headers['Sec-WebSocket-Key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        
        $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                   "Upgrade: websocket\r\n" .
                   "Connection: Upgrade\r\n" .
                   "Sec-WebSocket-Accept: $acceptKey\r\n\r\n";
        
        socket_write($this->clients[$clientId]['socket'], $response);
        $this->clients[$clientId]['handshake'] = true;
        
        echo "[" . date('H:i:s') . "] Handshake: $clientId\n";
    }
    
    private function handleMessage($clientId, $buffer) {
        $decoded = $this->decodeFrame($buffer);
        if ($decoded === false) return;
        
        if ($decoded['opcode'] === 0x8) {
            $this->disconnectClient($clientId);
            return;
        }
        
        if ($decoded['opcode'] === 0xA) return;
        
        $message = json_decode($decoded['payload'], true);
        if (!$message || !isset($message['action'])) return;
        
        if (isset($message['date'])) {
            $this->clients[$clientId]['date'] = $message['date'];
        }
        
        if (isset($message['projet'])) {
            $this->clients[$clientId]['projet'] = $message['projet'];
            echo "[" . date('H:i:s') . "] Client $clientId -> projet: {$message['projet']}\n";
        }
        
        echo "[" . date('H:i:s') . "] Action: {$message['action']}\n";
        
        $this->processAction($clientId, $message);
    }
    
    private function processAction($senderId, $message) {
        $action = $message['action'];
        $data = isset($message['data']) ? $message['data'] : [];
        $senderClientId = isset($message['sender']) ? $message['sender'] : null;
        $date = isset($message['date']) ? $message['date'] : date('Y-m-d');
        $projet = isset($message['projet']) ? $message['projet'] : null;
        
        // ACTION REGISTER : juste enregistrer le projet
        if ($action === 'register') {
            if ($projet) {
                $this->clients[$senderId]['projet'] = $projet;
            }
            echo "[" . date('H:i:s') . "] Client enregistré sur projet: $projet\n";
            return;
        }
        
        // ROUTING POINTAGE
        if (in_array($action, [
            'individual_add', 'individual_update_time', 'individual_update_left',
            'individual_delete', 'individual_who', 'individual_update_type',
            'partner_add', 'partner_update', 'partner_update_left', 'partner_delete',
            'group_create', 'group_assign', 'group_comment'
        ])) {
            $this->clients[$senderId]['projet'] = 'pointage';
            $this->pointageHandler->handle($action, $data, $senderClientId, $date);
            return;
        }
        
        // ROUTING ANIMATIONS
        // CORRIGÉ: inscription_validate (cohérent avec le client JS)
        if (in_array($action, [
            'animation_create', 'animation_update', 'animation_delete',
            'inscription_add', 'inscription_update', 'inscription_delete', 'inscription_validate'
        ])) {
            $this->clients[$senderId]['projet'] = 'animations';
            $this->animationHandler->handle($action, $data, $senderClientId);
            return;
        }
        
        // État initial pointage
        if ($action === 'get_initial_state') {
            $this->clients[$senderId]['projet'] = 'pointage';
            $this->sendInitialState($senderId, $date);
            return;
        }
    }
    
    private function sendInitialState($clientId, $date) {
        $stmt = $this->pdo->prepare("
            SELECT i.*, g.color, g.comment
            FROM individuals i
            LEFT JOIN groups g ON i.group_id = g.group_id
            WHERE DATE(i.arrival_time) = DATE(?)
        ");
        $stmt->execute([$date]);
        $individuals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $this->pdo->prepare("SELECT * FROM partners WHERE DATE(created_at) = DATE(?)");
        $stmt->execute([$date]);
        $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->sendToClient($clientId, [
            'action' => 'initial_state',
            'date' => $date,
            'data' => [
                'individuals' => $individuals,
                'partners' => $partners
            ]
        ]);
    }
    
    private function broadcastToDate($targetDate, $message) {
        foreach($this->clients as $client) {
            if (!$client['handshake'] || $client['date'] !== $targetDate) continue;
            $this->sendToClient($client['id'], $message);
        }
    }
    
    private function broadcastToProjet($projet, $message) {
        $count = 0;
        foreach($this->clients as $client) {
            if (!$client['handshake']) continue;
            if ($client['projet'] === $projet) {
                $this->sendToClient($client['id'], $message);
                $count++;
            }
        }
        echo "[" . date('H:i:s') . "] Broadcast '$projet': {$message['action']} -> $count client(s)\n";
    }
    
    private function broadcastToAll($message) {
        foreach($this->clients as $client) {
            if (!$client['handshake']) continue;
            $this->sendToClient($client['id'], $message);
        }
    }
    
    private function sendToClient($clientId, $message) {
        if (!isset($this->clients[$clientId])) return;
        
        $frame = $this->encodeFrame(json_encode($message));
        @socket_write($this->clients[$clientId]['socket'], $frame);
    }
    
    private function sendPingToAll() {
        foreach($this->clients as $client) {
            if (!$client['handshake']) continue;
            $this->sendToClient($client['id'], ['action' => 'ping']);
        }
    }
    
    private function disconnectClient($clientId) {
        if (!isset($this->clients[$clientId])) return;
        $projet = $this->clients[$clientId]['projet'] ?? 'inconnu';
        echo "[" . date('H:i:s') . "] Déconnexion: $clientId (projet: $projet)\n";
        @socket_close($this->clients[$clientId]['socket']);
        unset($this->clients[$clientId]);
    }
    
    private function refreshLock() {
        try {
            $now = time();
            $ip = getNetworkIP();
            
            $stmt = $this->pdo->prepare("UPDATE config SET value = ?, timestamp = ? WHERE key = 'ws_server_lock'");
            $stmt->execute([$ip, $now]);
            
            $stmt = $this->pdo->prepare("UPDATE config SET timestamp = ? WHERE key = 'ws_server_ip'");
            $stmt->execute([$now]);
            
        } catch(Exception $e) {
            echo "[" . date('H:i:s') . "] Erreur refresh: {$e->getMessage()}\n";
        }
    }
    
    private function encodeFrame($payload, $opcode = 0x1) {
        $length = strlen($payload);
        $header = chr(0x80 | $opcode);
        
        if ($length <= 125) {
            $header .= chr($length);
        } elseif ($length <= 65535) {
            $header .= chr(126) . pack('n', $length);
        } else {
            $header .= chr(127) . pack('NN', 0, $length);
        }
        
        return $header . $payload;
    }
    
    private function decodeFrame($buffer) {
        if (strlen($buffer) < 2) return false;
        
        $byte1 = ord($buffer[0]);
        $byte2 = ord($buffer[1]);
        
        $opcode = $byte1 & 0x0F;
        $masked = ($byte2 & 0x80) === 0x80;
        $length = $byte2 & 0x7F;
        
        $offset = 2;
        
        if ($length === 126) {
            if (strlen($buffer) < 4) return false;
            $length = unpack('n', substr($buffer, 2, 2))[1];
            $offset = 4;
        } elseif ($length === 127) {
            if (strlen($buffer) < 10) return false;
            $unpacked = unpack('N2', substr($buffer, 2, 8));
            $length = $unpacked[2];
            $offset = 10;
        }
        
        if ($masked) {
            if (strlen($buffer) < $offset + 4) return false;
            $mask = substr($buffer, $offset, 4);
            $offset += 4;
        }
        
        if (strlen($buffer) < $offset + $length) return false;
        
        $payload = substr($buffer, $offset, $length);
        
        if ($masked) {
            $decoded = '';
            for ($i = 0; $i < strlen($payload); $i++) {
                $decoded .= $payload[$i] ^ $mask[$i % 4];
            }
            $payload = $decoded;
        }
        
        return ['opcode' => $opcode, 'payload' => $payload];
    }
}

$server = new WebSocketServer();
$server->run();