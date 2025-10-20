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
        'attribuer_prime', 'valider_prime', 'generer_primes_presence', 'ajouter_presence_manuelle',
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

                // Log pour diagnostic
                error_log("Recherche employé ID: $employeId");

                $stmt = $conn->prepare("
                    SELECT e.*, p.nom as poste_nom, d.nom as departement_nom,
                           p.heures_semaine, p.heures_mois, p.heures_travail as heures_par_mois
                    FROM employes e
                    LEFT JOIN postes p ON e.poste_id = p.id
                    LEFT JOIN departements d ON p.departement_id = d.id
                    WHERE e.id = ?
                ");
                $stmt->execute([$employeId]);
                $employe = $stmt->fetch(PDO::FETCH_ASSOC);

                // Log pour diagnostic
                if (!$employe) {
                    error_log("Employé $employeId non trouvé dans la base de données");
                } else {
                    error_log("Employé trouvé: {$employe['nom']} {$employe['prenom']}");
                }

                if (!$employe) {
                    throw new Exception('Employé non trouvé (ID: ' . $employeId . ')');
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
                    LEFT JOIN postes p ON d.id = p.departement_id
                    LEFT JOIN employes e ON p.id = e.poste_id AND e.statut = 'actif'
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

            case 'get_stats_avancees':
                // Statistiques pour le Tableau de Bord RH Avancé
                $stmt = $conn->query("SELECT COUNT(*) as total FROM employes WHERE statut = 'actif'");
                $effectifTotal = $stmt->fetchColumn();

                // Calcul du taux de présence (mois en cours)
                $mois = date('n');
                $annee = date('Y');

                $stmt = $conn->prepare("
                    SELECT
                        COUNT(DISTINCT employe_id) as presents,
                        (SELECT COUNT(*) FROM employes WHERE statut = 'actif') as total_actifs
                    FROM presences
                    WHERE MONTH(heure_arrivee) = ? AND YEAR(heure_arrivee) = ? AND DATE(heure_arrivee) = CURDATE()
                ");
                $stmt->execute([$mois, $annee]);
                $presences = $stmt->fetch(PDO::FETCH_ASSOC);

                $tauxPresence = $presences['total_actifs'] > 0 ?
                    round(($presences['presents'] / $presences['total_actifs']) * 100, 1) : 0;

                // Masse salariale
                $stmt = $conn->prepare("
                    SELECT COALESCE(SUM(salaire_net), 0) as total
                    FROM bulletins_paie
                    WHERE mois = ? AND annee = ?
                ");
                $stmt->execute([$mois, $annee]);
                $masseSalariale = $stmt->fetchColumn();

                // Retard moyen
                $stmt = $conn->prepare("
                    SELECT AVG(TIMESTAMPDIFF(MINUTE,
                        CONCAT(DATE(pr.heure_arrivee), ' ', e.heure_debut),
                        pr.heure_arrivee
                    )) as retard_moyen
                    FROM presences pr
                    JOIN employes e ON pr.employe_id = e.id
                    WHERE MONTH(pr.heure_arrivee) = ? AND YEAR(pr.heure_arrivee) = ?
                    AND pr.heure_arrivee > CONCAT(DATE(pr.heure_arrivee), ' ', e.heure_debut)
                ");
                $stmt->execute([$mois, $annee]);
                $retardMoyen = round($stmt->fetchColumn() ?? 0);

                echo json_encode([
                    'success' => true,
                    'stats' => [
                        'effectif_total' => intval($effectifTotal),
                        'taux_presence' => floatval($tauxPresence),
                        'masse_salariale' => floatval($masseSalariale),
                        'retard_moyen' => intval($retardMoyen)
                    ]
                ]);
                break;

            case 'generate_custom_report':
                $input = json_decode(file_get_contents('php://input'), true);

                $type = $input['type'] ?? '';
                $dateDebut = $input['date_debut'] ?? '';
                $dateFin = $input['date_fin'] ?? '';

                if (!$type || !$dateDebut || !$dateFin) {
                    throw new Exception('Paramètres manquants');
                }

                $content = '<div class="space-y-4">';
                $title = '';

                switch ($type) {
                    case 'presences':
                        $title = 'Rapport Présences et Retards';
                        $stmt = $conn->prepare("
                            SELECT
                                e.nom, e.prenom,
                                COUNT(DISTINCT DATE(pr.heure_arrivee)) as jours_travailles,
                                SUM(CASE WHEN pr.heure_arrivee > CONCAT(DATE(pr.heure_arrivee), ' ', e.heure_debut) THEN 1 ELSE 0 END) as retards
                            FROM employes e
                            LEFT JOIN presences pr ON e.id = pr.employe_id
                                AND DATE(pr.heure_arrivee) BETWEEN ? AND ?
                            WHERE e.statut = 'actif'
                            GROUP BY e.id, e.nom, e.prenom
                            ORDER BY e.nom, e.prenom
                        ");
                        $stmt->execute([$dateDebut, $dateFin]);
                        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $content .= '<table class="min-w-full divide-y divide-gray-200">';
                        $content .= '<thead class="bg-gray-50"><tr>';
                        $content .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Employé</th>';
                        $content .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Jours travaillés</th>';
                        $content .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Retards</th>';
                        $content .= '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';

                        foreach ($data as $row) {
                            $content .= '<tr>';
                            $content .= '<td class="px-4 py-2">' . h($row['nom']) . ' ' . h($row['prenom']) . '</td>';
                            $content .= '<td class="px-4 py-2">' . $row['jours_travailles'] . '</td>';
                            $content .= '<td class="px-4 py-2">' . $row['retards'] . '</td>';
                            $content .= '</tr>';
                        }
                        $content .= '</tbody></table>';
                        break;

                    case 'salaires':
                        $title = 'Rapport Salaires et Coûts';
                        $stmt = $conn->prepare("
                            SELECT
                                e.nom, e.prenom,
                                COALESCE(SUM(b.salaire_brut), 0) as total_brut,
                                COALESCE(SUM(b.salaire_net), 0) as total_net
                            FROM employes e
                            LEFT JOIN bulletins_paie b ON e.id = b.employe_id
                                AND DATE(b.date_creation) BETWEEN ? AND ?
                            WHERE e.statut = 'actif'
                            GROUP BY e.id, e.nom, e.prenom
                            HAVING total_brut > 0
                            ORDER BY total_brut DESC
                        ");
                        $stmt->execute([$dateDebut, $dateFin]);
                        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $content .= '<table class="min-w-full divide-y divide-gray-200">';
                        $content .= '<thead class="bg-gray-50"><tr>';
                        $content .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Employé</th>';
                        $content .= '<th class="px-4 py-2 text-right text-xs font-medium text-gray-700">Salaire Brut</th>';
                        $content .= '<th class="px-4 py-2 text-right text-xs font-medium text-gray-700">Salaire Net</th>';
                        $content .= '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';

                        $totalBrut = 0;
                        $totalNet = 0;
                        foreach ($data as $row) {
                            $totalBrut += $row['total_brut'];
                            $totalNet += $row['total_net'];
                            $content .= '<tr>';
                            $content .= '<td class="px-4 py-2">' . h($row['nom']) . ' ' . h($row['prenom']) . '</td>';
                            $content .= '<td class="px-4 py-2 text-right">' . number_format($row['total_brut'], 0, ',', ' ') . ' FCFA</td>';
                            $content .= '<td class="px-4 py-2 text-right">' . number_format($row['total_net'], 0, ',', ' ') . ' FCFA</td>';
                            $content .= '</tr>';
                        }
                        $content .= '<tr class="bg-gray-100 font-bold">';
                        $content .= '<td class="px-4 py-2">TOTAL</td>';
                        $content .= '<td class="px-4 py-2 text-right">' . number_format($totalBrut, 0, ',', ' ') . ' FCFA</td>';
                        $content .= '<td class="px-4 py-2 text-right">' . number_format($totalNet, 0, ',', ' ') . ' FCFA</td>';
                        $content .= '</tr>';
                        $content .= '</tbody></table>';
                        break;

                    case 'effectifs':
                        $title = 'Rapport Effectifs et Démographie';
                        $stmt = $conn->query("
                            SELECT
                                d.nom as departement,
                                COUNT(e.id) as nb_employes
                            FROM departements d
                            LEFT JOIN postes p ON d.id = p.departement_id
                            LEFT JOIN employes e ON p.id = e.poste_id AND e.statut = 'actif'
                            GROUP BY d.id, d.nom
                            ORDER BY nb_employes DESC
                        ");
                        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $content .= '<table class="min-w-full divide-y divide-gray-200">';
                        $content .= '<thead class="bg-gray-50"><tr>';
                        $content .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Département</th>';
                        $content .= '<th class="px-4 py-2 text-right text-xs font-medium text-gray-700">Nombre d\'employés</th>';
                        $content .= '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';

                        foreach ($data as $row) {
                            $content .= '<tr>';
                            $content .= '<td class="px-4 py-2">' . h($row['departement']) . '</td>';
                            $content .= '<td class="px-4 py-2 text-right">' . $row['nb_employes'] . '</td>';
                            $content .= '</tr>';
                        }
                        $content .= '</tbody></table>';
                        break;

                    case 'turnover':
                        $title = 'Rapport Turnover et Rotation';
                        $content .= '<p class="text-gray-600">Fonctionnalité en cours de développement</p>';
                        break;
                }

                $content .= '</div>';

                echo json_encode([
                    'success' => true,
                    'title' => $title,
                    'content' => $content
                ]);
                break;

            case 'generer_bulletin_integre':
                $input = json_decode(file_get_contents('php://input'), true);

                // Validation des données
                $employeId = (int)($input['employe_id'] ?? 0);
                $mois = (int)($input['mois'] ?? 0);
                $annee = (int)($input['annee'] ?? 0);

                if (!$employeId || !$mois || !$annee) {
                    throw new Exception('Données invalides: employe_id, mois et annee requis');
                }

                // Récupérer les informations de l'employé
                $employe = $employeesManager->getEmployeeById($employeId);
                if (!$employe) {
                    throw new Exception('Employé non trouvé');
                }

                // Calculer les heures basées sur les présences
                $statsPresences = calculerHeuresParRapportPlanification($conn, $employeId, $mois, $annee);

                // Calculer le salaire de base selon les jours travaillés
                $salaireBase = floatval($employe['salaire'] ?? $employe['poste_salaire'] ?? 0);
                $heuresMensuellesContrat = floatval($employe['heures_par_mois'] ?? 173);
                $heuresReellesTravaillees = floatval($statsPresences['heures_reelles_total']);

                // Calculer le salaire proportionnel aux heures travaillées
                $salaireBrut = ($salaireBase / $heuresMensuellesContrat) * $heuresReellesTravaillees;

                // Ajouter les heures supplémentaires si spécifié
                $heuresSupplementaires = floatval($input['heures_supplementaires'] ?? 0);
                $tauxHoraire = $salaireBase / $heuresMensuellesContrat;
                $montantHeuresSup = $heuresSupplementaires * $tauxHoraire * 1.5; // 150% pour heures sup

                $salaireBrut += $montantHeuresSup;

                // Récupérer les primes de l'employé pour cette période
                $stmt = $conn->prepare("
                    SELECT SUM(montant) as total_primes
                    FROM primes_employes
                    WHERE id_employe = ? AND mois = ? AND annee = ? AND valide = 1
                ");
                $stmt->execute([$employeId, $mois, $annee]);
                $primes = floatval($stmt->fetchColumn() ?? 0);

                // Récupérer les avances
                $stmt = $conn->prepare("
                    SELECT SUM(montant_accorde) as total_avances
                    FROM avances_salaire
                    WHERE id_employe = ? AND statut = 'approuve'
                    AND MONTH(date_validation) = ? AND YEAR(date_validation) = ?
                ");
                $stmt->execute([$employeId, $mois, $annee]);
                $avances = floatval($stmt->fetchColumn() ?? 0);

                // Calculer les cotisations sociales (exemple: 20% du brut)
                $cotisationsSociales = $salaireBrut * 0.20;

                // Calculer les impôts (exemple: 10% du brut)
                $impots = $salaireBrut * 0.10;

                // Calcul du salaire net
                $salaireNet = $salaireBrut + $primes - $avances - $cotisationsSociales - $impots;

                // Ajustements manuels (jours)
                $ajustementsJours = (int)($input['ajustements_jours'] ?? 0);
                if ($ajustementsJours != 0) {
                    $ajustementMontant = ($salaireBase / 30) * $ajustementsJours;
                    $salaireBrut += $ajustementMontant;
                    $salaireNet += $ajustementMontant;
                }

                // Vérifier si un bulletin existe déjà
                $stmt = $conn->prepare("
                    SELECT id FROM bulletins_paie
                    WHERE employe_id = ? AND mois = ? AND annee = ?
                ");
                $stmt->execute([$employeId, $mois, $annee]);

                if ($stmt->fetch()) {
                    throw new Exception('Un bulletin existe déjà pour cette période');
                }

                // Insérer le bulletin
                $stmt = $conn->prepare("
                    INSERT INTO bulletins_paie (
                        employe_id, mois, annee,
                        salaire_base, heures_travaillees, heures_supplementaires, montant_heures_sup,
                        total_primes, montant_avances_remboursees, total_retenues,
                        total_cotisations, impots,
                        salaire_brut, salaire_net,
                        jours_travailles, jours_absences, jours_conges,
                        statut, commentaires
                    ) VALUES (
                        ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, 0,
                        ?, ?,
                        ?, ?,
                        ?, ?, 0,
                        'brouillon', ?
                    )
                ");

                $stmt->execute([
                    $employeId, $mois, $annee,
                    $salaireBase, $heuresReellesTravaillees, $heuresSupplementaires, $montantHeuresSup,
                    $primes, $avances,
                    $cotisationsSociales, $impots,
                    $salaireBrut, $salaireNet,
                    $statsPresences['jours_travailles'], $statsPresences['nb_absences'],
                    $input['commentaires'] ?? "Généré automatiquement avec présences - Taux présence: " . round($statsPresences['taux_presence'], 2) . "%"
                ]);

                $bulletinId = $conn->lastInsertId();

                // Log de l'action
                error_log("Bulletin généré avec présences - ID: $bulletinId, Employé: $employeId, Heures: $heuresReellesTravaillees");

                echo json_encode([
                    'success' => true,
                    'message' => 'Bulletin généré avec succès (basé sur les présences)',
                    'bulletin_id' => $bulletinId,
                    'stats' => [
                        'heures_travaillees' => $heuresReellesTravaillees,
                        'jours_travailles' => $statsPresences['jours_travailles'],
                        'taux_presence' => round($statsPresences['taux_presence'], 2)
                    ]
                ]);
                break;

            case 'creer_conge':
                $input = json_decode(file_get_contents('php://input'), true);

                // Validation
                $employeId = (int)($input['employe_id'] ?? 0);
                $typeConge = $input['type_conge'] ?? '';
                $dateDebut = $input['date_debut'] ?? '';
                $dateFin = $input['date_fin'] ?? '';
                $motif = $input['motif'] ?? '';

                if (!$employeId || !$typeConge || !$dateDebut || !$dateFin) {
                    throw new Exception('Données invalides: tous les champs sont requis');
                }

                // Vérifier que l'employé existe
                $stmt = $conn->prepare("SELECT id FROM employes WHERE id = ?");
                $stmt->execute([$employeId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Employé non trouvé');
                }

                // Vérifier que la date de fin est après la date de début
                if (strtotime($dateFin) < strtotime($dateDebut)) {
                    throw new Exception('La date de fin doit être postérieure à la date de début');
                }

                // Calculer le nombre de jours (inclus les weekends pour l'instant)
                $debut = new DateTime($dateDebut);
                $fin = new DateTime($dateFin);
                $nbJours = $debut->diff($fin)->days + 1; // +1 pour inclure le dernier jour

                // Insérer la demande de congé
                $stmt = $conn->prepare("
                    INSERT INTO conges (
                        employe_id, type, date_debut, date_fin, nb_jours, motif, statut, date_creation
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, 'en_attente', NOW()
                    )
                ");

                $stmt->execute([
                    $employeId,
                    $typeConge,
                    $dateDebut,
                    $dateFin,
                    $nbJours,
                    $motif
                ]);

                $congeId = $conn->lastInsertId();

                error_log("Demande de congé créée - ID: $congeId, Employé: $employeId, Période: $dateDebut à $dateFin ($nbJours jours)");

                echo json_encode([
                    'success' => true,
                    'message' => 'Demande de congé créée avec succès',
                    'conge_id' => $congeId,
                    'nb_jours' => $nbJours
                ]);
                break;

            case 'valider_conge':
                $input = json_decode(file_get_contents('php://input'), true);

                // Validation
                $congeId = (int)($input['id_conge'] ?? 0);
                $statut = $input['statut'] ?? '';
                $commentaire = $input['commentaire'] ?? '';

                if (!$congeId || !in_array($statut, ['approuve', 'refuse'])) {
                    throw new Exception('Données invalides');
                }

                // Vérifier que le congé existe et est en attente
                $stmt = $conn->prepare("SELECT * FROM conges WHERE id = ? AND statut = 'en_attente'");
                $stmt->execute([$congeId]);
                $conge = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$conge) {
                    throw new Exception('Congé non trouvé ou déjà traité');
                }

                // Mettre à jour le statut
                $stmt = $conn->prepare("
                    UPDATE conges
                    SET statut = ?, commentaire = ?, date_validation = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$statut, $commentaire, $congeId]);

                // Si approuvé, mettre à jour le solde de congés (si table existe)
                if ($statut === 'approuve') {
                    try {
                        $stmt = $conn->prepare("
                            UPDATE soldes_conges
                            SET jours_pris = jours_pris + ?,
                                jours_restants = jours_restants - ?
                            WHERE employe_id = ? AND annee = YEAR(NOW())
                        ");
                        $stmt->execute([$conge['nb_jours'], $conge['nb_jours'], $conge['employe_id']]);
                    } catch (Exception $e) {
                        // Table soldes_conges n'existe peut-être pas encore
                        error_log("Impossible de mettre à jour soldes_conges: " . $e->getMessage());
                    }
                }

                $message = $statut === 'approuve' ? 'Congé approuvé avec succès' : 'Congé refusé';

                error_log("Congé $statut - ID: $congeId, Employé: {$conge['employe_id']}, Période: {$conge['date_debut']} à {$conge['date_fin']}");

                echo json_encode([
                    'success' => true,
                    'message' => $message
                ]);
                break;

            case 'creer_avance':
                $input = json_decode(file_get_contents('php://input'), true);

                // Validation
                $employeId = (int)($input['employe_id'] ?? 0);
                $montant = floatval($input['montant'] ?? 0);
                $modeRemboursement = $input['mode_remboursement'] ?? '';
                $motif = $input['motif'] ?? '';

                if (!$employeId || !$montant || !$motif) {
                    throw new Exception('Données invalides: tous les champs sont requis');
                }

                if ($montant < 1000) {
                    throw new Exception('Le montant minimum est de 1 000 FCFA');
                }

                // Vérifier que l'employé existe
                $stmt = $conn->prepare("SELECT id FROM employes WHERE id = ?");
                $stmt->execute([$employeId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Employé non trouvé');
                }

                // Calculer les mensualités si mode remboursement multiple
                $nbMensualites = 1;
                $montantMensualite = $montant;

                if ($modeRemboursement === 'mensuel_3') {
                    $nbMensualites = 3;
                    $montantMensualite = $montant / 3;
                } elseif ($modeRemboursement === 'mensuel_6') {
                    $nbMensualites = 6;
                    $montantMensualite = $montant / 6;
                } elseif ($modeRemboursement === 'mensuel_12') {
                    $nbMensualites = 12;
                    $montantMensualite = $montant / 12;
                }

                // Insérer la demande d'avance
                $stmt = $conn->prepare("
                    INSERT INTO avances_salaire (
                        id_employe, montant_demande, motif, statut,
                        nb_mensualites, montant_mensualite,
                        date_demande, demande_par
                    ) VALUES (
                        ?, ?, ?, 'en_attente',
                        ?, ?,
                        NOW(), ?
                    )
                ");

                $stmt->execute([
                    $employeId,
                    $montant,
                    $motif,
                    $nbMensualites,
                    $montantMensualite,
                    $employeId
                ]);

                $avanceId = $conn->lastInsertId();

                error_log("Demande d'avance créée - ID: $avanceId, Employé: $employeId, Montant: $montant FCFA");

                echo json_encode([
                    'success' => true,
                    'message' => 'Demande d\'avance créée avec succès',
                    'avance_id' => $avanceId,
                    'nb_mensualites' => $nbMensualites
                ]);
                break;

            case 'valider_avance':
                $input = json_decode(file_get_contents('php://input'), true);

                // Validation
                $avanceId = (int)($input['id_avance'] ?? 0);
                $statut = $input['statut'] ?? '';
                $commentaire = $input['commentaire_validation'] ?? '';

                if (!$avanceId || !in_array($statut, ['approuve', 'refuse'])) {
                    throw new Exception('Données invalides');
                }

                // Vérifier que l'avance existe et est en attente
                $stmt = $conn->prepare("SELECT * FROM avances_salaire WHERE id = ? AND statut = 'en_attente'");
                $stmt->execute([$avanceId]);
                $avance = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$avance) {
                    throw new Exception('Avance non trouvée ou déjà traitée');
                }

                // Préparer les valeurs à mettre à jour
                if ($statut === 'approuve') {
                    // Lors de l'approbation, le montant accordé = montant demandé (par défaut)
                    $montantAccorde = $avance['montant_demande'];

                    $stmt = $conn->prepare("
                        UPDATE avances_salaire
                        SET statut = ?,
                            montant_accorde = ?,
                            commentaire_validation = ?,
                            date_validation = NOW(),
                            valide_par = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$statut, $montantAccorde, $commentaire, $_SESSION['admin_id'] ?? 1, $avanceId]);
                } else {
                    // Refus
                    $stmt = $conn->prepare("
                        UPDATE avances_salaire
                        SET statut = ?,
                            commentaire_validation = ?,
                            date_validation = NOW(),
                            valide_par = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$statut, $commentaire, $_SESSION['admin_id'] ?? 1, $avanceId]);
                }

                $message = $statut === 'approuve' ? 'Avance approuvée avec succès' : 'Avance refusée';

                error_log("Avance $statut - ID: $avanceId, Employé: {$avance['id_employe']}, Montant: {$avance['montant_demande']} FCFA");

                echo json_encode([
                    'success' => true,
                    'message' => $message
                ]);
                break;

            case 'get_rapport_avances_detaille':
                $debut = $_GET['debut'] ?? '';
                $fin = $_GET['fin'] ?? '';
                $statut = $_GET['statut'] ?? '';

                if (!$debut || !$fin) {
                    throw new Exception('Dates de début et de fin requises');
                }

                // Requête de base
                $sql = "
                    SELECT
                        a.*,
                        e.nom as employe_nom,
                        e.prenom as employe_prenom,
                        p.nom as poste_nom,
                        d.nom as departement_nom
                    FROM avances_salaire a
                    LEFT JOIN employes e ON a.id_employe = e.id
                    LEFT JOIN postes p ON e.poste_id = p.id
                    LEFT JOIN departements d ON p.departement_id = d.id
                    WHERE DATE(a.date_demande) BETWEEN ? AND ?
                ";

                $params = [$debut, $fin];

                // Filtrer par statut si spécifié
                if ($statut) {
                    $sql .= " AND a.statut = ?";
                    $params[] = $statut;
                }

                $sql .= " ORDER BY a.date_demande DESC";

                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $avances = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculer les statistiques
                $stats = [
                    'total_avances' => count($avances),
                    'montant_total' => 0,
                    'montant_accorde' => 0,
                    'montant_rembourse' => 0,
                    'montant_restant' => 0,
                    'en_attente' => 0,
                    'approuvees' => 0,
                    'refusees' => 0,
                    'remboursees' => 0
                ];

                foreach ($avances as &$avance) {
                    $stats['montant_total'] += floatval($avance['montant_demande']);

                    if ($avance['statut'] === 'approuve' || $avance['statut'] === 'rembourse') {
                        $stats['montant_accorde'] += floatval($avance['montant_accorde'] ?? 0);
                    }

                    // Calculer le montant remboursé (mensualité_actuelle * montant_mensualite)
                    $montantRembourse = floatval($avance['mensualite_actuelle'] ?? 0) * floatval($avance['montant_mensualite'] ?? 0);
                    $stats['montant_rembourse'] += $montantRembourse;
                    $avance['montant_rembourse'] = $montantRembourse;

                    // Montant restant à rembourser
                    $montantRestant = floatval($avance['montant_accorde'] ?? 0) - $montantRembourse;
                    $avance['montant_restant'] = max(0, $montantRestant);
                    $stats['montant_restant'] += $avance['montant_restant'];

                    // Calculer le taux de progression du remboursement
                    if ($avance['nb_mensualites'] > 0) {
                        $avance['progression'] = ($avance['mensualite_actuelle'] / $avance['nb_mensualites']) * 100;
                    } else {
                        $avance['progression'] = 0;
                    }

                    // Compter les statuts
                    switch ($avance['statut']) {
                        case 'en_attente':
                            $stats['en_attente']++;
                            break;
                        case 'approuve':
                            $stats['approuvees']++;
                            break;
                        case 'refuse':
                            $stats['refusees']++;
                            break;
                        case 'rembourse':
                            $stats['remboursees']++;
                            break;
                    }
                }

                echo json_encode([
                    'success' => true,
                    'rapport' => [
                        'stats' => $stats,
                        'avances' => $avances,
                        'periode' => [
                            'debut' => $debut,
                            'fin' => $fin
                        ]
                    ]
                ]);
                break;

            case 'attribuer_prime':
                $input = json_decode(file_get_contents('php://input'), true);

                // Validation
                $typeAttribution = $input['type_attribution'] ?? '';
                $typePrimeInput = $input['type_prime'] ?? '';
                $montant = floatval($input['montant'] ?? 0);
                $periode = $input['periode'] ?? '';
                $justification = $input['justification'] ?? '';

                // Si type_prime est un texte (PERFORMANCE, PRESENCE...), chercher l'ID
                if (!is_numeric($typePrimeInput)) {
                    // Mapping des noms vers les IDs (basé sur la table type_primes)
                    $stmt = $conn->prepare("SELECT id FROM type_primes WHERE nom LIKE ? LIMIT 1");

                    // Convertir PERFORMANCE -> %performance%
                    $searchTerm = '%' . str_replace('_', ' ', strtolower($typePrimeInput)) . '%';
                    $stmt->execute([$searchTerm]);
                    $typePrime = (int)$stmt->fetchColumn();

                    if (!$typePrime) {
                        // Fallback: utiliser le premier type de prime disponible
                        $stmt = $conn->query("SELECT id FROM type_primes LIMIT 1");
                        $typePrime = (int)$stmt->fetchColumn();
                    }
                } else {
                    $typePrime = (int)$typePrimeInput;
                }

                // Débogage
                error_log("Attribuer prime - Input: " . json_encode($input));
                error_log("type_attribution=$typeAttribution, type_prime_input=$typePrimeInput, type_prime_id=$typePrime, montant=$montant, periode=$periode");

                if (!$typeAttribution || !$typePrime || !$montant || !$periode) {
                    $missing = [];
                    if (!$typeAttribution) $missing[] = 'type_attribution';
                    if (!$typePrime) $missing[] = 'type_prime (valeur reçue: ' . $typePrimeInput . ')';
                    if (!$montant) $missing[] = 'montant';
                    if (!$periode) $missing[] = 'periode';
                    throw new Exception('Champs manquants: ' . implode(', ', $missing));
                }

                if ($montant < 1000) {
                    throw new Exception('Le montant minimum est de 1 000 FCFA');
                }

                // Extraire mois et année de la période (format: YYYY-MM)
                $periodeParts = explode('-', $periode);
                $annee = (int)$periodeParts[0];
                $mois = (int)$periodeParts[1];

                // Déterminer les employés concernés
                $employes = [];

                if ($typeAttribution === 'INDIVIDUEL') {
                    $employeId = (int)($input['employe_id'] ?? 0);
                    if (!$employeId) {
                        throw new Exception('Employé requis pour une attribution individuelle');
                    }
                    $employes[] = $employeId;

                } elseif ($typeAttribution === 'DEPARTEMENT') {
                    $departementInput = $input['departement'] ?? '';

                    // Si c'est un texte (nom du département), trouver l'ID
                    if (!is_numeric($departementInput)) {
                        $stmt = $conn->prepare("SELECT id FROM departements WHERE nom = ? LIMIT 1");
                        $stmt->execute([$departementInput]);
                        $departementId = (int)$stmt->fetchColumn();

                        if (!$departementId) {
                            throw new Exception("Département '$departementInput' non trouvé dans la base de données");
                        }
                    } else {
                        $departementId = (int)$departementInput;
                    }

                    error_log("DEPARTEMENT attribution - input: $departementInput, ID trouvé: $departementId");

                    $stmt = $conn->prepare("
                        SELECT e.id
                        FROM employes e
                        LEFT JOIN postes p ON e.poste_id = p.id
                        WHERE p.departement_id = ? AND e.statut = 'actif'
                    ");
                    $stmt->execute([$departementId]);
                    $employes = $stmt->fetchAll(PDO::FETCH_COLUMN);

                } elseif ($typeAttribution === 'TOUS') {
                    $stmt = $conn->query("SELECT id FROM employes WHERE statut = 'actif'");
                    $employes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                }

                if (empty($employes)) {
                    throw new Exception('Aucun employé trouvé pour cette attribution');
                }

                // Insérer les primes pour chaque employé (éviter les doublons)
                $stmtCheck = $conn->prepare("
                    SELECT id FROM primes_employes
                    WHERE id_employe = ? AND id_type_prime = ? AND mois = ? AND annee = ?
                ");

                $stmtInsert = $conn->prepare("
                    INSERT INTO primes_employes (
                        id_employe, id_type_prime, mois, annee, montant,
                        criteres_performance, valide, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
                ");

                $count = 0;
                $skipped = 0;
                foreach ($employes as $employeId) {
                    // Vérifier si la prime existe déjà
                    $stmtCheck->execute([$employeId, $typePrime, $mois, $annee]);

                    if (!$stmtCheck->fetch()) {
                        // La prime n'existe pas, on peut l'insérer
                        $stmtInsert->execute([
                            $employeId,
                            $typePrime,
                            $mois,
                            $annee,
                            $montant,
                            $justification
                        ]);
                        $count++;
                    } else {
                        // Prime existe déjà, on la skip
                        $skipped++;
                    }
                }

                error_log("Primes attribuées - Type: $typeAttribution, Montant: $montant FCFA, Nouvelles: $count, Ignorées (doublons): $skipped");

                $message = "Prime attribuée à $count employé(s)";
                if ($skipped > 0) {
                    $message .= " ($skipped employé(s) avaient déjà cette prime pour cette période)";
                }

                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'count' => $count,
                    'skipped' => $skipped
                ]);
                break;

            case 'generer_primes_presence':
                $input = json_decode(file_get_contents('php://input'), true);

                // Validation
                $mois = (int)($input['mois'] ?? 0);
                $annee = (int)($input['annee'] ?? 0);
                $montantPresence = floatval($input['montant_presence'] ?? 0);
                $seuilPresence = floatval($input['seuil_presence'] ?? 95);

                if (!$mois || !$annee || !$montantPresence) {
                    throw new Exception('Données invalides: mois, année et montant requis');
                }

                // Récupérer le type de prime "Présence" (ou créer si n'existe pas)
                $stmt = $conn->query("SELECT id FROM type_primes WHERE nom = 'Présence' OR nom LIKE '%pr_sence%' LIMIT 1");
                $typePrimeId = $stmt->fetchColumn();

                if (!$typePrimeId) {
                    // Créer le type de prime
                    $stmt = $conn->prepare("INSERT INTO type_primes (nom, description) VALUES ('Présence', 'Prime de présence automatique')");
                    $stmt->execute();
                    $typePrimeId = $conn->lastInsertId();
                }

                // Récupérer tous les employés actifs
                $stmt = $conn->query("SELECT id FROM employes WHERE statut = 'actif'");
                $employes = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $count = 0;
                foreach ($employes as $employeId) {
                    // Calculer le taux de présence
                    $statsPresences = calculerHeuresParRapportPlanification($conn, $employeId, $mois, $annee);
                    $tauxPresence = $statsPresences['taux_presence'];

                    // Si le taux de présence est >= seuil, attribuer la prime
                    if ($tauxPresence >= $seuilPresence) {
                        // Vérifier si prime n'existe pas déjà
                        $stmt = $conn->prepare("
                            SELECT id FROM primes_employes
                            WHERE id_employe = ? AND id_type_prime = ? AND mois = ? AND annee = ?
                        ");
                        $stmt->execute([$employeId, $typePrimeId, $mois, $annee]);

                        if (!$stmt->fetch()) {
                            // Insérer la prime
                            $stmt = $conn->prepare("
                                INSERT INTO primes_employes (
                                    id_employe, id_type_prime, mois, annee, montant,
                                    criteres_performance, note_performance, valide, created_at
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
                            ");

                            $stmt->execute([
                                $employeId,
                                $typePrimeId,
                                $mois,
                                $annee,
                                $montantPresence,
                                "Prime de présence automatique - Taux: " . round($tauxPresence, 2) . "%",
                                round($tauxPresence, 1)
                            ]);

                            $count++;
                        }
                    }
                }

                error_log("Primes de présence générées - Période: $mois/$annee, Seuil: $seuilPresence%, Employés: $count");

                echo json_encode([
                    'success' => true,
                    'message' => "Primes de présence générées pour $count employé(s)",
                    'count' => $count
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
    // Récupération des employés avec logging pour diagnostic
    $employes = $employeesManager->getAllEmployees(['statut' => 'actif']);

    // Log pour diagnostic
    error_log("Gestion Paie - Nombre d'employés récupérés: " . count($employes));
    
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
    error_log("ERREUR CRITIQUE - Chargement données gestion_paie.php: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());

    // Initialiser toutes les variables avec des valeurs par défaut pour éviter les erreurs
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
    $csrf_token = SecurityManager::generateCSRFToken();
    $conges_attente = [];
    $avances_attente = [];
    $primes_attente = [];

    // Afficher un message d'erreur visible pour l'administrateur
    if (!headers_sent()) {
        $errorMessage = $e->getMessage();
        $isTableMissing = strpos($errorMessage, "Table") !== false || strpos($errorMessage, "table") !== false;

        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Erreur - Système RH</title>";
        echo "<style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #ef4444; border-bottom: 3px solid #ef4444; padding-bottom: 10px; }
            .error-box { background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; }
            .solution-box { background: #dbeafe; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; }
            .success-box { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; }
            .btn { display: inline-block; padding: 12px 24px; margin: 10px 5px; background: #4f46e5; color: white; text-decoration: none; border-radius: 5px; font-weight: 600; }
            .btn:hover { background: #4338ca; }
            .btn-success { background: #10b981; }
            .btn-success:hover { background: #059669; }
            .btn-secondary { background: #6b7280; }
            .btn-secondary:hover { background: #4b5563; }
            code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
            ul { line-height: 1.8; }
        </style></head><body><div class='container'>";

        echo "<h1>❌ Erreur de chargement du Système RH</h1>";

        echo "<div class='error-box'>";
        echo "<p><strong>🔴 Message d'erreur:</strong></p>";
        echo "<p><code>" . htmlspecialchars($errorMessage) . "</code></p>";
        echo "<p style='margin-top:10px;'><small><strong>Fichier:</strong> " . htmlspecialchars($e->getFile()) . " (ligne " . $e->getLine() . ")</small></p>";
        echo "</div>";

        if ($isTableMissing) {
            echo "<div class='success-box'>";
            echo "<h2 style='color:#10b981;margin-top:0;'>✅ Solution rapide</h2>";
            echo "<p><strong>La table n'existe pas dans votre base de données.</strong></p>";
            echo "<p>Cliquez sur le bouton ci-dessous pour créer automatiquement TOUTES les tables nécessaires au système RH :</p>";
            echo "<p style='text-align:center;margin:20px 0;'>";
            echo "<a href='create_rh_tables.php' class='btn btn-success' style='font-size:18px;padding:15px 30px;'>🗄️ Créer toutes les tables RH</a>";
            echo "</p>";
            echo "<p style='text-align:center;'><small>Ce script va créer automatiquement 11 tables (departements, postes, employes, horaires, presences, type_primes, primes_employes, conges, soldes_conges, avances_salaire, bulletins_paie)</small></p>";
            echo "</div>";
        }

        echo "<div class='solution-box'>";
        echo "<h2 style='color:#3b82f6;margin-top:0;'>🔧 Autres actions possibles</h2>";
        echo "<ul style='margin:0;'>";
        echo "<li><strong>Diagnostic complet :</strong> Vérifier l'état de toutes les tables et données</li>";
        echo "<li><strong>Initialiser les données de test :</strong> Créer des départements, postes et employés de démonstration</li>";
        echo "<li><strong>Consulter la documentation :</strong> Guide complet de résolution</li>";
        echo "</ul>";
        echo "<p style='text-align:center;margin-top:20px;'>";
        echo "<a href='diagnostic_employes.php' class='btn'>📊 Lancer le diagnostic</a>";
        echo "<a href='init_test_data.php' class='btn'>🎯 Créer données de test</a>";
        echo "<a href='README_GESTION_PAIE.md' class='btn btn-secondary' target='_blank'>📖 Documentation</a>";
        echo "</p>";
        echo "</div>";

        echo "<div style='margin-top:30px;padding:15px;background:#f9fafb;border-radius:5px;'>";
        echo "<h3 style='margin-top:0;'>ℹ️ Informations techniques</h3>";
        echo "<p>Pour que le système RH fonctionne correctement, vous devez avoir :</p>";
        echo "<ul>";
        echo "<li>Une base de données MySQL/MariaDB configurée</li>";
        echo "<li>Les 11 tables du système RH créées</li>";
        echo "<li>Au moins un département et un poste dans la base</li>";
        echo "<li>Au moins un employé avec le statut 'actif'</li>";
        echo "</ul>";
        echo "</div>";

        echo "</div></body></html>";
        exit;
    }
}

include 'views/paie/index.php';