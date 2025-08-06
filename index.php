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
     <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <
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


        @keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(180deg); }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.1); opacity: 0.4; }
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Effet hover pour les cartes d'événements */
.event-card:hover {
    transform: translateY(-15px) scale(1.02);
    box-shadow: 0 30px 80px rgba(0,0,0,0.15) !important;
}

.event-card:hover .event-img {
    transform: scale(1.1);
}

.event-card .floating-date {
    animation: float 3s ease-in-out infinite;
}

/* Effets pour les boutons */
.btn-event-premium:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 15px 40px rgba(236, 72, 153, 0.4) !important;
}

.btn-event-popular:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 15px 40px rgba(59, 130, 246, 0.4) !important;
}

.btn-event-new:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 15px 40px rgba(16, 185, 129, 0.4) !important;
}

/* Animation pour les icônes des détails */
.detail-item:hover div {
    transform: scale(1.1);
    transition: transform 0.3s ease;
}

/* Effets pour les éléments de la newsletter */
.newsletter-signup input:focus {
    outline: none;
    border-color: rgba(236, 72, 153, 0.5) !important;
    background: rgba(255,255,255,0.15) !important;
    transform: scale(1.02);
}

.newsletter-signup input::placeholder {
    color: rgba(255,255,255,0.7);
}

.newsletter-signup button:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 15px 40px rgba(236, 72, 153, 0.5) !important;
}

/* Animations d'entrée pour les stats */
.stat-item {
    animation: fadeInUp 0.6s ease-out forwards;
    opacity: 0;
    transform: translateY(20px);
}

.stat-item:nth-child(1) { animation-delay: 0.1s; }
.stat-item:nth-child(2) { animation-delay: 0.2s; }
.stat-item:nth-child(3) { animation-delay: 0.3s; }

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Effet glassmorphism pour les badges */
.event-card div[style*="Premium"],
.event-card div[style*="Populaire"],
.event-card div[style*="Nouveau"] {
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    animation: shimmer 2s ease-in-out infinite;
}

@keyframes shimmer {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

/* Responsive Design */
@media (max-width: 768px) {
    .section-title h2 {
        font-size: 2.5rem !important;
    }
    
    .event-content {
        padding: 25px 20px 20px !important;
    }
    
    .newsletter-premium {
        padding: 50px 30px !important;
    }
    
    .newsletter-premium h3 {
        font-size: 2rem !important;
    }
     
    .newsletter-signup {
        flex-direction: column !important;
    }
    
    .newsletter-signup input {
        min-width: 100% !important;
    }
    
    .stats {
        gap: 20px !important;
    }
    
    .decorative-elements div {
        display: none;
    }
}

@media (max-width: 576px) {
    .event-img-container {
        height: 220px !important;
    }
    
    .section-title h2 {
        font-size: 2rem !important;
    }
    
    .event-content h3 {
        font-size: 1.2rem !important;
    }
    
    .newsletter-premium h3 {
        font-size: 1.8rem !important;
    }
}

/* Effet de particules flottantes */
.events::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(2px 2px at 20px 30px, rgba(236, 72, 153, 0.3), transparent),
        radial-gradient(2px 2px at 40px 70px, rgba(249, 115, 22, 0.3), transparent),
        radial-gradient(1px 1px at 90px 40px, rgba(59, 130, 246, 0.3), transparent),
        radial-gradient(1px 1px at 130px 80px, rgba(16, 185, 129, 0.3), transparent);
    background-repeat: repeat;
    background-size: 200px 200px;
    animation: sparkle 20s linear infinite;
    pointer-events: none;
    z-index: 1;
}

@keyframes sparkle {
    from { transform: translateY(0px); }
    to { transform: translateY(-200px); }
}

/* Amélioration des transitions */
* {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Effet de lueur pour les éléments interactifs */
.event-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, transparent, rgba(255,255,255,0.1), transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
    z-index: 1;
}

.event-card:hover::before {
    opacity: 1;
}

/* Style pour les tooltips (si besoin futur) */
.tooltip {
    position: relative;
    display: inline-block;
}

