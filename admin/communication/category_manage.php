

// === category_manage.php - Gestion des catégories ===
<?php
require_once '../../config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
if (!$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $nom = trim($_POST['nom'] ?? '');
            $couleur = $_POST['couleur'] ?? '#3b82f6';
            $icone = $_POST['icone'] ?? 'fas fa-bullhorn';
            
            if (empty($nom)) {
                throw new Exception('Nom requis');
            }
            
            $stmt = $conn->prepare("INSERT INTO annonce_categories (nom, couleur, icone) VALUES (?, ?, ?)");
            $stmt->execute([$nom, $couleur, $icone]);
            
            echo json_encode(['success' => true, 'message' => 'Catégorie créée']);
            break;
            
        case 'update':
            $id = $_POST['id'] ?? 0;
            $nom = trim($_POST['nom'] ?? '');
            $couleur = $_POST['couleur'] ?? '#3b82f6';
            $icone = $_POST['icone'] ?? 'fas fa-bullhorn';
            
            if (!$id || empty($nom)) {
                throw new Exception('Paramètres manquants');
            }
            
            $stmt = $conn->prepare("UPDATE annonce_categories SET nom=?, couleur=?, icone=? WHERE id=?");
            $stmt->execute([$nom, $couleur, $icone, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Catégorie modifiée']);
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? 0;
            if (!$id) {
                throw new Exception('ID manquant');
            }
            
            // Vérifier si des annonces utilisent cette catégorie
            $check = $conn->prepare("SELECT COUNT(*) as count FROM annonces WHERE categorie_id = ?");
            $check->execute([$id]);
            if ($check->fetch()['count'] > 0) {
                throw new Exception('Impossible de supprimer: catégorie utilisée');
            }
            
            $stmt = $conn->prepare("DELETE FROM annonce_categories WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Catégorie supprimée']);
            break;
            
        case 'list':
            $categories = $conn->query("
                SELECT ac.*, COUNT(a.id) as annonce_count 
                FROM annonce_categories ac 
                LEFT JOIN annonces a ON ac.id = a.categorie_id AND a.statut = 'publiee'
                GROUP BY ac.id 
                ORDER BY ac.nom
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;
            
        default:
            throw new Exception('Action non reconnue');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>