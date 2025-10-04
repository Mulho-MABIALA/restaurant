<?php
session_start();
require_once '../config.php';

// ============================================
// VÉRIFICATIONS DE SÉCURITÉ
// ============================================

// 1. Vérification authentification admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if (isset($_GET['action'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autorisé']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// 2. Vérification des permissions (à adapter selon votre système de rôles)
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
    
    return true; // Actions non restreintes
}

// ============================================
// INITIALISATION DES MANAGERS
// ============================================

require_once 'classes/EmployeesManager.php';
require_once 'classes/PresenceManager.php';
require_once 'classes/PaieManager.php';
require_once 'classes/PostesManager.php';
require_once 'classes/SecurityManager.php';
require_once 'classes/AuditManager.php';
require_once 'classes/PayrollCalculator.php';
require_once 'classes/BulletinPDFGenerateur.php';

$employeesManager = new EmployeesManager($conn);
$presenceManager = new PresenceManager($conn);
$paieManager = new PaieManager($conn);
$postesManager = new PostesManager($conn);
$auditManager = new AuditManager($conn);
$payrollCalculator = new PayrollCalculator($conn);

// ============================================
// FONCTIONS UTILITAIRES SÉCURISÉES
// ============================================

function getHorairesEmploye($conn, $employeId, $date) {
    $semaine_debut = date('Y-m-d', strtotime('monday', strtotime($date)));
    $jour_semaine = strtolower(date('l', strtotime($date)));
    
    $jours_mapping = [
        'monday' => 'lundi', 'tuesday' => 'mardi', 'wednesday' => 'mercredi',
        'thursday' => 'jeudi', 'friday' => 'vendredi', 'saturday' => 'samedi',
        'sunday' => 'dimanche'
    ];
    
    $jour_fr = $jours_mapping[$jour_semaine] ?? 'lundi';
    
    // CORRECTION: Utilisation de requête préparée
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

/**
 * Fonction utilitaire pour échapper les sorties HTML
 * Protection contre les attaques XSS
 */
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Fonction utilitaire pour formater les montants en FCFA
 */
function formaterMontant($montant) {
    return number_format($montant, 0, ',', ' ') . ' FCFA';
}

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
        'taux_presence' => 0
    ];
    
    // CORRECTION: Utilisation de requête préparée au lieu de query()
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
        
        $dateActuelle->add(new DateInterval('P1D'));
    }
    
    $joursProgrammes = ($result['heures_planifiees_total'] > 0) ? 
        ceil($result['heures_planifiees_total'] / 8) : 0;
        
    $result['taux_presence'] = $joursProgrammes > 0 ? 
        ($result['jours_travailles'] / $joursProgrammes) * 100 : 0;
    
    return $result;
}

