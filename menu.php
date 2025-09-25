<?php
include('config.php');
require_once 'admin/communication/fonctions_annonces.php';
if ($_POST['action'] ?? '' === 'add_to_cart') {
    if (!isset($_SESSION['panier'])) {
        $_SESSION['panier'] = [];
    }
    
    $plat_id = (int)$_POST['plat_id'];
    $quantite = (int)$_POST['quantite'];
    
    if (isset($_SESSION['panier'][$plat_id])) {
        $_SESSION['panier'][$plat_id] += $quantite;
    } else {
        $_SESSION['panier'][$plat_id] = $quantite;
    }
    
    echo json_encode(['success' => true]);
    exit;
}

if ($_POST['action'] ?? '' === 'update_cart') {
    $panier_data = json_decode($_POST['cart_data'], true);
    $_SESSION['panier'] = [];
    
    foreach ($panier_data as $item) {
        // Trouvez l'ID du plat par son nom
        $stmt = $conn->prepare("SELECT id FROM plats WHERE nom = ?");
        $stmt->execute([$item['item']]);
        $plat = $stmt->fetch();
        
        if ($plat) {
            $_SESSION['panier'][$plat['id']] = $item['quantity'];
        }
    }
    
    echo json_encode(['success' => true]);
    exit;
}

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les catégories disponibles (id et nom)
    $stmt_cat = $conn->query("SELECT id, nom FROM categories ORDER BY nom ASC");
    $categories_db = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

    if (empty($categories_db)) {
        die("Aucune catégorie disponible.");
    }

    // Vérifier si categorie_id est défini dans l'URL et valide, sinon prendre la première catégorie
    $categorie_id = isset($_GET['categorie_id']) 
        ? (int) $_GET['categorie_id'] 
        : $categories_db[0]['id'];

    // Vérifier que l'id existe bien dans les catégories
    $categorie_ids = array_column($categories_db, 'id');
    if (!in_array($categorie_id, $categorie_ids)) {
        $categorie_id = $categories_db[0]['id'];
    }

    // Récupérer les plats selon la catégorie ou tous
    if (isset($_GET['show_all']) && $_GET['show_all'] == 'true') {
        // Récupérer tous les plats groupés par catégorie
        // Récupérer tous les plats groupés par catégorie
$stmt = $conn->query("SELECT p.*, p.disponible, c.nom as categorie_nom FROM plats p 
                      JOIN categories c ON p.categorie_id = c.id 
                      ORDER BY c.nom ASC, p.nom ASC");
        $tous_plats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $plats = [];
        $show_all = true;
    } else {
        // Récupérer les plats de la catégorie sélectionnée
        // Récupérer les plats de la catégorie sélectionnée
$stmt = $conn->prepare("SELECT *, disponible FROM plats WHERE categorie_id = :categorie_id");
        $stmt->execute([':categorie_id' => $categorie_id]);
        $plats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $show_all = false;
    }

} catch (PDOException $e) {
    die("Erreur de connexion ou de requête : " . $e->getMessage());
}

// Récupération des items du panier
$plats_panier = [];
$total = 0;
$cart_count = 0;

