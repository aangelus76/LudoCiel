<?php
header('content-type:application/json');

try {
    // Connexion à la base de données SQLite
    $db_file = "Surdim.db";
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer les paramètres
    $user = isset($_GET['user']) ? $_GET['user'] : '';
    $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    if (empty($user)) {
        throw new Exception("Nom d'utilisateur requis");
    }
    
    // Construire les dates de début et fin du mois
    $startDate = sprintf("%04d-%02d-01", $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate));
    
    // Journal pour débogage
    file_put_contents('debug_getUserReservations.log', "User: $user, Month: $month, Year: $year, StartDate: $startDate, EndDate: $endDate\n", FILE_APPEND);
    
    // Requête pour obtenir toutes les réservations
    $query = "SELECT r.Id, r.StartDate, r.EndDate, r.Info, r.User, l.Name as ResourceName, l.Id as ResourceId 
              FROM surdim_Rent r
              JOIN surdim_List l ON r.Id_List = l.Id";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    $reservations = array();
    
    // Filtrer manuellement les réservations de l'utilisateur pour le mois
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['User'] === $user) {
            $rowStartDate = strtotime($row['StartDate']);
            $rowEndDate = strtotime($row['EndDate']);
            $monthStartDate = strtotime($startDate);
            $monthEndDate = strtotime($endDate);
            
            // Si la réservation chevauche le mois en cours
            if (($rowStartDate <= $monthEndDate && $rowEndDate >= $monthStartDate) ||
                ($rowStartDate >= $monthStartDate && $rowStartDate <= $monthEndDate) ||
                ($rowEndDate >= $monthStartDate && $rowEndDate <= $monthEndDate)) {
                $reservations[] = array(
                    'id' => $row['Id'],
                    'startDate' => $row['StartDate'],
                    'endDate' => $row['EndDate'],
                    'info' => $row['Info'],
                    'resourceName' => $row['ResourceName'],
                    'resourceId' => $row['ResourceId']
                );
            }
        }
    }
    
    // Trier les réservations par date de début
    usort($reservations, function($a, $b) {
        return strtotime($a['startDate']) - strtotime($b['startDate']);
    });
    
    // Journal pour débogage
    file_put_contents('debug_getUserReservations.log', "Found " . count($reservations) . " reservations\n", FILE_APPEND);
    
    echo json_encode(array(
        'success' => true,
        'reservations' => $reservations
    ));
    
} catch (Exception $e) {
    file_put_contents('debug_getUserReservations.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>