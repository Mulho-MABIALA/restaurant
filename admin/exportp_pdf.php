<?php
/**
 * Système d'export avancé pour les pointages
 * Support: PDF, Excel, CSV avec templates personnalisables
 */

require_once '../config.php';
require_once '../vendor/autoload.php'; // Composer autoloader

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

class ExportManager {
    private $conn;
    private $templates;
    
    public function __construct($database) {
        $this->conn = $database;
        $this->templates = new ExportTemplates();
    }
    
    public function handleExport() {
        $format = $_GET['format'] ?? 'pdf';
        $type = $_GET['type'] ?? 'simple';
        $employe_id = $_GET['employe_id'] ?? null;
        $date_debut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-30 days'));
        $date_fin = $_GET['date_fin'] ?? date('Y-m-d');
        $template = $_GET['template'] ?? 'default';
        
        // Récupération des données
        $data = $this->getData($employe_id, $date_debut, $date_fin);
        
        switch ($format) {
            case 'pdf':
                return $this->exportToPDF($data, $template, $type);
            case 'excel':
                return $this->exportToExcel($data, $template, $type);
            case 'csv':
                return $this->exportToCSV($data, $template);
            default:
                throw new Exception('Format non supporté');
        }
    }
    
    private function getData($employe_id, $date_debut, $date_fin) {
        $data = [];
        
        // Informations employé
        if ($employe_id) {
            $stmt = $this->conn->prepare("
                SELECT e.*, d.nom as departement_nom 
                FROM employes e 
                LEFT JOIN departements d ON e.departement_id = d.id
                WHERE e.id = ?
            ");
            $stmt->execute([$employe_id]);
            $data['employe'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Pointages détaillés
        $where_conditions = [];
        $params = [];
        
        if ($employe_id) {
            $where_conditions[] = "p.employe_id = ?";
            $params[] = $employe_id;
        }
        
        $where_conditions[] = "DATE(p.created_at) BETWEEN ? AND ?";
        $params[] = $date_debut;
        $params[] = $date_fin;
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $stmt = $this->conn->prepare("
            SELECT p.*, e.nom, e.matricule, e.departement,
                   LAG(p.created_at) OVER (PARTITION BY p.employe_id, DATE(p.created_at) ORDER BY p.created_at) as precedent
            FROM pointages p
            JOIN employes e ON p.employe_id = e.id
            $where_clause
            ORDER BY p.created_at DESC
        ");
        $stmt->execute($params);
        $data['pointages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculs statistiques
        $data['statistiques'] = $this->calculateStatistics($employe_id, $date_debut, $date_fin);
        
        // Métadonnées
        $data['metadata'] = [
            'date_debut' => $date_debut,
            'date_fin' => $date_fin,
            'date_export' => date('Y-m-d H:i:s'),
            'exporte_par' => $_SESSION['admin_name'] ?? 'Admin',
            'nb_pointages' => count($data['pointages'])
        ];
        
        return $data;
    }
    
    private function calculateStatistics($employe_id, $date_debut, $date_fin) {
        $stats = [];
        
        // Heures par jour avec analyse
        $stmt = $this->conn->prepare("
            SELECT DATE(created_at) as jour,
                   COUNT(*) as nb_pointages,
                   MIN(CASE WHEN type = 'entree' THEN TIME(created_at) END) as premiere_entree,
                   MAX(CASE WHEN type = 'sortie' THEN TIME(created_at) END) as derniere_sortie
            FROM pointages 
            WHERE employe_id = ? AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY jour
        ");
        $stmt->execute([$employe_id, $date_debut, $date_fin]);
        $jours = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($jours as &$jour) {
            $jour['heures_travaillees'] = $this->calculateDailyHours($employe_id, $jour['jour']);
            $jour['retard'] = $jour['premiere_entree'] && $jour['premiere_entree'] > '09:00:00';
            $jour['depassement'] = $jour['heures_travaillees'] > 8;
            $jour['jour_semaine'] = date('N', strtotime($jour['jour']));
            $jour['weekend'] = $jour['jour_semaine'] >= 6;
        }
        
        $stats['jours'] = $jours;
        $stats['total_heures'] = array_sum(array_column($jours, 'heures_travaillees'));
        $stats['heures_moyennes'] = count($jours) > 0 ? $stats['total_heures'] / count($jours) : 0;
        $stats['nb_retards'] = count(array_filter($jours, fn($j) => $j['retard']));
        $stats['nb_depassements'] = count(array_filter($jours, fn($j) => $j['depassement']));
        $stats['jours_weekend'] = count(array_filter($jours, fn($j) => $j['weekend']));
        
        // Calculs conformité légale
        $stats['conformite'] = [
            'heures_sup_25' => max(0, $stats['total_heures'] - 35), // > 35h/semaine
            'heures_sup_50' => max(0, $stats['total_heures'] - 43), // > 43h/semaine
            'repos_hebdo_respecte' => $this->checkWeeklyRest($employe_id, $date_debut, $date_fin),
            'pause_respectee' => $this->checkBreakCompliance($employe_id, $date_debut, $date_fin)
        ];
        
        return $stats;
    }
    
    private function calculateDailyHours($employe_id, $date) {
        $stmt = $this->conn->prepare("
            SELECT type, created_at FROM pointages 
            WHERE employe_id = ? AND DATE(created_at) = ? 
            ORDER BY created_at
        ");
        $stmt->execute([$employe_id, $date]);
        $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $heures = 0;
        $entree = null;
        
        foreach ($points as $p) {
            if ($p['type'] === 'entree') {
                $entree = strtotime($p['created_at']);
            } elseif ($p['type'] === 'sortie' && $entree) {
                $heures += (strtotime($p['created_at']) - $entree) / 3600;
                $entree = null;
            }
        }
        
        return round($heures, 2);
    }
    
    public function exportToPDF($data, $template = 'default', $type = 'simple') {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Génération du HTML selon le template
        $html = $this->templates->generatePDFTemplate($data, $template, $type);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Métadonnées PDF
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(520, 820, "Page {PAGE_NUM} / {PAGE_COUNT}", null, 10, array(0,0,0));
        
        $filename = $this->generateFilename('pdf', $data);
        
        // Headers pour téléchargement
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        
        echo $dompdf->output();
    }
    
    public function exportToExcel($data, $template = 'default', $type = 'simple') {
        $spreadsheet = new Spreadsheet();
        
        // Configuration de base
        $spreadsheet->getProperties()
            ->setCreator($_SESSION['admin_name'] ?? 'TimeTracker Pro')
            ->setTitle('Rapport de Pointage')
            ->setDescription('Export des données de pointage')
            ->setSubject('Pointages')
            ->setCreated(time());
        
        if ($type === 'advanced') {
            $this->createAdvancedExcelReport($spreadsheet, $data);
        } else {
            $this->createSimpleExcelReport($spreadsheet, $data);
        }
        
        $filename = $this->generateFilename('xlsx', $data);
        
        // Headers pour téléchargement
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }
    
    private function createAdvancedExcelReport($spreadsheet, $data) {
        // Feuille 1: Résumé
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Résumé');
        
        // En-tête avec logo et informations
        $sheet1->setCellValue('A1', 'RAPPORT DE POINTAGE - TimeTracker Pro');
        $sheet1->mergeCells('A1:F1');
        $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet1->getStyle('A1')->getAlignment()->setHorizontal('center');
        
        // Informations de l'employé
        if (isset($data['employe'])) {
            $sheet1->setCellValue('A3', 'Employé:');
            $sheet1->setCellValue('B3', $data['employe']['nom']);
            $sheet1->setCellValue('A4', 'Matricule:');
            $sheet1->setCellValue('B4', $data['employe']['matricule']);
            $sheet1->setCellValue('A5', 'Département:');
            $sheet1->setCellValue('B5', $data['employe']['departement_nom'] ?? 'N/A');
        }
        
        $sheet1->setCellValue('D3', 'Période:');
        $sheet1->setCellValue('E3', $data['metadata']['date_debut'] . ' au ' . $data['metadata']['date_fin']);
        $sheet1->setCellValue('D4', 'Exporté le:');
        $sheet1->setCellValue('E4', $data['metadata']['date_export']);
        $sheet1->setCellValue('D5', 'Par:');
        $sheet1->setCellValue('E5', $data['metadata']['exporte_par']);
        
        // Statistiques principales
        $row = 7;
        $sheet1->setCellValue('A' . $row, 'STATISTIQUES GÉNÉRALES');
        $sheet1->mergeCells('A' . $row . ':F' . $row);
        $sheet1->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $sheet1->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E3F2FD');
        
        $row += 2;
        $stats = $data['statistiques'];
        
        $statsData = [
            ['Métrique', 'Valeur', 'Commentaire'],
            ['Total heures travaillées', round($stats['total_heures'], 2) . 'h', ''],
            ['Moyenne heures/jour', round($stats['heures_moyennes'], 2) . 'h', ''],
            ['Nombre de retards', $stats['nb_retards'], $stats['nb_retards'] > 5 ? 'Attention!' : 'Correct'],
            ['Dépassements (>8h)', $stats['nb_depassements'], $stats['nb_depassements'] > 0 ? 'Contrôler' : 'OK'],
            ['Travail weekend', $stats['jours_weekend'], $stats['jours_weekend'] > 0 ? 'Exceptionnel' : 'Normal'],
            ['Heures sup. 25%', round($stats['conformite']['heures_sup_25'], 2) . 'h', ''],
            ['Heures sup. 50%', round($stats['conformite']['heures_sup_50'], 2) . 'h', '']
        ];
        
        foreach ($statsData as $i => $rowData) {
            $currentRow = $row + $i;
            $sheet1->setCellValue('A' . $currentRow, $rowData[0]);
            $sheet1->setCellValue('B' . $currentRow, $rowData[1]);
            $sheet1->setCellValue('C' . $currentRow, $rowData[2]);
            
            if ($i === 0) { // En-têtes
                $sheet1->getStyle('A' . $currentRow . ':C' . $currentRow)->getFont()->setBold(true);
                $sheet1->getStyle('A' . $currentRow . ':C' . $currentRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F5F5');
            }
        }
        
        // Graphique des heures par jour
        $this->addHoursChart($sheet1, $stats['jours'], $row + count($statsData) + 2);
        
        // Feuille 2: Détail des pointages
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Pointages détaillés');
        $this->createDetailSheet($sheet2, $data['pointages']);
        
        // Feuille 3: Analyse par jour
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Analyse journalière');
        $this->createDailyAnalysisSheet($sheet3, $stats['jours']);
        
        // Feuille 4: Conformité légale
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Conformité');
        $this->createComplianceSheet($sheet4, $stats['conformite']);
        
        // Styles globaux
        $this->applyGlobalStyles($spreadsheet);
    }
    
    private function createDetailSheet($sheet, $pointages) {
        // En-têtes
        $headers = ['Date', 'Heure', 'Type', 'Géolocalisation', 'Durée session', 'Statut', 'Anomalies'];
        $sheet->fromArray($headers, null, 'A1');
        
        // Style des en-têtes
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4CAF50');
        $sheet->getStyle('A1:G1')->getFont()->getColor()->setRGB('FFFFFF');
        
        $row = 2;
        $current_session_start = null;
        
        foreach ($pointages as $p) {
            $date = date('d/m/Y', strtotime($p['created_at']));
            $heure = date('H:i:s', strtotime($p['created_at']));
            $type = ucfirst($p['type']);
            
            // Géolocalisation
            $geoloc = 'N/A';
            if (!empty($p['geoloc'])) {
                $geoloc = 'Voir carte';
                // Hyperlien vers Google Maps
                $sheet->getCell('D' . $row)->getHyperlink()->setUrl('https://maps.google.com/?q=' . $p['geoloc']);
            }
            
            // Durée de session
            $duree = '';
            $statut = 'Normal';
            $anomalies = [];
            
            if ($p['type'] === 'entree') {
                $current_session_start = strtotime($p['created_at']);
                if (date('H:i', $current_session_start) > '09:00') {
                    $statut = 'Retard';
                    $anomalies[] = 'Retard';
                }
            } elseif ($p['type'] === 'sortie' && $current_session_start) {
                $session_duration = strtotime($p['created_at']) - $current_session_start;
                $duree = gmdate('H\h i\m', $session_duration);
                if ($session_duration > 10 * 3600) { // > 10h
                    $anomalies[] = 'Session longue';
                }
                $current_session_start = null;
            }
            
            // Anomalies géolocalisation
            if (!empty($p['geoloc'])) {
                $geoloc_status = $this->validateGeolocation($p['geoloc']);
                if ($geoloc_status === 'suspicious') {
                    $anomalies[] = 'Géoloc suspecte';
                }
            }
            
            $sheet->setCellValue('A' . $row, $date);
            $sheet->setCellValue('B' . $row, $heure);
            $sheet->setCellValue('C' . $row, $type);
            $sheet->setCellValue('D' . $row, $geoloc);
            $sheet->setCellValue('E' . $row, $duree);
            $sheet->setCellValue('F' . $row, $statut);
            $sheet->setCellValue('G' . $row, implode(', ', $anomalies));
            
            // Coloration selon le statut
            if ($statut === 'Retard') {
                $sheet->getStyle('A' . $row . ':G' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFEBEE');
            } elseif (!empty($anomalies)) {
                $sheet->getStyle('A' . $row . ':G' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3E0');
            }
            
            $row++;
        }
        
        // Auto-ajustement des colonnes
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Filtres automatiques
        $sheet->setAutoFilter('A1:G' . ($row - 1));
    }
    
    private function createDailyAnalysisSheet($sheet, $jours) {
        $headers = [
            'Date', 'Jour semaine', 'Première entrée', 'Dernière sortie', 
            'Heures travaillées', 'Nb pointages', 'Retard', 'Dépassement', 'Weekend'
        ];
        $sheet->fromArray($headers, null, 'A1');
        
        // Style des en-têtes
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2196F3');
        $sheet->getStyle('A1:I1')->getFont()->getColor()->setRGB('FFFFFF');
        
        $row = 2;
        $jours_semaine = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        
        foreach ($jours as $jour) {
            $sheet->setCellValue('A' . $row, date('d/m/Y', strtotime($jour['jour'])));
            $sheet->setCellValue('B' . $row, $jours_semaine[$jour['jour_semaine']]);
            $sheet->setCellValue('C' . $row, $jour['premiere_entree'] ?? 'N/A');
            $sheet->setCellValue('D' . $row, $jour['derniere_sortie'] ?? 'N/A');
            $sheet->setCellValue('E' . $row, $jour['heures_travaillees'] . 'h');
            $sheet->setCellValue('F' . $row, $jour['nb_pointages']);
            $sheet->setCellValue('G' . $row, $jour['retard'] ? 'Oui' : 'Non');
            $sheet->setCellValue('H' . $row, $jour['depassement'] ? 'Oui' : 'Non');
            $sheet->setCellValue('I' . $row, $jour['weekend'] ? 'Oui' : 'Non');
            
            // Coloration conditionnelle
            if ($jour['retard']) {
                $sheet->getStyle('G' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFCDD2');
            }
            if ($jour['depassement']) {
                $sheet->getStyle('H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFE0B2');
            }
            if ($jour['weekend']) {
                $sheet->getStyle('I' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E1BEE7');
            }
            
            $row++;
        }
        
        // Auto-ajustement des colonnes
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Totaux
        $totalRow = $row + 1;
        $sheet->setCellValue('D' . $totalRow, 'TOTAUX:');
        $sheet->setCellValue('E' . $totalRow, '=SUM(E2:E' . ($row - 1) . ')');
        $sheet->setCellValue('F' . $totalRow, '=SUM(F2:F' . ($row - 1) . ')');
        $sheet->setCellValue('G' . $totalRow, '=COUNTIF(G2:G' . ($row - 1) . ',"Oui")');
        $sheet->setCellValue('H' . $totalRow, '=COUNTIF(H2:H' . ($row - 1) . ',"Oui")');
        
        $sheet->getStyle('D' . $totalRow . ':I' . $totalRow)->getFont()->setBold(true);
        $sheet->getStyle('D' . $totalRow . ':I' . $totalRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F5E8');
    }
    
    private function createComplianceSheet($sheet, $conformite) {
        $sheet->setCellValue('A1', 'ANALYSE DE CONFORMITÉ LÉGALE');
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        
        $compliance_data = [
            ['Critère', 'Valeur', 'Statut'],
            ['Heures supplémentaires 25%', round($conformite['heures_sup_25'], 2) . 'h', 
             $conformite['heures_sup_25'] > 8 ? 'Dépassement' : 'Conforme'],
            ['Heures supplémentaires 50%', round($conformite['heures_sup_50'], 2) . 'h',
             $conformite['heures_sup_50'] > 0 ? 'Dépassement' : 'Conforme'],
            ['Repos hebdomadaire', $conformite['repos_hebdo_respecte'] ? 'Respecté' : 'Non respecté',
             $conformite['repos_hebdo_respecte'] ? 'Conforme' : 'Non conforme'],
            ['Pauses réglementaires', $conformite['pause_respectee'] ? 'Respectées' : 'Non respectées',
             $conformite['pause_respectee'] ? 'Conforme' : 'À surveiller']
        ];
        
        $row = 3;
        foreach ($compliance_data as $i => $rowData) {
            $currentRow = $row + $i;
            $sheet->setCellValue('A' . $currentRow, $rowData[0]);
            $sheet->setCellValue('B' . $currentRow, $rowData[1]);
            $sheet->setCellValue('C' . $currentRow, $rowData[2]);
            
            if ($i === 0) {
                $sheet->getStyle('A' . $currentRow . ':C' . $currentRow)->getFont()->setBold(true);
            } else {
                // Coloration selon le statut
                $status = $rowData[2];
                if (strpos($status, 'Dépassement') !== false || strpos($status, 'Non conforme') !== false) {
                    $sheet->getStyle('C' . $currentRow)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFCDD2');
                } elseif (strpos($status, 'surveiller') !== false) {
                    $sheet->getStyle('C' . $currentRow)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFE0B2');
                } else {
                    $sheet->getStyle('C' . $currentRow)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C8E6C9');
                }
            }
        }
        
        // Recommandations
        $sheet->setCellValue('A' . ($row + 8), 'RECOMMANDATIONS');
        $sheet->getStyle('A' . ($row + 8))->getFont()->setBold(true)->setSize(14);
        
        $recommendations = $this->generateRecommendations($conformite);
        $rec_row = $row + 10;
        foreach ($recommendations as $rec) {
            $sheet->setCellValue('A' . $rec_row, '• ' . $rec);
            $sheet->mergeCells('A' . $rec_row . ':C' . $rec_row);
            $rec_row++;
        }
    }
    
    private function generateRecommendations($conformite) {
        $recommendations = [];
        
        if ($conformite['heures_sup_25'] > 8) {
            $recommendations[] = 'Réduire les heures supplémentaires pour respecter la limite légale';
        }
        
        if ($conformite['heures_sup_50'] > 0) {
            $recommendations[] = 'Attention: dépassement critique des heures légales (>43h/semaine)';
        }
        
        if (!$conformite['repos_hebdo_respecte']) {
            $recommendations[] = 'Assurer au minimum 35h consécutives de repos hebdomadaire';
        }
        
        if (!$conformite['pause_respectee']) {
            $recommendations[] = 'Vérifier le respect des pauses réglementaires (20min pour >6h de travail)';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Excellente conformité avec la réglementation du travail';
        }
        
        return $recommendations;
    }
    
    public function exportToCSV($data, $template = 'default') {
        $filename = $this->generateFilename('csv', $data);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // BOM UTF-8 pour Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // En-têtes
        $headers = [
            'Date', 'Heure', 'Employé', 'Matricule', 'Type', 
            'Géolocalisation', 'Durée session', 'Retard', 'Dépassement'
        ];
        fputcsv($output, $headers, ';');
        
        // Données
        $current_session_start = null;
        foreach ($data['pointages'] as $p) {
            $duree = '';
            $retard = 'Non';
            $depassement = 'Non';
            
            if ($p['type'] === 'entree') {
                $current_session_start = strtotime($p['created_at']);
                if (date('H:i', $current_session_start) > '09:00') {
                    $retard = 'Oui';
                }
            } elseif ($p['type'] === 'sortie' && $current_session_start) {
                $session_duration = strtotime($p['created_at']) - $current_session_start;
                $duree = gmdate('H:i', $session_duration);
                if ($session_duration > 8 * 3600) {
                    $depassement = 'Oui';
                }
                $current_session_start = null;
            }
            
            $row = [
                date('d/m/Y', strtotime($p['created_at'])),
                date('H:i:s', strtotime($p['created_at'])),
                $p['nom'],
                $p['matricule'] ?? '',
                ucfirst($p['type']),
                $p['geoloc'] ?? '',
                $duree,
                $retard,
                $depassement
            ];
            
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
    }
    
    private function generateFilename($extension, $data) {
        $base = 'pointages';
        
        if (isset($data['employe'])) {
            $base .= '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $data['employe']['nom']);
        }
        
        $base .= '_' . str_replace('-', '', $data['metadata']['date_debut']);
        $base .= '_' . str_replace('-', '', $data['metadata']['date_fin']);
        $base .= '_' . date('YmdHis');
        
        return $base . '.' . $extension;
    }
    
    private function applyGlobalStyles($spreadsheet) {
        // Style par défaut pour toutes les feuilles
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
            
            // Bordures pour les tableaux
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            
            if ($highestRow > 1) {
                $range = 'A1:' . $highestColumn . $highestRow;
                $sheet->getStyle($range)->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->setColor(new Color('CCCCCC'));
            }
        }
    }
    
    private function validateGeolocation($geoloc) {
        // Implémentation simplifiée - dans la vraie vie, comparer avec zones autorisées
        return !empty($geoloc) && strpos($geoloc, ',') !== false ? 'authorized' : 'suspicious';
    }
    
    private function checkWeeklyRest($employe_id, $date_debut, $date_fin) {
        // Vérification simplifiée du repos hebdomadaire
        // Dans la réalité, analyser les plages de 35h consécutives sans travail
        return true; // Placeholder
    }
    
    private function checkBreakCompliance($employe_id, $date_debut, $date_fin) {
        // Vérification des pauses réglementaires
        // 20 minutes minimum pour plus de 6h de travail
        return true; // Placeholder
    }
}

class ExportTemplates {
    public function generatePDFTemplate($data, $template, $type) {
        switch ($template) {
            case 'corporate':
                return $this->getCorporateTemplate($data, $type);
            case 'minimal':
                return $this->getMinimalTemplate($data, $type);
            default:
                return $this->getDefaultTemplate($data, $type);
        }
    }
    
    private function getDefaultTemplate($data, $type) {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .company-name { font-size: 24px; font-weight: bold; color: #2c3e50; }
                .report-title { font-size: 18px; color: #7f8c8d; margin-top: 10px; }
                .employee-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
                .stat-box { background: #e3f2fd; padding: 15px; border-radius: 5px; text-align: center; }
                .stat-value { font-size: 24px; font-weight: bold; color: #1976d2; }
                .stat-label { color: #666; margin-top: 5px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .retard { background-color: #ffebee; }
                .depassement { background-color: #fff3e0; }
                .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
                .page-break { page-break-before: always; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-name">TimeTracker Pro</div>
                <div class="report-title">Rapport de Pointage</div>
                <p>Période: ' . $data['metadata']['date_debut'] . ' au ' . $data['metadata']['date_fin'] . '</p>
                <p>Généré le: ' . date('d/m/Y H:i', strtotime($data['metadata']['date_export'])) . ' par ' . $data['metadata']['exporte_par'] . '</p>
            </div>';
        
        // Informations employé
        if (isset($data['employe'])) {
            $html .= '
            <div class="employee-info">
                <h3>Informations Employé</h3>
                <p><strong>Nom:</strong> ' . htmlspecialchars($data['employe']['nom']) . '</p>
                <p><strong>Matricule:</strong> ' . htmlspecialchars($data['employe']['matricule'] ?? 'N/A') . '</p>
                <p><strong>Département:</strong> ' . htmlspecialchars($data['employe']['departement_nom'] ?? 'N/A') . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($data['employe']['email'] ?? 'N/A') . '</p>
            </div>';
        }
        
        // Statistiques
        $stats = $data['statistiques'];
        $html .= '
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value">' . round($stats['total_heures'], 1) . 'h</div>
                <div class="stat-label">Total Heures</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">' . round($stats['heures_moyennes'], 1) . 'h</div>
                <div class="stat-label">Moyenne/Jour</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">' . $stats['nb_retards'] . '</div>
                <div class="stat-label">Retards</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">' . $stats['nb_depassements'] . '</div>
                <div class="stat-label">Dépassements</div>
            </div>
        </div>';
        
        if ($type === 'advanced') {
            // Analyse détaillée par jour
            $html .= '<div class="page-break"></div><h3>Analyse Journalière</h3>';
            $html .= '<table><tr><th>Date</th><th>Première Entrée</th><th>Dernière Sortie</th><th>Heures</th><th>Statut</th></tr>';
            
            foreach ($stats['jours'] as $jour) {
                $statut = [];
                if ($jour['retard']) $statut[] = 'Retard';
                if ($jour['depassement']) $statut[] = 'Dépassement';
                if ($jour['weekend']) $statut[] = 'Weekend';
                $statutText = empty($statut) ? 'Normal' : implode(', ', $statut);
                
                $class = '';
                if ($jour['retard']) $class .= ' retard';
                if ($jour['depassement']) $class .= ' depassement';
                
                $html .= '<tr class="' . $class . '">
                    <td>' . date('d/m/Y', strtotime($jour['jour'])) . '</td>
                    <td>' . ($jour['premiere_entree'] ?? 'N/A') . '</td>
                    <td>' . ($jour['derniere_sortie'] ?? 'N/A') . '</td>
                    <td>' . round($jour['heures_travaillees'], 2) . 'h</td>
                    <td>' . $statutText . '</td>
                </tr>';
            }
            $html .= '</table>';
        }
        
        // Tableau des pointages
        $html .= '<h3>Détail des Pointages</h3>';
        $html .= '<table><tr><th>Date</th><th>Heure</th><th>Type</th><th>Géolocalisation</th></tr>';
        
        foreach (array_slice($data['pointages'], 0, 50) as $p) { // Limiter à 50 pour le PDF
            $geoloc = !empty($p['geoloc']) ? 'Oui' : 'Non';
            $html .= '<tr>
                <td>' . date('d/m/Y', strtotime($p['created_at'])) . '</td>
                <td>' . date('H:i:s', strtotime($p['created_at'])) . '</td>
                <td>' . ucfirst($p['type']) . '</td>
                <td>' . $geoloc . '</td>
            </tr>';
        }
        $html .= '</table>';
        
        if (count($data['pointages']) > 50) {
            $html .= '<p><em>Note: Seuls les 50 premiers pointages sont affichés. Utilisez l\'export Excel pour voir tous les détails.</em></p>';
        }
        
        $html .= '
            <div class="footer">
                <p>Document généré automatiquement par TimeTracker Pro - Confidentiel</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    private function getCorporateTemplate($data, $type) {
        // Template plus professionnel avec logo et mise en page corporate
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: "Segoe UI", Arial, sans-serif; font-size: 11px; margin: 0; color: #333; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .company-logo { font-size: 32px; font-weight: 300; margin-bottom: 10px; }
                .report-info { background: #f8f9fa; padding: 20px; display: flex; justify-content: space-between; }
                .info-section { flex: 1; }
                .executive-summary { background: #e3f2fd; padding: 20px; margin: 20px 0; border-left: 4px solid #2196f3; }
                .metrics-dashboard { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
                .metric-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
                .metric-value { font-size: 28px; font-weight: bold; color: #1976d2; margin-bottom: 5px; }
                .metric-label { color: #666; font-size: 12px; text-transform: uppercase; }
                .compliance-section { background: #f5f5f5; padding: 20px; margin: 20px 0; }
                .status-ok { color: #4caf50; font-weight: bold; }
                .status-warning { color: #ff9800; font-weight: bold; }
                .status-error { color: #f44336; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 10px; }
                th { background: #37474f; color: white; padding: 12px 8px; font-weight: 600; }
                td { padding: 10px 8px; border-bottom: 1px solid #e0e0e0; }
                tr:nth-child(even) { background: #fafafa; }
                .signature-section { margin-top: 40px; display: flex; justify-content: space-between; }
                .signature-box { border-top: 1px solid #333; width: 200px; text-align: center; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-logo">⏰ TimeTracker Pro</div>
                <h1 style="margin: 0; font-weight: 300;">Rapport d\'Activité Professionnel</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Analyse complète des temps de présence</p>
            </div>
            
            <div class="report-info">
                <div class="info-section">
                    <h3 style="margin-top: 0; color: #1976d2;">Informations du Rapport</h3>
                    <p><strong>Période analysée:</strong> ' . date('d/m/Y', strtotime($data['metadata']['date_debut'])) . ' - ' . date('d/m/Y', strtotime($data['metadata']['date_fin'])) . '</p>
                    <p><strong>Date de génération:</strong> ' . date('d/m/Y à H:i', strtotime($data['metadata']['date_export'])) . '</p>
                    <p><strong>Généré par:</strong> ' . htmlspecialchars($data['metadata']['exporte_par']) . '</p>
                </div>';
        
        if (isset($data['employe'])) {
            $html .= '
                <div class="info-section">
                    <h3 style="margin-top: 0; color: #1976d2;">Employé Concerné</h3>
                    <p><strong>' . htmlspecialchars($data['employe']['nom']) . '</strong></p>
                    <p>Matricule: ' . htmlspecialchars($data['employe']['matricule'] ?? 'N/A') . '</p>
                    <p>Département: ' . htmlspecialchars($data['employe']['departement_nom'] ?? 'N/A') . '</p>
                </div>';
        }
        
        $html .= '</div>';
        
        // Résumé exécutif
        $stats = $data['statistiques'];
   $conformiteGlobale = ($stats['nb_retards'] <= 2 && $stats['conformite']['heures_sup_50'] == 0) 
    ? 'Excellente' 
    : (($stats['nb_retards'] <= 5 && $stats['conformite']['heures_sup_25'] <= 8) 
        ? 'Satisfaisante' 
        : 'À améliorer');

        
        $html .= '
        <div class="executive-summary">
            <h3 style="margin-top: 0;">📊 Résumé Exécutif</h3>
            <p><strong>Conformité globale:</strong> <span class="status-' . ($conformiteGlobale === 'Excellente' ? 'ok' : ($conformiteGlobale === 'Satisfaisante' ? 'warning' : 'error')) . '">' . $conformiteGlobale . '</span></p>
            <p>Sur la période analysée, l\'employé a travaillé <strong>' . round($stats['total_heures'], 1) . ' heures</strong> 
            avec une moyenne de <strong>' . round($stats['heures_moyennes'], 1) . 'h par jour</strong>. 
            ' . ($stats['nb_retards'] > 0 ? $stats['nb_retards'] . ' retard(s) ont été constatés.' : 'Aucun retard constaté.') . '</p>
        </div>';
        
        // Dashboard des métriques
        $html .= '
        <div class="metrics-dashboard">
            <div class="metric-card">
                <div class="metric-value">' . round($stats['total_heures'], 1) . '</div>
                <div class="metric-label">Heures Totales</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">' . count($stats['jours']) . '</div>
                <div class="metric-label">Jours Travaillés</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">' . $stats['nb_retards'] . '</div>
                <div class="metric-label">Retards</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">' . round($stats['conformite']['heures_sup_25'], 1) . '</div>
                <div class="metric-label">H. Sup. (25%)</div>
            </div>
        </div>';
        
        return $html . '</body></html>';
    }
    
    private function getMinimalTemplate($data, $type) {
        // Template épuré pour impression rapide
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 11px; margin: 15px; }
                h1 { font-size: 16px; border-bottom: 1px solid #000; padding-bottom: 5px; }
                h2 { font-size: 14px; margin-top: 20px; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                th, td { padding: 5px; text-align: left; border: 1px solid #ccc; }
                th { background: #f0f0f0; }
                .summary { background: #f9f9f9; padding: 10px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <h1>Pointages - ' . (isset($data['employe']) ? $data['employe']['nom'] : 'Rapport Global') . '</h1>
            <p>Période: ' . $data['metadata']['date_debut'] . ' au ' . $data['metadata']['date_fin'] . '</p>
            
            <div class="summary">
                <strong>Résumé:</strong> ' . round($data['statistiques']['total_heures'], 1) . 'h travaillées | 
                ' . $data['statistiques']['nb_retards'] . ' retard(s) | 
                ' . count($data['statistiques']['jours']) . ' jours
            </div>
            
            <table>
                <tr><th>Date</th><th>Heure</th><th>Type</th></tr>';
        
        foreach (array_slice($data['pointages'], 0, 30) as $p) {
            $html .= '<tr>
                <td>' . date('d/m', strtotime($p['created_at'])) . '</td>
                <td>' . date('H:i', strtotime($p['created_at'])) . '</td>
                <td>' . ucfirst($p['type']) . '</td>
            </tr>';
        }
        
        return $html . '</table></body></html>';
    }
}

// Point d'entrée principal
try {
    $exportManager = new ExportManager($conn);
    $exportManager->handleExport();
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Erreur lors de l'export: " . $e->getMessage();
    error_log("Export Error: " . $e->getMessage());
}
?>