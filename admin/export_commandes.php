<?php
require_once '../config.php'; // Ajuste si besoin

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accès refusé');
}

// Force le téléchargement CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=commandes.csv');

$output = fopen('php://output', 'w');

// Entêtes du CSV
fputcsv($output, ['ID', 'Date de commande', 'Client', 'Statut', 'Total (FCFA)']);

try {
    // ✅ Exemple AVEC jointure client (optionnel)
    // Sinon, laisse juste client_id
    $stmt = $conn->query("
        SELECT 
            c.id,
            c.date_commande,
            c.client_id,
            c.statut,
            COALESCE(SUM(ci.quantite * ci.prix_unitaire), 0) AS total
        FROM commandes c
        LEFT JOIN commande_items ci ON ci.commande_id = c.id
        GROUP BY c.id, c.date_commande, c.client_id, c.statut
        ORDER BY c.date_commande DESC
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['date_commande'],
            $row['client_id'] ?? 'N/A',
            $row['statut'],
            number_format($row['total'], 0, ',', ' ')
        ]);
    }

} catch (PDOException $e) {
    fputcsv($output, ['Erreur SQL : ' . $e->getMessage()]);
}

fclose($output);
exit;
