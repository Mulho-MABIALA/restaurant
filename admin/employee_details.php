<?php
// Fixed EmployeeManager class - employee_details.php
require_once '../config.php';
require_once 'phpqrcode/qrlib.php';

class EmployeeManager {
    private $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    /**
     * Génère un code numérique unique pour un employé
     */
    private function generateUniqueCode(): string {
        // Trouver le dernier code utilisé
        $stmt = $this->conn->prepare("
            SELECT MAX(CAST(code_numerique AS UNSIGNED)) as max_code 
            FROM employes 
            WHERE code_numerique REGEXP '^[0-9]+$' 
            AND code_numerique IS NOT NULL
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        
        // Si aucun code existe, commencer à 1000000000 (1 milliard)
        // Sinon, prendre le suivant
        $next_code = ($result && $result['max_code']) ? $result['max_code'] + 1 : 1000000000;
        
        return (string)$next_code;
    }
    
    /**
     * Génère les données QR pour un employé
     */
    public function generateQRData(array $employee): string {
        // S'assurer que l'employé a un code numérique
        if (empty($employee['code_numerique'])) {
            $numeric_code = $this->generateUniqueCode();
            
            // Mettre à jour en base
            $stmt = $this->conn->prepare("UPDATE employes SET code_numerique = ? WHERE id = ?");
            $stmt->execute([$numeric_code, $employee['id']]);
            
            $employee['code_numerique'] = $numeric_code;
        }
        
        // Créer les données QR
        $qrData = [
            'type' => 'employee_badge',
            'id' => (int)$employee['id'],
            'code' => $employee['code_numerique'],
            'nom' => $employee['nom'] ?? '',
            'prenom' => $employee['prenom'] ?? '',
            'email' => $employee['email'] ?? '',
            'poste_id' => $employee['poste_id'] ? (int)$employee['poste_id'] : null,
            'timestamp' => time(),
            'version' => '1.1'
        ];
        
        return json_encode($qrData);
    }

    public function getEmployeeById(int $id): ?array {
    $stmt = $this->conn->prepare("
        SELECT e.*, 
               p.nom as poste_nom,
               p.couleur as poste_couleur,
               p.salaire as poste_salaire,
               p.type_contrat,
               p.duree_contrat,
               p.niveau_hierarchique,
               p.competences_requises,
               p.avantages,
               p.code_paie,
               p.categorie_paie,
               p.regime_social,
               p.taux_cotisation,
               p.salaire_min,
               p.salaire_max,
               p.heures_travail,
               p.departement_id,
               ps.nom as poste_superieur_nom,
               d.nom as departement_nom,
               d.description as departement_description
        FROM employes e 
        LEFT JOIN postes p ON e.poste_id = p.id 
        LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
        LEFT JOIN departements d ON p.departement_id = d.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}
    
    /**
     * Récupère les horaires de la semaine pour un employé
     */
    public function getWeeklySchedule(int $employee_id): ?array {
        $start_of_week = date('Y-m-d', strtotime('monday this week'));
        $stmt = $this->conn->prepare("SELECT * FROM horaires WHERE employe_id = ? AND semaine_debut = ?");
        $stmt->execute([$employee_id, $start_of_week]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Récupère les présences récentes depuis la table pointages (comme dans presence.php)
     */
    public function getRecentAttendances(int $employee_id, int $limit = 10): array {
        $limit = max(1, min(100, (int)$limit));
        
        // Récupération des pointages comme dans presence.php
        $stmt = $this->conn->prepare("
            SELECT 
                DATE(created_at) as date_presence,
                MIN(CASE WHEN type = 'entree' THEN TIME(created_at) END) as heure_arrivee,
                MAX(CASE WHEN type = 'sortie' THEN TIME(created_at) END) as heure_depart,
                COUNT(*) as nb_pointages,
                CASE 
                    WHEN MIN(CASE WHEN type = 'entree' THEN TIME(created_at) END) IS NULL THEN 'absent'
                    WHEN MIN(CASE WHEN type = 'entree' THEN TIME(created_at) END) > '09:00:00' THEN 'retard'
                    ELSE 'present'
                END as statut
            FROM pointages 
            WHERE employe_id = ? 
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) DESC 
            LIMIT " . $limit
        );
        $stmt->execute([$employee_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer la durée pour chaque jour
        foreach ($result as &$day) {
            if ($day['heure_arrivee'] && $day['heure_depart']) {
                $start = strtotime($day['heure_arrivee']);
                $end = strtotime($day['heure_depart']);
                $duration = ($end - $start) / 3600; // en heures
                $day['duree_heures'] = round($duration, 2);
            } else {
                $day['duree_heures'] = 0;
            }
        }
        
        return $result;
    }
    
    /**
     * Récupère les statistiques d'un employé basées sur les pointages
     */
    public function getEmployeeStatistics(int $employee_id): array {
        $stats = [];
        
        // Calcul de l'ancienneté
        $stmt = $this->conn->prepare("SELECT date_embauche FROM employes WHERE id = ?");
        $stmt->execute([$employee_id]);
        $date_embauche = $stmt->fetchColumn();
        
        if ($date_embauche) {
            $anciennete = date_diff(date_create($date_embauche), date_create('today'));
            $stats['anciennete'] = $anciennete->y . ' ans ' . $anciennete->m . ' mois';
        } else {
            $stats['anciennete'] = 'N/A';
        }
        
        // Présences ce mois (jours où il y a eu au moins une entrée)
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT DATE(created_at)) 
            FROM pointages 
            WHERE employe_id = ? 
            AND MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE()) 
            AND type = 'entree'
        ");
        $stmt->execute([$employee_id]);
        $stats['presences_ce_mois'] = $stmt->fetchColumn() ?: 0;
        
        // Retards ce mois (entrées après 9h)
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT DATE(created_at))
            FROM pointages 
            WHERE employe_id = ? 
            AND MONTH(created_at) = MONTH(CURDATE()) 
            AND YEAR(created_at) = YEAR(CURDATE()) 
            AND type = 'entree'
            AND TIME(created_at) > '09:00:00'
        ");
        $stmt->execute([$employee_id]);
        $stats['retards_ce_mois'] = $stmt->fetchColumn() ?: 0;
        
        // Calcul du taux de présence (30 derniers jours)
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT DATE(created_at)) as jours_presence
            FROM pointages 
            WHERE employe_id = ? 
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            AND type = 'entree'
        ");
        $stmt->execute([$employee_id]);
        $jours_presence = $stmt->fetchColumn() ?: 0;
        
        // Calculer les jours ouvrables sur 30 jours (approximation)
        $jours_ouvrables = 22; // approximation pour un mois
        $stats['taux_presence'] = $jours_ouvrables > 0 ? 
            number_format(($jours_presence / $jours_ouvrables) * 100, 1) . '%' : 'N/A';
        
        // Absences ce mois (jours sans pointage d'entrée)
        $stats['absences_ce_mois'] = max(0, $jours_ouvrables - $stats['presences_ce_mois']);
        
        // Heures travaillées ce mois (basé sur les pointages)
        $stmt = $this->conn->prepare("
            SELECT 
                SUM(
                    CASE 
                        WHEN sortie.created_at IS NOT NULL AND entree.created_at IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, entree.created_at, sortie.created_at) / 3600
                        ELSE 0
                    END
                ) as total_heures
            FROM (
                SELECT DATE(created_at) as jour, MIN(created_at) as created_at
                FROM pointages 
                WHERE employe_id = ? 
                AND MONTH(created_at) = MONTH(CURDATE()) 
                AND YEAR(created_at) = YEAR(CURDATE())
                AND type = 'entree'
                GROUP BY DATE(created_at)
            ) entree
            LEFT JOIN (
                SELECT DATE(created_at) as jour, MAX(created_at) as created_at
                FROM pointages 
                WHERE employe_id = ? 
                AND MONTH(created_at) = MONTH(CURDATE()) 
                AND YEAR(created_at) = YEAR(CURDATE())
                AND type = 'sortie'
                GROUP BY DATE(created_at)
            ) sortie ON entree.jour = sortie.jour
        ");
        $stmt->execute([$employee_id, $employee_id]);
        $heures = $stmt->fetchColumn() ?: 0;
        $stats['heures_ce_mois'] = $heures ? number_format($heures, 1) . 'h' : '0h';
        
        return $stats;
    }
    
    /**
     * Marque la présence d'un employé via pointages
     */
    public function markAttendance(int $employee_id, string $status = 'entree'): array {
        try {
            $today = date('Y-m-d');
            
            // Vérifier le dernier pointage du jour
            $stmt = $this->conn->prepare("
                SELECT type, created_at 
                FROM pointages 
                WHERE employe_id = ? AND DATE(created_at) = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$employee_id, $today]);
            $last_pointage = $stmt->fetch();
            
            // Déterminer le type de pointage à effectuer
            if (!$last_pointage) {
                $type = 'entree';
            } else {
                $type = ($last_pointage['type'] === 'entree') ? 'sortie' : 'entree';
            }
            
            // Si on force un status spécifique
            if ($status !== 'entree') {
                $type = $status;
            }
            
            // Insérer le nouveau pointage
            $stmt = $this->conn->prepare("
                INSERT INTO pointages (employe_id, type, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$employee_id, $type]);
            
            return ['success' => true, 'message' => ucfirst($type) . ' enregistrée avec succès'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

// Gestion des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $employee_id = $_POST['employee_id'] ?? null;
    
    if (!$employee_id) {
        echo json_encode(['success' => false, 'message' => 'ID employé requis']);
        exit;
    }
    
    $employeeManager = new EmployeeManager($conn);
    
    switch ($action) {
        case 'mark_attendance':
            $status = $_POST['status'] ?? 'entree';
            $result = $employeeManager->markAttendance($employee_id, $status);
            echo json_encode($result);
            break;
            
        case 'get_statistics':
            $stats = $employeeManager->getEmployeeStatistics($employee_id);
            echo json_encode(['success' => true, 'statistics' => $stats]);
            break;
            
        case 'generate_qr_data':
            // Nouvelle action pour générer les données QR
            $employee = $employeeManager->getEmployeeById($employee_id);
            if ($employee) {
                $qrData = $employeeManager->generateQRData($employee);
                echo json_encode(['success' => true, 'qr_data' => $qrData, 'code_numerique' => $employee['code_numerique']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Employé introuvable']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
    exit;
}

// Vérification de l'ID employé
if (!isset($_GET['id'])) {
    header('Location: admin_gestion.php');
    exit;
}

$employee_id = (int)$_GET['id'];

try {
    $employeeManager = new EmployeeManager($conn);
    
    // Récupérer les données de l'employé
    $employee = $employeeManager->getEmployeeById($employee_id);
    
    if (!$employee) {
        header('Location: admin_gestion.php?error=employee_not_found');
        exit;
    }
    
    // Récupérer les données supplémentaires
    $horaires = $employeeManager->getWeeklySchedule($employee_id);
    $presences = $employeeManager->getRecentAttendances($employee_id);
    $statistics = $employeeManager->getEmployeeStatistics($employee_id);
    
    // Générer les données QR
    $qrData = $employeeManager->generateQRData($employee);
    
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>restaurant Mulho - <?php echo htmlspecialchars($employee['prenom'] . ' ' . $employee['nom']); ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .card-shadow { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
        .contract-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .contract-cdi { background-color: #dcfce7; color: #166534; }
        .contract-cdd { background-color: #fef3c7; color: #92400e; }
        .contract-stage { background-color: #dbeafe; color: #1e40af; }
        .contract-apprentissage { background-color: #fce7f3; color: #be185d; }
        .contract-freelance { background-color: #f3e8ff; color: #7c3aed; }
        .contract-temps_partiel { background-color: #e0f2fe; color: #0277bd; }
        @media print {
            .no-print { display: none !important; }
            .badge-container { page-break-after: always; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto p-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6 card-shadow">
            <div class="flex items-center justify-between mb-4">
                <button onclick="window.close()" class="text-gray-600 hover:text-gray-800 transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Retour
                </button>
                <div class="flex space-x-2">
                    <button onclick="editEmployee()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-edit mr-2"></i>Modifier
                    </button>
                    <button onclick="generateBadge()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-qrcode mr-2"></i>Badge
                    </button>
                    <button onclick="generatePayslip()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-file-pdf mr-2"></i>Bulletin
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
                    <div class="flex items-center mt-2 flex-wrap gap-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                              style="background-color: <?php echo $employee['poste_couleur'] ?? '#6B7280'; ?>20; color: <?php echo $employee['poste_couleur'] ?? '#6B7280'; ?>;">
                            <?php echo htmlspecialchars($employee['poste_nom'] ?? 'Non défini'); ?>
                        </span>
                        <span class="contract-badge contract-<?php echo strtolower(str_replace(' ', '_', $employee['type_contrat'] ?? '')); ?>">
                            <?php echo htmlspecialchars($employee['type_contrat'] ?? 'Non défini'); ?>
                        </span>
                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php 
                            echo $employee['statut'] === 'actif' ? 'bg-green-100 text-green-800' : 
                                ($employee['statut'] === 'en_conge' ? 'bg-yellow-100 text-yellow-800' : 
                                ($employee['statut'] === 'absent' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); 
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $employee['statut'])); ?>
                        </span>
                    </div>
                    <?php if ($employee['code_numerique']): ?>
                        <div class="mt-2">
                            <span class="text-sm text-gray-600">Code numérique: </span>
                            <span class="font-mono font-bold text-blue-600"><?php echo $employee['code_numerique']; ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Informations détaillées -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Informations personnelles -->
                <div class="bg-white rounded-lg shadow-md p-6 card-shadow fade-in">
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
                            <p class="mt-1 text-sm text-gray-900">
                                <?php 
                                if ($employee['salaire']) {
                                    echo number_format($employee['salaire'], 0, ',', ' ') . ' FCFA';
                                } elseif ($employee['poste_salaire']) {
                                    echo number_format($employee['poste_salaire'], 0, ',', ' ') . ' FCFA (salaire du poste)';
                                } else {
                                    echo 'Non défini';
                                }
                                ?>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Horaires habituels</label>
                            <p class="mt-1 text-sm text-gray-900"><?php echo $employee['heure_debut'] . ' - ' . $employee['heure_fin']; ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">ID Employé</label>
                            <p class="mt-1 text-sm text-gray-900 font-mono">#<?php echo str_pad($employee['id'], 4, '0', STR_PAD_LEFT); ?></p>
                        </div>
                    </div>
                </div>
<!-- Correction dans la section HTML - Informations du poste -->
<?php if ($employee['poste_id']): ?>
<div class="bg-white rounded-lg shadow-md p-6 card-shadow fade-in">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">
        <i class="fas fa-briefcase mr-2 text-green-600"></i>Informations du poste
    </h2>
    
    <!-- Section département corrigée -->
    <?php if ($employee['departement_nom']): ?>
    <div class="mb-4 p-4 rounded-lg border-l-4 border-gray-300 bg-gray-50">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900">
                    <i class="fas fa-building mr-2 text-gray-600"></i>
                    Département: <?php echo htmlspecialchars($employee['departement_nom']); ?>
                </h3>
                <?php if ($employee['departement_description']): ?>
                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($employee['departement_description']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="mb-4 p-4 rounded-lg border-l-4 border-yellow-300 bg-yellow-50">
        <p class="text-sm text-yellow-800">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Aucun département assigné à ce poste
        </p>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Niveau hiérarchique</label>
            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($employee['niveau_hierarchique'] ?? 'Non défini'); ?></p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Code paie</label>
            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($employee['code_paie'] ?? 'Non défini'); ?></p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Catégorie paie</label>
            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($employee['categorie_paie'] ?? 'Non définie'); ?></p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Régime social</label>
            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($employee['regime_social'] ?? 'Non défini'); ?></p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Durée du contrat</label>
            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($employee['duree_contrat'] ?? 'Non spécifiée'); ?></p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Taux cotisation</label>
            <p class="mt-1 text-sm text-gray-900"><?php echo ($employee['taux_cotisation'] ?? 0) . '%'; ?></p>
        </div>
        <?php if ($employee['poste_superieur_nom']): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700">Poste supérieur</label>
            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($employee['poste_superieur_nom']); ?></p>
        </div>
        <?php endif; ?>
        <?php if (isset($employee['heures_travail']) && $employee['heures_travail']): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700">Heures/mois</label>
            <p class="mt-1 text-sm text-gray-900">
                <?php echo number_format($employee['heures_travail'], 0, ',', ' '); ?>h
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($employee['competences_requises']): ?>
    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Compétences requises</label>
        <div class="mt-2 p-3 bg-gray-50 rounded-lg">
            <p class="text-sm text-gray-800"><?php echo nl2br(htmlspecialchars($employee['competences_requises'])); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($employee['avantages']): ?>
    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Avantages</label>
        <div class="mt-2 p-3 bg-green-50 rounded-lg">
            <p class="text-sm text-gray-800"><?php echo nl2br(htmlspecialchars($employee['avantages'])); ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
                <!-- Horaires de la semaine -->
                <div class="bg-white rounded-lg shadow-md p-6 card-shadow fade-in">
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
                                        <?php echo ($debut && $fin) ? $debut . ' - ' . $fin : '<span class="text-red-500">Repos</span>'; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-times text-4xl text-gray-300 mb-2"></i>
                            <p class="text-gray-500">Aucun horaire planifié pour cette semaine</p>
                            <button onclick="planifierHoraires()" class="mt-2 text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-plus mr-1"></i>Planifier des horaires
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Présences récentes -->
                <div class="bg-white rounded-lg shadow-md p-6 card-shadow fade-in">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-clock mr-2 text-purple-600"></i>Présences récentes (basées sur les pointages)
                    </h2>
                    <?php if ($presences): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-3 text-sm font-medium text-gray-700">Date</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-700">Arrivée</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-700">Départ</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-700">Durée</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-700">Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($presences as $presence): ?>
                                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                                            <td class="py-3 text-sm text-gray-900">
                                                <?php echo date('d/m/Y', strtotime($presence['date_presence'])); ?>
                                                <div class="text-xs text-gray-500">
                                                    <?php 
                                                    $dayName = ['Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi', 
                                                               'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi', 'Sunday' => 'Dimanche'];
                                                    echo $dayName[date('l', strtotime($presence['date_presence']))] ?? date('l', strtotime($presence['date_presence']));
                                                    ?>
                                                </div>
                                            </td>
                                            <td class="py-3 text-sm text-gray-600">
                                                <?php echo $presence['heure_arrivee'] ?: '-'; ?>
                                            </td>
                                            <td class="py-3 text-sm text-gray-600">
                                                <?php echo $presence['heure_depart'] ?: '-'; ?>
                                            </td>
                                            <td class="py-3 text-sm text-gray-600">
                                                <?php 
                                                if ($presence['duree_heures'] > 0) {
                                                    echo number_format($presence['duree_heures'], 1) . 'h';
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td class="py-3">
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
                        <div class="text-center py-8">
                            <i class="fas fa-clock text-4xl text-gray-300 mb-2"></i>
                            <p class="text-gray-500">Aucune présence enregistrée</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- QR Code et Badge dynamique -->
                <div class="bg-white rounded-lg shadow-md p-6 text-center card-shadow fade-in">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">QR Code Badge</h3>
                    
                    <!-- Canvas pour le QR Code -->
                    <div class="mb-4">
                        <canvas id="qr-code-canvas" width="200" height="200" class="mx-auto border border-gray-200 rounded-lg"></canvas>
                    </div>
                    
                    <!-- Affichage du code numérique -->
                    <div class="mb-4">
                        <span class="text-sm text-gray-600">Code numérique: </span>
                        <span id="numeric-code-display" class="font-mono font-bold text-blue-600">
                            <?php echo $employee['code_numerique'] ?? 'Génération...'; ?>
                        </span>
                    </div>
                    
                    <div class="space-y-2">
                        <button onclick="regenerateQR()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition duration-200">
                            <i class="fas fa-sync-alt mr-2"></i>Régénérer QR
                        </button>
                        <button onclick="downloadQR()" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition duration-200">
                            <i class="fas fa-download mr-2"></i>Télécharger QR
                        </button>
                        <button onclick="printBadge()" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm transition duration-200">
                            <i class="fas fa-print mr-2"></i>Imprimer Badge
                        </button>
                    </div>
                </div>

                <!-- Badge preview -->
                <div class="bg-white rounded-lg shadow-md p-6 card-shadow fade-in">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Aperçu du badge</h3>
                    <div id="badge-preview" class="border-2 border-indigo-500 rounded-lg p-4 bg-white" style="min-height: 120px;">
                        <div class="flex items-center justify-between h-full">
                            <div class="flex-1 text-left">
                                <div class="text-xs font-bold text-indigo-600 mb-1">BADGE EMPLOYÉ</div>
                                <div class="text-sm font-semibold mb-1" style="line-height: 1.2;">
                                    <?= htmlspecialchars(($employee['prenom'] ?? '') . ' ' . ($employee['nom'] ?? '')) ?>
                                </div>
                                
                                <?php if (!empty($employee['poste_nom'])): ?>
                                <div class="text-xs text-gray-600 mb-1">
                                    <?= htmlspecialchars($employee['poste_nom']) ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="text-xs text-gray-500 mb-1">
                                    ID: <?= $employee['id'] ?>
                                </div>
                                
                                <div class="bg-gray-100 px-2 py-1 rounded text-xs font-mono" id="badge-code-display">
                                    <?= htmlspecialchars($employee['code_numerique'] ?? 'Génération...') ?>
                                </div>
                            </div>

                            <div class="flex-shrink-0 ml-2">
                                <canvas id="qr-badge-preview" width="80" height="80"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="bg-white rounded-lg shadow-md p-6 card-shadow fade-in">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions rapides</h3>
                    <div class="space-y-2">
                        <button onclick="marquerPresence('entree')" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition duration-200">
                            <i class="fas fa-sign-in-alt mr-2"></i>Marquer entrée
                        </button>
                        <button onclick="marquerPresence('sortie')" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm transition duration-200">
                            <i class="fas fa-sign-out-alt mr-2"></i>Marquer sortie
                        </button>
                        <button onclick="envoyerEmail()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition duration-200">
                            <i class="fas fa-envelope mr-2"></i>Envoyer email
                        </button>
                        <button onclick="voirPointages()" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm transition duration-200">
                            <i class="fas fa-chart-line mr-2"></i>Voir dashboard
                        </button>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="bg-white rounded-lg shadow-md p-6 card-shadow fade-in">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistiques du mois</h3>
                    <div class="space-y-4" id="statisticsContainer">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-check text-green-600 mr-2"></i>
                                <span class="text-sm text-gray-600">Ancienneté</span>
                            </div>
                            <span class="text-sm font-medium text-gray-900"><?php echo $statistics['anciennete']; ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <i class="fas fa-user-check text-green-600 mr-2"></i>
                                <span class="text-sm text-gray-600">Présences</span>
                            </div>
                            <span class="text-sm font-medium text-green-700"><?php echo $statistics['presences_ce_mois']; ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <i class="fas fa-clock text-yellow-600 mr-2"></i>
                                <span class="text-sm text-gray-600">Retards</span>
                            </div>
                            <span class="text-sm font-medium text-yellow-700"><?php echo $statistics['retards_ce_mois']; ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <i class="fas fa-user-times text-red-600 mr-2"></i>
                                <span class="text-sm text-gray-600">Absences</span>
                            </div>
                            <span class="text-sm font-medium text-red-700"><?php echo $statistics['absences_ce_mois']; ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <i class="fas fa-hourglass-half text-blue-600 mr-2"></i>
                                <span class="text-sm text-gray-600">Heures travaillées</span>
                            </div>
                            <span class="text-sm font-medium text-blue-700"><?php echo $statistics['heures_ce_mois']; ?></span>
                        </div>
                        
                        <div class="border-t pt-3 mt-3">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center">
                                    <i class="fas fa-chart-line text-purple-600 mr-2"></i>
                                    <span class="text-sm text-gray-600">Taux de présence</span>
                                </div>
                                <span class="text-sm font-bold text-purple-700"><?php echo $statistics['taux_presence']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <button onclick="refreshStatistics()" class="w-full mt-4 text-sm text-gray-600 hover:text-gray-800 transition duration-200">
                        <i class="fas fa-sync-alt mr-1"></i>Actualiser
                    </button>
                </div>

                <!-- Informations de contact -->
                <div class="bg-white rounded-lg shadow-md p-6 card-shadow fade-in">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Contact</h3>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-blue-600 mr-3 w-4"></i>
                            <a href="mailto:<?php echo htmlspecialchars($employee['email']); ?>" 
                               class="text-sm text-blue-600 hover:text-blue-800 transition duration-200">
                                <?php echo htmlspecialchars($employee['email']); ?>
                            </a>
                        </div>
                        
                        <?php if ($employee['telephone']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-phone text-green-600 mr-3 w-4"></i>
                            <a href="tel:<?php echo htmlspecialchars($employee['telephone']); ?>" 
                               class="text-sm text-green-600 hover:text-green-800 transition duration-200">
                                <?php echo htmlspecialchars($employee['telephone']); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex items-center">
                            <i class="fas fa-calendar text-purple-600 mr-3 w-4"></i>
                            <span class="text-sm text-gray-600">
                                Depuis le <?php echo date('d/m/Y', strtotime($employee['date_embauche'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Zone de notification -->
    <div id="notification" class="fixed top-4 right-4 z-50 hidden"></div>

    <!-- Badge imprimable (masqué par défaut) -->
    <div id="printable-badge" class="hidden">
        <div class="badge-container" style="width: 85mm; height: 54mm; border: 2px solid #4F46E5; border-radius: 8px; padding: 8px; background: white; margin: 10mm;">
            <div class="flex items-center justify-between h-full">
                <div class="flex-1 text-left">
                    <div class="text-xs font-bold text-indigo-600 mb-1">BADGE EMPLOYÉ</div>
                    <div class="text-sm font-semibold mb-1" style="line-height: 1.2;">
                        <?= htmlspecialchars(($employee['prenom'] ?? '') . ' ' . ($employee['nom'] ?? '')) ?>
                    </div>
                    
                    <?php if (!empty($employee['poste_nom'])): ?>
                    <div class="text-xs text-gray-600 mb-1">
                        <?= htmlspecialchars($employee['poste_nom']) ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="text-xs text-gray-500 mb-1">
                        ID: <?= $employee['id'] ?>
                    </div>
                    
                    <div class="bg-gray-100 px-2 py-1 rounded text-xs font-mono" id="print-badge-code">
                        <?= htmlspecialchars($employee['code_numerique'] ?? 'Génération...') ?>
                    </div>
                </div>

                <div class="flex-shrink-0 ml-2">
                    <canvas id="qr-print-canvas" width="80" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        const employeeId = <?php echo $employee['id']; ?>;
        let currentQRData = <?php echo json_encode($qrData); ?>;
        
        // Initialisation du QR Code au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            generateQRCode();
            updateBadgePreview();
        });
        
        // Génération du QR Code
        function generateQRCode() {
            try {
                // QR Code principal (200x200)
                new QRious({
                    element: document.getElementById('qr-code-canvas'),
                    value: currentQRData,
                    size: 200,
                    background: 'white',
                    foreground: '#4F46E5',
                    level: 'H'
                });
                
                // QR Code pour l'aperçu du badge (80x80)
                new QRious({
                    element: document.getElementById('qr-badge-preview'),
                    value: currentQRData,
                    size: 80,
                    background: 'white',
                    foreground: '#4F46E5',
                    level: 'H'
                });
                
                // QR Code pour l'impression (80x80)
                new QRious({
                    element: document.getElementById('qr-print-canvas'),
                    value: currentQRData,
                    size: 80,
                    background: 'white',
                    foreground: '#4F46E5',
                    level: 'H'
                });
                
            } catch (error) {
                console.error('Erreur génération QR:', error);
                showNotification('Erreur lors de la génération du QR Code', 'error');
            }
        }
        
        // Régénération du QR Code
        function regenerateQR() {
            showLoading();
            
            const formData = new FormData();
            formData.append('action', 'generate_qr_data');
            formData.append('employee_id', employeeId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    currentQRData = data.qr_data;
                    generateQRCode();
                    
                    // Mettre à jour l'affichage du code numérique
                    document.getElementById('numeric-code-display').textContent = data.code_numerique;
                    document.getElementById('badge-code-display').textContent = data.code_numerique;
                    document.getElementById('print-badge-code').textContent = data.code_numerique;
                    
                    showNotification('QR Code régénéré avec succès', 'success');
                } else {
                    showNotification(data.message || 'Erreur lors de la génération', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Erreur:', error);
                showNotification('Erreur lors de la génération du QR Code', 'error');
            });
        }
        
        // Mise à jour de l'aperçu du badge
        function updateBadgePreview() {
            setTimeout(() => {
                try {
                    new QRious({
                        element: document.getElementById('qr-badge-preview'),
                        value: currentQRData,
                        size: 80,
                        background: 'white',
                        foreground: '#4F46E5',
                        level: 'H'
                    });
                } catch (error) {
                    console.error('Erreur mise à jour aperçu:', error);
                }
            }, 100);
        }
        
        // Téléchargement du QR Code
        function downloadQR() {
            const canvas = document.getElementById('qr-code-canvas');
            const link = document.createElement('a');
            link.download = 'qr_<?php echo $employee['nom']; ?>_<?php echo $employee['prenom']; ?>.png';
            link.href = canvas.toDataURL();
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showNotification('QR Code téléchargé', 'success');
        }
        
        // Impression du badge
        function printBadge() {
            // Mettre à jour le QR Code d'impression
            updatePrintQR();
            
            // Masquer tout sauf le badge
            const originalDisplay = document.body.style.display;
            const printBadge = document.getElementById('printable-badge');
            
            // Créer une nouvelle fenêtre pour l'impression
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Badge - <?= htmlspecialchars($employee['prenom'] . ' ' . $employee['nom']) ?></title>
                    <style>
                        @page { margin: 10mm; }
                        body { margin: 0; font-family: Arial, sans-serif; }
                        .badge-container { 
                            width: 85mm; 
                            height: 54mm; 
                            border: 2px solid #4F46E5; 
                            border-radius: 8px; 
                            padding: 8px; 
                            background: white; 
                            display: flex;
                            align-items: center;
                        }
                    </style>
                </head>
                <body>
                    ${printBadge.innerHTML}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.focus();
            
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        }
        
        // Mise à jour du QR Code d'impression
        function updatePrintQR() {
            try {
                new QRious({
                    element: document.getElementById('qr-print-canvas'),
                    value: currentQRData,
                    size: 80,
                    background: 'white',
                    foreground: '#4F46E5',
                    level: 'H'
                });
            } catch (error) {
                console.error('Erreur QR impression:', error);
            }
        }
        
        // Fonctions principales existantes
        function editEmployee() {
            if (window.opener && window.opener.editEmployee) {
                window.opener.editEmployee(employeeId);
                window.close();
            } else {
                window.open(`admin_gestion.php?edit=${employeeId}`, '_blank');
            }
        }

        function generateBadge() {
            window.open(`generate_badge.php?id=${employeeId}`, '_blank');
        }

        function generatePayslip() {
            const mois = prompt("Entrez le mois pour le bulletin (YYYY-MM):", new Date().toISOString().slice(0, 7));
            if (mois) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_gestion.php?action=generer_bulletin';
                form.target = '_blank';
                
                const employeInput = document.createElement('input');
                employeInput.type = 'hidden';
                employeInput.name = 'employe_id';
                employeInput.value = employeeId;
                
                const moisInput = document.createElement('input');
                moisInput.type = 'hidden';
                moisInput.name = 'mois_annee';
                moisInput.value = mois;
                
                form.appendChild(employeInput);
                form.appendChild(moisInput);
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        }

        function marquerPresence(type = 'entree') {
            const actionText = type === 'entree' ? 'une entrée' : 'une sortie';
            if (confirm(`Enregistrer ${actionText} pour cet employé ?`)) {
                showLoading();
                
                const formData = new FormData();
                formData.append('action', 'mark_attendance');
                formData.append('employee_id', employeeId);
                formData.append('status', type);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification(data.message || 'Erreur lors du pointage', 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Erreur:', error);
                    showNotification('Erreur lors du pointage', 'error');
                });
            }
        }

        function envoyerEmail() {
            const email = '<?php echo $employee['email']; ?>';
            const subject = encodeURIComponent('Message concernant votre travail');
            window.location.href = `mailto:${email}?subject=${subject}`;
        }

        function voirPointages() {
            window.open(`presence.php?employe_id=${employeeId}`, '_blank');
        }

        function refreshStatistics() {
            showLoading();
            
            const formData = new FormData();
            formData.append('action', 'get_statistics');
            formData.append('employee_id', employeeId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    updateStatisticsDisplay(data.statistics);
                    showNotification('Statistiques actualisées', 'success');
                } else {
                    showNotification('Erreur lors de l\'actualisation', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Erreur:', error);
                showNotification('Erreur lors de l\'actualisation', 'error');
            });
        }

        function updateStatisticsDisplay(stats) {
            setTimeout(() => location.reload(), 1000);
        }

        // Fonctions utilitaires pour les notifications
        function showNotification(message, type = 'info') {
            const notification = document.getElementById('notification');
            const colors = {
                'success': 'bg-green-500',
                'error': 'bg-red-500',
                'warning': 'bg-yellow-500',
                'info': 'bg-blue-500'
            };
            
            notification.innerHTML = `
                <div class="${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center max-w-md">
                    <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle mr-2"></i>
                    ${message}
                    <button onclick="hideNotification()" class="ml-4 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            notification.classList.remove('hidden');
            
            setTimeout(() => {
                hideNotification();
            }, 5000);
        }

        function hideNotification() {
            document.getElementById('notification').classList.add('hidden');
        }

        function showLoading() {
            showNotification('Traitement en cours...', 'info');
        }

        function hideLoading() {
            hideNotification();
        }

        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'e':
                        e.preventDefault();
                        editEmployee();
                        break;
                    case 'i':
                        e.preventDefault();
                        marquerPresence('entree');
                        break;
                    case 'o':
                        e.preventDefault();
                        marquerPresence('sortie');
                        break;
                    case 'b':
                        e.preventDefault();
                        generateBadge();
                        break;
                    case 'q':
                        e.preventDefault();
                        regenerateQR();
                        break;
                    case 'p':
                        e.preventDefault();
                        printBadge();
                        break;
                }
            }
        });

        // Auto-refresh des statistiques toutes les 5 minutes
        setInterval(() => {
            refreshStatistics();
        }, 300000);

        // Animation d'entrée pour les cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.fade-in');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Fonction pour planifier les horaires (à implémenter selon vos besoins)
        function planifierHoraires() {
            if (window.opener && window.opener.planifierHoraires) {
                window.opener.planifierHoraires(employeeId);
            } else {
                // Rediriger vers la page de planification des horaires
                window.open(`planning.php?employee_id=${employeeId}`, '_blank');
            }
        }

        // Gestion des erreurs globales
        window.addEventListener('error', function(e) {
            console.error('Erreur JavaScript:', e.error);
            showNotification('Une erreur inattendue s\'est produite', 'error');
        });

        // Gestion de la visibilité de la page pour optimiser les performances
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                // Actualiser les données quand l'utilisateur revient sur l'onglet
                refreshStatistics();
            }
        });

        // Validation des données avant envoi
        function validateEmployeeData() {
            const requiredFields = ['nom', 'prenom', 'email'];
            const employee = <?php echo json_encode($employee); ?>;
            
            for (let field of requiredFields) {
                if (!employee[field] || employee[field].trim() === '') {
                    showNotification(`Le champ ${field} est requis`, 'warning');
                    return false;
                }
            }
            return true;
        }

        // Export des données de l'employé
        function exportEmployeeData() {
            const employee = <?php echo json_encode($employee); ?>;
            const data = {
                employee: employee,
                statistics: <?php echo json_encode($statistics); ?>,
                qr_data: currentQRData,
                export_date: new Date().toISOString()
            };
            
            const dataStr = JSON.stringify(data, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(dataBlob);
            link.download = `employee_${employeeId}_data.json`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showNotification('Données exportées avec succès', 'success');
        }

        // Fonction utilitaire pour formater les dates
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // Fonction utilitaire pour formater les heures
        function formatTime(timeString) {
            if (!timeString) return '-';
            const [hours, minutes] = timeString.split(':');
            return `${hours}:${minutes}`;
        }

        // Gestion du mode sombre (si implémenté)
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
        }

        // Charger le mode sombre sauvegardé
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }

        // Fonction pour copier le code numérique dans le presse-papiers
        function copyNumericCode() {
            const code = document.getElementById('numeric-code-display').textContent;
            navigator.clipboard.writeText(code).then(() => {
                showNotification('Code copié dans le presse-papiers', 'success');
            }).catch(() => {
                showNotification('Erreur lors de la copie', 'error');
            });
        }

        // Ajouter un event listener pour copier le code au clic
        document.getElementById('numeric-code-display').addEventListener('click', copyNumericCode);
        document.getElementById('badge-code-display').addEventListener('click', copyNumericCode);

        // Fonction pour partager les informations de l'employé
        function shareEmployee() {
            if (navigator.share) {
                navigator.share({
                    title: 'Informations Employé',
                    text: `<?php echo htmlspecialchars($employee['prenom'] . ' ' . $employee['nom']); ?> - <?php echo htmlspecialchars($employee['poste_nom'] ?? ''); ?>`,
                    url: window.location.href
                }).then(() => {
                    showNotification('Partagé avec succès', 'success');
                }).catch(() => {
                    showNotification('Erreur lors du partage', 'error');
                });
            } else {
                // Fallback: copier l'URL dans le presse-papiers
                navigator.clipboard.writeText(window.location.href).then(() => {
                    showNotification('Lien copié dans le presse-papiers', 'success');
                }).catch(() => {
                    showNotification('Partage non supporté', 'error');
                });
            }
        }

        // Console log pour debug (à supprimer en production)
        console.log('Employee Details Page Loaded for Employee ID:', employeeId);
        console.log('Current QR Data:', currentQRData);
        console.log('Employee Data:', <?php echo json_encode($employee); ?>);
    </script>
</body>
</html>