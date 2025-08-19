<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';

// Vérification unique de la session admin
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Variables de session
$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = $_SESSION['admin_name'] ?? '';
$admin_email = $_SESSION['admin_email'] ?? '';

// Si l'ID admin est manquant, tentative de récupération depuis l'email
if (!$admin_id) {
    if (!empty($admin_email)) {
        try {
            $stmt = $conn->prepare("SELECT id, username, email FROM admin WHERE email = ?");
            $stmt->execute([$admin_email]);
            $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin_data) {
                $_SESSION['admin_id'] = $admin_data['id'];
                $_SESSION['admin_name'] = $admin_data['username'];
                $_SESSION['admin_email'] = $admin_data['email'];

                $admin_id = $admin_data['id'];
                $admin_name = $admin_data['username'];
                $admin_email = $admin_data['email'];
            } else {
                header('Location: logout.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Erreur DB : " . $e->getMessage());
            header('Location: logout.php');
            exit;
        }
    } else {
        header('Location: logout.php');
        exit;
    }
}

$error = '';
$success = '';

// Récupération des infos actuelles de l'admin
try {
    $stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_data) {
        header('Location: logout.php');
        exit;
    }

    $admin_name = $admin_data['username'];
    $admin_email = $admin_data['email'];
    $current_photo = $admin_data['profile_photo'] ?? '';

    $_SESSION['admin_name'] = $admin_name;
    $_SESSION['admin_email'] = $admin_email;

} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des données : " . $e->getMessage();
}

