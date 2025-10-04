<?php
/**
 * Contrôleur pour la gestion des congés
 */

class CongeController {
    private $conn;
    private $auditManager;

    public function __construct($conn, $auditManager) {
        $this->conn = $conn;
        $this->auditManager = $auditManager;
    }

    public function handleRequest($action) {
        switch ($action) {
            case 'creer_conge':
                $this->creerConge();
                break;
            case 'get_conge_details':
                $this->getCongeDetails();
                break;
            case 'get_conges_historique':
                $this->getCongesHistorique();
                break;
            case 'get_conges_calendrier':
                $this->getCongesCalendrier();
                break;
            case 'valider_conge':
                $this->validerConge();
                break;
            case 'get_solde_conges':
                $this->getSoldeConges();
                break;
            case 'initialiser_soldes_conges':
                $this->initialiserSoldesConges();
                break;
            case 'get_employes_pour_soldes':
                $this->getEmployesPourSoldes();
                break;
            default:
                throw new Exception("Action non gérée: $action");
        }
    }

    private function creerConge() {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validation
        $validation = SecurityManager::validateInput($data, [
            'employe_id' => ['required' => true, 'type' => 'numeric'],
            'type_conge' => ['required' => true],
            'date_debut' => ['required' => true, 'type' => 'date'],
            'date_fin' => ['required' => true, 'type' => 'date']
        ]);

        if (!empty($validation)) {
            throw new Exception(implode(', ', $validation));
        }

        // Calcul du nombre de jours
        $debut = new DateTime($data['date_debut']);
        $fin = new DateTime($data['date_fin']);

        if ($fin < $debut) {
            throw new Exception('La date de fin doit être postérieure à la date de début');
        }

        $nbJours = $debut->diff($fin)->days + 1;

        // Insertion
        $stmt = $this->conn->prepare("
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
            echo json_encode([
                'success' => true,
                'message' => 'Demande de congé créée avec succès',
                'id' => $this->conn->lastInsertId(),
                'nb_jours' => $nbJours
            ]);
        } else {
            throw new Exception('Erreur lors de la création de la demande');
        }
    }

    private function getCongeDetails() {
        $congeId = $_GET['conge_id'] ?? 0;

        if (!$congeId) {
            throw new Exception('ID congé requis');
        }

        $stmt = $this->conn->prepare("
            SELECT c.*, e.nom, e.prenom, e.email, p.nom as poste_nom
            FROM conges c
            INNER JOIN employes e ON c.employe_id = e.id
            LEFT JOIN postes p ON e.poste_id = p.id
            WHERE c.id = ?
        ");
        $stmt->execute([$congeId]);
        $conge = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$conge) {
            throw new Exception('Congé non trouvé');
        }

        echo json_encode(['success' => true, 'conge' => $conge]);
    }

    private function getCongesHistorique() {
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

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $conges = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'conges' => $conges]);
    }

    private function getCongesCalendrier() {
        $mois = $_GET['mois'] ?? date('n');
        $annee = $_GET['annee'] ?? date('Y');
        $employeId = $_GET['employe_id'] ?? '';

        $premierJour = "$annee-" . str_pad($mois, 2, '0', STR_PAD_LEFT) . "-01";
        $dernierJour = date('Y-m-t', strtotime($premierJour));

        $sql = "
            SELECT c.*, e.nom, e.prenom
            FROM conges c
            INNER JOIN employes e ON c.employe_id = e.id
            WHERE (
                (YEAR(c.date_debut) = ? AND MONTH(c.date_debut) = ?)
                OR (YEAR(c.date_fin) = ? AND MONTH(c.date_fin) = ?)
                OR (c.date_debut <= ? AND c.date_fin >= ?)
            )
        ";

        $params = [$annee, $mois, $annee, $mois, $dernierJour, $premierJour];

        if (!empty($employeId)) {
            $sql .= " AND c.employe_id = ?";
            $params[] = $employeId;
        }

        $sql .= " ORDER BY c.date_debut";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $conges = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'conges' => $conges]);
    }

    private function validerConge() {
        $data = json_decode(file_get_contents('php://input'), true);

        $validation = SecurityManager::validateInput($data, [
            'id_conge' => ['required' => true, 'type' => 'numeric'],
            'statut' => ['required' => true]
        ]);

        if (!empty($validation)) {
            throw new Exception(implode(', ', $validation));
        }

        $stmt = $this->conn->prepare("
            UPDATE conges
            SET statut = ?, commentaire = ?, date_validation = NOW()
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
    }

    private function getSoldeConges() {
        $employeId = $_GET['employe_id'] ?? 0;

        if (!$employeId) {
            throw new Exception('ID employé requis');
        }

        $stmt = $this->conn->prepare("
            SELECT COALESCE(solde_annuel, 25) as annuel,
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
            // Créer un solde par défaut
            $stmt_create = $this->conn->prepare("
                INSERT INTO soldes_conges
                (employe_id, annee, solde_annuel, solde_maladie, solde_restant_annuel, solde_restant_maladie, date_creation)
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
    }

    private function initialiserSoldesConges() {
        $input = json_decode(file_get_contents('php://input'), true);

        $validation = SecurityManager::validateInput($input, [
            'annee' => ['required' => true, 'type' => 'numeric'],
            'solde_annuel' => ['required' => true, 'type' => 'numeric'],
            'solde_maladie' => ['required' => true, 'type' => 'numeric']
        ]);

        if (!empty($validation)) {
            throw new Exception(implode(', ', $validation));
        }

        require_once __DIR__ . '/../../../classes/EmployeesManager.php';
        $employeesManager = new EmployeesManager($this->conn);

        // Récupérer les employés concernés
        $filters = ['statut' => 'actif'];
        if (!empty($input['filtres']['departement_id'])) {
            $filters['departement_id'] = $input['filtres']['departement_id'];
        }
        if (!empty($input['filtres']['type_contrat'])) {
            $filters['type_contrat'] = $input['filtres']['type_contrat'];
        }

        $employes = $employeesManager->getAllEmployees($filters);

        $count = 0;
        foreach ($employes as $employe) {
            try {
                $stmt = $this->conn->prepare("
                    INSERT INTO soldes_conges
                    (employe_id, annee, solde_annuel, solde_maladie, solde_restant_annuel, solde_restant_maladie, date_creation)
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
                    $input['solde_annuel'],
                    $input['solde_maladie']
                ]);

                if ($success) $count++;
            } catch (Exception $e) {
                error_log("Erreur initialisation solde employé {$employe['id']}: " . $e->getMessage());
            }
        }

        // Audit
        $this->auditManager->logPayrollAction('INIT_SOLDES_CONGES', [
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
    }

    private function getEmployesPourSoldes() {
        require_once __DIR__ . '/../../../classes/EmployeesManager.php';
        $employeesManager = new EmployeesManager($this->conn);

        $filters = ['statut' => 'actif'];
        if (!empty($_GET['departement_id'])) $filters['departement_id'] = $_GET['departement_id'];
        if (!empty($_GET['type_contrat'])) $filters['type_contrat'] = $_GET['type_contrat'];

        $employes = $employeesManager->getAllEmployees($filters);
        echo json_encode(['success' => true, 'employes' => $employes]);
    }
}
