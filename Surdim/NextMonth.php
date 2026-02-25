<?php
header('content-type:application/json');

function convertEncodedDateToPHPDate($encodedDate) {
	$decodedDate = urldecode($encodedDate);
	$datePart = substr($decodedDate, 0, strpos($decodedDate, " GMT"));
	$timestamp = strtotime($datePart);
	$formattedDate = date('Y-m-d H:i:s', $timestamp);
	return $formattedDate;
}

function logMessage($message) {
    $logEntry = "[" . date('Y-m-d H:i:s') . "] Message: " . $message . "\n";
    file_put_contents('log.txt', $logEntry, FILE_APPEND);
}

$db_file = "Surdim.db";
$pdo = new PDO("sqlite:$db_file");

if (isset($_GET['Mode'])) {
    if ($_GET['Mode'] == "GetItem") {
/*      $newDate = date('Y-m-d', strtotime('first day of next month'));
        $date_debut_min = date('Y-m-01', strtotime($newDate));
        $date_debut_max = date('Y-m-t', strtotime($newDate)); */
		$currentDate = date('Y-m-d'); // Date courante
		$nextMonth = date('Y-m-d', strtotime('first day of this month'));
		$twoMonthsLater = date('Y-m-d', strtotime('+2 months', strtotime($nextMonth)));
		$date_debut_min = $nextMonth;
		$date_debut_max = date('Y-m-d', strtotime('last day of +2 month', strtotime($nextMonth)));

        $sql = "SELECT * FROM surdim_Rent WHERE DATE(startDate) BETWEEN :date_debut_min AND :date_debut_max AND Priority = :select ORDER BY startDate";
        $stmt = $pdo->prepare($sql);

        // Lier les valeurs des paramètres avec les dates fournies
        $stmt->bindParam(':date_debut_min', $date_debut_min);
        $stmt->bindParam(':date_debut_max', $date_debut_max);
        $stmt->bindParam(':select', $_GET['Select']);
    } 
	else if($_GET['Mode'] == "GetError"){
		$dateStart = $_GET['DateStart'];
		$dateEnd =$_GET['DateEnd'];
		$date_min = date('Y-m-d', strtotime($dateStart));
		$date_max = date('Y-m-d', strtotime($dateEnd));
		
		
		$sql = "SELECT * FROM surdim_Rent WHERE DATE(startDate) BETWEEN :date_min AND :date_max AND Priority = :select";
        $stmt = $pdo->prepare($sql);
		$stmt->bindParam(':date_min', $date_min);
        $stmt->bindParam(':date_max', $date_max);
        $stmt->bindParam(':select', $_GET['Select']);
		
		logMessage("Requette pour recherche de conflit! \n ## DateMin : ".$date_min." \n ## DateMax : ".$date_max." \n ##Select : ".$_GET['Select']);
		
	}
	else {
		$dateSelected = convertEncodedDateToPHPDate($_GET['Select']);
		$date_debut_min = date('Y-m-d', strtotime($dateSelected));		
		if($_GET['Step'] == "In"){
			$sql = "SELECT r.*, l.*
			FROM surdim_Rent r
			LEFT JOIN surdim_List l ON r.Id_List = l.Id
			WHERE DATE(r.endDate) = :date_debut_min ORDER BY r.Id_List";
		}
		else{
			$sql = "SELECT r.*, l.*
			FROM surdim_Rent r
			LEFT JOIN surdim_List l ON r.Id_List = l.Id
			WHERE DATE(r.startDate) = :date_debut_min ORDER BY r.Id_List";
		}
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(':date_debut_min', $date_debut_min, PDO::PARAM_STR);
		//Liste des prets du jour
/* 		$dateSelected = convertEncodedDateToPHPDate($_GET['Select']);
		$date_debut_min = date('Y-m-d', strtotime($dateSelected));

		$sql = "SELECT r.*, l.*
			FROM surdim_Rent r
			LEFT JOIN surdim_List l ON r.Priority = l.Priority
			WHERE DATE(r.startDate) = :date_debut_min ORDER BY r.Priority";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(':date_debut_min', $date_debut_min, PDO::PARAM_STR); */
		
		//Liste retour du jour
/* 		$dateSelected = convertEncodedDateToPHPDate($_GET['Select']);
		$date_debut_min = date('Y-m-d', strtotime($dateSelected));

		$sql = "SELECT r.*, l.*
			FROM surdim_Rent r
			LEFT JOIN surdim_List l ON r.Priority = l.Priority
			WHERE DATE(r.endDate) = :date_debut_min ORDER BY r.Priority";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(':date_debut_min', $date_debut_min, PDO::PARAM_STR); */
    }
}
//logMessage("Date d'utilsation Min: ".$date_debut_min." / Date d'utilisation Max : ".$date_debut_max." / Date de NewDate : ".$currentDate." / Date actuelle : ".date('Y-m-d'));
// Exécuter la requête
$stmt->execute();