.tooltip .tooltiptext {
    visibility: hidden;
    width: 200px;
    background: linear-gradient(135deg, #1a202c, #2d3748);
    color: white;
    text-align: center;
    border-radius: 10px;
    padding: 10px;
    position: absolute;
    z-index: 1000;
    bottom: 125%;
    left: 50%;
    margin-left: -100px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 0.9rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.tooltip:hover .tooltiptext {
    visibility: visible;
    opacity: 1;
}

/* Animation pour les éléments qui apparaissent */
.fade-in-up {
    opacity: 0;
    transform: translateY(30px);
    animation: fadeInUp 0.8s ease-out forwards;
}

/* Délais d'animation échelonnés */
.col-lg-4:nth-child(1) .event-card { animation-delay: 0.1s; }
.col-lg-4:nth-child(2) .event-card { animation-delay: 0.2s; }
.col-lg-4:nth-child(3) .event-card { animation-delay: 0.3s; }


 * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
        }

        .hero-carousel {
            position: relative;
            width: 100%;
            height: 100vh;
            min-height: 600px;
            overflow: hidden;
            background: #0a0a0a;
        }

        .carousel-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            transition: transform 1s cubic-bezier(0.23, 1, 0.32, 1);
        }

        .carousel-slide {
            min-width: 100%;
            height: 100%;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        .slide-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                135deg,
                rgba(0, 0, 0, 0.7) 0%,
                rgba(0, 0, 0, 0.4) 30%,
                rgba(0, 0, 0, 0.6) 70%,
                rgba(0, 0, 0, 0.8) 100%
            );
            backdrop-filter: blur(1px);
        }

        .slide-content {
            position: relative;
            z-index: 3;
            text-align: center;
            color: white;
            max-width: 900px;
            padding: 0 30px;
            animation: slideInUp 1.2s cubic-bezier(0.23, 1, 0.32, 1);
        }

        .slide-pretitle {
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #d4af37;
            margin-bottom: 1rem;
            opacity: 0.9;
            animation: fadeInDown 1s ease-out 0.3s both;
        }

        .slide-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #ffffff 0%, #f8f8f8 50%, #e8e8e8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 1s ease-out 0.5s both;
        }

        .slide-subtitle {
            font-family: 'Inter', sans-serif;
            font-size: clamp(1.1rem, 2.5vw, 1.4rem);
            font-weight: 400;
            line-height: 1.7;
            margin-bottom: 2.5rem;
            color: rgba(255, 255, 255, 0.9);
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 1s ease-out 0.7s both;
        }

        .slide-cta-wrapper {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease-out 0.9s both;
        }

        .slide-cta {
            display: inline-flex;
            align-items: center;
            padding: 16px 32px;
            background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            color: #1a1a1a;
            text-decoration: none;
            border-radius: 50px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }

        .slide-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(212, 175, 55, 0.4);
            background: linear-gradient(135deg, #f4d03f 0%, #d4af37 100%);
        }

        .slide-cta-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .slide-cta-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 15px 40px rgba(255, 255, 255, 0.1);
        }

        /* Navigation améliorée */
        .carousel-nav {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 12px;
            z-index: 4;
            background: rgba(0, 0, 0, 0.3);
            padding: 12px 20px;
            border-radius: 30px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            position: relative;
        }

        .nav-dot.active {
            background: #d4af37;
            transform: scale(1.4);
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.6);
        }

        .nav-dot:hover:not(.active) {
            background: rgba(255, 255, 255, 0.7);
            transform: scale(1.2);
        }

        /* Flèches redessinées */
        .carousel-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 1.5rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            z-index: 4;
            backdrop-filter: blur(20px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }

        .carousel-arrow:hover {
            background: rgba(212, 175, 55, 0.9);
            border-color: #d4af37;
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
        }

        .carousel-arrow.prev {
            left: 40px;
        }

        .carousel-arrow.next {
            right: 40px;
        }

        /* Indicateur de progression */
        .progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            background: #d4af37;
            transition: width 5s linear;
            z-index: 4;
        }

        /* Effets de particules améliorés */
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 2;
            overflow: hidden;
        }

        .floating-element {
            position: absolute;
            width: 6px;
            height: 6px;
            background: rgba(212, 175, 55, 0.6);
            border-radius: 50%;
            animation: float 8s infinite ease-in-out;
        }

        .floating-element:nth-child(even) {
            background: rgba(255, 255, 255, 0.4);
            animation-duration: 12s;
        }

        .floating-element.diamond {
            width: 8px;
            height: 8px;
            background: transparent;
            border: 1px solid rgba(212, 175, 55, 0.5);
            border-radius: 0;
            transform: rotate(45deg);
        }

        /* Animations raffinées */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(80px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 0.9;
                transform: translateY(0);
            }
        }

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

        @keyframes float {
            0%, 100% { 
                transform: translateY(0px) translateX(0px) rotate(0deg); 
                opacity: 0; 
            }
            25% { 
                transform: translateY(-80px) translateX(20px) rotate(90deg); 
                opacity: 0.8; 
            }
            50% { 
                transform: translateY(-160px) translateX(-10px) rotate(180deg); 
                opacity: 1; 
            }
            75% { 
                transform: translateY(-240px) translateX(30px) rotate(270deg); 
                opacity: 0.6; 
            }
        }

        /* Responsive Design Ultra-Optimisé */
        @media (max-width: 1200px) {
            .carousel-arrow {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
            
            .carousel-arrow.prev {
                left: 30px;
            }
            
            .carousel-arrow.next {
                right: 30px;
            }
        }

        @media (max-width: 768px) {
            .hero-carousel {
                min-height: 550px;
            }
            
            .slide-content {
                padding: 0 20px;
            }
            
            .slide-pretitle {
                font-size: 0.8rem;
                letter-spacing: 2px;
            }
            
            .slide-cta-wrapper {
                flex-direction: column;
                align-items: center;
            }
            
            .slide-cta {
                padding: 14px 28px;
                font-size: 0.9rem;
                width: 100%;
                max-width: 280px;
                justify-content: center;
            }
            
            .carousel-arrow {
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
            
            .carousel-arrow.prev {
                left: 20px;
            }
            
            .carousel-arrow.next {
                right: 20px;
            }
            
            .carousel-nav {
                bottom: 30px;
                padding: 10px 16px;
            }
            
            .carousel-slide {
                background-attachment: scroll;
            }
        }

        @media (max-width: 480px) {
            .hero-carousel {
                min-height: 500px;
            }
            
            .slide-content {
                padding: 0 15px;
            }
            
            .slide-subtitle {
                margin-bottom: 2rem;
            }
            
            .carousel-arrow {
                width: 40px;
                height: 40px;
                font-size: 0.9rem;
            }
            
            .carousel-arrow.prev {
                left: 15px;
            }
            
            .carousel-arrow.next {
                right: 15px;
            }
            
            .carousel-nav {
                bottom: 25px;
                gap: 8px;
            }
            
            .nav-dot {
                width: 8px;
                height: 8px;
            }
        }

        /* Animations d'entrée pour les éléments */
        .slide-content > * {
            opacity: 0;
            animation-fill-mode: both;
        }

        /* Mode sombre pour les appareils qui le supportent */
        @media (prefers-color-scheme: dark) {
            .slide-overlay {
                background: linear-gradient(
                    135deg,
                    rgba(0, 0, 0, 0.8) 0%,
                    rgba(0, 0, 0, 0.5) 30%,
                    rgba(0, 0, 0, 0.7) 70%,
                    rgba(0, 0, 0, 0.9) 100%
                );
            }
        }

        /* Performance optimizations */
        .carousel-slide {
            will-change: transform;
        }
        
        .carousel-container {
            will-change: transform;
        }


         :root {
            --primary: #ff6b35;
            --secondary: #f7931e;
            --accent: #ffd23f;
            --dark: #2d1810;
            --light: #faf8f5;
            --glass: rgba(255, 255, 255, 0.1);
            --shadow: rgba(0, 0, 0, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: white;
            overflow-x: hidden;
        }

        /* Particles Background */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-radius: 50%;
            animation: float-particle 20s infinite linear;
            opacity: 0.6;
        }

        @keyframes float-particle {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.6;
            }
            90% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Hero Section */
        .hero-section {
            min-height: 5vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="grad1" cx="50%" cy="50%" r="50%"><stop offset="0%" style="stop-color:%23ff6b35;stop-opacity:0.1" /><stop offset="100%" style="stop-color:%23ff6b35;stop-opacity:0" /></radialGradient></defs><circle cx="200" cy="200" r="150" fill="url(%23grad1)" /><circle cx="800" cy="800" r="200" fill="url(%23grad1)" /></svg>');
            opacity: 0.3;
            animation: pulse-bg 8s ease-in-out infinite;
        }

        @keyframes pulse-bg {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(180deg); }
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.5rem, 8vw, 6rem);
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 50%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            z-index: 2;
        }

        .hero-subtitle {
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 3rem;
            opacity: 0.8;
            font-weight: 300;
        }

        /* Glass Cards */
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 2rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.8s;
        }

        .glass-card:hover::before {
            left: 100%;
        }

        .glass-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(255, 107, 53, 0.2);
            border-color: rgba(255, 107, 53, 0.3);
        }

        /* Image Container with 3D Effect */
        .image-3d {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            transform-style: preserve-3d;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .image-3d:hover {
            transform: rotateY(5deg) rotateX(5deg) translateZ(50px);
        }

        .image-3d img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            transition: all 0.6s ease;
        }

        .image-3d:hover img {
            transform: scale(1.1);
            filter: brightness(1.1) contrast(1.1);
        }

        /* Floating Elements */
        .floating-element {
            position: absolute;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(odd) {
            animation-delay: -3s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        /* Stats with Neon Effect */
        .neon-stat {
            text-align: center;
            padding: 1.5rem;
            background: rgba(255, 107, 53, 0.1);
            border: 2px solid rgba(255, 107, 53, 0.3);
            border-radius: 16px;
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
        }

        .neon-stat:hover {
            box-shadow: 0 0 30px rgba(255, 107, 53, 0.4);
            border-color: var(--primary);
        }

        .neon-stat::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--primary), var(--secondary), var(--accent));
            border-radius: 16px;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .neon-stat:hover::before {
            opacity: 1;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 0.5rem;
        }

        /* Modern Features List */
        .feature-modern {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            border-left: 4px solid var(--primary);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .feature-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: width 0.4s ease;
            opacity: 0.1;
        }

        .feature-modern:hover::before {
            width: 100%;
        }

        .feature-modern:hover {
            transform: translateX(10px);
            background: rgba(255, 107, 53, 0.08);
            border-left-color: var(--accent);
        }

        .feature-icon-modern {
            font-size: 2rem;
            margin-right: 1.5rem;
            color: var(--primary);
            transition: all 0.4s ease;
        }

        .feature-modern:hover .feature-icon-modern {
            transform: scale(1.2) rotate(10deg);
            color: var(--accent);
        }

        /* CTA Button with Glow */
        .cta-glow {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.3);
        }

        .cta-glow::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s;
        }

        .cta-glow:hover::before {
            left: 100%;
        }

        .cta-glow:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 35px rgba(255, 107, 53, 0.5);
            color: white;
        }

        /* Morphing Shapes */
        .morph-shape {
            position: absolute;
            width: 200px;
            height: 200px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            opacity: 0.1;
            animation: morph 8s ease-in-out infinite;
        }

        @keyframes morph {
            0%, 100% {
                border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
                transform: rotate(0deg) scale(1);
            }
            25% {
                border-radius: 58% 42% 75% 25% / 76% 46% 54% 24%;
                transform: rotate(90deg) scale(1.1);
            }
            50% {
                border-radius: 50% 50% 33% 67% / 55% 27% 73% 45%;
                transform: rotate(180deg) scale(0.9);
            }
            75% {
                border-radius: 33% 67% 58% 42% / 63% 68% 32% 37%;
                transform: rotate(270deg) scale(1.05);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 2rem 0;
            }
            
            .glass-card {
                padding: 1.5rem;
                margin: 1rem 0;
            }
            
            .image-3d img {
                height: 300px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }

        /* Scroll Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

         @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        * {
            font-family: 'Inter', sans-serif;
        }

        .contact-section {
            padding: 60px 0;
            background: #f8fafc;
            position: relative;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
            color: #2d3748;
            font-weight: 800;
            font-size: 3rem;
            letter-spacing: -1px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .info-card {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 35px;
            border-radius: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 25px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.8s ease;
        }

        .info-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(255, 107, 107, 0.15);
            border-color: #ff6b6b;
        }

        .info-icon {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24, #feca57);
            color: white;
            width: 80px;
            height: 80px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
            box-shadow: 0 15px 35px rgba(255, 107, 107, 0.4);
            position: relative;
        }

        .info-icon::after {
            content: '';
            position: absolute;
            inset: -3px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24, #feca57);
            border-radius: 28px;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.4s ease;
            filter: blur(8px);
        }

        .info-card:hover .info-icon::after {
            opacity: 0.6;
        }

        .info-content h3 {
            color: #2d3748;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 1.3rem;
            letter-spacing: -0.5px;
        }

        .info-content p,
        .info-content a {
            color: #4a5568;
            margin: 0;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .info-content a:hover {
            color: #ff6b6b;
            transform: translateX(3px);
        }

        .opening-hours {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .opening-hours li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            font-size: 0.95rem;
        }

        .opening-hours li:last-child {
            border-bottom: none;
        }

        .day-name {
            font-weight: 700;
            color: #2d3748;
            letter-spacing: -0.3px;
        }

        .hours {
            color: #4a5568;
            font-weight: 500;
        }

        .closed {
            color: #e53e3e;
            font-weight: 700;
        }

        .contact-form {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 50px;
            border-radius: 25px;
            margin-top: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
        }

        .contact-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #ff6b6b, #ee5a24, #feca57, #5f27cd, #00d2d3);
            background-size: 300% 100%;
            animation: gradient 3s ease infinite;
        }

        @keyframes gradient {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .form-title {
            text-align: center;
            margin-bottom: 40px;
            color: #2d3748;
            font-weight: 800;
            font-size: 2.5rem;
            letter-spacing: -1px;
            position: relative;
        }

        .form-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, #ff6b6b, #feca57);
            border-radius: 2px;
        }

        .form-group {
            position: relative;
            margin-bottom: 25px;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 20px;
            font-size: 1rem;
            font-weight: 500;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #ff6b6b;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
            background: white;
            outline: none;
        }

        .form-control::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 140px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24, #feca57);
            background-size: 200% 200%;
            color: white;
            border: none;
            padding: 18px 50px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 15px 35px rgba(255, 107, 107, 0.4);
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }

        .submit-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 20px 40px rgba(255, 107, 107, 0.6);
            background-position: right center;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:active {
            transform: translateY(-1px) scale(1.01);
        }

        .message-status {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
        }

        .loading { 
            color: #ff6b6b; 
            background: rgba(255, 107, 107, 0.1);
            border: 2px solid rgba(255, 107, 107, 0.2);
        }
        .error-message { 
            color: #e53e3e; 
            background: rgba(229, 62, 62, 0.1);
            border: 2px solid rgba(229, 62, 62, 0.2);
        }
        .sent-message { 
            color: #38a169; 
            background: rgba(56, 161, 105, 0.1);
            border: 2px solid rgba(56, 161, 105, 0.2);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .contact-section {
                padding: 40px 0;
            }

            .section-title {
                font-size: 2rem;
                margin-bottom: 40px;
            }

            .info-card {
                flex-direction: column;
                text-align: center;
                padding: 30px;
                gap: 20px;
            }

            .info-icon {
                width: 70px;
                height: 70px;
                font-size: 1.8rem;
            }

            .contact-form {
                padding: 35px 25px;
                margin-top: 30px;
            }

            .form-title {
                font-size: 2rem;
                margin-bottom: 30px;
            }

            .opening-hours li {
                flex-direction: column;
                gap: 5px;
                text-align: center;
                padding: 12px 0;
            }
        }

        @media (max-width: 480px) {
            .info-card {
                padding: 25px 20px;
            }

            .contact-form {
                padding: 30px 20px;
            }

            .form-control {
                padding: 15px 16px;
            }

            .submit-btn {
                padding: 16px 40px;
                font-size: 1rem;
            }
        }

        /* Animations d'entrée */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .info-card {
            animation: fadeInUp 0.8s ease forwards;
        }

        .contact-form {
            animation: fadeInUp 1s ease forwards;
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
    <a href="#hero" class="nav-link-hover active text-gray-700 hover:text-pink-600 font-medium py-2 transition-colors duration-300 flex items-center space-x-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
        </svg>
        <span><?= $traduction['home'] ?? 'Accueil' ?></span>
    </a>
    <a href="#about" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium py-2 transition-colors duration-300 flex items-center space-x-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span><?= $traduction['about'] ?? 'À propos' ?></span>
    </a>
    <a href="menu.php" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium py-2 transition-colors duration-300 flex items-center space-x-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m0 0h2a2 2 0 002-2V7a2 2 0 00-2-2H9m0 0V3a2 2 0 012-2h2a2 2 0 012 2v2M7 13h10l-4-8H7l4 8z"></path>
        </svg>
        <span><?= $traduction['menu'] ?? 'Menu' ?></span>
    </a>
    <a href="#events" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium py-2 transition-colors duration-300 flex items-center space-x-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
        <span><?= $traduction['events'] ?? 'Événements' ?></span>
    </a>
    <a href="galerie.php" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium py-2 transition-colors duration-300 flex items-center space-x-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
        <span><?= $traduction['gallery'] ?? 'Galerie' ?></span>
    </a>
    <a href="#contact" class="nav-link-hover text-gray-700 hover:text-pink-600 font-medium py-2 transition-colors duration-300 flex items-center space-x-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
        </svg>
        <span>Contact</span>
    </a>
</nav>
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
                    <!-- Bouton Réserver -->
                    <a href="#book-a-table" class="bg-gradient-to-r from-pink-500 to-orange-500 text-white px-6 py-2.5 rounded-full font-semibold hover:from-pink-600 hover:to-orange-600 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-105">
                        Réserver une table
                    </a>
                </div>
 
                <!-- Mobile Menu Button -->
                
                
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
   <section id="hero" class="hero-carousel">
        <div class="progress-bar" id="progressBar"></div>
        
        <div class="carousel-container" id="carouselContainer">
            <!-- Slide 1 - Remplacez 'assets/img/slide1.jpg' par le chemin de votre image -->
            <div class="carousel-slide" style="background-image: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.4)), url('assets/img/slider1.jpg');">
                <div class="slide-overlay"></div>
                <div class="floating-elements">
                    <div class="floating-element" style="left: 5%; top: 20%; animation-delay: 0s;"></div>
                    <div class="floating-element" style="left: 15%; top: 60%; animation-delay: 2s;"></div>
                    <div class="floating-element diamond" style="left: 25%; top: 30%; animation-delay: 4s;"></div>
                    <div class="floating-element" style="left: 35%; top: 70%; animation-delay: 1s;"></div>
                    <div class="floating-element" style="left: 45%; top: 10%; animation-delay: 3s;"></div>
                    <div class="floating-element diamond" style="left: 55%; top: 50%; animation-delay: 5s;"></div>
                    <div class="floating-element" style="left: 65%; top: 80%; animation-delay: 1.5s;"></div>
                    <div class="floating-element" style="left: 75%; top: 25%; animation-delay: 3.5s;"></div>
                    <div class="floating-element diamond" style="left: 85%; top: 65%; animation-delay: 0.5s;"></div>
                    <div class="floating-element" style="left: 95%; top: 40%; animation-delay: 2.5s;"></div>
                </div>
                <div class="slide-content">
                    <div class="slide-pretitle">Restaurant Mulho</div>
                    <h1 class="slide-title">Saveurs Authentiques du Sénégal</h1>
                    <p class="slide-subtitle">Découvrez une expérience culinaire exceptionnelle où tradition et modernité se rencontrent. Nos chefs passionnés préparent chaque plat avec des ingrédients frais et authentiques.</p>
                    <div class="slide-cta-wrapper">
                        <a href="#menu" class="slide-cta">Découvrir le Menu</a>
                        <a href="#about" class="slide-cta slide-cta-secondary">Notre Histoire</a>
                    </div>
                </div>
            </div>

            <!-- Slide 2 - Remplacez 'assets/img/slide2.jpg' par le chemin de votre image -->
            <div class="carousel-slide" style="background-image: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.4)), url('assets/img/slider2.jpg');">
                <div class="slide-overlay"></div>
                <div class="floating-elements">
                    <div class="floating-element" style="left: 10%; top: 15%; animation-delay: 0.5s;"></div>
                    <div class="floating-element diamond" style="left: 20%; top: 55%; animation-delay: 2.5s;"></div>
                    <div class="floating-element" style="left: 30%; top: 35%; animation-delay: 4.5s;"></div>
                    <div class="floating-element" style="left: 40%; top: 75%; animation-delay: 1.5s;"></div>
                    <div class="floating-element diamond" style="left: 50%; top: 15%; animation-delay: 3.5s;"></div>
                    <div class="floating-element" style="left: 60%; top: 55%; animation-delay: 5.5s;"></div>
                    <div class="floating-element" style="left: 70%; top: 85%; animation-delay: 2s;"></div>
                    <div class="floating-element diamond" style="left: 80%; top: 30%; animation-delay: 4s;"></div>
                    <div class="floating-element" style="left: 90%; top: 70%; animation-delay: 1s;"></div>
                </div>
                <div class="slide-content">
                    <div class="slide-pretitle">Ambiance Unique</div>
                    <h1 class="slide-title">Un Cadre Chaleureux & Authentique</h1>
                    <p class="slide-subtitle">Plongez dans une atmosphère conviviale qui célèbre la richesse culturelle du Sénégal. Parfait pour vos repas en famille, entre amis ou vos occasions spéciales.</p>
                    <div class="slide-cta-wrapper">
                        <a href="#book-a-table" class="slide-cta">Réserver une Table</a>
                        <a href="galerie.php" class="slide-cta slide-cta-secondary">Voir la Galerie</a>
                    </div>
                </div>
            </div>

            <!-- Slide 3 - Remplacez 'assets/img/slide3.jpg' par le chemin de votre image -->
            <div class="carousel-slide" style="background-image: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.4)), url('assets/img/slider3.jpg');">
                <div class="slide-overlay"></div>
                <div class="floating-elements">
                    <div class="floating-element diamond" style="left: 8%; top: 25%; animation-delay: 1s;"></div>
                    <div class="floating-element" style="left: 18%; top: 65%; animation-delay: 3s;"></div>
                    <div class="floating-element" style="left: 28%; top: 45%; animation-delay: 5s;"></div>
                    <div class="floating-element diamond" style="left: 38%; top: 85%; animation-delay: 2s;"></div>
                    <div class="floating-element" style="left: 48%; top: 25%; animation-delay: 4s;"></div>
                    <div class="floating-element" style="left: 58%; top: 65%; animation-delay: 6s;"></div>
                    <div class="floating-element diamond" style="left: 68%; top: 95%; animation-delay: 2.5s;"></div>
                    <div class="floating-element" style="left: 78%; top: 35%; animation-delay: 4.5s;"></div>
                    <div class="floating-element" style="left: 88%; top: 75%; animation-delay: 1.5s;"></div>
                </div>
                <div class="slide-content">
                    <div class="slide-pretitle">Événements Privés</div>
                    <h1 class="slide-title">Célébrez Vos Moments Précieux</h1>
                    <p class="slide-subtitle">Organisez vos célébrations, événements d'entreprise et réceptions dans un cadre exceptionnel. Notre équipe personnalise chaque détail pour créer des souvenirs inoubliables.</p>
                    <div class="slide-cta-wrapper">
                        <a href="#events" class="slide-cta">Organiser un Événement</a>
                        <a href="#contact" class="slide-cta slide-cta-secondary">Nous Contacter</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation améliorée -->
        <div class="carousel-nav" id="carouselNav">
            <div class="nav-dot active" data-slide="0"></div>
            <div class="nav-dot" data-slide="1"></div>
            <div class="nav-dot" data-slide="2"></div>
        </div>

        <!-- Flèches -->
        <button class="carousel-arrow prev" id="prevBtn">‹</button>
        <button class="carousel-arrow next" id="nextBtn">›</button>
    </section>

      <!-- Particles Background -->
    <div class="particles"></div>

    <!-- Morphing Shapes -->
    <div class="morph-shape" style="top: 10%; right: 10%;"></div>
    <div class="morph-shape" style="bottom: 20%; left: 15%; animation-delay: -4s;"></div>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-bg"></div>
        <div class="container">
            <div class="text-center">
                <h1 class="hero-title fade-in">À propos de Mulho</h1>
                <p class="hero-subtitle fade-in">Où l'authenticité sénégalaise rencontre l'excellence culinaire</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-5">
        <div class="container">
            <div class="row g-5 align-items-center">
                <!-- Image Side -->
                <div class="col-lg-6">
                    <div class="glass-card fade-in">
                        <div class="image-3d">
                            <img src="assets/img/apropos.jpg" alt="Restaurant Mulho">
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="row g-3 mt-4 fade-in">
                        <div class="col-4">
                            <div class="neon-stat">
                                <div class="stat-number" data-count="15">0</div>
                                <div class="stat-label">Années</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="neon-stat">
                                <div class="stat-number" data-count="50">0</div>
                                <div class="stat-label">Plats</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="neon-stat">
                                <div class="stat-number" data-count="1000">0</div>
                                <div class="stat-label">Clients</div>
                            </div>
                        </div>
                    </div>

                    <!-- CTA -->
                    <div class="text-center mt-4 fade-in">
                        <a href="tel:787308706" class="cta-glow">
                            <i class="bi bi-telephone-fill"></i>
                            Réserver : 78 730 87 06
                        </a>
                    </div>
                </div>

                <!-- Content Side -->
                <div class="col-lg-6">
                    <div class="glass-card fade-in">
                        <h2 class="mb-4" style="font-family: 'Playfair Display', serif; font-size: 2.5rem; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            Notre Histoire
                        </h2>
                        
                        <p class="mb-4" style="font-size: 1.2rem; line-height: 1.8; opacity: 0.9;">
                            Bienvenue au Restaurant Mulho, où chaque plat raconte l'histoire passionnée de la gastronomie sénégalaise. 
                            Situé au cœur vibrant de Dakar, nous créons des expériences culinaires qui éveillent les sens et 
                            célèbrent l'authenticité de notre terroir.
                        </p>

                        <!-- Features -->
                        <div class="features-list">
                            <div class="feature-modern fade-in">
                                <i class="bi bi-gem feature-icon-modern"></i>
                                <div>
                                    <strong>Ingrédients Premium</strong><br>
                                    <span style="opacity: 0.8;">Sélection rigoureuse de produits locaux d'exception</span>
                                </div>
                            </div>

                            <div class="feature-modern fade-in">
                                <i class="bi bi-fire feature-icon-modern"></i>
                                <div>
                                    <strong>Cuisine Authentique</strong><br>
                                    <span style="opacity: 0.8;">Techniques traditionnelles sublimées par l'innovation</span>
                                </div>
                            </div>

                            <div class="feature-modern fade-in">
                                <i class="bi bi-hearts feature-icon-modern"></i>
                                <div>
                                    <strong>Expérience Unique</strong><br>
                                    <span style="opacity: 0.8;">Service personnalisé dans une atmosphère chaleureuse</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 p-4 fade-in" style="background: rgba(255, 107, 53, 0.1); border-radius: 16px; border-left: 4px solid var(--primary);">
                            <p style="margin: 0; font-style: italic; opacity: 0.9;">
                                "Notre passion transcende la simple restauration. Nous créons des moments magiques où chaque bouchée 
                                transporte nos invités dans un voyage sensoriel au cœur de l'âme sénégalaise."
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
        <!-- Section Menu -->
        <!-- Section Événements -->
<section id="events" class="events section" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #f1f5f9 100%); overflow: hidden; position: relative;">
    <!-- Éléments décoratifs d'arrière-plan -->
    <div class="decorative-elements" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1;">
        <div style="position: absolute; top: 10%; left: -5%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(236, 72, 153, 0.1) 0%, transparent 70%); border-radius: 50%; animation: float 6s ease-in-out infinite;"></div>
        <div style="position: absolute; bottom: 10%; right: -5%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(249, 115, 22, 0.08) 0%, transparent 70%); border-radius: 50%; animation: float 8s ease-in-out infinite reverse;"></div>
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 200px; height: 200px; background: radial-gradient(circle, rgba(59, 130, 246, 0.06) 0%, transparent 70%); border-radius: 50%; animation: pulse 4s ease-in-out infinite;"></div>
    </div>

    <div class="container" style="position: relative; z-index: 2;">
        <div class="section-title text-center mb-5" data-aos="fade-up">
            <div style="display: inline-block; padding: 8px 24px; background: linear-gradient(135deg, #ec4899, #f97316); border-radius: 30px; margin-bottom: 20px;">
                <span style="color: white; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">✨ Événements Spéciaux</span>
            </div>
            <h2 style="font-size: 3.5rem; font-weight: 800; background: linear-gradient(135deg, #1a202c, #4a5568); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 20px; line-height: 1.2;">
                <?= $traduction['events'] ?? 'Événements' ?>
            </h2>
            <p style="font-size: 1.2rem; color: #64748b; max-width: 600px; margin: 0 auto; line-height: 1.6;">
                Découvrez nos expériences culinaires uniques et nos soirées inoubliables
            </p>
        </div>
        
        <div class="row gy-4 mb-5">
            <!-- Événement 1 - Premium -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="event-card premium" style="background: white; border-radius: 25px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.08); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); height: 100%; position: relative; border: 1px solid rgba(255,255,255,0.3);">
                    <!-- Badge Premium -->
                    <div style="position: absolute; top: 20px; left: 20px; background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; padding: 6px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; z-index: 3; text-transform: uppercase; letter-spacing: 0.5px;">
                        🌟 Premium
                    </div>
                    
                    <div class="event-img-container" style="height: 280px; position: relative; overflow: hidden;">
                        <div class="event-img" style="height: 100%; background: linear-gradient(135deg, rgba(236, 72, 153, 0.85), rgba(249, 115, 22, 0.85)), url('assets/img/events/event-1.jpg'); background-size: cover; background-position: center; transition: transform 0.4s ease;"></div>
                        <div class="event-date floating-date" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); padding: 15px 18px; border-radius: 18px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.3);">
                            <div style="font-size: 1.8rem; font-weight: 800; color: #ec4899; line-height: 1;">15</div>
                            <div style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Mars</div>
                        </div>
                        <!-- Overlay gradient -->
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 50%; background: linear-gradient(transparent, rgba(0,0,0,0.3)); pointer-events: none;"></div>
                    </div>
                    
                    <div class="event-content" style="padding: 35px 30px 30px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                            <div style="width: 40px; height: 4px; background: linear-gradient(135deg, #ec4899, #f97316); border-radius: 2px;"></div>
                            <span style="color: #ec4899; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">Dégustation</span>
                        </div>
                        
                        <h3 style="color: #1a202c; font-weight: 700; margin-bottom: 15px; font-size: 1.4rem; line-height: 1.3;">Soirée Dégustation Exclusive</h3>
                        
                        <p style="color: #64748b; line-height: 1.7; margin-bottom: 25px; font-size: 0.95rem;">
                            Une expérience gastronomique d'exception avec menu 7 services, accords mets-vins sélectionnés par notre sommelier.
                        </p>
                        
                        <div class="event-details" style="margin-bottom: 25px;">
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 12px; padding: 8px 0;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #ec4899, #f97316); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="bi bi-clock" style="color: white; font-size: 1rem;"></i>
                                </div>
                                <span style="color: #4a5568; font-weight: 500;">19h00 - 23h00</span>
                            </div>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 12px; padding: 8px 0;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="bi bi-geo-alt" style="color: white; font-size: 1rem;"></i>
                                </div>
                                <span style="color: #4a5568; font-weight: 500;">Restaurant Mulho</span>
                            </div>
                            <div class="detail-item" style="display: flex; align-items: center; padding: 8px 0;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="bi bi-currency-dollar" style="color: white; font-size: 1rem;"></i>
                                </div>
                                <span style="color: #4a5568; font-weight: 600; font-size: 1.1rem;">25,000 FCFA</span>
                            </div>
                        </div>
                        
                        <a href="#book-a-table" class="btn-event-premium" style="background: linear-gradient(135deg, #ec4899, #f97316); color: white; padding: 15px 30px; border-radius: 30px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: 0 10px 30px rgba(236, 72, 153, 0.3); width: 100%; justify-content: center;">
                            <span>Réserver maintenant</span>
                            <i class="bi bi-arrow-right" style="font-size: 1rem;"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Événement 2 - Populaire -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="event-card popular" style="background: white; border-radius: 25px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.08); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); height: 100%; position: relative; border: 1px solid rgba(255,255,255,0.3);">
                    <!-- Badge Populaire -->
                    <div style="position: absolute; top: 20px; left: 20px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; padding: 6px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; z-index: 3; text-transform: uppercase; letter-spacing: 0.5px;">
                        🔥 Populaire
                    </div>
                    
                    <div class="event-img-container" style="height: 280px; position: relative; overflow: hidden;">
                        <div class="event-img" style="height: 100%; background: linear-gradient(135deg, rgba(59, 130, 246, 0.85), rgba(147, 51, 234, 0.85)), url('assets/img/events/event-2.jpg'); background-size: cover; background-position: center; transition: transform 0.4s ease;"></div>
                        <div class="event-date floating-date" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); padding: 15px 18px; border-radius: 18px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.3);">
                            <div style="font-size: 1.8rem; font-weight: 800; color: #3b82f6; line-height: 1;">22</div>
                            <div style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Mars</div>
                        </div>
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 50%; background: linear-gradient(transparent, rgba(0,0,0,0.3)); pointer-events: none;"></div>
                    </div>
                    
                    <div class="event-content" style="padding: 35px 30px 30px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                            <div style="width: 40px; height: 4px; background: linear-gradient(135deg, #3b82f6, #9333ea); border-radius: 2px;"></div>
                            <span style="color: #3b82f6; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">Musique</span>
                        </div>
                        
                        <h3 style="color: #1a202c; font-weight: 700; margin-bottom: 15px; font-size: 1.4rem; line-height: 1.3;">Soirée Musique Live</h3>
                        
                        <p style="color: #64748b; line-height: 1.7; margin-bottom: 25px; font-size: 0.95rem;">
                            Vibrez au rythme des meilleurs artistes locaux dans une ambiance intimiste et chaleureuse.
                        </p>
                        
                        <div class="event-details" style="margin-bottom: 25px;">
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 12px; padding: 8px 0;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #3b82f6, #9333ea); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="bi bi-clock" style="color: white; font-size: 1rem;"></i>
                                </div>
                                <span style="color: #4a5568; font-weight: 500;">20h00 - 00h00</span>
                            </div>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 12px; padding: 8px 0;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="bi bi-geo-alt" style="color: white; font-size: 1rem;"></i>
                                </div>
                                <span style="color: #4a5568; font-weight: 500;">Restaurant Mulho</span>
                            </div>
                            <div class="detail-item" style="display: flex; align-items: center; padding: 8px 0;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="bi bi-music-note" style="color: white; font-size: 1rem;"></i>
                                </div>
                                <span style="color: #10b981; font-weight: 600; font-size: 1.1rem;">Entrée libre</span>
                            </div>
                        </div>
                        
                        <a href="#book-a-table" class="btn-event-popular" style="background: linear-gradient(135deg, #3b82f6, #9333ea); color: white; padding: 15px 30px; border-radius: 30px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3); width: 100%; justify-content: center;">
                            <span>Réserver maintenant</span>
                            <i class="bi bi-arrow-right" style="font-size: 1rem;"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Événement 3 - Nouveau -->
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="event-card new" style="background: white; border-radius: 25px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.08); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); height: 100%; position: relative; border: 1px solid rgba(255,255,255,0.3);">
                    <!-- Badge Nouveau -->
                    <div style="position: absolute; top: 20px; left: 20px; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 6px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; z-index: 3; text-transform: uppercase; letter-spacing: 0.5px;">
                        ✨ Nouveau
                    </div>
                    
                    <div class="event-img-container" style="height: 280px; position: relative; overflow: hidden;">
                        <div class="event-img" style="height: 100%; background: linear-gradient(135deg, rgba(16, 185, 129, 0.85), rgba(5, 150, 105, 0.85)), url('assets/img/events/event-3.jpg'); background-size: cover; background-position: center; transition: transform 0.4s ease;"></div>
                        <div class="event-date floating-date" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); padding: 15px 18px; border-radius: 18px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid rgba(255,255,255,0.3);">
                            <div style="font-size: 1.8rem; font-weight: 800; color: #10b981; line-height: 1;">29</div>
                            <div style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Mars</div>
                        </div>
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 50%; background: linear-gradient(transparent, rgba(0,0,0,0.3)); pointer-events: none;"></div>
                    </div>
                    
                    <div class="event-content" style="padding: 35px 30px 30px;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                            <div style="width: 40px; height: 4px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 2px;"></div>
                            <span style="color: #10b981; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;">Atelier</span>
                        </div>
                        
                        <h3 style="color: #1a202c; font-weight: 700; margin-bottom: 15px; font-size: 1.4rem; line-height: 1.3;">Atelier Cuisine Interactive</h3>
                        
                        <p style="color: #64748b; line-height: 1.7; margin-bottom: 25px; font-size: 0.95rem;">
                            Découvrez les secrets de notre cuisine avec notre chef dans un atelier pratique et convivial.
                        </p>
                        
                        <div class="event-details" style="margin-bottom: 25px;">
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 12px; padding: 8px 0;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="bi bi-clock" style="color: white; font-size: 1rem;"></i>
                                </div>
                                <span style="color: #4a5568; font-weight: 500;">15h00 - 18h00</span>
                            </div>
                            <div class="detail-item" style="display: flex; align-items: center; margin-bottom: 12px; padding: 8px 0;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="bi bi-people" style="color: white; font-size: 1rem;"></i>
                                </div>
                                <span style="color: #4a5568; font-weight: 500;">Max 12 personnes</span>
                            </div>
                            <div class="detail-item" style="display: flex; align-items: center; padding: 8px 0;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="bi bi-currency-dollar" style="color: white; font-size: 1rem;"></i>
                                </div>
                                <span style="color: #4a5568; font-weight: 600; font-size: 1.1rem;">15,000 FCFA</span>
                            </div>
                        </div>
                        
                        <a href="#book-a-table" class="btn-event-new" style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 15px 30px; border-radius: 30px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3); width: 100%; justify-content: center;">
                            <span>Réserver maintenant</span>
                            <i class="bi bi-arrow-right" style="font-size: 1rem;"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

                </div>
            </div>
        </div>
    </div>
