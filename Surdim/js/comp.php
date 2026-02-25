<?php
	$fileContent = file_get_contents('dx.all.21.2.7.js');
$compressedContent = gzencode($fileContent, 9); // 9 est le niveau de compression (meilleure compression)

header('Content-Encoding: gzip');
header('Content-Type: application/javascript');
header('Content-Length: ' . strlen($compressedContent));

echo $compressedContent;

?>