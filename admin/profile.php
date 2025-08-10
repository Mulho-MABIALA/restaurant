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
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-user-cog text-blue-600"></i>
                        <h1 class="text-xl font-semibold text-gray-900">Mon Profil</h1>
                    </div>
                </div>
            </header>

            <!-- Contenu principal -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <div class="max-w-4xl mx-auto">
                    <!-- Messages d'erreur/succès -->
                    <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Carte Profil -->
                    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                        <!-- En-tête -->
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-6">
                            <div class="flex items-center space-x-4">
                                <!-- Avatar par défaut -->
                                <div class="flex-shrink-0">
                                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-white text-2xl"></i>
                                    </div>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($admin_name) ?></h2>
                                    <p class="text-blue-100"><?= htmlspecialchars($admin_email) ?></p>
                                    <p class="text-blue-200 text-sm mt-1">
                                        <i class="fas fa-shield-alt mr-1"></i>
                                        Administrateur
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Formulaire -->
                        <form method="POST" class="p-6 space-y-8" id="profileForm">
                            <!-- Section Informations personnelles -->
                            <div class="border-b border-gray-200 pb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-user mr-2 text-blue-600"></i>
                                    Informations personnelles
                                </h3>
                                
                                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <!-- Nom -->
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700">
                                            Nom complet <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($admin_name) ?>"
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                            required>
                                        <div class="error-message hidden text-red-500 text-xs mt-1"></div>
                                    </div>
                                    
                                    <!-- Email -->
                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700">
                                            Adresse email <span class="text-red-500">*</span>
                                        </label>
                                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin_email) ?>"
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                            required>
                                        <div class="error-message hidden text-red-500 text-xs mt-1"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section Changement de mot de passe -->
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                    <i class="fas fa-lock mr-2 text-blue-600"></i>
                                    Changer le mot de passe
                                </h3>
                                
                                <div class="space-y-4">
                                    <!-- Mot de passe actuel -->
                                    <div>
                                        <label for="current_password" class="block text-sm font-medium text-gray-700">
                                            Mot de passe actuel
                                        </label>
                                        <div class="relative">
                                            <input type="password" id="current_password" name="current_password"
                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 pr-10 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('current_password')">
                                                <i class="fas fa-eye text-gray-400" id="current_password_icon"></i>
                                            </button>
                                        </div>
                                        <div class="error-message hidden text-red-500 text-xs mt-1"></div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <!-- Nouveau mot de passe -->
                                        <div>
                                            <label for="new_password" class="block text-sm font-medium text-gray-700">
                                                Nouveau mot de passe
                                            </label>
                                            <div class="relative">
                                                <input type="password" id="new_password" name="new_password"
                                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 pr-10 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('new_password')">
                                                    <i class="fas fa-eye text-gray-400" id="new_password_icon"></i>
                                                </button>
                                            </div>
                                            <div class="password-strength mt-1 hidden">
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="password-strength-bar h-2 rounded-full transition-all duration-300"></div>
                                                </div>
                                                <p class="password-strength-text text-xs mt-1"></p>
                                            </div>
                                            <div class="error-message hidden text-red-500 text-xs mt-1"></div>
                                        </div>
                                        
                                        <!-- Confirmation -->
                                        <div>
                                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">
                                                Confirmer le mot de passe
                                            </label>
                                            <div class="relative">
                                                <input type="password" id="confirm_password" name="confirm_password"
                                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 pr-10 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('confirm_password')">
                                                    <i class="fas fa-eye text-gray-400" id="confirm_password_icon"></i>
                                                </button>
                                            </div>
                                            <div class="error-message hidden text-red-500 text-xs mt-1"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                                        <div class="flex">
                                            <i class="fas fa-info-circle text-blue-400 mr-2 mt-0.5"></i>
                                            <div class="text-sm text-blue-700">
                                                <p class="font-medium">Conseils pour un mot de passe sécurisé :</p>
                                                <ul class="mt-1 list-disc list-inside space-y-1 text-xs">
                                                    <li>Au moins 8 caractères</li>
                                                    <li>Mélange de majuscules et minuscules</li>
                                                    <li>Au moins un chiffre</li>
                                                    <li>Au moins un caractère spécial</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Boutons -->
                            <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                                <div class="text-sm text-gray-500">
                                    <i class="fas fa-clock mr-1"></i>
                                    Dernière modification : <?= date('d/m/Y à H:i') ?>
                                </div>
                                
                                <div class="flex space-x-3">
                                    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <i class="fas fa-times mr-2"></i>
                                        Annuler
                                    </a>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="submitBtn">
                                        <i class="fas fa-save mr-2"></i>
                                        <span id="submitBtnText">Enregistrer les modifications</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Validation JavaScript
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
                    
                    // Vérifier si le mot de passe actuel est requis
                    if (currentPasswordInput.value.length === 0) {
                        validateField(currentPasswordInput, false, 'Le mot de passe actuel est requis pour changer le mot de passe');
                    }
                } else {
                    clearFieldError(this);
                    hidePasswordStrength();
                }
                
                // Revalider la confirmation
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
            
            // Soumission du formulaire
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (validateForm()) {
                    // Afficher un indicateur de chargement
                    submitBtn.disabled = true;
                    submitBtnText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';
                    
                    // Soumettre le formulaire
                    this.submit();
                }
            });
        });
        
        function validateField(field, isValid, errorMessage) {
            const errorDiv = field.parentNode.querySelector('.error-message');
            
            if (isValid) {
                field.classList.remove('border-red-500');
                field.classList.add('border-green-500');
                errorDiv.classList.add('hidden');
            } else {
                field.classList.remove('border-green-500');
                field.classList.add('border-red-500');
                errorDiv.textContent = errorMessage;
                errorDiv.classList.remove('hidden');
            }
        }
        
        function clearFieldError(field) {
            const errorDiv = field.parentNode.querySelector('.error-message');
            field.classList.remove('border-red-500', 'border-green-500');
            errorDiv.classList.add('hidden');
        }
        
        function validateForm() {
            let isValid = true;
            
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value;
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Validation du nom
            if (name.length < 2) {
                validateField(document.getElementById('name'), false, 'Le nom doit contenir au moins 2 caractères');
                isValid = false;
            }
            
            // Validation de l'email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                validateField(document.getElementById('email'), false, 'Veuillez entrer une adresse email valide');
                isValid = false;
            }
            
            // Validation des mots de passe
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
        
        // Fonction pour basculer la visibilité du mot de passe
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
            
            // Capitaliser automatiquement la première lettre de chaque mot
            nameInput.addEventListener('blur', function() {
                this.value = this.value.replace(/\w\S*/g, (txt) => 
                    txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
                );
            });
        });
    </script>
</body>
</html>