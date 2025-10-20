<?php
// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Définir la langue par défaut
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'fr';
}

// Changer la langue si demandé
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en', 'es', 'wo'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Charger le fichier de traduction
$lang = $_SESSION['lang'];
$translations = [];

$lang_file = __DIR__ . "/languages/{$lang}.php";
if (file_exists($lang_file)) {
    $translations = include($lang_file);
} else {
    // Fallback vers le français si le fichier n'existe pas
    $translations = include(__DIR__ . "/languages/fr.php");
}

// Fonction helper pour obtenir une traduction
function t($key) {
    global $translations;

    // Gérer les clés imbriquées avec notation point (ex: "nav.home")
    $keys = explode('.', $key);
    $value = $translations;

    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $key; // Retourner la clé si traduction non trouvée
        }
    }

    return $value;
}

// Fonction pour obtenir la langue actuelle
function getCurrentLang() {
    return $_SESSION['lang'] ?? 'fr';
}

// Fonction pour obtenir toutes les langues disponibles
function getAvailableLanguages() {
    return [
        'fr' => ['name' => 'Français', 'flag' => '🇫🇷'],
        'en' => ['name' => 'English', 'flag' => '🇬🇧'],
        'es' => ['name' => 'Español', 'flag' => '🇪🇸'],
        'wo' => ['name' => 'Wolof', 'flag' => '🇸🇳']
    ];
}
