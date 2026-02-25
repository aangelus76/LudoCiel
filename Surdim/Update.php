<?php
function UpdateDB_Priority(){// copie le champ Priority dans Priority_Old
$db_file = "Surdim.db";
$pdo = new PDO("sqlite:$db_file");

try {
    $pdo->beginTransaction();

    $sql = "UPDATE surdim_List SET Priority_Old = Priority";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollback(); // En cas d'erreur, annuler la transaction
    die("Erreur : " . $e->getMessage());
}

}
	
	//UpdateDB_Priority();


/* ///AJOUT DE 1 OU PLUSIEUR ITEMS DANS LA BDD
 $db_file = "SurdimTest.db";
$pdo = new PDO("sqlite:$db_file");

// Nombre d'entrées à ajouter
$nombreAjouts = 1; // Par exemple, ajouter 4 entrées
$MyTitre = "Lancer d'anneaux";

$pdo->beginTransaction();

$insertQuery = "INSERT INTO surdim_List (Name, Name_Vue) VALUES (:name, :name_vue)";
$insertStatement = $pdo->prepare($insertQuery);

for ($i = 1; $i <= $nombreAjouts; $i++) {
    $nouveauTitre = $MyTitre;
    $nameVue = ($i === 1) ? $MyTitre : $i;

    $insertStatement->bindValue(':name', $nouveauTitre, PDO::PARAM_STR);
    $insertStatement->bindValue(':name_vue', $nameVue, PDO::PARAM_STR);
    $insertStatement->execute();
}

$selectQuery = "SELECT Id FROM surdim_List ORDER BY Name";
$result = $pdo->query($selectQuery);

$priority = 1;
$updateQuery = "UPDATE surdim_List SET Priority = :priority WHERE Id = :id";
$updateStatement = $pdo->prepare($updateQuery);

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $updateStatement->bindValue(':priority', $priority, PDO::PARAM_INT);
    $updateStatement->bindValue(':id', $row['Id'], PDO::PARAM_INT);
    $updateStatement->execute();

    $priority++;
}

$pdo->commit();

$pdo = null;

echo "<strong>Nouveaux titres ajoutés et priorités mises à jour avec succès!!</strong>";  */
//   ##############  V2 ############################
/*
$db_file = "Surdim.db";
$pdo = new PDO("sqlite:$db_file");

try {
    $pdo->beginTransaction();

    // Nombre d'entrées à ajouter
    $nombreAjouts = 3; // Par exemple, ajouter 4 entrées
    $MyTitre = "Cornhole";

    $insertQuery = "INSERT INTO surdim_List (Name, Name_Vue, Is_Titre) VALUES (:name, :name_vue, :Is_Titre)";
    $insertStatement = $pdo->prepare($insertQuery);

    for ($i = 1; $i <= $nombreAjouts; $i++) {
        $nouveauTitre = $MyTitre;
        $nameVue = ($i === 1) ? $MyTitre : $i;
        $IsTitre = ($i === 1) ? 'true' : 'false'; 
		
		//$nameVue = "2";
        //$IsTitre = 'false';

        $insertStatement->bindValue(':name', $nouveauTitre, PDO::PARAM_STR);
        $insertStatement->bindValue(':name_vue', $nameVue, PDO::PARAM_STR);
        $insertStatement->bindValue(':Is_Titre', $IsTitre, PDO::PARAM_STR);
        $insertStatement->execute();
    }

    // Mise à jour des priorités dans surdim_List
    $selectQuery = "SELECT Id FROM surdim_List ORDER BY Name";
    $result = $pdo->query($selectQuery);

    $priority = 0;
    $updateQuery = "UPDATE surdim_List SET Priority = :priority WHERE Id = :id";
    $updateStatement = $pdo->prepare($updateQuery);

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $updateStatement->bindValue(':priority', $priority, PDO::PARAM_INT);
        $updateStatement->bindValue(':id', $row['Id'], PDO::PARAM_INT);
        $updateStatement->execute();

        $priority++;
    }

    // Mise à jour des priorités dans surdim_rent
    $updateRentQuery = "UPDATE surdim_Rent 
                        SET Priority = (SELECT surdim_List.Priority 
                                        FROM surdim_List 
                                        WHERE surdim_Rent.Id_List = surdim_List.Id)";
    $pdo->exec($updateRentQuery);

    $pdo->commit();

    echo "<strong>Nouveaux titres ajoutés et priorités mises à jour avec succès !!</strong>";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Erreur PDO : " . $e->getMessage();
} finally {
    $pdo = null;
}



/* // SUPRESSION DE 1 OU PLUSIEUR ITEMS DE TITRE
 $db_file = "Surdim.db";
$pdo = new PDO("sqlite:$db_file");
$MyTitre = "Parachute";
// Valeurs de Name_Vue à supprimer pour le titre "DDDD"
$valuesASupprimer = array("Parachute"); // array("2","3");

$pdo->beginTransaction();

// Récupérer les identifiants des enregistrements à supprimer
$selectQuery = "SELECT Id FROM surdim_List WHERE Name = '".$MyTitre."' AND Name_Vue IN (" . implode(",", array_fill(0, count($valuesASupprimer), "?")) . ")";
$selectStatement = $pdo->prepare($selectQuery);

foreach ($valuesASupprimer as $index => $value) {
    $selectStatement->bindValue($index + 1, $value, PDO::PARAM_STR);
}

$selectStatement->execute();
$idsASupprimer = $selectStatement->fetchAll(PDO::FETCH_COLUMN);

// Supprimer les enregistrements correspondants
$deleteQuery = "DELETE FROM surdim_List WHERE Id IN (" . implode(",", $idsASupprimer) . ")";
$pdo->exec($deleteQuery);

// Sélectionner et trier les titres par ordre alphabétique
$selectQuery = "SELECT Id FROM surdim_List ORDER BY Name";
$result = $pdo->query($selectQuery);

$priority = 0;
$updateQuery = "UPDATE surdim_List SET Priority = :priority WHERE Id = :id";
$updateStatement = $pdo->prepare($updateQuery);

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $updateStatement->bindValue(':priority', $priority, PDO::PARAM_INT);
    $updateStatement->bindValue(':id', $row['Id'], PDO::PARAM_INT);
    $updateStatement->execute();

    $priority++;
}

$pdo->commit();

$pdo = null;

echo "Entrées ".$MyTitre." supprimées et priorités mises à jour avec succès.";  */
	

