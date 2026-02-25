<?php
header('content-type:application/json');

try {
    // Connexion à la base de données SQLite
    $db_file = "Surdim.db";
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les paramètres
    $currentMonth = isset($_GET['currentMonth']) ? intval($_GET['currentMonth']) : date('n') - 1; // JavaScript mois commence à 0
    $currentYear = isset($_GET['currentYear']) ? intval($_GET['currentYear']) : date('Y');

    // Calculer les dates des mois suivants
    $nextMonthDate = new DateTime("$currentYear-" . ($currentMonth + 1) . "-01");
    $nextMonthDate->modify('+1 month');
    $nextMonthStart = $nextMonthDate->format('Y-m-d');
    $nextMonthEnd = $nextMonthDate->format('Y-m-t');

    $afterNextMonthDate = clone $nextMonthDate;
    $afterNextMonthDate->modify('+1 month');
    $afterNextMonthStart = $afterNextMonthDate->format('Y-m-d');
    $afterNextMonthEnd = $afterNextMonthDate->format('Y-m-t');

    // Récupérer les ressources visibles et créer un mapping ID -> Index
    $queryResources = "
        SELECT * 
        FROM surdim_List 
        WHERE Visible = 1 
        ORDER BY Name ASC, Is_Titre DESC, Id ASC
    ";
    $stmtResources = $pdo->query($queryResources);
    $resources = $stmtResources->fetchAll(PDO::FETCH_ASSOC);

    $resourceMap = array();
    foreach ($resources as $index => $resource) {
        $resourceMap[$resource['Id']] = $index;
    }

    // Récupérer les réservations du mois suivant
    $queryNextMonth = "
        SELECT r.Id_List as ResourceId, 
               strftime('%d', r.StartDate) as StartDay, 
               strftime('%d', r.EndDate) as EndDay,
               r.User as User
        FROM surdim_Rent r
        JOIN surdim_List l ON r.Id_List = l.Id
        WHERE (r.StartDate BETWEEN :startDate AND :endDate OR
              r.EndDate BETWEEN :startDate AND :endDate OR
              (r.StartDate <= :startDate AND r.EndDate >= :endDate))
          AND l.Visible = 1
        ORDER BY r.Id_List, r.StartDate
    ";
    $stmtNextMonth = $pdo->prepare($queryNextMonth);
    $stmtNextMonth->bindParam(':startDate', $nextMonthStart);
    $stmtNextMonth->bindParam(':endDate', $nextMonthEnd);
    $stmtNextMonth->execute();
    
    $nextMonthReservations = array();
    while ($row = $stmtNextMonth->fetch(PDO::FETCH_ASSOC)) {
        if (isset($resourceMap[$row['ResourceId']])) {
            $resourceIndex = $resourceMap[$row['ResourceId']];
            $startDay = intval($row['StartDay']);
            $endDay = intval($row['EndDay']);
            
            $nextMonthReservations[] = array(
                'resourceIndex' => $resourceIndex,
                'startDay' => $startDay,
                'endDay' => $endDay,
                'user' => $row['User']
            );
        }
    }

    // Répéter pour le mois après le suivant
    $queryAfterNextMonth = "
        SELECT r.Id_List as ResourceId, 
               strftime('%d', r.StartDate) as StartDay, 
               strftime('%d', r.EndDate) as EndDay,
               r.User as User
        FROM surdim_Rent r
        JOIN surdim_List l ON r.Id_List = l.Id
        WHERE (r.StartDate BETWEEN :startDate AND :endDate OR
              r.EndDate BETWEEN :startDate AND :endDate OR
              (r.StartDate <= :startDate AND r.EndDate >= :endDate))
          AND l.Visible = 1
        ORDER BY r.Id_List, r.StartDate
    ";
    $stmtAfterNextMonth = $pdo->prepare($queryAfterNextMonth);
    $stmtAfterNextMonth->bindParam(':startDate', $afterNextMonthStart);
    $stmtAfterNextMonth->bindParam(':endDate', $afterNextMonthEnd);
    $stmtAfterNextMonth->execute();
    
    $afterNextMonthReservations = array();
    while ($row = $stmtAfterNextMonth->fetch(PDO::FETCH_ASSOC)) {
        if (isset($resourceMap[$row['ResourceId']])) {
            $resourceIndex = $resourceMap[$row['ResourceId']];
            $startDay = intval($row['StartDay']);
            $endDay = intval($row['EndDay']);
            
            $afterNextMonthReservations[] = array(
                'resourceIndex' => $resourceIndex,
                'startDay' => $startDay,
                'endDay' => $endDay,
                'user' => $row['User']
            );
        }
    }

    // Préparer la réponse
    $result = array(
        'success' => true,
        'nextMonth' => $nextMonthReservations,
        'afterNextMonth' => $afterNextMonthReservations
    );

    // Retourner la réponse
    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>