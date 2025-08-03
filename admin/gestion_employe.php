<?php
session_start();
require_once '../config.php';

// Vérifier si l'utilisateur est connecté et a les droits admin
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
   //  header('Location: login.php');
    // exit();
// }

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_employee':
            try {
              $stmt = $conn->prepare("INSERT INTO employes (nom, prenom, poste, telephone, email, date_embauche, salaire_horaire, statut, photo) VALUES (?, ?, ?, ?, ?, ?, ?, 'actif', ?)");
                $photo_url = "https://i.pravatar.cc/150?u=" . urlencode($_POST['nom'] . $_POST['prenom']);
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['prenom'],
                    $_POST['poste'],
                    $_POST['telephone'],
                    $_POST['email'],
                    $_POST['date_embauche'],
                    $_POST['salaire'],
                    $photo_url
                ]);
                echo json_encode(['success' => true, 'message' => 'Employé ajouté avec succès']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit();
            
        case 'update_employee':
            try {
                $stmt = $conn->prepare("UPDATE employes SET nom=?, prenom=?, poste=?, telephone=?, email=?, salaire_horaire=?, statut=? WHERE id=?");
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['prenom'],
                    $_POST['poste'],
                    $_POST['telephone'],
                    $_POST['email'],
                    $_POST['salaire'],
                    $_POST['statut'],
                    $_POST['id']
                ]);
                echo json_encode(['success' => true, 'message' => 'Employé modifié avec succès']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit();
            
        case 'delete_employee':
            try {
                $stmt = $conn->prepare("UPDATE employes SET statut='inactif' WHERE id=?");
                $stmt->execute([$_POST['id']]);
                echo json_encode(['success' => true, 'message' => 'Employé désactivé avec succès']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit();
            
        case 'get_employee':
            try {
                $stmt = $conn->prepare("SELECT * FROM employes WHERE id=?");
                $stmt->execute([$_POST['id']]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'employee' => $employee]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit();
            
        case 'save_schedule':
            try {
                // Supprimer les anciens horaires pour cet employé et cette semaine
                $stmt = $conn->prepare("DELETE FROM horaires WHERE employee_id=? AND semaine=?");
                $stmt->execute([$_POST['employee_id'], $_POST['semaine']]);
                
                // Insérer les nouveaux horaires
                $stmt = $conn->prepare("INSERT INTO horaires (employee_id, semaine, jour, heure_debut, heure_fin) VALUES (?, ?, ?, ?, ?)");
                foreach ($_POST['horaires'] as $jour => $horaire) {
                    if (!empty($horaire['debut']) && !empty($horaire['fin'])) {
                        $stmt->execute([$_POST['employee_id'], $_POST['semaine'], $jour, $horaire['debut'], $horaire['fin']]);
                    }
                }
                echo json_encode(['success' => true, 'message' => 'Horaires sauvegardés avec succès']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit();
    }
}

// Récupération des statistiques
try {
    // Total employés
    $stmt = $conn->query("SELECT COUNT(*) FROM employes WHERE statut != 'inactif'");
    $total_employees = $stmt->fetchColumn();
    
    // Employés présents aujourd'hui (basé sur les horaires)
    $today = date('N'); // 1=Lundi, 7=Dimanche
    $current_week = date('Y-W');
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT h.employee_id) FROM horaires h 
                          JOIN employees e ON h.employee_id = e.id 
                          WHERE h.semaine = ? AND h.jour = ? AND e.statut = 'actif'");
    $stmt->execute([$current_week, $today]);
    $present_today = $stmt->fetchColumn();
    
    // Nouveaux employés ce mois
    $stmt = $conn->prepare("SELECT COUNT(*) FROM employees WHERE MONTH(date_embauche) = MONTH(CURRENT_DATE) AND YEAR(date_embauche) = YEAR(CURRENT_DATE)");
    $stmt->execute();
    $new_this_month = $stmt->fetchColumn();
    
    // Taux de présence (simulation basée sur les horaires planifiés vs réels)
    $attendance_rate = $total_employees > 0 ? round(($present_today / $total_employees) * 100) : 0;
    
} catch(PDOException $e) {
    $total_employees = $present_today = $new_this_month = $attendance_rate = 0;
}

// Récupération des employés avec filtres
$where_clauses = ["e.statut != 'inactif'"];
$params = [];

if (isset($_GET['poste']) && !empty($_GET['poste'])) {
    $where_clauses[] = "e.poste = ?";
    $params[] = $_GET['poste'];
}

if (isset($_GET['statut']) && !empty($_GET['statut'])) {
    $where_clauses[] = "e.statut = ?";
    $params[] = $_GET['statut'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_clauses[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.email LIKE ?)";
    $search_param = '%' . $_GET['search'] . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = implode(' AND ', $where_clauses);

try {
    $stmt = $conn->prepare("SELECT e.*, 
                          CASE 
                            WHEN e.statut = 'actif' THEN 'Actif'
                            WHEN e.statut = 'conge' THEN 'En congé'
                            WHEN e.statut = 'absent' THEN 'Absent'
                            ELSE 'Inactif'
                          END as statut_label
                          FROM employes e 
                          WHERE $where_sql 
                          ORDER BY e.nom, e.prenom");
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $employees = [];
    $error_message = "Erreur lors de la récupération des employés: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Employés - Restaurant Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .actions-bar {
            padding: 20px 30px;
            background: white;
            border-bottom: 1px solid #eee;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: space-between;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-select, .search-box {
            padding: 8px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .search-box {
            min-width: 200px;
        }

        .employees-table {
            padding: 30px;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .employee-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .employee-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .employee-id {
            font-size: 0.8rem;
            color: #7f8c8d;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-actif {
            background: #d4edda;
            color: #155724;
        }

        .status-conge {
            background: #fff3cd;
            color: #856404;
        }

        .status-absent {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
            border-radius: 5px;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px 30px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
        }

        .salary-info {
            font-weight: 600;
            color: #28a745;
        }

        .alert {
            padding: 10px 20px;
            margin: 10px 0;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }

            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-group {
                justify-content: center;
            }

            .table-container {
                overflow-x: auto;
            }

            table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- En-tête -->
        <div class="header">
            <h1><i class="fas fa-users"></i> Gestion des Employés</h1>
            <p>Tableau de bord administrateur - Restaurant Le Gourmand</p>
        </div>

        <!-- Messages d'alerte -->
        <div id="alertContainer"></div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?php echo $total_employees; ?></div>
                <div class="stat-label">Total Employés</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-value"><?php echo $present_today; ?></div>
                <div class="stat-label">Présents Aujourd'hui</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
                <div class="stat-value"><?php echo $new_this_month; ?></div>
                <div class="stat-label">Nouveaux ce Mois</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-percentage"></i></div>
                <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                <div class="stat-label">Taux de Présence</div>
            </div>
        </div>

        <!-- Barre d'actions -->
        <div class="actions-bar">
            <div class="btn-group">
                <button class="btn btn-primary" onclick="openModal('addEmployeeModal')">
                    <i class="fas fa-user-plus"></i> Ajouter Employé
                </button>
                <button class="btn btn-secondary" onclick="openModal('scheduleModal')">
                    <i class="fas fa-calendar-alt"></i> Planifier Horaires
                </button>
                <button class="btn btn-success" onclick="generateReport()">
                    <i class="fas fa-file-export"></i> Générer Rapport
                </button>
            </div>
            <div class="filters">
                <form method="GET" id="filterForm" style="display: contents;">
                    <select class="filter-select" name="poste" onchange="this.form.submit()">
                        <option value="">Tous les postes</option>
                        <option value="serveur" <?php echo (isset($_GET['poste']) && $_GET['poste'] === 'serveur') ? 'selected' : ''; ?>>Serveur</option>
                        <option value="cuisinier" <?php echo (isset($_GET['poste']) && $_GET['poste'] === 'cuisinier') ? 'selected' : ''; ?>>Cuisinier</option>
                        <option value="manager" <?php echo (isset($_GET['poste']) && $_GET['poste'] === 'manager') ? 'selected' : ''; ?>>Manager</option>
                        <option value="barman" <?php echo (isset($_GET['poste']) && $_GET['poste'] === 'barman') ? 'selected' : ''; ?>>Barman</option>
                    </select>
                    <select class="filter-select" name="statut" onchange="this.form.submit()">
                        <option value="">Tous les statuts</option>
                        <option value="actif" <?php echo (isset($_GET['statut']) && $_GET['statut'] === 'actif') ? 'selected' : ''; ?>>Actif</option>
                        <option value="conge" <?php echo (isset($_GET['statut']) && $_GET['statut'] === 'conge') ? 'selected' : ''; ?>>En congé</option>
                        <option value="absent" <?php echo (isset($_GET['statut']) && $_GET['statut'] === 'absent') ? 'selected' : ''; ?>>Absent</option>
                    </select>
                    <input type="text" class="search-box" name="search" placeholder="Rechercher un employé..." 
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                           onkeyup="debounceSearch(this.value)">
                </form>
            </div>
        </div>

        <!-- Tableau des employés -->
        <div class="employees-table">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Poste</th>
                            <th>Statut</th>
                            <th>Contact</th>
                            <th>Embauche</th>
                            <th>Salaire</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.3;"></i><br>
                                    Aucun employé trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td>
                                        <div class="employee-info">
                                            <img src="<?php echo htmlspecialchars($employee['photo'] ?? 'https://i.pravatar.cc/150?u=' . urlencode($employee['nom'])); ?>" 
                                                 alt="<?php echo htmlspecialchars($employee['nom'] . ' ' . $employee['prenom']); ?>" 
                                                 class="employee-photo">
                                            <div>
                                                <div class="employee-name"><?php echo htmlspecialchars($employee['nom'] . ' ' . $employee['prenom']); ?></div>
                                                <div class="employee-id">ID: <?php echo str_pad($employee['id'], 3, '0', STR_PAD_LEFT); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo ucfirst(htmlspecialchars($employee['poste'])); ?></td>
                                    <td><span class="status-badge status-<?php echo $employee['statut']; ?>"><?php echo htmlspecialchars($employee['statut_label']); ?></span></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($employee['telephone']); ?></div>
                                        <div style="font-size: 0.8rem; color: #7f8c8d;"><?php echo htmlspecialchars($employee['email']); ?></div>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($employee['date_embauche'])); ?></td>
                                    <td><span class="salary-info"><?php echo number_format($employee['salaire_horaire'], 2); ?>€/h</span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info btn-sm" onclick="viewEmployee(<?php echo $employee['id']; ?>)" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning btn-sm" onclick="editEmployee(<?php echo $employee['id']; ?>)" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteEmployee(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['nom'] . ' ' . $employee['prenom']); ?>')" title="Désactiver">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Ajouter/Modifier Employé -->
    <div id="addEmployeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-user-plus"></i> Ajouter un Nouvel Employé</h2>
                <button class="close" onclick="closeModal('addEmployeeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="employeeForm">
                    <input type="hidden" id="employeeId" name="id">
                    <div class="form-group">
                        <label class="form-label">Nom</label>
                        <input type="text" class="form-control" name="nom" id="employeeNom" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prénom</label>
                        <input type="text" class="form-control" name="prenom" id="employeePrenom" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Poste</label>
                        <select class="form-control" name="poste" id="employeePoste" required>
                            <option value="">Sélectionner un poste</option>
                            <option value="serveur">Serveur</option>
                            <option value="cuisinier">Cuisinier</option>
                            <option value="manager">Manager</option>
                            <option value="barman">Barman</option>
                            <option value="receptionniste">Réceptionniste</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" name="telephone" id="employeeTelephone" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="employeeEmail" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date d'embauche</label>
                        <input type="date" class="form-control" name="date_embauche" id="employeeDateEmbauche" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Salaire (€/heure)</label>
                        <input type="number" class="form-control" name="salaire" id="employeeSalaire" step="0.50" required>
                    </div>
                    <div class="form-group" id="statutGroup" style="display: none;">
                        <label class="form-label">Statut</label>
                        <select class="form-control" name="statut" id="employeeStatut">
                            <option value="actif">Actif</option>
                            <option value="conge">En congé</option>
                            <option value="absent">Absent</option>
                        </select>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addEmployeeModal')">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Planification des Horaires -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-calendar-alt"></i> Planification des Horaires</h2>
                <button class="close" onclick="closeModal('scheduleModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm">
                    <div class="form-group">
                        <label class="form-label">Semaine</label>
                        <input type="week" class="form-control" id="weekSelector" name="semaine" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Employé</label>
                        <select class="form-control" id="employeeSelector" name="employee_id" required>
                            <option value="">Sélectionner un employé</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['nom'] . ' ' . $emp['prenom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Horaires de la semaine</label>
                        <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; margin-top: 15px;">
                            <div style="font-weight: bold; text-align: center;">Lun</div>
                            <div style="font-weight: bold; text-align: center;">Mar</div>
                            <div style="font-weight: bold; text-align: center;">Mer</div>
                            <div style="font-weight: bold; text-align: center;">Jeu</div>
                            <div style="font-weight: bold; text-align: center;">Ven</div>
                            <div style="font-weight: bold; text-align: center;">Sam</div>
                            <div style="font-weight: bold; text-align: center;">Dim</div>
                            
                            <div>
                                <input type="time" class="form-control" name="horaires[1][debut]" placeholder="Début">
                                <input type="time" class="form-control" name="horaires[1][fin]" placeholder="Fin" style="margin-top: 5px;">
                            </div>
                            <div>
                                <input type="time" class="form-control" name="horaires[2][debut]" placeholder="Début">
                                <input type="time" class="form-control" name="horaires[2][fin]" placeholder="Fin" style="margin-top: 5px;">
                            </div>
                            <div>
                                <input type="time" class="form-control" name="horaires[3][debut]" placeholder="Début">
                                <input type="time" class="form-control" name="horaires[3][fin]" placeholder="Fin" style="margin-top: 5px;">
                            </div>
                            <div>
                                <input type="time" class="form-control" name="horaires[4][debut]" placeholder="Début">
                                <input type="time" class="form-control" name="horaires[4][fin]" placeholder="Fin" style="margin-top: 5px;">
                            </div>
                            <div>
                                <input type="time" class="form-control" name="horaires[5][debut]" placeholder="Début">
                                <input type="time" class="form-control" name="horaires[5][fin]" placeholder="Fin" style="margin-top: 5px;">
                            </div>
                            <div>
                                <input type="time" class="form-control" name="horaires[6][debut]" placeholder="Début">
                                <input type="time" class="form-control" name="horaires[6][fin]" placeholder="Fin" style="margin-top: 5px;">
                            </div>
                            <div>
                                <input type="time" class="form-control" name="horaires[7][debut]" placeholder="Début">
                                <input type="time" class="form-control" name="horaires[7][fin]" placeholder="Fin" style="margin-top: 5px;">
                            </div>
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer Planning
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('scheduleModal')">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Détails Employé -->
    <div id="viewEmployeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user"></i> Détails de l'Employé</h2>
                <button class="close" onclick="closeModal('viewEmployeeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="employeeDetails">
                    <!-- Contenu généré dynamiquement -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let searchTimeout;
        let isEditing = false;

        // Fonction pour afficher les alertes
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                ${message}
                <button style="float: right; background: none; border: none; font-size: 18px; cursor: pointer;" onclick="this.parentElement.remove()">&times;</button>
            `;
            alertContainer.appendChild(alert);
            
            // Auto-supprimer après 5 secondes
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.remove();
                }
            }, 5000);
        }

        // Fonction pour la recherche avec délai
        function debounceSearch(searchTerm) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const form = document.getElementById('filterForm');
                const searchInput = form.querySelector('input[name="search"]');
                searchInput.value = searchTerm;
                form.submit();
            }, 500);
        }

        // Fonctions pour les modals
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Réinitialiser le formulaire si c'est le modal d'employé
            if (modalId === 'addEmployeeModal') {
                document.getElementById('employeeForm').reset();
                document.getElementById('employeeId').value = '';
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Ajouter un Nouvel Employé';
                document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Enregistrer';
                document.getElementById('statutGroup').style.display = 'none';
                isEditing = false;
            }
        }

        // Fermer le modal en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Fonction pour voir les détails d'un employé
        function viewEmployee(id) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_employee&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const employee = data.employee;
                    const detailsHtml = `
                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start;">
                            <div style="text-align: center;">
                                <img src="${employee.photo || 'https://i.pravatar.cc/150?u=' + encodeURIComponent(employee.nom)}" 
                                     alt="${employee.nom} ${employee.prenom}" 
                                     style="width: 120px; height: 120px; border-radius: 50%; border: 4px solid #667eea;">
                                <h3 style="margin: 15px 0 5px; color: #2c3e50;">${employee.nom} ${employee.prenom}</h3>
                                <p style="color: #7f8c8d; font-size: 0.9rem;">ID: ${employee.id.toString().padStart(3, '0')}</p>
                            </div>
                            <div>
                                <div class="form-group">
                                    <strong>Poste:</strong> ${employee.poste.charAt(0).toUpperCase() + employee.poste.slice(1)}
                                </div>
                                <div class="form-group">
                                    <strong>Statut:</strong> 
                                    <span class="status-badge status-${employee.statut}">
                                        ${employee.statut === 'actif' ? 'Actif' : employee.statut === 'conge' ? 'En congé' : 'Absent'}
                                    </span>
                                </div>
                                <div class="form-group">
                                    <strong>Téléphone:</strong> ${employee.telephone}
                                </div>
                                <div class="form-group">
                                    <strong>Email:</strong> ${employee.email}
                                </div>
                                <div class="form-group">
                                    <strong>Date d'embauche:</strong> ${new Date(employee.date_embauche).toLocaleDateString('fr-FR')}
                                </div>
                                <div class="form-group">
                                    <strong>Salaire:</strong> <span class="salary-info">${parseFloat(employee.salaire_horaire).toFixed(2)}€/h</span>
                                </div>
                            </div>
                        </div>
                    `;
                    document.getElementById('employeeDetails').innerHTML = detailsHtml;
                    openModal('viewEmployeeModal');
                } else {
                    showAlert('Erreur lors de la récupération des détails: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Erreur de connexion: ' + error.message, 'error');
            });
        }

        // Fonction pour modifier un employé
        function editEmployee(id) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_employee&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const employee = data.employee;
                    
                    // Remplir le formulaire
                    document.getElementById('employeeId').value = employee.id;
                    document.getElementById('employeeNom').value = employee.nom;
                    document.getElementById('employeePrenom').value = employee.prenom;
                    document.getElementById('employeePoste').value = employee.poste;
                    document.getElementById('employeeTelephone').value = employee.telephone;
                    document.getElementById('employeeEmail').value = employee.email;
                    document.getElementById('employeeDateEmbauche').value = employee.date_embauche;
                    document.getElementById('employeeSalaire').value = employee.salaire_horaire;
                    document.getElementById('employeeStatut').value = employee.statut;
                    
                    // Modifier l'apparence du modal
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Modifier l\'Employé';
                    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Mettre à jour';
                    document.getElementById('statutGroup').style.display = 'block';
                    
                    isEditing = true;
                    openModal('addEmployeeModal');
                } else {
                    showAlert('Erreur lors de la récupération des données: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Erreur de connexion: ' + error.message, 'error');
            });
        }

        // Fonction pour supprimer un employé
        function deleteEmployee(id, name) {
            if (confirm(`Êtes-vous sûr de vouloir désactiver ${name} ?`)) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_employee&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showAlert('Erreur: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Erreur de connexion: ' + error.message, 'error');
                });
            }
        }

        // Fonction pour générer un rapport
        function generateReport() {
            showAlert('Génération du rapport en cours...', 'success');
            
            // Simuler la génération d'un rapport
            setTimeout(() => {
                const reportData = {
                    totalEmployees: <?php echo $total_employees; ?>,
                    presentToday: <?php echo $present_today; ?>,
                    newThisMonth: <?php echo $new_this_month; ?>,
                    attendanceRate: <?php echo $attendance_rate; ?>
                };
                
                let reportContent = `RAPPORT DE GESTION DES EMPLOYÉS\n`;
                reportContent += `Date: ${new Date().toLocaleDateString('fr-FR')}\n\n`;
                reportContent += `STATISTIQUES GÉNÉRALES:\n`;
                reportContent += `- Total employés: ${reportData.totalEmployees}\n`;
                reportContent += `- Présents aujourd'hui: ${reportData.presentToday}\n`;
                reportContent += `- Nouveaux ce mois: ${reportData.newThisMonth}\n`;
                reportContent += `- Taux de présence: ${reportData.attendanceRate}%\n\n`;
                reportContent += `LISTE DES EMPLOYÉS:\n`;
                
                <?php foreach ($employees as $emp): ?>
                reportContent += `- <?php echo htmlspecialchars($emp['nom'] . ' ' . $emp['prenom']); ?> (<?php echo ucfirst($emp['poste']); ?>) - <?php echo ucfirst($emp['statut']); ?>\n`;
                <?php endforeach; ?>
                
                // Créer et télécharger le fichier
                const blob = new Blob([reportContent], { type: 'text/plain' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `rapport_employes_${new Date().toISOString().split('T')[0]}.txt`;
                a.click();
                window.URL.revokeObjectURL(url);
                
                showAlert('Rapport généré et téléchargé avec succès!');
            }, 2000);
        }

        // Gestion du formulaire d'employé (ajout/modification)
        document.getElementById('employeeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const action = isEditing ? 'update_employee' : 'add_employee';
            formData.append('action', action);
            
            // Désactiver le bouton pendant la requête
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
            submitBtn.disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message);
                    closeModal('addEmployeeModal');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showAlert('Erreur: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Erreur de connexion: ' + error.message, 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Gestion du formulaire de planification
        document.getElementById('scheduleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'save_schedule');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message);
                    closeModal('scheduleModal');
                    document.getElementById('scheduleForm').reset();
                } else {
                    showAlert('Erreur: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Erreur de connexion: ' + error.message, 'error');
            });
        });

        // Initialiser la semaine courante dans le sélecteur
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const year = now.getFullYear();
            const week = Math.floor((now - new Date(year, 0, 1)) / (7 * 24 * 60 * 60 * 1000)) + 1;
            document.getElementById('weekSelector').value = `${year}-W${week.toString().padStart(2, '0')}`;
        });

        // Gestion des erreurs globales
        window.addEventListener('error', function(e) {
            console.error('Erreur JavaScript:', e.error);
            showAlert('Une erreur inattendue s\'est produite. Veuillez actualiser la page.', 'error');
        });
    </script>
</body>
</html>