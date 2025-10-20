<?php
session_start();
require_once '../config.php';
require_once 'includes/language.php';

// Définir la locale française pour les dates
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fra');

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer tous les événements
    $queryEvenements = "SELECT * FROM evenements ORDER BY date_evenement DESC";
    $stmtEvenements = $conn->prepare($queryEvenements);
    $stmtEvenements->execute();
    $evenements = $stmtEvenements->fetchAll(PDO::FETCH_ASSOC);

    // Ajouter le nombre de photos pour chaque événement
    foreach ($evenements as &$event) {
        try {
            $stmtPhotos = $conn->prepare("SELECT COUNT(*) as nb FROM evenements_galerie WHERE evenement_id = ?");
            $stmtPhotos->execute([$event['id']]);
            $event['nb_photos'] = $stmtPhotos->fetchColumn();
        } catch (PDOException $e) {
            // Si la table galerie n'existe pas, on met 0
            $event['nb_photos'] = 0;
        }
    }

} catch (PDOException $e) {
    error_log("Erreur SQL : " . $e->getMessage());
    echo "<!-- Erreur SQL: " . $e->getMessage() . " -->";
    $evenements = [];
}

// Fonction pour formater la date en français
function formatDateFr($date) {
    $moisFr = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'
    ];

    $timestamp = strtotime($date);
    $jour = date('d', $timestamp);
    $moisNum = (int)date('m', $timestamp);
    $annee = date('Y', $timestamp);

    return "$jour " . $moisFr[$moisNum] . " $annee";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Événements - Restaurant Mulho</title>
    <meta name="description" content="Découvrez nos événements et soirées spéciales">
    <link rel="icon" type="image/x-icon" href="assets/img/logo.jpg">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;600;700;800&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #000000;
            color: #ffffff;
            overflow-x: hidden;
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            width: 100%;
            height: 100vh;
            min-height: 700px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.6) 0%, rgba(0, 0, 0, 0.4) 50%, rgba(0, 0, 0, 0.7) 100%);
            z-index: 1;
        }

        .hero-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 50%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
        }

        /* Image de fond par défaut si pas d'image d'événement */
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 50%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1511578314322-379afb476865?ixlib=rb-4.0.3&auto=format&fit=crop&w=1469&q=80') center/cover;
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1400px;
            padding: 0 60px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
        }

        .hero-left {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-right {
            background: rgba(60, 60, 60, 0.85);
            backdrop-filter: blur(20px);
            padding: 80px 60px;
            border-radius: 0;
            color: white;
        }

        .event-pretitle {
            font-family: 'Inter', sans-serif;
            font-size: 1.1rem;
            font-weight: 300;
            color: rgba(255, 255, 255, 0.9);
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .event-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.5rem, 6vw, 4rem);
            font-weight: 300;
            line-height: 1.1;
            margin-bottom: 30px;
            color: #D4AF37;
        }

        .event-date {
            font-family: 'Inter', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 40px;
            color: #ffffff;
        }

        .event-description {
            font-family: 'Inter', sans-serif;
            font-size: 1.15rem;
            line-height: 1.8;
            margin-bottom: 50px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 300;
        }

        .event-cta-btn {
            background: #D4AF37;
            color: #000000;
            padding: 18px 50px;
            border: none;
            font-family: 'Inter', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
        }

        .event-cta-btn:hover {
            background: #F4D03F;
            color: #000000;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.4);
        }

        .scroll-indicator {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
            animation: bounce 2s infinite;
            cursor: pointer;
            z-index: 2;
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

        /* Events List Section */
        .events-list-section {
            background: #000000;
            padding: 120px 0;
            min-height: 100vh;
        }

        .section-title-container {
            text-align: center;
            margin-bottom: 100px;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(3rem, 8vw, 5rem);
            font-weight: 700;
            color: #D4AF37;
            margin-bottom: 20px;
            text-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
        }

        .section-subtitle {
            font-family: 'Inter', sans-serif;
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 300;
        }

        /* Event Card */
        .event-card {
            background: rgba(30, 30, 30, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(212, 175, 55, 0.2);
            margin-bottom: 60px;
            transition: all 0.4s ease;
            overflow: hidden;
        }

        .event-card:hover {
            border-color: rgba(212, 175, 55, 0.5);
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(212, 175, 55, 0.2);
        }

        .event-card-inner {
            display: grid;
            grid-template-columns: 45% 55%;
        }

        .event-card-image {
            position: relative;
            height: 450px;
            overflow: hidden;
        }

        .event-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .event-card:hover .event-card-image img {
            transform: scale(1.1);
        }

        .event-card-badge {
            position: absolute;
            top: 30px;
            right: 30px;
            background: rgba(212, 175, 55, 0.95);
            color: #000000;
            padding: 15px 25px;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .event-card-content {
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .event-card-category {
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            color: #D4AF37;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
        }

        .event-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .event-card-meta {
            display: flex;
            gap: 30px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .event-card-meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
        }

        .event-card-meta-item i {
            color: #D4AF37;
            font-size: 1.1rem;
        }

        .event-card-description {
            font-family: 'Inter', sans-serif;
            font-size: 1.05rem;
            line-height: 1.8;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 30px;
            font-weight: 300;
        }

        .event-card-price {
            font-family: 'Inter', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #D4AF37;
            margin-bottom: 30px;
        }

        .event-card-actions {
            display: flex;
            gap: 15px;
        }

        .btn-event-primary {
            background: #D4AF37;
            color: #000000;
            padding: 15px 35px;
            border: none;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-event-primary:hover {
            background: #F4D03F;
            color: #000000;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.4);
        }

        .btn-event-secondary {
            background: transparent;
            color: #D4AF37;
            padding: 15px 35px;
            border: 2px solid #D4AF37;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-event-secondary:hover {
            background: #D4AF37;
            color: #000000;
            transform: translateY(-2px);
        }

        /* Modal Galerie */
        .modal-galerie {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.97);
            overflow-y: auto;
        }

        .modal-galerie.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .modal-content-galerie {
            background: rgba(30, 30, 30, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(212, 175, 55, 0.3);
            width: 90%;
            max-width: 1200px;
            padding: 50px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: transparent;
            border: none;
            width: 45px;
            height: 45px;
            font-size: 2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
            color: #D4AF37;
        }

        .modal-close:hover {
            color: #F4D03F;
            transform: rotate(90deg);
        }

        .modal-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: #D4AF37;
            margin-bottom: 40px;
            text-align: center;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .gallery-item {
            position: relative;
            overflow: hidden;
            cursor: pointer;
            aspect-ratio: 1;
            border: 1px solid rgba(212, 175, 55, 0.2);
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .gallery-item:hover img {
            transform: scale(1.1);
        }

        .gallery-item-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            padding: 20px;
            color: white;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }

        .gallery-item:hover .gallery-item-overlay {
            transform: translateY(0);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 120px 20px;
        }

        .empty-state i {
            font-size: 5rem;
            color: rgba(212, 175, 55, 0.3);
            margin-bottom: 30px;
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 15px;
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 1.1rem;
        }

        /* Section Réservation */
        .reservation-section {
            background: #000000;
            padding: 120px 0;
        }

        .reservation-title-container {
            text-align: center;
            margin-bottom: 80px;
        }

        .reservation-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(3rem, 8vw, 5rem);
            font-weight: 700;
            color: #D4AF37;
            margin-bottom: 20px;
            text-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
        }

        .reservation-subtitle {
            font-family: 'Inter', sans-serif;
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 300;
        }

        .reservation-grid {
            display: grid;
            grid-template-columns: 40% 60%;
            background: rgba(30, 30, 30, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(212, 175, 55, 0.2);
            overflow: hidden;
        }

        .reservation-image {
            background: url('https://images.unsplash.com/photo-1414235077428-338989a2e8c0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80') center/cover;
            min-height: 600px;
            position: relative;
        }

        .reservation-image::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.2), rgba(0, 0, 0, 0.5));
        }

        .reservation-form-container {
            padding: 60px 50px;
        }

        .reservation-form {
            width: 100%;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            width: 100%;
        }

        .form-input,
        .form-textarea {
            width: 100%;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(212, 175, 55, 0.3);
            border-radius: 8px;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input::placeholder,
        .form-textarea::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #D4AF37;
            background: rgba(255, 255, 255, 0.08);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-messages {
            margin: 20px 0;
            min-height: 30px;
        }

        .reservation-submit-btn {
            background: linear-gradient(135deg, #D4AF37, #F4D03F);
            color: #000000;
            border: none;
            padding: 18px 50px;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
        }

        .reservation-submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(212, 175, 55, 0.5);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero-content {
                grid-template-columns: 1fr;
                padding: 0 40px;
            }

            .hero-image {
                width: 100%;
                opacity: 0.3;
            }

            .hero-right {
                padding: 60px 40px;
            }

            .event-card-inner {
                grid-template-columns: 1fr;
            }

            .event-card-image {
                height: 350px;
            }

            .event-card-content {
                padding: 40px 30px;
            }

            .reservation-grid {
                grid-template-columns: 1fr;
            }

            .reservation-image {
                min-height: 300px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .hero-section {
                min-height: 600px;
            }

            .hero-right {
                padding: 40px 30px;
            }

            .event-title {
                font-size: 2.5rem;
            }

            .event-card-title {
                font-size: 2rem;
            }

            .event-card-actions {
                flex-direction: column;
            }

            .btn-event-primary,
            .btn-event-secondary {
                justify-content: center;
                width: 100%;
            }

            .modal-content-galerie {
                padding: 30px;
            }

            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }

            .reservation-form-container {
                padding: 40px 25px;
            }

            .reservation-submit-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php include('includes/navbar.php'); ?>

    <?php if (!empty($evenements)): ?>
        <?php $firstEvent = $evenements[0]; ?>
        <!-- Hero Section -->
        <section class="hero-section">
            <?php if (!empty($firstEvent['image'])): ?>
                <img src="../admin/uploads/evenements/<?= htmlspecialchars($firstEvent['image']) ?>"
                     alt="<?= htmlspecialchars($firstEvent['titre']) ?>"
                     class="hero-image"
                     onerror="this.style.display='none'">
            <?php endif; ?>

            <div class="hero-background"></div>

            <div class="hero-content">
                <div class="hero-left"></div>

                <div class="hero-right">
                    <?php if (!empty($firstEvent['lieu'])): ?>
                        <div class="event-pretitle"><?= strtoupper(htmlspecialchars($firstEvent['lieu'])) ?></div>
                    <?php endif; ?>

                    <h1 class="event-title"><?= htmlspecialchars($firstEvent['titre']) ?></h1>

                    <div class="event-date">
                        <?= formatDateFr($firstEvent['date_evenement']) ?>
                    </div>

                    <p class="event-description">
                        <?php if (!empty($firstEvent['heure_evenement'])): ?>
                            Nous vous attendons le <?= date('d', strtotime($firstEvent['date_evenement'])) ?>
                            <?= date('F', strtotime($firstEvent['date_evenement'])) ?> à
                            <?= substr($firstEvent['heure_evenement'], 0, 5) ?> !
                        <?php else: ?>
                            <?= nl2br(htmlspecialchars($firstEvent['description'])) ?>
                        <?php endif; ?>
                    </p>

                    <a href="tel:787308706" class="event-cta-btn">Je veux l'entendre</a>
                </div>
            </div>

            <div class="scroll-indicator" onclick="document.getElementById('all-events').scrollIntoView({behavior: 'smooth'})">
                <p style="font-size: 0.9rem; margin-bottom: 10px; opacity: 0.8;">Voir tous les événements</p>
                <i class="fas fa-chevron-down" style="font-size: 1.5rem;"></i>
            </div>
        </section>
    <?php endif; ?>

    <!-- All Events Section -->
    <section class="events-list-section" id="all-events">
        <div class="container">
            <div class="section-title-container">
                <h2 class="section-title">Tous nos événements</h2>
                <p class="section-subtitle">Découvrez notre programmation complète</p>
            </div>

            <?php if (empty($evenements)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>Aucun événement à venir</h3>
                    <p>Restez connectés pour découvrir nos prochains événements !</p>
                </div>
            <?php else: ?>
                <?php foreach ($evenements as $index => $event): ?>
                    <div class="event-card" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                        <div class="event-card-inner">
                            <div class="event-card-image">
                                <?php if (!empty($event['image'])): ?>
                                    <img src="../admin/uploads/evenements/<?= htmlspecialchars($event['image']) ?>"
                                         alt="<?= htmlspecialchars($event['titre']) ?>"
                                         onerror="this.parentElement.innerHTML='<div style=\'height:100%;background:rgba(212,175,55,0.1);display:flex;align-items:center;justify-content:center;color:rgba(212,175,55,0.5);font-size:3rem;\'><i class=\'fas fa-image\'></i></div>'">
                                <?php else: ?>
                                    <div style="height:100%;background:rgba(212,175,55,0.1);display:flex;align-items:center;justify-content:center;color:rgba(212,175,55,0.5);font-size:3rem;">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="event-card-content">
                                <?php if (!empty($event['lieu'])): ?>
                                    <div class="event-card-category"><?= htmlspecialchars($event['lieu']) ?></div>
                                <?php endif; ?>

                                <h3 class="event-card-title"><?= htmlspecialchars($event['titre']) ?></h3>

                                <div class="event-card-meta">
                                    <div class="event-card-meta-item">
                                        <i class="far fa-calendar"></i>
                                        <span><?= date('d/m/Y', strtotime($event['date_evenement'])) ?></span>
                                    </div>

                                    <?php if (!empty($event['heure_evenement'])): ?>
                                        <div class="event-card-meta-item">
                                            <i class="far fa-clock"></i>
                                            <span><?= substr($event['heure_evenement'], 0, 5) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($event['nb_photos'] > 0): ?>
                                        <div class="event-card-meta-item">
                                            <i class="fas fa-images"></i>
                                            <span><?= $event['nb_photos'] ?> photo<?= $event['nb_photos'] > 1 ? 's' : '' ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <p class="event-card-description">
                                    <?= nl2br(htmlspecialchars($event['description'])) ?>
                                </p>

                                <div class="event-card-actions">
                                    <a href="tel:787308706" class="btn-event-primary">
                                        <i class="fas fa-phone-alt"></i>
                                        Réserver
                                    </a>

                                    <?php if ($event['nb_photos'] > 0): ?>
                                        <button class="btn-event-secondary" onclick="openGallery(<?= $event['id'] ?>, '<?= htmlspecialchars(addslashes($event['titre'])) ?>')">
                                            <i class="fas fa-images"></i>
                                            Voir la galerie
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section Réservation -->
    <section class="reservation-section" id="reservation">
        <div class="container">
            <div class="reservation-title-container" data-aos="fade-up">
                <h2 class="reservation-title">Réserver une table</h2>
                <p class="reservation-subtitle">Réservez votre table pour profiter de nos événements</p>
            </div>

            <div class="reservation-content" data-aos="fade-up" data-aos-delay="100">
                <div class="reservation-grid">
                    <!-- Image -->
                    <div class="reservation-image"></div>

                    <!-- Formulaire -->
                    <div class="reservation-form-container">
                        <form action="forms/book-a-table.php" method="post" role="form" class="reservation-form php-email-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" name="name" class="form-input" placeholder="Votre nom" required>
                                </div>
                                <div class="form-group">
                                    <input type="email" name="email" class="form-input" placeholder="Votre email" required>
                                </div>
                                <div class="form-group">
                                    <input type="text" name="phone" class="form-input" placeholder="Votre téléphone" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <input type="date" name="date" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <input type="time" name="time" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <input type="number" name="people" class="form-input" placeholder="Nombre de personnes" required min="1">
                                </div>
                            </div>

                            <div class="form-group">
                                <textarea name="message" class="form-textarea" rows="5" placeholder="Message (optionnel)"></textarea>
                            </div>

                            <div class="form-messages">
                                <div class="loading" style="display: none; color: #D4AF37;">Envoi en cours...</div>
                                <div class="error-message" style="display: none; color: #ef4444;"></div>
                                <div class="sent-message" style="display: none; color: #10b981;">Votre demande de réservation a été envoyée. Nous vous rappellerons pour confirmer. Merci !</div>
                            </div>

                            <button type="submit" class="reservation-submit-btn">
                                <i class="fas fa-calendar-check"></i>
                                Réserver maintenant
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal Galerie -->
    <div id="modalGalerie" class="modal-galerie" onclick="closeGalleryIfOutside(event)">
        <div class="modal-content-galerie" onclick="event.stopPropagation()">
            <button class="modal-close" onclick="closeGallery()">×</button>

            <h2 class="modal-title" id="galleryTitle"></h2>

            <div class="gallery-grid" id="galleryGrid">
                <!-- Les photos seront chargées ici -->
            </div>
        </div>
    </div>

    <?php include('includes/footer.php'); ?>

    <!-- Scripts -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>

    <script>
        // Initialize AOS
        if (typeof AOS !== 'undefined') {
            AOS.init({
                duration: 800,
                easing: 'ease-in-out',
                once: true,
                mirror: false
            });
        }

        // Gallery functions
        function openGallery(eventId, eventTitle) {
            const modal = document.getElementById('modalGalerie');
            const galleryTitle = document.getElementById('galleryTitle');
            const galleryGrid = document.getElementById('galleryGrid');

            galleryTitle.textContent = eventTitle;
            galleryGrid.innerHTML = '<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:3rem;color:#D4AF37;"></i></div>';

            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Charger les photos via AJAX
            fetch(`get_event_gallery.php?id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.photos.length > 0) {
                        galleryGrid.innerHTML = data.photos.map(photo => `
                            <div class="gallery-item">
                                <img src="../admin/uploads/evenements/${photo.image}" alt="${photo.legende || ''}" onerror="this.parentElement.style.display='none'">
                                ${photo.legende ? `<div class="gallery-item-overlay">${photo.legende}</div>` : ''}
                            </div>
                        `).join('');
                    } else {
                        galleryGrid.innerHTML = '<p style="text-align:center;color:rgba(255,255,255,0.6);grid-column:1/-1;padding:60px 20px;">Aucune photo disponible</p>';
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    galleryGrid.innerHTML = '<p style="text-align:center;color:#D4AF37;grid-column:1/-1;padding:60px 20px;">Erreur de chargement</p>';
                });
        }

        function closeGallery() {
            const modal = document.getElementById('modalGalerie');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function closeGalleryIfOutside(event) {
            if (event.target.id === 'modalGalerie') {
                closeGallery();
            }
        }

        // Close with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeGallery();
            }
        });

        // Gestion du formulaire de réservation
        document.addEventListener('DOMContentLoaded', function() {
            const reservationForm = document.querySelector('.php-email-form');

            if (reservationForm) {
                reservationForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const loadingDiv = this.querySelector('.loading');
                    const errorDiv = this.querySelector('.error-message');
                    const successDiv = this.querySelector('.sent-message');
                    const submitBtn = this.querySelector('.reservation-submit-btn');

                    // Réinitialiser les messages
                    loadingDiv.style.display = 'block';
                    errorDiv.style.display = 'none';
                    successDiv.style.display = 'none';
                    submitBtn.disabled = true;

                    // Récupérer les données du formulaire
                    const formData = new FormData(this);

                    // Envoyer la requête AJAX
                    console.log('Form action:', this.action);
                    console.log('FormData contents:');
                    for (let pair of formData.entries()) {
                        console.log(pair[0] + ': ' + pair[1]);
                    }

                    fetch(this.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        console.log('Response ok:', response.ok);
                        console.log('Response headers:', [...response.headers.entries()]);

                        // Get response as text first to see what we're getting
                        return response.text().then(text => {
                            console.log('Raw response text:', text);
                            try {
                                const data = JSON.parse(text);
                                console.log('Parsed JSON:', data);
                                return data;
                            } catch (e) {
                                console.error('JSON parse error:', e);
                                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                            }
                        });
                    })
                    .then(data => {
                        loadingDiv.style.display = 'none';
                        submitBtn.disabled = false;

                        if (data.status === 'success') {
                            successDiv.textContent = data.message;
                            successDiv.style.display = 'block';
                            this.reset();

                            // Masquer le message après 5 secondes
                            setTimeout(() => {
                                successDiv.style.display = 'none';
                            }, 5000);
                        } else {
                            errorDiv.textContent = data.message || 'Une erreur est survenue';
                            errorDiv.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Full error object:', error);
                        console.error('Error message:', error.message);
                        console.error('Error stack:', error.stack);
                        loadingDiv.style.display = 'none';
                        submitBtn.disabled = false;
                        errorDiv.textContent = 'Erreur de connexion. Veuillez réessayer. Consultez la console pour plus de détails.';
                        errorDiv.style.display = 'block';
                    });
                });
            }
        });
    </script>
</body>
</html>
