<?php
/**
 * Handler pour la gestion du pointage (individuals, partners, groups)
 * Isolé du serveur WebSocket principal pour faciliter maintenance
 */
class PointageHandler {
    private $pdo;
    private $colors = ['#EFEFFE', '#F7EFDF', '#EFFBBD', '#E1FBDE', '#FBEFED', '#E9ECE8', '#E3EDFE', '#FDF9D9', '#EFFEED', '#F8F7E7', '#E8F6F6', '#F8EFF6', '#D9EEF0', '#EBE7D5', '#E4FEF3'];
    
    // Callbacks broadcast
    private $broadcastToDate;
    private $broadcastToProjet;
    private $broadcastToAll;
    
    public function __construct($pdo, $callbackDate, $callbackProjet, $callbackAll) {
        $this->pdo = $pdo;
        $this->broadcastToDate = $callbackDate;
        $this->broadcastToProjet = $callbackProjet;
        $this->broadcastToAll = $callbackAll;
    }
    
    /**
     * Point d'entrée principal - dispatch vers la bonne méthode
     */
    public function handle($action, $data, $senderId, $date) {
        switch($action) {
            case 'individual_add':
                $this->handleIndividualAdd($data, $senderId, $date);
                break;
            case 'individual_update_time':
                $this->handleIndividualTimeUpdate($data, $senderId, $date);
                break;
            case 'individual_update_left':
                $this->handleIndividualLeftUpdate($data, $senderId, $date);
                break;
            case 'individual_delete':
                $this->handleIndividualDelete($data, $senderId, $date);
                break;
            case 'individual_who':
                $this->handleIndividualWho($data, $senderId, $date);
                break;
            case 'individual_update_type':
                $this->handleIndividualTypeUpdate($data, $senderId, $date);
                break;
            case 'partner_add':
                $this->handlePartnerAdd($data, $senderId, $date);
                break;
            case 'partner_update':
                $this->handlePartnerUpdate($data, $senderId, $date);
                break;
            case 'partner_update_left':
                $this->handlePartnerUpdateLeft($data, $senderId, $date);
                break;
            case 'partner_delete':
                $this->handlePartnerDelete($data, $senderId, $date);
                break;
            case 'group_create':
                $this->handleGroupCreate($data, $senderId, $date);
                break;
            case 'group_assign':
                $this->handleGroupAssign($data, $senderId, $date);
                break;
            case 'group_comment':
                $this->handleGroupComment($data, $senderId, $date);
                break;
        }
    }
    
    // ==================== INDIVIDUALS ====================
    
    private function handleIndividualAdd($data, $senderId, $date) {
        $tempId = uniqid('temp_', true);
        
        // Broadcast optimistic UI (temp_id)
        ($this->broadcastToDate)($date, [
            'action' => 'individual_adding',
            'sender' => $senderId,
            'date' => $date,
            'data' => array_merge($data, ['id' => $tempId, 'has_left' => 0, 'whoIs' => null])
        ]);
        
        // Insert BDD
        $stmt = $this->pdo->prepare("
            INSERT INTO individuals (type, arrival_time, duration, has_left)
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([$data['type'], $data['arrival_time'], $data['duration']]);
        $realId = $this->pdo->lastInsertId();
        
        // Broadcast confirmation (real_id)
        ($this->broadcastToDate)($date, [
            'action' => 'individual_added',
            'sender' => $senderId,
            'date' => $date,
            'data' => [
                'temp_id' => $tempId,
                'real_id' => $realId,
                'type' => $data['type'],
                'arrival_time' => $data['arrival_time'],
                'duration' => $data['duration'],
                'has_left' => 0
            ]
        ]);
    }
    
    private function handleIndividualTimeUpdate($data, $senderId, $date) {
        $stmt = $this->pdo->prepare("UPDATE individuals SET duration = ? WHERE id = ?");
        $stmt->execute([$data['duration'], $data['id']]);
        
        ($this->broadcastToDate)($date, [
            'action' => 'individual_time_updated',
            'sender' => $senderId,
            'date' => $date,
            'data' => ['id' => $data['id'], 'duration' => $data['duration']]
        ]);
    }
    
    private function handleIndividualLeftUpdate($data, $senderId, $date) {
        $stmt = $this->pdo->prepare("UPDATE individuals SET has_left = ? WHERE id = ?");
        $stmt->execute([$data['has_left'], $data['id']]);
        
        ($this->broadcastToDate)($date, [
            'action' => 'individual_left_updated',
            'sender' => $senderId,
            'date' => $date,
            'data' => ['id' => $data['id'], 'has_left' => $data['has_left']]
        ]);
    }
    
    private function handleIndividualDelete($data, $senderId, $date) {
        $stmt = $this->pdo->prepare("DELETE FROM individuals WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        ($this->broadcastToDate)($date, [
            'action' => 'individual_deleted',
            'sender' => $senderId,
            'date' => $date,
            'data' => ['id' => $data['id']]
        ]);
    }
    
