<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$plat_id = isset($_POST['plat_id']) ? (int)$_POST['plat_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($plat_id <= 0 || $action !== 'add') {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

try {
    // Vérifier que le plat existe et est disponible
    $stmt = $conn->prepare("
        SELECT id, nom, prix, disponible 
        FROM plats 
        WHERE id = ?
    ");
    $stmt->execute([$plat_id]);
    $plat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plat) {
        echo json_encode(['success' => false, 'message' => 'Plat non trouvé']);
        exit;
    }
    
    // Vérifier la disponibilité
    if ($plat['disponible'] == 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Ce plat n\'est plus disponible',
            'error' => 'unavailable'
        ]);
        exit;
    }
    
    // Initialiser le panier s'il n'existe pas
    if (!isset($_SESSION['panier'])) {
        $_SESSION['panier'] = [];
    }
    
    // Ajouter le plat au panier
    if (isset($_SESSION['panier'][$plat_id])) {
        $_SESSION['panier'][$plat_id]['quantite']++;
    } else {
        $_SESSION['panier'][$plat_id] = [
            'nom' => $plat['nom'],
            'prix' => $plat['prix'],
            'quantite' => 1
        ];
    }
    
    // Calculer le nombre total d'articles dans le panier
    $total_articles = array_sum(array_column($_SESSION['panier'], 'quantite'));
    
    echo json_encode([
        'success' => true,
        'message' => 'Plat ajouté au panier avec succès',
        'panier_count' => $total_articles
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur de base de données : ' . $e->getMessage()
    ]);
}
?>