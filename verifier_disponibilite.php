<?php
require_once 'config.php';

header('Content-Type: application/json');

$nom = isset($_GET['id']) ? $_GET['id'] : '';

if (!empty($nom)) {
    try {
        $stmt = $conn->prepare("SELECT disponible FROM plats WHERE nom = ?");
        $stmt->execute([$nom]);
        $plat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'disponible' => $plat ? ($plat['disponible'] == 1) : false
        ]);
    } catch (PDOException $e) {
        echo json_encode(['disponible' => false]);
    }
} else {
    echo json_encode(['disponible' => false]);
}
?>