// Récupérer les résultats de la recherche
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
//logMessage(count($results));
// Afficher les résultats
if (count($results) > 0) {

	$currentId = 1;
	
	if ($_GET['Mode'] == "GetList") {
		$groupedResults = [];

		foreach ($results as $item) {
			$user = $item['User'];
			if (!isset($groupedResults[$user])) {
				$groupedResults[$user] = $item;
			} else {
				$groupedResults[$user]['Name'] .= ', ' . $item['Name'];
			}
		}
		if (count($groupedResults) > 0) {
			$currentId = 1;
			echo '[';
				foreach ($groupedResults as $row) {
					echo '{';
					echo '"key":"'.ucfirst($row['User']).'", ';
					$Names = explode(', ', $row['Name']);
					//items: ['Carrome', 'Carrome', 'PitchCar', 'Bonk', 'Passe trappe'],
					echo '"items":[';
					$Passe = 1;
					foreach($Names as $Name){
						if($Passe>= Count($Names)){
							echo '"'.$Name.'"';
							$Passe = 1;
						}
						else{
							echo '"'.$Name.'",';
						}
						$Passe++;
					}
					echo ']';
					if($currentId >= Count($groupedResults)){
						echo "}";
					}
					else{
						echo "},";
					}
					$currentId++;
				}
			echo ']';
		} 
		
	}
	else if ($_GET['Mode'] == "GetError") {
		echo '[{"Conflict":true}]';
	}
	else{
		$groupedResults = [];
		foreach ($results as $item) {
			$user = $item['User'];
			if (!isset($groupedResults[$user])) {
				$groupedResults[$user] = $item;
			} else {
				$dateTimeS = new DateTime($item['StartDate']);
				$dateTimeE = new DateTime($item['EndDate']);
				
				$groupedResults[$user]['StartDate'] .= ', Du ' . $dateTimeS->format('d/m').' au '.$dateTimeE->format('d/m'); //$item['startDate']
			}
		}
		if (count($groupedResults) > 0) {
			$currentId = 1;
			echo '[';
			foreach ($results as $row) {
				echo '{';
				echo '"key":"' . ucfirst($row['User']) . '", ';
				$Dates = explode(', ', $row['StartDate']);
				sort($Dates);
				//items: ['Carrome', 'Carrome', 'PitchCar', 'Bonk', 'Passe trappe'],
				echo '"items":[';
				$Passe = 1;
				foreach($Dates as $Name){
					$dateTimeS = new DateTime($row['StartDate']);
					$dateTimeE = new DateTime($row['EndDate']);
					if($Passe>= Count($Dates)){
						echo '"Du '.$dateTimeS->format('d/m').' au '.$dateTimeE->format('d/m').'"';
						$Passe = 1;
					}
					else{
						echo '"'.$Name.'",';
					}
					$Passe++;
				}
				echo ']';
				if ($currentId >= Count($results)) {
					echo "}";
				}
				else {
					echo "},";
				}
				$currentId++;
			}
			echo ']';
		}
	}
}

?>