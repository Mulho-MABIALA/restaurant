<?php
session_start();
include('lang.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Galerie - Restaurant Mulho</title>
    <meta name="description" content="Découvrez notre galerie photos - Restaurant Mulho">
    
    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #ec4899, #f97316);
            --secondary-gradient: linear-gradient(135deg, #3b82f6, #8b5cf6);
            --dark-bg: #0f172a;
            --light-bg: #f8fafc;
            --glass-bg: rgba(255, 255, 255, 0.95);
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: var(--light-bg);
            overflow-x: hidden;
        }

        /* ===== HEADER ===== */
        .header-glass {
            backdrop-filter: blur(20px);
            background: var(--glass-bg) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: fixed !important;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .header-glass.scrolled {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .nav-link-hover {
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
        }

        .nav-link-hover::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 50%;
            background: var(--primary-gradient);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(-50%);
            border-radius: 1px;
        }

        .nav-link-hover:hover,
        .nav-link-hover.active {
            background: rgba(236, 72, 153, 0.05);
            color: #ec4899 !important;
        }

        .nav-link-hover:hover::after,
        .nav-link-hover.active::after {
            width: 80%;
        }

        .logo-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-family: 'Playfair Display', serif;
        }

        .mobile-menu {
            transform: translateX(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.98);
        }

        .mobile-menu.open {
            transform: translateX(0);
        }

        .dropdown-menu {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.98) !important;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
        }

        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        /* ===== MAIN CONTENT ===== */
        main.main {
            margin-top: 80px;
            min-height: calc(100vh - 80px);
        }

        /* ===== HERO SECTION ===== */
        .hero-gallery {
            background: linear-gradient(135deg, var(--dark-bg) 0%, #1e293b 50%, #334155 100%);
            color: white;
            padding: 140px 0 100px;
            position: relative;
            overflow: hidden;
            transform: translateY(0);
            transition: transform 0.5s ease-out;
        }

        .hero-gallery::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/img/slider2.jpg') ;
            opacity: 0.3;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 4.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #ffffff, #f1f5f9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .hero-subtitle {
            font-size: 1.35rem;
            color: #cbd5e1;
            margin-bottom: 2rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            font-weight: 300;
            letter-spacing: 0.5px;
        }

        .breadcrumb-custom {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .breadcrumb-custom a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.3s ease;
            font-size: 1.05rem;
        }

        .breadcrumb-custom a:hover {
            color: #ec4899;
        }

        .breadcrumb-custom .current {
            color: #ec4899;
            font-weight: 500;
        }

        /* ===== GALLERY SECTION ===== */
        .gallery-section {
            padding: 100px 0;
            background: linear-gradient(180deg, var(--light-bg) 0%, #ffffff 100%);
            position: relative;
        }

        .gallery-section::before {
            content: '';
            position: absolute;
            top: -60px;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(to bottom, rgba(15, 23, 42, 0.1), transparent);
        }

        .gallery-filters {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 4rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.85rem 2.25rem;
            border: 2px solid var(--border-color);
            background: white;
            color: var(--text-light);
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            font-size: 1.05rem;
            letter-spacing: 0.5px;
        }

        .filter-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--primary-gradient);
            transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
        }

        .filter-btn span {
            position: relative;
            z-index: 2;
        }

        .filter-btn:hover::before,
        .filter-btn.active::before {
            left: 0;
        }

        .filter-btn:hover,
        .filter-btn.active {
            color: white;
            border-color: #ec4899;
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        /* ===== GALLERY GRID ===== */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2.5rem;
            margin-bottom: 4rem;
        }

        .gallery-item {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            transform: translateY(20px);
            opacity: 0;
        }

        .gallery-item.visible {
            transform: translateY(0);
            opacity: 1;
        }

        .gallery-item:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-lg);
        }

        .gallery-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.8), rgba(249, 115, 22, 0.8));
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 2;
        }

        .gallery-item:hover::before {
            opacity: 1;
        }

        .gallery-item img {
            width: 100%;
            height: 320px;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .gallery-item:hover img {
            transform: scale(1.12);
        }

        .gallery-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 3;
            text-align: center;
            color: white;
            width: 90%;
        }

        .gallery-item:hover .gallery-overlay {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        .zoom-icon {
            width: 65px;
            height: 65px;
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.35);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            backdrop-filter: blur(10px);
            transition: all 0.4s ease;
        }

        .gallery-item:hover .zoom-icon {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.6);
            transform: scale(1.15);
        }

        .gallery-title {
            font-weight: 600;
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        .gallery-category {
            font-size: 0.95rem;
            opacity: 0.9;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            letter-spacing: 0.5px;
        }

        /* ===== STATS SECTION ===== */
        .stats-section {
            background: var(--dark-bg);
            color: white;
            padding: 100px 0;
            position: relative;
            overflow: hidden;
        }

        .stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(236, 72, 153, 0.1) 0%, transparent 20%),
                radial-gradient(circle at 80% 70%, rgba(249, 115, 22, 0.1) 0%, transparent 20%);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 4rem;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .stat-item {
            padding: 2.5rem;
            background: rgba(15, 23, 42, 0.4);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.4s ease;
        }

        .stat-item:hover {
            transform: translateY(-8px);
            background: rgba(15, 23, 42, 0.5);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.75rem;
            font-family: 'Playfair Display', serif;
            letter-spacing: 1px;
        }

        .stat-label {
            font-size: 1.15rem;
            color: #cbd5e1;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        /* ===== SCROLL TO TOP ===== */
        .scroll-top {
            position: fixed;
            bottom: 40px;
            right: 40px;
            background: var(--primary-gradient);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            text-decoration: none;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 999;
            display: none;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s ease;
        }

        .scroll-top.visible {
            opacity: 1;
            transform: translateY(0);
            display: flex;
        }

        .scroll-top:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 20px 40px rgba(236, 72, 153, 0.4);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .hero-title {
                font-size: 3.5rem;
            }
            
            .gallery-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 2rem;
            }
        }

        @media (max-width: 768px) {
            main.main {
                margin-top: 70px;
            }
            
            .hero-title {
                font-size: 2.8rem;
            }
            
            .hero-gallery {
                padding: 100px 0 70px;
            }
            
            .gallery-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 1.75rem;
            }
            
            .gallery-filters {
                gap: 0.75rem;
            }
            
            .filter-btn {
                padding: 0.7rem 1.8rem;
                font-size: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 2.5rem;
            }
            
            .stat-number {
                font-size: 3rem;
            }
        }

        @media (max-width: 576px) {
            .hero-title {
                font-size: 2.3rem;
            }
            
            .hero-subtitle {
                font-size: 1.15rem;
            }
            
            .gallery-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .filter-btn {
                width: 100%;
                max-width: 250px;
            }
        }

        /* ===== ANIMATIONS ===== */
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

        .fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }
        .delay-6 { animation-delay: 0.6s; }
        .delay-7 { animation-delay: 0.7s; }
        .delay-8 { animation-delay: 0.8s; }
        .delay-9 { animation-delay: 0.9s; }
    </style>
