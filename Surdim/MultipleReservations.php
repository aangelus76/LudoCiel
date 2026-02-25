<?php
header('Content-Type: application/json');

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

try {
    // Vérifier que les données POST existent
    $postData = json_decode(file_get_contents('php://input'), true);
    
    if (!$postData || !isset($postData['resourceIds']) || !isset($postData['startDate']) || !isset($postData['endDate']) || !isset($postData['user'])) {
        throw new Exception("Données manquantes");
    }

    $resourceIds = $postData['resourceIds']; // Tableau d'IDs
    $startDate = convertDate($postData['startDate']);
    $endDate = convertDate($postData['endDate']);
    $user = strtolower_utf8($postData['user']);
    $phone = isset($postData['phone']) ? $postData['phone'] : '';
    $info = isset($postData['info']) && $postData['info'] !== '' ? strtolower_utf8($postData['info']) : '';

    $createdCount = 0;
    $errors = array();

    // Boucle sur chaque ressource pour créer une réservation
    foreach ($resourceIds as $resourceId) {
        try {
            // Récupérer la priorité de la ressource depuis surdim_List
            $queryPriority = "SELECT Id FROM surdim_List WHERE Id = :resourceId";
            $stmtPriority = PDO_Execute($queryPriority, array($resourceId));
            
            if ($stmtPriority) {
                $DataInsert = "INSERT INTO surdim_Rent (Id, Id_List, Priority, User, StartDate, EndDate, Info, Phone, DateSave) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $DataSet = PDO_Execute($DataInsert, array(NULL, $resourceId, $resourceId, ucfirst($user), $startDate, $endDate, $info, $phone, date("d-m-Y")));
                
                if ($DataSet) {
                    $createdCount++;
                } else {
                    $errors[] = "Erreur lors de la création de la réservation pour la ressource ID: " . $resourceId;
                }
            } else {
                $errors[] = "Ressource non trouvée: " . $resourceId;
            }
        } catch (Exception $e) {
            $errors[] = "Erreur pour ressource " . $resourceId . ": " . $e->getMessage();
        }
    }

    echo json_encode(array(
        'success' => true,
        'created' => $createdCount,
        'total' => count($resourceIds),
        'errors' => $errors
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>
