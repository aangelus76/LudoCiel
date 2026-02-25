<?php
header('Content-Type: application/json'); // Important : déclarer que c'est du JSON

include "_pdo.php";
$db_file = "Surdim.db";
PDO_Connect("sqlite:$db_file");

function strtolower_utf8($string){
	$convert_to=array("a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z","à","á","â","ã","ä","å","æ","ç","è","é","ê","ë","ì","í","î","ï","ð","ñ","ò","ó","ô","õ","ö","ø","ù","ú","û","ü","ý","а","б","в","г","д","е","ё","ж","з","и","й","к","л","м","н","о","п","р","с","т","у","ф","х","ц","ч","ш","щ","ъ","ы","ь","э","ю","я","`");
	$convert_from=array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","À","Á","Â","Ã","Ä","Å","Æ","Ç","È","É","Ê","Ë","Ì","Í","Î","Ï","Ð","Ñ","Ò","Ó","Ô","Õ","Ö","Ø","Ù","Ú","Û","Ü","Ý","А","Б","В","Г","Д","Е","Ё","Ж","З","И","Й","К","Л","М","Н","О","П","Р","С","Т","У","Ф","Х","Ц","Ч","Ш","Щ","Ъ","Ъ","Ь","Э","Ю","Я","'");
	return str_replace($convert_from,$convert_to,$string);
}

function convertDate($dateString) {
    $dateWithoutGMT = substr($dateString, 0, strpos($dateString, "GMT") - 1);
    $timestamp = strtotime($dateWithoutGMT);

    if ($timestamp !== false) {
        $formattedDate = date('Y-m-d 12:00:00', $timestamp);
        return $formattedDate;
    } else {
        return "Erreur de conversion de la date.";
    }
}

if (!empty($_GET)){
	$requiredFields = ['StartDate', 'EndDate', 'User', 'Info', 'ControlType'];
	if (array_diff($requiredFields, array_keys($_GET)) === []) {

		$GameID = $_GET['GameID'];
		$StartDate = $_GET['StartDate'];
		$StartDateF = convertDate($StartDate);
		$EndDate = $_GET['EndDate'];
		$EndDateF = convertDate($EndDate);
		$Commentary = $_GET['Info'] == "Nan" ? "" : strtolower_utf8($_GET['Info']);
		$Phone = isset($_GET['Phone']) ? $_GET['Phone'] : null;
		$User = strtolower_utf8($_GET['User']);
		$Priority = isset($_GET['Priority']) ? $_GET['Priority'] : null;
		$Methode = $_GET['ControlType'];
		
		try {
			if($Methode == "Add"){
				$DataInsert = "INSERT INTO surdim_Rent (Id, Id_List, Priority, User, StartDate, EndDate, Info, Phone, DateSave) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
				$DataSet = PDO_Execute($DataInsert, array(NULL, $GameID, $Priority, ucfirst($User), $StartDateF, $EndDateF, $Commentary, $Phone, date("d-m-Y")));
				
				// Retour JSON de succès
				echo json_encode([
					'success' => true,
					'message' => 'Réservation ajoutée avec succès',
					'action' => 'add'
				]);
			}
			elseif($Methode == "Update"){
				if(isset($_GET['IsReturn']) && $_GET['IsReturn'] == true) {
					$DataUpdate = "UPDATE surdim_Rent 
								  SET EndDate='".$EndDateF."',
									  ReturnedDate='".date('Y-m-d H:i:s')."'
								  WHERE Id = '".$GameID."';";
				} else {
					$DataUpdate = "UPDATE surdim_Rent 
								  SET Id_List='".$Priority."',
									  User='".ucfirst($User)."',
									  StartDate='".$StartDateF."',
									  EndDate='".$EndDateF."',
									  Info='".$Commentary."',
									  Phone='".$Phone."'
								  WHERE Id = '".$GameID."';";
				}
				$DataSet = PDO_Execute($DataUpdate);
				
				// Retour JSON de succès
				echo json_encode([
					'success' => true,
					'message' => 'Réservation modifiée avec succès',
					'action' => 'update'
				]);
			}
			elseif($Methode == "Return"){
				$DataUpdate = "UPDATE surdim_Rent 
							  SET Status = 1,
								  EndDate = '".$EndDateF."'
							  WHERE Id = '".$GameID."';";
				$DataSet = PDO_Execute($DataUpdate);
				
				// Retour JSON de succès
				echo json_encode([
					'success' => true,
					'message' => 'Retour enregistré avec succès',
					'action' => 'return'
				]);
			}
			elseif($Methode == "Delete"){
				$DataDelete = "DELETE FROM surdim_Rent WHERE id = '".$_GET['GameID']."';";
				$DataSet = PDO_Execute($DataDelete);
				
				// Retour JSON de succès
				echo json_encode([
					'success' => true,
					'message' => 'Réservation supprimée avec succès',
					'action' => 'delete'
				]);
			}
			else{
				// Méthode inconnue
				echo json_encode([
					'success' => false,
					'message' => 'Méthode de contrôle inconnue',
					'error' => 'UNKNOWN_METHOD'
				]);
			}
		} catch (Exception $e) {
			// Erreur lors de l'exécution
			echo json_encode([
				'success' => false,
				'message' => 'Erreur lors de l\'opération',
				'error' => $e->getMessage()
			]);
		}
	} else {
		// Champs requis manquants
		echo json_encode([
			'success' => false,
			'message' => 'Champs requis manquants',
			'error' => 'MISSING_FIELDS'
		]);
	}
} else {
	// Aucune donnée reçue
	echo json_encode([
		'success' => false,
		'message' => 'Aucune donnée reçue',
		'error' => 'NO_DATA'
	]);
}
?>