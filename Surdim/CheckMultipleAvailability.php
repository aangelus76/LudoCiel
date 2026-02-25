<?php
header('Content-Type: application/json');

function convertEncodedDateToPHPDate($encodedDate) {
    $decodedDate = urldecode($encodedDate);
    $datePart = substr($decodedDate, 0, strpos($decodedDate, " GMT"));
    $timestamp = strtotime($datePart);
    $formattedDate = date('Y-m-d H:i:s', $timestamp);
    return $formattedDate;
}

try {
    // Connexion à la base de données SQLite
    $db_file = "Surdim.db";
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Vérifier si les dates sont fournies
    if (!isset($_GET['startDate']) || !isset($_GET['endDate'])) {
        throw new Exception("Les paramètres 'startDate' et 'endDate' sont requis.");
    }

    $startDate = convertEncodedDateToPHPDate($_GET['startDate']);
    $endDate = convertEncodedDateToPHPDate($_GET['endDate']);

    // Récupérer toutes les ressources visibles
    $queryResources = "
        SELECT Id, Name_Vue, Name
        FROM surdim_List 
        WHERE Visible = 1 
        ORDER BY Name ASC, Is_Titre DESC, Id ASC
    ";
    $stmtResources = $pdo->query($queryResources);
    $resources = $stmtResources->fetchAll(PDO::FETCH_ASSOC);

    // Pour chaque ressource, vérifier si elle est disponible sur la période
    $result = array();
    
    foreach ($resources as $resource) {
        $resourceId = $resource['Id'];
        
        // Vérifier s'il existe des réservations qui chevauchent la période demandée
        $queryOverlap = "
            SELECT COUNT(*) as count
            FROM surdim_Rent
            WHERE Id_List = :resourceId
            AND (
                (StartDate <= :endDate AND EndDate >= :startDate)
            )
        ";
        
        $stmtOverlap = $pdo->prepare($queryOverlap);
        $stmtOverlap->bindParam(':resourceId', $resourceId);
        $stmtOverlap->bindParam(':startDate', $startDate);
        $stmtOverlap->bindParam(':endDate', $endDate);
        $stmtOverlap->execute();
        
        $overlapResult = $stmtOverlap->fetch(PDO::FETCH_ASSOC);
        $isAvailable = ($overlapResult['count'] == 0);
        
        $result[] = array(
            'id' => $resourceId,
            'name' => $resource['Name'],
            'nameVue' => $resource['Name_Vue'],
            'available' => $isAvailable
        );
    }

    echo json_encode(array(
        'success' => true,
        'resources' => $result
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>
