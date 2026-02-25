<?php
header('Content-Type: application/json');

/**
 * Génère les 52 semaines d'une année (Lundi→Dimanche)
 * Semaine 1 = semaine qui contient le 1er janvier (peut commencer en déc N-1)
 * Max 52 semaines - les derniers jours de déc qui tombent après la semaine 52 vont en semaine 1 de N+1
 */
function genererSemainesAnnee($annee) {
    $semaines = [];
    
    // Trouver le lundi de la semaine qui contient le 1er janvier
    $dateDebut = new DateTime("$annee-01-01");
    $jourSemaine = (int)$dateDebut->format('N');
    
    if ($jourSemaine != 1) {
        $dateDebut->modify('-' . ($jourSemaine - 1) . ' days');
    }
    
    // Générer exactement 52 semaines
    for ($numSemaine = 1; $numSemaine <= 52; $numSemaine++) {
        $lundi = clone $dateDebut;
        $dimanche = clone $dateDebut;
        $dimanche->modify('+6 days');
        
        $semaines[] = [
            'numero' => $numSemaine,
            'lundi' => $lundi->format('Y-m-d'),
            'dimanche' => $dimanche->format('Y-m-d')
        ];
        
        $dateDebut->modify('+7 days');
    }
    
    return $semaines;
}

/**
 * Trouve le numéro de semaine custom pour une date donnée
 */
function getNumeroSemaine($date, $annee) {
    $semaines = genererSemainesAnnee($annee);
    $dateObj = new DateTime($date);
    
    foreach ($semaines as $s) {
        $lundi = new DateTime($s['lundi']);
        $dimanche = new DateTime($s['dimanche']);
        
        if ($dateObj >= $lundi && $dateObj <= $dimanche) {
            return $s['numero'];
        }
    }
    
    // Si pas trouvé, c'est que la date est après la semaine 52 (donc semaine 1 de N+1)
    return null;
}

/**
 * Convertit minutes en format HH:MM
 */
function formatHeures($minutes) {
    $heures = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%d:%02d', $heures, $mins);
}

