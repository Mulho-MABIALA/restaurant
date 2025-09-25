<?php
class EmployeesManager {
    private $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    public function getAllEmployees($filters = []) {
        $sql = "
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
                p.heures_travail as heures_par_mois,
                ps.nom as poste_superieur_nom,
                d.nom as departement_nom,
                d.couleur as departement_couleur,
                d.id as departement_id
            FROM employes e
            LEFT JOIN postes p ON e.poste_id = p.id
            LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
            LEFT JOIN departements d ON p.departement_id = d.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($filters['statut'])) {
            $sql .= " AND e.statut = ?";
            $params[] = $filters['statut'];
        }
        
        if (!empty($filters['departement_id'])) {
            $sql .= " AND d.id = ?";
            $params[] = $filters['departement_id'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.email LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY e.statut DESC, e.nom, e.prenom";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur getAllEmployees: " . $e->getMessage());
            return [];
        }
    }
    
    public function getEmployeeById($id) {
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
                    p.heures_travail as heures_par_mois,
                    d.nom as departement_nom
                FROM employes e
                LEFT JOIN postes p ON e.poste_id = p.id
                LEFT JOIN departements d ON p.departement_id = d.id
                WHERE e.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur getEmployeeById: " . $e->getMessage());
            return null;
        }
    }
    
    public function getEmployeeStatistics() {
        $stats = [];
        
        try {
            // Total employés actifs
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes WHERE statut = 'actif'");
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            $stats['total_actifs'] = $result ? (int) $result['total'] : 0;
            
            // Présents aujourd'hui
            $stmt = $this->conn->query("
                SELECT COUNT(DISTINCT p.employe_id) as presents
                FROM presences p
                INNER JOIN employes e ON p.employe_id = e.id
                WHERE DATE(p.heure_arrivee) = CURDATE()
                AND p.heure_arrivee IS NOT NULL
                AND e.statut = 'actif'
            ");
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            $stats['presents_aujourd_hui'] = $result ? (int) $result['presents'] : 0;
            
            // Absents aujourd'hui
            $stmt = $this->conn->query("
                SELECT COUNT(*) as absents
                FROM employes e
                LEFT JOIN presences p ON e.id = p.employe_id AND DATE(p.heure_arrivee) = CURDATE()
                WHERE e.statut = 'actif'
                AND p.employe_id IS NULL
            ");
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            $stats['absents_aujourd_hui'] = $result ? (int) $result['absents'] : 0;
            
            // Retards aujourd'hui
            $stmt = $this->conn->query("
                SELECT COUNT(*) as retards
                FROM presences p
                INNER JOIN employes e ON p.employe_id = e.id
                WHERE DATE(p.heure_arrivee) = CURDATE()
                AND TIME(p.heure_arrivee) > e.heure_debut
                AND e.statut = 'actif'
            ");
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            $stats['retards_aujourd_hui'] = $result ? (int) $result['retards'] : 0;
            
            // Total admins
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes WHERE is_admin = 1 AND statut = 'actif'");
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            $stats['total_admins'] = $result ? (int) $result['total'] : 0;
            
            // Total inactifs
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes WHERE statut = 'inactif'");
            $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            $stats['total_inactifs'] = $result ? (int) $result['total'] : 0;
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Erreur getEmployeeStatistics: " . $e->getMessage());
            return [
                'total_actifs' => 0,
                'presents_aujourd_hui' => 0,
                'absents_aujourd_hui' => 0,
                'retards_aujourd_hui' => 0,
                'total_admins' => 0,
                'total_inactifs' => 0
            ];
        }
    }
    
    public function getEmployeePayrollData($employeeId, $period) {
        try {
            $employee = $this->getEmployeeById($employeeId);
            if (!$employee) {
                return null;
            }
            
            // Ajouter données de paie spécifiques
            $employee['salaire_effectif'] = $employee['salaire'] ?: $employee['poste_salaire'];
            $employee['taux_horaire'] = $employee['salaire_effectif'] / ($employee['heures_par_mois'] ?: 173.33);
            
            return $employee;
        } catch (Exception $e) {
            error_log("Erreur getEmployeePayrollData: " . $e->getMessage());
            return null;
        }
    }
}
?>