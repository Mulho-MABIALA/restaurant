<?php
session_start();
include('lang.php');
require_once 'config.php';

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Requête pour récupérer les horaires d'ouverture/fermeture par jour
    $query = "
        SELECT jour, heure_ouverture, heure_fermeture, ferme
        FROM horaires
        ORDER BY FIELD(jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche')
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur SQL horaires : " . $e->getMessage());
    $results = []; // Valeur de repli
}

// Requête pour récupérer les plats disponibles (triés du plus récent au plus ancien)
try {
    $stmt = $conn->query("SELECT * FROM plats ORDER BY created_at DESC");
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur SQL produits : " . $e->getMessage());
    $produits = [];
}

// Compter les articles dans le panier
$nb_articles_panier = 0;
if (!empty($_SESSION['panier']) && is_array($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $id => $details) {
        if (isset($details['quantite']) && is_numeric($details['quantite'])) {
            $nb_articles_panier += (int)$details['quantite'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Restaurant Mulho</title>
    <meta name="description" content="Restaurant Mulho - Découvrez nos plats de qualité">
    <meta name="keywords" content="restaurant, mulho, dakar, senegal">
    
    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Inter:wght@100;200;300;400;500;600;700;800;900&family=Amatic+SC:wght@400;700&display=swap" rel="stylesheet">

    <!-- CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .header-glass {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: fixed !important;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .nav-link-hover {
            position: relative;
            transition: all 0.3s ease;
        }

        .nav-link-hover::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -4px;
            left: 50%;
            background: linear-gradient(90deg, #ec4899, #f97316);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .nav-link-hover:hover::after,
        .nav-link-hover.active::after {
            width: 100%;
        }

        .logo-gradient {
            background: linear-gradient(135deg, #ec4899, #f97316, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .mobile-menu {
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
        }

        .mobile-menu.open {
            transform: translateX(0);
        }

        .dropdown-menu {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .cart-badge {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* CORRECTION PRINCIPALE : Ajouter du margin-top pour éviter que le contenu soit masqué par le header fixe */
        main.main {
            margin-top: 80px; /* Hauteur du header */
        }

        /* Section Hero */
        .hero {
            min-height: 80vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 2rem;
        }

        .btn-get-started {
            background: linear-gradient(135deg, #ec4899, #f97316);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-get-started:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(236, 72, 153, 0.3);
            color: white;
        }

        /* Sections */
        .section {
            padding: 80px 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 1rem;
        }

        .light-background {
            background-color: #f8fafc;
        }

        .dark-background {
            background-color: #1a202c;
            color: white;
        }

        /* Corrections pour que le contenu soit visible */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }

        .col-lg-5, .col-lg-7, .col-lg-4, .col-lg-8, .col-lg-3, .col-md-6 {
            padding: 0 15px;
            flex: 1;
        }

        .col-lg-5 { flex: 0 0 41.666667%; }
        .col-lg-7 { flex: 0 0 58.333333%; }
        .col-lg-4 { flex: 0 0 33.333333%; }
        .col-lg-8 { flex: 0 0 66.666667%; }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .col-lg-5, .col-lg-7, .col-lg-4, .col-lg-8 {
                flex: 0 0 100%;
                margin-bottom: 2rem;
            }
            
            main.main {
                margin-top: 70px;
            }
        }
        #preloader {
            display: none !important;
        }
    </style>
</head>
<body class="index-page">
    <header id="header" class="header-glass">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Logo -->
                <div class="flex-shrink-0">
                    <a href="index.php" class="flex items-center space-x-2 group">
                        <div class="w-10 h-10 bg-gradient-to-br from-pink-500 to-orange-500 rounded-xl flex items-center justify-center shadow-lg group-hover:shadow-xl transition-all duration-300 group-hover:scale-105">
                            <span class="text-white font-bold text-lg">M</span>
                        </div>
                        <h1 class="text-2xl font-bold logo-gradient">Mulho</h1>
                    </a>
                </div>
                
                <!-- Navigation Desktop -->
                <nav class="hidden lg:flex items-center space-x-8">
                    <a href="#hero" class="nav-link-hover active text-gray-700 hover:text-pink-600 font-medium py-2 transition-colors duration-300">
                        <?= $traduction['home'] ?? 'Accueil' ?>
                    </a>
                    <a href="#about" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium py-2 transition-colors duration-300">
                        <?= $traduction['about'] ?? 'À propos' ?>
                    </a>
                    <a href="#menu" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium py-2 transition-colors duration-300">
                        <?= $traduction['menu'] ?? 'Menu' ?>
                    </a>
                    <a href="#events" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium py-2 transition-colors duration-300">
                        <?= $traduction['events'] ?? 'Événements' ?>
                    </a>
                    <a href="galerie.php" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium py-2 transition-colors duration-300">
                        <?= $traduction['gallery'] ?? 'Galerie' ?>
                    </a>
                    <a href="#contact" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium py-2 transition-colors duration-300">
                        Contact
                    </a>

                    <!-- Language Dropdown -->
                    <div class="relative dropdown">
                        <button class="flex items-center space-x-2 text-gray-700 hover:text-pink-600 font-medium py-2 px-3 rounded-lg hover:bg-gray-100 transition-all duration-300">
                            <span class="text-lg">🌐</span>
                            <span>Langues</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-2xl border border-gray-100 py-2">
                            <a href="?lang=fr" onclick="changeLanguage('fr')"
                               class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-50 transition-colors duration-200">
                                <span class="text-lg">🇫🇷</span>
                                <span class="text-gray-700 font-medium">Français</span>
                            </a>
                            <a href="?lang=en" onclick="changeLanguage('en')"
                               class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-50 transition-colors duration-200">
                                <span class="text-lg">🇬🇧</span>
                                <span class="text-gray-700 font-medium">English</span>
                            </a>
                            <a href="?lang=wo" onclick="changeLanguage('wo')"
                               class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-50 transition-colors duration-200">
                                <span class="text-lg">🇸🇳</span>
                                <span class="text-gray-700 font-medium">Wolof</span>
                            </a>
                        </div>
                    </div>
                </nav>
                
                <!-- Actions Desktop -->
                <div class="hidden lg:flex items-center space-x-4">
                    <!-- Panier -->
                    <a href="panier.php" class="relative flex items-center space-x-2 text-gray-700 hover:text-pink-600 transition-colors duration-300">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Panier</span>
                        <span id="cart-count" class="cart-badge absolute -top-1 -right-1 bg-gradient-to-r from-pink-500 to-orange-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold shadow-lg">
                            <?php echo $nb_articles_panier ?? 0; ?>
                        </span>
                    </a>
                    
                    <!-- Bouton Réserver -->
                    <a href="#book-a-table" class="bg-gradient-to-r from-pink-500 to-orange-500 text-white px-6 py-2.5 rounded-full font-semibold hover:from-pink-600 hover:to-orange-600 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-105">
                        Réserver une table
                    </a>
                </div>
 
                <!-- Mobile Menu Button -->
                <div class="lg:hidden flex items-center space-x-4">
                    <!-- Panier Mobile -->
                    <a href="panier.php" class="text-gray-700 hover:text-pink-600 transition-colors duration-300 relative">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <?php if($nb_articles_panier > 0): ?>
                        <span class="cart-badge absolute -top-2 -right-2 bg-gradient-to-r from-pink-500 to-orange-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">
                            <?php echo $nb_articles_panier; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <button id="mobile-menu-toggle" class="text-gray-700 hover:text-pink-600 focus:outline-none transition-colors duration-300">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="mobile-menu lg:hidden fixed inset-y-0 right-0 w-80 bg-white shadow-2xl z-50">
            <div class="flex flex-col h-full">
                <!-- Mobile Header -->
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold logo-gradient">Navigation</h2>
                    <button id="mobile-menu-close" class="text-gray-500 hover:text-gray-700 transition-colors duration-300">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <!-- Mobile Navigation -->
                <nav class="flex-1 px-6 py-8 space-y-4">
                    <a href="#hero" class="block text-gray-700 hover:text-pink-600 font-medium py-3 border-b border-gray-100 transition-colors duration-300">
                        <?= $traduction['home'] ?? 'Accueil' ?>
                    </a>
                    <a href="#about" class="block text-gray-700 hover:text-pink-600 font-medium py-3 border-b border-gray-100 transition-colors duration-300">
                        <?= $traduction['about'] ?? 'À propos' ?>
                    </a>
                    <a href="#menu" class="block text-gray-700 hover:text-pink-600 font-medium py-3 border-b border-gray-100 transition-colors duration-300">
                        <?= $traduction['menu'] ?? 'Menu' ?>
                    </a>
                    <a href="#events" class="block text-gray-700 hover:text-pink-600 font-medium py-3 border-b border-gray-100 transition-colors duration-300">
                        <?= $traduction['events'] ?? 'Événements' ?>
                    </a>
                    <a href="#gallery" class="block text-gray-700 hover:text-pink-600 font-medium py-3 border-b border-gray-100 transition-colors duration-300">
                        <?= $traduction['gallery'] ?? 'Galerie' ?>
                    </a>
                    <a href="#contact" class="block text-gray-700 hover:text-pink-600 font-medium py-3 border-b border-gray-100 transition-colors duration-300">
                        Contact
                    </a>
                </nav>
                <!-- Mobile CTA -->
                <div class="p-6 border-t border-gray-200">
                    <a href="#book-a-table" class="block w-full bg-gradient-to-r from-pink-500 to-orange-500 text-white text-center px-6 py-3 rounded-full font-semibold hover:from-pink-600 hover:to-orange-600 transition-all duration-300 shadow-lg">
                        Réserver une table
                    </a>
                </div>
            </div>
        </div>
    </header>
    <main class="main">
        <!-- Section Hero -->
        <section id="hero" class="hero section light-background">
            <div class="container">
                <div class="row gy-4 justify-content-center justify-content-lg-between">
                    <div class="col-lg-5 order-2 order-lg-1 d-flex flex-column justify-content-center">
                        <h1 data-aos="fade-up">Profitez de vos meilleurs plats de qualité<br></h1>
                        <p data-aos="fade-up" data-aos-delay="100" class="mb-4">
                            Découvrez une expérience culinaire exceptionnelle dans notre restaurant Mulho. 
                            Des saveurs authentiques du Sénégal aux créations modernes.
                        </p>
                        <div class="d-flex" data-aos="fade-up" data-aos-delay="200">
                            <a href="#book-a-table" class="btn-get-started">Réserver une table</a>
                        </div>
                    </div>
                    <div class="col-lg-5 order-1 order-lg-2 hero-img" data-aos="zoom-out">
                        <img src="assets/img/hero-img.png" class="img-fluid animated" alt="Restaurant Mulho" style="max-width: 100%; height: auto;">
                    </div>
                </div>
            </div>
        </section>
        <!-- Section À propos -->
        <section id="about" class="about section">
            <div class="container section-title" data-aos="fade-up">
                <h2>À propos de nous</h2>
                <p><span>En savoir plus</span> <span class="description-title">À propos de nous</span></p>
            </div>
            <div class="container">
                <div class="row gy-4">
                    <div class="col-lg-7" data-aos="fade-up" data-aos-delay="100">
                        <img src="assets/img/about.jpg" class="img-fluid mb-4" alt="À propos du restaurant" style="width: 100%; height: 400px; object-fit: cover; border-radius: 10px;">
                        <div class="book-a-table" style="background: linear-gradient(135deg, #ec4899, #f97316); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                            <h3>Réserver une table</h3>
                            <a href="tel:787308706" style="color: white; font-size: 1.5rem; font-weight: bold;">78 730 87 06</a>
                        </div>
                    </div>
                    <div class="col-lg-5" data-aos="fade-up" data-aos-delay="250">
                        <div class="content ps-0 ps-lg-5">
                            <p class="fst-italic">
                                Bienvenue au Restaurant Mulho, où la tradition culinaire sénégalaise rencontre l'innovation moderne. 
                                Situé au cœur de Dakar, nous vous proposons une expérience gastronomique unique.
                            </p>
                            <ul style="list-style: none; padding: 0;">
                                <li style="margin: 15px 0; display: flex; align-items: flex-start;">
                                    <i class="bi bi-check-circle-fill" style="color: #ec4899; margin-right: 10px; margin-top: 5px;"></i>
                                    <span>Ingrédients frais et locaux sélectionnés avec soin pour chaque plat</span>
                                </li>
                                <li style="margin: 15px 0; display: flex; align-items: flex-start;">
                                    <i class="bi bi-check-circle-fill" style="color: #ec4899; margin-right: 10px; margin-top: 5px;"></i>
                                    <span>Cuisine authentique préparée par nos chefs expérimentés</span>
                                </li>
                                <li style="margin: 15px 0; display: flex; align-items: flex-start;">
                                    <i class="bi bi-check-circle-fill" style="color: #ec4899; margin-right: 10px; margin-top: 5px;"></i>
                                    <span>Ambiance chaleureuse et service attentionné pour tous nos clients</span>
                                </li>
                            </ul>
                            <p>
                                Notre passion pour la gastronomie nous pousse à créer des plats qui racontent une histoire, 
                                mélangeant les saveurs traditionnelles du Sénégal avec des techniques culinaires modernes.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Section Menu -->
        <?php include('menu.php'); ?>
        <!-- Section Réserver une table -->
        <section id="book-a-table" class="book-a-table section">
            <div class="container section-title" data-aos="fade-up">
                <h2>Réserver une table</h2>
                <p><span>Réservez votre</span> <span class="description-title">Table</span></p>
            </div>
            <div class="container">
                <div class="row g-0" data-aos="fade-up" data-aos-delay="100">
                    <div class="col-lg-4 reservation-img" style="background-image: url(assets/img/reservation.jpg); background-size: cover; background-position: center; min-height: 400px;"></div>
                    <div class="col-lg-8 d-flex align-items-center" style="background: #f8fafc; padding: 60px 40px;" data-aos="fade-up" data-aos-delay="200">
                        <form action="forms/book-a-table.php" method="post" role="form" class="php-email-form" style="width: 100%;">
                            <div class="row gy-4">
                                <div class="col-lg-4 col-md-6">
                                    <input type="text" name="name" class="form-control" id="name" placeholder="Votre nom" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <input type="email" class="form-control" name="email" id="email" placeholder="Votre email" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <input type="text" class="form-control" name="phone" id="phone" placeholder="Votre téléphone" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <input type="date" name="date" class="form-control" id="date" placeholder="Date" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <input type="time" class="form-control" name="time" id="time" placeholder="Heure" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <input type="number" class="form-control" name="people" id="people" placeholder="Nombre de personnes" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                                </div>
                            </div>
                            <div class="form-group mt-3">
                                <textarea class="form-control" name="message" rows="5" placeholder="Message" style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px; width: 100%;"></textarea>
                            </div>
                            <div class="text-center mt-3">
                                <div class="loading" style="display: none;">Chargement</div>
                                <div class="error-message" style="display: none; color: #e53e3e;"></div>
                                <div class="sent-message" style="display: none; color: #38a169;">Votre demande de réservation a été envoyée. Nous vous rappellerons ou enverrons un email pour confirmer votre réservation. Merci !</div>
                                <button type="submit" style="background: linear-gradient(135deg, #ec4899, #f97316); color: white; border: none; padding: 15px 40px; border-radius: 50px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">Réserver une table</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        <!-- Section Contact -->
        <section id="contact" class="contact section">
            <div class="container section-title" data-aos="fade-up">
                <h2>Contact</h2>
                <p><span>Besoin d'aide ?</span> <span class="description-title">Contactez-nous</span></p>
            </div>
            <div class="container" data-aos="fade-up" data-aos-delay="100">
                <div class="mb-5">
                    <iframe style="width: 100%; height: 400px; border-radius: 15px;" 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3858.9689555935147!2d-17.44270312595434!3d14.693425085886857!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xec10d6c8b7e6c13%3A0x20e6e5b6b7e6c13!2sMedina%2C%20Dakar%2C%20Senegal!5e0!3m2!1sen!2sus!4v1641234567890!5m2!1sen!2sus" 
                            frameborder="0" allowfullscreen=""></iframe>
                </div>
                
                <div class="row gy-4">
                    <div class="col-md-6">
                        <div class="info-item d-flex align-items-center" data-aos="fade-up" data-aos-delay="200" style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px;">
                            <i class="icon bi bi-geo-alt" style="color: #ec4899; font-size: 2rem; margin-right: 20px;"></i>
                            <div>
                                <h3 style="color: #1a202c; font-weight: 600; margin-bottom: 5px;">Adresse</h3>
                                <p style="color: #666; margin: 0;">Dakar, Medina rue 27x24</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-item d-flex align-items-center" data-aos="fade-up" data-aos-delay="300" style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px;">
                            <i class="icon bi bi-telephone" style="color: #ec4899; font-size: 2rem; margin-right: 20px;"></i>
                            <div>
                                <h3 style="color: #1a202c; font-weight: 600; margin-bottom: 5px;">Appelez-nous</h3>
                                <p style="margin: 0;"><a href="tel:787308706" style="color: #666; text-decoration: none;">78 730 87 06</a></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-item d-flex align-items-center" data-aos="fade-up" data-aos-delay="400" style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px;">
                            <i class="icon bi bi-envelope" style="color: #ec4899; font-size: 2rem; margin-right: 20px;"></i>
                            <div>
                                <h3 style="color: #1a202c; font-weight: 600; margin-bottom: 5px;">Envoyez-nous un email</h3>
                                <p style="margin: 0;"><a href="mailto:mulhomabiala29@gmail.com" style="color: #666; text-decoration: none;">mulhomabiala29@gmail.com</a></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="info-item d-flex align-items-center" data-aos="fade-up" data-aos-delay="500" style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px;">
                            <i class="icon bi bi-clock" style="color: #ec4899; font-size: 2rem; margin-right: 20px;"></i>
                            <div>
                                <h3 style="color: #1a202c; font-weight: 600; margin-bottom: 10px;">Heures d'ouverture</h3>
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <?php if (!empty($results)): ?>
                                        <?php foreach ($results as $row): ?>
                                            <li style="margin: 5px 0; color: #666;">
                                                <strong><?= htmlspecialchars($row['jour']) ?> :</strong>
                                                <?php if ($row['ferme'] == 1): ?>
                                                    <span style="color: #e53e3e;">Fermé</span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars(substr($row['heure_ouverture'], 0, 5)) ?> -
                                                    <?= htmlspecialchars(substr($row['heure_fermeture'], 0, 5)) ?>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li style="color: #666;">Aucun horaire trouvé.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulaire de contact -->
                <form action="forms/contact.php" method="post" class="php-email-form" data-aos="fade-up" data-aos-delay="600" style="background: white; padding: 40px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-top: 40px;">
                    <div class="row gy-4">
                        <div class="col-md-6">
                            <input type="text" name="name" class="form-control" placeholder="Votre nom" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                        </div>
                        <div class="col-md-6">
                            <input type="email" class="form-control" name="email" placeholder="Votre email" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                        </div>
                        <div class="col-md-12">
                            <input type="text" class="form-control" name="subject" placeholder="Sujet" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                        </div>
                        <div class="col-md-12">
                            <textarea class="form-control" name="message" rows="6" placeholder="Message" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px; resize: vertical;"></textarea>
                        </div>
                        <div class="col-md-12 text-center">
                            <div class="loading" style="display: none;">Chargement</div>
                            <div class="error-message" style="display: none; color: #e53e3e;"></div>
                            <div class="sent-message" style="display: none; color: #38a169;">Votre message a été envoyé. Merci !</div>
                            <button type="submit" style="background: linear-gradient(135deg, #ec4899, #f97316); color: white; border: none; padding: 15px 40px; border-radius: 50px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">Envoyer le message</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </main>

   
 <?php include('footer.php'); ?>
    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center" style="position: fixed; bottom: 30px; right: 30px; background: linear-gradient(135deg, #ec4899, #f97316); color: white; width: 50px; height: 50px; border-radius: 50%; text-decoration: none; box-shadow: 0 5px 15px rgba(236, 72, 153, 0.3); transition: all 0.3s ease; z-index: 999; display: none;">
        <i class="bi bi-arrow-up-short" style="font-size: 1.5rem;"></i>
    </a>

    <!-- Scripts -->
    <script src="cart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // === 🔁 Mise à jour du panier ===
        function updateCartCount() {
            const cartCount = document.getElementById('cart-count');
            if (!cartCount) return;
            try {
                const cart = JSON.parse(localStorage.getItem('cart')) || [];
                const count = cart.reduce((sum, item) => sum + (item.quantity || 0), 0);
                cartCount.textContent = count;
            } catch (e) {
                console.error("Erreur panier :", e);
                cartCount.textContent = "0";
            }
        }
        updateCartCount();

        // === 🌐 Changement de langue ===
        function changeLanguage(lang) {
            console.log('Langue sélectionnée:', lang);
            window.location.search = `?lang=${lang}`;
        }
        window.changeLanguage = changeLanguage;

        // === 📱 Mobile menu ===
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        const mobileMenuClose = document.getElementById('mobile-menu-close');
        const mobileMenu = document.getElementById('mobile-menu');

        function openMobileMenu() {
            mobileMenu?.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenu() {
            mobileMenu?.classList.remove('open');
            document.body.style.overflow = 'auto';
        }

        mobileMenuToggle?.addEventListener('click', openMobileMenu);
        mobileMenuClose?.addEventListener('click', closeMobileMenu);

        // === 🔗 Scroll fluide vers les ancres ===
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                closeMobileMenu();
            });
        });

        // === 🔼 Bouton scroll to top ===
        const scrollTop = document.getElementById('scroll-top');
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollTop.style.display = 'flex';
            } else {
                scrollTop.style.display = 'none';
            }
        });

        scrollTop?.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // === 🍽️ Fonction d'ajout au panier ===
        window.ajouterAuPanier = function(productId) {
            // Cette fonction devrait être définie dans cart.js
            console.log('Ajout au panier:', productId);
            // Simuler l'ajout
            updateCartCount();
        };

        // === 📅 Animation AOS (si disponible) ===
        if (typeof AOS !== 'undefined') {
            AOS.init({
                duration: 1000,
                easing: 'ease-in-out',
                once: true,
                mirror: false
            });
        }
    });
    </script>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>
</body>
</html>