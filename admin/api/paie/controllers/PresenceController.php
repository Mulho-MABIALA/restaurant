<?php
/**
 * Contrôleur pour la gestion des présences
 */

class PresenceController {
    private $conn;
    private $auditManager;

    public function __construct($conn, $auditManager) {
        $this->conn = $conn;
        $this->auditManager = $auditManager;
    }

    public function handleRequest($action) {
        switch ($action) {
            case 'get_presences_jour':
                $this->getPresencesJour();
                break;
            case 'get_details_presence_employe':
                $this->getDetailsPresenceEmploye();
                break;
            case 'ajouter_presence_manuelle':
                $this->ajouterPresenceManuelle();
                break;
            case 'verifier_coherence_planification':
                $this->verifierCoherencePlanification();
                break;
            case 'get_presence_stats_for_payroll':
                $this->getPresenceStatsForPayroll();
                break;
            default:
                throw new Exception("Action non gérée: $action");
        }
    }

    private function getPresencesJour() {
        $date = $_GET['date'] ?? date('Y-m-d');

        require_once __DIR__ . '/../../../includes/presence_functions.php';

        $stmt = $this->conn->prepare("
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
            $horairePlanifie = getHorairesEmploye($this->conn, $resultat['employe_id'], $date);
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
    }

    private function getDetailsPresenceEmploye() {
        $employeId = $_GET['employe_id'] ?? null;
        $date = $_GET['date'] ?? date('Y-m-d');

        if (!$employeId) {
            throw new Exception('ID employé requis');
        }

        require_once __DIR__ . '/../../../includes/presence_functions.php';

        // Informations de base
        $stmt = $this->conn->prepare("
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

        // Horaire planifié
        $horairePlanifie = getHorairesEmploye($this->conn, $employeId, $date);

        // Présence du jour
        $stmt = $this->conn->prepare("
            SELECT *,
                   TIME(heure_arrivee) as heure_arrivee_format,
                   TIME(heure_depart) as heure_depart_format,
                   CASE
                       WHEN heure_arrivee IS NOT NULL AND heure_depart IS NOT NULL
                       THEN TIMESTAMPDIFF(MINUTE, heure_arrivee, heure_depart) / 60.0
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

        // Statistiques du mois
        $mois = date('n', strtotime($date));
        $annee = date('Y', strtotime($date));
        $statsMois = calculerHeuresParRapportPlanification($this->conn, $employeId, $mois, $annee);

        echo json_encode([
            'success' => true,
            'employe' => $employe,
            'presence_jour' => $presenceJour,
            'horaire_planifie' => $horairePlanifie,
            'stats_mois' => $statsMois
        ]);
    }

    private function ajouterPresenceManuelle() {
        $input = json_decode(file_get_contents('php://input'), true);

        $validation = SecurityManager::validateInput($input, [
            'employe_id' => ['required' => true, 'type' => 'numeric'],
            'date' => ['required' => true, 'type' => 'date'],
            'heure_arrivee' => ['required' => true]
        ]);

        if (!empty($validation)) {
            throw new Exception('Données invalides: ' . implode(', ', $validation));
        }

        // Vérifier présence existante
        $stmt = $this->conn->prepare("
            SELECT id FROM presences
            WHERE employe_id = ? AND DATE(heure_arrivee) = ?
        ");
        $stmt->execute([$input['employe_id'], $input['date']]);
        if ($stmt->fetch()) {
            throw new Exception('Une présence existe déjà pour cette date');
        }

        // Créer les timestamps
        $heureArrivee = $input['date'] . ' ' . $input['heure_arrivee'];
        $heureDepart = null;

        if (!empty($input['heure_depart'])) {
            $heureDepart = $input['date'] . ' ' . $input['heure_depart'];

            if (strtotime($heureDepart) <= strtotime($heureArrivee)) {
                throw new Exception('L\'heure de départ doit être postérieure à l\'heure d\'arrivée');
            }
        }

        // Insérer
        $stmt = $this->conn->prepare("
            INSERT INTO presences
            (employe_id, heure_arrivee, heure_depart, commentaire, ajout_manuel, date_creation)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");

        $success = $stmt->execute([
            $input['employe_id'],
            $heureArrivee,
            $heureDepart,
            $input['commentaire'] ?? 'Ajout manuel par admin'
        ]);

        if ($success) {
            // Audit
            $this->auditManager->logPayrollAction('ADD_PRESENCE_MANUAL', [
                'employe_id' => $input['employe_id'],
                'date' => $input['date'],
                'heure_arrivee' => $input['heure_arrivee'],
                'heure_depart' => $input['heure_depart'] ?? null,
                'admin_id' => $_SESSION['admin_id'] ?? 1
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Présence ajoutée avec succès',
                'id' => $this->conn->lastInsertId()
            ]);
        } else {
            throw new Exception('Erreur lors de l\'enregistrement');
        }
    }

    private function verifierCoherencePlanification() {
        $date = $_GET['date'] ?? date('Y-m-d');
        $semaine_debut = date('Y-m-d', strtotime('monday', strtotime($date)));

        require_once __DIR__ . '/../../../includes/presence_functions.php';

        $planification = getPlanificationSemaine($this->conn, $semaine_debut);
        $incohérences = [];

        foreach ($planification as $planning) {
            $employeId = $planning['employe_id'];
            $statsPresences = calculerHeuresParRapportPlanification($this->conn, $employeId, date('n'), date('Y'));

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
    }

    private function getPresenceStatsForPayroll() {
        $employeeId = $_GET['employee_id'] ?? null;
        $month = $_GET['month'] ?? date('n');
        $year = $_GET['year'] ?? date('Y');

        if (!$employeeId) {
            throw new Exception('ID employé requis');
        }

        require_once __DIR__ . '/../../../classes/PresenceManager.php';
        $presenceManager = new PresenceManager($this->conn);

        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));

        $presenceStats = $presenceManager->getPresenceStatistics($employeeId, $startDate, $endDate);

        echo json_encode(['success' => true, 'presence_stats' => $presenceStats]);
    }
}
