<?php
    session_start();
    require_once '../config.php';
    require_once './permissions.php';

    // Vérifie l'accès admin
    if (! isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }

    // Vérifier que admin_id existe
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    // Vérifier les permissions
    requireAccess($conn, $_SESSION['admin_id'], 'commandes');

    // Récupérer les infos de l'admin
    $stmt_admin = $conn->prepare("SELECT username, email FROM admin WHERE id = ?");
    $stmt_admin->execute([$_SESSION['admin_id']]);
    $admin_info = $stmt_admin->fetch(PDO::FETCH_ASSOC);
    $admin_name = $admin_info['username'] ?? $_SESSION['admin_username'] ?? 'Admin';
    $admin_email = $admin_info['email'] ?? 'admin@restaurant.com';
    $admin_photo = null; // Photo non disponible dans la base de données

    // Fonction pour échapper les valeurs
    function e($value)
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    // Fonction pour mettre à jour automatiquement le champ vu_admin après 1 heure
    function updateOldOrdersVuStatus($conn)
    {
        try {
            $stmt = $conn->prepare("
            UPDATE commandes
            SET vu_admin = 1
            WHERE vu_admin = 0
            AND TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 1
        ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            return 0;
        }
    }
function getAllProductsWithCategories($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT p.*, c.nom as nom_categorie, c.couleur as couleur_categorie
            FROM plats p 
            LEFT JOIN categories c ON p.categorie_id = c.id 
            ORDER BY c.nom, p.nom
        ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Vérifiez que la requête retourne des résultats
        if ($result === false) {
            throw new Exception("Erreur lors de l'exécution de la requête produits");
        }
        
        return $result;
    } catch (PDOException $e) {
        throw new Exception("Erreur base de données produits: " . $e->getMessage());
    }
}

