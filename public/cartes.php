<?php
session_start();
require_once '../config.php';
require_once 'includes/language.php';

try {
    // Récupération des catégories et des plats
    $stmt = $conn->query("SELECT id, nom FROM categories ORDER BY nom");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->query("
        SELECT p.id, p.nom, p.description, p.prix, p.image, c.nom AS categorie_nom, p.categorie_id
        FROM plats p
        LEFT JOIN categories c ON p.categorie_id = c.id
        ORDER BY c.nom, p.nom ASC
    ");
    $plats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organiser les plats par catégorie
    $platsByCategory = [];
    foreach ($plats as $plat) {
        $categoryName = $plat['categorie_nom'] ?? 'Non catégorisé';
        if (!isset($platsByCategory[$categoryName])) {
            $platsByCategory[$categoryName] = [];
        }
        $platsByCategory[$categoryName][] = $plat;
    }

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('nav.carte') ?> - Restaurant</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'playfair': ['Playfair Display', 'serif'],
                        'inter': ['Inter', 'sans-serif']
                    },
                    colors: {
                        'gold': '#D4AF37',
                        'dark-green': '#2D4A2B'
                    }
                }
            }
        }
    </script>
    
    <style>
        body {
            background: #000000;
            font-family: 'Inter', sans-serif;
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            width: 100%;
            height: 100vh;
            min-height: 600px;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0.5) 50%, rgba(0, 0, 0, 0.8) 100%), 
                        url('https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80') center/cover;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-content {
            text-align: center;
            color: white;
            max-width: 1200px;
            padding: 0 2rem;
        }

        .restaurant-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(3rem, 8vw, 6rem);
            font-weight: 700;
            font-style: italic;
            line-height: 0.9;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #D4AF37 0%, #F4D03F 50%, #D4AF37 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
        }

        .subtitle {
            font-family: 'Inter', sans-serif;
            font-size: clamp(1rem, 2.5vw, 1.5rem);
            font-weight: 300;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 2rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .scroll-to-menu {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            text-align: center;
            animation: bounce 2s infinite;
            cursor: pointer;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateX(-50%) translateY(0);
            }
            40% {
                transform: translateX(-50%) translateY(-10px);
            }
            60% {
                transform: translateX(-50%) translateY(-5px);
            }
        }

        /* Menu Section */
        .menu-section {
            background: #000000;
            padding: 80px 0;
            min-height: 100vh;
        }

        .main-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(3rem, 8vw, 5rem);
            font-weight: 700;
            color: #D4AF37;
            margin-bottom: 20px;
            text-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
            text-align: center;
        }

        .menu-subtitle {
            font-family: 'Inter', sans-serif;
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 300;
            margin-bottom: 60px;
            text-align: center;
        }

        /* Navigation */
        .nav-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 40px 0 80px 0;
            position: relative;
        }

        .nav-arrow {
            color: #D4AF37;
            font-size: 24px;
            cursor: pointer;
            padding: 20px;
            transition: all 0.3s ease;
            user-select: none;
        }

        .nav-arrow:hover {
            color: #F4D03F;
            transform: scale(1.2);
        }

        .nav-categories {
            display: flex;
            align-items: center;
            gap: 60px;
            position: relative;
        }

        .nav-category {
            color: rgba(255, 255, 255, 0.6);
            font-size: 1.1rem;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.4s ease;
            position: relative;
            padding: 10px 0;
            white-space: nowrap;
        }

        .nav-category.active {
            color: #D4AF37;
            font-weight: 600;
        }

        .nav-category:hover {
            color: #F4D03F;
        }

        /* Progress Bar */
        .progress-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto 60px auto;
            position: relative;
            height: 4px;
        }

        .progress-bg {
            width: 100%;
            height: 2px;
            background: rgba(255, 255, 255, 0.2);
            position: relative;
        }

        .progress-bar {
            height: 2px;
            background: #D4AF37;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: absolute;
            top: 0;
            left: 0;
        }

        .progress-dots {
            position: absolute;
            top: -4px;
            left: 0;
            width: 100%;
            display: flex;
            justify-content: space-between;
        }

        .progress-dot {
            width: 10px;
            height: 10px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .progress-dot.active {
            background: #D4AF37;
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.5);
        }

        /* Menu Content */
        .menu-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
            min-height: 60vh;
        }

        .category-content {
            display: none;
            animation: fadeInUp 0.8s ease-out;
        }

        .category-content.active {
            display: block;
        }

        .category-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2rem, 4vw, 3rem);
            color: #D4AF37;
            text-align: center;
            margin-bottom: 80px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 3px;
        }

        /* Menu Items */
        .menu-items {
            display: flex;
            flex-direction: column;
            gap: 40px;
        }

        .menu-item {
            display: flex;
            align-items: flex-start;
            gap: 30px;
            position: relative;
            opacity: 0;
            transform: translateY(30px);
            animation: slideInUp 0.6s ease-out forwards;
        }

        .menu-item:nth-child(2) { animation-delay: 0.1s; }
        .menu-item:nth-child(3) { animation-delay: 0.2s; }
        .menu-item:nth-child(4) { animation-delay: 0.3s; }
        .menu-item:nth-child(5) { animation-delay: 0.4s; }

        .item-image {
            flex-shrink: 0;
            width: 120px;
            height: 120px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(212, 175, 55, 0.3);
            transition: all 0.4s ease;
        }

        .item-image:hover {
            border-color: #D4AF37;
            box-shadow: 0 8px 24px rgba(212, 175, 55, 0.4);
            transform: scale(1.05);
        }

        .no-image {
            flex-shrink: 0;
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(212, 175, 55, 0.05) 100%);
            border: 2px solid rgba(212, 175, 55, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(212, 175, 55, 0.5);
            font-size: 2rem;
            transition: all 0.4s ease;
        }

        .no-image:hover {
            border-color: rgba(212, 175, 55, 0.4);
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.15) 0%, rgba(212, 175, 55, 0.08) 100%);
        }

        .item-content {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-family: 'Inter', sans-serif;
            font-size: 1.4rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .item-description {
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.5;
            font-weight: 300;
        }

        .item-price {
            font-family: 'Inter', sans-serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: #D4AF37;
            white-space: nowrap;
            margin-left: 30px;
            text-align: right;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 100px 20px;
            color: rgba(255, 255, 255, 0.5);
        }

        .empty-state i {
            font-size: 4rem;
            color: rgba(212, 175, 55, 0.3);
            margin-bottom: 30px;
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: 15px;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .nav-categories {
                gap: 40px;
            }
            
            .menu-container {
                padding: 0 20px;
            }
        }

        @media (max-width: 768px) {
            .nav-categories {
                gap: 20px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .nav-category {
                font-size: 0.9rem;
            }
            
            .menu-item {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 20px;
            }
            
            .item-content {
                flex-direction: column;
                align-items: center;
                gap: 15px;
                width: 100%;
            }
            
            .item-price {
                margin-left: 0;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .nav-arrow {
                display: none;
            }
            
            .item-image,
            .no-image {
                width: 100px;
                height: 100px;
            }
        }

        .price-format {
            font-family: 'Inter', sans-serif;
            font-weight: 700;
        }

        /* Gallery Section */
        .gallery-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            padding: 100px 0;
            position: relative;
        }

        .gallery-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.5rem, 6vw, 4rem);
            font-weight: 700;
            color: #D4AF37;
            text-align: center;
            margin-bottom: 3rem;
            text-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
        }

        .gallery-item {
            position: relative;
            height: 300px;
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            border: 2px solid rgba(212, 175, 55, 0.2);
        }

        .gallery-item:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border-color: rgba(212, 175, 55, 0.6);
        }

        .gallery-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.4s ease;
        }

        .gallery-item:hover .gallery-image {
            transform: scale(1.1);
        }

        .gallery-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.4) 0%, rgba(212, 175, 55, 0.2) 100%);
            opacity: 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .gallery-item:hover .gallery-overlay {
            opacity: 1;
        }

        .gallery-dish-name {
            color: white;
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.8);
        }

        .gallery-view-icon {
            color: #D4AF37;
            font-size: 2rem;
            margin-top: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.8);
        }

        /* Lightbox Modal */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 9999;
            backdrop-filter: blur(10px);
        }

        .lightbox.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lightbox-content {
            position: relative;
            max-width: 90vw;
            max-height: 90vh;
            margin: 0 auto;
        }

        .lightbox-image {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
        }

        .lightbox-info {
            text-align: center;
            color: white;
            margin-top: 20px;
        }

        .lightbox-dish-name {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 600;
            color: #D4AF37;
            margin-bottom: 10px;
        }
        .lightbox-dish-description,
