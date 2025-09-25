<?php
/**
 * Gestionnaire des avances sur salaire
 */
class AvancesManager {
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
    // GESTION DES DEMANDES D'AVANCES
    // ==============================================
    
    /**
     * Créer une demande d'avance
     */
    public function creerDemandeAvance($data) {
        try {
            // Validation des données
          if (!$this->validerDonneesDemandeAvance($data)) {
                return false;
            }
            
            // Vérifier l'éligibilité de l'employé
            if (!$this->verifierEligibiliteAvance($data['id_employe'], $data['montant_demande'])) {
                return false;
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO avances_salaire 
                (id_employe, montant_demande, motif, nb_mensualites, demande_par, statut)
                VALUES (?, ?, ?, ?, ?, 'en_attente')
            ");
            
            $result = $stmt->execute([
                $data['id_employe'],
                $data['montant_demande'],
                $data['motif'],
                $data['nb_mensualites'] ?? 1,
                $data['demande_par']
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'id_avance' => $this->conn->lastInsertId()
                ];
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors de la création de la demande: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Valider ou refuser une demande d'avance
     */
    public function validerDemandeAvance($id_avance, $statut, $montant_accorde = null, $commentaire = null, $validateur_id = null) {
        try {
            if (!in_array($statut, ['approuve', 'refuse'])) {
                $this->errors[] = "Statut invalide";
                return false;
            }
            
            $this->conn->beginTransaction();
            
            // Récupérer les détails de la demande
            $stmt = $this->conn->prepare("
                SELECT * FROM avances_salaire 
                WHERE id = ? AND statut = 'en_attente'
            ");
            $stmt->execute([$id_avance]);
            $avance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$avance) {
                throw new Exception("Demande non trouvée ou déjà traitée");
            }
            
            // Si approuvé, valider le montant accordé
            if ($statut === 'approuve') {
                $montant_accorde = $montant_accorde ?: $avance['montant_demande'];
                
                // Vérifier à nouveau l'éligibilité avec le montant accordé
                if (!$this->verifierEligibiliteAvance($avance['id_employe'], $montant_accorde)) {
                    throw new Exception("Montant accordé trop élevé par rapport au salaire");
                }
                
                // Calculer la mensualité
                $mensualite = $montant_accorde / $avance['nb_mensualites'];
                
                $stmt = $this->conn->prepare("
                    UPDATE avances_salaire 
                    SET statut = ?, montant_accorde = ?, montant_mensualite = ?,
                        commentaire_validation = ?, valide_par = ?, date_validation = NOW()
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    $statut, $montant_accorde, $mensualite,
                    $commentaire, $validateur_id, $id_avance
                ]);
            } else {
                // Refus
                $stmt = $this->conn->prepare("
                    UPDATE avances_salaire 
                    SET statut = ?, commentaire_validation = ?, valide_par = ?, date_validation = NOW()
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([
                    $statut, $commentaire, $validateur_id, $id_avance
                ]);
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->errors[] = "Erreur lors de la validation: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Récupérer les demandes d'avances
     */
    public function getDemandesAvances($filters = []) {
        try {
            $where = ["1=1"];
            $params = [];
            
            if (!empty($filters['employe_id'])) {
                $where[] = "av.id_employe = ?";
                $params[] = $filters['employe_id'];
            }
            
            if (!empty($filters['statut'])) {
                $where[] = "av.statut = ?";
                $params[] = $filters['statut'];
            }
            
            if (!empty($filters['mois']) && !empty($filters['annee'])) {
                $where[] = "MONTH(av.date_demande) = ? AND YEAR(av.date_demande) = ?";
                $params[] = $filters['mois'];
                $params[] = $filters['annee'];
            }
            
            $sql = "
                SELECT 
                    av.*,
                    CONCAT(e.nom, ' ', e.prenom) as employe_nom,
                    e.salaire_base,
                    CONCAT(v.nom, ' ', v.prenom) as validateur_nom,
                    COALESCE(SUM(ra.montant_rembourse), 0) as montant_rembourse
                FROM avances_salaire av
                JOIN employes e ON av.id_employe = e.id
                LEFT JOIN employes v ON av.valide_par = v.id
                LEFT JOIN remboursements_avances ra ON av.id = ra.id_avance
                WHERE " . implode(' AND ', $where) . "
                GROUP BY av.id
                ORDER BY av.date_demande DESC
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $avances = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculer le solde restant pour chaque avance
            foreach ($avances as &$avance) {
                $avance['solde_restant'] = $avance['montant_accorde'] - $avance['montant_rembourse'];
                $avance['pourcentage_rembourse'] = $avance['montant_accorde'] > 0 ? 
                    ($avance['montant_rembourse'] / $avance['montant_accorde']) * 100 : 0;
            }
            
            return $avances;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement des avances: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Récupérer les avances en cours pour un employé
     */
    public function getAvancesEnCours($id_employe) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    av.*,
                    COALESCE(SUM(ra.montant_rembourse), 0) as montant_rembourse,
                    (av.montant_accorde - COALESCE(SUM(ra.montant_rembourse), 0)) as solde_restant
                FROM avances_salaire av
                LEFT JOIN remboursements_avances ra ON av.id = ra.id_avance
                WHERE av.id_employe = ? 
                    AND av.statut = 'approuve' 
                    AND av.mensualite_actuelle < av.nb_mensualites
                GROUP BY av.id
                HAVING solde_restant > 0
                ORDER BY av.date_validation
            ");
            
            $stmt->execute([$id_employe]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement des avances en cours: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Calculer le montant total à déduire pour un employé ce mois
     */
    public function calculerDeductionMensuelle($id_employe) {
        try {
            $avances_en_cours = $this->getAvancesEnCours($id_employe);
            $total_deduction = 0;
            
            foreach ($avances_en_cours as $avance) {
                $total_deduction += $avance['montant_mensualite'];
            }
            
            return $total_deduction;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du calcul des déductions: " . $e->getMessage();
            return 0;
        }
    }
    
    /**
     * Enregistrer un remboursement d'avance (appelé lors de la génération du bulletin)
     */
    public function enregistrerRemboursement($id_avance, $id_bulletin, $montant_rembourse) {
        try {
            $this->conn->beginTransaction();
            
            // Récupérer les détails de l'avance
            $stmt = $this->conn->prepare("SELECT * FROM avances_salaire WHERE id = ?");
            $stmt->execute([$id_avance]);
            $avance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$avance) {
                throw new Exception("Avance non trouvée");
            }
            
            // Enregistrer le remboursement
            $stmt = $this->conn->prepare("
                INSERT INTO remboursements_avances 
                (id_avance, id_bulletin, montant_rembourse, numero_mensualite)
                VALUES (?, ?, ?, ?)
            ");
            
            $numero_mensualite = $avance['mensualite_actuelle'] + 1;
            
            $stmt->execute([
                $id_avance,
                $id_bulletin,
                $montant_rembourse,
                $numero_mensualite
            ]);
            
            // Mettre à jour l'avance
            $stmt = $this->conn->prepare("
                UPDATE avances_salaire 
                SET mensualite_actuelle = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$numero_mensualite, $id_avance]);
            
            // Si c'est la dernière mensualité, marquer comme remboursée
            if ($numero_mensualite >= $avance['nb_mensualites']) {
                $stmt = $this->conn->prepare("
                    UPDATE avances_salaire 
                    SET statut = 'rembourse', date_remboursement_complete = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$id_avance]);
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->errors[] = "Erreur lors de l'enregistrement du remboursement: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Traiter les remboursements automatiques pour un bulletin
     */
    public function traiterRemboursementsAutomatiques($id_employe, $id_bulletin) {
        try {
            $avances_en_cours = $this->getAvancesEnCours($id_employe);
            $total_rembourse = 0;
            
            foreach ($avances_en_cours as $avance) {
                if ($this->enregistrerRemboursement($avance['id'], $id_bulletin, $avance['montant_mensualite'])) {
                    $total_rembourse += $avance['montant_mensualite'];
                }
            }
            
            return $total_rembourse;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du traitement automatique: " . $e->getMessage();
            return 0;
        }
    }
    
    // ==============================================
    // MÉTHODES PRIVÉES
    // ==============================================
    
   private function validerDonneesDemandeAvance($data) {
        if (empty($data['id_employe']) || empty($data['montant_demande']) || empty($data['motif'])) {
            $this->errors[] = "Données manquantes";
            return false;
        }
        
        if ($data['montant_demande'] <= 0) {
            $this->errors[] = "Le montant doit être positif";
            return false;
        }
        
        if (isset($data['nb_mensualites']) && ($data['nb_mensualites'] < 1 || $data['nb_mensualites'] > 12)) {
            $this->errors[] = "Le nombre de mensualités doit être entre 1 et 12";
            return false;
        }
        
        return true;
    }
    
    private function verifierEligibiliteAvance($id_employe, $montant_demande) {
        try {
            // Récupérer le salaire de l'employé
            $stmt = $this->conn->prepare("SELECT salaire_base FROM employes WHERE id = ? AND statut = 'actif'");
            $stmt->execute([$id_employe]);
            $salaire_base = $stmt->fetchColumn();
            
            if (!$salaire_base) {
                $this->errors[] = "Employé non trouvé ou inactif";
                return false;
            }
            
            // Vérifier que l'avance ne dépasse pas 80% du salaire
            $limite_avance = $salaire_base * 0.8;
            if ($montant_demande > $limite_avance) {
                $this->errors[] = "Le montant demandé dépasse 80% du salaire de base (" . number_format($limite_avance, 0, ',', ' ') . " FCFA)";
                return false;
            }
            
            // Vérifier qu'il n'y a pas déjà trop d'avances en cours
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) FROM avances_salaire 
                WHERE id_employe = ? AND statut IN ('en_attente', 'approuve') 
                AND (statut = 'en_attente' OR mensualite_actuelle < nb_mensualites)
            ");
            $stmt->execute([$id_employe]);
            $nb_avances_actives = $stmt->fetchColumn();
            
            if ($nb_avances_actives >= 2) {
                $this->errors[] = "L'employé a déjà le maximum d'avances autorisées";
                return false;
            }
            
            // Vérifier le montant total des avances en cours
            $avances_en_cours = $this->getAvancesEnCours($id_employe);
            $total_avances = 0;
            foreach ($avances_en_cours as $avance) {
                $total_avances += $avance['solde_restant'];
            }
            
            if (($total_avances + $montant_demande) > $limite_avance) {
                $this->errors[] = "Le total des avances dépasserait la limite autorisée";
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors de la vérification d'éligibilité: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Statistiques des avances
     */
    public function getStatistiquesAvances($mois = null, $annee = null) {
        try {
            $mois = $mois ?: date('n');
            $annee = $annee ?: date('Y');
            
            $stats = [];
            
            // Nombre de demandes par statut
            $stmt = $this->conn->prepare("
                SELECT 
                    statut,
                    COUNT(*) as nombre,
                    SUM(montant_demande) as montant_total
                FROM avances_salaire 
                WHERE MONTH(date_demande) = ? AND YEAR(date_demande) = ?
                GROUP BY statut
            ");
            $stmt->execute([$mois, $annee]);
            $stats['par_statut'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Montant total des avances en cours
            $stmt = $this->conn->prepare("
                SELECT 
                    COUNT(*) as nb_avances_actives,
                    SUM(av.montant_accorde - COALESCE(SUM(ra.montant_rembourse), 0)) as montant_en_cours
                FROM avances_salaire av
                LEFT JOIN remboursements_avances ra ON av.id = ra.id_avance
                WHERE av.statut = 'approuve' AND av.mensualite_actuelle < av.nb_mensualites
                GROUP BY av.id
            ");
            $stmt->execute();
            $stats['en_cours'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['nb_avances_actives' => 0, 'montant_en_cours' => 0];
            
            return $stats;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du calcul des statistiques: " . $e->getMessage();
            return false;
        }
    }
}