    private function handleIndividualWho($data, $senderId, $date) {
        $stmt = $this->pdo->prepare("UPDATE individuals SET whoIs = ? WHERE id = ?");
        $stmt->execute([$data['whoIs'], $data['id']]);
        
        ($this->broadcastToDate)($date, [
            'action' => 'individual_who_updated',
            'sender' => $senderId,
            'date' => $date,
            'data' => ['id' => $data['id'], 'whoIs' => $data['whoIs']]
        ]);
    }
    
    private function handleIndividualTypeUpdate($data, $senderId, $date) {
        $stmt = $this->pdo->prepare("UPDATE individuals SET type = ? WHERE id = ?");
        $stmt->execute([$data['type'], $data['id']]);
        
        ($this->broadcastToDate)($date, [
            'action' => 'individual_type_updated',
            'sender' => $senderId,
            'date' => $date,
            'data' => ['id' => $data['id'], 'type' => $data['type']]
        ]);
    }
    
    // ==================== PARTNERS ====================
    
    private function handlePartnerAdd($data, $senderId, $date) {
        $tempId = uniqid('temp_partner_', true);
        
        $parts = explode(':', $data['input_duration']);
        $hours = intval($parts[0]);
        $minutes = intval($parts[1]);
        $size = intval($data['size']);
        $totalMinutes = ($hours * 60 + $minutes) * $size;
        $totalHours = floor($totalMinutes / 60);
        $totalMins = $totalMinutes % 60;
        $totalDuration = sprintf('%02d:%02d', $totalHours, $totalMins);
        
        $createdAt = $date . ' ' . date('H:i:s');
        
        // Broadcast optimistic UI
        ($this->broadcastToDate)($date, [
            'action' => 'partner_adding',
            'sender' => $senderId,
            'date' => $date,
            'data' => array_merge($data, [
                'id' => $tempId,
                'total_duration' => $totalDuration,
                'has_left' => 0,
                'created_at' => $createdAt
            ])
        ]);
        
        // Insert BDD
        $stmt = $this->pdo->prepare("
            INSERT INTO partners (name, size, input_duration, total_duration, has_left, created_at)
            VALUES (?, ?, ?, ?, 0, ?)
        ");
        $stmt->execute([$data['name'], $data['size'], $data['input_duration'], $totalDuration, $createdAt]);
        $realId = $this->pdo->lastInsertId();
        
        // Broadcast confirmation
        ($this->broadcastToDate)($date, [
            'action' => 'partner_added',
            'sender' => $senderId,
            'date' => $date,
            'data' => [
                'temp_id' => $tempId,
                'real_id' => $realId,
                'name' => $data['name'],
                'size' => $data['size'],
                'input_duration' => $data['input_duration'],
                'total_duration' => $totalDuration,
                'has_left' => 0,
                'created_at' => $createdAt
            ]
        ]);
    }
    
    private function handlePartnerUpdate($data, $senderId, $date) {
        $parts = explode(':', $data['input_duration']);
        $hours = intval($parts[0]);
        $minutes = intval($parts[1]);
        $size = intval($data['size']);
        $totalMinutes = ($hours * 60 + $minutes) * $size;
        $totalHours = floor($totalMinutes / 60);
        $totalMins = $totalMinutes % 60;
        $totalDuration = sprintf('%02d:%02d', $totalHours, $totalMins);
        
        $stmt = $this->pdo->prepare("
            UPDATE partners
            SET name = ?, size = ?, input_duration = ?, total_duration = ?
            WHERE id = ?
        ");
        $stmt->execute([$data['name'], $data['size'], $data['input_duration'], $totalDuration, $data['id']]);
        
        ($this->broadcastToDate)($date, [
            'action' => 'partner_updated',
            'sender' => $senderId,
            'date' => $date,
            'data' => array_merge($data, ['total_duration' => $totalDuration])
        ]);
    }
    
    private function handlePartnerUpdateLeft($data, $senderId, $date) {
        $stmt = $this->pdo->prepare("UPDATE partners SET has_left = ? WHERE id = ?");
        $stmt->execute([$data['has_left'], $data['id']]);
        
        ($this->broadcastToDate)($date, [
            'action' => 'partner_left_updated',
            'sender' => $senderId,
            'date' => $date,
            'data' => $data
        ]);
    }
    
    private function handlePartnerDelete($data, $senderId, $date) {
        $stmt = $this->pdo->prepare("DELETE FROM partners WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        ($this->broadcastToDate)($date, [
            'action' => 'partner_deleted',
            'sender' => $senderId,
            'date' => $date,
            'data' => ['id' => $data['id']]
        ]);
    }
    
    // ==================== GROUPS ====================
    
    private function handleGroupCreate($data, $senderId, $date) {
        $groupId = $this->generateUniqueGroupId();
        
        ($this->broadcastToDate)($date, [
            'action' => 'group_creating',
            'sender' => $senderId,
            'date' => $date,
            'data' => ['group_id' => $groupId]
        ]);
        
        ($this->broadcastToDate)($date, [
            'action' => 'group_created',
            'sender' => $senderId,
            'date' => $date,
            'data' => ['group_id' => $groupId]
        ]);
    }
    
    private function handleGroupAssign($data, $senderId, $date) {
        $ids = explode(',', $data['id']);
        $groupId = $data['group_id'];
        
        if ($groupId === '') {
            $stmt = $this->pdo->prepare("UPDATE individuals SET group_id = NULL WHERE id = ?");
            foreach($ids as $id) {
                $stmt->execute([trim($id)]);
            }
            
            ($this->broadcastToDate)($date, [
                'action' => 'group_assigned',
                'sender' => $senderId,
                'date' => $date,
                'data' => ['ids' => array_map('trim', $ids), 'group_id' => '', 'color' => '']
            ]);
            return;
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM groups WHERE group_id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$group) {
            $color = $this->getNextColor($date);
            $stmt = $this->pdo->prepare("
                INSERT INTO groups (group_id, color, created_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$groupId, $color, $data['created_at']]);
        } else {
            $color = $group['color'];
        }
        
        $stmt = $this->pdo->prepare("UPDATE individuals SET group_id = ? WHERE id = ?");
        foreach($ids as $id) {
            $stmt->execute([$groupId, trim($id)]);
        }
        
        ($this->broadcastToDate)($date, [
            'action' => 'group_assigned',
            'sender' => $senderId,
            'date' => $date,
            'data' => ['ids' => array_map('trim', $ids), 'group_id' => $groupId, 'color' => $color]
        ]);
    }
    
    private function handleGroupComment($data, $senderId, $date) {
        $stmt = $this->pdo->prepare("UPDATE groups SET comment = ? WHERE group_id = ?");
        $stmt->execute([$data['comment'], $data['group_id']]);
        
        ($this->broadcastToDate)($date, [
            'action' => 'group_comment_updated',
            'sender' => $senderId,
            'date' => $date,
            'data' => ['group_id' => $data['group_id'], 'comment' => $data['comment']]
        ]);
    }
    
    // ==================== UTILITAIRES ====================
    
    private function generateUniqueGroupId() {
        $validChars = "ACDEFHJKLMNPQRTUVWXY123479";
        do {
            $id = '';
            for($i = 0; $i < 4; $i++) {
                $id .= $validChars[random_int(0, strlen($validChars) - 1)];
            }
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM groups WHERE group_id = ?");
            $stmt->execute([$id]);
        } while($stmt->fetchColumn() > 0);
        return $id;
    }
    
    private function getNextColor($date) {
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT group_id) FROM groups WHERE DATE(created_at) = DATE(?)");
        $stmt->execute([$date]);
        $count = $stmt->fetchColumn();
        return $this->colors[$count % count($this->colors)];
    }
}