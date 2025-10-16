<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Vérifier que l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

try {
    $aujourdhui = date('Y-m-d');

    // Compter les nouvelles réservations (non lues)
    $stmt_nouvelles = $conn->prepare("SELECT COUNT(*) as total FROM reservations WHERE statut = 'non_lu'");
    $stmt_nouvelles->execute();
    $nombre_nouvelles = $stmt_nouvelles->fetch()['total'] ?? 0;

    // Récupérer les réservations du jour
    $stmt_aujourdhui = $conn->prepare("
        SELECT
            id, nom, email, telephone, personnes,
            date_reservation, heure_reservation, message,
            date_envoi, statut
        FROM reservations
        WHERE date_reservation = ?
        ORDER BY heure_reservation ASC
    ");
    $stmt_aujourdhui->execute([$aujourdhui]);
    $reservations_aujourdhui = $stmt_aujourdhui->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les dernières réservations non lues
    $stmt_dernieres = $conn->prepare("
        SELECT
            id, nom, email, telephone, personnes,
            date_reservation, heure_reservation, message,
            date_envoi, statut
        FROM reservations
        WHERE statut = 'non_lu'
        ORDER BY date_envoi DESC
        LIMIT 5
    ");
    $stmt_dernieres->execute();
    $dernieres_reservations = $stmt_dernieres->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'nombre_nouvelles' => $nombre_nouvelles,
        'reservations_aujourdhui' => $reservations_aujourdhui,
        'dernieres_reservations' => $dernieres_reservations,
        'date_actuelle' => $aujourdhui
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des données'
    ]);
}
