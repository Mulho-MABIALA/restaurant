<?php
/**
 * Contrôleur pour la gestion des primes
 */

class PrimeController {
    private $conn;
    private $auditManager;

    public function __construct($conn, $auditManager) {
        $this->conn = $conn;
        $this->auditManager = $auditManager;
    }

    public function handleRequest($action) {
        switch ($action) {
            case 'attribuer_prime':
                $this->attribuerPrime();
                break;
            case 'valider_prime':
                $this->validerPrime();
                break;
            case 'get_primes_historique':
                $this->getPrimesHistorique();
                break;
            case 'get_prime_details':
                $this->getPrimeDetails();
                break;
            case 'generer_primes_presence':
                $this->genererPrimesPresence();
                break;
            default:
                throw new Exception("Action non gérée: $action");
        }
    }

    private function attribuerPrime() {
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

        // Mapping type de prime
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
                $stmt = $this->conn->prepare("
                    SELECT e.id
                    FROM employes e
                    INNER JOIN postes p ON e.poste_id = p.id
                    INNER JOIN departements d ON p.departement_id = d.id
                    WHERE d.nom = ? AND e.statut = 'actif'
                ");
                $stmt->execute([$data['departement']]);
                $employes_cibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;

            case 'TOUS':
                $stmt = $this->conn->query("SELECT id FROM employes WHERE statut = 'actif'");
                $employes_cibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
                break;
        }

        if (empty($employes_cibles)) {
            throw new Exception('Aucun employé trouvé pour cette attribution');
        }

        // Insertion des primes
        $periode_parts = explode('-', $data['periode']);
        $mois = (int)$periode_parts[1];
        $annee = (int)$periode_parts[0];

        $stmt = $this->conn->prepare("
            INSERT INTO primes_employes
            (id_employe, id_type_prime, mois, annee, montant, criteres_performance, valide, created_at)
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
    }

    private function validerPrime() {
        $data = json_decode(file_get_contents('php://input'), true);

        $validation = SecurityManager::validateInput($data, [
            'id_prime' => ['required' => true, 'type' => 'numeric']
        ]);

        if (!empty($validation)) {
            throw new Exception(implode(', ', $validation));
        }

        $statut = $data['statut'] ?? 'valide';
        $valide = ($statut === 'valide') ? 1 : 0;

        $stmt = $this->conn->prepare("
            UPDATE primes_employes
            SET valide = ?,
                commentaire = ?,
                date_validation = NOW(),
                valide_par = ?
            WHERE id = ?
        ");

        $success = $stmt->execute([
            $valide,
            $data['commentaire'] ?? '',
            1, // ID admin
            $data['id_prime']
        ]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Prime ' . $statut]);
        } else {
            throw new Exception('Erreur lors de la validation');
        }
    }

    private function getPrimesHistorique() {
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

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $primes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'primes' => $primes]);
    }

    private function getPrimeDetails() {
        $primeId = $_GET['prime_id'] ?? 0;

        if (!$primeId) {
            throw new Exception('ID prime requis');
        }

        $stmt = $this->conn->prepare("
            SELECT p.*, e.nom, e.prenom, e.email,
                   po.nom as poste_nom,
                   tp.nom as type_prime_nom,
                   tp.description as type_prime_description,
                   v.nom as valideur_nom,
                   v.prenom as valideur_prenom
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
            throw new Exception('Prime non trouvée');
        }

        echo json_encode(['success' => true, 'prime' => $prime]);
    }

    private function genererPrimesPresence() {
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

        // Fonctionnalité en développement
        echo json_encode([
            'success' => true,
            'message' => 'Fonctionnalité en cours de développement',
            'count' => 0
        ]);
    }
}
