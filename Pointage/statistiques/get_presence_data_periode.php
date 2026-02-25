<?php
header('Content-Type: application/json');

/**
 * Génère toutes les semaines d'une année (Lundi→Dimanche)
 * Semaine 1 = premier lundi de l'année (même si commence en décembre N-1)
 */
function genererSemainesAnnee($annee) {
    $semaines = [];
    
    // Trouver le premier lundi de l'année
    $dateDebut = new DateTime("$annee-01-01");
    
    // Si le 1er janvier n'est pas un lundi, reculer jusqu'au lundi précédent
    while ($dateDebut->format('N') != 1) {
        $dateDebut->modify('-1 day');
    }
    
    $numSemaine = 1;
    
    while (true) {
        $lundi = clone $dateDebut;
        $dimanche = clone $dateDebut;
        $dimanche->modify('+6 days');
        
        // Arrêter si le prochain lundi serait dans l'année suivante
        $prochainLundi = clone $dimanche;
        $prochainLundi->modify('+1 day');
        if ($prochainLundi->format('Y') > $annee) {
            // Ajouter la dernière semaine et stop
            $semaines[] = [
                'numero' => $numSemaine,
                'lundi' => $lundi->format('Y-m-d'),
                'dimanche' => $dimanche->format('Y-m-d')
            ];
            break;
        }
        
        $semaines[] = [
            'numero' => $numSemaine,
            'lundi' => $lundi->format('Y-m-d'),
            'dimanche' => $dimanche->format('Y-m-d')
        ];
        
        $dateDebut->modify('+7 days');
        $numSemaine++;
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
    
    return 1;
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

    $dateDebut = $_GET['date_debut'] ?? null;
    $dateFin = $_GET['date_fin'] ?? null;

    if (!$dateDebut || !$dateFin) {
        throw new Exception('Dates manquantes');
    }

    // Requête principale adhérents
    $sql = "
        SELECT 
            strftime('%Y-%m-%d', arrival_time) as date_complete,
            strftime('%w', arrival_time) as jour_semaine,
            type,
            COUNT(*) as total,
            GROUP_CONCAT(duration) as durations
        FROM individuals 
        WHERE strftime('%Y-%m-%d', arrival_time) BETWEEN :date_debut AND :date_fin
        GROUP BY strftime('%Y-%m-%d', arrival_time), type
        ORDER BY strftime('%Y-%m-%d', arrival_time)
    ";

    // Affluence par quart d'heure et par jour
    $sqlHeures = "
        SELECT 
            strftime('%w', arrival_time) as jour_semaine,
            strftime('%H', arrival_time) as heure,
            CASE 
                WHEN CAST(strftime('%M', arrival_time) AS INTEGER) < 15 THEN 0
                WHEN CAST(strftime('%M', arrival_time) AS INTEGER) < 30 THEN 15
                WHEN CAST(strftime('%M', arrival_time) AS INTEGER) < 45 THEN 30
                ELSE 45
            END as minute,
            COUNT(*) as total
        FROM individuals 
        WHERE strftime('%Y-%m-%d', arrival_time) BETWEEN :date_debut AND :date_fin
        GROUP BY jour_semaine, heure, minute
        ORDER BY jour_semaine, heure, minute
    ";

    // Données brutes par date complète
    $sqlJourHeures = "
        SELECT 
            strftime('%Y-%m-%d', arrival_time) as date_complete,
            strftime('%H', arrival_time) as heure,
            CASE 
                WHEN CAST(strftime('%M', arrival_time) AS INTEGER) < 15 THEN 0
                WHEN CAST(strftime('%M', arrival_time) AS INTEGER) < 30 THEN 15
                WHEN CAST(strftime('%M', arrival_time) AS INTEGER) < 45 THEN 30
                ELSE 45
            END as minute,
            strftime('%w', arrival_time) as jour_semaine,
            COUNT(*) as total
        FROM individuals 
        WHERE strftime('%Y-%m-%d', arrival_time) BETWEEN :date_debut AND :date_fin
        GROUP BY date_complete, heure, minute
        ORDER BY date_complete, heure, minute
    ";

    // Partenaires par jour de semaine
    $sqlPartnersParJour = "
        SELECT 
            strftime('%w', created_at) as jour_semaine,
            SUM(size) as total
        FROM partners 
        WHERE strftime('%Y-%m-%d', created_at) BETWEEN :date_debut AND :date_fin
        GROUP BY jour_semaine
        ORDER BY jour_semaine
    ";

    // Nombre de groupes partenaires
    $sqlNombreGroupesPartenaires = "
        SELECT 
            COUNT(*) as nombre_groupes
        FROM partners 
        WHERE strftime('%Y-%m-%d', created_at) BETWEEN :date_debut AND :date_fin
    ";

    // Total partenaires (personnes) avec heures
    $sqlPartners = "
        SELECT 
            SUM(size) as total_partners,
            SUM(
                CAST(SUBSTR(total_duration, 1, INSTR(total_duration, ':') - 1) AS INTEGER) * 60 + 
                CAST(SUBSTR(total_duration, INSTR(total_duration, ':') + 1, 2) AS INTEGER)
            ) as total_minutes_partners
        FROM partners 
        WHERE strftime('%Y-%m-%d', created_at) BETWEEN :date_debut AND :date_fin
    ";

    // Exécution requêtes
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['date_debut' => $dateDebut, 'date_fin' => $dateFin]);
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtHeures = $pdo->prepare($sqlHeures);
    $stmtHeures->execute(['date_debut' => $dateDebut, 'date_fin' => $dateFin]);
    $resultatsHeures = $stmtHeures->fetchAll(PDO::FETCH_ASSOC);

    $stmtJourHeures = $pdo->prepare($sqlJourHeures);
    $stmtJourHeures->execute(['date_debut' => $dateDebut, 'date_fin' => $dateFin]);
    $resultatsJourHeures = $stmtJourHeures->fetchAll(PDO::FETCH_ASSOC);

    $stmtPartners = $pdo->prepare($sqlPartners);
    $stmtPartners->execute(['date_debut' => $dateDebut, 'date_fin' => $dateFin]);
    $resultatsPartners = $stmtPartners->fetch(PDO::FETCH_ASSOC);
    
    $stmtNombreGroupesPartenaires = $pdo->prepare($sqlNombreGroupesPartenaires);
    $stmtNombreGroupesPartenaires->execute(['date_debut' => $dateDebut, 'date_fin' => $dateFin]);
    $resultatsNombreGroupesPartenaires = $stmtNombreGroupesPartenaires->fetch(PDO::FETCH_ASSOC);

    $stmtPartnersParJour = $pdo->prepare($sqlPartnersParJour);
    $stmtPartnersParJour->execute(['date_debut' => $dateDebut, 'date_fin' => $dateFin]);
    $resultatsPartnersParJour = $stmtPartnersParJour->fetchAll(PDO::FETCH_ASSOC);

    // Structure données
    $data = [
        'dates' => [],
        'dates_completes' => [],
        'adultes' => [],
        'enfants' => [],
        'jeunes' => [],
        'total_adultes' => 0,
        'total_enfants' => 0,
        'total_jeunes' => 0,
        'total_general' => 0,
        'total_heures' => 0,
        'heures_adultes' => 0,
        'heures_enfants' => 0,
        'heures_jeunes' => 0,
        'presences_par_jour' => [
            'Dimanche' => 0, 'Lundi' => 0, 'Mardi' => 0, 'Mercredi' => 0,
            'Jeudi' => 0, 'Vendredi' => 0, 'Samedi' => 0
        ],
        'presences_partenaires_par_jour' => [
            'Dimanche' => 0, 'Lundi' => 0, 'Mardi' => 0, 'Mercredi' => 0,
            'Jeudi' => 0, 'Vendredi' => 0, 'Samedi' => 0
        ],
        'presences_totales_par_jour' => [
            'Dimanche' => 0, 'Lundi' => 0, 'Mardi' => 0, 'Mercredi' => 0,
            'Jeudi' => 0, 'Vendredi' => 0, 'Samedi' => 0
        ],
        'affluence_heures' => [
            'Dimanche' => [], 'Lundi' => [], 'Mardi' => [], 'Mercredi' => [],
            'Jeudi' => [], 'Vendredi' => [], 'Samedi' => []
        ],
        'affluence_par_semaine' => [],
        'affluence_semaines_jours' => [],
        'partenaires' => [
            'total_presences' => intval($resultatsPartners['total_partners'] ?? 0),
            'total_heures' => formatHeures(intval($resultatsPartners['total_minutes_partners'] ?? 0)),
            'nombre_groupes' => intval($resultatsNombreGroupesPartenaires['nombre_groupes'] ?? 0)
        ]
    ];

    $presencesParJour = [];
    $totalMinutes = 0;
    $minutesAdultes = 0;
    $minutesEnfants = 0;
    $minutesJeunes = 0;
    $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

    // Traitement adhérents
    foreach ($resultats as $row) {
        $dateComplete = $row['date_complete'];
        $dateAffichage = (new DateTime($dateComplete))->format('d/m');
        
        $jourSemaine = intval($row['jour_semaine']);
        $data['presences_par_jour'][$jours[$jourSemaine]] += intval($row['total']);
        
        if (!in_array($dateAffichage, $data['dates'])) {
            $data['dates'][] = $dateAffichage;
            $data['dates_completes'][] = $dateComplete;
            $presencesParJour[$dateAffichage] = [
                'ADULTE' => 0,
                'ENFANT' => 0,
                'JEUNE' => 0
            ];
        }

        $presencesParJour[$dateAffichage][$row['type']] = intval($row['total']);

        $durations = explode(',', $row['durations']);
        foreach ($durations as $duration) {
            if (!empty($duration)) {
                list($hours, $minutes) = explode(':', $duration);
                $mins = intval($hours) * 60 + intval($minutes);
                $totalMinutes += $mins;

                switch ($row['type']) {
                    case 'ADULTE':
                        $minutesAdultes += $mins;
                        break;
                    case 'ENFANT':
                        $minutesEnfants += $mins;
                        break;
                    case 'JEUNE':
                        $minutesJeunes += $mins;
                        break;
                }
            }
        }

        switch ($row['type']) {
            case 'ADULTE':
                $data['total_adultes'] += intval($row['total']);
                break;
            case 'ENFANT':
                $data['total_enfants'] += intval($row['total']);
                break;
            case 'JEUNE':
                $data['total_jeunes'] += intval($row['total']);
                break;
        }
    }

    // Partenaires par jour
    foreach ($resultatsPartnersParJour as $row) {
        $jourSemaine = intval($row['jour_semaine']);
        $data['presences_partenaires_par_jour'][$jours[$jourSemaine]] = intval($row['total']);
    }

    // Totaux par jour
    foreach ($jours as $jour) {
        $data['presences_totales_par_jour'][$jour] = 
            $data['presences_par_jour'][$jour] + $data['presences_partenaires_par_jour'][$jour];
    }

    // Affluence par heure et jour de semaine
    foreach ($resultatsHeures as $row) {
        $jourSemaine = intval($row['jour_semaine']);
        $heure = intval($row['heure']);
        $minute = intval($row['minute']);
        $key = sprintf("%02d:%02d", $heure, $minute);
        
        if (!isset($data['affluence_heures'][$jours[$jourSemaine]][$key])) {
            $data['affluence_heures'][$jours[$jourSemaine]][$key] = 0;
        }
        
        $data['affluence_heures'][$jours[$jourSemaine]][$key] = intval($row['total']);
    }

    // Affluence par semaine
    foreach ($resultatsJourHeures as $row) {
        $dateComplete = $row['date_complete'];
        $annee = (int)substr($dateComplete, 0, 4);
        $numSemaine = getNumeroSemaine($dateComplete, $annee);
        $semaineKey = $annee . '-' . str_pad($numSemaine, 2, '0', STR_PAD_LEFT);
        
        $heure = intval($row['heure']);
        $minute = intval($row['minute']);
        $key = sprintf("%02d:%02d", $heure, $minute);
        
        if (!isset($data['affluence_par_semaine'][$semaineKey])) {
            $data['affluence_par_semaine'][$semaineKey] = [];
        }
        
        if (!isset($data['affluence_par_semaine'][$semaineKey][$key])) {
            $data['affluence_par_semaine'][$semaineKey][$key] = 0;
        }
        
        $data['affluence_par_semaine'][$semaineKey][$key] += intval($row['total']);
        
        // Affluence par semaine + jour
        $jourSemaine = intval($row['jour_semaine']);
        $jour = $jours[$jourSemaine];
        
        if (!isset($data['affluence_semaines_jours'][$semaineKey])) {
            $data['affluence_semaines_jours'][$semaineKey] = [];
        }
        
        if (!isset($data['affluence_semaines_jours'][$semaineKey][$jour])) {
            $data['affluence_semaines_jours'][$semaineKey][$jour] = [];
        }
        
        if (!isset($data['affluence_semaines_jours'][$semaineKey][$jour][$key])) {
            $data['affluence_semaines_jours'][$semaineKey][$jour][$key] = 0;
        }
        
        $data['affluence_semaines_jours'][$semaineKey][$jour][$key] += intval($row['total']);
    }

    // Finalisation
    foreach ($data['dates'] as $date) {
        $data['adultes'][] = $presencesParJour[$date]['ADULTE'];
        $data['enfants'][] = $presencesParJour[$date]['ENFANT'];
        $data['jeunes'][] = $presencesParJour[$date]['JEUNE'];
    }

    $data['total_general'] = $data['total_adultes'] + $data['total_enfants'] + $data['total_jeunes'];
    $data['total_heures'] = formatHeures($totalMinutes);
    
    $data['heures_adultes'] = formatHeures($minutesAdultes);
    $data['heures_enfants'] = formatHeures($minutesEnfants);
    $data['heures_jeunes'] = formatHeures($minutesJeunes);
    $data['heures_adherents'] = formatHeures($data['total_general'] * 90);

    $data['total_presences_global'] = $data['total_general'] + $data['partenaires']['total_presences'];
    
    // Addition heures adhérents + partenaires
    $minutesAdherentsTotal = $data['total_general'] * 90;
    $minutesPartenairesTotal = intval($resultatsPartners['total_minutes_partners'] ?? 0);
    $data['total_heures_global'] = formatHeures($minutesAdherentsTotal + $minutesPartenairesTotal);

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'details' => $e->getTraceAsString()
    ]);
}