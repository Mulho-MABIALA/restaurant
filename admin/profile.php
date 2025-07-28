<?php
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config.php'; // Ta connexion PDO → ça doit définir $pdo, pas $conn


$conn = $conn ?? $conn;

// Vérifie que tes variables de session existent pour éviter les warnings
$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = $_SESSION['admin_name'] ?? '';
$admin_email = $_SESSION['admin_email'] ?? '';

if (!$admin_id) {
    // Si l'id est manquant, force une déconnexion
    header('Location: logout.php');
    exit;
}

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        // Vérification du mot de passe actuel si changement demandé
        if (!empty($current_password) || !empty($new_password)) {
            $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($current_password, $admin['password'])) {
                $error = "Mot de passe actuel incorrect";
            } elseif ($new_password !== $confirm_password) {
                $error = "Les nouveaux mots de passe ne correspondent pas";
            }
        }

        if (empty($error)) {
            // Mise à jour des informations
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admins SET name = ?, email = ?, password = ? WHERE id = ?");
                $stmt->execute([$new_name, $new_email, $hashed_password, $admin_id]);
            } else {
                $stmt = $conn->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$new_name, $new_email, $admin_id]);
            }

            // Met à jour la session
            $_SESSION['admin_name'] = $new_name;
            $_SESSION['admin_email'] = $new_email;

            $admin_name = $new_name;
            $admin_email = $new_email;

            $success = "Profil mis à jour avec succès";
        }
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
                    <h1 class="text-xl font-semibold text-gray-900">Mon Profil</h1>
                    <!-- Vous pouvez ajouter ici le bloc date/heure/profil si nécessaire -->
                </div>
            </header>

            <!-- Contenu principal -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <div class="max-w-3xl mx-auto">
                    <!-- Messages d'erreur/succès -->
                    <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <?= htmlspecialchars($success) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Carte Profil -->
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <!-- En-tête -->
                        <div class="bg-blue-600 px-6 py-4">
                            <h2 class="text-xl font-semibold text-white">Informations du compte</h2>
                        </div>
                        
                        <!-- Formulaire -->
                        <form method="POST" class="p-6 space-y-6">
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <!-- Nom -->
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700">Nom complet</label>
                                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($admin_name) ?>"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">Adresse email</label>
                                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin_email) ?>"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            
                            <div class="border-t border-gray-200 pt-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Changer le mot de passe</h3>
                                
                                <div class="space-y-4">
                                    <!-- Mot de passe actuel -->
                                    <div>
                                        <label for="current_password" class="block text-sm font-medium text-gray-700">Mot de passe actuel</label>
                                        <input type="password" id="current_password" name="current_password"
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <!-- Nouveau mot de passe -->
                                        <div>
                                            <label for="new_password" class="block text-sm font-medium text-gray-700">Nouveau mot de passe</label>
                                            <input type="password" id="new_password" name="new_password"
                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        
                                        <!-- Confirmation -->
                                        <div>
                                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirmer le mot de passe</label>
                                            <input type="password" id="confirm_password" name="confirm_password"
                                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Boutons -->
                            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                                <a href="dashboard.php" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Annuler
                                </a>
                                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>