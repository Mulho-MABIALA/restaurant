<?php

require_once '../config.php';
require_once '../vendor/autoload.php'; // Pour TCPDF
session_start();

// Vérifie l'accès admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Configuration des types de contrat
const TYPES_CONTRAT = [
    'CDI' => 'Contrat à Durée Indéterminée',
    'CDD' => 'Contrat à Durée Déterminée', 
    'STAGE' => 'Stage',
    'APPRENTISSAGE' => 'Contrat d\'Apprentissage',
    'CONSULTANT' => 'Consultant',
    'SAISONNIER' => 'Contrat Saisonnier'
];

// ====================================================================
// 2. CLASSES ET FONCTIONS UTILITAIRES
// ====================================================================

/**
 * Classe pour gérer les opérations sur les postes
 */
class PosteManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Récupère tous les postes avec leurs informations détaillées
     */
    public function getAllPostes() {
    $stmt = $this->conn->query("
        SELECT p.*,
        (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif') as nb_employes,
        ps.nom as poste_superieur_nom, 
        nh.libelle as niveau_libelle,
        d.nom as departement_nom
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
   public function createPoste($data) {
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
        !empty($data['niveau_hierarchique']) ? intval($data['niveau_hierarchique']) : null,
        !empty($data['poste_superieur_id']) ? $data['poste_superieur_id'] : null,
        $data['competences_requises'] ?? null,
        intval($data['nombre_postes_prevus'] ?? 1),
        $data['duree_contrat'] ?? null,
        $data['avantages'] ?? null,
        $data['code_paie'] ?? null,
        $data['categorie_paie'] ?? null,
        $data['regime_social'] ?? null,
        $data['taux_cotisation'] ?? null,
        intval($data['heures_travail'] ?? 35),
        !empty($data['departement_id']) ? $data['departement_id'] : null
    ]);
    
    return $this->conn->lastInsertId();
}
 
    public function updatePoste($id, $data) {
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
            UPDATE postes SET nom = ?, description = ?, salaire = ?, couleur = ?, type_contrat = ?,
                   niveau_hierarchique = ?, poste_superieur_id = ?, competences_requises = ?,
                   nombre_postes_prevus = ?, duree_contrat = ?, avantages = ?,
                   code_paie = ?, categorie_paie = ?, regime_social = ?, taux_cotisation = ?,
                   heures_travail = ?
            WHERE id = ? AND actif = TRUE
        ");
        
        $result = $stmt->execute([
            $data['nom'], $data['description'] ?? null, intval($data['salaire'] ?? 0),
            $data['couleur'] ?? '#3B82F6', $data['type_contrat'] ?? 'CDI',
            !empty($data['niveau_hierarchique']) ? intval($data['niveau_hierarchique']) : null,
            !empty($data['poste_superieur_id']) ? $data['poste_superieur_id'] : null,
            $data['competences_requises'] ?? null, intval($data['nombre_postes_prevus'] ?? 1),
            $data['duree_contrat'] ?? null, $data['avantages'] ?? null,
            $data['code_paie'] ?? null, $data['categorie_paie'] ?? null,
            $data['regime_social'] ?? null, $data['taux_cotisation'] ?? null,
            intval($data['heures_travail'] ?? 35), $id
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Poste non trouvé ou non modifiable');
        }
        
        return true;
    }
    
    /**
     * Supprime un poste (désactivation logique)
     */
    public function deletePoste($id) {
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
    
    /**
     * Duplique un poste
     */
    public function duplicatePoste($id) {
        $stmt = $this->conn->prepare("SELECT * FROM postes WHERE id = ? AND actif = TRUE");
        $stmt->execute([$id]);
        $original = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$original) {
            throw new Exception('Poste non trouvé');
        }
        
        // Générer nom unique
        $nouveau_nom = $original['nom'] . ' (Copie)';
        $counter = 1;
        
        while (true) {
            $stmt = $this->conn->prepare("SELECT id FROM postes WHERE nom = ? AND actif = TRUE");
            $stmt->execute([$nouveau_nom]);
            if (!$stmt->fetch()) break;
            $counter++;
            $nouveau_nom = $original['nom'] . ' (Copie ' . $counter . ')';
        }
        
        // Créer copie
        $original['nom'] = $nouveau_nom;
        unset($original['id']);
        
        return $this->createPoste($original);
    }
    
    /**
     * Recherche des postes avec filtres
     */
    public function searchPostes($filters) {
        $sql = "SELECT p.*,
                (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif') as nb_employes,
                ps.nom as poste_superieur_nom, nh.libelle as niveau_libelle
                FROM postes p
                LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
                LEFT JOIN niveaux_hierarchiques nh ON p.niveau_hierarchique = nh.niveau
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
class PosteSuperieurManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Vérifie si l'utilisateur est admin
     */
    private function checkAdminAccess() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        throw new Exception('Accès refusé. Seuls les administrateurs peuvent gérer les postes supérieurs.');
    }
}

    /**
     * Récupère tous les postes supérieurs possibles
     */
    public function getAvailablePostesSupérieurs($excludeId = null) {
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
    public function setPosteSuperior($posteId, $posteSuperieurId) {
        $this->checkAdminAccess();
        
        // Vérifications de sécurité
        if ($posteId == $posteSuperieurId) {
            throw new Exception('Un poste ne peut pas être son propre supérieur');
        }
        
        // Vérifier que le poste supérieur existe
        if ($posteSuperieurId) {
            $stmt = $this->conn->prepare("SELECT id FROM postes WHERE id = ? AND actif = TRUE");
            $stmt->execute([$posteSuperieurId]);
            if (!$stmt->fetch()) {
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
    public function removePosteSuperior($posteId) {
        $this->checkAdminAccess();
        
        $stmt = $this->conn->prepare("UPDATE postes SET poste_superieur_id = NULL WHERE id = ? AND actif = TRUE");
        $stmt->execute([$posteId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Poste non trouvé');
        }
        
        return true;
    }
    
    /**
     * Détecte les cycles dans la hiérarchie
     */
    private function detectsCycle($posteId, $posteSuperieurId, $visited = []) {
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
    
    /**
     * Récupère l'arbre hiérarchique complet
     */
    public function getHierarchieComplete() {
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

class DepartementManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function getAllDepartements() {
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
    
    public function createDepartement($data) {
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
            INSERT INTO departements (nom, description)
            VALUES (?, ?)
        ");
        
        $stmt->execute([
            $nom,
            $data['description'] ?? null
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    public function updateDepartement($id, $data) {
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
            SET nom = ?, description = ?
            WHERE id = ? AND actif = TRUE
        ");
        
        $result = $stmt->execute([
            $nom,
            $data['description'] ?? null,
            $id
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Département non trouvé ou non modifiable');
        }
        
        return true;
    }
    
    public function deleteDepartement($id) {
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

/**
 * Classe pour gérer les niveaux hiérarchiques
 */
class NiveauManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    public function getAllNiveaux() {
        $stmt = $this->conn->query("SELECT * FROM niveaux_hierarchiques ORDER BY niveau ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function createNiveau($data) {
        if (empty($data['niveau']) || empty($data['libelle'])) {
            throw new Exception('Le niveau et le libellé sont requis');
        }
        
        $niveau = intval($data['niveau']);
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
    
    public function updateNiveau($id, $data) {
        if (empty($data['niveau']) || empty($data['libelle'])) {
            throw new Exception('Niveau et libellé requis');
        }
        
        $niveau = intval($data['niveau']);
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
    
    public function deleteNiveau($id) {
        $stmt = $this->conn->prepare("SELECT niveau FROM niveaux_hierarchiques WHERE id = ?");
        $stmt->execute([$id]);
        $niveau_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$niveau_data) {
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

/**
 * Fonctions utilitaires
 */
class Utils {
    public static function sendJsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    public static function logActivity($conn, $action, $table, $id, $details) {
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

/**
 * Générateur de PDF
 */
class PDFGenerator {
    public static function generatePostesPDF($conn) {
        try {
            $posteManager = new PosteManager($conn);
            $postes = $posteManager->getAllPostes();
            
            $pdf = new TCPDF();
            $pdf->SetCreator('Système de Gestion RH');
            $pdf->SetAuthor('Restaurant Management System');
            $pdf->SetTitle('Liste des Postes - ' . date('d/m/Y'));
            $pdf->AddPage();
            
            // En-tête
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 10, 'LISTE DES POSTES', 0, 1, 'C');
            $pdf->Cell(0, 5, 'Générée le ' . date('d/m/Y à H:i'), 0, 1, 'C');
            $pdf->Ln(10);
            
            // Statistiques
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'STATISTIQUES GÉNÉRALES', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            
            $total_postes = count($postes);
            $total_employes = array_sum(array_column($postes, 'nb_employes'));
            $salaires = array_filter(array_column($postes, 'salaire'));
            $salaire_moyen = !empty($salaires) ? array_sum($salaires) / count($salaires) : 0;
            
            $pdf->Cell(50, 6, 'Nombre total de postes:', 0, 0, 'L');
            $pdf->Cell(0, 6, $total_postes, 0, 1, 'L');
            $pdf->Cell(50, 6, 'Nombre total d\'employés:', 0, 0, 'L');
            $pdf->Cell(0, 6, $total_employes, 0, 1, 'L');
            $pdf->Cell(50, 6, 'Salaire moyen:', 0, 0, 'L');
            $pdf->Cell(0, 6, number_format($salaire_moyen, 0, ',', ' ') . ' FCFA', 0, 1, 'L');
            $pdf->Ln(5);
            
            // Tableau des postes
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'DÉTAIL DES POSTES', 0, 1, 'L');
            
            // En-têtes
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Cell(40, 8, 'POSTE', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'TYPE CONTRAT', 1, 0, 'C', true);
            $pdf->Cell(35, 8, 'NIVEAU', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'SALAIRE (FCFA)', 1, 0, 'C', true);
            $pdf->Cell(20, 8, 'EMPLOYÉS', 1, 0, 'C', true);
            $pdf->Cell(15, 8, 'HEURES', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'DESCRIPTION', 1, 1, 'C', true);
            
            // Données
            $pdf->SetFont('helvetica', '', 8);
            foreach ($postes as $poste) {
                $pdf->Cell(40, 6, substr($poste['nom'], 0, 25), 1, 0, 'L');
                $pdf->Cell(25, 6, $poste['type_contrat'] ?? 'CDI', 1, 0, 'C');
                $pdf->Cell(35, 6, $poste['niveau_libelle'] ?? 'Non défini', 1, 0, 'C');
                $pdf->Cell(30, 6, number_format($poste['salaire'], 0, ',', ' '), 1, 0, 'R');
                $pdf->Cell(20, 6, $poste['nb_employes'], 1, 0, 'C');
                $pdf->Cell(15, 6, $poste['heures_travail'] ?? '35', 1, 0, 'C');
                $pdf->Cell(30, 6, substr($poste['description'] ?? '', 0, 30), 1, 1, 'L');
            }
            
            return $pdf->Output('postes_' . date('Y-m-d') . '.pdf', 'S');
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la génération du PDF: ' . $e->getMessage());
        }
    }
}

// ====================================================================
// 3. GESTIONNAIRES DE REQUÊTES AJAX
// ====================================================================

// Initialisation des managers
$posteManager = new PosteManager($conn);
$niveauManager = new NiveauManager($conn);
$departementManager = new DepartementManager($conn);
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
                $input = json_decode(file_get_contents('php://input'), true);
                $posteManager->deletePoste($input['id']);
                Utils::logActivity($conn, 'DELETE_POSTE', 'postes', $input['id'], $input);
                Utils::sendJsonResponse(['success' => true, 'message' => 'Poste supprimé avec succès']);
                break;
                
            case 'duplicate_poste':
                $input = json_decode(file_get_contents('php://input'), true);
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
                $posteSuperieurManager->removePosteSuperior($input['poste_id']);
                Utils::logActivity($conn, 'REMOVE_POSTE_SUPERIEUR', 'postes', $input['poste_id'], $input);
                Utils::sendJsonResponse(['success' => true, 'message' => 'Relation hiérarchique supprimée']);
                break;
                
            case 'get_hierarchie_complete':
                $hierarchie = $posteSuperieurManager->getHierarchieComplete();
                Utils::sendJsonResponse(['success' => true, 'hierarchie' => $hierarchie]);
                break;

            // === STATISTIQUES ===
            case 'get_stats':
                $stats = [];
                
                $stmt = $conn->query("SELECT COUNT(*) FROM postes WHERE actif = TRUE");
                $stats['total_postes'] = $stmt->fetchColumn();
                
                $stmt = $conn->query("SELECT type_contrat, COUNT(*) as nb_postes FROM postes WHERE actif = TRUE GROUP BY type_contrat");
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
                    $postes_par_id[$poste['id']] = $poste;
                    $postes_par_id[$poste['id']]['enfants'] = [];
                }
                
                $arbre = [];
                foreach ($postes as $poste) {
                    if (!empty($poste['poste_superieur_id']) && isset($postes_par_id[$poste['poste_superieur_id']])) {
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

// === EXPORT PDF ===
if (isset($_GET['action']) && $_GET['action'] === 'export_postes_pdf' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdfContent = PDFGenerator::generatePostesPDF($conn);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="postes_' . date('Y-m-d') . '.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        Utils::logActivity($conn, 'EXPORT_POSTES_PDF', 'postes', null, ['date' => date('Y-m-d H:i:s')]);
        echo $pdfContent;
        exit;
    } catch (Exception $e) {
        die('Erreur lors de l\'export PDF: ' . $e->getMessage());
    }
}

// ====================================================================
// 4. CHARGEMENT DES DONNÉES POUR L'AFFICHAGE  
// ====================================================================
try {
    $postes = $posteManager->getAllPostes();
    
    $stmt = $conn->query("SELECT id, nom FROM postes WHERE actif = TRUE ORDER BY niveau_hierarchique, nom");
    $tous_postes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $niveaux_hierarchiques = $niveauManager->getAllNiveaux();
    
    // Récupération des départements
    $stmt = $conn->query("SELECT id, nom FROM departements WHERE actif = TRUE ORDER BY nom");
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification {
            position: fixed; top: 20px; right: 20px; z-index: 1000;
            opacity: 0; transition: opacity 0.3s ease;
        }
        .notification.show { opacity: 1; }
        .loading {
            display: none; position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%); z-index: 1000;
        }
        .organigramme-node { transition: transform 0.2s ease; }
        .organigramme-node:hover { transform: scale(1.05); }
        .niveau-1 { border-left: 4px solid #ef4444; }
        .niveau-2 { border-left: 4px solid #f97316; }
        .niveau-3 { border-left: 4px solid #eab308; }
        .niveau-4 { border-left: 4px solid #22c55e; }
        .niveau-5 { border-left: 4px solid #3b82f6; }
        .tab-active { background-color: #3b82f6; color: white; }
        .tab-inactive { background-color: #f3f4f6; color: #6b7280; }

        #confirmModal {
    z-index: 60 !important;
}

/* Modal de gestion des départements */
#departementsModal {
    z-index: 50;
}

/* Modal de gestion des niveaux */
#niveauxModal {
    z-index: 50;
}

/* Modal de gestion des postes */
#posteModal {
    z-index: 50;
}

/* Alternative: Vous pouvez aussi utiliser cette hiérarchie plus claire */
.modal-base {
    z-index: 50;
}

.modal-confirmation {
    z-index: 60;
}

.modal-priority {
    z-index: 70;
}
/* Configuration des z-index pour les modals */

/* Modal de base - niveau le plus bas */
#posteModal,
#departementsModal,
#niveauxModal {
    z-index: 50 !important;
}

/* Modal de gestion hiérarchie - niveau intermédiaire */
#hierarchieModal {
    z-index: 55 !important;
}

/* Modal de modification hiérarchie - niveau supérieur */
#modifierHierarchieModal {
    z-index: 60 !important;
}

/* Modal de confirmation - niveau le plus élevé */
#confirmModal {
    z-index: 65 !important;
}

/* Classes génériques pour organiser la hiérarchie */
.modal-base {
    z-index: 50 !important;
}

.modal-secondary {
    z-index: 55 !important;
}

.modal-tertiary {
    z-index: 60 !important;
}

.modal-confirmation {
    z-index: 65 !important;
}

.modal-priority {
    z-index: 70 !important;
}

/* Assurer que les overlays respectent aussi la hiérarchie */
.modal-overlay-base {
    z-index: 49;
}

.modal-overlay-secondary {
    z-index: 54;
}

.modal-overlay-tertiary {
    z-index: 59;
}

.modal-overlay-confirmation {
    z-index: 64;
}
    </style>
</head>
<body class="bg-gray-50">
    <!-- Notification Toast -->
    <div id="notification" class="notification bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
        <span id="notificationText"></span>
    </div>
    
    <!-- Loading Spinner -->
    <div id="loading" class="loading">
        <div class="bg-white p-4 rounded-lg shadow-lg">
            <i class="fas fa-spinner fa-spin text-blue-600 text-2xl"></i>
            <span class="ml-2">Chargement...</span>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto p-6">
        <!-- En-tête -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">
                <i class="fas fa-briefcase mr-3 text-blue-600"></i>Gestion des Postes
            </h1>
            <div class="flex space-x-3">
    <button onclick="exportPostesPDF()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-file-pdf mr-2"></i>Export PDF
    </button>
    <button onclick="openDepartementsModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-building mr-2"></i>Gérer Départements
    </button>
    <button onclick="openNiveauxModal()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-layer-group mr-2"></i>Gérer Niveaux
    </button>

    <button onclick="openHierarchieModal()" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-sitemap mr-2"></i>Gérer Hiérarchie
    </button>
   
    <button onclick="openAddModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
        <i class="fas fa-plus mr-2"></i>Nouveau Poste
    </button>
</div>
        </div>
        
        <!-- Onglets de navigation -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button onclick="showTab('postes')" id="tab-postes" class="tab-active py-2 px-1 border-b-2 border-transparent font-medium text-sm">
                        <i class="fas fa-list mr-2"></i>Liste des Postes
                    </button>
                    <button onclick="showTab('organigramme')" id="tab-organigramme" class="tab-inactive py-2 px-1 border-b-2 border-transparent font-medium text-sm">
                        <i class="fas fa-sitemap mr-2"></i>Organigramme
                    </button>
                    <button onclick="showTab('previsions')" id="tab-previsions" class="tab-inactive py-2 px-1 border-b-2 border-transparent font-medium text-sm">
                        <i class="fas fa-chart-line mr-2"></i>Prévisions
                    </button>
                </nav>
            </div>
        </div>

        <!-- Contenu de l'onglet Liste des Postes -->
        <div id="content-postes">
            <!-- Barre de recherche et filtres -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                        <input type="text" id="searchInput" placeholder="Nom, description, compétences..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type de contrat</label>
                        <select id="typeContratFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Tous les types</option>
                            <?php foreach (TYPES_CONTRAT as $code => $libelle): ?>
                                <option value="<?php echo $code; ?>"><?php echo $libelle; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Niveau hiérarchique</label>
                        <select id="niveauFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Tous les niveaux</option>
                            <?php foreach ($niveaux_hierarchiques as $niveau): ?>
                                <option value="<?php echo $niveau['niveau']; ?>"><?php echo $niveau['niveau']; ?> - <?php echo $niveau['libelle']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Département</label>
                        <select id="departementFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Tous les départements</option>
                            <?php foreach ($departements as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['nom']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="applyFilters()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-search mr-2"></i>Filtrer
                    </button>
                    <button onclick="clearFilters()" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition-colors">
                        <i class="fas fa-times mr-2"></i>Effacer
                    </button>
                </div>
            </div>
            
            <!-- Grille des postes -->
            <div id="postesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($postes as $poste): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow niveau-<?php echo $poste['niveau_hierarchique'] ?? 5; ?>">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center">
                                <div class="w-4 h-4 rounded-full mr-3" style="background-color: <?php echo $poste['couleur']; ?>"></div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($poste['nom']); ?></h3>
                                    <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full"><?php echo $poste['type_contrat'] ?? 'CDI'; ?></span>
                                </div>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button onclick="editPoste(<?php echo $poste['id']; ?>)" class="text-blue-600 hover:text-blue-800 transition-colors" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="duplicatePoste(<?php echo $poste['id']; ?>)" class="text-green-600 hover:text-green-800 transition-colors" title="Dupliquer">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button onclick="deletePoste(<?php echo $poste['id']; ?>)" class="text-red-600 hover:text-red-800 transition-colors" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="space-y-2 mb-4">
                            <p class="text-gray-600 text-sm min-h-[40px]"><?php echo htmlspecialchars($poste['description'] ?? 'Aucune description'); ?></p>
                            <?php if (!empty($poste['competences_requises'])): ?>
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-star mr-1"></i>Compétences: <?php echo htmlspecialchars(substr($poste['competences_requises'], 0, 50)); ?>...
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($poste['poste_superieur_nom'])): ?>
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-level-up-alt mr-1"></i>Rapporte à: <?php echo htmlspecialchars($poste['poste_superieur_nom']); ?>
                                </div>
                            <?php endif; ?>
                                                    <?php if (!empty($poste['avantages'])): ?>
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-gift mr-1"></i>Avantages: <?php echo htmlspecialchars(substr($poste['avantages'], 0, 50)); ?>...
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($poste['departement_nom'])): ?>
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-building mr-1"></i>Département: <?php echo htmlspecialchars($poste['departement_nom']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-clock mr-1"></i>
                                Heures/semaine: <?php echo $poste['heures_travail'] ?? '35'; ?>h
                            </div>

                        </div>
                        
                        <div class="text-center mb-4">
                            <span class="text-gray-500 text-sm">Salaire:</span>
                            <div class="font-medium text-green-600 text-lg"><?php echo number_format($poste['salaire'], 0, ',', ' '); ?> FCFA</div>
                            <?php if (!empty($poste['duree_contrat'])): ?>
                                <div class="text-xs text-gray-500">Durée: <?php echo htmlspecialchars($poste['duree_contrat']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($poste['departement_nom'])): ?>
    <div class="text-xs text-gray-500">
        <i class="fas fa-building mr-1"></i>Département: <?php echo htmlspecialchars($poste['departement_nom']); ?>
    </div>
<?php endif; ?>
                        
                        <div class="pt-4 border-t border-gray-200 space-y-2">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500">Employés actuels:</span>
                                <span class="font-medium bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                                    <?php echo $poste['nb_employes']; ?>/<?php echo $poste['nombre_postes_prevus'] ?? 1; ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500">Niveau:</span>
                                <span class="font-medium text-gray-800">
                                    <?php echo $poste['niveau_libelle'] ?? 'Non défini'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Message si aucun poste -->
            <div id="noResults" class="text-center py-12 hidden">
                <i class="fas fa-search text-gray-400 text-6xl mb-4"></i>
                <h3 class="text-xl font-medium text-gray-600 mb-2">Aucun poste trouvé</h3>
                <p class="text-gray-500">Modifiez vos critères de recherche ou ajoutez un nouveau poste.</p>
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

    <!-- Modal de confirmation de suppression -->
    <div id="confirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Confirmation</h3>
                </div>
                <div class="p-6">
                    <p id="confirmMessage" class="text-gray-700 mb-6"></p>
                    <div class="flex justify-end space-x-3">
                        <button onclick="closeConfirmModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                            Annuler
                        </button>
                        <button id="confirmButton" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                            Confirmer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour ajouter/modifier un poste -->
    <div id="posteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-screen overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 id="modalTitle" class="text-lg font-semibold text-gray-900">Ajouter un poste</h3>
                </div>
                <form id="posteForm" class="p-6">
                    <input type="hidden" id="posteId" name="id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Informations de base -->
                        <div class="space-y-4">
                            <h4 class="font-medium text-gray-900 border-b pb-2">Informations de base</h4>
                            <div>
                                <label for="nom" class="block text-sm font-medium text-gray-700 mb-2">Nom du poste *</label>
                                <input type="text" id="nom" name="nom" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="type_contrat" class="block text-sm font-medium text-gray-700 mb-2">Type de contrat</label>
                                <select id="type_contrat" name="type_contrat"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <?php foreach (TYPES_CONTRAT as $code => $libelle): ?>
                                        <option value="<?php echo $code; ?>"><?php echo $libelle; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="niveau_hierarchique" class="block text-sm font-medium text-gray-700 mb-2">Niveau hiérarchique</label>
                                <select id="niveau_hierarchique" name="niveau_hierarchique"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Sélectionnez un niveau</option>
                                    <?php foreach ($niveaux_hierarchiques as $niveau): ?>
                                        <option value="<?php echo $niveau['niveau']; ?>"><?php echo $niveau['niveau']; ?> - <?php echo $niveau['libelle']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="heures_travail" class="block text-sm font-medium text-gray-700 mb-2">Heures de travail par semaine</label>
                                <input type="number" id="heures_travail" name="heures_travail" min="1" max="80" value="35"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="poste_superieur_id" class="block text-sm font-medium text-gray-700 mb-2">Poste supérieur</label>
                                <select id="poste_superieur_id" name="poste_superieur_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Aucun (poste de direction)</option>
                                    <?php foreach ($tous_postes as $poste): ?>
                                        <option value="<?php echo $poste['id']; ?>"><?php echo htmlspecialchars($poste['nom']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="couleur" class="block text-sm font-medium text-gray-700 mb-2">Couleur</label>
                                <input type="color" id="couleur" name="couleur" value="#3B82F6"
                                       class="w-full h-10 border border-gray-300 rounded-md cursor-pointer">
                            </div>
                            <div>
                                <label for="departement_id" class="block text-sm font-medium text-gray-700 mb-2">Département</label>
                                <select id="departement_id" name="departement_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Sélectionnez un département</option>
                                    <?php foreach ($departements as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['nom']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <!-- Informations détaillées -->
                        <div class="space-y-4">
                            <h4 class="font-medium text-gray-900 border-b pb-2">Détails du poste</h4>
                            <div>
                                <label for="salaire" class="block text-sm font-medium text-gray-700 mb-2">Salaire (FCFA)</label>
                                <input type="number" id="salaire" name="salaire" step="1" min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="nombre_postes_prevus" class="block text-sm font-medium text-gray-700 mb-2">Nombre de postes prévus</label>
                                <input type="number" id="nombre_postes_prevus" name="nombre_postes_prevus" min="1" value="1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="duree_contrat" class="block text-sm font-medium text-gray-700 mb-2">Durée du contrat</label>
                                <input type="text" id="duree_contrat" name="duree_contrat" placeholder="Ex: 12 mois, Indéterminée"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="code_paie" class="block text-sm font-medium text-gray-700 mb-2">Code Paie</label>
                                <input type="text" id="code_paie" name="code_paie"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="categorie_paie" class="block text-sm font-medium text-gray-700 mb-2">Catégorie de Paie</label>
                                <select id="categorie_paie" name="categorie_paie"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Sélectionnez une catégorie</option>
                                    <option value="Cadre">Cadre</option>
                                    <option value="Non-cadre">Non-cadre</option>
                                    <option value="Stagiaire">Stagiaire</option>
                                    <option value="Apprenti">Apprenti</option>
                                </select>
                            </div>
                            <div>
                                <label for="regime_social" class="block text-sm font-medium text-gray-700 mb-2">Régime Social</label>
                                <select id="regime_social" name="regime_social"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Sélectionnez un régime</option>
                                    <option value="Régime général">Régime général</option>
                                    <option value="Régime agricole">Régime agricole</option>
                                </select>
                            </div>
                            <div>
                                <label for="taux_cotisation" class="block text-sm font-medium text-gray-700 mb-2">Taux de Cotisation (%)</label>
                                <input type="number" id="taux_cotisation" name="taux_cotisation" step="0.01" min="0" max="100"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Champs texte longs -->
                    <div class="mt-6 space-y-4">
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description du poste</label>
                            <textarea id="description" name="description" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                        </div>
                        <div>
                            <label for="competences_requises" class="block text-sm font-medium text-gray-700 mb-2">Compétences requises</label>
                            <textarea id="competences_requises" name="competences_requises" rows="3"
                                      placeholder="Listez les compétences et qualifications nécessaires..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                        </div>
                        <div>
                            <label for="avantages" class="block text-sm font-medium text-gray-700 mb-2">Avantages</label>
                            <textarea id="avantages" name="avantages" rows="2"
                                      placeholder="Avantages sociaux, primes, etc."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                            Annuler
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de gestion des niveaux hiérarchiques -->
    <div id="niveauxModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Gestion des Niveaux Hiérarchiques</h3>
                    <button onclick="closeNiveauxModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="p-6">
                    <!-- Formulaire d'ajout/modification de niveau -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-4">Ajouter/Modifier un niveau</h4>
                        <form id="niveauForm" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <input type="hidden" id="niveauId" name="id">
                            <div>
                                <label for="niveauNum" class="block text-sm font-medium text-gray-700 mb-1">Niveau (numéro) *</label>
                                <input type="number" id="niveauNum" name="niveau" min="1" max="99" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="niveauLibelle" class="block text-sm font-medium text-gray-700 mb-1">Libellé *</label>
                                <input type="text" id="niveauLibelle" name="libelle" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="niveauDescription" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <input type="text" id="niveauDescription" name="description"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            <div class="md:col-span-3 flex space-x-3">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-save mr-2"></i>Enregistrer
                                </button>
                                <button type="button" onclick="clearNiveauForm()" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition-colors">
                                    <i class="fas fa-times mr-2"></i>Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                     
                    <!-- Liste des niveaux existants -->
                    <div>
                        <h4 class="font-medium text-gray-900 mb-4">Niveaux existants</h4>
                        <div id="niveauxList" class="space-y-2">
                            <!-- Liste générée dynamiquement -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- MODAL DE GESTION DES DÉPARTEMENTS - À ajouter après la modal des niveaux -->
<!-- MODAL DE GESTION DES DÉPARTEMENTS - Version simplifiée -->
<div id="departementsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-4xl w-full max-h-screen overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Gestion des Départements</h3>
                <button onclick="closeDepartementsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <!-- Formulaire d'ajout/modification de département -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="font-medium text-gray-900 mb-4">Ajouter/Modifier un département</h4>
                    <form id="departementForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" id="departementId" name="id">
                        
                        <div>
                            <label for="departementNom" class="block text-sm font-medium text-gray-700 mb-1">Nom du département *</label>
                            <input type="text" id="departementNom" name="nom" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="departementDescription" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <input type="text" id="departementDescription" name="description"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div class="md:col-span-2 flex space-x-3">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                                <i class="fas fa-save mr-2"></i>Enregistrer
                            </button>
                            <button type="button" onclick="clearDepartementForm()" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition-colors">
                                <i class="fas fa-times mr-2"></i>Annuler
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Liste des départements existants -->
                <div>
                    <h4 class="font-medium text-gray-900 mb-4">Départements existants</h4>
                    <div id="departementsList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Liste générée dynamiquement -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de gestion de la hiérarchie (Admin seulement) -->
<div id="hierarchieModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-6xl w-full max-h-screen overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-sitemap mr-2"></i>Gestion de la Hiérarchie (Admin)
                </h3>
                <button onclick="closeHierarchieModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <!-- Instructions -->
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h4 class="font-medium text-blue-900 mb-2">Instructions</h4>
                    <ul class="text-sm text-blue-800 space-y-1">
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
<div id="editHierarchieModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-60">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-lg w-full">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Modifier la hiérarchie</h3>
            </div>
            
            <div class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Poste</label>
                    <input type="text" id="currentPosteName" readonly 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Poste supérieur</label>
                    <select id="newPosteSuperieur" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Aucun (poste de direction)</option>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button onclick="closeEditHierarchieModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button onclick="saveHierarchieChange()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Enregistrer
                    </button>
                    <button onclick="removeHierarchieRelation()" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        <i class="fas fa-unlink mr-2"></i>Supprimer relation
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- ====================================================================== -->
    <!-- 6. JAVASCRIPT ORGANISÉ PAR MODULES                                    -->
    <!-- ====================================================================== -->
    <script>
        // ============================================================
// VARIABLES GLOBALES ET CONFIGURATION
// ============================================================
let postes = <?php echo json_encode($postes); ?>;
let niveauxHierarchiques = <?php echo json_encode($niveaux_hierarchiques); ?>;
let departements = <?php echo json_encode($departements); ?>;
let currentAction = null;
let currentTab = 'postes';

// ============================================================
// MODULE UTILITAIRES
// ============================================================
const Utils = {
    showNotification: function(message, type = 'success') {
        const notification = document.getElementById('notification');
        const notificationText = document.getElementById('notificationText');
        notificationText.textContent = message;
        notification.className = `notification ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white px-6 py-3 rounded-lg shadow-lg show`;
        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    },

    showLoading: function() {
        document.getElementById('loading').style.display = 'block';
    },

    hideLoading: function() {
        document.getElementById('loading').style.display = 'none';
    },

    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    formatNumber: function(number) {
        return new Intl.NumberFormat('fr-FR').format(number);
    }
};

// ============================================================
// MODULE VALIDATION
// ============================================================
const Validator = {
    validatePosteForm: function() {
        const nom = document.getElementById('nom').value.trim();
        const salaire = parseInt(document.getElementById('salaire').value) || 0;
        const nombrePostes = parseInt(document.getElementById('nombre_postes_prevus').value) || 1;
        const tauxCotisation = parseFloat(document.getElementById('taux_cotisation').value) || 0;
        const heuresTravail = parseInt(document.getElementById('heures_travail').value) || 35;

        if (nom.length < 2) {
            Utils.showNotification('Le nom du poste doit contenir au moins 2 caractères', 'error');
            return false;
        }
        if (salaire < 0) {
            Utils.showNotification('Le salaire ne peut pas être négatif', 'error');
            return false;
        }
        if (nombrePostes < 1) {
            Utils.showNotification('Le nombre de postes prévus doit être au moins de 1', 'error');
            return false;
        }
        if (tauxCotisation < 0 || tauxCotisation > 100) {
            Utils.showNotification('Le taux de cotisation doit être compris entre 0 et 100%', 'error');
            return false;
        }
        if (heuresTravail < 1 || heuresTravail > 80) {
            Utils.showNotification('Le nombre d\'heures de travail doit être compris entre 1 et 80 heures par semaine', 'error');
            return false;
        }
        return true;
    },

    validateNiveauForm: function() {
        const niveau = parseInt(document.getElementById('niveauNum').value) || 0;
        const libelle = document.getElementById('niveauLibelle').value.trim();

        if (niveau < 1 || niveau > 99) {
            Utils.showNotification('Le niveau doit être compris entre 1 et 99', 'error');
            return false;
        }
        if (libelle.length < 2) {
            Utils.showNotification('Le libellé doit contenir au moins 2 caractères', 'error');
            return false;
        }
        return true;
    },
validateDepartementForm: function() {
    const nom = document.getElementById('departementNom').value.trim();
    if (nom.length < 2) {
        Utils.showNotification('Le nom du département doit contenir au moins 2 caractères', 'error');
        return false;
    }
    return true;
}
};

// ============================================================
// MODULE GESTION DES ONGLETS
// ============================================================
const TabManager = {
    showTab: function(tabName) {
        const contents = ['postes', 'organigramme', 'previsions'];
        
        contents.forEach(content => {
            const contentEl = document.getElementById(`content-${content}`);
            const tabEl = document.getElementById(`tab-${content}`);
            
            if (contentEl) contentEl.classList.add('hidden');
            if (tabEl) {
                tabEl.classList.remove('tab-active');
                tabEl.classList.add('tab-inactive');
            }
        });
        
        const activeContent = document.getElementById(`content-${tabName}`);
        const activeTab = document.getElementById(`tab-${tabName}`);
        
        if (activeContent) activeContent.classList.remove('hidden');
        if (activeTab) {
            activeTab.classList.remove('tab-inactive');
            activeTab.classList.add('tab-active');
        }
        
        currentTab = tabName;
        
        if (tabName === 'organigramme') {
            OrganigrammeManager.load();
        } else if (tabName === 'previsions') {
            PrevisionManager.load();
        }
    }
};

// ============================================================
// MODULE GESTION DES MODALES
// ============================================================
const ModalManager = {
    openAddModal: function() {
        document.getElementById('modalTitle').textContent = 'Ajouter un poste';
        document.getElementById('posteForm').reset();
        document.getElementById('posteId').value = '';
        document.getElementById('couleur').value = '#3B82F6';
        document.getElementById('type_contrat').value = 'CDI';
        document.getElementById('nombre_postes_prevus').value = '1';
        document.getElementById('heures_travail').value = '35';
        document.getElementById('posteModal').classList.remove('hidden');
        document.getElementById('nom').focus();
    },

    closeModal: function() {
        document.getElementById('posteModal').classList.add('hidden');
    },

    openConfirmModal: function(message, action) {
        document.getElementById('confirmMessage').textContent = message;
        document.getElementById('confirmModal').classList.remove('hidden');
        currentAction = action;
    },

    closeConfirmModal: function() {
        document.getElementById('confirmModal').classList.add('hidden');
        currentAction = null;
    },

    openNiveauxModal: function() {
        document.getElementById('niveauxModal').classList.remove('hidden');
        NiveauManager.load();
    },

    closeNiveauxModal: function() {
        document.getElementById('niveauxModal').classList.add('hidden');
        NiveauManager.clearForm();
    },

    openDepartementsModal: function() {
        document.getElementById('departementsModal').classList.remove('hidden');
        DepartementManager.load();
    },

    closeDepartementsModal: function() {
        document.getElementById('departementsModal').classList.add('hidden');
        DepartementManager.clearForm();
    }
};

// ============================================================
// MODULE GESTION DES DÉPARTEMENTS
// ============================================================
const DepartementManager = {
    load: function() {
        Utils.showLoading();
        fetch('?action=get_departements', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                Utils.hideLoading();
                if (data.success) {
                    departements = data.departements;
                    this.render(data.departements);
                    this.updateSelects(data.departements);
                } else {
                    Utils.showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                Utils.hideLoading();
                Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
            });
    },

    render: function(departements) {
        const container = document.getElementById('departementsList');
        container.innerHTML = '';
        
        if (departements.length === 0) {
            container.innerHTML = `
                <div class="col-span-full text-center text-gray-500 py-8">
                    <i class="fas fa-building text-4xl mb-2"></i>
                    <p>Aucun département défini.</p>
                </div>
            `;
            return;
        }

        departements.forEach(dept => {
            const card = document.createElement('div');
            card.className = 'bg-white border rounded-lg p-4 hover:shadow-md transition-shadow';
            card.style.borderLeftColor = dept.couleur;
            card.style.borderLeftWidth = '4px';
            
            card.innerHTML = `
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1">
                        <h5 class="font-semibold text-gray-900 text-lg">${Utils.escapeHtml(dept.nom)}</h5>
                        ${dept.responsable ? `<p class="text-sm text-gray-600 mt-1"><i class="fas fa-user mr-1"></i>${Utils.escapeHtml(dept.responsable)}</p>` : ''}
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="DepartementManager.edit(${dept.id})" class="text-blue-600 hover:text-blue-800" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="DepartementManager.delete(${dept.id})" class="text-red-600 hover:text-red-800" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                ${dept.description ? `<p class="text-sm text-gray-600 mb-3">${Utils.escapeHtml(dept.description)}</p>` : ''}
                
                <div class="space-y-2">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-500">Postes associés:</span>
                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full font-medium">
                            ${dept.nb_postes || 0} poste(s)
                        </span>
                    </div>
                    
                    ${dept.budget_annuel ? `
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-500">Budget annuel:</span>
                            <span class="font-medium text-green-600">
                                ${Utils.formatNumber(dept.budget_annuel)} FCFA
                            </span>
                        </div>
                    ` : ''}
                </div>
            `;
            container.appendChild(card);
        });
    },

    updateSelects: function(departements) {
        const selects = ['departement_id', 'departementFilter'];
        
        selects.forEach(selectId => {
            const select = document.getElementById(selectId);
            if (select) {
                const currentValue = select.value;
                const options = select.querySelectorAll('option:not(:first-child)');
                options.forEach(option => option.remove());
                
                departements.forEach(dept => {
                    const option = document.createElement('option');
                    option.value = dept.id;
                    option.textContent = dept.nom;
                    select.appendChild(option);
                });
                
                if (currentValue) {
                    select.value = currentValue;
                }
            }
        });
    },

    clearForm: function() {
        document.getElementById('departementForm').reset();
        document.getElementById('departementId').value = '';
    },

    edit: function(id) {
        const dept = departements.find(d => d.id == id);
        if (!dept) {
            Utils.showNotification('Département non trouvé', 'error');
            return;
        }
        
        document.getElementById('departementId').value = dept.id;
        document.getElementById('departementNom').value = dept.nom;
        document.getElementById('departementDescription').value = dept.description || '';
        document.getElementById('departementForm').scrollIntoView({ behavior: 'smooth' });
    },

    delete: function(id) {
        const dept = departements.find(d => d.id == id);
        if (!dept) return;
        
        ModalManager.openConfirmModal(
            `Êtes-vous sûr de vouloir supprimer le département "${dept.nom}" ?\n\nCette action est irréversible et ne sera possible que si aucun poste n'est associé à ce département.`,
            () => {
                Utils.showLoading();
                fetch('?action=delete_departement', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: id})
                })
                .then(response => response.json())
                .then(data => {
                    Utils.hideLoading();
                    ModalManager.closeConfirmModal();
                    if (data.success) {
                        Utils.showNotification(data.message);
                        DepartementManager.load();
                    } else {
                        Utils.showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    Utils.hideLoading();
                    ModalManager.closeConfirmModal();
                    Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
                });
            }
        );
    }
};

// ============================================================
// MODULE GESTION DES NIVEAUX HIÉRARCHIQUES
// ============================================================
const NiveauManager = {
    load: function() {
        Utils.showLoading();
        fetch('?action=get_niveaux', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                Utils.hideLoading();
                if (data.success) {
                    niveauxHierarchiques = data.niveaux;
                    this.render(data.niveaux);
                    this.updateSelects(data.niveaux);
                } else {
                    Utils.showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                Utils.hideLoading();
                Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
            });
    },

    render: function(niveaux) {
        const container = document.getElementById('niveauxList');
        container.innerHTML = '';
        
        if (niveaux.length === 0) {
            container.innerHTML = `
                <div class="text-center text-gray-500 py-4">
                    <i class="fas fa-layer-group text-2xl mb-2"></i>
                    <p>Aucun niveau hiérarchique défini.</p>
                </div>
            `;
            return;
        }

        niveaux.forEach(niveau => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50';
            item.innerHTML = `
                <div class="flex items-center">
                    <div class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium mr-3">
                        ${niveau.niveau}
                    </div>
                    <div>
                        <div class="font-medium text-gray-900">${Utils.escapeHtml(niveau.libelle)}</div>
                        ${niveau.description ? `<div class="text-sm text-gray-500">${Utils.escapeHtml(niveau.description)}</div>` : ''}
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button onclick="NiveauManager.edit(${niveau.id})" class="text-blue-600 hover:text-blue-800" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="NiveauManager.delete(${niveau.id})" class="text-red-600 hover:text-red-800" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(item);
        });
    },

    updateSelects: function(niveaux) {
        const selects = ['niveau_hierarchique', 'niveauFilter'];
        
        selects.forEach(selectId => {
            const select = document.getElementById(selectId);
            if (select) {
                const currentValue = select.value;
                const options = select.querySelectorAll('option:not(:first-child)');
                options.forEach(option => option.remove());
                
                niveaux.forEach(niveau => {
                    const option = document.createElement('option');
                    option.value = niveau.niveau;
                    option.textContent = `${niveau.niveau} - ${niveau.libelle}`;
                    select.appendChild(option);
                });
                
                if (currentValue) {
                    select.value = currentValue;
                }
            }
        });
    },

    clearForm: function() {
        document.getElementById('niveauForm').reset();
        document.getElementById('niveauId').value = '';
    },

    edit: function(id) {
        const niveau = niveauxHierarchiques.find(n => n.id == id);
        if (!niveau) {
            Utils.showNotification('Niveau non trouvé', 'error');
            return;
        }
        
        document.getElementById('niveauId').value = niveau.id;
        document.getElementById('niveauNum').value = niveau.niveau;
        document.getElementById('niveauLibelle').value = niveau.libelle;
        document.getElementById('niveauDescription').value = niveau.description || '';
        
        document.getElementById('niveauForm').scrollIntoView({ behavior: 'smooth' });
    },

    delete: function(id) {
        const niveau = niveauxHierarchiques.find(n => n.id == id);
        if (!niveau) return;
        
        ModalManager.openConfirmModal(
            `Êtes-vous sûr de vouloir supprimer le niveau "${niveau.libelle}" ?\n\nCette action est irréversible et ne sera possible que si aucun poste n'utilise ce niveau.`,
            () => {
                Utils.showLoading();
                fetch('?action=delete_niveau', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: id})
                })
                .then(response => response.json())
                .then(data => {
                    Utils.hideLoading();
                    ModalManager.closeConfirmModal();
                    if (data.success) {
                        Utils.showNotification(data.message);
                        NiveauManager.load();
                    } else {
                        Utils.showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    Utils.hideLoading();
                    ModalManager.closeConfirmModal();
                    Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
                });
            }
        );
    }
};

// ============================================================
// MODULE GESTION DES POSTES
// ============================================================
const PosteManager = {
    edit: function(id) {
        const poste = postes.find(p => p.id == id);
        if (!poste) {
            Utils.showNotification('Poste non trouvé', 'error');
            return;
        }
        
        document.getElementById('modalTitle').textContent = 'Modifier le poste';
        document.getElementById('posteId').value = poste.id;
        document.getElementById('nom').value = poste.nom;
        document.getElementById('description').value = poste.description || '';
        document.getElementById('salaire').value = poste.salaire || '';
        document.getElementById('couleur').value = poste.couleur;
        document.getElementById('type_contrat').value = poste.type_contrat || 'CDI';
        document.getElementById('niveau_hierarchique').value = poste.niveau_hierarchique || '';
        document.getElementById('poste_superieur_id').value = poste.poste_superieur_id || '';
        document.getElementById('competences_requises').value = poste.competences_requises || '';
        document.getElementById('nombre_postes_prevus').value = poste.nombre_postes_prevus || '1';
        document.getElementById('duree_contrat').value = poste.duree_contrat || '';
        document.getElementById('heures_travail').value = poste.heures_travail || '35';
        document.getElementById('avantages').value = poste.avantages || '';
        document.getElementById('code_paie').value = poste.code_paie || '';
        document.getElementById('categorie_paie').value = poste.categorie_paie || '';
        document.getElementById('regime_social').value = poste.regime_social || '';
        document.getElementById('taux_cotisation').value = poste.taux_cotisation || '';
        document.getElementById('departement_id').value = poste.departement_id || '';
        
        document.getElementById('posteModal').classList.remove('hidden');
        document.getElementById('nom').focus();
    },

    delete: function(id) {
        const poste = postes.find(p => p.id == id);
        if (!poste) return;
        
        ModalManager.openConfirmModal(
            `Êtes-vous sûr de vouloir supprimer le poste "${poste.nom}" ?`,
            () => {
                Utils.showLoading();
                fetch('?action=delete_poste', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: id})
                })
                .then(response => response.json())
                .then(data => {
                    Utils.hideLoading();
                    ModalManager.closeConfirmModal();
                    if (data.success) {
                        Utils.showNotification(data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        Utils.showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    Utils.hideLoading();
                    ModalManager.closeConfirmModal();
                    Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
                });
            }
        );
    },

    duplicate: function(id) {
        const poste = postes.find(p => p.id == id);
        if (!poste) return;
        
        ModalManager.openConfirmModal(
            `Voulez-vous dupliquer le poste "${poste.nom}" ?`,
            () => {
                Utils.showLoading();
                fetch('?action=duplicate_poste', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: id})
                })
                .then(response => response.json())
                .then(data => {
                    Utils.hideLoading();
                    ModalManager.closeConfirmModal();
                    if (data.success) {
                        Utils.showNotification(data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        Utils.showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    Utils.hideLoading();
                    ModalManager.closeConfirmModal();
                    Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
                });
            }
        );
    },

    applyFilters: function() {
        const search = document.getElementById('searchInput').value.trim();
        const typeContrat = document.getElementById('typeContratFilter').value;
        const niveau = document.getElementById('niveauFilter').value;
        const departement = document.getElementById('departementFilter').value;
        
        Utils.showLoading();
        const formData = new FormData();
        formData.append('search', search);
        formData.append('type_contrat', typeContrat);
        formData.append('niveau_hierarchique', niveau);
        formData.append('departement_id', departement);
        
        fetch('?action=search_postes', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            Utils.hideLoading();
            if (data.success) {
                this.updateGrid(data.postes);
            } else {
                Utils.showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            Utils.hideLoading();
            Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
        });
    },

    clearFilters: function() {
        document.getElementById('searchInput').value = '';
        document.getElementById('typeContratFilter').value = '';
        document.getElementById('niveauFilter').value = '';
        document.getElementById('departementFilter').value = '';
        location.reload();
    },

   // Remplacez cette fonction dans le module PosteManager
updateGrid: function(postesData) {
    const grid = document.getElementById('postesGrid');
    const noResults = document.getElementById('noResults');
    
    if (postesData.length === 0) {
        grid.classList.add('hidden');
        noResults.classList.remove('hidden');
        return;
    }
    
    grid.classList.remove('hidden');
    noResults.classList.add('hidden');
    
    grid.innerHTML = '';
    postesData.forEach(poste => {
        const card = document.createElement('div');
        card.className = `bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow niveau-${poste.niveau_hierarchique || 5}`;
        
        card.innerHTML = `
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <div class="w-4 h-4 rounded-full mr-3" style="background-color: ${poste.couleur || '#3B82F6'}"></div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">${Utils.escapeHtml(poste.nom)}</h3>
                        <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">${poste.type_contrat || 'CDI'}</span>
                    </div>
                </div>
                
                <div class="flex space-x-2">
                    <button onclick="editPoste(${poste.id})" class="text-blue-600 hover:text-blue-800 transition-colors" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="duplicatePoste(${poste.id})" class="text-green-600 hover:text-green-800 transition-colors" title="Dupliquer">
                        <i class="fas fa-copy"></i>
                    </button>
                    <button onclick="deletePoste(${poste.id})" class="text-red-600 hover:text-red-800 transition-colors" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            
            <div class="space-y-2 mb-4">
                <p class="text-gray-600 text-sm min-h-[40px]">${Utils.escapeHtml(poste.description || 'Aucune description')}</p>
                ${poste.competences_requises ? `
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-star mr-1"></i>Compétences: ${Utils.escapeHtml(poste.competences_requises.substring(0, 50))}${poste.competences_requises.length > 50 ? '...' : ''}
                    </div>
                ` : ''}
                ${poste.poste_superieur_nom ? `
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-level-up-alt mr-1"></i>Rapporte à: ${Utils.escapeHtml(poste.poste_superieur_nom)}
                    </div>
                ` : ''}
                ${poste.avantages ? `
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-gift mr-1"></i>Avantages: ${Utils.escapeHtml(poste.avantages.substring(0, 50))}${poste.avantages.length > 50 ? '...' : ''}
                    </div>
                ` : ''}
                ${poste.departement_nom ? `
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-building mr-1"></i>Département: ${Utils.escapeHtml(poste.departement_nom)}
                    </div>
                ` : ''}
                <div class="text-xs text-gray-500">
                    <i class="fas fa-clock mr-1"></i>
                    Heures/semaine: ${poste.heures_travail || '35'}h
                </div>
            </div>
            
            <div class="text-center mb-4">
                <span class="text-gray-500 text-sm">Salaire:</span>
                <div class="font-medium text-green-600 text-lg">${Utils.formatNumber(poste.salaire || 0)} FCFA</div>
                ${poste.duree_contrat ? `<div class="text-xs text-gray-500">Durée: ${Utils.escapeHtml(poste.duree_contrat)}</div>` : ''}
            </div>
            
            <div class="pt-4 border-t border-gray-200 space-y-2">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-500">Employés actuels:</span>
                    <span class="font-medium bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                        ${poste.nb_employes || 0}/${poste.nombre_postes_prevus || 1}
                    </span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-500">Niveau:</span>
                    <span class="font-medium text-gray-800">
                        ${poste.niveau_libelle || 'Non défini'}
                    </span>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });
}
};

// ============================================================
// MODULE ORGANIGRAMME
// ============================================================
const OrganigrammeManager = {
    load: function() {
        Utils.showLoading();
        fetch('?action=get_organigramme', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                Utils.hideLoading();
                if (data.success) {
                    this.render(data.organigramme);
                } else {
                    const container = document.getElementById('organigrammeContainer');
                    container.innerHTML = `
                        <div class="text-center text-gray-500 py-8">
                            <i class="fas fa-exclamation-triangle text-4xl mb-2"></i>
                            <p>${data.message || 'Erreur lors du chargement de l\'organigramme.'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                Utils.hideLoading();
                const container = document.getElementById('organigrammeContainer');
                container.innerHTML = `
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-exclamation-triangle text-4xl mb-2"></i>
                        <p>Erreur de connexion : ${error.message}</p>
                    </div>
                `;
            });
    },

    render: function(arbre) {
        const container = document.getElementById('organigrammeContainer');
        container.innerHTML = '';
        
        if (!arbre || arbre.length === 0) {
            container.innerHTML = `
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-sitemap text-4xl mb-2"></i>
                    <p>Aucun poste à afficher dans l'organigramme.</p>
                </div>
            `;
            return;
        }
        
        arbre.forEach(poste => {
            container.appendChild(this.createPosteNode(poste));
        });
    },

    createPosteNode: function(poste) {
        const node = document.createElement('div');
        node.className = 'organigramme-node mb-4';

        const card = document.createElement('div');
        card.className = `bg-white border-l-4 rounded-lg p-4 shadow-md niveau-${poste.niveau_hierarchique}`;
        card.style.borderColor = poste.couleur;
        card.innerHTML = `
            <div class="flex justify-between items-center">
                <div>
                    <h4 class="font-semibold text-gray-900">${Utils.escapeHtml(poste.nom)}</h4>
                    <p class="text-sm text-gray-600">${poste.type_contrat || 'CDI'} - ${poste.niveau_libelle || 'Niveau non défini'}</p>
                </div>
                <div class="text-right">
                    <div class="text-sm font-medium text-green-600">${Utils.formatNumber(poste.salaire || 0)} FCFA</div>
                    <div class="text-xs text-gray-500">${poste.nb_employes || 0} employé(s)</div>
                </div>
            </div>
            ${poste.description ? `<p class="text-xs text-gray-500 mt-2">${Utils.escapeHtml(poste.description.substring(0, 100))}${poste.description.length > 100 ? '...' : ''}</p>` : ''}
        `;

        node.appendChild(card);

        if (poste.enfants && poste.enfants.length > 0) {
            const childrenContainer = document.createElement('div');
            childrenContainer.className = 'children-container ml-6 mt-2 border-l-2 border-gray-200 pl-4';
            poste.enfants.forEach(enfant => {
                childrenContainer.appendChild(this.createPosteNode(enfant));
            });
            node.appendChild(childrenContainer);
        }

        return node;
    }
};

// ============================================================
// MODULE PRÉVISIONS
// ============================================================
const PrevisionManager = {
    load: function() {
        Utils.showLoading();
        fetch('?action=get_stats', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                Utils.hideLoading();
                if (data.success) {
                    this.renderPostesSousDotes(data.stats.postes_sous_dotes);
                    this.calculateCoutsPrevisionnels();
                } else {
                    const sousDotesContainer = document.getElementById('postesSousDotesList');
                    sousDotesContainer.innerHTML = `
                        <div class="text-center text-gray-500 py-4">
                            <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                            <p>${data.message || 'Erreur lors du chargement des prévisions.'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                Utils.hideLoading();
                const sousDotesContainer = document.getElementById('postesSousDotesList');
                sousDotesContainer.innerHTML = `
                    <div class="text-center text-gray-500 py-4">
                        <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                        <p>Erreur de connexion : ${error.message}</p>
                    </div>
                `;
            });
    },

    renderPostesSousDotes: function(postesSousDotes) {
        const container = document.getElementById('postesSousDotesList');
        container.innerHTML = '';
        
        if (!postesSousDotes || postesSousDotes.length === 0) {
            container.innerHTML = `
                <div class="text-center text-gray-500 py-4">
                    <i class="fas fa-check-circle text-2xl mb-2 text-green-500"></i>
                    <p>Aucun poste sous-doté détecté.</p>
                </div>
            `;
            return;
        }
        
        postesSousDotes.forEach(poste => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between p-4 bg-red-50 border border-red-200 rounded-lg mb-2';
            item.innerHTML = `
                <div>
                    <h4 class="font-medium text-red-900">${Utils.escapeHtml(poste.nom)}</h4>
                    <p class="text-sm text-red-700">
                        ${poste.nb_employes_actuels} employé(s) sur ${poste.nombre_postes_prevus} prévu(s)
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-lg font-bold text-red-600">-${poste.deficit}</div>
                    <div class="text-xs text-red-500">employé(s) manquant(s)</div>
                </div>
            `;
            container.appendChild(item);
        });
    },

    calculateCoutsPrevisionnels: function() {
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
        
        document.getElementById('coutActuel').textContent = Utils.formatNumber(coutActuel) + ' FCFA';
        document.getElementById('coutPrevisionnel').textContent = Utils.formatNumber(coutPrevisionnel) + ' FCFA';
        
        const diffElement = document.getElementById('coutDifference');
        diffElement.textContent = (difference >= 0 ? '+' : '') + Utils.formatNumber(difference) + ' FCFA';
        diffElement.className = difference > 0 ? 'text-2xl font-bold text-red-600' :
                               difference < 0 ? 'text-2xl font-bold text-green-600' :
                               'text-2xl font-bold text-gray-600';
    }
};

// ============================================================
// MODULE EXPORT
// ============================================================
const ExportManager = {
    exportPostesPDF: function() {
        Utils.showLoading();
        const link = document.createElement('a');
        link.href = '?action=export_postes_pdf';
        link.download = `postes_${new Date().toISOString().split('T')[0]}.pdf`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(() => {
            Utils.hideLoading();
            Utils.showNotification('Export PDF terminé avec succès');
        }, 1000);
    }
};

// ============================================================
// FONCTIONS GLOBALES (POUR COMPATIBILITÉ AVEC L'HTML)
// ============================================================
function showTab(tabName) {
    TabManager.showTab(tabName);
}

function openAddModal() {
    ModalManager.openAddModal();
}

function closeModal() {
    ModalManager.closeModal();
}

function openConfirmModal(message, action) {
    ModalManager.openConfirmModal(message, action);
}

function closeConfirmModal() {
    ModalManager.closeConfirmModal();
}

function openNiveauxModal() {
    ModalManager.openNiveauxModal();
}

function closeNiveauxModal() {
    ModalManager.closeNiveauxModal();
}

function openDepartementsModal() {
    ModalManager.openDepartementsModal();
}

function closeDepartementsModal() {
    ModalManager.closeDepartementsModal();
}

function clearDepartementForm() {
    DepartementManager.clearForm();
}

function clearNiveauForm() {
    NiveauManager.clearForm();
}

function editPoste(id) {
    PosteManager.edit(id);
}

function deletePoste(id) {
    PosteManager.delete(id);
}

function duplicatePoste(id) {
    PosteManager.duplicate(id);
}

function applyFilters() {
    PosteManager.applyFilters();
}

function clearFilters() {
    PosteManager.clearFilters();
}

function exportPostesPDF() {
    ExportManager.exportPostesPDF();
}

// ============================================================
// GESTIONNAIRES D'ÉVÉNEMENTS ET INITIALISATION
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // Gestionnaire de formulaire pour les postes
    const posteForm = document.getElementById('posteForm');
    if (posteForm) {
        posteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!Validator.validatePosteForm()) return;
            
            Utils.showLoading();
            const formData = new FormData(e.target);
            const isEdit = formData.get('id') !== '';
            const action = isEdit ? 'update_poste' : 'add_poste';
            
            fetch('?action=' + action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Utils.hideLoading();
                if (data.success) {
                    Utils.showNotification(data.message);
                    ModalManager.closeModal();
                    setTimeout(() => location.reload(), 500);
                } else {
                    Utils.showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                Utils.hideLoading();
                Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
            });
        });
    }

    // Gestionnaire de formulaire pour les niveaux
    const niveauForm = document.getElementById('niveauForm');
    if (niveauForm) {
        niveauForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!Validator.validateNiveauForm()) return;
            
            Utils.showLoading();
            const formData = new FormData(e.target);
            const isEdit = formData.get('id') !== '';
            const action = isEdit ? 'update_niveau' : 'add_niveau';
            
            fetch('?action=' + action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Utils.hideLoading();
                if (data.success) {
                    Utils.showNotification(data.message);
                    NiveauManager.clearForm();
                    NiveauManager.load();
                } else {
                    Utils.showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                Utils.hideLoading();
                Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
            });
        });
    }

    // Gestionnaire de formulaire pour les départements
    const departementForm = document.getElementById('departementForm');
    if (departementForm) {
        departementForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!Validator.validateDepartementForm()) return;
            
            Utils.showLoading();
            const formData = new FormData(e.target);
            const isEdit = formData.get('id') !== '';
            const action = isEdit ? 'update_departement' : 'add_departement';
            
            fetch('?action=' + action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Utils.hideLoading();
                if (data.success) {
                    Utils.showNotification(data.message);
                    DepartementManager.clearForm();
                    DepartementManager.load();
                } else {
                    Utils.showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                Utils.hideLoading();
                Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
            });
        });
    }

    // Gestionnaire du bouton de confirmation
    const confirmButton = document.getElementById('confirmButton');
    if (confirmButton) {
        confirmButton.addEventListener('click', function() {
            if (currentAction) {
                currentAction();
            }
        });
    }

    // Gestionnaires de recherche et filtres
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', Utils.debounce(PosteManager.applyFilters.bind(PosteManager), 500));
    }
    
    const typeContratFilter = document.getElementById('typeContratFilter');
    if (typeContratFilter) {
        typeContratFilter.addEventListener('change', PosteManager.applyFilters.bind(PosteManager));
    }
    
    const niveauFilter = document.getElementById('niveauFilter');
    if (niveauFilter) {
        niveauFilter.addEventListener('change', PosteManager.applyFilters.bind(PosteManager));
    }
    
    const departementFilter = document.getElementById('departementFilter');
    if (departementFilter) {
        departementFilter.addEventListener('change', PosteManager.applyFilters.bind(PosteManager));
    }

    // Gestionnaires de clic sur les modales pour fermeture
    const modals = [
        { id: 'posteModal', closeFunc: ModalManager.closeModal },
        { id: 'confirmModal', closeFunc: ModalManager.closeConfirmModal },
        { id: 'niveauxModal', closeFunc: ModalManager.closeNiveauxModal },
        { id: 'departementsModal', closeFunc: ModalManager.closeDepartementsModal }
    ];

    modals.forEach(modal => {
        const modalElement = document.getElementById(modal.id);
        if (modalElement) {
            modalElement.addEventListener('click', function(e) {
                if (e.target === this) modal.closeFunc();
            });
        }
    });

    // Gestionnaire de touche Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            ModalManager.closeModal();
            ModalManager.closeConfirmModal();
            ModalManager.closeNiveauxModal();
            ModalManager.closeDepartementsModal();
        }
    });

    // Validation en temps réel des champs
    const validationFields = [
        {
            id: 'nom',
            validator: function(value) {
                if (value.length > 0 && value.length < 2) {
                    Utils.showNotification('Le nom du poste doit contenir au moins 2 caractères', 'error');
                    this.focus();
                }
            }
        },
        {
            id: 'salaire',
            validator: function(value) {
                const salaire = parseInt(value) || 0;
                if (value && salaire < 0) {
                    Utils.showNotification('Le salaire ne peut pas être négatif', 'error');
                    this.value = 0;
                }
            }
        },
        {
            id: 'nombre_postes_prevus',
            validator: function(value) {
                const nombre = parseInt(value) || 1;
                if (nombre < 1) {
                    Utils.showNotification('Le nombre de postes prévus doit être au moins de 1', 'error');
                    this.value = 1;
                }
            }
        },
        {
            id: 'heures_travail',
            validator: function(value) {
                const heures = parseInt(value) || 35;
                if (heures < 1 || heures > 80) {
                    Utils.showNotification('Le nombre d\'heures doit être compris entre 1 et 80 heures par semaine', 'error');
                    this.value = 35;
                }
            }
        },
        {
            id: 'taux_cotisation',
            validator: function(value) {
                const taux = parseFloat(value) || 0;
                if (value && (taux < 0 || taux > 100)) {
                    Utils.showNotification('Le taux de cotisation doit être compris entre 0 et 100%', 'error');
                    this.value = '';
                }
            }
        },
        {
            id: 'niveauNum',
            validator: function(value) {
                const niveau = parseInt(value) || 0;
                if (value && (niveau < 1 || niveau > 99)) {
                    Utils.showNotification('Le niveau doit être compris entre 1 et 99', 'error');
                    this.value = '';
                }
            }
        },
        {
            id: 'niveauLibelle',
            validator: function(value) {
                if (value.length > 0 && value.length < 2) {
                    Utils.showNotification('Le libellé doit contenir au moins 2 caractères', 'error');
                    this.focus();
                }
            }
        },
        {
            id: 'departementNom',
            validator: function(value) {
                if (value.length > 0 && value.length < 2) {
                    Utils.showNotification('Le nom du département doit contenir au moins 2 caractères', 'error');
                    this.focus();
                }
            }
        },
    ];

    validationFields.forEach(field => {
        const element = document.getElementById(field.id);
        if (element) {
            element.addEventListener('blur', function() {
                field.validator.call(this, this.value.trim());
            });
        }
    });

    // Initialiser l'onglet par défaut
    TabManager.showTab('postes');
});

// ============================================================
// MODULE GESTION HIÉRARCHIE (ADMIN SEULEMENT)
// ============================================================
const HierarchieManager = {
    currentPosteId: null,
    
    openModal: function() {
        document.getElementById('hierarchieModal').classList.remove('hidden');
        this.loadHierarchie();
    },
    
    closeModal: function() {
        document.getElementById('hierarchieModal').classList.add('hidden');
        this.currentPosteId = null;
    },
    
    loadHierarchie: function() {
        Utils.showLoading();
        fetch('?action=get_hierarchie_complete', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                Utils.hideLoading();
                if (data.success) {
                    this.renderHierarchie(data.hierarchie);
                } else {
                    Utils.showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                Utils.hideLoading();
                Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
            });
    },
    
    renderHierarchie: function(hierarchie) {
        const container = document.getElementById('hierarchieTree');
        container.innerHTML = '';
        
        // Créer l'arbre
        const tree = this.buildTree(hierarchie);
        tree.forEach(node => {
            container.appendChild(this.createNodeElement(node));
        });
    },
    
    buildTree: function(postes) {
        const postesMap = {};
        const tree = [];
        
        // Créer la map des postes
        postes.forEach(poste => {
            postesMap[poste.id] = { ...poste, children: [] };
        });
        
        // Construire l'arbre
        postes.forEach(poste => {
            if (poste.poste_superieur_id && postesMap[poste.poste_superieur_id]) {
                postesMap[poste.poste_superieur_id].children.push(postesMap[poste.id]);
            } else {
                tree.push(postesMap[poste.id]);
            }
        });
        
        return tree;
    },
    
    createNodeElement: function(node) {
        const div = document.createElement('div');
        div.className = 'border border-gray-200 rounded-lg p-4 bg-white';
        
        const nodeHtml = `
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center">
                    <i class="fas fa-briefcase mr-2 text-blue-600"></i>
                    <span class="font-medium text-gray-900">${Utils.escapeHtml(node.nom)}</span>
                    <span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                        ${node.niveau_libelle || 'N/A'}
                    </span>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">${node.nb_employes} employé(s)</span>
                    <button onclick="HierarchieManager.editRelation(${node.id}, '${node.nom.replace(/'/g, "\\'")}', ${node.poste_superieur_id || 'null'})" 
                            class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-edit mr-1"></i>Modifier
                    </button>
                </div>
            </div>
        `;
        
        div.innerHTML = nodeHtml;
        
        // Ajouter les enfants si ils existent
        if (node.children && node.children.length > 0) {
            const childrenContainer = document.createElement('div');
            childrenContainer.className = 'ml-6 mt-3 space-y-2 border-l-2 border-gray-200 pl-4';
            
            node.children.forEach(child => {
                childrenContainer.appendChild(this.createNodeElement(child));
            });
            
            div.appendChild(childrenContainer);
        }
        
        return div;
    },
    
    editRelation: function(posteId, posteName, currentSuperieurId) {
        this.currentPosteId = posteId;
        document.getElementById('currentPosteName').value = posteName;
        
        // Charger les postes supérieurs possibles
        Utils.showLoading();
        fetch(`?action=get_postes_superieurs&exclude=${posteId}`, { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                Utils.hideLoading();
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
                    
                    document.getElementById('editHierarchieModal').classList.remove('hidden');
                } else {
                    Utils.showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                Utils.hideLoading();
                Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
            });
    },
    
    closeEditModal: function() {
        document.getElementById('editHierarchieModal').classList.add('hidden');
        this.currentPosteId = null;
    },
    
    saveChange: function() {
        if (!this.currentPosteId) return;
        
        const newSuperieurId = document.getElementById('newPosteSuperieur').value || null;
        
        Utils.showLoading();
        fetch('?action=set_poste_superieur', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                poste_id: this.currentPosteId,
                poste_superieur_id: newSuperieurId
            })
        })
        .then(response => response.json())
        .then(data => {
            Utils.hideLoading();
            if (data.success) {
                Utils.showNotification(data.message);
                this.closeEditModal();
                this.loadHierarchie();
            } else {
                Utils.showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            Utils.hideLoading();
            Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
        });
    },
    
    removeRelation: function() {
        if (!this.currentPosteId) return;
        
        ModalManager.openConfirmModal(
            'Êtes-vous sûr de vouloir supprimer la relation hiérarchique de ce poste ?',
            () => {
                Utils.showLoading();
                fetch('?action=remove_poste_superieur', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ poste_id: this.currentPosteId })
                })
                .then(response => response.json())
                .then(data => {
                    Utils.hideLoading();
                    ModalManager.closeConfirmModal();
                    if (data.success) {
                        Utils.showNotification(data.message);
                        this.closeEditModal();
                        this.loadHierarchie();
                    } else {
                        Utils.showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    Utils.hideLoading();
                    ModalManager.closeConfirmModal();
                    Utils.showNotification('Erreur de connexion: ' + error.message, 'error');
                });
            }
        );
    }
};

// FONCTIONS GLOBALES POUR LA HIÉRARCHIE
function openHierarchieModal() {
    HierarchieManager.openModal();
}

function closeHierarchieModal() {
    HierarchieManager.closeModal();
}

function closeEditHierarchieModal() {
    HierarchieManager.closeEditModal();
}

function saveHierarchieChange() {
    HierarchieManager.saveChange();
}

function removeHierarchieRelation() {
    HierarchieManager.removeRelation();
}

    </script>
</body>
</html>
            
            