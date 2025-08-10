<?php
session_start();
// Vérification de l'authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config.php'; // Configuration de la base de données

// Initialisation des variables
$success = '';
$error = '';
$settings = [];

    // Charger les paramètres existants
    try {
  $stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

} catch (PDOException $e) {
    $error = "Erreur lors du chargement des paramètres: " . $e->getMessage();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        foreach ($_POST['settings'] as $key => $value) {
            // Nettoyer les entrées
            $value = htmlspecialchars(trim($value));
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            
            // Mettre à jour ou insérer le paramètre
            $stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
        }
        
        $conn->commit();
        $success = "Paramètres mis à jour avec succès";
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Administration</title>
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
                    <h1 class="text-xl font-semibold text-gray-900">Paramètres du système</h1>
                </div>
            </header>

            <!-- Contenu principal -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <div class="max-w-4xl mx-auto">
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

                    <!-- Carte Paramètres -->
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <!-- En-tête -->
                        <div class="bg-blue-600 px-6 py-4">
                            <h2 class="text-xl font-semibold text-white">Configuration du système</h2>
                        </div>
                        
                        <!-- Formulaire -->
                        <form method="POST" class="p-6 space-y-6">
                            <!-- Section Générale -->
                            <div class="border-b border-gray-200 pb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Paramètres généraux</h3>
                                
                                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <!-- Nom du restaurant -->
                                    <div>
                                        <label for="settings[restaurant_name]" class="block text-sm font-medium text-gray-700">Nom du restaurant</label>
                                        <input type="text" id="settings[restaurant_name]" name="settings[restaurant_name]" 
                                            value="<?= htmlspecialchars($settings['restaurant_name'] ?? '') ?>" 
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    
                                    <!-- Email de contact -->
                                    <div>
                                        <label for="settings[contact_email]" class="block text-sm font-medium text-gray-700">Email de contact</label>
                                        <input type="email" id="settings[contact_email]" name="settings[contact_email]" 
                                            value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" 
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    
                                    <!-- Téléphone -->
                                    <div>
                                        <label for="settings[contact_phone]" class="block text-sm font-medium text-gray-700">Téléphone</label>
                                        <input type="text" id="settings[contact_phone]" name="settings[contact_phone]" 
                                            value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>" 
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    
                                    <!-- Adresse -->
                                    <div>
                                        <label for="settings[restaurant_address]" class="block text-sm font-medium text-gray-700">Adresse</label>
                                        <textarea id="settings[restaurant_address]" name="settings[restaurant_address]" rows="2"
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($settings['restaurant_address'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section Réservations -->
                            <div class="border-b border-gray-200 pb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Paramètres des réservations</h3>
                                
                                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <!-- Capacité maximale -->
                                    <div>
                                        <label for="settings[max_capacity]" class="block text-sm font-medium text-gray-700">Capacité maximale</label>
                                        <input type="number" id="settings[max_capacity]" name="settings[max_capacity]" 
                                            value="<?= htmlspecialchars($settings['max_capacity'] ?? '50') ?>" 
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    
                                    <!-- Délai de réservation -->
                                    <div>
                                        <label for="settings[booking_delay]" class="block text-sm font-medium text-gray-700">Délai minimum (heures)</label>
                                        <input type="number" id="settings[booking_delay]" name="settings[booking_delay]" 
                                            value="<?= htmlspecialchars($settings['booking_delay'] ?? '2') ?>" 
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    
                                    <!-- Heure d'ouverture -->
                                    <div>
                                        <label for="settings[opening_time]" class="block text-sm font-medium text-gray-700">Heure d'ouverture</label>
                                        <input type="time" id="settings[opening_time]" name="settings[opening_time]" 
                                            value="<?= htmlspecialchars($settings['opening_time'] ?? '11:00') ?>" 
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    
                                    <!-- Heure de fermeture -->
                                    <div>
                                        <label for="settings[closing_time]" class="block text-sm font-medium text-gray-700">Heure de fermeture</label>
                                        <input type="time" id="settings[closing_time]" name="settings[closing_time]" 
                                            value="<?= htmlspecialchars($settings['closing_time'] ?? '23:00') ?>" 
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section Notification -->
                            <div class="pb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Paramètres de notification</h3>
                                
                                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <!-- Notification email -->
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="settings[email_notifications]" name="settings[email_notifications]" type="checkbox" 
                                                value="1" <?= isset($settings['email_notifications']) && $settings['email_notifications'] ? 'checked' : '' ?>
                                                class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="settings[email_notifications]" class="font-medium text-gray-700">Activer les notifications par email</label>
                                            <p class="text-gray-500">Envoyer des emails pour les nouvelles réservations</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Email admin -->
                                    <div>
                                        <label for="settings[admin_email]" class="block text-sm font-medium text-gray-700">Email admin</label>
                                        <input type="email" id="settings[admin_email]" name="settings[admin_email]" 
                                            value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>" 
                                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Boutons -->
                            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                                <button type="reset" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Réinitialiser
                                </button>
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