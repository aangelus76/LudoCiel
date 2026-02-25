<?php
/**
 * Configuration WebSocket pour LudoPresence
 * 
 * INSTRUCTIONS DE CONFIGURATION :
 * 1. Modifiez PHP_PATH avec le chemin vers php.exe de votre installation php-desktop
 * 2. Vérifiez que WS_PORT (8888) est libre sur tous les postes
 * 3. Le sous-réseau devrait être détecté automatiquement (10.212.51.x)
 */

// ==================== CONFIGURATION À MODIFIER ====================

/**
 * Chemin vers l'exécutable PHP de php-desktop
 * Exemples courants :
 * - Windows: 'D:\Dev\PHP\Socket\php\php.exe'
 * - Ou le chemin relatif si php-desktop est portable: __DIR__ . '\php\php.exe'
 */
define('PHP_PATH', 'S:\Ludotheque\Outils\MultiProjet\php\php.exe');

/**
 * Port du serveur WebSocket
 * Doit être libre et identique sur tous les postes
 */
define('WS_PORT', 8080);

// ==================== CONFIGURATION AVANCÉE ====================

/**
 * Délais et timeouts (en secondes)
 */
define('WS_PING_INTERVAL', 2);        // Intervalle entre les pings serveur
define('WS_CLIENT_TIMEOUT', 10);      // Timeout client si pas de ping reçu
define('WS_LOCK_TIMEOUT', 3);        // Durée max du verrou serveur
define('WS_LOCK_REFRESH', 5);         // Intervalle de rafraîchissement du verrou
define('WS_IP_VALIDITY', 10);         // Validité de l'IP enregistrée

/**
 * Configuration base de données
 */
define('DB_PATH', __DIR__ . '/../Presences.db');

/**
 * Détection automatique du subnet
 * Laissez null pour détection automatique, ou forcez si besoin
 */
define('FORCE_SUBNET', null); // Exemple: '10.212.51' pour forcer

// ==================== NE PAS MODIFIER CI-DESSOUS ====================

/**
 * Récupère l'adresse IP réseau du poste (non loopback)
 */
function getNetworkIP() {
    // Méthode 1: Via socket_create (extension sockets activée)
    $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if ($sock) {
        @socket_connect($sock, "8.8.8.8", 53);
        @socket_getsockname($sock, $ip);
        @socket_close($sock);
        
        if ($ip && $ip !== '127.0.0.1' && !preg_match('/^169\.254\./', $ip)) {
            if (FORCE_SUBNET !== null) {
                if (strpos($ip, FORCE_SUBNET) === 0) {
                    return $ip;
                }
            } else {
                return $ip;
            }
        }
    }
    
    // Méthode 2: Via ipconfig (Windows fallback)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec('ipconfig', $output);
        foreach ($output as $line) {
            if (preg_match('/IPv4.*:\s+(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
                $ip = $matches[1];
                if ($ip !== '127.0.0.1' && !preg_match('/^169\.254\./', $ip)) {
                    if (FORCE_SUBNET !== null) {
                        if (strpos($ip, FORCE_SUBNET) === 0) {
                            return $ip;
                        }
                    } else {
                        return $ip;
                    }
                }
            }
        }
    }
    
    // Méthode 3: Via hostname
    $host = gethostname();
    $ip = gethostbyname($host);
    if ($ip !== $host && $ip !== '127.0.0.1') {
        return $ip;
    }
    
    return '127.0.0.1';
}

/**
 * Retourne l'adresse WebSocket complète
 */
function getWebSocketAddress() {
    return 'ws://' . getNetworkIP() . ':' . WS_PORT;
}

/**
 * Vérifie que PHP_PATH existe
 */
function validateConfig() {
    if (!file_exists(PHP_PATH)) {
        throw new Exception(
            "Le chemin PHP_PATH n'existe pas : " . PHP_PATH . "\n" .
            "Veuillez modifier config.php avec le bon chemin vers php.exe"
        );
    }
    
    if (!file_exists(DB_PATH)) {
        throw new Exception(
            "Base de données introuvable : " . DB_PATH . "\n" .
            "Vérifiez que le fichier Presences.db existe"
        );
    }
}