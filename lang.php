<?php
// Ne pas laisser d'espace avant <?php
declare(strict_types=1);

// Vérification de sécurité
if (defined('LANG_LOADED')) {
    die('Accès direct interdit');
}
define('LANG_LOADED', true);

// Démarrer la session de manière sécurisée
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Configuration
$defaultLang = 'fr';
$allowedLangs = ['fr', 'en', 'wo']; // Langues autorisées
$langDir = __DIR__ . '/langues/';

// Validation de la langue
$lang = $defaultLang;
if (isset($_GET['lang']) && in_array($_GET['lang'], $allowedLangs, true)) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
} elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $allowedLangs, true)) {
    $lang = $_SESSION['lang'];
}

// Chemin sécurisé du fichier de langue
$langFile = realpath($langDir . $lang . '.php');
$defaultLangFile = realpath($langDir . $defaultLang . '.php');

// Chargement sécurisé des traductions
$traduction = [];
try {
    if ($langFile && is_readable($langFile)) {
        $traduction = include $langFile;
    } elseif ($defaultLangFile && is_readable($defaultLangFile)) {
        $traduction = include $defaultLangFile;
    }
} catch (Throwable $e) {
    error_log("Erreur chargement langue: " . $e->getMessage());
}

// Fallback minimal sécurisé
if (!is_array($traduction)) {
    $traduction = [
        'home' => 'Accueil',
        'about' => 'À propos',
        'menu' => 'Menu',
        'events' => 'Événements',
        'gallery' => 'Galerie',
        'order' => 'Commander',
        'contact' => 'Contact',
        'book_table' => 'Réserver une table'
    ];
}

// Protection contre XSS
array_walk($traduction, function(&$value) {
    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
});
?>