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
        $stmt = $conn->query("SELECT p.*, c.nom as categorie_nom FROM plats p 
                              JOIN categories c ON p.categorie_id = c.id 
                              ORDER BY c.nom ASC, p.nom ASC");
        $tous_plats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $plats = [];
        $show_all = true;
    } else {
        // Récupérer les plats de la catégorie sélectionnée
        $stmt = $conn->prepare("SELECT * FROM plats WHERE categorie_id = :categorie_id");
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1a1a1a;
            --secondary: #f8f9fa;
            --accent: #d4a574;
            --text-primary: #2c3e50;
            --text-secondary: #666666;
            --text-light: #999999;
            --white: #ffffff;
            --paper: #fefefe;
            --paper-shadow: rgba(0, 0, 0, 0.08);
            --paper-shadow-hover: rgba(0, 0, 0, 0.15);
            --border: #e9ecef;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --paper-edge: #f1f3f4;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--text-primary);
            line-height: 1.6;
            font-weight: 400;
            min-height: 100vh;
        }

        /* Header Ultra Clean */
        .header {
            background: var(--white);
            padding: 24px 0;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(20px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.03);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 600;
            color: var(--primary);
            letter-spacing: 1px;
        }

        .header-actions {
            display: flex;
            gap: 8px;
        }

        .header-btn {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
            padding: 10px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
        }

        .header-btn:hover {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Badge pour le nombre d'items dans le panier */
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--accent);
            color: var(--white);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 11px;
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

        /* Container avec effet papier */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 48px 24px;
        }

        /* Category Navigation avec effet papier */
        .category-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 64px;
            justify-content: center;
            flex-wrap: wrap;
            background: var(--paper);
            padding: 24px;
            border-radius: 20px;
            box-shadow: 
                0 4px 20px var(--paper-shadow),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            position: relative;
        }

        .category-nav::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--paper-edge), transparent);
        }

        .category-btn {
            padding: 12px 20px;
            background: var(--white);
            color: var(--text-secondary);
            text-decoration: none;
            border: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            position: relative;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .category-btn::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--accent);
            transition: all 0.3s ease;
            transform: translateX(-50%);
            border-radius: 1px;
        }

        .category-btn.active,
        .category-btn:hover {
            color: var(--primary);
            background: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .category-btn.active::after,
        .category-btn:hover::after {
            width: 60%;
        }

        /* Category Section avec effet papier */
        .category-section {
            margin-bottom: 80px;
            background: var(--paper);
            border-radius: 24px;
            padding: 48px;
            box-shadow: 
                0 8px 40px var(--paper-shadow),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
            position: relative;
            overflow: hidden;
        }

        .category-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 24px;
            right: 24px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--paper-edge), transparent);
        }

        .category-section::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 24px;
            right: 24px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--paper-edge), transparent);
        }

        .category-title {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 500;
            color: var(--primary);
            margin-bottom: 40px;
            text-align: center;
            position: relative;
        }

        .category-title::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 50%;
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, var(--accent), #c19654);
            transform: translateX(-50%);
            border-radius: 1px;
        }

        /* Menu Items avec effet feuille de papier */
        .menu-grid {
            display: grid;
            gap: 32px;
        }

        .menu-item {
            display: flex;
            align-items: flex-start;
            padding: 32px;
            background: var(--white);
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            box-shadow: 
                0 4px 20px rgba(0, 0, 0, 0.06),
                0 1px 4px rgba(0, 0, 0, 0.04),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.8);
            overflow: hidden;
        }

        /* Effet de texture papier subtil */
        .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.3) 1px, transparent 1px),
                radial-gradient(circle at 80% 70%, rgba(0, 0, 0, 0.02) 1px, transparent 1px);
            background-size: 40px 40px, 60px 60px;
            pointer-events: none;
        }

        /* Effet de pliage en coin */
        .menu-item::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 24px;
            height: 24px;
            background: linear-gradient(-45deg, var(--paper-edge) 50%, transparent 50%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .menu-item:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.12),
                0 8px 24px rgba(0, 0, 0, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 1);
        }

        .menu-item:hover::after {
            opacity: 1;
        }

        /* Ombre portée réaliste au hover */
        .menu-item:hover {
            box-shadow: 
                0 25px 80px rgba(0, 0, 0, 0.15),
                0 10px 30px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 1);
        }

        .menu-item-image {
            width: 90px;
            height: 90px;
            border-radius: 16px;
            object-fit: cover;
            margin-right: 24px;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 2;
        }

        .menu-item-placeholder {
            width: 90px;
            height: 90px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--secondary), #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 24px;
            margin-right: 24px;
            flex-shrink: 0;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 2;
        }

        .menu-item-content {
            flex: 1;
            min-width: 0;
            position: relative;
            z-index: 2;
        }

        .menu-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .menu-item-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
            margin-right: 16px;
            letter-spacing: -0.5px;
        }

        .menu-item-price {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent);
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--accent), #c19654);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .menu-item-description {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
        }

        /* Bouton rapide d'ajout avec effet papier */
        .quick-add-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, var(--accent), #c19654);
            color: var(--white);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0;
            transform: scale(0.8) translateY(10px);
            box-shadow: 0 4px 16px rgba(212, 165, 116, 0.3);
            z-index: 10;
        }

        .menu-item:hover .quick-add-btn {
            opacity: 1;
            transform: scale(1) translateY(0);
        }

        .quick-add-btn:hover {
            background: linear-gradient(135deg, #c19654, #b8884d);
            transform: scale(1.1) translateY(-2px);
            box-shadow: 0 8px 24px rgba(212, 165, 116, 0.4);
        }

        .quick-add-btn:active {
            transform: scale(0.95) translateY(0);
        }

        /* Modal Panier avec effet papier */
        .cart-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(26, 26, 26, 0.8);
            backdrop-filter: blur(15px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3000;
            animation: fadeIn 0.3s ease;
        }

        .cart-content {
            background: var(--paper);
            border-radius: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            position: relative;
            box-shadow: 
                0 40px 120px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .cart-header {
            background: linear-gradient(135deg, var(--accent), #c19654);
            color: var(--white);
            padding: 32px;
            display: flex;
            justify-content: between;
            align-items: center;
            position: relative;
        }

        .cart-title {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 600;
            flex: 1;
        }

        .close-cart {
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
            border: none;
            font-size: 20px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-cart:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        .cart-body {
            flex: 1;
            overflow-y: auto;
            padding: 0;
        }

        .cart-empty {
            text-align: center;
            padding: 80px 32px;
            color: var(--text-secondary);
        }

        .cart-empty i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .cart-items {
            padding: 0;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 24px 32px;
            border-bottom: 1px solid var(--border);
            gap: 20px;
            background: var(--white);
            transition: background 0.2s ease;
        }

        .cart-item:hover {
            background: var(--secondary);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-image {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            object-fit: cover;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .cart-item-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .cart-item-details {
            flex: 1;
            min-width: 0;
        }

        .cart-item-name {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .cart-item-instructions {
            font-size: 12px;
            color: var(--text-light);
            margin-bottom: 8px;
            font-style: italic;
        }

        .cart-item-price {
            color: var(--accent);
            font-weight: 600;
        }

        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quantity-btn-small {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border);
            background: var(--white);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 12px;
            color: var(--text-secondary);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .quantity-btn-small:hover {
            border-color: var(--accent);
            background: var(--accent);
            color: var(--white);
            transform: scale(1.05);
        }

        .quantity-display-small {
            font-weight: 600;
            min-width: 30px;
            text-align: center;
            font-size: 14px;
        }

        .remove-item {
            color: var(--danger);
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .remove-item:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        .cart-footer {
            border-top: 1px solid var(--border);
            padding: 32px;
            background: var(--secondary);
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 20px;
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
            background: linear-gradient(135deg, var(--accent), #c19654);
            color: var(--white);
            border: none;
            padding: 18px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 16px;
            box-shadow: 0 4px 20px rgba(212, 165, 116, 0.3);
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(212, 165, 116, 0.4);
        }

        .continue-shopping {
            width: 100%;
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
            padding: 14px;
            border-radius: 12px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .continue-shopping:hover {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        /* Order Modal avec effet papier */
        .order-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(26, 26, 26, 0.85);
            backdrop-filter: blur(15px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            animation: fadeIn 0.3s ease;
        }

        .order-content {
            background: var(--paper);
            border-radius: 24px;
            max-width: 400px;
            width: 90%;
            position: relative;
            box-shadow: 
                0 40px 120px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
            overflow: hidden;
            animation: slideUp 0.3s ease;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .order-modal-header {
            position: relative;
            height: 140px;
            background: linear-gradient(135deg, var(--accent), #c19654);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .order-modal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.1);
        }

        .order-item-image {
            width: 90px;
            height: 90px;
            border-radius: 16px;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 2;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .order-item-placeholder {
            width: 90px;
            height: 90px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 32px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 2;
            box-shadow: inset 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .close-modal {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
            border: none;
            font-size: 18px;
            cursor: pointer;
            width: 36px;
            height: 36px;
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
            padding: 32px;
            flex: 1;
            overflow-y: auto;
        }

        .order-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .order-title {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .order-item-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .order-item-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--accent);
        }

        /* Special Instructions avec effet papier */
        .special-instructions {
            margin-bottom: 24px;
        }

        .special-instructions-label {
            display: block;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
        }

        .special-instructions-input {
            width: 100%;
            padding: 16px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: var(--white);
            resize: vertical;
            min-height: 80px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .special-instructions-input:focus {
            outline: none;
            border-color: var(--accent);
            background: var(--white);
            box-shadow: 
                0 0 0 4px rgba(212, 165, 116, 0.1),
                inset 0 2px 4px rgba(0, 0, 0, 0.05);
            transform: scale(1.02);
        }

        .special-instructions-input::placeholder {
            color: var(--text-light);
            font-style: italic;
        }

        /* Quantity Section avec effet papier */
        .quantity-section {
            margin-bottom: 32px;
        }

        .quantity-label {
            display: block;
            margin-bottom: 16px;
            font-size: 16px;
            font-weight: 600;
            color: var(--primary);
        }

        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 16px;
        }

        .quantity-btn {
            width: 48px;
            height: 48px;
            border: 2px solid var(--border);
            background: var(--white);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 18px;
            font-weight: bold;
            color: var(--text-secondary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .quantity-btn:hover {
            border-color: var(--accent);
            background: var(--accent);
            color: var(--white);
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(212, 165, 116, 0.3);
        }

        .quantity-btn:active {
            transform: scale(0.95);
        }

        .quantity-display {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            min-width: 60px;
            text-align: center;
            background: var(--secondary);
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Order Summary avec effet papier */
        .order-summary {
            background: var(--secondary);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .order-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .order-summary-row:last-child {
            margin-bottom: 0;
            padding-top: 12px;
            border-top: 2px solid var(--border);
            font-weight: 700;
        }

        .order-summary-label {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .order-summary-value {
            color: var(--primary);
            font-weight: 600;
            font-size: 14px;
        }

        .order-summary-total {
            font-size: 18px;
            color: var(--accent);
        }

        /* Action Button avec effet papier */
        .add-to-cart-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--accent), #c19654);
            color: var(--white);
            border: none;
            padding: 18px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 24px rgba(212, 165, 116, 0.3);
        }

        .add-to-cart-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .add-to-cart-btn:hover::before {
            left: 100%;
        }

        .add-to-cart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(212, 165, 116, 0.4);
        }

        .add-to-cart-btn:active {
            transform: translateY(0);
        }

        /* Empty State avec effet papier */
        .empty-state {
            text-align: center;
            padding: 120px 32px;
            background: var(--white);
            border-radius: 20px;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }

        .empty-state i {
            font-size: 64px;
            color: var(--text-light);
            margin-bottom: 32px;
            opacity: 0.6;
        }

        .empty-state h3 {
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 12px;
            font-weight: 500;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        /* Hero avec effet papier */
        .hero {
            text-align: center;
            padding: 160px 32px;
            background: var(--paper);
            display: none;
            margin: 48px auto;
            max-width: 800px;
            border-radius: 24px;
            box-shadow: 
                0 20px 80px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 54px;
            font-weight: 500;
            color: var(--primary);
            margin-bottom: 24px;
            letter-spacing: -1px;
        }

        .hero p {
            font-size: 20px;
            color: var(--text-secondary);
            max-width: 500px;
            margin: 0 auto;
        }

        /* Footer avec effet papier */
        .footer-info {
            text-align: center;
            padding: 32px;
            margin-top: 80px;
            background: var(--paper);
            border-radius: 20px;
            font-size: 14px;
            color: var(--text-light);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.06),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }

        /* Animation pour l'ajout rapide */
        @keyframes quickAddSuccess {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); background: linear-gradient(135deg, var(--success), #16a34a); }
            100% { transform: scale(1); }
        }

        .quick-add-success {
            animation: quickAddSuccess 0.6s ease;
        }

        /* Toast avec effet papier */
        .toast {
            position: fixed;
            top: 24px;
            right: 24px;
            background: var(--paper);
            color: var(--text-primary);
            padding: 20px 24px;
            border-radius: 16px;
            z-index: 3000;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
            transform: translateX(400px);
            transition: transform 0.3s ease;
            border: 1px solid var(--success);
        }

        .toast .fa-check-circle {
            color: var(--success);
            font-size: 18px;
        }

        .toast.show {
            transform: translateX(0);
        }

        /* Responsive avec effets papier préservés */
        @media (max-width: 768px) {
            .container {
                padding: 32px 16px;
            }
            
            .header-content {
                padding: 0 16px;
            }
            
            .logo {
                font-size: 24px;
            }
            
            .category-nav {
                padding: 20px;
                margin-bottom: 48px;
            }
            
            .category-section {
                padding: 32px 24px;
                margin-bottom: 60px;
            }
            
            .category-title {
                font-size: 24px;
                margin-bottom: 32px;
            }
            
            .menu-item {
                padding: 24px 20px;
            }
            
            .menu-item-image,
            .menu-item-placeholder {
                width: 70px;
                height: 70px;
                margin-right: 16px;
            }
            
            .menu-item-name {
                font-size: 16px;
            }
            
            .menu-item-price {
                font-size: 16px;
            }
            
            .quick-add-btn {
                width: 36px;
                height: 36px;
                top: 16px;
                right: 16px;
            }

            .order-content {
                width: 95%;
                max-width: none;
                max-height: 90vh;
            }

            .order-body {
                padding: 24px;
            }

            .order-modal-header {
                height: 120px;
            }

            .order-item-image,
            .order-item-placeholder {
                width: 70px;
                height: 70px;
            }
            
            .hero {
                padding: 100px 24px;
                margin: 32px 16px;
            }
            
            .hero h1 {
                font-size: 40px;
            }

            .hero p {
                font-size: 18px;
            }

            .cart-content {
                width: 95%;
                max-width: none;
            }

            .cart-header,
            .cart-footer {
                padding: 24px;
            }

            .empty-state {
                padding: 80px 24px;
            }

            .empty-state i {
                font-size: 48px;
            }

            .empty-state h3 {
                font-size: 20px;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .hidden {
            display: none !important;
        }
        
    </style>
</head>
<body>
   
    <?php 
    // Vérification et notification pour le menu
    if (compterAnnoncesActives('menu') > 0) {
        echo '<div class="menu-annonces-section">';
        afficherNotificationAnnonces('menu');
        afficherAnnonces('menu', 'top');
        echo '</div>';
    }
    ?>
    <!-- Header -->
   <header class="header">
    <div class="header-content">

        <div class="logo" style="display: flex; align-items: center; gap: 10px;">
            <img src="assets/img/logo.jpg" alt="Logo Mulho" style="width:60px; height:60px; object-fit:cover; border-radius:8px;">
            <span style="font-size: 1.8rem; font-weight: bold;">Mulho</span>
        </div>

        <div class="header-actions">
            <button class="header-btn" onclick="showMenu()">
                <i class="fas fa-utensils"></i>
                Menu
            </button>
            <button class="header-btn">
                <i class="fas fa-info"></i>
            </button>
            <button class="header-btn" onclick="openCartModal()" id="cartBtn">
                <i class="fas fa-shopping-bag"></i>
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
                    <div class="menu-grid">
                        <?php foreach ($plats_categorie as $plat): ?>
                            <div class="menu-item" onclick="openOrderModal('<?= htmlspecialchars($plat['nom']) ?>', <?= $plat['prix'] ?>, '<?= htmlspecialchars($plat['image'] ?? '') ?>', '<?= htmlspecialchars($plat['description'] ?? '') ?>')">
                                <button class="quick-add-btn" onclick="event.stopPropagation(); quickAddToCart('<?= htmlspecialchars($plat['nom']) ?>', <?= $plat['prix'] ?>, '<?= htmlspecialchars($plat['image'] ?? '') ?>', '<?= htmlspecialchars($plat['description'] ?? '') ?>')">
                                    <i class="fas fa-plus"></i>
                                </button>
                                
                                <?php if (!empty($plat['image'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($plat['image']) ?>" 
                                         alt="<?= htmlspecialchars($plat['nom']) ?>" 
                                         class="menu-item-image">
                                <?php else: ?>
                                    <div class="menu-item-placeholder">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="menu-item-content">
                                    <div class="menu-item-header">
                                        <div class="menu-item-name"><?= htmlspecialchars($plat['nom'] ?? 'Nom non disponible') ?></div>
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
            
            <div class="category-section">
                <h2 class="category-title"><?= htmlspecialchars($categorie_nom) ?></h2>
                
                <?php if (!empty($plats)): ?>
                    <div class="menu-grid">
                        <?php foreach ($plats as $plat): ?>
                            <div class="menu-item" onclick="openOrderModal('<?= htmlspecialchars($plat['nom']) ?>', <?= $plat['prix'] ?>, '<?= htmlspecialchars($plat['image'] ?? '') ?>', '<?= htmlspecialchars($plat['description'] ?? '') ?>')">
                                <button class="quick-add-btn" onclick="event.stopPropagation(); quickAddToCart('<?= htmlspecialchars($plat['nom']) ?>', <?= $plat['prix'] ?>, '<?= htmlspecialchars($plat['image'] ?? '') ?>', '<?= htmlspecialchars($plat['description'] ?? '') ?>')">
                                    <i class="fas fa-plus"></i>
                                </button>
                                
                                <?php if (!empty($plat['image'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($plat['image']) ?>" 
                                         alt="<?= htmlspecialchars($plat['nom']) ?>" 
                                         class="menu-item-image">
                                <?php else: ?>
                                    <div class="menu-item-placeholder">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="menu-item-content">
                                    <div class="menu-item-header">
                                        <div class="menu-item-name"><?= htmlspecialchars($plat['nom'] ?? 'Nom non disponible') ?></div>
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
        <h1>Bienvenue chez TERAL</h1>
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

        // Ouvrir la modal avec les détails de l'item
        function openOrderModal(name, price, image, description) {
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
        }

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

        // Ajouter au panier depuis la modal
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
   
</script>
</body>
</html>