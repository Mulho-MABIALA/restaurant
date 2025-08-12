<?php
require_once '../config.php';
session_start();

// Sécurité : accès réservé aux utilisateurs connectés
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Filtrage poste
$poste = isset($_GET['poste']) ? $_GET['poste'] : 'tous';
$posteFilterSQL = ($poste !== 'tous' && in_array($poste, ['grill','froid','dessert'])) ? "AND cd.poste = :poste" : "";

try {
    // Requête SQL corrigée avec gestion d'erreurs
    $sql = "SELECT 
                cd.id, cd.nom_plat, cd.temps_prevu, cd.prepare, cd.poste, 
                c.id AS commande_id, c.date_commande
            FROM commande_details cd
            JOIN commandes c ON cd.commande_id = c.id
            WHERE cd.prepare = 0
            $posteFilterSQL
            ORDER BY c.date_commande ASC, cd.poste ASC";

    $stmt = $conn->prepare($sql);
    if ($poste !== 'tous' && in_array($poste, ['grill','froid','dessert'])) {
        $stmt->bindValue(':poste', $poste);
    }
    $stmt->execute();
    $plats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques
    $sqlStats = "SELECT 
                    COUNT(*) as total_plats,
                    SUM(CASE WHEN cd.prepare = 1 THEN 1 ELSE 0 END) as plats_prepares,
                    COUNT(DISTINCT c.id) as total_commandes
                 FROM commande_details cd
                 JOIN commandes c ON cd.commande_id = c.id
                 WHERE DATE(c.date_commande) = CURDATE()";
    
    $stmtStats = $conn->prepare($sqlStats);
    $stmtStats->execute();
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur SQL dans cuisine.php: " . $e->getMessage());
    $plats = [];
    $stats = ['total_plats' => 0, 'plats_prepares' => 0, 'total_commandes' => 0];
}

// Fonction format lisible - CORRIGÉE
function tempsEcoule($datetime) {
    // Vérification et nettoyage de la valeur datetime
    if (empty($datetime) || $datetime === null) {
        return "Temps inconnu";
    }
    
    // Conversion sécurisée en timestamp
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return "Temps inconnu";
    }
    
    $diff = time() - $timestamp;
    
    if ($diff < 0) {
        return "Future"; // Commande dans le futur
    } elseif ($diff < 60) {
        return "il y a " . $diff . " seconde" . ($diff > 1 ? "s" : "");
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "il y a " . $minutes . " minute" . ($minutes > 1 ? "s" : "");
    } elseif ($diff < 86400) {
        $heures = floor($diff / 3600);
        return "il y a " . $heures . " heure" . ($heures > 1 ? "s" : "");
    } else {
        $jours = floor($diff / 86400);
        return "il y a " . $jours . " jour" . ($jours > 1 ? "s" : "");
    }
}

