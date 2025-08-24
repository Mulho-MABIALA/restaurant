<?php
require_once '../config.php';
require_once 'phpqrcode/qrlib.php';

// =============================================================================
// GESTIONNAIRE D'EMPLOYÉS
// =============================================================================

class EmployeeManager  // Correction: Ajout du mot-clé 'class'
{
    private $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    // Version corrigée de getAllEmployees
    public function getAllEmployees(): array {
        try {
            $stmt = $this->conn->query("
                SELECT e.*, 
                       p.nom as poste_nom,
                       p.couleur as poste_couleur,
                       p.salaire as poste_salaire,
                       p.type_contrat,
                       p.duree_contrat,
                       p.niveau_hierarchique,
                       p.competences_requises,
                       p.avantages,
                       p.code_paie,
                       p.categorie_paie,
                       p.regime_social,
                       p.taux_cotisation,
                       p.salaire_min,
                       p.salaire_max,
                       p.heures_travail as heures_par_mois,  -- CORRECTION: depuis table postes
                       ps.nom as poste_superieur_nom
                FROM employes e 
                LEFT JOIN postes p ON e.poste_id = p.id 
                LEFT JOIN postes ps ON p.poste_superieur_id = ps.id 
                WHERE e.statut = 'actif'
                ORDER BY e.nom, e.prenom
            ");
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Employés trouvés: " . count($result)); // Debug
            return $result;
            
        } catch (PDOException $e) {
            error_log("Erreur SQL getAllEmployees: " . $e->getMessage());
            return [];
        }
    }
   
    //  Récupère un employé par son ID avec toutes ses informations
    public function getEmployeeById(int $id): ?array {  // Correction: Ajout de 'public function'
        try {
            $stmt = $this->conn->prepare("
                SELECT e.*, 
                       p.nom as poste_nom,
                       p.couleur as poste_couleur,
                       p.salaire as poste_salaire,
                       p.type_contrat,
                       p.duree_contrat,
                       p.niveau_hierarchique,
                       p.competences_requises,
                       p.avantages,
                       p.code_paie,
                       p.categorie_paie,
                       p.regime_social,
                       p.taux_cotisation,
                       p.salaire_min,
                       p.salaire_max,
                       p.heures_travail as heures_par_mois  -- CORRECTION: depuis table postes
                FROM employes e 
                LEFT JOIN postes p ON e.poste_id = p.id 
                WHERE e.id = ?
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
            
        } catch (PDOException $e) {
            error_log("Erreur SQL getEmployeeById: " . $e->getMessage());
            return null;
        }
    }

    
    /**
     * Récupère les statistiques des employés
     */
    public function getStatistics(): array {
        $stats = [];
        
        // Total employés actifs
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes WHERE statut = 'actif'");
        $stats['total_actifs'] = $stmt->fetch()['total'];
        
        // Présents aujourd'hui (employés actifs)
        $stats['presents_aujourd_hui'] = $stats['total_actifs'];
        
        // Nouveaux ce mois
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes WHERE DATE_FORMAT(date_embauche, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')");
        $stats['nouveaux_ce_mois'] = $stmt->fetch()['total'];
        
        // Total administrateurs
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes WHERE is_admin = 1 AND statut != 'inactif'");
        $stats['total_admins'] = $stmt->fetch()['total'];
        
        // Statistiques par type de contrat
        $stmt = $this->conn->query("
            SELECT p.type_contrat, COUNT(e.id) as count 
            FROM employes e 
            JOIN postes p ON e.poste_id = p.id 
            WHERE e.statut = 'actif' 
            GROUP BY p.type_contrat
        ");
        $stats['par_contrat'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }
    
    
    //  Ajoute un nouvel employé
   
    public function addEmployee(array $data): array {
        try {
            // Validation des champs requis
            $this->validateRequiredFields($data, ['nom', 'prenom', 'email', 'date_embauche']);
            
            // Vérifier l'unicité de l'email
            $this->checkEmailUniqueness($data['email']);
            
            // Gestion de l'upload de photo
            $photo_filename = $this->handlePhotoUpload();
            
            // Insertion de l'employé
            $employee_id = $this->insertEmployee($data, $photo_filename);
            
            // Génération du QR Code
          // Génération du QR Code
$numeric_code = $this->generateAndSaveQRCode($employee_id, $data);

// Log de l'activité
$this->logActivity('CREATE_EMPLOYEE', 'employes', $employee_id, [
    'nom' => $data['nom'], 
    'prenom' => $data['prenom'],
    'code_numerique' => $numeric_code  // Le code est bien récupéré ici
]);

return [
    'success' => true,
    'message' => 'Employé ajouté avec succès',
    'employee_id' => $employee_id,
    'numeric_code' => $numeric_code    // Et retourné ici
];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Met à jour un employé existant
     */
    public function updateEmployee(array $data): array {
        try {
            if (empty($data['id'])) {
                throw new Exception('ID employé requis');
            }
            
            $employee_id = $data['id'];
            $current_employee = $this->getEmployeeById($employee_id);
            
            if (!$current_employee) {
                throw new Exception('Employé non trouvé');
            }
            
            // Vérifier l'unicité de l'email (sauf pour l'employé actuel)
            $this->checkEmailUniqueness($data['email'], $employee_id);
            
            // Gestion de l'upload de photo
            $photo_filename = $this->handlePhotoUpload($current_employee['photo']);
            
            // Mise à jour
            $this->updateEmployeeData($employee_id, $data, $photo_filename);
            
            // Log de l'activité
            $this->logActivity('UPDATE_EMPLOYEE', 'employes', $employee_id, [
                'nom' => $data['nom'], 
                'prenom' => $data['prenom']
            ]);
            
            return ['success' => true, 'message' => 'Employé modifié avec succès'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Désactive un employé
     */
    public function deactivateEmployee(int $employee_id): array {
        try {
            $stmt = $this->conn->prepare("UPDATE employes SET statut = 'inactif' WHERE id = ?");
            $stmt->execute([$employee_id]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Employé non trouvé');
            }
            
            $this->logActivity('DEACTIVATE_EMPLOYEE', 'employes', $employee_id, ['statut' => 'inactif']);
            
            return ['success' => true, 'message' => 'Employé désactivé avec succès'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Récupère les types de contrat disponibles
     */
    public function getTypesContrat(): array {
        return [
            'CDI' => 'Contrat à Durée Indéterminée',
            'CDD' => 'Contrat à Durée Déterminée',
            'Stage' => 'Stage',
            'Apprentissage' => 'Contrat d\'Apprentissage',
            'Freelance' => 'Freelance/Consultant',
            'Temps_partiel' => 'Temps Partiel'
        ];
    }
    
    // =============================================================================
    // MÉTHODES PRIVÉES UTILITAIRES
    // =============================================================================
    
    private function validateRequiredFields(array $data, array $required_fields): void {
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Le champ $field est requis");
            }
        }
    }
    
    private function checkEmailUniqueness(string $email, int $exclude_id = null): void {
        $sql = "SELECT id FROM employes WHERE email = ? AND statut != 'inactif'";
        $params = [$email];
        
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->fetch()) {
            throw new Exception('Cet email est déjà utilisé par un autre employé actif');
        }
    }
    
    private function handlePhotoUpload(string $current_photo = 'default-avatar.png'): string {
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            return $current_photo;
        }
        
        $upload_dir = 'uploads/photos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($file_ext, $allowed_exts) || $_FILES['photo']['size'] > 5000000) {
            throw new Exception('Format de photo non valide ou taille trop importante');
        }
        
        $photo_filename = uniqid() . '.' . $file_ext;
        $upload_path = $upload_dir . $photo_filename;
        
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
            throw new Exception('Erreur lors de l\'upload de la photo');
        }
        
        // Supprimer l'ancienne photo si ce n'est pas la photo par défaut
        if ($current_photo !== 'default-avatar.png') {
            $old_photo = $upload_dir . $current_photo;
            if (file_exists($old_photo)) {
                unlink($old_photo);
            }
        }
        
        return $photo_filename;
    }
    
    private function insertEmployee(array $data, string $photo_filename): int {
        $salaire = !empty($data['salaire']) ? (int) $data['salaire'] : null;
        
        $stmt = $this->conn->prepare("
            INSERT INTO employes (nom, prenom, email, telephone, poste_id, salaire, date_embauche, 
                                  heure_debut, heure_fin, photo, is_admin, statut) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['nom'],
            $data['prenom'],
            $data['email'],
            $data['telephone'] ?? null,
            $data['poste_id'] ?? null,
            $salaire,
            $data['date_embauche'],
            $data['heure_debut'] ?? '08:00:00',
            $data['heure_fin'] ?? '17:00:00',
            $photo_filename,
            isset($data['is_admin']) ? 1 : 0,
            $data['statut'] ?? 'actif'
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    private function updateEmployeeData(int $employee_id, array $data, string $photo_filename): void {
        $salaire = !empty($data['salaire']) ? (int) $data['salaire'] : null;
        
        $stmt = $this->conn->prepare("
            UPDATE employes 
            SET nom = ?, prenom = ?, email = ?, telephone = ?, poste_id = ?, salaire = ?, 
                date_embauche = ?, heure_debut = ?, heure_fin = ?, photo = ?, 
                is_admin = ?, statut = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['nom'],
            $data['prenom'],
            $data['email'],
            $data['telephone'] ?? null,
            $data['poste_id'] ?? null,
            $salaire,
            $data['date_embauche'],
            $data['heure_debut'] ?? '08:00:00',
            $data['heure_fin'] ?? '17:00:00',
            $photo_filename,
            isset($data['is_admin']) ? 1 : 0,
            $data['statut'] ?? 'actif',
            $employee_id
        ]);
    }
    
    private function generateAndSaveQRCode(int $employee_id, array $data): string {
        $numeric_code = QRCodeGenerator::generateNumericCode($employee_id, $this->conn);
        
        $qr_data = json_encode([
            'type' => 'employee_badge',
            'id' => (int)$employee_id,
            'code' => $numeric_code,
            'nom' => trim($data['nom']),
            'prenom' => trim($data['prenom']),
            'email' => $data['email'],
            'poste_id' => $data['poste_id'] ?? null,
            'timestamp' => time(),
            'version' => '1.0'
        ], JSON_UNESCAPED_UNICODE);
        
        $qr_filename = QRCodeGenerator::generateQRCode($employee_id, $numeric_code, $qr_data);
        
        // Mise à jour avec le QR Code ET le code numérique
        $stmt = $this->conn->prepare("UPDATE employes SET qr_code = ?, qr_data = ?, code_numerique = ? WHERE id = ?");
        $stmt->execute([$qr_filename, $qr_data, $numeric_code, $employee_id]);
        
        return $numeric_code;
    }
    
    private function logActivity(string $action, string $table, int $record_id, array $details): void {
        $stmt = $this->conn->prepare("
            INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$action, $table, $record_id, json_encode($details)]);
    }
}

// =============================================================================
// GÉNÉRATEUR DE QR CODE
// =============================================================================

class QRCodeGenerator {
    public static function generateNumericCode(int $employee_id, PDO $conn): string {
        $max_attempts = 10;
        $attempt = 0;
        
        do {
            $date_prefix = date('Ymd');
            $id_part = str_pad($employee_id, 4, '0', STR_PAD_LEFT);
            $random_part = str_pad(rand(10, 99), 2, '0', STR_PAD_LEFT);
            $numeric_code = $date_prefix . $id_part . $random_part;
            
            // Vérifier l'unicité
            $stmt = $conn->prepare("SELECT id FROM employes WHERE code_numerique = ?");
            $stmt->execute([$numeric_code]);
            
            if (!$stmt->fetch()) {
                return $numeric_code;
            }
            
            $attempt++;
        } while ($attempt < $max_attempts);
        
        // Fallback
        return $date_prefix . $id_part . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
    }
    
    public static function generateQRCode(int $employee_id, string $numeric_code, string $qr_data): string {
        $qr_dir = 'qrcodes/';
        if (!is_dir($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }
        
        $qr_filename = 'employee_' . $employee_id . '_' . $numeric_code . '.png';
        $qr_path = $qr_dir . $qr_filename;
        
        try {
            QRcode::png($qr_data, $qr_path, QR_ECLEVEL_H, 10, 2);
            
            // Optimisation de l'image si GD est disponible
            if (extension_loaded('gd')) {
                self::optimizeQRImage($qr_path);
            }
            
        } catch (Exception $e) {
            error_log("Erreur génération QR: " . $e->getMessage());
            QRcode::png($numeric_code, $qr_path, QR_ECLEVEL_M, 8, 2);
        }
        
        return $qr_filename;
    }
    
    private static function optimizeQRImage(string $qr_path): void {
        try {
            $source = imagecreatefrompng($qr_path);
            if (!$source) return;
            
            $width = imagesx($source);
            $height = imagesy($source);
            $target_size = 400;
            
            $new_image = imagecreatetruecolor($target_size, $target_size);
            $white = imagecolorallocate($new_image, 255, 255, 255);
            imagefill($new_image, 0, 0, $white);
            
            imagecopyresampled(
                $new_image, $source,
                0, 0, 0, 0,
                $target_size, $target_size,
                $width, $height
            );
            
            imagesavealpha($new_image, true);
            imagepng($new_image, $qr_path, 0);
            
            imagedestroy($source);
            imagedestroy($new_image);
            
        } catch (Exception $e) {
            error_log("Erreur optimisation QR: " . $e->getMessage());
        }
    }
}

// =============================================================================
// GESTIONNAIRE DE PAIE
// =============================================================================

class PayrollManager {
    private $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
//    Calcule le salaire net d'un employé pour un mois donné
   
    public function calculerSalaireNet(int $employe_id, string $mois_annee): array {
        // Récupérer les données de l'employé et de son poste
        $stmt = $this->conn->prepare("
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
        
        // Utiliser le salaire de l'employé s'il existe, sinon celui du poste
        $salaire_brut = $data['salaire'] ?: ($data['salaire_poste'] ?: 0);
        
        // Pour la démonstration, nous utilisons des valeurs par défaut
        // Dans un vrai système, ces données viendraient d'autres tables
        $primes_individuelles = 0; // À récupérer depuis une table de primes
        $absences_jours = 0; // À récupérer depuis une table de présences
        $retenues_diverses = 0; // À récupérer depuis une table de retenues
        $cotisations_supplementaires = 0; // À récupérer depuis configuration
        
        // Calculs
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

    /**
     * Génère un bulletin de paie en PDF
     */
    public function genererBulletinPaie(array $details): string {
        // Vérifier si TCPDF est disponible
        if (!class_exists('TCPDF')) {
            // Version simple sans TCPDF
            return $this->genererBulletinHTML($details);
        }
        
        require_once '../vendor/autoload.php';
        
        // Créer le PDF
        $pdf = new TCPDF();
        $pdf->SetCreator('Système de Gestion RH');
        $pdf->SetAuthor('Restaurant Management System');
        $pdf->SetTitle('Bulletin de Paie - ' . $details['nom'] . ' ' . $details['prenom']);
        $pdf->AddPage();
        
        // En-tête
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'BULLETIN DE PAIE', 0, 1, 'C');
        $pdf->Cell(0, 10, strtoupper($details['nom'] . ' ' . $details['prenom']), 0, 1, 'C');
        $pdf->Cell(0, 10, 'Poste: ' . ($details['poste_nom'] ?: 'Non défini'), 0, 1, 'C');
        $pdf->Cell(0, 10, 'Mois: ' . date('F Y', strtotime($details['mois_annee'] . '-01')), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Détails du salaire
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'DETAILS DU SALAIRE', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        $lignes = [
            ['Salaire brut de base:', $details['salaire_brut']],
            ['Primes individuelles:', $details['primes']],
            ['Retenues pour absences:', -$details['retenues_absences']],
            ['Cotisations sociales:', -$details['cotisations']],
            ['Autres retenues:', -$details['retenues_diverses']]
        ];
        
        foreach ($lignes as $ligne) {
            $pdf->Cell(100, 6, $ligne[0], 0, 0, 'L');
            $montant = ($ligne[1] < 0 ? '-' : '') . number_format(abs($ligne[1]), 0, ',', ' ') . ' FCFA';
            $pdf->Cell(0, 6, $montant, 0, 1, 'R');
        }
        
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(100, 8, 'SALAIRE NET A PAYER:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell(0, 8, number_format($details['salaire_net'], 0, ',', ' ') . ' FCFA', 0, 1, 'R');
        $pdf->SetTextColor(0, 0, 0);
        
        return $pdf->Output('', 'S'); // Retourner le contenu du PDF
    }
// Version alternative sans TCPDF (génère du HTML)
  
    private function genererBulletinHTML(array $details): string {
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Bulletin de Paie</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .details { margin: 20px 0; }
                .line { display: flex; justify-content: space-between; padding: 5px 0; }
                .total { font-weight: bold; color: green; font-size: 18px; border-top: 2px solid #000; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>BULLETIN DE PAIE</h1>
                <h2>" . strtoupper($details['nom'] . ' ' . $details['prenom']) . "</h2>
                <p>Poste: " . ($details['poste_nom'] ?: 'Non défini') . "</p>
                <p>Mois: " . date('F Y', strtotime($details['mois_annee'] . '-01')) . "</p>
            </div>
            
            <div class='details'>
                <h3>DETAILS DU SALAIRE</h3>
                <div class='line'>
                    <span>Salaire brut de base:</span>
                    <span>" . number_format($details['salaire_brut'], 0, ',', ' ') . " FCFA</span>
                </div>
                <div class='line'>
                    <span>Primes individuelles:</span>
                    <span>" . number_format($details['primes'], 0, ',', ' ') . " FCFA</span>
                </div>
                <div class='line'>
                    <span>Retenues pour absences:</span>
                    <span>-" . number_format($details['retenues_absences'], 0, ',', ' ') . " FCFA</span>
                </div>
                <div class='line'>
                    <span>Cotisations sociales:</span>
                    <span>-" . number_format($details['cotisations'], 0, ',', ' ') . " FCFA</span>
                </div>
                <div class='line'>
                    <span>Autres retenues:</span>
                    <span>-" . number_format($details['retenues_diverses'], 0, ',', ' ') . " FCFA</span>
                </div>
                <div class='line total'>
                    <span>SALAIRE NET A PAYER:</span>
                    <span>" . number_format($details['salaire_net'], 0, ',', ' ') . " FCFA</span>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
// Enregistre un bulletin de paie dans la base de données

    public function enregistrerBulletinPaie(array $details): int {
        $stmt = $this->conn->prepare("
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
        return $this->conn->lastInsertId();
    }
  
// Génère les bulletins pour tous les employés actifs
   
    public function genererBulletinsPourTous(string $mois_annee): array {
        $resultats = [];
        $stmt = $this->conn->prepare("SELECT id FROM employes WHERE statut = 'actif'");
        $stmt->execute();
        $employes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($employes as $employe_id) {
            try {
                $details = $this->calculerSalaireNet($employe_id, $mois_annee);
                $bulletin_id = $this->enregistrerBulletinPaie($details);
                $resultats[$employe_id] = [
                    'success' => true,
                    'bulletin_id' => $bulletin_id,
                    'salaire_net' => $details['salaire_net']
                ];
            } catch (Exception $e) {
                $resultats[$employe_id] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return $resultats;
    }
}

// GESTIONNAIRE DE POSTES - AMÉLIORÉ
class PosteManager {
    private $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    /**
     * Récupère tous les postes avec toutes leurs informations
     */
    public function getAllPostes(): array {
        $stmt = $this->conn->query("
            SELECT p.*, 
                   ps.nom as poste_superieur_nom
            FROM postes p 
            LEFT JOIN postes ps ON p.poste_superieur_id = ps.id 
            WHERE p.actif = 1 
            ORDER BY p.niveau_hierarchique ASC, p.nom
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère un poste par son ID avec toutes ses informations
     */
    public function getPosteById(int $id): ?array {
        $stmt = $this->conn->prepare("
            SELECT p.*, 
                   ps.nom as poste_superieur_nom
            FROM postes p 
            LEFT JOIN postes ps ON p.poste_superieur_id = ps.id 
            WHERE p.id = ? AND p.actif = 1
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    //  Récupère les types de contrat disponibles
    
    public function getTypesContrat(): array {
        return [
            'CDI' => 'Contrat à Durée Indéterminée',
            'CDD' => 'Contrat à Durée Déterminée',
            'Stage' => 'Stage',
            'Apprentissage' => 'Contrat d\'Apprentissage',
            'Freelance' => 'Freelance/Consultant',
            'Temps_partiel' => 'Temps Partiel'
        ];
    }
}

class APIHandler {
    private $employeeManager;
    private $posteManager;
    private $payrollManager;
    
    public function __construct(PDO $conn) {
        $this->employeeManager = new EmployeeManager($conn);
        $this->posteManager = new PosteManager($conn);
        $this->payrollManager = new PayrollManager($conn);
    }
    
    public function handleRequest(): void {
        $action = $_GET['action'] ?? $_POST['ajax_action'] ?? '';
        
        // Pour les actions de génération de bulletins, on ne force pas le JSON
        if (in_array($action, ['generer_bulletin'])) {
            // Ces actions peuvent retourner du PDF
        } else {
            header('Content-Type: application/json');
        }
        
        switch ($action) {
            case 'get_employees':
                $this->getEmployees();
                break;
            case 'get_statistics':
                $this->getStatistics();
                break;
            case 'get_postes':
                $this->getPostes();
                break;
            case 'get_poste_details':
                $this->getPosteDetails();
                break;
            case 'add_employee':
                $this->addEmployee();
                break;
            case 'update_employee':
                $this->updateEmployee();
                break;
            case 'delete_employee':
                $this->deleteEmployee();
                break;
            case 'generer_bulletin':
                $this->genererBulletin();
                break;
            case 'generer_tous_bulletins':
                $this->genererTousBulletins();
                break;
            default:
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        }
        exit;
    }
    
    private function getEmployees(): void {
        try {
            $employees = $this->employeeManager->getAllEmployees();
            echo json_encode(['success' => true, 'employees' => $employees]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des employés']);
        }
    }
    
    private function getStatistics(): void {
        try {
            $statistics = $this->employeeManager->getStatistics();
            echo json_encode(['success' => true, 'statistics' => $statistics]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des statistiques']);
        }
    }
    
    private function getPostes(): void {
        try {
            $postes = $this->posteManager->getAllPostes();
            echo json_encode(['success' => true, 'postes' => $postes]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des postes']);
        }
    }
    
    private function getPosteDetails(): void {
        try {
            $poste_id = $_GET['id'] ?? null;
            if (!$poste_id) {
                echo json_encode(['success' => false, 'message' => 'ID poste requis']);
                return;
            }
            
            $poste = $this->posteManager->getPosteById($poste_id);
            if (!$poste) {
                echo json_encode(['success' => false, 'message' => 'Poste non trouvé']);
                return;
            }
            
            echo json_encode(['success' => true, 'poste' => $poste]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement du poste']);
        }
    }
    
    private function addEmployee(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            return;
        }
        
        $result = $this->employeeManager->addEmployee($_POST);
        echo json_encode($result);
    }
    
    private function updateEmployee(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            return;
        }
        
        $result = $this->employeeManager->updateEmployee($_POST);
        echo json_encode($result);
    }
    
    private function deleteEmployee(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['id'])) {
            echo json_encode(['success' => false, 'message' => 'ID employé requis']);
            return;
        }
        
        $result = $this->employeeManager->deactivateEmployee($input['id']);
        echo json_encode($result);
    }
    
    private function genererBulletin(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            return;
        }

        try {
            $employe_id = $_POST['employe_id'] ?? null;
            $mois_annee = $_POST['mois_annee'] ?? null;

            if (!$employe_id || !$mois_annee) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Employé et mois requis']);
                return;
            }

            // Vérifier que l'employé existe
            $employee = $this->employeeManager->getEmployeeById($employe_id);
            if (!$employee) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Employé non trouvé']);
                return;
            }

            // Calculer le salaire et générer le bulletin
            $details = $this->payrollManager->calculerSalaireNet($employe_id, $mois_annee);
            $pdf_content = $this->payrollManager->genererBulletinPaie($details);
            
            // Enregistrer le bulletin dans la base de données
            $bulletin_id = $this->payrollManager->enregistrerBulletinPaie($details);

            // Vérifier si c'est du PDF ou du HTML
            if (strpos($pdf_content, '<!DOCTYPE html') !== false) {
                // C'est du HTML, on le convertit pour le téléchargement
                header('Content-Type: text/html; charset=UTF-8');
                header('Content-Disposition: attachment; filename="bulletin_' . $employee['nom'] . '_' . $mois_annee . '.html"');
            } else {
                // C'est du PDF
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="bulletin_' . $employee['nom'] . '_' . $mois_annee . '.pdf"');
            }
            
            header('Content-Length: ' . strlen($pdf_content));
            echo $pdf_content;

        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function genererTousBulletins(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            return;
        }

        try {
            $mois_annee = $_POST['mois_annee'] ?? null;

            if (!$mois_annee) {
                echo json_encode(['success' => false, 'message' => 'Mois requis']);
                return;
            }

            $resultats = $this->payrollManager->genererBulletinsPourTous($mois_annee);
            
            $count_success = count(array_filter($resultats, function($r) { return $r['success']; }));
            $count_total = count($resultats);

            if ($count_success > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Bulletins générés avec succès",
                    'count' => $count_success,
                    'total' => $count_total,
                    'details' => $resultats
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Aucun bulletin généré']);
            }

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}

if (isset($_GET['action']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']))) {
    $apiHandler = new APIHandler($conn);
    $apiHandler->handleRequest();
}

try {
    $posteManager = new PosteManager($conn);
    $postes = $posteManager->getAllPostes();
    
    $employeeManager = new EmployeeManager($conn);
    $employes = $employeeManager->getAllEmployees();
} catch (Exception $e) {
    $postes = [];
    $employes = [];
    error_log("Erreur lors du chargement des données: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <!-- gère l'encodage des caractères -->
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Employés - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .hover-scale { transition: transform 0.2s; }
        .hover-scale:hover { transform: scale(1.05); }
        .card-shadow { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
        .notification { position: fixed; top: 20px; right: 20px; z-index: 1000; }
        .contract-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .contract-cdi { background-color: #dcfce7; color: #166534; }
        .contract-cdd { background-color: #fef3c7; color: #92400e; }
        .contract-stage { background-color: #dbeafe; color: #1e40af; }
        .contract-apprentissage { background-color: #fce7f3; color: #be185d; }
        .contract-freelance { background-color: #f3e8ff; color: #7c3aed; }
        .contract-temps_partiel { background-color: #e0f2fe; color: #0277bd; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-utensils text-orange-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-900">Gestion Restaurant</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="toggleView()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-th" id="viewIcon"></i>
                        <span id="viewText">Vue Cartes</span>
                    </button>
                    <button onclick="openAddModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Ajouter Employé
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Statistiques Dashboard -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Employés Actifs</p>
                        <p class="text-2xl font-bold text-gray-900" id="totalActifs">0</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-check-circle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Présents Aujourd'hui</p>
                        <p class="text-2xl font-bold text-gray-900" id="presentsAujourdhui">0</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                        <i class="fas fa-user-plus text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Nouveaux ce mois</p>
                        <p class="text-2xl font-bold text-gray-900" id="nouveauxCeMois">0</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-crown text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Administrateurs</p>
                        <p class="text-2xl font-bold text-gray-900" id="totalAdmins">0</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres et Recherche -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6 card-shadow">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <input type="text" id="searchInput" placeholder="Rechercher par nom, email..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <select id="filterPoste" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tous les postes</option>
                    </select>
                </div>
                <div>
                    <select id="filterContrat" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tous les contrats</option>
                        <option value="CDI">CDI</option>
                        <option value="CDD">CDD</option>
                        <option value="Stage">Stage</option>
                        <option value="Apprentissage">Apprentissage</option>
                        <option value="Freelance">Freelance</option>
                        <option value="Temps_partiel">Temps Partiel</option>
                    </select>
                </div>
                <div>
                    <select id="filterStatut" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tous les statuts</option>
                        <option value="actif">Actif</option>
                        <option value="en_conge">En congé</option>
                        <option value="absent">Absent</option>
                        <option value="inactif">Inactif</option>
                    </select>
                </div>
                <div>
                    <button onclick="resetFilters()" class="w-full px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition duration-200">
                        <i class="fas fa-undo mr-2"></i>Réinitialiser
                    </button>
                </div>
            </div>
        </div>

        <!-- Section Génération des Bulletins de Paie -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6 card-shadow">
            <h2 class="text-xl font-semibold mb-6">Génération des Bulletins de Paie</h2>
            <form id="genererBulletinForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="employe_id" class="block text-sm font-medium text-gray-700 mb-2">Employé</label>
                        <select id="employe_id" name="employe_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            <option value="">Sélectionnez un employé</option>
                            <?php foreach ($employes as $employe): ?>
                                <option value="<?php echo $employe['id']; ?>">
                                    <?php echo htmlspecialchars($employe['nom'] . ' ' . $employe['prenom'] . ' (' . ($employe['poste_nom'] ?? 'Aucun poste') . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="mois_annee" class="block text-sm font-medium text-gray-700 mb-2">Mois</label>
                        <input type="month" id="mois_annee" name="mois_annee" required
                               value="<?php echo date('Y-m'); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="flex items-end">
                        <button type="button" onclick="genererBulletin()"
                                class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-file-pdf mr-2"></i>Générer Bulletin
                        </button>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="button" onclick="genererTousBulletins()"
                            class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 transition-colors">
                        <i class="fas fa-file-pdf mr-2"></i>Générer pour tous les employés actifs
                    </button>
                </div>
            </form>
        </div>

        <!-- Vue Tableau -->
        <div id="tableView" class="bg-white rounded-lg shadow-md overflow-hidden card-shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
    <tr>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Photo</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employé</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poste</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contrat</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Salaire</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Heures/Mois</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
    </tr>
</thead>
                    <tbody id="employeesTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Les employés seront chargés ici -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Vue Cartes -->
        <div id="cardView" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 hidden">
            <!-- Les cartes seront chargées ici -->
        </div>
    </div>

    <!-- Modal Ajouter/Modifier Employé -->
    <div id="employeeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 id="modalTitle" class="text-lg font-semibold text-gray-900">Ajouter un employé</h3>
                </div>
                
                <form id="employeeForm" class="p-6" enctype="multipart/form-data">
                    <input type="hidden" id="employeeId" name="id">
                    <input type="hidden" name="ajax_action" id="ajaxAction" value="add_employee">
                    
                    <!-- Photo de profil -->
                    <div class="mb-6 text-center">
                        <div class="relative inline-block">
                            <img id="photoPreview" src="uploads/photos/default-avatar.png" 
                                 class="w-24 h-24 rounded-full border-4 border-gray-200 object-cover">
                            <label for="photo" class="absolute bottom-0 right-0 bg-blue-600 text-white rounded-full p-2 cursor-pointer hover:bg-blue-700">
                                <i class="fas fa-camera text-sm"></i>
                                <input type="file" id="photo" name="photo" accept="image/*" class="hidden">
                            </label>
                        </div>
                    </div>

                    <!-- Informations personnelles -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="nom" class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                            <input type="text" id="nom" name="nom" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="prenom" class="block text-sm font-medium text-gray-700 mb-2">Prénom *</label>
                            <input type="text" id="prenom" name="prenom" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                            <input type="email" id="email" name="email" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="telephone" class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Informations professionnelles -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="poste" class="block text-sm font-medium text-gray-700 mb-2">Poste *</label>
                            <select id="poste" name="poste_id" required onchange="updatePosteInfo()"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Sélectionner un poste</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="salaire" class="block text-sm font-medium text-gray-700 mb-2">
                                Salaire (FCFA)
                                <span id="salaireRange" class="text-xs text-gray-500"></span>
                            </label>
                            <input type="number" id="salaire" name="salaire" min="0" step="1" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Informations contrat -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="typeContrat" class="block text-sm font-medium text-gray-700 mb-2">Type de contrat</label>
                            <input type="text" id="typeContrat" name="type_contrat" readonly
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-700">
                        </div>
                        
                        <div>
                            <label for="dureeContrat" class="block text-sm font-medium text-gray-700 mb-2">Durée du contrat</label>
                            <input type="text" id="dureeContrat" name="duree_contrat" readonly
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-700">
                        </div>
                    </div>

                    <!-- Dates et horaires -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label for="dateEmbauche" class="block text-sm font-medium text-gray-700 mb-2">Date d'embauche *</label>
                            <input type="date" id="dateEmbauche" name="date_embauche" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="heureDebut" class="block text-sm font-medium text-gray-700 mb-2">Heure début</label>
                            <input type="time" id="heureDebut" name="heure_debut" value="08:00" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label for="heureFin" class="block text-sm font-medium text-gray-700 mb-2">Heure fin</label>
                            <input type="time" id="heureFin" name="heure_fin" value="17:00" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Statut et options -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="statut" class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                            <select id="statut" name="statut" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="actif">Actif</option>
                                <option value="en_conge">En congé</option>
                                <option value="absent">Absent</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <label class="flex items-center">
                                <input type="checkbox" id="isAdmin" name="is_admin" value="1" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">
                                    <i class="fas fa-crown text-yellow-500 mr-1"></i>
                                    Administrateur
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Informations du poste (affichage uniquement) -->
                    <div id="posteInfo" class="bg-gray-50 rounded-lg p-4 mb-6 hidden">
                        <h4 class="text-md font-semibold text-gray-800 mb-3">Informations du poste</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="font-medium text-gray-600">Niveau hiérarchique:</span>
                                <span id="niveauHierarchique" class="ml-2 text-gray-800"></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-600">Code paie:</span>
                                <span id="codePaie" class="ml-2 text-gray-800"></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-600">Catégorie paie:</span>
                                <span id="categoriePaie" class="ml-2 text-gray-800"></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-600">Régime social:</span>
                                <span id="regimeSocial" class="ml-2 text-gray-800"></span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="font-medium text-gray-600">Compétences requises:</span>
                            <div id="competencesRequises" class="mt-1 text-gray-800"></div>
                        </div>
                        <div class="mt-3">
                            <span class="font-medium text-gray-600">Avantages:</span>
                            <div id="avantages" class="mt-1 text-gray-800"></div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200">
                            Annuler
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-save mr-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Zone de notification -->
    <div id="notification" class="notification hidden"></div>

    <script>
        let currentView = localStorage.getItem('preferredView') || 'table';
        let employees = [];
        let postes = [];

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            loadPostes();
            loadEmployees();
            loadStatistics();
            setView();
            
            // Auto-refresh des statistiques toutes les 30 secondes
            setInterval(loadStatistics, 30000);
            
            // Événements
            document.getElementById('searchInput').addEventListener('input', filterEmployees);
            document.getElementById('filterPoste').addEventListener('change', filterEmployees);
            document.getElementById('filterContrat').addEventListener('change', filterEmployees);
            document.getElementById('filterStatut').addEventListener('change', filterEmployees);
            document.getElementById('photo').addEventListener('change', previewPhoto);
            document.getElementById('employeeForm').addEventListener('submit', saveEmployee);
        });
        
        function toggleView() {
            currentView = currentView === 'table' ? 'cards' : 'table';
            localStorage.setItem('preferredView', currentView);
            setView();
        }

        function setView() {
            const tableView = document.getElementById('tableView');
            const cardView = document.getElementById('cardView');
            const viewIcon = document.getElementById('viewIcon');
            const viewText = document.getElementById('viewText');
            
            if (currentView === 'table') {
                tableView.classList.remove('hidden');
                cardView.classList.add('hidden');
                viewIcon.className = 'fas fa-th';
                viewText.textContent = 'Vue Cartes';
            } else {
                tableView.classList.add('hidden');
                cardView.classList.remove('hidden');
                viewIcon.className = 'fas fa-list';
                viewText.textContent = 'Vue Tableau';
            }
        }

        function loadStatistics() {
            fetch('?action=get_statistics')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('totalActifs').textContent = data.statistics.total_actifs;
                        document.getElementById('presentsAujourdhui').textContent = data.statistics.presents_aujourd_hui;
                        document.getElementById('nouveauxCeMois').textContent = data.statistics.nouveaux_ce_mois;
                        document.getElementById('totalAdmins').textContent = data.statistics.total_admins;
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        function loadPostes() {
            fetch('?action=get_postes')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        postes = data.postes;
                        updatePostesSelects();
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }

        function updatePostesSelects() {
            const filterPoste = document.getElementById('filterPoste');
            const modalPoste = document.getElementById('poste');
            
            filterPoste.innerHTML = '<option value="">Tous les postes</option>';
            modalPoste.innerHTML = '<option value="">Sélectionner un poste</option>';
            
            postes.forEach(poste => {
                filterPoste.innerHTML += `<option value="${poste.id}">${poste.nom}</option>`;
                modalPoste.innerHTML += `<option value="${poste.id}">${poste.nom} - ${poste.type_contrat || 'Non défini'}</option>`;
            });
        }

        function updatePosteInfo() {
            const posteId = document.getElementById('poste').value;
            const posteInfo = document.getElementById('posteInfo');
            
            if (!posteId) {
                posteInfo.classList.add('hidden');
                document.getElementById('typeContrat').value = '';
                document.getElementById('dureeContrat').value = '';
                document.getElementById('salaire').value = '';
                document.getElementById('salaireRange').textContent = '';
                return;
            }

            fetch(`?action=get_poste_details&id=${posteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.poste) {
                        const poste = data.poste;
                        
                        // Remplir les champs automatiquement
                        document.getElementById('typeContrat').value = poste.type_contrat || '';
                        document.getElementById('dureeContrat').value = poste.duree_contrat || '';
                        document.getElementById('salaire').value = poste.salaire || '';
                        
                        // Afficher la fourchette de salaire
                        if (poste.salaire_min && poste.salaire_max) {
                            document.getElementById('salaireRange').textContent = 
                                `(${formatSalaire(poste.salaire_min)} - ${formatSalaire(poste.salaire_max)} FCFA)`;
                        }
                        
                        // Afficher les informations du poste
                        document.getElementById('niveauHierarchique').textContent = poste.niveau_hierarchique || 'Non défini';
                        document.getElementById('codePaie').textContent = poste.code_paie || 'Non défini';
                        document.getElementById('categoriePaie').textContent = poste.categorie_paie || 'Non définie';
                        document.getElementById('regimeSocial').textContent = poste.regime_social || 'Non défini';
                        document.getElementById('competencesRequises').textContent = poste.competences_requises || 'Aucune spécifiée';
                        document.getElementById('avantages').textContent = poste.avantages || 'Aucun spécifié';
                        
                        posteInfo.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    posteInfo.classList.add('hidden');
                });
        }

        function loadEmployees() {
            fetch('?action=get_employees')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        employees = data.employees;
                        displayEmployees(employees);
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }
        
        function displayEmployees(employeesList) {
            if (currentView === 'table') {
                displayTableView(employeesList);
            } else {
                displayCardView(employeesList);
            }
        }

        function displayTableView(employeesList) {
            const tbody = document.getElementById('employeesTableBody');
            tbody.innerHTML = '';
            
            employeesList.forEach(employee => {
                const row = createEmployeeRow(employee);
                tbody.appendChild(row);
            });
        }

       function createEmployeeRow(employee) {
    const row = document.createElement('tr');
    row.className = 'hover:bg-gray-50 fade-in';
    
    row.innerHTML = `
        <td class="px-6 py-4 whitespace-nowrap">
            <img src="uploads/photos/${employee.photo || 'default-avatar.png'}" 
                 class="h-10 w-10 rounded-full object-cover">
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm font-medium text-gray-900">
                ${employee.prenom} ${employee.nom}
                ${employee.is_admin ? '<i class="fas fa-crown text-yellow-500 ml-1" title="Administrateur"></i>' : ''}
            </div>
            <div class="text-sm text-gray-500">ID: ${employee.id}</div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                      style="background-color: ${employee.poste_couleur || '#6B7280'}20; color: ${employee.poste_couleur || '#6B7280'};">
                    ${employee.poste_nom || 'Non défini'}
                </span>
            </div>
            <div class="text-xs text-gray-500 mt-1">Niveau: ${employee.niveau_hierarchique || 'N/A'}</div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex flex-col space-y-1">
                <span class="contract-badge contract-${(employee.type_contrat || '').toLowerCase().replace(' ', '_')}">
                    ${employee.type_contrat || 'Non défini'}
                </span>
                <div class="text-xs text-gray-500">${employee.duree_contrat || 'Non spécifiée'}</div>
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
            <div>${employee.email}</div>
            <div class="text-gray-500">${employee.telephone || 'N/A'}</div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusClass(employee.statut)}">
                ${getStatusText(employee.statut)}
            </span>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
            ${employee.salaire ? formatSalaire(employee.salaire) + ' FCFA' : 'Non défini'}
            <div class="text-xs text-gray-500">${employee.heure_debut} - ${employee.heure_fin}</div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
            <!-- COLONNE HEURES depuis table heures_travail -->
            <div class="flex items-center">
                <i class="fas fa-clock text-blue-500 mr-1"></i>
                <span class="font-medium text-blue-600">${employee.heures_par_mois || '0'}h</span>
            </div>
            <div class="text-xs text-gray-500">par mois</div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
            <div class="flex space-x-2">
                <button onclick="viewEmployee(${employee.id})" class="text-blue-600 hover:text-blue-900" title="Voir détails">
                    <i class="fas fa-eye"></i>
                </button>
                <button onclick="editEmployee(${employee.id})" class="text-green-600 hover:text-green-900" title="Modifier">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="generateBadge(${employee.id})" class="text-purple-600 hover:text-purple-900" title="Badge">
                    <i class="fas fa-qrcode"></i>
                </button>
                <button onclick="deleteEmployee(${employee.id})" class="text-red-600 hover:text-red-900" title="Désactiver">
                    <i class="fas fa-user-slash"></i>
                </button>
            </div>
        </td>
    `;
    
    return row;
}

        function displayCardView(employeesList) {
            const cardView = document.getElementById('cardView');
            cardView.innerHTML = '';
            
            employeesList.forEach(employee => {
                const card = createEmployeeCard(employee);
                cardView.appendChild(card);
            });
        }

        function createEmployeeCard(employee) {
            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200 fade-in';
            
            card.innerHTML = `
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <img src="uploads/photos/${employee.photo || 'default-avatar.png'}" 
                             class="h-16 w-16 rounded-full object-cover border-2 border-gray-200">
                        <div class="ml-4 flex-1">
                            <h3 class="text-lg font-semibold text-gray-900">
                                ${employee.prenom} ${employee.nom}
                                ${employee.is_admin ? '<i class="fas fa-crown text-yellow-500 ml-1" title="Administrateur"></i>' : ''}
                            </h3>
                            <div class="flex items-center mt-1 flex-wrap gap-1">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" 
                                      style="background-color: ${employee.poste_couleur || '#6B7280'}20; color: ${employee.poste_couleur || '#6B7280'};">
                                    ${employee.poste_nom || 'Non défini'}
                                </span>
                                <span class="contract-badge contract-${(employee.type_contrat || '').toLowerCase().replace(' ', '_')}">
                                    ${employee.type_contrat || 'Non défini'}
                                </span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusClass(employee.statut)}">
                                    ${getStatusText(employee.statut)}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-2 text-sm text-gray-600">
                        <div class="flex items-center">
                            <i class="fas fa-envelope w-4 mr-2"></i>
                            ${employee.email}
                        </div>
                        ${employee.telephone ? `
                            <div class="flex items-center">
                                <i class="fas fa-phone w-4 mr-2"></i>
                                ${employee.telephone}
                            </div>
                        ` : ''}
                        <div class="flex items-center">
                            <i class="fas fa-calendar w-4 mr-2"></i>
                            Embauché le ${formatDate(employee.date_embauche)}
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-clock w-4 mr-2"></i>
                            ${employee.heure_debut} - ${employee.heure_fin}
                        </div>
                        ${employee.salaire ? `
                            <div class="flex items-center">
                                <i class="fas fa-money-bill w-4 mr-2"></i>
                                ${formatSalaire(employee.salaire)} FCFA
                            </div>
                        ` : ''}
                        ${employee.duree_contrat ? `
                            <div class="flex items-center">
                                <i class="fas fa-contract w-4 mr-2"></i>
                                ${employee.duree_contrat}
                            </div>
                        ` : ''}
                        ${employee.niveau_hierarchique ? `
                            <div class="flex items-center">
                                <i class="fas fa-layer-group w-4 mr-2"></i>
                                Niveau ${employee.niveau_hierarchique}
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="mt-4 flex justify-end space-x-2">
                        <button onclick="viewEmployee(${employee.id})" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg" title="Voir détails">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="editEmployee(${employee.id})" class="p-2 text-green-600 hover:bg-green-50 rounded-lg" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="generateBadge(${employee.id})" class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg" title="Badge">
                            <i class="fas fa-qrcode"></i>
                        </button>
                        <button onclick="deleteEmployee(${employee.id})" class="p-2 text-red-600 hover:bg-red-50 rounded-lg" title="Désactiver">
                            <i class="fas fa-user-slash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            return card;
        }
        
        function getStatusClass(statut) {
            const classes = {
                'actif': 'bg-green-100 text-green-800',
                'en_conge': 'bg-yellow-100 text-yellow-800',
                'absent': 'bg-red-100 text-red-800',
                'inactif': 'bg-gray-100 text-gray-800'
            };
            return classes[statut] || 'bg-gray-100 text-gray-800';
        }

        function getStatusText(statut) {
            const texts = {
                'actif': 'Actif',
                'en_conge': 'En congé',
                'absent': 'Absent',
                'inactif': 'Inactif'
            };
            return texts[statut] || 'Inconnu';
        }

        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }

        function formatSalaire(salaire) {
            if (!salaire) return '';
            return parseInt(salaire).toLocaleString('fr-FR');
        }

        function filterEmployees() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const posteFilter = document.getElementById('filterPoste').value;
            const contratFilter = document.getElementById('filterContrat').value;
            const statutFilter = document.getElementById('filterStatut').value;
            
            const filtered = employees.filter(employee => {
                const matchesSearch = !searchTerm || 
                    employee.nom.toLowerCase().includes(searchTerm) ||
                    employee.prenom.toLowerCase().includes(searchTerm) ||
                    employee.email.toLowerCase().includes(searchTerm);
                
                const matchesPoste = !posteFilter || employee.poste_id == posteFilter;
                const matchesContrat = !contratFilter || employee.type_contrat === contratFilter;
                const matchesStatut = !statutFilter || employee.statut === statutFilter;
                
                return matchesSearch && matchesPoste && matchesContrat && matchesStatut;
            });
            
            displayEmployees(filtered);
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterPoste').value = '';
            document.getElementById('filterContrat').value = '';
            document.getElementById('filterStatut').value = '';
            displayEmployees(employees);
        }

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Ajouter un employé';
            document.getElementById('employeeForm').reset();
            document.getElementById('employeeId').value = '';
            document.getElementById('ajaxAction').value = 'add_employee';
            document.getElementById('photoPreview').src = 'uploads/photos/default-avatar.png';
            document.getElementById('posteInfo').classList.add('hidden');
            document.getElementById('employeeModal').classList.remove('hidden');
        }

        function editEmployee(id) {
            const employee = employees.find(e => e.id == id);
            if (!employee) return;
            
            document.getElementById('modalTitle').textContent = 'Modifier l\'employé';
            document.getElementById('employeeId').value = employee.id;
            document.getElementById('ajaxAction').value = 'update_employee';
            document.getElementById('nom').value = employee.nom;
            document.getElementById('prenom').value = employee.prenom;
            document.getElementById('email').value = employee.email;
            document.getElementById('telephone').value = employee.telephone || '';
            document.getElementById('poste').value = employee.poste_id || '';
            document.getElementById('salaire').value = employee.salaire || '';
            document.getElementById('dateEmbauche').value = employee.date_embauche;
            document.getElementById('statut').value = employee.statut;
            document.getElementById('heureDebut').value = employee.heure_debut;
            document.getElementById('heureFin').value = employee.heure_fin;
            document.getElementById('isAdmin').checked = employee.is_admin == 1;
            document.getElementById('typeContrat').value = employee.type_contrat || '';
            document.getElementById('dureeContrat').value = employee.duree_contrat || '';
            document.getElementById('photoPreview').src = `uploads/photos/${employee.photo || 'default-avatar.png'}`;
            
            // Afficher les informations du poste si un poste est sélectionné
            if (employee.poste_id) {
                updatePosteInfo();
            }
            
            document.getElementById('employeeModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('employeeModal').classList.add('hidden');
        }

        function previewPhoto(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('photoPreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        function saveEmployee(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Employé sauvegardé avec succès!', 'success');
                    closeModal();
                    loadEmployees();
                    loadStatistics();
                } else {
                    showNotification(data.message || 'Erreur lors de la sauvegarde', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Erreur lors de la sauvegarde', 'error');
            });
        }

        function viewEmployee(id) {
            window.open(`employee_details.php?id=${id}`, '_blank');
        }

        function generateBadge(id) {
            window.open(`generate_badge.php?id=${id}`, '_blank');
        }

        function deleteEmployee(id) {
            if (confirm('Êtes-vous sûr de vouloir désactiver cet employé?')) {
                fetch('?action=delete_employee', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Employé désactivé avec succès!', 'success');
                        loadEmployees();
                        loadStatistics();
                    } else {
                        showNotification(data.message || 'Erreur lors de la désactivation', 'error');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    showNotification('Erreur lors de la désactivation', 'error');
                });
            }
        }

     function genererBulletin() {
    const employe_id = document.getElementById('employe_id').value;
    const mois_annee = document.getElementById('mois_annee').value;

    if (!employe_id || !mois_annee) {
        showNotification('Veuillez sélectionner un employé et un mois.', 'error');
        return;
    }

    showLoading();
    
    fetch('generer_bulletin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `employe_id=${employe_id}&mois_annee=${mois_annee}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            try {
                // Décoder le contenu base64
                const byteCharacters = atob(data.pdf);
                const byteNumbers = new Array(byteCharacters.length);
                for (let i = 0; i < byteCharacters.length; i++) {
                    byteNumbers[i] = byteCharacters.charCodeAt(i);
                }
                const byteArray = new Uint8Array(byteNumbers);
                
                // Déterminer le type MIME
                let mimeType, extension;
                if (data.type === 'html') {
                    mimeType = 'text/html';
                    extension = 'html';
                } else {
                    mimeType = 'application/pdf';
                    extension = 'pdf';
                }
                
                const blob = new Blob([byteArray], { type: mimeType });
                
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                
                // Récupérer le nom de l'employé pour le nom du fichier
                const employeSelect = document.getElementById('employe_id');
                const employeText = employeSelect.options[employeSelect.selectedIndex].text;
                const employeName = employeText.split(' (')[0].replace(/\s+/g, '_');
                
                link.download = `bulletin_${employeName}_${mois_annee}.${extension}`;
                
                // Forcer le téléchargement
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Libérer la mémoire
                window.URL.revokeObjectURL(url);
                
                showNotification('Bulletin généré et téléchargé avec succès !', 'success');
                
            } catch (error) {
                console.error('Erreur lors du traitement du fichier:', error);
                showNotification('Erreur lors du traitement du fichier téléchargé', 'error');
            }
        } else {
            showNotification(data.message || 'Erreur lors de la génération', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erreur:', error);
        showNotification('Erreur lors de la génération du bulletin: ' + error.message, 'error');
    });
}


        function showNotification(message, type = 'info') {
            const notification = document.getElementById('notification');
            const colors = {
                'success': 'bg-green-500',
                'error': 'bg-red-500',
                'warning': 'bg-yellow-500',
                'info': 'bg-blue-500'
            };
            
            notification.innerHTML = `
                <div class="${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle mr-2"></i>
                    ${message}
                    <button onclick="hideNotification()" class="ml-4 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            notification.classList.remove('hidden');
            
            setTimeout(() => {
                hideNotification();
            }, 5000);
        }

        function hideNotification() {
            document.getElementById('notification').classList.add('hidden');
        }

        function showLoading() {
            showNotification('Traitement en cours...', 'info');
        }

        function hideLoading() {
            hideNotification();
        }                            
        
        // Fermer la modal en cliquant à l'extérieur
        document.getElementById('employeeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>