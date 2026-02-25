<?php
include "_pdo.php";
$db_file = "Surdim.db";
PDO_Connect("sqlite:$db_file");

// 1. Récupérer les ressources visibles et réattribuer les priorités dynamiquement
$data = PDO_FetchAll("
    SELECT * 
    FROM surdim_List 
    WHERE Visible = 1 
    ORDER BY Name ASC, Is_Titre DESC, Priority ASC
");

$CurrentId = 0;
$priorityMap = array();

// Stocker les informations nécessaires pour les réservations
$dynamicRentData = array();

foreach($data as $row) {
    // Réattribuer une nouvelle priorité basée sur l'ordre de tri
    $newPriority = $CurrentId;
    $priorityMap[$row['Id']] = $newPriority; // Mapping de l'Id à la nouvelle Priority

    // Récupérer les réservations associées à cette ressource visible
    $rentData = PDO_FetchAll("
        SELECT r.*, l.Name_Vue 
        FROM surdim_Rent r
        JOIN surdim_List l ON r.Id_List = l.Id
        WHERE r.Id_List = :listId 
          AND l.Visible = 1
    ", array(':listId' => $row['Id']));

    // Appliquer la nouvelle priorité dynamique aux réservations récupérées
    foreach ($rentData as $rentRow) {
        $rentRow['Priority'] = $newPriority;
        $rentRow['ListId'] = $row['Id']; // Ajouter l'Id de la liste à la réservation
        $dynamicRentData[] = $rentRow;
    }

    $CurrentId++;
}

// 2. Afficher la liste des ressources
echo "<h2>Liste des ressources</h2>";
echo "<ul>";
foreach($data as $row) {
    echo "<li>";
    echo 'Nom : ' . $row['Name_Vue'] . ' (ID : ' . $row['Id'] . ', Priority : ' . $priorityMap[$row['Id']] . ')';
    if($row['Is_Titre'] == "true"){
        echo ' - Titre';
    }
    echo "</li>";
}
echo "</ul>";

// 3. Afficher la liste des réservations sur une période de 1 mois avec les priorités dynamiques et l'ID de surdim_List
$oneMonthAgo = date('Y-m-d H:i:s', strtotime('-1 month'));
$currentDate = date('Y-m-d H:i:s');

echo "<h2>Liste des réservations (1 mois)</h2>";
echo "<ul>";
foreach($dynamicRentData as $reservation) {
    if ($reservation['StartDate'] >= $oneMonthAgo && $reservation['EndDate'] <= $currentDate) {
        echo "<li>";
        echo 'Nom : ' . $reservation['Name_Vue'] . ' - Utilisateur : ' . $reservation['User'] . ' - Du ' . $reservation['StartDate'] . ' au ' . $reservation['EndDate'] . ' - Priority : ' . $reservation['Priority'] . ' - ID de la ressource : ' . $reservation['ListId'];
        echo "</li>";
    }
}
echo "</ul>";
?>
