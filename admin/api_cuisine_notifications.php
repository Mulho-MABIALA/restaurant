<?php
/**
 * API pour les notifications de la cuisine vers la page commandes.php
 * Permet de notifier en temps réel quand une commande change de statut
 */

session_start();
require_once '../config.php';

// Vérifie l'accès admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

header('Content-Type: application/json');

// ===== RÉCUPÉRER LES COMMANDES PRÊTES NON VUES =====
if (isset($_GET['action']) && $_GET['action'] === 'get_commandes_pretes') {
    try {
        $stmt = $conn->prepare("
            SELECT c.*,
                   TIMESTAMPDIFF(MINUTE, c.updated_at, NOW()) as temps_depuis_pret
            FROM commandes c
            WHERE c.statut = 'Prêt'
            AND c.vu_admin = 0
            ORDER BY c.updated_at DESC
        ");
        $stmt->execute();
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pour chaque commande, récupérer les détails
        foreach ($commandes as &$commande) {
            $stmt_details = $conn->prepare("
                SELECT nom_plat, quantite, prix
                FROM commande_details
                WHERE commande_id = ?
            ");
            $stmt_details->execute([$commande['id']]);
            $commande['details'] = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'commandes' => $commandes,
            'count' => count($commandes)
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ===== MARQUER UNE COMMANDE COMME VUE =====
if (isset($_POST['action']) && $_POST['action'] === 'marquer_vu' && isset($_POST['id'])) {
    try {
        $id = $_POST['id'];

        $stmt = $conn->prepare("UPDATE commandes SET vu_admin = 1 WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Commande marquée comme vue'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ===== RÉCUPÉRER LE NOMBRE DE COMMANDES PRÊTES NON VUES =====
if (isset($_GET['action']) && $_GET['action'] === 'count_commandes_pretes') {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM commandes
            WHERE statut = 'Prêt'
            AND vu_admin = 0
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'count' => (int)$result['count']
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ===== RÉCUPÉRER TOUTES LES NOTIFICATIONS NON LUES =====
if (isset($_GET['action']) && $_GET['action'] === 'get_notifications') {
    try {
        $stmt = $conn->prepare("
            SELECT *
            FROM notifications
            WHERE vue = 0
            ORDER BY date DESC
            LIMIT 10
        ");
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'count' => count($notifications)
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Si aucune action n'est spécifiée
echo json_encode([
    'success' => false,
    'message' => 'Action non spécifiée'
]);
?>
