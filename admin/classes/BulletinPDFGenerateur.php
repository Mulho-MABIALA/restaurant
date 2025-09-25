<?php

    require_once '../config.php';
require_once(__DIR__ . '/../../vendor/vendor/tecnickcom/tcpdf/tcpdf.php');

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Rediriger si l'admin n'est pas connecté
    if (! isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
// Vérifier si la classe n'existe pas déjà avant de la déclarer
if (!class_exists('BulletinPDFGenerateur')) {
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
            
            $this->output_dir = $output_dir;
            
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
                
                // Générer le nom de fichier
                $nom_fichier = $this->genererNomFichier($bulletin_data);
                $chemin_complet = $this->output_dir . $nom_fichier;
                
                // Sauvegarder le PDF
                $this->pdf->Output($chemin_complet, 'F');
                
                return [
                    'success' => true,
                    'fichier' => $nom_fichier,
                    'chemin' => $chemin_complet,
                    'taille' => filesize($chemin_complet)
                ];
                
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        /**
         * Configurer les paramètres du document PDF
         */
        private function configurerDocument($bulletin_data) {
            // Informations du document
            $this->pdf->SetCreator('Système de Paie - ' . $this->restaurant_info['nom']);
            $this->pdf->SetAuthor($this->restaurant_info['nom']);
            $this->pdf->SetTitle('Bulletin de Paie - ' . $bulletin_data['nom'] . ' ' . $bulletin_data['prenom']);
            $this->pdf->SetSubject('Bulletin de Paie ' . $this->getMoisFrancais($bulletin_data['mois']) . ' ' . $bulletin_data['annee']);
            
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
            $y_start = $this->pdf->GetY();
            
            // Section Employé (colonne gauche)
            $this->pdf->SetFont('helvetica', 'B', 12);
            $this->pdf->SetTextColor(44, 62, 80);
            $this->pdf->Cell(90, 6, 'INFORMATIONS EMPLOYÉ', 0, 0, 'L');
            
            // Section Période (colonne droite)
            $this->pdf->Cell(90, 6, 'PÉRIODE DE PAIE', 0, 1, 'L');
            
            $this->pdf->SetFont('helvetica', '', 10);
            $this->pdf->SetTextColor(52, 73, 94);
            
            // Informations employé
            $this->pdf->Cell(90, 5, 'Nom: ' . strtoupper($bulletin_data['nom']), 0, 0, 'L');
            $this->pdf->Cell(90, 5, 'Mois: ' . $this->getMoisFrancais($bulletin_data['mois']) . ' ' . $bulletin_data['annee'], 0, 1, 'L');
            
            $this->pdf->Cell(90, 5, 'Prénom: ' . ucfirst($bulletin_data['prenom']), 0, 0, 'L');
            $this->pdf->Cell(90, 5, 'Statut: ' . ucfirst($bulletin_data['statut']), 0, 1, 'L');
            
            $this->pdf->Cell(90, 5, 'Poste: ' . $bulletin_data['nom_poste'], 0, 0, 'L');
            $this->pdf->Cell(90, 5, 'Date création: ' . date('d/m/Y', strtotime($bulletin_data['date_creation'])), 0, 1, 'L');
            
            if (!empty($bulletin_data['email'])) {
                $this->pdf->Cell(90, 5, 'Email: ' . $bulletin_data['email'], 0, 1, 'L');
            }
            
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
            $this->pdf->Cell(120, 6, 'Salaire de base', 1, 0, 'L');
            $this->pdf->Cell(30, 6, '-', 1, 0, 'C');
            $this->pdf->Cell(30, 6, $this->formaterMontant($bulletin_data['salaire_base']), 1, 1, 'R');
            
            // Primes
            if (!empty($bulletin_data['details']['primes'])) {
                foreach ($bulletin_data['details']['primes'] as $prime) {
                    $this->pdf->Cell(120, 6, $prime['nom_element'], 1, 0, 'L');
                    $base = $prime['base_calcul'] === 'montant_fixe' ? '-' : $prime['taux_applique'] . '%';
                    $this->pdf->Cell(30, 6, $base, 1, 0, 'C');
                    $this->pdf->Cell(30, 6, $this->formaterMontant($prime['montant']), 1, 1, 'R');
                }
            }
            
            // Total brut
            $this->pdf->SetFont('helvetica', 'B', 9);
            $this->pdf->SetFillColor(241, 196, 15);
            $this->pdf->Cell(120, 6, 'SALAIRE BRUT', 1, 0, 'L', true);
            $this->pdf->Cell(30, 6, '', 1, 0, 'C', true);
            $this->pdf->Cell(30, 6, $this->formaterMontant($bulletin_data['salaire_brut']), 1, 1, 'R', true);
            
            // Cotisations et retenues
            $this->pdf->SetFont('helvetica', '', 9);
            $this->pdf->SetFillColor(255, 255, 255);
            
            // Cotisations
            if (!empty($bulletin_data['details']['cotisations'])) {
                foreach ($bulletin_data['details']['cotisations'] as $cotisation) {
                    $this->pdf->Cell(120, 6, $cotisation['nom_element'], 1, 0, 'L');
                    $base = $cotisation['base_calcul'] === 'montant_fixe' ? '-' : $cotisation['taux_applique'] . '%';
                    $this->pdf->Cell(30, 6, $base, 1, 0, 'C');
                    $this->pdf->Cell(30, 6, '- ' . $this->formaterMontant($cotisation['montant']), 1, 1, 'R');
                }
            }
            
            // Retenues
            if (!empty($bulletin_data['details']['retenues'])) {
                foreach ($bulletin_data['details']['retenues'] as $retenue) {
                    $this->pdf->Cell(120, 6, $retenue['nom_element'], 1, 0, 'L');
                    $this->pdf->Cell(30, 6, '-', 1, 0, 'C');
                    $this->pdf->Cell(30, 6, '- ' . $this->formaterMontant($retenue['montant']), 1, 1, 'R');
                }
            }
            
            $this->pdf->Ln(3);
        }
        
        /**
         * Générer le résumé final
         */
        private function genererResume($bulletin_data) {
            // Tableau de résumé
            $this->pdf->SetFont('helvetica', 'B', 11);
            $this->pdf->SetFillColor(46, 204, 113);
            $this->pdf->SetTextColor(255, 255, 255);
            $this->pdf->SetDrawColor(39, 174, 96);
            
            $this->pdf->Cell(120, 10, 'SALAIRE NET À PAYER', 1, 0, 'L', true);
            $this->pdf->Cell(60, 10, $this->formaterMontant($bulletin_data['salaire_net']), 1, 1, 'R', true);
            
            $this->pdf->Ln(5);
            
            // Informations complémentaires
            $this->pdf->SetFont('helvetica', '', 9);
            $this->pdf->SetTextColor(127, 140, 141);
            
            if ($bulletin_data['heures_supplementaires'] > 0) {
                $this->pdf->Cell(0, 4, 'Heures supplémentaires: ' . $bulletin_data['heures_supplementaires'] . 'h', 0, 1, 'L');
            }
            
            if ($bulletin_data['jours_conges'] > 0) {
                $this->pdf->Cell(0, 4, 'Jours de congés: ' . $bulletin_data['jours_conges'], 0, 1, 'L');
            }
            
            if ($bulletin_data['jours_absences'] > 0) {
                $this->pdf->Cell(0, 4, 'Jours d\'absences: ' . $bulletin_data['jours_absences'], 0, 1, 'L');
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
         * Générer le nom de fichier pour le bulletin
         */
        private function genererNomFichier($bulletin_data) {
            $nom = strtolower(str_replace(' ', '_', $bulletin_data['nom']));
            $prenom = strtolower(str_replace(' ', '_', $bulletin_data['prenom']));
            $mois = str_pad($bulletin_data['mois'], 2, '0', STR_PAD_LEFT);
            
            return "bulletin_{$nom}_{$prenom}_{$bulletin_data['annee']}_{$mois}.pdf";
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
            
            return $mois_fr[$mois] ?? 'Mois inconnu';
        }
        
        /**
         * Télécharger directement un bulletin
         */
        public function telechargerBulletin($bulletin_data, $nom_fichier = null) {
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
                
                // Nom de fichier pour téléchargement
                if (!$nom_fichier) {
                    $nom_fichier = $this->genererNomFichier($bulletin_data);
                }
                
                // Headers pour téléchargement
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $nom_fichier . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                
                // Sortie du PDF
                $this->pdf->Output($nom_fichier, 'D');
                
            } catch (Exception $e) {
                throw new Exception('Erreur lors de la génération du PDF: ' . $e->getMessage());
            }
        }
        
        /**
         * Afficher un bulletin dans le navigateur
         */
        public function afficherBulletin($bulletin_data, $nom_fichier = null) {
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
                
                // Nom de fichier pour affichage
                if (!$nom_fichier) {
                    $nom_fichier = $this->genererNomFichier($bulletin_data);
                }
                
                // Headers pour affichage inline
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $nom_fichier . '"');
                
                // Sortie du PDF
                $this->pdf->Output($nom_fichier, 'I');
                
            } catch (Exception $e) {
                throw new Exception('Erreur lors de l\'affichage du PDF: ' . $e->getMessage());
            }
        }
    }
}

