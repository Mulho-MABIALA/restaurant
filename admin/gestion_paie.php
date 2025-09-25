<?php
session_start();
require_once '../config.php';

// Vérification sécurité admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Inclusion des classes managers
require_once 'classes/EmployeesManager.php';
require_once 'classes/PresenceManager.php';
require_once 'classes/PaieManager.php';
require_once 'classes/PostesManager.php';
require_once 'classes/SecurityManager.php';
require_once 'classes/AuditManager.php';

// Classes existantes (conservées pour compatibilité)
require_once 'classes/PayrollCalculator.php';
require_once 'classes/BulletinUtilities.php';
require_once 'classes/BulletinPDFGenerateur.php';
require_once 'classes/CongesManager.php';
require_once 'classes/AvancesManager.php';
require_once 'classes/PrimesManager.php';

// Initialisation des managers
$employeesManager = new EmployeesManager($conn);
$presenceManager = new PresenceManager($conn);
$paieManager = new PaieManager($conn);
$postesManager = new PostesManager($conn);
$auditManager = new AuditManager($conn);

// Managers existants (conservés)
$payrollCalculator = new PayrollCalculator($conn);
$pdfGenerator = new BulletinPDFGenerateur([
    'nom' => 'Restaurant Le Savoureux',
    'adresse' => '123 Avenue de la République, Dakar, Sénégal',
    'telephone' => '+221 33 123 45 67',
    'email' => 'contact@lesavoureux.sn',
    'ninea' => '123456789'
]);
$congesManager = new CongesManager($conn);
$avancesManager = new AvancesManager($conn);
$primesManager = new PrimesManager($conn);

