<?php
class PaieManager {
    private $conn;
    private $employeesManager;
    private $presenceManager;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
        $this->employeesManager = new EmployeesManager($connection);
        $this->presenceManager = new PresenceManager($connection);
    }
    
    public function calculatePayroll($employeeId, $month, $year, $options = []) {
        try {
            $this->conn->beginTransaction();
            
            // Récupérer données employé
            $employee = $this->employeesManager->getEmployeePayrollData($employeeId, "$year-$month");
            if (!$employee) {
                throw new Exception("Employé non trouvé");
            }
            
            // Période de calcul
            $startDate = "$year-$month-01";
            $endDate = date('Y-m-t', strtotime($startDate));
            
            // Données de présence
            $presenceStats = $this->presenceManager->getPresenceStatistics($employeeId, $startDate, $endDate);
            
            // Calculs de base
            $salaireBase = (float) ($employee['salaire_effectif'] ?? 0);
            $tauxHoraire = $salaireBase / 173.33; // Heures standard par mois
            
            // Heures supplémentaires
            $heuresSupplementaires = $options['heures_supplementaires'] ?? $presenceStats['overtime_hours'];
            $montantHeuresSupp = $heuresSupplementaires * $tauxHoraire * 1.5;
            
            // Jours d'absence
            $joursAbsence = $options['jours_absences'] ?? $presenceStats['absence_days'];
            $deductionAbsences = ($salaireBase / 22) * $joursAbsence; // 22 jours ouvrables moyens
            
            // Primes
            $primes = $this->calculatePrimes($employeeId, $month, $year);
            $totalPrimes = array_sum(array_column($primes, 'montant'));
            
            // Avances
            $avances = $this->getAvancesEnCours($employeeId, $month, $year);
            $totalAvances = array_sum(array_column($avances, 'montant'));
            
            // Salaire brut
            $salaireBrut = $salaireBase + $montantHeuresSupp + $totalPrimes - $deductionAbsences;
            
            // Cotisations sociales
            $tauxCotisation = (float) ($employee['taux_cotisation'] ?? 20) / 100;
            $cotisationsSociales = $salaireBrut * $tauxCotisation;
            
            // Salaire net
            $salaireNet = $salaireBrut - $cotisationsSociales - $totalAvances;
            
            $calculData = [
                'employe_id' => $employeeId,
                'mois' => $month,
                'annee' => $year,
                'salaire_base' => $salaireBase,
                'heures_supplementaires' => $heuresSupplementaires,
                'montant_heures_supp' => $montantHeuresSupp,
                'jours_absence' => $joursAbsence,
                'deduction_absences' => $deductionAbsences,
                'total_primes' => $totalPrimes,
                'total_avances' => $totalAvances,
                'salaire_brut' => $salaireBrut,
                'cotisations_sociales' => $cotisationsSociales,
                'salaire_net' => $salaireNet,
                'taux_cotisation' => $tauxCotisation * 100,
                'presence_stats' => $presenceStats,
                'primes_detail' => $primes,
                'avances_detail' => $avances
            ];
            
            $this->conn->commit();
            return $calculData;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Erreur calculatePayroll: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function generateBulletin($calculData) {
    try {
        $this->conn->beginTransaction();
        
        // Vérifier si un bulletin existe déjà
        $stmt = $this->conn->prepare("
            SELECT id_bulletin FROM bulletins_paie 
            WHERE id_employe = ? AND mois = ? AND annee = ?
        ");
        $stmt->execute([$calculData['employe_id'], $calculData['mois'], $calculData['annee']]);
        
        if ($stmt->fetch()) {
            throw new Exception("Un bulletin existe déjà pour cette période");
        }
        
        // Insérer le nouveau bulletin avec les bonnes colonnes
        $stmt = $this->conn->prepare("
            INSERT INTO bulletins_paie (
                id_employe, mois, annee, salaire_base, total_primes,
                total_retenues, total_cotisations, salaire_brut, salaire_net, 
                heures_travaillees, heures_supplementaires, jours_conges, 
                jours_absences, montant_absences, montant_avances_remboursees, 
                total_primes_variables, statut, date_creation
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'brouillon', NOW())
        ");
        
        $stmt->execute([
            $calculData['employe_id'],
            $calculData['mois'], 
            $calculData['annee'],
            $calculData['salaire_base'],
            $calculData['total_primes'],
            $calculData['total_avances'], // total_retenues
            $calculData['cotisations_sociales'], // total_cotisations
            $calculData['salaire_brut'],
            $calculData['salaire_net'],
            $calculData['presence_stats']['hours_worked'] ?? 0, // heures_travaillees
            $calculData['heures_supplementaires'],
            0, // jours_conges
            $calculData['jours_absence'],
            $calculData['deduction_absences'], // montant_absences
            $calculData['total_avances'], // montant_avances_remboursees
            0 // total_primes_variables
        ]);
        
        $bulletinId = $this->conn->lastInsertId();
        
        // Logger l'action
        $this->logPayrollAction('GENERATE_BULLETIN', [
            'bulletin_id' => $bulletinId,
            'employe_id' => $calculData['employe_id'],
            'periode' => $calculData['mois'] . '/' . $calculData['annee'],
            'salaire_net' => $calculData['salaire_net']
        ]);
        
        $this->conn->commit();
        return $bulletinId;
        
    } catch (Exception $e) {
        $this->conn->rollBack();
        error_log("Erreur generateBulletin: " . $e->getMessage());
        throw $e;
    }
}
   public function getBulletinDetails($bulletinId) {
    try {
        $stmt = $this->conn->prepare("
            SELECT 
                bp.id_bulletin as id,
                bp.*,
                CONCAT(e.prenom, ' ', e.nom) as employe_nom,
                e.nom, 
                e.prenom, 
                e.email as employe_email,
                p.nom as poste_nom,
                p.nom as nom_poste, 
                d.nom as departement_nom
            FROM bulletins_paie bp
            INNER JOIN employes e ON bp.id_employe = e.id
            LEFT JOIN postes p ON e.poste_id = p.id
            LEFT JOIN departements d ON p.departement_id = d.id
            WHERE bp.id_bulletin = ?
        ");
        
        $stmt->execute([$bulletinId]);
        $bulletin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bulletin) {
            throw new Exception("Bulletin non trouvé");
        }
        
        return $bulletin;
        
    } catch (PDOException $e) {
        error_log("Erreur getBulletinDetails: " . $e->getMessage());
        throw new Exception("Erreur lors de la récupération du bulletin");
    }
}

    private function calculatePrimes($employeeId, $month, $year) {
        try {
            $stmt = $this->conn->prepare("
                SELECT pe.*, tp.nom as type_nom
                FROM primes_employes pe
                LEFT JOIN types_primes tp ON pe.type_prime_id = tp.id
                WHERE pe.employe_id = ? AND pe.mois = ? AND pe.annee = ? AND pe.valide = 1
            ");
            $stmt->execute([$employeeId, $month, $year]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur calculatePrimes: " . $e->getMessage());
            return [];
        }
    }
    
    private function getAvancesEnCours($employeeId, $month, $year) {
        try {
            $stmt = $this->conn->prepare("
                SELECT *
                FROM avances_salaire
                WHERE employe_id = ? 
                AND statut = 'approuve'
                AND (
                    (mode_remboursement = 'UNIQUE' AND YEAR(date_remboursement) = ? AND MONTH(date_remboursement) = ?)
                    OR (mode_remboursement = 'ECHELONNE' AND date_debut_remboursement <= ? AND date_fin_remboursement >= ?)
                )
            ");
            
            $dateRemboursement = "$year-$month-01";
            $stmt->execute([$employeeId, $year, $month, $dateRemboursement, $dateRemboursement]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur getAvancesEnCours: " . $e->getMessage());
            return [];
        }
    }
    
    public function getPayrollStatistics($filters = []) {
        try {
            $stats = [];
            
            // Masse salariale du mois
            $mois = $filters['mois'] ?? date('n');
            $annee = $filters['annee'] ?? date('Y');
            
            $stmt = $this->conn->prepare("
                SELECT 
                    COUNT(*) as nb_bulletins,
                    SUM(salaire_net) as masse_salariale,
                    AVG(salaire_net) as salaire_moyen,
                    SUM(total_primes) as total_primes,
                    SUM(total_avances) as total_avances,
                    SUM(heures_supplementaires) as total_heures_supp
                FROM bulletins_paie
                WHERE mois = ? AND annee = ? AND statut != 'brouillon'
            ");
            $stmt->execute([$mois, $annee]);
            $stats['paie'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Statistiques employés
            $stats['employes'] = $this->employeesManager->getEmployeeStatistics();
            
            // Évolution masse salariale
            $stmt = $this->conn->prepare("
                SELECT mois, SUM(salaire_net) as masse
                FROM bulletins_paie
                WHERE annee = ? AND statut != 'brouillon'
                GROUP BY mois
                ORDER BY mois
            ");
            $stmt->execute([$annee]);
            $stats['evolution_mensuelle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Erreur getPayrollStatistics: " . $e->getMessage());
            return [];
        }
    }
    
   public function getBulletins($filters = []) {
    try {
        $sql = "
            SELECT 
                bp.id_bulletin as id,
                bp.id_employe as employe_id,
                bp.mois, 
                bp.annee,
                bp.salaire_base,
                bp.total_primes,
                bp.total_retenues as total_avances,
                bp.salaire_brut,
                bp.salaire_net,
                bp.heures_supplementaires,
                bp.jours_absences,
                bp.statut,
                bp.date_creation,
                e.nom, 
                e.prenom, 
                e.email, 
                p.nom as poste_nom
            FROM bulletins_paie bp
            INNER JOIN employes e ON bp.id_employe = e.id
            LEFT JOIN postes p ON e.poste_id = p.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($filters['mois'])) {
            $sql .= " AND bp.mois = ?";
            $params[] = $filters['mois'];
        }
        
        if (!empty($filters['annee'])) {
            $sql .= " AND bp.annee = ?";
            $params[] = $filters['annee'];
        }
        
        if (!empty($filters['statut'])) {
            $sql .= " AND bp.statut = ?";
            $params[] = $filters['statut'];
        }
        
        if (!empty($filters['employe_id'])) {
            $sql .= " AND bp.id_employe = ?";
            $params[] = $filters['employe_id'];
        }
        
        $sql .= " ORDER BY bp.date_creation DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log pour debug
        error_log("Bulletins trouvés : " . count($results));
        
        return $results;
        
    } catch (PDOException $e) {
        error_log("Erreur getBulletins: " . $e->getMessage());
        return [];
    }
}
    
    private function logPayrollAction($action, $data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details, created_at)
                VALUES (?, 'bulletins_paie', ?, ?, NOW())
            ");
            $stmt->execute([
                $action,
                $data['bulletin_id'] ?? 0,
                json_encode($data)
            ]);
        } catch (PDOException $e) {
            error_log("Erreur logPayrollAction: " . $e->getMessage());
        }
    }
}
?>