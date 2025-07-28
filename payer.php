<?php
require_once 'config.php';
session_start();

// Clés de test PayDunya
define('PAYDUNYA_API_KEY', 'test_private_nCXj4So54nWBCozGiMadf6V6hMF');
define('PAYDUNYA_PUBLIC_KEY', 'test_public_U2HfIlJ1j57hIIMmrKPFnXR4OQj');
define('PAYDUNYA_TOKEN', 'KryvYOBuKqVOQCAiFKav');
define('PAYDUNYA_MODE', 'test'); // ou 'live'

// Récupération de l'ID commande
$commande_id = $_GET['commande'] ?? null;
if (!$commande_id) die("ID commande manquant");

// Récupération des infos de la commande
$stmt = $conn->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$commande_id]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$commande || $commande['mode_paiement'] !== 'en ligne') {
    die("Commande invalide");
}


// Création de la transaction PayDunya
$montant_fcfa = intval($commande['total']);
$payload = [
    "invoice" => [
        "items" => [
            [
                "name" => "Commande #" . $commande_id,
                "quantity" => 1,
                "unit_price" => $montant_fcfa,
                "total_price" => $montant_fcfa
            ]
        ],
        "total_amount" => $montant_fcfa,
        "description" => "Paiement de la commande #" . $commande_id
    ],
    "store" => [
        "name" => "JUNGLE",
        "tagline" => "Livraison rapide",
        "postal_address" => "Dakar, Sénégal",
        "phone" => "771234567",
        "website_url" => "https://votresite.com"
    ],
"actions" => [
    "cancel_url" => "https://abcd1234.ngrok-free.app/restaurant/echec.php",
    "return_url" => "https://abcd1234.ngrok-free.app/restaurant/confirmation.php?commande=$commande_id",
    "callback_url" => "https://abcd1234.ngrok-free.app/restaurant/paydunya_callback.php"
]


];

// Initialiser la session CURL
$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://app.paydunya.com/api/v1/checkout-invoice/create",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "PAYDUNYA-MASTER-KEY: " . PAYDUNYA_TOKEN,
        "PAYDUNYA-PRIVATE-KEY: " . PAYDUNYA_API_KEY,
        "PAYDUNYA-PUBLIC-KEY: " . PAYDUNYA_PUBLIC_KEY,
        "PAYDUNYA-TOKEN: " . PAYDUNYA_TOKEN
    ]
]);

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$result = json_decode($response, true);

// Vérifie si la requête a réussi
if ($http_code === 200 && isset($result['response_code']) && $result['response_code'] == "00") {
    $payment_url = $result['invoice_url'] ?? $result['response_text'];
    header("Location: $payment_url");
    exit;
} else {
    echo "<h2>Erreur de paiement</h2>";
    echo "<pre>" . print_r($result, true) . "</pre>";
}
echo "<h2>Erreur de paiement</h2>";
echo "<strong>Code HTTP :</strong> $http_code<br>";
echo "<strong>Réponse API :</strong><pre>" . print_r($result, true) . "</pre>";

?>
