<?php
require_once '../config.php';

// Vérifier si TCPDF est disponible
$tcpdf_available = false;
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
    $tcpdf_available = class_exists('TCPDF');
}

function sendJsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function calculerSalaireNet($conn, $employe_id, $mois_annee) {
    $stmt = $conn->prepare("
        SELECT e.*, 
               p.salaire AS salaire_poste, 
               p.taux_cotisation, 
               p.categorie_paie, 
               p.regime_social,
               p.nom as poste_nom
        FROM employes e
        LEFT JOIN postes p ON e.poste_id = p.id
        WHERE e.id = ?
    ");
    $stmt->execute([$employe_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        throw new Exception("Employé non trouvé.");
    }
    
    $salaire_brut = $data['salaire'] ?: ($data['salaire_poste'] ?: 0);
    
    $primes_individuelles = 0;
    $absences_jours = 0;
    $retenues_diverses = 0;
    $cotisations_supplementaires = 0;
    
    $retenues_absences = ($absences_jours * $salaire_brut) / 30;
    $salaire_brut_apres_absences = $salaire_brut - $retenues_absences + $primes_individuelles;
    
    $taux_cotisations = ($data['taux_cotisation'] ?: 0) + $cotisations_supplementaires;
    $cotisations = $salaire_brut_apres_absences * ($taux_cotisations / 100);
    
    $salaire_net = $salaire_brut_apres_absences - $cotisations - $retenues_diverses;
    
    return [
        'salaire_brut' => $salaire_brut,
        'salaire_brut_apres_absences' => $salaire_brut_apres_absences,
        'primes' => $primes_individuelles,
        'retenues_absences' => $retenues_absences,
        'cotisations' => $cotisations,
        'retenues_diverses' => $retenues_diverses,
        'salaire_net' => $salaire_net,
        'mois_annee' => $mois_annee,
        'employe_id' => $employe_id,
        'poste_id' => $data['poste_id'],
        'categorie_paie' => $data['categorie_paie'],
        'regime_social' => $data['regime_social'],
        'nom' => $data['nom'],
        'prenom' => $data['prenom'],
        'poste_nom' => $data['poste_nom']
    ];
}

function genererBulletinPaie($details, $conn) {
    global $tcpdf_available;
    
    if ($tcpdf_available) {
        return genererBulletinPDF($details);
    } else {
        // Si TCPDF n'est pas disponible, on génère un PDF simple avec la librairie native
        return genererBulletinPDFSimple($details);
    }
}

function genererBulletinPDF($details) {
    // Créer une instance TCPDF avec des paramètres corrects
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Informations du document
    $pdf->SetCreator('Système de Gestion RH');
    $pdf->SetAuthor('Restaurant Management System');
    $pdf->SetTitle('Bulletin de Paie - ' . $details['nom'] . ' ' . $details['prenom']);
    $pdf->SetSubject('Bulletin de Paie');
    
    // Supprimer l'en-tête et le pied de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 25);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // En-tête du bulletin
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 15, 'BULLETIN DE PAIE', 0, 1, 'C');
    
    $pdf->Ln(5);
    
    // Informations employé
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, strtoupper($details['nom'] . ' ' . $details['prenom']), 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'Poste: ' . ($details['poste_nom'] ?: 'Non défini'), 0, 1, 'C');
    $pdf->Cell(0, 8, 'Période: ' . date('F Y', strtotime($details['mois_annee'] . '-01')), 0, 1, 'C');
    
    $pdf->Ln(10);
    
    // Ligne de séparation
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(5);
    
    // Titre section détails
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 10, 'DÉTAILS DU SALAIRE', 0, 1, 'L');
    
    $pdf->Ln(5);
    
    // Tableau des éléments de paie
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    
    $lignes = [
        ['Salaire brut de base', number_format($details['salaire_brut'], 0, ',', ' ') . ' FCFA'],
        ['Primes individuelles', number_format($details['primes'], 0, ',', ' ') . ' FCFA'],
        ['Retenues pour absences', '-' . number_format($details['retenues_absences'], 0, ',', ' ') . ' FCFA'],
        ['Cotisations sociales', '-' . number_format($details['cotisations'], 0, ',', ' ') . ' FCFA'],
        ['Autres retenues', '-' . number_format($details['retenues_diverses'], 0, ',', ' ') . ' FCFA']
    ];
    
    foreach ($lignes as $ligne) {
        $pdf->Cell(120, 8, $ligne[0], 0, 0, 'L');
        $pdf->Cell(60, 8, $ligne[1], 0, 1, 'R');
    }
    
    $pdf->Ln(10);
    
    // Ligne de séparation pour le total
    $pdf->SetDrawColor(0, 51, 102);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(5);
    
    // Salaire net
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(0, 128, 0);
    $pdf->Cell(120, 12, 'SALAIRE NET À PAYER:', 0, 0, 'L');
    $pdf->Cell(60, 12, number_format($details['salaire_net'], 0, ',', ' ') . ' FCFA', 0, 1, 'R');
    
    $pdf->Ln(20);
    
    // Pied de page
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'Document généré automatiquement le ' . date('d/m/Y à H:i'), 0, 1, 'C');
    
    // Retourner le contenu du PDF
    return $pdf->Output('', 'S');
}

