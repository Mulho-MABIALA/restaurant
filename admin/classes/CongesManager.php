<?php
/**
 * Gestionnaire des congés et absences
 */
class CongesManager {
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
    // GESTION DES TYPES DE CONGÉS
    // ==============================================
    
    /**
     * Récupérer tous les types de congés actifs
     */
    public function getTypesConges() {
        try {
            $stmt = $this->conn->query("
                SELECT * FROM types_conges 
                WHERE actif = 1 
                ORDER BY nom
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement des types de congés: " . $e->getMessage();
            return false;
        }
    }
    
    // ==============================================
    // GESTION DES DEMANDES DE CONGÉS
    // ==============================================
    
    /**
     * Créer une demande de congé
     */
    public function creerDemandeConge($data) {
        try {
            // Validation des données
            if (!$this->validerDonneesDemandeConge($data)) {
                return false;
            }
            
            // Vérifier les conflits de dates
            if (!$this->verifierDisponibilite($data['id_employe'], $data['date_debut'], $data['date_fin'])) {
                $this->errors[] = "Conflit de dates avec une demande existante";
                return false;
            }
            
            // Calculer le nombre de jours
            $nb_jours = $this->calculerNombreJours($data['date_debut'], $data['date_fin']);
            
            // Vérifier le solde disponible
            if (!$this->verifierSoldeDisponible($data['id_employe'], $data['id_type_conge'], $nb_jours)) {
                $this->errors[] = "Solde de congés insuffisant";
                return false;
            }
            
            $this->conn->beginTransaction();
            
            // Insérer la demande
            $stmt = $this->conn->prepare("
                INSERT INTO conges_employes 
                (id_employe, id_type_conge, date_debut, date_fin, nb_jours_demandes, 
                 motif, demande_par, statut) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')
            ");
            
            $result = $stmt->execute([
                $data['id_employe'],
                $data['id_type_conge'],
                $data['date_debut'],
                $data['date_fin'],
                $nb_jours,
                $data['motif'] ?? null,
                $data['demande_par']
            ]);
            
            $id_conge = $this->conn->lastInsertId();
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'id_conge' => $id_conge,
                'nb_jours' => $nb_jours
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->errors[] = "Erreur lors de la création de la demande: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Valider/Refuser une demande de congé
     */
    public function validerDemandeConge($id_conge, $statut, $commentaire = null, $validateur_id = null) {
        try {
            if (!in_array($statut, ['approuve', 'refuse'])) {
                $this->errors[] = "Statut invalide";
                return false;
            }
            
            $this->conn->beginTransaction();
            
            // Mettre à jour la demande
            $stmt = $this->conn->prepare("
                UPDATE conges_employes 
                SET statut = ?, commentaire_validation = ?, valide_par = ?, date_validation = NOW()
                WHERE id = ? AND statut = 'en_attente'
            ");
            
            $result = $stmt->execute([$statut, $commentaire, $validateur_id, $id_conge]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Demande non trouvée ou déjà traitée");
            }
            
            // Si approuvé, créer le planning et mettre à jour les soldes
            if ($statut === 'approuve') {
                $this->creerPlanningConge($id_conge);
                $this->mettreAJourSoldesConges($id_conge);
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
     * Récupérer les demandes de congés
     */
    public function getDemandesConges($filters = []) {
        try {
            $where = ["1=1"];
            $params = [];
            
            if (!empty($filters['employe_id'])) {
                $where[] = "ce.id_employe = ?";
                $params[] = $filters['employe_id'];
            }
            
            if (!empty($filters['statut'])) {
                $where[] = "ce.statut = ?";
                $params[] = $filters['statut'];
            }
            
            if (!empty($filters['mois']) && !empty($filters['annee'])) {
                $where[] = "(MONTH(ce.date_debut) = ? OR MONTH(ce.date_fin) = ?) AND (YEAR(ce.date_debut) = ? OR YEAR(ce.date_fin) = ?)";
                $params[] = $filters['mois'];
                $params[] = $filters['mois'];
                $params[] = $filters['annee'];
                $params[] = $filters['annee'];
            }
            
            $sql = "
                SELECT 
                    ce.*,
                    CONCAT(e.nom, ' ', e.prenom) as employe_nom,
                    tc.nom as type_conge_nom,
                    tc.couleur,
                    CONCAT(v.nom, ' ', v.prenom) as validateur_nom
                FROM conges_employes ce
                JOIN employes e ON ce.id_employe = e.id
                JOIN types_conges tc ON ce.id_type_conge = tc.id
                LEFT JOIN employes v ON ce.valide_par = v.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY ce.date_demande DESC
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement des demandes: " . $e->getMessage();
            return false;
        }
    }
    
    // ==============================================
    // GESTION DES SOLDES
    // ==============================================
    
    /**
     * Récupérer les soldes de congés d'un employé
     */
    public function getSoldesConges($id_employe, $annee = null) {
        try {
            $annee = $annee ?: date('Y');
            
            $stmt = $this->conn->prepare("
                SELECT 
                    sc.*,
                    tc.nom as type_conge_nom,
                    tc.couleur
                FROM soldes_conges sc
                JOIN types_conges tc ON sc.id_type_conge = tc.id
                WHERE sc.id_employe = ? AND sc.annee = ?
                ORDER BY tc.nom
            ");
            
            $stmt->execute([$id_employe, $annee]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement des soldes: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Initialiser les soldes de congés pour une nouvelle année
     */
    public function initialiserSoldesAnnuels($annee = null) {
        try {
            $annee = $annee ?: date('Y');
            
            $this->conn->beginTransaction();
            
            // Récupérer tous les employés actifs
            $stmt = $this->conn->query("SELECT id FROM employes WHERE statut = 'actif'");
            $employes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Récupérer tous les types de congés avec jours par an
            $stmt = $this->conn->query("SELECT id, jours_par_an FROM types_conges WHERE actif = 1 AND jours_par_an > 0");
            $types_conges = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($employes as $id_employe) {
                foreach ($types_conges as $type) {
                    // Vérifier si le solde existe déjà
                    $stmt = $this->conn->prepare("
                        SELECT id FROM soldes_conges 
                        WHERE id_employe = ? AND id_type_conge = ? AND annee = ?
                    ");
                    $stmt->execute([$id_employe, $type['id'], $annee]);
                    
                    if (!$stmt->fetch()) {
                        // Calculer les reports de l'année précédente
                        $jours_reports = $this->calculerReports($id_employe, $type['id'], $annee - 1);
                        
                        // Créer le solde
                        $stmt = $this->conn->prepare("
                            INSERT INTO soldes_conges 
                            (id_employe, id_type_conge, annee, jours_acquis, jours_restants, jours_reports)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        $jours_total = $type['jours_par_an'] + $jours_reports;
                        
                        $stmt->execute([
                            $id_employe,
                            $type['id'],
                            $annee,
                            $type['jours_par_an'],
                            $jours_total,
                            $jours_reports
                        ]);
                    }
                }
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->errors[] = "Erreur lors de l'initialisation des soldes: " . $e->getMessage();
            return false;
        }
    }
    
    // ==============================================
    // GESTION DES ABSENCES
    // ==============================================
    
    /**
     * Enregistrer une absence
     */
    public function enregistrerAbsence($data) {
        try {
            // Validation
            if (empty($data['id_employe']) || empty($data['id_type_absence']) || empty($data['date_absence'])) {
                $this->errors[] = "Données manquantes";
                return false;
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO absences 
                (id_employe, id_type_absence, date_absence, heure_debut, heure_fin, 
                 duree_heures, motif, justifiee, signale_par, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'signale')
            ");
            
            $duree = $this->calculerDureeAbsence($data['heure_debut'] ?? null, $data['heure_fin'] ?? null);
            
            $result = $stmt->execute([
                $data['id_employe'],
                $data['id_type_absence'],
                $data['date_absence'],
                $data['heure_debut'] ?? null,
                $data['heure_fin'] ?? null,
                $duree,
                $data['motif'] ?? null,
                $data['justifiee'] ?? 0,
                $data['signale_par']
            ]);
            
            return [
                'success' => true,
                'id_absence' => $this->conn->lastInsertId()
            ];
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors de l'enregistrement: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Récupérer les absences
     */
    public function getAbsences($filters = []) {
        try {
            $where = ["1=1"];
            $params = [];
            
            if (!empty($filters['employe_id'])) {
                $where[] = "a.id_employe = ?";
                $params[] = $filters['employe_id'];
            }
            
            if (!empty($filters['mois']) && !empty($filters['annee'])) {
                $where[] = "MONTH(a.date_absence) = ? AND YEAR(a.date_absence) = ?";
                $params[] = $filters['mois'];
                $params[] = $filters['annee'];
            }
            
            $sql = "
                SELECT 
                    a.*,
                    CONCAT(e.nom, ' ', e.prenom) as employe_nom,
                    ta.nom as type_absence_nom,
                    ta.couleur,
                    ta.deduction_salaire
                FROM absences a
                JOIN employes e ON a.id_employe = e.id
                JOIN types_absences ta ON a.id_type_absence = ta.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.date_absence DESC, a.created_at DESC
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement des absences: " . $e->getMessage();
            return false;
        }
    }
    
    // ==============================================
    // MÉTHODES PRIVÉES
    // ==============================================
    private function validerDonneesDemandeConge($data) {

        if (empty($data['id_employe']) || empty($data['id_type_conge']) || 
            empty($data['date_debut']) || empty($data['date_fin'])) {
            $this->errors[] = "Données manquantes";
            return false;
        }
        
        if (strtotime($data['date_fin']) < strtotime($data['date_debut'])) {
            $this->errors[] = "La date de fin ne peut être antérieure à la date de début";
            return false;
        }
        
        return true;
    }
    
    private function verifierDisponibilite($id_employe, $date_debut, $date_fin) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM conges_employes 
            WHERE id_employe = ? 
            AND statut IN ('en_attente', 'approuve')
            AND (
                (date_debut <= ? AND date_fin >= ?) OR
                (date_debut <= ? AND date_fin >= ?) OR
                (date_debut >= ? AND date_fin <= ?)
            )
        ");
        
        $stmt->execute([
            $id_employe, 
            $date_debut, $date_debut,
            $date_fin, $date_fin,
            $date_debut, $date_fin
        ]);
        
        return $stmt->fetchColumn() == 0;
    }
    
    private function calculerNombreJours($date_debut, $date_fin) {
        $debut = new DateTime($date_debut);
        $fin = new DateTime($date_fin);
        $fin->add(new DateInterval('P1D')); // Inclure le dernier jour
        
        $periode = new DatePeriod($debut, new DateInterval('P1D'), $fin);
        $jours_ouvres = 0;
        
        foreach ($periode as $date) {
            // Exclure samedi (6) et dimanche (0)
            if ($date->format('w') != 0 && $date->format('w') != 6) {
                $jours_ouvres++;
            }
        }
        
        return $jours_ouvres;
    }
    
    private function verifierSoldeDisponible($id_employe, $id_type_conge, $nb_jours) {
        $stmt = $this->conn->prepare("
            SELECT jours_restants FROM soldes_conges 
            WHERE id_employe = ? AND id_type_conge = ? AND annee = ?
        ");
        
        $stmt->execute([$id_employe, $id_type_conge, date('Y')]);
        $solde = $stmt->fetchColumn();
        
        return $solde !== false && $solde >= $nb_jours;
    }
    
    private function creerPlanningConge($id_conge) {
        // Récupérer les détails du congé
        $stmt = $this->conn->prepare("
            SELECT id_employe, id_type_conge, date_debut, date_fin 
            FROM conges_employes WHERE id = ?
        ");
        $stmt->execute([$id_conge]);
        $conge = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conge) return false;
        
        // Créer les entrées de planning pour chaque jour
        $debut = new DateTime($conge['date_debut']);
        $fin = new DateTime($conge['date_fin']);
        $fin->add(new DateInterval('P1D'));
        
        $periode = new DatePeriod($debut, new DateInterval('P1D'), $fin);
        
        $stmt = $this->conn->prepare("
            INSERT INTO planning_conges (id_conge, date_conge, id_employe, id_type_conge)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($periode as $date) {
            // Exclure weekends pour les congés payés
            if ($date->format('w') != 0 && $date->format('w') != 6) {
                $stmt->execute([
                    $id_conge,
                    $date->format('Y-m-d'),
                    $conge['id_employe'],
                    $conge['id_type_conge']
                ]);
            }
        }
        
        return true;
    }
    
    private function mettreAJourSoldesConges($id_conge) {
        // Récupérer les détails du congé
        $stmt = $this->conn->prepare("
            SELECT id_employe, id_type_conge, nb_jours_demandes 
            FROM conges_employes WHERE id = ?
        ");
        $stmt->execute([$id_conge]);
        $conge = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conge) return false;
        
        // Mettre à jour le solde
        $stmt = $this->conn->prepare("
            UPDATE soldes_conges 
            SET jours_pris = jours_pris + ?, 
                jours_restants = jours_restants - ?
            WHERE id_employe = ? AND id_type_conge = ? AND annee = ?
        ");
        
        return $stmt->execute([
            $conge['nb_jours_demandes'],
            $conge['nb_jours_demandes'],
            $conge['id_employe'],
            $conge['id_type_conge'],
            date('Y')
        ]);
    }
    
    private function calculerReports($id_employe, $id_type_conge, $annee_precedente) {
        // Récupérer le type de congé pour vérifier s'il est reportable
        $stmt = $this->conn->prepare("SELECT reporte FROM types_conges WHERE id = ?");
        $stmt->execute([$id_type_conge]);
        $reporte = $stmt->fetchColumn();
        
        if (!$reporte) return 0;
        
        // Récupérer les jours restants de l'année précédente
        $stmt = $this->conn->prepare("
            SELECT jours_restants FROM soldes_conges 
            WHERE id_employe = ? AND id_type_conge = ? AND annee = ?
        ");
        $stmt->execute([$id_employe, $id_type_conge, $annee_precedente]);
        $jours_restants = $stmt->fetchColumn();
        
        // Limiter le report (ex: max 10 jours)
        return min($jours_restants ?: 0, 10);
    }
    
    private function calculerDureeAbsence($heure_debut, $heure_fin) {
        if (!$heure_debut || !$heure_fin) {
            return 8; // Journée complète par défaut
        }
        
        $debut = new DateTime($heure_debut);
        $fin = new DateTime($heure_fin);
        $diff = $debut->diff($fin);
        
        return $diff->h + ($diff->i / 60);
    }
    
    /**
     * Récupérer le calendrier des congés pour affichage
     */
    public function getCalendrierConges($mois, $annee) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    pc.date_conge,
                    CONCAT(e.nom, ' ', e.prenom) as employe_nom,
                    tc.nom as type_conge,
                    tc.couleur
                FROM planning_conges pc
                JOIN employes e ON pc.id_employe = e.id
                JOIN types_conges tc ON pc.id_type_conge = tc.id
                WHERE MONTH(pc.date_conge) = ? AND YEAR(pc.date_conge) = ?
                ORDER BY pc.date_conge, e.nom
            ");
            
            $stmt->execute([$mois, $annee]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement du calendrier: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Statistiques des congés
     */
    public function getStatistiquesConges($annee = null) {
        try {
            $annee = $annee ?: date('Y');
            
            $stmt = $this->conn->prepare("
                SELECT 
                    tc.nom as type_conge,
                    COUNT(ce.id) as nb_demandes,
                    SUM(CASE WHEN ce.statut = 'approuve' THEN ce.nb_jours_demandes ELSE 0 END) as jours_pris,
                    AVG(CASE WHEN ce.statut = 'approuve' THEN ce.nb_jours_demandes ELSE NULL END) as moyenne_jours
                FROM types_conges tc
                LEFT JOIN conges_employes ce ON tc.id = ce.id_type_conge 
                    AND YEAR(ce.date_debut) = ?
                WHERE tc.actif = 1
                GROUP BY tc.id, tc.nom
                ORDER BY jours_pris DESC
            ");
            
            $stmt->execute([$annee]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du calcul des statistiques: " . $e->getMessage();
            return false;
        }
    }
}