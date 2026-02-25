<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Stats</title>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<style>
    body { font-family: Arial, sans-serif; }
    table { border-collapse: collapse; width: 48%; margin-right: 20px; float: left; }
    th, td { border: 1px solid #dddddd; text-align: left; padding: 8px; }
    th { background-color: #f2f2f2; cursor: pointer; }
    .arrow { cursor: pointer; }
    #summary { clear: both; margin-top: 20px; }
</style>
<script>
$(document).ready(function(){
    var resourceCounts = {};
    var totalResources = 0;
    var totalLoans = 0;
    var sortDirection = 'asc'; // Tracks the sorting direction for the count column

    // Initialize resource counts with zero
    <?php
    $db_file = "Surdim.db";
    $pdo = new PDO("sqlite:$db_file");
    $sql = "SELECT Name FROM surdim_List";
    $stmt = $pdo->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "resourceCounts['" . addslashes($row['Name']) . "'] = 0;\n";
        echo "totalResources++;\n";
    }
    ?>

    function filterTable() {
        // Reset counts to zero
        for (var key in resourceCounts) {
            resourceCounts[key] = 0;
        }
        totalLoans = 0;

        $("#loansTable tr").filter(function() {
            var userVal = $("#userFilter").val().toLowerCase();
            var resourceVal = $("#resourceFilter").val().toLowerCase();
            var startDateVal = $("#startDateFilter").val().toLowerCase();
            var isVisible = ($(this).children("td").eq(1).text().toLowerCase().indexOf(userVal) > -1 || !userVal) &&
                            ($(this).children("td").eq(2).text().toLowerCase().indexOf(resourceVal) > -1 || !resourceVal) &&
                            ($(this).children("td").eq(3).text().toLowerCase().indexOf(startDateVal) > -1 || !startDateVal);
            $(this).toggle(isVisible);

            if (isVisible) {
                var resourceName = $(this).children("td").eq(2).text();
                resourceCounts[resourceName] = (resourceCounts[resourceName] || 0) + 1;
                totalLoans++;
            }
        });
        updateResourceCountTable();
        updateVisibleCount();
    }

    function updateVisibleCount() {
        var visibleRows = $("#loansTable tr:visible").length;
        $("#visibleCount").text("Nombre de prêts visibles: " + visibleRows);
    }

    function updateResourceCountTable() {
        var resourcesArray = [];
        for (var resourceName in resourceCounts) {
            resourcesArray.push({ name: resourceName, count: resourceCounts[resourceName] });
        }

        // Apply sorting
        if (sortDirection === 'asc') {
            resourcesArray.sort(function(a, b) { return a.count - b.count; });
            sortDirection = 'desc';
        } else {
            resourcesArray.sort(function(a, b) { return b.count - a.count; });
            sortDirection = 'asc';
        }

        var tableHtml = "<tr><th>Ressource  : " + totalResources + "/"+ ($("#TTwo tr:visible").length-1)+"</th><th>Compteur : "+totalLoans+" <span class='arrow'> &#9660;</span></th></tr>";
        resourcesArray.forEach(function(item) {
            tableHtml += "<tr><td>" + item.name + "</td><td>" + item.count + "</td></tr>";
        });
        $("#resourceCountTable tbody").html(tableHtml);
    }

    $("#resourceCountTable").on("click", ".arrow", function() {
        updateResourceCountTable(); // Update the table when arrow is clicked
    });

    $("#userFilter, #resourceFilter, #startDateFilter").on("keyup", filterTable);

    // Initial count update
    filterTable();
});
</script>
</head>
<body>
<h1>Statistiques.</h1>
<div id="visibleCount"></div>
<table border="1">
<thead>
<tr>
    <th>ID</th>
    <th>Utilisateur <input type="text" id="userFilter" placeholder="Filtrer par utilisateur"></th>
    <th>Nom de la Ressource <input type="text" id="resourceFilter" placeholder="Filtrer par ressource"></th>
    <th>Date de Début <input type="text" id="startDateFilter" placeholder="Filtrer par date de début"></th>
    <th>Date de Fin</th>
    <th>Nb-Jour</th>
    <th>Infos</th>
</tr>
</thead>
<tbody id="loansTable">
<?php
// Remplissage du tableau avec les données
$sql = "SELECT r.Id, r.User, l.Name, r.StartDate, r.EndDate, r.Info
        FROM surdim_Rent r
        JOIN surdim_List l ON r.Id_List = l.Id";
$stmt = $pdo->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $startDate = new DateTime($row['StartDate']);
    $endDate = new DateTime($row['EndDate']);
    $interval = $startDate->diff($endDate)->days + 1;  // +1 pour inclure les deux jours

    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['Id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['User']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Name']) . "</td>";
    echo "<td>" . htmlspecialchars($startDate->format('d/m/Y')) . "</td>";
    echo "<td>" . htmlspecialchars($endDate->format('d/m/Y')) . "</td>";
    echo "<td>" . $interval . "</td>";
    echo "<td>" . htmlspecialchars($row['Info']) . "</td>";
    echo "</tr>\n";
}
?>
</tbody>
</table>

<table id="resourceCountTable">
    <tbody id="TTwo"></tbody>
</table>

<div id="summary"></div>

</body>
</html>
