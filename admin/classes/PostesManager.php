<?php
class PostesManager {
    private $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    public function getAllPostes($filters = []) {
        try {
            $sql = "
                SELECT p.*,
                    (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif') as nb_employes,
                    ps.nom as poste_superieur_nom,
                    nh.libelle as niveau_libelle,
                    d.nom as departement_nom,
                    d.responsable_nom as departement_responsable_nom,
                    d.responsable_prenom as departement_responsable_prenom
                FROM postes p
                LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
                LEFT JOIN niveaux_hierarchiques nh ON p.niveau_hierarchique = nh.niveau
                LEFT JOIN departements d ON p.departement_id = d.id
                WHERE p.actif = TRUE
            ";
            
            $params = [];
            
            if (!empty($filters['departement_id'])) {
                $sql .= " AND p.departement_id = ?";
                $params[] = $filters['departement_id'];
            }
            
            if (!empty($filters['type_contrat'])) {
                $sql .= " AND p.type_contrat = ?";
                $params[] = $filters['type_contrat'];
            }
            
            $sql .= " ORDER BY p.niveau_hierarchique, p.nom";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Erreur getAllPostes: " . $e->getMessage());
            return [];
        }
    }
    
    public function getPosteById($id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT p.*,
                    ps.nom as poste_superieur_nom,
                    nh.libelle as niveau_libelle,
                    d.nom as departement_nom
                FROM postes p
                LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
                LEFT JOIN niveaux_hierarchiques nh ON p.niveau_hierarchique = nh.niveau
                LEFT JOIN departements d ON p.departement_id = d.id
                WHERE p.id = ? AND p.actif = TRUE
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur getPosteById: " . $e->getMessage());
            return null;
        }
    }
    
    public function getContractTypes() {
        return [
            'CDI' => 'Contrat à Durée Indéterminée',
            'CDD' => 'Contrat à Durée Déterminée',
            'STAGE' => 'Stage',
            'APPRENTISSAGE' => 'Contrat d\'Apprentissage',
            'CONSULTANT' => 'Consultant',
            'SAISONNIER' => 'Contrat Saisonnier'
        ];
    }
    
    public function getDepartements() {
        try {
            $stmt = $this->conn->query("
                SELECT d.*, COUNT(p.id) as nb_postes
                FROM departements d
                LEFT JOIN postes p ON d.id = p.departement_id AND p.actif = TRUE
                WHERE d.actif = TRUE
                GROUP BY d.id
                ORDER BY d.nom
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur getDepartements: " . $e->getMessage());
            return [];
        }
    }
    
    public function getSalaryRanges($departementId = null) {
        try {
            $sql = "
                SELECT 
                    MIN(salaire) as salaire_min,
                    MAX(salaire) as salaire_max,
                    AVG(salaire) as salaire_moyen,
                    COUNT(*) as nb_postes
                FROM postes
                WHERE actif = TRUE AND salaire > 0
            ";
            
            $params = [];
            if ($departementId) {
                $sql .= " AND departement_id = ?";
                $params[] = $departementId;
            }
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur getSalaryRanges: " . $e->getMessage());
            return null;
        }
    }
    
    public function getPostesForPayroll() {
        try {
            $stmt = $this->conn->query("
                SELECT p.id, p.nom, p.salaire, p.type_contrat, p.taux_cotisation, p.heures_travail,
                    d.nom as departement_nom
                FROM postes p
                LEFT JOIN departements d ON p.departement_id = d.id
                WHERE p.actif = TRUE
                ORDER BY d.nom, p.nom
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur getPostesForPayroll: " . $e->getMessage());
            return [];
        }
    }
}

?>