.lightbox-dish-price {
    display: none !important;
}
.lightbox-info {
    display: none !important;
}

        .lightbox-dish-description {
            font-family: 'Inter', sans-serif;
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 10px;
        }

        .lightbox-dish-price {
            font-family: 'Inter', sans-serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: #D4AF37;
        }

        .lightbox-close {
            position: absolute;
            top: -60px;
            right: 0;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 10px;
            transition: all 0.3s ease;
        }

        .lightbox-close:hover {
            color: #D4AF37;
            transform: scale(1.2);
        }

        .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-size: 2.5rem;
            cursor: pointer;
            padding: 20px;
            transition: all 0.3s ease;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lightbox-nav:hover {
            color: #D4AF37;
            background: rgba(212, 175, 55, 0.2);
            transform: translateY(-50%) scale(1.1);
        }

        .lightbox-prev {
            left: -80px;
        }

        .lightbox-next {
            right: -80px;
        }

        .lightbox-counter {
            position: absolute;
            top: -60px;
            left: 50%;
            transform: translateX(-50%);
            color: rgba(255, 255, 255, 0.8);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
        }

        /* Responsive Gallery */
        @media (max-width: 768px) {
            .gallery-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                padding: 0 20px;
            }

            .gallery-item {
                height: 250px;
            }

            .lightbox-nav {
                font-size: 1.5rem;
                width: 45px;
                height: 45px;
            }

            .lightbox-prev {
                left: -60px;
            }

            .lightbox-next {
                right: -60px;
            }

            .lightbox-dish-name {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .gallery-grid {
                grid-template-columns: 1fr;
                padding: 0 15px;
            }

            .lightbox-nav {
                position: fixed;
                top: auto;
                bottom: 20px;
                transform: none;
                font-size: 1.3rem;
                width: 40px;
                height: 40px;
            }

            .lightbox-prev {
                left: 20px;
            }

            .lightbox-next {
                right: 20px;
            }
        }
    </style>
