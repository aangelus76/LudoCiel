<?php
header('content-type:application/json');

try {
    // Connexion à la base de données SQLite avec des options explicites
    $db_file = "Surdim.db";
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer les paramètres
    $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    // Construire les dates de début et fin du mois
    $startDate = sprintf("%04d-%02d-01", $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate));
    
    // Requête brute pour obtenir toutes les réservations du mois
    $query = "
        SELECT User 
        FROM surdim_Rent 
        WHERE (StartDate BETWEEN :startDate AND :endDate OR 
               EndDate BETWEEN :startDate AND :endDate OR 
               (StartDate <= :startDate AND EndDate >= :endDate))
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':startDate', $startDate);
    $stmt->bindParam(':endDate', $endDate);
    $stmt->execute();
    
    // Utiliser un array associatif pour éliminer les doublons
    $reserversDict = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $reserversDict[$row['User']] = true;
    }
    
    // Convertir les clés du dictionnaire en tableau
    $reservers = array_keys($reserversDict);
    
    // Trier les réservateurs manuellement
    sort($reservers, SORT_STRING);
    
    echo json_encode(array(
        'success' => true,
        'reservers' => $reservers
    ));
    
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>