<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    // Récupérer uniquement les avis validés
    $stmt = $conn->prepare("SELECT message, note, date_creation FROM avis WHERE valide = 1 ORDER BY date_creation DESC");
    $stmt->execute();
    $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '';
    foreach ($avis as $avi) {
        $stars = str_repeat('<i class="fas fa-star text-warning"></i>', $avi['note']) .
                 str_repeat('<i class="far fa-star text-warning"></i>', 5 - $avi['note']);

        $html .= '
        <div class="col-md-6" data-aos="fade-up">
            <div class="avis-card">
                <div class="avis-header">
                    <h4><i class="fas fa-user-circle"></i> Client anonyme</h4>
                    <div class="client-note">' . $stars . '</div>
                </div>
                <p class="mb-0">' . nl2br(htmlspecialchars($avi['message'])) . '</p>
                <small class="text-muted mt-2 d-block">' . date('d/m/Y', strtotime($avi['date_creation'])) . '</small>
            </div>
        </div>';
    }
    
    if (empty($html)) {
        $html = '<div class="col-12 text-center"><p>Aucun avis pour le moment.</p></div>';
    }
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des avis: " . $e->getMessage());
    echo json_encode(['success' => false, 'html' => '<div class="col-12 text-center"><p>Erreur lors du chargement des avis.</p></div>']);
}
?>