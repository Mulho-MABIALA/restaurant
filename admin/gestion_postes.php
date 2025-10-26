<?php
    require_once '../config.php';
    session_start();

    // Vérifie l'accès admin
    if (! isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }

    // Configuration des types de contrat
    const TYPES_CONTRAT = [
        'CDI'           => 'Contrat à Durée Indéterminée',
        'CDD'           => 'Contrat à Durée Déterminée',
        'STAGE'         => 'Stage',
        'APPRENTISSAGE' => 'Contrat d\'Apprentissage',
        'CONSULTANT'    => 'Consultant',
        'SAISONNIER'    => 'Contrat Saisonnier',
    ];
    class PosteManager
    {
        private $conn;

        public function __construct($connection)
        {
            $this->conn = $connection;
        }
         public function getAllPostes()
    {
        $stmt = $this->conn->query("
            SELECT p.*,
            (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif') as nb_employes,
            ps.nom as poste_superieur_nom,
            nh.libelle as niveau_libelle,
            d.nom as departement_nom,
            d.responsable_nom as departement_responsable_nom,
            d.responsable_prenom as departement_responsable_prenom
            FROM postes p
            LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
            LEFT JOIN niveaux_hierarchiques nh ON p.niveau_hierarchique = nh.niveau
            LEFT JOIN departements d ON p.departement_id = d.id
            WHERE p.actif = TRUE
            ORDER BY p.niveau_hierarchique, p.nom
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


        /**
         * Crée un nouveau poste
         */
        public function createPoste($data)
        {
            // Validation
            if (empty($data['nom'])) {
                throw new Exception('Le nom du poste est requis');
            }

            // Vérifier unicité
            $stmt = $this->conn->prepare("SELECT id FROM postes WHERE nom = ? AND actif = TRUE");
            $stmt->execute([$data['nom']]);
            if ($stmt->fetch()) {
                throw new Exception('Un poste avec ce nom existe déjà');
            }

            // Insertion avec département
            $stmt = $this->conn->prepare("
        INSERT INTO postes (nom, description, salaire, couleur, type_contrat,
                          niveau_hierarchique, poste_superieur_id, competences_requises,
                          nombre_postes_prevus, duree_contrat, avantages, code_paie,
                          categorie_paie, regime_social, taux_cotisation, heures_travail,
                          departement_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

            $stmt->execute([
                $data['nom'],
                $data['description'] ?? null,
                intval($data['salaire'] ?? 0),
                $data['couleur'] ?? '#3B82F6',
                $data['type_contrat'] ?? 'CDI',
                ! empty($data['niveau_hierarchique']) ? intval($data['niveau_hierarchique']) : null,
                ! empty($data['poste_superieur_id']) ? $data['poste_superieur_id'] : null,
                $data['competences_requises'] ?? null,
                intval($data['nombre_postes_prevus'] ?? 1),
                $data['duree_contrat'] ?? null,
                $data['avantages'] ?? null,
                $data['code_paie'] ?? null,
                $data['categorie_paie'] ?? null,
                $data['regime_social'] ?? null,
                $data['taux_cotisation'] ?? null,
                intval($data['heures_travail'] ?? 35),
                ! empty($data['departement_id']) ? $data['departement_id'] : null,
            ]);

            return $this->conn->lastInsertId();
        }

        public function updatePoste($id, $data)
        {
            if (empty($data['nom'])) {
                throw new Exception('Le nom du poste est requis');
            }

            // Vérifier unicité (excluant le poste actuel)
            $stmt = $this->conn->prepare("SELECT id FROM postes WHERE nom = ? AND id != ? AND actif = TRUE");
            $stmt->execute([$data['nom'], $id]);
            if ($stmt->fetch()) {
                throw new Exception('Un poste avec ce nom existe déjà');
            }

            $stmt = $this->conn->prepare("
        UPDATE postes SET
            nom = ?,
            description = ?,
            salaire = ?,
            couleur = ?,
            type_contrat = ?,
            niveau_hierarchique = ?,
            poste_superieur_id = ?,
            competences_requises = ?,
            nombre_postes_prevus = ?,
            duree_contrat = ?,
            avantages = ?,
            code_paie = ?,
            categorie_paie = ?,
            regime_social = ?,
            taux_cotisation = ?,
            heures_travail = ?,
            departement_id = ?
        WHERE id = ? AND actif = TRUE
    ");

            $result = $stmt->execute([
                $data['nom'],
                $data['description'] ?? null,
                intval($data['salaire'] ?? 0),
                $data['couleur'] ?? '#3B82F6',
                $data['type_contrat'] ?? 'CDI',
                ! empty($data['niveau_hierarchique']) ? intval($data['niveau_hierarchique']) : null,
                ! empty($data['poste_superieur_id']) ? $data['poste_superieur_id'] : null,
                $data['competences_requises'] ?? null,
                intval($data['nombre_postes_prevus'] ?? 1),
                $data['duree_contrat'] ?? null,
                $data['avantages'] ?? null,
                $data['code_paie'] ?? null,
                $data['categorie_paie'] ?? null,
                $data['regime_social'] ?? null,
                $data['taux_cotisation'] ?? null,
                intval($data['heures_travail'] ?? 35),
                ! empty($data['departement_id']) ? $data['departement_id'] : null, // AJOUT DE CE CHAMP
                $id,                                                              // L'ID doit être en dernier
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Poste non trouvé ou non modifiable');
            }

            return true;
        }

        public function deletePoste($id)
        {
            // Vérifications de sécurité
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM employes WHERE poste_id = ? AND statut != 'inactif'");
            $stmt->execute([$id]);
            $nb_employees = $stmt->fetchColumn();

            if ($nb_employees > 0) {
                throw new Exception("Impossible de supprimer ce poste car $nb_employees employé(s) y sont associé(s)");
            }

            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM postes WHERE poste_superieur_id = ? AND actif = TRUE");
            $stmt->execute([$id]);
            $nb_subordonnes = $stmt->fetchColumn();

            if ($nb_subordonnes > 0) {
                throw new Exception("Impossible de supprimer ce poste car $nb_subordonnes poste(s) en dépendent hiérarchiquement");
            }

            // Désactivation
            $stmt = $this->conn->prepare("UPDATE postes SET actif = FALSE WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Poste non trouvé');
            }

            return true;
        }

        public function duplicatePoste($id)
        {
            $stmt = $this->conn->prepare("SELECT * FROM postes WHERE id = ? AND actif = TRUE");
            $stmt->execute([$id]);
            $original = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $original) {
                throw new Exception('Poste non trouvé');
            }

            // Générer nom unique
            $nouveau_nom = $original['nom'] . ' (Copie)';
            $counter     = 1;

            while (true) {
                $stmt = $this->conn->prepare("SELECT id FROM postes WHERE nom = ? AND actif = TRUE");
                $stmt->execute([$nouveau_nom]);
                if (! $stmt->fetch()) {
                    break;
                }

                $counter++;
                $nouveau_nom = $original['nom'] . ' (Copie ' . $counter . ')';
            }

            // Créer copie avec le nouveau nom
            $original['nom'] = $nouveau_nom;
            unset($original['id']); // Supprimer l'ID original

            // S'assurer que tous les champs nécessaires sont présents
            $dataToInsert = [
                'nom'                  => $original['nom'],
                'description'          => $original['description'] ?? null,
                'salaire'              => $original['salaire'] ?? 0,
                'couleur'              => $original['couleur'] ?? '#3B82F6',
                'type_contrat'         => $original['type_contrat'] ?? 'CDI',
                'niveau_hierarchique'  => $original['niveau_hierarchique'] ?? null,
                'poste_superieur_id'   => $original['poste_superieur_id'] ?? null,
                'competences_requises' => $original['competences_requises'] ?? null,
                'nombre_postes_prevus' => $original['nombre_postes_prevus'] ?? 1,
                'duree_contrat'        => $original['duree_contrat'] ?? null,
                'avantages'            => $original['avantages'] ?? null,
                'code_paie'            => $original['code_paie'] ?? null,
                'categorie_paie'       => $original['categorie_paie'] ?? null,
                'regime_social'        => $original['regime_social'] ?? null,
                'taux_cotisation'      => $original['taux_cotisation'] ?? null,
                'heures_travail'       => $original['heures_travail'] ?? 35,
                'departement_id'       => $original['departement_id'] ?? null,
            ];

            return $this->createPoste($dataToInsert);
        }

         public function searchPostes($filters)
    {
        $sql = "SELECT p.*,
                (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif') as nb_employes,
                ps.nom as poste_superieur_nom, 
                nh.libelle as niveau_libelle,
                d.nom as departement_nom,
                d.responsable_nom as departement_responsable_nom,
                d.responsable_prenom as departement_responsable_prenom
                FROM postes p
                LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
                LEFT JOIN niveaux_hierarchiques nh ON p.niveau_hierarchique = nh.niveau
                LEFT JOIN departements d ON p.departement_id = d.id
                WHERE p.actif = TRUE";

        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (p.nom LIKE ? OR p.description LIKE ? OR p.competences_requises LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['type_contrat'])) {
            $sql .= " AND p.type_contrat = ?";
            $params[] = $filters['type_contrat'];
        }

        if (!empty($filters['niveau_hierarchique'])) {
            $sql .= " AND p.niveau_hierarchique = ?";
            $params[] = $filters['niveau_hierarchique'];
        }

        if (!empty($filters['departement_id'])) {
            $sql .= " AND p.departement_id = ?";
            $params[] = $filters['departement_id'];
        }

        $sql .= " ORDER BY p.niveau_hierarchique, p.nom";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

    class PosteSuperieurManager
    {
        private $conn;

        public function __construct($connection)
        {
            $this->conn = $connection;
        }

        /**
         * Vérifie si l'utilisateur est admin
         */
        private function checkAdminAccess()
        {
            if (! isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
                throw new Exception('Accès refusé. Seuls les administrateurs peuvent gérer les postes supérieurs.');
            }
        }

        /**
         * Récupère tous les postes supérieurs possibles
         */
        public function getAvailablePostesSupérieurs($excludeId = null)
        {
            $this->checkAdminAccess();

            $sql = "SELECT p.*,
                       nh.libelle as niveau_libelle,
                       d.nom as departement_nom
                FROM postes p
                LEFT JOIN niveaux_hierarchiques nh ON p.niveau_hierarchique = nh.niveau
                LEFT JOIN departements d ON p.departement_id = d.id
                WHERE p.actif = TRUE";

            $params = [];
            if ($excludeId) {
                $sql .= " AND p.id != ?";
                $params[] = $excludeId;
            }

            $sql .= " ORDER BY p.niveau_hierarchique ASC, p.nom";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * Définit un poste supérieur
         */
        public function setPosteSuperior($posteId, $posteSuperieurId)
        {
            $this->checkAdminAccess();

            // Vérifications de sécurité
            if ($posteId == $posteSuperieurId) {
                throw new Exception('Un poste ne peut pas être son propre supérieur');
            }

            // Vérifier que le poste supérieur existe
            if ($posteSuperieurId) {
                $stmt = $this->conn->prepare("SELECT id FROM postes WHERE id = ? AND actif = TRUE");
                $stmt->execute([$posteSuperieurId]);
                if (! $stmt->fetch()) {
                    throw new Exception('Le poste supérieur spécifié n\'existe pas');
                }
            }

            // Vérifier qu'on ne crée pas de cycle dans la hiérarchie
            if ($posteSuperieurId && $this->detectsCycle($posteId, $posteSuperieurId)) {
                throw new Exception('Cette assignation créerait une boucle dans la hiérarchie');
            }

            // Mise à jour
            $stmt = $this->conn->prepare("UPDATE postes SET poste_superieur_id = ? WHERE id = ? AND actif = TRUE");
            $stmt->execute([$posteSuperieurId ?: null, $posteId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Poste non trouvé ou non modifiable');
            }

            return true;
        }

        /**
         * Supprime la relation de supériorité d'un poste
         */
        public function removePosteSuperior($posteId)
        {
            $this->checkAdminAccess();

            $stmt = $this->conn->prepare("UPDATE postes SET poste_superieur_id = NULL WHERE id = ? AND actif = TRUE");
            $stmt->execute([$posteId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Poste non trouvé');
            }

            return true;
        }

        private function detectsCycle($posteId, $posteSuperieurId, $visited = [])
        {
            if (in_array($posteSuperieurId, $visited)) {
                return true; // Cycle détecté
            }

            $visited[] = $posteSuperieurId;

            $stmt = $this->conn->prepare("SELECT poste_superieur_id FROM postes WHERE id = ? AND actif = TRUE");
            $stmt->execute([$posteSuperieurId]);
            $parent = $stmt->fetch();

            if ($parent && $parent['poste_superieur_id']) {
                if ($parent['poste_superieur_id'] == $posteId) {
                    return true; // Cycle direct
                }
                return $this->detectsCycle($posteId, $parent['poste_superieur_id'], $visited);
            }

            return false;
        }

        public function getHierarchieComplete()
        {
            $this->checkAdminAccess();

            $stmt = $this->conn->query("
            SELECT p.id, p.nom, p.poste_superieur_id,
                   nh.libelle as niveau_libelle,
                   (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif') as nb_employes
            FROM postes p
            LEFT JOIN niveaux_hierarchiques nh ON p.niveau_hierarchique = nh.niveau
            WHERE p.actif = TRUE
            ORDER BY p.niveau_hierarchique, p.nom
        ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    class DepartementManager
    {
        private $conn;

        public function __construct($connection)
        {
            $this->conn = $connection;
        }

         public function getAllDepartements()
    {
        $stmt = $this->conn->query("
            SELECT d.*,
                   COUNT(p.id) as nb_postes
            FROM departements d
            LEFT JOIN postes p ON d.id = p.departement_id AND p.actif = TRUE
            WHERE d.actif = TRUE
            GROUP BY d.id
            ORDER BY d.nom
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createDepartement($data)
    {
        if (empty($data['nom'])) {
            throw new Exception('Le nom du département est requis');
        }

        $nom = trim($data['nom']);

        // Vérifier unicité
        $stmt = $this->conn->prepare("SELECT id FROM departements WHERE nom = ? AND actif = TRUE");
        $stmt->execute([$nom]);
        if ($stmt->fetch()) {
            throw new Exception('Un département avec ce nom existe déjà');
        }

        $stmt = $this->conn->prepare("
            INSERT INTO departements (nom, description, responsable_nom, responsable_prenom)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $nom,
            $data['description'] ?? null,
            $data['responsable_nom'] ?? null,
            $data['responsable_prenom'] ?? null
        ]);

        return $this->conn->lastInsertId();
    }

    public function updateDepartement($id, $data)
    {
        if (empty($data['nom'])) {
            throw new Exception('Le nom du département est requis');
        }

        $nom = trim($data['nom']);

        // Vérifier unicité (excluant le département actuel)
        $stmt = $this->conn->prepare("SELECT id FROM departements WHERE nom = ? AND id != ? AND actif = TRUE");
        $stmt->execute([$nom, $id]);
        if ($stmt->fetch()) {
            throw new Exception('Un département avec ce nom existe déjà');
        }

        $stmt = $this->conn->prepare("
            UPDATE departements
            SET nom = ?, description = ?, responsable_nom = ?, responsable_prenom = ?
            WHERE id = ? AND actif = TRUE
        ");

        $result = $stmt->execute([
            $nom,
            $data['description'] ?? null,
            $data['responsable_nom'] ?? null,
            $data['responsable_prenom'] ?? null,
            $id
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Département non trouvé ou non modifiable');
        }

        return true;
    }
    public function getDepartementById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT d.*,
                   COUNT(p.id) as nb_postes
            FROM departements d
            LEFT JOIN postes p ON d.id = p.departement_id AND p.actif = TRUE
            WHERE d.id = ? AND d.actif = TRUE
            GROUP BY d.id
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

        public function deleteDepartement($id)
        {
            // Vérifier utilisation
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM postes WHERE departement_id = ? AND actif = TRUE");
            $stmt->execute([$id]);
            $nb_postes = $stmt->fetchColumn();

            if ($nb_postes > 0) {
                throw new Exception("Impossible de supprimer ce département car $nb_postes poste(s) y sont associé(s)");
            }

            // Désactivation logique
            $stmt = $this->conn->prepare("UPDATE departements SET actif = FALSE WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Département non trouvé');
            }

            return true;
        }
    }
    



    class NiveauManager
    {
        private $conn;

        public function __construct($connection)
        {
            $this->conn = $connection;
        }

        public function getAllNiveaux()
        {
            $stmt = $this->conn->query("SELECT * FROM niveaux_hierarchiques ORDER BY niveau ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function createNiveau($data)
        {
            if (empty($data['niveau']) || empty($data['libelle'])) {
                throw new Exception('Le niveau et le libellé sont requis');
            }

            $niveau  = intval($data['niveau']);
            $libelle = trim($data['libelle']);

            // Vérifications d'unicité
            $stmt = $this->conn->prepare("SELECT id FROM niveaux_hierarchiques WHERE niveau = ?");
            $stmt->execute([$niveau]);
            if ($stmt->fetch()) {
                throw new Exception('Ce niveau hiérarchique existe déjà');
            }

            $stmt = $this->conn->prepare("SELECT id FROM niveaux_hierarchiques WHERE libelle = ?");
            $stmt->execute([$libelle]);
            if ($stmt->fetch()) {
                throw new Exception('Ce libellé existe déjà');
            }

            $stmt = $this->conn->prepare("
            INSERT INTO niveaux_hierarchiques (niveau, libelle, description)
            VALUES (?, ?, ?)
        ");
            $stmt->execute([$niveau, $libelle, $data['description'] ?? null]);

            return $this->conn->lastInsertId();
        }

        public function updateNiveau($id, $data)
        {
            if (empty($data['niveau']) || empty($data['libelle'])) {
                throw new Exception('Niveau et libellé requis');
            }

            $niveau  = intval($data['niveau']);
            $libelle = trim($data['libelle']);

            // Vérifications d'unicité
            $stmt = $this->conn->prepare("SELECT id FROM niveaux_hierarchiques WHERE niveau = ? AND id != ?");
            $stmt->execute([$niveau, $id]);
            if ($stmt->fetch()) {
                throw new Exception('Ce niveau hiérarchique existe déjà');
            }

            $stmt = $this->conn->prepare("SELECT id FROM niveaux_hierarchiques WHERE libelle = ? AND id != ?");
            $stmt->execute([$libelle, $id]);
            if ($stmt->fetch()) {
                throw new Exception('Ce libellé existe déjà');
            }

            $stmt = $this->conn->prepare("
            UPDATE niveaux_hierarchiques
            SET niveau = ?, libelle = ?, description = ?
            WHERE id = ?
        ");
            $stmt->execute([$niveau, $libelle, $data['description'] ?? null, $id]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Niveau non trouvé ou non modifiable');
            }

            return true;
        }

        public function deleteNiveau($id)
        {
            $stmt = $this->conn->prepare("SELECT niveau FROM niveaux_hierarchiques WHERE id = ?");
            $stmt->execute([$id]);
            $niveau_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $niveau_data) {
                throw new Exception('Niveau non trouvé');
            }

            // Vérifier utilisation
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM postes WHERE niveau_hierarchique = ? AND actif = TRUE");
            $stmt->execute([$niveau_data['niveau']]);
            $nb_postes = $stmt->fetchColumn();

            if ($nb_postes > 0) {
                throw new Exception("Impossible de supprimer ce niveau car $nb_postes poste(s) l'utilisent");
            }

            $stmt = $this->conn->prepare("DELETE FROM niveaux_hierarchiques WHERE id = ?");
            $stmt->execute([$id]);

            return true;
        }
    }

    class Utils
    {
        public static function sendJsonResponse($data)
        {
            // Nettoyer tout output précédent
            if (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');

            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }

        public static function logActivity($conn, $action, $table, $id, $details)
        {
            try {
                $stmt = $conn->prepare("
                INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details)
                VALUES (?, ?, ?, ?)
            ");
                $stmt->execute([$action, $table, $id, json_encode($details)]);
            } catch (Exception $e) {
                // Log silencieux
            }
        }
    }

    // Initialisation des managers
    $posteManager          = new PosteManager($conn);
    $niveauManager         = new NiveauManager($conn);
    $departementManager    = new DepartementManager($conn);
    $posteSuperieurManager = new PosteSuperieurManager($conn);

    if (isset($_GET['action'])) {
        $action = $_GET['action'];

        try {
            switch ($action) {
                // === GESTION DES NIVEAUX ===
                case 'get_niveaux':
                    $niveaux = $niveauManager->getAllNiveaux();
                    Utils::sendJsonResponse(['success' => true, 'niveaux' => $niveaux]);
                    break;

                case 'add_niveau':
                    $niveau_id = $niveauManager->createNiveau($_POST);
                    Utils::logActivity($conn, 'CREATE_NIVEAU', 'niveaux_hierarchiques', $niveau_id, $_POST);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'Niveau ajouté avec succès', 'niveau_id' => $niveau_id]);
                    break;

                case 'update_niveau':
                    $niveauManager->updateNiveau($_POST['id'], $_POST);
                    Utils::logActivity($conn, 'UPDATE_NIVEAU', 'niveaux_hierarchiques', $_POST['id'], $_POST);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'Niveau modifié avec succès']);
                    break;

                case 'delete_niveau':
                    $input = json_decode(file_get_contents('php://input'), true);
                    $niveauManager->deleteNiveau($input['id']);
                    Utils::logActivity($conn, 'DELETE_NIVEAU', 'niveaux_hierarchiques', $input['id'], $input);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'Niveau supprimé avec succès']);
                    break;

                // === GESTION DES POSTES ===
                case 'add_poste':
                    $poste_id = $posteManager->createPoste($_POST);
                    Utils::logActivity($conn, 'CREATE_POSTE', 'postes', $poste_id, $_POST);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'Poste ajouté avec succès', 'poste_id' => $poste_id]);
                    break;

                case 'update_poste':
                    $posteManager->updatePoste($_POST['id'], $_POST);
                    Utils::logActivity($conn, 'UPDATE_POSTE', 'postes', $_POST['id'], $_POST);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'Poste modifié avec succès']);
                    break;

                case 'delete_poste':
                    try {
                        // Lire les données JSON envoyées
                        $input = json_decode(file_get_contents('php://input'), true);

                        if (! $input || ! isset($input['id'])) {
                            Utils::sendJsonResponse(['success' => false, 'message' => 'ID du poste manquant']);
                            break;
                        }

                        $posteManager->deletePoste($input['id']);
                        Utils::logActivity($conn, 'DELETE_POSTE', 'postes', $input['id'], $input);
                        Utils::sendJsonResponse(['success' => true, 'message' => 'Poste supprimé avec succès']);

                    } catch (Exception $e) {
                        Utils::sendJsonResponse(['success' => false, 'message' => $e->getMessage()]);
                    }
                    break;

                case 'duplicate_poste':
                    $input      = json_decode(file_get_contents('php://input'), true);
                    $nouveau_id = $posteManager->duplicatePoste($input['id']);
                    Utils::logActivity($conn, 'DUPLICATE_POSTE', 'postes', $nouveau_id, $input);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'Poste dupliqué avec succès', 'nouveau_id' => $nouveau_id]);
                    break;

                case 'get_postes':
                    $postes = $posteManager->getAllPostes();
                    Utils::sendJsonResponse(['success' => true, 'postes' => $postes]);
                    break;

                case 'search_postes':
                    $postes = $posteManager->searchPostes($_POST);
                    Utils::sendJsonResponse(['success' => true, 'postes' => $postes]);
                    break;

                // === GESTION DES DÉPARTEMENTS ===
                case 'get_departements':
                    $departements = $departementManager->getAllDepartements();
                    Utils::sendJsonResponse(['success' => true, 'departements' => $departements]);
                    break;

                case 'add_departement':
                    $dept_id = $departementManager->createDepartement($_POST);
                    Utils::logActivity($conn, 'CREATE_DEPARTEMENT', 'departements', $dept_id, $_POST);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'Département ajouté avec succès', 'departement_id' => $dept_id]);
                    break;

                case 'update_departement':
                    $departementManager->updateDepartement($_POST['id'], $_POST);
                    Utils::logActivity($conn, 'UPDATE_DEPARTEMENT', 'departements', $_POST['id'], $_POST);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'Département modifié avec succès']);
                    break;

                case 'delete_departement':
                    $input = json_decode(file_get_contents('php://input'), true);
                    $departementManager->deleteDepartement($input['id']);
                    Utils::logActivity($conn, 'DELETE_DEPARTEMENT', 'departements', $input['id'], $input);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'Département supprimé avec succès']);
                    break;

                    case 'get_departement_details':
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['id'])) {
        Utils::sendJsonResponse(['success' => false, 'message' => 'ID du département manquant']);
        break;
    }
    
    $departement = $departementManager->getDepartementById($input['id']);
    if ($departement) {
        Utils::sendJsonResponse([
            'success' => true, 
            'departement' => $departement
        ]);
    } else {
        Utils::sendJsonResponse([
            'success' => false, 
            'message' => 'Département non trouvé'
        ]);
    }
    break;

                // === GESTION DES POSTES SUPÉRIEURS (ADMIN SEULEMENT) ===
                case 'get_postes_superieurs':
                    $postes_sup = $posteSuperieurManager->getAvailablePostesSupérieurs($_GET['exclude'] ?? null);
                    Utils::sendJsonResponse(['success' => true, 'postes' => $postes_sup]);
                    break;

                case 'set_poste_superieur':
                    $input = json_decode(file_get_contents('php://input'), true);
                    $posteSuperieurManager->setPosteSuperior($input['poste_id'], $input['poste_superieur_id'] ?? null);
                    Utils::logActivity($conn, 'SET_POSTE_SUPERIEUR', 'postes', $input['poste_id'], $input);
                    Utils::sendJsonResponse(['success' => true, 'message' => 'Hiérarchie mise à jour avec succès']);
                    break;

                case 'remove_poste_superieur':
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['poste_id'])) {
        Utils::sendJsonResponse(['success' => false, 'message' => 'ID du poste manquant']);
        break;
    }

                case 'get_hierarchie_complete':
                    $hierarchie = $posteSuperieurManager->getHierarchieComplete();
                    Utils::sendJsonResponse(['success' => true, 'hierarchie' => $hierarchie]);
                    break;

                // === STATISTIQUES ===
                case 'get_stats':
                    $stats = [];

                    $stmt                  = $conn->query("SELECT COUNT(*) FROM postes WHERE actif = TRUE");
                    $stats['total_postes'] = $stmt->fetchColumn();

                    $stmt                          = $conn->query("SELECT type_contrat, COUNT(*) as nb_postes FROM postes WHERE actif = TRUE GROUP BY type_contrat");
                    $stats['repartition_contrats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $stmt = $conn->query("
                    SELECT p.nom,
                           (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif') as nb_employes_actuels,
                           p.nombre_postes_prevus,
                           (p.nombre_postes_prevus - (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif')) as deficit
                    FROM postes p
                    WHERE p.actif = TRUE
                    AND p.nombre_postes_prevus > (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif')
                    ORDER BY deficit DESC
                ");
                    $stats['postes_sous_dotes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    Utils::sendJsonResponse(['success' => true, 'stats' => $stats]);
                    break;

                // === ORGANIGRAMME ===
                case 'get_organigramme':
                    $postes = $posteManager->getAllPostes();

                    // Construire arbre hiérarchique
                    $postes_par_id = [];
                    foreach ($postes as $poste) {
                        $postes_par_id[$poste['id']]            = $poste;
                        $postes_par_id[$poste['id']]['enfants'] = [];
                    }

                    $arbre = [];
                    foreach ($postes as $poste) {
                        if (! empty($poste['poste_superieur_id']) && isset($postes_par_id[$poste['poste_superieur_id']])) {
                            $postes_par_id[$poste['poste_superieur_id']]['enfants'][] = &$postes_par_id[$poste['id']];
                        } else {
                            $arbre[] = &$postes_par_id[$poste['id']];
                        }
                    }

                    Utils::sendJsonResponse(['success' => true, 'organigramme' => $arbre]);
                    break;

                default:
                    Utils::sendJsonResponse(['success' => false, 'message' => 'Action non reconnue']);
            }
        } catch (Exception $e) {
            Utils::sendJsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    try {
        $postes = $posteManager->getAllPostes();

        $stmt        = $conn->query("SELECT id, nom FROM postes WHERE actif = TRUE ORDER BY niveau_hierarchique, nom");
        $tous_postes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $niveaux_hierarchiques = $niveauManager->getAllNiveaux();

        // Récupération des départements
        $stmt         = $conn->query("SELECT id, nom FROM departements WHERE actif = TRUE ORDER BY nom");
        $departements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        die("Erreur: " . $e->getMessage());
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Postes - Restaurant</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/cards-design.css">
    <style>
        .table-hover tbody tr:hover {
            background-color: rgba(59, 130, 246, 0.08);
        }
        .badge-contrat {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        .color-indicator {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .poste-table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .progress {
            height: 8px;
            width: 100px;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .info-label {
            font-weight: 500;
            color: #6c757d;
            min-width: 140px;
        }
        .modal-content {
            border-radius: 0.5rem;
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .detail-section {
            border-left: 3px solid #0d6efd;
            padding-left: 1rem;
            margin-bottom: 1.5rem;
        }
        .tab-content {
            padding: 20px 0;
        }
        .hidden {
            display: none;
        }
        .tab-active {
            background-color: #0d6efd;
            color: white;
        }
        .tab-inactive {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            opacity: 0;
            transition: opacity 0.5s;
        }
        .notification.show {
            opacity: 1;
        }
        #loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            z-index: 9999;
        }
        .organigramme-node {
            margin-bottom: 15px;
        }
        .children-container {
            margin-left: 30px;
            border-left: 2px solid #dee2e6;
            padding-left: 15px;
        }
        .niveau-1 { border-left-color: #dc3545; }
        .niveau-2 { border-left-color: #fd7e14; }
        .niveau-3 { border-left-color: #ffc107; }
        .niveau-4 { border-left-color: #20c997; }
        .niveau-5 { border-left-color: #0dcaf0; }

  /* Correction pour les modals de confirmation */
.modal-top-level {
    z-index: 1070 !important;
}

.modal-top-level .modal-backdrop {
    z-index: 1065 !important;
}

#confirmModal {
    z-index: 1060 !important;
}

#confirmModal .modal-backdrop {
    z-index: 1055 !important;
}
    </style>
</head>
<body class="bg-light">
    <!-- Notification Toast -->
    <div id="notification" class="toast position-fixed top-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
        <div class="toast-header">
            <strong class="me-auto">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="notificationText"></div>
    </div>

    <!-- Loading Spinner -->
    <div id="loading" class="d-none position-fixed top-50 start-50 translate-middle z-50">
        <div class="bg-white p-4 rounded shadow-lg d-flex align-items-center">
            <div class="spinner-border text-primary me-2" role="status"></div>
            <span>Chargement...</span>
        </div>
    </div>

    <div class="container-fluid py-4">
        <!-- En-tête -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-briefcase me-2 text-primary"></i>Gestion des Postes
            </h1>
            <div class="d-flex gap-2">
                <button onclick="openDepartementsModal()" class="btn btn-primary">
                    <i class="fas fa-building me-2"></i>Gérer Départements
                </button>
                <button onclick="openNiveauxModal()" class="btn btn-primary">
                    <i class="fas fa-layer-group me-2"></i>Gérer Niveaux
                </button>
                <button onclick="openHierarchieModal()" class="btn btn-primary">
                    <i class="fas fa-sitemap me-2"></i>Gérer Hiérarchie
                </button>
                <button onclick="openAddModal()" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>Nouveau Poste
                </button>
            </div>
        </div>

        <!-- Onglets de navigation -->
        <div class="d-flex border-bottom mb-4" id="postsTabs">
            <button id="tab-postes" class="tab-active px-4 py-2 border-0 rounded-top" onclick="showTab('postes')">
                <i class="fas fa-list me-2"></i>Liste des Postes
            </button>
            <button id="tab-organigramme" class="tab-inactive px-4 py-2 border-0 rounded-top" onclick="showTab('organigramme')">
                <i class="fas fa-sitemap me-2"></i>Organigramme
            </button>
            <button id="tab-previsions" class="tab-inactive px-4 py-2 border-0 rounded-top" onclick="showTab('previsions')">
                <i class="fas fa-chart-line me-2"></i>Prévisions
            </button>
        </div>

        <!-- Contenu de l'onglet Liste des Postes -->
        <div id="content-postes">
            <!-- Barre de recherche et filtres -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label for="searchInput" class="form-label">Recherche</label>
                            <input type="text" id="searchInput" class="form-control" placeholder="Nom, description, compétences...">
                        </div>
                        <div class="col-md-2">
                            <label for="typeContratFilter" class="form-label">Type de contrat</label>
                            <select id="typeContratFilter" class="form-select">
                                <option value="">Tous les types</option>
                                <?php foreach (TYPES_CONTRAT as $code => $libelle): ?>
                                    <option value="<?php echo $code; ?>"><?php echo $libelle; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="niveauFilter" class="form-label">Niveau hiérarchique</label>
                            <select id="niveauFilter" class="form-select">
                                <option value="">Tous les niveaux</option>
                                <?php foreach ($niveaux_hierarchiques as $niveau): ?>
                                    <option value="<?php echo $niveau['niveau']; ?>"><?php echo $niveau['niveau']; ?> -<?php echo $niveau['libelle']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="departementFilter" class="form-label">Département</label>
                            <select id="departementFilter" class="form-select">
                                <option value="">Tous les départements</option>
                                <?php foreach ($departements as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="d-flex gap-2 w-100">
                                <button onclick="applyFilters()" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-search me-2"></i>Filtrer
                                </button>
                                <button onclick="clearFilters()" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grille des postes -->
            <div class="card">
                <div class="card-body">
                    <div id="postesGrid" class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                        <?php foreach ($postes as $poste): ?>
                            <div class="col">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-header d-flex justify-content-between align-items-center" style="border-left: 4px solid                                                                                                                                             <?php echo $poste['couleur']; ?>">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($poste['nom']); ?></h5>
                                        <span class="badge bg-secondary"><?php echo $poste['type_contrat'] ?? 'CDI'; ?></span>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text text-muted"><?php echo ! empty($poste['description']) ? htmlspecialchars(substr($poste['description'], 0, 100)) . (strlen($poste['description']) > 100 ? '...' : '') : 'Aucune description'; ?></p>

                                        <div class="mb-3">
                                            <small class="text-muted">Département:</small>
                                            <div><?php echo ! empty($poste['departement_nom']) ? htmlspecialchars($poste['departement_nom']) : 'Non assigné'; ?></div>
                                        </div>

                                        <div class="mb-3">
                                            <small class="text-muted">Niveau hiérarchique:</small>
                                            <div><?php echo $poste['niveau_libelle'] ?? 'Non défini'; ?></div>
                                        </div>

                                        <div class="mb-3">
                                            <small class="text-muted">Salaire:</small>
                                            <div class="fw-bold text-success"><?php echo number_format($poste['salaire'], 0, ',', ' '); ?> FCFA</div>
                                        </div>

                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <small class="text-muted">Effectif:</small>
                                                <span><?php echo $poste['nb_employes']; ?>/<?php echo $poste['nombre_postes_prevus'] ?? 1; ?></span>
                                            </div>
                                            <div class="progress" style="height: 8px;">
                                                <?php
                                                    $percentage = $poste['nombre_postes_prevus'] > 0
                                                        ? min(100, ($poste['nb_employes'] / $poste['nombre_postes_prevus']) * 100)
                                                        : 0;
                                                    $bgClass = $percentage >= 100 ? 'bg-success' : ($percentage >= 70 ? 'bg-warning' : 'bg-danger');
                                                ?>
                                                <div class="progress-bar<?php echo $bgClass; ?>"
                                                     role="progressbar"
                                                     style="width:                                                                   <?php echo $percentage; ?>%"
                                                     aria-valuenow="<?php echo $percentage; ?>"
                                                     aria-valuemin="0"
                                                     aria-valuemax="100"></div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <?php if ($poste['nb_employes'] >= ($poste['nombre_postes_prevus'] ?? 1)): ?>
                                                <span class="badge bg-success">Complet</span>
                                            <?php elseif ($poste['nb_employes'] > 0): ?>
                                                <span class="badge bg-warning text-dark">Partiel</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Vacant</span>
                                            <?php endif; ?>

                                            <div class="btn-group">
                                                <button onclick="viewPoste(<?php echo $poste['id']; ?>)" class="btn btn-sm btn-info text-white" title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="editPoste(<?php echo $poste['id']; ?>)" class="btn btn-sm btn-primary" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="duplicatePoste(<?php echo $poste['id']; ?>)" class="btn btn-sm btn-success" title="Dupliquer">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                                <button onclick="deletePoste(<?php echo $poste['id']; ?>)" class="btn btn-sm btn-danger" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Message si aucun poste -->
                    <div id="noResults" class="text-center py-5 d-none">
                        <i class="fas fa-search text-muted fs-1 mb-3"></i>
                        <h5 class="text-muted">Aucun poste trouvé</h5>
                        <p class="text-muted">Modifiez vos critères de recherche ou ajoutez un nouveau poste.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenu de l'onglet Organigramme -->
        <div id="content-organigramme" class="hidden">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-6">Organigramme de l'entreprise</h2>
                <div id="organigrammeContainer" class="overflow-x-auto"></div>
            </div>
        </div>

        <!-- Contenu de l'onglet Prévisions -->
        <div id="content-previsions" class="hidden">
            <div class="space-y-6">
                <!-- Postes sous-dotés -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold mb-4 text-red-600">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Postes sous-dotés
                    </h3>
                    <div id="postesSousDotesList" class="space-y-3"></div>
                </div>

                <!-- Coûts prévisionnels -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold mb-4 text-blue-600">
                        <i class="fas fa-calculator mr-2"></i>Coûts prévisionnels
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <div id="coutActuel" class="text-2xl font-bold text-blue-600">-</div>
                            <div class="text-gray-600">Coût actuel</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <div id="coutPrevisionnel" class="text-2xl font-bold text-green-600">-</div>
                            <div class="text-gray-600">Coût prévisionnel</div>
                        </div>
                        <div class="text-center p-4 bg-orange-50 rounded-lg">
                            <div id="coutDifference" class="text-2xl font-bold text-orange-600">-</div>
                            <div class="text-gray-600">Différence</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de détail du poste -->
    <div class="modal fade" id="viewPosteModal" tabindex="-1" aria-labelledby="viewPosteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPosteModalLabel">Détails du poste</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="posteDetailsContent">
                    <!-- Contenu chargé dynamiquement -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="confirmModal" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-danger" id="confirmButton">Confirmer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour ajouter/modifier un poste -->
    <div class="modal fade" id="posteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Ajouter un poste</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="posteForm">
                    <div class="modal-body">
                        <input type="hidden" id="posteId" name="id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nom" class="form-label">Nom du poste *</label>
                                    <input type="text" class="form-control" id="nom" name="nom" required>
                                </div>
                                <div class="mb-3">
                                    <label for="type_contrat" class="form-label">Type de contrat</label>
                                    <select class="form-select" id="type_contrat" name="type_contrat">
                                        <?php foreach (TYPES_CONTRAT as $code => $libelle): ?>
                                            <option value="<?php echo $code; ?>"><?php echo $libelle; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="niveau_hierarchique" class="form-label">Niveau hiérarchique</label>
                                    <select class="form-select" id="niveau_hierarchique" name="niveau_hierarchique">
                                        <option value="">Sélectionnez un niveau</option>
                                        <?php foreach ($niveaux_hierarchiques as $niveau): ?>
                                            <option value="<?php echo $niveau['niveau']; ?>"><?php echo $niveau['niveau']; ?> -<?php echo $niveau['libelle']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="heures_travail" class="form-label">Heures de travail par semaine</label>
                                    <input type="number" class="form-control" id="heures_travail" name="heures_travail" min="1" max="80" value="35">
                                </div>
                                <div class="mb-3">
                                    <label for="poste_superieur_id" class="form-label">Poste supérieur</label>
                                    <select class="form-select" id="poste_superieur_id" name="poste_superieur_id">
                                        <option value="">Aucun (poste de direction)</option>
                                        <?php foreach ($tous_postes as $poste): ?>
                                            <option value="<?php echo $poste['id']; ?>"><?php echo htmlspecialchars($poste['nom']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="couleur" class="form-label">Couleur</label>
                                    <input type="color" class="form-control form-control-color" id="couleur" name="couleur" value="#3B82F6" title="Choisir une couleur">
                                </div>
                                <div class="mb-3">
                                    <label for="departement_id" class="form-label">Département</label>
                                    <select class="form-select" id="departement_id" name="departement_id" onchange="onDepartementChange()">
                                        <option value="">Sélectionnez un département</option>
                                        <?php foreach ($departements as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['nom']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                <label for="responsable_departement" class="form-label">Responsable de département</label>
                                <input type="text" class="form-control" id="responsable_departement" name="responsable_departement" readonly placeholder="Sélectionnez d'abord un département">
                                <small class="form-text text-muted">Ce champ se remplit automatiquement selon le département sélectionné</small>
                            </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="salaire" class="form-label">Salaire (FCFA)</label>
                                    <input type="number" class="form-control" id="salaire" name="salaire" step="1" min="0">
                                </div>
                                <div class="mb-3">
                                    <label for="nombre_postes_prevus" class="form-label">Nombre de postes prévus</label>
                                    <input type="number" class="form-control" id="nombre_postes_prevus" name="nombre_postes_prevus" min="1" value="1">
                                </div>
                                <div class="mb-3">
                                    <label for="duree_contrat" class="form-label">Durée du contrat</label>
                                    <input type="text" class="form-control" id="duree_contrat" name="duree_contrat" placeholder="Ex: 12 mois, Indéterminée">
                                </div>
                                <div class="mb-3">
                                    <label for="code_paie" class="form-label">Code Paie</label>
                                    <input type="text" class="form-control" id="code_paie" name="code_paie">
                                </div>
                                <div class="mb-3">
                                    <label for="categorie_paie" class="form-label">Catégorie de Paie</label>
                                    <select class="form-select" id="categorie_paie" name="categorie_paie">
                                        <option value="">Sélectionnez une catégorie</option>
                                        <option value="Cadre">Cadre</option>
                                        <option value="Non-cadre">Non-cadre</option>
                                        <option value="Stagiaire">Stagiaire</option>
                                        <option value="Apprenti">Apprenti</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="regime_social" class="form-label">Régime Social</label>
                                    <select class="form-select" id="regime_social" name="regime_social">
                                        <option value="">Sélectionnez un régime</option>
                                        <option value="Régime général">Régime général</option>
                                        <option value="Régime agricole">Régime agricole</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="taux_cotisation" class="form-label">Taux de Cotisation (%)</label>
                                    <input type="number" class="form-control" id="taux_cotisation" name="taux_cotisation" step="0.01" min="0" max="100">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description du poste</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="competences_requises" class="form-label">Compétences requises</label>
                                    <textarea class="form-control" id="competences_requises" name="competences_requises" rows="3" placeholder="Listez les compétences et qualifications nécessaires..."></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="avantages" class="form-label">Avantages</label>
                                    <textarea class="form-control" id="avantages" name="avantages" rows="2" placeholder="Avantages sociaux, primes, etc."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de gestion des niveaux hiérarchiques -->
    <div class="modal fade" id="niveauxModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gestion des Niveaux Hiérarchiques</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Formulaire d'ajout/modification de niveau -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Ajouter/Modifier un niveau</h6>
                        </div>
                        <div class="card-body">
                            <form id="niveauForm">
                                <input type="hidden" id="niveauId" name="id">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="niveauNum" class="form-label">Niveau (numéro) *</label>
                                            <input type="number" class="form-control" id="niveauNum" name="niveau" min="1" max="99" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="niveauLibelle" class="form-label">Libellé *</label>
                                            <input type="text" class="form-control" id="niveauLibelle" name="libelle" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="niveauDescription" class="form-label">Description</label>
                                            <input type="text" class="form-control" id="niveauDescription" name="description">
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Enregistrer
                                    </button>
                                    <button type="button" onclick="clearNiveauForm()" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Annuler
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Liste des niveaux existants -->
                    <div>
                        <h6 class="mb-3">Niveaux existants</h6>
                        <div id="niveauxList" class="list-group">
                            <!-- Liste générée dynamiquement -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de gestion des départements -->
   <div class="modal fade" id="departementsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gestion des Départements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Formulaire d'ajout/modification de département -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Ajouter/Modifier un département</h6>
                    </div>
                    <div class="card-body">
                        <form id="departementForm">
                            <input type="hidden" id="departementId" name="id">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="departementNom" class="form-label">Nom du département *</label>
                                        <input type="text" class="form-control" id="departementNom" name="nom" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="departementDescription" class="form-label">Description</label>
                                        <input type="text" class="form-control" id="departementDescription" name="description">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="responsableNom" class="form-label">Nom du responsable</label>
                                        <input type="text" class="form-control" id="responsableNom" name="responsable_nom">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="responsablePrenom" class="form-label">Prénom du responsable</label>
                                        <input type="text" class="form-control" id="responsablePrenom" name="responsable_prenom">
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Enregistrer
                                </button>
                                <button type="button" onclick="clearDepartementForm()" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

                    <!-- Liste des départements existants -->
                    <div>
                        <h6 class="mb-3">Départements existants</h6>
                        <div id="departementsList" class="row row-cols-1 row-cols-md-2 g-3">
                            <!-- Liste générée dynamiquement -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de gestion de la hiérarchie (Admin seulement) -->
    <div class="modal fade" id="hierarchieModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-sitemap me-2"></i>Gestion de la Hiérarchie (Admin)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Instructions -->
                    <div class="alert alert-info mb-4">
                        <h6 class="alert-heading">Instructions</h6>
                        <ul class="mb-0">
                            <li>• Cliquez sur un poste pour modifier sa relation hiérarchique</li>
                            <li>• Les postes de niveau supérieur ne peuvent pas dépendre de postes de niveau inférieur</li>
                            <li>• Le système empêche la création de boucles hiérarchiques</li>
                        </ul>
                    </div>

                    <!-- Arbre hiérarchique interactif -->
                    <div id="hierarchieTree" class="space-y-4">
                        <!-- Généré dynamiquement -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal d'édition de relation hiérarchique -->
    <div class="modal fade" id="editHierarchieModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier la hiérarchie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Poste</label>
                        <input type="text" id="currentPosteName" class="form-control" readonly>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Poste supérieur</label>
                        <select id="newPosteSuperieur" class="form-select">
                            <option value="">Aucun (poste de direction)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="saveHierarchieChange()">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                    <button type="button" class="btn btn-danger" onclick="removeHierarchieRelation()">
                        <i class="fas fa-unlink me-2"></i>Supprimer relation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
      <script>
    // Variables globales
    let postes =                 <?php echo json_encode($postes); ?>;
    let niveauxHierarchiques =                               <?php echo json_encode($niveaux_hierarchiques); ?>;
    let departements =                       <?php echo json_encode($departements); ?>;
    let currentAction = null;
    let currentTab = 'postes';
    let currentPosteId = null;

    // Utilitaire pour afficher les notifications
    function showNotification(message, type = 'success') {
        const notification = document.getElementById('notification');
        const notificationText = document.getElementById('notificationText');

        notificationText.textContent = message;

        // Changer la couleur selon le type
        const toastHeader = notification.querySelector('.toast-header strong');
        if (type === 'success') {
            toastHeader.className = 'me-auto text-success';
        } else {
            toastHeader.className = 'me-auto text-danger';
        }

        // Afficher la notification
        const bsToast = new bootstrap.Toast(notification);
        bsToast.show();
    }

    // Fonction pour afficher le chargement
    function showLoading() {
        document.getElementById('loading').classList.remove('d-none');
    }

    // Fonction pour cacher le chargement
    function hideLoading() {
        document.getElementById('loading').classList.add('d-none');
    }

    // Fonction pour échapper le HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Fonction pour formater les nombres
    function formatNumber(number) {
        return new Intl.NumberFormat('fr-FR').format(number);
    }

   // Dans votre fonction makeRequest, améliorez cette partie :

function makeRequest(action, data = {}, method = 'POST') {
    // Pour les actions de suppression, utiliser JSON
    if (action.includes('delete')) {
        return fetch(`?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => {
                    console.error('Réponse non-JSON:', text);
                    throw new Error('Réponse du serveur invalide');
                });
            }
        });
    }

    // Pour les autres actions, gérer correctement FormData et objets simples
    let body;
    if (data instanceof FormData) {
        body = data;
    } else {
        const formData = new FormData();
        for (const key in data) {
            if (data.hasOwnProperty(key) && data[key] !== null && data[key] !== undefined) {
                formData.append(key, data[key]);
            }
        }
        body = formData;
    }

    return fetch(`?action=${action}`, {
        method: method,
        body: body
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            return response.text().then(text => {
                console.error('Réponse non-JSON:', text);
                throw new Error('Réponse du serveur invalide');
            });
        }
    });
}

    // Fonctions pour gérer les onglets
    function showTab(tabName) {
        // Cacher tous les contenus d'onglets
        document.querySelectorAll('[id^="content-"]').forEach(tab => {
            tab.classList.add('hidden');
        });

        // Désactiver tous les onglets
        document.querySelectorAll('[id^="tab-"]').forEach(tab => {
            tab.classList.remove('tab-active');
            tab.classList.add('tab-inactive');
        });

        // Afficher l'onglet sélectionné
        document.getElementById(`content-${tabName}`).classList.remove('hidden');
        document.getElementById(`tab-${tabName}`).classList.remove('tab-inactive');
        document.getElementById(`tab-${tabName}`).classList.add('tab-active');

        currentTab = tabName;

        // Charger le contenu spécifique de l'onglet
        if (tabName === 'organigramme') {
            loadOrganigramme();
        } else if (tabName === 'previsions') {
            loadPrevisions();
        }
    }

   function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Ajouter un poste';
    document.getElementById('posteForm').reset();
    document.getElementById('posteId').value = '';
    document.getElementById('couleur').value = '#3B82F6';
    
    // Vider le champ responsable de département
    document.getElementById('responsable_departement').value = '';
    document.getElementById('responsable_departement').placeholder = 'Sélectionnez d\'abord un département';

    const modal = new bootstrap.Modal(document.getElementById('posteModal'));
    modal.show();
}

    function closeModal() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('posteModal'));
    if (modal) {
        modal.hide();
    }
}

    function openNiveauxModal() {
        loadNiveaux();
        const modal = new bootstrap.Modal(document.getElementById('niveauxModal'));
        modal.show();
    }

    function closeNiveauxModal() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('niveauxModal'));
        modal.hide();
    }

    function openDepartementsModal() {
        loadDepartements();
        const modal = new bootstrap.Modal(document.getElementById('departementsModal'));
        modal.show();
    }

    function closeDepartementsModal() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('departementsModal'));
        modal.hide();
    }

    function openHierarchieModal() {
        loadHierarchie();
        const modal = new bootstrap.Modal(document.getElementById('hierarchieModal'));
        modal.show();
    }

    function closeHierarchieModal() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('hierarchieModal'));
    if (modal) {
        modal.hide();
    }
    // Réinitialiser ici car on quitte complètement la gestion de hiérarchie
    currentPosteId = null;
    console.log('Modal hiérarchie fermé, currentPosteId réinitialisé');
}

    function closeEditHierarchieModal() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('editHierarchieModal'));
    if (modal) {
        modal.hide();
    }
    // Ne pas réinitialiser currentPosteId ici car on peut encore en avoir besoin
    console.log('Modal fermé, currentPosteId conservé:', currentPosteId);
}

    // Fonctions pour la gestion des postes
   function viewPoste(posteId) {
    showLoading();

    const poste = postes.find(p => p.id == posteId);
    if (!poste) {
        hideLoading();
        showNotification('Poste non trouvé', 'error');
        return;
    }

    // Construction du contenu HTML avec responsable de département
    let detailsHtml = `
        <div class="mb-4">
            <div class="d-flex align-items-center mb-3">
                <span class="color-indicator me-2" style="background-color: ${poste.couleur || '#3B82F6'}"></span>
                <h4 class="mb-0">${escapeHtml(poste.nom)}</h4>
                <span class="badge bg-secondary ms-2">${poste.type_contrat || 'CDI'}</span>
            </div>
            ${poste.description ? `<p class="text-muted">${escapeHtml(poste.description)}</p>` : ''}
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="detail-section">
                    <h5 class="mb-3">Informations générales</h5>

                    <div class="mb-2 d-flex">
                        <span class="info-label">Département:</span>
                        <span>${poste.departement_nom ? escapeHtml(poste.departement_nom) : 'Non assigné'}</span>
                    </div>

                    <!-- NOUVEAU: Affichage du responsable de département -->
                    <div class="mb-2 d-flex">
                        <span class="info-label">Responsable département:</span>
                        <span>${(() => {
                            if (poste.departement_responsable_nom || poste.departement_responsable_prenom) {
                                return escapeHtml(`${poste.departement_responsable_prenom || ''} ${poste.departement_responsable_nom || ''}`.trim());
                            }
                            return 'Non défini';
                        })()}</span>
                    </div>

                    <div class="mb-2 d-flex">
                        <span class="info-label">Niveau hiérarchique:</span>
                        <span>${poste.niveau_libelle || 'Non défini'}</span>
                    </div>

                    <div class="mb-2 d-flex">
                        <span class="info-label">Poste supérieur:</span>
                        <span>${poste.poste_superieur_nom ? escapeHtml(poste.poste_superieur_nom) : 'Aucun'}</span>
                    </div>

                    <div class="mb-2 d-flex">
                        <span class="info-label">Heures/semaine:</span>
                        <span>${poste.heures_travail || '35'}h</span>
                    </div>

                    <div class="mb-2 d-flex">
                        <span class="info-label">Salaire:</span>
                        <span class="fw-bold text-success">${formatNumber(poste.salaire || 0)} FCFA</span>
                    </div>
                </div>
            </div>

                <div class="col-md-6">
                    <div class="detail-section">
                        <h5 class="mb-3">Effectif et postes</h5>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Employés actuels:</span>
                                <span class="fw-bold">${poste.nb_employes || 0}/${poste.nombre_postes_prevus || 1}</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                ${function() {
                                    const percentage = poste.nombre_postes_prevus > 0
                                        ? Math.min(100, (poste.nb_employes / poste.nombre_postes_prevus) * 100)
                                        : 0;
                                    const bgClass = percentage >= 100 ? 'bg-success' : (percentage >= 70 ? 'bg-warning' : 'bg-danger');
                                    return `<div class="progress-bar ${bgClass}"
                                             role="progressbar"
                                             style="width: ${percentage}%"
                                             aria-valuenow="${percentage}"
                                             aria-valuemin="0"
                                             aria-valuemax="100"></div>`;
                                }()}
                            </div>
                        </div>

                        ${poste.duree_contrat ? `
                        <div class="mb-2 d-flex">
                            <span class="info-label">Durée contrat:</span>
                            <span>${escapeHtml(poste.duree_contrat)}</span>
                        </div>
                        ` : ''}

                        ${poste.code_paie ? `
                        <div class="mb-2 d-flex">
                            <span class="info-label">Code paie:</span>
                            <span>${escapeHtml(poste.code_paie)}</span>
                        </div>
                        ` : ''}

                        ${poste.categorie_paie ? `
                        <div class="mb-2 d-flex">
                            <span class="info-label">Catégorie paie:</span>
                            <span>${escapeHtml(poste.categorie_paie)}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;

        // Section compétences requises
        if (poste.competences_requises) {
            detailsHtml += `
                <div class="detail-section">
                    <h5>Compétences requises</h5>
                    <p>${escapeHtml(poste.competences_requises)}</p>
                </div>
            `;
        }

        // Section avantages
        if (poste.avantages) {
            detailsHtml += `
                <div class="detail-section">
                    <h5>Avantages</h5>
                    <p>${escapeHtml(poste.avantages)}</p>
                </div>
            `;
        }

        // Section informations administratives
        detailsHtml += `
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-section">
                        <h5>Informations administratives</h5>

                        ${poste.regime_social ? `
                        <div class="mb-2 d-flex">
                            <span class="info-label">Régime social:</span>
                            <span>${escapeHtml(poste.regime_social)}</span>
                        </div>
                        ` : ''}

                        ${poste.taux_cotisation ? `
                        <div class="mb-2 d-flex">
                            <span class="info-label">Taux cotisation:</span>
                            <span>${poste.taux_cotisation}%</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;

        // Mise à jour du contenu du modal et affichage
        document.getElementById('posteDetailsContent').innerHTML = detailsHtml;
        hideLoading();

        // Afficher le modal
        const viewModal = new bootstrap.Modal(document.getElementById('viewPosteModal'));
        viewModal.show();
    }

function onDepartementChange() {
    const departementSelect = document.getElementById('departement_id');
    const responsableInput = document.getElementById('responsable_departement');
    
    if (!departementSelect.value) {
        responsableInput.value = '';
        responsableInput.placeholder = 'Sélectionnez d\'abord un département';
        return;
    }
    
    // Afficher un loading
    responsableInput.value = 'Chargement...';
    responsableInput.disabled = true;
    
    // Requête pour récupérer les détails du département
    fetch('?action=get_departement_details', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: departementSelect.value })
    })
    .then(response => response.json())
    .then(data => {
        responsableInput.disabled = false;
        
        if (data.success && data.departement) {
            const dept = data.departement;
            let responsableText = '';
            
            if (dept.responsable_nom || dept.responsable_prenom) {
                responsableText = `${dept.responsable_prenom || ''} ${dept.responsable_nom || ''}`.trim();
            }
            
            responsableInput.value = responsableText;
            responsableInput.placeholder = responsableText ? '' : 'Aucun responsable défini';
        } else {
            responsableInput.value = '';
            responsableInput.placeholder = 'Erreur lors du chargement';
            showNotification('Erreur lors du chargement des informations du département', 'error');
        }
    })
    .catch(error => {
        responsableInput.disabled = false;
        responsableInput.value = '';
        responsableInput.placeholder = 'Erreur de connexion';
        console.error('Erreur:', error);
        showNotification('Erreur de connexion', 'error');
    });
}

  function editPoste(posteId) {
    const poste = postes.find(p => p.id == posteId);
    if (!poste) {
        showNotification('Poste non trouvé', 'error');
        return;
    }

    document.getElementById('modalTitle').textContent = 'Modifier le poste';

    // Remplir tous les champs existants
    const fields = [
        'id', 'nom', 'description', 'salaire', 'couleur', 'type_contrat',
        'niveau_hierarchique', 'poste_superieur_id', 'competences_requises',
        'nombre_postes_prevus', 'duree_contrat', 'heures_travail', 'avantages',
        'code_paie', 'categorie_paie', 'regime_social', 'taux_cotisation', 'departement_id'
    ];

    fields.forEach(field => {
        const element = document.getElementById(field === 'id' ? 'posteId' : field);
        if (element && poste[field] !== undefined) {
            element.value = poste[field] || '';
        }
    });

    // Remplir le champ responsable de département
    let responsableText = '';
    if (poste.departement_responsable_nom || poste.departement_responsable_prenom) {
        responsableText = `${poste.departement_responsable_prenom || ''} ${poste.departement_responsable_nom || ''}`.trim();
    }
    document.getElementById('responsable_departement').value = responsableText;

    const modal = new bootstrap.Modal(document.getElementById('posteModal'));
    modal.show();
}


function deletePoste(posteId) {
    const poste = postes.find(p => p.id == posteId);
    if (!poste) return;

    document.getElementById('confirmMessage').textContent = `Êtes-vous sûr de vouloir supprimer le poste "${poste.nom}" ?\n\nCette action est irréversible.`;

    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    confirmModal.show();

    // Définir l'action de confirmation
    document.getElementById('confirmButton').onclick = function() {
        showLoading();

        // Utiliser fetch avec JSON au lieu de FormData
        fetch('?action=delete_poste', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: posteId })
        })
        .then(response => {
            // Vérifier si la réponse est du JSON valide
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                // Si ce n'est pas du JSON, lire comme texte pour déboguer
                return response.text().then(text => {
                    console.error('Réponse non-JSON reçue:', text);
                    throw new Error('Réponse du serveur invalide');
                });
            }
        })
        .then(data => {
            hideLoading();
            confirmModal.hide();
            if (data.success) {
                showNotification(data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            hideLoading();
            confirmModal.hide();
            console.error('Erreur complète:', error);
            showNotification('Erreur de connexion: ' + error.message, 'error');
        });
    };
}

   // Correction de la fonction duplicatePoste dans le JavaScript
function duplicatePoste(posteId) {
    const poste = postes.find(p => p.id == posteId);
    if (!poste) return;

    document.getElementById('confirmMessage').textContent = `Voulez-vous dupliquer le poste "${poste.nom}" ?`;

    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    confirmModal.show();

    // Définir l'action de confirmation
    document.getElementById('confirmButton').onclick = function() {
        showLoading();

        // Utiliser fetch avec JSON (comme pour delete_poste)
        fetch('?action=duplicate_poste', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: posteId })
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => {
                    console.error('Réponse non-JSON:', text);
                    throw new Error('Réponse du serveur invalide');
                });
            }
        })
        .then(data => {
            hideLoading();
            confirmModal.hide();
            if (data.success) {
                showNotification(data.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            hideLoading();
            confirmModal.hide();
            console.error('Erreur complète:', error);
            showNotification('Erreur de connexion: ' + error.message, 'error');
        });
    };
}

    function applyFilters() {
        const filters = {
            search: document.getElementById('searchInput').value.trim(),
            type_contrat: document.getElementById('typeContratFilter').value,
            niveau_hierarchique: document.getElementById('niveauFilter').value,
            departement_id: document.getElementById('departementFilter').value
        };

        showLoading();
        makeRequest('search_postes', filters)
            .then(data => {
                hideLoading();
                if (data.success) {
                    updatePostesGrid(data.postes);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Erreur de connexion: ' + error.message, 'error');
            });
    }

    function clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('typeContratFilter').value = '';
        document.getElementById('niveauFilter').value = '';
        document.getElementById('departementFilter').value = '';
        location.reload();
    }
function updatePostesGrid(postesData) {
    const grid = document.getElementById('postesGrid');
    const noResults = document.getElementById('noResults');

    if (postesData.length === 0) {
        grid.innerHTML = '';
        noResults.classList.remove('d-none');
        return;
    }

    noResults.classList.add('d-none');

    let gridHtml = '';
    postesData.forEach(poste => {
            const percentage = poste.nombre_postes_prevus > 0
                ? Math.min(100, (poste.nb_employes / poste.nombre_postes_prevus) * 100)
                : 0;
            const bgClass = percentage >= 100 ? 'bg-success' : (percentage >= 70 ? 'bg-warning' : 'bg-danger');
            const statusBadge = poste.nb_employes >= (poste.nombre_postes_prevus || 1)
                ? '<span class="badge bg-success">Complet</span>'
                : (poste.nb_employes > 0
                    ? '<span class="badge bg-warning text-dark">Partiel</span>'
                    : '<span class="badge bg-danger">Vacant</span>');

              let responsableText = 'Non défini';
        if (poste.departement_responsable_nom || poste.departement_responsable_prenom) {
            responsableText = `${poste.departement_responsable_prenom || ''} ${poste.departement_responsable_nom || ''}`.trim();
        }

        gridHtml += `
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center" style="border-left: 4px solid ${poste.couleur || '#3B82F6'}">
                        <h5 class="card-title mb-0">${escapeHtml(poste.nom)}</h5>
                        <span class="badge bg-secondary">${poste.type_contrat || 'CDI'}</span>
                    </div>
                    <div class="card-body">
                            <p class="card-text text-muted">${poste.description ? escapeHtml(poste.description.substring(0, 100)) + (poste.description.length > 100 ? '...' : '') : 'Aucune description'}</p>

                            <div class="mb-3">
                                <small class="text-muted">Département:</small>
                                <div>${poste.departement_nom ? escapeHtml(poste.departement_nom) : 'Non assigné'}</div>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">Niveau hiérarchique:</small>
                                <div>${poste.niveau_libelle || 'Non défini'}</div>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">Salaire:</small>
                                <div class="fw-bold text-success">${formatNumber(poste.salaire || 0)} FCFA</div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted">Effectif:</small>
                                    <span>${poste.nb_employes || 0}/${poste.nombre_postes_prevus || 1}</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar ${bgClass}"
                                         role="progressbar"
                                         style="width: ${percentage}%"
                                         aria-valuenow="${percentage}"
                                         aria-valuemin="0"
                                         aria-valuemax="100"></div>
                                </div>
                            </div>
 <div class="mb-3">
                            <small class="text-muted">Département:</small>
                            <div>${poste.departement_nom ? escapeHtml(poste.departement_nom) : 'Non assigné'}</div>
                        </div>

                        <!-- NOUVEAU: Responsable de département -->
                        <div class="mb-3">
                            <small class="text-muted">Responsable:</small>
                            <div class="fw-bold">${escapeHtml(responsableText)}</div>
                        </div>
                            <div class="d-flex justify-content-between align-items-center">
                                ${statusBadge}

                                <div class="btn-group">
                                    <button onclick="viewPoste(${poste.id})" class="btn btn-sm btn-info text-white" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="editPoste(${poste.id})" class="btn btn-sm btn-primary" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="duplicatePoste(${poste.id})" class="btn btn-sm btn-success" title="Dupliquer">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button onclick="deletePoste(${poste.id})" class="btn btn-sm btn-danger" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        grid.innerHTML = gridHtml;
    }

    // Fonctions pour la gestion des niveaux hiérarchiques
    function loadNiveaux() {
        showLoading();
        makeRequest('get_niveaux')
            .then(data => {
                hideLoading();
                if (data.success) {
                    niveauxHierarchiques = data.niveaux;
                    renderNiveaux(data.niveaux);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Erreur de connexion: ' + error.message, 'error');
            });
    }

    function renderNiveaux(niveaux) {
        const container = document.getElementById('niveauxList');
        container.innerHTML = '';

        if (niveaux.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-layer-group fs-1 mb-2"></i>
                    <p>Aucun niveau hiérarchique défini.</p>
                </div>
            `;
            return;
        }

        niveaux.forEach(niveau => {
            const item = document.createElement('div');
            item.className = 'list-group-item';
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-primary me-2">${niveau.niveau}</span>
                            <h6 class="mb-0">${escapeHtml(niveau.libelle)}</h6>
                        </div>
                        ${niveau.description ? `<small class="text-muted">${escapeHtml(niveau.description)}</small>` : ''}
                    </div>
                    <div class="btn-group">
                        <button onclick="editNiveau(${niveau.id})" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteNiveau(${niveau.id})" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.appendChild(item);
        });
    }

    function editNiveau(niveauId) {
        const niveau = niveauxHierarchiques.find(n => n.id == niveauId);
        if (!niveau) {
            showNotification('Niveau non trouvé', 'error');
            return;
        }

        document.getElementById('niveauId').value = niveau.id;
        document.getElementById('niveauNum').value = niveau.niveau;
        document.getElementById('niveauLibelle').value = niveau.libelle;
        document.getElementById('niveauDescription').value = niveau.description || '';
    }

    function deleteNiveau(niveauId) {
        const niveau = niveauxHierarchiques.find(n => n.id == niveauId);
        if (!niveau) return;

        document.getElementById('confirmMessage').textContent = `Êtes-vous sûr de vouloir supprimer le niveau "${niveau.libelle}" ?\n\nCette action est irréversible.`;

        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        confirmModal.show();

        // Définir l'action de confirmation
        document.getElementById('confirmButton').onclick = function() {
            showLoading();
            makeRequest('delete_niveau', { id: niveauId })
                .then(data => {
                    hideLoading();
                    confirmModal.hide();
                    if (data.success) {
                        showNotification(data.message);
                        loadNiveaux();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    confirmModal.hide();
                    showNotification('Erreur de connexion: ' + error.message, 'error');
                });
        };
    }

    function clearNiveauForm() {
        document.getElementById('niveauForm').reset();
        document.getElementById('niveauId').value = '';
    }

    // Fonctions pour la gestion des départements
    function loadDepartements() {
        showLoading();
        makeRequest('get_departements')
            .then(data => {
                hideLoading();
                if (data.success) {
                    departements = data.departements;
                    renderDepartements(data.departements);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Erreur de connexion: ' + error.message, 'error');
            });
    }

    function renderDepartements(departements) {
    const container = document.getElementById('departementsList');
    container.innerHTML = '';

    if (departements.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center text-muted py-4">
                <i class="fas fa-building fs-1 mb-2"></i>
                <p>Aucun département défini.</p>
            </div>
        `;
        return;
    }

    departements.forEach(dept => {
        const col = document.createElement('div');
        col.className = 'col';
        
        // Construire le nom du responsable
        let responsableText = 'Aucun responsable';
        if (dept.responsable_nom || dept.responsable_prenom) {
            responsableText = `${dept.responsable_prenom || ''} ${dept.responsable_nom || ''}`.trim();
        }
        
        col.innerHTML = `
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">${escapeHtml(dept.nom)}</h5>
                    ${dept.description ? `<p class="card-text">${escapeHtml(dept.description)}</p>` : ''}
                    <div class="mb-2">
                        <small class="text-muted">Responsable:</small>
                        <div class="fw-bold">${escapeHtml(responsableText)}</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge bg-primary">${dept.nb_postes || 0} poste(s)</span>
                        <div class="btn-group">
                            <button onclick="editDepartement(${dept.id})" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteDepartement(${dept.id})" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(col);
    });
}

function editDepartement(departementId) {
    const dept = departements.find(d => d.id == departementId);
    if (!dept) {
        showNotification('Département non trouvé', 'error');
        return;
    }

    document.getElementById('departementId').value = dept.id;
    document.getElementById('departementNom').value = dept.nom;
    document.getElementById('departementDescription').value = dept.description || '';
    document.getElementById('responsableNom').value = dept.responsable_nom || '';
    document.getElementById('responsablePrenom').value = dept.responsable_prenom || '';
}
    function deleteDepartement(departementId) {
        const dept = departements.find(d => d.id == departementId);
        if (!dept) return;

        document.getElementById('confirmMessage').textContent = `Êtes-vous sûr de vouloir supprimer le département "${dept.nom}" ?\n\nCette action est irréversible.`;

        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        confirmModal.show();

        // Définir l'action de confirmation
        document.getElementById('confirmButton').onclick = function() {
            showLoading();
            makeRequest('delete_departement', { id: departementId })
                .then(data => {
                    hideLoading();
                    confirmModal.hide();
                    if (data.success) {
                        showNotification(data.message);
                        loadDepartements();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    confirmModal.hide();
                    showNotification('Erreur de connexion: ' + error.message, 'error');
                });
        };
    }

   function clearDepartementForm() {
    document.getElementById('departementForm').reset();
    document.getElementById('departementId').value = '';
    document.getElementById('responsableNom').value = '';
    document.getElementById('responsablePrenom').value = '';
}

    // Fonctions pour la gestion de la hiérarchie
    function loadHierarchie() {
        showLoading();
        makeRequest('get_hierarchie_complete')
            .then(data => {
                hideLoading();
                if (data.success) {
                    renderHierarchie(data.hierarchie);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Erreur de connexion: ' + error.message, 'error');
            });
    }

    function renderHierarchie(hierarchie) {
        const container = document.getElementById('hierarchieTree');
        container.innerHTML = '';

        if (!hierarchie || hierarchie.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-sitemap fs-1 mb-2"></i>
                    <p>Aucune hiérarchie à afficher.</p>
                </div>
            `;
            return;
        }

        // Construire l'arbre hiérarchique
        const postesMap = {};
        const tree = [];

        // Créer la map des postes
        hierarchie.forEach(poste => {
            postesMap[poste.id] = { ...poste, children: [] };
        });

        // Construire l'arbre
        hierarchie.forEach(poste => {
            if (poste.poste_superieur_id && postesMap[poste.poste_superieur_id]) {
                postesMap[poste.poste_superieur_id].children.push(postesMap[poste.id]);
            } else {
                tree.push(postesMap[poste.id]);
            }
        });

        // Rendre l'arbre
        tree.forEach(node => {
            container.appendChild(createHierarchieNode(node));
        });
    }

    function createHierarchieNode(node) {
        const div = document.createElement('div');
        div.className = 'card mb-3';

        div.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">${escapeHtml(node.nom)}</h6>
                        <small class="text-muted">${node.niveau_libelle || 'N/A'} - ${node.nb_employes} employé(s)</small>
                    </div>
                    <button onclick="editHierarchieRelation(${node.id}, '${escapeHtml(node.nom)}', ${node.poste_superieur_id || 'null'})" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-edit me-1"></i>Modifier
                    </button>
                </div>
            </div>
        `;

        // Ajouter les enfants s'ils existent
        if (node.children && node.children.length > 0) {
            const childrenContainer = document.createElement('div');
            childrenContainer.className = 'ms-4';

            node.children.forEach(child => {
                childrenContainer.appendChild(createHierarchieNode(child));
            });

            div.appendChild(childrenContainer);
        }

        return div;
    }

   function editHierarchieRelation(posteId, posteName, currentSuperieurId) {
    // S'assurer que currentPosteId est bien défini
    currentPosteId = parseInt(posteId);
    
    console.log('editHierarchieRelation appelée avec:', {
        posteId: currentPosteId,
        posteName: posteName,
        currentSuperieurId: currentSuperieurId
    });
    
    document.getElementById('currentPosteName').value = posteName;

    showLoading();
    makeRequest(`get_postes_superieurs&exclude=${posteId}`)
        .then(data => {
            hideLoading();
            if (data.success) {
                const select = document.getElementById('newPosteSuperieur');
                select.innerHTML = '<option value="">Aucun (poste de direction)</option>';

                data.postes.forEach(poste => {
                    const option = document.createElement('option');
                    option.value = poste.id;
                    option.textContent = `${poste.nom} (${poste.niveau_libelle || 'N/A'})`;
                    if (poste.id == currentSuperieurId) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });

                const modal = new bootstrap.Modal(document.getElementById('editHierarchieModal'));
                modal.show();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showNotification('Erreur de connexion: ' + error.message, 'error');
        });
}

   function saveHierarchieChange() {
    if (!currentPosteId || currentPosteId === null || currentPosteId === undefined) {
        console.error('currentPosteId non défini:', currentPosteId);
        showNotification('Erreur: Aucun poste sélectionné', 'error');
        return;
    }

    const newSuperieurId = document.getElementById('newPosteSuperieur').value || null;
    
    console.log('saveHierarchieChange avec:', {
        poste_id: currentPosteId,
        poste_superieur_id: newSuperieurId
    });

    showLoading();
    
    // Utiliser fetch avec JSON
    fetch('?action=set_poste_superieur', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            poste_id: currentPosteId,
            poste_superieur_id: newSuperieurId
        })
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            return response.text().then(text => {
                console.error('Réponse non-JSON:', text);
                throw new Error('Réponse du serveur invalide');
            });
        }
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            showNotification(data.message);
            closeEditHierarchieModal();
            loadHierarchie();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erreur lors de la sauvegarde:', error);
        showNotification('Erreur de connexion: ' + error.message, 'error');
    });
}
    function removeHierarchieRelation() {
    // Vérifier que currentPosteId est bien défini
    if (!currentPosteId || currentPosteId === null || currentPosteId === undefined) {
        console.error('currentPosteId non défini:', currentPosteId);
        showNotification('Erreur: Aucun poste sélectionné', 'error');
        return;
    }
    
    console.log('removeHierarchieRelation appelée avec currentPosteId:', currentPosteId);
    
    // Fermer d'abord le modal d'édition de hiérarchie
    const editModal = bootstrap.Modal.getInstance(document.getElementById('editHierarchieModal'));
    if (editModal) {
        editModal.hide();
    }
    
    // Attendre que le modal soit fermé avant d'ouvrir la confirmation
    setTimeout(() => {
        document.getElementById('confirmMessage').textContent = 'Êtes-vous sûr de vouloir supprimer la relation hiérarchique de ce poste ?';
        
        // Ajouter la classe pour z-index élevé
        document.getElementById('confirmModal').classList.add('modal-top-level');
        
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        confirmModal.show();
        
        // Définir l'action de confirmation
        document.getElementById('confirmButton').onclick = function() {
            console.log('Confirmation - currentPosteId:', currentPosteId);
            
            showLoading();
            
            // Utiliser fetch avec JSON comme pour les autres actions de suppression
            fetch('?action=remove_poste_superieur', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ poste_id: currentPosteId })
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        console.error('Réponse non-JSON:', text);
                        throw new Error('Réponse du serveur invalide');
                    });
                }
            })
            .then(data => {
                hideLoading();
                confirmModal.hide();
                
                // Nettoyer la classe après fermeture
                setTimeout(() => {
                    document.getElementById('confirmModal').classList.remove('modal-top-level');
                }, 300);
                
                if (data.success) {
                    showNotification(data.message);
                    // Réinitialiser currentPosteId après succès
                    currentPosteId = null;
                    loadHierarchie();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                confirmModal.hide();
                
                setTimeout(() => {
                    document.getElementById('confirmModal').classList.remove('modal-top-level');
                }, 300);
                
                console.error('Erreur lors de la suppression:', error);
                showNotification('Erreur de connexion: ' + error.message, 'error');
            });
        };
    }, 300); // Délai pour laisser le temps au modal de se fermer
}
    // Fonctions pour l'organigramme
    function loadOrganigramme() {
        showLoading();
        makeRequest('get_organigramme')
            .then(data => {
                hideLoading();
                if (data.success) {
                    renderOrganigramme(data.organigramme);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Erreur de connexion: ' + error.message, 'error');
            });
    }

    function renderOrganigramme(organigramme) {
        const container = document.getElementById('organigrammeContainer');
        container.innerHTML = '';

        if (!organigramme || organigramme.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-sitemap fs-1 mb-2"></i>
                    <p>Aucun poste à afficher dans l'organigramme.</p>
                </div>
            `;
            return;
        }

        organigramme.forEach(poste => {
            container.appendChild(createOrganigrammeNode(poste));
        });
    }

    function createOrganigrammeNode(poste) {
        const node = document.createElement('div');
        node.className = `organigramme-node niveau-${poste.niveau_hierarchique || 5}`;

        const card = document.createElement('div');
        card.className = 'card';
        card.style.borderLeft = `4px solid ${poste.couleur || '#3B82F6'}`;
        card.innerHTML = `
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">${escapeHtml(poste.nom)}</h6>
                        <small class="text-muted">${poste.type_contrat || 'CDI'} - ${poste.niveau_libelle || 'Niveau non défini'}</small>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-success">${formatNumber(poste.salaire || 0)} FCFA</div>
                        <small class="text-muted">${poste.nb_employes || 0} employé(s)</small>
                    </div>
                </div>
                ${poste.description ? `<p class="card-text mt-2"><small>${escapeHtml(poste.description.substring(0, 100))}${poste.description.length > 100 ? '...' : ''}</small></p>` : ''}
            </div>
        `;

        node.appendChild(card);

        if (poste.enfants && poste.enfants.length > 0) {
            const childrenContainer = document.createElement('div');
            childrenContainer.className = 'children-container';
            poste.enfants.forEach(enfant => {
                childrenContainer.appendChild(createOrganigrammeNode(enfant));
            });
            node.appendChild(childrenContainer);
        }

        return node;
    }

    // Fonctions pour les prévisions
    function loadPrevisions() {
        showLoading();
        makeRequest('get_stats')
            .then(data => {
                hideLoading();
                if (data.success) {
                    renderPostesSousDotes(data.stats.postes_sous_dotes);
                    calculateCoutsPrevisionnels();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Erreur de connexion: ' + error.message, 'error');
            });
    }

    function renderPostesSousDotes(postesSousDotes) {
        const container = document.getElementById('postesSousDotesList');
        container.innerHTML = '';

        if (!postesSousDotes || postesSousDotes.length === 0) {
            container.innerHTML = `
                <div class="text-center text-success py-4">
                    <i class="fas fa-check-circle fs-1 mb-2"></i>
                    <p>Aucun poste sous-doté détecté.</p>
                </div>
            `;
            return;
        }

        postesSousDotes.forEach(poste => {
            const item = document.createElement('div');
            item.className = 'alert alert-warning';
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="alert-heading">${escapeHtml(poste.nom)}</h6>
                        <p class="mb-0">${poste.nb_employes_actuels} employé(s) sur ${poste.nombre_postes_prevus} prévu(s)</p>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold fs-5">-${poste.deficit}</div>
                        <small>employé(s) manquant(s)</small>
                    </div>
                </div>
            `;
            container.appendChild(item);
        });
    }

    function calculateCoutsPrevisionnels() {
        let coutActuel = 0;
        let coutPrevisionnel = 0;

        postes.forEach(poste => {
            const salaire = parseInt(poste.salaire) || 0;
            const nbEmployesActuels = parseInt(poste.nb_employes) || 0;
            const nbEmployesPrevus = parseInt(poste.nombre_postes_prevus) || 1;

            coutActuel += salaire * nbEmployesActuels;
            coutPrevisionnel += salaire * nbEmployesPrevus;
        });

        const difference = coutPrevisionnel - coutActuel;

        document.getElementById('coutActuel').textContent = formatNumber(coutActuel) + ' FCFA';
        document.getElementById('coutPrevisionnel').textContent = formatNumber(coutPrevisionnel) + ' FCFA';

        const differenceElement = document.getElementById('coutDifference');
        differenceElement.textContent = (difference >= 0 ? '+' : '') + formatNumber(difference) + ' FCFA';

        if (difference > 0) {
            differenceElement.className = 'text-2xl font-bold text-danger';
        } else if (difference < 0) {
            differenceElement.className = 'text-2xl font-bold text-success';
        } else {
            differenceElement.className = 'text-2xl font-bold text-muted';
        }
    }

    // Gestionnaires d'événements pour les formulaires
document.getElementById('posteForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const isEdit = formData.get('id') !== '' && formData.get('id') !== null;
    const action = isEdit ? 'update_poste' : 'add_poste';

    showLoading();

    // Utiliser directement fetch au lieu de makeRequest pour éviter les problèmes
    fetch(`?action=${action}`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();

        if (data.success) {
            showNotification(data.message);
            const modal = bootstrap.Modal.getInstance(document.getElementById('posteModal'));
            if (modal) {
                modal.hide();
            }
            // Recharger la page après un court délai
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('Erreur de connexion: ' + error.message, 'error');
    });
});

    document.getElementById('niveauForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const isEdit = formData.get('id') !== '';
        const action = isEdit ? 'update_niveau' : 'add_niveau';

        showLoading();
        makeRequest(action, formData)
            .then(data => {
                hideLoading();
                if (data.success) {
                    showNotification(data.message);
                    clearNiveauForm();
                    loadNiveaux();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Erreur de connexion: ' + error.message, 'error');
            });
    });

    document.getElementById('departementForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const isEdit = formData.get('id') !== '';
        const action = isEdit ? 'update_departement' : 'add_departement';

        showLoading();
        makeRequest(action, formData)
            .then(data => {
                hideLoading();
                if (data.success) {
                    showNotification(data.message);
                    clearDepartementForm();
                    loadDepartements();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Erreur de connexion: ' + error.message, 'error');
            });
    });

    // Initialisation au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        // Initialiser les tooltips Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Afficher l'onglet par défaut
        showTab('postes');
    });
    </script>
</body>
</html>