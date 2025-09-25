<?php
require_once '../config.php';
require_once(__DIR__ . '/../../vendor/vendor/tecnickcom/tcpdf/tcpdf.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Rediriger si l'admin n'est pas connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

class BulletinPDFGenerateur {
    private $pdf;
    private $restaurant_info;
    private $output_dir;
    
    public function __construct($restaurant_info = [], $output_dir = 'bulletins/') {
        $this->restaurant_info = array_merge([
            'nom' => 'Restaurant Le Savoureux',
            'adresse' => '123 Avenue de la République, Dakar, Sénégal',
            'telephone' => '+221 33 123 45 67',
            'email' => 'contact@lesavoureux.sn',
            'ninea' => '123456789',
            'logo' => 'assets/logo.png'
        ], $restaurant_info);
        
        // Créer le chemin absolu pour le dossier de sortie
        $this->output_dir = __DIR__ . '/../' . $output_dir;
        
        // Créer le dossier de sortie s'il n'existe pas
        if (!is_dir($this->output_dir)) {
            mkdir($this->output_dir, 0755, true);
        }
    }
    
    /**
     * Générer un bulletin de paie en PDF
     */
    public function genererBulletinPDF($bulletin_data) {
        try {
            // Initialiser TCPDF
            $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Configuration du document
            $this->configurerDocument($bulletin_data);
            
            // Ajouter une page
            $this->pdf->AddPage();
            
            // Générer le contenu
            $this->genererEnTete();
            $this->genererInfosEmploye($bulletin_data);
            $this->genererTableauSalaire($bulletin_data);
            $this->genererResume($bulletin_data);
            $this->genererPiedPage();
            
            // Retourner le contenu pour l'affichage direct
            return $this->pdf->Output('', 'S');
            
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la génération du PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * Configurer les paramètres du document PDF
     */
    private function configurerDocument($bulletin_data) {
        // Extraire nom et prénom de façon sécurisée
        $nom = $this->extraireNom($bulletin_data);
        $prenom = $this->extrairePrenom($bulletin_data);
        
        // Informations du document
        $this->pdf->SetCreator('Système de Paie - ' . $this->restaurant_info['nom']);
        $this->pdf->SetAuthor($this->restaurant_info['nom']);
        $this->pdf->SetTitle('Bulletin de Paie - ' . $prenom . ' ' . $nom);
        $this->pdf->SetSubject('Bulletin de Paie ' . $this->getMoisFrancais($bulletin_data['mois'] ?? 1) . ' ' . ($bulletin_data['annee'] ?? date('Y')));
        
        // Marges
        $this->pdf->SetMargins(15, 20, 15);
        $this->pdf->SetHeaderMargin(5);
        $this->pdf->SetFooterMargin(10);
        
        // Auto page break
        $this->pdf->SetAutoPageBreak(TRUE, 25);
        
        // Police par défaut
        $this->pdf->SetFont('helvetica', '', 10);
        
        // Supprimer header/footer par défaut
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
    }
    
    /**
     * Extraire le nom de façon sécurisée
     */
    private function extraireNom($bulletin_data) {
        // Priorité aux champs séparés
        if (!empty($bulletin_data['nom'])) {
            return $bulletin_data['nom'];
        }
        
        // Sinon essayer d'extraire de employe_nom
        if (!empty($bulletin_data['employe_nom'])) {
            $parts = explode(' ', $bulletin_data['employe_nom'], 2);
            return $parts[1] ?? 'Nom';
        }
        
        return 'Nom';
    }
    
    /**
     * Extraire le prénom de façon sécurisée
     */
    private function extrairePrenom($bulletin_data) {
        // Priorité aux champs séparés
        if (!empty($bulletin_data['prenom'])) {
            return $bulletin_data['prenom'];
        }
        
        // Sinon essayer d'extraire de employe_nom
        if (!empty($bulletin_data['employe_nom'])) {
            $parts = explode(' ', $bulletin_data['employe_nom'], 2);
            return $parts[0] ?? 'Prénom';
        }
        
        return 'Prénom';
    }
    
    /**
     * Générer l'en-tête du bulletin
     */
    private function genererEnTete() {
        // Logo et informations restaurant (colonne gauche)
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->SetTextColor(44, 62, 80);
        $this->pdf->Cell(90, 8, $this->restaurant_info['nom'], 0, 0, 'L');
        
        // Titre du document (colonne droite)
        $this->pdf->SetFont('helvetica', 'B', 18);
        $this->pdf->SetTextColor(231, 76, 60);
        $this->pdf->Cell(90, 8, 'BULLETIN DE PAIE', 0, 1, 'R');
        
        // Informations restaurant
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetTextColor(52, 73, 94);
        $this->pdf->Cell(90, 4, $this->restaurant_info['adresse'], 0, 0, 'L');
        $this->pdf->Cell(90, 4, '', 0, 1, 'R');
        
        $this->pdf->Cell(90, 4, 'Tél: ' . $this->restaurant_info['telephone'], 0, 0, 'L');
        $this->pdf->Cell(90, 4, '', 0, 1, 'R');
        
        $this->pdf->Cell(90, 4, 'Email: ' . $this->restaurant_info['email'], 0, 0, 'L');
        $this->pdf->Cell(90, 4, '', 0, 1, 'R');
        
        if (!empty($this->restaurant_info['ninea'])) {
            $this->pdf->Cell(90, 4, 'NINEA: ' . $this->restaurant_info['ninea'], 0, 1, 'L');
        }
        
        // Ligne de séparation
        $this->pdf->Ln(5);
        $this->pdf->SetDrawColor(189, 195, 199);
        $this->pdf->Line(15, $this->pdf->GetY(), 195, $this->pdf->GetY());
        $this->pdf->Ln(8);
    }
    
    /**
     * Générer les informations de l'employé et de la période
     */
    private function genererInfosEmploye($bulletin_data) {
        // Extraction sécurisée des données
        $nom = $this->extraireNom($bulletin_data);
        $prenom = $this->extrairePrenom($bulletin_data);
        $poste = $bulletin_data['nom_poste'] ?? $bulletin_data['poste_nom'] ?? 'Poste non défini';
        $email = $bulletin_data['employe_email'] ?? 'Email non défini';
        $mois = $this->getMoisFrancais($bulletin_data['mois'] ?? 1);
        $annee = $bulletin_data['annee'] ?? date('Y');
        
        // Section employé
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->SetTextColor(44, 62, 80);
        $this->pdf->Cell(0, 8, 'INFORMATIONS EMPLOYÉ', 0, 1, 'L');
        $this->pdf->Ln(2);
        
        // Informations employé en deux colonnes
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetTextColor(52, 73, 94);
        
        // Colonne gauche
        $this->pdf->Cell(90, 6, 'Nom: ' . strtoupper($nom), 0, 0, 'L');
        $this->pdf->Cell(90, 6, 'Période: ' . $mois . ' ' . $annee, 0, 1, 'L');
        
        $this->pdf->Cell(90, 6, 'Prénom: ' . ucfirst(strtolower($prenom)), 0, 0, 'L');
        $this->pdf->Cell(90, 6, 'Bulletin N°: ' . ($bulletin_data['id'] ?? 'N/A'), 0, 1, 'L');
        
        $this->pdf->Cell(90, 6, 'Poste: ' . $poste, 0, 0, 'L');
        $this->pdf->Cell(90, 6, 'Statut: ' . ucfirst($bulletin_data['statut'] ?? 'brouillon'), 0, 1, 'L');
        
        $this->pdf->Cell(90, 6, 'Email: ' . $email, 0, 1, 'L');
        
        $this->pdf->Ln(5);
    }
    
    /**
     * Générer le tableau détaillé du salaire
     */
    private function genererTableauSalaire($bulletin_data) {
        // Titre de section
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->SetTextColor(44, 62, 80);
        $this->pdf->Cell(0, 8, 'DÉTAIL DU SALAIRE', 0, 1, 'C');
        $this->pdf->Ln(2);
        
        // En-têtes du tableau
        $this->pdf->SetFont('helvetica', 'B', 10);
        $this->pdf->SetFillColor(236, 240, 241);
        $this->pdf->SetTextColor(44, 62, 80);
        $this->pdf->SetDrawColor(189, 195, 199);
        
        $this->pdf->Cell(120, 8, 'LIBELLÉ', 1, 0, 'L', true);
        $this->pdf->Cell(30, 8, 'BASE', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, 'MONTANT', 1, 1, 'R', true);
        
        // Contenu du tableau
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetTextColor(52, 73, 94);
        $this->pdf->SetFillColor(255, 255, 255);
        
        // Salaire de base
        $salaireBase = floatval($bulletin_data['salaire_base'] ?? 0);
        $this->pdf->Cell(120, 6, 'Salaire de base', 1, 0, 'L');
        $this->pdf->Cell(30, 6, '-', 1, 0, 'C');
        $this->pdf->Cell(30, 6, $this->formaterMontant($salaireBase), 1, 1, 'R');
        
        // Heures supplémentaires si présentes
        $heuresSupp = floatval($bulletin_data['heures_supplementaires'] ?? 0);
        if ($heuresSupp > 0) {
            $montantHeuresSupp = $heuresSupp * (($salaireBase / 173.33) * 1.5); // Approximation
            $this->pdf->Cell(120, 6, 'Heures supplémentaires (' . $heuresSupp . 'h)', 1, 0, 'L');
            $this->pdf->Cell(30, 6, '150%', 1, 0, 'C');
            $this->pdf->Cell(30, 6, $this->formaterMontant($montantHeuresSupp), 1, 1, 'R');
        }
        
        // Primes
        $totalPrimes = floatval($bulletin_data['total_primes'] ?? 0);
        if ($totalPrimes > 0) {
            $this->pdf->Cell(120, 6, 'Total des primes', 1, 0, 'L');
            $this->pdf->Cell(30, 6, '-', 1, 0, 'C');
            $this->pdf->Cell(30, 6, $this->formaterMontant($totalPrimes), 1, 1, 'R');
        }
        
        // Total brut
        $salaireBrut = floatval($bulletin_data['salaire_brut'] ?? ($salaireBase + $totalPrimes));
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->SetFillColor(241, 196, 15);
        $this->pdf->Cell(120, 6, 'SALAIRE BRUT', 1, 0, 'L', true);
        $this->pdf->Cell(30, 6, '', 1, 0, 'C', true);
        $this->pdf->Cell(30, 6, $this->formaterMontant($salaireBrut), 1, 1, 'R', true);
        
        // Cotisations sociales
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetFillColor(255, 255, 255);
        
        $cotisations = floatval($bulletin_data['total_cotisations'] ?? 0);
        if ($cotisations > 0) {
            $tauxCotisation = ($cotisations / $salaireBrut) * 100;
            $this->pdf->Cell(120, 6, 'Cotisations sociales', 1, 0, 'L');
            $this->pdf->Cell(30, 6, number_format($tauxCotisation, 1) . '%', 1, 0, 'C');
            $this->pdf->Cell(30, 6, '- ' . $this->formaterMontant($cotisations), 1, 1, 'R');
        }
        
        // Retenues (avances, absences)
        $totalRetenues = floatval($bulletin_data['total_retenues'] ?? 0);
        $montantAbsences = floatval($bulletin_data['montant_absences'] ?? 0);
        $avances = floatval($bulletin_data['montant_avances_remboursees'] ?? 0);
        
        if ($montantAbsences > 0) {
            $joursAbsences = intval($bulletin_data['jours_absences'] ?? 0);
            $this->pdf->Cell(120, 6, 'Retenue absences (' . $joursAbsences . ' jour(s))', 1, 0, 'L');
            $this->pdf->Cell(30, 6, '-', 1, 0, 'C');
            $this->pdf->Cell(30, 6, '- ' . $this->formaterMontant($montantAbsences), 1, 1, 'R');
        }
        
        if ($avances > 0) {
            $this->pdf->Cell(120, 6, 'Avances remboursées', 1, 0, 'L');
            $this->pdf->Cell(30, 6, '-', 1, 0, 'C');
            $this->pdf->Cell(30, 6, '- ' . $this->formaterMontant($avances), 1, 1, 'R');
        }
        
        $this->pdf->Ln(3);
    }
    
    /**
     * Générer le résumé final
     */
    private function genererResume($bulletin_data) {
        // Tableau de résumé
        $salaireNet = floatval($bulletin_data['salaire_net'] ?? 0);
        
        $this->pdf->SetFont('helvetica', 'B', 11);
        $this->pdf->SetFillColor(46, 204, 113);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->SetDrawColor(39, 174, 96);
        
        $this->pdf->Cell(120, 10, 'SALAIRE NET À PAYER', 1, 0, 'L', true);
        $this->pdf->Cell(60, 10, $this->formaterMontant($salaireNet), 1, 1, 'R', true);
        
        $this->pdf->Ln(5);
        
        // Informations complémentaires
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetTextColor(127, 140, 141);
        
        $heuresSupp = floatval($bulletin_data['heures_supplementaires'] ?? 0);
        if ($heuresSupp > 0) {
            $this->pdf->Cell(0, 4, 'Heures supplémentaires: ' . $heuresSupp . 'h', 0, 1, 'L');
        }
        
        $joursConges = intval($bulletin_data['jours_conges'] ?? 0);
        if ($joursConges > 0) {
            $this->pdf->Cell(0, 4, 'Jours de congés: ' . $joursConges, 0, 1, 'L');
        }
        
        $joursAbsences = intval($bulletin_data['jours_absences'] ?? 0);
        if ($joursAbsences > 0) {
            $this->pdf->Cell(0, 4, 'Jours d\'absences: ' . $joursAbsences, 0, 1, 'L');
        }
        
        $heuresTravaillees = floatval($bulletin_data['heures_travaillees'] ?? 0);
        if ($heuresTravaillees > 0) {
            $this->pdf->Cell(0, 4, 'Heures travaillées: ' . $heuresTravaillees . 'h', 0, 1, 'L');
        }
        
        $this->pdf->Ln(8);
        
        // Note légale
        $this->pdf->SetFont('helvetica', 'I', 8);
        $this->pdf->SetTextColor(149, 165, 166);
        $this->pdf->MultiCell(0, 4, 'Ce bulletin de paie est conforme à la législation sénégalaise du travail. Conservez ce document, il vous sera demandé pour toute démarche administrative.', 0, 'J');
    }
    
    /**
     * Générer le pied de page
     */
    private function genererPiedPage() {
        // Position en bas de page
        $this->pdf->SetY(-25);
        
        // Ligne de séparation
        $this->pdf->SetDrawColor(189, 195, 199);
        $this->pdf->Line(15, $this->pdf->GetY(), 195, $this->pdf->GetY());
        
        // Texte du pied de page
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->SetTextColor(127, 140, 141);
        $this->pdf->Ln(2);
        $this->pdf->Cell(0, 4, 'Document généré automatiquement le ' . date('d/m/Y à H:i'), 0, 1, 'C');
        $this->pdf->Cell(0, 4, $this->restaurant_info['nom'] . ' - Système de gestion de paie', 0, 1, 'C');
    }
    
    /**
     * Générer le nom de fichier sécurisé
     */
    private function genererNomFichier($bulletin_data) {
        $nom = $this->extraireNom($bulletin_data);
        $prenom = $this->extrairePrenom($bulletin_data);
        $mois = str_pad($bulletin_data['mois'] ?? 1, 2, '0', STR_PAD_LEFT);
        $annee = $bulletin_data['annee'] ?? date('Y');
        
        // Nettoyer les noms pour le fichier
        $nom = preg_replace('/[^a-zA-Z0-9]/', '_', $nom);
        $prenom = preg_replace('/[^a-zA-Z0-9]/', '_', $prenom);
        
        return "bulletin_{$nom}_{$prenom}_{$annee}_{$mois}.pdf";
    }
    
    /**
     * Formater un montant pour l'affichage
     */
    private function formaterMontant($montant) {
        return number_format($montant, 0, ',', ' ') . ' FCFA';
    }
    
    /**
     * Obtenir le nom du mois en français
     */
    private function getMoisFrancais($mois) {
        $mois_fr = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        
        return $mois_fr[intval($mois)] ?? 'Mois inconnu';
    }
    
    /**
     * Télécharger directement un bulletin
     */
    public function telechargerBulletin($bulletin_data, $nom_fichier = null) {
        try {
            // Générer le PDF
            $pdfContent = $this->genererBulletinPDF($bulletin_data);
            
            // Nom de fichier pour téléchargement
            if (!$nom_fichier) {
                $nom_fichier = $this->genererNomFichier($bulletin_data);
            }
            
            // Headers pour téléchargement
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $nom_fichier . '"');
            header('Content-Length: ' . strlen($pdfContent));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            
            // Sortie du PDF
            echo $pdfContent;
            exit;
            
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la génération du PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * Afficher un bulletin dans le navigateur
     */
    public function afficherBulletin($bulletin_data, $nom_fichier = null) {
    try {
        // Générer le PDF
        $pdfContent = $this->genererBulletinPDF($bulletin_data);
        
        // Nom de fichier pour affichage
        if (!$nom_fichier) {
            $nom_fichier = $this->genererNomFichier($bulletin_data);
        }
        
        // Headers pour affichage INLINE dans le navigateur
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $nom_fichier . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: public, max-age=0');
        
        // Sortie du PDF pour affichage
        echo $pdfContent;
        exit;
        
    } catch (Exception $e) {
        throw new Exception('Erreur lors de l\'affichage du PDF: ' . $e->getMessage());
    }
}
}

/**
 * Classe utilitaire pour les opérations sur les bulletins
 */
class BulletinUtilities {
    
    /**
     * Valider les données d'un bulletin avant génération
     */
    public static function validerDonneesBulletin($bulletin_data) {
        $erreurs = [];
        
        // Vérifier qu'on a au moins employe_nom ou nom/prenom
        if (empty($bulletin_data['employe_nom']) && 
            (empty($bulletin_data['nom']) || empty($bulletin_data['prenom']))) {
            $erreurs[] = "Les informations de l'employé sont obligatoires";
        }
        
        if (empty($bulletin_data['mois']) || $bulletin_data['mois'] < 1 || $bulletin_data['mois'] > 12) {
            $erreurs[] = "Le mois doit être compris entre 1 et 12";
        }
        
        if (empty($bulletin_data['annee']) || $bulletin_data['annee'] < 2020) {
            $erreurs[] = "L'année doit être valide";
        }
        
        if (!isset($bulletin_data['salaire_base']) || floatval($bulletin_data['salaire_base']) < 0) {
            $erreurs[] = "Le salaire de base doit être positif";
        }
        
        if (!isset($bulletin_data['salaire_net']) || floatval($bulletin_data['salaire_net']) < 0) {
            $erreurs[] = "Le salaire net ne peut pas être négatif";
        }
        
        return $erreurs;
    }
    
    /**
     * Archiver un bulletin (changer le statut)
     */
    public static function archiverBulletin($conn, $id_bulletin) {
        try {
            $stmt = $conn->prepare("
                UPDATE bulletins_paie 
                SET statut = 'paye' 
                WHERE id_bulletin = ? AND statut = 'valide'
            ");
            $stmt->execute([$id_bulletin]);
            
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Supprimer un bulletin en brouillon
     */
    public static function supprimerBrouillon($conn, $id_bulletin) {
        try {
            $conn->beginTransaction();
            
            // Supprimer le bulletin
            $stmt = $conn->prepare("
                DELETE FROM bulletins_paie 
                WHERE id_bulletin = ? AND statut = 'brouillon'
            ");
            $stmt->execute([$id_bulletin]);
            
            $result = $stmt->rowCount() > 0;
            
            $conn->commit();
            return $result;
            
        } catch (Exception $e) {
            $conn->rollBack();
            return false;
        }
    }
    
    /**
     * Calculer les statistiques d'un employé
     */
    public static function getStatistiquesEmploye($conn, $id_employe, $annee = null) {
        $where = "WHERE id_employe = ? AND statut IN ('valide', 'paye')";
        $params = [$id_employe];
        
        if ($annee) {
            $where .= " AND annee = ?";
            $params[] = $annee;
        }
        
        $sql = "
            SELECT 
                COUNT(*) as nb_bulletins,
                SUM(salaire_net) as total_net,
                AVG(salaire_net) as moyenne_net,
                SUM(total_primes) as total_primes,
                SUM(total_cotisations) as total_cotisations,
                SUM(heures_supplementaires) as total_heures_supp
            FROM bulletins_paie 
            $where
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Exporter la liste des bulletins en CSV
     */
    public static function exporterBulletinsCSV($bulletins, $nom_fichier = null) {
        if (!$nom_fichier) {
            $nom_fichier = 'bulletins_' . date('Y_m_d') . '.csv';
        }
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nom_fichier . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // BOM UTF-8 pour Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // En-têtes
        fputcsv($output, [
            'ID', 'Nom', 'Prénom', 'Poste', 'Mois', 'Année',
            'Salaire Base', 'Total Primes', 'Total Cotisations', 
            'Total Retenues', 'Salaire Brut', 'Salaire Net', 'Statut'
        ], ';');
        
        // Données
        foreach ($bulletins as $bulletin) {
            fputcsv($output, [
                $bulletin['id_bulletin'] ?? $bulletin['id'],
                $bulletin['nom'] ?? '',
                $bulletin['prenom'] ?? '',
                $bulletin['nom_poste'] ?? $bulletin['poste_nom'] ?? '',
                $bulletin['mois'],
                $bulletin['annee'],
                $bulletin['salaire_base'],
                $bulletin['total_primes'],
                $bulletin['total_cotisations'],
                $bulletin['total_retenues'],
                $bulletin['salaire_brut'],
                $bulletin['salaire_net'],
                $bulletin['statut']
            ], ';');
        }
        
        fclose($output);
        exit;
    }
}
?>