function genererBulletinPDFSimple($details) {
    // Version de fallback qui génère un HTML qui sera converti en PDF côté client
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Bulletin de Paie</title>
        <style>
            @page { size: A4; margin: 20mm; }
            body { 
                font-family: 'Arial', sans-serif; 
                margin: 0; 
                padding: 0; 
                color: #333;
                line-height: 1.4;
            }
            .header { 
                text-align: center; 
                margin-bottom: 30px; 
                border-bottom: 3px solid #003366;
                padding-bottom: 20px;
            }
            .header h1 {
                color: #003366;
                font-size: 24px;
                margin: 0 0 10px 0;
            }
            .header h2 {
                font-size: 18px;
                margin: 5px 0;
                color: #000;
            }
            .header p {
                margin: 3px 0;
                color: #666;
            }
            .details { 
                margin: 30px 0; 
            }
            .details h3 {
                color: #003366;
                border-bottom: 2px solid #ccc;
                padding-bottom: 5px;
                margin-bottom: 20px;
            }
            .line { 
                display: flex; 
                justify-content: space-between; 
                padding: 8px 0; 
                border-bottom: 1px dotted #ccc;
            }
            .line:last-of-type {
                border-bottom: none;
            }
            .total { 
                font-weight: bold; 
                color: #008000; 
                font-size: 20px; 
                border-top: 3px solid #003366; 
                padding-top: 15px;
                margin-top: 20px;
                background-color: #f9f9f9;
                padding: 15px;
            }
            .footer {
                margin-top: 40px;
                text-align: center;
                font-size: 10px;
                color: #999;
            }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>BULLETIN DE PAIE</h1>
            <h2>" . strtoupper($details['nom'] . ' ' . $details['prenom']) . "</h2>
            <p><strong>Poste:</strong> " . ($details['poste_nom'] ?: 'Non défini') . "</p>
            <p><strong>Période:</strong> " . date('F Y', strtotime($details['mois_annee'] . '-01')) . "</p>
        </div>
        
        <div class='details'>
            <h3>DÉTAILS DU SALAIRE</h3>
            <div class='line'>
                <span>Salaire brut de base</span>
                <span>" . number_format($details['salaire_brut'], 0, ',', ' ') . " FCFA</span>
            </div>
            <div class='line'>
                <span>Primes individuelles</span>
                <span>" . number_format($details['primes'], 0, ',', ' ') . " FCFA</span>
            </div>
            <div class='line'>
                <span>Retenues pour absences</span>
                <span>-" . number_format($details['retenues_absences'], 0, ',', ' ') . " FCFA</span>
            </div>
            <div class='line'>
                <span>Cotisations sociales</span>
                <span>-" . number_format($details['cotisations'], 0, ',', ' ') . " FCFA</span>
            </div>
            <div class='line'>
                <span>Autres retenues</span>
                <span>-" . number_format($details['retenues_diverses'], 0, ',', ' ') . " FCFA</span>
            </div>
            <div class='line total'>
                <span>SALAIRE NET À PAYER</span>
                <span>" . number_format($details['salaire_net'], 0, ',', ' ') . " FCFA</span>
            </div>
        </div>
        
        <div class='footer'>
            <p>Document généré automatiquement le " . date('d/m/Y à H:i') . "</p>
        </div>
    </body>
    </html>";
    
    return $html;
}

function enregistrerBulletinPaie($conn, $details) {
    $stmt = $conn->prepare("
        INSERT INTO bulletins_paie
        (employe_id, poste_id, mois_annee, salaire_brut, cotisations, salaire_net, primes, retenues, statut)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'valide')
    ");
    $stmt->execute([
        $details['employe_id'],
        $details['poste_id'],
        $details['mois_annee'] . '-01',
        $details['salaire_brut_apres_absences'],
        $details['cotisations'],
        $details['salaire_net'],
        $details['primes'],
        $details['retenues_absences'] + $details['retenues_diverses']
    ]);
    return $conn->lastInsertId();
}

// Traitement principal
try {
    // Nettoyer le buffer de sortie pour éviter les caractères parasites
    if (ob_get_level()) {
        ob_clean();
    }
    
    if (empty($_POST['employe_id']) || empty($_POST['mois_annee'])) {
        throw new Exception('Employé ou mois manquant.');
    }

    $employe_id = intval($_POST['employe_id']);
    $mois_annee = $_POST['mois_annee'];

    // 1. Calculer le salaire net
    $details = calculerSalaireNet($conn, $employe_id, $mois_annee);

    // 2. Générer le bulletin
    $pdfContent = genererBulletinPaie($details, $conn);

    // 3. Enregistrer le bulletin dans la base de données
    $bulletin_id = enregistrerBulletinPaie($conn, $details);

    // 4. Vérifier si le contenu est du HTML ou du PDF
    $isHtml = strpos($pdfContent, '<!DOCTYPE html') !== false;
    
    // 5. Retourner la réponse appropriée
    sendJsonResponse([
        'success' => true,
        'pdf' => base64_encode($pdfContent),
        'bulletin_id' => $bulletin_id,
        'message' => 'Bulletin généré avec succès',
        'type' => $isHtml ? 'html' : 'pdf'
    ]);

} catch (Exception $e) {
    // Nettoyer le buffer en cas d'erreur aussi
    if (ob_get_level()) {
        ob_clean();
    }
    
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>