<?php
/**
 * Handler pour la gestion des animations et inscriptions
 * FULL WEBSOCKET - INSERT/UPDATE/DELETE + BROADCAST
 */
class AnimationHandler {
    private $pdo;
    private $broadcastToAll;
    
    public function __construct($pdo, $callbackAll) {
        $this->pdo = $pdo;
        $this->broadcastToAll = $callbackAll;
    }
    
    /**
     * Point d'entrÃ©e principal - opÃ©rations BDD + broadcast
     */
    public function handle($action, $data, $senderId) {
        switch($action) {
            case 'animation_create':
                $this->handleAnimationCreate($data, $senderId);
                break;
            case 'animation_update':
                $this->handleAnimationUpdate($data, $senderId);
                break;
            case 'animation_delete':
                $this->handleAnimationDelete($data, $senderId);
                break;
            case 'inscription_add':
                $this->handleInscriptionAdd($data, $senderId);
                break;
            case 'inscription_update':
                $this->handleInscriptionUpdate($data, $senderId);
                break;
            case 'inscription_delete':
                $this->handleInscriptionDelete($data, $senderId);
                break;
            case 'inscription_validate':
                $this->handleInscriptionValidate($data, $senderId);
                break;
        }
    }
    
    // ==================== ANIMATIONS ====================
    
    private function handleAnimationCreate($data, $senderId) {
        // INSERT en BDD
        $stmt = $this->pdo->prepare("
            INSERT INTO animations (date, heure_debut, heure_fin, nom, type_id, nb_places, lieu, animateur_id, compter_stats)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['date'],
            $data['heure_debut'],
            $data['heure_fin'],
            $data['nom'],
            $data['type_id'] ?? null,
            $data['nb_places'] ?? null,
            $data['lieu'] ?? null,
            $data['animateur_id'] ?? null,
            isset($data['compter_stats']) ? $data['compter_stats'] : 0
        ]);
        
        $animationId = $this->pdo->lastInsertId();
        
        // RÃ©cupÃ©rer l'animation complÃ¨te avec compteurs
        $animation = $this->getAnimationById($animationId);
        
