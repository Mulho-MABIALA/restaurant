<?php
require_once 'config.php';

// Récupérer uniquement les événements à venir, triés par date croissante
$stmt = $conn->prepare("
    SELECT * FROM evenements 
    WHERE date_evenement >= CURDATE() 
    ORDER BY date_evenement ASC, heure_evenement ASC
");
$stmt->execute();
$evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour formater la date en français
function formatDateFr($date) {
    $mois = [
        1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Août',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'
    ];
    
    $timestamp = strtotime($date);
    $jour = date('d', $timestamp);
    $moisNum = (int)date('m', $timestamp);
    $annee = date('Y', $timestamp);
    
    return $jour . ' ' . $mois[$moisNum] . ' ' . $annee;
}

// Fonction pour déterminer si l'événement est bientôt (dans les 7 prochains jours)
function estBientot($date) {
    $aujourd_hui = new DateTime();
    $date_evenement = new DateTime($date);
    $diff = $aujourd_hui->diff($date_evenement);
    
    return $diff->days <= 7 && $diff->invert == 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Événements à venir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --events-primary: #667eea;
            --events-primary-dark: #5a67d8;
            --events-secondary: #f093fb;
            --events-accent: #f6d365;
            --events-danger: #ff6b6b;
            --events-success: #4ecdc4;
            --events-gray-50: #f8fafc;
            --events-gray-100: #f1f5f9;
            --events-gray-200: #e2e8f0;
            --events-gray-300: #cbd5e0;
            --events-gray-500: #64748b;
            --events-gray-600: #475569;
            --events-gray-700: #334155;
            --events-gray-800: #1e293b;
            --events-gray-900: #0f172a;
            --events-white: #ffffff;
        }

        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');

        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--events-gray-800);
            line-height: 1.6;
        }

        /* Header Section - Simplifié */
        .events-header {
            background: transparent;
            color: var(--events-white);
            padding: 2rem 0 1rem;
            text-align: center;
        }

        .events-title {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .events-subtitle {
            display: none; /* Caché car on va le mettre en bas */
        }

        /* Filters */
        .events-filters {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            padding: 2rem 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .events-filter-tabs {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .events-filter-tab {
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--events-gray-200);
            border-radius: 50px;
            background: var(--events-white);
            color: var(--events-gray-600);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .events-filter-tab:hover,
        .events-filter-tab.events-active {
            background: linear-gradient(135deg, var(--events-primary), var(--events-primary-dark));
            border-color: var(--events-primary);
            color: var(--events-white);
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        /* Main Content */
        .events-main {
            padding: 3rem 0 2rem;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Circular Event Cards */
        .events-card {
            position: relative;
            width: 280px;
            height: 350px; /* Augmenté pour les boutons */
            margin: 0 auto;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .events-card:hover {
            transform: scale(1.05);
        }

        .events-circle {
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--events-white) 0%, #f8fafc 100%);
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 10px 20px rgba(0, 0, 0, 0.05),
                inset 0 -2px 10px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
            cursor: pointer;
        }

        .events-circle::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, 
                transparent 30%, 
                rgba(255, 255, 255, 0.1) 50%, 
                transparent 70%);
            transform: rotate(45deg);
            transition: all 0.6s ease;
            opacity: 0;
        }

        .events-card:hover .events-circle::before {
            opacity: 1;
            animation: shine 1.5s ease-in-out;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        /* Date Badge Circulaire */
        .events-date-circle {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--events-primary), var(--events-primary-dark));
            color: var(--events-white);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
            z-index: 2;
        }

        .events-date-day {
            font-size: 1.1rem;
            line-height: 1;
        }

        .events-date-month {
            font-size: 0.7rem;
            opacity: 0.9;
        }

        /* Soon Badge */
        .events-soon-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: linear-gradient(135deg, var(--events-accent), #ffd89b);
            color: var(--events-gray-800);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(246, 211, 101, 0.4);
            z-index: 2;
        }

        /* Event Content */
        .events-content {
            z-index: 1;
            position: relative;
            width: 100%;
        }

        .events-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--events-primary), var(--events-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }

        .events-card-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            color: var(--events-gray-900);
            line-height: 1.3;
            max-height: 2.6rem;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .events-meta-circle {
            margin-bottom: 1rem;
        }

        .events-meta-item {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--events-gray-600);
            font-size: 0.8rem;
            margin-bottom: 0.3rem;
        }

        .events-meta-item i {
            width: 14px;
            margin-right: 0.4rem;
            color: var(--events-primary);
        }

        /* Action Buttons - En dehors du cercle */
        .events-actions {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 0.8rem;
            justify-content: center;
            width: 100%;
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .events-card:hover .events-actions {
            opacity: 1;
            transform: translateX(-50%) translateY(5px);
        }

        /* Supprimer l'ancien overlay */
        .events-actions-overlay {
            display: none;
        }

        .events-btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .events-btn-primary {
            background: linear-gradient(135deg, var(--events-primary), var(--events-primary-dark));
            color: var(--events-white);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .events-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: var(--events-white);
        }

        .events-btn-secondary {
            background: linear-gradient(135deg, var(--events-success), #26d0ce);
            color: var(--events-white);
            box-shadow: 0 4px 15px rgba(78, 205, 196, 0.3);
        }

        .events-btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(78, 205, 196, 0.4);
            color: var(--events-white);
        }

        /* Footer avec le texte déplacé */
        .events-footer {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            color: var(--events-white);
            padding: 3rem 0;
            text-align: center;
            margin-top: 4rem;
        }

        .events-footer-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
        }

        .events-footer-text {
            font-size: 1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        .events-empty {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: 0 auto;
        }

        .events-empty i {
            font-size: 4rem;
            background: linear-gradient(135deg, var(--events-gray-300), var(--events-gray-400));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
        }

        .events-empty h3 {
            color: var(--events-gray-900);
            margin-bottom: 0.5rem;
        }

        .events-empty p {
            color: var(--events-gray-600);
        }

        /* Loading */
        .events-loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .events-spinner {
            width: 3rem;
            height: 3rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid var(--events-white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Modal */
        .events-modal-header {
            background: linear-gradient(135deg, var(--events-primary), var(--events-primary-dark));
            color: var(--events-white);
        }

        .events-modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .events-title {
                font-size: 2rem;
            }
            
            .events-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 2rem;
                padding: 0 1rem;
            }
            
            .events-card {
                width: 240px;
                height: 240px;
            }
            
            .events-circle {
                padding: 1.5rem;
            }
            
            .events-icon {
                font-size: 2rem;
            }
            
            .events-card-title {
                font-size: 1rem;
            }
            
            .events-filter-tabs {
                gap: 0.5rem;
                padding: 0 1rem;
            }
            
            .events-filter-tab {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }

        .events-fade-in {
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(30px) scale(0.95); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }

        .events-hidden {
            display: none !important;
        }

        /* Gradient overlay for better text readability */
        .events-circle::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at center, transparent 60%, rgba(0, 0, 0, 0.02) 100%);
            border-radius: 50%;
            pointer-events: none;
        }
    </style>