/**
 * Classe utilitaire pour les opérations sur les bulletins
 * Vérifier si la classe n'existe pas déjà avant de la déclarer
 */
if (!class_exists('BulletinUtilities')) {
    class BulletinUtilities {
        
        /**
         * Valider les données d'un bulletin avant génération
         */
        public static function validerDonneesBulletin($bulletin_data) {
            $erreurs = [];
            
            // Vérifications obligatoires
            if (empty($bulletin_data['nom'])) {
                $erreurs[] = "Le nom de l'employé est obligatoire";
            }
            
            if (empty($bulletin_data['prenom'])) {
                $erreurs[] = "Le prénom de l'employé est obligatoire";
            }
            
            if (empty($bulletin_data['mois']) || $bulletin_data['mois'] < 1 || $bulletin_data['mois'] > 12) {
                $erreurs[] = "Le mois doit être compris entre 1 et 12";
            }
            
            if (empty($bulletin_data['annee']) || $bulletin_data['annee'] < 2020) {
                $erreurs[] = "L'année doit être valide";
            }
            
            if (!isset($bulletin_data['salaire_base']) || $bulletin_data['salaire_base'] < 0) {
                $erreurs[] = "Le salaire de base doit être positif";
            }
            
            if (!isset($bulletin_data['salaire_net']) || $bulletin_data['salaire_net'] < 0) {
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
                    SET statut = 'payé' 
                    WHERE id_bulletin = ? AND statut = 'validé'
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
                
                // Supprimer les détails
                $stmt = $conn->prepare("DELETE FROM details_bulletins WHERE id_bulletin = ?");
                $stmt->execute([$id_bulletin]);
                
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
            $where = "WHERE id_employe = ? AND statut IN ('validé', 'payé')";
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
                    $bulletin['id_bulletin'],
                    $bulletin['nom'],
                    $bulletin['prenom'],
                    $bulletin['nom_poste'],
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
}
?>