<?php
// get_horaires_jour.php

header('Content-Type: application/json');
require_once 'config.php'; // Connexion à la BDD

// Traduction des jours anglais (reçus via JS) en jours français (utilisés en base de données)
$jours_traduits = [
    'monday'    => 'Lundi',
    'tuesday'   => 'Mardi',
    'wednesday' => 'Mercredi',
    'thursday'  => 'Jeudi',
    'friday'    => 'Vendredi',
    'saturday'  => 'Samedi',
    'sunday'    => 'Dimanche',
];

// Récupération du jour depuis la requête GET
$jourAnglais = strtolower($_GET['jour'] ?? '');
$jourFrancais = $jours_traduits[$jourAnglais] ?? null;

if (!$jourFrancais) {
    echo json_encode([
        'success' => false,
        'message' => 'Jour invalide transmis.'
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT heure_ouverture, heure_fermeture, ferme FROM horaires WHERE jour = ?");
    $stmt->execute([$jourFrancais]);
    $horaire = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$horaire) {
        echo json_encode([
            'success' => false,
            'message' => "Aucun horaire trouvé pour $jourFrancais."
        ]);
        exit;
    }

    if ($horaire['ferme']) {
        echo json_encode([
            'success' => false,
            'message' => "Désolé, le restaurant est fermé le $jourFrancais."
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'jour' => $jourFrancais,
        'heure_debut' => substr($horaire['heure_ouverture'], 0, 5),  // format "HH:MM"
        'heure_fin' => substr($horaire['heure_fermeture'], 0, 5)
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => "Erreur serveur : " . $e->getMessage()
    ]);
}
