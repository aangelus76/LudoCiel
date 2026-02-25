<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO('sqlite:../Presences.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer tables si n'existent pas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS periodes_vacances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nom TEXT NOT NULL,
            date_debut DATE NOT NULL,
            date_fin DATE NOT NULL,
            annee INTEGER NOT NULL
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fermetures (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            motif TEXT NOT NULL,
            date_debut DATE NOT NULL,
            date_fin DATE NOT NULL,
            annee INTEGER NOT NULL
        )
    ");
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        // ===== VACANCES =====
        case 'getAll':
            $annee = $_GET['annee'] ?? null;
            if ($annee) {
                $stmt = $pdo->prepare("SELECT * FROM periodes_vacances WHERE annee = ? ORDER BY date_debut");
                $stmt->execute([$annee]);
            } else {
                $stmt = $pdo->query("SELECT * FROM periodes_vacances ORDER BY annee DESC, date_debut");
            }
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'add':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data['nom'] || !$data['date_debut'] || !$data['date_fin'] || !$data['annee']) {
                throw new Exception('Données manquantes');
            }
            $stmt = $pdo->prepare("INSERT INTO periodes_vacances (nom, date_debut, date_fin, annee) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['nom'], $data['date_debut'], $data['date_fin'], $data['annee']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;
            
        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data['id']) {
                throw new Exception('ID manquant');
            }
            $stmt = $pdo->prepare("UPDATE periodes_vacances SET nom = ?, date_debut = ?, date_fin = ?, annee = ? WHERE id = ?");
            $stmt->execute([$data['nom'], $data['date_debut'], $data['date_fin'], $data['annee'], $data['id']]);
            echo json_encode(['success' => true]);
            break;
            
        case 'delete':
            if (!isset($_GET['id'])) {
                throw new Exception('ID manquant');
            }
            $stmt = $pdo->prepare("DELETE FROM periodes_vacances WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(['success' => true]);
            break;
        
        // ===== FERMETURES =====
        case 'getFermetures':
            $annee = $_GET['annee'] ?? null;
            if ($annee) {
                $stmt = $pdo->prepare("SELECT * FROM fermetures WHERE annee = ? ORDER BY date_debut");
                $stmt->execute([$annee]);
            } else {
                $stmt = $pdo->query("SELECT * FROM fermetures ORDER BY annee DESC, date_debut");
            }
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'addFermeture':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data['motif'] || !$data['date_debut'] || !$data['date_fin'] || !$data['annee']) {
                throw new Exception('Données manquantes');
            }
            $stmt = $pdo->prepare("INSERT INTO fermetures (motif, date_debut, date_fin, annee) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['motif'], $data['date_debut'], $data['date_fin'], $data['annee']]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;
            
        case 'updateFermeture':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data['id']) {
                throw new Exception('ID manquant');
            }
            $stmt = $pdo->prepare("UPDATE fermetures SET motif = ?, date_debut = ?, date_fin = ?, annee = ? WHERE id = ?");
            $stmt->execute([$data['motif'], $data['date_debut'], $data['date_fin'], $data['annee'], $data['id']]);
            echo json_encode(['success' => true]);
            break;
            
        case 'deleteFermeture':
            if (!isset($_GET['id'])) {
                throw new Exception('ID manquant');
            }
            $stmt = $pdo->prepare("DELETE FROM fermetures WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(['success' => true]);
            break;
            
        default:
            throw new Exception('Action invalide');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}