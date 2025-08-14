<?php
// employee_details.php - Page de détails d'un employé
require_once '../config.php';

if (!isset($_GET['id'])) {
    header('Location: admin_gestion.php');
    exit;
}

$employee_id = $_GET['id'];

try {
    $stmt = $conn->prepare("SELECT * FROM vue_employes_complet WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        header('Location: admin_gestion.php');
        exit;
    }
    
    // Récupérer les horaires de la semaine courante
    $start_of_week = date('Y-m-d', strtotime('monday this week'));
    $stmt = $conn->prepare("SELECT * FROM horaires WHERE employe_id = ? AND semaine_debut = ?");
    $stmt->execute([$employee_id, $start_of_week]);
    $horaires = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Récupérer les présences récentes
    $stmt = $conn->prepare("
        SELECT * FROM presences 
        WHERE employe_id = ? 
        ORDER BY date_presence DESC 
        LIMIT 10
    ");
    $stmt->execute([$employee_id]);
    $presences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails - <?php echo htmlspecialchars($employee['prenom'] . ' ' . $employee['nom']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto p-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <button onclick="window.close()" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-arrow-left mr-2"></i>Retour
                </button>
                <div class="flex space-x-2">
                    <button onclick="editEmployee()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-edit mr-2"></i>Modifier
                    </button>
                    <button onclick="generateBadge()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-qrcode mr-2"></i>Badge
                    </button>
                </div>
            </div>
            
            <div class="flex items-center">
                <img src="uploads/photos/<?php echo htmlspecialchars($employee['photo'] ?? 'default-avatar.png'); ?>" 
                     class="w-24 h-24 rounded-full border-4 border-gray-200 object-cover">
                <div class="ml-6">
                    <h1 class="text-3xl font-bold text-gray-900">
                        <?php echo htmlspecialchars($employee['prenom'] . ' ' . $employee['nom']); ?>
                        <?php if ($employee['is_admin']): ?>
                            <i class="fas fa-crown text-yellow-500 ml-2" title="Administrateur"></i>
                        <?php endif; ?>
                    </h1>
                    <div class="flex items-center mt-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                              style="background-color: <?php echo $employee['poste_couleur']; ?>20; color: <?php echo $employee['poste_couleur']; ?>;">
                            <?php echo htmlspecialchars($employee['poste_nom'] ?? 'Non défini'); ?>
                        </span>
                        <span class="ml-3 px-3 py-1 rounded-full text-sm font-medium <?php 
                            echo $employee['statut'] === 'actif' ? 'bg-green-100 text-green-800' : 
                                ($employee['statut'] === 'en_conge' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); 
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $employee['statut'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Informations personnelles -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-user mr-2 text-blue-600"></i>Informations personnelles
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($employee['email']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Téléphone</label>
                            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($employee['telephone'] ?? 'Non renseigné'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date d'embauche</label>
                            <p class="mt-1 text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($employee['date_embauche'])); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Salaire</label>
                            <p class="mt-1 text-sm text-gray-900"><?php echo $employee['salaire'] ? number_format($employee['salaire'], 2, ',', ' ') . ' €' : 'Non défini'; ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Horaires habituels</label>
                            <p class="mt-1 text-sm text-gray-900"><?php echo $employee['heure_debut'] . ' - ' . $employee['heure_fin']; ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ID Employé</label>
                            <p class="mt-1 text-sm text-gray-900">#<?php echo $employee['id']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Horaires de la semaine -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-calendar-week mr-2 text-green-600"></i>Horaires de la semaine
                    </h2>
                    <?php if ($horaires): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php 
                            $jours = [
                                'lundi' => 'Lundi',
                                'mardi' => 'Mardi', 
                                'mercredi' => 'Mercredi',
                                'jeudi' => 'Jeudi',
                                'vendredi' => 'Vendredi',
                                'samedi' => 'Samedi',
                                'dimanche' => 'Dimanche'
                            ];
                            
                            foreach ($jours as $jour => $label): 
                                $debut = $horaires[$jour . '_debut'];
                                $fin = $horaires[$jour . '_fin'];
                            ?>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="font-medium text-gray-700"><?php echo $label; ?></span>
                                    <span class="text-gray-600">
                                        <?php echo ($debut && $fin) ? $debut . ' - ' . $fin : 'Repos'; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4">Aucun horaire planifié pour cette semaine</p>
                    <?php endif; ?>
                </div>

                <!-- Présences récentes -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-clock mr-2 text-purple-600"></i>Présences récentes
                    </h2>
                    <?php if ($presences): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-2 text-sm font-medium text-gray-700">Date</th>
                                        <th class="text-left py-2 text-sm font-medium text-gray-700">Arrivée</th>
                                        <th class="text-left py-2 text-sm font-medium text-gray-700">Départ</th>
                                        <th class="text-left py-2 text-sm font-medium text-gray-700">Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($presences as $presence): ?>
                                        <tr class="border-b border-gray-100">
                                            <td class="py-2 text-sm text-gray-900">
                                                <?php echo date('d/m/Y', strtotime($presence['date_presence'])); ?>
                                            </td>
                                            <td class="py-2 text-sm text-gray-600">
                                                <?php echo $presence['heure_arrivee'] ?? '-'; ?>
                                            </td>
                                            <td class="py-2 text-sm text-gray-600">
                                                <?php echo $presence['heure_depart'] ?? '-'; ?>
                                            </td>
                                            <td class="py-2">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php
                                                    echo $presence['statut'] === 'present' ? 'bg-green-100 text-green-800' :
                                                        ($presence['statut'] === 'retard' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                                ?>">
                                                    <?php echo ucfirst($presence['statut']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4">Aucune présence enregistrée</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- QR Code -->
                <?php if ($employee['qr_code']): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 text-center">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">QR Code</h3>
                        <img src="qrcodes/<?php echo htmlspecialchars($employee['qr_code']); ?>" 
                             class="w-32 h-32 mx-auto border border-gray-200 rounded-lg">
                        <button onclick="downloadQR()" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                            <i class="fas fa-download mr-2"></i>Télécharger
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Actions rapides -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions rapides</h3>
                    <div class="space-y-2">
                        <button onclick="planifierHoraires()" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                            <i class="fas fa-calendar-plus mr-2"></i>Planifier horaires
                        </button>
                        <button onclick="marquerPresence()" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg text-sm">
                            <i class="fas fa-check-circle mr-2"></i>Marquer présent
                        </button>
                        <button onclick="envoyerEmail()" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm">
                            <i class="fas fa-envelope mr-2"></i>Envoyer email
                        </button>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistiques</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Ancienneté</span>
                            <span class="text-sm font-medium text-gray-900">
                                <?php 
                                $anciennete = date_diff(date_create($employee['date_embauche']), date_create('today'));
                                echo $anciennete->y . ' ans ' . $anciennete->m . ' mois';
                                ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Présences ce mois</span>
                            <span class="text-sm font-medium text-gray-900">
                                <?php
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM presences WHERE employe_id = ? AND MONTH(date_presence) = MONTH(CURDATE()) AND YEAR(date_presence) = YEAR(CURDATE()) AND statut = 'present'");
                                $stmt->execute([$employee['id']]);
                                echo $stmt->fetchColumn();
                                ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">Taux de présence</span>
                            <span class="text-sm font-medium text-gray-900">
                                <?php
                                $stmt = $conn->prepare("
                                    SELECT 
                                        (COUNT(CASE WHEN statut = 'present' THEN 1 END) * 100.0 / COUNT(*)) as taux
                                    FROM presences 
                                    WHERE employe_id = ? AND date_presence >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                                ");
                                $stmt->execute([$employee['id']]);
                                $taux = $stmt->fetchColumn();
                                echo $taux ? number_format($taux, 1) . '%' : 'N/A';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editEmployee() {
            window.opener.editEmployee(<?php echo $employee['id']; ?>);
            window.close();
        }

        function generateBadge() {
            window.open('generate_badges.php?id=<?php echo $employee['id']; ?>');
        }

        function downloadQR() {
            const link = document.createElement('a');
            link.download = 'qr_<?php echo $employee['nom']; ?>_<?php echo $employee['prenom']; ?>.png';
            link.href = 'qrcodes/<?php echo $employee['qr_code']; ?>';
            link.click();
        }

        function planifierHoraires() {
            // Ouvrir modal de planification ou rediriger
            alert('Fonctionnalité de planification à implémenter');
        }

        function marquerPresence() {
            if (confirm('Marquer cet employé comme présent aujourd\'hui ?')) {
                fetch('ajax/mark_presence.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        employee_id: <?php echo $employee['id']; ?>,
                        status: 'present'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.success ? 'Présence marquée!' : 'Erreur: ' + data.message);
                    if (data.success) location.reload();
                });
            }
        }

        function envoyerEmail() {
            const email = '<?php echo $employee['email']; ?>';
            window.location.href = `mailto:${email}`;
        }
    </script>
</body>
</html>