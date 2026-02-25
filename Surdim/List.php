<?php
// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:Surdim.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}

// Récupération des ressources, incluant celles qui ont Visible = 0
$query = "SELECT Name, COUNT(CASE WHEN Visible = 1 THEN 1 ELSE NULL END) as QuantiteVisible, COUNT(*) as QuantiteTotale, MIN(Visible) as AllVisible FROM surdim_List GROUP BY Name";
$stmt = $db->prepare($query);
$stmt->execute();
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement de l'ajout de ressource
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_resource'])) {
    $name = $_POST['name'];
    $quantity = (int)$_POST['quantity'];

    for ($i = 0; $i < $quantity; $i++) {
        // Vérifier s'il existe déjà une ressource avec Visible = 0 pour ce nom
        $checkQuery = "SELECT Id FROM surdim_List WHERE Name = :name AND Visible = 0 LIMIT 1";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':name', $name);
        $checkStmt->execute();
        $existingResource = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingResource) {
            // Si une ressource existe avec Visible = 0, on la réactive
            $reactivateQuery = "UPDATE surdim_List SET Visible = 1 WHERE Id = :id";
            $reactivateStmt = $db->prepare($reactivateQuery);
            $reactivateStmt->bindParam(':id', $existingResource['Id']);
            $reactivateStmt->execute();
        } else {
            // Si aucune ressource n'existe avec Visible = 0, on ajoute une nouvelle ressource
            $countQuery = "SELECT COUNT(*) FROM surdim_List WHERE Name = :name";
            $countStmt = $db->prepare($countQuery);
            $countStmt->bindParam(':name', $name);
            $countStmt->execute();
            $count = $countStmt->fetchColumn() + 1;

            $addQuery = "INSERT INTO surdim_List (Name, Name_Vue, Is_Titre, Visible) VALUES (:name, :name_vue, :is_titre, 1)";
            $addStmt = $db->prepare($addQuery);
            $addStmt->bindParam(':name', $name);
            $name_vue = ($count == 1) ? $name : (string)$count;
            $addStmt->bindParam(':name_vue', $name_vue);
            $is_titre = ($count == 1) ? 'true' : 'false';
            $addStmt->bindParam(':is_titre', $is_titre);
            $addStmt->execute();
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Traitement de l'incrémentation de la quantité
if (isset($_POST['increment'])) {
    $name = $_POST['name'];

    // Vérifier s'il y a une ressource avec Visible = 0 pour ce nom
    $checkQuery = "SELECT Id FROM surdim_List WHERE Name = :name AND Visible = 0 ORDER BY Name_Vue DESC LIMIT 1";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':name', $name);
    $checkStmt->execute();
    $existingResource = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existingResource) {
        // Réactiver la ressource avec Visible = 0
        $reactivateQuery = "UPDATE surdim_List SET Visible = 1 WHERE Id = :id";
        $reactivateStmt = $db->prepare($reactivateQuery);
        $reactivateStmt->bindParam(':id', $existingResource['Id']);
        $reactivateStmt->execute();
    } else {
        // Ajouter une nouvelle ressource avec Name_Vue suivant
        $countQuery = "SELECT COUNT(*) FROM surdim_List WHERE Name = :name";
        $countStmt = $db->prepare($countQuery);
        $countStmt->bindParam(':name', $name);
        $countStmt->execute();
        $count = $countStmt->fetchColumn() + 1;

        $addQuery = "INSERT INTO surdim_List (Name, Name_Vue, Is_Titre, Visible) VALUES (:name, :name_vue, 'false', 1)";
        $addStmt = $db->prepare($addQuery);
        $addStmt->bindParam(':name', $name);
        $name_vue = (string)$count;
        $addStmt->bindParam(':name_vue', $name_vue);
        $addStmt->execute();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Traitement de la réduction de la quantité
if (isset($_POST['decrement'])) {
    $name = $_POST['name'];

    // Trouver l'ID de la ressource avec le plus grand Name_Vue qui est encore visible
    $decrementQuery = "SELECT Id FROM surdim_List WHERE Name = :name AND Visible = 1 ORDER BY CAST(Name_Vue AS INTEGER) DESC LIMIT 1";
    $decrementStmt = $db->prepare($decrementQuery);
    $decrementStmt->bindParam(':name', $name);
    $decrementStmt->execute();
    $resourceToDeactivate = $decrementStmt->fetch(PDO::FETCH_ASSOC);

    if ($resourceToDeactivate) {
        // Désactiver cette ressource
        $updateQuery = "UPDATE surdim_List SET Visible = 0 WHERE Id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':id', $resourceToDeactivate['Id']);
        $updateStmt->execute();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Traitement de la suppression de la ressource
if (isset($_POST['delete'])) {
    $name = $_POST['name'];

    // Supprimer toutes les ressources avec le même nom
    $deleteQuery = "DELETE FROM surdim_List WHERE Name = :name";
    $deleteStmt = $db->prepare($deleteQuery);
    $deleteStmt->bindParam(':name', $name);
    $deleteStmt->execute();

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Ressources</title>
    <script src="js/jquery-3.6.0.min.js"></script>
    <style>
		body{
			font-family: calibri;
		}
		.resource-table {
			 width: 100%;
			 border-collapse: separate;
			/* Utilisez "separate" au lieu de "collapse" pour respecter le border-radius */
			 border-spacing: 0;
			/* Éliminer les espaces entre les cellules */
			 margin-bottom: 20px;
			 border-radius: 10px;
			/* Arrondir les coins supérieurs et inférieurs du tableau */
			 overflow: hidden;
			/* S'assurer que les coins restent arrondis */
			 border: 1px solid #ddd;
			/* Bordure extérieure du tableau */
			box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
		}
		 .resource-table th, .resource-table td {
			 border: none;
			/* Supprimez les bordures de chaque cellule pour éviter les conflits avec le border-radius */
			 padding: 8px;
			 border-bottom: 1px solid #ddd;
			/* Ajouter une bordure en bas des cellules pour garder les séparations */
		}
		 .resource-table th {
			 background-color: #f2f2f2;
			 text-align: left;
		}
		 .resource-table tr:last-child td {
			 border-bottom: none;
			/* Supprimer la bordure inférieure de la dernière rangée */
		}
		 .grayed-out {
			 background-color: #d3d3d3;
		}
		 .red-background {
			 background-color: #ffcccc;
		}
		 .menu-filter {
			 margin-bottom: 20px;
			 margin-top: 25px;
		}
		 .menu-filter button {
			 margin-right: 5px;
		}
		 h3 {
			 margin-top: 14px;
			 margin-bottom: 1px;
		}
		 .form-container {
			 max-width: 410px;
			/**margin: 0 auto;
			*/
			 background-color: #fff;
			 padding: 2px 20px;
			 border-radius: 8px;
			 box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
		}
		 .form-container h2 {
			 text-align: center;
			 margin-bottom: 18px;
			 font-size: 1.5em;
			 color: #333;
			 margin-top: 5px;
		}
		 .form-row {
			 display: flex;
			 justify-content: space-between;
			 margin-bottom: 15px;
		}
		 .form-group {
			 flex: 1;
			 margin-right: 10px;
		}
		 .form-group:last-child {
			 margin-right: 0;
		}
		 label {
			 display: block;
			 font-weight: bold;
			 margin-bottom: 5px;
			 font-size: 0.9em;
			 color: #555;
		}
		 input[type="text"]{
			 width: 240px;
			 padding: 10px;
			 font-size: 1em;
			 border: 1px solid #ccc;
			 border-radius: 4px;
			 box-sizing: border-box;
			 transition: border-color 0.3s;
		}
		 input[type="number"] {
			 width: 70px;
			 padding: 10px;
			 font-size: 1em;
			 border: 1px solid #ccc;
			 border-radius: 4px;
			 box-sizing: border-box;
			 transition: border-color 0.3s;
		}
		 input[type="text"]:focus, input[type="number"]:focus {
			 border-color: #888;
			 outline: none;
		}
		 button[name="add_resource"] {
			 flex-basis: 100%;
			 padding: 10px;
			 font-size: 1.1em;
			 background-color: #28a745;
			 color: #fff;
			 border: none;
			 border-radius: 4px;
			 cursor: pointer;
			 transition: background-color 0.3s;
			 height: 46px;
			 margin-top: 19px;
		}
		 button[name="add_resource"]:hover {
			 background-color: #218838;
		}
		.Container {
			 margin: auto;
			 width: 500px;
		}
    </style>
</head>
<body>
<div class="Container">
<form method="POST">
<div class="form-container">
        <h2>Ajouter une Nouvelle Ressource</h2>
        <div class="form-row">
            <div class="form-group">
                <label for="name">Nom :</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="quantity">Quantité :</label>
                <input type="number" id="quantity" name="quantity" min="1" value="1" required>
            </div>
            <button type="submit" name="add_resource">Ajouter</button>
        </div>
    </div>
</form>
    <div class="menu-filter">
        <?php
    $letters = [];
    foreach ($resources as $resource) {
        $firstLetter = strtoupper($resource['Name'][0]);
        if (!in_array($firstLetter, $letters)) {
            $letters[] = $firstLetter;
        }
    }

    // Trier les lettres par ordre alphabétique
    sort($letters);

    // Afficher les boutons
    foreach ($letters as $letter) {
        echo '<button class="filter-btn" data-letter="' . $letter . '">' . $letter . '</button>';
    }
?>
        <button class="filter-btn" data-letter="*">*</button>
    </div>
    
    <div id="resource-container" style="width: 450px;">
        <?php 
        $currentLetter = '';
        foreach ($resources as $index => $resource) {
            $firstLetter = strtoupper($resource['Name'][0]);
            if ($firstLetter !== $currentLetter) {
                if ($currentLetter !== '') echo '</table>';
                echo '<div class="letter" data-letter="' . $firstLetter . '"><h3>' . $firstLetter . '</h3></div>';
                echo '<table class="resource-table" data-letter="' . $firstLetter . '">';
                echo '<tr><th style="width:300px">Nom</th><th style="width:50px">Quantité</th><th style="width:50px">Actions</th></tr>';
                $currentLetter = $firstLetter;
            }

            // Détermine la classe CSS en fonction de la quantité visible
            $rowClass = '';
            if ($resource['QuantiteVisible'] == 0) {
                $rowClass = 'red-background';
            } elseif ($resource['QuantiteVisible'] < $resource['QuantiteTotale']) {
                $rowClass = 'grayed-out';
            }

            echo '<tr class="' . $rowClass . '" data-letter="' . $firstLetter . '">';
            echo '<td>' . htmlspecialchars(mb_strtoupper($resource['Name'], 'UTF-8')) . '</td>';
            echo '<td style="text-align:center"><strong style="font-size:20px">' . htmlspecialchars($resource['QuantiteVisible']) . '</strong> / <strong style="color:#4ca623">' . htmlspecialchars($resource['QuantiteTotale']) . '</strong></td>';
            echo '<td  style="text-align:center">';
            echo '<form method="POST" style="display:inline;">';
            echo '<input type="hidden" name="name" value="' . htmlspecialchars($resource['Name']) . '">';
            echo '<button type="submit" name="increment">+</button>';
            if ($resource['QuantiteVisible'] > 0) {
                echo ' <button type="submit" name="decrement">-</button>';
            }
            if ($resource['QuantiteVisible'] == 0) {
                echo ' <button type="submit" name="delete">X</button>';
            }
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>'; // Ferme le dernier tableau
        ?>
    </div>
</div>
    <script>
        $(document).ready(function(){
            $('.filter-btn').on('click', function(){
                var letter = $(this).data('letter');
                if (letter === '*') {
                    $('table[data-letter], .letter').show();
                } else {
                    $('table[data-letter], .letter').hide();
                    $('table[data-letter="' + letter + '"], .letter[data-letter="' + letter + '"]').show();
                }
            });
        });
    </script>
</body>
</html>
