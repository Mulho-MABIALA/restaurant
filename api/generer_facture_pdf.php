<?php
require_once '../config.php';
require_once '../vendor/autoload.php'; // Pour TCPDF ou FPDF

use TCPDF;

// Récupérer l'ID de la commande
$commande_id = $_GET['id'] ?? null;

if (!$commande_id) {
    die('ID de commande manquant');
}

// Récupérer les données de la commande
$stmt = $conn->prepare("
    SELECT *
    FROM commandes
    WHERE id = ?
");
$stmt->execute([$commande_id]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$commande) {
    die('Commande introuvable');
}

// Récupérer les détails de la commande
$stmt = $conn->prepare("
    SELECT *
    FROM commande_details
    WHERE commande_id = ?
");
$stmt->execute([$commande_id]);
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Créer le PDF avec TCPDF (ou utiliser FPDF si préféré)
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Informations du document
$pdf->SetCreator('Restaurant Management System');
$pdf->SetAuthor('Restaurant');
$pdf->SetTitle('Facture ' . $commande['numero_commande']);
$pdf->SetSubject('Facture de vente');

// Supprimer header/footer par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Ajouter une page
$pdf->AddPage();

// Logo et informations restaurant (en-tête)
$pdf->SetFont('helvetica', 'B', 20);
$pdf->Cell(0, 10, 'RESTAURANT', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'Adresse du restaurant', 0, 1, 'C');
$pdf->Cell(0, 5, 'Tél: +242 XX XX XX XX', 0, 1, 'C');
$pdf->Ln(10);

// Titre facture
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'FACTURE', 0, 1, 'C');
$pdf->Ln(5);

// Informations facture
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(95, 6, 'N° Facture: ' . $commande['numero_commande'], 0, 0);
$pdf->Cell(95, 6, 'Date: ' . date('d/m/Y', strtotime($commande['date_commande'])), 0, 1);

// Informations client
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'Client:', 0, 1);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $commande['nom_client'] ?: 'Client sur place', 0, 1);
if ($commande['email']) {
    $pdf->Cell(0, 6, 'Email: ' . $commande['email'], 0, 1);
}
if ($commande['telephone']) {
    $pdf->Cell(0, 6, 'Tél: ' . $commande['telephone'], 0, 1);
}

$pdf->Ln(10);

// Tableau des articles
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(90, 7, 'Désignation', 1, 0, 'L', true);
$pdf->Cell(30, 7, 'Qté', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'Prix Unit.', 1, 0, 'R', true);
$pdf->Cell(35, 7, 'Total', 1, 1, 'R', true);

// Lignes de détail
$pdf->SetFont('helvetica', '', 10);
$total_ht = 0;

foreach ($details as $detail) {
    $prix_unit = $detail['prix'] ?? 0;
    $quantite = $detail['quantite'] ?? 1;
    $total_ligne = $prix_unit * $quantite;
    $total_ht += $total_ligne;

    $pdf->Cell(90, 6, $detail['nom_plat'], 1, 0, 'L');
    $pdf->Cell(30, 6, $quantite, 1, 0, 'C');
    $pdf->Cell(35, 6, number_format($prix_unit, 0, ',', ' ') . ' FCFA', 1, 0, 'R');
    $pdf->Cell(35, 6, number_format($total_ligne, 0, ',', ' ') . ' FCFA', 1, 1, 'R');
}

// Totaux
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(155, 7, 'Total HT', 1, 0, 'R');
$pdf->Cell(35, 7, number_format($total_ht, 0, ',', ' ') . ' FCFA', 1, 1, 'R');

$tva = $total_ht * 0.18; // 18% TVA
$pdf->Cell(155, 7, 'TVA (18%)', 1, 0, 'R');
$pdf->Cell(35, 7, number_format($tva, 0, ',', ' ') . ' FCFA', 1, 1, 'R');

$total_ttc = $total_ht + $tva;
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetFillColor(200, 220, 255);
$pdf->Cell(155, 8, 'TOTAL TTC', 1, 0, 'R', true);
$pdf->Cell(35, 8, number_format($total_ttc, 0, ',', ' ') . ' FCFA', 1, 1, 'R', true);

// Mode de paiement
$pdf->Ln(10);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Mode de paiement: ' . $commande['mode_paiement'], 0, 1);
$pdf->Cell(0, 6, 'Statut: ' . $commande['statut_paiement'], 0, 1);

// Pied de page
$pdf->Ln(20);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'Merci de votre visite !', 0, 1, 'C');
$pdf->Cell(0, 5, 'Document généré automatiquement le ' . date('d/m/Y à H:i'), 0, 1, 'C');

// Sauvegarder le PDF
$filename = 'facture_' . $commande['numero_commande'] . '.pdf';
$filepath = '../uploads/factures/' . $filename;

// Créer le dossier si n'existe pas
if (!file_exists('../uploads/factures')) {
    mkdir('../uploads/factures', 0777, true);
}

// Sauvegarder dans fichier
$pdf->Output($filepath, 'F');

// Mettre à jour la commande avec le chemin du PDF
$stmt = $conn->prepare("UPDATE commandes SET facture_pdf_path = ? WHERE id = ?");
$stmt->execute([$filepath, $commande_id]);

// Afficher dans le navigateur
$pdf->Output($filename, 'I');