//########## V3 ##################//
$db_file = "Surdim.db";
$pdo = new PDO("sqlite:$db_file");

try {
    $pdo->beginTransaction();

    // Nombre d'entrées à ajouter
    $nombreAjouts = 2; // Par exemple, ajouter 4 entrées
    $MyTitre = "Smak";

    $insertQuery = "INSERT INTO surdim_List (Name, Name_Vue, Is_Titre) VALUES (:name, :name_vue, :Is_Titre)";
    $insertStatement = $pdo->prepare($insertQuery);

    // Vérifier si le titre existe déjà dans Name_Vue de la base surdim_List
    $existingTitleQuery = "SELECT COUNT(*) FROM surdim_List WHERE Name_Vue = :name_vue";
    $existingTitleStatement = $pdo->prepare($existingTitleQuery);
    $existingTitleStatement->bindValue(':name_vue', $MyTitre, PDO::PARAM_STR);
    $existingTitleStatement->execute();
    $titleExists = $existingTitleStatement->fetchColumn() > 0;

    for ($i = 1; $i <= $nombreAjouts; $i++) {
        $nouveauTitre = $MyTitre;
        $nameVue = ($i === 1 && !$titleExists) ? $MyTitre : ($i + 1); // Ajout de 1 pour que Name_Vue commence à 2
        $IsTitre = ($i === 1 && !$titleExists) ? 'true' : 'false';

        $insertStatement->bindValue(':name', $nouveauTitre, PDO::PARAM_STR);
        $insertStatement->bindValue(':name_vue', $nameVue, PDO::PARAM_STR);
        $insertStatement->bindValue(':Is_Titre', $IsTitre, PDO::PARAM_STR);
        $insertStatement->execute();
    }

    // Mise à jour des priorités dans surdim_List
    $selectQuery = "SELECT Id FROM surdim_List ORDER BY Name";
    $result = $pdo->query($selectQuery);

    $priority = 0;
    $updateQuery = "UPDATE surdim_List SET Priority = :priority WHERE Id = :id";
    $updateStatement = $pdo->prepare($updateQuery);

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $updateStatement->bindValue(':priority', $priority, PDO::PARAM_INT);
        $updateStatement->bindValue(':id', $row['Id'], PDO::PARAM_INT);
        $updateStatement->execute();

        $priority++;
    }

    // Mise à jour des priorités dans surdim_rent
    $updateRentQuery = "UPDATE surdim_Rent 
                        SET Priority = (SELECT surdim_List.Priority 
                                        FROM surdim_List 
                                        WHERE surdim_Rent.Id_List = surdim_List.Id)";
    $pdo->exec($updateRentQuery);

    $pdo->commit();

    echo "<strong>Nouveaux titres ajoutés et priorités mises à jour avec succès !!</strong>";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Erreur PDO : " . $e->getMessage();
} finally {
    $pdo = null;
}

?>
