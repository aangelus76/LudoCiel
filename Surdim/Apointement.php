<?php
header('content-type:application/json');

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

    $date_debut_min = convertEncodedDateToPHPDate($_GET['startDate']);
    $date_debut_max = convertEncodedDateToPHPDate($_GET['endDate']);

    // Récupérer les ressources visibles et réattribuer les priorités dynamiquement
    $queryList = "
        SELECT * 
        FROM surdim_List 
        WHERE Visible = 1 
        ORDER BY Name ASC, Is_Titre DESC, Id ASC
    ";
    $stmtList = $pdo->query($queryList);
    $data = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    if (empty($data)) {
        throw new Exception("Aucune ressource visible trouvée.");
    }

    // Créer un mapping ID => priorité séquentielle pour l'affichage
    $priorityMap = array();
    $CurrentId = 0;
    foreach ($data as $row) {
        $priorityMap[$row['Id']] = $CurrentId;
        $CurrentId++;
    }

    // Log du mapping pour débogage
    file_put_contents('debug_mapping.log', print_r($priorityMap, true), FILE_APPEND);

    // Requête SQL pour récupérer les réservations associées à des ressources visibles
    $queryRent = "
        SELECT r.*, l.Name_Vue, l.Id as ListId, l.Name as ResourceName
        FROM surdim_Rent r
        JOIN surdim_List l ON r.Id_List = l.Id
        WHERE r.StartDate BETWEEN :date_debut_min AND :date_debut_max
          AND l.Visible = 1
        ORDER BY r.StartDate ASC
    ";
    $stmtRent = $pdo->prepare($queryRent);
    $stmtRent->bindParam(':date_debut_min', $date_debut_min);
    $stmtRent->bindParam(':date_debut_max', $date_debut_max);
    $stmtRent->execute();

    $results = $stmtRent->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        echo json_encode([]);
        exit;
    }

    // Log des résultats pour débogage
    file_put_contents('debug_results.log', print_r($results, true), FILE_APPEND);

    $jsonResponse = [];
    foreach ($results as $row) {
        // Vérifier si l'ID de la ressource est dans notre mapping
        if (isset($priorityMap[$row['ListId']])) {
            // Utiliser la priorité mappée pour l'affichage
            $newPriority = $priorityMap[$row['ListId']];
            
            $jsonResponse[] = [
                'text' => ucfirst($row['User']),
                'startDate' => str_replace(" ","T",$row['StartDate']),
                'endDate' => str_replace(" ","T",$row['EndDate']),
                'description' => $row['Info'],
                'ID' => $row['Id'],
                'priority' => $newPriority,
                'Phone' => $row['Phone'],
                'dateSave' => $row['DateSave'],
                'ListId' => $row['ListId'],        // ID réel de la ressource dans la BD
                'ResourceName' => $row['ResourceName'], // Nom de la ressource pour débogage
                'status' => intval($row['Status']) // Statut (retourné ou non)
            ];
        } else {
            // Journaliser les ressources qui n'ont pas de correspondance
            file_put_contents('missing_resources.log', 
                "Ressource non trouvée: ListId=" . $row['ListId'] . 
                ", User=" . $row['User'] . 
                ", StartDate=" . $row['StartDate'] . "\n", 
                FILE_APPEND);
        }
    }

    // Retourner la réponse JSON
    echo json_encode($jsonResponse);

} catch (Exception $e) {
    // Journaliser l'erreur
    file_put_contents('error.log', date('Y-m-d H:i:s') . ": " . $e->getMessage() . "\n", FILE_APPEND);
    
    // Retourner une réponse JSON avec un message d'erreur
    echo json_encode(['error' => $e->getMessage()]);
}
?>