function getHeuresPoste($conn, $posteId) {
    $stmt = $conn->prepare("
        SELECT heures_semaine, heures_mois, salaire_base 
        FROM postes 
        WHERE id = ?
    ");
    $stmt->execute([$posteId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 8. AJOUT D'UNE FONCTION POUR RÉCUPÉRER LA PLANIFICATION DE LA SEMAINE
function getPlanificationSemaine($conn, $semaine_debut) {
    $stmt = $conn->prepare("
        SELECT h.*, e.nom, e.prenom, p.nom as poste_nom
        FROM horaires h
        INNER JOIN employes e ON h.employe_id = e.id
        LEFT JOIN postes p ON e.poste_id = p.id
        WHERE h.semaine_debut = ?
        ORDER BY e.nom, e.prenom
    ");
    $stmt->execute([$semaine_debut]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction utilitaire pour formater les montants
function formaterMontant($montant) {
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}

// Fonction utilitaire pour échapper les sorties
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Fonction pour récupérer les horaires planifiés d'un employé
function getHorairesEmploye($conn, $employeId, $date) {
    $semaine_debut = date('Y-m-d', strtotime('monday', strtotime($date)));
    $jour_semaine = strtolower(date('l', strtotime($date))); // lundi, mardi, etc.
    
    // Traduire en français
    $jours_mapping = [
        'monday' => 'lundi',
        'tuesday' => 'mardi', 
        'wednesday' => 'mercredi',
        'thursday' => 'jeudi',
        'friday' => 'vendredi',
        'saturday' => 'samedi',
        'sunday' => 'dimanche'
    ];
    
    $jour_fr = $jours_mapping[$jour_semaine] ?? 'lundi';
    
    $stmt = $conn->prepare("
        SELECT 
            {$jour_fr}_debut as heure_debut_prevue,
            {$jour_fr}_fin as heure_fin_prevue
        FROM horaires 
        WHERE employe_id = ? AND semaine_debut = ?
    ");
    
    $stmt->execute([$employeId, $semaine_debut]);
    $horaire = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($horaire && $horaire['heure_debut_prevue'] && $horaire['heure_fin_prevue']) {
        return [
            'heure_debut' => $horaire['heure_debut_prevue'],
            'heure_fin' => $horaire['heure_fin_prevue'],
            'est_programme' => true
        ];
    }
    
    // Si pas d'horaire spécifique, récupérer les horaires par défaut de l'employé
    $stmt2 = $conn->prepare("SELECT heure_debut, heure_fin FROM employes WHERE id = ?");
    $stmt2->execute([$employeId]);
    $employe = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    return [
        'heure_debut' => $employe['heure_debut'] ?? '08:00:00',
        'heure_fin' => $employe['heure_fin'] ?? '17:00:00',
        'est_programme' => false
    ];
}

// Fonction pour déterminer le statut de présence correct
function determinerStatutPresence($presence, $horairePlanifie) {
    // Si pas d'horaire programmé pour ce jour = PAUSE (pas absent)
    if (!$horairePlanifie['est_programme']) {
        return 'pause';
    }
    
    // Si pas de présence enregistrée = ABSENT
    if (!$presence || !$presence['heure_arrivee']) {
        return 'absent';
    }
    
    // Comparer avec l'heure prévue (avec tolérance de 15 minutes)
    $heureArrivee = new DateTime($presence['heure_arrivee']);
    $heureDebut = new DateTime($horairePlanifie['heure_debut']);
    $heureDebut->add(new DateInterval('PT15M')); // Tolérance de 15 minutes
    
    if ($heureArrivee > $heureDebut) {
        return 'retard';
    }
    
    return 'present';
}

// FONCTION PRINCIPALE MANQUANTE
function calculerHeuresParRapportPlanification($conn, $employeId, $mois, $annee) {
    $premierjour = "$annee-$mois-01";
    $dernierjour = date('Y-m-t', strtotime($premierjour));
    
    $result = [
        'heures_planifiees_total' => 0,
        'heures_reelles_total' => 0,
        'jours_travailles' => 0,
        'jours_en_pause' => 0,
        'nb_retards' => 0,
        'nb_absences' => 0,
        'taux_presence' => 0,
        'details_par_jour' => []
    ];
    
    // Récupérer toutes les présences du mois
    $stmt = $conn->prepare("
        SELECT DATE(heure_arrivee) as date_presence, heure_arrivee, heure_depart
        FROM presences 
        WHERE employe_id = ? 
        AND DATE(heure_arrivee) BETWEEN ? AND ?
    ");
    $stmt->execute([$employeId, $premierjour, $dernierjour]);
    $presences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parcourir tous les jours du mois
    $dateActuelle = new DateTime($premierjour);
    $dateFin = new DateTime($dernierjour);
    
    while ($dateActuelle <= $dateFin) {
        $dateStr = $dateActuelle->format('Y-m-d');
        $horairePlanifie = getHorairesEmploye($conn, $employeId, $dateStr);
        
        // Trouver la présence pour ce jour
        $presenceJour = null;
        foreach ($presences as $presence) {
            if ($presence['date_presence'] == $dateStr) {
                $presenceJour = $presence;
                break;
            }
        }
        
        $statut = determinerStatutPresence($presenceJour, $horairePlanifie);
        
        $heuresPlanifiees = 0;
        $heuresReelles = 0;
        
        if ($horairePlanifie['est_programme']) {
            // Calculer les heures planifiées
            $debut = new DateTime($horairePlanifie['heure_debut']);
            $fin = new DateTime($horairePlanifie['heure_fin']);
            $heuresPlanifiees = ($fin->getTimestamp() - $debut->getTimestamp()) / 3600;
            
            $result['heures_planifiees_total'] += $heuresPlanifiees;
            
            if ($presenceJour && $presenceJour['heure_arrivee'] && $presenceJour['heure_depart']) {
                $arrivee = new DateTime($presenceJour['heure_arrivee']);
                $depart = new DateTime($presenceJour['heure_depart']);
                $heuresReelles = ($depart->getTimestamp() - $arrivee->getTimestamp()) / 3600;
                $result['heures_reelles_total'] += $heuresReelles;
                $result['jours_travailles']++;
            } else if ($statut === 'absent') {
                $result['nb_absences']++;
            }
            
            if ($statut === 'retard') {
                $result['nb_retards']++;
            }
        } else {
            $result['jours_en_pause']++;
        }
        
        $result['details_par_jour'][] = [
            'date' => $dateStr,
            'statut' => $statut,
            'heures_planifiees' => $heuresPlanifiees,
            'heures_reelles' => $heuresReelles,
            'horaire_planifie' => $horairePlanifie
        ];
        
        $dateActuelle->add(new DateInterval('P1D'));
    }
    
    // Calculer le taux de présence (jours travaillés / jours programmés)
    $joursProgrammes = ($result['heures_planifiees_total'] > 0) ? 
        count(array_filter($result['details_par_jour'], function($jour) {
            return $jour['heures_planifiees'] > 0;
        })) : 0;
        
    $result['taux_presence'] = $joursProgrammes > 0 ? 
        ($result['jours_travailles'] / $joursProgrammes) * 100 : 0;
    
    return $result;
}
?>
// Traitement des actions AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Validation CSRF pour actions sensibles
    $sensibleActions = ['generer_bulletin', 'modifier_bulletin', 'supprimer_bulletin', 
                       'valider_conge', 'valider_avance', 'attribuer_prime'];
    
    if (in_array($_GET['action'], $sensibleActions)) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!SecurityManager::validateCSRFToken($input['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
            exit;
        }
    }
    
    try {
        switch ($_GET['action']) {
            // === GESTION EMPLOYÉS INTÉGRÉE ===
            case 'get_employes':
                $filters = [];
                if (isset($_GET['statut'])) $filters['statut'] = $_GET['statut'];
                if (isset($_GET['departement_id'])) $filters['departement_id'] = $_GET['departement_id'];
                if (isset($_GET['search'])) $filters['search'] = $_GET['search'];
                
                $employes = $employeesManager->getAllEmployees($filters);
                echo json_encode(['success' => true, 'employes' => $employes]);
                break;
                
            case 'get_employee_payroll_data':
                $employeeId = $_GET['employee_id'] ?? null;
                $period = $_GET['period'] ?? date('Y-m');
                
                if (!$employeeId) {
                    throw new Exception('ID employé requis');
                }
                
                $data = $employeesManager->getEmployeePayrollData($employeeId, $period);
                echo json_encode(['success' => true, 'data' => $data]);
                break;
                
            // === STATISTIQUES RH AVANCÉES ===
            case 'get_dashboard_stats_advanced':
                $stats = [];
                
                // Statistiques employés de base
                $stats['employes'] = $employeesManager->getEmployeeStatistics();
                
                // Statistiques paie
                $filters = [
                    'mois' => $_GET['mois'] ?? date('n'),
                    'annee' => $_GET['annee'] ?? date('Y')
                ];
                $stats['paie'] = $paieManager->getPayrollStatistics($filters);
                
                // Statistiques présences intégrées
                $startDate = date('Y-m-01');
                $endDate = date('Y-m-t');
                
                $stmt = $conn->query("
                    SELECT 
                        COUNT(DISTINCT e.id) as total_employes_actifs,
                        COUNT(DISTINCT p.employe_id) as employes_avec_presences,
                        AVG(
                            CASE WHEN p.heure_arrivee AND p.heure_depart THEN 
                                TIMESTAMPDIFF(HOUR, p.heure_arrivee, p.heure_depart)
                            END
                        ) as heures_moyennes_par_jour
                    FROM employes e
                    LEFT JOIN presences p ON e.id = p.employe_id 
                        AND DATE(p.heure_arrivee) BETWEEN '$startDate' AND '$endDate'
                    WHERE e.statut = 'actif'
                ");
                $stats['presences'] = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Données postes pour filtres
                $stats['postes'] = $postesManager->getPostesForPayroll();
                $stats['departements'] = $postesManager->getDepartements();
                
                echo json_encode(['success' => true, 'stats' => $stats]);
                break;
                
                case 'calculate_payroll_with_presences':
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validation = SecurityManager::validateInput($input, [
        'employe_id' => ['required' => true, 'type' => 'numeric'],
        'mois' => ['required' => true, 'type' => 'numeric'],
        'annee' => ['required' => true, 'type' => 'numeric']
    ]);
    
    if (!empty($validation)) {
        throw new Exception('Données invalides: ' . implode(', ', $validation));
    }
    
    // Récupérer les informations de l'employé et de son poste
    $stmt = $conn->prepare("
        SELECT e.*, p.heures_semaine, p.heures_mois, p.salaire_base
        FROM employes e
        LEFT JOIN postes p ON e.poste_id = p.id
        WHERE e.id = ?
    ");
    $stmt->execute([$input['employe_id']]);
    $employe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employe) {
        throw new Exception('Employé non trouvé');
    }
    
    // Calculer les statistiques avec planification
    $statsPresences = calculerHeuresParRapportPlanification(
        $conn, 
        $input['employe_id'], 
        $input['mois'], 
        $input['annee']
    );
    
    // Calcul du salaire basé sur les heures réelles vs planifiées
    $heuresPlanifiees = $employe['heures_mois'] ?? 173; // Heures standard si pas défini
    $heuresReelles = $statsPresences['heures_reelles_total'];
    $salaireBase = $employe['salaire_base'] ?? $employe['salaire'];
    
    // Calcul des heures supplémentaires et déductions
    $heuresSupp = max(0, $heuresReelles - $heuresPlanifiees);
    $heuresManquantes = max(0, $heuresPlanifiees - $heuresReelles);
    
    $tauxHoraire = $salaireBase / $heuresPlanifiees;
    
    $calculData = [
        'employe_id' => $input['employe_id'],
        'mois' => $input['mois'],
        'annee' => $input['annee'],
        'salaire_base' => $salaireBase,
        'heures_planifiees' => $heuresPlanifiees,
        'heures_reelles' => $heuresReelles,
        'heures_supplementaires' => $heuresSupp,
        'heures_manquantes' => $heuresManquantes,
        'montant_heures_supp' => $heuresSupp * $tauxHoraire * 1.25,
        'deduction_absences' => $heuresManquantes * $tauxHoraire,
        'taux_presence' => $statsPresences['taux_presence'],
        'nb_retards' => $statsPresences['nb_retards'],
        'nb_absences' => $statsPresences['nb_absences'],
        'jours_en_pause' => $statsPresences['jours_en_pause'],
        'stats_presences' => $statsPresences,
        'avec_planification' => true
    ];
    
    echo json_encode(['success' => true, 'calcul' => $calculData]);
    break;
    case 'verifier_coherence_planification':
    $date = $_GET['date'] ?? date('Y-m-d');
    $semaine_debut = date('Y-m-d', strtotime('monday', strtotime($date)));
    
    try {
        $planification = getPlanificationSemaine($conn, $semaine_debut);
        $incohérences = [];
        
        foreach ($planification as $planning) {
            $employeId = $planning['employe_id'];
            $statsPresences = calculerHeuresParRapportPlanification($conn, $employeId, date('n'), date('Y'));
            
            // Vérifier les incohérences
            if ($statsPresences['taux_presence'] < 80) {
                $incohérences[] = [
                    'employe_id' => $employeId,
                    'nom' => $planning['nom'] . ' ' . $planning['prenom'],
                    'probleme' => 'Taux de présence faible',
                    'taux' => round($statsPresences['taux_presence'], 1) . '%'
                ];
            }
            
            if ($statsPresences['nb_retards'] > 5) {
                $incohérences[] = [
                    'employe_id' => $employeId,
                    'nom' => $planning['nom'] . ' ' . $planning['prenom'],
                    'probleme' => 'Nombreux retards',
                    'nb_retards' => $statsPresences['nb_retards']
                ];
            }
        }
        
        echo json_encode(['success' => true, 'incoherences' => $incohérences]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;
    case 'ajouter_presence_manuelle':
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validation = SecurityManager::validateInput($input, [
        'employe_id' => ['required' => true, 'type' => 'numeric'],
        'date' => ['required' => true, 'type' => 'date'],
        'heure_arrivee' => ['required' => true]
    ]);
    
    if (!empty($validation)) {
        throw new Exception('Données invalides: ' . implode(', ', $validation));
    }
    
    try {
        // Vérifier si une présence existe déjà pour ce jour
        $stmt = $conn->prepare("
            SELECT id FROM presences 
            WHERE employe_id = ? AND DATE(heure_arrivee) = ?
        ");
        $stmt->execute([$input['employe_id'], $input['date']]);
        
        if ($stmt->fetch()) {
            throw new Exception('Une présence existe déjà pour cette date');
        }
        
        // Créer les timestamps complets
        $heureArrivee = $input['date'] . ' ' . $input['heure_arrivee'];
        $heureDepart = null;
        
        if (!empty($input['heure_depart'])) {
            $heureDepart = $input['date'] . ' ' . $input['heure_depart'];
            
            // Vérifier que l'heure de départ est après l'arrivée
            if (strtotime($heureDepart) <= strtotime($heureArrivee)) {
                throw new Exception('L\'heure de départ doit être postérieure à l\'heure d\'arrivée');
            }
        }
        
        // Insérer la présence
        $stmt = $conn->prepare("
            INSERT INTO presences (employe_id, heure_arrivee, heure_depart, commentaire, ajout_manuel, date_creation)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        
        $success = $stmt->execute([
            $input['employe_id'],
            $heureArrivee,
            $heureDepart,
            $input['commentaire'] ?? 'Ajout manuel par admin'
        ]);
        
        if ($success) {
            // Log de l'audit
            $auditManager->logPayrollAction('ADD_PRESENCE_MANUAL', [
                'employe_id' => $input['employe_id'],
                'date' => $input['date'],
                'heure_arrivee' => $input['heure_arrivee'],
                'heure_depart' => $input['heure_depart'] ?? null,
                'admin_id' => $_SESSION['admin_id'] ?? 1
            ]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Présence ajoutée avec succès',
                'id' => $conn->lastInsertId()
            ]);
        } else {
            throw new Exception('Erreur lors de l\'enregistrement');
        }
        
    } catch (Exception $e) {
        throw new Exception('Erreur ajout présence: ' . $e->getMessage());
    }
    break;
            // === INTÉGRATION PRÉSENCES DANS PAIE ===
            case 'calculate_payroll_with_presences':
                $input = json_decode(file_get_contents('php://input'), true);
                
                $validation = SecurityManager::validateInput($input, [
                    'employe_id' => ['required' => true, 'type' => 'numeric'],
                    'mois' => ['required' => true, 'type' => 'numeric'],
                    'annee' => ['required' => true, 'type' => 'numeric']
                ]);
                
                if (!empty($validation)) {
                    throw new Exception('Données invalides: ' . implode(', ', $validation));
                }
                
                // Calcul avec intégration automatique des présences
                $calculData = $paieManager->calculatePayroll(
                    $input['employe_id'],
                    $input['mois'], 
                    $input['annee'],
                    $input['options'] ?? []
                );
                
                echo json_encode(['success' => true, 'calcul' => $calculData]);
                break;
                case 'get_statistiques_detaillees':
    $debut = $_GET['debut'] ?? '';
    $fin = $_GET['fin'] ?? '';
    
    if (empty($debut) || empty($fin)) {
        throw new Exception('Périodes requises');
    }
    
    // Convertir les périodes en dates
    $dateDebut = $debut . '-01';
    $dateFin = date('Y-m-t', strtotime($fin . '-01'));
    
    $stats = [];
    
    // Statistiques générales
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT bp.employe_id) as total_employes,
            COUNT(bp.id) as total_bulletins,
            SUM(bp.salaire_net) as masse_salariale,
            AVG(bp.salaire_net) as salaire_moyen
        FROM bulletins_paie bp
        WHERE DATE_FORMAT(CONCAT(bp.annee, '-', LPAD(bp.mois, 2, '0'), '-01'), '%Y-%m-%d') 
              BETWEEN ? AND ?
    ");
    $stmt->execute([$dateDebut, $dateFin]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Top 10 salaires
    $stmt = $conn->prepare("
        SELECT 
            CONCAT(e.prenom, ' ', e.nom) as nom_complet,
            p.nom as poste,
            AVG(bp.salaire_net) as salaire_moyen,
            COUNT(bp.id) as nb_bulletins
        FROM bulletins_paie bp
        INNER JOIN employes e ON bp.employe_id = e.id
        LEFT JOIN postes p ON e.poste_id = p.id
        WHERE DATE_FORMAT(CONCAT(bp.annee, '-', LPAD(bp.mois, 2, '0'), '-01'), '%Y-%m-%d') 
              BETWEEN ? AND ?
        GROUP BY bp.employe_id, e.prenom, e.nom, p.nom
        ORDER BY salaire_moyen DESC
        LIMIT 10
    ");
    $stmt->execute([$dateDebut, $dateFin]);
    $stats['top_salaires'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'stats' => $stats]);
    break;

case 'get_conges_calendrier':
    $mois = $_GET['mois'] ?? date('n');
    $annee = $_GET['annee'] ?? date('Y');
    $employeId = $_GET['employe_id'] ?? '';
    
    $sql = "
        SELECT c.*, e.nom, e.prenom
        FROM conges c
        INNER JOIN employes e ON c.employe_id = e.id
        WHERE (
            (YEAR(c.date_debut) = ? AND MONTH(c.date_debut) = ?) OR
            (YEAR(c.date_fin) = ? AND MONTH(c.date_fin) = ?) OR
            (c.date_debut <= ? AND c.date_fin >= ?)
        )
    ";
    $params = [$annee, $mois, $annee, $mois];
    
    // Dates du mois pour la condition de chevauchement
    $premierJour = "$annee-" . str_pad($mois, 2, '0', STR_PAD_LEFT) . "-01";
    $dernierJour = date('Y-m-t', strtotime($premierJour));
    $params[] = $dernierJour;
    $params[] = $premierJour;
    
    if (!empty($employeId)) {
        $sql .= " AND c.employe_id = ?";
        $params[] = $employeId;
    }
    
    $sql .= " ORDER BY c.date_debut";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $conges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'conges' => $conges]);
    break;case 'get_employes_pour_soldes':
    $filters = [
        'statut' => 'actif'
    ];
    if (!empty($_GET['departement_id'])) $filters['departement_id'] = $_GET['departement_id'];
    if (!empty($_GET['type_contrat'])) $filters['type_contrat'] = $_GET['type_contrat'];
    
    $employes = $employeesManager->getAllEmployees($filters);
    echo json_encode(['success' => true, 'employes' => $employes]);
    break;

case 'initialiser_soldes_conges':
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validation = SecurityManager::validateInput($input, [
        'annee' => ['required' => true, 'type' => 'numeric'],
        'solde_annuel' => ['required' => true, 'type' => 'numeric'],
        'solde_maladie' => ['required' => true, 'type' => 'numeric']
    ]);
    
    if (!empty($validation)) {
        throw new Exception(implode(', ', $validation));
    }
    
    // Récupérer les employés concernés
    $filters = ['statut' => 'actif'];
    if (!empty($input['filtres']['departement_id'])) {
        $filters['departement_id'] = $input['filtres']['departement_id'];
    }
    if (!empty($input['filtres']['type_contrat'])) {
        $filters['type_contrat'] = $input['filtres']['type_contrat'];
    }
    
    $employes = $employeesManager->getAllEmployees($filters);
    
    // Initialisation des soldes
    $count = 0;
    foreach ($employes as $employe) {
        try {
            // Insertion ou mise à jour du solde
            $stmt = $conn->prepare("
                INSERT INTO soldes_conges (employe_id, annee, solde_annuel, solde_maladie, 
                                         solde_restant_annuel, solde_restant_maladie, date_creation)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                solde_annuel = VALUES(solde_annuel),
                solde_maladie = VALUES(solde_maladie),
                solde_restant_annuel = VALUES(solde_restant_annuel),
                solde_restant_maladie = VALUES(solde_restant_maladie),
                derniere_maj = NOW()
            ");
            
            $success = $stmt->execute([
                $employe['id'],
                $input['annee'],
                $input['solde_annuel'],
                $input['solde_maladie'],
                $input['solde_annuel'], // solde_restant initial
                $input['solde_maladie']  // solde_restant initial
            ]);
            
            if ($success) $count++;
            
        } catch (Exception $e) {
            error_log("Erreur initialisation solde employé {$employe['id']}: " . $e->getMessage());
        }
    }
    
    // Log de l'audit
    $auditManager->logPayrollAction('INIT_SOLDES_CONGES', [
        'annee' => $input['annee'],
        'employes_traites' => $count,
        'solde_annuel' => $input['solde_annuel'],
        'solde_maladie' => $input['solde_maladie']
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => "Soldes initialisés pour $count employé(s)",
        'count' => $count
    ]);
    break;
case 'get_rapport_avances_detaille':
    $debut = $_GET['debut'] ?? '';
    $fin = $_GET['fin'] ?? '';
    $statut = $_GET['statut'] ?? '';
    
    if (empty($debut) || empty($fin)) {
        throw new Exception('Dates requises');
    }
    
    $rapport = [];
    
    // Requête de base - CORRIGÉE
    $sql = "
        SELECT a.*, 
               CONCAT(e.prenom, ' ', e.nom) as employe_nom,
               e.email as employe_email,
               p.nom as poste_nom
        FROM avances_salaire a
        INNER JOIN employes e ON a.id_employe = e.id
        LEFT JOIN postes p ON e.poste_id = p.id
        WHERE DATE(a.date_demande) BETWEEN ? AND ?
    ";
    $params = [$debut, $fin];
    
    if (!empty($statut)) {
        $sql .= " AND a.statut = ?";
        $params[] = $statut;
    }
    
    $sql .= " ORDER BY a.date_demande DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rapport['avances'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques générales - CORRIGÉES
    $sqlStats = "
        SELECT 
            COUNT(*) as total_avances,
            SUM(a.montant_demande) as montant_total,
            SUM(CASE WHEN a.statut = 'rembourse' THEN a.montant_demande ELSE 0 END) as montant_rembourse,
            SUM(CASE WHEN a.statut IN ('approuve', 'en_cours') THEN a.montant_demande ELSE 0 END) as montant_restant
        FROM avances_salaire a
        WHERE DATE(a.date_demande) BETWEEN ? AND ?
    ";
    $paramsStats = [$debut, $fin];
    
    if (!empty($statut)) {
        $sqlStats .= " AND a.statut = ?";
        $paramsStats[] = $statut;
    }
    
    $stmt = $conn->prepare($sqlStats);
    $stmt->execute($paramsStats);
    $rapport['stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Top employés - CORRIGÉ
    $sqlTop = "
        SELECT 
            CONCAT(e.prenom, ' ', e.nom) as nom_complet,
            e.id as employe_id,
            COUNT(a.id) as nb_avances,
            SUM(a.montant_demande) as montant_total,
            SUM(CASE WHEN a.statut = 'rembourse' THEN a.montant_demande ELSE 0 END) as montant_rembourse,
            SUM(CASE WHEN a.statut IN ('approuve', 'en_cours') THEN a.montant_demande ELSE 0 END) as montant_restant
        FROM avances_salaire a
        INNER JOIN employes e ON a.id_employe = e.id
        WHERE DATE(a.date_demande) BETWEEN ? AND ?
    ";
    $paramsTop = [$debut, $fin];
    
    if (!empty($statut)) {
        $sqlTop .= " AND a.statut = ?";
        $paramsTop[] = $statut;
    }
    
    $sqlTop .= "
        GROUP BY e.id, e.prenom, e.nom
        ORDER BY montant_total DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($sqlTop);
    $stmt->execute($paramsTop);
    $rapport['top_employes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Évolution mensuelle (pour le graphique) - CORRIGÉE
    $sqlEvolution = "
        SELECT 
            DATE_FORMAT(a.date_demande, '%Y-%m') as mois,
            COUNT(*) as nb_avances,
            SUM(a.montant_demande) as montant_total
        FROM avances_salaire a
        WHERE DATE(a.date_demande) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(a.date_demande, '%Y-%m')
        ORDER BY mois
    ";
    
    $stmt = $conn->prepare($sqlEvolution);
    $stmt->execute([$debut, $fin]);
    $rapport['evolution_mensuelle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'rapport' => $rapport]);
    break;

case 'export_rapport_avances':
    $debut = $_GET['debut'] ?? '';
    $fin = $_GET['fin'] ?? '';
    $statut = $_GET['statut'] ?? '';
    
    if (empty($debut) || empty($fin)) {
        throw new Exception('Dates requises');
    }
    
    // Récupération des données
    $sql = "
        SELECT a.*, 
               CONCAT(e.prenom, ' ', e.nom) as employe_nom,
               e.email as employe_email,
               p.nom as poste_nom,
               d.nom as departement_nom
        FROM avances_salaire a
        INNER JOIN employes e ON a.employe_id = e.id
        LEFT JOIN postes p ON e.poste_id = p.id
        LEFT JOIN departements d ON p.departement_id = d.id
        WHERE DATE(a.date_creation) BETWEEN ? AND ?
    ";
    $params = [$debut, $fin];
    
    if (!empty($statut)) {
        $sql .= " AND a.statut = ?";
        $params[] = $statut;
    }
    
    $sql .= " ORDER BY a.date_creation DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $avances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Export CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_avances_' . $debut . '_' . $fin . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // En-têtes
    fputcsv($output, [
        'Date création',
        'Employé',
        'Email',
        'Poste',
        'Département',
        'Montant (FCFA)',
        'Motif',
        'Mode remboursement',
        'Statut',
        'Date validation',
        'Commentaire'
    ], ';');
    
    // Données
    foreach ($avances as $avance) {
        fputcsv($output, [
            date('d/m/Y', strtotime($avance['date_creation'])),
            $avance['employe_nom'],
            $avance['employe_email'],
            $avance['poste_nom'] ?: 'N/A',
            $avance['departement_nom'] ?: 'N/A',
            number_format($avance['montant'], 0, ',', ' '),
            $avance['motif'],
            $avance['mode_remboursement'] ?: 'UNIQUE',
            ucfirst(str_replace('_', ' ', $avance['statut'])),
            $avance['date_validation'] ? date('d/m/Y', strtotime($avance['date_validation'])) : '',
            $avance['commentaire'] ?: ''
        ], ';');
    }
    
    fclose($output);
    exit;

case 'export_statistiques_pdf':
    $debut = $_GET['debut'] ?? '';
    $fin = $_GET['fin'] ?? '';
    
    if (empty($debut) || empty($fin)) {
        http_response_code(400);
        echo "Paramètres manquants";
        exit;
    }
    
    // Récupération des statistiques (reprendre le code de get_statistiques_detaillees)
    $dateDebut = $debut . '-01';
    $dateFin = date('Y-m-t', strtotime($fin . '-01'));
    
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT bp.employe_id) as total_employes,
            COUNT(bp.id) as total_bulletins,
            SUM(bp.salaire_net) as masse_salariale,
            AVG(bp.salaire_net) as salaire_moyen
        FROM bulletins_paie bp
        WHERE DATE_FORMAT(CONCAT(bp.annee, '-', LPAD(bp.mois, 2, '0'), '-01'), '%Y-%m-%d') 
            BETWEEN ? AND ?
    ");
    $stmt->execute([$dateDebut, $dateFin]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Générer un PDF simple ou rediriger vers un générateur PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="statistiques_paie_' . $debut . '_' . $fin . '.pdf"');
    
    // Ici, vous pouvez intégrer une bibliothèque PDF comme TCPDF ou Dompdf
    echo "PDF generation en cours de développement";
    exit;
            case 'get_presence_stats_for_payroll':
                $employeeId = $_GET['employee_id'] ?? null;
                $month = $_GET['month'] ?? date('n');
                $year = $_GET['year'] ?? date('Y');
                
                if (!$employeeId) {
                    throw new Exception('ID employé requis');
                }
                
                $startDate = "$year-$month-01";
                $endDate = date('Y-m-t', strtotime($startDate));
                
                $presenceStats = $presenceManager->getPresenceStatistics($employeeId, $startDate, $endDate);
                echo json_encode(['success' => true, 'presence_stats' => $presenceStats]);
                break;
           case 'get_bulletin_details':
    $bulletinId = $_GET['bulletin_id'] ?? 0;
    
    try {
        $bulletin = $paieManager->getBulletinDetails($bulletinId);
        echo json_encode(['success' => true, 'bulletin' => $bulletin]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;

case 'preview_employes_masse':
    $filters = [
        'statut' => 'actif'
    ];
    if (!empty($_GET['departement_id'])) $filters['departement_id'] = $_GET['departement_id'];
    if (!empty($_GET['type_contrat'])) $filters['type_contrat'] = $_GET['type_contrat'];
    
    $mois = $_GET['mois'] ?? date('n');
    $annee = $_GET['annee'] ?? date('Y');
    $ignoreExistants = $_GET['ignorer_existants'] === 'true';
    
    $employes = $employeesManager->getAllEmployees($filters);
    
    // Vérifier quels employés ont déjà des bulletins
    foreach ($employes as &$employe) {
        $stmt = $conn->prepare("
            SELECT id FROM bulletins_paie 
            WHERE employe_id = ? AND mois = ? AND annee = ?
        ");
        $stmt->execute([$employe['id'], $mois, $annee]);
        $employe['has_bulletin'] = $stmt->fetch() ? true : false;
    }
    
    echo json_encode(['success' => true, 'employes' => $employes]);
    break;

case 'generer_bulletins_masse':
    $input = json_decode(file_get_contents('php://input'), true);
    
    $validation = SecurityManager::validateInput($input, [
        'mois' => ['required' => true, 'type' => 'numeric'],
        'annee' => ['required' => true, 'type' => 'numeric'],
        'mode_generation' => ['required' => true]
    ]);
    
    if (!empty($validation)) {
        throw new Exception(implode(', ', $validation));
    }
    
    // Récupérer les employés éligibles
    $filters = ['statut' => 'actif'];
    if (!empty($input['filtres']['departement_id'])) {
        $filters['departement_id'] = $input['filtres']['departement_id'];
    }
    if (!empty($input['filtres']['type_contrat'])) {
        $filters['type_contrat'] = $input['filtres']['type_contrat'];
    }
    
    $employes = $employeesManager->getAllEmployees($filters);
    
    $count = 0;
    $errors = [];
    
    foreach ($employes as $employe) {
        try {
            // Vérifier s'il existe déjà un bulletin
            if ($input['options']['ignorer_existants']) {
                $stmt = $conn->prepare("
                    SELECT id FROM bulletins_paie 
                    WHERE employe_id = ? AND mois = ? AND annee = ?
                ");
                $stmt->execute([$employe['id'], $input['mois'], $input['annee']]);
                if ($stmt->fetch()) {
                    continue; // Skip cet employé
                }
            }
            
            // Préparer les options de calcul
            $optionsCalcul = array_merge($input['options'], [
                'employe_id' => $employe['id'],
                'avec_presences' => $input['mode_generation'] === 'INTEGRE'
            ]);
            
            // Générer le bulletin selon le mode choisi
            if ($input['mode_generation'] === 'INTEGRE') {
                $calculData = $paieManager->calculatePayroll(
                    $employe['id'],
                    $input['mois'],
                    $input['annee'],
                    $optionsCalcul
                );
                $bulletinId = $paieManager->generateBulletin($calculData);
            } else {
                $result = $payrollCalculator->genererBulletin(
                    $employe['id'],
                    $input['mois'],
                    $input['annee'],
                    $optionsCalcul
                );
                if (!$result['success']) {
                    throw new Exception($result['error'] ?? 'Erreur génération bulletin');
                }
            }
            
            $count++;
            
        } catch (Exception $e) {
            $errors[] = "Employé {$employe['prenom']} {$employe['nom']}: " . $e->getMessage();
            error_log("Erreur génération masse employé {$employe['id']}: " . $e->getMessage());
        }
    }
    
    // Log de l'audit
    $auditManager->logPayrollAction('GENERATE_BULLETINS_MASSE', [
        'periode' => $input['mois'] . '/' . $input['annee'],
        'employes_traites' => $count,
        'erreurs' => count($errors),
        'mode' => $input['mode_generation']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => "Génération terminée",
        'count' => $count,
        'errors' => $errors
    ]);
    break;
            // === GÉNÉRATION BULLETINS AVEC PRÉSENCES ===
            case 'generer_bulletin_integre':
                $input = json_decode(file_get_contents('php://input'), true);
                
                // Validation des données
                $validation = SecurityManager::validateInput($input, [
                    'employe_id' => ['required' => true, 'type' => 'numeric'],
                    'mois' => ['required' => true, 'type' => 'numeric'],
                    'annee' => ['required' => true, 'type' => 'numeric']
                ]);
                
                if (!empty($validation)) {
                    throw new Exception(implode(', ', $validation));
                }
                
                // Calcul avec présences
                $calculData = $paieManager->calculatePayroll(
                    $input['employe_id'],
                    $input['mois'],
                    $input['annee'],
                    $input
                );
                
                // Génération bulletin
                $bulletinId = $paieManager->generateBulletin($calculData);
                
                // Audit
                $auditManager->logPayrollAction('GENERATE_BULLETIN_INTEGRE', [
                    'bulletin_id' => $bulletinId,
                    'employe_id' => $input['employe_id'],
                    'periode' => $input['mois'] . '/' . $input['annee'],
                    'avec_presences' => true
                ]);
                
                echo json_encode([
                    'success' => true, 
                    'bulletin_id' => $bulletinId,
                    'calcul_data' => $calculData
                ]);
                break;
                
            // === ACTIONS BULLETINS EXISTANTES ===
            case 'get_bulletins':
    $bulletins = $paieManager->getBulletins($_GET);
    
    // Debug log pour vérifier les données
    error_log("Nombre de bulletins retournés: " . count($bulletins));
    if (!empty($bulletins)) {
        error_log("Structure premier bulletin: " . json_encode($bulletins[0]));
    }
    
    echo json_encode(['success' => true, 'bulletins' => $bulletins]);
    break;
                
            case 'generer_bulletin':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $payrollCalculator->genererBulletin(
                    $data['employe_id'],
                    $data['mois'],
                    $data['annee'],
                    [
                        'heures_supplementaires' => $data['heures_supplementaires'] ?? 0,
                        'jours_absence' => $data['jours_absence'] ?? 0,
                        'commentaires' => $data['commentaires'] ?? ''
                    ]
                );
                echo json_encode($result);
                break;
                
            case 'modifier_bulletin':
    $input = json_decode(file_get_contents('php://input'), true);
    $bulletinId = $input['bulletin_id'];
    
    $stmt = $conn->prepare("
        UPDATE bulletins_paie SET
            salaire_base = ?, heures_supplementaires = ?, 
            jours_absences = ?, montant_avances_remboursees = ?, 
            total_primes = ?
        WHERE id_bulletin = ? AND statut = 'brouillon'
    ");
    
    $success = $stmt->execute([
        $input['salaire_base'], 
        $input['heures_supplementaires'],
        $input['jours_absences'], 
        $input['avances'],
        ($input['prime_transport'] ?? 0) + ($input['prime_logement'] ?? 0),
        $bulletinId
    ]);
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la modification']);
    }
    break;
                
            case 'supprimer_bulletin':
    $data = json_decode(file_get_contents('php://input'), true);
    $bulletinId = $data['bulletin_id'];
    
    $stmt = $conn->prepare("DELETE FROM bulletins_paie WHERE id_bulletin = ? AND statut = 'brouillon'");
    $success = $stmt->execute([$bulletinId]);
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']);
    }
    break;
    case 'voir_bulletin':
    $id = $_GET['id'] ?? 0;
    
    try {
        $bulletin = $paieManager->getBulletinDetails($id);
        
        if ($bulletin) {
            // Créer une instance du générateur PDF
            $pdfGenerator = new BulletinPDFGenerateur([
                'nom' => 'Restaurant Le Savoureux',
                'adresse' => '123 Avenue de la République, Dakar, Sénégal',
                'telephone' => '+221 33 123 45 67',
                'email' => 'contact@lesavoureux.sn',
                'ninea' => '123456789'
            ]);
            
            // Afficher le PDF dans le navigateur (inline)
            $pdfGenerator->afficherBulletin($bulletin);
            
        } else {
            http_response_code(404);
            echo "Bulletin non trouvé";
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Erreur affichage bulletin: " . $e->getMessage());
        echo "Erreur lors de l'affichage du PDF: " . $e->getMessage();
    }
    exit;
    case 'valider_bulletin':
    $input = json_decode(file_get_contents('php://input'), true);
    $bulletinId = $input['bulletin_id'];
    
    try {
        $stmt = $conn->prepare("
            UPDATE bulletins_paie 
            SET statut = 'valide', date_validation = NOW() 
            WHERE id_bulletin = ? AND statut = 'brouillon'
        ");
        $success = $stmt->execute([$bulletinId]);
        
        if ($success && $stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Bulletin validé avec succès']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Bulletin non trouvé ou déjà validé']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la validation: ' . $e->getMessage()]);
    }
    break;
    case 'creer_conge':
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validation manuelle des données
    $erreurs = [];
    
    if (empty($data['employe_id']) || !is_numeric($data['employe_id'])) {
        $erreurs[] = 'ID employé invalide';
    }
    
    if (empty($data['type_conge'])) {
        $erreurs[] = 'Type de congé requis';
    }
    
    if (empty($data['date_debut']) || !strtotime($data['date_debut'])) {
        $erreurs[] = 'Date de début invalide';
    }
    
    if (empty($data['date_fin']) || !strtotime($data['date_fin'])) {
        $erreurs[] = 'Date de fin invalide';
    }
    
    // Vérifier si l'employé existe
    if (empty($erreurs)) {
        $stmt = $conn->prepare("SELECT id FROM employes WHERE id = ? AND statut = 'actif'");
        $stmt->execute([$data['employe_id']]);
        if (!$stmt->fetch()) {
            $erreurs[] = 'Employé non trouvé ou inactif';
        }
    }
    
    if (!empty($erreurs)) {
        echo json_encode(['success' => false, 'error' => implode(', ', $erreurs)]);
        exit;
    }
    
    try {
        // Calcul automatique du nombre de jours
        $debut = new DateTime($data['date_debut']);
        $fin = new DateTime($data['date_fin']);
        
        // Vérifier que la date de fin est après la date de début
        if ($fin < $debut) {
            throw new Exception('La date de fin doit être postérieure à la date de début');
        }
        
        // Calculer le nombre de jours (inclus week-ends pour l'instant)
        $nbJours = $debut->diff($fin)->days + 1;
        
        // Insertion en base
        $stmt = $conn->prepare("
            INSERT INTO conges (employe_id, type, date_debut, date_fin, nb_jours, motif, statut, date_creation)
            VALUES (?, ?, ?, ?, ?, ?, 'en_attente', NOW())
        ");
        
        $success = $stmt->execute([
            $data['employe_id'],
            $data['type_conge'],
            $data['date_debut'],
            $data['date_fin'],
            $nbJours,
            $data['motif'] ?? ''
        ]);
        
        if ($success) {
            // Log de l'action si vous avez une table de logs
            try {
                $stmt_log = $conn->prepare("
                    INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details, created_at)
                    VALUES ('CREATE_CONGE', 'conges', ?, ?, NOW())
                ");
                $stmt_log->execute([
                    $conn->lastInsertId(),
                    json_encode([
                        'employe_id' => $data['employe_id'],
                        'type' => $data['type_conge'],
                        'nb_jours' => $nbJours
                    ])
                ]);
            } catch (Exception $e) {
                // Log non critique, on continue
                error_log("Erreur log congé: " . $e->getMessage());
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Demande de congé créée avec succès',
                'id' => $conn->lastInsertId(),
                'nb_jours' => $nbJours
            ]);
        } else {
            throw new Exception('Erreur lors de la création de la demande');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;
    case 'get_solde_conges':
    $employeId = $_GET['employe_id'] ?? 0;
    
    if (!$employeId) {
        echo json_encode(['success' => false, 'error' => 'ID employé requis']);
        exit;
    }
    
    try {
        // Récupérer le solde de congés pour l'année en cours
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(solde_annuel, 25) as annuel,
                COALESCE(solde_maladie, 10) as maladie,
                COALESCE(solde_restant_annuel, 25) as restant_annuel,
                COALESCE(solde_restant_maladie, 10) as restant_maladie,
                DATE(derniere_maj) as derniere_maj
            FROM soldes_conges 
            WHERE employe_id = ? AND annee = ?
        ");
        $stmt->execute([$employeId, date('Y')]);
        $solde = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$solde) {
            // Créer un solde par défaut si aucun n'existe
            $stmt_create = $conn->prepare("
                INSERT INTO soldes_conges (employe_id, annee, solde_annuel, solde_maladie, solde_restant_annuel, solde_restant_maladie, date_creation)
                VALUES (?, ?, 25, 10, 25, 10, NOW())
                ON DUPLICATE KEY UPDATE derniere_maj = NOW()
            ");
            $stmt_create->execute([$employeId, date('Y')]);
            
            $solde = [
                'annuel' => 25,
                'maladie' => 10,
                'restant_annuel' => 25,
                'restant_maladie' => 10,
                'derniere_maj' => date('Y-m-d')
            ];
        }
        
        echo json_encode(['success' => true, 'solde' => $solde]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;
    case 'get_conge_details':
    $congeId = $_GET['conge_id'] ?? 0;
    
    if (!$congeId) {
        echo json_encode(['success' => false, 'error' => 'ID congé requis']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT c.*, e.nom, e.prenom, e.email,
                   p.nom as poste_nom
            FROM conges c
            INNER JOIN employes e ON c.employe_id = e.id
            LEFT JOIN postes p ON e.poste_id = p.id
            WHERE c.id = ?
        ");
        $stmt->execute([$congeId]);
        $conge = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conge) {
            echo json_encode(['success' => false, 'error' => 'Congé non trouvé']);
            exit;
        }
        
        echo json_encode(['success' => true, 'conge' => $conge]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;
    case 'get_conges_historique':
    $filters = [
        'debut' => $_GET['debut'] ?? '',
        'fin' => $_GET['fin'] ?? '',
        'statut' => $_GET['statut'] ?? '',
        'employe_id' => $_GET['employe_id'] ?? ''
    ];
    
    $sql = "
        SELECT c.*, e.nom, e.prenom,
               CONCAT(e.prenom, ' ', e.nom) as employe_nom,
               p.nom as poste_nom
        FROM conges c
        INNER JOIN employes e ON c.employe_id = e.id
        LEFT JOIN postes p ON e.poste_id = p.id
        WHERE 1=1
    ";
    $params = [];
    
    if (!empty($filters['debut'])) {
        $sql .= " AND DATE(c.date_creation) >= ?";
        $params[] = $filters['debut'];
    }
    
    if (!empty($filters['fin'])) {
        $sql .= " AND DATE(c.date_creation) <= ?";
        $params[] = $filters['fin'];
    }
    
    if (!empty($filters['statut'])) {
        $sql .= " AND c.statut = ?";
        $params[] = $filters['statut'];
    }
    
    if (!empty($filters['employe_id'])) {
        $sql .= " AND c.employe_id = ?";
        $params[] = $filters['employe_id'];
    }
    
    $sql .= " ORDER BY c.date_creation DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $conges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'conges' => $conges]);
    break;
    case 'telecharger_bulletin':
    $id = $_GET['id'] ?? 0;
    
    try {
        $bulletin = $paieManager->getBulletinDetails($id);
        
        if ($bulletin) {
            // Créer une instance du générateur PDF
            $pdfGenerator = new BulletinPDFGenerateur([
                'nom' => 'Restaurant Mulho',
                'adresse' => '123 Avenue de la République, Dakar, Sénégal',
                'telephone' => '+221 78 730 06',
                'email' => 'mulhomabiala29@gmail.com',
                'ninea' => '123456789'
            ]);
            
            // Télécharger le PDF (attachment)
            $pdfGenerator->telechargerBulletin($bulletin);
            
        } else {
            http_response_code(404);
            echo "Bulletin non trouvé";
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Erreur téléchargement bulletin: " . $e->getMessage());
        echo "Erreur lors de la génération du PDF: " . $e->getMessage();
    }
    exit;
                
            case 'export_csv':
                $mois = $_GET['mois'] ?? date('n');
                $annee = $_GET['annee'] ?? date('Y');
                
                $stmt = $conn->prepare("
                    SELECT bp.*, e.nom, e.prenom, p.nom as poste_nom
                    FROM bulletins_paie bp
                    INNER JOIN employes e ON bp.employe_id = e.id
                    LEFT JOIN postes p ON e.poste_id = p.id
                    WHERE bp.mois = ? AND bp.annee = ?
                    ORDER BY e.nom, e.prenom
                ");
                $stmt->execute([$mois, $annee]);
                $bulletins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="bulletins_' . $mois . '_' . $annee . '.csv"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, [
                    'Employé', 'Poste', 'Salaire Base', 'Heures Sup.', 'Primes', 
                    'Avances', 'Salaire Brut', 'Salaire Net', 'Statut'
                ]);
                
                foreach ($bulletins as $bulletin) {
                    fputcsv($output, [
                        $bulletin['prenom'] . ' ' . $bulletin['nom'],
                        $bulletin['poste_nom'],
                        $bulletin['salaire_base'],
                        $bulletin['heures_supplementaires'],
                        $bulletin['total_primes'],
                        $bulletin['total_avances'],
                        $bulletin['salaire_brut'],
                        $bulletin['salaire_net'],
                        $bulletin['statut']
                    ]);
                }
                fclose($output);
                exit;
                
            // === GESTION CONGÉS ===
            case 'creer_conge':
                $data = json_decode(file_get_contents('php://input'), true);
                
                // Validation des données
                $validation = SecurityManager::validateInput($data, [
                    'employe_id' => ['required' => true, 'type' => 'numeric'],
                    'type_conge' => ['required' => true],
                    'date_debut' => ['required' => true, 'type' => 'date'],
                    'date_fin' => ['required' => true, 'type' => 'date']
                ]);
                
                if (!empty($validation)) {
                    throw new Exception(implode(', ', $validation));
                }
                
                // Calcul automatique du nombre de jours
                $debut = new DateTime($data['date_debut']);
                $fin = new DateTime($data['date_fin']);
                $nbJours = $debut->diff($fin)->days + 1;
                
                // Insertion en base
                $stmt = $conn->prepare("
                    INSERT INTO conges (employe_id, type, date_debut, date_fin, nb_jours, motif, statut, date_creation)
                    VALUES (?, ?, ?, ?, ?, ?, 'en_attente', NOW())
                ");
                
                $success = $stmt->execute([
                    $data['employe_id'],
                    $data['type_conge'],
                    $data['date_debut'],
                    $data['date_fin'],
                    $nbJours,
                    $data['motif'] ?? ''
                ]);
                
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Demande de congé créée']);
                } else {
                    throw new Exception('Erreur lors de la création de la demande');
                }
                break;
                
            case 'valider_conge':
                $data = json_decode(file_get_contents('php://input'), true);
                
                $validation = SecurityManager::validateInput($data, [
                    'id_conge' => ['required' => true, 'type' => 'numeric'],
                    'statut' => ['required' => true]
                ]);
                
                if (!empty($validation)) {
                    throw new Exception(implode(', ', $validation));
                }
                
                $stmt = $conn->prepare("
                    UPDATE conges SET statut = ?, commentaire = ?, date_validation = NOW()
                    WHERE id = ?
                ");
                
                $success = $stmt->execute([
                    $data['statut'],
                    $data['commentaire'] ?? '',
                    $data['id_conge']
                ]);
                
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Congé ' . $data['statut']]);
                } else {
                    throw new Exception('Erreur lors de la validation');
                }
                break;
                
            case 'get_solde_conges':
                $employeId = $_GET['employe_id'];
                
                // Requête pour récupérer le solde (à adapter selon votre structure de BDD)
                $stmt = $conn->prepare("
                    SELECT 
                        COALESCE(solde_annuel, 25) as annuel,
                        COALESCE(solde_maladie, 0) as maladie,
                        DATE(derniere_maj) as derniere_maj
                    FROM soldes_conges 
                    WHERE employe_id = ?
                ");
                $stmt->execute([$employeId]);
                $solde = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$solde) {
                    $solde = ['annuel' => 25, 'maladie' => 0, 'derniere_maj' => date('Y-m-d')];
                }
                
                echo json_encode(['success' => true, 'solde' => $solde]);
                break;
                
           case 'creer_avance':
    $data = json_decode(file_get_contents('php://input'), true);
    
    $validation = SecurityManager::validateInput($data, [
        'employe_id' => ['required' => true, 'type' => 'numeric'],
        'montant' => ['required' => true, 'type' => 'numeric'],
        'motif' => ['required' => true]
    ]);
    
    if (!empty($validation)) {
        throw new Exception(implode(', ', $validation));
    }
    
    // Adapter les noms de colonnes à votre structure de table
    $stmt = $conn->prepare("
        INSERT INTO avances_salaire (id_employe, montant_demande, motif, statut, 
        nb_mensualites, date_demande, demande_par)
        VALUES (?, ?, ?, 'en_attente', ?, NOW(), ?)
    ");
    
    // Calculer le nombre de mensualités selon le mode
    $nbMensualites = 1; // Par défaut pour UNIQUE
    $mode = $data['mode_remboursement'] ?? 'UNIQUE';
    
    if ($mode !== 'UNIQUE') {
        $nbMensualites = (int) str_replace('MENSUEL_', '', $mode);
    }
    
    $success = $stmt->execute([
        $data['employe_id'],
        $data['montant'],
        $data['motif'],
        $nbMensualites,
        $data['employe_id'] // demande_par = même employé qui fait la demande
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Demande d\'avance créée']);
    } else {
        throw new Exception('Erreur lors de la création de la demande');
    }
    break;
                
           case 'valider_avance':
    $data = json_decode(file_get_contents('php://input'), true);
    
    $validation = SecurityManager::validateInput($data, [
        'id_avance' => ['required' => true, 'type' => 'numeric'],
        'statut' => ['required' => true]
    ]);
    
    if (!empty($validation)) {
        throw new Exception(implode(', ', $validation));
    }
    
    $stmt = $conn->prepare("
        UPDATE avances_salaire 
        SET statut = ?, commentaire_validation = ?, date_validation = NOW(), valide_par = ?
        WHERE id = ?
    ");
    
    $success = $stmt->execute([
        $data['statut'],
        $data['commentaire'] ?? '',
        1, // ID de l'admin qui valide - à adapter selon votre système
        $data['id_avance']
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Avance ' . $data['statut']]);
    } else {
        throw new Exception('Erreur lors de la validation');
    }
    break;
                
            // === GESTION PRIMES ===
           case 'attribuer_prime':
    $data = json_decode(file_get_contents('php://input'), true);
    
    $validation = SecurityManager::validateInput($data, [
        'type_attribution' => ['required' => true],
        'type_prime' => ['required' => true],
        'montant' => ['required' => true, 'type' => 'numeric'],
        'periode' => ['required' => true]
    ]);
    
    if (!empty($validation)) {
        throw new Exception(implode(', ', $validation));
    }
    
    // D'abord, convertir le type de prime en ID
    $typePrimeMapping = [
        'PERFORMANCE' => 1,
        'PRESENCE' => 2,
        'ANCIENNETE' => 3,
        'OBJECTIF' => 4,
        'EXCEPTIONNELLE' => 5,
        'TRANSPORT' => 6,
        'REPAS' => 7
    ];
    
    $idTypePrime = $typePrimeMapping[$data['type_prime']] ?? 1;
    
    // Déterminer les employés concernés
    $employes_cibles = [];
    switch ($data['type_attribution']) {
        case 'INDIVIDUEL':
            if (empty($data['employe_id'])) {
                throw new Exception('ID employé requis pour attribution individuelle');
            }
            $employes_cibles = [$data['employe_id']];
            break;
            
        case 'DEPARTEMENT':
            if (empty($data['departement'])) {
                throw new Exception('Département requis');
            }
            $stmt = $conn->prepare("
                SELECT e.id FROM employes e
                INNER JOIN postes p ON e.poste_id = p.id
                INNER JOIN departements d ON p.departement_id = d.id
                WHERE d.nom = ? AND e.statut = 'actif'
            ");
            $stmt->execute([$data['departement']]);
            $employes_cibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
            
        case 'TOUS':
            $stmt = $conn->query("SELECT id FROM employes WHERE statut = 'actif'");
            $employes_cibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
    }
    
    if (empty($employes_cibles)) {
        throw new Exception('Aucun employé trouvé pour cette attribution');
    }
    
    // Insertion des primes avec la bonne structure
    $periode_parts = explode('-', $data['periode']);
    $mois = (int)$periode_parts[1];
    $annee = (int)$periode_parts[0];
    
    $stmt = $conn->prepare("
        INSERT INTO primes_employes (id_employe, id_type_prime, mois, annee, montant, 
                                    criteres_performance, valide, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
    ");
    
    $count = 0;
    foreach ($employes_cibles as $employe_id) {
        $success = $stmt->execute([
            $employe_id,
            $idTypePrime,
            $mois,
            $annee,
            $data['montant'],
            $data['justification'] ?? ''
        ]);
        if ($success) $count++;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "Prime attribuée à $count employé(s)",
        'count' => $count
    ]);
    break;
                if (empty($employes_cibles)) {
                    throw new Exception('Aucun employé trouvé pour cette attribution');
                }
                
                // Insertion des primes
                $periode_parts = explode('-', $data['periode']);
                $mois = (int)$periode_parts[1];
                $annee = (int)$periode_parts[0];
                
                $stmt = $conn->prepare("
                    INSERT INTO primes_employes (employe_id, type, montant, mois, annee, 
                                                description, valide, date_creation)
                    VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
                ");
                
                $count = 0;
                foreach ($employes_cibles as $employe_id) {
                    $success = $stmt->execute([
                        $employe_id,
                        $data['type_prime'],
                        $data['montant'],
                        $mois,
                        $annee,
                        $data['justification'] ?? ''
                    ]);
                    if ($success) $count++;
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Prime attribuée à $count employé(s)",
                    'count' => $count
                ]);
                break;
                
         case 'valider_prime':
    $data = json_decode(file_get_contents('php://input'), true);
    
    $validation = SecurityManager::validateInput($data, [
        'id_prime' => ['required' => true, 'type' => 'numeric']
    ]);
    
    if (!empty($validation)) {
        throw new Exception(implode(', ', $validation));
    }
    
    $statut = $data['statut'] ?? 'valide';
    $valide = ($statut === 'valide') ? 1 : 0;
    
    $stmt = $conn->prepare("
        UPDATE primes_employes 
        SET valide = ?, commentaire = ?, date_validation = NOW(), valide_par = ?
        WHERE id = ?
    ");
    
    $success = $stmt->execute([
        $valide,
        $data['commentaire'] ?? '',
        1, // ID de l'admin - à adapter selon votre système
        $data['id_prime']
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Prime ' . $statut]);
    } else {
        throw new Exception('Erreur lors de la validation');
    }
    break;
    case 'get_primes_historique':
    $filters = [
        'debut' => $_GET['debut'] ?? '',
        'fin' => $_GET['fin'] ?? '',
        'statut' => $_GET['statut'] ?? '',
        'employe_id' => $_GET['employe_id'] ?? ''
    ];
    
    $sql = "
        SELECT p.*, e.nom, e.prenom,
               CONCAT(e.prenom, ' ', e.nom) as employe_nom,
               tp.nom as type_prime_nom,
               po.nom as poste_nom
        FROM primes_employes p
        INNER JOIN employes e ON p.id_employe = e.id
        LEFT JOIN types_primes tp ON p.id_type_prime = tp.id
        LEFT JOIN postes po ON e.poste_id = po.id
        WHERE 1=1
    ";
    $params = [];
    
    if (!empty($filters['debut'])) {
        $sql .= " AND DATE(p.created_at) >= ?";
        $params[] = $filters['debut'];
    }
    
    if (!empty($filters['fin'])) {
        $sql .= " AND DATE(p.created_at) <= ?";
        $params[] = $filters['fin'];
    }
    
    if (!empty($filters['statut'])) {
        if ($filters['statut'] === 'valide') {
            $sql .= " AND p.valide = 1";
        } else if ($filters['statut'] === 'en_attente') {
            $sql .= " AND p.valide = 0";
        }
    }
    
    if (!empty($filters['employe_id'])) {
        $sql .= " AND p.id_employe = ?";
        $params[] = $filters['employe_id'];
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $primes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'primes' => $primes]);
    break;
    case 'get_prime_details':
    $primeId = $_GET['prime_id'] ?? 0;
    
    if (!$primeId) {
        echo json_encode(['success' => false, 'error' => 'ID prime requis']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT p.*, e.nom, e.prenom, e.email,
                   po.nom as poste_nom,
                   tp.nom as type_prime_nom, tp.description as type_prime_description,
                   v.nom as valideur_nom, v.prenom as valideur_prenom
            FROM primes_employes p
            INNER JOIN employes e ON p.id_employe = e.id
            LEFT JOIN postes po ON e.poste_id = po.id
            LEFT JOIN types_primes tp ON p.id_type_prime = tp.id
            LEFT JOIN employes v ON p.valide_par = v.id
            WHERE p.id = ?
        ");
        $stmt->execute([$primeId]);
        $prime = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prime) {
            echo json_encode(['success' => false, 'error' => 'Prime non trouvée']);
            exit;
        }
        
        echo json_encode(['success' => true, 'prime' => $prime]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;
                
   case 'get_presences_jour':
    $date = $_GET['date'] ?? date('Y-m-d');
    
    try {
        $stmt = $conn->prepare("
            SELECT p.*, e.id as employe_id, e.nom, e.prenom, e.statut, 
                   po.nom as poste_nom,
                   TIME(p.heure_arrivee) as heure_arrivee_format,
                   TIME(p.heure_depart) as heure_depart_format
            FROM employes e
            LEFT JOIN presences p ON e.id = p.employe_id AND DATE(p.heure_arrivee) = ?
            LEFT JOIN postes po ON e.poste_id = po.id
            WHERE e.statut = 'actif'
            ORDER BY e.nom, e.prenom
        ");
        
        $stmt->execute([$date]);
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $presences = [];
        foreach ($resultats as $resultat) {
            $horairePlanifie = getHorairesEmploye($conn, $resultat['employe_id'], $date);
            $statutPresence = determinerStatutPresence($resultat, $horairePlanifie);
            
            $presences[] = [
                'employe_id' => $resultat['employe_id'],
                'nom' => $resultat['nom'],
                'prenom' => $resultat['prenom'],
                'poste_nom' => $resultat['poste_nom'],
                'statut' => $resultat['statut'],
                'statut_presence' => $statutPresence,
                'heure_arrivee_format' => $resultat['heure_arrivee_format'],
                'heure_depart_format' => $resultat['heure_depart_format'],
                'heure_debut_prevue' => $horairePlanifie['heure_debut'],
                'heure_fin_prevue' => $horairePlanifie['heure_fin'],
                'est_programme' => $horairePlanifie['est_programme']
            ];
        }
        
        echo json_encode(['success' => true, 'presences' => $presences]);
    } catch (Exception $e) {
        error_log("Erreur récupération présences: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;

// 5. MODIFICATION DU CASE 'get_details_presence_employe'
case 'get_details_presence_employe':
    $employeId = $_GET['employe_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');
    
    if (!$employeId) {
        echo json_encode(['success' => false, 'error' => 'ID employé requis']);
        exit;
    }
    
    try {
        // Informations de base de l'employé avec heures totales du poste
        $stmt = $conn->prepare("
            SELECT e.*, p.nom as poste_nom, p.heures_semaine, p.heures_mois, 
                   d.nom as departement_nom
            FROM employes e
            LEFT JOIN postes p ON e.poste_id = p.id
            LEFT JOIN departements d ON p.departement_id = d.id
            WHERE e.id = ?
        ");
        $stmt->execute([$employeId]);
        $employe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employe) {
            throw new Exception('Employé non trouvé');
        }
        
        // Horaire planifié pour ce jour
        $horairePlanifie = getHorairesEmploye($conn, $employeId, $date);
        
        // Présence du jour spécifique
        $stmt = $conn->prepare("
            SELECT *,
                   TIME(heure_arrivee) as heure_arrivee_format,
                   TIME(heure_depart) as heure_depart_format,
                   CASE 
                       WHEN heure_arrivee IS NOT NULL AND heure_depart IS NOT NULL THEN
                           TIMESTAMPDIFF(MINUTE, heure_arrivee, heure_depart) / 60.0
                       ELSE 0
                   END as heures_travaillees
            FROM presences
            WHERE employe_id = ? AND DATE(heure_arrivee) = ?
        ");
        $stmt->execute([$employeId, $date]);
        $presenceJour = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($presenceJour) {
            $presenceJour['statut_presence'] = determinerStatutPresence($presenceJour, $horairePlanifie);
        }
        
        // Statistiques du mois avec planification
        $mois = date('n', strtotime($date));
        $annee = date('Y', strtotime($date));
        $statsMois = calculerHeuresParRapportPlanification($conn, $employeId, $mois, $annee);
        
        echo json_encode([
            'success' => true,
            'employe' => $employe,
            'presence_jour' => $presenceJour,
            'horaire_planifie' => $horairePlanifie,
            'stats_mois' => $statsMois
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur détails présence employé: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;
            case 'generer_primes_presence':
                $data = json_decode(file_get_contents('php://input'), true);
                
                $validation = SecurityManager::validateInput($data, [
                    'mois' => ['required' => true, 'type' => 'numeric'],
                    'annee' => ['required' => true, 'type' => 'numeric'],
                    'montant_presence' => ['required' => true, 'type' => 'numeric'],
                    'seuil_presence' => ['required' => true, 'type' => 'numeric']
                ]);
                
                if (!empty($validation)) {
                    throw new Exception(implode(', ', $validation));
                }
                
                // Simulation de génération de primes de présence
                // À adapter selon votre logique métier
                echo json_encode([
                    'success' => true, 
                    'message' => 'Fonctionnalité en cours de développement',
                    'count' => 0
                ]);
                break;
                
            // === GESTION POSTES ===
            case 'get_postes':
                $filters = [];
                if (isset($_GET['departement_id'])) $filters['departement_id'] = $_GET['departement_id'];
                if (isset($_GET['type_contrat'])) $filters['type_contrat'] = $_GET['type_contrat'];
                
                $postes = $postesManager->getAllPostes($filters);
                echo json_encode(['success' => true, 'postes' => $postes]);
                break;
                
            case 'get_departements':
                $departements = $postesManager->getDepartements();
                echo json_encode(['success' => true, 'departements' => $departements]);
                break;
                
            case 'get_contract_types':
                $contractTypes = $postesManager->getContractTypes();
                echo json_encode(['success' => true, 'contract_types' => $contractTypes]);
                break;

                case 'get_avances_historique':
    $filters = [
        'debut' => $_GET['debut'] ?? '',
        'fin' => $_GET['fin'] ?? '',
        'statut' => $_GET['statut'] ?? '',
        'employe_id' => $_GET['employe_id'] ?? ''
    ];
    
    $sql = "
        SELECT a.*, e.nom, e.prenom,
               CONCAT(e.prenom, ' ', e.nom) as employe_nom,
               p.nom as poste_nom
        FROM avances_salaire a
        INNER JOIN employes e ON a.id_employe = e.id
        LEFT JOIN postes p ON e.poste_id = p.id
        WHERE 1=1
    ";
    $params = [];
    
    if (!empty($filters['debut'])) {
        $sql .= " AND DATE(a.date_demande) >= ?";
        $params[] = $filters['debut'];
    }
    
    if (!empty($filters['fin'])) {
        $sql .= " AND DATE(a.date_demande) <= ?";
        $params[] = $filters['fin'];
    }
    
    if (!empty($filters['statut'])) {
        $sql .= " AND a.statut = ?";
        $params[] = $filters['statut'];
    }
    
    if (!empty($filters['employe_id'])) {
        $sql .= " AND a.id_employe = ?";
        $params[] = $filters['employe_id'];
    }
    
    $sql .= " ORDER BY a.date_demande DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $avances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'avances' => $avances]);
    break;
    case 'get_avance_details':
    $avanceId = $_GET['avance_id'] ?? 0;
    
    if (!$avanceId) {
        echo json_encode(['success' => false, 'error' => 'ID avance requis']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT a.*, e.nom, e.prenom, e.email,
                   p.nom as poste_nom,
                   v.nom as valideur_nom, v.prenom as valideur_prenom
            FROM avances_salaire a
            INNER JOIN employes e ON a.id_employe = e.id
            LEFT JOIN postes p ON e.poste_id = p.id
            LEFT JOIN employes v ON a.valide_par = v.id
            WHERE a.id = ?
        ");
        $stmt->execute([$avanceId]);
        $avance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$avance) {
            echo json_encode(['success' => false, 'error' => 'Avance non trouvée']);
            exit;
        }
        
        echo json_encode(['success' => true, 'avance' => $avance]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;
                
            // === RAPPORTS ET STATISTIQUES ===
            case 'get_statistiques':
                $mois = $_GET['mois'] ?? date('n');
                $annee = $_GET['annee'] ?? date('Y');
                
                $stats = $paieManager->getPayrollStatistics(['mois' => $mois, 'annee' => $annee]);
                echo json_encode(['success' => true, 'stats' => $stats]);
                break;
                
            case 'get_rapport_avances':
                $filters = [
                    'debut' => $_GET['debut'] ?? '',
                    'fin' => $_GET['fin'] ?? '',
                    'statut' => $_GET['statut'] ?? ''
                ];
                
                $sql = "
                    SELECT a.*, e.nom, e.prenom,
                           CONCAT(e.prenom, ' ', e.nom) as employe_nom
                    FROM avances_salaire a
                    INNER JOIN employes e ON a.employe_id = e.id
                    WHERE 1=1
                ";
                $params = [];
                
                if (!empty($filters['debut'])) {
                    $sql .= " AND DATE(a.date_creation) >= ?";
                    $params[] = $filters['debut'];
                }
                
                if (!empty($filters['fin'])) {
                    $sql .= " AND DATE(a.date_creation) <= ?";
                    $params[] = $filters['fin'];
                }
                
                if (!empty($filters['statut'])) {
                    $sql .= " AND a.statut = ?";
                    $params[] = $filters['statut'];
                }
                
                $sql .= " ORDER BY a.date_creation DESC LIMIT 100";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $avances = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'avances' => $avances]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
        }
    } catch (Exception $e) {
        error_log("Erreur action {$_GET['action']}: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Chargement des données pour l'interface
try {
    // Employés actifs avec toutes leurs informations
    $employes = $employeesManager->getAllEmployees(['statut' => 'actif']);
    
    // Bulletins du mois en cours
    $bulletins = $paieManager->getBulletins([
        'mois' => date('n'),
        'annee' => date('Y')
    ]);
    
    // Statistiques générales
    $stats = [];
    $statsEmployes = $employeesManager->getEmployeeStatistics();
    $statsPaie = $paieManager->getPayrollStatistics();
    
    $stats['employes_actifs'] = $statsEmployes['total_actifs'] ?? 0;
    $stats['bulletins_mois'] = count($bulletins);
    $stats['masse_salariale'] = 0;
    foreach ($bulletins as $bulletin) {
        $stats['masse_salariale'] += $bulletin['salaire_net'] ?? 0;
    }

    
    
    // Demandes en attente
    $conges_attente = [];
    $avances_attente = [];
    $primes_attente = [];
    
    // Récupération des congés en attente
    try {
        $stmt = $conn->prepare("
            SELECT c.*, e.nom, e.prenom 
            FROM conges c 
            INNER JOIN employes e ON c.employe_id = e.id 
            WHERE c.statut = 'en_attente' 
            ORDER BY c.date_creation DESC
        ");
        $stmt->execute();
        $conges_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur récupération congés: " . $e->getMessage());
    }
    
 try {
    $stmt = $conn->prepare("
        SELECT a.*, e.nom, e.prenom 
        FROM avances_salaire a 
        INNER JOIN employes e ON a.id_employe = e.id  
        WHERE a.statut = 'en_attente' 
        ORDER BY a.date_demande DESC                  
    ");
    $stmt->execute();
    $avances_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur récupération avances: " . $e->getMessage());
}
    
   // Récupération des primes en attente
try {
    $stmt = $conn->prepare("
        SELECT p.*, e.nom, e.prenom, tp.nom as type_prime_nom
        FROM primes_employes p 
        INNER JOIN employes e ON p.id_employe = e.id
        LEFT JOIN types_primes tp ON p.id_type_prime = tp.id
        WHERE p.valide = 0 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $primes_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur récupération primes: " . $e->getMessage());
}
    $stats['conges_attente'] = count($conges_attente);
    $stats['avances_attente'] = count($avances_attente);
    $stats['primes_attente'] = count($primes_attente);
    
    // Postes et départements pour les filtres
    $postes = $postesManager->getAllPostes();
    $departements = $postesManager->getDepartements();
    
    // Token CSRF
    $csrf_token = SecurityManager::generateCSRFToken();
    
} catch (Exception $e) {
    error_log("Erreur chargement données: " . $e->getMessage());
    
    // Données par défaut en cas d'erreur
    $employes = [];
    $bulletins = [];
    $stats = [
        'employes_actifs' => 0,
        'bulletins_mois' => 0,
        'masse_salariale' => 0,
        'conges_attente' => 0,
        'avances_attente' => 0,
        'primes_attente' => 0
    ];
    $conges_attente = [];
    $avances_attente = [];
    $primes_attente = [];
    $postes = [];
    $departements = [];
    $csrf_token = '';
}

// Nettoyage et sécurisation des données pour l'affichage
$employes = SecurityManager::sanitizeOutput($employes);
$bulletins = SecurityManager::sanitizeOutput($bulletins);
$conges_attente = SecurityManager::sanitizeOutput($conges_attente);
$avances_attente = SecurityManager::sanitizeOutput($avances_attente);
$primes_attente = SecurityManager::sanitizeOutput($primes_attente);
$postes = SecurityManager::sanitizeOutput($postes);
$departements = SecurityManager::sanitizeOutput($departements);

// Inclusion du fichier de vue
include 'views/paie/index.php';
?>