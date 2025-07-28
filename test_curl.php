<?php
$ch = curl_init("https://api.paydunya.com");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if(curl_errno($ch)) {
    echo "Erreur cURL : " . curl_error($ch);
} else {
    echo "Code HTTP : $http_code<br>";
    echo "Réponse : " . htmlspecialchars($response) . "<br>";
}
curl_close($ch);
?>