// ============================================
// TRAITEMENT DES ACTIONS AJAX
// ============================================

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    
    // CORRECTION: Validation CSRF pour TOUTES les actions de modification
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
    
    // CORRECTION: Vérification des permissions
    if (!checkPermission($action)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission refusée']);
        exit;
    }
    
    // CORRECTION: Rate limiting simple (à améliorer avec Redis/Memcached en production)
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = ['count' => 0, 'time' => time()];
    }
    
    if (time() - $_SESSION['rate_limit']['time'] > 60) {
        $_SESSION['rate_limit'] = ['count' => 0, 'time' => time()];
    }
    
    $_SESSION['rate_limit']['count']++;
    
    if ($_SESSION['rate_limit']['count'] > 100) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Trop de requêtes']);
        exit;
    }
    
    try {
        switch ($action) {
            case 'get_employes':
                $filters = [];
                if (isset($_GET['statut'])) $filters['statut'] = $_GET['statut'];
                if (isset($_GET['departement_id'])) $filters['departement_id'] = (int)$_GET['departement_id'];
                if (isset($_GET['search'])) $filters['search'] = $_GET['search'];
                
                $employes = $employeesManager->getAllEmployees($filters);
                echo json_encode(['success' => true, 'employes' => $employes]);
                break;
                
            case 'calculate_payroll_with_presences':
                $input = json_decode(file_get_contents('php://input'), true);
                
                $validation = SecurityManager::validateInput($input, [
                    'employe_id' => ['required' => true, 'type' => 'numeric'],
                    'mois' => ['required' => true, 'type' => 'numeric', 'min' => 1, 'max' => 12],
                    'annee' => ['required' => true, 'type' => 'numeric', 'min' => 2020, 'max' => 2100]
                ]);
                
                if (!empty($validation)) {
                    throw new Exception('Données invalides: ' . implode(', ', $validation));
                }
                
                // CORRECTION: Transaction pour garantir la cohérence
                $conn->beginTransaction();
                
                try {
                    $stmt = $conn->prepare("
                        SELECT e.*, p.heures_semaine, p.heures_mois, p.salaire_base
                        FROM employes e
                        LEFT JOIN postes p ON e.poste_id = p.id
                        WHERE e.id = ? FOR UPDATE
                    ");
                    $stmt->execute([$input['employe_id']]);
                    $employe = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$employe) {
                        throw new Exception('Employé non trouvé');
                    }
                    
                    $statsPresences = calculerHeuresParRapportPlanification(
                        $conn, 
                        $input['employe_id'], 
                        $input['mois'], 
                        $input['annee']
                    );
                    
                    $heuresPlanifiees = $employe['heures_mois'] ?? 173;
                    $heuresReelles = $statsPresences['heures_reelles_total'];
                    $salaireBase = $employe['salaire_base'] ?? $employe['salaire'];
                    
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
                        'nb_absences' => $statsPresences['nb_absences']
                    ];
                    
                    $conn->commit();
                    echo json_encode(['success' => true, 'calcul' => $calculData]);
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    throw $e;
                }
                break;
                
            case 'ajouter_presence_manuelle':
                $input = json_decode(file_get_contents('php://input'), true);
                
                $validation = SecurityManager::validateInput($input, [
                    'employe_id' => ['required' => true, 'type' => 'numeric'],
                    'date' => ['required' => true, 'type' => 'date'],
                    'heure_arrivee' => ['required' => true, 'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/']
                ]);
                
                if (!empty($validation)) {
                    throw new Exception('Données invalides: ' . implode(', ', $validation));
                }
                
                $conn->beginTransaction();
                
                try {
                    $stmt = $conn->prepare("
                        SELECT id FROM presences 
                        WHERE employe_id = ? AND DATE(heure_arrivee) = ?
                        FOR UPDATE
                    ");
                    $stmt->execute([$input['employe_id'], $input['date']]);
                    
                    if ($stmt->fetch()) {
                        throw new Exception('Une présence existe déjà pour cette date');
                    }
                    
                    $heureArrivee = $input['date'] . ' ' . $input['heure_arrivee'];
                    $heureDepart = null;
                    
                    if (!empty($input['heure_depart'])) {
                        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $input['heure_depart'])) {
                            throw new Exception('Format heure de départ invalide');
                        }
                        
                        $heureDepart = $input['date'] . ' ' . $input['heure_depart'];
                        
                        if (strtotime($heureDepart) <= strtotime($heureArrivee)) {
                            throw new Exception('L\'heure de départ doit être postérieure à l\'heure d\'arrivée');
                        }
                    }
                    
                    $stmt = $conn->prepare("
                        INSERT INTO presences (employe_id, heure_arrivee, heure_depart, commentaire, ajout_manuel, date_creation)
                        VALUES (?, ?, ?, ?, 1, NOW())
                    ");
                    
                    $stmt->execute([
                        $input['employe_id'],
                        $heureArrivee,
                        $heureDepart,
                        filter_var($input['commentaire'] ?? 'Ajout manuel', FILTER_SANITIZE_STRING)
                    ]);
                    
                    $auditManager->logPayrollAction('ADD_PRESENCE_MANUAL', [
                        'employe_id' => $input['employe_id'],
                        'date' => $input['date'],
                        'admin_id' => $_SESSION['admin_id'] ?? 1
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
                
            // Ajouter les autres cases ici avec les mêmes corrections...
            
            default:
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
        }
        
    } catch (Exception $e) {
        // CORRECTION: Ne pas exposer les détails techniques
        error_log("Erreur action {$action}: " . $e->getMessage() . " | User: " . ($_SESSION['admin_id'] ?? 'unknown'));
        
        // Message générique pour le client
        echo json_encode([
            'success' => false, 
            'error' => 'Une erreur est survenue. Veuillez réessayer ou contacter l\'administrateur.'
        ]);
    }
    exit;
}

// ============================================
// CHARGEMENT DES DONNÉES POUR L'INTERFACE
// ============================================

try {
    $employes = $employeesManager->getAllEmployees(['statut' => 'actif']);
    $bulletins = $paieManager->getBulletins(['mois' => date('n'), 'annee' => date('Y')]);
    
    $statsEmployes = $employeesManager->getEmployeeStatistics();
    $statsPaie = $paieManager->getPayrollStatistics();
    
    $stats = [
        'employes_actifs' => $statsEmployes['total_actifs'] ?? 0,
        'bulletins_mois' => count($bulletins),
        'masse_salariale' => array_sum(array_column($bulletins, 'salaire_net')),
        'conges_attente' => 0,
        'avances_attente' => 0,
        'primes_attente' => 0
    ];
    
    // Récupération sécurisée des demandes en attente
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM conges WHERE statut = 'en_attente'
    ");
    $stmt->execute();
    $stats['conges_attente'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM avances_salaire WHERE statut = 'en_attente'
    ");
    $stmt->execute();
    $stats['avances_attente'] = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM primes_employes WHERE valide = 0
    ");
    $stmt->execute();
    $stats['primes_attente'] = $stmt->fetchColumn();
    
    $postes = $postesManager->getAllPostes();
    $departements = $postesManager->getDepartements();
    $csrf_token = SecurityManager::generateCSRFToken();
    
} catch (Exception $e) {
    error_log("Erreur chargement données: " . $e->getMessage());
    
    $employes = [];
    $bulletins = [];
    $stats = ['employes_actifs' => 0, 'bulletins_mois' => 0, 'masse_salariale' => 0];
    $postes = [];
    $departements = [];
    $csrf_token = '';
}

// CORRECTION: Sécurisation des sorties
$employes = SecurityManager::sanitizeOutput($employes);
$bulletins = SecurityManager::sanitizeOutput($bulletins);
$postes = SecurityManager::sanitizeOutput($postes);
$departements = SecurityManager::sanitizeOutput($departements);

include 'views/paie/index.php';