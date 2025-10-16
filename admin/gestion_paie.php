<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (isset($_GET['action'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autorisé']);
        exit;
    }
    header('Location: login.php');
    exit;
}

function checkPermission($action) {
    $adminRole = $_SESSION['admin_role'] ?? 'admin';
    
    $restrictedActions = [
        'supprimer_bulletin' => ['admin', 'super_admin'],
        'valider_bulletin' => ['admin', 'super_admin', 'rh_manager'],
        'generer_bulletins_masse' => ['admin', 'super_admin'],
    ];
    
    if (isset($restrictedActions[$action])) {
        return in_array($adminRole, $restrictedActions[$action]);
    }
    
    return true;
}

require_once 'classes/EmployeesManager.php';
require_once 'classes/PresenceManager.php';
require_once 'classes/PaieManager.php';
require_once 'classes/PostesManager.php';
require_once 'classes/SecurityManager.php';
require_once 'classes/AuditManager.php';
require_once 'classes/PayrollCalculator.php';

$employeesManager = new EmployeesManager($conn);
$presenceManager = new PresenceManager($conn);
$paieManager = new PaieManager($conn);
$postesManager = new PostesManager($conn);
$auditManager = new AuditManager($conn);
$payrollCalculator = new PayrollCalculator($conn);

// Fonctions utilitaires
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function formaterMontant($montant) {
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}

function getHorairesEmploye($conn, $employeId, $date) {
    $semaine_debut = date('Y-m-d', strtotime('monday', strtotime($date)));
    $jour_semaine = strtolower(date('l', strtotime($date)));
    
    $jours_mapping = [
        'monday' => 'lundi', 'tuesday' => 'mardi', 'wednesday' => 'mercredi',
        'thursday' => 'jeudi', 'friday' => 'vendredi', 'saturday' => 'samedi',
        'sunday' => 'dimanche'
    ];
    
    $jour_fr = $jours_mapping[$jour_semaine] ?? 'lundi';
    
    $stmt = $conn->prepare("
        SELECT {$jour_fr}_debut as heure_debut_prevue,
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
    
    $stmt2 = $conn->prepare("SELECT heure_debut, heure_fin FROM employes WHERE id = ?");
    $stmt2->execute([$employeId]);
    $employe = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    return [
        'heure_debut' => $employe['heure_debut'] ?? '08:00:00',
        'heure_fin' => $employe['heure_fin'] ?? '17:00:00',
        'est_programme' => false
    ];
}

function determinerStatutPresence($presence, $horairePlanifie) {
    if (!$horairePlanifie['est_programme']) {
        return 'pause';
    }
    
    if (!$presence || !$presence['heure_arrivee']) {
        return 'absent';
    }
    
    $heureArrivee = new DateTime($presence['heure_arrivee']);
    $heureDebut = new DateTime($horairePlanifie['heure_debut']);
    $heureDebut->add(new DateInterval('PT15M'));
    
    return ($heureArrivee > $heureDebut) ? 'retard' : 'present';
}

function calculerHeuresParRapportPlanification($conn, $employeId, $mois, $annee) {
    $premierjour = "$annee-" . str_pad($mois, 2, '0', STR_PAD_LEFT) . "-01";
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
    
    $stmt = $conn->prepare("
        SELECT DATE(heure_arrivee) as date_presence, heure_arrivee, heure_depart
        FROM presences 
        WHERE employe_id = ? AND DATE(heure_arrivee) BETWEEN ? AND ?
    ");
    $stmt->execute([$employeId, $premierjour, $dernierjour]);
    $presences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $dateActuelle = new DateTime($premierjour);
    $dateFin = new DateTime($dernierjour);
    
    while ($dateActuelle <= $dateFin) {
        $dateStr = $dateActuelle->format('Y-m-d');
        $horairePlanifie = getHorairesEmploye($conn, $employeId, $dateStr);
        
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
    
    $joursProgrammes = count(array_filter($result['details_par_jour'], function($jour) {
        return $jour['heures_planifiees'] > 0;
    }));
        
    $result['taux_presence'] = $joursProgrammes > 0 ? 
        ($result['jours_travailles'] / $joursProgrammes) * 100 : 0;
    
    return $result;
}

// TRAITEMENT DES ACTIONS AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    
    $writeActions = [
        'generer_bulletin', 'modifier_bulletin', 'supprimer_bulletin', 'valider_bulletin',
        'creer_conge', 'valider_conge', 'creer_avance', 'valider_avance',
        'attribuer_prime', 'valider_prime', 'ajouter_presence_manuelle',
        'generer_bulletins_masse', 'generer_bulletin_integre', 'initialiser_soldes_conges'
    ];
    
    if (in_array($action, $writeActions)) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!SecurityManager::validateCSRFToken($input['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
            exit;
        }
    }
    
    if (!checkPermission($action)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission refusée']);
        exit;
    }
    
    try {
        switch ($action) {
            case 'get_presences_jour':
                $date = $_GET['date'] ?? date('Y-m-d');
                
                $stmt = $conn->prepare("
                    SELECT 
                        e.id as employe_id,
                        e.nom,
                        e.prenom,
                        p_poste.nom as poste_nom,
                        e.statut,
                        pr.heure_arrivee,
                        pr.heure_depart,
                        DATE_FORMAT(pr.heure_arrivee, '%H:%i') as heure_arrivee_format,
                        DATE_FORMAT(pr.heure_depart, '%H:%i') as heure_depart_format
                    FROM employes e
                    LEFT JOIN postes p_poste ON e.poste_id = p_poste.id
                    LEFT JOIN presences pr ON e.id = pr.employe_id 
                        AND DATE(pr.heure_arrivee) = ?
                    WHERE e.statut = 'actif'
                    ORDER BY e.nom, e.prenom
                ");
                
                $stmt->execute([$date]);
                $presences = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($presences as &$presence) {
                    $horairePlanifie = getHorairesEmploye($conn, $presence['employe_id'], $date);
                    $presence['statut_presence'] = determinerStatutPresence($presence, $horairePlanifie);
                }
                
                echo json_encode(['success' => true, 'presences' => $presences]);
                break;
                
            case 'get_details_presence_employe':
                $employeId = (int)$_GET['employe_id'];
                $date = $_GET['date'] ?? date('Y-m-d');
                
                $stmt = $conn->prepare("
                    SELECT e.*, p.nom as poste_nom, d.nom as departement_nom, 
                           p.heures_semaine, p.heures_mois
                    FROM employes e
                    LEFT JOIN postes p ON e.poste_id = p.id
                    LEFT JOIN departements d ON e.departement_id = d.id
                    WHERE e.id = ?
                ");
                $stmt->execute([$employeId]);
                $employe = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$employe) {
                    throw new Exception('Employé non trouvé');
                }
                
                $stmt = $conn->prepare("
                    SELECT *, 
                           DATE_FORMAT(heure_arrivee, '%H:%i') as heure_arrivee_format,
                           DATE_FORMAT(heure_depart, '%H:%i') as heure_depart_format,
                           TIMESTAMPDIFF(HOUR, heure_arrivee, heure_depart) as heures_travaillees
                    FROM presences
                    WHERE employe_id = ? AND DATE(heure_arrivee) = ?
                ");
                $stmt->execute([$employeId, $date]);
                $presenceJour = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $horairePlanifie = getHorairesEmploye($conn, $employeId, $date);
                
                if ($presenceJour) {
                    $presenceJour['statut_presence'] = determinerStatutPresence($presenceJour, $horairePlanifie);
                }
                
                $mois = date('n', strtotime($date));
                $annee = date('Y', strtotime($date));
                
                $statsResult = calculerHeuresParRapportPlanification($conn, $employeId, $mois, $annee);
                
                echo json_encode([
                    'success' => true,
                    'employe' => $employe,
                    'presence_jour' => $presenceJour,
                    'horaire_planifie' => $horairePlanifie,
                    'stats_mois' => $statsResult
                ]);
                break;
                
            case 'ajouter_presence_manuelle':
                $input = json_decode(file_get_contents('php://input'), true);
                
                $conn->beginTransaction();
                
                try {
                    $stmt = $conn->prepare("
                        SELECT id FROM presences 
                        WHERE employe_id = ? AND DATE(heure_arrivee) = ?
                    ");
                    $stmt->execute([$input['employe_id'], $input['date']]);
                    
                    if ($stmt->fetch()) {
                        throw new Exception('Une présence existe déjà pour cette date');
                    }
                    
                    $heureArrivee = $input['date'] . ' ' . $input['heure_arrivee'];
                    $heureDepart = null;
                    
                    if (!empty($input['heure_depart'])) {
                        $heureDepart = $input['date'] . ' ' . $input['heure_depart'];
                        
                        if (strtotime($heureDepart) <= strtotime($heureArrivee)) {
                            throw new Exception('L\'heure de départ doit être postérieure à l\'heure d\'arrivée');
                        }
                    }
                    
                    $stmt = $conn->prepare("
                        INSERT INTO presences (employe_id, heure_arrivee, heure_depart, commentaire, date_creation)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $input['employe_id'],
                        $heureArrivee,
                        $heureDepart,
                        $input['commentaire'] ?? 'Ajout manuel'
                    ]);
                    
                    $conn->commit();
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Présence ajoutée avec succès',
                        'id' => $conn->lastInsertId()
                    ]);
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    throw $e;
                }
                break;
                
            case 'valider_bulletin':
                $input = json_decode(file_get_contents('php://input'), true);
                $bulletinId = (int)$input['bulletin_id'];
                
                $stmt = $conn->prepare("
                    UPDATE bulletins_paie 
                    SET statut = 'valide', date_validation = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$bulletinId]);
                
                echo json_encode(['success' => true, 'message' => 'Bulletin validé']);
                break;
                
            case 'get_dashboard_stats_advanced':
                $stmt = $conn->query("SELECT COUNT(*) as total_actifs FROM employes WHERE statut = 'actif'");
                $statsEmployes = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $mois = date('n');
                $annee = date('Y');
                
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as bulletins_generes, SUM(salaire_net) as masse_salariale
                    FROM bulletins_paie
                    WHERE mois = ? AND annee = ?
                ");
                $stmt->execute([$mois, $annee]);
                $statsPaie = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(DISTINCT employe_id) as employes_avec_presences,
                        AVG(TIMESTAMPDIFF(HOUR, heure_arrivee, heure_depart)) as heures_moyennes_par_jour
                    FROM presences
                    WHERE MONTH(heure_arrivee) = ? AND YEAR(heure_arrivee) = ?
                ");
                $stmt->execute([$mois, $annee]);
                $statsPresences = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $conn->query("SELECT COUNT(*) FROM conges WHERE statut = 'en_attente'");
                $conges_attente = $stmt->fetchColumn();
                
                $stmt = $conn->query("SELECT COUNT(*) FROM avances_salaire WHERE statut = 'en_attente'");
                $avances_attente = $stmt->fetchColumn();
                
                $stmt = $conn->query("SELECT COUNT(*) FROM primes_employes WHERE valide = 0");
                $primes_attente = $stmt->fetchColumn();
                
                $stmt = $conn->query("
                    SELECT d.nom, COUNT(e.id) as nb_employes
                    FROM departements d
                    LEFT JOIN employes e ON d.id = e.departement_id AND e.statut = 'actif'
                    GROUP BY d.id, d.nom
                ");
                $departements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'stats' => [
                        'employes' => $statsEmployes,
                        'paie' => $statsPaie,
                        'presences' => array_merge($statsPresences, [
                            'total_employes_actifs' => $statsEmployes['total_actifs']
                        ]),
                        'conges_attente' => $conges_attente,
                        'avances_attente' => $avances_attente,
                        'primes_attente' => $primes_attente,
                        'departements' => $departements
                    ]
                ]);
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
        }
        
    } catch (Exception $e) {
        error_log("Erreur action {$action}: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// CHARGEMENT DES DONNÉES POUR L'INTERFACE
try {
    $employes = $employeesManager->getAllEmployees(['statut' => 'actif']);
    
    $stmt = $conn->prepare("
        SELECT b.*, 
               e.nom as employe_nom, 
               e.prenom as employe_prenom,
               p.nom as poste_nom,
               0 as avec_presences
        FROM bulletins_paie b
        LEFT JOIN employes e ON b.employe_id = e.id
        LEFT JOIN postes p ON e.poste_id = p.id
        WHERE b.mois = ? AND b.annee = ?
        ORDER BY b.date_creation DESC
    ");
    $stmt->execute([date('n'), date('Y')]);
    $bulletins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->query("SELECT COUNT(*) FROM employes WHERE statut = 'actif'");
    $employes_actifs = $stmt->fetchColumn();
    
    $masse_salariale = 0;
    foreach ($bulletins as $bulletin) {
        $masse_salariale += floatval($bulletin['salaire_net'] ?? 0);
    }
    
    $stmt = $conn->query("SELECT COUNT(*) FROM conges WHERE statut = 'en_attente'");
    $conges_count = $stmt->fetchColumn();

    $stmt = $conn->query("SELECT COUNT(*) FROM avances_salaire WHERE statut = 'en_attente'");
    $avances_count = $stmt->fetchColumn();

    $stmt = $conn->query("SELECT COUNT(*) FROM primes_employes WHERE valide = 0");
    $primes_count = $stmt->fetchColumn();

    $stats = [
        'employes_actifs' => intval($employes_actifs),
        'bulletins_mois' => count($bulletins),
        'masse_salariale' => floatval($masse_salariale),
        'conges_attente' => intval($conges_count),
        'avances_attente' => intval($avances_count),
        'primes_attente' => intval($primes_count)
    ];

    $postes = $postesManager->getAllPostes();
    $departements = $postesManager->getDepartements();
    $csrf_token = SecurityManager::generateCSRFToken();

    $stmt = $conn->prepare("
        SELECT c.*, e.nom, e.prenom, e.email
        FROM conges c
        LEFT JOIN employes e ON c.employe_id = e.id
        WHERE c.statut = 'en_attente' 
        ORDER BY c.date_creation DESC
    ");
    $stmt->execute();
    $conges_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("
        SELECT a.*, e.nom, e.prenom
        FROM avances_salaire a
        LEFT JOIN employes e ON a.id_employe = e.id
        WHERE a.statut = 'en_attente' 
        ORDER BY a.date_demande DESC
    ");
    $stmt->execute();
    $avances_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("
        SELECT p.*, e.nom, e.prenom, tp.nom as type_prime_nom
        FROM primes_employes p
        LEFT JOIN employes e ON p.id_employe = e.id
        LEFT JOIN type_primes tp ON p.id_type_prime = tp.id
        WHERE p.valide = 0 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $primes_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Erreur chargement données: " . $e->getMessage());
    
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
    $postes = [];
    $departements = [];
    $csrf_token = '';
    $conges_attente = [];
    $avances_attente = [];
    $primes_attente = [];
}

include 'views/paie/index.php';