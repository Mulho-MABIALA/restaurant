<?php
/**
 * Gestionnaire des primes variables
 */
class PrimesManager {
    private $conn;
    private $errors = [];
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Récupérer les erreurs
     */
    public function getErrors() {
        return $this->errors;
    }
    
    // ==============================================
    // GESTION DES TYPES DE PRIMES
    // ==============================================
    
    /**
     * Récupérer tous les types de primes
     */
    public function getTypesPrimes() {
        try {
            $stmt = $this->conn->query("
                SELECT * FROM types_primes 
                WHERE actif = 1 
                ORDER BY nom
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement des types de primes: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Créer un nouveau type de prime
     */
    public function creerTypePrime($data) {
        try {
            if (!$this->validerTypePrime($data)) {
                return false;
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO types_primes 
                (nom, code, type_calcul, montant_fixe, pourcentage, base_calcul, 
                 automatique, soumis_cotisations, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['nom'],
                $data['code'],
                $data['type_calcul'],
                $data['montant_fixe'] ?? 0,
                $data['pourcentage'] ?? 0,
                $data['base_calcul'] ?? 'salaire_base',
                $data['automatique'] ?? 0,
                $data['soumis_cotisations'] ?? 1,
                $data['description'] ?? null
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'id_type_prime' => $this->conn->lastInsertId()
                ];
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors de la création du type de prime: " . $e->getMessage();
            return false;
        }
    }
    
    // ==============================================
    // GESTION DES PRIMES EMPLOYÉS
    // ==============================================
    
    /**
     * Attribuer une prime à un employé
     */
    public function attribuerPrime($data) {
        try {
            if (!$this->validerAttributionPrime($data)) {
                return false;
            }
            
            // Vérifier si une prime existe déjà pour cette période
            $stmt = $this->conn->prepare("
                SELECT id FROM primes_employes 
                WHERE id_employe = ? AND id_type_prime = ? AND mois = ? AND annee = ?
            ");
            $stmt->execute([
                $data['id_employe'],
                $data['id_type_prime'],
                $data['mois'],
                $data['annee']
            ]);
            
            if ($stmt->fetch()) {
                $this->errors[] = "Une prime de ce type existe déjà pour cette période";
                return false;
            }
            
            // Calculer le montant de la prime
            $montant = $this->calculerMontantPrime($data);
            if ($montant === false) {
                return false;
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO primes_employes 
                (id_employe, id_type_prime, mois, annee, montant, criteres_performance, 
                 note_performance, commentaire, valide)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            
            $result = $stmt->execute([
                $data['id_employe'],
                $data['id_type_prime'],
                $data['mois'],
                $data['annee'],
                $montant,
                $data['criteres_performance'] ?? null,
                $data['note_performance'] ?? null,
                $data['commentaire'] ?? null
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'id_prime' => $this->conn->lastInsertId(),
                    'montant' => $montant
                ];
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors de l'attribution de la prime: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Valider une prime
     */
    public function validerPrime($id_prime, $validateur_id, $commentaire = null) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE primes_employes 
                SET valide = 1, valide_par = ?, date_validation = NOW(), commentaire = ?
                WHERE id = ? AND valide = 0
            ");
            
            $result = $stmt->execute([$validateur_id, $commentaire, $id_prime]);
            
            if ($stmt->rowCount() === 0) {
                $this->errors[] = "Prime non trouvée ou déjà validée";
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors de la validation: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Récupérer les primes d'un employé
     */
    public function getPrimesEmploye($id_employe, $mois = null, $annee = null) {
        try {
            $where = ["pe.id_employe = ?"];
            $params = [$id_employe];
            
            if ($mois !== null) {
                $where[] = "pe.mois = ?";
                $params[] = $mois;
            }
            
            if ($annee !== null) {
                $where[] = "pe.annee = ?";
                $params[] = $annee;
            }
            
            $sql = "
                SELECT 
                    pe.*,
                    tp.nom as type_prime_nom,
                    tp.code,
                    tp.soumis_cotisations,
                    CONCAT(v.nom, ' ', v.prenom) as validateur_nom
                FROM primes_employes pe
                JOIN types_primes tp ON pe.id_type_prime = tp.id
                LEFT JOIN employes v ON pe.valide_par = v.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY pe.annee DESC, pe.mois DESC, tp.nom
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement des primes: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Récupérer toutes les primes en attente de validation
     */
    public function getPrimesEnAttente($filters = []) {
        try {
            $where = ["pe.valide = 0"];
            $params = [];
            
            if (!empty($filters['mois'])) {
                $where[] = "pe.mois = ?";
                $params[] = $filters['mois'];
            }
            
            if (!empty($filters['annee'])) {
                $where[] = "pe.annee = ?";
                $params[] = $filters['annee'];
            }
            
            if (!empty($filters['type_prime'])) {
                $where[] = "pe.id_type_prime = ?";
                $params[] = $filters['type_prime'];
            }
            
            $sql = "
                SELECT 
                    pe.*,
                    CONCAT(e.nom, ' ', e.prenom) as employe_nom,
                    e.salaire_base,
                    tp.nom as type_prime_nom,
                    tp.code,
                    tp.type_calcul
                FROM primes_employes pe
                JOIN employes e ON pe.id_employe = e.id
                JOIN types_primes tp ON pe.id_type_prime = tp.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY pe.created_at DESC
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement des primes en attente: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Générer automatiquement les primes de présence pour un mois
     */
    public function genererPrimesPresence($mois, $annee) {
        try {
            $this->conn->beginTransaction();
            
            // Récupérer le type de prime de présence
            $stmt = $this->conn->prepare("SELECT * FROM types_primes WHERE code = 'PRES' AND automatique = 1");
            $stmt->execute();
            $type_prime = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$type_prime) {
                throw new Exception("Type de prime de présence non trouvé");
            }
            
            // Récupérer tous les employés actifs
            $stmt = $this->conn->query("SELECT id FROM employes WHERE statut = 'actif'");
            $employes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $primes_generees = 0;
            
            foreach ($employes as $id_employe) {
                // Vérifier si l'employé a des absences injustifiées ce mois
                $stmt = $this->conn->prepare("
                    SELECT COUNT(*) FROM absences a
                    JOIN types_absences ta ON a.id_type_absence = ta.id
                    WHERE a.id_employe = ? 
                    AND MONTH(a.date_absence) = ? 
                    AND YEAR(a.date_absence) = ?
                    AND ta.code = 'AI'
                    AND a.statut = 'confirme'
                ");
                $stmt->execute([$id_employe, $mois, $annee]);
                $nb_absences = $stmt->fetchColumn();
                
                // Si pas d'absence injustifiée, attribuer la prime
                if ($nb_absences == 0) {
                    $data = [
                        'id_employe' => $id_employe,
                        'id_type_prime' => $type_prime['id'],
                        'mois' => $mois,
                        'annee' => $annee,
                        'montant_fixe' => $type_prime['montant_fixe']
                    ];
                    
                    if ($this->attribuerPrime($data)) {
                        $primes_generees++;
                    }
                }
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'primes_generees' => $primes_generees
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->errors[] = "Erreur lors de la génération automatique: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Calculer les primes pour la paie
     */
    public function calculerPrimesForPayroll($id_employe, $mois, $annee) {
        try {
            $primes = $this->getPrimesEmploye($id_employe, $mois, $annee);
            
            $result = [
                'total' => 0,
                'total_soumis_cotisations' => 0,
                'total_non_soumis' => 0,
                'elements' => []
            ];
            
            foreach ($primes as $prime) {
                if ($prime['valide']) {
                    $result['total'] += $prime['montant'];
                    
                    if ($prime['soumis_cotisations']) {
                        $result['total_soumis_cotisations'] += $prime['montant'];
                    } else {
                        $result['total_non_soumis'] += $prime['montant'];
                    }
                    
                    $result['elements'][] = [
                        'nom' => $prime['type_prime_nom'],
                        'code' => $prime['code'],
                        'montant' => $prime['montant'],
                        'soumis_cotisations' => $prime['soumis_cotisations']
                    ];
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du calcul des primes: " . $e->getMessage();
            return false;
        }
    }
    
    // ==============================================
    // GESTION DES CRITÈRES DE PERFORMANCE
    // ==============================================
    
    /**
     * Créer des critères de performance pour une prime
     */
    public function creerCriteresPerformance($id_type_prime, $criteres) {
        try {
            $this->conn->beginTransaction();
            
            // Supprimer les anciens critères
            $stmt = $this->conn->prepare("DELETE FROM criteres_performance WHERE id_type_prime = ?");
            $stmt->execute([$id_type_prime]);
            
            // Ajouter les nouveaux critères
            $stmt = $this->conn->prepare("
                INSERT INTO criteres_performance 
                (id_type_prime, nom, description, poids, note_min, note_max)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($criteres as $critere) {
                $stmt->execute([
                    $id_type_prime,
                    $critere['nom'],
                    $critere['description'] ?? null,
                    $critere['poids'] ?? 1.0,
                    $critere['note_min'] ?? 0,
                    $critere['note_max'] ?? 20
                ]);
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->errors[] = "Erreur lors de la création des critères: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Récupérer les critères de performance d'une prime
     */
    public function getCriteresPerformance($id_type_prime) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM criteres_performance 
                WHERE id_type_prime = ? AND actif = 1
                ORDER BY nom
            ");
            $stmt->execute([$id_type_prime]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement des critères: " . $e->getMessage();
            return false;
        }
    }
    
    // ==============================================
    // MÉTHODES PRIVÉES
    // ==============================================
    
    private function validerTypePrime($data) {
        if (empty($data['nom']) || empty($data['code'])) {
            $this->errors[] = "Nom et code requis";
            return false;
        }
        
        if (!in_array($data['type_calcul'], ['fixe', 'pourcentage', 'variable'])) {
            $this->errors[] = "Type de calcul invalide";
            return false;
        }
        
        return true;
    }
    
    private function validerAttributionPrime($data) {
        $required = ['id_employe', 'id_type_prime', 'mois', 'annee'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->errors[] = "Champ requis manquant: $field";
                return false;
            }
        }
        
        if ($data['mois'] < 1 || $data['mois'] > 12) {
            $this->errors[] = "Mois invalide";
            return false;
        }
        
        return true;
    }
    
    private function calculerMontantPrime($data) {
        try {
            // Récupérer le type de prime
            $stmt = $this->conn->prepare("SELECT * FROM types_primes WHERE id = ?");
            $stmt->execute([$data['id_type_prime']]);
            $type_prime = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$type_prime) {
                $this->errors[] = "Type de prime non trouvé";
                return false;
            }
            
            switch ($type_prime['type_calcul']) {
                case 'fixe':
                    return $data['montant_fixe'] ?? $type_prime['montant_fixe'];
                    
                case 'pourcentage':
                    $base = $this->calculerBaseCalcul($data['id_employe'], $type_prime['base_calcul']);
                    return $base * ($type_prime['pourcentage'] / 100);
                    
                case 'variable':
                    if (!isset($data['montant_variable'])) {
                        $this->errors[] = "Montant variable requis";
                        return false;
                    }
                    return $data['montant_variable'];
                    
                default:
                    $this->errors[] = "Type de calcul non supporté";
                    return false;
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du calcul du montant: " . $e->getMessage();
            return false;
        }
    }
    
    private function calculerBaseCalcul($id_employe, $base_calcul) {
        try {
            switch ($base_calcul) {
                case 'salaire_base':
                    $stmt = $this->conn->prepare("SELECT salaire_base FROM employes WHERE id = ?");
                    $stmt->execute([$id_employe]);
                    return $stmt->fetchColumn() ?: 0;
                    
                case 'salaire_brut':
                    // TODO: Calculer le salaire brut du mois précédent
                    return 0;
                    
                case 'heures_travaillees':
                    // TODO: Calculer les heures travaillées du mois
                    return 0;
                    
                default:
                    return 0;
            }
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Statistiques des primes
     */
    public function getStatistiquesPrimes($mois = null, $annee = null) {
        try {
            $mois = $mois ?: date('n');
            $annee = $annee ?: date('Y');
            
            $stats = [];
            
            // Total par type de prime
            $stmt = $this->conn->prepare("
                SELECT 
                    tp.nom as type_prime,
                    COUNT(pe.id) as nombre_attributions,
                    SUM(pe.montant) as montant_total,
                    AVG(pe.montant) as montant_moyen
                FROM types_primes tp
                LEFT JOIN primes_employes pe ON tp.id = pe.id_type_prime 
                    AND pe.mois = ? AND pe.annee = ? AND pe.valide = 1
                WHERE tp.actif = 1
                GROUP BY tp.id, tp.nom
                ORDER BY montant_total DESC
            ");
            $stmt->execute([$mois, $annee]);
            $stats['par_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Primes en attente de validation
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as nb_en_attente, SUM(montant) as montant_en_attente
                FROM primes_employes 
                WHERE mois = ? AND annee = ? AND valide = 0
            ");
            $stmt->execute([$mois, $annee]);
            $stats['en_attente'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $stats;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du calcul des statistiques: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Supprimer une prime non validée
     */
    public function supprimerPrime($id_prime) {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM primes_employes 
                WHERE id = ? AND valide = 0
            ");
            
            $result = $stmt->execute([$id_prime]);
            
            if ($stmt->rowCount() === 0) {
                $this->errors[] = "Prime non trouvée ou déjà validée";
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors de la suppression: " . $e->getMessage();
            return false;
        }
    }
}