// Fonction pour enregistrer l'historique des modifications
function saveProfileHistory($conn, $admin_id, $field_changed, $old_value, $new_value) {
    try {
        // Vérifier si la table existe, sinon la créer
        $check_table = $conn->query("SHOW TABLES LIKE 'admin_profile_history'");
        if ($check_table->rowCount() == 0) {
            $create_table = "
                CREATE TABLE admin_profile_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    admin_id INT NOT NULL,
                    field_changed VARCHAR(50) NOT NULL,
                    old_value TEXT,
                    new_value TEXT,
                    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE
                )
            ";
            $conn->exec($create_table);
        }

        $stmt = $conn->prepare("
            INSERT INTO admin_profile_history 
            (admin_id, field_changed, old_value, new_value, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt->execute([
            $admin_id, 
            $field_changed, 
            $old_value, 
            $new_value, 
            $ip_address, 
            $user_agent
        ]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la sauvegarde de l'historique : " . $e->getMessage());
    }
}

// Récupération de l'historique des modifications
$profile_history = [];
try {
    $stmt = $conn->prepare("
        SELECT field_changed, old_value, new_value, changed_at, ip_address 
        FROM admin_profile_history 
        WHERE admin_id = ? 
        ORDER BY changed_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$admin_id]);
    $profile_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table n'existe peut-être pas encore
    $profile_history = [];
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        if (empty($new_name)) {
            throw new Exception("Le nom est obligatoire");
        }

        if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("L'email est obligatoire et doit être valide");
        }

        // Vérifier si l'email est déjà utilisé
        if ($new_email !== $admin_email) {
            $stmt = $conn->prepare("SELECT id FROM admin WHERE email = ? AND id != ?");
            $stmt->execute([$new_email, $admin_id]);
            if ($stmt->fetch()) {
                throw new Exception("Cette adresse email est déjà utilisée par un autre administrateur");
            }
        }

        // VALIDATION DES MOTS DE PASSE SEULEMENT SI CHANGEMENT DEMANDÉ
        $password_change_requested = !empty($new_password) || !empty($confirm_password);
        
        if ($password_change_requested) {
            if (empty($current_password)) {
                throw new Exception("Le mot de passe actuel est requis pour changer le mot de passe");
            }

            if (empty($new_password)) {
                throw new Exception("Le nouveau mot de passe est requis");
            }

            // Vérifier le mot de passe actuel
            $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin_row = $stmt->fetch();

            if (!$admin_row || !password_verify($current_password, $admin_row['password'])) {
                throw new Exception("Mot de passe actuel incorrect");
            }

            if (strlen($new_password) < 6) {
                throw new Exception("Le nouveau mot de passe doit contenir au moins 6 caractères");
            }

            if ($new_password !== $confirm_password) {
                throw new Exception("Les nouveaux mots de passe ne correspondent pas");
            }
        }

        // Enregistrer l'historique des modifications avant la mise à jour
        if ($new_name !== $admin_name) {
            saveProfileHistory($conn, $admin_id, 'username', $admin_name, $new_name);
        }
        if ($new_email !== $admin_email) {
            saveProfileHistory($conn, $admin_id, 'email', $admin_email, $new_email);
        }
        if ($password_change_requested) {
            saveProfileHistory($conn, $admin_id, 'password', 'Ancien mot de passe', 'Nouveau mot de passe');
        }

        // Mise à jour en base
        if ($password_change_requested && !empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin SET username = ?, email = ?, password = ? WHERE id = ?");
            $stmt->execute([$new_name, $new_email, $hashed_password, $admin_id]);
        } else {
            $stmt = $conn->prepare("UPDATE admin SET username = ?, email = ? WHERE id = ?");
            $stmt->execute([$new_name, $new_email, $admin_id]);
        }

        $_SESSION['admin_name'] = $new_name;
        $_SESSION['admin_email'] = $new_email;

        $admin_name = $new_name;
        $admin_email = $new_email;

        $success = "Profil mis à jour avec succès";

        // Recharger l'historique après modification
        $stmt = $conn->prepare("
            SELECT field_changed, old_value, new_value, changed_at, ip_address 
            FROM admin_profile_history 
            WHERE admin_id = ? 
            ORDER BY changed_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$admin_id]);
        $profile_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'pulse-slow': 'pulse 3s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(100%)' },
                            '100%': { transform: 'translateY(0)' }
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header avec animation -->
            <header class="bg-white/80 backdrop-blur-sm shadow-sm border-b border-gray-200/50 sticky top-0 z-10">
                <div class="px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                    <div class="flex items-center space-x-3 animate-fade-in">
                        <div class="p-2 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg shadow-lg">
                            <i class="fas fa-user-cog text-white text-lg"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">Mon Profil</h1>
                            <p class="text-sm text-gray-500">Gérer vos informations personnelles</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <i class="fas fa-circle text-green-400 text-xs mr-1"></i>
                            En ligne
                        </span>
                    </div>
                </div>
            </header>

            <!-- Contenu principal -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <div class="max-w-6xl mx-auto space-y-8">
                    <!-- Messages d'erreur/succès avec animations -->
                    <?php if ($error): ?>
                    <div class="bg-gradient-to-r from-red-500 to-pink-500 text-white px-6 py-4 rounded-xl shadow-lg animate-slide-up">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-xl mr-3"></i>
                            <div>
                                <p class="font-semibold">Erreur</p>
                                <p class="text-sm opacity-90"><?= htmlspecialchars($error) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="bg-gradient-to-r from-green-500 to-emerald-500 text-white px-6 py-4 rounded-xl shadow-lg animate-slide-up">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-xl mr-3"></i>
                            <div>
                                <p class="font-semibold">Succès</p>
                                <p class="text-sm opacity-90"><?= htmlspecialchars($success) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Colonne principale - Formulaire -->
                        <div class="lg:col-span-2 space-y-8">
                            <!-- Carte Profil avec design moderne -->
                            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                <!-- En-tête avec gradient moderne -->
                                <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-700 px-8 py-8 relative overflow-hidden">
                                    <!-- Motif de fond -->
                                    <div class="absolute inset-0 bg-black/10"></div>
                                    <div class="absolute -top-4 -right-4 w-24 h-24 bg-white/10 rounded-full"></div>
                                    <div class="absolute -bottom-4 -left-4 w-32 h-32 bg-white/5 rounded-full"></div>
                                    
                                    <div class="relative flex items-center space-x-6">
                                        <!-- Avatar avec effet hover -->
                                        <div class="flex-shrink-0 group">
                                            <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center border-4 border-white/30 group-hover:border-white/50 transition-all duration-300 group-hover:scale-105">
                                                <i class="fas fa-user text-white text-2xl"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h2 class="text-3xl font-bold text-white mb-1"><?= htmlspecialchars($admin_name) ?></h2>
                                            <p class="text-blue-100 text-lg"><?= htmlspecialchars($admin_email) ?></p>
                                            <div class="flex items-center space-x-4 mt-3">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white/20 text-white border border-white/30">
                                                    <i class="fas fa-shield-alt mr-1"></i>
                                                    Super Administrateur
                                                </span>
                                                <span class="text-blue-200 text-sm">
                                                    <i class="fas fa-calendar-alt mr-1"></i>
                                                    Membre depuis <?= date('M Y', strtotime($admin_data['created_at'] ?? 'now')) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Formulaire avec sections améliorées -->
                                <form method="POST" class="p-8 space-y-10" id="profileForm">
                                    <!-- Section Informations personnelles -->
                                    <div class="space-y-6">
                                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-100">
                                            <div class="p-2 bg-blue-100 rounded-lg">
                                                <i class="fas fa-user text-blue-600"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900">Informations personnelles</h3>
                                                <p class="text-sm text-gray-500">Gérez vos informations de base</p>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                            <!-- Nom avec style moderne -->
                                            <div class="group">
                                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Nom complet <span class="text-red-500">*</span>
                                                </label>
                                                <div class="relative">
                                                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($admin_name) ?>"
                                                        class="w-full border border-gray-300 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 group-hover:border-blue-300"
                                                        required>
                                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                        <i class="fas fa-user text-gray-400"></i>
                                                    </div>
                                                </div>
                                                <div class="error-message hidden text-red-500 text-xs mt-2"></div>
                                            </div>
                                            
                                            <!-- Email avec style moderne -->
                                            <div class="group">
                                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Adresse email <span class="text-red-500">*</span>
                                                </label>
                                                <div class="relative">
                                                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin_email) ?>"
                                                        class="w-full border border-gray-300 rounded-xl shadow-sm py-3 px-4 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 group-hover:border-blue-300"
                                                        required>
                                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                        <i class="fas fa-envelope text-gray-400"></i>
                                                    </div>
                                                </div>
                                                <div class="error-message hidden text-red-500 text-xs mt-2"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Section Changement de mot de passe -->
                                    <div class="space-y-6">
                                        <div class="flex items-center space-x-3 pb-4 border-b border-gray-100">
                                            <div class="p-2 bg-purple-100 rounded-lg">
                                                <i class="fas fa-lock text-purple-600"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900">Sécurité du compte</h3>
                                                <p class="text-sm text-gray-500">Modifiez votre mot de passe</p>
                                            </div>
                                        </div>
                                        
                                        <div class="space-y-6">
                                            <!-- Mot de passe actuel -->
                                            <div class="group">
                                                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Mot de passe actuel
                                                </label>
                                                <div class="relative">
                                                    <input type="password" id="current_password" name="current_password"
                                                        class="w-full border border-gray-300 rounded-xl shadow-sm py-3 px-4 pr-12 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 group-hover:border-purple-300">
                                                    <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('current_password')">
                                                        <i class="fas fa-eye text-gray-400 hover:text-gray-600 transition-colors duration-200" id="current_password_icon"></i>
                                                    </button>
                                                </div>
                                                <div class="error-message hidden text-red-500 text-xs mt-2"></div>
                                            </div>
                                            
                                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                                <!-- Nouveau mot de passe -->
                                                <div class="group">
                                                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                                        Nouveau mot de passe
                                                    </label>
                                                    <div class="relative">
                                                        <input type="password" id="new_password" name="new_password"
                                                            class="w-full border border-gray-300 rounded-xl shadow-sm py-3 px-4 pr-12 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 group-hover:border-purple-300">
                                                        <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('new_password')">
                                                            <i class="fas fa-eye text-gray-400 hover:text-gray-600 transition-colors duration-200" id="new_password_icon"></i>
                                                        </button>
                                                    </div>
                                                    <div class="password-strength mt-2 hidden">
                                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                                            <div class="password-strength-bar h-2 rounded-full transition-all duration-300"></div>
                                                        </div>
                                                        <p class="password-strength-text text-xs mt-1"></p>
                                                    </div>
                                                    <div class="error-message hidden text-red-500 text-xs mt-2"></div>
                                                </div>
                                                
                                                <!-- Confirmation -->
                                                <div class="group">
                                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                                        Confirmer le mot de passe
                                                    </label>
                                                    <div class="relative">
                                                        <input type="password" id="confirm_password" name="confirm_password"
                                                            class="w-full border border-gray-300 rounded-xl shadow-sm py-3 px-4 pr-12 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 group-hover:border-purple-300">
                                                        <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('confirm_password')">
                                                            <i class="fas fa-eye text-gray-400 hover:text-gray-600 transition-colors duration-200" id="confirm_password_icon"></i>
                                                        </button>
                                                    </div>
                                                    <div class="error-message hidden text-red-500 text-xs mt-2"></div>
                                                </div>
                                            </div>
                                            
                                            <!-- Conseils de sécurité -->
                                            <div class="bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-xl p-4">
                                                <div class="flex">
                                                    <i class="fas fa-info-circle text-blue-500 mr-3 mt-0.5"></i>
                                                    <div class="text-sm text-blue-700">
                                                        <p class="font-medium mb-2">Conseils pour un mot de passe sécurisé :</p>
                                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                                            <div class="flex items-center">
                                                                <i class="fas fa-check text-green-500 mr-1"></i>
                                                                Au moins 8 caractères
                                                            </div>
                                                            <div class="flex items-center">
                                                                <i class="fas fa-check text-green-500 mr-1"></i>
                                                                Majuscules et minuscules
                                                            </div>
                                                            <div class="flex items-center">
                                                                <i class="fas fa-check text-green-500 mr-1"></i>
                                                                Au moins un chiffre
                                                            </div>
                                                            <div class="flex items-center">
                                                                <i class="fas fa-check text-green-500 mr-1"></i>
                                                                Caractère spécial
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Boutons avec design moderne -->
                                    <div class="flex flex-col sm:flex-row justify-between items-center pt-8 border-t border-gray-100 space-y-4 sm:space-y-0">
                                        <div class="text-sm text-gray-500 flex items-center">
                                            <i class="fas fa-clock mr-2 text-gray-400"></i>
                                            Dernière modification : <?= date('d/m/Y à H:i') ?>
                                        </div>
                                        
                                        <div class="flex space-x-3">
                                            <a href="dashboard.php" class="inline-flex items-center px-6 py-3 border border-gray-300 rounded-xl shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                                <i class="fas fa-arrow-left mr-2"></i>
                                                Retour
                                            </a>
                                            <button type="submit" class="inline-flex items-center px-8 py-3 border border-transparent rounded-xl shadow-sm text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform hover:scale-105 transition-all duration-200" id="submitBtn">
                                                <i class="fas fa-save mr-2"></i>
                                                <span id="submitBtnText">Enregistrer les modifications</span>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Colonne latérale - Statistiques et historique -->
                        <div class="space-y-8">
                            <!-- Statistiques du compte -->
                            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                <div class="p-6 border-b border-gray-100">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                                        Statistiques du compte
                                    </h3>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl p-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-blue-600 font-medium">Membre depuis</p>
                                                <p class="text-lg font-bold text-blue-900"><?= date('d/m/Y', strtotime($admin_data['created_at'] ?? 'now')) ?></p>
                                            </div>
                                            <i class="fas fa-calendar-alt text-blue-600 text-2xl"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-gradient-to-r from-green-50 to-green-100 rounded-xl p-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-green-600 font-medium">Dernière connexion</p>
                                                <p class="text-lg font-bold text-green-900"><?= date('d/m/Y H:i', strtotime($admin_data['last_login'] ?? 'now')) ?></p>
                                            </div>
                                            <i class="fas fa-sign-in-alt text-green-600 text-2xl"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-xl p-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-purple-600 font-medium">Niveau d'accès</p>
                                                <p class="text-lg font-bold text-purple-900">Super Admin</p>
                                            </div>
                                            <i class="fas fa-shield-alt text-purple-600 text-2xl"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Historique des modifications -->
                            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                <div class="p-6 border-b border-gray-100">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i class="fas fa-history text-orange-600 mr-2"></i>
                                        Historique des modifications
                                    </h3>
                                    <p class="text-sm text-gray-500 mt-1">Les 10 dernières modifications</p>
                                </div>
                                <div class="p-6">
                                    <?php if (empty($profile_history)): ?>
                                    <div class="text-center py-8">
                                        <i class="fas fa-history text-gray-300 text-4xl mb-4"></i>
                                        <p class="text-gray-500 text-sm">Aucune modification enregistrée</p>
                                        <p class="text-gray-400 text-xs mt-1">Les futures modifications apparaîtront ici</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="space-y-4 max-h-96 overflow-y-auto">
                                        <?php foreach ($profile_history as $index => $history): ?>
                                        <div class="flex items-start space-x-3 p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors duration-200">
                                            <div class="flex-shrink-0 mt-0.5">
                                                <?php
                                                $icon = match($history['field_changed']) {
                                                    'username' => 'fa-user text-blue-500',
                                                    'email' => 'fa-envelope text-green-500',
                                                    'password' => 'fa-lock text-red-500',
                                                    default => 'fa-edit text-gray-500'
                                                };
                                                ?>
                                                <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center shadow-sm">
                                                    <i class="fas <?= $icon ?> text-sm"></i>
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between">
                                                    <p class="text-sm font-medium text-gray-900 capitalize">
                                                        <?php
                                                        $field_names = [
                                                            'username' => 'Nom d\'utilisateur',
                                                            'email' => 'Adresse email',
                                                            'password' => 'Mot de passe'
                                                        ];
                                                        echo $field_names[$history['field_changed']] ?? $history['field_changed'];
                                                        ?>
                                                    </p>
                                                    <span class="text-xs text-gray-500">
                                                        <?= date('d/m H:i', strtotime($history['changed_at'])) ?>
                                                    </span>
                                                </div>
                                                <?php if ($history['field_changed'] !== 'password'): ?>
                                                <div class="mt-1 text-xs text-gray-600">
                                                    <span class="line-through text-red-500"><?= htmlspecialchars($history['old_value']) ?></span>
                                                    <span class="mx-1">→</span>
                                                    <span class="text-green-600 font-medium"><?= htmlspecialchars($history['new_value']) ?></span>
                                                </div>
                                                <?php else: ?>
                                                <p class="mt-1 text-xs text-gray-500">Mot de passe modifié</p>
                                                <?php endif; ?>
                                                <div class="mt-1 flex items-center text-xs text-gray-400">
                                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                                    <span class="truncate"><?= htmlspecialchars($history['ip_address']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Bouton pour voir plus d'historique -->
                                    <div class="mt-4 text-center">
                                        <button class="text-blue-600 hover:text-blue-800 text-sm font-medium transition-colors duration-200">
                                            <i class="fas fa-chevron-down mr-1"></i>
                                            Voir plus d'historique
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Sécurité du compte -->
                            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                <div class="p-6 border-b border-gray-100">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <i class="fas fa-shield-alt text-red-600 mr-2"></i>
                                        Sécurité
                                    </h3>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-xl">
                                        <div class="flex items-center space-x-3">
                                            <i class="fas fa-check-circle text-green-600"></i>
                                            <div>
                                                <p class="text-sm font-medium text-green-900">Authentification</p>
                                                <p class="text-xs text-green-700">Connexion sécurisée</p>
                                            </div>
                                        </div>
                                        <span class="text-green-600 text-xs font-medium bg-green-100 px-2 py-1 rounded-full">
                                            Actif
                                        </span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-xl">
                                        <div class="flex items-center space-x-3">
                                            <i class="fas fa-lock text-blue-600"></i>
                                            <div>
                                                <p class="text-sm font-medium text-blue-900">Mot de passe</p>
                                                <p class="text-xs text-blue-700">Dernière modification il y a <?= isset($profile_history[0]) && $profile_history[0]['field_changed'] === 'password' ? date_diff(new DateTime($profile_history[0]['changed_at']), new DateTime())->days . ' jours' : 'plus de 30 jours' ?></p>
                                            </div>
                                        </div>
                                        <span class="text-blue-600 text-xs font-medium bg-blue-100 px-2 py-1 rounded-full">
                                            Sécurisé
                                        </span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between p-3 bg-orange-50 rounded-xl">
                                        <div class="flex items-center space-x-3">
                                            <i class="fas fa-mobile-alt text-orange-600"></i>
                                            <div>
                                                <p class="text-sm font-medium text-orange-900">Double authentification</p>
                                                <p class="text-xs text-orange-700">Recommandé pour plus de sécurité</p>
                                            </div>
                                        </div>
                                        <button class="text-orange-600 text-xs font-medium bg-orange-100 hover:bg-orange-200 px-3 py-1 rounded-full transition-colors duration-200">
                                            Configurer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Toast notifications -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <script>
        // Validation JavaScript améliorée avec animations
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('profileForm');
            const submitBtn = document.getElementById('submitBtn');
            const submitBtnText = document.getElementById('submitBtnText');
            
            // Validation en temps réel
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const currentPasswordInput = document.getElementById('current_password');
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            // Animation d'entrée pour les éléments
            const animateElements = document.querySelectorAll('.animate-fade-in');
            animateElements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Validation du nom
            nameInput.addEventListener('input', function() {
                validateField(this, this.value.trim().length >= 2, 'Le nom doit contenir au moins 2 caractères');
            });
            
            // Validation de l'email
            emailInput.addEventListener('input', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                validateField(this, emailRegex.test(this.value), 'Veuillez entrer une adresse email valide');
            });
            
            // Validation des mots de passe
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = checkPasswordStrength(password);
                updatePasswordStrength(strength);
                
                if (password.length > 0) {
                    validateField(this, password.length >= 6, 'Le mot de passe doit contenir au moins 6 caractères');
                    
                    if (currentPasswordInput.value.length === 0) {
                        validateField(currentPasswordInput, false, 'Le mot de passe actuel est requis pour changer le mot de passe');
                    }
                } else {
                    clearFieldError(this);
                    hidePasswordStrength();
                }
                
                if (confirmPasswordInput.value.length > 0) {
                    validateField(confirmPasswordInput, password === confirmPasswordInput.value, 'Les mots de passe ne correspondent pas');
                }
            });
            
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value.length > 0) {
                    validateField(this, this.value === newPasswordInput.value, 'Les mots de passe ne correspondent pas');
                } else {
                    clearFieldError(this);
                }
            });
            
            currentPasswordInput.addEventListener('input', function() {
                if (newPasswordInput.value.length > 0 && this.value.length === 0) {
                    validateField(this, false, 'Le mot de passe actuel est requis pour changer le mot de passe');
                } else {
                    clearFieldError(this);
                }
            });
            
            // Soumission du formulaire avec animation
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (validateForm()) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                    submitBtnText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';
                    
                    showToast('Enregistrement en cours...', 'info');
                    
                    setTimeout(() => {
                        this.submit();
                    }, 500);
                }
            });
        });
        
        function validateField(field, isValid, errorMessage) {
            const errorDiv = field.closest('div').querySelector('.error-message');
            const inputWrapper = field.closest('.group');
            
            if (isValid) {
                field.classList.remove('border-red-500', 'ring-red-500');
                field.classList.add('border-green-500', 'ring-green-500');
                inputWrapper?.classList.add('validate-success');
                inputWrapper?.classList.remove('validate-error');
                errorDiv?.classList.add('hidden');
            } else {
                field.classList.remove('border-green-500', 'ring-green-500');
                field.classList.add('border-red-500', 'ring-red-500');
                inputWrapper?.classList.add('validate-error');
                inputWrapper?.classList.remove('validate-success');
                if (errorDiv) {
                    errorDiv.textContent = errorMessage;
                    errorDiv.classList.remove('hidden');
                }
            }
        }
        
        function clearFieldError(field) {
            const errorDiv = field.closest('div').querySelector('.error-message');
            const inputWrapper = field.closest('.group');
            
            field.classList.remove('border-red-500', 'border-green-500', 'ring-red-500', 'ring-green-500');
            inputWrapper?.classList.remove('validate-error', 'validate-success');
            errorDiv?.classList.add('hidden');
        }
        
        function validateForm() {
            let isValid = true;
            
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value;
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (name.length < 2) {
                validateField(document.getElementById('name'), false, 'Le nom doit contenir au moins 2 caractères');
                isValid = false;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                validateField(document.getElementById('email'), false, 'Veuillez entrer une adresse email valide');
                isValid = false;
            }
            
            if (newPassword.length > 0) {
                if (currentPassword.length === 0) {
                    validateField(document.getElementById('current_password'), false, 'Le mot de passe actuel est requis');
                    isValid = false;
                }
                
                if (newPassword.length < 6) {
                    validateField(document.getElementById('new_password'), false, 'Le mot de passe doit contenir au moins 6 caractères');
                    isValid = false;
                }
                
                if (newPassword !== confirmPassword) {
                    validateField(document.getElementById('confirm_password'), false, 'Les mots de passe ne correspondent pas');
                    isValid = false;
                }
            }
            
            return isValid;
        }
        
        function checkPasswordStrength(password) {
            let score = 0;
            let feedback = [];
            
            if (password.length >= 8) score += 1;
            else feedback.push('Au moins 8 caractères');
            
            if (/[a-z]/.test(password)) score += 1;
            else feedback.push('Une minuscule');
            
            if (/[A-Z]/.test(password)) score += 1;
            else feedback.push('Une majuscule');
            
            if (/[0-9]/.test(password)) score += 1;
            else feedback.push('Un chiffre');
            
            if (/[^A-Za-z0-9]/.test(password)) score += 1;
            else feedback.push('Un caractère spécial');
            
            return { score, feedback };
        }
        
        function updatePasswordStrength(strength) {
            const strengthContainer = document.querySelector('.password-strength');
            const strengthBar = document.querySelector('.password-strength-bar');
            const strengthText = document.querySelector('.password-strength-text');
            
            if (document.getElementById('new_password').value.length === 0) {
                hidePasswordStrength();
                return;
            }
            
            strengthContainer.classList.remove('hidden');
            
            const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];
            const labels = ['Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
            const widths = ['20%', '40%', '60%', '80%', '100%'];
            
            strengthBar.style.width = widths[strength.score];
            strengthBar.style.backgroundColor = colors[strength.score];
            strengthText.textContent = `Force: ${labels[strength.score]}`;
            strengthText.style.color = colors[strength.score];
            
            if (strength.feedback.length > 0) {
                strengthText.textContent += ` - Manque: ${strength.feedback.join(', ')}`;
            }
        }
        
        function hidePasswordStrength() {
            document.querySelector('.password-strength').classList.add('hidden');
        }
        
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Système de notifications toast
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            const colors = {
                success: 'from-green-500 to-emerald-500',
                error: 'from-red-500 to-pink-500',
                info: 'from-blue-500 to-purple-500',
                warning: 'from-yellow-500 to-orange-500'
            };
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-triangle',
                info: 'fa-info-circle',
                warning: 'fa-exclamation-triangle'
            };
            
            toast.className = `transform transition-all duration-300 translate-x-full bg-gradient-to-r ${colors[type]} text-white px-6 py-4 rounded-xl shadow-lg flex items-center space-x-3 max-w-sm`;
            
            toast.innerHTML = `
                <i class="fas ${icons[type]} text-xl"></i>
                <span class="flex-1">${message}</span>
                <button onclick="this.parentElement.remove()" class="text-white hover:text-gray-200 transition-colors duration-200">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.getElementById('toast-container').appendChild(toast);
            
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);
            
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Confirmation avant de quitter si des modifications sont en cours
        let formChanged = false;
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('profileForm');
            const inputs = form.querySelectorAll('input, textarea, select');
            
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    formChanged = true;
                });
            });
            
            window.addEventListener('beforeunload', function(e) {
                if (formChanged) {
                    e.preventDefault();
                    e.returnValue = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter?';
                }
            });
            
            form.addEventListener('submit', function() {
                formChanged = false;
            });
        });
        
        // Formatage automatique des champs
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('name');
            
            nameInput.addEventListener('blur', function() {
                this.value = this.value.replace(/\w\S*/g, (txt) => 
                    txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
                );
            });
        });
        
        // Animation au scroll pour l'historique
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in');
                }
            });
        }, observerOptions);
        
        document.querySelectorAll('.space-y-4 > div').forEach(el => {
            observer.observe(el);
        });
    </script>
    
    <style>
        .validate-success {
            animation: successPulse 0.3s ease-in-out;
        }
        
        .validate-error {
            animation: errorShake 0.3s ease-in-out;
        }
        
        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .group:hover input {
            border-color: rgba(59, 130, 246, 0.3);
            transition: border-color 0.2s ease-in-out;
        }
        
        /* Scrollbar personnalisée pour l'historique */
        .max-h-96::-webkit-scrollbar {
            width: 4px;
        }
        
        .max-h-96::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 2px;
        }
        
        .max-h-96::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }
        
        .max-h-96::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Animations personnalisées */
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }

        .animate-slide-up {
            animation: slideUp 0.4s ease-out forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Effets de survol pour les cards */
        .hover\:shadow-2xl:hover {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        /* Gradients personnalisés pour les backgrounds */
        .bg-gradient-to-br {
            background: linear-gradient(135deg, var(--tw-gradient-stops));
        }

        /* Backdrop blur pour les éléments flottants */
        .backdrop-blur-sm {
            backdrop-filter: blur(4px);
        }

        /* Effets de glow pour les éléments interactifs */
        .focus\:ring-blue-500:focus {
            --tw-ring-color: rgb(59 130 246 / 0.5);
            box-shadow: 0 0 0 3px var(--tw-ring-color);
        }

        /* Transition fluide pour tous les éléments */
        * {
            transition: all 0.2s ease-in-out;
        }

        /* Styles pour les états de validation */
        .border-green-500 {
            border-color: rgb(34 197 94);
            box-shadow: 0 0 0 1px rgb(34 197 94 / 0.2);
        }

        .border-red-500 {
            border-color: rgb(239 68 68);
            box-shadow: 0 0 0 1px rgb(239 68 68 / 0.2);
        }

        /* Amélioration des boutons */
        button:active {
            transform: scale(0.98);
        }

        /* Styles pour les notifications toast */
        #toast-container {
            pointer-events: none;
        }

        #toast-container > div {
            pointer-events: auto;
        }

        /* Animation pour le loader */
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .fa-spin {
            animation: spin 1s linear infinite;
        }
    </style>
</body>
</html>