</section>

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
                
             <div class="contact-section">
        <div class="container">
            <h1 class="section-title">Contactez-nous</h1>
            
            <div class="row gy-4">
                <div class="col-md-6">
                    <div class="info-card" data-aos="fade-up" data-aos-delay="200">
                        <div class="info-icon">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div class="info-content">
                            <h3>Adresse</h3>
                            <p>Dakar, Medina rue 27x24</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="info-card" data-aos="fade-up" data-aos-delay="300">
                        <div class="info-icon">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <div class="info-content">
                            <h3>Appelez-nous</h3>
                            <p><a href="tel:787308706">78 730 87 06</a></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="info-card" data-aos="fade-up" data-aos-delay="400">
                        <div class="info-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="info-content">
                            <h3>Envoyez-nous un email</h3>
                            <p><a href="mailto:mulhomabiala29@gmail.com">mulhomabiala29@gmail.com</a></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="info-card" data-aos="fade-up" data-aos-delay="500">
                        <div class="info-icon">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div class="info-content">
                            <h3>Heures d'ouverture</h3>
                            <ul class="opening-hours">
                                <?php if (!empty($results)): ?>
                                    <?php foreach ($results as $row): ?>
                                        <li>
                                            <span class="day-name"><?= htmlspecialchars($row['jour']) ?></span>
                                            <span class="hours">
                                                <?php if ($row['ferme'] == 1): ?>
                                                    <span class="closed">Fermé</span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars(substr($row['heure_ouverture'], 0, 5)) ?> -
                                                    <?= htmlspecialchars(substr($row['heure_fermeture'], 0, 5)) ?>
                                                <?php endif; ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>
                                        <span class="hours">Aucun horaire trouvé.</span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulaire de contact -->
            <form action="forms/contact.php" method="post" class="php-email-form contact-form" data-aos="fade-up" data-aos-delay="600">
                <h2 class="form-title">Envoyez-nous un message</h2>
                
                <div class="row gy-4">
                    <div class="col-md-6">
                        <div class="form-group">
                            <input type="text" name="name" class="form-control" placeholder="Votre nom complet" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <input type="email" class="form-control" name="email" placeholder="Votre adresse email" required>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <input type="text" class="form-control" name="subject" placeholder="Sujet de votre message" required>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <textarea class="form-control" name="message" rows="6" placeholder="Votre message détaillé..." required style="resize: vertical;"></textarea>
                        </div>
                    </div>
                    <div class="col-md-12 text-center">
                        <div class="loading message-status" style="display: none;">Envoi en cours...</div>
                        <div class="error-message message-status" style="display: none;"></div>
                        <div class="sent-message message-status" style="display: none;">Votre message a été envoyé avec succès ! Merci de nous avoir contactés.</div>
                        <button type="submit" class="submit-btn">Envoyer le message</button>
                    </div>
                </div>
            </form>
        </div>
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
    <script>
        // Create particles
        function createParticles() {
            const particlesContainer = document.querySelector('.particles');
            const particleCount = 50;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 15) + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Animate stats
        function animateStats() {
            const stats = document.querySelectorAll('[data-count]');
            stats.forEach(stat => {
                const target = parseInt(stat.getAttribute('data-count'));
                let current = 0;
                const increment = target / 100;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    stat.textContent = Math.floor(current) + (target >= 1000 ? '+' : '');
                }, 50);
            });
        }

        // Scroll animations
        function setupScrollAnimations() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        if (entry.target.querySelector('[data-count]')) {
                            setTimeout(animateStats, 300);
                        }
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.fade-in').forEach(el => {
                observer.observe(el);
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            createParticles();
            setupScrollAnimations();
            
            // Add initial visible class to hero elements
            setTimeout(() => {
                document.querySelectorAll('.hero-section .fade-in').forEach(el => {
                    el.classList.add('visible');
                });
            }, 100);
        });

        // Mouse parallax effect
        document.addEventListener('mousemove', (e) => {
            const mouseX = e.clientX / window.innerWidth;
            const mouseY = e.clientY / window.innerHeight;
            
            document.querySelectorAll('.morph-shape').forEach((shape, index) => {
                const speed = (index + 1) * 0.02;
                const x = (mouseX - 0.5) * speed * 100;
                const y = (mouseY - 0.5) * speed * 100;
                shape.style.transform += ` translate(${x}px, ${y}px)`;
            });
        });
    </script>
     <script>
        class ProfessionalCarousel {
            constructor() {
                this.currentSlide = 0;
                this.totalSlides = 3;
                this.isAnimating = false;
                this.autoPlayInterval = null;
                this.progressInterval = null;
                this.autoPlayDuration = 6000;
                
                this.container = document.getElementById('carouselContainer');
                this.navDots = document.querySelectorAll('.nav-dot');
                this.prevBtn = document.getElementById('prevBtn');
                this.nextBtn = document.getElementById('nextBtn');
                this.progressBar = document.getElementById('progressBar');
                
                this.init();
            }
            
            init() {
                this.setupEventListeners();
                this.startAutoPlay();
                this.animateSlideContent();
                this.preloadImages();
            }
            
            preloadImages() {
                const slides = document.querySelectorAll('.carousel-slide');
                slides.forEach(slide => {
                    const bgImage = slide.style.backgroundImage;
                    if (bgImage && bgImage !== 'none') {
                        const imageUrl = bgImage.replace(/url\(['"]?(.*?)['"]?\)/, '$1');
                        const img = new Image();
                        img.src = imageUrl;
                    }
                });
            }
            
            setupEventListeners() {
                this.navDots.forEach((dot, index) => {
                    dot.addEventListener('click', () => this.goToSlide(index));
                });
                
                this.prevBtn.addEventListener('click', () => this.previousSlide());
                this.nextBtn.addEventListener('click', () => this.nextSlide());
                
                // Gestion du hover
                const carousel = this.container.parentElement;
                carousel.addEventListener('mouseenter', () => {
                    this.stopAutoPlay();
                    this.stopProgress();
                });
                
                carousel.addEventListener('mouseleave', () => {
                    this.startAutoPlay();
                });
                
                // Navigation clavier
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowLeft') this.previousSlide();
                    if (e.key === 'ArrowRight') this.nextSlide();
                    if (e.key === ' ') {
                        e.preventDefault();
                        this.toggleAutoPlay();
                    }
                });
                
                // Support tactile amélioré
                this.setupTouchSupport();
                
                // Gestion de la visibilité
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        this.stopAutoPlay();
                        this.stopProgress();
                    } else {
                        this.startAutoPlay();
                    }
                });
            }
            
            setupTouchSupport() {
                let startX = null;
                let startY = null;
                let isDragging = false;
                
                this.container.addEventListener('touchstart', (e) => {
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                    isDragging = false;
                }, { passive: true });
                
                this.container.addEventListener('touchmove', (e) => {
                    if (!startX || !startY) return;
                    
                    const deltaX = Math.abs(e.touches[0].clientX - startX);
                    const deltaY = Math.abs(e.touches[0].clientY - startY);
                    
                    if (deltaX > deltaY && deltaX > 10) {
                        isDragging = true;
                        e.preventDefault();
                    }
                }, { passive: false });
                
                this.container.addEventListener('touchend', (e) => {
                    if (!startX || !isDragging) return;
                    
                    const endX = e.changedTouches[0].clientX;
                    const diff = startX - endX;
                    
                    if (Math.abs(diff) > 50) {
                        if (diff > 0) {
                            this.nextSlide();
                        } else {
                            this.previousSlide();
                        }
                    }
                    
                    startX = null;
                    startY = null;
                    isDragging = false;
                }, { passive: true });
            }
            
            goToSlide(slideIndex) {
                if (this.isAnimating || slideIndex === this.currentSlide) return;
                
                this.isAnimating = true;
                this.currentSlide = slideIndex;
                
                const translateX = -slideIndex * 100;
                this.container.style.transform = `translateX(${translateX}%)`;
                
                this.updateNavigation();
                this.animateSlideContent();
                this.resetProgress();
                
                setTimeout(() => {
                    this.isAnimating = false;
                }, 1000);
            }
            
            nextSlide() {
                const nextIndex = (this.currentSlide + 1) % this.totalSlides;
                this.goToSlide(nextIndex);
            }
            
            previousSlide() {
                const prevIndex = this.currentSlide === 0 ? this.totalSlides - 1 : this.currentSlide - 1;
                this.goToSlide(prevIndex);
            }
            
            updateNavigation() {
                this.navDots.forEach((dot, index) => {
                    dot.classList.toggle('active', index === this.currentSlide);
                });
            }
            
            animateSlideContent() {
                const slides = document.querySelectorAll('.carousel-slide');
                slides.forEach((slide, index) => {
                    const content = slide.querySelector('.slide-content');
                    const elements = content.querySelectorAll('.slide-pretitle, .slide-title, .slide-subtitle, .slide-cta-wrapper');
                    
                    if (index === this.currentSlide) {
                        elements.forEach((el, i) => {
                            el.style.animation = 'none';
                            setTimeout(() => {
                                const delay = i * 0.2;
                                if (el.classList.contains('slide-pretitle')) {
                                    el.style.animation = `fadeInDown 1s ease-out ${delay}s both`;
                                } else {
                                    el.style.animation = `fadeInUp 1s ease-out ${delay + 0.3}s both`;
                                }
                            }, 100);
                        });
                    }
                });
            }
            
            startAutoPlay() {
                this.stopAutoPlay();
                this.autoPlayInterval = setInterval(() => {
                    this.nextSlide();
                }, this.autoPlayDuration);
                this.startProgress();
            }
            
            stopAutoPlay() {
                if (this.autoPlayInterval) {
                    clearInterval(this.autoPlayInterval);
                    this.autoPlayInterval = null;
                }
            }
            
            toggleAutoPlay() {
                if (this.autoPlayInterval) {
                    this.stopAutoPlay();
                    this.stopProgress();
                } else {
                    this.startAutoPlay();
                }
            }
            
            startProgress() {
                this.resetProgress();
                this.progressInterval = setInterval(() => {
                    const currentWidth = parseFloat(this.progressBar.style.width) || 0;
                    const increment = 100 / (this.autoPlayDuration / 100);
                    const newWidth = currentWidth + increment;
                    
                    if (newWidth >= 100) {
                        this.resetProgress();
                    } else {
                        this.progressBar.style.width = newWidth + '%';
                    }
                }, 100);
            }
            
            stopProgress() {
                if (this.progressInterval) {
                    clearInterval(this.progressInterval);
                    this.progressInterval = null;
                }
            }
            
            resetProgress() {
                this.progressBar.style.width = '0%';
            }
        }
        
        // Initialize carousel when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            new ProfessionalCarousel();
        });
        
        // Performance optimizations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.willChange = 'transform';
                } else {
                    entry.target.style.willChange = 'auto';
                }
            });
        });
        
        document.querySelectorAll('.carousel-slide').forEach(slide => {
            observer.observe(slide);
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