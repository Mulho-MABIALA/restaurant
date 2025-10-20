<?php
include('../config.php');
require_once '../admin/communication/fonctions_annonces.php';
require_once 'includes/language.php';
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

            /* Theme Colors - Dark Mode (par défaut) */
            --bg-primary: #000000;
            --bg-secondary: rgba(30, 30, 30, 0.6);
            --text-main: #ffffff;
            --text-muted: rgba(255, 255, 255, 0.7);
            --text-dim: rgba(255, 255, 255, 0.5);
            --gold: #D4AF37;
            --border-color: rgba(212, 175, 55, 0.2);
            --header-bg: #000000;
        }

        /* Light Mode */
        body.light-mode {
            --bg-primary: #ffffff;
            --bg-secondary: rgba(249, 250, 251, 0.9);
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --text-dim: #9ca3af;
            --gold: #D4AF37;
            --border-color: rgba(212, 175, 55, 0.3);
            --header-bg: #ffffff;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-main);
            line-height: 1.6;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Header moderne */
        .header {
            background: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
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
            color: var(--gold);
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
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .header-btn:hover {
            background: var(--gold);
            color: #000000;
            border-color: var(--gold);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.4);
        }

        /* Theme Toggle Button */
        .theme-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--gold);
        }

        .theme-toggle:hover {
            background: var(--gold);
            color: #000000;
            border-color: var(--gold);
            transform: translateY(-1px);
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
            max-width: 1100px;
            margin: 0 auto;
            padding: 1rem 2rem;
        }

        /* Navigation des catégories */
        .category-nav-wrapper {
            margin-bottom: 40px;
            padding: 20px 0 10px;
        }

        .category-nav-container {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .nav-arrow {
            background: transparent;
            border: none;
            color: #D4AF37;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0 20px;
            transition: all 0.3s ease;
            opacity: 0.7;
        }

        .nav-arrow:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        .category-nav {
            display: flex;
            gap: 40px;
            justify-content: center;
            background: transparent;
            padding: 0;
            border-radius: 0;
            box-shadow: none;
            position: relative;
        }

        .category-btn {
            padding: 5px 0;
            background: transparent;
            color: var(--text-dim);
            text-decoration: none;
            border: none;
            font-weight: 400;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.4s ease;
            border-radius: 0;
            position: relative;
            white-space: nowrap;
        }

        .category-btn.active,
        .category-btn:hover {
            background: transparent;
            color: var(--gold);
            border: none;
            transform: none;
            box-shadow: none;
            font-weight: 600;
        }

        /* Barre de progression */
        .progress-bar-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 80px;
            position: relative;
        }

        .progress-bar {
            height: 2px;
            background: rgba(255, 255, 255, 0.2);
            position: relative;
            border-radius: 2px;
        }

        .progress-bar::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            background: #D4AF37;
            transition: width 0.4s ease;
        }

        .progress-dots {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            transform: translateY(-50%);
            display: flex;
            justify-content: space-between;
        }

        .progress-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transition: all 0.4s ease;
            position: relative;
        }

        .progress-dot.active {
            background: #D4AF37;
            box-shadow: 0 0 12px rgba(212, 175, 55, 0.6);
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
            margin-bottom: 2rem;
            background: transparent;
            border-radius: 0;
            padding: 1rem 0;
            box-shadow: none;
        }

        .category-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 3.5vw, 2.5rem);
            font-weight: 600;
            color: var(--gold);
            margin-bottom: 40px;
            text-align: center;
            position: relative;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        .category-title::after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 50%;
            width: 0;
            height: 0;
            background: transparent;
            transform: translateX(-50%);
            border-radius: 0;
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
            padding: 0;
            margin-bottom: 25px;
            background: transparent;
            border: none;
            border-radius: 0;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: visible;
            gap: 25px;
        }

        .menu-item:hover {
            transform: none;
            box-shadow: none;
            border-color: transparent;
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
            width: 100px;
            height: 100px;
            border-radius: 10px;
            object-fit: cover;
            margin-right: 0;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(212, 175, 55, 0.3);
            transition: all 0.4s ease;
        }

        .menu-item-image:hover {
            border-color: #D4AF37;
            box-shadow: 0 8px 24px rgba(212, 175, 55, 0.4);
            transform: scale(1.05);
        }

        .menu-item-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(212, 175, 55, 0.05) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(212, 175, 55, 0.5);
            font-size: 1.8rem;
            margin-right: 0;
            flex-shrink: 0;
            box-shadow: none;
            border: 2px solid rgba(212, 175, 55, 0.2);
            transition: all 0.4s ease;
        }

        .menu-item-placeholder:hover {
            border-color: rgba(212, 175, 55, 0.4);
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.15) 0%, rgba(212, 175, 55, 0.08) 100%);
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
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-main);
            margin-right: 1rem;
            line-height: 1.3;
            margin-bottom: 6px;
        }

        .menu-item-price {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--gold);
            flex-shrink: 0;
            white-space: nowrap;
            margin-left: 25px;
        }

        .menu-item-description {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 0;
            font-weight: 300;
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
            padding: 100px 20px;
            background: transparent;
            border-radius: 0;
            box-shadow: none;
        }

        .empty-state i {
            font-size: 4rem;
            color: rgba(212, 175, 55, 0.3);
            margin-bottom: 30px;
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: #D4AF37;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 1rem;
        }

        /* Modal du panier - Design moderne */
        .cart-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
            animation: fadeIn 0.3s ease;
            padding: 1rem;
        }

        .cart-modal.show {
            display: flex;
        }

        .cart-content {
            background: var(--white);
            border-radius: 20px;
            max-width: 600px;
            width: 100%;
            max-height: 95vh;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from {
                transform: translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .cart-header {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            color: var(--white);
            padding: 1.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            position: relative;
            overflow: hidden;
        }

        .cart-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #D4AF37, #f59e0b, #D4AF37);
        }

        .cart-header-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .cart-icon-wrapper {
            width: 48px;
            height: 48px;
            background: rgba(212, 175, 55, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
        }

        .cart-icon-wrapper i {
            font-size: 1.25rem;
        }

        .cart-title-wrapper {
            flex: 1;
        }

        .cart-title {
            font-family: 'Inter', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
            color: white;
        }

        .cart-item-count {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            margin-top: 2px;
        }

        .close-cart {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .close-cart:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .cart-body {
            flex: 1;
            overflow-y: auto;
            background: #f9fafb;
        }

        /* Scrollbar personnalisée */
        .cart-body::-webkit-scrollbar {
            width: 8px;
        }

        .cart-body::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .cart-body::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 4px;
        }

        .cart-body::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        .cart-empty {
            text-align: center;
            padding: 5rem 2rem;
            color: #6b7280;
            background: white;
        }

        .cart-empty-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
        }

        .cart-empty-icon i {
            font-size: 2rem;
        }

        .cart-empty h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .cart-empty p {
            font-size: 0.875rem;
            color: #9ca3af;
        }

        .cart-items {
            padding: 1rem;
            background: white;
        }

        .cart-item {
            display: flex;
            align-items: flex-start;
            padding: 1.25rem;
            background: white;
            border-radius: 12px;
            gap: 1rem;
            transition: all 0.3s ease;
            margin-bottom: 0.75rem;
            border: 1px solid #e5e7eb;
            position: relative;
        }

        .cart-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        .cart-item:last-child {
            margin-bottom: 0;
        }

        .cart-item-image {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .cart-item-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .cart-item-content {
            flex: 1;
            min-width: 0;
        }

        .cart-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
            gap: 0.5rem;
        }

        .cart-item-name {
            font-weight: 600;
            color: #111827;
            font-size: 0.9375rem;
            line-height: 1.3;
            flex: 1;
        }

        .remove-item {
            color: #ef4444;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .remove-item:hover {
            background: rgba(239, 68, 68, 0.1);
            transform: scale(1.1);
        }

        .cart-item-instructions {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 0.75rem;
            font-style: italic;
            line-height: 1.4;
            padding: 0.5rem;
            background: #f9fafb;
            border-radius: 6px;
            border-left: 2px solid #D4AF37;
        }

        .cart-item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-item-price {
            color: #D4AF37;
            font-weight: 700;
            font-size: 1rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            background: #f3f4f6;
            padding: 0.25rem;
            border-radius: 8px;
        }

        .quantity-btn-small {
            width: 28px;
            height: 28px;
            border: none;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.75rem;
            color: #6b7280;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .quantity-btn-small:hover {
            background: #D4AF37;
            color: white;
            transform: scale(1.1);
        }

        .quantity-btn-small:active {
            transform: scale(0.95);
        }

        .quantity-display-small {
            font-weight: 700;
            min-width: 24px;
            text-align: center;
            font-size: 0.875rem;
            color: #111827;
        }

        .cart-footer {
            background: white;
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        .cart-summary {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.25rem;
        }

        .cart-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }

        .cart-summary-row:last-child {
            margin-bottom: 0;
        }

        .cart-summary-label {
            color: #6b7280;
            font-weight: 500;
        }

        .cart-summary-value {
            color: #111827;
            font-weight: 600;
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 2px dashed #e5e7eb;
            margin-top: 0.75rem;
        }

        .cart-total-label {
            color: #111827;
            font-size: 1.125rem;
            font-weight: 700;
        }

        .cart-total-amount {
            color: #D4AF37;
            font-size: 1.375rem;
            font-weight: 800;
        }

        .cart-promo {
            background: #fef3c7;
            border: 1px dashed #f59e0b;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: #92400e;
        }

        .cart-promo i {
            color: #f59e0b;
        }

        .checkout-btn {
            width: 100%;
            background: linear-gradient(135deg, #1f2937, #111827);
            color: white;
            border: none;
            padding: 1.125rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.625rem;
            position: relative;
            overflow: hidden;
        }

        .checkout-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .checkout-btn:hover::before {
            left: 100%;
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .checkout-btn:active {
            transform: translateY(0);
        }

        .continue-shopping {
            width: 100%;
            background: white;
            color: #6b7280;
            border: 1px solid #e5e7eb;
            padding: 0.875rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .continue-shopping:hover {
            background: #f9fafb;
            color: #111827;
            border-color: #d1d5db;
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
            background: transparent;
            border-radius: 0;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.5);
            box-shadow: none;
            border-top: 1px solid rgba(212, 175, 55, 0.2);
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
            <button class="theme-toggle" onclick="toggleTheme()" id="themeToggle" title="Changer le thème">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>
            <button class="header-btn" onclick="openCartModal()" id="cartBtn">
                <i class="fas fa-shopping-bag"></i>
                <span>Panier</span>
                <span class="cart-badge" id="cartBadge">0</span>
            </button>
        </div>
    </div>
</header>


    <!-- Modal Panier - Design amélioré -->
    <div class="cart-modal" id="cartModal">
        <div class="cart-content">
            <!-- Header moderne -->
            <div class="cart-header">
                <div class="cart-header-content">
                    <div class="cart-icon-wrapper">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="cart-title-wrapper">
                        <h2 class="cart-title">Mon Panier</h2>
                        <div class="cart-item-count" id="cartItemCount">0 article</div>
                    </div>
                </div>
                <button class="close-cart" onclick="closeCartModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Corps du panier -->
            <div class="cart-body" id="cartBody">
                <!-- État vide -->
                <div class="cart-empty" id="cartEmpty">
                    <div class="cart-empty-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h3>Votre panier est vide</h3>
                    <p>Découvrez nos délicieux plats et commencez votre commande</p>
                </div>
                <!-- Liste des articles -->
                <div class="cart-items" id="cartItems" style="display: none;"></div>
            </div>

            <!-- Footer avec résumé et actions -->
            <div class="cart-footer" id="cartFooter" style="display: none;">
                <!-- Message info QR Code -->
                <div class="cart-promo">
                    <i class="fas fa-utensils"></i>
                    <span>Votre commande sera envoyée directement à la cuisine</span>
                </div>

                <!-- Résumé de la commande -->
                <div class="cart-summary">
                    <div class="cart-summary-row">
                        <span class="cart-summary-label">Sous-total</span>
                        <span class="cart-summary-value" id="cartSubtotal">0 F</span>
                    </div>
                    <div class="cart-total">
                        <span class="cart-total-label">Total</span>
                        <span class="cart-total-amount" id="cartTotalAmount">0 F</span>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <button class="checkout-btn" onclick="proceedToCheckout()">
                    <i class="fas fa-arrow-right"></i>
                    <span>Passer la commande</span>
                </button>

                <button class="continue-shopping" onclick="closeCartModal()">
                    <i class="fas fa-arrow-left" style="margin-right: 0.5rem;"></i>
                    Continuer mes achats
                </button>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container" id="menuContainer">
        <!-- Category Navigation avec flèches et barre de progression -->
        <div class="category-nav-wrapper">
            <div class="category-nav-container">
                <button class="nav-arrow" id="prevCategory" onclick="navigateCategories(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <div class="category-nav">
                    <a href="?show_all=true"
                       class="category-btn <?= isset($_GET['show_all']) && $_GET['show_all'] == 'true' ? 'active' : '' ?>"
                       data-index="0">
                        Tout afficher
                    </a>
                    <?php
                    $index = 1;
                    foreach ($categories_db as $cat): ?>
                        <a href="?categorie_id=<?= (int)$cat['id'] ?>"
                           class="category-btn <?= (!isset($_GET['show_all']) && $categorie_id === (int)$cat['id']) ? 'active' : '' ?>"
                           data-index="<?= $index ?>">
                            <?= htmlspecialchars($cat['nom']) ?>
                        </a>
                    <?php
                    $index++;
                    endforeach; ?>
                </div>

                <button class="nav-arrow" id="nextCategory" onclick="navigateCategories(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <!-- Barre de progression -->
            <div class="progress-bar-container">
                <div class="progress-bar" id="progressBar">
                    <div class="progress-dots" id="progressDots">
                        <!-- Les points seront générés par JavaScript -->
                    </div>
                </div>
            </div>
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

    // Charger le thème sauvegardé
    loadTheme();
});

// Fonction pour changer le thème
function toggleTheme() {
    const body = document.body;
    const themeIcon = document.getElementById('themeIcon');

    if (body.classList.contains('light-mode')) {
        // Passer en mode sombre
        body.classList.remove('light-mode');
        themeIcon.classList.remove('fa-sun');
        themeIcon.classList.add('fa-moon');
        localStorage.setItem('theme', 'dark');
    } else {
        // Passer en mode clair
        body.classList.add('light-mode');
        themeIcon.classList.remove('fa-moon');
        themeIcon.classList.add('fa-sun');
        localStorage.setItem('theme', 'light');
    }
}

// Fonction pour charger le thème sauvegardé
function loadTheme() {
    const savedTheme = localStorage.getItem('theme');
    const body = document.body;
    const themeIcon = document.getElementById('themeIcon');

    if (savedTheme === 'light') {
        body.classList.add('light-mode');
        themeIcon.classList.remove('fa-moon');
        themeIcon.classList.add('fa-sun');
    } else {
        body.classList.remove('light-mode');
        themeIcon.classList.remove('fa-sun');
        themeIcon.classList.add('fa-moon');
    }
}

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
            const cartItemCount = document.getElementById('cartItemCount');

            if (cartItems.length === 0) {
                cartEmpty.style.display = 'block';
                cartItemsContainer.style.display = 'none';
                cartFooter.style.display = 'none';
                cartItemCount.textContent = '0 article';
                return;
            }

            cartEmpty.style.display = 'none';
            cartItemsContainer.style.display = 'block';
            cartFooter.style.display = 'block';

            let cartHTML = '';
            let totalAmount = 0;
            let totalItems = 0;

            cartItems.forEach((item, index) => {
                totalAmount += item.total;
                totalItems += item.quantity;

                cartHTML += `
                    <div class="cart-item" style="animation: fadeIn 0.3s ease;">
                        ${item.image && item.image.trim() !== '' ?
                            `<img src="uploads/${item.image}" alt="${item.item}" class="cart-item-image">` :
                            `<div class="cart-item-placeholder"><i class="fas fa-utensils"></i></div>`
                        }
                        <div class="cart-item-content">
                            <div class="cart-item-header">
                                <div class="cart-item-name">${item.item}</div>
                                <i class="fas fa-trash remove-item" onclick="removeCartItem(${index})" title="Supprimer"></i>
                            </div>
                            ${item.specialInstructions ?
                                `<div class="cart-item-instructions"><i class="fas fa-info-circle" style="margin-right: 0.25rem;"></i>${item.specialInstructions}</div>` :
                                ''
                            }
                            <div class="cart-item-footer">
                                <div class="cart-item-price">${item.total.toLocaleString()} FCFA</div>
                                <div class="quantity-controls">
                                    <button class="quantity-btn-small" onclick="updateCartItemQuantity(${index}, -1)" title="Diminuer">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <span class="quantity-display-small">${item.quantity}</span>
                                    <button class="quantity-btn-small" onclick="updateCartItemQuantity(${index}, 1)" title="Augmenter">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            cartItemsContainer.innerHTML = cartHTML;

            // Mettre à jour les totaux
            document.getElementById('cartSubtotal').textContent = totalAmount.toLocaleString() + ' FCFA';
            document.getElementById('cartTotalAmount').textContent = totalAmount.toLocaleString() + ' FCFA';

            // Mettre à jour le compteur d'articles
            cartItemCount.textContent = totalItems + (totalItems > 1 ? ' articles' : ' article');
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

// Navigation des catégories et barre de progression
document.addEventListener('DOMContentLoaded', function() {
    const categoryBtns = document.querySelectorAll('.category-btn');
    const progressBar = document.getElementById('progressBar');
    const progressDotsContainer = document.getElementById('progressDots');

    // Générer les points de progression
    categoryBtns.forEach((btn, index) => {
        const dot = document.createElement('div');
        dot.className = 'progress-dot';
        dot.setAttribute('data-index', index);
        progressDotsContainer.appendChild(dot);
    });

    // Mettre à jour la barre de progression
    function updateProgressBar() {
        const activeBtn = document.querySelector('.category-btn.active');
        if (activeBtn) {
            const activeIndex = parseInt(activeBtn.getAttribute('data-index'));
            const totalCategories = categoryBtns.length;
            const progressPercent = (activeIndex / (totalCategories - 1)) * 100;

            progressBar.style.setProperty('--progress-width', progressPercent + '%');
            progressBar.querySelector('::before').style.width = progressPercent + '%';

            // Mettre à jour les points
            document.querySelectorAll('.progress-dot').forEach((dot, index) => {
                if (index <= activeIndex) {
                    dot.classList.add('active');
                } else {
                    dot.classList.remove('active');
                }
            });
        }
    }

    // Mettre à jour au chargement
    updateProgressBar();
});

// Navigation avec les flèches
function navigateCategories(direction) {
    const categoryBtns = Array.from(document.querySelectorAll('.category-btn'));
    const activeBtn = document.querySelector('.category-btn.active');

    if (!activeBtn) return;

    const currentIndex = parseInt(activeBtn.getAttribute('data-index'));
    const newIndex = currentIndex + direction;

    if (newIndex >= 0 && newIndex < categoryBtns.length) {
        window.location.href = categoryBtns[newIndex].getAttribute('href');
    }
}
</script>
</body>
</html>