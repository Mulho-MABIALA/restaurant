<?php
session_start();
require_once '../config.php';
require_once './permissions.php';

// Vérifie l'accès admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Vérifier que admin_id existe
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Vérifier les permissions pour la cuisine
requireAccess($conn, $_SESSION['admin_id'], 'commandes');

// Récupérer les infos de l'admin
$stmt_admin = $conn->prepare("SELECT username, email FROM admin WHERE id = ?");
$stmt_admin->execute([$_SESSION['admin_id']]);
$admin_info = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$admin_name = $admin_info['username'] ?? $_SESSION['admin_username'] ?? 'Admin';

// Fonction helper pour échapper les valeurs
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ===== GESTION AJAX POUR RÉCUPÉRER LES COMMANDES EN ATTENTE =====
if (isset($_POST['action']) && $_POST['action'] === 'get_commandes_cuisine') {
    header('Content-Type: application/json');

    try {
        // Récupérer les commandes "En cours" et "En préparation"
        $stmt = $conn->prepare("
            SELECT c.*,
                   CASE
                       WHEN c.type_commande = 'manuelle' THEN 'Manuelle'
                       ELSE 'Client'
                   END as origine,
                   TIMESTAMPDIFF(MINUTE, c.created_at, NOW()) as temps_ecoule
            FROM commandes c
            WHERE c.statut IN ('En cours', 'En préparation')
            ORDER BY c.created_at ASC
        ");
        $stmt->execute();
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pour chaque commande, récupérer les détails
        foreach ($commandes as &$commande) {
            $stmt_details = $conn->prepare("
                SELECT nom_plat, quantite, prix
                FROM commande_details
                WHERE commande_id = ?
            ");
            $stmt_details->execute([$commande['id']]);
            $commande['details'] = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'commandes' => $commandes
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ===== GESTION AJAX POUR DÉMARRER LA PRÉPARATION =====
if (isset($_POST['action']) && $_POST['action'] === 'demarrer_preparation' && isset($_POST['id'])) {
    header('Content-Type: application/json');

    try {
        $id = $_POST['id'];

        $stmt = $conn->prepare("UPDATE commandes SET statut = 'En préparation' WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Préparation démarrée'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors du démarrage'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ===== GESTION AJAX POUR MARQUER COMME PRÊT =====
if (isset($_POST['action']) && $_POST['action'] === 'marquer_pret' && isset($_POST['id'])) {
    header('Content-Type: application/json');

    try {
        $id = $_POST['id'];

        // Marquer la commande comme "Prête"
        $stmt = $conn->prepare("UPDATE commandes SET statut = 'Prêt', vu_admin = 0 WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            // Créer une notification pour l'admin/serveur
            $stmt_notif = $conn->prepare("
                INSERT INTO notifications (message, type, date, vue)
                VALUES (?, 'success', NOW(), 0)
            ");
            $stmt_notif->execute(["Commande #$id est prête pour le service"]);

            echo json_encode([
                'success' => true,
                'message' => 'Commande marquée comme prête'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ===== GESTION AJAX POUR ANNULER UNE COMMANDE =====
if (isset($_POST['action']) && $_POST['action'] === 'annuler_commande' && isset($_POST['id'])) {
    header('Content-Type: application/json');

    try {
        $id = $_POST['id'];
        $raison = $_POST['raison'] ?? 'Annulée par la cuisine';

        $stmt = $conn->prepare("UPDATE commandes SET statut = 'Annulée' WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            // Notification
            $stmt_notif = $conn->prepare("
                INSERT INTO notifications (message, type, date, vue)
                VALUES (?, 'warning', NOW(), 0)
            ");
            $stmt_notif->execute(["Commande #$id annulée: $raison"]);

            echo json_encode([
                'success' => true,
                'message' => 'Commande annulée'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Fonction helper pour échapper les valeurs
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuisine - Gestion des commandes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .cuisine-header {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .cuisine-header h1 {
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .cuisine-header .stats {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }

        .stat-box {
            padding: 10px 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-box.attente {
            background: #fff3cd;
            color: #856404;
        }

        .stat-box.preparation {
            background: #d1ecf1;
            color: #0c5460;
        }

        .commandes-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .commande-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }

        .commande-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .commande-card.en-cours {
            border-left: 5px solid var(--warning-color);
        }

        .commande-card.en-preparation {
            border-left: 5px solid var(--info-color);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            50% { box-shadow: 0 5px 25px rgba(23, 162, 184, 0.3); }
        }

        .commande-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .commande-numero {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }

        .commande-temps {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .commande-temps.urgent {
            background: #ffc107;
            color: #856404;
            font-weight: bold;
        }

        .commande-info {
            margin-bottom: 15px;
        }

        .commande-info .info-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 0.95rem;
        }

        .commande-info .label {
            color: #666;
        }

        .commande-info .value {
            font-weight: 600;
            color: #333;
        }

        .plats-list {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .plat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .plat-item:last-child {
            border-bottom: none;
        }

        .plat-nom {
            font-weight: 500;
            color: #333;
        }

        .plat-quantite {
            background: #007bff;
            color: white;
            padding: 2px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
        }

        .commande-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-action {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-demarrer {
            background: var(--info-color);
            color: white;
        }

        .btn-demarrer:hover {
            background: #138496;
            transform: scale(1.05);
        }

        .btn-pret {
            background: var(--primary-color);
            color: white;
        }

        .btn-pret:hover {
            background: #218838;
            transform: scale(1.05);
        }

        .btn-annuler {
            background: var(--danger-color);
            color: white;
            flex: 0.5;
        }

        .btn-annuler:hover {
            background: #c82333;
        }

        .badge-origine {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .badge-origine.manuelle {
            background: #ffc107;
            color: #856404;
        }

        .badge-origine.client {
            background: #17a2b8;
            color: white;
        }

        .empty-state {
            background: white;
            padding: 60px 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #666;
        }

        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 10px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
        }

        .refresh-indicator.active .spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .notification-badge {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary-color);
            color: white;
            padding: 15px 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            display: none;
            z-index: 1001;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateX(-50%) translateY(-100%);
            }
            to {
                transform: translateX(-50%) translateY(0);
            }
        }

        .notification-badge.show {
            display: block;
        }

        .btn-retour {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-retour:hover {
            background: #667eea;
            color: white;
            transform: translateX(-5px);
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="cuisine-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="fas fa-utensils"></i>
                    Gestion Cuisine
                </h1>
                <a href="dashboard.php" class="btn-retour">
                    <i class="fas fa-arrow-left"></i>
                    Retour au Dashboard
                </a>
            </div>
            <div class="stats">
                <div class="stat-box attente">
                    <div><strong id="stat-attente">0</strong></div>
                    <small>En attente</small>
                </div>
                <div class="stat-box preparation">
                    <div><strong id="stat-preparation">0</strong></div>
                    <small>En préparation</small>
                </div>
            </div>
        </div>

        <!-- Indicateur de rafraîchissement -->
        <div class="refresh-indicator" id="refreshIndicator">
            <i class="fas fa-sync-alt spinner"></i>
            <span>Mise à jour automatique</span>
        </div>

        <!-- Notification -->
        <div class="notification-badge" id="notificationBadge"></div>

        <!-- Container des commandes -->
        <div class="commandes-container" id="commandesContainer">
            <div class="empty-state">
                <i class="fas fa-clock"></i>
                <h3>Chargement des commandes...</h3>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuration
        const AUTO_REFRESH_INTERVAL = 5000; // 5 secondes
        let refreshTimer;
        let nouvellesCommandesAudio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuFzvLZiTYIG2m98OScTgwOUKjo77RgGwU7k9n0zHkpBSh+zPDekD8IElyx6OyrVBUIRp3e8r1rHwUrhc/y2ogzBx1qwPDlm0wLDlOq6e+yXhoEOpPY88x3KAUpfs/v3o8+BxJbr+frrVMUB0ae3/O9aB0FLoXP8tuIMQcdbMPz5ppKCg5TqunwsVsaBDyU2fPNdSYEK4HQ8d+OOwYSXLLo7K1SFAdGoN/zv2YbBCyEz/PciC4HH23E9OaYSAkNVKzq8bBZGQQ8ldv0znMjBCuB0fLgjDoFEl2z6e2uUBIHSKHh9L9kGQMrhNDz3YgrBiBuxfTnlkYIDVWs6/KvVxgEPJXc9c9xIQMrgtPz4Ys4BRJftOrur04QBkii4/TAYhYDK4XR9N6HJwYgb8X16JRDBgxWre70rlUWAz2W3vbRbx0CK4PU9eGJNAQSYLXr8LFNDQZJo+T1w2AUAy2G0/feh/IAIHDHzuyYSQsLV67w97RRFgM+l+H4025hBSuF1fXii/AEFE+56/OyTAkFSabm9sVhMQMuhNL54Yf5ACFxx+HvmkYLCliy8vq1TxMCPpjk+tNsHQEric3z5I/vBBJPuu/1tEoFBUqn6PjIYiwCLoTS+eCI8wAjcsrj9ZZCKQ0=');

        // Fonction pour afficher une notification
        function showNotification(message, type = 'success') {
            const badge = document.getElementById('notificationBadge');
            badge.textContent = message;
            badge.className = 'notification-badge show';
            badge.style.background = type === 'success' ? '#28a745' : '#ffc107';

            setTimeout(() => {
                badge.classList.remove('show');
            }, 3000);
        }

        // Fonction pour charger les commandes
        async function chargerCommandes(silent = false) {
            if (!silent) {
                document.getElementById('refreshIndicator').classList.add('active');
            }

            try {
                const formData = new FormData();
                formData.append('action', 'get_commandes_cuisine');

                const response = await fetch('cuisine.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    afficherCommandes(data.commandes);

                    // Vérifier s'il y a de nouvelles commandes
                    const anciensIds = window.commandesIds || [];
                    const nouveauxIds = data.commandes.map(c => c.id);
                    const nouvellesCommandes = nouveauxIds.filter(id => !anciensIds.includes(id));

                    if (nouvellesCommandes.length > 0 && anciensIds.length > 0) {
                        showNotification(`${nouvellesCommandes.length} nouvelle(s) commande(s) !`, 'warning');
                        // Jouer un son
                        try {
                            nouvellesCommandesAudio.play();
                        } catch (e) {
                            console.log('Impossible de jouer le son:', e);
                        }
                    }

                    window.commandesIds = nouveauxIds;
                } else {
                    console.error('Erreur:', data.message);
                }
            } catch (error) {
                console.error('Erreur de chargement:', error);
            } finally {
                document.getElementById('refreshIndicator').classList.remove('active');
            }
        }

        // Fonction pour afficher les commandes
        function afficherCommandes(commandes) {
            const container = document.getElementById('commandesContainer');

            // Mettre à jour les statistiques
            const enAttente = commandes.filter(c => c.statut === 'En cours').length;
            const enPreparation = commandes.filter(c => c.statut === 'En préparation').length;

            document.getElementById('stat-attente').textContent = enAttente;
            document.getElementById('stat-preparation').textContent = enPreparation;

            if (commandes.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>Aucune commande en attente</h3>
                        <p>Toutes les commandes ont été traitées !</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = commandes.map(commande => {
                const tempsEcoule = parseInt(commande.temps_ecoule);
                const isUrgent = tempsEcoule > 15;
                const statutClass = commande.statut === 'En préparation' ? 'en-preparation' : 'en-cours';

                return `
                    <div class="commande-card ${statutClass}" data-id="${commande.id}">
                        <span class="badge-origine ${commande.origine.toLowerCase()}">${commande.origine}</span>

                        <div class="commande-header">
                            <div class="commande-numero">#${String(commande.id).padStart(4, '0')}</div>
                            <div class="commande-temps ${isUrgent ? 'urgent' : ''}">
                                <i class="fas fa-clock"></i> ${tempsEcoule} min
                            </div>
                        </div>

                        <div class="commande-info">
                            <div class="info-row">
                                <span class="label"><i class="fas fa-user"></i> Client:</span>
                                <span class="value">${escapeHtml(commande.nom_client)}</span>
                            </div>
                            ${commande.num_table ? `
                                <div class="info-row">
                                    <span class="label"><i class="fas fa-chair"></i> Table:</span>
                                    <span class="value">${escapeHtml(commande.num_table)}</span>
                                </div>
                            ` : ''}
                            <div class="info-row">
                                <span class="label"><i class="fas fa-receipt"></i> Total:</span>
                                <span class="value">${parseFloat(commande.total).toLocaleString()} FCFA</span>
                            </div>
                            <div class="info-row">
                                <span class="label"><i class="fas fa-info-circle"></i> Statut:</span>
                                <span class="value">${commande.statut}</span>
                            </div>
                        </div>

                        <div class="plats-list">
                            <strong><i class="fas fa-list"></i> Articles:</strong>
                            ${commande.details.map(detail => `
                                <div class="plat-item">
                                    <span class="plat-nom">${escapeHtml(detail.nom_plat)}</span>
                                    <span class="plat-quantite">x${detail.quantite}</span>
                                </div>
                            `).join('')}
                        </div>

                        <div class="commande-actions">
                            ${commande.statut === 'En cours' ? `
                                <button class="btn-action btn-demarrer" onclick="demarrerPreparation(${commande.id})">
                                    <i class="fas fa-play"></i> Démarrer
                                </button>
                            ` : ''}
                            ${commande.statut === 'En préparation' ? `
                                <button class="btn-action btn-pret" onclick="marquerPret(${commande.id})">
                                    <i class="fas fa-check"></i> Prêt
                                </button>
                            ` : ''}
                            <button class="btn-action btn-annuler" onclick="annulerCommande(${commande.id})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Fonction pour démarrer la préparation
        async function demarrerPreparation(id) {
            try {
                const formData = new FormData();
                formData.append('action', 'demarrer_preparation');
                formData.append('id', id);

                const response = await fetch('cuisine.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Préparation démarrée !', 'success');
                    await chargerCommandes(true);
                } else {
                    alert('Erreur: ' + data.message);
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur lors du démarrage de la préparation');
            }
        }

        // Fonction pour marquer comme prêt
        async function marquerPret(id) {
            try {
                const formData = new FormData();
                formData.append('action', 'marquer_pret');
                formData.append('id', id);

                const response = await fetch('cuisine.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Commande prête pour le service !', 'success');
                    await chargerCommandes(true);
                } else {
                    alert('Erreur: ' + data.message);
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur lors du marquage');
            }
        }

        // Fonction pour annuler une commande
        async function annulerCommande(id) {
            const raison = prompt('Raison de l\'annulation:');
            if (!raison) return;

            try {
                const formData = new FormData();
                formData.append('action', 'annuler_commande');
                formData.append('id', id);
                formData.append('raison', raison);

                const response = await fetch('cuisine.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Commande annulée', 'warning');
                    await chargerCommandes(true);
                } else {
                    alert('Erreur: ' + data.message);
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Erreur lors de l\'annulation');
            }
        }

        // Fonction utilitaire pour échapper le HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        // Démarrage automatique
        window.addEventListener('DOMContentLoaded', () => {
            console.log('Cuisine.php - Initialisation...');

            // Charger immédiatement
            chargerCommandes();

            // Rafraîchissement automatique
            refreshTimer = setInterval(() => {
                chargerCommandes(true);
            }, AUTO_REFRESH_INTERVAL);
        });

        // Nettoyer le timer lors de la fermeture
        window.addEventListener('beforeunload', () => {
            if (refreshTimer) {
                clearInterval(refreshTimer);
            }
        });
    </script>
</body>
</html>