        // Broadcast
        ($this->broadcastToAll)([
            'action' => 'animation_created',
            'sender' => $senderId,
            'data' => $animation
        ]);
    }
    
    private function handleAnimationUpdate($data, $senderId) {
        // UPDATE en BDD
        $stmt = $this->pdo->prepare("
            UPDATE animations 
            SET date = ?, heure_debut = ?, heure_fin = ?, nom = ?, type_id = ?, 
                nb_places = ?, lieu = ?, animateur_id = ?, compter_stats = ?, presence_reelle = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['date'],
            $data['heure_debut'],
            $data['heure_fin'],
            $data['nom'],
            $data['type_id'] ?? null,
            $data['nb_places'] ?? null,
            $data['lieu'] ?? null,
            $data['animateur_id'] ?? null,
            isset($data['compter_stats']) ? $data['compter_stats'] : 0,
            isset($data['presence_reelle']) && $data['presence_reelle'] !== '' ? $data['presence_reelle'] : null,
            $data['id']
        ]);
        
        // RÃ©cupÃ©rer l'animation complÃ¨te avec compteurs
        $animation = $this->getAnimationById($data['id']);
        
        // Broadcast
        ($this->broadcastToAll)([
            'action' => 'animation_updated',
            'sender' => $senderId,
            'data' => $animation
        ]);
    }
    
    private function handleAnimationDelete($data, $senderId) {
        // DELETE en BDD (cascade sur inscriptions)
        $stmt = $this->pdo->prepare("DELETE FROM animations WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        // Broadcast
        ($this->broadcastToAll)([
            'action' => 'animation_deleted',
            'sender' => $senderId,
            'data' => ['id' => $data['id']]
        ]);
    }
    
    // ==================== INSCRIPTIONS ====================
    
    private function handleInscriptionAdd($data, $senderId) {
        $animationId = $data['animation_id'];
        $nb_personnes = $data['nb_personnes'] ?? 1;

        // Vérifier les places disponibles
        $anim = $this->pdo->prepare("
            SELECT nb_places,
                   (SELECT SUM(COALESCE(nb_personnes, 1)) FROM inscriptions WHERE animation_id = ? AND parent_id IS NULL AND statut = 'inscrit') as total_places_prises
            FROM animations WHERE id = ?
        ");
        $anim->execute([$animationId, $animationId]);
        $animData = $anim->fetch(PDO::FETCH_ASSOC);

        $statut = 'inscrit';
        if ($animData && $animData['nb_places']) {
            $placesRestantes = $animData['nb_places'] - ($animData['total_places_prises'] ?? 0);
            if ($placesRestantes <= 0) {
                $statut = 'attente';
            }
        }

        // INSERT en BDD
        $stmt = $this->pdo->prepare("
            INSERT INTO inscriptions (animation_id, identite, telephone, nb_personnes, statut, parent_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $animationId,
            $data['identite'],
            $data['telephone'] ?? null,
            $nb_personnes,
            $statut,
            $data['parent_id'] ?? null
        ]);
        
        $inscriptionId = $this->pdo->lastInsertId();

        // Créer les invités automatiquement si nb_personnes > 1 ET pas d'invité existant (parent_id null)
        if ($nb_personnes > 1 && ($data['parent_id'] ?? null) === null) {
            // Recalculer les places restantes après l'insertion du parent
            $placesRestantes = isset($placesRestantes) ? $placesRestantes - 1 : null;
            for ($i = 1; $i < $nb_personnes; $i++) {
                $statutInvite = 'inscrit';
                if ($placesRestantes !== null) {
                    if ($placesRestantes <= 0) {
                        $statutInvite = 'attente';
                    } else {
                        $placesRestantes--;
                    }
                }
                $this->pdo->prepare("
                    INSERT INTO inscriptions (animation_id, identite, telephone, nb_personnes, statut, parent_id)
                    VALUES (?, ?, NULL, NULL, ?, ?)
                ")->execute([
                    $animationId,
                    $data['identite'],
                    $statutInvite,
                    $inscriptionId
                ]);
            }
        }
        
        // RÃ©cupÃ©rer les compteurs mis Ã  jour
        $compteurs = $this->getAnimationCompteurs($data['animation_id']);
        
        // Broadcast
        ($this->broadcastToAll)([
            'action' => 'inscription_added',
            'sender' => $senderId,
            'data' => [
                'animation_id' => $data['animation_id'],
                'id' => $inscriptionId,
                'compteurs' => $compteurs
            ]
        ]);
    }
    
    private function handleInscriptionUpdate($data, $senderId) {
        // RÃ©cupÃ©rer l'inscription actuelle
        $stmt = $this->pdo->prepare("SELECT * FROM inscriptions WHERE id = ?");
        $stmt->execute([$data['id']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) return;
        
        $animationId = $current['animation_id'];
        
        // UPDATE identitÃ© si fournie
        if (isset($data['identite'])) {
            $this->pdo->prepare("UPDATE inscriptions SET identite = ? WHERE id = ?")
                ->execute([$data['identite'], $data['id']]);
        }
        
        // UPDATE nb_personnes (gÃ©rer les invitÃ©s/accompagnants)
        if (isset($data['nb_personnes']) && $current['parent_id'] === null) {
            $old_nb = $current['nb_personnes'];
            $new_nb = $data['nb_personnes'];
            
            if ($new_nb > $old_nb) {
                // RÃ©cupÃ©rer l'animation pour vÃ©rifier les places
                $animation = $this->getAnimationById($animationId);
                $placesRestantes = null;
                if ($animation['nb_places']) {
                    $placesRestantes = $animation['nb_places'] - ($animation['total_places_prises'] ?? 0);
                }
                
                // Ajouter des invitÃ©s
                for ($i = $old_nb; $i < $new_nb; $i++) {
                    // DÃ©terminer le statut : 'inscrit' si places dispo, sinon 'attente'
                    $statut = 'attente';
                    if ($placesRestantes === null || $placesRestantes > 0) {
                        $statut = 'inscrit';
                        if ($placesRestantes !== null) {
                            $placesRestantes--;
                        }
                    }
                    
                    $this->pdo->prepare("
                        INSERT INTO inscriptions (animation_id, identite, telephone, nb_personnes, statut, parent_id)
                        VALUES (?, ?, NULL, NULL, ?, ?)
                    ")->execute([$animationId, $current['identite'], $statut, $data['id']]);
                }
            } else if ($new_nb < $old_nb) {
                // Supprimer des invitÃ©s
                $this->pdo->prepare("
                    DELETE FROM inscriptions 
                    WHERE parent_id = ? 
                    ORDER BY id DESC 
                    LIMIT ?
                ")->execute([$data['id'], $old_nb - $new_nb]);
            }
            
            $this->pdo->prepare("UPDATE inscriptions SET nb_personnes = ? WHERE id = ?")
                ->execute([$new_nb, $data['id']]);
        }
        
        // RÃ©cupÃ©rer les compteurs mis Ã  jour
        $compteurs = $this->getAnimationCompteurs($animationId);
        
        // Broadcast
        ($this->broadcastToAll)([
            'action' => 'inscription_updated',
            'sender' => $senderId,
            'data' => [
                'animation_id' => $animationId,
                'id' => $data['id'],
                'compteurs' => $compteurs
            ]
        ]);
    }
    
    private function handleInscriptionDelete($data, $senderId) {
        // RÃ©cupÃ©rer l'animation_id avant suppression
        $stmt = $this->pdo->prepare("SELECT animation_id FROM inscriptions WHERE id = ?");
        $stmt->execute([$data['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) return;
        
        $animationId = $row['animation_id'];
        
        // DELETE en BDD (cascade sur enfants via parent_id)
        $stmt = $this->pdo->prepare("DELETE FROM inscriptions WHERE id = ? OR parent_id = ?");
        $stmt->execute([$data['id'], $data['id']]);
        
        // RÃ©cupÃ©rer les compteurs mis Ã  jour
        $compteurs = $this->getAnimationCompteurs($animationId);
        
        // Broadcast
        ($this->broadcastToAll)([
            'action' => 'inscription_deleted',
            'sender' => $senderId,
            'data' => [
                'animation_id' => $animationId,
                'id' => $data['id'],
                'compteurs' => $compteurs
            ]
        ]);
    }
    
    private function handleInscriptionValidate($data, $senderId) {
        // RÃ©cupÃ©rer l'animation_id
        $stmt = $this->pdo->prepare("SELECT animation_id FROM inscriptions WHERE id = ?");
        $stmt->execute([$data['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) return;
        
        $animationId = $row['animation_id'];
        
        // UPDATE statut
        $this->pdo->prepare("UPDATE inscriptions SET statut = 'inscrit' WHERE id = ?")
            ->execute([$data['id']]);
        
        // RÃ©cupÃ©rer les compteurs mis Ã  jour
        $compteurs = $this->getAnimationCompteurs($animationId);
        
        // Broadcast
        ($this->broadcastToAll)([
            'action' => 'inscription_validated',
            'sender' => $senderId,
            'data' => [
                'animation_id' => $animationId,
                'id' => $data['id'],
                'compteurs' => $compteurs
            ]
        ]);
    }
    
    // ==================== UTILITAIRES ====================
    
    /**
     * RÃ©cupÃ¨re une animation complÃ¨te avec compteurs
     */
    private function getAnimationById($id) {
        $stmt = $this->pdo->prepare("
            SELECT a.*, at.nom as type_nom, an.nom as animateur_nom,
                (SELECT COUNT(*) FROM inscriptions WHERE animation_id = a.id AND statut = 'inscrit') as total_inscrits,
                (SELECT SUM(COALESCE(nb_personnes, 1)) FROM inscriptions WHERE animation_id = a.id AND parent_id IS NULL AND statut = 'inscrit') as total_places_prises,
                (SELECT COUNT(*) FROM inscriptions WHERE animation_id = a.id AND statut = 'attente') as total_liste_attente
            FROM animations a
            LEFT JOIN animation_types at ON a.type_id = at.id
            LEFT JOIN animateurs an ON a.animateur_id = an.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * RÃ©cupÃ¨re uniquement les compteurs d'une animation
     */
    private function getAnimationCompteurs($animationId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM inscriptions WHERE animation_id = ? AND statut = 'inscrit') as total_inscrits,
                (SELECT SUM(COALESCE(nb_personnes, 1)) FROM inscriptions WHERE animation_id = ? AND parent_id IS NULL AND statut = 'inscrit') as total_places_prises,
                (SELECT COUNT(*) FROM inscriptions WHERE animation_id = ? AND statut = 'attente') as total_liste_attente
        ");
        $stmt->execute([$animationId, $animationId, $animationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
