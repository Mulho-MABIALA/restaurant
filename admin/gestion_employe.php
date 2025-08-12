<?php
/**
 * GESTION DES EMPLOYÉS - RESTAURANT ADMIN
 * =====================================
 * 
 * Cette page gère la liste des employés avec les fonctionnalités suivantes :
 * - Ajout/modification/suppression d'employés
 * - Upload de photos
 * - Gestion des heures de travail
 * - Planification des horaires
 * - Export PDF/Excel
 * - Interface responsive
 */

// Configuration des erreurs PHP pour le développement
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log des données POST pour debug
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data: " . print_r($_POST, true));
}

session_start();
require_once '../config.php';

/**
 * TRAITEMENT DES ACTIONS AJAX
 * ==========================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        
        /**
         * AJOUT D'UN NOUVEL EMPLOYÉ
         */
        case 'add_employee':
            try {
                // Validation des champs requis
                $required_fields = ['nom', 'prenom', 'poste', 'telephone', 'email', 'date_embauche', 'salaire', 'heures_travail'];
                foreach ($required_fields as $field) {
                    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                        throw new Exception("Le champ '$field' est requis");
                    }
                }

                // Vérification de l'unicité de l'email
                $stmt = $conn->prepare("SELECT COUNT(*) FROM employes WHERE email = ?");
                $stmt->execute([$_POST['email']]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Un employé avec cet email existe déjà");
                }

                // Gestion de l'upload de photo
                $photo_url = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $photo_url = handlePhotoUpload($_FILES['photo']);
                } else {
                    // Photo par défaut si aucun upload
                    $photo_url = "https://i.pravatar.cc/150?u=" . urlencode($_POST['nom'] . $_POST['prenom']);
                }

                // Insertion de l'employé en base
                $stmt = $conn->prepare("
                    INSERT INTO employes (nom, prenom, poste, telephone, email, date_embauche, 
                                        salaire_horaire, heures_travail, statut, photo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'actif', ?)
                ");
                $stmt->execute([
                    trim($_POST['nom']),
                    trim($_POST['prenom']),
                    $_POST['poste'],
                    $_POST['telephone'],
                    $_POST['email'],
                    $_POST['date_embauche'],
                    floatval($_POST['salaire']),
                    intval($_POST['heures_travail']),
                    $photo_url
                ]);

                $employee_id = $conn->lastInsertId();

                // Génération du QR code
                generateQRCode($employee_id);

                echo json_encode([
                    'success' => true,
                    'message' => 'Employé ajouté avec succès',
                    'employee_id' => $employee_id
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();

        /**
         * MISE À JOUR D'UN EMPLOYÉ
         */
        case 'update_employee':
            try {
                if (!isset($_POST['id']) || empty($_POST['id'])) {
                    throw new Exception("ID employé manquant");
                }

                $required_fields = ['nom', 'prenom', 'poste', 'telephone', 'email', 'salaire', 'heures_travail', 'statut'];
                foreach ($required_fields as $field) {
                    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                        throw new Exception("Le champ '$field' est requis");
                    }
                }

                // Vérification email unique (sauf pour l'employé actuel)
                $stmt = $conn->prepare("SELECT COUNT(*) FROM employes WHERE email = ? AND id != ?");
                $stmt->execute([$_POST['email'], $_POST['id']]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Un autre employé avec cet email existe déjà");
                }

                // Gestion de la photo si upload
                $photo_update = "";
                $params = [
                    trim($_POST['nom']),
                    trim($_POST['prenom']),
                    $_POST['poste'],
                    $_POST['telephone'],
                    $_POST['email'],
                    floatval($_POST['salaire']),
                    intval($_POST['heures_travail']),
                    $_POST['statut'],
                    $_POST['id']
                ];

                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $photo_url = handlePhotoUpload($_FILES['photo']);
                    $photo_update = ", photo = ?";
                    array_splice($params, -1, 0, $photo_url); // Insérer avant l'ID
                }

                $stmt = $conn->prepare("
                    UPDATE employes 
                    SET nom=?, prenom=?, poste=?, telephone=?, email=?, 
                        salaire_horaire=?, heures_travail=?, statut=? $photo_update 
                    WHERE id=?
                ");
                $result = $stmt->execute($params);

                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Employé modifié avec succès' : 'Erreur lors de la mise à jour'
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();

        /**
         * SUPPRESSION (DÉSACTIVATION) D'UN EMPLOYÉ
         */
        case 'delete_employee':
            try {
                if (!isset($_POST['id']) || empty($_POST['id'])) {
                    throw new Exception("ID employé manquant");
                }

                $stmt = $conn->prepare("UPDATE employes SET statut='inactif' WHERE id=?");
                $result = $stmt->execute([$_POST['id']]);

                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Employé désactivé avec succès' : 'Erreur lors de la désactivation'
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();

        /**
         * RÉCUPÉRATION D'UN EMPLOYÉ POUR MODIFICATION
         */
        case 'get_employee':
            try {
                if (!isset($_POST['id']) || empty($_POST['id'])) {
                    throw new Exception("ID employé manquant");
                }

                $stmt = $conn->prepare("SELECT * FROM employes WHERE id=?");
                $stmt->execute([$_POST['id']]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($employee) {
                    echo json_encode(['success' => true, 'employee' => $employee]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Employé non trouvé']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();

        /**
         * SAUVEGARDE DES HORAIRES DE TRAVAIL
         */
        case 'save_schedule':
            try {
                if (!isset($_POST['employee_id'], $_POST['semaine']) || 
                    empty($_POST['employee_id']) || empty($_POST['semaine'])) {
                    throw new Exception("Données manquantes pour la planification");
                }

                // Supprimer les anciens horaires de cette semaine
                $stmt = $conn->prepare("DELETE FROM horaires WHERE employee_id=? AND semaine=?");
                $stmt->execute([$_POST['employee_id'], $_POST['semaine']]);

                // Insérer les nouveaux horaires
                $stmt = $conn->prepare("
                    INSERT INTO horaires (employee_id, semaine, jour, heure_debut, heure_fin) 
                    VALUES (?, ?, ?, ?, ?)
                ");

                if (isset($_POST['horaires']) && is_array($_POST['horaires'])) {
                    foreach ($_POST['horaires'] as $jour => $horaire) {
                        if (!empty($horaire['debut']) && !empty($horaire['fin'])) {
                            $stmt->execute([
                                $_POST['employee_id'], 
                                $_POST['semaine'], 
                                $jour, 
                                $horaire['debut'], 
                                $horaire['fin']
                            ]);
                        }
                    }
                }

                echo json_encode(['success' => true, 'message' => 'Horaires sauvegardés avec succès']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();

        /**
         * RÉCUPÉRATION DES STATISTIQUES
         */
        case 'get_stats':
            try {
                // Total employés actifs
                $stmt = $conn->query("SELECT COUNT(*) FROM employes WHERE statut != 'inactif'");
                $total_employees = $stmt->fetchColumn();

                // Présents aujourd'hui (basé sur les horaires)
                $today = date('N');
                $current_week = date('Y-W');
                $stmt = $conn->prepare("
                    SELECT COUNT(DISTINCT h.employee_id) 
                    FROM horaires h 
                    JOIN employes e ON h.employee_id = e.id 
                    WHERE h.semaine = ? AND h.jour = ? AND e.statut = 'actif'
                ");
                $stmt->execute([$current_week, $today]);
                $present_today = $stmt->fetchColumn();

                // Nouveaux employés ce mois
                $stmt = $conn->query("
                    SELECT COUNT(*) FROM employes 
                    WHERE MONTH(date_embauche) = MONTH(CURRENT_DATE) 
                    AND YEAR(date_embauche) = YEAR(CURRENT_DATE) 
                    AND statut != 'inactif'
                ");
                $new_this_month = $stmt->fetchColumn();

                // Taux de présence
                $attendance_rate = $total_employees > 0 ? round(($present_today / $total_employees) * 100) : 0;

                echo json_encode([
                    'success' => true,
                    'stats' => [
                        'total_employees' => (int)$total_employees,
                        'present_today' => (int)$present_today,
                        'new_this_month' => (int)$new_this_month,
                        'attendance_rate' => (int)$attendance_rate
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur base de données: ' . $e->getMessage()]);
            }
            exit();

        /**
         * EXPORT DES DONNÉES
         */
        case 'export_data':
            try {
                $format = $_POST['format'] ?? 'excel';
                $employees = getAllEmployeesForExport($conn);
                
                if ($format === 'pdf') {
                    generatePDFExport($employees);
                } else {
                    generateExcelExport($employees);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();

        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            exit();
    }
}

/**
 * FONCTIONS UTILITAIRES
 * ====================
 */

/**
 * Gestion de l'upload des photos d'employés
 */
function handlePhotoUpload($file) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception("Type de fichier non autorisé. Utilisez JPG, PNG ou GIF.");
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception("Fichier trop volumineux. Maximum 5MB.");
    }
    
    $upload_dir = __DIR__ . '/../uploads/photos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('emp_') . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'uploads/photos/' . $filename;
    } else {
        throw new Exception("Erreur lors de l'upload du fichier.");
    }
}

/**
 * Génération du QR Code pour l'employé
 */
function generateQRCode($employee_id) {
    require_once '../phpqrcode/qrlib.php';
    
    $qr_content = "EMP-" . $employee_id;
    $qr_dir = __DIR__ . '/../qrcodes/';
    
    if (!is_dir($qr_dir)) {
        mkdir($qr_dir, 0777, true);
    }
    
    $qr_file = $qr_dir . $employee_id . ".png";
    QRcode::png($qr_content, $qr_file, QR_ECLEVEL_L, 4);
    
    // Mise à jour en base avec le chemin du QR code
    global $conn;
    $stmt = $conn->prepare("UPDATE employes SET qr_code = ? WHERE id = ?");
    $stmt->execute(['qrcodes/' . $employee_id . '.png', $employee_id]);
}

/**
 * Récupération de tous les employés pour l'export
 */
function getAllEmployeesForExport($conn) {
    $stmt = $conn->query("
        SELECT e.*, 
               CASE 
                 WHEN e.statut = 'actif' THEN 'Actif'
                 WHEN e.statut = 'conge' THEN 'En congé'
                 WHEN e.statut = 'absent' THEN 'Absent'
                 ELSE 'Inactif'
               END as statut_label
        FROM employes e 
        WHERE e.statut != 'inactif'
        ORDER BY e.nom, e.prenom
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * RÉCUPÉRATION DES DONNÉES POUR L'AFFICHAGE
 * =========================================
 */

// Statistiques générales
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM employes WHERE statut != 'inactif'");
    $total_employees = $stmt->fetchColumn();

    $today = date('N');
    $current_week = date('Y-W');
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT h.employee_id) 
        FROM horaires h 
        JOIN employes e ON h.employee_id = e.id 
        WHERE h.semaine = ? AND h.jour = ? AND e.statut = 'actif'
    ");
    $stmt->execute([$current_week, $today]);
    $present_today = $stmt->fetchColumn();

    $stmt = $conn->query("
        SELECT COUNT(*) FROM employes 
        WHERE MONTH(date_embauche) = MONTH(CURRENT_DATE) 
        AND YEAR(date_embauche) = YEAR(CURRENT_DATE)
        AND statut != 'inactif'
    ");
    $new_this_month = $stmt->fetchColumn();

    $attendance_rate = $total_employees > 0 ? round(($present_today / $total_employees) * 100) : 0;
} catch (Exception $e) {
    $total_employees = $present_today = $new_this_month = $attendance_rate = 0;
}

// Récupération des employés avec filtres
$where_clauses = ["e.statut != 'inactif'"];
$params = [];

// Filtres GET
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
    $search = '%' . $_GET['search'] . '%';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$where_sql = implode(' AND ', $where_clauses);

try {
    $stmt = $conn->prepare("
        SELECT e.*, 
               CASE 
                 WHEN e.statut = 'actif' THEN 'Actif'
                 WHEN e.statut = 'conge' THEN 'En congé'
                 WHEN e.statut = 'absent' THEN 'Absent'
                 ELSE 'Inactif'
               END as statut_label
        FROM employes e 
        WHERE $where_sql 
        ORDER BY e.nom, e.prenom
    ");
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Employés - Restaurant Admin</title>
    
    <!-- Icônes Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Styles personnalisés -->
    <style>
        /**
         * STYLES GÉNÉRAUX
         * ===============
         */
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
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        /**
         * EN-TÊTE
         * =======
         */
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

        /**
         * STATISTIQUES
         * ============
         */
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
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea, #764ba2);
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
            font-weight: 500;
        }

        /**
         * BARRE D'ACTIONS
         * ===============
         */
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
            font-size: 0.9rem;
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

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /**
         * FILTRES ET RECHERCHE
         * ====================
         */
        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-select, .search-box {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .filter-select:focus, .search-box:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box {
            min-width: 250px;
        }

        /**
         * TABLEAU DES EMPLOYÉS
         * ====================
         */
        .employees-section {
            padding: 30px;
        }

        .view-toggle {
            margin-bottom: 20px;
            text-align: center;
        }

        .view-toggle .btn {
            margin: 0 5px;
        }

        .view-toggle .btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        /* Vue tableau */
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
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
            margin-bottom: 3px;
        }

        .employee-id {
            font-size: 0.8rem;
            color: #7f8c8d;
        }

        .status-badge {
            padding: 6px 12px;
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

        .salary-info {
            font-weight: 600;
            color: #28a745;
        }

        .hours-info {
            font-weight: 600;
            color: #6f42c1;
        }

        /* Vue cartes */
        .cards-container {
            display: none;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }

        .cards-container.active {
            display: grid;
        }

        .employee-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .employee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .card-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid white;
            margin-bottom: 10px;
        }

        .card-body {
            padding: 20px;
        }

        .card-info {
            margin-bottom: 10px;
        }

        .card-info strong {
            color: #2c3e50;
        }

        .card-actions {
            border-top: 1px solid #eee;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        /**
         * MODAUX
         * ======
         */
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
            margin: 3% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
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

        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }

        .close:hover {
            opacity: 0.7;
        }

        /**
         * FORMULAIRES
         * ===========
         */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
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
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .file-input-container {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            display: none;
        }

        .file-input-label {
            display: block;
            padding: 12px 15px;
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .file-input-label:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .photo-preview {
            margin-top: 10px;
            text-align: center;
        }

        .photo-preview img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 10px;
            border: 3px solid #667eea;
        }

        /**
         * PLANIFICATION DES HORAIRES
         * ==========================
         */
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .day-header {
            font-weight: bold;
            padding: 10px 5px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 5px;
            text-align: center;
            font-size: 0.9rem;
        }

        .time-input-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .time-input-group input {
            font-size: 0.8rem;
            padding: 8px;
        }

        /**
         * ALERTES
         * =======
         */
        .alert {
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-info {
            background-color: #cce7ff;
            color: #004085;
            border: 1px solid #b6d7ff;
        }

        /**
         * RESPONSIVE DESIGN
         * =================
         */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .container {
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
                gap: 15px;
            }

            .btn-group {
                justify-content: center;
            }

            .filters {
                justify-content: center;
            }

            .search-box {
                min-width: auto;
                width: 100%;
            }

            .schedule-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .day-header {
                grid-column: 1;
            }

            .cards-container {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                margin: 5% auto;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 20px;
            }

            .header h1 {
                font-size: 1.8rem;
            }

            .stat-card {
                padding: 20px;
            }

            .modal-body {
                padding: 20px;
            }
        }

        /**
         * ANIMATIONS ET EFFETS
         * ====================
         */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- EN-TÊTE -->
        <div class="header">
            <h1><i class="fas fa-users"></i> Gestion des Employés</h1>
            <p>Tableau de bord administrateur - Restaurant Le Gourmand</p>
        </div>

        <!-- CONTAINER D'ALERTES -->
        <div id="alertContainer"></div>

        <!-- STATISTIQUES -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value" id="total-employees"><?php echo $total_employees; ?></div>
                <div class="stat-label">Total Employés</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-value" id="present-today"><?php echo $present_today; ?></div>
                <div class="stat-label">Présents Aujourd'hui</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
                <div class="stat-value" id="new-this-month"><?php echo $new_this_month; ?></div>
                <div class="stat-label">Nouveaux ce Mois</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-percentage"></i></div>
                <div class="stat-value" id="attendance-rate"><?php echo $attendance_rate; ?>%</div>
                <div class="stat-label">Taux de Présence</div>
            </div>
        </div>

        <!-- BARRE D'ACTIONS -->
        <div class="actions-bar">
            <div class="btn-group">
                <button class="btn btn-primary" onclick="openModal('addEmployeeModal')">
                    <i class="fas fa-user-plus"></i> Ajouter Employé
                </button>
                <button class="btn btn-secondary" onclick="openModal('scheduleModal')">
                    <i class="fas fa-calendar-alt"></i> Planifier Horaires
                </button>
                <div class="dropdown" style="position: relative; display: inline-block;">
                    <button class="btn btn-success" onclick="toggleExportMenu()">
                        <i class="fas fa-file-export"></i> Exporter <i class="fas fa-chevron-down"></i>
                    </button>
                    <div id="exportMenu" class="dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; background: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 100;">
                        <button class="dropdown-item" onclick="exportData('excel')" style="display: block; width: 100%; padding: 10px 15px; border: none; background: none; text-align: left; cursor: pointer;">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="dropdown-item" onclick="exportData('pdf')" style="display: block; width: 100%; padding: 10px 15px; border: none; background: none; text-align: left; cursor: pointer;">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- FILTRES ET RECHERCHE -->
            <div class="filters">
                <form method="GET" id="filterForm" style="display: contents;">
                    <select class="filter-select" name="poste" onchange="this.form.submit()">
                        <option value="">Tous les postes</option>
                        <option value="serveur" <?php echo (isset($_GET['poste']) && $_GET['poste'] === 'serveur') ? 'selected' : ''; ?>>Serveur</option>
                        <option value="cuisinier" <?php echo (isset($_GET['poste']) && $_GET['poste'] === 'cuisinier') ? 'selected' : ''; ?>>Cuisinier</option>
                        <option value="manager" <?php echo (isset($_GET['poste']) && $_GET['poste'] === 'manager') ? 'selected' : ''; ?>>Manager</option>
                        <option value="barman" <?php echo (isset($_GET['poste']) && $_GET['poste'] === 'barman') ? 'selected' : ''; ?>>Barman</option>
                        <option value="receptionniste" <?php echo (isset($_GET['poste']) && $_GET['poste'] === 'receptionniste') ? 'selected' : ''; ?>>Réceptionniste</option>
                    </select>
                    <select class="filter-select" name="statut" onchange="this.form.submit()">
                        <option value="">Tous les statuts</option>
                        <option value="actif" <?php echo (isset($_GET['statut']) && $_GET['statut'] === 'actif') ? 'selected' : ''; ?>>Actif</option>
                        <option value="conge" <?php echo (isset($_GET['statut']) && $_GET['statut'] === 'conge') ? 'selected' : ''; ?>>En congé</option>
                        <option value="absent" <?php echo (isset($_GET['statut']) && $_GET['statut'] === 'absent') ? 'selected' : ''; ?>>Absent</option>
                    </select>
                    <input type="text" class="search-box" name="search" placeholder="🔍 Rechercher un employé..." 
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                           onkeyup="debounceSearch(this.value)">
                </form>
            </div>
        </div>

        <!-- SECTION DES EMPLOYÉS -->
        <div class="employees-section">
            <!-- TOGGLE DE VUE -->
            <div class="view-toggle">
                <button class="btn active" onclick="switchView('table')" id="tableViewBtn">
                    <i class="fas fa-table"></i> Vue Tableau
                </button>
                <button class="btn" onclick="switchView('cards')" id="cardsViewBtn">
                    <i class="fas fa-th-large"></i> Vue Cartes
                </button>
            </div>

            <!-- VUE TABLEAU -->
            <div class="table-container" id="tableView">
                <table>
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Poste</th>
                            <th>Statut</th>
                            <th>Contact</th>
                            <th>Embauche</th>
                            <th>Salaire</th>
                            <th>Heures/sem</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.3;"></i><br>
                                    Aucun employé trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td>
                                        <div class="employee-info">
                                            <img src="<?= htmlspecialchars($employee['photo'] ?? 'https://i.pravatar.cc/150?u=' . urlencode($employee['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" 
                                                 alt="<?= htmlspecialchars(($employee['nom'] ?? '') . ' ' . ($employee['prenom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" 
                                                 class="employee-photo"
                                                 onerror="this.src='https://i.pravatar.cc/150?u=<?= urlencode($employee['nom'] ?? '') ?>'">
                                            <div>
                                                <div class="employee-name"><?= htmlspecialchars(($employee['nom'] ?? '') . ' ' . ($employee['prenom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="employee-id">ID: <?= str_pad($employee['id'] ?? 0, 3, '0', STR_PAD_LEFT); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= ucfirst(htmlspecialchars($employee['poste'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($employee['statut'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= htmlspecialchars($employee['statut_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($employee['telephone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div style="font-size: 0.8rem; color: #7f8c8d;"><?= htmlspecialchars($employee['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td><?= !empty($employee['date_embauche']) ? date('d/m/Y', strtotime($employee['date_embauche'])) : ''; ?></td>
                                    <td>
                                        <span class="salary-info"><?= isset($employee['salaire_horaire']) ? number_format($employee['salaire_horaire'], 2) . '€/h' : ''; ?></span>
                                    </td>
                                    <td>
                                        <span class="hours-info"><?= isset($employee['heures_travail']) ? $employee['heures_travail'] . 'h' : '0h'; ?></span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info btn-sm" onclick="viewEmployee(<?= (int)($employee['id'] ?? 0); ?>)" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning btn-sm" onclick="editEmployee(<?= (int)($employee['id'] ?? 0); ?>)" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteEmployee(<?= (int)($employee['id'] ?? 0); ?>, '<?= htmlspecialchars(($employee['nom'] ?? '') . ' ' . ($employee['prenom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')" title="Désactiver">
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

            <!-- VUE CARTES -->
            <div class="cards-container" id="cardsView">
                <?php if (empty($employees)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #7f8c8d;">
                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.3;"></i><br>
                        Aucun employé trouvé
                    </div>
                <?php else: ?>
                    <?php foreach ($employees as $employee): ?>
                        <div class="employee-card">
                            <div class="card-header">
                                <img src="<?= htmlspecialchars($employee['photo'] ?? 'https://i.pravatar.cc/150?u=' . urlencode($employee['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="<?= htmlspecialchars(($employee['nom'] ?? '') . ' ' . ($employee['prenom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" 
                                     class="card-photo"
                                     onerror="this.src='https://i.pravatar.cc/150?u=<?= urlencode($employee['nom'] ?? '') ?>'">
                                <h3><?= htmlspecialchars(($employee['nom'] ?? '') . ' ' . ($employee['prenom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p>ID: <?= str_pad($employee['id'] ?? 0, 3, '0', STR_PAD_LEFT); ?></p>
                            </div>
                            <div class="card-body">
                                <div class="card-info">
                                    <strong>Poste:</strong> <?= ucfirst(htmlspecialchars($employee['poste'] ?? '', ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                                <div class="card-info">
                                    <strong>Statut:</strong> 
                                    <span class="status-badge status-<?= htmlspecialchars($employee['statut'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($employee['statut_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <div class="card-info">
                                    <strong>Téléphone:</strong> <?= htmlspecialchars($employee['telephone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="card-info">
                                    <strong>Email:</strong> <?= htmlspecialchars($employee['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="card-info">
                                    <strong>Embauche:</strong> <?= !empty($employee['date_embauche']) ? date('d/m/Y', strtotime($employee['date_embauche'])) : ''; ?>
                                </div>
                                <div class="card-info">
                                    <strong>Salaire:</strong> 
                                    <span class="salary-info"><?= isset($employee['salaire_horaire']) ? number_format($employee['salaire_horaire'], 2) . '€/h' : ''; ?></span>
                                </div>
                                <div class="card-info">
                                    <strong>Heures/semaine:</strong> 
                                    <span class="hours-info"><?= isset($employee['heures_travail']) ? $employee['heures_travail'] . 'h' : '0h'; ?></span>
                                </div>
                            </div>
                            <div class="card-actions">
                                <button class="btn btn-info btn-sm" onclick="viewEmployee(<?= (int)($employee['id'] ?? 0); ?>)">
                                    <i class="fas fa-eye"></i> Voir
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="editEmployee(<?= (int)($employee['id'] ?? 0); ?>)">
                                    <i class="fas fa-edit"></i> Modifier
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteEmployee(<?= (int)($employee['id'] ?? 0); ?>, '<?= htmlspecialchars(($employee['nom'] ?? '') . ' ' . ($employee['prenom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')">
                                    <i class="fas fa-trash"></i> Supprimer
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL AJOUTER/MODIFIER EMPLOYÉ -->
    <div id="addEmployeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-user-plus"></i> Ajouter un Nouvel Employé</h2>
                <button class="close" onclick="closeModal('addEmployeeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="employeeForm" enctype="multipart/form-data">
                    <input type="hidden" id="employeeId" name="id">
                    
                    <div class="form-grid">
                        <!-- INFORMATIONS PERSONNELLES -->
                        <div class="form-group">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="nom" id="employeeNom" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Prénom *</label>
                            <input type="text" class="form-control" name="prenom" id="employeePrenom" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Poste *</label>
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
                            <label class="form-label">Téléphone *</label>
                            <input type="tel" class="form-control" name="telephone" id="employeeTelephone" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="employeeEmail" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Date d'embauche *</label>
                            <input type="date" class="form-control" name="date_embauche" id="employeeDateEmbauche" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Salaire (€/heure) *</label>
                            <input type="number" class="form-control" name="salaire" id="employeeSalaire" step="0.50" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Heures de travail/semaine *</label>
                            <input type="number" class="form-control" name="heures_travail" id="employeeHeures" min="1" max="60" required>
                        </div>
                    </div>
                    
                    <!-- PHOTO -->
                    <div class="form-group">
                        <label class="form-label">Photo de profil</label>
                        <div class="file-input-container">
                            <input type="file" id="photoInput" name="photo" class="file-input" accept="image/*">
                            <label for="photoInput" class="file-input-label">
                                <i class="fas fa-camera"></i> Choisir une photo (JPG, PNG, GIF - Max 5MB)
                            </label>
                        </div>
                        <div id="photoPreview" class="photo-preview" style="display: none;"></div>
                    </div>
                    
                    <!-- STATUT (SEULEMENT EN MODIFICATION) -->
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
                            <i class="fas fa-times"></i> Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL PLANIFICATION DES HORAIRES -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-calendar-alt"></i> Planification des Horaires</h2>
                <button class="close" onclick="closeModal('scheduleModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm">
                    <div class="form-group">
                        <label class="form-label">Semaine *</label>
                        <input type="week" class="form-control" id="weekSelector" name="semaine" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Employé *</label>
                        <select class="form-control" id="employeeSelector" name="employee_id" required>
                            <option value="">Sélectionner un employé</option>
                            <?php foreach ($employees as $emp): ?>
                                <?php if ($emp['statut'] === 'actif'): ?>
                                    <option value="<?php echo $emp['id']; ?>">
                                        <?php echo htmlspecialchars($emp['nom'] . ' ' . $emp['prenom'] . ' - ' . ucfirst($emp['poste'])); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Horaires de la semaine</label>
                        <div class="schedule-grid">
                            <div class="day-header">Lun</div>
                            <div class="day-header">Mar</div>
                            <div class="day-header">Mer</div>
                            <div class="day-header">Jeu</div>
                            <div class="day-header">Ven</div>
                            <div class="day-header">Sam</div>
                            <div class="day-header">Dim</div>
                            
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <div class="time-input-group">
                                    <input type="time" class="form-control" name="horaires[<?php echo $i; ?>][debut]" placeholder="Début">
                                    <input type="time" class="form-control" name="horaires[<?php echo $i; ?>][fin]" placeholder="Fin">
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer Planning
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('scheduleModal')">
                            <i class="fas fa-times"></i> Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL DÉTAILS EMPLOYÉ -->
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

    <!-- SCRIPTS JAVASCRIPT -->
    <script>
        /**
         * VARIABLES GLOBALES
         * ==================
         */
        let searchTimeout;
        let isEditing = false;
        let currentView = 'table';

        /**
         * FONCTIONS D'AFFICHAGE DES ALERTES
         * =================================
         */
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} fade-in`;
            
            const iconMap = {
                'success': 'check-circle',
                'error': 'exclamation-triangle',
                'warning': 'exclamation-circle',
                'info': 'info-circle'
            };
            
            alert.innerHTML = `
                <i class="fas fa-${iconMap[type] || 'info-circle'}"></i>
                <span>${message}</span>
                <button style="margin-left: auto; background: none; border: none; font-size: 18px; cursor: pointer; opacity: 0.7;" 
                        onclick="this.parentElement.remove()" title="Fermer">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            alertContainer.appendChild(alert);
            
            // Auto-supprimer après 5 secondes
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }

        /**
         * GESTION DES STATISTIQUES
         * =========================
         */
        function updateStats() {
            const submitData = new FormData();
            submitData.append('action', 'get_stats');

            fetch(window.location.href, {
                method: 'POST',
                body: submitData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const stats = data.stats;
                    animateNumber('total-employees', stats.total_employees);
                    animateNumber('present-today', stats.present_today);
                    animateNumber('new-this-month', stats.new_this_month);
                    animateNumber('attendance-rate', stats.attendance_rate, '%');
                }
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour des stats:', error);
            });
        }

        /**
         * ANIMATION DES CHIFFRES
         */
        function animateNumber(elementId, targetValue, suffix = '') {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            const currentValue = parseInt(element.textContent) || 0;
            const increment = targetValue > currentValue ? 1 : -1;
            const duration = 1000;
            const steps = Math.abs(targetValue - currentValue);
            const stepDuration = steps > 0 ? duration / steps : 0;
            
            let current = currentValue;
            
            const timer = setInterval(() => {
                if ((increment > 0 && current >= targetValue) || 
                    (increment < 0 && current <= targetValue)) {
                    element.textContent = targetValue + suffix;
                    clearInterval(timer);
                } else {
                    current += increment;
                    element.textContent = current + suffix;
                }
            }, stepDuration);
        }

        /**
         * GESTION DES VUES (TABLEAU/CARTES)
         * ==================================
         */
        function switchView(viewType) {
            const tableView = document.getElementById('tableView');
            const cardsView = document.getElementById('cardsView');
            const tableBtn = document.getElementById('tableViewBtn');
            const cardsBtn = document.getElementById('cardsViewBtn');
            
            if (viewType === 'table') {
                tableView.style.display = 'block';
                cardsView.classList.remove('active');
                tableBtn.classList.add('active');
                cardsBtn.classList.remove('active');
                currentView = 'table';
            } else {
                tableView.style.display = 'none';
                cardsView.classList.add('active');
                cardsBtn.classList.add('active');
                tableBtn.classList.remove('active');
                currentView = 'cards';
            }
            
            // Sauvegarder la préférence
            localStorage.setItem('preferredView', viewType);
        }

        /**
         * GESTION DES MODAUX
         * ==================
         */
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Animations d'entrée
            const modalContent = modal.querySelector('.modal-content');
            modalContent.style.transform = 'scale(0.7)';
            modalContent.style.opacity = '0';
            
            setTimeout(() => {
                modalContent.style.transform = 'scale(1)';
                modalContent.style.opacity = '1';
                modalContent.style.transition = 'all 0.3s ease';
            }, 50);
            
            // Initialisation spécifique aux modaux
            if (modalId === 'scheduleModal') {
                initializeWeekSelector();
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            const modalContent = modal.querySelector('.modal-content');
            
            modalContent.style.transform = 'scale(0.7)';
            modalContent.style.opacity = '0';
            
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }, 300);
            
            // Réinitialisation des formulaires
            if (modalId === 'addEmployeeModal') {
                resetEmployeeForm();
            } else if (modalId === 'scheduleModal') {
                document.getElementById('scheduleForm').reset();
            }
        }

        /**
         * INITIALISATION DU SÉLECTEUR DE SEMAINE
         */
        function initializeWeekSelector() {
            const now = new Date();
            const year = now.getFullYear();
            let week = getWeekNumber(now);
            document.getElementById('weekSelector').value = `${year}-W${week.toString().padStart(2, '0')}`;
        }

        function getWeekNumber(date) {
            const firstDayOfYear = new Date(date.getFullYear(), 0, 1);
            const pastDaysOfYear = (date - firstDayOfYear) / 86400000;
            return Math.ceil((pastDaysOfYear + firstDayOfYear.getDay() + 1) / 7);
        }

        /**
         * GESTION DES FORMULAIRES
         * =======================
         */
        
        /**
         * RÉINITIALISATION DU FORMULAIRE EMPLOYÉ
         */
        function resetEmployeeForm() {
            document.getElementById('employeeForm').reset();
            document.getElementById('employeeId').value = '';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Ajouter un Nouvel Employé';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Enregistrer';
            document.getElementById('statutGroup').style.display = 'none';
            document.getElementById('photoPreview').style.display = 'none';
            isEditing = false;
            
            // Réinitialiser les styles de bordure
            const inputs = document.querySelectorAll('#employeeForm .form-control');
            inputs.forEach(input => {
                input.style.borderColor = '#e9ecef';
            });
        }

        /**
         * GESTION DE LA PRÉVISUALISATION DES PHOTOS
         */
        document.getElementById('photoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('photoPreview');
            
            if (file) {
                // Validation du type de fichier
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showAlert('Type de fichier non autorisé. Utilisez JPG, PNG ou GIF.', 'error');
                    e.target.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                // Validation de la taille (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    showAlert('Fichier trop volumineux. Maximum 5MB.', 'error');
                    e.target.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Prévisualisation" style="max-width: 150px; max-height: 150px; border-radius: 10px; border: 3px solid #667eea;">
                        <p style="margin-top: 10px; font-size: 0.9rem; color: #7f8c8d;">Prévisualisation de la photo</p>
                    `;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });

        /**
         * SOUMISSION DU FORMULAIRE EMPLOYÉ
         */
        document.getElementById('employeeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const action = isEditing ? 'update_employee' : 'add_employee';
            formData.append('action', action);
            
            // Validation côté client
            if (!validateEmployeeForm()) {
                return;
            }
            
            // Désactiver le bouton pendant la requête
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
            submitBtn.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeModal('addEmployeeModal');
                    updateStats();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('Erreur: ' + (data.message || 'Erreur inconnue'), 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('Erreur de connexion: ' + error.message, 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        /**
         * VALIDATION DU FORMULAIRE EMPLOYÉ
         */
        function validateEmployeeForm() {
            const requiredFields = [
                { id: 'employeeNom', name: 'Nom' },
                { id: 'employeePrenom', name: 'Prénom' },
                { id: 'employeePoste', name: 'Poste' },
                { id: 'employeeTelephone', name: 'Téléphone' },
                { id: 'employeeEmail', name: 'Email' },
                { id: 'employeeDateEmbauche', name: 'Date d\'embauche' },
                { id: 'employeeSalaire', name: 'Salaire' },
                { id: 'employeeHeures', name: 'Heures de travail' }
            ];
            
            let isValid = true;
            let errors = [];
            
            requiredFields.forEach(field => {
                const input = document.getElementById(field.id);
                const value = input.value.trim();
                
                if (!value) {
                    isValid = false;
                    input.style.borderColor = '#dc3545';
                    errors.push(field.name + ' est requis');
                } else {
                    input.style.borderColor = '#28a745';
                    
                    // Validations spécifiques
                    if (field.id === 'employeeEmail' && !validateEmail(value)) {
                        isValid = false;
                        input.style.borderColor = '#dc3545';
                        errors.push('Format email invalide');
                    }
                    
                    if (field.id === 'employeeSalaire' && (isNaN(value) || parseFloat(value) <= 0)) {
                        isValid = false;
                        input.style.borderColor = '#dc3545';
                        errors.push('Le salaire doit être un nombre positif');
                    }
                    
                    if (field.id === 'employeeHeures' && (isNaN(value) || parseInt(value) < 1 || parseInt(value) > 60)) {
                        isValid = false;
                        input.style.borderColor = '#dc3545';
                        errors.push('Les heures doivent être entre 1 et 60');
                    }
                    
                    if (field.id === 'employeeTelephone' && !validatePhone(value)) {
                        isValid = false;
                        input.style.borderColor = '#dc3545';
                        errors.push('Format téléphone invalide');
                    }
                }
            });
            
            if (!isValid) {
                showAlert('Erreurs de validation: ' + errors.join(', '), 'error');
            }
            
            return isValid;
        }

        /**
         * FONCTIONS DE VALIDATION
         */
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function validatePhone(phone) {
            const re = /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/;
            return re.test(phone) || phone.length >= 8;
        }

        /**
         * ACTIONS SUR LES EMPLOYÉS
         * ========================
         */
        
        /**
         * VOIR LES DÉTAILS D'UN EMPLOYÉ
         */
        function viewEmployee(id) {
            const submitData = new FormData();
            submitData.append('action', 'get_employee');
            submitData.append('id', id);

            fetch(window.location.href, {
                method: 'POST',
                body: submitData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.employee) {
                    displayEmployeeDetails(data.employee);
                } else {
                    showAlert('Erreur: ' + (data.message || 'Employé non trouvé'), 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('Erreur de connexion: ' + error.message, 'error');
            });
        }

        /**
         * AFFICHAGE DES DÉTAILS D'UN EMPLOYÉ
         */
        function displayEmployeeDetails(employee) {
            const detailsHtml = `
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; align-items: start;">
                    <div style="text-align: center;">
                        <img src="${employee.photo || 'https://i.pravatar.cc/150?u=' + encodeURIComponent(employee.nom)}" 
                             alt="${employee.nom} ${employee.prenom}" 
                             style="width: 150px; height: 150px; border-radius: 50%; border: 4px solid #667eea; margin-bottom: 15px;"
                             onerror="this.src='https://i.pravatar.cc/150?u=${encodeURIComponent(employee.nom)}'">
                        <h3 style="margin: 10px 0 5px; color: #2c3e50;">${employee.nom} ${employee.prenom}</h3>
                        <p style="color: #7f8c8d; font-size: 0.9rem; margin-bottom: 15px;">ID: ${employee.id.toString().padStart(3, '0')}</p>
                        
                        <!-- QR Code si disponible -->
                        ${employee.qr_code ? `
                            <div style="margin-top: 20px;">
                                <p style="font-weight: 600; margin-bottom: 10px;">QR Code:</p>
                                <img src="${employee.qr_code}" alt="QR Code" style="width: 120px; height: 120px; border: 2px solid #ddd; border-radius: 10px;">
                            </div>
                        ` : ''}
                    </div>
                    <div>
                        <div class="card-info" style="margin-bottom: 15px;">
                            <strong style="color: #2c3e50;">Poste:</strong> 
                            <span style="background: #e9ecef; padding: 5px 10px; border-radius: 15px; margin-left: 10px;">
                                ${employee.poste.charAt(0).toUpperCase() + employee.poste.slice(1)}
                            </span>
                        </div>
                        <div class="card-info" style="margin-bottom: 15px;">
                            <strong style="color: #2c3e50;">Statut:</strong> 
                            <span class="status-badge status-${employee.statut}" style="margin-left: 10px;">
                                ${employee.statut === 'actif' ? 'Actif' : employee.statut === 'conge' ? 'En congé' : 'Absent'}
                            </span>
                        </div>
                        <div class="card-info" style="margin-bottom: 15px;">
                            <strong style="color: #2c3e50;">Téléphone:</strong> 
                            <a href="tel:${employee.telephone}" style="color: #667eea; text-decoration: none; margin-left: 10px;">
                                <i class="fas fa-phone"></i> ${employee.telephone}
                            </a>
                        </div>
                        <div class="card-info" style="margin-bottom: 15px;">
                            <strong style="color: #2c3e50;">Email:</strong> 
                            <a href="mailto:${employee.email}" style="color: #667eea; text-decoration: none; margin-left: 10px;">
                                <i class="fas fa-envelope"></i> ${employee.email}
                            </a>
                        </div>
                        <div class="card-info" style="margin-bottom: 15px;">
                            <strong style="color: #2c3e50;">Date d'embauche:</strong> 
                            <span style="margin-left: 10px;">${new Date(employee.date_embauche).toLocaleDateString('fr-FR')}</span>
                        </div>
                        <div class="card-info" style="margin-bottom: 15px;">
                            <strong style="color: #2c3e50;">Salaire:</strong> 
                            <span class="salary-info" style="margin-left: 10px; font-size: 1.1rem;">
                                ${parseFloat(employee.salaire_horaire).toFixed(2)}€/h
                            </span>
                        </div>
                        <div class="card-info" style="margin-bottom: 15px;">
                            <strong style="color: #2c3e50;">Heures de travail:</strong> 
                            <span class="hours-info" style="margin-left: 10px; font-size: 1.1rem;">
                                ${employee.heures_travail || 0}h/semaine
                            </span>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('employeeDetails').innerHTML = detailsHtml;
            openModal('viewEmployeeModal');
        }

        /**
         * MODIFIER UN EMPLOYÉ
         */
        function editEmployee(id) {
            const submitData = new FormData();
            submitData.append('action', 'get_employee');
            submitData.append('id', id);

            fetch(window.location.href, {
                method: 'POST',
                body: submitData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.employee) {
                    populateEmployeeForm(data.employee);
                } else {
                    showAlert('Erreur: ' + (data.message || 'Employé non trouvé'), 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('Erreur de connexion: ' + error.message, 'error');
            });
        }

        /**
         * REMPLISSAGE DU FORMULAIRE AVEC LES DONNÉES EMPLOYÉ
         */
        function populateEmployeeForm(employee) {
            document.getElementById('employeeId').value = employee.id;
            document.getElementById('employeeNom').value = employee.nom || '';
            document.getElementById('employeePrenom').value = employee.prenom || '';
            document.getElementById('employeePoste').value = employee.poste || '';
            document.getElementById('employeeTelephone').value = employee.telephone || '';
            document.getElementById('employeeEmail').value = employee.email || '';
            document.getElementById('employeeDateEmbauche').value = employee.date_embauche || '';
            document.getElementById('employeeSalaire').value = employee.salaire_horaire || '';
            document.getElementById('employeeHeures').value = employee.heures_travail || '';
            document.getElementById('employeeStatut').value = employee.statut || 'actif';
            
            // Afficher la photo actuelle si elle existe
            if (employee.photo) {
                const preview = document.getElementById('photoPreview');
                preview.innerHTML = `
                    <img src="${employee.photo}" alt="Photo actuelle" style="max-width: 150px; max-height: 150px; border-radius: 10px; border: 3px solid #667eea;">
                    <p style="margin-top: 10px; font-size: 0.9rem; color: #7f8c8d;">Photo actuelle</p>
                `;
                preview.style.display = 'block';
            }
            
            // Modifier l'interface pour la modification
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit"></i> Modifier l\'Employé';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Mettre à jour';
            document.getElementById('statutGroup').style.display = 'block';
            
            isEditing = true;
            openModal('addEmployeeModal');
        }

        /**
         * SUPPRIMER (DÉSACTIVER) UN EMPLOYÉ
         */
        function deleteEmployee(id, name) {
            if (confirm(`Êtes-vous sûr de vouloir désactiver ${name} ?\n\nCette action changera son statut à "inactif" mais conservera ses données.`)) {
                const submitData = new FormData();
                submitData.append('action', 'delete_employee');
                submitData.append('id', id);

                fetch(window.location.href, {
                    method: 'POST',
                    body: submitData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        updateStats();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('Erreur: ' + (data.message || 'Erreur inconnue'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showAlert('Erreur de connexion: ' + error.message, 'error');
                });
            }
        }

        /**
         * GESTION DES HORAIRES
         * ====================
         */
        document.getElementById('scheduleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'save_schedule');
            
            const employeeId = document.getElementById('employeeSelector').value;
            const semaine = document.getElementById('weekSelector').value;
            
            if (!employeeId || !semaine) {
                showAlert('Veuillez sélectionner un employé et une semaine', 'error');
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeModal('scheduleModal');
                } else {
                    showAlert('Erreur: ' + (data.message || 'Erreur inconnue'), 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('Erreur de connexion: ' + error.message, 'error');
            });
        });

        /**
         * VALIDATION DES HEURES
         */
        document.addEventListener('change', function(e) {
            if (e.target.type === 'time' && e.target.name && e.target.name.includes('horaires')) {
                const nameMatch = e.target.name.match(/horaires\[(\d+)\]\[(debut|fin)\]/);
                if (nameMatch) {
                    const jour = nameMatch[1];
                    const type = nameMatch[2];
                    
                    if (type === 'fin') {
                        const debutInput = document.querySelector(`input[name="horaires[${jour}][debut]"]`);
                        const finInput = e.target;
                        
                        if (debutInput.value && finInput.value && debutInput.value >= finInput.value) {
                            showAlert('L\'heure de fin doit être postérieure à l\'heure de début', 'error');
                            finInput.value = '';
                            finInput.style.borderColor = '#dc3545';
                        } else {
                            finInput.style.borderColor = '#28a745';
                        }
                    }
                }
            }
        });

        /**
         * RECHERCHE ET FILTRES
         * ====================
         */
        function debounceSearch(searchTerm) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const form = document.getElementById('filterForm');
                const searchInput = form.querySelector('input[name="search"]');
                searchInput.value = searchTerm;
                form.submit();
            }, 500);
        }

        /**
         * EXPORT DES DONNÉES
         * ==================
         */
        function toggleExportMenu() {
            const menu = document.getElementById('exportMenu');
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }

        function exportData(format) {
            document.getElementById('exportMenu').style.display = 'none';
            
            const submitData = new FormData();
            submitData.append('action', 'export_data');
            submitData.append('format', format);
            
            showAlert(`Export ${format.toUpperCase()} en cours...`, 'info');
            
            fetch(window.location.href, {
                method: 'POST',
                body: submitData
            })
            .then(response => {
                if (format === 'excel') {
                    return response.blob();
                } else {
                    return response.json();
                }
            })
            .then(data => {
                if (format === 'excel') {
                    // Télécharger le fichier Excel
                    const blob = new Blob([data], { 
                        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
                    });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `employes_${new Date().toISOString().split('T')[0]}.xlsx`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    showAlert('Export Excel téléchargé avec succès!', 'success');
                } else {
                    if (data.success) {
                        showAlert('Export PDF généré avec succès!', 'success');
                    } else {showAlert('Erreur lors de l\'export PDF: ' + (data.message || 'Erreur inconnue'), 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Erreur export:', error);
                showAlert('Erreur lors de l\'export: ' + error.message, 'error');
            });
        }

        /**
         * FERMETURE DU MENU D'EXPORT AU CLIC EXTÉRIEUR
         */
        document.addEventListener('click', function(e) {
            const exportMenu = document.getElementById('exportMenu');
            const exportButton = e.target.closest('button');
            
            if (exportMenu && exportMenu.style.display === 'block' && 
                (!exportButton || !exportButton.textContent.includes('Exporter'))) {
                exportMenu.style.display = 'none';
            }
        });

        /**
         * GESTION DES RACCOURCIS CLAVIER
         * ==============================
         */
        document.addEventListener('keydown', function(e) {
            // Échap pour fermer les modaux
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (modal.style.display === 'block') {
                        const modalId = modal.id;
                        closeModal(modalId);
                    }
                });
            }
            
            // Ctrl+N pour ajouter un employé
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openModal('addEmployeeModal');
            }
            
            // Ctrl+H pour ouvrir la planification
            if (e.ctrlKey && e.key === 'h') {
                e.preventDefault();
                openModal('scheduleModal');
            }
            
            // Ctrl+E pour exporter
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                toggleExportMenu();
            }
        });

        /**
         * GESTION DU RESPONSIVE
         * =====================
         */
        function handleResize() {
            const width = window.innerWidth;
            
            // Adaptation des vues selon la taille d'écran
            if (width <= 768) {
                // Forcer la vue cartes sur mobile si c'est la vue tableau
                if (currentView === 'table') {
                    switchView('cards');
                }
            }
            
            // Ajustement des modaux
            const modals = document.querySelectorAll('.modal-content');
            modals.forEach(modal => {
                if (width <= 480) {
                    modal.style.width = '95%';
                    modal.style.margin = '5% auto';
                } else if (width <= 768) {
                    modal.style.width = '90%';
                    modal.style.margin = '3% auto';
                }
            });
        }

        // Écouter les changements de taille
        window.addEventListener('resize', handleResize);

        /**
         * FONCTIONS DE MISE À JOUR EN TEMPS RÉEL
         * ======================================
         */
        
        /**
         * Actualisation automatique des statistiques
         */
        function autoRefreshStats() {
            updateStats();
            
            // Programmer la prochaine mise à jour dans 5 minutes
            setTimeout(autoRefreshStats, 5 * 60 * 1000);
        }

        /**
         * GESTION DES ÉTATS DE CHARGEMENT
         * ===============================
         */
        function showLoading(element) {
            if (element) {
                element.classList.add('loading');
                element.style.opacity = '0.6';
                element.style.pointerEvents = 'none';
            }
        }

        function hideLoading(element) {
            if (element) {
                element.classList.remove('loading');
                element.style.opacity = '1';
                element.style.pointerEvents = 'auto';
            }
        }

        /**
         * VALIDATION EN TEMPS RÉEL DES FORMULAIRES
         * ========================================
         */
        function setupRealTimeValidation() {
            const emailInput = document.getElementById('employeeEmail');
            const phoneInput = document.getElementById('employeeTelephone');
            const salaryInput = document.getElementById('employeeSalaire');
            const hoursInput = document.getElementById('employeeHeures');
            
            // Validation email en temps réel
            if (emailInput) {
                emailInput.addEventListener('input', function() {
                    const email = this.value.trim();
                    if (email && !validateEmail(email)) {
                        this.style.borderColor = '#dc3545';
                        showTooltip(this, 'Format email invalide');
                    } else if (email) {
                        this.style.borderColor = '#28a745';
                        hideTooltip(this);
                    } else {
                        this.style.borderColor = '#e9ecef';
                        hideTooltip(this);
                    }
                });
            }
            
            // Validation téléphone en temps réel
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    const phone = this.value.trim();
                    if (phone && !validatePhone(phone)) {
                        this.style.borderColor = '#dc3545';
                        showTooltip(this, 'Format téléphone invalide');
                    } else if (phone) {
                        this.style.borderColor = '#28a745';
                        hideTooltip(this);
                    } else {
                        this.style.borderColor = '#e9ecef';
                        hideTooltip(this);
                    }
                });
            }
            
            // Validation salaire en temps réel
            if (salaryInput) {
                salaryInput.addEventListener('input', function() {
                    const salary = parseFloat(this.value);
                    if (this.value && (isNaN(salary) || salary <= 0)) {
                        this.style.borderColor = '#dc3545';
                        showTooltip(this, 'Le salaire doit être positif');
                    } else if (this.value) {
                        this.style.borderColor = '#28a745';
                        hideTooltip(this);
                    } else {
                        this.style.borderColor = '#e9ecef';
                        hideTooltip(this);
                    }
                });
            }
            
            // Validation heures en temps réel
            if (hoursInput) {
                hoursInput.addEventListener('input', function() {
                    const hours = parseInt(this.value);
                    if (this.value && (isNaN(hours) || hours < 1 || hours > 60)) {
                        this.style.borderColor = '#dc3545';
                        showTooltip(this, 'Entre 1 et 60 heures');
                    } else if (this.value) {
                        this.style.borderColor = '#28a745';
                        hideTooltip(this);
                    } else {
                        this.style.borderColor = '#e9ecef';
                        hideTooltip(this);
                    }
                });
            }
        }

        /**
         * GESTION DES TOOLTIPS
         * ====================
         */
        function showTooltip(element, message) {
            hideTooltip(element); // Supprimer l'ancien tooltip
            
            const tooltip = document.createElement('div');
            tooltip.className = 'validation-tooltip';
            tooltip.textContent = message;
            tooltip.style.cssText = `
                position: absolute;
                background: #dc3545;
                color: white;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 0.8rem;
                z-index: 1000;
                top: -35px;
                left: 0;
                white-space: nowrap;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            `;
            
            element.style.position = 'relative';
            element.appendChild(tooltip);
        }

        function hideTooltip(element) {
            const tooltip = element.querySelector('.validation-tooltip');
            if (tooltip) {
                tooltip.remove();
            }
        }

        /**
         * GESTION DU STOCKAGE LOCAL
         * =========================
         */
        function saveUserPreferences() {
            const preferences = {
                view: currentView,
                lastSearch: document.querySelector('input[name="search"]')?.value || '',
                lastFilters: {
                    poste: document.querySelector('select[name="poste"]')?.value || '',
                    statut: document.querySelector('select[name="statut"]')?.value || ''
                }
            };
            
            localStorage.setItem('employeeManagementPrefs', JSON.stringify(preferences));
        }

        function loadUserPreferences() {
            try {
                const prefs = JSON.parse(localStorage.getItem('employeeManagementPrefs') || '{}');
                
                // Restaurer la vue préférée
                if (prefs.view && prefs.view !== currentView) {
                    switchView(prefs.view);
                }
                
                return prefs;
            } catch (e) {
                console.warn('Erreur lors du chargement des préférences:', e);
                return {};
            }
        }

        /**
         * FONCTIONS UTILITAIRES
         * =====================
         */
        
        /**
         * Formater les devises
         */
        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR'
            }).format(amount);
        }

        /**
         * Formater les dates
         */
        function formatDate(dateString) {
            return new Intl.DateTimeFormat('fr-FR').format(new Date(dateString));
        }

        /**
         * Capitaliser la première lettre
         */
        function capitalize(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        /**
         * Générer un ID unique
         */
        function generateUniqueId() {
            return Date.now().toString(36) + Math.random().toString(36).substr(2);
        }

        /**
         * INITIALISATION DE L'APPLICATION
         * ===============================
         */
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Initialisation de la gestion des employés...');
            
            // Charger les préférences utilisateur
            loadUserPreferences();
            
            // Configurer la validation en temps réel
            setupRealTimeValidation();
            
            // Initialiser la semaine actuelle
            initializeWeekSelector();
            
            // Démarrer l'actualisation automatique des stats
            setTimeout(autoRefreshStats, 30000); // Première mise à jour après 30s
            
            // Ajuster l'interface selon la taille d'écran
            handleResize();
            
            // Sauvegarder les préférences lors des changements
            window.addEventListener('beforeunload', saveUserPreferences);
            
            // Ajouter des tooltips informatifs
            addTooltips();
            
            console.log('✅ Application initialisée avec succès');
        });

        /**
         * AJOUT DE TOOLTIPS INFORMATIFS
         * =============================
         */
        function addTooltips() {
            const tooltipElements = [
                { selector: '[title]', position: 'top' },
                { selector: '.stat-card', position: 'bottom' }
            ];
            
            tooltipElements.forEach(({ selector, position }) => {
                const elements = document.querySelectorAll(selector);
                elements.forEach(el => {
                    el.addEventListener('mouseenter', function(e) {
                        const title = this.getAttribute('title') || this.getAttribute('data-tooltip');
                        if (title && !this.querySelector('.custom-tooltip')) {
                            showCustomTooltip(this, title, position);
                        }
                    });
                    
                    el.addEventListener('mouseleave', function() {
                        const tooltip = this.querySelector('.custom-tooltip');
                        if (tooltip) {
                            tooltip.remove();
                        }
                    });
                });
            });
        }

        function showCustomTooltip(element, text, position = 'top') {
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = text;
            tooltip.style.cssText = `
                position: absolute;
                background: #2c3e50;
                color: white;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 0.8rem;
                z-index: 1001;
                white-space: nowrap;
                box-shadow: 0 4px 8px rgba(0,0,0,0.3);
                pointer-events: none;
                opacity: 0;
                transition: opacity 0.3s ease;
            `;
            
            // Positionner le tooltip
            const rect = element.getBoundingClientRect();
            if (position === 'top') {
                tooltip.style.bottom = '100%';
                tooltip.style.left = '50%';
                tooltip.style.transform = 'translateX(-50%)';
                tooltip.style.marginBottom = '5px';
            } else {
                tooltip.style.top = '100%';
                tooltip.style.left = '50%';
                tooltip.style.transform = 'translateX(-50%)';
                tooltip.style.marginTop = '5px';
            }
            
            element.style.position = 'relative';
            element.appendChild(tooltip);
            
            // Animation d'apparition
            setTimeout(() => {
                tooltip.style.opacity = '1';
            }, 10);
        }

        /**
         * GESTION DES ERREURS GLOBALES
         * ============================
         */
        window.addEventListener('error', function(e) {
            console.error('Erreur JavaScript:', e.error);
            
            // Ne pas afficher d'alerte pour les erreurs mineures
            if (!e.error?.message?.includes('ResizeObserver') && 
                !e.error?.message?.includes('Non-Error promise rejection')) {
                showAlert('Une erreur inattendue s\'est produite. Veuillez rafraîchir la page.', 'error');
            }
        });

        window.addEventListener('unhandledrejection', function(e) {
            console.error('Promise rejetée:', e.reason);
            
            if (typeof e.reason === 'string' && e.reason.includes('fetch')) {
                showAlert('Erreur de connexion réseau. Vérifiez votre connexion.', 'error');
            }
        });

        /**
         * MODE SOMBRE (OPTIONNEL)
         * =======================
         */
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDark);
        }

        // Charger le mode sombre si activé
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }

        /**
         * NOTIFICATIONS PUSH (POUR EXTENSION FUTURE)
         * ==========================================
         */
        function requestNotificationPermission() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        console.log('Notifications autorisées');
                    }
                });
            }
        }

        function showNotification(title, options = {}) {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(title, {
                    icon: '/favicon.ico',
                    badge: '/favicon.ico',
                    ...options
                });
            }
        }

        /**
         * FONCTIONS D'ACCESSIBILITÉ
         * =========================
         */
        function improveAccessibility() {
            // Ajouter des labels ARIA
            const buttons = document.querySelectorAll('button:not([aria-label])');
            buttons.forEach(btn => {
                const icon = btn.querySelector('i[class*="fa-"]');
                if (icon && !btn.textContent.trim()) {
                    const iconClass = Array.from(icon.classList).find(c => c.startsWith('fa-'));
                    if (iconClass) {
                        const actionMap = {
                            'fa-eye': 'Voir détails',
                            'fa-edit': 'Modifier',
                            'fa-trash': 'Supprimer',
                            'fa-save': 'Enregistrer',
                            'fa-times': 'Fermer',
                            'fa-plus': 'Ajouter'
                        };
                        btn.setAttribute('aria-label', actionMap[iconClass] || 'Action');
                    }
                }
            });
            
            // Améliorer la navigation au clavier
            const interactiveElements = document.querySelectorAll('button, input, select, a');
            interactiveElements.forEach((el, index) => {
                if (!el.hasAttribute('tabindex')) {
                    el.setAttribute('tabindex', '0');
                }
            });
        }

        // Améliorer l'accessibilité au chargement
        document.addEventListener('DOMContentLoaded', improveAccessibility);

        console.log('📋 Module de gestion des employés chargé avec succès');
    </script>
</body>
</html>

<?php
/**
 * FONCTIONS D'EXPORT (À IMPLÉMENTER)
 * ==================================
 */

/**
 * Génération d'export Excel
 */
function generateExcelExport($employees) {
    // Cette fonction nécessiterait une bibliothèque comme PhpSpreadsheet
    // Implémentation simplifiée pour l'exemple
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="employes_' . date('Y-m-d') . '.xlsx"');
    
    // Ici, vous intégreriez PhpSpreadsheet pour créer un vrai fichier Excel
    // Pour l'instant, on simule avec un CSV
    $output = fopen('php://output', 'w');
    
    // En-têtes
    fputcsv($output, [
        'ID', 'Nom', 'Prénom', 'Poste', 'Email', 'Téléphone', 
        'Date d\'embauche', 'Salaire/h', 'Heures/semaine', 'Statut'
    ], ';');
    
    // Données
    foreach ($employees as $emp) {
        fputcsv($output, [
            $emp['id'],
            $emp['nom'],
            $emp['prenom'],
            $emp['poste'],
            $emp['email'],
            $emp['telephone'],
            $emp['date_embauche'],
            $emp['salaire_horaire'],
            $emp['heures_travail'],
            $emp['statut_label']
        ], ';');
    }
    
    fclose($output);
    exit();
}

/**
 * Génération d'export PDF
 */
function generatePDFExport($employees) {
    // Cette fonction nécessiterait une bibliothèque comme TCPDF ou mPDF
    // Implémentation simplifiée pour l'exemple
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Export PDF généré (fonction à implémenter avec TCPDF/mPDF)',
        'download_url' => '#'
    ]);
}
?>
        </script>
    </body>