// Fonction pour récupérer toutes les catégories
function getAllCategories($conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM categories ORDER BY nom");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($result === false) {
            throw new Exception("Erreur lors de l'exécution de la requête catégories");
        }
        
        return $result;
    } catch (PDOException $e) {
        throw new Exception("Erreur base de données catégories: " . $e->getMessage());
    }
}
// ===== GESTION AJAX POUR CRÉER UNE COMMANDE MANUELLE =====
if (isset($_POST['action']) && $_POST['action'] === 'creer_commande_manuelle') {
    header('Content-Type: application/json');
    
    try {
        $nom_client = $_POST['nom_client'] ?? '';
        $email = $_POST['email'] ?? '';
        $telephone = $_POST['telephone'] ?? '';
        $num_table = $_POST['num_table'] ?? '';
        $produits = json_decode($_POST['produits'] ?? '[]', true);
        $remise_type = $_POST['remise_type'] ?? 'aucune'; // 'pourcentage', 'montant', 'aucune'
        $remise_valeur = floatval($_POST['remise_valeur'] ?? 0);
        $total_original = floatval($_POST['total_original'] ?? 0);
        $total_final = floatval($_POST['total_final'] ?? 0);
        
        // Validation des données - simplifiée
        if (empty($num_table) || empty($produits)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez renseigner le numéro de table et sélectionner des produits']);
            exit;
        }
        
        // Générer des valeurs par défaut si vides
        if (empty($nom_client)) {
            $nom_client = "Table " . $num_table;
        }
        if (empty($telephone)) {
            $telephone = "0000000000";
        }
        
        // Calculer la remise
        $remise_montant = 0;
        if ($remise_type === 'pourcentage' && $remise_valeur > 0) {
            $remise_montant = ($total_original * $remise_valeur) / 100;
        } elseif ($remise_type === 'montant' && $remise_valeur > 0) {
            $remise_montant = $remise_valeur;
        }
        
        // Commencer une transaction
        $conn->beginTransaction();
        
        // Insérer la commande
        $stmt = $conn->prepare("
            INSERT INTO commandes (
                nom_client, email, telephone, num_table, 
                total, statut, statut_paiement, vu_admin, 
                type_commande, remise_type, remise_valeur, remise_montant,
                created_at, date_commande
            ) VALUES (
                :nom_client, :email, :telephone, :num_table,
                :total, 'En cours', 'Impayé', 0,
                'manuelle', :remise_type, :remise_valeur, :remise_montant,
                NOW(), NOW()
            )
        ");
        
        $result = $stmt->execute([
            'nom_client' => $nom_client,
            'email' => $email,
            'telephone' => $telephone,
            'num_table' => $num_table,
            'total' => $total_final,
            'remise_type' => $remise_type,
            'remise_valeur' => $remise_valeur,
            'remise_montant' => $remise_montant
        ]);
        
        if (!$result) {
            throw new Exception('Erreur lors de l\'insertion de la commande');
        }
        
        $commande_id = $conn->lastInsertId();
        
        // Insérer les détails de la commande
        foreach ($produits as $produit) {
            $stmt_detail = $conn->prepare("
                INSERT INTO commande_details (
                    commande_id, nom_plat, quantite, prix
                ) VALUES (
                    :commande_id, :nom_plat, :quantite, :prix
                )
            ");

            $stmt_detail->execute([
                'commande_id' => $commande_id,
                'nom_plat' => $produit['nom'],
                'quantite' => $produit['quantite'],
                'prix' => $produit['prix']
            ]);
        }
        
        // Valider la transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Commande créée avec succès',
            'commande_id' => $commande_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
}

// ===== GESTION AJAX POUR RÉCUPÉRER LES PRODUITS =====
if (isset($_POST['action']) && $_POST['action'] === 'get_produits') {
    header('Content-Type: application/json');
    
    try {
        $produits = getAllProductsWithCategories($conn);
        $categories = getAllCategories($conn);
        
        // Log pour débogage (optionnel)
        error_log("Nombre de produits récupérés: " . count($produits));
        error_log("Nombre de catégories récupérées: " . count($categories));
        
        echo json_encode([
            'success' => true,
            'produits' => $produits,
            'categories' => $categories
        ]);
    } catch (Exception $e) {
        // Log l'erreur
        error_log("Erreur lors de la récupération des produits: " . $e->getMessage());
        
        echo json_encode([
            'success' => false, 
            'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Récupérer les produits et catégories pour le modal
$produits_disponibles = getAllProductsWithCategories($conn);
$categories_disponibles = getAllCategories($conn);
    // Mettre à jour le statut "vu" des anciennes commandes automatiquement
    updateOldOrdersVuStatus($conn);

    // Gestion de la modification du statut de paiement (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'update_payment_status' && isset($_POST['id'])) {
        header('Content-Type: application/json');

        try {
            $id              = $_POST['id'];
            $statut_paiement = $_POST['statut_paiement'] ?? 'Impayé';

            $stmt   = $conn->prepare("UPDATE commandes SET statut_paiement = :statut_paiement WHERE id = :id");
            $result = $stmt->execute([
                'statut_paiement' => $statut_paiement,
                'id'              => $id,
            ]);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Statut de paiement modifié avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification: ' . $e->getMessage()]);
        }
        exit;
    }

    // Gestion de la modification AJAX (modifiée pour inclure le statut de paiement)
    if (isset($_POST['action']) && $_POST['action'] === 'modifier' && isset($_POST['id'])) {
        header('Content-Type: application/json');

        try {
            $id              = $_POST['id'];
            $statut          = $_POST['statut'] ?? '';
            $statut_paiement = $_POST['statut_paiement'] ?? 'Impayé';
            $vu_admin        = isset($_POST['vu_admin']) && $_POST['vu_admin'] === '1' ? 1 : 0;

            $stmt   = $conn->prepare("UPDATE commandes SET statut = :statut, statut_paiement = :statut_paiement, vu_admin = :vu_admin WHERE id = :id");
            $result = $stmt->execute([
                'statut'          => $statut,
                'statut_paiement' => $statut_paiement,
                'vu_admin'        => $vu_admin,
                'id'              => $id,
            ]);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Commande modifiée avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification: ' . $e->getMessage()]);
        }
        exit;
    }

    // Gestion pour récupérer une commande (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'get_commande' && isset($_POST['id'])) {
        header('Content-Type: application/json');

        try {
            $stmt = $conn->prepare("SELECT * FROM commandes WHERE id = :id");
            $stmt->execute(['id' => $_POST['id']]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($commande) {
                echo json_encode(['success' => true, 'data' => $commande]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
        exit;
    }

    // Action pour récupérer les détails complets d'une commande (avec produits)
    if (isset($_POST['action']) && $_POST['action'] === 'get_commande_details' && isset($_POST['id'])) {
        header('Content-Type: application/json');

        try {
            // Récupérer la commande
            $stmt = $conn->prepare("SELECT * FROM commandes WHERE id = :id");
            $stmt->execute(['id' => $_POST['id']]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($commande) {
                // Récupérer les produits de la commande
                $stmt = $conn->prepare("
                    SELECT * FROM commande_details
                    WHERE commande_id = :commande_id
                ");
                $stmt->execute(['commande_id' => $_POST['id']]);
                $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'commande' => $commande,
                    'produits' => $produits
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
        exit;
    }

    // Gestion de la suppression AJAX
    if (isset($_POST['action']) && $_POST['action'] === 'supprimer' && isset($_POST['id'])) {
        header('Content-Type: application/json');

        try {
            $stmt = $conn->prepare("DELETE FROM commandes WHERE id = :id");
            $stmt->execute(['id' => $_POST['id']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Commande supprimée avec succès']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
        }
        exit;
    }

    // Recherche & filtre par statut
    $search          = $_GET['search'] ?? '';
    $filtre_statut   = $_GET['statut'] ?? '';
    $filtre_paiement = $_GET['paiement'] ?? '';

    try {
        $sql = "SELECT *, 
        CASE 
            WHEN type_commande = 'manuelle' THEN CONCAT('[MANUELLE] ', nom_client)
            ELSE nom_client 
        END as nom_client_display 
        FROM commandes WHERE 1";
        $params = [];

        if (! empty($search)) {
            $sql .= " AND (nom_client LIKE :search OR email LIKE :search OR telephone LIKE :search)";
            $params['search'] = "%$search%";
        }

        if (! empty($filtre_statut)) {
            $sql .= " AND statut = :statut";
            $params['statut'] = $filtre_statut;
        }

        if (! empty($filtre_paiement)) {
            $sql .= " AND statut_paiement = :paiement";
            $params['paiement'] = $filtre_paiement;
        }

        // Ordre par date/id pour garder la cohérence
        $sql .= " ORDER BY id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erreur : " . $e->getMessage());
    }

    // Statistiques améliorées
    $total_cmd      = count($commandes);
    $nouvelles_cmd  = count(array_filter($commandes, fn($c) => ! $c['vu_admin']));
    $cmd_aujourdhui = count(array_filter($commandes, fn($c) => date('Y-m-d', strtotime($c['created_at'] ?? $c['date_commande'] ?? 'now')) === date('Y-m-d')));
    // Ne compter que les commandes payées dans le total des ventes
    $total_ventes = array_sum(array_map(function ($cmd) {
        return ($cmd['statut_paiement'] ?? 'Impayé') === 'Payé' ? $cmd['total'] : 0;
    }, $commandes));
    $moyenne_cmd = $total_cmd > 0 ? intval($total_ventes / $total_cmd) : 0;

    // Statistiques de paiement
    $commandes_payees   = count(array_filter($commandes, fn($c) => ($c['statut_paiement'] ?? 'Impayé') === 'Payé'));
    $commandes_impayees = $total_cmd - $commandes_payees;

    // Liste des statuts possibles
    $statuts_disponibles = ['En cours', 'Préparation en cours', 'Livré', 'Terminée', 'Annulé'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>restaurant Mulho</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/cards-design.css">
    <style>
        body {
            font-family: ui-sans-serif, system-ui, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        /* Styles supplémentaires pour les cards de commandes */
        .dashboard-card {
            backdrop-filter: blur(20px);
        }

        /* Indicateurs de tendance */
        .trend-indicator {
            width: 60px;
            height: 4px;
            border-radius: 2px;
            background: linear-gradient(90deg, transparent 0%, currentColor 100%);
            opacity: 0.6;
        }

        /* Styles pour le tableau avec bordures plus visibles */
        .table-wrapper {
            border: 2px solid #d1d5db;
            border-radius: 12px;
            overflow: hidden;
        }

        .table-row {
            transition: all 0.3s ease;
            border-bottom: 1px solid #e5e7eb;
            position: relative;
        }

        .table-row.cursor-pointer:hover {
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
            transform: translateY(-1px);
        }

        .table-row.cursor-pointer:active {
            transform: translateY(0);
        }

        /* Icône "voir" au survol de la ligne */
        .table-row.cursor-pointer::before {
            content: '\f06e';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: -30px;
            top: 50%;
            transform: translateY(-50%);
            color: #3b82f6;
            font-size: 16px;
            opacity: 0;
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .table-row.cursor-pointer:hover::before {
            opacity: 1;
            left: -25px;
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .table-header {
            border-bottom: 2px solid #d1d5db;
        }

        .table-cell {
            border-right: 1px solid #e5e7eb;
        }

        .table-cell:last-child {
            border-right: none;
        }

        /* Styles pour un tableau compact */
        tbody td {
            vertical-align: middle;
        }

        /* Hauteur de ligne fixe pour uniformité */
        tbody tr {
            height: 60px;
        }

        /* Style pour le tableau */
        table {
            min-width: 1400px;
            table-layout: fixed;
        }

        /* Forcer l'affichage des colonnes */
        thead th, tbody td {
            box-sizing: border-box;
        }

        .action-btn {
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            padding: 8px 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        .btn-voir {
            background-color: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }
        .btn-voir:hover {
            background-color: #bbf7d0;
            border-color: #86efac;
        }
        .btn-modifier {
            background-color: #dbeafe;
            color: #1e40af;
            border-color: #bfdbfe;
        }
        .btn-modifier:hover {
            background-color: #bfdbfe;
            border-color: #93c5fd;
        }
        .btn-supprimer {
            background-color: #fee2e2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .btn-supprimer:hover {
            background-color: #fecaca;
            border-color: #fca5a5;
        }
        .status-badge {
            border-radius: 12px;
            font-weight: 500;
            font-size: 12px;
            padding: 4px 12px;
        }
        .modal-overlay {
            backdrop-filter: blur(4px);
        }

        /* Styles pour le modal de modification */
        .modal-content {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .form-input {
            transition: all 0.3s ease;
            border-radius: 12px;
        }

        .form-input:focus {
            transform: translateY(-1px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        /* Styles pour le modal de commande manuelle */
.category-btn {
    transition: all 0.2s ease;
}

.category-btn.active {
    background-color: #dc2626 !important;
    color: white !important;
    border-color: #dc2626 !important;
}

.category-btn:not(.active):hover {
    background-color: #f3f4f6;
    border-color: #d1d5db;
}

.produit-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
}

.produit-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.produit-image {
    width: 100%;
    height: 70px;
    object-fit: cover;
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
}

.produit-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.btn-ajouter {
    transition: all 0.2s ease;
}

.btn-ajouter:hover {
    transform: scale(1.05);
}

.panier-item {
    animation: slideIn 0.3s ease-out;
    transition: all 0.3s ease;
}

.panier-item.removing {
    animation: slideOut 0.3s ease-in;
    transform: translateX(100%);
    opacity: 0;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideOut {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100%);
    }
}

.quantite-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.quantite-btn {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 1px solid #d1d5db;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 12px;
}

.quantite-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.quantite-btn:active {
    transform: scale(0.95);
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .w-96 {
        width: 350px;
    }
}

@media (max-width: 768px) {
    #commandeManuelleModal .max-w-7xl {
        max-width: 100%;
        margin: 0;
        height: 100vh;
        max-height: 100vh;
        border-radius: 0;
    }
    
    .w-96 {
        width: 100%;
        order: 2;
    }
    
    #commandeManuelleModal .flex {
        flex-direction: column;
    }
}

/* Styles pour le modal de commande manuelle */
.category-btn {
    transition: all 0.2s ease;
}

.category-btn.active {
    background-color: #dc2626 !important;
    color: white !important;
    border-color: #dc2626 !important;
}

.category-btn:not(.active):hover {
    background-color: #f3f4f6;
    border-color: #d1d5db;
}

.produit-card {
    transition: all 0.3s ease;
    border-radius: 16px;
    overflow: hidden;
    position: relative;
    border: 2px solid transparent;
}

.produit-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
    border-color: #e5e7eb;
}

.produit-image {
    width: 100%;
    height: 140px;
    object-fit: cover;
    border-radius: 12px;
    position: relative;
}

.produit-placeholder {
    width: 100%;
    height: 70px;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.produit-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.produit-quantity-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #dc2626;
    color: white;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    min-width: 24px;
    text-align: center;
}

.btn-ajouter {
    transition: all 0.2s ease;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    padding: 8px 16px;
    width: 100%;
    border: 2px solid transparent;
}

.btn-ajouter:hover {
    transform: scale(1.02);
    border-color: #059669;
}

.btn-ajouter.added {
    background-color: #059669 !important;
    border-color: #059669 !important;
}

/* Amélioration des cartes produits */
.produit-info {
    padding: 12px;
    background: white;
}

.produit-nom {
    font-weight: 600;
    font-size: 10px;
    color: #1f2937;
    margin-bottom: 2px;
    line-height: 1.2;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 24px;
}

.produit-prix {
    font-weight: 700;
    font-size: 16px;
    color: #059669;
}

.panier-item {
    animation: slideIn 0.3s ease-out;
    transition: all 0.3s ease;
}

.panier-item.removing {
    animation: slideOut 0.3s ease-in;
    transform: translateX(100%);
    opacity: 0;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideOut {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(100%);
    }
}

.quantite-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.quantite-btn {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 1px solid #d1d5db;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 12px;
}

.quantite-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.quantite-btn:active {
    transform: scale(0.95);
}

/* Grid responsive pour produits */
.produits-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
    padding: 20px;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
    .w-96 {
        width: 350px;
    }
    
    .produits-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    }
}

@media (max-width: 768px) {
    #commandeManuelleModal .max-w-7xl {
        max-width: 100%;
        margin: 0;
        height: 100vh;
        max-height: 100vh;
        border-radius: 0;
    }
    
    .w-96 {
        width: 100%;
        order: 2;
    }
    
    #commandeManuelleModal .flex {
        flex-direction: column;
    }
    
    .produits-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 12px;
        padding: 16px;
    }
}

 /* Ajoutez ces styles pour améliorer l'affichage */
    .produit-card {
        transition: all 0.3s ease;
    }
    
    .produit-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    .produit-image {
        height: 160px;
        object-fit: cover;
        width: 100%;
    }
    
    .produit-placeholder {
        height: 160px;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .produit-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .produit-quantity-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #dc2626;
        color: white;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
    }
    
    .panier-item {
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .quantite-btn {
        transition: all 0.2s ease;
    }

    .quantite-btn:hover {
        background: #e5e7eb !important;
    }

    /* Badge panier animé */
    #headerItemCount {
        animation: none;
    }

    #headerItemCount.pulse-badge {
        animation: pulseBadge 0.5s ease;
    }

    @keyframes pulseBadge {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.3);
        }
    }

    /* Styles pour l'impression */
    @media print {
        body * {
            visibility: hidden;
        }
        #printArea, #printArea * {
            visibility: visible;
        }
        #printArea {
            position: absolute;
            left: 0;
            top: 0;
        }
    }
    </style>
</head>
<body>
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-slate-900 shadow-lg sticky top-0 z-40">
                <div class="px-4 sm:px-6 lg:px-8 py-4">
                    <div class="flex justify-between items-center">
                        <!-- Section Titre -->
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-teal-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-chart-line text-white text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-white">
                                    Tableau de Bord
                                </h1>
                                <p class="text-gray-400 text-sm">
                                    Bienvenue, <?= htmlspecialchars($admin_name) ?> ✨
                                </p>
                            </div>
                        </div>

                        <!-- Contrôles -->
                        <div class="flex items-center space-x-4" x-data="{ profileOpen: false, notificationsOpen: false }">
                            <!-- Badge de Notifications -->
                            <div class="relative">
                                <button
                                    @click="notificationsOpen = !notificationsOpen"
                                    id="notification-button-commandes"
                                    class="relative w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center hover:bg-slate-700 transition-all focus:outline-none"
                                    type="button"
                                >
                                    <i class="fas fa-bell text-white text-lg"></i>
                                    <span id="notification-badge-commandes" class="absolute -top-1 -right-1 w-6 h-6 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center animate-pulse hidden">
                                        0
                                    </span>
                                </button>

                                <!-- Panneau de Notifications -->
                                <div
                                    x-show="notificationsOpen"
                                    @click.away="notificationsOpen = false"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="transform opacity-0 scale-95 translate-y-2"
                                    x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
                                    x-transition:leave-end="transform opacity-0 scale-95 translate-y-2"
                                    class="absolute right-0 mt-2 w-96 bg-slate-800 rounded-xl shadow-2xl overflow-hidden z-50 max-h-[500px] overflow-y-auto"
                                    style="display: none;"
                                >
                                    <!-- En-tête Notifications -->
                                    <div class="px-5 py-4 border-b border-slate-700 bg-gradient-to-r from-emerald-600 to-teal-600">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-white font-bold text-base">Notifications Commandes</h3>
                                            <span id="notification-count-commandes" class="bg-white text-emerald-600 px-2 py-1 rounded-full text-xs font-bold">0</span>
                                        </div>
                                    </div>

                                    <!-- Commandes du jour -->
                                    <div class="px-5 py-3 border-b border-slate-700 bg-slate-750">
                                        <div class="flex items-center justify-between mb-2">
                                            <h4 class="text-teal-400 font-semibold text-sm flex items-center">
                                                <i class="fas fa-calendar-day mr-2"></i>
                                                Aujourd'hui (<span id="today-count-commandes">0</span>)
                                            </h4>
                                        </div>
                                        <div id="today-commandes" class="space-y-2 max-h-48 overflow-y-auto">
                                            <!-- Chargées dynamiquement -->
                                        </div>
                                    </div>

                                    <!-- Nouvelles Commandes -->
                                    <div class="px-5 py-3">
                                        <h4 class="text-amber-400 font-semibold text-sm mb-2 flex items-center">
                                            <i class="fas fa-bell mr-2 animate-pulse"></i>
                                            Nouvelles commandes
                                        </h4>
                                        <div id="new-commandes" class="space-y-2">
                                            <!-- Chargées dynamiquement -->
                                        </div>
                                    </div>

                                    <!-- Footer -->
                                    <div class="px-5 py-3 border-t border-slate-700 bg-slate-900">
                                        <button onclick="window.location.reload()" class="w-full text-center text-sm text-emerald-400 hover:text-emerald-300 font-medium">
                                            <i class="fas fa-sync-alt mr-2"></i>Actualiser
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Widget Date/Heure -->
                            <div class="hidden sm:flex items-center space-x-5 bg-slate-800 rounded-xl px-5 py-3">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-slate-700 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-calendar text-blue-400 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400 uppercase">Aujourd'hui</p>
                                        <p class="text-sm font-bold text-white"><?= date('d M Y') ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-slate-700 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-clock text-teal-400 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-400 uppercase">Heure</p>
                                        <p class="text-sm font-bold text-white font-mono" id="live-clock"><?= date('H:i:s') ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Menu Profil -->
                            <div class="relative">
                                <button
                                    @click="profileOpen = !profileOpen"
                                    class="relative w-12 h-12 rounded-xl flex items-center justify-center hover:opacity-90 transition-opacity focus:outline-none overflow-hidden"
                                    type="button"
                                >
                                    <?php if (!empty($admin_photo) && file_exists(__DIR__ . '/' . $admin_photo)): ?>
                                        <img src="<?= htmlspecialchars($admin_photo) ?>"
                                             alt="Photo de profil"
                                             class="w-full h-full object-cover rounded-xl">
                                    <?php else: ?>
                                        <div class="w-full h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center">
                                            <span class="text-white font-bold text-base">
                                                <?= strtoupper(substr($admin_name, 0, 1)) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-400 border-2 border-slate-900 rounded-full"></div>
                                </button>

                                <!-- Dropdown Profil -->
                                <div
                                    x-show="profileOpen"
                                    @click.away="profileOpen = false"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="transform opacity-0 scale-95"
                                    x-transition:enter-end="transform opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="transform opacity-100 scale-100"
                                    x-transition:leave-end="transform opacity-0 scale-95"
                                    class="absolute right-0 mt-2 w-72 bg-slate-800 rounded-xl shadow-xl overflow-hidden z-50"
                                    style="display: none;"
                                >
                                    <!-- En-tête -->
                                    <div class="px-5 py-4 border-b border-slate-700">
                                        <div class="flex items-center space-x-3">
                                            <?php if (!empty($admin_photo) && file_exists(__DIR__ . '/' . $admin_photo)): ?>
                                                <img src="<?= htmlspecialchars($admin_photo) ?>"
                                                     alt="Photo de profil"
                                                     class="w-12 h-12 object-cover rounded-lg">
                                            <?php else: ?>
                                                <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                                                    <span class="text-white font-bold text-base"><?= strtoupper(substr($admin_name, 0, 1)) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <p class="text-white font-semibold text-base"><?= htmlspecialchars($admin_name) ?></p>
                                                <p class="text-gray-400 text-sm"><?= htmlspecialchars($admin_email) ?></p>
                                            </div>
                                        </div>
                                        <div class="mt-3 flex items-center">
                                            <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                                            <span class="text-green-400 text-sm">En ligne</span>
                                        </div>
                                    </div>

                                    <!-- Menu -->
                                    <div class="py-2">
                                        <a href="profile.php" class="flex items-center px-5 py-3 hover:bg-slate-700 transition-colors">
                                            <i class="fas fa-user text-blue-400 w-5"></i>
                                            <span class="ml-3 text-white text-sm">Mon profil</span>
                                            <span class="ml-auto text-gray-400">›</span>
                                        </a>
                                        <a href="settings.php" class="flex items-center px-5 py-3 hover:bg-slate-700 transition-colors">
                                            <i class="fas fa-envelope text-purple-400 w-5"></i>
                                            <span class="ml-3 text-white text-sm">Changer email</span>
                                            <span class="ml-auto text-gray-400">›</span>
                                        </a>
                                        <div class="border-t border-slate-700 my-2"></div>
                                        <a href="logout.php" class="flex items-center px-5 py-3 hover:bg-red-900/20 transition-colors">
                                            <i class="fas fa-sign-out-alt text-red-400 w-5"></i>
                                            <span class="ml-3 text-red-400 text-sm font-medium">Déconnexion</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-auto p-6">
                <!-- Section Header -->
                <div class="mb-8 flex justify-between items-center">
                    <h2 class="text-xs uppercase tracking-widest text-gray-800 font-semibold">Gerez les commandes</h2>
                    <button onclick="openCommandeManuelleModal()"
                            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-700 text-white font-semibold rounded-lg shadow-lg hover:from-green-700 hover:to-emerald-800 transform hover:scale-105 transition-all duration-200">
                        <i class="fas fa-plus-circle mr-3 text-lg"></i>
                        Nouvelle commande manuelle
                    </button>
                </div>

                <!-- Statistiques Cards - Layout corrigé en 2 rangées -->
                <div class="space-y-6 mb-8">
                    <!-- Première rangée - 3 cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Total Commandes -->
                        <div class="dashboard-card card-indigo p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-4">
                                        <div class="icon-wrapper icon-indigo mr-4">
                                            <i class="fas fa-shopping-cart text-xl"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Total Commandes</p>
                                            <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $total_cmd?></h3>
                                        </div>
                                    </div>
                                    <div class="trend-indicator icon-total"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Nouvelles Commandes -->
                        <div class="dashboard-card card-red p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-4">
                                        <div class="icon-wrapper icon-red mr-4">
                                            <i class="fas fa-bell text-xl"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Nouvelles</p>
                                            <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $nouvelles_cmd?></h3>
                                        </div>
                                    </div>
                                    <div class="trend-indicator icon-nouvelles" style="width: <?php echo $total_cmd > 0 ? min(($nouvelles_cmd / $total_cmd) * 60, 60) : 0?>px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Aujourd'hui -->
                        <div class="dashboard-card card-cyan p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-4">
                                        <div class="icon-wrapper icon-cyan mr-4">
                                            <i class="fas fa-calendar-day text-xl"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Aujourd'hui</p>
                                            <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $cmd_aujourdhui?></h3>
                                        </div>
                                    </div>
                                    <div class="trend-indicator icon-aujourdhui" style="width: <?php echo $total_cmd > 0 ? min(($cmd_aujourdhui / $total_cmd) * 60, 60) : 0?>px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Deuxième rangée - 3 cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Total Ventes -->
                        <div class="dashboard-card card-green p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-4">
                                        <div class="icon-wrapper icon-green mr-4">
                                            <i class="fas fa-chart-line text-xl"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Total Ventes</p>
                                            <h3 class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($total_ventes, 0, ',', ' ')?> FCFA</h3>
                                        </div>
                                    </div>
                                    <div class="trend-indicator icon-ventes"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Commandes Payées -->
                        <div class="dashboard-card card-teal p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-4">
                                        <div class="icon-wrapper icon-teal mr-4">
                                            <i class="fas fa-check-circle text-xl"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Payées</p>
                                            <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $commandes_payees?></h3>
                                        </div>
                                    </div>
                                    <div class="trend-indicator icon-payees" style="width: <?php echo $total_cmd > 0 ? min(($commandes_payees / $total_cmd) * 60, 60) : 0?>px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Commandes Impayées -->
                        <div class="dashboard-card card-orange p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-4">
                                        <div class="icon-wrapper icon-orange mr-4">
                                            <i class="fas fa-exclamation-circle text-xl"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">Impayées</p>
                                            <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $commandes_impayees?></h3>
                                        </div>
                                    </div>
                                    <div class="trend-indicator icon-impayees" style="width: <?php echo $total_cmd > 0 ? min(($commandes_impayees / $total_cmd) * 60, 60) : 0?>px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-300 my-8"></div>

                <!-- Tabs Navigation -->
                <div class="mb-6">
                    <div class="flex space-x-1 bg-gray-100 p-1 rounded-lg inline-flex">
                        <button onclick="switchTab('actives')" id="tab-actives" class="tab-button px-6 py-2 rounded-md font-medium transition-all duration-200 bg-white text-gray-900 shadow-sm">
                            <i class="fas fa-shopping-cart mr-2"></i>Commandes actives
                        </button>
                        <button onclick="switchTab('historique')" id="tab-historique" class="tab-button px-6 py-2 rounded-md font-medium transition-all duration-200 text-gray-600 hover:text-gray-900">
                            <i class="fas fa-history mr-2"></i>Historique
                        </button>
                    </div>
                </div>

                <!-- Contenu Commandes Actives -->
                <div id="content-actives">

                <!-- Section Header -->
                <div class="mb-6">
                    <h2 class="text-xs uppercase tracking-widest text-gray-600 font-semibold mb-4">ACTIONS & FILTRES</h2>
                </div>

               <!-- Filtres et Recherche -->
<div class="bg-white/80 backdrop-blur-md border border-gray-200 rounded-lg shadow-md p-6 mb-8">
    <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
        <div class="md:col-span-5">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Recherche</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input type="text"
                       name="search"
                       placeholder="Recherche par nom, email ou téléphone..."
                       value="<?php echo e($search)?>"
                       class="block w-full pl-10 pr-3 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors text-base">
                       
            </div>
            
        </div>

        <div class="md:col-span-3">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Statut</label>
            <select name="statut" class="block w-full px-3 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors text-base">
                <option value="">Tous les statuts</option>
                <?php foreach ($statuts_disponibles as $statut): ?>
                    <option value="<?php echo e($statut)?>" <?php echo $filtre_statut == $statut ? 'selected' : ''?>>
                        <?php echo e($statut)?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Paiement</label>
            <select name="paiement" class="block w-full px-3 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors text-base">
                <option value="">Tous</option>
                <option value="Payé" <?php echo $filtre_paiement == 'Payé' ? 'selected' : ''?>>Payé</option>
                <option value="Impayé" <?php echo $filtre_paiement == 'Impayé' ? 'selected' : ''?>>Impayé</option>
            </select>
        </div>

        <div class="md:col-span-2">
            <button type="submit" class="w-full px-4 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-lg hover:from-emerald-600 hover:to-teal-700 focus:ring-2 focus:ring-emerald-500 transition-colors font-medium">
                Filtrer
            </button>
        </div>
    </form>
</div>

                <div class="border-t border-gray-300 my-8"></div>

                <!-- Section Header -->
                <div class="mb-6">
                    <h2 class="text-xs uppercase tracking-widest text-gray-600 font-semibold mb-4">LISTE DES COMMANDES</h2>
                </div>

                <!-- Info scroll horizontal -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-blue-500 text-xl mr-3"></i>
                        <p class="text-sm text-blue-800">
                            <strong>Astuce :</strong> Cliquez sur une ligne pour voir les détails. Faites défiler horizontalement pour voir toutes les colonnes.
                            <span class="text-blue-600">→</span>
                        </p>
                    </div>
                </div>

                <!-- Tableau des Commandes avec bordures visibles -->
                <div class="bg-white/80 backdrop-blur-md shadow-md table-wrapper">
                    <div class="px-6 py-4 bg-gray-50/80 border-b-2 border-gray-300">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-800">Commandes</h3>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50/80">
                                <tr class="table-header">
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 80px; min-width: 80px;">
                                        <i class="fas fa-hashtag mr-1"></i>N°
                                    </th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 200px; min-width: 200px;">
                                        <i class="fas fa-user mr-1"></i>Client
                                    </th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 250px; min-width: 250px;">
                                        <i class="fas fa-phone mr-1"></i>Contact
                                    </th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 80px; min-width: 80px;">
                                    <i class="fas fa-table mr-1"></i>Table
                                    </th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 120px;">
                                        <i class="fas fa-coins mr-1"></i>Total
                                    </th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 110px;">
                                        <i class="fas fa-tasks mr-1"></i>Statut
                                    </th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 100px;">
                                        <i class="fas fa-credit-card mr-1"></i>Paiement
                                    </th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 90px;">
                                        <i class="fas fa-eye mr-1"></i>Vu
                                    </th>
                                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 130px;">
                                        <i class="fas fa-calendar mr-1"></i>Date
                                    </th>
                                    <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider" style="width: 140px;">
                                        <i class="fas fa-cogs mr-1"></i>Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white/50" id="commandesTableBody">
                              <?php if (empty($commandes)): ?>
    <tr>
        <td colspan="10" class="px-6 py-20 text-center">
            <div class="flex flex-col items-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-search-minus text-gray-400 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-700 mb-2">Aucune commande trouvée</h3>
                <p class="text-gray-500">Essayez de modifier vos critères de recherche</p>
            </div>
        </td>
    </tr>
<?php else: ?>
<?php
    // Numérotation séquentielle inversée pour l'affichage - commence par le total et décrémente
    $total_commandes = count($commandes);
    $numero_affichage = $total_commandes;
    foreach ($commandes as $cmd):
?>
    <tr class="table-row cursor-pointer hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 transition-all duration-200"
        id="commande-<?php echo $cmd['id']?>"
        data-commande-id="<?php echo $cmd['id']?>"
        onclick="ouvrirModalDepuisLigne(event, <?php echo $cmd['id']?>)"
        title="Cliquez pour voir les détails">
        <td class="px-3 py-3 whitespace-nowrap border-r border-gray-200">
            <div class="flex items-center justify-center">
                <div class="w-7 h-7 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                    <span class="text-white text-xs font-bold"><?php echo $numero_affichage?></span>
                </div>
            </div>
        </td>
        <td class="px-3 py-3 border-r border-gray-200">
            <div class="flex items-center">
                <div class="w-8 h-8 bg-gradient-to-r from-emerald-400 to-teal-500 rounded-full flex items-center justify-center mr-2 flex-shrink-0">
                    <span class="text-white font-bold text-xs"><?php echo strtoupper(substr(e($cmd['nom_client']), 0, 1))?></span>
                </div>
                <div class="truncate">
                    <?php if (($cmd['type_commande'] ?? 'en_ligne') === 'manuelle'): ?>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                            <i class="fas fa-user-edit text-xs"></i>
                        </span>
                    <?php endif; ?>
                    <span class="text-sm font-semibold text-gray-900"><?php echo e($cmd['nom_client'])?></span>
                </div>
            </div>
        </td>
        <td class="px-3 py-3 border-r border-gray-200">
            <div class="space-y-0.5">
                <div class="text-xs text-gray-900 font-medium truncate flex items-center">
                    <i class="fas fa-envelope text-gray-400 mr-1 text-xs"></i>
                    <?php echo e($cmd['email'])?>
                </div>
                <div class="text-xs text-gray-500 flex items-center">
                    <i class="fas fa-phone text-gray-400 mr-1 text-xs"></i>
                    <?php echo e($cmd['telephone'])?>
                </div>
            </div>
        </td>
        <td class="px-3 py-3 whitespace-nowrap border-r border-gray-200 text-center">
            <div class="flex items-center justify-center">
                <span class="w-7 h-7 bg-blue-100 rounded-full flex items-center justify-center text-sm font-bold text-gray-800">
                    <?php echo e($cmd['num_table'] ?? 'N/A')?>
                </span>
            </div>
        </td>
        <td class="px-3 py-3 whitespace-nowrap border-r border-gray-200">
            <div class="flex items-center">
                <i class="fas fa-coins text-emerald-500 mr-1.5 text-xs"></i>
                <span class="text-sm font-bold text-gray-800"><?php echo number_format($cmd['total'], 0)?> FCFA</span>
            </div>
        </td>
        <td class="px-3 py-3 whitespace-nowrap border-r border-gray-200 text-center">
            <?php
                $statutClass = '';
                $statutIcon  = '';
                switch ($cmd['statut']) {
                    case 'En cours':
                        $statutClass = 'bg-yellow-100 text-yellow-800';
                        $statutIcon  = 'fas fa-clock';
                        break;
                    case 'Livré':
                    case 'Terminée':
                        $statutClass = 'bg-green-100 text-green-800';
                        $statutIcon  = 'fas fa-check-circle';
                        break;
                    case 'Annulé':
                        $statutClass = 'bg-red-100 text-red-800';
                        $statutIcon  = 'fas fa-times-circle';
                        break;
                    case 'Préparation en cours':
                        $statutClass = 'bg-blue-100 text-blue-800';
                        $statutIcon  = 'fas fa-utensils';
                        break;
                    default:
                        $statutClass = 'bg-gray-100 text-gray-800';
                        $statutIcon  = 'fas fa-question-circle';
                }
            ?>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold <?php echo $statutClass?> border" id="statut-<?php echo $cmd['id']?>">
                <i class="<?php echo $statutIcon?> mr-1 text-xs"></i>
                <?php echo e($cmd['statut'])?>
            </span>
        </td>
        <td class="px-3 py-3 whitespace-nowrap border-r border-gray-200 text-center">
            <?php
                $statutPaiement = $cmd['statut_paiement'] ?? 'Impayé';
                $paiementClass  = $statutPaiement === 'Payé' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-orange-100 text-orange-800 border-orange-200';
                $paiementIcon   = $statutPaiement === 'Payé' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            ?>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold <?php echo $paiementClass?> border" id="paiement-<?php echo $cmd['id']?>">
                <i class="<?php echo $paiementIcon?> mr-1 text-xs"></i>
                <?php echo e($statutPaiement)?>
            </span>
        </td>
        <td class="px-3 py-3 whitespace-nowrap border-r border-gray-200 text-center">
            <?php if ($cmd['vu_admin']): ?>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200" id="vu-<?php echo $cmd['id']?>">
                    <i class="fas fa-check-circle text-xs mr-1"></i>
                    Lu
                </span>
            <?php else: ?>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200" id="vu-<?php echo $cmd['id']?>">
                    <i class="fas fa-circle text-xs mr-1 animate-pulse"></i>
                    Nouveau
                </span>
            <?php endif; ?>
        </td>
        <td class="px-3 py-3 border-r border-gray-200">
            <div class="flex items-center text-xs text-gray-500">
                <i class="fas fa-calendar text-gray-400 mr-1.5 text-xs"></i>
                <div>
                    <?php
                        $date = $cmd['date_commande'] ?? $cmd['created_at'] ?? 'Non défini';
                        if ($date !== 'Non défini') {
                            echo date('d/m/Y', strtotime($date));
                            echo '<br><span class="text-xs text-gray-400">' . date('H:i', strtotime($date)) . '</span>';
                        } else {
                            echo $date;
                        }
                    ?>
                </div>
            </div>
        </td>
        <td class="px-3 py-3 text-center">
            <div class="flex items-center justify-center gap-1">
                <a href="recu.php?id=<?php echo $cmd['id']?>"
                   target="_blank"
                   class="inline-flex items-center px-2 py-1.5 text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 rounded-lg transition-all duration-200 border border-green-200"
                   title="Voir le reçu">
                    <i class="fas fa-eye text-sm"></i>
                </a>
                <button onclick="openEditModal(<?php echo $cmd['id']?>, '<?php echo $numero_affichage?>')"
                   class="inline-flex items-center px-2 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-all duration-200 border border-blue-200"
                   title="Modifier">
                    <i class="fas fa-edit text-sm"></i>
                </button>
                <button onclick="confirmDelete(<?php echo $cmd['id']?>, '<?php echo e($cmd['nom_client'])?>', '<?php echo $numero_affichage?>')"
                   class="inline-flex items-center px-2 py-1.5 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-all duration-200 border border-red-200"
                   title="Supprimer">
                    <i class="fas fa-trash text-sm"></i>
                </button>
            </div>
        </td>
    </tr>
<?php
    $numero_affichage--; // Décrémenter pour la prochaine ligne
endforeach; ?>
<?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                </div>
                <!-- Fin Content Actives -->

                <!-- Contenu Historique -->
                <div id="content-historique" class="hidden">
                    <!-- Filtres Historique -->
                    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 mb-6">
                        <h3 class="text-lg font-semibold mb-4">Filtres Historique</h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Recherche</label>
                                <input type="text" id="hist-search" placeholder="Nom, email, téléphone..."
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                                <select id="hist-statut" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Tous les statuts</option>
                                    <?php foreach ($statuts_disponibles as $statut): ?>
                                        <option value="<?= e($statut) ?>"><?= e($statut) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Paiement</label>
                                <select id="hist-paiement" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Tous</option>
                                    <option value="Payé">Payé</option>
                                    <option value="Impayé">Impayé</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                                <input type="date" id="hist-date" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            </div>
                        </div>
                    </div>

                    <!-- Statistiques Historique -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="dashboard-card card-blue">
                            <div class="icon-wrapper icon-blue">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Total Commandes</h3>
                                <p class="card-value" id="hist-total"><?= count($commandes) ?></p>
                            </div>
                        </div>
                        <div class="dashboard-card card-green">
                            <div class="icon-wrapper icon-green">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Total (FCFA)</h3>
                                <p class="card-value" id="hist-montant"><?= number_format(array_sum(array_column($commandes, 'total')), 0, ',', ' ') ?></p>
                            </div>
                        </div>
                        <div class="dashboard-card card-purple">
                            <div class="icon-wrapper icon-purple">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Payées</h3>
                                <p class="card-value" id="hist-payees"><?= count(array_filter($commandes, fn($c) => $c['statut_paiement'] === 'Payé')) ?></p>
                            </div>
                        </div>
                        <div class="dashboard-card card-orange">
                            <div class="icon-wrapper icon-orange">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="card-content">
                                <h3 class="card-title">Moyenne (FCFA)</h3>
                                <p class="card-value" id="hist-moyenne"><?= count($commandes) > 0 ? number_format(array_sum(array_column($commandes, 'total')) / count($commandes), 0, ',', ' ') : 0 ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Tableau Historique -->
                    <div class="bg-white rounded-2xl shadow-xl border-2 border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse" id="table-historique">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">N°</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Client</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Contact</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Table</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Statut</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Paiement</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $numero = count($commandes);
                                    foreach ($commandes as $cmd):
                                    ?>
                                        <tr class="border-b border-gray-200 hover:bg-gray-50"
                                            data-nom="<?= strtolower($cmd['nom_client']) ?>"
                                            data-email="<?= strtolower($cmd['email']) ?>"
                                            data-telephone="<?= $cmd['telephone'] ?>"
                                            data-statut="<?= $cmd['statut'] ?>"
                                            data-paiement="<?= $cmd['statut_paiement'] ?>"
                                            data-date="<?= date('Y-m-d', strtotime($cmd['created_at'])) ?>">
                                            <td class="px-6 py-4">
                                                <div class="numero-commande"><?= $numero ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center mr-3">
                                                        <span class="text-white font-medium text-sm"><?= strtoupper(substr(e($cmd['nom_client']), 0, 1)) ?></span>
                                                    </div>
                                                    <span class="font-medium"><?= e($cmd['nom_client']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm">
                                                    <div><?= e($cmd['email']) ?></div>
                                                    <div class="text-gray-500"><?= e($cmd['telephone']) ?></div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
                                                    #<?= e($cmd['num_table']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="font-bold text-gray-900"><?= number_format($cmd['total'] ?? 0, 0, ',', ' ') ?> FCFA</span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php
                                                $badge_classes = [
                                                    'En attente' => 'bg-yellow-100 text-yellow-800',
                                                    'En préparation' => 'bg-blue-100 text-blue-800',
                                                    'Prête' => 'bg-green-100 text-green-800',
                                                    'Servie' => 'bg-purple-100 text-purple-800',
                                                    'Annulée' => 'bg-red-100 text-red-800'
                                                ];
                                                $badge_class = $badge_classes[$cmd['statut']] ?? 'bg-gray-100 text-gray-800';
                                                ?>
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badge_class ?>">
                                                    <?= e($cmd['statut']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($cmd['statut_paiement'] === 'Payé'): ?>
                                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                                        <i class="fas fa-check-circle mr-1"></i>Payé
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                                        <i class="fas fa-times-circle mr-1"></i>Impayé
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600">
                                                <?= date('d/m/Y H:i', strtotime($cmd['created_at'])) ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <button onclick="viewCommandeDetails(<?= $cmd['id'] ?>)"
                                                        class="text-blue-600 hover:text-blue-800 mr-2">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php
                                    $numero--;
                                    endforeach;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Fin Content Historique -->

            </main>
        </div>
    </div>

    <!-- Modal de modification -->
    <div id="editModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50 hidden">
        <div class="modal-content p-8 m-4 max-w-md w-full">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-edit text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Modifier la commande</h3>
                        <p class="text-gray-600" id="editCommandeInfo"></p>
                    </div>
                </div>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="editForm" class="space-y-6">
                <input type="hidden" id="editCommandeId" name="id">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Statut de la commande</label>
                    <select id="editStatut" name="statut" class="form-input block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-base" required>
                        <?php foreach ($statuts_disponibles as $statut): ?>
                            <option value="<?php echo e($statut)?>"><?php echo e($statut)?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Statut de paiement</label>
                    <select id="editStatutPaiement" name="statut_paiement" class="form-input block w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-base">
                        <option value="Impayé">Impayé</option>
                        <option value="Payé">Payé</option>
                    </select>
                </div>

                <div class="flex items-center p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <input type="checkbox" id="editVuAdmin" name="vu_admin" value="1" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 transition-colors">
                    <label for="editVuAdmin" class="ml-3 text-sm font-medium text-gray-700">
                        <span class="flex items-center">
                            <i class="fas fa-eye text-gray-400 mr-2"></i>
                            Marquer comme consulté par l'admin
                        </span>
                    </label>
                </div>

                <div class="flex space-x-4 pt-4">
                    <button type="button" onclick="closeEditModal()"
                            class="flex-1 px-4 py-3 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                        <i class="fas fa-times mr-2"></i>
                        Annuler
                    </button>
                    <button type="submit"
                            id="saveEditBtn"
                            class="flex-1 px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg font-medium hover:from-blue-600 hover:to-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 m-4 max-w-md w-full border border-gray-200 shadow-xl">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">Confirmer la suppression</h3>
                <p class="text-gray-600 mb-2">Vous êtes sur le point de supprimer définitivement la commande :</p>
                <div class="bg-gray-50 rounded-lg p-3 mb-4 border border-gray-200">
                    <p class="font-medium text-gray-800" id="deleteCommandeInfo"></p>
                </div>
                <p class="text-red-600 text-sm font-medium mb-6">Cette action est irréversible !</p>
                <div class="flex space-x-3">
                    <button onclick="closeDeleteModal()"
                            class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                        Annuler
                    </button>
                    <button onclick="deleteCommande()"
                            id="confirmDeleteBtn"
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors">
                        Supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

  <!-- Modal de visualisation des détails de commande -->
  <div id="detailsCommandeModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
      <div class="p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6 border-b pb-4">
          <h3 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-receipt text-emerald-600 mr-2"></i>
            Détails de la commande
          </h3>
          <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600">
            <i class="fas fa-times text-2xl"></i>
          </button>
        </div>

        <!-- Contenu -->
        <div id="details-content">
          <!-- Le contenu sera injecté ici par JavaScript -->
        </div>
      </div>
    </div>
  </div>

  <!-- ===== MODAL DE COMMANDE MANUELLE (à ajouter avant </body>) ===== -->
<div id="commandeManuelleModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-7xl h-full max-h-[95vh] m-4 flex flex-col overflow-hidden">
        <!-- Header du modal -->
        <div class="bg-gradient-to-r from-green-600 to-emerald-700 text-white p-6 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-shopping-cart text-xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">Nouvelle commande manuelle</h2>
                    <p class="text-green-100">Sélectionnez les produits pour créer une commande</p>
                </div>
            </div>

            <!-- Boutons d'action du header -->
            <div class="flex items-center space-x-4">
                <!-- Bouton Panier -->
                <button onclick="openPanierModal()" class="relative bg-white/20 hover:bg-white/30 px-6 py-3 rounded-lg transition-all duration-300 flex items-center space-x-3">
                    <i class="fas fa-shopping-cart text-xl"></i>
                    <span class="font-semibold">Panier</span>
                    <span id="headerItemCount" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full min-w-[24px] text-center">0</span>
                </button>

                <!-- Bouton Fermer -->
                <button onclick="closeCommandeManuelleModal()" class="text-white/80 hover:text-white transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        <!-- Contenu principal -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Panel produits (pleine largeur maintenant) -->
            <div class="flex-1 flex flex-col">
                <!-- Barre de recherche et navigation -->
                <div class="p-6 border-b border-gray-200 bg-gray-50">

                    <!-- Barre de recherche -->
                    <div class="relative mb-6">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               id="searchProduits" 
                               placeholder="Recherche" 
                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-base">
                        <button class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-times text-gray-400 hover:text-gray-600 cursor-pointer hidden" id="clearSearch"></i>
                        </button>
                    </div>

                    <!-- Navigation par catégories -->
                    <div class="flex flex-wrap gap-3" id="categoriesNav">
                        <button class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200 category-btn active" data-category="all">
                            <i class="fas fa-th-large mr-2"></i>
                            Tous
                        </button>
                        <!-- Les catégories seront ajoutées dynamiquement -->
                    </div>
                </div>

                <!-- Grille des produits -->
                <div class="flex-1 overflow-auto p-4">
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 gap-2" id="produitsGrid">
                        <!-- Les produits seront chargés dynamiquement -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODAL PANIER ===== -->
<div id="panierModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm modal-overlay flex items-center justify-center z-[60] hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[95vh] m-4 flex flex-col overflow-hidden">
        <!-- Header du panier -->
        <div class="bg-gradient-to-r from-green-600 via-green-500 to-emerald-600 text-white p-6 flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center mr-4 animate-float">
                    <i class="fas fa-shopping-basket text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-3xl font-bold">Récapitulatif de commande</h3>
                    <p class="text-green-50 text-sm mt-1">Vérifiez et validez votre commande</p>
                </div>
            </div>
            <button onclick="closePanierModal()" class="w-10 h-10 bg-white/10 hover:bg-white/20 rounded-lg flex items-center justify-center transition-all">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Contenu du panier -->
        <div class="flex-1 overflow-auto bg-gray-50">
            <!-- Informations table -->
            <div class="p-6 border-b-2 border-gray-300 bg-white">
                <label class="block text-sm font-bold text-gray-700 mb-3 flex items-center">
                    <i class="fas fa-table text-green-600 mr-2"></i>
                    Numéro de table <span class="text-red-500 ml-1">*</span>
                </label>
                <div class="relative">
                    <input type="number" id="numTable" placeholder="Entrez le N° de table" min="1"
                           class="w-full px-5 py-4 pl-14 border-2 border-gray-300 rounded-xl focus:ring-4 focus:ring-green-200 focus:border-green-500 text-center font-bold text-2xl transition-all">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-hashtag text-gray-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Liste des articles -->
            <div class="p-6">
                <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-list text-green-600 mr-2"></i>
                    Articles commandés
                </h4>
                <div id="panierItems" class="space-y-4">
                    <!-- Message panier vide -->
                    <div id="panierVide" class="text-center py-16 bg-white rounded-xl border-2 border-dashed border-gray-300">
                        <div class="w-20 h-20 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-shopping-cart text-gray-400 text-3xl"></i>
                        </div>
                        <p class="text-gray-500 text-lg font-medium">Aucun article sélectionné</p>
                        <p class="text-gray-400 text-sm mt-2">Ajoutez des produits pour commencer</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer avec totaux et actions -->
        <div class="border-t-4 border-gray-300 p-6 bg-white space-y-5">
            <!-- Options de remise -->
            <div class="space-y-3">
                <label class="block text-base font-bold text-gray-700 flex items-center">
                    <i class="fas fa-percent-circle text-green-600 mr-2"></i>
                    Remise (Optionnel)
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <button onclick="toggleRemiseType('pourcentage')" id="btnRemisePourcentage"
                            class="px-4 py-3 border-2 border-gray-300 rounded-xl text-sm font-semibold hover:bg-green-50 hover:border-green-400 transition-all">
                        <i class="fas fa-percent mr-2"></i>Pourcentage
                    </button>
                    <button onclick="toggleRemiseType('montant')" id="btnRemiseMontant"
                            class="px-4 py-3 border-2 border-gray-300 rounded-xl text-sm font-semibold hover:bg-green-50 hover:border-green-400 transition-all">
                        <i class="fas fa-coins mr-2"></i>Montant fixe
                    </button>
                </div>
                <input type="number" id="remiseValeur" placeholder="Entrez la valeur de la remise" min="0" step="0.01"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-4 focus:ring-green-200 focus:border-green-500 transition-all hidden">
            </div>

            <!-- Totaux -->
            <div class="space-y-4 bg-gradient-to-br from-gray-50 to-gray-100 p-5 rounded-2xl border-2 border-gray-200 shadow-inner">
                <div class="flex justify-between items-center text-base">
                    <span class="text-gray-600 font-semibold flex items-center">
                        <i class="fas fa-calculator text-gray-400 mr-2"></i>Sous-total
                    </span>
                    <span id="sousTotal" class="font-bold text-gray-800 text-lg">0 FCFA</span>
                </div>
                <div class="flex justify-between items-center text-base text-green-600" id="ligneRemise" style="display: none;">
                    <span class="font-semibold flex items-center">
                        <i class="fas fa-tag text-green-500 mr-2"></i>Remise
                    </span>
                    <span id="montantRemise" class="font-bold text-lg">-0 FCFA</span>
                </div>
                <div class="flex justify-between items-center text-2xl font-bold border-t-2 border-gray-300 pt-4 mt-2">
                    <span class="flex items-center text-gray-800">
                        <i class="fas fa-receipt text-green-600 mr-2 text-xl"></i>Total
                    </span>
                    <span id="totalFinal" class="text-green-600">0 FCFA</span>
                </div>
            </div>

            <!-- Boutons d'action -->
            <div class="grid grid-cols-3 gap-3 pt-3">
                <button onclick="viderPanier()"
                        class="px-4 py-4 bg-red-50 border-2 border-red-300 text-red-600 rounded-xl hover:bg-red-100 hover:border-red-400 transition-all font-semibold shadow-sm">
                    <i class="fas fa-trash mb-1"></i>
                    <div class="text-xs">Vider</div>
                </button>
                <button onclick="imprimerRecu()"
                        class="px-4 py-4 bg-blue-50 border-2 border-blue-300 text-blue-600 rounded-xl hover:bg-blue-100 hover:border-blue-400 transition-all font-semibold shadow-sm">
                    <i class="fas fa-print mb-1"></i>
                    <div class="text-xs">Imprimer</div>
                </button>
                <button onclick="effectuerPaiement()" id="btnPayer"
                        class="px-4 py-4 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:from-green-700 hover:to-emerald-700 transition-all font-bold shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="fas fa-check-circle mb-1"></i>
                    <div class="text-sm">Valider</div>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== ZONE D'IMPRESSION (masquée) ===== -->
<div id="printArea" class="hidden"></div>


    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-4"></div>

    <!-- Scripts -->
     <script>
    let commandeToDelete = null;
    let commandeToEdit = null;
    let currentClientName = null;
    
    // Variables globales pour la commande manuelle - INITIALISATION CORRECTE
    let produits = [];
    let categories = [];
    let panier = [];
    let remiseType = 'aucune';
    let remiseValeur = 0;

    // Fonction pour ouvrir le modal de modification
    function openEditModal(id, numeroAffichage) {
        commandeToEdit = id;
        const modal = document.getElementById('editModal');
        
        // Récupérer le numéro de commande visuel depuis le tableau
        const row = document.getElementById('commande-' + id);
        let numeroCommande = numeroAffichage || id;
        
        if (row) {
            const numeroElement = row.querySelector('.numero-commande');
            if (numeroElement) {
                numeroCommande = numeroElement.textContent;
            }
        }

        // Récupérer les données de la commande via AJAX
        fetch('commandes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_commande&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const commande = data.data;

                // Remplir le formulaire avec le numéro visuel
                document.getElementById('editCommandeId').value = commande.id;
                document.getElementById('editCommandeInfo').textContent = `N°${numeroCommande} - ${commande.nom_client}`;
                document.getElementById('editStatut').value = commande.statut;
                document.getElementById('editStatutPaiement').value = commande.statut_paiement || 'Impayé';
                document.getElementById('editVuAdmin').checked = commande.vu_admin == 1;

                // Afficher le modal
                modal.classList.remove('hidden');
            } else {
                showToast('Erreur: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showToast('Erreur de connexion', 'error');
        });
    }

    // Fonction pour fermer le modal de modification
    function closeEditModal() {
        const modal = document.getElementById('editModal');
        modal.classList.add('hidden');
        commandeToEdit = null;
    }

    // Gestionnaire de soumission du formulaire de modification
    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();

        if (!commandeToEdit) return;

        const saveBtn = document.getElementById('saveEditBtn');
        const originalText = saveBtn.innerHTML;

        // Animation de chargement
        saveBtn.innerHTML = `
            <i class="fas fa-spinner fa-spin mr-2"></i>
            Enregistrement...
        `;
        saveBtn.disabled = true;

        // Récupérer les données du formulaire
        const formData = new FormData(this);
        formData.append('action', 'modifier');

        // Requête AJAX
        fetch('commandes.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour l'affichage dans le tableau
                updateTableRow(commandeToEdit, formData);

                // Mettre à jour les statistiques automatiquement
                setTimeout(() => {
                    updateStats();
                }, 500);

                showToast('Commande modifiée avec succès!', 'success');
                closeEditModal();
            } else {
                showToast('Erreur: ' + data.message, 'error');
            }

            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        })
        .catch(error => {
            console.error('Erreur:', error);
            showToast('Erreur de connexion', 'error');
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        });
    });

    // Fonction pour supprimer la commande via AJAX
    function deleteCommande() {
        if (!commandeToDelete) return;

        const confirmBtn = document.getElementById('confirmDeleteBtn');
        const originalText = confirmBtn.innerHTML;

        // Animation de chargement
        confirmBtn.innerHTML = `
            <i class="fas fa-spinner fa-spin mr-2"></i>
            Suppression...
        `;
        confirmBtn.disabled = true;

        // Sauvegarder l'ID de la commande à supprimer
        const commandeId = commandeToDelete;

        // Requête AJAX
        fetch('commandes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=supprimer&id=${commandeId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Animation de suppression de la ligne
                const row = document.getElementById('commande-' + commandeId);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-100%)';

                    setTimeout(() => {
                        row.remove();
                        // Renumeroter les commandes après suppression
                        renumberCommandes();
                        // Mettre à jour les statistiques automatiquement
                        updateStats();
                    }, 300);
                }

                showToast('Commande supprimée avec succès!', 'success');
                closeDeleteModal();
            } else {
                showToast('Erreur: ' + data.message, 'error');
            }

            // Restaurer le bouton dans tous les cas
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        })
        .catch(error => {
            console.error('Erreur:', error);
            showToast('Erreur de connexion', 'error');
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        });
    }

    // Fonction pour mettre à jour une ligne du tableau
    function updateTableRow(commandeId, formData) {
        const row = document.getElementById('commande-' + commandeId);
        if (!row) return;

        // Mettre à jour le statut
        const statutElement = document.getElementById('statut-' + commandeId);
        const newStatut = formData.get('statut');

        // Déterminer l'icône et la classe selon le statut
        let statutClass = '';
        let statutIcon = '';
        switch(newStatut) {
            case 'En cours':
                statutClass = 'bg-yellow-100 text-yellow-800';
                statutIcon = 'fas fa-clock';
                break;
            case 'Livré':
            case 'Terminée':
                statutClass = 'bg-green-100 text-green-800';
                statutIcon = 'fas fa-check-circle';
                break;
            case 'Annulé':
                statutClass = 'bg-red-100 text-red-800';
                statutIcon = 'fas fa-times-circle';
                break;
            case 'Préparation en cours':
                statutClass = 'bg-blue-100 text-blue-800';
                statutIcon = 'fas fa-utensils';
                break;
            default:
                statutClass = 'bg-gray-100 text-gray-800';
                statutIcon = 'fas fa-question-circle';
        }

        statutElement.className = 'status-badge flex items-center ' + statutClass;
        statutElement.innerHTML = `<i class="${statutIcon} mr-1 text-xs"></i>${newStatut}`;

        // Mettre à jour le statut de paiement
        const paiementElement = document.getElementById('paiement-' + commandeId);
        const newPaiement = formData.get('statut_paiement');
        const paiementClass = newPaiement === 'Payé' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800';
        const paiementIcon = newPaiement === 'Payé' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';

        paiementElement.className = 'status-badge flex items-center ' + paiementClass;
        paiementElement.innerHTML = `<i class="${paiementIcon} mr-1 text-xs"></i>${newPaiement}`;

        // Mettre à jour le statut "vu"
        const vuElement = document.getElementById('vu-' + commandeId);
        const vuAdmin = formData.get('vu_admin') === '1';
        if (vuAdmin) {
            vuElement.className = 'status-badge bg-green-100 text-green-800 flex items-center';
            vuElement.innerHTML = '<i class="fas fa-eye text-xs mr-1"></i>Consulté';
        } else {
            vuElement.className = 'status-badge bg-red-100 text-red-800 flex items-center';
            vuElement.innerHTML = '<i class="fas fa-exclamation text-xs mr-1"></i>Nouveau';
        }

        // Animation de mise à jour
        row.style.backgroundColor = '#f0fdf4';
        setTimeout(() => {
            row.style.backgroundColor = '';
        }, 1000);
    }

    // Fonction pour ouvrir le modal de commande manuelle - CORRIGÉE
    function openCommandeManuelleModal() {
        const modal = document.getElementById('commandeManuelleModal');
        modal.classList.remove('hidden');
        
        // Reset du panier
        panier = [];
        remiseType = 'aucune';
        remiseValeur = 0;
        
        // Charger les produits et initialiser l'interface seulement après le chargement
        chargerProduits().then((success) => {
            if (success) {
                updatePanierDisplay();
                updateTotaux();
                
                // Clear form
                document.getElementById('numTable').value = '';
                document.getElementById('searchProduits').value = '';
                document.getElementById('remiseValeur').value = '';
                document.getElementById('remiseValeur').classList.add('hidden');
                
                // Reset remise buttons
                document.getElementById('btnRemisePourcentage').classList.remove('bg-green-600', 'text-white');
                document.getElementById('btnRemisePourcentage').classList.add('border-gray-300');
                document.getElementById('btnRemiseMontant').classList.remove('bg-green-600', 'text-white');
                document.getElementById('btnRemiseMontant').classList.add('border-gray-300');
            }
        });
    }

    // Fonction pour fermer le modal
    function closeCommandeManuelleModal() {
        const modal = document.getElementById('commandeManuelleModal');
        modal.classList.add('hidden');
    }

    // Fonctions pour gérer le modal panier
    function openPanierModal() {
        const panierModal = document.getElementById('panierModal');
        panierModal.classList.remove('hidden');
        updatePanierDisplay();
        updateTotaux();
    }

    function closePanierModal() {
        const panierModal = document.getElementById('panierModal');
        panierModal.classList.add('hidden');
    }

    // Fonction pour imprimer le reçu
    function imprimerRecu() {
        if (panier.length === 0) {
            showToast('Le panier est vide', 'error');
            return;
        }

        const numTable = document.getElementById('numTable').value;
        if (!numTable) {
            showToast('Veuillez saisir un numéro de table', 'error');
            return;
        }

        // Calculer les totaux
        const sousTotal = panier.reduce((sum, item) => sum + (item.prix * item.quantite), 0);
        let montantRemise = 0;

        if (remiseType === 'pourcentage') {
            montantRemise = (sousTotal * remiseValeur) / 100;
        } else if (remiseType === 'montant') {
            montantRemise = remiseValeur;
        }

        const total = sousTotal - montantRemise;
        const dateNow = new Date();
        const dateStr = dateNow.toLocaleDateString('fr-FR');
        const heureStr = dateNow.toLocaleTimeString('fr-FR');

        // Générer le HTML du reçu
        let recuHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reçu - Table ${numTable}</title>
            <style>
                @media print {
                    @page { margin: 0; }
                    body { margin: 1cm; }
                }
                body {
                    font-family: 'Courier New', monospace;
                    width: 80mm;
                    margin: 0 auto;
                    padding: 10px;
                }
                .header {
                    text-align: center;
                    border-bottom: 2px dashed #000;
                    padding-bottom: 10px;
                    margin-bottom: 10px;
                }
                .restaurant-name {
                    font-size: 20px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .info-line {
                    font-size: 11px;
                    margin: 2px 0;
                }
                .section {
                    margin: 15px 0;
                }
                .item-line {
                    display: flex;
                    justify-content: space-between;
                    margin: 5px 0;
                    font-size: 12px;
                }
                .item-name {
                    flex: 1;
                }
                .item-qty {
                    width: 30px;
                    text-align: center;
                }
                .item-price {
                    width: 80px;
                    text-align: right;
                }
                .separator {
                    border-top: 1px dashed #000;
                    margin: 10px 0;
                }
                .total-line {
                    display: flex;
                    justify-content: space-between;
                    font-size: 13px;
                    margin: 5px 0;
                }
                .total-line.grand-total {
                    font-size: 16px;
                    font-weight: bold;
                    border-top: 2px solid #000;
                    padding-top: 5px;
                    margin-top: 10px;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    border-top: 2px dashed #000;
                    padding-top: 10px;
                    font-size: 11px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="restaurant-name">RESTAURANT JUNGLE</div>
                <div class="info-line">Abidjan, Côte d'Ivoire</div>
                <div class="info-line">Tél: +225 XX XX XX XX XX</div>
                <div class="info-line">Date: ${dateStr} ${heureStr}</div>
            </div>

            <div class="section">
                <div class="info-line" style="font-weight: bold; text-align: center; font-size: 14px;">
                    TABLE N° ${numTable}
                </div>
            </div>

            <div class="separator"></div>

            <div class="section">
                <div style="font-weight: bold; margin-bottom: 10px;">ARTICLES:</div>
        `;

        // Ajouter les articles
        panier.forEach(item => {
            recuHTML += `
                <div class="item-line">
                    <span class="item-name">${item.nom}</span>
                    <span class="item-qty">x${item.quantite}</span>
                    <span class="item-price">${parseInt(item.prix * item.quantite)} FCFA</span>
                </div>
            `;
        });

        recuHTML += `
            </div>

            <div class="separator"></div>

            <div class="section">
                <div class="total-line">
                    <span>Sous-total:</span>
                    <span>${parseInt(sousTotal)} FCFA</span>
                </div>
        `;

        if (montantRemise > 0) {
            recuHTML += `
                <div class="total-line" style="color: green;">
                    <span>Remise${remiseType === 'pourcentage' ? ' (' + remiseValeur + '%)' : ''}:</span>
                    <span>-${parseInt(montantRemise)} FCFA</span>
                </div>
            `;
        }

        recuHTML += `
                <div class="total-line grand-total">
                    <span>TOTAL:</span>
                    <span>${parseInt(total)} FCFA</span>
                </div>
            </div>

            <div class="footer">
                <div style="margin-top: 10px;">Merci de votre visite!</div>
                <div style="margin-top: 5px;">À bientôt</div>
            </div>
        </body>
        </html>
        `;

        // Créer une fenêtre d'impression
        const printWindow = window.open('', '_blank');
        printWindow.document.write(recuHTML);
        printWindow.document.close();

        // Attendre que le contenu soit chargé puis imprimer
        printWindow.onload = function() {
            printWindow.print();
        };
    }

    // Fonction pour charger les produits - CORRIGÉE
    function chargerProduits() {
        console.log("Tentative de chargement des produits...");
        
        return fetch('commandes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_produits'
        })
        .then(response => {
            console.log("Réponse reçue, status:", response.status);
            if (!response.ok) {
                throw new Error('Erreur réseau: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log("Données reçues:", data);
            if (data.success) {
                produits = data.produits || [];
                categories = data.categories || [];
                console.log(`${produits.length} produits chargés, ${categories.length} catégories`);
                
                initializeCategories();
                afficherProduits(produits);
                return true;
            } else {
                throw new Error(data.message || 'Erreur inconnue du serveur');
            }
        })
        .catch(error => {
            console.error('Erreur détaillée:', error);
            showToast('Erreur de chargement: ' + error.message, 'error');
            // Afficher des produits vides pour éviter les erreurs
            produits = [];
            categories = [];
            initializeCategories();
            afficherProduits([]);
            return false;
        });
    }

    // Fonction pour initialiser les catégories
    function initializeCategories() {
        const categoriesNav = document.getElementById('categoriesNav');
        
        // Garder le bouton "Tous"
        const btnTous = categoriesNav.querySelector('[data-category="all"]');
        categoriesNav.innerHTML = '';
        categoriesNav.appendChild(btnTous);
        
        // Ajouter les catégories
        categories.forEach(cat => {
            const categoryColors = {
                'Burgers': { bg: 'bg-blue-100', text: 'text-blue-800', icon: 'fas fa-hamburger' },
                'Asiatique': { bg: 'bg-red-100', text: 'text-red-800', icon: 'fas fa-utensils' },
                'Italien': { bg: 'bg-green-100', text: 'text-green-800', icon: 'fas fa-pizza-slice' },
                'Plats Froid': { bg: 'bg-cyan-100', text: 'text-cyan-800', icon: 'fas fa-snowflake' },
                'Plats chauds': { bg: 'bg-purple-100', text: 'text-purple-800', icon: 'fas fa-fire' },
                'Boissons': { bg: 'bg-orange-100', text: 'text-orange-800', icon: 'fas fa-glass-water' }
            };
            
            const colorData = categoryColors[cat.nom] || { bg: 'bg-gray-100', text: 'text-gray-800', icon: 'fas fa-tag' };
            
            const button = document.createElement('button');
            button.className = `inline-flex items-center px-4 py-2 rounded-full text-sm font-medium ${colorData.bg} ${colorData.text} border border-current category-btn`;
            button.dataset.category = cat.id;
            button.innerHTML = `<i class="${colorData.icon} mr-2"></i>${cat.nom}`;
            button.onclick = () => filterByCategory(cat.id);
            
            categoriesNav.appendChild(button);
        });
    }

    // Fonction pour filtrer par catégorie
    function filterByCategory(categoryId) {
        // Mettre à jour les boutons actifs
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        if (categoryId === 'all') {
            document.querySelector('[data-category="all"]').classList.add('active');
            afficherProduits(produits);
        } else {
            document.querySelector(`[data-category="${categoryId}"]`).classList.add('active');
            const produitsFiltres = produits.filter(p => p.categorie_id == categoryId);
            afficherProduits(produitsFiltres);
        }
    }

    // Fonction pour afficher les produits
   function afficherProduits(produitsToShow) {
        const grid = document.getElementById('produitsGrid');
        
        // Vérifier que produitsToShow est un tableau
        if (!Array.isArray(produitsToShow)) {
            produitsToShow = [];
        }
        
        if (produitsToShow.length === 0) {
            grid.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-search text-gray-400 text-xl"></i>
                    </div>
                    <p class="text-gray-500">Aucun produit disponible</p>
                </div>
            `;
            return;
        }
        
        grid.innerHTML = '';
        produitsToShow.forEach(produit => {
            // Debug: afficher les infos du produit
            if (produit.image) {
                console.log('Produit:', produit.nom, 'Image:', produit.image);
            }

            // Vérifier si le produit est dans le panier
            const itemInPanier = panier.find(item => item.id == produit.id);
            const quantiteInPanier = itemInPanier ? itemInPanier.quantite : 0;

            const card = document.createElement('div');
            card.className = 'produit-card bg-white shadow-sm border border-gray-100';

            // Gérer les différents formats d'image (image, image_url, ou chemin complet)
            const imagePath = produit.image || produit.image_url || '';

            // Construire le bon chemin d'image
            let imageSrc = '';
            if (imagePath) {
                // Si le chemin commence par uploads/, ajouter ../
                if (imagePath.startsWith('uploads/')) {
                    imageSrc = '../' + imagePath;
                }
                // Si c'est juste un nom de fichier, ajouter ../uploads/
                else if (!imagePath.includes('/')) {
                    imageSrc = '../uploads/' + imagePath;
                }
                // Sinon utiliser tel quel
                else {
                    imageSrc = imagePath;
                }
            }

            const imageHtml = imageSrc ?
                `<img src="${imageSrc}"
                      alt="${produit.nom}"
                      class="produit-image"
                      onerror="console.log('Erreur image:', this.src); this.style.display='none'; this.nextElementSibling.style.display='flex';">`
                : '';

            card.innerHTML = `
                <div class="relative">
                    ${imageHtml}
                    <div class="produit-placeholder" style="${imagePath ? 'display: none;' : 'display: flex;'}">
                        <i class="fas fa-utensils text-gray-300 text-xl"></i>
                    </div>

                    <div class="produit-badge text-[10px] px-2 py-0.5">${parseInt(produit.prix)}</div>

                    ${quantiteInPanier > 0 ? `<div class="produit-quantity-badge text-xs">${quantiteInPanier}</div>` : ''}
                </div>

                <div class="produit-info p-1.5">
                    <h3 class="produit-nom font-semibold text-gray-800 mb-1 text-[11px] leading-tight">${produit.nom}</h3>
                    <div class="flex items-center justify-between gap-1">
                        <span class="produit-prix font-bold text-green-600 text-xs">${parseInt(produit.prix)}</span>
                        <button onclick="ajouterAuPanier(${produit.id})"
                                class="btn-ajouter px-1.5 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition-colors ${quantiteInPanier > 0 ? 'bg-green-700' : ''}">
                            <i class="fas fa-plus text-[10px]"></i>
                        </button>
                    </div>
                </div>
            `;
            grid.appendChild(card);
        });
    }

    // Fonction pour ajouter un produit au panier - CORRIGÉE
    function ajouterAuPanier(produitId) {
        const produit = produits.find(p => p.id == produitId);
        if (!produit) {
            console.error("Produit non trouvé:", produitId);
            return;
        }

        const existingItemIndex = panier.findIndex(item => item.id == produitId);

        if (existingItemIndex >= 0) {
            // Produit déjà dans le panier, augmenter la quantité
            panier[existingItemIndex].quantite++;
        } else {
            // Nouveau produit, l'ajouter au panier
            panier.push({
                id: produit.id,
                nom: produit.nom,
                prix: parseFloat(produit.prix),
                quantite: 1,
                image: produit.image || produit.image_url || null
            });
        }

        updatePanierDisplay();
        updateTotaux();
        updateProductBadges(); // Mettre à jour les badges sur les produits

        // Animer le badge du panier
        const headerBadge = document.getElementById('headerItemCount');
        if (headerBadge) {
            headerBadge.classList.add('pulse-badge');
            setTimeout(() => {
                headerBadge.classList.remove('pulse-badge');
            }, 500);
        }

        // Animation de feedback
        showToast(`${produit.nom} ajouté au panier`, 'success');
    }

    // Nouvelle fonction pour mettre à jour les badges des produits
    function updateProductBadges() {
        const productCards = document.querySelectorAll('.produit-card');
        productCards.forEach(card => {
            const productId = card.querySelector('button').onclick.toString().match(/ajouterAuPanier\((\d+)\)/)[1];
            const itemInPanier = panier.find(item => item.id == productId);
            const quantiteInPanier = itemInPanier ? itemInPanier.quantite : 0;
            
            // Mettre à jour le badge de quantité
            const badge = card.querySelector('.produit-quantity-badge');
            if (quantiteInPanier > 0) {
                if (!badge) {
                    const newBadge = document.createElement('div');
                    newBadge.className = 'produit-quantity-badge';
                    newBadge.textContent = quantiteInPanier;
                    card.querySelector('.relative').appendChild(newBadge);
                } else {
                    badge.textContent = quantiteInPanier;
                }
                
                // Changer l'apparence du bouton
                const button = card.querySelector('.btn-ajouter');
                button.classList.add('bg-green-700');
                button.innerHTML = '<i class="fas fa-check mr-1"></i>Ajouté';
            } else {
                if (badge) {
                    badge.remove();
                }
                
                // Remettre le bouton à son état initial
                const button = card.querySelector('.btn-ajouter');
                button.classList.remove('bg-green-700');
                button.innerHTML = '<i class="fas fa-plus mr-1"></i>Ajouter';
            }
        });
    }

    // Fonction pour mettre à jour l'affichage du panier - AMÉLIORÉE
    function updatePanierDisplay() {
        const panierItems = document.getElementById('panierItems');
        const panierVide = document.getElementById('panierVide');
        const headerItemCount = document.getElementById('headerItemCount');

        if (panier.length === 0) {
            panierVide.style.display = 'block';
            panierItems.innerHTML = '';
            panierItems.appendChild(panierVide);
            if (headerItemCount) headerItemCount.textContent = '0';
            return;
        }

        panierVide.style.display = 'none';
        const totalItems = panier.reduce((sum, item) => sum + item.quantite, 0);
        if (headerItemCount) headerItemCount.textContent = totalItems;

        panierItems.innerHTML = '';
        
        panier.forEach((item, index) => {
            const itemDiv = document.createElement('div');
            itemDiv.className = 'panier-item bg-white rounded-xl p-5 border-2 border-gray-200 shadow-sm hover:shadow-md transition-all';

            const imageHtml = item.image ?
                `<img src="${item.image.startsWith('uploads/') ? '../' + item.image : (item.image.includes('/') ? item.image : '../uploads/' + item.image)}"
                      alt="${item.nom}"
                      class="w-16 h-16 object-cover rounded-lg"
                      onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'w-16 h-16 bg-gradient-to-br from-gray-100 to-gray-200 rounded-lg flex items-center justify-center\\'><i class=\\'fas fa-utensils text-gray-400 text-xl\\'></i></div>';")>`
                :
                `<div class="w-16 h-16 bg-gradient-to-br from-gray-100 to-gray-200 rounded-lg flex items-center justify-center">
                    <i class="fas fa-utensils text-gray-400 text-xl"></i>
                </div>`;

            itemDiv.innerHTML = `
                <!-- Ligne 1: Image + Infos + Supprimer -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center flex-1">
                        ${imageHtml}
                        <div class="ml-4 flex-1">
                            <h4 class="font-bold text-gray-900 text-base mb-1">${item.nom}</h4>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                    <i class="fas fa-tag text-green-600 mr-1"></i>${parseInt(item.prix)} FCFA
                                </span>
                            </div>
                        </div>
                    </div>
                    <button onclick="retirerDuPanier(${index})"
                            class="ml-3 w-8 h-8 bg-red-100 text-red-600 hover:bg-red-200 rounded-lg flex items-center justify-center transition-all">
                        <i class="fas fa-trash text-sm"></i>
                    </button>
                </div>

                <!-- Ligne 2: Contrôles quantité + Prix total -->
                <div class="flex items-center justify-between bg-gray-50 p-3 rounded-lg">
                    <div class="flex items-center">
                        <span class="text-sm font-medium text-gray-600 mr-3">Quantité:</span>
                        <div class="flex items-center gap-2 bg-white rounded-lg border-2 border-gray-200 px-2">
                            <button onclick="modifierQuantite(${index}, -1)"
                                    class="w-8 h-8 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded transition-all flex items-center justify-center">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="text-lg font-bold text-gray-900 min-w-[30px] text-center">${item.quantite}</span>
                            <button onclick="modifierQuantite(${index}, 1)"
                                    class="w-8 h-8 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded transition-all flex items-center justify-center">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-500 mb-0.5">Total</div>
                        <div class="text-xl font-bold text-green-600">${parseInt(item.prix * item.quantite)} <span class="text-sm">FCFA</span></div>
                    </div>
                </div>
            `;
            panierItems.appendChild(itemDiv);
        });
    }

    // Fonction pour modifier la quantité - CORRIGÉE
    function modifierQuantite(index, delta) {
        if (panier[index]) {
            panier[index].quantite += delta;
            
            if (panier[index].quantite <= 0) {
                // Supprimer l'article si la quantité devient 0
                panier.splice(index, 1);
            }
            
            updatePanierDisplay();
            updateTotaux();
            updateProductBadges(); // Mettre à jour les badges sur les produits
        }
    }

    // Fonction pour retirer un item du panier - CORRIGÉE
    function retirerDuPanier(index) {
        if (panier[index]) {
            const itemName = panier[index].nom;
            panier.splice(index, 1);
            
            updatePanierDisplay();
            updateTotaux();
            updateProductBadges(); // Mettre à jour les badges sur les produits
            
            showToast(`${itemName} retiré du panier`, 'success');
        }
    }

    // Fonction pour vider le panier
    function viderPanier() {
        if (panier.length === 0) return;
        
        if (confirm('Êtes-vous sûr de vouloir vider le panier ?')) {
            panier = [];
            updatePanierDisplay();
            updateTotaux();
            showToast('Panier vidé', 'success');
        }
    }

    // Fonction pour gérer les types de remise
    function toggleRemiseType(type) {
        const btnPourcentage = document.getElementById('btnRemisePourcentage');
        const btnMontant = document.getElementById('btnRemiseMontant');
        const inputRemise = document.getElementById('remiseValeur');
        
        // Reset buttons
        [btnPourcentage, btnMontant].forEach(btn => {
            btn.classList.remove('bg-green-600', 'text-white');
            btn.classList.add('border-gray-300');
        });
        
        if (remiseType === type) {
            // Désactiver la remise
            remiseType = 'aucune';
            inputRemise.classList.add('hidden');
            inputRemise.value = '';
            remiseValeur = 0;
        } else {
            // Activer le type de remise
            remiseType = type;
            inputRemise.classList.remove('hidden');
            inputRemise.focus();
            
            if (type === 'pourcentage') {
                btnPourcentage.classList.add('bg-green-600', 'text-white');
                btnPourcentage.classList.remove('border-gray-300');
                inputRemise.placeholder = 'Pourcentage (%)';
                inputRemise.max = '100';
            } else {
                btnMontant.classList.add('bg-green-600', 'text-white');
                btnMontant.classList.remove('border-gray-300');
                inputRemise.placeholder = 'Montant (FCFA)';
                inputRemise.removeAttribute('max');
            }
        }
        
        updateTotaux();
    }

    // Fonction pour mettre à jour les totaux
    function updateTotaux() {
        const sousTotal = panier.reduce((sum, item) => sum + (item.prix * item.quantite), 0);
        remiseValeur = parseFloat(document.getElementById('remiseValeur').value) || 0;
        
        let montantRemise = 0;
        if (remiseType === 'pourcentage' && remiseValeur > 0) {
            montantRemise = (sousTotal * Math.min(remiseValeur, 100)) / 100;
        } else if (remiseType === 'montant' && remiseValeur > 0) {
            montantRemise = Math.min(remiseValeur, sousTotal);
        }
        
        const totalFinal = Math.max(0, sousTotal - montantRemise);
        
        // Mise à jour de l'affichage
        document.getElementById('sousTotal').textContent = `${parseInt(sousTotal)} FCFA`;
        document.getElementById('totalFinal').textContent = `${parseInt(totalFinal)} FCFA`;
        
        const ligneRemise = document.getElementById('ligneRemise');
        const montantRemiseSpan = document.getElementById('montantRemise');
        
        if (montantRemise > 0) {
            ligneRemise.style.display = 'flex';
            montantRemiseSpan.textContent = `-${parseInt(montantRemise)} FCFA`;
        } else {
            ligneRemise.style.display = 'none';
        }
        
        // Activer/désactiver le bouton payer
        const btnPayer = document.getElementById('btnPayer');
        if (panier.length === 0) {
            btnPayer.disabled = true;
            btnPayer.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            btnPayer.disabled = false;
            btnPayer.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    // Event listener pour la valeur de remise
    document.addEventListener('DOMContentLoaded', function() {
        const remiseInput = document.getElementById('remiseValeur');
        if (remiseInput) {
            remiseInput.addEventListener('input', updateTotaux);
        }
        
        // Event listener pour la recherche
        const searchInput = document.getElementById('searchProduits');
        const clearSearch = document.getElementById('clearSearch');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                
                if (query) {
                    clearSearch.classList.remove('hidden');
                    const produitsFiltres = produits.filter(p => 
                        p.nom.toLowerCase().includes(query) || 
                        (p.description && p.description.toLowerCase().includes(query))
                    );
                    afficherProduits(produitsFiltres);
                    
                    // Désactiver les filtres de catégorie pendant la recherche
                    document.querySelectorAll('.category-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                } else {
                    clearSearch.classList.add('hidden');
                    afficherProduits(produits);
                    document.querySelector('[data-category="all"]').classList.add('active');
                }
            });
        }
        
        if (clearSearch) {
            clearSearch.addEventListener('click', function() {
                searchInput.value = '';
                this.classList.add('hidden');
                afficherProduits(produits);
                document.querySelector('[data-category="all"]').classList.add('active');
            });
        }
    });

    // Fonction pour effectuer le paiement (enregistrer la commande)
    function effectuerPaiement() {
        const numTable = document.getElementById('numTable').value.trim();
        
        // Validation simplifiée - seulement le numéro de table
        if (!numTable) {
            showToast('Veuillez renseigner le numéro de table', 'error');
            return;
        }
        
        if (panier.length === 0) {
            showToast('Le panier est vide', 'error');
            return;
        }
        
        const btnPayer = document.getElementById('btnPayer');
        const originalText = btnPayer.innerHTML;
        
        // Animation de chargement
        btnPayer.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';
        btnPayer.disabled = true;
        
        // Calculer les totaux
        const sousTotal = panier.reduce((sum, item) => sum + (item.prix * item.quantite), 0);
        let montantRemise = 0;
        if (remiseType === 'pourcentage' && remiseValeur > 0) {
            montantRemise = (sousTotal * Math.min(remiseValeur, 100)) / 100;
        } else if (remiseType === 'montant' && remiseValeur > 0) {
            montantRemise = Math.min(remiseValeur, sousTotal);
        }
        const totalFinal = Math.max(0, sousTotal - montantRemise);
        
        // Préparer les données avec des valeurs par défaut
        const formData = new FormData();
        formData.append('action', 'creer_commande_manuelle');
        formData.append('nom_client', `Table ${numTable}`);
        formData.append('email', '');
        formData.append('telephone', '0000000000');
        formData.append('num_table', numTable);
        formData.append('produits', JSON.stringify(panier));
        formData.append('remise_type', remiseType);
        formData.append('remise_valeur', remiseValeur);
        formData.append('total_original', sousTotal);
        formData.append('total_final', totalFinal);
        
        // Envoi de la requête
        fetch('commandes.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Commande créée avec succès !', 'success');
                closeCommandeManuelleModal();
                
                // Recharger la page pour voir la nouvelle commande
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showToast('Erreur: ' + data.message, 'error');
                console.error('Erreur serveur:', data);
            }
            
            btnPayer.innerHTML = originalText;
            btnPayer.disabled = false;
        })
        .catch(error => {
            console.error('Erreur:', error);
            showToast('Erreur de connexion', 'error');
            btnPayer.innerHTML = originalText;
            btnPayer.disabled = false;
        });
    }

    // Fermer le modal en cliquant à l'extérieur
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('commandeManuelleModal');
        if (e.target === modal) {
            closeCommandeManuelleModal();
        }
    });

    // Fermer le modal avec Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('commandeManuelleModal');
            if (!modal.classList.contains('hidden')) {
                closeCommandeManuelleModal();
            }
        }
    });

    // Fonction pour fermer la modale de suppression
    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        modal.classList.add('hidden');
        
        // IMPORTANT: Réinitialiser toutes les variables
        commandeToDelete = null;
        currentClientName = null;
        
        // Réinitialiser le contenu du modal pour éviter l'affichage des anciennes données
        document.getElementById('deleteCommandeInfo').textContent = '';
        
        // Réinitialiser le bouton
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.innerHTML = 'Supprimer';
        confirmBtn.disabled = false;
    }

    // Fonction pour afficher la modale de confirmation de suppression
    function confirmDelete(id, nomClient, numeroAffichage) {
        // Fermer d'abord tout modal ouvert (au cas où)
        closeDeleteModal();
        closeEditModal();
        
        // Récupérer le numéro de commande visuel depuis le tableau
        const row = document.getElementById('commande-' + id);
        let numeroCommande = numeroAffichage || id;
        
        if (row && !numeroAffichage) {
            const numeroElement = row.querySelector('.numero-commande');
            if (numeroElement) {
                numeroCommande = numeroElement.textContent;
            }
        }
        
        // Définir les nouvelles valeurs
        commandeToDelete = id;
        currentClientName = nomClient;
        
        const modal = document.getElementById('deleteModal');
        // Utiliser le numéro visuel au lieu de l'ID
        document.getElementById('deleteCommandeInfo').textContent = `Commande N°${numeroCommande} - ${nomClient}`;
        
        // S'assurer que le bouton est dans son état normal
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.innerHTML = 'Supprimer';
        confirmBtn.disabled = false;
        
        modal.classList.remove('hidden');
    }

    // Fonction pour renuméroter les commandes après suppression
    function renumberCommandes() {
        const rows = document.querySelectorAll('#commandesTableBody tr:not([colspan])');
        const totalRows = rows.length;
        
        rows.forEach((row, index) => {
            const numeroElement = row.querySelector('.numero-commande');
            if (numeroElement) {
                // Numérotation inverse : la première ligne (index 0) aura le numéro le plus élevé
                numeroElement.textContent = totalRows - index;
            }
        });
    }

    // Fonction pour afficher les notifications toast
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
        const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';

        toast.className = `${bgColor} text-white px-6 py-4 rounded-lg shadow-lg transform translate-x-full transition-all duration-300 font-medium max-w-sm`;
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="${icon} mr-3"></i>
                <span>${message}</span>
                <button onclick="this.closest('div').remove()" class="ml-4 text-white/80 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);

        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }
        }, 4000);
    }

    // Fonction pour mettre à jour les statistiques
    function updateStats() {
        const remainingRows = document.querySelectorAll('#commandesTableBody tr:not([colspan])');
        const totalCommandes = remainingRows.length;

        // Compter les nouvelles commandes, payées/impayées et aujourd'hui
        let nouvellesCommandes = 0;
        let commandesAujourdhui = 0;
        let commandesPayees = 0;
        let totalVentes = 0;

        const today = new Date().toISOString().split('T')[0];

        remainingRows.forEach(row => {
            // Compter les nouvelles (colonne "Vu")
            const vuElement = row.querySelector('[id^="vu-"]');
            if (vuElement && vuElement.textContent.includes('Nouveau')) {
                nouvellesCommandes++;
            }

            // Compter les payées (colonne "Paiement")
            const paiementElement = row.querySelector('[id^="paiement-"]');
            if (paiementElement && paiementElement.textContent.includes('Payé')) {
                commandesPayees++;
                
                // Extraire le montant pour les ventes (seulement les commandes payées)
                const totalCell = row.cells[4];
                if (totalCell) {
                    const montantText = totalCell.textContent;
                    const montant = parseInt(montantText.replace(/[^\d]/g, '')) || 0;
                    totalVentes += montant;
                }
            }

        if (dateCell) {
            const dateText = dateCell.textContent;
            // Vérifier si la date contient aujourd'hui
            if (dateText.includes(today) || dateText.includes(new Date().toISOString().split('T')[0])) {
                commandesAujourdhui++;
            }
        }
    });

    const commandesImpayees = totalCommandes - commandesPayees;

    // Mettre à jour les cards de statistiques
    const statCards = document.querySelectorAll('.dashboard-card');

    // Total Commandes (première card)
    if (statCards[0]) {
        statCards[0].querySelector('.text-3xl.font-bold').textContent = totalCommandes;
    }

    // Nouvelles (deuxième card)
    if (statCards[1]) {
        statCards[1].querySelector('.text-3xl.font-bold').textContent = nouvellesCommandes;
        updateTrendIndicator(statCards[1], nouvellesCommandes, totalCommandes);
    }

    // Aujourd'hui (troisième card)
    if (statCards[2]) {
        statCards[2].querySelector('.text-3xl.font-bold').textContent = commandesAujourdhui;
        updateTrendIndicator(statCards[2], commandesAujourdhui, totalCommandes);
    }

    // Total Ventes (quatrième card)
    if (statCards[3]) {
        const ventesElement = statCards[3].querySelector('.text-2xl.font-bold');
        if (ventesElement) {
            ventesElement.textContent = new Intl.NumberFormat('fr-FR').format(totalVentes) + ' FCFA';
        }
    }

    // Payées (cinquième card)
    if (statCards[4]) {
        statCards[4].querySelector('.text-3xl.font-bold').textContent = commandesPayees;
        updateTrendIndicator(statCards[4], commandesPayees, totalCommandes);
    }

    // Impayées (sixième card)
    if (statCards[5]) {
        statCards[5].querySelector('.text-3xl.font-bold').textContent = commandesImpayees;
        updateTrendIndicator(statCards[5], commandesImpayees, totalCommandes);
    }

    // Animation des cards mises à jour
    statCards.forEach(card => {
        card.style.transform = 'scale(1.02)';
        card.style.transition = 'transform 0.2s ease';
        setTimeout(() => {
            card.style.transform = 'scale(1)';
        }, 200);
    });

    // Vérifier s'il n'y a plus de commandes
    if (totalCommandes === 0) {
        const tbody = document.getElementById('commandesTableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="px-6 py-20 text-center">
                    <div class="flex flex-col items-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-search-minus text-gray-400 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-700 mb-2">Aucune commande trouvée</h3>
                        <p class="text-gray-500">Toutes les commandes ont été supprimées</p>
                    </div>
                </td>
            </tr>
        `;
    }
}

// Fonction helper pour mettre à jour les indicateurs de tendance
function updateTrendIndicator(card, current, total) {
    const trendIndicator = card.querySelector('.trend-indicator');
    if (trendIndicator && total > 0) {
        const newWidth = Math.min((current / total) * 60, 60);
        trendIndicator.style.width = newWidth + 'px';
        trendIndicator.style.transition = 'width 0.5s ease';
    }
}

        // Animation au chargement des cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';

                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 150);
            });

            // Animation des indicateurs de tendance
            setTimeout(() => {
                const trendIndicators = document.querySelectorAll('.trend-indicator');
                trendIndicators.forEach((indicator, index) => {
                    const originalWidth = indicator.style.width;
                    indicator.style.width = '0px';
                    setTimeout(() => {
                        indicator.style.transition = 'width 1s cubic-bezier(0.4, 0, 0.2, 1)';
                        indicator.style.width = originalWidth || '60px';
                    }, 200 + (index * 100));
                });
            }, 800);
        });

        // Fermer les modales en cliquant à l'extérieur
        document.getElementById('editModal').addEventListener('click', (e) => {
            if (e.target.id === 'editModal') {
                closeEditModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', (e) => {
            if (e.target.id === 'deleteModal') {
                closeDeleteModal();
            }
        });

        // Fermer les modales avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (!document.getElementById('editModal').classList.contains('hidden')) {
                    closeEditModal();
                }
                if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                    closeDeleteModal();
                }
            }
        });

        // Fonction pour vérifier et mettre à jour automatiquement les statuts
        function checkStatusUpdates() {
            // Cette fonction peut être appelée périodiquement pour vérifier les mises à jour
            fetch(window.location.href + '?ajax_check_status=1')
            .then(response => response.json())
            .then(data => {
                if (data.updated_count > 0) {
                    showToast(`${data.updated_count} commande(s) mise(s) à jour automatiquement`, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Erreur lors de la vérification des statuts:', error);
            });
        }

        // Exemple d'appel périodique (toutes les 10 minutes)
        setInterval(checkStatusUpdates, 600000);

        // ===== GESTION DES TABS =====
        function switchTab(tab) {
            // Gérer les boutons
            const tabs = ['actives', 'historique'];
            tabs.forEach(t => {
                const btn = document.getElementById(`tab-${t}`);
                const content = document.getElementById(`content-${t}`);

                if (t === tab) {
                    btn.classList.add('bg-white', 'text-gray-900', 'shadow-sm');
                    btn.classList.remove('text-gray-600');
                    content.classList.remove('hidden');
                } else {
                    btn.classList.remove('bg-white', 'text-gray-900', 'shadow-sm');
                    btn.classList.add('text-gray-600');
                    content.classList.add('hidden');
                }
            });
        }

        // ===== FILTRES HISTORIQUE =====
        function applyHistFilters() {
            const search = document.getElementById('hist-search').value.toLowerCase();
            const statut = document.getElementById('hist-statut').value;
            const paiement = document.getElementById('hist-paiement').value;
            const date = document.getElementById('hist-date').value;

            const rows = document.querySelectorAll('#table-historique tbody tr');
            let visibleCount = 0;
            let totalMontant = 0;
            let payeesCount = 0;

            rows.forEach(row => {
                let show = true;

                // Filtre recherche
                if (search) {
                    const nom = row.dataset.nom || '';
                    const email = row.dataset.email || '';
                    const telephone = row.dataset.telephone || '';
                    if (!nom.includes(search) && !email.includes(search) && !telephone.includes(search)) {
                        show = false;
                    }
                }

                // Filtre statut
                if (statut && row.dataset.statut !== statut) {
                    show = false;
                }

                // Filtre paiement
                if (paiement && row.dataset.paiement !== paiement) {
                    show = false;
                }

                // Filtre date
                if (date && row.dataset.date !== date) {
                    show = false;
                }

                if (show) {
                    row.style.display = '';
                    visibleCount++;

                    // Calculer statistiques
                    const montantText = row.querySelector('td:nth-child(5) span').textContent;
                    const montant = parseFloat(montantText.replace(/[^0-9]/g, ''));
                    totalMontant += montant;

                    if (row.dataset.paiement === 'Payé') {
                        payeesCount++;
                    }
                } else {
                    row.style.display = 'none';
                }
            });

            // Mettre à jour les statistiques
            document.getElementById('hist-total').textContent = visibleCount;
            document.getElementById('hist-montant').textContent = totalMontant.toLocaleString('fr-FR');
            document.getElementById('hist-payees').textContent = payeesCount;
            document.getElementById('hist-moyenne').textContent = visibleCount > 0 ? Math.round(totalMontant / visibleCount).toLocaleString('fr-FR') : 0;
        }

        // Attacher les événements de filtrage
        document.addEventListener('DOMContentLoaded', function() {
            const histSearch = document.getElementById('hist-search');
            const histStatut = document.getElementById('hist-statut');
            const histPaiement = document.getElementById('hist-paiement');
            const histDate = document.getElementById('hist-date');

            if (histSearch) histSearch.addEventListener('input', applyHistFilters);
            if (histStatut) histStatut.addEventListener('change', applyHistFilters);
            if (histPaiement) histPaiement.addEventListener('change', applyHistFilters);
            if (histDate) histDate.addEventListener('change', applyHistFilters);
        });

        // ===== FONCTION POUR AFFICHER LES DÉTAILS D'UNE COMMANDE =====
        async function viewCommandeDetails(id) {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_commande_details&id=${id}`
                });

                const data = await response.json();

                if (data.success) {
                    const cmd = data.commande;
                    const produits = data.produits || [];

                    // Construire le HTML des détails
                    let html = `
                        <div class="space-y-6">
                            <!-- Informations client -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                    <i class="fas fa-user text-emerald-600 mr-2"></i>
                                    Informations Client
                                </h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-600">Nom</p>
                                        <p class="font-medium">${cmd.nom_client || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Email</p>
                                        <p class="font-medium">${cmd.email || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Téléphone</p>
                                        <p class="font-medium">${cmd.telephone || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Table</p>
                                        <p class="font-medium">#${cmd.num_table || 'N/A'}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Produits -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                    <i class="fas fa-utensils text-emerald-600 mr-2"></i>
                                    Produits commandés
                                </h4>
                                <div class="space-y-2">
                                    ${produits.map(p => `
                                        <div class="flex justify-between items-center py-2 border-b">
                                            <div class="flex items-center">
                                                <span class="font-medium">${p.nom_plat || 'Produit'}</span>
                                                <span class="text-gray-500 ml-2">x${p.quantite || 1}</span>
                                            </div>
                                            <span class="font-semibold">${(p.prix_unitaire * p.quantite).toLocaleString('fr-FR')} FCFA</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>

                            <!-- Statuts et paiement -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-sm text-gray-600 mb-2">Statut</p>
                                    <span class="px-3 py-1 rounded-full text-sm font-semibold ${getBadgeClass(cmd.statut)}">
                                        ${cmd.statut}
                                    </span>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-sm text-gray-600 mb-2">Paiement</p>
                                    <span class="px-3 py-1 rounded-full text-sm font-semibold ${cmd.statut_paiement === 'Payé' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                        <i class="fas fa-${cmd.statut_paiement === 'Payé' ? 'check' : 'times'}-circle mr-1"></i>
                                        ${cmd.statut_paiement}
                                    </span>
                                </div>
                            </div>

                            <!-- Total -->
                            <div class="bg-emerald-50 rounded-lg p-4 border-2 border-emerald-200">
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-semibold text-gray-800">Total</span>
                                    <span class="text-2xl font-bold text-emerald-600">${(cmd.total || 0).toLocaleString('fr-FR')} FCFA</span>
                                </div>
                            </div>

                            <!-- Date -->
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-calendar mr-2"></i>
                                Commandé le ${new Date(cmd.created_at).toLocaleDateString('fr-FR', {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })}
                            </div>
                        </div>
                    `;

                    document.getElementById('details-content').innerHTML = html;
                    document.getElementById('detailsCommandeModal').classList.remove('hidden');
                } else {
                    alert('Erreur: ' + (data.message || 'Impossible de charger les détails'));
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur de connexion');
            }
        }

        function getBadgeClass(statut) {
            const classes = {
                'En attente': 'bg-yellow-100 text-yellow-800',
                'En préparation': 'bg-blue-100 text-blue-800',
                'Prête': 'bg-green-100 text-green-800',
                'Servie': 'bg-purple-100 text-purple-800',
                'Annulée': 'bg-red-100 text-red-800',
                'En cours': 'bg-blue-100 text-blue-800'
            };
            return classes[statut] || 'bg-gray-100 text-gray-800';
        }

        function closeDetailsModal() {
            document.getElementById('detailsCommandeModal').classList.add('hidden');
        }

        // Ouvrir le modal en cliquant sur la ligne
        function ouvrirModalDepuisLigne(event, commandeId) {
            // Ne pas ouvrir le modal si on clique sur un bouton ou un lien
            if (event.target.closest('button') || event.target.closest('a')) {
                return;
            }

            // Ouvrir le modal de visualisation des détails
            viewCommandeDetails(commandeId);
        }

        // Live clock update
        setInterval(() => {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const clockElement = document.getElementById('live-clock');
            if (clockElement) {
                clockElement.textContent = `${hours}:${minutes}:${seconds}`;
            }
        }, 1000);

        // ========== SYSTÈME DE NOTIFICATIONS COMMANDES ==========

        let dernierNombreCommandes = 0;
        let notificationSoundPlayed = false;

        // Créer un son de notification
        function playNotificationSound() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.value = 800;
                oscillator.type = 'sine';

                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.5);
            } catch (e) {
                console.log('Notification sonore non disponible');
            }
        }

        // Formater la date
        function formatDateFr(dateString) {
            const date = new Date(dateString);
            const options = { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' };
            return date.toLocaleDateString('fr-FR', options);
        }

        // Formater le montant
        function formatMontant(montant) {
            return new Intl.NumberFormat('fr-FR').format(montant) + ' FCFA';
        }

        // Mettre à jour les notifications
        function updateNotificationsCommandes() {
            fetch('get_nouvelles_commandes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const nombreNouvelles = data.nombre_nouvelles;
                        const commandesAujourdhui = data.commandes_aujourdhui;
                        const dernieresCommandes = data.dernieres_commandes;

                        // Mettre à jour les badges
                        const badge = document.getElementById('notification-badge-commandes');
                        const countSpan = document.getElementById('notification-count-commandes');
                        const todayCount = document.getElementById('today-count-commandes');

                        if (nombreNouvelles > 0) {
                            badge.textContent = nombreNouvelles;
                            badge.classList.remove('hidden');
                            countSpan.textContent = nombreNouvelles;

                            // Si le nombre a augmenté, jouer le son
                            if (nombreNouvelles > dernierNombreCommandes) {
                                playNotificationSound();
                                showToastCommande('Nouvelle commande reçue !', 'success');
                            }
                        } else {
                            badge.classList.add('hidden');
                            countSpan.textContent = '0';
                        }

                        dernierNombreCommandes = nombreNouvelles;

                        // Mettre à jour le compteur des commandes du jour
                        todayCount.textContent = commandesAujourdhui.length;

                        // Afficher les commandes du jour
                        const todayContainer = document.getElementById('today-commandes');
                        if (commandesAujourdhui.length > 0) {
                            todayContainer.innerHTML = commandesAujourdhui.map(cmd => `
                                <div class="bg-slate-700 rounded-lg p-3 hover:bg-slate-600 transition-colors border border-teal-500/30">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-semibold text-white text-sm">${cmd.nom_client}</span>
                                        <span class="text-teal-400 font-bold text-sm">Table ${cmd.num_table || 'N/A'}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-gray-400">${formatMontant(cmd.total)}</span>
                                        <span class="text-gray-400">${cmd.telephone}</span>
                                    </div>
                                    <div class="mt-1 text-xs">
                                        <span class="px-2 py-0.5 rounded ${cmd.statut === 'En cours' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-green-500/20 text-green-400'}">${cmd.statut}</span>
                                    </div>
                                </div>
                            `).join('');
                        } else {
                            todayContainer.innerHTML = `
                                <div class="text-center py-4 text-gray-400 text-sm">
                                    <i class="fas fa-calendar-times mb-2"></i>
                                    <p>Aucune commande aujourd'hui</p>
                                </div>
                            `;
                        }

                        // Afficher les nouvelles commandes
                        const newContainer = document.getElementById('new-commandes');
                        if (dernieresCommandes.length > 0) {
                            newContainer.innerHTML = dernieresCommandes.map(cmd => `
                                <div class="bg-slate-700 rounded-lg p-3 hover:bg-slate-600 transition-colors border-l-4 border-amber-500">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-semibold text-white text-sm">${cmd.nom_client}</span>
                                        <span class="bg-amber-500 text-white px-2 py-0.5 rounded text-xs font-bold">NEW</span>
                                    </div>
                                    <div class="text-xs text-gray-400 mb-1">
                                        <i class="fas fa-calendar mr-1"></i>${formatDateFr(cmd.created_at)}
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-emerald-400 font-bold">${formatMontant(cmd.total)}</span>
                                        <span class="text-gray-400">Table ${cmd.num_table || 'N/A'}</span>
                                    </div>
                                </div>
                            `).join('');
                        } else {
                            newContainer.innerHTML = `
                                <div class="text-center py-4 text-gray-400 text-sm">
                                    <i class="fas fa-check-circle mb-2"></i>
                                    <p>Tout est à jour !</p>
                                </div>
                            `;
                        }

                        // Afficher un rappel si des commandes sont prévues aujourd'hui
                        if (commandesAujourdhui.length > 0 && !notificationSoundPlayed) {
                            showToastCommande(`📋 ${commandesAujourdhui.length} commande(s) aujourd'hui`, 'info');
                            notificationSoundPlayed = true;
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la récupération des notifications:', error);
                });
        }

        // Afficher un toast de notification
        function showToastCommande(message, type = 'info') {
            let toastContainer = document.getElementById('toast-container-commandes');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container-commandes';
                toastContainer.className = 'fixed top-20 right-4 z-50 space-y-2';
                document.body.appendChild(toastContainer);
            }

            const colors = {
                success: 'from-green-500 to-emerald-600',
                info: 'from-blue-500 to-indigo-600',
                warning: 'from-amber-500 to-orange-600',
                error: 'from-red-500 to-rose-600'
            };

            const icons = {
                success: 'fa-check-circle',
                info: 'fa-info-circle',
                warning: 'fa-exclamation-triangle',
                error: 'fa-times-circle'
            };

            const toast = document.createElement('div');
            toast.className = `bg-gradient-to-r ${colors[type]} text-white px-6 py-4 rounded-xl shadow-2xl transform transition-all duration-300 flex items-center space-x-3 min-w-[300px]`;
            toast.innerHTML = `
                <i class="fas ${icons[type]} text-2xl"></i>
                <span class="font-medium">${message}</span>
            `;

            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.style.transform = 'translateX(400px)';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Initialiser les notifications au chargement
        document.addEventListener('DOMContentLoaded', function() {
            updateNotificationsCommandes();
            // Mettre à jour toutes les 30 secondes
            setInterval(updateNotificationsCommandes, 30000);
        });

    </script>

    <!-- Footer -->
    <?php include 'footer.php'; ?>
</body>
</html>