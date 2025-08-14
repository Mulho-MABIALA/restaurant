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
            --events-primary: #3b82f6;
            --events-primary-dark: #2563eb;
            --events-secondary: #10b981;
            --events-accent: #f59e0b;
            --events-danger: #ef4444;
            --events-gray-50: #f9fafb;
            --events-gray-100: #f3f4f6;
            --events-gray-200: #e5e7eb;
            --events-gray-300: #d1d5db;
            --events-gray-500: #6b7280;
            --events-gray-600: #4b5563;
            --events-gray-700: #374151;
            --events-gray-800: #1f2937;
            --events-gray-900: #111827;
            --events-white: #ffffff;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--events-gray-50);
            color: var(--events-gray-800);
            line-height: 1.6;
        }

        /* Header Section */
        .events-header {
            background: linear-gradient(135deg, var(--events-primary) 0%, var(--events-primary-dark) 100%);
            color: var(--events-white);
            padding: 4rem 0 2rem;
            text-align: center;
        }

        .events-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .events-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Filters */
        .events-filters {
            background: var(--events-white);
            padding: 2rem 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
            border-radius: 25px;
            background: var(--events-white);
            color: var(--events-gray-600);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .events-filter-tab:hover,
        .events-filter-tab.events-active {
            background: var(--events-primary);
            border-color: var(--events-primary);
            color: var(--events-white);
            text-decoration: none;
        }

        /* Main Content */
        .events-main {
            padding: 3rem 0;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
        }

        .events-card {
            background: var(--events-white);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--events-gray-100);
        }

        .events-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .events-image {
            height: 200px;
            position: relative;
            overflow: hidden;
        }

        .events-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .events-card:hover .events-image img {
            transform: scale(1.05);
        }

        .events-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--events-primary), var(--events-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--events-white);
        }

        .events-date-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--events-white);
            border-radius: 8px;
            padding: 0.5rem;
            text-align: center;
            min-width: 60px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .events-date-day {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--events-gray-900);
            line-height: 1;
        }

        .events-date-month {
            font-size: 0.75rem;
            color: var(--events-gray-500);
            text-transform: uppercase;
        }

        .events-soon-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--events-accent);
            color: var(--events-white);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .events-content {
            padding: 1.5rem;
        }

        .events-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--events-gray-900);
        }

        .events-meta {
            margin-bottom: 1rem;
        }

        .events-meta-item {
            display: flex;
            align-items: center;
            color: var(--events-gray-600);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .events-meta-item i {
            width: 16px;
            margin-right: 0.5rem;
            color: var(--events-primary);
        }

        .events-description {
            color: var(--events-gray-600);
            margin-bottom: 1.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .events-actions {
            display: flex;
            gap: 0.75rem;
        }

        .events-btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .events-btn-primary {
            background: var(--events-primary);
            color: var(--events-white);
        }

        .events-btn-primary:hover {
            background: var(--events-primary-dark);
            color: var(--events-white);
        }

        .events-btn-secondary {
            background: var(--events-secondary);
            color: var(--events-white);
        }

        .events-btn-secondary:hover {
            background: #059669;
            color: var(--events-white);
        }

        /* Empty State */
        .events-empty {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--events-white);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
        }

        .events-empty i {
            font-size: 3rem;
            color: var(--events-gray-300);
            margin-bottom: 1rem;
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
            width: 2rem;
            height: 2rem;
            border: 3px solid var(--events-gray-200);
            border-top: 3px solid var(--events-primary);
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
            background: var(--events-primary);
            color: var(--events-white);
        }

        .events-modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .events-title {
                font-size: 2rem;
            }
            
            .events-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .events-actions {
                flex-direction: column;
            }
            
            .events-filter-tabs {
                gap: 0.5rem;
            }
            
            .events-filter-tab {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }

        .events-fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .events-hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    
    <!-- Header -->
    <header class="events-header">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8" data-aos="fade-up">
                    <i class="fas fa-calendar-star fa-3x mb-3"></i>
                    <h1 class="events-title">Événements à venir</h1>
                    <p class="events-subtitle">
                        Découvrez nos prochains événements et réservez votre place
                    </p>
                </div>
            </div>
        </div>
    </header>

    <!-- Filters -->
    <section class="events-filters">
        <div class="container">
            <div class="events-filter-tabs" data-aos="fade-up" data-aos-delay="200">
                <a href="#" class="events-filter-tab events-active" data-filter="all">
                    <i class="fas fa-calendar-alt me-2"></i>Tous
                </a>
                <a href="#" class="events-filter-tab" data-filter="soon">
                    <i class="fas fa-clock me-2"></i>Bientôt
                </a>
                <a href="#" class="events-filter-tab" data-filter="this-month">
                    <i class="fas fa-calendar-month me-2"></i>Ce mois-ci
                </a>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="events-main">
        <div class="container">
            <!-- Loading -->
            <div class="events-loading">
                <div class="events-spinner"></div>
                <p class="text-muted">Chargement...</p>
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
                        ?>
                        <div class="events-card-wrapper events-fade-in" 
                             data-aos="fade-up" 
                             data-aos-delay="<?= 300 + ($index * 100) ?>"
                             data-category="<?= $estBientot ? 'soon' : '' ?> <?= $thisMonth ? 'this-month' : '' ?>">
                            <div class="events-card">
                                <div class="events-image">
                                    <?php if ($evenement['image'] && file_exists("admin/uploads/evenements/" . $evenement['image'])): ?>
                                        <img src="admin/uploads/evenements/<?= htmlspecialchars($evenement['image']) ?>" 
                                             alt="<?= htmlspecialchars($evenement['titre']) ?>">
                                    <?php else: ?>
                                        <div class="events-placeholder">
                                            <i class="fas fa-calendar-alt fa-3x"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="events-date-badge">
                                        <div class="events-date-day">
                                            <?= date('d', strtotime($evenement['date_evenement'])) ?>
                                        </div>
                                        <div class="events-date-month">
                                            <?= date('M', strtotime($evenement['date_evenement'])) ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($estBientot): ?>
                                        <div class="events-soon-badge">
                                            Bientôt
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="events-content">
                                    <h3 class="events-card-title">
                                        <?= htmlspecialchars($evenement['titre']) ?>
                                    </h3>
                                    
                                    <div class="events-meta">
                                        <div class="events-meta-item">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?= formatDateFr($evenement['date_evenement']) ?>
                                        </div>
                                        <div class="events-meta-item">
                                            <i class="fas fa-clock"></i>
                                            <?= date('H:i', strtotime($evenement['heure_evenement'])) ?>
                                        </div>
                                        <div class="events-meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?= htmlspecialchars($evenement['lieu']) ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($evenement['description']): ?>
                                        <p class="events-description">
                                            <?= htmlspecialchars($evenement['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="events-actions">
                                        <button class="events-btn events-btn-primary" onclick="voirPlus(<?= $evenement['id'] ?>)">
                                            <i class="fas fa-eye me-2"></i>Voir plus
                                        </button>
                                        <button class="events-btn events-btn-secondary" onclick="reserver(<?= $evenement['id'] ?>)">
                                            <i class="fas fa-ticket-alt me-2"></i>Réserver
                                        </button>
                                    </div>
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
                <div class="modal-header" style="background: var(--events-secondary);">
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
            duration: 600,
            easing: 'ease-out',
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
    </script>
</body>
</html>