if (isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
    $ids = array_keys($_SESSION['panier']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $conn->prepare("SELECT * FROM plats WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    while ($plat = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $plat['quantite'] = $_SESSION['panier'][$plat['id']];
        $plat['sous_total'] = $plat['prix'] * $plat['quantite'];
        $total += $plat['sous_total'];
        $plats_panier[] = $plat;
        $cart_count += $plat['quantite'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mulho - Restaurant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1f2937;
            --primary-light: #374151;
            --secondary: #f9fafb;
            --accent: #f59e0b;
            --accent-dark: #d97706;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --border: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --radius-sm: 0.375rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Header moderne */
        .header {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
        }

        .header-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo img {
            width: 3rem;
            height: 3rem;
            object-fit: cover;
            border-radius: var(--radius-md);
        }

        .logo span {
            font-family: 'Playfair Display', serif;
            font-size: 1.875rem;
            font-weight: 600;
            color: var(--primary);
            letter-spacing: -0.025em;
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
        }

        .header-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1rem;
            background: var(--white);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .header-btn:hover {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .cart-badge {
            position: absolute;
            top: -0.5rem;
            right: -0.5rem;
            background: var(--accent);
            color: var(--white);
            border-radius: 9999px;
            width: 1.25rem;
            height: 1.25rem;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: scale(0);
            transition: transform 0.2s ease;
        }

        .cart-badge.show {
            transform: scale(1);
        }

        /* Annonces défilantes */
        .menu-annonces-section {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            padding: 0.75rem 0;
            overflow: hidden;
            position: relative;
            box-shadow: var(--shadow-sm);
        }

        .annonces-container {
            width: 100%;
            overflow: hidden;
            white-space: nowrap;
        }

        .annonce-wrapper {
            display: inline-block;
            white-space: nowrap;
            animation: defilement-annonces 30s linear infinite;
            padding-right: 100%;
        }

        .annonce-wrapper:hover {
            animation-play-state: paused;
        }

        @keyframes defilement-annonces {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        .annonce {
            display: inline-block;
            padding: 0.625rem 1.5rem;
            margin: 0 0.75rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.875rem;
        }

        /* Container principal */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Navigation des catégories */
        .category-nav {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 3rem;
            justify-content: center;
            flex-wrap: wrap;
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
        }

        .category-btn {
            padding: 0.75rem 1.25rem;
            background: var(--gray-50);
            color: var(--text-secondary);
            text-decoration: none;
            border: 1px solid var(--border);
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            border-radius: var(--radius-md);
            position: relative;
        }

        .category-btn.active,
        .category-btn:hover {
            background: var(--accent);
            color: var(--white);
            border-color: var(--accent);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Contrôles d'affichage */
        .view-controls {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            justify-content: flex-end;
        }

        .view-btn {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .view-btn:hover,
        .view-btn.active {
            background: var(--accent);
            color: var(--white);
            border-color: var(--accent);
        }

        /* Sections de catégories */
        .category-section {
            margin-bottom: 4rem;
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: 2.5rem;
            box-shadow: var(--shadow);
        }

        .category-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.875rem;
            font-weight: 500;
            color: var(--primary);
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
        }

        .category-title::after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 50%;
            width: 4rem;
            height: 2px;
            background: var(--accent);
            transform: translateX(-50%);
            border-radius: 1px;
        }

        /* Grille des menus - Mode Liste (par défaut) */
        .menu-grid {
            display: grid;
            gap: 1rem;
        }

        .menu-grid.list-view {
            grid-template-columns: 1fr;
        }

        /* Grille des menus - Mode Cartes */
        .menu-grid.grid-view {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        /* Cartes de menu - Mode Liste */
        .menu-item {
            display: flex;
            align-items: flex-start;
            padding: 1.5rem;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .menu-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent);
        }

        /* Cartes de menu - Mode Grille */
        .menu-grid.grid-view .menu-item {
            flex-direction: column;
            text-align: center;
            padding: 1.5rem;
        }

        .menu-grid.grid-view .menu-item-image,
        .menu-grid.grid-view .menu-item-placeholder {
            margin-right: 0;
            margin-bottom: 1rem;
            width: 100%;
            height: 12rem;
        }

        .menu-grid.grid-view .menu-item-content {
            width: 100%;
        }

        /* Images des plats */
        .menu-item-image {
            width: 5rem;
            height: 5rem;
            border-radius: var(--radius-lg);
            object-fit: cover;
            margin-right: 1.5rem;
            flex-shrink: 0;
            box-shadow: var(--shadow);
        }

        .menu-item-placeholder {
            width: 5rem;
            height: 5rem;
            border-radius: var(--radius-lg);
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 1.5rem;
            margin-right: 1.5rem;
            flex-shrink: 0;
            box-shadow: var(--shadow-sm);
        }

        .menu-item-content {
            flex: 1;
            min-width: 0;
        }

        .menu-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .menu-item-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--primary);
            margin-right: 1rem;
            line-height: 1.4;
        }

        .menu-item-price {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--accent);
            flex-shrink: 0;
        }

        .menu-item-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        /* Bouton d'ajout rapide */
        .quick-add-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--accent);
            color: var(--white);
            border: none;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            opacity: 0;
            transform: scale(0.8);
            box-shadow: var(--shadow-md);
            z-index: 10;
        }

        .menu-item:hover .quick-add-btn {
            opacity: 1;
            transform: scale(1);
        }

        .quick-add-btn:hover {
            background: var(--accent-dark);
            transform: scale(1.1);
            box-shadow: var(--shadow-lg);
        }

        .quick-add-success {
            background: var(--success) !important;
            animation: pulse 0.6s ease;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

        /* État vide */
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--text-light);
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 0.75rem;
            font-weight: 500;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        /* Modal du panier */
        .cart-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 50;
            animation: fadeIn 0.3s ease;
        }

        .cart-content {
            background: var(--white);
            border-radius: var(--radius-xl);
            max-width: 32rem;
            width: 90%;
            max-height: 90vh;
            position: relative;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .cart-header {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: var(--white);
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 600;
            flex: 1;
        }

        .close-cart {
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .close-cart:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .cart-body {
            flex: 1;
            overflow-y: auto;
        }

        .cart-empty {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .cart-empty i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .cart-items {
            padding: 0;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            gap: 1rem;
            transition: background 0.2s ease;
        }

        .cart-item:hover {
            background: var(--gray-50);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-image {
            width: 4rem;
            height: 4rem;
            border-radius: var(--radius-md);
            object-fit: cover;
            flex-shrink: 0;
            box-shadow: var(--shadow-sm);
        }

        .cart-item-placeholder {
            width: 4rem;
            height: 4rem;
            border-radius: var(--radius-md);
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .cart-item-details {
            flex: 1;
            min-width: 0;
        }

        .cart-item-name {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .cart-item-instructions {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-bottom: 0.5rem;
            font-style: italic;
        }

        .cart-item-price {
            color: var(--accent);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quantity-btn-small {
            width: 2rem;
            height: 2rem;
            border: 1px solid var(--border);
            background: var(--white);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .quantity-btn-small:hover {
            border-color: var(--accent);
            background: var(--accent);
            color: var(--white);
        }

        .quantity-display-small {
            font-weight: 600;
            min-width: 1.5rem;
            text-align: center;
            font-size: 0.875rem;
        }

        .remove-item {
            color: var(--danger);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius);
            transition: all 0.2s ease;
        }

        .remove-item:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        .cart-footer {
            border-top: 1px solid var(--border);
            padding: 2rem;
            background: var(--gray-50);
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .cart-total-label {
            color: var(--primary);
        }

        .cart-total-amount {
            color: var(--accent);
        }

        .checkout-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: var(--white);
            border: none;
            padding: 1rem;
            border-radius: var(--radius-lg);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .checkout-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .continue-shopping {
            width: 100%;
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
            padding: 0.75rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .continue-shopping:hover {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        /* Modal de commande */
        .order-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 50;
            animation: fadeIn 0.3s ease;
        }

        .order-content {
            background: var(--white);
            border-radius: var(--radius-xl);
            max-width: 28rem;
            width: 90%;
            position: relative;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            animation: slideUp 0.3s ease;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
        }

        @keyframes slideUp {
            from { transform: translateY(2rem); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .order-modal-header {
            position: relative;
            height: 9rem;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .order-item-image {
            width: 5.5rem;
            height: 5.5rem;
            border-radius: var(--radius-lg);
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 2;
            box-shadow: var(--shadow-lg);
        }

        .order-item-placeholder {
            width: 5.5rem;
            height: 5.5rem;
            border-radius: var(--radius-lg);
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 2rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 2;
        }

        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
            border: none;
            font-size: 1.125rem;
            cursor: pointer;
            width: 2.25rem;
            height: 2.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
            z-index: 3;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .order-body {
            padding: 2rem;
            flex: 1;
            overflow-y: auto;
        }

        .order-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .order-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.375rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .order-item-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .order-item-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--accent);
        }

        /* Instructions spéciales */
        .special-instructions {
            margin-bottom: 1.5rem;
        }

        .special-instructions-label {
            display: block;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primary);
        }

        .special-instructions-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: var(--white);
            resize: vertical;
            min-height: 5rem;
        }

        .special-instructions-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .special-instructions-input::placeholder {
            color: var(--text-light);
            font-style: italic;
        }

        /* Section quantité */
        .quantity-section {
            margin-bottom: 2rem;
        }

        .quantity-label {
            display: block;
            margin-bottom: 1rem;
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
        }

        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.25rem;
            margin-bottom: 1rem;
        }

        .quantity-btn {
            width: 3rem;
            height: 3rem;
            border: 2px solid var(--border);
            background: var(--white);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.125rem;
            font-weight: bold;
            color: var(--text-secondary);
            box-shadow: var(--shadow-sm);
        }

        .quantity-btn:hover {
            border-color: var(--accent);
            background: var(--accent);
            color: var(--white);
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }

        .quantity-btn:active {
            transform: scale(0.95);
        }

        .quantity-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            min-width: 3.75rem;
            text-align: center;
            background: var(--gray-50);
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-md);
            border: 2px solid var(--border);
        }

        /* Résumé de commande */
        .order-summary {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
        }

        .order-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .order-summary-row:last-child {
            margin-bottom: 0;
            padding-top: 0.75rem;
            border-top: 2px solid var(--border);
            font-weight: 700;
        }

        .order-summary-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .order-summary-value {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .order-summary-total {
            font-size: 1.125rem;
            color: var(--accent);
        }

        /* Bouton d'ajout au panier */
        .add-to-cart-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: var(--white);
            border: none;
            padding: 1.125rem;
            border-radius: var(--radius-lg);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .add-to-cart-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .add-to-cart-btn:active {
            transform: translateY(0);
        }

        /* Hero section */
        .hero {
            text-align: center;
            padding: 8rem 2rem;
            background: var(--white);
            display: none;
            margin: 3rem auto;
            max-width: 50rem;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3.375rem;
            font-weight: 500;
            color: var(--primary);
            margin-bottom: 1.5rem;
            letter-spacing: -0.025em;
        }

        .hero p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            max-width: 31.25rem;
            margin: 0 auto;
        }

        /* Footer */
        .footer-info {
            text-align: center;
            padding: 2rem;
            margin-top: 4rem;
            background: var(--white);
            border-radius: var(--radius-xl);
            font-size: 0.875rem;
            color: var(--text-light);
            box-shadow: var(--shadow);
        }

        /* Toast notifications */
        .toast {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            background: var(--white);
            color: var(--text-primary);
            padding: 1.25rem 1.5rem;
            border-radius: var(--radius-lg);
            z-index: 50;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            box-shadow: var(--shadow-xl);
            transform: translateX(25rem);
            transition: transform 0.3s ease;
            border: 1px solid var(--success);
        }

        .toast .fa-check-circle {
            color: var(--success);
            font-size: 1.125rem;
        }

        .toast.show {
            transform: translateX(0);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .container {
                padding: 1.5rem 1rem;
            }
            
            .category-section {
                padding: 2rem;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                padding: 1rem;
            }
            
            .logo span {
                font-size: 1.5rem;
            }
            
            .header-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.8125rem;
            }
            
            .container {
                padding: 1.5rem 0.75rem;
            }
            
            .category-nav {
                flex-direction: column;
                padding: 1.25rem;
                margin-bottom: 2rem;
            }
            
            .category-btn {
                text-align: center;
                padding: 1rem;
            }
            
            .view-controls {
                justify-content: center;
                margin: 1rem 0;
            }
            
            .category-section {
                padding: 1.5rem 1rem;
                margin-bottom: 3rem;
            }
            
            .category-title {
                font-size: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .menu-grid.grid-view {
                grid-template-columns: 1fr;
            }
            
            .menu-item {
                padding: 1.25rem 1rem;
            }
            
            .menu-item-image,
            .menu-item-placeholder {
                width: 4rem;
                height: 4rem;
                margin-right: 1rem;
            }
            
            .menu-item-name {
                font-size: 1rem;
            }
            
            .menu-item-price {
                font-size: 1rem;
            }
            
            .quick-add-btn {
                width: 2.25rem;
                height: 2.25rem;
                top: 0.75rem;
                right: 0.75rem;
            }

            .order-content {
                width: 95%;
                max-width: none;
                max-height: 90vh;
            }

            .order-body {
                padding: 1.5rem;
            }

            .order-modal-header {
                height: 7.5rem;
            }

            .order-item-image,
            .order-item-placeholder {
                width: 4.5rem;
                height: 4.5rem;
            }
            
            .hero {
                padding: 5rem 1.5rem;
                margin: 2rem 0.75rem;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.125rem;
            }

            .cart-content {
                width: 95%;
                max-width: none;
            }

            .cart-header,
            .cart-footer {
                padding: 1.5rem;
            }

            .cart-item {
                padding: 1.25rem 1.5rem;
            }

            .empty-state {
                padding: 4rem 1.5rem;
            }

            .empty-state i {
                font-size: 3rem;
            }

            .empty-state h3 {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 480px) {
            .header-actions {
                gap: 0.25rem;
            }
            
            .header-btn {
                padding: 0.5rem;
                font-size: 0;
            }
            
            .header-btn i {
                font-size: 1rem;
            }
            
            .category-nav {
                gap: 0.25rem;
            }
            
            .category-btn {
                padding: 0.75rem 1rem;
                font-size: 0.8125rem;
            }
            
            .menu-item-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .menu-item-name {
                margin-right: 0;
            }
        }

        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
   
   <?php 
if (compterAnnoncesActives('menu') > 0) {
    echo '<div class="menu-annonces-section">';
    afficherNotificationAnnonces('menu');
    echo '<div class="annonces-container">';
    echo '<div class="annonce-wrapper">';
    afficherAnnonces('menu', 'top');
    echo '</div></div>';
    echo '</div>';
}
?>
    <!-- Header -->
   <header class="header">
    <div class="header-content">

        <div class="logo">
            <img src="assets/img/logo.jpg" alt="Logo Mulho">
            <span>Mulho</span>
        </div>

        <div class="header-actions">
            <button class="header-btn" onclick="showMenu()">
                <i class="fas fa-utensils"></i>
                <span>Menu</span>
            </button>
            <button class="header-btn">
                <i class="fas fa-info"></i>
                <span>Info</span>
            </button>
            <button class="header-btn" onclick="openCartModal()" id="cartBtn">
                <i class="fas fa-shopping-bag"></i>
                <span>Panier</span>
                <span class="cart-badge" id="cartBadge">0</span>
            </button>
        </div>
    </div>
</header>


    <!-- Modal Panier -->
    <div class="cart-modal" id="cartModal">
        <div class="cart-content">
            <div class="cart-header">
                <h2 class="cart-title">Votre Panier</h2>
                <button class="close-cart" onclick="closeCartModal()">&times;</button>
            </div>
            <div class="cart-body" id="cartBody">
                <div class="cart-empty" id="cartEmpty">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>Votre panier est vide</h3>
                    <p>Ajoutez des plats délicieux pour commencer</p>
                </div>
                <div class="cart-items" id="cartItems" style="display: none;"></div>
            </div>
            <div class="cart-footer" id="cartFooter" style="display: none;">
                <div class="cart-total">
                    <span class="cart-total-label">Total</span>
                    <span class="cart-total-amount" id="cartTotalAmount">0 F</span>
                </div>
              <button class="checkout-btn" onclick="proceedToCheckout()">
    <i class="fas fa-credit-card"></i>
    Passer la commande
</button>
        
                <button class="continue-shopping" onclick="closeCartModal()">
                    Continuer mes achats
                </button>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container" id="menuContainer">
        <!-- Category Navigation -->
        <div class="category-nav">
            <a href="?show_all=true" 
               class="category-btn <?= isset($_GET['show_all']) && $_GET['show_all'] == 'true' ? 'active' : '' ?>">
                Tout afficher
            </a>
            <?php foreach ($categories_db as $cat): ?>
                <a href="?categorie_id=<?= (int)$cat['id'] ?>" 
                   class="category-btn <?= (!isset($_GET['show_all']) && $categorie_id === (int)$cat['id']) ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['nom']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($show_all): ?>
            <!-- Affichage de tous les plats groupés par catégorie -->
            <?php 
                $plats_par_categorie = [];
                foreach ($tous_plats as $plat) {
                    $plats_par_categorie[$plat['categorie_nom']][] = $plat;
                }
            ?>
            
            <?php foreach ($plats_par_categorie as $nom_categorie => $plats_categorie): ?>
                <div class="category-section">
                    <h2 class="category-title"><?= htmlspecialchars($nom_categorie) ?></h2>
                    <div class="menu-grid list-view">
                        <?php foreach ($plats_categorie as $plat): ?>
    <?php $isAvailable = isset($plat['disponible']) && $plat['disponible'] == 1; ?>
    
    <div class="menu-item <?= !$isAvailable ? 'opacity-50' : '' ?>" 
         <?= $isAvailable ? "onclick=\"openOrderModal('" . htmlspecialchars($plat['nom']) . "', " . $plat['prix'] . ", '" . htmlspecialchars($plat['image'] ?? '') . "', '" . htmlspecialchars($plat['description'] ?? '') . "')\"" : '' ?>>
        
        <?php if ($isAvailable): ?>
            <button class="quick-add-btn" onclick="event.stopPropagation(); quickAddToCart('<?= htmlspecialchars($plat['nom']) ?>', <?= $plat['prix'] ?>, '<?= htmlspecialchars($plat['image'] ?? '') ?>', '<?= htmlspecialchars($plat['description'] ?? '') ?>')">
                <i class="fas fa-plus"></i>
            </button>
        <?php else: ?>
            <div class="quick-add-btn" style="background: #ef4444; cursor: not-allowed;">
                <i class="fas fa-ban"></i>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($plat['image'])): ?>
            <img src="uploads/<?= htmlspecialchars($plat['image']) ?>" 
                    alt="<?= htmlspecialchars($plat['nom']) ?>" 
                    class="menu-item-image <?= !$isAvailable ? 'grayscale' : '' ?>"
                    style="<?= !$isAvailable ? 'filter: grayscale(100%);' : '' ?>">
        <?php else: ?>
            <div class="menu-item-placeholder <?= !$isAvailable ? 'opacity-50' : '' ?>">
                <i class="fas fa-utensils"></i>
            </div>
        <?php endif; ?>
        
        <div class="menu-item-content">
            <div class="menu-item-header">
                <div class="menu-item-name <?= !$isAvailable ? 'line-through text-gray-500' : '' ?>">
                    <?= htmlspecialchars($plat['nom'] ?? 'Nom non disponible') ?>
                    <?php if (!$isAvailable): ?>
                        <span style="color: #ef4444; font-size: 0.8em;"> (Non disponible)</span>
                    <?php endif; ?>
                </div>
                <div class="menu-item-price"><?= number_format($plat['prix'] ?? 0, 0, ',', ' ') ?> F</div>
            </div>
            <?php if (!empty($plat['description'])): ?>
                <div class="menu-item-description"><?= htmlspecialchars($plat['description']) ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
        <?php else: ?>
            <!-- Affichage d'une seule catégorie -->
            <?php
                // Trouver le nom de la catégorie active
                $categorie_nom = '';
                foreach ($categories_db as $cat) {
                    if ($categorie_id === (int)$cat['id']) {
                        $categorie_nom = $cat['nom'];
                        break;
                    }
                }
            ?>
            <div class="view-controls">
                <button class="view-btn active" data-view="list" title="Affichage en liste">
                    <i class="fas fa-list"></i>
                </button>
                <button class="view-btn" data-view="grid" title="Affichage en grille">
                    <i class="fas fa-th"></i>
                </button>
            </div>
            
            <div class="category-section">
                <h2 class="category-title"><?= htmlspecialchars($categorie_nom) ?></h2>
                
                <?php if (!empty($plats)): ?>
                    <div class="menu-grid list-view">
                    <?php foreach ($plats as $plat): ?>
    <?php $isAvailable = isset($plat['disponible']) && $plat['disponible'] == 1; ?>
    
    <div class="menu-item <?= !$isAvailable ? 'opacity-50' : '' ?>" 
        <?= $isAvailable ? "onclick=\"openOrderModal('" . htmlspecialchars($plat['nom']) . "', " . $plat['prix'] . ", '" . htmlspecialchars($plat['image'] ?? '') . "', '" . htmlspecialchars($plat['description'] ?? '') . "')\"" : '' ?>>
        
        <?php if ($isAvailable): ?>
            <button class="quick-add-btn" onclick="event.stopPropagation(); quickAddToCart('<?= htmlspecialchars($plat['nom']) ?>', <?= $plat['prix'] ?>, '<?= htmlspecialchars($plat['image'] ?? '') ?>', '<?= htmlspecialchars($plat['description'] ?? '') ?>')">
                <i class="fas fa-plus"></i>
            </button>
        <?php else: ?>
            <div class="quick-add-btn" style="background: #ef4444; cursor: not-allowed;">
                <i class="fas fa-ban"></i>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($plat['image'])): ?>
            <img src="uploads/<?= htmlspecialchars($plat['image']) ?>" 
                    alt="<?= htmlspecialchars($plat['nom']) ?>" 
                    class="menu-item-image"
                    style="<?= !$isAvailable ? 'filter: grayscale(100%);' : '' ?>">
        <?php else: ?>
            <div class="menu-item-placeholder">
                <i class="fas fa-utensils"></i>
            </div>
        <?php endif; ?>
        
        <div class="menu-item-content">
            <div class="menu-item-header">
                <div class="menu-item-name <?= !$isAvailable ? 'line-through' : '' ?>" style="<?= !$isAvailable ? 'color: #6b7280;' : '' ?>">
                    <?= htmlspecialchars($plat['nom'] ?? 'Nom non disponible') ?>
                    <?php if (!$isAvailable): ?>
                        <span style="color: #ef4444; font-size: 0.8em;"> (Non disponible)</span>
                    <?php endif; ?>
                </div>
                <div class="menu-item-price"><?= number_format($plat['prix'] ?? 0, 0, ',', ' ') ?> F</div>
            </div>
            <?php if (!empty($plat['description'])): ?>
                <div class="menu-item-description"><?= htmlspecialchars($plat['description']) ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-utensils"></i>
                        <h3>Bientôt disponible</h3>
                        <p>Cette catégorie sera prochainement remplie</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer-info">
            Commande sans frais • Confirmation en temps réel
        </div>
    </div>

    <!-- Enhanced Order Modal -->
    <div class="order-modal" id="orderModal">
        <div class="order-content">
            <div class="order-modal-header">
                <button class="close-modal" onclick="closeOrderModal()">&times;</button>
                <div id="orderItemImageContainer">
                    <!-- Image will be inserted here -->
                </div>
            </div>
            
            <div class="order-body">
                <div class="order-header">
                    <h3 class="order-title">Personnaliser</h3>
                    <div class="order-item-name" id="orderItemName">Nom du plat</div>
                    <div class="order-item-price" id="orderItemPrice">Prix</div>
                </div>

                <!-- Special Instructions -->
                <div class="special-instructions">
                    <label class="special-instructions-label">Instructions spéciales</label>
                    <textarea 
                        id="specialInstructions" 
                        class="special-instructions-input" 
                        placeholder="Pas de poivre, moins de sel..."
                        rows="3"></textarea>
                </div>

                <!-- Quantity Section -->
                <div class="quantity-section">
                    <label class="quantity-label">Quantité</label>
                    <div class="quantity-control">
                        <button class="quantity-btn" type="button" onclick="changeQuantity(-1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <div class="quantity-display" id="quantityDisplay">1</div>
                        <button class="quantity-btn" type="button" onclick="changeQuantity(1)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="order-summary">
                    <div class="order-summary-row">
                        <span class="order-summary-label">Prix unitaire</span>
                        <span class="order-summary-value" id="unitPrice">0 F</span>
                    </div>
                    <div class="order-summary-row">
                        <span class="order-summary-label">Quantité</span>
                        <span class="order-summary-value" id="summaryQuantity">1</span>
                    </div>
                    <div class="order-summary-row">
                        <span class="order-summary-label">Total</span>
                        <span class="order-summary-value order-summary-total" id="totalPrice">0 F</span>
                    </div>
                </div>

                <!-- Add to Cart Button -->
                <button class="add-to-cart-btn" onclick="addToCart()">
                    <i class="fas fa-shopping-bag"></i>
                    <span id="addToCartText">Ajouter au panier</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Hero Section -->
    <div class="hero" id="heroSection">
        <h1>Bienvenue chez Mulho</h1>
        <p>Découvrez nos spécialités culinaires authentiques</p>
    </div>
    
    <form id="checkoutForm" action="commander.php" method="POST" style="display: none;">
        <input type="hidden" name="cart_data" id="cartDataInput">
    </form>

<script>
    // Sauvegarder le panier dans sessionStorage
function saveCartToStorage() {
    try {
        sessionStorage.setItem('mulho_cart', JSON.stringify(cartItems));
        console.log('Panier sauvegardé:', cartItems);
    } catch (error) {
        console.error('Erreur lors de la sauvegarde du panier:', error);
    }
}

// Charger le panier depuis sessionStorage
function loadCartFromStorage() {
    try {
        const savedCart = sessionStorage.getItem('mulho_cart');
        if (savedCart) {
            cartItems = JSON.parse(savedCart);
            console.log('Panier chargé:', cartItems);
            updateCartDisplay();
            return true;
        }
    } catch (error) {
        console.error('Erreur lors du chargement du panier:', error);
        cartItems = [];
    }
    return false;
}

// Vider le panier du storage
function clearCartStorage() {
    try {
        sessionStorage.removeItem('mulho_cart');
    } catch (error) {
        console.error('Erreur lors du vidage du panier:', error);
    }
}
    
        // Variables globales
        let selectedItem = {};
        let currentQuantity = 1;
        let cartItems = [];

      document.addEventListener('DOMContentLoaded', function() {
    // Charger le panier sauvegardé
    loadCartFromStorage();
    showMenu();
    updateCartDisplay();
    initViewModes();
});

        // Gestion du panier
        function openCartModal() {
            document.getElementById('cartModal').style.display = 'flex';
            renderCartItems();
        }

        function closeCartModal() {
            document.getElementById('cartModal').style.display = 'none';
        }

        function renderCartItems() {
            const cartItemsContainer = document.getElementById('cartItems');
            const cartEmpty = document.getElementById('cartEmpty');
            const cartFooter = document.getElementById('cartFooter');

            if (cartItems.length === 0) {
                cartEmpty.style.display = 'block';
                cartItemsContainer.style.display = 'none';
                cartFooter.style.display = 'none';
                return;
            }

            cartEmpty.style.display = 'none';
            cartItemsContainer.style.display = 'block';
            cartFooter.style.display = 'block';

            let cartHTML = '';
            let totalAmount = 0;

            cartItems.forEach((item, index) => {
                totalAmount += item.total;
                
                cartHTML += `
                    <div class="cart-item">
                        ${item.image && item.image.trim() !== '' ? 
                            `<img src="uploads/${item.image}" alt="${item.item}" class="cart-item-image">` :
                            `<div class="cart-item-placeholder"><i class="fas fa-utensils"></i></div>`
                        }
                        <div class="cart-item-details">
                            <div class="cart-item-name">${item.item}</div>
                            ${item.specialInstructions ? 
                                `<div class="cart-item-instructions">${item.specialInstructions}</div>` : 
                                ''
                            }
                            <div class="cart-item-price">${item.total.toLocaleString()} F</div>
                        </div>
                        <div class="cart-item-actions">
                            <div class="quantity-controls">
                                <button class="quantity-btn-small" onclick="updateCartItemQuantity(${index}, -1)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="quantity-display-small">${item.quantity}</span>
                                <button class="quantity-btn-small" onclick="updateCartItemQuantity(${index}, 1)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <i class="fas fa-trash remove-item" onclick="removeCartItem(${index})"></i>
                        </div>
                    </div>
                `;
            });

            cartItemsContainer.innerHTML = cartHTML;
            document.getElementById('cartTotalAmount').textContent = totalAmount.toLocaleString() + ' F';
        }

        // Fonction updateCartItemQuantity modifiée
function updateCartItemQuantity(index, change) {
    if (cartItems[index]) {
        const newQuantity = cartItems[index].quantity + change;
        if (newQuantity > 0) {
            cartItems[index].quantity = newQuantity;
            cartItems[index].total = cartItems[index].price * newQuantity;
            saveCartToStorage(); // Sauvegarder après modification
            renderCartItems();
            updateCartDisplay();
        } else {
            removeCartItem(index);
        }
    }
}
        // Fonction removeCartItem modifiée
function removeCartItem(index) {
    cartItems.splice(index, 1);
    saveCartToStorage(); // Sauvegarder après modification
    renderCartItems();
    updateCartDisplay();
    showToast('Article supprimé du panier');
}

// Fonction pour vider complètement le panier (optionnelle)
function clearCart() {
    cartItems = [];
    saveCartToStorage();
    renderCartItems();
    updateCartDisplay();
    showToast('Panier vidé');
}

        function updateCartDisplay() {
            const cartBadge = document.getElementById('cartBadge');
            const totalItems = cartItems.reduce((total, item) => total + item.quantity, 0);
            
            cartBadge.textContent = totalItems;
            if (totalItems > 0) {
                cartBadge.classList.add('show');
            } else {
                cartBadge.classList.remove('show');
            }
        }

      // Remplacez votre fonction proceedToCheckout dans menu.php par celle-ci :

function proceedToCheckout() {
    if (cartItems.length === 0) {
        showToast('Votre panier est vide');
        return;
    }
    
    console.log('Procédure de checkout avec:', cartItems);
    
    // Sauvegarder dans sessionStorage avant de quitter la page
    saveCartToStorage();
    
    // Méthode 1 : Redirection simple (recommandée)
    window.location.href = 'commander.php';
}

// Version alternative avec envoi AJAX (si vous préférez)
function proceedToCheckoutWithAjax() {
    if (cartItems.length === 0) {
        showToast('Votre panier est vide');
        return;
    }
    
    console.log('Checkout AJAX avec:', cartItems);
    
    // Sauvegarder dans le storage
    saveCartToStorage();
    
    // Envoyer au serveur pour synchroniser la session
    fetch('commander.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=sync_cart&cart_data=' + encodeURIComponent(JSON.stringify(cartItems))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Panier synchronisé, redirection...');
            window.location.href = 'commander.php';
        } else {
            console.error('Erreur de synchronisation:', data.message);
            // Redirection quand même avec les données dans le storage
            window.location.href = 'commander.php';
        }
    })
    .catch(error => {
        console.error('Erreur AJAX:', error);
        // Redirection de secours
        window.location.href = 'commander.php';
    });
}

        // Alternative : fonction de redirection directe (à tester si la première ne marche pas)
        function redirectToCommander() {
            console.log('Redirection directe vers commander.php');
            window.location.replace('commander.php');
        }

        // Afficher le menu
        function showMenu() {
            document.getElementById('heroSection').style.display = 'none';
            document.getElementById('menuContainer').style.display = 'block';
        }

        // Afficher la modal de commande (renommé pour éviter les conflits)
        function showOrderModal() {
            document.getElementById('orderModal').style.display = 'flex';
        }

        function openOrderModal(name, price, image, description) {
    fetch('verifier_disponibilite.php?id=' + encodeURIComponent(name))
    .then(response => response.json())
    .then(data => {
        if (!data.disponible) {
            showToast('Ce plat n\'est plus disponible');
            return;
        }
        
        selectedItem = { 
            name: name, 
            price: price, 
            image: image, 
            description: description 
        };
        
        // Update modal content
        document.getElementById('orderItemName').textContent = name;
        document.getElementById('orderItemPrice').textContent = price.toLocaleString() + ' FCFA';
        document.getElementById('unitPrice').textContent = price.toLocaleString() + ' F';
        
        // Update image
        const imageContainer = document.getElementById('orderItemImageContainer');
        if (image && image.trim() !== '') {
            imageContainer.innerHTML = `<img src="uploads/${image}" alt="${name}" class="order-item-image">`;
        } else {
            imageContainer.innerHTML = `<div class="order-item-placeholder"><i class="fas fa-utensils"></i></div>`;
        }
        
        // Reset and update totals
        currentQuantity = 1;
        document.getElementById('quantityDisplay').textContent = '1';
        document.getElementById('specialInstructions').value = '';
        updateOrderSummary();
        showOrderModal();
    }); // ferme le .then(data => {...})
} // ✅ ferme enfin la fonction openOrderModal


        // Fermer la modal de commande
        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
// Fonction quickAddToCart modifiée
function quickAddToCart(name, price, image, description) {
    const orderData = {
        item: name,
        price: price,
        quantity: 1,
        total: price,
        specialInstructions: '',
        image: image,
        id: Date.now()
    };
                cartItems.push(orderData);
    saveCartToStorage(); // Sauvegarder après modification
    updateCartDisplay();

            
            // Trouver le bouton qui a été cliqué
             const clickedButton = event.target.closest('.quick-add-btn');
            
            
          
    clickedButton.classList.add('quick-add-success');
    clickedButton.innerHTML = '<i class="fas fa-check"></i>';
    
    showToast(`${name} ajouté au panier !`);
    
    setTimeout(() => {
        clickedButton.classList.remove('quick-add-success');
        clickedButton.innerHTML = '<i class="fas fa-plus"></i>';
    }, 1000);

    console.log('Panier mis à jour:', cartItems);
}
        // Changer la quantité
        function changeQuantity(change) {
            const newQuantity = currentQuantity + change;
            if (newQuantity >= 1) {
                currentQuantity = newQuantity;
                document.getElementById('quantityDisplay').textContent = currentQuantity;
                updateOrderSummary();
            }
        }

        // Mettre à jour le résumé de commande
        function updateOrderSummary() {
            const total = selectedItem.price * currentQuantity;
            document.getElementById('summaryQuantity').textContent = currentQuantity;
            document.getElementById('totalPrice').textContent = total.toLocaleString() + ' F';
            document.getElementById('addToCartText').textContent = 
                `${total.toLocaleString()} F • Ajouter`;
        }
       // Fonction addToCart modifiée
function addToCart() {
    const specialInstructions = document.getElementById('specialInstructions').value;
    
    const orderData = {
        item: selectedItem.name,
        price: selectedItem.price,
        quantity: currentQuantity,
        total: selectedItem.price * currentQuantity,
        specialInstructions: specialInstructions,
        image: selectedItem.image,
        id: Date.now()
    };

    cartItems.push(orderData);
    saveCartToStorage(); // Sauvegarder après modification
    updateCartDisplay();

    const btn = document.querySelector('.add-to-cart-btn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
    btn.style.background = 'linear-gradient(135deg, var(--success), #16a34a)';
    
    showToast(`${selectedItem.name} ajouté au panier !`);
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.style.background = 'linear-gradient(135deg, var(--accent), #c19654)';
        closeOrderModal();
    }, 1500);

    console.log('Panier mis à jour:', cartItems);
}

        // Afficher une notification toast
        function showToast(message) {
            // Supprimer les anciens toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());
            
            // Créer le nouveau toast
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(toast);
            
            // Animer l'entrée
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            // Supprimer après 3 secondes
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 3000);
        }

        // Fermer les modals en cliquant à l'extérieur
        document.addEventListener('click', function(event) {
            const orderModal = document.getElementById('orderModal');
            const cartModal = document.getElementById('cartModal');
            
            if (event.target === orderModal) {
                closeOrderModal();
            }

            if (event.target === cartModal) {
                closeCartModal();
            }
        });

        // Gestion des touches du clavier
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeOrderModal();
                closeCartModal();
            }
        });
   
   // Gestion des modes d'affichage
function initViewModes() {
    const viewBtns = document.querySelectorAll('.view-btn');
    const menuGrids = document.querySelectorAll('.menu-grid');
    
    // Récupérer le mode sauvegardé ou utiliser 'list' par défaut
    const savedViewMode = localStorage.getItem('viewMode') || 'list';
    
    // Appliquer le mode sauvegardé
    setViewMode(savedViewMode);
    
    // Écouter les clics sur les boutons de mode
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const viewMode = this.getAttribute('data-view');
            setViewMode(viewMode);
            localStorage.setItem('viewMode', viewMode);
        });
    });
}

function setViewMode(mode) {
    const viewBtns = document.querySelectorAll('.view-btn');
    const menuGrids = document.querySelectorAll('.menu-grid');
    
    // Mettre à jour les boutons
    viewBtns.forEach(btn => {
        if (btn.getAttribute('data-view') === mode) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    // Mettre à jour l'affichage des grids
    menuGrids.forEach(grid => {
        grid.classList.remove('list-view', 'grid-view');
        grid.classList.add(`${mode}-view`);
    });
}
</script>
</body>
</html>