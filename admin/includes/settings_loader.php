<?php
/**
 * Chargeur de paramètres système
 * Charge tous les paramètres depuis la base de données et les rend disponibles globalement
 */

// Vérifier que la connexion DB existe
if (!isset($conn)) {
    require_once __DIR__ . '/../../config.php';
}

// Variable globale pour stocker les paramètres
global $system_settings;

// Fonction pour récupérer un paramètre
function getSetting($key, $default = '') {
    global $system_settings;
    return $system_settings[$key] ?? $default;
}

// Fonction pour récupérer tous les paramètres
function getAllSettings() {
    global $system_settings;
    return $system_settings ?? [];
}

// Charger les paramètres depuis la base de données
try {
    $stmt = $conn->query("SELECT setting_key, setting_value FROM settings");
    $system_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    // Si erreur, initialiser avec des valeurs par défaut
    $system_settings = [
        'restaurant_name' => 'Restaurant Mulho',
        'restaurant_email' => 'contact@restaurant.com',
        'restaurant_phone' => '+221 XX XXX XX XX',
        'restaurant_address' => 'Dakar, Sénégal',
        'currency' => 'FCFA',
        'tax_rate' => '0',
        'delivery_fee' => '0',
        'min_order_amount' => '0'
    ];
}

// Définir des constantes pour un accès facile
if (!defined('RESTAURANT_NAME')) {
    define('RESTAURANT_NAME', getSetting('restaurant_name', 'Restaurant Mulho'));
}
if (!defined('RESTAURANT_EMAIL')) {
    define('RESTAURANT_EMAIL', getSetting('contact_email', 'contact@restaurant.com'));
}
if (!defined('RESTAURANT_PHONE')) {
    define('RESTAURANT_PHONE', getSetting('contact_phone', '+221 XX XXX XX XX'));
}
if (!defined('RESTAURANT_ADDRESS')) {
    define('RESTAURANT_ADDRESS', getSetting('restaurant_address', 'Dakar, Sénégal'));
}
if (!defined('CURRENCY')) {
    define('CURRENCY', getSetting('currency', 'FCFA'));
}
?>
