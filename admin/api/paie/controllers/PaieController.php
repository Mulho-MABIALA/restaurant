<?php
/**
 * Contrôleur pour la gestion de la paie
 */

class PaieController {
    private $conn;
    private $auditManager;
    private $paieManager;
    private $payrollCalculator;
    private $employeesManager;

    public function __construct($conn, $auditManager) {
        $this->conn = $conn;
        $this->auditManager = $auditManager;

        require_once __DIR__ . '/../../../classes/PaieManager.php';
        require_once __DIR__ . '/../../../classes/PayrollCalculator.php';
        require_once __DIR__ . '/../../../classes/EmployeesManager.php';
        require_once __DIR__ . '/../../../classes/BulletinPDFGenerateur.php';

        $this->paieManager = new PaieManager($conn);
        $this->payrollCalculator = new PayrollCalculator($conn);
        $this->employeesManager = new EmployeesManager($conn);
    }

    public function handleRequest($action) {
        switch ($action) {
            case 'get_bulletins':
                $this->getBulletins();
                break;
            case 'get_bulletin_details':
                $this->getBulletinDetails();
                break;
            case 'generer_bulletin':
                $this->genererBulletin();
                break;
            case 'generer_bulletin_integre':
                $this->genererBulletinIntegre();
                break;
            case 'generer_bulletins_masse':
                $this->genererBulletinsMasse();
                break;
            case 'preview_employes_masse':
                $this->previewEmployesMasse();
                break;
            case 'modifier_bulletin':
                $this->modifierBulletin();
                break;
            case 'supprimer_bulletin':
                $this->supprimerBulletin();
                break;
            case 'valider_bulletin':
                $this->validerBulletin();
                break;
            case 'voir_bulletin':
                $this->voirBulletin();
                break;
            case 'telecharger_bulletin':
                $this->telechargerBulletin();
                break;
            case 'export_csv':
                $this->exportCSV();
                break;
            case 'calculate_payroll_with_presences':
                $this->calculatePayrollWithPresences();
                break;
            case 'get_employee_payroll_data':
                $this->getEmployeePayrollData();
                break;
            default:
                throw new Exception("Action non gérée: $action");
        }
    }

    private function getBulletins() {
        $bulletins = $this->paieManager->getBulletins($_GET);
        echo json_encode(['success' => true, 'bulletins' => $bulletins]);
    }

    private function getBulletinDetails() {
        $bulletinId = $_GET['bulletin_id'] ?? 0;
        $bulletin = $this->paieManager->getBulletinDetails($bulletinId);
        echo json_encode(['success' => true, 'bulletin' => $bulletin]);
    }

