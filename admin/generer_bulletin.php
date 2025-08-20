<?php
require_once '../config.php';
require_once '../vendor/autoload.php'; // TCPDF

try {
    if (empty($_POST['employe_id']) || empty($_POST['mois_annee'])) {
        throw new Exception('Employé ou mois manquant.');
    }

    $employe_id = intval($_POST['employe_id']);
    $mois_annee = $_POST['mois_annee'];

    // 1. Calculer le salaire net
    $details = calculerSalaireNet($conn, $employe_id, $mois_annee);

    // 2. Générer le bulletin PDF
    $pdfContent = genererBulletinPaie($details, $conn);

    // 3. Enregistrer le bulletin dans la base de données
    $bulletin_id = enregistrerBulletinPaie($conn, $details);

    // 4. Retourner le PDF en base64 pour téléchargement
    sendJsonResponse([
        'success' => true,
        'pdf' => base64_encode($pdfContent),
        'bulletin_id' => $bulletin_id
    ]);

} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
