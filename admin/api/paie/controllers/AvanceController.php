<?php
/**
 * Contrôleur pour la gestion des avances sur salaire
 */

class AvanceController {
    private $conn;
    private $auditManager;

    public function __construct($conn, $auditManager) {
        $this->conn = $conn;
        $this->auditManager = $auditManager;
    }

    public function handleRequest($action) {
        switch ($action) {
            case 'creer_avance':
                $this->creerAvance();
                break;
            case 'valider_avance':
                $this->validerAvance();
                break;
            case 'get_avances_historique':
                $this->getAvancesHistorique();
                break;
            case 'get_rapport_avances_detaille':
                $this->getRapportAvancesDetaille();
                break;
            case 'export_rapport_avances':
                $this->exportRapportAvances();
                break;
            default:
                throw new Exception("Action non gérée: $action");
        }
    }

    private function creerAvance() {
        $data = json_decode(file_get_contents('php://input'), true);

        $validation = SecurityManager::validateInput($data, [
            'employe_id' => ['required' => true, 'type' => 'numeric'],
            'montant' => ['required' => true, 'type' => 'numeric'],
            'motif' => ['required' => true]
        ]);

        if (!empty($validation)) {
            throw new Exception(implode(', ', $validation));
        }

        // Calculer le nombre de mensualités
        $nbMensualites = 1;
        $mode = $data['mode_remboursement'] ?? 'UNIQUE';
        if ($mode !== 'UNIQUE') {
            $nbMensualites = (int) str_replace('MENSUEL_', '', $mode);
        }

        $stmt = $this->conn->prepare("
            INSERT INTO avances_salaire
            (id_employe, montant_demande, motif, statut, nb_mensualites, date_demande, demande_par)
            VALUES (?, ?, ?, 'en_attente', ?, NOW(), ?)
        ");

        $success = $stmt->execute([
            $data['employe_id'],
            $data['montant'],
            $data['motif'],
            $nbMensualites,
            $data['employe_id']
        ]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Demande d\'avance créée']);
        } else {
            throw new Exception('Erreur lors de la création de la demande');
        }
    }

    private function validerAvance() {
        $data = json_decode(file_get_contents('php://input'), true);

        $validation = SecurityManager::validateInput($data, [
            'id_avance' => ['required' => true, 'type' => 'numeric'],
            'statut' => ['required' => true]
        ]);

        if (!empty($validation)) {
            throw new Exception(implode(', ', $validation));
        }

        $stmt = $this->conn->prepare("
            UPDATE avances_salaire
            SET statut = ?,
                commentaire_validation = ?,
                date_validation = NOW(),
                valide_par = ?
            WHERE id = ?
        ");

        $success = $stmt->execute([
            $data['statut'],
            $data['commentaire'] ?? '',
            1, // ID admin - à adapter
            $data['id_avance']
        ]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Avance ' . $data['statut']]);
        } else {
            throw new Exception('Erreur lors de la validation');
        }
    }

    private function getAvancesHistorique() {
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

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $avances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'avances' => $avances]);
    }

    private function getRapportAvancesDetaille() {
        $debut = $_GET['debut'] ?? '';
        $fin = $_GET['fin'] ?? '';
        $statut = $_GET['statut'] ?? '';

        if (empty($debut) || empty($fin)) {
            throw new Exception('Dates requises');
        }

        $rapport = [];

        // Avances
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

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $rapport['avances'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Statistiques générales
        $sqlStats = "
            SELECT COUNT(*) as total_avances,
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

        $stmt = $this->conn->prepare($sqlStats);
        $stmt->execute($paramsStats);
        $rapport['stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Top employés
        $sqlTop = "
            SELECT CONCAT(e.prenom, ' ', e.nom) as nom_complet,
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

        $sqlTop .= " GROUP BY e.id, e.prenom, e.nom ORDER BY montant_total DESC LIMIT 10";

        $stmt = $this->conn->prepare($sqlTop);
        $stmt->execute($paramsTop);
        $rapport['top_employes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Évolution mensuelle
        $sqlEvolution = "
            SELECT DATE_FORMAT(a.date_demande, '%Y-%m') as mois,
                   COUNT(*) as nb_avances,
                   SUM(a.montant_demande) as montant_total
            FROM avances_salaire a
            WHERE DATE(a.date_demande) BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(a.date_demande, '%Y-%m')
            ORDER BY mois
        ";

        $stmt = $this->conn->prepare($sqlEvolution);
        $stmt->execute([$debut, $fin]);
        $rapport['evolution_mensuelle'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'rapport' => $rapport]);
    }

    private function exportRapportAvances() {
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
            INNER JOIN employes e ON a.id_employe = e.id
            LEFT JOIN postes p ON e.poste_id = p.id
            LEFT JOIN departements d ON p.departement_id = d.id
            WHERE DATE(a.date_demande) BETWEEN ? AND ?
        ";
        $params = [$debut, $fin];

        if (!empty($statut)) {
            $sql .= " AND a.statut = ?";
            $params[] = $statut;
        }

        $sql .= " ORDER BY a.date_demande DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $avances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Export CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rapport_avances_' . $debut . '_' . $fin . '.csv"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

        // En-têtes
        fputcsv($output, [
            'Date demande', 'Employé', 'Email', 'Poste', 'Département',
            'Montant (FCFA)', 'Motif', 'Mode remboursement', 'Statut',
            'Date validation', 'Commentaire'
        ], ';');

        // Données
        foreach ($avances as $avance) {
            fputcsv($output, [
                date('d/m/Y', strtotime($avance['date_demande'])),
                $avance['employe_nom'],
                $avance['employe_email'],
                $avance['poste_nom'] ?: 'N/A',
                $avance['departement_nom'] ?: 'N/A',
                number_format($avance['montant_demande'], 0, ',', ' '),
                $avance['motif'],
                $avance['nb_mensualites'] == 1 ? 'UNIQUE' : "MENSUEL_{$avance['nb_mensualites']}",
                ucfirst(str_replace('_', ' ', $avance['statut'])),
                $avance['date_validation'] ? date('d/m/Y', strtotime($avance['date_validation'])) : '',
                $avance['commentaire_validation'] ?: ''
            ], ';');
        }

        fclose($output);
        exit;
    }
}
