<?php
/**
 * Router principal pour l'API Paie/RH
 * Réorganisation du code pour éviter les duplications
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../classes/SecurityManager.php';
require_once __DIR__ . '/../../classes/AuditManager.php';

// Contrôleurs
require_once __DIR__ . '/controllers/PaieController.php';
require_once __DIR__ . '/controllers/CongeController.php';
require_once __DIR__ . '/controllers/AvanceController.php';
require_once __DIR__ . '/controllers/PrimeController.php';
require_once __DIR__ . '/controllers/PresenceController.php';
require_once __DIR__ . '/controllers/EmployeController.php';
require_once __DIR__ . '/controllers/StatistiqueController.php';

// Configuration
header('Content-Type: application/json');
$conn = getDBConnection();
$securityManager = new SecurityManager();
$auditManager = new AuditManager($conn);

// Récupération de l'action
$action = $_GET['action'] ?? '';

// Validation CSRF pour actions sensibles
$sensibleActions = [
    'generer_bulletin', 'generer_bulletins_masse', 'modifier_bulletin', 'supprimer_bulletin',
    'valider_bulletin', 'valider_conge', 'valider_avance', 'valider_prime',
    'attribuer_prime', 'initialiser_soldes_conges', 'ajouter_presence_manuelle'
];

if (in_array($action, $sensibleActions)) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!SecurityManager::validateCSRFToken($input['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
        exit;
    }
}

// Routage vers les contrôleurs
try {
    switch ($action) {
        // === PAIE ===
        case 'get_bulletins':
        case 'get_bulletin_details':
        case 'generer_bulletin':
        case 'generer_bulletin_integre':
        case 'generer_bulletins_masse':
        case 'preview_employes_masse':
        case 'modifier_bulletin':
        case 'supprimer_bulletin':
        case 'valider_bulletin':
        case 'voir_bulletin':
        case 'telecharger_bulletin':
        case 'export_csv':
        case 'calculate_payroll_with_presences':
        case 'get_employee_payroll_data':
            $controller = new PaieController($conn, $auditManager);
            $controller->handleRequest($action);
            break;

        // === CONGÉS ===
        case 'creer_conge':
        case 'get_conge_details':
        case 'get_conges_historique':
        case 'get_conges_calendrier':
        case 'valider_conge':
        case 'get_solde_conges':
        case 'initialiser_soldes_conges':
        case 'get_employes_pour_soldes':
            $controller = new CongeController($conn, $auditManager);
            $controller->handleRequest($action);
            break;

        // === AVANCES ===
        case 'creer_avance':
        case 'valider_avance':
        case 'get_avances_historique':
        case 'get_rapport_avances_detaille':
        case 'export_rapport_avances':
            $controller = new AvanceController($conn, $auditManager);
            $controller->handleRequest($action);
            break;

        // === PRIMES ===
        case 'attribuer_prime':
        case 'valider_prime':
        case 'get_primes_historique':
        case 'get_prime_details':
        case 'generer_primes_presence':
            $controller = new PrimeController($conn, $auditManager);
            $controller->handleRequest($action);
            break;

        // === PRÉSENCES ===
        case 'get_presences_jour':
        case 'get_details_presence_employe':
        case 'ajouter_presence_manuelle':
        case 'verifier_coherence_planification':
        case 'get_presence_stats_for_payroll':
            $controller = new PresenceController($conn, $auditManager);
            $controller->handleRequest($action);
            break;

        // === EMPLOYÉS ===
        case 'get_employes':
        case 'get_postes':
        case 'get_departements':
        case 'get_contract_types':
            $controller = new EmployeController($conn);
            $controller->handleRequest($action);
            break;

        // === STATISTIQUES ===
        case 'get_dashboard_stats_advanced':
        case 'get_statistiques_detaillees':
        case 'export_statistiques_pdf':
            $controller = new StatistiqueController($conn);
            $controller->handleRequest($action);
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Action non reconnue: ' . $action]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Erreur API Paie - Action: $action - " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
