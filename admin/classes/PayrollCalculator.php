<?php
/**
 * Calculateur de paie enrichi avec congés, avances et primes
 */
class PayrollCalculator {
    private $conn;
    private $errors = [];
    private $congesManager;
    private $avancesManager;
    private $primesManager;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        
        // Initialiser les gestionnaires
        require_once 'CongesManager.php';
        require_once 'AvancesManager.php';
        require_once 'PrimesManager.php';
        
        $this->congesManager = new CongesManager($database_connection);
        $this->avancesManager = new AvancesManager($database_connection);
        $this->primesManager = new PrimesManager($database_connection);
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Calculer le salaire avec toutes les nouvelles fonctionnalités
     */
    public function calculerSalaire($id_employe, $mois, $annee, $heures_supp = 0, $jours_absences = 0, $jours_conges = 0) {
        try {
            // 1. Récupérer les données de base de l'employé
            $employe = $this->getEmployeData($id_employe);
            if (!$employe) {
                $this->errors[] = "Employé non trouvé";
                return false;
            }
            
            // 2. Calcul du salaire de base ajusté
            $salaire_base = $employe['salaire_base'];
            $jours_travailles = $this->calculerJoursTravailles($mois, $annee);
            
            // 3. Récupérer les absences réelles du système
            $absences_data = $this->calculerAbsences($id_employe, $mois, $annee);
            
            // 4. Récupérer les primes du mois
            $primes_data = $this->primesManager->calculerPrimesForPayroll($id_employe, $mois, $annee);
            
            // 5. Récupérer les avances à déduire
            $avances_deduction = $this->avancesManager->calculerDeductionMensuelle($id_employe);
            
            // 6. Calculs de base
            $salaire_journalier = $salaire_base / $jours_travailles;
            
            // Déductions pour absences non payées
            $deduction_absences = $absences_data['montant_deduction'];
            $salaire_base_ajuste = $salaire_base - $deduction_absences;
            
            // Heures supplémentaires (votre logique existante)
            $taux_horaire = $salaire_base / ($jours_travailles * 8);
            $montant_heures_supp = $heures_supp * $taux_horaire * 1.5; // Majoration 50%
            
            // 7. Calcul salaire brut
            $salaire_brut = $salaire_base_ajuste + $montant_heures_supp + $primes_data['total_soumis_cotisations'];
            
            // 8. Cotisations sociales (votre logique existante)
            $cotisations = $this->calculerCotisations($salaire_brut, $employe);
            
            // 9. Autres retenues
            $autres_retenues = $avances_deduction; // Remboursement des avances
            
            // 10. Salaire net
            $salaire_net = $salaire_brut - $cotisations['total'] - $autres_retenues + $primes_data['total_non_soumis'];
            
            return [
                'employe' => $employe,
                'salaire_base' => $salaire_base,
                'salaire_base_ajuste' => $salaire_base_ajuste,
                'jours_travailles' => $jours_travailles,
                'heures_supplementaires' => $heures_supp,
                'montant_heures_supp' => $montant_heures_supp,
                
                // Nouvelles données
                'absences' => $absences_data,
                'primes' => $primes_data,
                'avances_remboursement' => $avances_deduction,
                
                // Totaux
                'salaire_brut' => $salaire_brut,
                'cotisations' => $cotisations,
                'salaire_net' => $salaire_net,
                
                // Détails pour le bulletin
                'periode' => [
                    'mois' => $mois,
                    'annee' => $annee,
                    'libelle' => $this->getLibelleMois($mois) . ' ' . $annee
                ]
            ];
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur de calcul: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Générer un bulletin avec toutes les nouvelles fonctionnalités
     */
    public function genererBulletin($id_employe, $mois, $annee, $options = []) {
        try {
            // Vérifier si un bulletin existe déjà
            if ($this->bulletinExists($id_employe, $mois, $annee)) {
                $this->errors[] = "Un bulletin existe déjà pour cette période";
                return false;
            }
            
            // Calculer le salaire
            $calcul = $this->calculerSalaire(
                $id_employe, 
                $mois, 
                $annee,
                $options['heures_supplementaires'] ?? 0,
                $options['jours_absences'] ?? 0,
                $options['jours_conges'] ?? 0
            );
            
            if (!$calcul) {
                return false;
            }
            
            $this->conn->beginTransaction();
            
            // Insérer le bulletin principal
            $stmt = $this->conn->prepare("
                INSERT INTO bulletins_paie 
                (id_employe, mois, annee, salaire_base, salaire_brut, salaire_net,
                 heures_supplementaires, montant_heures_supp, total_primes, total_cotisations,
                 jours_absences_non_payees, montant_absences, montant_avances_remboursees,
                 total_primes_variables, statut, date_generation)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'brouillon', NOW())
            ");
            
            $result = $stmt->execute([
                $id_employe,
                $mois,
                $annee,
                $calcul['salaire_base'],
                $calcul['salaire_brut'],
                $calcul['salaire_net'],
                $calcul['heures_supplementaires'],
                $calcul['montant_heures_supp'],
                $calcul['primes']['total'],
                $calcul['cotisations']['total'],
                $calcul['absences']['jours_perdus'],
                $calcul['absences']['montant_deduction'],
                $calcul['avances_remboursement'],
                $calcul['primes']['total']
            ]);
            
            $id_bulletin = $this->conn->lastInsertId();
            
            // Insérer les détails des primes
            $this->insererDetailsPrimes($id_bulletin, $calcul['primes']['elements']);
            
            // Insérer les détails des cotisations
            $this->insererDetailsCotisations($id_bulletin, $calcul['cotisations']['elements']);
            
            // Traiter les remboursements d'avances automatiquement
            if ($calcul['avances_remboursement'] > 0) {
                $this->avancesManager->traiterRemboursementsAutomatiques($id_employe, $id_bulletin);
            }
            
            $this->conn->commit();
            
            return [
                'id_bulletin' => $id_bulletin,
                'calcul' => $calcul
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->errors[] = "Erreur lors de la génération: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Calculer les absences avec impact sur la paie
     */
    private function calculerAbsences($id_employe, $mois, $annee) {
        $absences = $this->congesManager->getAbsences([
            'employe_id' => $id_employe,
            'mois' => $mois,
            'annee' => $annee
        ]);
        
        $jours_perdus = 0;
        $heures_perdues = 0;
        $montant_deduction = 0;
        $details = [];
        
        if ($absences) {
            // Récupérer le salaire journalier
            $stmt = $this->conn->prepare("SELECT salaire_base FROM employes WHERE id = ?");
            $stmt->execute([$id_employe]);
            $salaire_base = $stmt->fetchColumn();
            
            $jours_travailles = $this->calculerJoursTravailles($mois, $annee);
            $salaire_journalier = $salaire_base / $jours_travailles;
            $taux_horaire = $salaire_journalier / 8;
            
            foreach ($absences as $absence) {
                if ($absence['deduction_salaire'] && $absence['statut'] === 'confirme') {
                    $heures_perdues += $absence['duree_heures'];
                    $jours_perdus += ($absence['duree_heures'] / 8);
                    $montant_deduction += ($absence['duree_heures'] * $taux_horaire);
                    
                    $details[] = [
                        'date' => $absence['date_absence'],
                        'type' => $absence['type_absence_nom'],
                        'heures' => $absence['duree_heures'],
                        'montant' => ($absence['duree_heures'] * $taux_horaire)
                    ];
                }
            }
        }
        
        return [
            'jours_perdus' => $jours_perdus,
            'heures_perdues' => $heures_perdues,
            'montant_deduction' => $montant_deduction,
            'details' => $details
        ];
    }
    
    /**
     * Récupérer les bulletins avec filtres enrichis
     */
    public function getBulletins($filters = []) {
        try {
            $where = ["1=1"];
            $params = [];
            
            // Filtres existants + nouveaux
            if (!empty($filters['employe_id'])) {
                $where[] = "bp.id_employe = ?";
                $params[] = $filters['employe_id'];
            }
            
            if (!empty($filters['mois'])) {
                $where[] = "bp.mois = ?";
                $params[] = $filters['mois'];
            }
            
            if (!empty($filters['annee'])) {
                $where[] = "bp.annee = ?";
                $params[] = $filters['annee'];
            }
            
            if (!empty($filters['statut'])) {
                $where[] = "bp.statut = ?";
                $params[] = $filters['statut'];
            }
            
            $sql = "
                SELECT 
                    bp.*,
                    CONCAT(e.nom, ' ', e.prenom) as employe_nom,
                    e.email as employe_email,
                    p.nom as poste_nom,
                    CASE 
                        WHEN bp.montant_avances_remboursees > 0 THEN 'Avec avance'
                        ELSE 'Standard'
                    END as type_bulletin
                FROM bulletins_paie bp
                JOIN employes e ON bp.id_employe = e.id
                LEFT JOIN postes p ON e.poste_id = p.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY bp.annee DESC, bp.mois DESC, bp.date_generation DESC
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement des bulletins: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Récupérer un bulletin avec tous les détails enrichis
     */
    public function getBulletin($id_bulletin) {
        try {
            // Bulletin principal
            $stmt = $this->conn->prepare("
                SELECT 
                    bp.*,
                    CONCAT(e.nom, ' ', e.prenom) as employe_nom,
                    e.email, e.telephone,
                    p.nom as poste_nom,
                    e.date_embauche,
                    e.numero_cnss
                FROM bulletins_paie bp
                JOIN employes e ON bp.id_employe = e.id
                LEFT JOIN postes p ON e.poste_id = p.id
                WHERE bp.id = ?
            ");
            
            $stmt->execute([$id_bulletin]);
            $bulletin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$bulletin) {
                return false;
            }
            
            // Détails des primes
            $stmt = $this->conn->prepare("
                SELECT 
                    pe.*,
                    tp.nom as type_prime_nom,
                    tp.code
                FROM primes_employes pe
                JOIN types_primes tp ON pe.id_type_prime = tp.id
                WHERE pe.id_employe = ? AND pe.mois = ? AND pe.annee = ? AND pe.valide = 1
            ");
            
            $stmt->execute([$bulletin['id_employe'], $bulletin['mois'], $bulletin['annee']]);
            $bulletin['primes_details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Détails des avances remboursées
            $stmt = $this->conn->prepare("
                SELECT 
                    ra.*,
                    av.motif
                FROM remboursements_avances ra
                JOIN avances_salaire av ON ra.id_avance = av.id
                WHERE ra.id_bulletin = ?
            ");
            
            $stmt->execute([$id_bulletin]);
            $bulletin['avances_details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Détails des absences
            $absences_data = $this->calculerAbsences($bulletin['id_employe'], $bulletin['mois'], $bulletin['annee']);
            $bulletin['absences_details'] = $absences_data['details'];
            
            return $bulletin;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du chargement du bulletin: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Statistiques enrichies
     */
    public function getStatistiquesPaie($mois = null, $annee = null) {
        try {
            $mois = $mois ?: date('n');
            $annee = $annee ?: date('Y');
            
            $stmt = $this->conn->prepare("
                SELECT 
                    COUNT(*) as nombre_bulletins,
                    SUM(salaire_net) as masse_salariale_nette,
                    SUM(salaire_brut) as masse_salariale_brute,
                    AVG(salaire_net) as salaire_moyen,
                    SUM(total_cotisations) as total_cotisations,
                    SUM(total_primes_variables) as total_primes,
                    SUM(montant_avances_remboursees) as total_avances_remboursees,
                    SUM(montant_absences) as total_deductions_absences
                FROM bulletins_paie 
                WHERE mois = ? AND annee = ?
            ");
            
            $stmt->execute([$mois, $annee]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Ajouter des statistiques spécifiques aux nouvelles fonctionnalités
            $stats['employes_avec_primes'] = $this->compterEmployesAvecPrimes($mois, $annee);
            $stats['employes_avec_avances'] = $this->compterEmployesAvecAvances($mois, $annee);
            $stats['total_jours_absences'] = $this->calculerTotalJoursAbsences($mois, $annee);
            
            return $stats;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors du calcul des statistiques: " . $e->getMessage();
            return false;
        }
    }
    
    // ==============================================
    // MÉTHODES PRIVÉES UTILITAIRES
    // ==============================================
    
    private function getEmployeData($id_employe) {
        $stmt = $this->conn->prepare("
            SELECT e.*, p.nom as poste_nom 
            FROM employes e 
            LEFT JOIN postes p ON e.poste_id = p.id 
            WHERE e.id = ? AND e.statut = 'actif'
        ");
        $stmt->execute([$id_employe]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function calculerJoursTravailles($mois, $annee) {
        $premier_jour = new DateTime("$annee-$mois-01");
        $dernier_jour = new DateTime("$annee-$mois-" . $premier_jour->format('t'));
        
        $jours_ouvres = 0;
        $periode = new DatePeriod($premier_jour, new DateInterval('P1D'), $dernier_jour->add(new DateInterval('P1D')));
        
        foreach ($periode as $date) {
            if ($date->format('w') != 0 && $date->format('w') != 6) { // Exclure weekend
                $jours_ouvres++;
            }
        }
        
        return $jours_ouvres;
    }
    
    private function calculerCotisations($salaire_brut, $employe) {
        // Votre logique existante de calcul des cotisations
        $cotisations = [
            'elements' => [],
            'total' => 0
        ];
        
        // CNSS (exemple pour le Sénégal)
        $cnss = $salaire_brut * 0.056; // 5.6%
        $cotisations['elements']['CNSS'] = $cnss;
        $cotisations['total'] += $cnss;
        
        // IPRES
        $ipres = $salaire_brut * 0.06; // 6%
        $cotisations['elements']['IPRES'] = $ipres;
        $cotisations['total'] += $ipres;
        
        // CSS
        $css = $salaire_brut * 0.01; // 1%
        $cotisations['elements']['CSS'] = $css;
        $cotisations['total'] += $css;
        
        return $cotisations;
    }
    
    private function bulletinExists($id_employe, $mois, $annee) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM bulletins_paie 
            WHERE id_employe = ? AND mois = ? AND annee = ?
        ");
        $stmt->execute([$id_employe, $mois, $annee]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function insererDetailsPrimes($id_bulletin, $primes) {
        if (empty($primes)) return;
        
        $stmt = $this->conn->prepare("
            INSERT INTO bulletin_primes (id_bulletin, nom_prime, code_prime, montant, soumis_cotisations)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($primes as $prime) {
            $stmt->execute([
                $id_bulletin,
                $prime['nom'],
                $prime['code'],
                $prime['montant'],
                $prime['soumis_cotisations']
            ]);
        }
    }
    
    private function insererDetailsCotisations($id_bulletin, $cotisations) {
        $stmt = $this->conn->prepare("
            INSERT INTO bulletin_cotisations (id_bulletin, nom_cotisation, montant, taux)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($cotisations as $nom => $montant) {
            // Calculer le taux approximatif
            $taux = 0;
            switch ($nom) {
                case 'CNSS': $taux = 5.6; break;
                case 'IPRES': $taux = 6.0; break;
                case 'CSS': $taux = 1.0; break;
            }
            
            $stmt->execute([$id_bulletin, $nom, $montant, $taux]);
        }
    }
    
    private function getLibelleMois($mois) {
        $mois_fr = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        return $mois_fr[$mois] ?? 'Mois inconnu';
    }
    
    private function compterEmployesAvecPrimes($mois, $annee) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT id_employe) 
            FROM primes_employes 
            WHERE mois = ? AND annee = ? AND valide = 1
        ");
        $stmt->execute([$mois, $annee]);
        return $stmt->fetchColumn();
    }
    
    private function compterEmployesAvecAvances($mois, $annee) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT bp.id_employe) 
            FROM bulletins_paie bp 
            WHERE bp.mois = ? AND bp.annee = ? AND bp.montant_avances_remboursees > 0
        ");
        $stmt->execute([$mois, $annee]);
        return $stmt->fetchColumn();
    }
    
    private function calculerTotalJoursAbsences($mois, $annee) {
        $stmt = $this->conn->prepare("
            SELECT SUM(jours_absences_non_payees) 
            FROM bulletins_paie 
            WHERE mois = ? AND annee = ?
        ");
        $stmt->execute([$mois, $annee]);
        return $stmt->fetchColumn() ?: 0;
    }
    
    /**
     * Valider un bulletin (méthode existante étendue)
     */
    public function validerBulletin($id_bulletin) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE bulletins_paie 
                SET statut = 'valide', date_validation = NOW() 
                WHERE id = ? AND statut = 'brouillon'
            ");
            
            $result = $stmt->execute([$id_bulletin]);
            
            if ($stmt->rowCount() === 0) {
                $this->errors[] = "Bulletin non trouvé ou déjà validé";
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors de la validation: " . $e->getMessage();
            return false;
        }
    }
}