    private function genererBulletin() {
        $data = json_decode(file_get_contents('php://input'), true);

        $result = $this->payrollCalculator->genererBulletin(
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
    }

    private function genererBulletinIntegre() {
        $input = json_decode(file_get_contents('php://input'), true);

        // Validation
        $validation = SecurityManager::validateInput($input, [
            'employe_id' => ['required' => true, 'type' => 'numeric'],
            'mois' => ['required' => true, 'type' => 'numeric'],
            'annee' => ['required' => true, 'type' => 'numeric']
        ]);

        if (!empty($validation)) {
            throw new Exception(implode(', ', $validation));
        }

        // Calcul avec présences
        $calculData = $this->paieManager->calculatePayroll(
            $input['employe_id'],
            $input['mois'],
            $input['annee'],
            $input
        );

        // Génération bulletin
        $bulletinId = $this->paieManager->generateBulletin($calculData);

        // Audit
        $this->auditManager->logPayrollAction('GENERATE_BULLETIN_INTEGRE', [
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
    }

    private function genererBulletinsMasse() {
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

        $employes = $this->employeesManager->getAllEmployees($filters);

        $count = 0;
        $errors = [];

        foreach ($employes as $employe) {
            try {
                // Vérifier s'il existe déjà un bulletin
                if ($input['options']['ignorer_existants']) {
                    $stmt = $this->conn->prepare("
                        SELECT id FROM bulletins_paie
                        WHERE employe_id = ? AND mois = ? AND annee = ?
                    ");
                    $stmt->execute([$employe['id'], $input['mois'], $input['annee']]);
                    if ($stmt->fetch()) {
                        continue;
                    }
                }

                // Préparer les options
                $optionsCalcul = array_merge($input['options'], [
                    'employe_id' => $employe['id'],
                    'avec_presences' => $input['mode_generation'] === 'INTEGRE'
                ]);

                // Générer selon le mode
                if ($input['mode_generation'] === 'INTEGRE') {
                    $calculData = $this->paieManager->calculatePayroll(
                        $employe['id'],
                        $input['mois'],
                        $input['annee'],
                        $optionsCalcul
                    );
                    $this->paieManager->generateBulletin($calculData);
                } else {
                    $result = $this->payrollCalculator->genererBulletin(
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

        // Audit
        $this->auditManager->logPayrollAction('GENERATE_BULLETINS_MASSE', [
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
    }

    private function previewEmployesMasse() {
        $filters = ['statut' => 'actif'];
        if (!empty($_GET['departement_id'])) $filters['departement_id'] = $_GET['departement_id'];
        if (!empty($_GET['type_contrat'])) $filters['type_contrat'] = $_GET['type_contrat'];

        $mois = $_GET['mois'] ?? date('n');
        $annee = $_GET['annee'] ?? date('Y');
        $ignoreExistants = $_GET['ignorer_existants'] === 'true';

        $employes = $this->employeesManager->getAllEmployees($filters);

        // Vérifier bulletins existants
        foreach ($employes as &$employe) {
            $stmt = $this->conn->prepare("
                SELECT id FROM bulletins_paie
                WHERE employe_id = ? AND mois = ? AND annee = ?
            ");
            $stmt->execute([$employe['id'], $mois, $annee]);
            $employe['has_bulletin'] = $stmt->fetch() ? true : false;
        }

        echo json_encode(['success' => true, 'employes' => $employes]);
    }

    private function modifierBulletin() {
        $input = json_decode(file_get_contents('php://input'), true);
        $bulletinId = $input['bulletin_id'];

        $stmt = $this->conn->prepare("
            UPDATE bulletins_paie
            SET salaire_base = ?,
                heures_supplementaires = ?,
                jours_absences = ?,
                montant_avances_remboursees = ?,
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

        echo json_encode(['success' => $success]);
    }

    private function supprimerBulletin() {
        $data = json_decode(file_get_contents('php://input'), true);
        $bulletinId = $data['bulletin_id'];

        $stmt = $this->conn->prepare("
            DELETE FROM bulletins_paie
            WHERE id_bulletin = ? AND statut = 'brouillon'
        ");
        $success = $stmt->execute([$bulletinId]);

        echo json_encode(['success' => $success]);
    }

    private function validerBulletin() {
        $input = json_decode(file_get_contents('php://input'), true);
        $bulletinId = $input['bulletin_id'];

        $stmt = $this->conn->prepare("
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
    }

    private function voirBulletin() {
        $id = $_GET['id'] ?? 0;
        $bulletin = $this->paieManager->getBulletinDetails($id);

        if ($bulletin) {
            $pdfGenerator = new BulletinPDFGenerateur([
                'nom' => 'Restaurant Mulho',
                'adresse' => '123 Avenue de la République, Dakar, Sénégal',
                'telephone' => '+221 78 730 06',
                'email' => 'mulhomabiala29@gmail.com',
                'ninea' => '123456789'
            ]);

            $pdfGenerator->afficherBulletin($bulletin);
        } else {
            http_response_code(404);
            echo "Bulletin non trouvé";
        }
        exit;
    }

    private function telechargerBulletin() {
        $id = $_GET['id'] ?? 0;
        $bulletin = $this->paieManager->getBulletinDetails($id);

        if ($bulletin) {
            $pdfGenerator = new BulletinPDFGenerateur([
                'nom' => 'Restaurant Mulho',
                'adresse' => '123 Avenue de la République, Dakar, Sénégal',
                'telephone' => '+221 78 730 06',
                'email' => 'mulhomabiala29@gmail.com',
                'ninea' => '123456789'
            ]);

            $pdfGenerator->telechargerBulletin($bulletin);
        } else {
            http_response_code(404);
            echo "Bulletin non trouvé";
        }
        exit;
    }

    private function exportCSV() {
        $mois = $_GET['mois'] ?? date('n');
        $annee = $_GET['annee'] ?? date('Y');

        $stmt = $this->conn->prepare("
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
            'Employé', 'Poste', 'Salaire Base', 'Heures Sup.',
            'Primes', 'Avances', 'Salaire Brut', 'Salaire Net', 'Statut'
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
    }

    private function calculatePayrollWithPresences() {
        $input = json_decode(file_get_contents('php://input'), true);

        $validation = SecurityManager::validateInput($input, [
            'employe_id' => ['required' => true, 'type' => 'numeric'],
            'mois' => ['required' => true, 'type' => 'numeric'],
            'annee' => ['required' => true, 'type' => 'numeric']
        ]);

        if (!empty($validation)) {
            throw new Exception('Données invalides: ' . implode(', ', $validation));
        }

        // Calcul avec intégration des présences
        $calculData = $this->paieManager->calculatePayroll(
            $input['employe_id'],
            $input['mois'],
            $input['annee'],
            $input['options'] ?? []
        );

        echo json_encode(['success' => true, 'calcul' => $calculData]);
    }

    private function getEmployeePayrollData() {
        $employeeId = $_GET['employee_id'] ?? null;
        $period = $_GET['period'] ?? date('Y-m');

        if (!$employeeId) {
            throw new Exception('ID employé requis');
        }

        $data = $this->employeesManager->getEmployeePayrollData($employeeId, $period);
        echo json_encode(['success' => true, 'data' => $data]);
    }
}
