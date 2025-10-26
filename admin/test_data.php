<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

try {
    // Test connexion
    $conn->query("SELECT 1");
    
    // Test employes
    $stmt = $conn->query("SELECT COUNT(*) as count FROM employes WHERE statut = 'actif'");
    $employesCount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Test bulletins
    $stmt = $conn->query("SELECT COUNT(*) as count FROM bulletins_paie");
    $bulletinsCount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Test structure bulletins
    $stmt = $conn->query("DESCRIBE bulletins_paie");
    $structureBulletins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Test premier bulletin
    $stmt = $conn->query("SELECT * FROM bulletins_paie LIMIT 1");
    $premierBulletin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Test employes structure
    $stmt = $conn->query("DESCRIBE employes");
    $structureEmployes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'connexion' => 'OK',
        'employes_count' => $employesCount['count'],
        'bulletins_count' => $bulletinsCount['count'],
        'structure_bulletins' => $structureBulletins,
        'premier_bulletin' => $premierBulletin,
        'structure_employes' => $structureEmployes
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}