</head>
<body>
    
    <!-- Header -->
    <header >
           <sction class="events-footer">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8" data-aos="fade-up">
                    <h2 class="events-footer-title">Découvrez nos événements</h2>
                    <p class="events-footer-text">
                        Participez à nos prochains événements et vivez des moments inoubliables. 
                        Réservez votre place dès maintenant !
                    </p>
                </div>
            </div>
        </div>
    </section>

    </header>

    
 
    <!-- Main Content -->
    <section class="events-main">
        <div class="container">
            <!-- Loading -->
            <div class="events-loading">
                <div class="events-spinner"></div>
                <p class="text-white">Chargement...</p>
            </div>

            <?php if (empty($evenements)): ?>
                <!-- Empty State -->
                <div class="events-empty events-fade-in" data-aos="fade-up">
                    <i class="fas fa-calendar-times"></i>
                    <h3>Aucun événement à venir</h3>
                    <p>Il n'y a actuellement aucun événement programmé.<br>
                    Revenez bientôt pour découvrir nos prochaines activités !</p>
                </div>
            <?php else: ?>
                <!-- Events Grid -->
                <div class="events-grid" id="eventsContainer">
                    <?php foreach ($evenements as $index => $evenement): ?>
                        <?php 
                        $estBientot = estBientot($evenement['date_evenement']);
                        $dateObj = new DateTime($evenement['date_evenement']);
                        $thisMonth = $dateObj->format('Y-m') === date('Y-m');
                        
                        // Déterminer l'icône basée sur le titre ou le type d'événement
                        $icon = 'fa-calendar-alt';
                        $titre_lower = strtolower($evenement['titre']);
                        if (strpos($titre_lower, 'concert') !== false || strpos($titre_lower, 'musique') !== false) {
                            $icon = 'fa-music';
                        } elseif (strpos($titre_lower, 'formation') !== false || strpos($titre_lower, 'atelier') !== false) {
                            $icon = 'fa-graduation-cap';
                        } elseif (strpos($titre_lower, 'conférence') !== false) {
                            $icon = 'fa-microphone';
                        } elseif (strpos($titre_lower, 'sport') !== false) {
                            $icon = 'fa-running';
                        } elseif (strpos($titre_lower, 'exposition') !== false) {
                            $icon = 'fa-palette';
                        }
                        ?>
                        <div class="events-card-wrapper events-fade-in" 
                             data-aos="zoom-in" 
                             data-aos-delay="<?= 300 + ($index * 100) ?>"
                             data-category="<?= $estBientot ? 'soon' : '' ?> <?= $thisMonth ? 'this-month' : '' ?>">
                            <div class="events-card">
                                <div class="events-circle" onclick="voirPlus(<?= $evenement['id'] ?>)">
                                    
                                    <div class="events-date-circle">
                                        <div class="events-date-day">
                                            <?= date('d', strtotime($evenement['date_evenement'])) ?>
                                        </div>
                                        <div class="events-date-month">
                                            <?= date('M', strtotime($evenement['date_evenement'])) ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($estBientot): ?>
                                        <div class="events-soon-badge">
                                            <i class="fas fa-bolt me-1"></i>Bientôt
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="events-content">
                                        <div class="events-icon">
                                            <i class="fas <?= $icon ?>"></i>
                                        </div>
                                        
                                        <h3 class="events-card-title">
                                            <?= htmlspecialchars($evenement['titre']) ?>
                                        </h3>
                                        
                                        <div class="events-meta-circle">
                                            <div class="events-meta-item">
                                                <i class="fas fa-clock"></i>
                                                <?= date('H:i', strtotime($evenement['heure_evenement'])) ?>
                                            </div>
                                            <div class="events-meta-item">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars(substr($evenement['lieu'], 0, 20) . (strlen($evenement['lieu']) > 20 ? '...' : '')) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Boutons à l'extérieur du cercle -->
                                <div class="events-actions">
                                    <button class="events-btn events-btn-primary" onclick="voirPlus(<?= $evenement['id'] ?>)">
                                        <i class="fas fa-eye"></i>Voir plus
                                    </button>
                                    <button class="events-btn events-btn-secondary" onclick="reserver(<?= $evenement['id'] ?>)">
                                        <i class="fas fa-ticket-alt"></i>Réserver
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Modal Detail -->
    <div class="modal fade" id="eventDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content events-modal-content">
                <div class="modal-header events-modal-header">
                    <h5 class="modal-title" id="modalEventTitle">
                        <i class="fas fa-calendar-alt me-2"></i>Détails de l'événement
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalEventContent">
                    <!-- Contenu chargé dynamiquement -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="events-btn events-btn-primary" onclick="reserver(currentEventId)">
                        <i class="fas fa-ticket-alt me-2"></i>Réserver
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Reservation -->
    <div class="modal fade" id="reservationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content events-modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--events-success), #26d0ce);">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-ticket-alt me-2"></i>Réservation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Réservation en cours de développement</strong><br>
                        Cette fonctionnalité sera bientôt disponible. 
                        En attendant, vous pouvez nous contacter directement.
                    </div>
                    <div class="text-center">
                        <h5 id="reservationEventTitle"></h5>
                        <p class="text-muted" id="reservationEventDetails"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="events-btn events-btn-primary">
                        <i class="fas fa-phone me-2"></i>Nous contacter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>
        // Initialisation AOS
        AOS.init({
            duration: 800,
            easing: 'ease-out-cubic',
            once: true
        });

        let currentEventId = null;
        const evenements = <?= json_encode($evenements) ?>;

        // Gestion des filtres
        document.querySelectorAll('.events-filter-tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Mise à jour de l'onglet actif
                document.querySelectorAll('.events-filter-tab').forEach(t => t.classList.remove('events-active'));
                this.classList.add('events-active');
                
                // Filtrage
                const filter = this.getAttribute('data-filter');
                filterEvents(filter);
            });
        });

        function filterEvents(filter) {
            const events = document.querySelectorAll('.events-card-wrapper');
            const loading = document.querySelector('.events-loading');
            
            // Afficher le spinner
            loading.style.display = 'block';
            
            // Masquer tous les événements
            events.forEach(event => event.classList.add('events-hidden'));
            
            setTimeout(() => {
                events.forEach(event => {
                    let shouldShow = false;
                    
                    if (filter === 'all') {
                        shouldShow = true;
                    } else {
                        const categories = event.getAttribute('data-category') || '';
                        shouldShow = categories.includes(filter);
                    }
                    
                    if (shouldShow) {
                        event.classList.remove('events-hidden');
                        event.classList.add('events-fade-in');
                    }
                });
                
                loading.style.display = 'none';
            }, 300);
        }

        function voirPlus(eventId) {
            const evenement = evenements.find(e => e.id == eventId);
            if (!evenement) return;
            
            currentEventId = eventId;
            
            const modalTitle = document.getElementById('modalEventTitle');
            const modalContent = document.getElementById('modalEventContent');
            
            modalTitle.innerHTML = `<i class="fas fa-calendar-alt me-2"></i>${evenement.titre}`;
            
            const imageHtml = evenement.image 
                ? `<img src="admin/uploads/evenements/${evenement.image}" class="img-fluid rounded mb-3" alt="${evenement.titre}">`
                : `<div class="text-center mb-3 p-4 bg-light rounded">
                     <i class="fas fa-calendar-alt text-muted" style="font-size: 3rem;"></i>
                   </div>`;
            
            modalContent.innerHTML = `
                ${imageHtml}
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6><i class="fas fa-calendar-alt text-primary me-2"></i>Date</h6>
                        <p>${formatDateFr(evenement.date_evenement)}</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-clock text-primary me-2"></i>Heure</h6>
                        <p>${evenement.heure_evenement}</p>
                    </div>
                </div>
                <div class="mb-3">
                    <h6><i class="fas fa-map-marker-alt text-primary me-2"></i>Lieu</h6>
                    <p>${evenement.lieu}</p>
                </div>
                ${evenement.description ? `
                    <div>
                        <h6><i class="fas fa-info-circle text-primary me-2"></i>Description</h6>
                        <p>${evenement.description}</p>
                    </div>
                ` : ''}
            `;
            
            new bootstrap.Modal(document.getElementById('eventDetailModal')).show();
        }

        function reserver(eventId) {
            const evenement = evenements.find(e => e.id == eventId);
            if (!evenement) return;
            
            // Fermer le modal de détails s'il est ouvert
            const detailModal = bootstrap.Modal.getInstance(document.getElementById('eventDetailModal'));
            if (detailModal) {
                detailModal.hide();
            }
            
            document.getElementById('reservationEventTitle').textContent = evenement.titre;
            document.getElementById('reservationEventDetails').textContent = 
                `${formatDateFr(evenement.date_evenement)} à ${evenement.heure_evenement} - ${evenement.lieu}`;
            
            new bootstrap.Modal(document.getElementById('reservationModal')).show();
        }

        function formatDateFr(dateString) {
            const mois = [
                'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin',
                'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'
            ];
            
            const date = new Date(dateString);
            const jour = date.getDate().toString().padStart(2, '0');
            const moisIndex = date.getMonth();
            const annee = date.getFullYear();
            
            return `${jour} ${mois[moisIndex]} ${annee}`;
        }

        // Auto-refresh
        setInterval(() => {
            fetch('<?= $_SERVER['PHP_SELF'] ?>?ajax=1')
                .then(response => response.text())
                .then(data => {
                    // Logique de mise à jour si nécessaire
                })
                .catch(error => console.log('Erreur:', error));
        }, 300000);

        // Animation supplémentaire au survol des cercles
        document.addEventListener('DOMContentLoaded', function() {
            const circles = document.querySelectorAll('.events-circle');
            
            circles.forEach(circle => {
                circle.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                
                circle.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>