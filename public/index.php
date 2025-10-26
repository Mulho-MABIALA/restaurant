<?php
session_start();
require_once '../config.php';
require_once 'includes/language.php';
try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Requête pour récupérer les horaires d'ouverture/fermeture par jour
    $query = "
        SELECT jour, heure_ouverture, heure_fermeture, ferme
        FROM horaires_ouverture
        ORDER BY FIELD(jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche')
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les plats pour le carrousel (limité à 15 plats avec images)
    $queryPlatsCarrousel = "
        SELECT p.id, p.nom, p.image, p.prix, p.description, c.nom as categorie_nom
        FROM plats p
        LEFT JOIN categories c ON p.categorie_id = c.id
        WHERE p.disponible = 1 AND p.image IS NOT NULL
        ORDER BY RAND()
        LIMIT 15
    ";
    $stmtCarrousel = $conn->prepare($queryPlatsCarrousel);
    $stmtCarrousel->execute();
    $platsCarrousel = $stmtCarrousel->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer TOUS les plats disponibles pour le modal
    $queryAllPlats = "
        SELECT p.id, p.nom, p.image, p.prix, p.description, c.nom as categorie_nom
        FROM plats p
        LEFT JOIN categories c ON p.categorie_id = c.id
        WHERE p.disponible = 1
        ORDER BY c.nom, p.nom
    ";
    $stmtPlats = $conn->prepare($queryAllPlats);
    $stmtPlats->execute();
    $allPlats = $stmtPlats->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les informations de la section À propos
    $stmtAbout = $conn->prepare("SELECT * FROM about_section WHERE id = 1 LIMIT 1");
    $stmtAbout->execute();
    $aboutData = $stmtAbout->fetch(PDO::FETCH_ASSOC);

    // Calculer les statistiques automatiques
    $stmtTotalPlats = $conn->query("SELECT COUNT(*) as total FROM plats WHERE disponible = 1");
    $totalPlats = $stmtTotalPlats->fetch(PDO::FETCH_ASSOC)['total'];

    $stmtTotalReservations = $conn->query("SELECT COUNT(*) as total FROM reservations");
    $totalReservations = $stmtTotalReservations->fetch(PDO::FETCH_ASSOC)['total'];

    // Calculer les années d'existence
    $anneeCreation = 2020; // À ajuster selon votre restaurant
    $anneesExistence = date('Y') - $anneeCreation;

} catch (PDOException $e) {
    error_log("Erreur SQL : " . $e->getMessage());
    $results = [];
    $platsCarrousel = [];
    $allPlats = [];
    $aboutData = ['titre' => 'À propos de Mulho', 'description' => '', 'sous_titre' => '', 'image' => null];
    $totalPlats = 50;
    $totalReservations = 100;
    $anneesExistence = 5;
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title><?php echo htmlspecialchars(getSetting('restaurant_name', 'Restaurant Mulho')); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(getSetting('restaurant_name', 'Restaurant Mulho')); ?> - Découvrez nos plats de qualité">
    <meta name="keywords" content="restaurant, <?php echo htmlspecialchars(getSetting('restaurant_name', 'mulho')); ?>, dakar, senegal">
    <link rel="icon" type="image/x-icon" href="assets/img/logo.jpg">

    <?php include 'includes/pwa-meta.php'; ?>
    
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            color: var(--text-main);
            background: var(--bg-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        #preloader {
            display: none !important;
        }

        /* Section Hero */
        .hero {
            min-height: 100vh;
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

        :root {
            --primary: #ff6b35;
            --secondary: #f7931e;
            --accent: #ffd23f;

            /* Theme Colors - Light Mode (par défaut) */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #e2e8f0;
            --text-main: #1a202c;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
            --gradient-start: #f8fafc;
            --gradient-end: #e2e8f0;
        }

        /* Dark Mode */
        body.dark-mode {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-main: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --card-bg: #1e293b;
            --gradient-start: #1e293b;
            --gradient-end: #0f172a;
        }

        /* Section À propos */
        .about-section {
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }

        .about-hero {
            text-align: center;
            margin-bottom: 60px;
        }

        .about-title {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .about-subtitle {
            font-size: 1.3rem;
            color: #64748b;
            font-weight: 400;
        }

        .about-content-wrapper {
            display: flex;
            gap: 40px;
            align-items: center;
            margin-bottom: 50px;
        }

        .about-image-container {
            flex: 0 0 45%;
            position: relative;
        }

        .about-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            transition: transform 0.4s ease;
        }

        .about-image:hover {
            transform: scale(1.02);
        }

        .about-text-container {
            flex: 1;
        }

        .about-section-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 1.5rem;
        }

        .about-description {
            font-size: 1.15rem;
            line-height: 1.8;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .about-features {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 2rem;
        }

        .about-feature {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: var(--card-bg);
            border-radius: 16px;
            border-left: 4px solid #ff6b35;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .about-feature:hover {
            transform: translateX(10px);
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.15);
        }

        .about-feature-icon {
            font-size: 2rem;
            color: #ff6b35;
            flex-shrink: 0;
        }

        .about-feature-content h4 {
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 5px;
        }

        .about-feature-content p {
            color: var(--text-muted);
            margin: 0;
            font-size: 0.95rem;
        }

        .about-quote {
            background: linear-gradient(135deg, #fff5f0, #ffffff);
            border-left: 4px solid #ff6b35;
            border-radius: 16px;
            padding: 30px;
            font-style: italic;
            color: #475569;
            font-size: 1.1rem;
            line-height: 1.7;
            margin-top: 30px;
            box-shadow: 0 4px 20px rgba(255, 107, 53, 0.1);
        }

        .about-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 50px;
        }

        .about-stat-card {
            text-align: center;
            padding: 30px;
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .about-stat-card:hover {
            transform: translateY(-5px);
            border-color: #ff6b35;
            box-shadow: 0 12px 35px rgba(255, 107, 53, 0.15);
        }

        .about-stat-number {
            font-size: 3.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .about-stat-label {
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
        }

        .about-cta {
            text-align: center;
            margin-top: 50px;
        }

        .about-cta-btn {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
            padding: 18px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(255, 107, 53, 0.3);
            transition: all 0.3s ease;
        }

        .about-cta-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(255, 107, 53, 0.4);
            color: white;
        }

        @media (max-width: 768px) {
            .about-title {
                font-size: 2.5rem;
            }

            .about-content-wrapper {
                flex-direction: column;
            }

            .about-image-container {
                flex: 0 0 100%;
            }

            .about-image {
                height: 350px;
            }

            .about-section-title {
                font-size: 2rem;
            }

            .about-stats {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .about-stat-number {
                font-size: 2.5rem;
            }

            .about-feature {
                padding: 15px;
            }
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        * {
            font-family: 'Inter', sans-serif;
        }

        /* Section Contact */
        .contact-section {
            padding: 40px 0;
            background: var(--bg-secondary);
        }

        .info-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.15);
            border-color: #ff6b6b;
        }

        .info-icon {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .info-content h3 {
            color: var(--text-main);
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .info-content p,
        .info-content a {
            color: var(--text-muted);
            margin: 0;
            text-decoration: none;
        }

        .info-content a:hover {
            color: #ff6b6b;
        }

        .opening-hours {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .opening-hours li {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .opening-hours li:last-child {
            border-bottom: none;
        }

        .day-name {
            font-weight: 600;
            color: var(--text-main);
        }

        .hours {
            color: var(--text-muted);
        }

        .closed {
            color: #e53e3e;
            font-weight: 600;
        }

        .contact-form {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 35px;
            margin-top: 30px;
        }

        .form-title {
            text-align: center;
            margin-bottom: 30px;
            color: var(--text-main);
            font-weight: 700;
            font-size: 2rem;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 1rem;
            background: var(--bg-secondary);
            color: var(--text-main);
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-control:focus {
            border-color: #ff6b6b;
            background: var(--card-bg);
            outline: none;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.3);
        }

        .message-status {
            text-align: center;
            margin-top: 15px;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
        }

        .loading {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.1);
        }
        .error-message {
            color: #e53e3e;
            background: rgba(229, 62, 62, 0.1);
        }
        .sent-message {
            color: #38a169;
            background: rgba(56, 161, 105, 0.1);
        }

        @media (max-width: 768px) {
            .info-card {
                flex-direction: column;
                text-align: center;
            }

            .contact-form {
                padding: 25px 20px;
            }

            .opening-hours li {
                flex-direction: column;
                gap: 5px;
                text-align: center;
            }
        }
    </style>
  <style>
    .rating-stars {
        display: inline-block;
        unicode-bidi: bidi-override;
        direction: rtl;
    }
    .rating-stars input {
        display: none;
    }
    .rating-stars label {
        display: inline-block;
        padding: 0 3px;
        font-size: 2rem;
        color: #ddd;
        cursor: pointer;
        transition: color 0.3s;
    }
    .rating-stars input:checked ~ label,
    .rating-stars label:hover,
    .rating-stars label:hover ~ label {
        color: #ffc107;
    }
    .rating-stars input:checked + label {
        color: #ffc107;
    }
    
    /* Style pour les cartes d'avis */
    .avis-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
    }
    .avis-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(255, 107, 53, 0.15);
    }
    .avis-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    .client-note {
        color: #ffc107;
        font-size: 1.2rem;
    }

    /* Section Nos Menus */
    .menus-section {
        padding: 60px 0;
        background: #fff;
    }

    .menus-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
        padding: 0 20px;
    }

    .menus-title {
        font-size: 2rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }

    .voir-tout-btn {
        background: transparent;
        border: none;
        color: #1a202c;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .voir-tout-btn:hover {
        color: #ff6b35;
        transform: translateX(5px);
    }

    .menus-container {
        display: flex;
        gap: 30px;
        overflow-x: auto;
        padding: 30px 20px;
        scroll-behavior: smooth;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    .menus-container::-webkit-scrollbar {
        display: none;
    }

    .menu-card {
        flex: 0 0 auto;
        text-align: center;
        cursor: pointer;
        transition: transform 0.3s ease;
        width: 220px;
        margin: 0 10px;
    }

    .menu-card:hover {
        transform: translateY(-10px);
    }

    .menu-circle-wrapper {
        width: 220px;
        height: 220px;
        border-radius: 50%;
        overflow: hidden;
        margin: 0 auto 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
        position: relative;
        border: 3px solid #fff;
    }

    .menu-card:hover .menu-circle-wrapper {
        transform: scale(1.08);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
    }

    .menu-card-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .menu-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1a202c;
        text-align: center;
        max-width: 220px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.4;
        padding: 0 10px;
    }

    /* Animation de défilement automatique */
    @keyframes scroll {
        0% {
            transform: translateX(0);
        }
        100% {
            transform: translateX(calc(-220px * 15));
        }
    }

    .menus-container:hover {
        animation-play-state: paused;
    }

    /* Modal pour tous les produits */
    .modal-menus {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(5px);
        overflow-y: auto;
    }

    .modal-menus.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content-menus {
        background: white;
        border-radius: 24px;
        width: 90%;
        max-width: 1200px;
        max-height: 85vh;
        overflow-y: auto;
        padding: 40px;
        position: relative;
        animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-close {
        position: absolute;
        top: 20px;
        right: 20px;
        background: #f3f4f6;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-size: 1.5rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .modal-close:hover {
        background: #ff6b35;
        color: white;
        transform: rotate(90deg);
    }

    .modal-title {
        font-size: 2rem;
        font-weight: 700;
        color: #1a202c;
        margin-bottom: 30px;
        text-align: center;
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 25px;
    }

    .product-card {
        background: #f8fafc;
        border-radius: 16px;
        padding: 20px;
        transition: all 0.3s ease;
        cursor: pointer;
        border: 2px solid transparent;
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border-color: #ff6b35;
    }

    .product-image {
        width: 100%;
        height: 180px;
        object-fit: cover;
        border-radius: 12px;
        margin-bottom: 15px;
    }

    .product-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1a202c;
        margin-bottom: 8px;
    }

    .product-category {
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 8px;
    }

    .product-price {
        font-size: 1.2rem;
        font-weight: 700;
        color: #ff6b35;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .menus-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .menus-title {
            font-size: 1.5rem;
        }

        .menu-card {
            width: 180px;
            margin: 0 8px;
        }

        .menu-circle-wrapper {
            width: 180px;
            height: 180px;
        }

        .menu-name {
            font-size: 0.95rem;
            max-width: 180px;
        }

        .menus-container {
            gap: 20px;
            padding: 20px 15px;
        }

        .modal-content-menus {
            padding: 25px;
            width: 95%;
        }

        .products-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
    }
</style>
</head>

<body class="index-page">
    
    
    <?php include('includes/navbar.php'); ?>

    <?php include('includes/carrousel.php'); ?>

    <!-- Section Nos Menus -->
    <section class="menus-section">
        <div class="container">
            <div class="menus-header">
                <h2 class="menus-title"><?= t('menu.title') ?></h2>
                <button class="voir-tout-btn" onclick="openModalMenus()">
                    <?= t('menu.all_menus') ?>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>

            <div class="menus-container" id="menusCarousel">
                <?php foreach ($platsCarrousel as $plat): ?>
                    <div class="menu-card">
                        <div class="menu-circle-wrapper">
                            <?php if (!empty($plat['image'])): ?>
                                <img src="uploads/<?= htmlspecialchars($plat['image']) ?>"
                                     alt="<?= htmlspecialchars($plat['nom']) ?>"
                                     class="menu-card-image">
                            <?php else: ?>
                                <div class="menu-card-image" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-utensils" style="font-size: 3rem; color: #94a3b8;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="menu-name"><?= htmlspecialchars($plat['nom']) ?></div>
                    </div>
                <?php endforeach; ?>

                <!-- Dupliquer les cartes pour un défilement infini -->
                <?php foreach ($platsCarrousel as $plat): ?>
                    <div class="menu-card">
                        <div class="menu-circle-wrapper">
                            <?php if (!empty($plat['image'])): ?>
                                <img src="uploads/<?= htmlspecialchars($plat['image']) ?>"
                                     alt="<?= htmlspecialchars($plat['nom']) ?>"
                                     class="menu-card-image">
                            <?php else: ?>
                                <div class="menu-card-image" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-utensils" style="font-size: 3rem; color: #94a3b8;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="menu-name"><?= htmlspecialchars($plat['nom']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Modal Tous les Produits -->
    <div id="modalMenus" class="modal-menus" onclick="closeModalIfOutside(event)">
        <div class="modal-content-menus" onclick="event.stopPropagation()">
            <button class="modal-close" onclick="closeModalMenus()">
                <i class="fas fa-times"></i>
            </button>

            <h2 class="modal-title"><?= t('menu.all_menus') ?></h2>

            <div class="products-grid">
                <?php foreach ($allPlats as $plat): ?>
                    <div class="product-card">
                        <?php if (!empty($plat['image'])): ?>
                            <img src="uploads/<?= htmlspecialchars($plat['image']) ?>"
                                 alt="<?= htmlspecialchars($plat['nom']) ?>"
                                 class="product-image">
                        <?php else: ?>
                            <div class="product-image" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-utensils" style="font-size: 3rem; color: #94a3b8;"></i>
                            </div>
                        <?php endif; ?>

                        <div class="product-name"><?= htmlspecialchars($plat['nom']) ?></div>

                        <?php if (!empty($plat['categorie_nom'])): ?>
                            <div class="product-category"><?= htmlspecialchars($plat['categorie_nom']) ?></div>
                        <?php endif; ?>

                        <div class="product-price"><?= number_format($plat['prix'], 0, ',', ' ') ?> FCFA</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Section À propos -->
    <section class="about-section" data-aos="fade-up">
        <div class="container">
            <!-- Hero Title -->
            <div class="about-hero">
                <h1 class="about-title"><?= htmlspecialchars($aboutData['titre'] ?? 'À propos de Mulho') ?></h1>
                <p class="about-subtitle"><?= htmlspecialchars($aboutData['sous_titre'] ?? 'Où l\'authenticité sénégalaise rencontre l\'excellence culinaire') ?></p>
            </div>

            <!-- Content Wrapper -->
            <div class="about-content-wrapper" data-aos="fade-up" data-aos-delay="100">
                <!-- Image -->
                <div class="about-image-container">
                    <?php if (!empty($aboutData['image'])): ?>
                        <img src="uploads/<?= htmlspecialchars($aboutData['image']) ?>" alt="<?= htmlspecialchars($aboutData['titre'] ?? 'Restaurant Mulho') ?>" class="about-image">
                    <?php else: ?>
                        <img src="assets/img/apropos.jpg" alt="Restaurant Mulho" class="about-image">
                    <?php endif; ?>
                </div>

                <!-- Text Content -->
                <div class="about-text-container">
                    <h2 class="about-section-title"><?= t('about.subtitle') ?></h2>

                    <p class="about-description">
                        <?= nl2br(htmlspecialchars($aboutData['description'] ?? 'Bienvenue au Restaurant Mulho, où chaque plat raconte l\'histoire passionnée de la gastronomie sénégalaise. Situé au cœur vibrant de Dakar, nous créons des expériences culinaires qui éveillent les sens et célèbrent l\'authenticité de notre terroir.')) ?>
                    </p>

                    <!-- Features -->
                    <div class="about-features">
                        <div class="about-feature">
                            <i class="bi bi-gem about-feature-icon"></i>
                            <div class="about-feature-content">
                                <h4><?= t('about.feature_quality') ?></h4>
                                <p><?= t('about.feature_quality_desc') ?></p>
                            </div>
                        </div>

                        <div class="about-feature">
                            <i class="bi bi-fire about-feature-icon"></i>
                            <div class="about-feature-content">
                                <h4><?= t('about.feature_chef') ?></h4>
                                <p><?= t('about.feature_chef_desc') ?></p>
                            </div>
                        </div>

                        <div class="about-feature">
                            <i class="bi bi-hearts about-feature-icon"></i>
                            <div class="about-feature-content">
                                <h4><?= t('about.feature_ambiance') ?></h4>
                                <p><?= t('about.feature_ambiance_desc') ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Quote -->
                    <div class="about-quote">
                        "Notre passion transcende la simple restauration. Nous créons des moments magiques où chaque bouchée transporte nos invités dans un voyage sensoriel au cœur de l'âme sénégalaise."
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="about-stats" data-aos="fade-up" data-aos-delay="200">
                <div class="about-stat-card">
                    <div class="about-stat-number" data-count="<?= $anneesExistence ?>">0</div>
                    <div class="about-stat-label"><?= t('about.years_experience') ?></div>
                </div>
                <div class="about-stat-card">
                    <div class="about-stat-number" data-count="<?= $totalPlats ?>">0</div>
                    <div class="about-stat-label"><?= t('about.dishes') ?></div>
                </div>
                <div class="about-stat-card">
                    <div class="about-stat-number" data-count="<?= $totalReservations ?>">0</div>
                    <div class="about-stat-label"><?= t('about.happy_customers') ?></div>
                </div>
            </div>

            <!-- CTA Button -->
            <div class="about-cta" data-aos="fade-up" data-aos-delay="300">
                <a href="tel:787308706" class="about-cta-btn">
                    <i class="bi bi-telephone-fill"></i>
<?= t('misc.book_now_call') ?> : 78 730 87 06
                </a>
            </div>
        </div>
    </section>
    <!-- Section Réserver une table -->
    <section id="book-a-table" class="book-a-table section">
        <div class="container section-title" data-aos="fade-up">
            <h2><?= t('contact.form_title') ?></h2>
        </div>
        <div class="container">
            <div class="row g-0" data-aos="fade-up" data-aos-delay="100">
                <div class="col-lg-4 reservation-img" style="background-image: url(assets/img/reservation.jpg); background-size: cover; background-position: center; min-height: 400px;"></div>
                <div class="col-lg-8 d-flex align-items-center" style="background: #f8fafc; padding: 60px 40px;" data-aos="fade-up" data-aos-delay="200">
                    <form action="forms/book-a-table.php" method="post" role="form" class="php-email-form" style="width: 100%;">
                        <div class="row gy-4">
                            <div class="col-lg-4 col-md-6">
                                <input type="text" name="name" class="form-control" id="name" placeholder="<?= t('contact.name') ?>" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <input type="email" class="form-control" name="email" id="email" placeholder="<?= t('contact.email') ?>" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <input type="text" class="form-control" name="phone" id="phone" placeholder="<?= t('contact.phone') ?>" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <input type="date" name="date" class="form-control" id="date" placeholder="<?= t('contact.date') ?>" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <input type="time" class="form-control" name="time" id="time" placeholder="<?= t('contact.time') ?>" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <input type="number" class="form-control" name="people" id="people" placeholder="<?= t('contact.guests') ?>" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px;">
                            </div>
                        </div>
                        <div class="form-group mt-3">
                            <textarea class="form-control" name="message" rows="5" placeholder="<?= t('contact.message') ?>" style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px; width: 100%;"></textarea>
                        </div>
                        <div class="text-center mt-3">
                            <div class="loading" style="display: none;"><?= t('actions.loading') ?></div>
                            <div class="error-message" style="display: none; color: #e53e3e;"></div>
                            <div class="sent-message" style="display: none; color: #38a169;"><?= t('contact.success_message') ?></div>
                            <button type="submit" style="background: linear-gradient(135deg, #ec4899, #f97316); color: white; border: none; padding: 15px 40px; border-radius: 50px; font-weight: 600; cursor: pointer; transition: all 0.3s ease;"><?= t('contact.form_title') ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
<!-- ======= Avis Clients Section ======= -->
<section id="avis" class="avis section">
    <div class="container section-title" data-aos="fade-up">
        <h2><?= t('misc.reviews') ?></h2>
        <p><span><?= t('misc.what_clients_say') ?></span> <span class="description-title"><?= t('misc.our_clients') ?></span></p>
    </div>
    
    <div class="container" data-aos="fade-up" data-aos-delay="100">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="glass-card p-4 mb-5">
                    <h3 class="text-center mb-4" style="font-family: 'Playfair Display', serif; background: linear-gradient(135deg, var(--primary), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                        <?= t('misc.leave_review') ?>
                    </h3>
                    
                    <form id="avis-form" method="post" action="traitement_avis.php">
                        <div class="row gy-4">

                            <div class="col-md-12">
                                <div class="alert alert-info" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 10px; padding: 12px;">
                                    <i class="fas fa-info-circle"></i> <?= t('misc.anonymous_review') ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <textarea class="form-control" name="message" rows="5" placeholder="<?= t('misc.share_experience') ?>" required style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px; word-break: break-word; overflow-wrap: break-word;"></textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group text-center">
                                    <label class="mb-2"><?= t('misc.rate_experience') ?></label>
                                    <div class="rating-stars mb-3">
                                        <input type="radio" id="star5" name="note" value="5" />
                                        <label for="star5"><i class="fas fa-star"></i></label>
                                        <input type="radio" id="star4" name="note" value="4" />
                                        <label for="star4"><i class="fas fa-star"></i></label>
                                        <input type="radio" id="star3" name="note" value="3" />
                                        <label for="star3"><i class="fas fa-star"></i></label>
                                        <input type="radio" id="star2" name="note" value="2" />
                                        <label for="star2"><i class="fas fa-star"></i></label>
                                        <input type="radio" id="star1" name="note" value="1" required />
                                        <label for="star1"><i class="fas fa-star"></i></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <div class="loading" style="display: none;"><?= t('actions.loading') ?></div>
                            <div class="error-message" style="display: none;"></div>
                            <div class="sent-message" style="display: none;"><?= t('misc.review_success') ?></div>
                            <button type="submit" class="cta-glow"><?= t('misc.submit_review') ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="row" id="avis-container">
            <!-- Les avis validés seront chargés ici via JavaScript -->
        </div>
    </div>
</section>
    <!-- Section Contact -->
    <section id="contact" class="contact section">
        <div class="container section-title" data-aos="fade-up">
            <h2><?= t('nav.contact') ?></h2>
            <p><span><?= t('contact.subtitle') ?></span></p>
        </div>
        <div class="container" data-aos="fade-up" data-aos-delay="100">
            <div class="mb-5">
                <iframe style="width: 100%; height: 400px; border-radius: 15px;" 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3858.9689555935147!2d-17.44270312595434!3d14.693425085886857!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xec10d6c8b7e6c13%3A0x20e6e5b6b7e6c13!2sMedina%2C%20Dakar%2C%20Senegal!5e0!3m2!1sen!2sus!4v1641234567890!5m2!1sen!2sus" 
                        frameborder="0" allowfullscreen=""></iframe>
            </div>
            
            <div class="contact-section">
                <div class="container">
                    <h1 class="section-title"><?= t('contact.title') ?></h1>
                    
                    <div class="row gy-4">
                        <div class="col-md-6">
                            <div class="info-card" data-aos="fade-up" data-aos-delay="200">
                                <div class="info-icon">
                                    <i class="bi bi-geo-alt"></i>
                                </div>
                                <div class="info-content">
                                    <h3><?= t('contact.address') ?></h3>
                                    <p><?php echo htmlspecialchars(getSetting('restaurant_address', 'Dakar, Medina rue 27x24')); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="info-card" data-aos="fade-up" data-aos-delay="300">
                                <div class="info-icon">
                                    <i class="bi bi-telephone"></i>
                                </div>
                                <div class="info-content">
                                    <h3><?= t('contact.phone') ?></h3>
                                    <p><a href="tel:<?php echo preg_replace('/[^0-9]/', '', getSetting('contact_phone', '787308706')); ?>"><?php echo htmlspecialchars(getSetting('contact_phone', '78 730 87 06')); ?></a></p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="info-card" data-aos="fade-up" data-aos-delay="400">
                                <div class="info-icon">
                                    <i class="bi bi-envelope"></i>
                                </div>
                                <div class="info-content">
                                    <h3><?= t('contact.email') ?></h3>
                                    <p><a href="mailto:<?php echo htmlspecialchars(getSetting('contact_email', 'mulhomabiala29@gmail.com')); ?>"><?php echo htmlspecialchars(getSetting('contact_email', 'mulhomabiala29@gmail.com')); ?></a></p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="info-card" data-aos="fade-up" data-aos-delay="500">
                                <div class="info-icon">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div class="info-content">
                                    <h3><?= t('contact.opening_hours') ?></h3>
                                    <ul class="opening-hours">
                                        <?php if (!empty($results)): ?>
                                            <?php foreach ($results as $row): ?>
                                                <li>
                                                    <span class="day-name"><?= htmlspecialchars($row['jour']) ?></span>
                                                    <span class="hours">
                                                        <?php if ($row['ferme'] == 1): ?>
                                                            <span class="closed"><?= t('contact.closed') ?></span>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars(substr($row['heure_ouverture'], 0, 5)) ?> -
                                                            <?= htmlspecialchars(substr($row['heure_fermeture'], 0, 5)) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li>
                                                <span class="hours"><?= t('misc.no_hours') ?></span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulaire de contact -->
                    <form action="forms/contact.php" method="post" class="php-email-form contact-form" data-aos="fade-up" data-aos-delay="600">
                        <h2 class="form-title"><?= t('contact.title') ?></h2>

                        <div class="row gy-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <input type="text" name="name" class="form-control" placeholder="<?= t('contact.name') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <input type="email" class="form-control" name="email" placeholder="<?= t('contact.email') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <input type="text" class="form-control" name="subject" placeholder="<?= t('contact.message') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <textarea class="form-control" name="message" rows="6" placeholder="<?= t('contact.message') ?>" required style="resize: vertical;"></textarea>
                                </div>
                            </div>
                            <div class="col-md-12 text-center">
                                <div class="loading message-status" style="display: none;"><?= t('actions.loading') ?></div>
                                <div class="error-message message-status" style="display: none;"></div>
                                <div class="sent-message message-status" style="display: none;"><?= t('contact.success_message') ?></div>
                                <button type="submit" class="submit-btn"><?= t('contact.send') ?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php include('includes/footer.php'); ?>

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center" style="position: fixed; bottom: 30px; right: 30px; background: linear-gradient(135deg, #ec4899, #f97316); color: white; width: 50px; height: 50px; border-radius: 50%; text-decoration: none; box-shadow: 0 5px 15px rgba(236, 72, 153, 0.3); transition: all 0.3s ease; z-index: 999; display: none;">
        <i class="bi bi-arrow-up-short" style="font-size: 1.5rem;"></i>
    </a>

    <!-- Scripts -->
    <script src="cart.js"></script>
    <script>
    // Fonction pour changer le thème
    function toggleTheme() {
        const body = document.body;
        const themeIcon = document.getElementById('theme-icon');

        if (body.classList.contains('dark-mode')) {
            // Passer en mode clair
            body.classList.remove('dark-mode');
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
            localStorage.setItem('theme', 'light');
        } else {
            // Passer en mode sombre
            body.classList.add('dark-mode');
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
            localStorage.setItem('theme', 'dark');
        }
    }

    // Fonction pour charger le thème sauvegardé
    function loadTheme() {
        const savedTheme = localStorage.getItem('theme');
        const body = document.body;
        const themeIcon = document.getElementById('theme-icon');

        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            if (themeIcon) {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            }
        } else {
            body.classList.remove('dark-mode');
            if (themeIcon) {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Charger le thème sauvegardé
        loadTheme();

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

        // === 🔗 Scroll fluide vers les ancres ===
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
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

    document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la soumission du formulaire d'avis
    const avisForm = document.getElementById('avis-form');
    if (avisForm) {
        avisForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const loading = this.querySelector('.loading');
            const errorMessage = this.querySelector('.error-message');
            const sentMessage = this.querySelector('.sent-message');
            
            // Masquer les messages précédents
            errorMessage.style.display = 'none';
            sentMessage.style.display = 'none';
            loading.style.display = 'block';
            submitBtn.disabled = true;
            
            fetch('traitement_avis.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                
                if (data.success) {
                    sentMessage.style.display = 'block';
                    avisForm.reset();
                    // Recharger les avis après soumission
                    chargerAvis();
                } else {
                    errorMessage.textContent = data.message || 'Une erreur est survenue';
                    errorMessage.style.display = 'block';
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                errorMessage.textContent = 'Erreur de connexion';
                errorMessage.style.display = 'block';
                console.error('Erreur:', error);
            })
            .finally(() => {
                submitBtn.disabled = false;
            });
        });
    }
    
    // Charger les avis validés
    function chargerAvis() {
        fetch('../admin/get_avis.php')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('avis-container');
                if (container && data.success) {
                    container.innerHTML = data.html;
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des avis:', error);
            });
    }
    
    // Charger les avis au chargement de la page
    chargerAvis();
});
    </script>

    <script>
        // Animate stats for About section
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

        // Observe About section for stats animation
        document.addEventListener('DOMContentLoaded', () => {
            const aboutSection = document.querySelector('.about-section');
            if (aboutSection) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            animateStats();
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.3 });

                observer.observe(aboutSection);
            }
        });

        // === 🍽️ Gestion du Modal des Menus ===
        function openModalMenus() {
            const modal = document.getElementById('modalMenus');
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModalMenus() {
            const modal = document.getElementById('modalMenus');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }

        function closeModalIfOutside(event) {
            if (event.target.id === 'modalMenus') {
                closeModalMenus();
            }
        }

        // Fermer le modal avec la touche Échap
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModalMenus();
            }
        });

        // === 🎠 Défilement automatique du carrousel de menus ===
        const menusCarousel = document.getElementById('menusCarousel');
        if (menusCarousel) {
            let scrollAmount = 0;
            let scrollSpeed = 1.5; // pixels par frame (augmenté pour meilleure visibilité)
            const cardWidth = 250; // 220px width + 30px gap

            function autoScroll() {
                scrollAmount += scrollSpeed;
                menusCarousel.scrollLeft = scrollAmount;

                // Réinitialiser quand on atteint la moitié (on a dupliqué les cartes)
                if (scrollAmount >= menusCarousel.scrollWidth / 2) {
                    scrollAmount = 0;
                    menusCarousel.scrollLeft = 0;
                }

                requestAnimationFrame(autoScroll);
            }

            // Démarrer le défilement automatique
            autoScroll();

            // Arrêter le défilement au survol
            menusCarousel.addEventListener('mouseenter', function() {
                scrollSpeed = 0;
            });

            // Reprendre le défilement après le survol
            menusCarousel.addEventListener('mouseleave', function() {
                scrollSpeed = 1.5;
            });
        }
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