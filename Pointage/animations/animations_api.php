<?php
header('Content-Type: application/json; charset=utf-8');

class AnimationsAPI {
    private $pdo;

    public function __construct() {
		try {
			$this->pdo = new PDO('sqlite:../Presences.db');
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->pdo->exec("PRAGMA foreign_keys = ON");
			$this->initDatabase();
		} catch(PDOException $e) {
			die(json_encode(array('error' => 'Erreur connexion BDD: ' . $e->getMessage())));
		}
	}

    private function initDatabase() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS animation_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT NOT NULL UNIQUE,
                actif INTEGER DEFAULT 1
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS animateurs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT NOT NULL UNIQUE,
                actif INTEGER DEFAULT 1
            )
        ");

        try {
            $this->pdo->exec("ALTER TABLE animation_types ADD COLUMN actif INTEGER DEFAULT 1");
        } catch(PDOException $e) {}

        try {
            $this->pdo->exec("ALTER TABLE animateurs ADD COLUMN actif INTEGER DEFAULT 1");
        } catch(PDOException $e) {}

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS animations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date DATE NOT NULL,
                heure_debut TIME NOT NULL,
                heure_fin TIME NOT NULL,
                nom TEXT NOT NULL,
                type_id INTEGER,
                nb_places INTEGER,
                lieu TEXT,
                animateur_id INTEGER,
                compter_stats BOOLEAN DEFAULT 0,
                presence_reelle INTEGER NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (type_id) REFERENCES animation_types(id),
                FOREIGN KEY (animateur_id) REFERENCES animateurs(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS inscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                animation_id INTEGER NOT NULL,
                identite TEXT NOT NULL,
                telephone TEXT,
                nb_personnes INTEGER,
                statut TEXT DEFAULT 'inscrit',
                parent_id INTEGER,
                date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (animation_id) REFERENCES animations(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES inscriptions(id) ON DELETE CASCADE
            )
        ");

        try {
            $this->pdo->exec("ALTER TABLE inscriptions ADD COLUMN parent_id INTEGER");
        } catch(PDOException $e) {}

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS emails (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM animation_types");
        if ($stmt->fetchColumn() == 0) {
            $this->pdo->exec("INSERT INTO animation_types (nom) VALUES ('SoirÃ©e'),('JDR'),('Proto'),('3D')");
        }

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM animateurs");
        if ($stmt->fetchColumn() == 0) {
            $this->pdo->exec("INSERT INTO animateurs (nom) VALUES ('GrÃ©gory'),('Marine'),('Guillaume'),('Anthony'),('Equipe'),('Animateur')");
        }
    }

    public function handleRequest() {
        $action = $_GET['action'] ?? '';

        try {
            switch ($action) {
                case 'getAnimations': $this->getAnimations(); break;
                case 'getAnimation': $this->getAnimation(); break;
                case 'createAnimation': $this->createAnimation(); break;
                case 'updateAnimation': $this->updateAnimation(); break;
                case 'deleteAnimation': $this->deleteAnimation(); break;
                case 'getTypes': $this->getTypes(); break;
                case 'addType': $this->addType(); break;
                case 'updateType': $this->updateType(); break;
                case 'toggleType': $this->toggleType(); break;
                case 'getAnimateurs': $this->getAnimateurs(); break;
                case 'addAnimateur': $this->addAnimateur(); break;
                case 'updateAnimateur': $this->updateAnimateur(); break;
                case 'toggleAnimateur': $this->toggleAnimateur(); break;
                case 'getInscriptions': $this->getInscriptions(); break;
                case 'addInscription': $this->addInscription(); break;
                case 'deleteInscription': $this->deleteInscription(); break;
                case 'updateInscription': $this->updateInscription(); break;
                case 'validerInscription': $this->validerInscription(); break;
                case 'getAnimationWithInscriptions': $this->getAnimationWithInscriptions(); break;
                case 'getEmails': $this->getEmails(); break;
                case 'addEmail': $this->addEmail(); break;
                case 'updateEmail': $this->updateEmail(); break;
                case 'deleteEmail': $this->deleteEmail(); break;
                default: throw new Exception('Action invalide');
            }
        } catch(Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

	private function getAnimations() {
		$month = $_GET['month'] ?? null;
		$year = $_GET['year'] ?? null;
		$periodMode = $_GET['periodMode'] ?? null;

		$query = "SELECT a.*, at.nom as type_nom, an.nom as animateur_nom,
					  (SELECT COUNT(*) FROM inscriptions WHERE animation_id = a.id AND statut = 'inscrit') as total_inscrits,
					  (SELECT SUM(COALESCE(nb_personnes, 1)) FROM inscriptions WHERE animation_id = a.id AND parent_id IS NULL AND statut = 'inscrit') as total_places_prises,
					  (SELECT COUNT(*) FROM inscriptions WHERE animation_id = a.id AND statut = 'attente') as total_liste_attente
					  FROM animations a
					  LEFT JOIN animation_types at ON a.type_id = at.id
					  LEFT JOIN animateurs an ON a.animateur_id = an.id";

		if ($periodMode == 1) {
			$startMonth = $_GET['startMonth'] ?? null;
			$startYear = $_GET['startYear'] ?? null;
			$endMonth = $_GET['endMonth'] ?? null;
			$endYear = $_GET['endYear'] ?? null;

			if ($startMonth && $startYear && $endMonth && $endYear) {
				$startDate = sprintf('%02d-%02d-%04d', 1, $startMonth, $startYear);
				$endDate = sprintf('%02d-%02d-%04d', 31, $endMonth, $endYear);

				$query .= " WHERE substr(a.date, 7, 4) || substr(a.date, 4, 2) || substr(a.date, 1, 2) 
							BETWEEN substr(?, 7, 4) || substr(?, 4, 2) || substr(?, 1, 2) 
							AND substr(?, 7, 4) || substr(?, 4, 2) || substr(?, 1, 2) 
							ORDER BY substr(a.date, 7, 4) || substr(a.date, 4, 2) || substr(a.date, 1, 2) DESC, a.heure_debut DESC";
				$stmt = $this->pdo->prepare($query);
				$stmt->execute([$startDate, $startDate, $startDate, $endDate, $endDate, $endDate]);
			} else {
				$stmt = $this->pdo->query($query . " ORDER BY substr(a.date, 7, 4) || substr(a.date, 4, 2) || substr(a.date, 1, 2) DESC, a.heure_debut DESC");
			}
		} else if ($month !== null && $year !== null) {
			$query .= " WHERE substr(a.date, 4, 2) = ? AND substr(a.date, 7, 4) = ? ORDER BY substr(a.date, 7, 4) || substr(a.date, 4, 2) || substr(a.date, 1, 2) DESC, a.heure_debut DESC";
			$stmt = $this->pdo->prepare($query);
			$stmt->execute([sprintf('%02d', $month), $year]);
		} else {
			$stmt = $this->pdo->query($query . " ORDER BY substr(a.date, 7, 4) || substr(a.date, 4, 2) || substr(a.date, 1, 2) DESC, a.heure_debut DESC");
		}

		echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
	}

    private function getAnimation() {
        if (!isset($_GET['id'])) throw new Exception('ID manquant');
        
        $stmt = $this->pdo->prepare("
            SELECT a.*, at.nom as type_nom, an.nom as animateur_nom
            FROM animations a
            LEFT JOIN animation_types at ON a.type_id = at.id
            LEFT JOIN animateurs an ON a.animateur_id = an.id
            WHERE a.id = ?
        ");
        $stmt->execute([$_GET['id']]);
        
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    }

    private function getAnimationWithInscriptions() {
        if (!isset($_GET['id'])) throw new Exception('ID manquant');
        
        $stmt = $this->pdo->prepare("
            SELECT a.*, at.nom as type_nom, an.nom as animateur_nom
            FROM animations a
            LEFT JOIN animation_types at ON a.type_id = at.id
            LEFT JOIN animateurs an ON a.animateur_id = an.id
            WHERE a.id = ?
        ");
        $stmt->execute([$_GET['id']]);
        $animation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $this->pdo->prepare("SELECT * FROM inscriptions WHERE animation_id = ? ORDER BY COALESCE(parent_id, id), parent_id IS NULL DESC, id");
        $stmt->execute([$_GET['id']]);
        $inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'animation' => $animation,
            'inscriptions' => $inscriptions
        ]);
    }

    private function createAnimation() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $dates = $data['dates'] ?? [];
        if (empty($dates)) throw new Exception('Au moins une date requise');
        
        foreach ($dates as $date) {
            $stmt = $this->pdo->prepare("INSERT INTO animations (date, heure_debut, heure_fin, nom, type_id, nb_places, lieu, animateur_id, compter_stats) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $date,
                $data['heure_debut'],
                $data['heure_fin'],
                $data['nom'],
                $data['type_id'] ?: null,
                $data['nb_places'] ?: null,
                $data['lieu'] ?: null,
                $data['animateur_id'] ?: null,
                $data['compter_stats'] ? 1 : 0
            ]);
        }
        
        echo json_encode(['success' => true]);
    }

    private function updateAnimation() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'])) throw new Exception('ID manquant');
        
        $stmt = $this->pdo->prepare("UPDATE animations SET date = ?, heure_debut = ?, heure_fin = ?, nom = ?, type_id = ?, nb_places = ?, lieu = ?, animateur_id = ?, compter_stats = ?, presence_reelle = ? WHERE id = ?");
        $stmt->execute([
            $data['date'],
            $data['heure_debut'],
            $data['heure_fin'],
            $data['nom'],
            $data['type_id'] ?: null,
            $data['nb_places'] ?: null,
            $data['lieu'] ?: null,
            $data['animateur_id'] ?: null,
            $data['compter_stats'] ? 1 : 0,
            isset($data['presence_reelle']) && $data['presence_reelle'] !== '' ? $data['presence_reelle'] : null,
            $data['id']
        ]);
        
        echo json_encode(['success' => true]);
    }

    private function deleteAnimation() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'])) throw new Exception('ID manquant');
        
        $stmt = $this->pdo->prepare("DELETE FROM animations WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        echo json_encode(['success' => true]);
    }

    private function getTypes() {
        $stmt = $this->pdo->query("SELECT * FROM animation_types ORDER BY nom");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function addType() {
        $data = json_decode(file_get_contents('php://input'), true);
        $nom = trim($data['nom'] ?? '');
        if (empty($nom)) throw new Exception('Nom requis');
        
        $stmt = $this->pdo->prepare("INSERT INTO animation_types (nom) VALUES (?)");
        $stmt->execute([$nom]);
        
        echo json_encode(['success' => true, 'id' => $this->pdo->lastInsertId()]);
    }

    private function updateType() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id']) || empty($data['nom'])) throw new Exception('ID et nom requis');
        
        $stmt = $this->pdo->prepare("UPDATE animation_types SET nom = ? WHERE id = ?");
        $stmt->execute([$data['nom'], $data['id']]);
        
        echo json_encode(['success' => true]);
    }

    private function toggleType() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'])) throw new Exception('ID manquant');
        
        $stmt = $this->pdo->prepare("UPDATE animation_types SET actif = 1 - actif WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        echo json_encode(['success' => true]);
    }

    private function getAnimateurs() {
        $stmt = $this->pdo->query("SELECT * FROM animateurs ORDER BY nom");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function addAnimateur() {
        $data = json_decode(file_get_contents('php://input'), true);
        $nom = trim($data['nom'] ?? '');
        if (empty($nom)) throw new Exception('Nom requis');
        
        $stmt = $this->pdo->prepare("INSERT INTO animateurs (nom) VALUES (?)");
        $stmt->execute([$nom]);
        
        echo json_encode(['success' => true, 'id' => $this->pdo->lastInsertId()]);
    }

    private function updateAnimateur() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id']) || empty($data['nom'])) throw new Exception('ID et nom requis');
        
        $stmt = $this->pdo->prepare("UPDATE animateurs SET nom = ? WHERE id = ?");
        $stmt->execute([$data['nom'], $data['id']]);
        
        echo json_encode(['success' => true]);
    }

    private function toggleAnimateur() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'])) throw new Exception('ID manquant');
        
        $stmt = $this->pdo->prepare("UPDATE animateurs SET actif = 1 - actif WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        echo json_encode(['success' => true]);
    }

    private function getInscriptions() {
        if (!isset($_GET['animation_id'])) throw new Exception('ID animation manquant');
        
        $stmt = $this->pdo->prepare("SELECT * FROM inscriptions WHERE animation_id = ? ORDER BY date_inscription");
        $stmt->execute([$_GET['animation_id']]);
        
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function addInscription() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $this->pdo->prepare("INSERT INTO inscriptions (animation_id, identite, telephone, nb_personnes, statut) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['animation_id'],
            $data['identite'],
            $data['telephone'] ?? null,
            $data['nb_personnes'] ?? 1,
            $data['statut'] ?? 'inscrit'
        ]);
        
        $parentId = $this->pdo->lastInsertId();
        $nb_personnes = $data['nb_personnes'] ?? 1;
        
        // CrÃ©er les invitÃ©s automatiquement si nb_personnes > 1
        if ($nb_personnes > 1) {
            for ($i = 1; $i < $nb_personnes; $i++) {
                $this->pdo->prepare("INSERT INTO inscriptions (animation_id, identite, telephone, nb_personnes, statut, parent_id) VALUES (?, ?, NULL, NULL, ?, ?)")
                    ->execute([
                        $data['animation_id'],
                        $data['identite'],
                        $data['statut'] ?? 'inscrit',
                        $parentId
                    ]);
            }
        }
        
        echo json_encode(['success' => true, 'id' => $parentId]);
    }

    private function deleteInscription() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'])) throw new Exception('ID manquant');
        
        $stmt = $this->pdo->prepare("DELETE FROM inscriptions WHERE id = ? OR parent_id = ?");
        $stmt->execute([$data['id'], $data['id']]);
        
        echo json_encode(['success' => true]);
    }

    private function updateInscription() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'])) throw new Exception('ID manquant');
        
        $stmt = $this->pdo->prepare("SELECT * FROM inscriptions WHERE id = ?");
        $stmt->execute([$data['id']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (isset($data['identite'])) {
            $this->pdo->prepare("UPDATE inscriptions SET identite = ? WHERE id = ?")->execute([$data['identite'], $data['id']]);
        }
        
        if (isset($data['nb_personnes']) && $current['parent_id'] === null) {
            $old_nb = $current['nb_personnes'];
            $new_nb = $data['nb_personnes'];
            if ($new_nb > $old_nb) {
                for ($i = $old_nb; $i < $new_nb; $i++) {
                    $this->pdo->prepare("INSERT INTO inscriptions (animation_id, identite, telephone, nb_personnes, statut, parent_id) VALUES (?, ?, NULL, NULL, 'attente', ?)")->execute([$current['animation_id'], $current['identite'], $data['id']]);
                }
            } else if ($new_nb < $old_nb) {
                $this->pdo->prepare("DELETE FROM inscriptions WHERE parent_id = ? ORDER BY id DESC LIMIT ?")->execute([$data['id'], $old_nb - $new_nb]);
            }
            $this->pdo->prepare("UPDATE inscriptions SET nb_personnes = ? WHERE id = ?")->execute([$new_nb, $data['id']]);
        }
        echo json_encode(['success' => true]);
    }

    private function validerInscription() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'])) throw new Exception('ID manquant');
        $this->pdo->prepare("UPDATE inscriptions SET statut = 'inscrit' WHERE id = ?")->execute([$data['id']]);
        echo json_encode(['success' => true]);
    }

    // === EMAILS ===
    private function getEmails() {
        $stmt = $this->pdo->query("SELECT * FROM emails ORDER BY email");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function addEmail() {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) throw new Exception('Email requis');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email invalide');
        
        try {
            $stmt = $this->pdo->prepare("INSERT INTO emails (email) VALUES (?)");
            $stmt->execute([$email]);
            echo json_encode(['success' => true, 'id' => $this->pdo->lastInsertId()]);
        } catch(PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                throw new Exception('Cet email existe déjà');
            }
            throw $e;
        }
    }

    private function updateEmail() {
        $id = $_POST['id'] ?? null;
        $email = trim($_POST['email'] ?? '');
        if (!$id || empty($email)) throw new Exception('ID et email requis');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email invalide');
        
        $stmt = $this->pdo->prepare("UPDATE emails SET email = ? WHERE id = ?");
        $stmt->execute([$email, $id]);
        echo json_encode(['success' => true]);
    }

    private function deleteEmail() {
        $id = $_POST['id'] ?? null;
        if (!$id) throw new Exception('ID requis');
        
        $stmt = $this->pdo->prepare("DELETE FROM emails WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    }
}

$api = new AnimationsAPI();
$api->handleRequest();
