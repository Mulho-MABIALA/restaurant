<?php
// =============================================================================
// GESTION DES REQUÊTES AJAX
// =============================================================================

// Vérifier si c'est une requête AJAX
if (isset($_GET['action']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']))) {
    require_once '../config.php';
    require_once 'phpqrcode/qrlib.php';
    
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? $_POST['ajax_action'] ?? '';
    
    switch ($action) {
        
        // =============================================================================
        // RÉCUPÉRATION DES EMPLOYÉS
        // =============================================================================
        case 'get_employees':
            try {
                $stmt = $conn->query("SELECT * FROM vue_employes_complet ORDER BY nom, prenom");
                $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'employees' => $employees
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors du chargement des employés'
                ]);
            }
            exit;
            
        // =============================================================================
        // RÉCUPÉRATION DES STATISTIQUES
        // =============================================================================
        case 'get_statistics':
            try {
                // Total employés actifs
                $stmt = $conn->query("SELECT COUNT(*) as total FROM employes WHERE statut = 'actif'");
                $total_actifs = $stmt->fetch()['total'];
                
                // Présents aujourd'hui (statut actif, pas en congé ou absent)
                $stmt = $conn->query("SELECT COUNT(*) as total FROM employes WHERE statut = 'actif'");
                $presents_aujourd_hui = $stmt->fetch()['total'];
                
                // Nouveaux ce mois
                $stmt = $conn->query("SELECT COUNT(*) as total FROM employes WHERE DATE_FORMAT(date_embauche, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')");
                $nouveaux_ce_mois = $stmt->fetch()['total'];
                
                // Total administrateurs
                $stmt = $conn->query("SELECT COUNT(*) as total FROM employes WHERE is_admin = 1 AND statut != 'inactif'");
                $total_admins = $stmt->fetch()['total'];
                
                echo json_encode([
                    'success' => true,
                    'statistics' => [
                        'total_actifs' => $total_actifs,
                        'presents_aujourd_hui' => $presents_aujourd_hui,
                        'nouveaux_ce_mois' => $nouveaux_ce_mois,
                        'total_admins' => $total_admins
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors du chargement des statistiques'
                ]);
            }
            exit;
            
        // =============================================================================
        // RÉCUPÉRATION DES POSTES
        // =============================================================================
        case 'get_postes':
            try {
                $stmt = $conn->query("SELECT * FROM postes ORDER BY nom");
                $postes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'postes' => $postes
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors du chargement des postes'
                ]);
            }
            exit;
            
        // =============================================================================
        // AJOUT D'UN EMPLOYÉ
        // =============================================================================
        case 'add_employee':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }
    
            try {
                // Validation des champs requis
                $required_fields = ['nom', 'prenom', 'email', 'date_embauche'];
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("Le champ $field est requis");
                    }
                }
                
                // Vérifier si l'email existe déjà
                $stmt = $conn->prepare("SELECT id FROM employes WHERE email = ? AND statut != 'inactif'");
                $stmt->execute([$_POST['email']]);
                if ($stmt->fetch()) {
                    throw new Exception('Cet email est déjà utilisé par un autre employé actif');
                }
                
                // Gestion de l'upload de photo
                $photo_filename = 'default-avatar.png';
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/photos/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($file_ext, $allowed_exts) && $_FILES['photo']['size'] <= 5000000) {
                        $photo_filename = uniqid() . '.' . $file_ext;
                        $upload_path = $upload_dir . $photo_filename;
                        
                        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                            throw new Exception('Erreur lors de l\'upload de la photo');
                        }
                    } else {
                        throw new Exception('Format de photo non valide ou taille trop importante');
                    }
                }
                
                // Convertir le salaire en entier si fourni
                $salaire = null;
                if (!empty($_POST['salaire'])) {
                    $salaire = (int) $_POST['salaire'];
                }
                
                // Insertion de l'employé
                $stmt = $conn->prepare("
                    INSERT INTO employes (nom, prenom, email, telephone, poste_id, salaire, date_embauche, 
                                          heure_debut, heure_fin, photo, is_admin, statut) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                  $stmt->execute([
            $_POST['nom'],
            $_POST['prenom'],
            $_POST['email'],
            $_POST['telephone'] ?? null,
            $_POST['poste_id'] ?? null,
            $salaire,
            $_POST['date_embauche'],
            $_POST['heure_debut'] ?? '08:00:00',
            $_POST['heure_fin'] ?? '17:00:00',
            $photo_filename,
            isset($_POST['is_admin']) ? 1 : 0,
            $_POST['statut'] ?? 'actif'
        ]);
        
        $employee_id = $conn->lastInsertId();
                
                // ============= NOUVEAU : GÉNÉRATION DU CODE NUMÉRIQUE =============
        function generateNumericCode($employee_id, $conn) {
            // Générer un code unique de 8 chiffres
            $base_code = str_pad($employee_id, 4, '0', STR_PAD_LEFT); // ID sur 4 chiffres
            $random_suffix = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT); // 4 chiffres aléatoires
            
            $numeric_code = $base_code . $random_suffix;
            
            // Vérifier l'unicité du code généré
            $stmt = $conn->prepare("SELECT id FROM employes WHERE code_numerique = ?");
            $stmt->execute([$numeric_code]);
            
            // Si le code existe déjà, régénérer (très peu probable)
            if ($stmt->fetch()) {
                return generateNumericCode($employee_id, $conn); // Récursion
            }
            
            return $numeric_code;
        }
        
        $numeric_code = generateNumericCode($employee_id, $conn);
        
        // Génération du QR Code avec données enrichies
        $qr_data = json_encode([
            'id' => $employee_id,
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'email' => $_POST['email'],
            'code_numerique' => $numeric_code, // Ajout du code numérique dans le QR
            'generated' => date('Y-m-d H:i:s')
        ]);
                 $qr_dir = 'qrcodes/';
        if (!is_dir($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }
        
        $qr_filename = 'qr_employee_' . $employee_id . '.png';
        $qr_path = $qr_dir . $qr_filename;
        
        QRcode::png($qr_data, $qr_path, QR_ECLEVEL_L, 4);
        
        // Mise à jour avec le QR Code ET le code numérique
        $stmt = $conn->prepare("UPDATE employes SET qr_code = ?, qr_data = ?, code_numerique = ? WHERE id = ?");
        $stmt->execute([$qr_filename, $qr_data, $numeric_code, $employee_id]);
        
        // Log de l'activité
        $stmt = $conn->prepare("
            INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            'CREATE_EMPLOYEE',
            'employes',
            $employee_id,
            json_encode([
                'nom' => $_POST['nom'], 
                'prenom' => $_POST['prenom'],
                'code_numerique' => $numeric_code
            ])
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Employé ajouté avec succès',
            'employee_id' => $employee_id,
            'numeric_code' => $numeric_code // Retourner le code pour affichage
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
        // =============================================================================
        // MODIFICATION D'UN EMPLOYÉ
        // =============================================================================
        case 'update_employee':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                exit;
            }
            
            try {
                if (empty($_POST['id'])) {
                    throw new Exception('ID employé requis');
                }
                
                $employee_id = $_POST['id'];
                
                // Vérifier si l'employé existe
                $stmt = $conn->prepare("SELECT * FROM employes WHERE id = ?");
                $stmt->execute([$employee_id]);
                $current_employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$current_employee) {
                    throw new Exception('Employé non trouvé');
                }
                
                // Vérifier l'email unique (sauf pour l'employé actuel)
                $stmt = $conn->prepare("SELECT id FROM employes WHERE email = ? AND id != ? AND statut != 'inactif'");
                $stmt->execute([$_POST['email'], $employee_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Cet email est déjà utilisé par un autre employé actif');
                }
                
                $photo_filename = $current_employee['photo'];
                
                // Gestion de l'upload de photo
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/photos/';
                    
                    $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($file_ext, $allowed_exts) && $_FILES['photo']['size'] <= 5000000) {
                        $photo_filename = uniqid() . '.' . $file_ext;
                        $upload_path = $upload_dir . $photo_filename;
                        
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                            // Supprimer l'ancienne photo (sauf default)
                            if ($current_employee['photo'] !== 'default-avatar.png') {
                                $old_photo = $upload_dir . $current_employee['photo'];
                                if (file_exists($old_photo)) {
                                    unlink($old_photo);
                                }
                            }
                        } else {
                            throw new Exception('Erreur lors de l\'upload de la photo');
                        }
                    } else {
                        throw new Exception('Format de photo non valide ou taille trop importante');
                    }
                }
                
                // Convertir le salaire en entier si fourni
                $salaire = null;
                if (!empty($_POST['salaire'])) {
                    $salaire = (int) $_POST['salaire'];
                }
                
                // Mise à jour de l'employé
                $stmt = $conn->prepare("
                    UPDATE employes 
                    SET nom = ?, prenom = ?, email = ?, telephone = ?, poste_id = ?, salaire = ?, 
                        date_embauche = ?, heure_debut = ?, heure_fin = ?, photo = ?, 
                        is_admin = ?, statut = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['prenom'],
                    $_POST['email'],
                    $_POST['telephone'] ?? null,
                    $_POST['poste_id'] ?? null,
                    $salaire,
                    $_POST['date_embauche'],
                    $_POST['heure_debut'] ?? '08:00:00',
                    $_POST['heure_fin'] ?? '17:00:00',
                    $photo_filename,
                    isset($_POST['is_admin']) ? 1 : 0,
                    $_POST['statut'] ?? 'actif',
                    $employee_id
                ]);
                
                // Log de l'activité
                $stmt = $conn->prepare("
                    INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    'UPDATE_EMPLOYEE',
                    'employes',
                    $employee_id,
                    json_encode(['nom' => $_POST['nom'], 'prenom' => $_POST['prenom']])
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Employé modifié avec succès'
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            exit;
            
        // =============================================================================
        // DÉSACTIVATION D'UN EMPLOYÉ
        // =============================================================================
        case 'delete_employee':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            try {
                if (empty($input['id'])) {
                    throw new Exception('ID employé requis');
                }
                
                $employee_id = $input['id'];
                
                // Désactivation logique (pas de suppression physique)
                $stmt = $conn->prepare("UPDATE employes SET statut = 'inactif' WHERE id = ?");
                $stmt->execute([$employee_id]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Employé non trouvé');
                }
                
                // Log de l'activité
                $stmt = $conn->prepare("
                    INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    'DEACTIVATE_EMPLOYEE',
                    'employes',
                    $employee_id,
                    json_encode(['statut' => 'inactif'])
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Employé désactivé avec succès'
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            exit;
    }
}

// =============================================================================
// INTERFACE UTILISATEUR HTML
// =============================================================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Employés - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .hover-scale { transition: transform 0.2s; }
        .hover-scale:hover { transform: scale(1.05); }
        .card-shadow { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
        .notification { position: fixed; top: 20px; right: 20px; z-index: 1000; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-utensils text-orange-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-900">Gestion Restaurant</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="toggleView()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-th" id="viewIcon"></i>
                        <span id="viewText">Vue Cartes</span>
                    </button>
                    <button onclick="openAddModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Ajouter Employé
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Statistiques Dashboard -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Employés Actifs</p>
                        <p class="text-2xl font-bold text-gray-900" id="totalActifs">0</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-check-circle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Présents Aujourd'hui</p>
                        <p class="text-2xl font-bold text-gray-900" id="presentsAujourdhui">0</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                        <i class="fas fa-user-plus text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Nouveaux ce mois</p>
                        <p class="text-2xl font-bold text-gray-900" id="nouveauxCeMois">0</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-crown text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Administrateurs</p>
                        <p class="text-2xl font-bold text-gray-900" id="totalAdmins">0</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres et Recherche -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6 card-shadow">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <input type="text" id="searchInput" placeholder="Rechercher par nom, email..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <select id="filterPoste" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tous les postes</option>
                    </select>
                </div>
                <div>
                    <select id="filterStatut" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tous les statuts</option>
                        <option value="actif">Actif</option>
                        <option value="en_conge">En congé</option>
                        <option value="absent">Absent</option>
                        <option value="inactif">Inactif</option>
                    </select>
                </div>
                <div>
                    <button onclick="resetFilters()" class="w-full px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition duration-200">
                        <i class="fas fa-undo mr-2"></i>Réinitialiser
                    </button>
                </div>
            </div>
        </div>

        <!-- Vue Tableau -->
        <div id="tableView" class="bg-white rounded-lg shadow-md overflow-hidden card-shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Photo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employé</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poste</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Salaire</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="employeesTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Les employés seront chargés ici -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Vue Cartes -->
        <div id="cardView" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 hidden">
            <!-- Les cartes seront chargées ici -->
        </div>
    </div>

    <!-- Modal Ajouter/Modifier Employé -->
    <div id="employeeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-screen overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 id="modalTitle" class="text-lg font-semibold text-gray-900">Ajouter un employé</h3>
                </div>
                
                <form id="employeeForm" class="p-6" enctype="multipart/form-data">
                    <input type="hidden" id="employeeId" name="id">
                    <input type="hidden" name="ajax_action" id="ajaxAction" value="add_employee">
                    
                    <!-- Photo de profil -->
                    <div class="mb-6 text-center">
                        <div class="relative inline-block">
                            <img id="photoPreview" src="uploads/photos/default-avatar.png" 
                                 class="w-24 h-24 rounded-full border-4 border-gray-200 object-cover">
                            <label for="photo" class="absolute bottom-0 right-0 bg-blue-600 text-white rounded-full p-2 cursor-pointer hover:bg-blue-700">
                                <i class="fas fa-camera text-sm"></i>
                                <input type="file" id="photo" name="photo" accept="image/*" class="hidden">
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="nom" class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                            <input type="text" id="nom" name="nom" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="prenom" class="block text-sm font-medium text-gray-700 mb-2">Prénom *</label>
                            <input type="text" id="prenom" name="prenom" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                            <input type="email" id="email" name="email" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="telephone" class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="poste" class="block text-sm font-medium text-gray-700 mb-2">Poste *</label>
                            <select id="poste" name="poste_id" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Sélectionner un poste</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="salaire" class="block text-sm font-medium text-gray-700 mb-2">Salaire (FCFA)</label>
                            <input type="number" id="salaire" name="salaire" min="0" step="1" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="dateEmbauche" class="block text-sm font-medium text-gray-700 mb-2">Date d'embauche *</label>
                            <input type="date" id="dateEmbauche" name="date_embauche" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="statut" class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                            <select id="statut" name="statut" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="actif">Actif</option>
                                <option value="en_conge">En congé</option>
                                <option value="absent">Absent</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="heureDebut" class="block text-sm font-medium text-gray-700 mb-2">Heure début</label>
                            <input type="time" id="heureDebut" name="heure_debut" value="08:00" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="heureFin" class="block text-sm font-medium text-gray-700 mb-2">Heure fin</label>
                            <input type="time" id="heureFin" name="heure_fin" value="17:00" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="isAdmin" name="is_admin" value="1" 
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">
                                <i class="fas fa-crown text-yellow-500 mr-1"></i>
                                Administrateur
                            </span>
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200">
                            Annuler
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-save mr-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Zone de notification -->
    <div id="notification" class="notification hidden"></div>

    <script>
        // =============================================================================
        // VARIABLES GLOBALES ET INITIALISATION
        // =============================================================================
        
        let currentView = localStorage.getItem('preferredView') || 'table';
        let employees = [];
        let postes = [];

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            loadPostes();
            loadEmployees();
            loadStatistics();
            setView();
            
            // Auto-refresh des statistiques toutes les 30 secondes
            setInterval(loadStatistics, 30000);
            
            // Événements
            document.getElementById('searchInput').addEventListener('input', filterEmployees);
            document.getElementById('filterPoste').addEventListener('change', filterEmployees);
            document.getElementById('filterStatut').addEventListener('change', filterEmployees);
            document.getElementById('photo').addEventListener('change', previewPhoto);
            document.getElementById('employeeForm').addEventListener('submit', saveEmployee);
        });

        // =============================================================================
        // GESTION DES VUES
        // =============================================================================
        
        function toggleView() {
            currentView = currentView === 'table' ? 'cards' : 'table';
            localStorage.setItem('preferredView', currentView);
            setView();
        }

        function setView() {
            const tableView = document.getElementById('tableView');
            const cardView = document.getElementById('cardView');
            const viewIcon = document.getElementById('viewIcon');
            const viewText = document.getElementById('viewText');
            
            if (currentView === 'table') {
                tableView.classList.remove('hidden');
                cardView.classList.add('hidden');
                viewIcon.className = 'fas fa-th';
                viewText.textContent = 'Vue Cartes';
            } else {
                tableView.classList.add('hidden');
                cardView.classList.remove('hidden');
                viewIcon.className = 'fas fa-list';
                viewText.textContent = 'Vue Tableau';
            }
        }

        // =============================================================================
        // CHARGEMENT DES DONNÉES
        // =============================================================================
        
        function loadStatistics() {
            fetch('?action=get_statistics')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('totalActifs').textContent = data.statistics.total_actifs;
                        document.getElementById('presentsAujourdhui').textContent = data.statistics.presents_aujourd_hui;
                        document.getElementById('nouveauxCeMois').textContent = data.statistics.nouveaux_ce_mois;
                        document.getElementById('totalAdmins').textContent = data.statistics.total_admins;
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        function loadPostes() {
            fetch('?action=get_postes')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        postes = data.postes;
                        updatePostesSelects();
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        function updatePostesSelects() {
            const filterPoste = document.getElementById('filterPoste');
            const modalPoste = document.getElementById('poste');
            
            // Effacer les options existantes (sauf la première)
            filterPoste.innerHTML = '<option value="">Tous les postes</option>';
            modalPoste.innerHTML = '<option value="">Sélectionner un poste</option>';
            
            postes.forEach(poste => {
                filterPoste.innerHTML += `<option value="${poste.id}">${poste.nom}</option>`;
                modalPoste.innerHTML += `<option value="${poste.id}">${poste.nom}</option>`;
            });
        }

        function loadEmployees() {
            fetch('?action=get_employees')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        employees = data.employees;
                        displayEmployees(employees);
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        // =============================================================================
        // AFFICHAGE DES EMPLOYÉS
        // =============================================================================
        
        function displayEmployees(employeesList) {
            if (currentView === 'table') {
                displayTableView(employeesList);
            } else {
                displayCardView(employeesList);
            }
        }

        function displayTableView(employeesList) {
            const tbody = document.getElementById('employeesTableBody');
            tbody.innerHTML = '';
            
            employeesList.forEach(employee => {
                const row = createEmployeeRow(employee);
                tbody.appendChild(row);
            });
        }

        function createEmployeeRow(employee) {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 fade-in';
            
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap">
                    <img src="uploads/photos/${employee.photo || 'default-avatar.png'}" 
                         class="h-10 w-10 rounded-full object-cover">
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">
                        ${employee.prenom} ${employee.nom}
                        ${employee.is_admin ? '<i class="fas fa-crown text-yellow-500 ml-1" title="Administrateur"></i>' : ''}
                    </div>
                    <div class="text-sm text-gray-500">ID: ${employee.id}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                              style="background-color: ${employee.poste_couleur || '#6B7280'}20; color: ${employee.poste_couleur || '#6B7280'};">
                            ${employee.poste_nom || 'Non défini'}
                        </span>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <div>${employee.email}</div>
                    <div class="text-gray-500">${employee.telephone || 'N/A'}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusClass(employee.statut)}">
                        ${getStatusText(employee.statut)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${employee.salaire ? formatSalaire(employee.salaire) + ' FCFA' : 'Non défini'}
                    <div class="text-xs text-gray-500">${employee.heure_debut} - ${employee.heure_fin}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex space-x-2">
                        <button onclick="viewEmployee(${employee.id})" class="text-blue-600 hover:text-blue-900" title="Voir détails">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="editEmployee(${employee.id})" class="text-green-600 hover:text-green-900" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="generateBadge(${employee.id})" class="text-purple-600 hover:text-purple-900" title="Badge">
                            <i class="fas fa-qrcode"></i>
                        </button>
                        <button onclick="deleteEmployee(${employee.id})" class="text-red-600 hover:text-red-900" title="Désactiver">
                            <i class="fas fa-user-slash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            return row;
        }

        function displayCardView(employeesList) {
            const cardView = document.getElementById('cardView');
            cardView.innerHTML = '';
            
            employeesList.forEach(employee => {
                const card = createEmployeeCard(employee);
                cardView.appendChild(card);
            });
        }

        function createEmployeeCard(employee) {
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 fade-in';
            
            card.innerHTML = `
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <img src="uploads/photos/${employee.photo || 'default-avatar.png'}" 
                             class="h-16 w-16 rounded-full object-cover border-2 border-gray-200">
                        <div class="ml-4 flex-1">
                            <h3 class="text-lg font-semibold text-gray-900">
                                ${employee.prenom} ${employee.nom}
                                ${employee.is_admin ? '<i class="fas fa-crown text-yellow-500 ml-1" title="Administrateur"></i>' : ''}
                            </h3>
                            <div class="flex items-center mt-1">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                                      style="background-color: ${employee.poste_couleur || '#6B7280'}20; color: ${employee.poste_couleur || '#6B7280'};">
                                    ${employee.poste_nom || 'Non défini'}
                                </span>
                                <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusClass(employee.statut)}">
                                    ${getStatusText(employee.statut)}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-2 text-sm text-gray-600">
                        <div class="flex items-center">
                            <i class="fas fa-envelope w-4 mr-2"></i>
                            ${employee.email}
                        </div>
                        ${employee.telephone ? `
                            <div class="flex items-center">
                                <i class="fas fa-phone w-4 mr-2"></i>
                                ${employee.telephone}
                            </div>
                        ` : ''}
                        <div class="flex items-center">
                            <i class="fas fa-calendar w-4 mr-2"></i>
                            Embauché le ${formatDate(employee.date_embauche)}
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-clock w-4 mr-2"></i>
                            ${employee.heure_debut} - ${employee.heure_fin}
                        </div>
                        ${employee.salaire ? `
                            <div class="flex items-center">
                                <i class="fas fa-money-bill w-4 mr-2"></i>
                                ${formatSalaire(employee.salaire)} FCFA
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="mt-4 flex justify-end space-x-2">
                        <button onclick="viewEmployee(${employee.id})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="Voir détails">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="editEmployee(${employee.id})" class="p-2 text-green-600 hover:bg-green-50 rounded-lg" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="generateBadge(${employee.id})" class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg" title="Badge">
                            <i class="fas fa-qrcode"></i>
                        </button>
                        <button onclick="deleteEmployee(${employee.id})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="Désactiver">
                            <i class="fas fa-user-slash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            return card;
        }

        // =============================================================================
        // FONCTIONS UTILITAIRES
        // =============================================================================
        
        function getStatusClass(statut) {
            const classes = {
                'actif': 'bg-green-100 text-green-800',
                'en_conge': 'bg-yellow-100 text-yellow-800',
                'absent': 'bg-red-100 text-red-800',
                'inactif': 'bg-gray-100 text-gray-800'
            };
            return classes[statut] || 'bg-gray-100 text-gray-800';
        }

        function getStatusText(statut) {
            const texts = {
                'actif': 'Actif',
                'en_conge': 'En congé',
                'absent': 'Absent',
                'inactif': 'Inactif'
            };
            return texts[statut] || 'Inconnu';
        }

        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }

        function formatSalaire(salaire) {
            if (!salaire) return '';
            // Formater le salaire en entier avec des espaces comme séparateurs de milliers
            return parseInt(salaire).toLocaleString('fr-FR');
        }

        // =============================================================================
        // FILTRAGE ET RECHERCHE
        // =============================================================================
        
        function filterEmployees() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const posteFilter = document.getElementById('filterPoste').value;
            const statutFilter = document.getElementById('filterStatut').value;
            
            const filtered = employees.filter(employee => {
                const matchesSearch = !searchTerm || 
                    employee.nom.toLowerCase().includes(searchTerm) ||
                    employee.prenom.toLowerCase().includes(searchTerm) ||
                    employee.email.toLowerCase().includes(searchTerm);
                
                const matchesPoste = !posteFilter || employee.poste_id == posteFilter;
                const matchesStatut = !statutFilter || employee.statut === statutFilter;
                
                return matchesSearch && matchesPoste && matchesStatut;
            });
            
            displayEmployees(filtered);
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterPoste').value = '';
            document.getElementById('filterStatut').value = '';
            displayEmployees(employees);
        }

        // =============================================================================
        // GESTION DU MODAL
        // =============================================================================
        
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Ajouter un employé';
            document.getElementById('employeeForm').reset();
            document.getElementById('employeeId').value = '';
            document.getElementById('ajaxAction').value = 'add_employee';
            document.getElementById('photoPreview').src = 'uploads/photos/default-avatar.png';
            document.getElementById('employeeModal').classList.remove('hidden');
        }

        function editEmployee(id) {
            const employee = employees.find(e => e.id == id);
            if (!employee) return;
            
            document.getElementById('modalTitle').textContent = 'Modifier l\'employé';
            document.getElementById('employeeId').value = employee.id;
            document.getElementById('ajaxAction').value = 'update_employee';
            document.getElementById('nom').value = employee.nom;
            document.getElementById('prenom').value = employee.prenom;
            document.getElementById('email').value = employee.email;
            document.getElementById('telephone').value = employee.telephone || '';
            document.getElementById('poste').value = employee.poste_id || '';
            document.getElementById('salaire').value = employee.salaire || '';
            document.getElementById('dateEmbauche').value = employee.date_embauche;
            document.getElementById('statut').value = employee.statut;
            document.getElementById('heureDebut').value = employee.heure_debut;
            document.getElementById('heureFin').value = employee.heure_fin;
            document.getElementById('isAdmin').checked = employee.is_admin == 1;
            document.getElementById('photoPreview').src = `uploads/photos/${employee.photo || 'default-avatar.png'}`;
            
            document.getElementById('employeeModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('employeeModal').classList.add('hidden');
        }

        // =============================================================================
        // PRÉVISUALISATION DE LA PHOTO
        // =============================================================================
        
        function previewPhoto(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('photoPreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        // =============================================================================
        // SAUVEGARDE DE L'EMPLOYÉ
        // =============================================================================
        
        function saveEmployee(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Employé sauvegardé avec succès!', 'success');
                    closeModal();
                    loadEmployees();
                    loadStatistics();
                } else {
                    showNotification(data.message || 'Erreur lors de la sauvegarde', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Erreur lors de la sauvegarde', 'error');
            });
        }

        // =============================================================================
        // ACTIONS SUR LES EMPLOYÉS
        // =============================================================================
        
        function viewEmployee(id) {
            // Ouvrir une modal de détails ou rediriger vers une page de détails
            window.open(`employee_details.php?id=${id}`, '_blank');
        }

        function generateBadge(id) {
            window.open(`generate_badges.php?id=${id}`, '_blank');
        }

        function deleteEmployee(id) {
            if (confirm('Êtes-vous sûr de vouloir désactiver cet employé?')) {
                fetch('?action=delete_employee', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Employé désactivé avec succès!', 'success');
                        loadEmployees();
                        loadStatistics();
                    } else {
                        showNotification(data.message || 'Erreur lors de la désactivation', 'error');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showNotification('Erreur lors de la désactivation', 'error');
                });
            }
        }

        // =============================================================================
        // NOTIFICATIONS
        // =============================================================================
        
        function showNotification(message, type = 'info') {
            const notification = document.getElementById('notification');
            const colors = {
                'success': 'bg-green-500',
                'error': 'bg-red-500',
                'warning': 'bg-yellow-500',
                'info': 'bg-blue-500'
            };
            
            notification.innerHTML = `
                <div class="${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center">
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

        // =============================================================================
        // GESTION DU CLIC EN DEHORS DU MODAL
        // =============================================================================
        
        document.getElementById('employeeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>