</head>
<body>
    <header id="header" class="header-glass">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Logo -->
                <div class="flex-shrink-0">
                    <a href="index.php" class="flex items-center space-x-2 group">
                        <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-orange-500 rounded-xl flex items-center justify-center shadow-lg group-hover:shadow-xl transition-all duration-300 group-hover:scale-105">
                            <span class="text-white font-bold text-xl">M</span>
                        </div>
                        <h1 class="text-2xl font-bold logo-gradient">Mulho</h1>
                    </a>
                </div>
                
                <!-- Navigation Desktop -->
                <nav class="hidden lg:flex items-center space-x-2">
                    <a href="index.php" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium transition-colors duration-300">
                        <?= $traduction['home'] ?? 'Accueil' ?>
                    </a>
                    <a href="index.php#about" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium transition-colors duration-300">
                        <?= $traduction['about'] ?? 'À propos' ?>
                    </a>
                    <a href="index.php#menu" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium transition-colors duration-300">
                        <?= $traduction['menu'] ?? 'Menu' ?>
                    </a>
                    <a href="galerie.php" class="nav-link-hover active text-gray-700 hover:text-pink-600 font-medium transition-colors duration-300">
                        <?= $traduction['gallery'] ?? 'Galerie' ?>
                    </a>
                    <a href="index.php#contact" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium transition-colors duration-300">
                        Contact
                    </a>

                    <!-- Language Dropdown -->
                    <div class="relative dropdown ml-4">
                        <button class="flex items-center space-x-2 text-gray-700 hover:text-pink-600 font-medium py-2 px-3 rounded-lg hover:bg-gray-100 transition-all duration-300">
                            <span class="text-lg">🌐</span>
                            <span>Langues</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-2xl border border-gray-100 py-2">
                            <a href="?lang=fr" onclick="changeLanguage('fr')" class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-50 transition-colors duration-200">
                                <span class="text-lg">🇫🇷</span>
                                <span class="text-gray-700 font-medium">Français</span>
                            </a>
                            <a href="?lang=en" onclick="changeLanguage('en')" class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-50 transition-colors duration-200">
                                <span class="text-lg">🇬🇧</span>
                                <span class="text-gray-700 font-medium">English</span>
                            </a>
                            <a href="?lang=wo" onclick="changeLanguage('wo')" class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-50 transition-colors duration-200">
                                <span class="text-lg">🇸🇳</span>
                                <span class="text-gray-700 font-medium">Wolof</span>
                            </a>
                        </div>
                    </div>
                </nav>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-toggle" class="lg:hidden text-gray-700 hover:text-pink-600 transition-colors duration-300">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="mobile-menu lg:hidden fixed inset-y-0 right-0 w-80 shadow-2xl z-50">
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold logo-gradient">Navigation</h2>
                    <button id="mobile-menu-close" class="text-gray-500 hover:text-gray-700 transition-colors duration-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <nav class="flex-1 px-6 py-8 space-y-4">
                    <a href="index.php" class="block text-gray-700 hover:text-pink-600 font-medium py-3 border-b border-gray-100 transition-colors duration-300">
                        <?= $traduction['home'] ?? 'Accueil' ?>
                    </a>
                    <a href="index.php#about" class="block text-gray-700 hover:text-pink-600 font-medium py-3 border-b border-gray-100 transition-colors duration-300">
                        <?= $traduction['about'] ?? 'À propos' ?>
                    </a>
                    <a href="index.php#menu" class="block text-gray-700 hover:text-pink-600 font-medium py-3 border-b border-gray-100 transition-colors duration-300">
                        <?= $traduction['menu'] ?? 'Menu' ?>
                    </a>
                    <a href="galerie.php" class="block text-gray-700 hover:text-pink-600 font-medium py-3 border-b border-gray-100 transition-colors duration-300">
                        <?= $traduction['gallery'] ?? 'Galerie' ?>
                    </a>
                    <a href="index.php#contact" class="block text-gray-700 hover:text-pink-600 font-medium py-3 border-b border-gray-100 transition-colors duration-300">
                        Contact
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main class="main">
        <!-- Hero Section -->
        <section class="hero-gallery">
            <div class="container">
                <div class="hero-content">
                    <h1 class="hero-title fade-in-up">Notre Galerie</h1>
                    <p class="hero-subtitle fade-in-up">
                        Plongez dans l'univers visuel de Mulho et découvrez nos créations culinaires,
                        notre ambiance chaleureuse et nos moments de convivialité.
                    </p>
                    <div class="breadcrumb-custom fade-in-up">
                        <a href="index.php">Accueil</a>
                        <i class="fas fa-chevron-right" style="color: #64748b;"></i>
                        <span class="current">Galerie</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Gallery Section -->
        <section class="gallery-section">
            <div class="container">
                <!-- Filters -->
                <div class="gallery-filters fade-in-up">
                    <button class="filter-btn active" data-filter="all">
                        <span>Tout voir</span>
                    </button>
                    <button class="filter-btn" data-filter="plats">
                        <span>Nos Plats</span>
                    </button>
                    <button class="filter-btn" data-filter="ambiance">
                        <span>Ambiance</span>
                    </button>
                    <button class="filter-btn" data-filter="evenements">
                        <span>Événements</span>
                    </button>
                </div>

                <!-- Gallery Grid -->
                <div class="gallery-grid">
                    <?php 
                    $gallery_items = [
                        ['img' => 'assets/img/gallery/gallery-1.jpg', 'title' => 'Thieboudienne Royal', 'category' => 'plats'],
                        ['img' => 'assets/img/gallery/gallery-2.jpg', 'title' => 'Ambiance Chaleureuse', 'category' => 'ambiance'],
                        ['img' => 'assets/img/gallery/gallery-3.jpg', 'title' => 'Yassa Poulet', 'category' => 'plats'],
                        ['img' => 'assets/img/gallery/gallery-4.jpg', 'title' => 'Soirée Spéciale', 'category' => 'evenements'],
                        ['img' => 'assets/img/gallery/gallery-5.jpg', 'title' => 'Mafé Traditionnel', 'category' => 'plats'],
                        ['img' => 'assets/img/gallery/gallery-6.jpg', 'title' => 'Terrasse Restaurant', 'category' => 'ambiance'],
                        ['img' => 'assets/img/gallery/gallery-7.jpg', 'title' => 'Pastels Maison', 'category' => 'plats'],
                        ['img' => 'assets/img/gallery/gallery-8.jpg', 'title' => 'Célébration', 'category' => 'evenements'],
                        ['img' => 'assets/img/gallery/gallery-9.jpg', 'title' => 'Notre Équipe', 'category' => 'ambiance']
                    ];
                    
                    foreach($gallery_items as $index => $item): ?>
                    <div class="gallery-item delay-<?= $index+1 ?>" data-category="<?= $item['category'] ?>">
                        <a href="<?= $item['img'] ?>" data-lightbox="gallery" data-title="<?= $item['title'] ?>">
                            <img src="<?= $item['img'] ?>" alt="<?= $item['title'] ?>">
                            <div class="gallery-overlay">
                                <div class="zoom-icon">
                                    <i class="fas fa-search-plus fa-lg"></i>
                                </div>
                                <h3 class="gallery-title"><?= $item['title'] ?></h3>
                                <p class="gallery-category"><?= ucfirst($item['category']) ?></p>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats-section">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-item fade-in-up">
                        <div class="stat-number">150+</div>
                        <div class="stat-label">Photos de qualité</div>
                    </div>
                    <div class="stat-item fade-in-up delay-2">
                        <div class="stat-number">50+</div>
                        <div class="stat-label">Plats différents</div>
                    </div>
                    <div class="stat-item fade-in-up delay-3">
                        <div class="stat-number">25+</div>
                        <div class="stat-label">Événements capturés</div>
                    </div>
                    <div class="stat-item fade-in-up delay-4">
                        <div class="stat-number">1000+</div>
                        <div class="stat-label">Moments partagés</div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include('footer.php'); ?>

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top">
        <i class="bi bi-arrow-up-short" style="font-size: 1.8rem;"></i>
    </a>

    <!-- Scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Header scroll effect
        const header = document.getElementById('header');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        const mobileMenuClose = document.getElementById('mobile-menu-close');

        function openMobileMenu() {
            mobileMenu.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenu() {
            mobileMenu.classList.remove('open');
            document.body.style.overflow = '';
        }

        mobileMenuToggle?.addEventListener('click', openMobileMenu);
        mobileMenuClose?.addEventListener('click', closeMobileMenu);

        // Gallery filters
        const filterBtns = document.querySelectorAll('.filter-btn');
        const galleryItems = document.querySelectorAll('.gallery-item');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all buttons
                filterBtns.forEach(b => b.classList.remove('active'));
                // Add active class to clicked button
                btn.classList.add('active');

                const filter = btn.getAttribute('data-filter');

                galleryItems.forEach(item => {
                    const category = item.getAttribute('data-category');
                    
                    if (filter === 'all' || category === filter) {
                        item.style.display = 'block';
                        setTimeout(() => {
                            item.classList.add('visible');
                        }, 50);
                    } else {
                        item.classList.remove('visible');
                        setTimeout(() => {
                            item.style.display = 'none';
                        }, 300);
                    }
                });
            });
        });

        // Changement de langue
        function changeLanguage(lang) {
            window.location.search = `?lang=${lang}`;
        }
        window.changeLanguage = changeLanguage;

        // Scroll to top
        const scrollTop = document.getElementById('scroll-top');
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 500) {
                scrollTop.classList.add('visible');
            } else {
                scrollTop.classList.remove('visible');
            }
        });

        scrollTop?.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Parallax effect for hero section
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero-gallery');
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.3}px)`;
            }
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        // Observe gallery items
        galleryItems.forEach(item => {
            observer.observe(item);
        });

        // Observe stats
        const statItems = document.querySelectorAll('.stat-item');
        statItems.forEach(item => {
            observer.observe(item);
        });

        // Counter animation for stats
        const animateCounters = () => {
            const counters = document.querySelectorAll('.stat-number');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent);
                const increment = target / 100;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target + '+';
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current) + '+';
                    }
                }, 20);
            });
        };

        // Trigger counter animation when stats section is visible
        const statsSection = document.querySelector('.stats-section');
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        if (statsSection) {
            statsObserver.observe(statsSection);
        }

        // Initialize GLightbox
        const lightbox = GLightbox({
            selector: '[data-lightbox]',
            touchNavigation: true,
            loop: true,
            autoplayVideos: true,
            moreText: 'Voir plus',
            moreLength: 60
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
                closeMobileMenu();
            });
        });

        // Add hover effects to filter buttons
        filterBtns.forEach(btn => {
            btn.addEventListener('mouseenter', () => {
                if (!btn.classList.contains('active')) {
                    btn.style.transform = 'translateY(-3px)';
                }
            });

            btn.addEventListener('mouseleave', () => {
                if (!btn.classList.contains('active')) {
                    btn.style.transform = 'translateY(0)';
                }
            });
        });
    });
    </script>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
</body>
</html>