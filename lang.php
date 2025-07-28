<?php
// lang.php

// Langue par défaut
$defaultLang = 'fr';

// Déterminer la langue via GET ou SESSION
if (isset($_GET['lang'])) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
} elseif (isset($_SESSION['lang'])) {
    $lang = $_SESSION['lang'];
} else {
    $lang = $defaultLang;
}

// Sécuriser la langue (évite inclusion de fichiers non valides)
$langFile = __DIR__ . '/lang/' . basename($lang) . '.php';

if (file_exists($langFile)) {
    $traduction = include $langFile;
} else {
    include("langues/fr.php");

}