</head>
<?php include('includes/navbar.php'); ?>
<body class="bg-black">
    
    <!-- Hero Section -->
    <section class="hero-section" id="hero">
        <div class="hero-content">
            <h1 class="restaurant-title">
                <?= t('nav.carte') ?>
            </h1>

            <p class="subtitle">
                <?= t('menu.subtitle_carte') ?>
            </p>
        </div>

        <div class="scroll-to-menu" onclick="scrollToMenu()">
            <p class="text-sm opacity-75 mb-2"><?= t('menu.discover_our_menu') ?></p>
            <i class="fas fa-chevron-down text-xl"></i>
        </div>
    </section>

    <!-- Menu Section -->
    <section class="menu-section" id="menu">
        <div class="container mx-auto">
            <h1 class="main-title"><?= t('nav.carte') ?></h1>
            <p class="menu-subtitle"><?= t('menu.discover_menu_2025') ?></p>
            
            <!-- Navigation -->
            <div class="nav-container">
                <div class="nav-arrow" id="prevBtn">
                    <i class="fas fa-chevron-left"></i>
                </div>
                
                <div class="nav-categories" id="navCategories">
                    <?php if (!empty($platsByCategory)): ?>
                        <?php $index = 0; ?>
                        <?php foreach ($platsByCategory as $categoryName => $plats): ?>
                            <div class="nav-category <?= $index === 0 ? 'active' : '' ?>" 
                                 data-category="<?= $index ?>" 
                                 data-name="<?= htmlspecialchars($categoryName) ?>">
                                <?= htmlspecialchars($categoryName) ?>
                            </div>
                            <?php $index++; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="nav-category active" data-category="0"><?= t('menu.coming_soon') ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="nav-arrow" id="nextBtn">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress-bg">
                    <div class="progress-bar" id="progressBar"></div>
                </div>
                <div class="progress-dots" id="progressDots">
                    <?php if (!empty($platsByCategory)): ?>
                        <?php for ($i = 0; $i < count($platsByCategory); $i++): ?>
                            <div class="progress-dot <?= $i === 0 ? 'active' : '' ?>"></div>
                        <?php endfor; ?>
                    <?php else: ?>
                        <div class="progress-dot active"></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Menu Content -->
            <div class="menu-container">
                <?php if (!empty($platsByCategory)): ?>
                    <?php $categoryIndex = 0; ?>
                    <?php foreach ($platsByCategory as $categoryName => $plats): ?>
                        <div class="category-content <?= $categoryIndex === 0 ? 'active' : '' ?>" 
                             id="category-<?= $categoryIndex ?>">
                            
                            <h2 class="category-title"><?= strtoupper(htmlspecialchars($categoryName)) ?></h2>
                            
                            <div class="menu-items">
                                <?php foreach ($plats as $plat): ?>
                                    <div class="menu-item">
                                        <!-- Image du plat -->
                                        <?php if (!empty($plat['image']) && file_exists('uploads/' . $plat['image'])): ?>
                                            <img src="uploads/<?= htmlspecialchars($plat['image']) ?>" 
                                                 alt="<?= htmlspecialchars($plat['nom']) ?>"
                                                 class="item-image">
                                        <?php else: ?>
                                            <div class="no-image">
                                                <i class="fas fa-utensils"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Contenu du plat -->
                                        <div class="item-content">
                                            <div class="item-details">
                                                <h3 class="item-name"><?= htmlspecialchars($plat['nom']) ?></h3>
                                                <?php if (!empty($plat['description'])): ?>
                                                    <p class="item-description"><?= htmlspecialchars($plat['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="item-price price-format">
                                                <?= number_format($plat['prix'], 0, ',', ' ') ?> FCFA
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php $categoryIndex++; ?>
                    <?php endforeach; ?>
                    
                <?php else: ?>
                    <!-- État vide -->
                    <div class="category-content active">
                        <div class="empty-state">
                            <i class="fas fa-utensils"></i>
                            <h3><?= t('menu.in_preparation') ?></h3>
                            <p><?= t('menu.chef_working') ?><br><?= t('menu.come_back_soon') ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section class="gallery-section" id="gallery">
        <div class="container mx-auto">
            <h2 class="gallery-title"><?= t('nav.gallery') ?></h2>
            
            <div class="gallery-grid" id="galleryGrid">
                <!-- Les images seront générées par PHP -->
                <?php
                // Récupérer toutes les images des plats
                $galleryImages = [];
                if (!empty($platsByCategory)) {
                    foreach ($platsByCategory as $categoryName => $plats) {
                        foreach ($plats as $plat) {
                            if (!empty($plat['image'])) {
                                // Vérifier si le fichier existe
                                $imagePath = 'uploads/' . $plat['image'];
                                if (file_exists($imagePath)) {
                                    $galleryImages[] = $plat;
                                }
                            }
                        }
                    }
                }
                
                // Debug: Afficher le nombre d'images trouvées
                // echo "<!-- Nombre d'images trouvées: " . count($galleryImages) . " -->";
                
                if (!empty($galleryImages)):
                    foreach ($galleryImages as $index => $plat):
                ?>
                    <div class="gallery-item" onclick="openLightbox(<?= $index ?>)">
                        <img src="uploads/<?= htmlspecialchars($plat['image']) ?>" 
                             alt="<?= htmlspecialchars($plat['nom']) ?>"
                             class="gallery-image"
                             loading="lazy">
                        <div class="gallery-overlay">
                            <div class="gallery-dish-name"><?= htmlspecialchars($plat['nom']) ?></div>
                            <i class="fas fa-search-plus gallery-view-icon"></i>
                        </div>
                    </div>
                <?php 
                    endforeach;
                else:
                ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 5rem 1rem; color: rgba(255, 255, 255, 0.6);">
                        <i class="fas fa-images" style="font-size: 4rem; color: rgba(212, 175, 55, 0.3); margin-bottom: 2rem; display: block;"></i>
                        <h3 style="font-family: 'Playfair Display', serif; font-size: 1.5rem; margin-bottom: 1rem; color: #D4AF37;"><?= t('menu.gallery_construction') ?></h3>
                        <p style="font-size: 1.1rem;"><?= t('menu.images_coming_soon') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Lightbox Modal -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
        <div class="lightbox-content">
            <div class="lightbox-close" onclick="closeLightbox()">&times;</div>
            <div class="lightbox-counter" id="lightboxCounter"></div>
            
            <div class="lightbox-nav lightbox-prev" onclick="previousImage(event)">
                <i class="fas fa-chevron-left"></i>
            </div>
            
            <img id="lightboxImage" class="lightbox-image" src="" alt="">
            
            <div class="lightbox-nav lightbox-next" onclick="nextImage(event)">
                <i class="fas fa-chevron-right"></i>
            </div>
            
            <div class="lightbox-info">
                <div class="lightbox-dish-name" id="lightboxDishName"></div>
                <div class="lightbox-dish-description" id="lightboxDishDescription"></div>
                <div class="lightbox-dish-price" id="lightboxDishPrice"></div>
            </div>
        </div>
    </div>
<?php include('includes/footer.php'); ?>
    <script>
        // Données des plats pour la galerie
        const galleryData = [
            <?php
            if (!empty($galleryImages)) {
                $jsonData = [];
                foreach ($galleryImages as $plat) {
                    $jsonData[] = json_encode([
                        'image' => 'uploads/' . $plat['image'],
                        'nom' => $plat['nom'],
                        'description' => $plat['description'] ?? '',
                        'prix' => number_format($plat['prix'], 0, ',', ' ') . ' FCFA'
                    ]);
                }
                echo implode(',', $jsonData);
            }
            ?>
        ];

        let currentImageIndex = 0;

        function openLightbox(index) {
            currentImageIndex = index;
            const lightbox = document.getElementById('lightbox');
            updateLightboxContent();
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox(event) {
            if (event && event.target !== event.currentTarget && event.type !== 'click') return;
            
            const lightbox = document.getElementById('lightbox');
            lightbox.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function nextImage(event) {
            event.stopPropagation();
            currentImageIndex = (currentImageIndex + 1) % galleryData.length;
            updateLightboxContent();
        }

        function previousImage(event) {
            event.stopPropagation();
            currentImageIndex = (currentImageIndex - 1 + galleryData.length) % galleryData.length;
            updateLightboxContent();
        }

        // Dans votre fonction updateLightboxContent(), remplacez cette partie :

function updateLightboxContent() {
    if (galleryData.length === 0) return;
    
    const data = galleryData[currentImageIndex];
    document.getElementById('lightboxImage').src = data.image;
    document.getElementById('lightboxImage').alt = data.nom;
    document.getElementById('lightboxDishName').textContent = data.nom;
    // Masquer la description et le prix
    document.getElementById('lightboxDishDescription').textContent = '';
    document.getElementById('lightboxDishPrice').textContent = '';
    document.getElementById('lightboxCounter').textContent = `${currentImageIndex + 1} / ${galleryData.length}`;
}
        // Navigation clavier
        document.addEventListener('keydown', function(e) {
            const lightbox = document.getElementById('lightbox');
            if (lightbox.classList.contains('active')) {
                switch(e.key) {
                    case 'Escape':
                        closeLightbox();
                        break;
                    case 'ArrowLeft':
                        previousImage(e);
                        break;
                    case 'ArrowRight':
                        nextImage(e);
                        break;
                }
            }
        });

        function scrollToMenu() {
            document.getElementById('menu').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const categories = document.querySelectorAll('.nav-category');
            const contents = document.querySelectorAll('.category-content');
            const progressBar = document.getElementById('progressBar');
            const progressDots = document.querySelectorAll('.progress-dot');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            let currentCategory = 0;
            const totalCategories = categories.length;

            function updateProgress() {
                const percentage = totalCategories > 1 ? (currentCategory / (totalCategories - 1)) * 100 : 0;
                progressBar.style.width = percentage + '%';
                
                progressDots.forEach((dot, index) => {
                    dot.classList.toggle('active', index === currentCategory);
                });
            }

            function showCategory(index) {
                // Masquer toutes les catégories
                categories.forEach(cat => cat.classList.remove('active'));
                contents.forEach(content => content.classList.remove('active'));
                
                // Afficher la catégorie sélectionnée
                if (categories[index] && contents[index]) {
                    categories[index].classList.add('active');
                    contents[index].classList.add('active');
                    currentCategory = index;
                    updateProgress();
                    
                    // Animer les items du menu
                    const menuItems = contents[index].querySelectorAll('.menu-item');
                    menuItems.forEach((item, i) => {
                        item.style.opacity = '0';
                        item.style.transform = 'translateY(30px)';
                        setTimeout(() => {
                            item.style.transition = 'all 0.6s ease-out';
                            item.style.opacity = '1';
                            item.style.transform = 'translateY(0)';
                        }, i * 100);
                    });
                }
            }

            // Navigation par clic sur les catégories
            categories.forEach((category, index) => {
                category.addEventListener('click', () => {
                    showCategory(index);
                });
            });

            // Navigation par flèches
            prevBtn.addEventListener('click', () => {
                const newIndex = currentCategory > 0 ? currentCategory - 1 : totalCategories - 1;
                showCategory(newIndex);
            });

            nextBtn.addEventListener('click', () => {
                const newIndex = currentCategory < totalCategories - 1 ? currentCategory + 1 : 0;
                showCategory(newIndex);
            });

            // Navigation par clavier
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') {
                    prevBtn.click();
                } else if (e.key === 'ArrowRight') {
                    nextBtn.click();
                }
            });

            // Initialiser la première catégorie
            updateProgress();
        });
    </script>
</body>
</html>