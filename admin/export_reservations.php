<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../config.php';

// Vérifier la session admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('Accès refusé');
}

// Test de connectivité
if (isset($_GET['test'])) {
    echo "Export endpoint OK";
    exit;
}

// IMPORTANT: Nettoyer tous les buffers existants
while (ob_get_level()) {
    ob_end_clean();
}

// Démarrer un nouveau buffer propre
ob_start();

try {
    // Récupérer les filtres de l'URL
    $search = $_GET['search'] ?? '';
    $date_filter = $_GET['date_filter'] ?? '';
    $personnes_filter = $_GET['personnes_filter'] ?? '';

    // Construire la requête avec les mêmes filtres que la page principale
    $query = "SELECT id, nom, email, telephone, personnes, date_reservation, 
              heure_reservation, message, date_envoi, statut 
              FROM reservations WHERE 1=1";
    $params = [];

    // Appliquer les filtres
    if (!empty($search)) {
        $query .= " AND (nom LIKE ? OR email LIKE ? OR telephone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($date_filter)) {
        $query .= " AND date_reservation = ?";
        $params[] = $date_filter;
    }

    if (!empty($personnes_filter)) {
        if ($personnes_filter === '1-2') {
            $query .= " AND personnes BETWEEN 1 AND 2";
        } elseif ($personnes_filter === '3-4') {
            $query .= " AND personnes BETWEEN 3 AND 4";
        } elseif ($personnes_filter === '5+') {
            $query .= " AND personnes >= 5";
        }
    }

    $query .= " ORDER BY date_envoi DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Vérifier et charger TCPDF avec une approche plus robuste
    if (!class_exists('TCPDF')) {
        // Essayer différents chemins possibles
        $tcpdf_paths = [
            __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
            __DIR__ . '/../vendor/tcpdf/tcpdf.php',
            __DIR__ . '/tcpdf/tcpdf.php'
        ];
        
        $tcpdf_loaded = false;
        foreach ($tcpdf_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $tcpdf_loaded = true;
                break;
            }
        }
        
        if (!$tcpdf_loaded) {
            throw new Exception('TCPDF non trouvé. Veuillez installer TCPDF avec: composer require tecnickcom/tcpdf');
        }
    }

    // Créer le PDF avec gestion d'erreur
    try {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    } catch (Exception $e) {
        throw new Exception('Erreur lors de l\'initialisation de TCPDF: ' . $e->getMessage());
    }

    // Configuration du PDF
    $pdf->SetCreator('Restaurant Admin');
    $pdf->SetAuthor('Restaurant');
    $pdf->SetTitle('Liste des Réservations');
    $pdf->SetSubject('Export des réservations');
    $pdf->SetKeywords('Réservations, Restaurant, Export');

    // Supprimer header et footer par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 20);

    // Ajouter une page
    $pdf->AddPage();

    // Définir la police
    $pdf->SetFont('helvetica', '', 10);

    // Titre
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'LISTE DES RÉSERVATIONS', 0, 1, 'C');
    $pdf->Ln(5);

    // Informations d'export
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'Exporté le : ' . date('d/m/Y à H:i:s'), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Nombre total : ' . count($reservations) . ' réservation(s)', 0, 1, 'L');
    
    // Afficher les filtres appliqués
    if (!empty($search) || !empty($date_filter) || !empty($personnes_filter)) {
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 8, 'Filtres appliqués :', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        
        if (!empty($search)) {
            $pdf->Cell(0, 6, '• Recherche : ' . htmlspecialchars($search), 0, 1, 'L');
        }
        if (!empty($date_filter)) {
            $pdf->Cell(0, 6, '• Date : ' . htmlspecialchars($date_filter), 0, 1, 'L');
        }
        if (!empty($personnes_filter)) {
            $pdf->Cell(0, 6, '• Personnes : ' . htmlspecialchars($personnes_filter), 0, 1, 'L');
        }
    }
    
    $pdf->Ln(10);

    if (!empty($reservations)) {
        // En-têtes du tableau
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(230, 230, 230);
        
        // Largeurs des colonnes
        $w = array(15, 35, 45, 25, 20, 20, 20);
        $headers = array('ID', 'Nom', 'Email', 'Téléphone', 'Date', 'Heure', 'Pers.');
        
        for($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();

        // Données
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetFillColor(245, 245, 245);
        $fill = false;

        foreach ($reservations as $index => $res) {
            // Ajuster la hauteur de ligne si nécessaire
            $height = 6;
            
            $pdf->Cell($w[0], $height, $res['id'], 1, 0, 'C', $fill);
            
            // Gestion de l'encodage UTF-8 améliorée
            $nom = mb_convert_encoding($res['nom'], 'ISO-8859-1', 'UTF-8');
            $email = mb_convert_encoding($res['email'], 'ISO-8859-1', 'UTF-8');
            
            $pdf->Cell($w[1], $height, substr($nom, 0, 20), 1, 0, 'L', $fill);
            $pdf->Cell($w[2], $height, substr($email, 0, 25), 1, 0, 'L', $fill);
            $pdf->Cell($w[3], $height, $res['telephone'], 1, 0, 'L', $fill);
            $pdf->Cell($w[4], $height, date('d/m/Y', strtotime($res['date_reservation'])), 1, 0, 'C', $fill);
            $pdf->Cell($w[5], $height, substr($res['heure_reservation'], 0, 5), 1, 0, 'C', $fill);
            $pdf->Cell($w[6], $height, $res['personnes'], 1, 0, 'C', $fill);
            $pdf->Ln();
            
            $fill = !$fill;
            
            // Vérifier si on a besoin d'une nouvelle page
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                
                // Réimprimer les en-têtes
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetFillColor(230, 230, 230);
                for($i = 0; $i < count($headers); $i++) {
                    $pdf->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', 1);
                }
                $pdf->Ln();
                $pdf->SetFont('helvetica', '', 8);
            }
        }

        // Ajouter une section messages si il y en a
        $messages_found = false;
        foreach ($reservations as $res) {
            if (!empty($res['message'])) {
                $messages_found = true;
                break;
            }
        }

        if ($messages_found) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'MESSAGES DES CLIENTS', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);

            foreach ($reservations as $res) {
                if (!empty($res['message'])) {
                    $pdf->Ln(3);
                    $pdf->SetFont('helvetica', 'B', 9);
                    
                    $nom_message = mb_convert_encoding($res['nom'], 'ISO-8859-1', 'UTF-8');
                    $pdf->Cell(0, 6, $nom_message . ' (ID: ' . $res['id'] . ')', 0, 1, 'L');
                    
                    $pdf->SetFont('helvetica', '', 8);
                    $message = mb_convert_encoding(strip_tags($res['message']), 'ISO-8859-1', 'UTF-8');
                    $pdf->MultiCell(0, 5, $message, 0, 'L');
                }
            }
        }
        
    } else {
        $pdf->SetFont('helvetica', 'I', 12);
        $pdf->Cell(0, 20, 'Aucune réservation trouvée avec les critères sélectionnés.', 0, 1, 'C');
    }

    // Nettoyer le buffer de sortie
    ob_end_clean();

    // Générer le PDF en mémoire
    $pdf_content = $pdf->Output('', 'S');
    
    // Vérifier que le PDF a été généré
    if (empty($pdf_content)) {
        throw new Exception('Le PDF généré est vide');
    }

    // Headers HTTP pour le téléchargement
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="reservations_' . date('Y-m-d_H-i-s') . '.pdf"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . strlen($pdf_content));

    // Envoyer le PDF
    echo $pdf_content;
    exit;

} catch (Exception $e) {
    // Nettoyer le buffer en cas d'erreur
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log l'erreur
    error_log('Erreur export PDF: ' . $e->getMessage() . ' - Trace: ' . $e->getTraceAsString());
    
    // Retourner une erreur HTTP
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'Erreur lors de la génération du PDF: ' . $e->getMessage()
    ]);
    exit;
}
?>