// Fonction pour calculer la priorité
function getPriorite($dateCommande, $tempsPrevu) {
    if (empty($dateCommande)) return 'normale';
    
    $timestamp = strtotime($dateCommande);
    if ($timestamp === false) return 'normale';
    
    $diffMin = floor((time() - $timestamp) / 60);
    
    if ($diffMin > ($tempsPrevu * 1.5)) {
        return 'critique'; // Rouge
    } elseif ($diffMin > $tempsPrevu) {
        return 'urgent'; // Orange
    } else {
        return 'normale'; // Vert
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>🧑‍🍳 Écran Cuisine - Suivi de Production</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .critique { 
            background: linear-gradient(45deg, #fee2e2, #fecaca) !important;
            border-left: 5px solid #ef4444;
        }
        .urgent { 
            background: linear-gradient(45deg, #fed7aa, #fdba74) !important;
            border-left: 5px solid #f97316;
        }
        .normale { 
            background: linear-gradient(45deg, #dcfce7, #bbf7d0) !important;
            border-left: 5px solid #22c55e;
        }
        .stats-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }
        .tab-active {
            background: linear-gradient(145deg, #4CAF50, #45a049);
            color: white;
            transform: translateY(-2px);
        }
        .notification {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
  
<body class="font-sans">

    <!-- Header avec statistiques -->
    <div class="card mx-6 mt-6 mb-4 p-6 rounded-xl shadow-lg">
        <h1 class="text-3xl font-bold mb-4 text-center text-gray-800">
            🧑‍🍳 Écran Cuisine - Suivi de Production
        </h1>
        
        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="stats-card p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-blue-600"><?= $stats['total_commandes'] ?></div>
                <div class="text-sm text-gray-600">Commandes aujourd'hui</div>
            </div>
            <div class="stats-card p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-orange-600"><?= count($plats) ?></div>
                <div class="text-sm text-gray-600">En cours de préparation</div>
            </div>
            <div class="stats-card p-4 rounded-lg text-center">
                <div class="text-2xl font-bold text-green-600"><?= $stats['plats_prepares'] ?></div>
                <div class="text-sm text-gray-600">Plats préparés</div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="flex flex-wrap justify-center gap-3">
            <a href="cuisine.php?poste=tous" 
               class="px-4 py-2 rounded-full font-semibold transition-all duration-300 hover:shadow-lg <?= $poste === 'tous' ? 'tab-active' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                Tous
            </a>
            <a href="cuisine.php?poste=grill" 
               class="px-4 py-2 rounded-full font-semibold transition-all duration-300 hover:shadow-lg <?= $poste === 'grill' ? 'tab-active' : 'bg-orange-200 text-orange-700 hover:bg-orange-300' ?>">
                🔥 Grill
            </a>
            <a href="cuisine.php?poste=froid" 
               class="px-4 py-2 rounded-full font-semibold transition-all duration-300 hover:shadow-lg <?= $poste === 'froid' ? 'tab-active' : 'bg-blue-200 text-blue-700 hover:bg-blue-300' ?>">
                ❄️ Froid
            </a>
            <a href="cuisine.php?poste=dessert" 
               class="px-4 py-2 rounded-full font-semibold transition-all duration-300 hover:shadow-lg <?= $poste === 'dessert' ? 'tab-active' : 'bg-purple-200 text-purple-700 hover:bg-purple-300' ?>">
                🍰 Dessert
            </a>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="mx-6 mb-6">
        <?php if (empty($plats)): ?>
            <div class="card p-8 rounded-xl shadow-lg text-center">
                <div class="text-6xl mb-4">🎉</div>
                <p class="text-gray-600 text-xl">Aucun plat en attente de préparation.</p>
                <p class="text-gray-500 text-sm mt-2">Tous les plats sont préparés !</p>
            </div>
        <?php else: ?>
            <!-- Vue carte pour mobile -->
            <div class="block md:hidden space-y-4">
                <?php foreach ($plats as $plat):
                    $diffMin = !empty($plat['date_commande']) ? floor((time() - strtotime($plat['date_commande'])) / 60) : 0;
                    $elapsedLabel = tempsEcoule($plat['date_commande']);
                    $priorite = getPriorite($plat['date_commande'], $plat['temps_prevu']);
                ?>
                <div class="card p-4 rounded-xl shadow-lg <?= $priorite ?> transition-all duration-300 hover:shadow-xl">
                    <div class="flex justify-between items-start mb-3">
                        <div class="text-lg font-bold text-gray-800">#<?= (int)$plat['commande_id'] ?></div>
                        <div class="text-sm bg-gray-100 px-2 py-1 rounded-full">
                            <?= ucfirst(htmlspecialchars($plat['poste'])) ?>
                        </div>
                    </div>
                    <div class="text-xl font-semibold mb-2"><?= htmlspecialchars($plat['nom_plat']) ?></div>
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-sm text-gray-600">Temps prévu: <?= (int) $plat['temps_prevu'] ?> min</span>
                        <span class="text-sm font-mono <?= $priorite === 'critique' ? 'text-red-700 notification' : 'text-gray-700' ?>">
                            <?= $elapsedLabel ?>
                        </span>
                    </div>
                    <form method="POST" action="marquer_prepare.php" onsubmit="return confirm('Marquer ce plat comme préparé ?');" class="w-full">
                        <input type="hidden" name="id_detail" value="<?= $plat['id'] ?>" />
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold transition-colors duration-300">
                            ✔️ Marquer comme préparé
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Vue tableau pour desktop -->
            <div class="hidden md:block card rounded-xl shadow-lg overflow-hidden">
                <table class="min-w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-4 px-6 text-left font-semibold text-gray-700">Commande</th>
                            <th class="py-4 px-6 text-left font-semibold text-gray-700">Plat</th>
                            <th class="py-4 px-6 text-left font-semibold text-gray-700">Poste</th>
                            <th class="py-4 px-6 text-center font-semibold text-gray-700">Temps prévu</th>
                            <th class="py-4 px-6 text-center font-semibold text-gray-700">Temps écoulé</th>
                            <th class="py-4 px-6 text-center font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plats as $plat):
                            $diffMin = !empty($plat['date_commande']) ? floor((time() - strtotime($plat['date_commande'])) / 60) : 0;
                            $elapsedLabel = tempsEcoule($plat['date_commande']);
                            $priorite = getPriorite($plat['date_commande'], $plat['temps_prevu']);
                        ?>
                        <tr class="<?= $priorite ?> border-b border-gray-200 hover:shadow-lg transition-all duration-300">
                            <td class="py-4 px-6 font-bold text-lg">#<?= (int)$plat['commande_id'] ?></td>
                            <td class="py-4 px-6 font-semibold"><?= htmlspecialchars($plat['nom_plat']) ?></td>
                            <td class="py-4 px-6">
                                <span class="px-3 py-1 rounded-full text-sm font-medium
                                    <?= $plat['poste'] === 'grill' ? 'bg-orange-200 text-orange-800' : 
                                        ($plat['poste'] === 'froid' ? 'bg-blue-200 text-blue-800' : 'bg-purple-200 text-purple-800') ?>">
                                    <?= ucfirst(htmlspecialchars($plat['poste'])) ?>
                                </span>
                            </td>
                            <td class="py-4 px-6 text-center font-semibold"><?= (int) $plat['temps_prevu'] ?> min</td>
                            <td class="py-4 px-6 text-center font-mono <?= $priorite === 'critique' ? 'text-red-700 font-bold notification' : 'text-gray-700' ?>">
                                <?= $elapsedLabel ?>
                            </td>
                            <td class="py-4 px-6 text-center">
                                <form method="POST" action="marquer_prepare.php" onsubmit="return confirm('Marquer ce plat comme préparé ?');">
                                    <input type="hidden" name="id_detail" value="<?= $plat['id'] ?>" />
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold transition-colors duration-300 hover:shadow-lg">
                                        ✔️ Préparé
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Notification sonore pour les commandes critiques -->
    <?php 
    $commandesCritiques = array_filter($plats, function($plat) {
        return getPriorite($plat['date_commande'], $plat['temps_prevu']) === 'critique';
    });
    if (!empty($commandesCritiques)): 
    ?>
    <audio id="alertSound" preload="auto">
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvWkbBjiR2O7Icy0EJHXK7+ONQQR" type="audio/wav">
    </audio>
    <script>
        // Son d'alerte pour les commandes critiques (discret)
        document.addEventListener('DOMContentLoaded', function() {
            const alertSound = document.getElementById('alertSound');
            if (alertSound) {
                alertSound.volume = 0.3;
                alertSound.play().catch(e => console.log('Autoplay bloqué'));
            }
        });
    </script>
    <?php endif; ?>

    <script>
        // Auto refresh toutes les 30 secondes
        let refreshTimer = setTimeout(() => {
            window.location.reload();
        }, 30000);

        // Affichage du temps de refresh
        let countdown = 30;
        const countdownElement = document.createElement('div');
        countdownElement.className = 'fixed bottom-4 right-4 bg-gray-800 text-white px-3 py-2 rounded-lg text-sm';
        countdownElement.innerHTML = `Actualisation dans <span class="font-bold">${countdown}</span>s`;
        document.body.appendChild(countdownElement);

        const countdownTimer = setInterval(() => {
            countdown--;
            countdownElement.innerHTML = `Actualisation dans <span class="font-bold">${countdown}</span>s`;
            if (countdown <= 0) {
                clearInterval(countdownTimer);
            }
        }, 1000);

        // Pause/reprendre l'actualisation si l'utilisateur interagit
        let userActive = false;
        document.addEventListener('click', function() {
            if (!userActive) {
                clearTimeout(refreshTimer);
                clearInterval(countdownTimer);
                countdownElement.innerHTML = '<span class="text-yellow-400">⏸️ Actualisation en pause</span>';
                userActive = true;
                
                // Reprendre après 2 minutes d'inactivité
                setTimeout(() => {
                    if (userActive) {
                        window.location.reload();
                    }
                }, 120000);
            }
        });

        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'r':
                case 'R':
                    if (e.ctrlKey) return; // Laisser Ctrl+R normal
                    window.location.reload();
                    break;
                case '1':
                    window.location.href = 'cuisine.php?poste=tous';
                    break;
                case '2':
                    window.location.href = 'cuisine.php?poste=grill';
                    break;
                case '3':
                    window.location.href = 'cuisine.php?poste=froid';
                    break;
                case '4':
                    window.location.href = 'cuisine.php?poste=dessert';
                    break;
            }
        });
    </script>
</body>
</html>