try {
    $pdo = new PDO("sqlite:../Presences.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $annee = $_GET['annee'] ?? null;

    if (!$annee) {
        throw new Exception('Année manquante');
    }

    // Générer les 52 semaines de l'année
    $semaines = genererSemainesAnnee($annee);
    
    // Déterminer les vraies dates min/max à interroger
    $premiereSemaine = $semaines[0];
    $derniereSemaine = $semaines[51]; // Semaine 52
    
    $dateDebut = $premiereSemaine['lundi'];
    $dateFin = $derniereSemaine['dimanche'];

    // Requête adhérents
    $sql = "
        SELECT 
            strftime('%Y-%m-%d', arrival_time) as date_complete,
            COUNT(*) as total
        FROM individuals 
        WHERE strftime('%Y-%m-%d', arrival_time) BETWEEN :date_debut AND :date_fin
        GROUP BY date_complete
    ";

    // Requête partenaires avec durée
    $sqlPartners = "
        SELECT 
            strftime('%Y-%m-%d', created_at) as date_complete,
            SUM(size) as total,
            SUM(
                CAST(SUBSTR(total_duration, 1, INSTR(total_duration, ':') - 1) AS INTEGER) * 60 + 
                CAST(SUBSTR(total_duration, INSTR(total_duration, ':') + 1, 2) AS INTEGER)
            ) as total_minutes
        FROM partners 
        WHERE strftime('%Y-%m-%d', created_at) BETWEEN :date_debut AND :date_fin
        GROUP BY date_complete
    ";

    // Requête animations (soirées type_id=1)
    $sqlAnimations = "
        SELECT 
            substr(date, 7, 4) || '-' || substr(date, 4, 2) || '-' || substr(date, 1, 2) as date_complete,
            presence_reelle as total
        FROM animations 
        WHERE compter_stats = 1 
        AND presence_reelle IS NOT NULL
        AND type_id = 1
        AND (substr(date, 7, 4) || '-' || substr(date, 4, 2) || '-' || substr(date, 1, 2)) BETWEEN :date_debut AND :date_fin
    ";

    // Exécution
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['date_debut' => $dateDebut, 'date_fin' => $dateFin]);
    $resultatsAdherents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtPartners = $pdo->prepare($sqlPartners);
    $stmtPartners->execute(['date_debut' => $dateDebut, 'date_fin' => $dateFin]);
    $resultatsPartners = $stmtPartners->fetchAll(PDO::FETCH_ASSOC);

    $stmtAnimations = $pdo->prepare($sqlAnimations);
    $stmtAnimations->execute(['date_debut' => $dateDebut, 'date_fin' => $dateFin]);
    $resultatsAnimations = $stmtAnimations->fetchAll(PDO::FETCH_ASSOC);

    // Initialisation données par semaine
    $dataParSemaine = [];
    foreach ($semaines as $s) {
        $dataParSemaine[$s['numero']] = [
            'adherents' => 0,
            'partenaires' => 0,
            'soirees' => 0,
            'total' => 0,
            'minutes_adherents' => 0,
            'minutes_partenaires' => 0,
            'minutes_soirees' => 0
        ];
    }

    // Remplissage adhérents
    foreach ($resultatsAdherents as $row) {
        $numSemaine = getNumeroSemaine($row['date_complete'], $annee);
        if ($numSemaine !== null && isset($dataParSemaine[$numSemaine])) {
            $nb = intval($row['total']);
            $dataParSemaine[$numSemaine]['adherents'] += $nb;
            $dataParSemaine[$numSemaine]['minutes_adherents'] += $nb * 90;
        }
    }

    // Remplissage partenaires
    foreach ($resultatsPartners as $row) {
        $numSemaine = getNumeroSemaine($row['date_complete'], $annee);
        if ($numSemaine !== null && isset($dataParSemaine[$numSemaine])) {
            $dataParSemaine[$numSemaine]['partenaires'] += intval($row['total']);
            $dataParSemaine[$numSemaine]['minutes_partenaires'] += intval($row['total_minutes']);
        }
    }

    // Remplissage animations
    foreach ($resultatsAnimations as $row) {
        $numSemaine = getNumeroSemaine($row['date_complete'], $annee);
        if ($numSemaine !== null && isset($dataParSemaine[$numSemaine])) {
            $nb = intval($row['total']);
            $dataParSemaine[$numSemaine]['soirees'] += $nb;
            $dataParSemaine[$numSemaine]['minutes_soirees'] += $nb * 210;
        }
    }

    // Calcul totaux
    $totalAdherents = 0;
    $totalPartenaires = 0;
    $totalSoirees = 0;
    $totalMinutesAdherents = 0;
    $totalMinutesPartenaires = 0;
    $totalMinutesSoirees = 0;

    foreach ($dataParSemaine as $num => &$s) {
        $s['total'] = $s['adherents'] + $s['partenaires'] + $s['soirees'];
        $totalAdherents += $s['adherents'];
        $totalPartenaires += $s['partenaires'];
        $totalSoirees += $s['soirees'];
        $totalMinutesAdherents += $s['minutes_adherents'];
        $totalMinutesPartenaires += $s['minutes_partenaires'];
        $totalMinutesSoirees += $s['minutes_soirees'];
    }

    $totalPresences = $totalAdherents + $totalPartenaires + $totalSoirees;
    $moyenneSemaine = 52 > 0 ? round($totalPresences / 52) : 0;
    $totalMinutes = $totalMinutesAdherents + $totalMinutesPartenaires + $totalMinutesSoirees;

    // Top 5 semaines
    $semainesTriees = $dataParSemaine;
    uasort($semainesTriees, function($a, $b) {
        return $b['total'] - $a['total'];
    });
    $top5 = array_slice($semainesTriees, 0, 5, true);

    // Résultat
    $data = [
        'semaines' => $semaines,
        'data_par_semaine' => $dataParSemaine,
        'totaux' => [
            'adherents' => $totalAdherents,
            'partenaires' => $totalPartenaires,
            'soirees' => $totalSoirees,
            'total_presences' => $totalPresences,
            'moyenne_semaine' => $moyenneSemaine
        ],
        'heures' => [
            'adherents' => formatHeures($totalMinutesAdherents),
            'partenaires' => formatHeures($totalMinutesPartenaires),
            'soirees' => formatHeures($totalMinutesSoirees),
            'total' => formatHeures($totalMinutes)
        ],
        'top_5_semaines' => $top5
    ];

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'details' => $e->getTraceAsString()
    ]);
}
