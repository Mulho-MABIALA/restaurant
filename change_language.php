<?php
session_start();
$allowed_langs = ['fr', 'en', 'wo'];
$lang = $_GET['lang'] ?? 'fr';

if(in_array($lang, $allowed_langs)) {
    $_SESSION['lang'] = $lang;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}