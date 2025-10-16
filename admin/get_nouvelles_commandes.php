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

    // Compter les nouvelles commandes (vu_admin = 0)
    $stmt_nouvelles = $conn->prepare("SELECT COUNT(*) as total FROM commandes WHERE vu_admin = 0");
    $stmt_nouvelles->execute();
    $nombre_nouvelles = $stmt_nouvelles->fetch()['total'] ?? 0;

    // Récupérer les commandes du jour
    $stmt_aujourdhui = $conn->prepare("
        SELECT
            id, nom_client, email, telephone, num_table, total,
            statut, statut_paiement, vu_admin, created_at, date_commande
        FROM commandes
        WHERE DATE(created_at) = ? OR DATE(date_commande) = ?
        ORDER BY created_at DESC
    ");
    $stmt_aujourdhui->execute([$aujourdhui, $aujourdhui]);
    $commandes_aujourdhui = $stmt_aujourdhui->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les dernières commandes non vues
    $stmt_dernieres = $conn->prepare("
        SELECT
            id, nom_client, email, telephone, num_table, total,
            statut, statut_paiement, vu_admin, created_at, date_commande
        FROM commandes
        WHERE vu_admin = 0
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt_dernieres->execute();
    $dernieres_commandes = $stmt_dernieres->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'nombre_nouvelles' => $nombre_nouvelles,
        'commandes_aujourdhui' => $commandes_aujourdhui,
        'dernieres_commandes' => $dernieres_commandes,
        'date_actuelle' => $aujourdhui
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des données'
    ]);
}
