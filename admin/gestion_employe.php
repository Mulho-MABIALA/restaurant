<?php
// Gestion des erreurs pour AJAX
if (isset($_GET['action']) || isset($_POST['ajaxAction'])) {
    // Capturer toute sortie inattendue
    ob_start();

    ini_set('display_errors', 0);
    error_reporting(E_ALL);

    // Handler d'erreur pour AJAX
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        // Ignorer les warnings de conversion implicite (PHP 8.1+)
        if (strpos($errstr, 'Implicit conversion') !== false) {
            return false; // Laisser PHP gérer normalement
        }

        // Pour les erreurs graves uniquement
        if ($errno === E_ERROR || $errno === E_USER_ERROR || $errno === E_RECOVERABLE_ERROR) {
            ob_clean(); // Nettoyer le buffer
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erreur PHP: ' . $errstr,
                'file' => basename($errfile),
                'line' => $errline
            ]);
            exit;
        }

        return false; // Laisser PHP gérer les autres erreurs normalement
    });

    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            ob_clean(); // Nettoyer le buffer
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erreur fatale: ' . $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line']
            ]);
        }
    });
}

session_start();
require_once '../config.php';
require_once './permissions.php';
require_once 'phpqrcode/qrlib.php';
require_once 'classes/PayrollCalculator.php';
require_once 'classes/BulletinPDFGenerateur.php';

// Fonction pour détecter si c'est une requête AJAX
$isAjaxRequest = isset($_GET['action']) || isset($_POST['ajaxAction']);

// Rediriger si l'admin n'est pas connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expirée']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// Vérifier que admin_id existe
if (!isset($_SESSION['admin_id'])) {
    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expirée']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// Vérifier les permissions
if (!canAccess($conn, $_SESSION['admin_id'], 'gestion_employes')) {
    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès refusé']);
        exit;
    }
    header('Location: access_denied.php');
    exit;
}
    // GESTIONNAIRE D'EMPLOYÉS
    class EmployeeManager
    {
        private $conn;

        public function __construct(PDO $connection)
        {
            $this->conn = $connection;
        }

  public function getAllEmployees(): array
{
    try {
        $stmt = $this->conn->query("
            SELECT e.*,
                p.nom as poste_nom,
                   p.couleur as poste_couleur,
                   p.salaire as poste_salaire,
                   p.type_contrat,
                   p.duree_contrat,
                   p.niveau_hierarchique,
                   p.competences_requises,
                   p.avantages,
                   p.code_paie,
                   p.categorie_paie,
                   p.regime_social,
                   p.taux_cotisation,
                   p.salaire_min,
                   p.salaire_max,
                   p.heures_travail as heures_par_mois,
                   ps.nom as poste_superieur_nom,
                   d.nom as departement_nom,
                   d.couleur as departement_couleur,
                   d.id as departement_id,
                   e.num_secu,
                   e.num_identite,
                   e.type_identite,
                   e.situation_familiale,
                   e.nombre_enfants,
                   e.iban,
                   e.nom_banque,
                   e.titulaire_compte,
                   e.bic
            FROM employes e
            LEFT JOIN postes p ON e.poste_id = p.id
            LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
            LEFT JOIN departements d ON p.departement_id = d.id
            ORDER BY e.statut DESC, e.nom, e.prenom
        ");

        if (!$stmt) {
            error_log("Erreur: Impossible d'exécuter la requête getAllEmployees");
            return [];
        }

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($result === false) {
            error_log("Erreur: fetchAll a retourné false dans getAllEmployees");
            return [];
        }

        error_log("getAllEmployees: " . count($result) . " employés récupérés avec succès");
        return $result;

    } catch (PDOException $e) {
        error_log("Erreur SQL getAllEmployees: " . $e->getMessage());
        error_log("Code erreur: " . $e->getCode());
        return [];
    } catch (Exception $e) {
        error_log("Erreur générale getAllEmployees: " . $e->getMessage());
        return [];
    }
}

        public function reactivateEmployee(int $employee_id): array
        {
            try {
                $stmt = $this->conn->prepare("SELECT statut, nom, prenom FROM employes WHERE id = ?");
                $stmt->execute([$employee_id]);
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);

                if (! $employee) {
                    throw new Exception('Employé non trouvé');
                }

                if ($employee['statut'] !== 'inactif') {
                    throw new Exception('Cet employé est déjà actif');
                }

                $stmt = $this->conn->prepare("UPDATE employes SET statut = 'actif' WHERE id = ?");
                $stmt->execute([$employee_id]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('Erreur lors de la réactivation');
                }

                $this->logActivity('REACTIVATE_EMPLOYEE', 'employes', $employee_id, [
                    'statut' => 'actif',
                    'nom'    => $employee['nom'],
                    'prenom' => $employee['prenom'],
                ]);

                return [
                    'success' => true,
                    'message' => 'Employé ' . $employee['prenom'] . ' ' . $employee['nom'] . ' réactivé avec succès',
                ];

            } catch (Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }

       public function getEmployeeById(int $id): ?array
{
    try {
        $stmt = $this->conn->prepare("
            SELECT e.*,
                   p.nom as poste_nom,
                   p.couleur as poste_couleur,
                   p.salaire as poste_salaire,
                   p.type_contrat,
                   p.duree_contrat,
                   p.niveau_hierarchique,
                   p.competences_requises,
                   p.avantages,
                   p.code_paie,
                   p.categorie_paie,
                   p.regime_social,
                   p.taux_cotisation,
                   p.salaire_min,
                   p.salaire_max,
                   p.heures_travail as heures_par_mois,
                   e.num_secu,
                   e.num_identite,
                   e.type_identite,
                   e.situation_familiale,
                   e.nombre_enfants,
                   e.iban,
                   e.nom_banque,
                   e.titulaire_compte,
                   e.bic
                   /* SUPPRIMER: e.niveau_etude, e.langues, e.competences, e.formations, e.experiences */
            FROM employes e
            LEFT JOIN postes p ON e.poste_id = p.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;

    } catch (PDOException $e) {
        error_log("Erreur SQL getEmployeeById: " . $e->getMessage());
        return null;
    }
}
        public function permanentDeleteEmployee(int $employee_id): array
        {
            try {
                $this->conn->beginTransaction();

                $stmt = $this->conn->prepare("SELECT statut FROM employes WHERE id = ?");
                $stmt->execute([$employee_id]);
                $employee = $stmt->fetch();

                if (! $employee) {
                    throw new Exception('Employé non trouvé');
                }

                if ($employee['statut'] !== 'inactif') {
                    throw new Exception('Seuls les employés inactifs peuvent être supprimés définitivement');
                }

                $this->conn->prepare("DELETE FROM presences WHERE employe_id = ?")->execute([$employee_id]);
                $this->conn->prepare("DELETE FROM bulletins_paie WHERE employe_id = ?")->execute([$employee_id]);

                $stmt = $this->conn->prepare("DELETE FROM employes WHERE id = ?");
                $stmt->execute([$employee_id]);

                $this->logActivity('PERMANENT_DELETE_EMPLOYEE', 'employes', $employee_id, ['action' => 'suppression_definitive']);

                $this->conn->commit();
                return ['success' => true, 'message' => 'Employé supprimé définitivement'];

            } catch (Exception $e) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
       public function getStatistics(): array
{
    $stats = [];

    try {
        // Total employés actifs - avec gestion d'erreur
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes WHERE statut = 'actif'");
        $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $stats['total_actifs'] = $result ? (int) $result['total'] : 0;

        // Présents aujourd'hui - Requête sécurisée
        $stmt = $this->conn->query("
            SELECT COUNT(DISTINCT p.employe_id) as presents
            FROM presences p
            INNER JOIN employes e ON p.employe_id = e.id
            WHERE DATE(p.heure_arrivee) = CURDATE()
            AND p.heure_arrivee IS NOT NULL
            AND e.statut = 'actif'
        ");
        $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $stats['presents_aujourd_hui'] = $result ? (int) $result['presents'] : 0;

        // Nouveaux employés ce mois - sécurisé
        $stmt = $this->conn->query("
            SELECT COUNT(*) as total 
            FROM employes 
            WHERE DATE_FORMAT(date_embauche, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') 
            AND statut = 'actif'
        ");
        $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $stats['nouveaux_ce_mois'] = $result ? (int) $result['total'] : 0;

        // Total admins - sécurisé
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes WHERE is_admin = 1 AND statut = 'actif'");
        $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $stats['total_admins'] = $result ? (int) $result['total'] : 0;

        // Absents aujourd'hui - Requête sécurisée
        $stmt = $this->conn->query("
            SELECT COUNT(*) as absents
            FROM employes e
            LEFT JOIN presences p ON e.id = p.employe_id AND DATE(p.heure_arrivee) = CURDATE()
            WHERE e.statut = 'actif'
            AND p.employe_id IS NULL
        ");
        $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $stats['absents_aujourd_hui'] = $result ? (int) $result['absents'] : 0;

        // Retards aujourd'hui - sécurisé
        $stmt = $this->conn->query("
            SELECT COUNT(*) as retards
            FROM presences p
            INNER JOIN employes e ON p.employe_id = e.id
            WHERE DATE(p.heure_arrivee) = CURDATE()
            AND TIME(p.heure_arrivee) > e.heure_debut
            AND e.statut = 'actif'
        ");
        $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $stats['retards_aujourd_hui'] = $result ? (int) $result['retards'] : 0;

        // Par type de contrat - avec gestion d'erreur
        try {
            $stmt = $this->conn->query("
                SELECT p.type_contrat, COUNT(e.id) as count
                FROM employes e
                LEFT JOIN postes p ON e.poste_id = p.id
                WHERE e.statut = 'actif'
                GROUP BY p.type_contrat
            ");
            $stats['par_contrat'] = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            error_log("Erreur par_contrat: " . $e->getMessage());
            $stats['par_contrat'] = [];
        }

        // Total inactifs - sécurisé
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes WHERE statut = 'inactif'");
        $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $stats['total_inactifs'] = $result ? (int) $result['total'] : 0;

        // Debug - Log des statistiques
        error_log("Statistiques calculées avec succès: " . json_encode($stats));

        return $stats;

    } catch (PDOException $e) {
        error_log("Erreur SQL getStatistics: " . $e->getMessage());
        // Retourner des valeurs par défaut en cas d'erreur
        return [
            'total_actifs'         => 0,
            'presents_aujourd_hui' => 0,
            'nouveaux_ce_mois'     => 0,
            'total_admins'         => 0,
            'absents_aujourd_hui'  => 0,
            'retards_aujourd_hui'  => 0,
            'par_contrat'          => [],
            'total_inactifs'       => 0,
        ];
    } catch (Exception $e) {
        error_log("Erreur générale getStatistics: " . $e->getMessage());
        return [
            'total_actifs'         => 0,
            'presents_aujourd_hui' => 0,
            'nouveaux_ce_mois'     => 0,
            'total_admins'         => 0,
            'absents_aujourd_hui'  => 0,
            'retards_aujourd_hui'  => 0,
            'par_contrat'          => [],
            'total_inactifs'       => 0,
        ];
    }
}


        public function getPresenceDetailsForEmployee(int $employee_id, string $date = null): array
        {
            if (! $date) {
                $date = date('Y-m-d');
            }

            $stmt = $this->conn->prepare("
            SELECT p.*, e.nom, e.prenom, e.heure_debut as heure_prevue_debut, e.heure_fin as heure_prevue_fin
            FROM presences p
            INNER JOIN employes e ON p.employe_id = e.id
            WHERE p.employe_id = ? AND DATE(p.heure_arrivee) = ?
            ORDER BY p.heure_arrivee DESC
            LIMIT 1
        ");
            $stmt->execute([$employee_id, $date]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! $result) {
                return ['status' => 'absent', 'message' => 'Aucune présence enregistrée'];
            }

            $heure_arrivee = new DateTime($result['heure_arrivee']);
            $heure_prevue  = new DateTime($date . ' ' . $result['heure_prevue_debut']);

            if ($heure_arrivee > $heure_prevue) {
                $retard                   = $heure_arrivee->diff($heure_prevue);
                $result['status']         = 'retard';
                $result['retard_minutes'] = ($retard->h * 60) + $retard->i;
                $result['message']        = 'En retard de ' . $result['retard_minutes'] . ' minutes';
            } else {
                $result['status']  = 'a_temps';
                $result['message'] = 'À l\'heure';
            }

            if ($result['heure_depart']) {
                $result['status'] .= '_parti';
                $result['message'] .= ' - Parti à ' . date('H:i', strtotime($result['heure_depart']));
            } else {
                $result['message'] .= ' - Encore au travail';
            }

            return $result;
        }

        public function markDeparture(int $employee_id): array
        {
            try {
                $today = date('Y-m-d');
                $now   = date('Y-m-d H:i:s');

                $stmt = $this->conn->prepare("
                SELECT p.*, e.nom, e.prenom
                FROM presences p
                INNER JOIN employes e ON p.employe_id = e.id
                WHERE p.employe_id = ? AND DATE(p.heure_arrivee) = ? AND p.heure_arrivee IS NOT NULL
            ");
                $stmt->execute([$employee_id, $today]);
                $presence = $stmt->fetch(PDO::FETCH_ASSOC);

                if (! $presence) {
                    return ['success' => false, 'message' => 'Aucune arrivée enregistrée pour aujourd\'hui'];
                }

                if ($presence['heure_depart']) {
                    return [
                        'success' => false,
                        'message' => 'Départ déjà enregistré à ' . date('H:i', strtotime($presence['heure_depart'])),
                    ];
                }

                $stmt = $this->conn->prepare("
                UPDATE presences
                SET heure_depart = ?, statut = 'parti'
                WHERE id = ?
            ");
                $stmt->execute([$now, $presence['id']]);

                $arrivee            = new DateTime($presence['heure_arrivee']);
                $depart             = new DateTime($now);
                $duree              = $arrivee->diff($depart);
                $heures_travaillees = $duree->h + ($duree->i / 60);

                return [
                    'success'          => true,
                    'message'          => 'Départ enregistré avec succès',
                    'heure_depart'     => date('H:i', strtotime($now)),
                    'duree_travaillee' => round($heures_travaillees, 2),
                    'employee'         => ['nom' => $presence['nom'], 'prenom' => $presence['prenom']],
                ];

            } catch (Exception $e) {
                error_log("Erreur markDeparture: " . $e->getMessage());
                return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement du départ'];
            }
        }

        public function getTodayAttendance(): array
        {
            try {
                $stmt = $this->conn->query("
                SELECT e.id, e.nom, e.prenom, e.photo, e.heure_debut, e.heure_fin,
                       p.nom as poste_nom, p.couleur as poste_couleur,
                       pr.heure_arrivee, pr.heure_depart,
                       CASE
                           WHEN pr.heure_arrivee IS NULL THEN 'absent'
                           WHEN pr.heure_depart IS NOT NULL THEN 'parti'
                           WHEN TIME(pr.heure_arrivee) > e.heure_debut THEN 'retard'
                           ELSE 'present'
                       END as statut_presences,
                       CASE
                           WHEN pr.heure_arrivee IS NOT NULL AND TIME(pr.heure_arrivee) > e.heure_debut
                           THEN TIMESTAMPDIFF(MINUTE,
                                CONCAT(DATE(pr.heure_arrivee), ' ', e.heure_debut),
                                pr.heure_arrivee)
                           ELSE 0
                       END as retard_minutes
                FROM employes e
                LEFT JOIN postes p ON e.poste_id = p.id
                LEFT JOIN presences pr ON e.id = pr.employe_id AND DATE(pr.heure_arrivee) = CURDATE()
                WHERE e.statut = 'actif'
                ORDER BY
                    CASE
                        WHEN pr.heure_arrivee IS NULL THEN 0
                        WHEN pr.heure_depart IS NULL THEN 1
                        ELSE 2
                    END,
                    pr.heure_arrivee ASC
            ");

                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            } catch (PDOException $e) {
                error_log("Erreur getTodayAttendance: " . $e->getMessage());
                return [];
            }
        }

      public function addEmployee(array $data): array
{
    try {
        $this->conn->beginTransaction();
        $this->checkEmailUniqueness($data['email']);
        $this->validateRequiredFields($data, ['nom', 'prenom', 'email', 'date_embauche']);

        $photo_filename = $this->handlePhotoUpload();
        $cv_filename = $this->handleDocumentUpload('cv');
        $contrat_filename = $this->handleDocumentUpload('contrat');
        $piece_identite_filename = $this->handleDocumentUpload('piece_identite');

        $employee_id = $this->insertEmployee($data, $photo_filename, $cv_filename, $contrat_filename, $piece_identite_filename);

        $numeric_code = $this->generateAndSaveQRCode($employee_id, $data);

        $this->logActivity('CREATE_EMPLOYEE', 'employes', $employee_id, [
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'code_numerique' => $numeric_code,
        ]);

        $this->conn->commit();
        return [
            'success' => true,
            'message' => 'Employé ajouté avec succès',
            'employee_id' => $employee_id,
            'numeric_code' => $numeric_code,
        ];
    } catch (Exception $e) {
        $this->conn->rollBack();
        error_log("Erreur addEmployee: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}


        public function updateEmployee(array $data): array
        {
            try {
                if (empty($data['id'])) {
                    throw new Exception('ID employé requis');
                }

                $employee_id      = $data['id'];
                $current_employee = $this->getEmployeeById($employee_id);

                if (! $current_employee) {
                    throw new Exception('Employé non trouvé');
                }

                $this->checkEmailUniqueness($data['email'], $employee_id);
                $photo_filename = $this->handlePhotoUpload($current_employee['photo']);
                $this->updateEmployeeData($employee_id, $data, $photo_filename);

                $this->logActivity('UPDATE_EMPLOYEE', 'employes', $employee_id, [
                    'nom'    => $data['nom'],
                    'prenom' => $data['prenom'],
                ]);

                return ['success' => true, 'message' => 'Employé modifié avec succès'];

            } catch (Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }

        public function deactivateEmployee(int $employee_id): array
        {
            try {
                $stmt = $this->conn->prepare("UPDATE employes SET statut = 'inactif' WHERE id = ?");
                $stmt->execute([$employee_id]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('Employé non trouvé');
                }

                $this->logActivity('DEACTIVATE_EMPLOYEE', 'employes', $employee_id, ['statut' => 'inactif']);

                return ['success' => true, 'message' => 'Employé désactivé avec succès'];

            } catch (Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }

        public function getTypesContrat(): array
        {
            return [
                'CDI'           => 'Contrat à Durée Indéterminée',
                'CDD'           => 'Contrat à Durée Déterminée',
                'Stage'         => 'Stage',
                'Apprentissage' => 'Contrat d\'Apprentissage',
                'Freelance'     => 'Freelance/Consultant',
                'Temps_partiel' => 'Temps Partiel',
            ];
        }

  private function validateRequiredFields(array $data, array $required_fields): void
{
    // Validation des champs de base seulement
    $basic_required = ['nom', 'prenom', 'email', 'date_embauche'];
    
    foreach ($basic_required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Le champ $field est requis");
        }
    }
    
    // Validation email
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Format d'email invalide");
    }
    
    // Validations optionnelles (uniquement si les champs sont fournis)
    if (!empty($data['num_secu']) && !preg_match('/^[0-9]{15}$/', $data['num_secu'])) {
        throw new Exception("Le numéro de sécurité sociale doit contenir exactement 15 chiffres");
    }
    
    if (!empty($data['iban']) && strlen(str_replace(' ', '', $data['iban'])) < 15) {
        throw new Exception("L'IBAN semble invalide");
    }
}

        private function checkEmailUniqueness(string $email, int $exclude_id = null): void
        {
            // Nettoyer l'email
            $email = trim(strtolower($email));

            if (empty($email)) {
                throw new Exception('Email requis');
            }

            $sql    = "SELECT id, nom, prenom FROM employes WHERE LOWER(TRIM(email)) = ? AND statut = 'actif'";
            $params = [$email];

            if ($exclude_id) {
                $sql .= " AND id != ?";
                $params[] = $exclude_id;
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                throw new Exception("Cet email est déjà utilisé par {$existing['prenom']} {$existing['nom']} (ID: {$existing['id']})");
            }
        }

        private function handlePhotoUpload(string $current_photo = 'default-avatar.png'): string
        {
            if (! isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                return $current_photo;
            }

            $upload_dir = 'uploads/photos/';
            if (! is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

            if (! in_array($file_ext, $allowed_exts) || $_FILES['photo']['size'] > 5000000) {
                throw new Exception('Format de photo non valide ou taille trop importante');
            }

            $photo_filename = uniqid() . '.' . $file_ext;
            $upload_path    = $upload_dir . $photo_filename;

            if (! move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                throw new Exception('Erreur lors de l\'upload de la photo');
            }

            if ($current_photo !== 'default-avatar.png') {
                $old_photo = $upload_dir . $current_photo;
                if (file_exists($old_photo)) {
                    unlink($old_photo);
                }
            }

            return $photo_filename;
        }

      private function handleDocumentUpload(string $fieldName, string $currentFile = null): string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return $currentFile ?? '';
    }

    $upload_dir = 'uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
    $allowed_exts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    if (!in_array($file_ext, $allowed_exts) || $_FILES[$fieldName]['size'] > 10000000) {
        throw new Exception('Format de document non valide ou taille trop importante (max 10MB)');
    }

    $filename = uniqid() . '_' . $fieldName . '.' . $file_ext;
    $upload_path = $upload_dir . $filename;

    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $upload_path)) {
        throw new Exception('Erreur lors de l\'upload du document');
    }

    // Supprimer l'ancien fichier s'il existe
    if ($currentFile && file_exists($upload_dir . $currentFile)) {
        unlink($upload_dir . $currentFile);
    }

    return $filename;
}
private function insertEmployee(array $data, string $photo_filename, string $cv_filename = null, string $contrat_filename = null, string $piece_identite_filename = null): int
{
    $salaire = !empty($data['salaire']) ? (int) $data['salaire'] : null;

    $stmt = $this->conn->prepare("
        INSERT INTO employes (
            nom, prenom, email, telephone, poste_id, salaire, date_embauche,
            heure_debut, heure_fin, photo, is_admin, statut,
            date_naissance, lieu_naissance, nationalite, sexe,
            contact_urgence_nom, contact_urgence_relation, contact_urgence_telephone,
            adresse, num_secu, num_identite, type_identite, situation_familiale,
            nombre_enfants, iban, nom_banque, titulaire_compte, bic,
            cv, contrat, piece_identite, code_numerique
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['nom'] ?? '',
        $data['prenom'] ?? '',
        $data['email'] ?? '',
        $data['telephone'] ?? null,
        $data['poste_id'] ?? null,
        $salaire,
        $data['date_embauche'] ?? '',
        $data['heure_debut'] ?? '08:00:00',
        $data['heure_fin'] ?? '17:00:00',
        $photo_filename,
        isset($data['is_admin']) ? 1 : 0,
        $data['statut'] ?? 'actif',
        $data['date_naissance'] ?? null,
        $data['lieu_naissance'] ?? null,
        $data['nationalite'] ?? null,
        $data['sexe'] ?? null,
        $data['contact_urgence_nom'] ?? null,
        $data['contact_urgence_relation'] ?? null,
        $data['contact_urgence_telephone'] ?? null,
        $data['adresse'] ?? null,
        $data['num_secu'] ?? null,
        $data['num_identite'] ?? null,
        $data['type_identite'] ?? null,
        $data['situation_familiale'] ?? null,
        $data['nombre_enfants'] ?? 0,
        $data['iban'] ?? null,
        $data['nom_banque'] ?? null,
        $data['titulaire_compte'] ?? null,
        $data['bic'] ?? null,
        $cv_filename,           // 30
        $contrat_filename,      // 31
        $piece_identite_filename, // 32
        null                    // 33 - code_numerique sera mis à jour plus tard
    ]);

    return $this->conn->lastInsertId();
}
 
private function updateEmployeeData(int $employee_id, array $data, string $photo_filename): void
{
    $salaire = !empty($data['salaire']) ? (int) $data['salaire'] : null;

    $stmt = $this->conn->prepare("
        UPDATE employes
        SET nom = ?, prenom = ?, email = ?, telephone = ?, poste_id = ?, salaire = ?,
            date_embauche = ?, heure_debut = ?, heure_fin = ?, photo = ?,
            is_admin = ?, statut = ?,
            date_naissance = ?, lieu_naissance = ?, nationalite = ?, sexe = ?,
            contact_urgence_nom = ?, contact_urgence_relation = ?, contact_urgence_telephone = ?,
            adresse = ?, num_secu = ?, num_identite = ?, type_identite = ?, situation_familiale = ?,
            nombre_enfants = ?, iban = ?, nom_banque = ?, titulaire_compte = ?, bic = ?,
            cv = ?, contrat = ?, piece_identite = ?, code_numerique = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $data['nom'],
        $data['prenom'],
        $data['email'],
        $data['telephone'] ?? null,
        $data['poste_id'] ?? null,
        $salaire,
        $data['date_embauche'],
        $data['heure_debut'] ?? '08:00:00',
        $data['heure_fin'] ?? '17:00:00',
        $photo_filename,
        isset($data['is_admin']) ? 1 : 0,
        $data['statut'] ?? 'actif',
        $data['date_naissance'] ?? null,
        $data['lieu_naissance'] ?? null,
        $data['nationalite'] ?? null,
        $data['sexe'] ?? null,
        $data['contact_urgence_nom'] ?? null,
        $data['contact_urgence_relation'] ?? null,
        $data['contact_urgence_telephone'] ?? null,
        $data['adresse'] ?? null,
        $data['num_secu'] ?? null,
        $data['num_identite'] ?? null,
        $data['type_identite'] ?? null,
        $data['situation_familiale'] ?? null,
        $data['nombre_enfants'] ?? 0,
        $data['iban'] ?? null,
        $data['nom_banque'] ?? null,
        $data['titulaire_compte'] ?? null,
        $data['bic'] ?? null,
        $data['cv'] ?? null,
        $data['contrat'] ?? null,
        $data['piece_identite'] ?? null,
        $data['code_numerique'] ?? null,
        $employee_id
    ]);
}

        private function generateAndSaveQRCode(int $employee_id, array $data): string
        {
            $numeric_code = QRCodeGenerator::generateNumericCode($employee_id, $this->conn);

            $qr_data = json_encode([
                'type'      => 'employee_badge',
                'id'        => (int) $employee_id,
                'code'      => $numeric_code,
                'nom'       => trim($data['nom']),
                'prenom'    => trim($data['prenom']),
                'email'     => $data['email'],
                'poste_id'  => $data['poste_id'] ?? null,
                'timestamp' => time(),
                'version'   => '1.0',
            ], JSON_UNESCAPED_UNICODE);

            $qr_filename = QRCodeGenerator::generateQRCode($employee_id, $numeric_code, $qr_data);

            $stmt = $this->conn->prepare("UPDATE employes SET qr_code = ?, qr_data = ?, code_numerique = ? WHERE id = ?");
            $stmt->execute([$qr_filename, $qr_data, $numeric_code, $employee_id]);

            return $numeric_code;
        }

        private function logActivity(string $action, string $table, int $record_id, array $details): void
        {
            $stmt = $this->conn->prepare("
            INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details)
            VALUES (?, ?, ?, ?)
        ");
            $stmt->execute([$action, $table, $record_id, json_encode($details)]);
        }
    }

    class QRCodeGenerator
    {
        public static function generateNumericCode(int $employee_id, PDO $conn): string
        {
            $max_attempts = 20;
            $attempt      = 0;

            do {
                // Générer un nombre aléatoire de 5 chiffres (10000 à 99999)
                $numeric_code = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);

                // Vérifier l'unicité
                $stmt = $conn->prepare("SELECT id FROM employes WHERE code_numerique = ?");
                $stmt->execute([$numeric_code]);

                if (! $stmt->fetch()) {
                    return $numeric_code;
                }

                $attempt++;
                // Attendre entre les tentatives pour éviter les collisions
                if ($attempt < $max_attempts) {
                    usleep(100000); // 100ms
                }

            } while ($attempt < $max_attempts);

            // Fallback si échec
            throw new Exception('Impossible de générer un code numérique unique de 5 chiffres après ' . $max_attempts . ' tentatives');
        }

        public static function generateQRCode(int $employee_id, string $numeric_code, string $qr_data): string
        {
            $qr_dir = 'qrcodes/';
            if (! is_dir($qr_dir)) {
                mkdir($qr_dir, 0755, true);
            }

            $qr_filename = 'employee_' . $employee_id . '_' . $numeric_code . '.png';
            $qr_path     = $qr_dir . $qr_filename;

            try {
                QRcode::png($qr_data, $qr_path, QR_ECLEVEL_H, 10, 2);

                if (extension_loaded('gd')) {
                    self::optimizeQRImage($qr_path);
                }

            } catch (Exception $e) {
                error_log("Erreur génération QR: " . $e->getMessage());
                QRcode::png($numeric_code, $qr_path, QR_ECLEVEL_M, 8, 2);
            }

            return $qr_filename;
        }

        private static function optimizeQRImage(string $qr_path): void
        {
            try {
                $source = imagecreatefrompng($qr_path);
                if (! $source) {
                    return;
                }

                $width       = imagesx($source);
                $height      = imagesy($source);
                $target_size = 400;

                $new_image = imagecreatetruecolor($target_size, $target_size);
                $white     = imagecolorallocate($new_image, 255, 255, 255);
                imagefill($new_image, 0, 0, $white);

                imagecopyresampled(
                    $new_image, $source,
                    0, 0, 0, 0,
                    $target_size, $target_size,
                    $width, $height
                );

                imagesavealpha($new_image, true);
                imagepng($new_image, $qr_path, 0);

                imagedestroy($source);
                imagedestroy($new_image);

            } catch (Exception $e) {
                error_log("Erreur optimisation QR: " . $e->getMessage());
            }
        }
    }
    class DepartementManager
    {
        private $conn;

        public function __construct(PDO $connection)
        {
            $this->conn = $connection;
        }
        public function getAllDepartements(): array
        {
            try {
                $stmt = $this->conn->query("
            SELECT d.*,
                COUNT(DISTINCT p.id) as nombre_postes,
                COUNT(DISTINCT e.id) as nombre_employes
            FROM departements d
            LEFT JOIN postes p ON d.id = p.departement_id AND p.actif = 1
            LEFT JOIN employes e ON p.id = e.poste_id AND e.statut = 'actif'
            WHERE d.actif = 1
            GROUP BY d.id
            ORDER BY d.nom
        ");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Erreur SQL getAllDepartements: " . $e->getMessage());
                return [];
            }
        }
    }
    class ReportingManager
    {
        private $conn;

        public function __construct(PDO $connection)
        {
            $this->conn = $connection;
        }

private function getTotalEmployesSecure(): int
{
    try {
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int) $result['total'] : 0;
    } catch (PDOException $e) {
        error_log("Erreur getTotalEmployesSecure: " . $e->getMessage());
        return 0;
    }
}

private function getNouveauxEmployesMoisSecure(): int
{
    try {
        $stmt = $this->conn->query("
            SELECT COUNT(*) as total 
            FROM employes 
            WHERE DATE_FORMAT(date_embauche, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') 
            AND statut = 'actif'
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int) $result['total'] : 0;
    } catch (PDOException $e) {
        error_log("Erreur getNouveauxEmployesMoisSecure: " . $e->getMessage());
        return 0;
    }
}

private function getTauxPresenceMoisSecure(): float
{
    try {
        $stmt = $this->conn->query("
            SELECT
                COUNT(DISTINCT e.id) as total_employes,
                COUNT(DISTINCT p.employe_id) as employes_presents
            FROM employes e
            LEFT JOIN presences p ON e.id = p.employe_id
                AND DATE_FORMAT(p.heure_arrivee, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
            WHERE e.statut = 'actif'
        ");

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['total_employes'] > 0) {
            return round(($result['employes_presents'] / $result['total_employes']) * 100, 2);
        }

        return 0.0;
    } catch (PDOException $e) {
        error_log("Erreur getTauxPresenceMoisSecure: " . $e->getMessage());
        return 0.0;
    }
}

private function getMoyenneRetardsSecure(): float
{
    try {
        $stmt = $this->conn->query("
            SELECT AVG(retard_minutes) as moyenne_retards
            FROM (
                SELECT TIMESTAMPDIFF(MINUTE, CONCAT(DATE(p.heure_arrivee), ' ', e.heure_debut), p.heure_arrivee) as retard_minutes
                FROM presences p
                INNER JOIN employes e ON p.employe_id = e.id
                WHERE DATE_FORMAT(p.heure_arrivee, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
                AND TIMESTAMPDIFF(MINUTE, CONCAT(DATE(p.heure_arrivee), ' ', e.heure_debut), p.heure_arrivee) > 0
            ) as retards
        ");

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['moyenne_retards'] ? round((float) $result['moyenne_retards'], 2) : 0.0;
    } catch (PDOException $e) {
        error_log("Erreur getMoyenneRetardsSecure: " . $e->getMessage());
        return 0.0;
    }
}

private function getMasseSalarialeMensuelleSecure(): float
{
    try {
        $stmt = $this->conn->query("
            SELECT COALESCE(SUM(COALESCE(e.salaire, p.salaire, 0)), 0) as masse_salariale
            FROM employes e
            LEFT JOIN postes p ON e.poste_id = p.id
            WHERE e.statut = 'actif'
        ");

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($result['masse_salariale'] ?? 0);
    } catch (PDOException $e) {
        error_log("Erreur getMasseSalarialeMensuelleSecure: " . $e->getMessage());
        return 0.0;
    }
}

private function getSalaireMoyenSecure(): float
{
    try {
        $stmt = $this->conn->query("
            SELECT COALESCE(AVG(COALESCE(e.salaire, p.salaire, 0)), 0) as salaire_moyen
            FROM employes e
            LEFT JOIN postes p ON e.poste_id = p.id
            WHERE e.statut = 'actif' 
            AND (e.salaire > 0 OR p.salaire > 0)
        ");

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return round((float) ($result['salaire_moyen'] ?? 0), 2);
    } catch (PDOException $e) {
        error_log("Erreur getSalaireMoyenSecure: " . $e->getMessage());
        return 0.0;
    }
}

private function getDefaultStats(): array
{
    return [
        'total_employes'         => 0,
        'employes_actifs'        => 0,
        'employes_inactifs'      => 0,
        'nouveaux_employes_mois' => 0,
        'taux_presence'          => 0.0,
        'moyenne_retards'        => 0.0,
        'masse_salariale_mensuelle' => 0.0,
        'salaire_moyen'             => 0.0,
        'repartition_departements'  => [],
        'repartition_contrats'      => [],
    ];
}
    public function getDashboardStats(): array
{
    $stats = [];
    
    try {
        // Statistiques de base - avec gestion d'erreur améliorée
        $stats['total_employes']         = $this->getTotalEmployesSecure();
        $stats['employes_actifs']        = $this->getEmployesActifs();
        $stats['employes_inactifs']      = $this->getEmployesInactifs();
        $stats['nouveaux_employes_mois'] = $this->getNouveauxEmployesMoisSecure();

        // Statistiques de présence - avec gestion d'erreur
        $stats['taux_presence']   = $this->getTauxPresenceMoisSecure();
        $stats['moyenne_retards'] = $this->getMoyenneRetardsSecure();

        // Statistiques financières - avec valeurs par défaut
        $stats['masse_salariale_mensuelle'] = $this->getMasseSalarialeMensuelleSecure();
        $stats['salaire_moyen']             = $this->getSalaireMoyenSecure();

        // Répartitions - avec gestion d'erreur
        $stats['repartition_departements'] = $this->getRepartitionParDepartement();
        $stats['repartition_contrats'] = $this->getRepartitionContrats();

        return $stats;
        
    } catch (PDOException $e) {
        error_log("Erreur SQL getDashboardStats: " . $e->getMessage());
        // Retourner des valeurs par défaut plutôt que d'échouer
        return $this->getDefaultStats();
    } catch (Exception $e) {
        error_log("Erreur générale getDashboardStats: " . $e->getMessage());
        return $this->getDefaultStats();
    }
}

       public function generateCustomReport(string $type, array $filters = []): array
{
    try {
        switch ($type) {
            case 'presences':
                return $this->generatePresenceReport($filters);
            case 'salaires':
                return $this->generateSalaryReport($filters);
            case 'effectifs':
                return $this->generateWorkforceReport($filters);
            case 'turnover':
                return $this->generateTurnoverReport($filters);
            default:
                throw new Exception('Type de rapport non supporté: ' . $type);
        }
    } catch (Exception $e) {
        error_log("Erreur generateCustomReport: " . $e->getMessage());
        return [];
    }
}

        private function getTotalEmployes(): int
        {
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes");
            return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }

        private function getEmployesActifs(): int
        {
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes WHERE statut = 'actif'");
            return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }

        private function getEmployesInactifs(): int
        {
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes WHERE statut = 'inactif'");
            return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }

        private function getNouveauxEmployesMois(): int
        {
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM employes WHERE DATE_FORMAT(date_embauche, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') AND statut = 'actif'");
            return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        }

        private function getTauxPresenceMois(): float
        {
            $stmt = $this->conn->query("
            SELECT
                COUNT(DISTINCT e.id) as total_employes,
                COUNT(DISTINCT p.employe_id) as employes_presents
            FROM employes e
            LEFT JOIN presences p ON e.id = p.employe_id
                AND DATE_FORMAT(p.heure_arrivee, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
            WHERE e.statut = 'actif'
        ");

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['total_employes'] > 0) {
                return round(($result['employes_presents'] / $result['total_employes']) * 100, 2);
            }

            return 0;
        }

        private function getMoyenneRetards(): float
        {
            $stmt = $this->conn->query("
            SELECT AVG(retard_minutes) as moyenne_retards
            FROM (
                SELECT TIMESTAMPDIFF(MINUTE, CONCAT(DATE(p.heure_arrivee), ' ', e.heure_debut), p.heure_arrivee) as retard_minutes
                FROM presences p
                INNER JOIN employes e ON p.employe_id = e.id
                WHERE DATE_FORMAT(p.heure_arrivee, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
                AND TIMESTAMPDIFF(MINUTE, CONCAT(DATE(p.heure_arrivee), ' ', e.heure_debut), p.heure_arrivee) > 0
            ) as retards
        ");

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? round((float) $result['moyenne_retards'], 2) : 0;
        }

        private function getMasseSalarialeMensuelle(): float
        {
            $stmt = $this->conn->query("
            SELECT COALESCE(SUM(e.salaire), 0) as masse_salariale
            FROM employes e
            WHERE e.statut = 'actif'
        ");

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (float) $result['masse_salariale'];
        }

        private function getSalaireMoyen(): float
        {
            $stmt = $this->conn->query("
            SELECT COALESCE(AVG(e.salaire), 0) as salaire_moyen
            FROM employes e
            WHERE e.statut = 'actif' AND e.salaire > 0
        ");

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return round((float) $result['salaire_moyen'], 2);
        }

        private function getRepartitionParDepartement(): array
        {
            $stmt = $this->conn->query("
            SELECT
                d.nom,
                COUNT(e.id) as count
            FROM employes e
            LEFT JOIN postes p ON e.poste_id = p.id
            LEFT JOIN departements d ON p.departement_id = d.id
            WHERE e.statut = 'actif'
            GROUP BY d.id, d.nom
            ORDER BY d.nom
        ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        private function getRepartitionContrats(): array
        {
            $stmt = $this->conn->query("
            SELECT
                p.type_contrat,
                COUNT(e.id) as count
            FROM employes e
            LEFT JOIN postes p ON e.poste_id = p.id
            WHERE e.statut = 'actif'
            GROUP BY p.type_contrat
            ORDER BY p.type_contrat
        ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        private function generatePresenceReport(array $filters): array
        {
            $query = "
            SELECT
                e.nom,
                e.prenom,
                d.nom as departement,
                COUNT(p.id) as jours_presence,
                SUM(CASE WHEN TIME(p.heure_arrivee) > e.heure_debut THEN 1 ELSE 0 END) as jours_retard,
                AVG(TIMESTAMPDIFF(MINUTE, CONCAT(DATE(p.heure_arrivee), ' ', e.heure_debut), p.heure_arrivee)) as retard_moyen_minutes
            FROM employes e
            LEFT JOIN presences p ON e.id = p.employe_id
            LEFT JOIN postes po ON e.poste_id = po.id
            LEFT JOIN departements d ON po.departement_id = d.id
            WHERE e.statut = 'actif'
        ";

            $params = [];

            if (! empty($filters['date_debut'])) {
                $query .= " AND DATE(p.heure_arrivee) >= ?";
                $params[] = $filters['date_debut'];
            }

            if (! empty($filters['date_fin'])) {
                $query .= " AND DATE(p.heure_arrivee) <= ?";
                $params[] = $filters['date_fin'];
            }

            if (! empty($filters['departement_id'])) {
                $query .= " AND d.id = ?";
                $params[] = $filters['departement_id'];
            }

            $query .= " GROUP BY e.id ORDER BY e.nom, e.prenom";

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
private function generateSalaryReport(array $filters): array
{
    $query = "
        SELECT
            e.id,
            e.nom,
            e.prenom,
            d.nom as departement,
            p.nom as poste,
            p.type_contrat,
            COALESCE(e.salaire, p.salaire, 0) as salaire_brut,
            COALESCE(p.taux_cotisation, 0) as taux_cotisation,
            COALESCE((e.salaire * p.taux_cotisation / 100), (p.salaire * p.taux_cotisation / 100), 0) as cotisations,
            COALESCE(
                (e.salaire - (e.salaire * p.taux_cotisation / 100)), 
                (p.salaire - (p.salaire * p.taux_cotisation / 100)), 
                0
            ) as salaire_net,
            e.date_embauche,
            e.statut,
            COUNT(DISTINCT bp.id) as bulletins_generes,
            MAX(bp.mois_annee) as dernier_bulletin
        FROM employes e
        LEFT JOIN postes p ON e.poste_id = p.id
        LEFT JOIN departements d ON p.departement_id = d.id
        LEFT JOIN bulletins_paie bp ON e.id = bp.employe_id
        WHERE e.statut = 'actif'
    ";

    $params = [];

    if (!empty($filters['date_debut'])) {
        $query .= " AND bp.mois_annee >= ?";
        $params[] = $filters['date_debut'] . '-01';
    }

    if (!empty($filters['date_fin'])) {
        $query .= " AND bp.mois_annee <= ?";
        $params[] = $filters['date_fin'] . '-01';
    }

    if (!empty($filters['departement_id'])) {
        $query .= " AND d.id = ?";
        $params[] = $filters['departement_id'];
    }

    $query .= " GROUP BY e.id ORDER BY d.nom, e.nom, e.prenom";

    try {
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Si aucune donnée de bulletin, on récupère quand même les employés actifs
        if (empty($results)) {
            $querySimple = "
                SELECT
                    e.id,
                    e.nom,
                    e.prenom,
                    d.nom as departement,
                    p.nom as poste,
                    p.type_contrat,
                    COALESCE(e.salaire, p.salaire, 0) as salaire_brut,
                    COALESCE(p.taux_cotisation, 0) as taux_cotisation,
                    COALESCE((e.salaire * p.taux_cotisation / 100), (p.salaire * p.taux_cotisation / 100), 0) as cotisations,
                    COALESCE(
                        (e.salaire - (e.salaire * p.taux_cotisation / 100)), 
                        (p.salaire - (p.salaire * p.taux_cotisation / 100)), 
                        0
                    ) as salaire_net,
                    e.date_embauche,
                    e.statut,
                    0 as bulletins_generes,
                    NULL as dernier_bulletin
                FROM employes e
                LEFT JOIN postes p ON e.poste_id = p.id
                LEFT JOIN departements d ON p.departement_id = d.id
                WHERE e.statut = 'actif'
            ";
            
            $paramsSimple = [];
            
            if (!empty($filters['departement_id'])) {
                $querySimple .= " AND d.id = ?";
                $paramsSimple[] = $filters['departement_id'];
            }
            
            $querySimple .= " ORDER BY d.nom, e.nom, e.prenom";
            
            $stmt = $this->conn->prepare($querySimple);
            $stmt->execute($paramsSimple);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $results;
        
    } catch (PDOException $e) {
        error_log("Erreur generateSalaryReport: " . $e->getMessage());
        return [];
    }
}


private function generateWorkforceReport(array $filters): array
{
    $query = "
        SELECT
            d.nom as departement,
            p.type_contrat,
            COUNT(e.id) as nombre_employes,
            AVG(COALESCE(e.salaire, p.salaire, 0)) as salaire_moyen,
            MIN(e.date_embauche) as plus_ancien,
            MAX(e.date_embauche) as plus_recent,
            COUNT(CASE WHEN e.sexe = 'M' THEN 1 END) as hommes,
            COUNT(CASE WHEN e.sexe = 'F' THEN 1 END) as femmes,
            AVG(
                CASE 
                    WHEN e.date_naissance IS NOT NULL 
                    THEN TIMESTAMPDIFF(YEAR, e.date_naissance, CURDATE())
                    ELSE NULL 
                END
            ) as age_moyen
        FROM employes e
        LEFT JOIN postes p ON e.poste_id = p.id
        LEFT JOIN departements d ON p.departement_id = d.id
        WHERE e.statut = 'actif'
    ";

    $params = [];

    if (!empty($filters['date_debut'])) {
        $query .= " AND e.date_embauche >= ?";
        $params[] = $filters['date_debut'];
    }

    if (!empty($filters['date_fin'])) {
        $query .= " AND e.date_embauche <= ?";
        $params[] = $filters['date_fin'];
    }

    if (!empty($filters['departement_id'])) {
        $query .= " AND d.id = ?";
        $params[] = $filters['departement_id'];
    }

    $query .= " GROUP BY d.id, d.nom, p.type_contrat 
                HAVING COUNT(e.id) > 0
                ORDER BY d.nom, p.type_contrat";

    try {
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur generateWorkforceReport: " . $e->getMessage());
        return [];
    }
}

     private function generateTurnoverReport(array $filters): array
{
    $query = "
        SELECT
            d.nom as departement,
            COUNT(CASE WHEN e.statut = 'actif' THEN 1 END) as effectif_actuel,
            COUNT(CASE WHEN e.statut = 'inactif' THEN 1 END) as departs,
            COUNT(CASE WHEN e.date_embauche BETWEEN ? AND ? THEN 1 END) as embauches_periode,
            COUNT(CASE WHEN e.statut = 'inactif' AND e.date_depart BETWEEN ? AND ? THEN 1 END) as departs_periode,
            AVG(
                CASE 
                    WHEN e.statut = 'inactif' AND e.date_depart IS NOT NULL
                    THEN TIMESTAMPDIFF(MONTH, e.date_embauche, e.date_depart)
                    WHEN e.statut = 'actif'
                    THEN TIMESTAMPDIFF(MONTH, e.date_embauche, CURDATE())
                    ELSE NULL
                END
            ) as duree_moyenne
        FROM employes e
        LEFT JOIN postes p ON e.poste_id = p.id
        LEFT JOIN departements d ON p.departement_id = d.id
        WHERE 1=1
    ";

    $params = [
        $filters['date_debut'] ?? date('Y-m-01'),
        $filters['date_fin'] ?? date('Y-m-t'),
        $filters['date_debut'] ?? date('Y-m-01'),
        $filters['date_fin'] ?? date('Y-m-t'),
    ];

    if (!empty($filters['departement_id'])) {
        $query .= " AND d.id = ?";
        $params[] = $filters['departement_id'];
    }

    $query .= " GROUP BY d.id, d.nom ORDER BY d.nom";

    try {
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur generateTurnoverReport: " . $e->getMessage());
        return [];
    }
}
    }
    class PosteManager
    {
        private $conn;

        public function __construct(PDO $connection)
        {
            $this->conn = $connection;
        }

        public function getAllPostes(): array
        {
            $stmt = $this->conn->query("
            SELECT p.*,
                   ps.nom as poste_superieur_nom
                    FROM postes p
                    LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
                    WHERE p.actif = 1
                    ORDER BY p.niveau_hierarchique ASC, p.nom
        ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function getPosteById(int $id): ?array
        {
            $stmt = $this->conn->prepare("
            SELECT p.*,
                   ps.nom as poste_superieur_nom
            FROM postes p
            LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
            WHERE p.id = ? AND p.actif = 1
        ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        }

        public function getTypesContrat(): array
        {
            return [
                'CDI'           => 'Contrat à Durée Indéterminée',
                'CDD'           => 'Contrat à Durée Déterminée',
                'Stage'         => 'Stage',
                'Apprentissage' => 'Contrat d\'Apprentissage',
                'Freelance'     => 'Freelance/Consultant',
                'Temps_partiel' => 'Temps Partiel',
            ];
        }
    }

    class APIHandler
    {
        private $payrollCalculator;
        private $employeeManager;
        private $posteManager;
        private $reportingManager;
        private $conn;

        public function __construct(PDO $conn)
        {
            $this->conn             = $conn;
            $this->employeeManager  = new EmployeeManager($conn);
            $this->posteManager     = new PosteManager($conn);
            $this->reportingManager = new ReportingManager($conn);
            $this->payrollCalculator = new PayrollCalculator($conn);
            //$this->pdfGenerator = new BulletinPDFGenerateur($conn);
        }

        private function getEmployeesWithPresence(): void
        {
            try {
                $employees = $this->employeeManager->getAllEmployeesWithPresenceStatus();
                echo json_encode(['success' => true, 'employees' => $employees]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des employés avec présence']);
            }
        }

        private function getDepartements(): void
        {
            try {
                $departementManager = new DepartementManager($this->conn);
                $departements       = $departementManager->getAllDepartements();
                echo json_encode(['success' => true, 'departements' => $departements]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des départements']);
            }
        }

        private function reactivateEmployee(): void
        {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['id'])) {
                echo json_encode(['success' => false, 'message' => 'ID employé requis']);
                return;
            }

            try {
                $result = $this->employeeManager->reactivateEmployee($input['id']);
                echo json_encode($result);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la réactivation: ' . $e->getMessage()]);
            }
        }

        private function permanentDeleteEmployee(): void
        {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['id'])) {
                echo json_encode(['success' => false, 'message' => 'ID employé requis']);
                return;
            }

            $result = $this->employeeManager->permanentDeleteEmployee($input['id']);
            echo json_encode($result);
        }

        private function getDashboardStats(): void
        {
            try {
                $stats = $this->reportingManager->getDashboardStats();
                echo json_encode(['success' => true, 'stats' => $stats]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        }

        private function generateReport(): void
        {
            try {
                $input   = json_decode(file_get_contents('php://input'), true);
                $type    = $input['type'] ?? '';
                $filters = $input['filters'] ?? [];

                $report = $this->reportingManager->generateCustomReport($type, $filters);
                echo json_encode(['success' => true, 'report' => $report]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        }
  public function handleRequest(): void
{
    // TOUJOURS définir le header JSON en premier
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? $_POST['ajax_action'] ?? '';

    // Log de l'action demandée pour debug
    error_log("Action demandée: " . $action);

    try {
        switch ($action) {
            case 'get_employees':
                $this->getEmployees();
                break;
            case 'get_statistics':
                $this->getStatistics();
                break;
            case 'get_postes':
                $this->getPostes();
                break;
            case 'get_poste_details':
                $this->getPosteDetails();
                break;
            case 'add_employee':
                $this->addEmployee();
                break;
            case 'update_employee':
                $this->updateEmployee();
                break;
            case 'delete_employee':
                $this->deleteEmployee();
                break;
            case 'get_employees_with_presences':
                $this->getEmployeesWithPresence();
                break;
            case 'get_departements':
                $this->getDepartements();
                break;
            case 'get_dashboard_stats':
                $this->getDashboardStats();
                break;
            case 'generate_report':
                $this->generateReport();
                break;
            case 'reactivate_employee':
                $this->reactivateEmployee();
                break;
            case 'permanent_delete_employee':
                $this->permanentDeleteEmployee();
                break;
            case 'get_today_attendance':
                $this->getTodayAttendance();
                break;
            default:
                echo json_encode([
                    'success' => false, 
                    'message' => 'Action non reconnue: ' . $action
                ]);
        }
    } catch (Exception $e) {
        error_log("Erreur dans handleRequest pour action $action: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Erreur serveur: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

        private function getTodayAttendance(): void
        {
            try {
                $attendance = $this->employeeManager->getTodayAttendance();
                echo json_encode(['success' => true, 'attendance' => $attendance]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des présences']);
            }
        }

        // Modifiez également votre méthode getEmployees pour une meilleure gestion d'erreur
        private function getEmployees(): void
        {
            try {
                $employees = $this->employeeManager->getAllEmployees();

                // Debug: vérifiez ce qui est retourné
                error_log("Nombre d'employés récupérés: " . count($employees));

                echo json_encode([
                    'success'   => true,
                    'employees' => $employees,
                    'count'     => count($employees),
                ]);
            } catch (Exception $e) {
                error_log("Erreur getEmployees: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors du chargement des employés: ' . $e->getMessage(),
                ]);
            }
        }
        private function getStatistics(): void
        {
            try {
                $statistics = $this->employeeManager->getStatistics();
                echo json_encode(['success' => true, 'statistics' => $statistics]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des statistiques']);
            }
        }

        private function getPostes(): void
        {
            try {
                $postes = $this->posteManager->getAllPostes();
                echo json_encode(['success' => true, 'postes' => $postes]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement des postes']);
            }
        }

        private function getPosteDetails(): void
        {
            try {
                $poste_id = $_GET['id'] ?? null;
                if (! $poste_id) {
                    echo json_encode(['success' => false, 'message' => 'ID poste requis']);
                    return;
                }

                $poste = $this->posteManager->getPosteById($poste_id);
                if (! $poste) {
                    echo json_encode(['success' => false, 'message' => 'Poste non trouvé']);
                    return;
                }

                echo json_encode(['success' => true, 'poste' => $poste]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors du chargement du poste']);
            }
        }

        private function addEmployee(): void
        {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                return;
            }

            $result = $this->employeeManager->addEmployee($_POST);
            echo json_encode($result);
        }

        private function updateEmployee(): void
        {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                return;
            }

            $result = $this->employeeManager->updateEmployee($_POST);
            echo json_encode($result);
        }

        private function deleteEmployee(): void
        {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['id'])) {
                echo json_encode(['success' => false, 'message' => 'ID employé requis']);
                return;
            }

            $result = $this->employeeManager->deactivateEmployee($input['id']);
            echo json_encode($result);
        }

public function verifierPrerequisPaie(int $employe_id): array
{
    try {
        $stmt = $this->conn->prepare("
            SELECT e.nom, e.prenom, e.statut, e.salaire,
                p.salaire as salaire_poste, p.taux_cotisation,
                e.iban, e.titulaire_compte
            FROM employes e
            LEFT JOIN postes p ON e.poste_id = p.id
            WHERE e.id = ?
        ");
        $stmt->execute([$employe_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            return ['valid' => false, 'errors' => ['Employé non trouvé']];
        }

        $errors = [];

        if ($employee['statut'] !== 'actif') {
            $errors[] = 'L\'employé n\'est pas actif';
        }

        $salaire = $employee['salaire'] ?: $employee['salaire_poste'];
        if (!$salaire || $salaire <= 0) {
            $errors[] = 'Aucun salaire défini pour cet employé';
        }

        if (!$employee['iban']) {
            $errors[] = 'IBAN manquant pour le virement';
        }

        if (!$employee['titulaire_compte']) {
            $errors[] = 'Nom du titulaire du compte manquant';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'employee' => $employee
        ];

    } catch (PDOException $e) {
        error_log("Erreur verifierPrerequisPaie: " . $e->getMessage());
        return ['valid' => false, 'errors' => ['Erreur de base de données']];
    }
}
}
   $apiHandler = new APIHandler($conn);
if (isset($_GET['action']) || isset($_POST['ajax_action'])) {
    // Nettoyer le buffer de sortie si nécessaire
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    $apiHandler->handleRequest();
    exit; // Important pour éviter l'affichage du HTML après une requête API
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>restaurant Mulho</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->
    <link rel="stylesheet" href="employe.css">
    <style>
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Contenu Principal -->
        <div class="flex-1 overflow-y-auto">
            <!-- Header avec bouton d'ajout -->
            <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-40">
                <div class="px-4 sm:px-6 lg:px-8 py-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">
                                <i class="fas fa-users mr-3 text-green-600"></i>
                                Gestion des Employés
                            </h1>
                            <p class="text-gray-600 mt-2">Gérez votre équipe et les informations RH</p>
                        </div>
                        <button onclick="openAddModal()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition duration-200 shadow-sm hover:shadow-md">
                            <i class="fas fa-plus mr-2"></i>Ajouter Employé
                        </button>
                    </div>
                </div>
            </header>

            <!-- Statistiques Dashboard -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
            <!-- Employés Actifs -->
            <div class="stat-card">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Employés Actifs</p>
                        <p class="text-2xl font-bold text-gray-800" id="totalActifs">0</p>
                    </div>
                </div>
            </div>

            <!-- Présents Aujourd'hui -->
            <div class="stat-card">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Présents</p>
                        <p class="text-2xl font-bold text-gray-800" id="presentsAujourdhui">0</p>
                    </div>
                </div>
            </div>

            <!-- Absents -->
            <div class="stat-card">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-times-circle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Absents</p>
                        <p class="text-2xl font-bold text-gray-800" id="absentsAujourdhui">0</p>
                    </div>
                </div>
            </div>

            <!-- En Retard -->
            <div class="stat-card">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-orange-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">En Retard</p>
                        <p class="text-2xl font-bold text-gray-800" id="retardsAujourdhui">0</p>
                    </div>
                </div>
            </div>

            <!-- Inactifs -->
            <div class="stat-card">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-slash text-gray-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Inactifs</p>
                        <p class="text-2xl font-bold text-gray-800" id="totalInactifs">0</p>
                    </div>
                </div>
            </div>

            <!-- Administrateurs -->
            <div class="stat-card">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-crown text-amber-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Admins</p>
                        <p class="text-2xl font-bold text-gray-800" id="totalAdmins">0</p>
                    </div>
                </div>
            </div>
        </div>
<div class="card p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <!-- Champ de recherche -->
        <div>
            <input type="text" id="searchInput" placeholder="Rechercher par nom, email..."
                class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-800 placeholder-gray-400 focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>

        <!-- Filtre département -->
        <div>
            <select id="filterDepartement" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-800 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Tous les départements</option>
            </select>
        </div>

        <!-- Filtre poste -->
        <div>
            <select id="filterPoste" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-800 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Tous les postes</option>
            </select>
        </div>

        <!-- Filtre contrat -->
        <div>
            <select id="filterContrat" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-800 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Tous les contrats</option>
                <option value="CDI">CDI</option>
                <option value="CDD">CDD</option>
                <option value="Stage">Stage</option>
                <option value="Apprentissage">Apprentissage</option>
                <option value="Freelance">Freelance</option>
                <option value="Temps_partiel">Temps Partiel</option>
            </select>
        </div>

        <!-- Filtre statut -->
        <div>
            <select id="filterStatut" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-800 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Tous les statuts</option>
                <option value="actif">Actif</option>
                <option value="en_conge">En congé</option>
                <option value="absent">Absent</option>
                <option value="inactif">Inactif</option>
            </select>
        </div>

        <!-- NOUVEAU: Boutons d'action -->
        <div class="flex space-x-2">
            <button onclick="applyFilters()" class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-all duration-200 shadow-sm">
                <i class="fas fa-search mr-2"></i>Filtrer
            </button>
            <button onclick="resetFilters()" class="px-3 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-all duration-200 shadow-sm" title="Réinitialiser">
                <i class="fas fa-undo"></i>
            </button>
        </div>
    </div>
</div>

    <div class="card p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4 text-gray-800">Génération des Bulletins de Paie</h2>
    <p class="text-gray-600 mb-4">Gérez la paie, primes, congés et générez les bulletins professionnels.</p>
    <a href="gestion_paie.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-all duration-200 shadow-sm inline-flex items-center">
        <i class="fas fa-calculator mr-2"></i>Accéder à la Gestion de Paie
    </a>
</div>

<!-- 6. MODIFICATION DE LA SECTION TABLEAU POUR AJOUTER LES BORDURES -->
<div id="tableView" class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Photo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Employé</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-700 uppercase tracking-wider">Département</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Poste</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Contrat</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Présence</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Salaire</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Heures/Mois</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Documents</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="employeesTableBody" class="divide-y divide-gray-200 bg-white">
                <!-- Les employés seront chargés ici -->
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div id="paginationContainer" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-4 rounded-lg shadow">
        <div class="flex-1 flex justify-between sm:hidden">
            <button onclick="changePage(currentPage - 1)" id="prevPageMobile" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Précédent
            </button>
            <button onclick="changePage(currentPage + 1)" id="nextPageMobile" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Suivant
            </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    Affichage de
                    <span class="font-medium" id="startRange">1</span>
                    à
                    <span class="font-medium" id="endRange">10</span>
                    sur
                    <span class="font-medium" id="totalEmployees">0</span>
                    employés
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" id="paginationNumbers">
                    <!-- Les numéros de page seront générés ici -->
                </nav>
            </div>
        </div>
    </div>
</div>

    </div>

    <!-- Modal Ajouter/Modifier Employé -->
    <div id="employeeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 transition-opacity duration-300" onclick="closeModalOnBackdrop(event)">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl transform transition-all duration-300" onclick="event.stopPropagation()">
                <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-green-600 to-emerald-600">
                    <div class="flex items-center justify-between">
                        <h3 id="modalTitle" class="text-xl font-bold text-white">
                            <i class="fas fa-user-plus mr-2"></i>
                            Ajouter un employé
                        </h3>
                        <button type="button" onclick="closeModal()" class="text-white hover:text-gray-200 transition-colors duration-200">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                </div>

                <form id="employeeForm" class="p-6" enctype="multipart/form-data">
                    <input type="hidden" id="employeeId" name="id">
                    <input type="hidden" name="ajax_action" id="ajaxAction" value="add_employee">

                    <!-- Photo de profil -->
                    <div class="mb-6 text-center">
                        <div class="relative inline-block">
                            <img id="photoPreview" src="uploads/photos/default-avatar.png"
                                class="w-32 h-32 rounded-full border-4 border-green-200 object-cover shadow-lg">
                            <label for="photo" class="absolute bottom-0 right-0 bg-green-600 text-white rounded-full p-3 cursor-pointer hover:bg-green-700 shadow-md transition-all duration-200 hover:scale-110">
                                <i class="fas fa-camera text-sm"></i>
                                <input type="file" id="photo" name="photo" accept="image/*" class="hidden" onchange="previewPhoto(event)">
                            </label>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">Cliquez sur l'icône pour ajouter une photo</p>
                    </div>

                    <!-- Informations personnelles -->
                    <div class="mb-6">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user text-green-600"></i>
                            </div>
                            <h4 class="ml-3 text-lg font-semibold text-gray-800">Informations personnelles</h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg">
                            <div>
                                <label for="nom" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-user mr-1 text-gray-400"></i>Nom *
                                </label>
                                <input type="text" id="nom" name="nom" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                            </div>

                            <div>
                                <label for="prenom" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-user mr-1 text-gray-400"></i>Prénom *
                                </label>
                                <input type="text" id="prenom" name="prenom" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-envelope mr-1 text-gray-400"></i>Email *
                                </label>
                                <input type="email" id="email" name="email" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                            </div>

                            <div>
                                <label for="telephone" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-phone mr-1 text-gray-400"></i>Téléphone
                                </label>
                                <input type="tel" id="telephone" name="telephone"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all duration-200">
                            </div>
                        </div>
                    </div>
                    <!-- Dans la section "Informations personnelles" -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <!-- Ajouter ces nouveaux champs -->
    <div>
        <label for="date_naissance" class="block text-sm font-medium text-gray-700 mb-2">Date de naissance</label>
        <input type="date" id="date_naissance" name="date_naissance"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
    </div>

    <div>
        <label for="lieu_naissance" class="block text-sm font-medium text-gray-700 mb-2">Lieu de naissance</label>
        <input type="text" id="lieu_naissance" name="lieu_naissance"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
    </div>

    <div>
        <label for="nationalite" class="block text-sm font-medium text-gray-700 mb-2">Nationalité</label>
        <select id="nationalite" name="nationalite"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-green-500">
            <option value="">Sélectionner un pays</option>
            <option value="Afghanistan">Afghanistan</option>
            <option value="Afrique du Sud">Afrique du Sud</option>
            <option value="Albanie">Albanie</option>
            <option value="Algérie">Algérie</option>
            <option value="Allemagne">Allemagne</option>
            <option value="Andorre">Andorre</option>
            <option value="Angola">Angola</option>
            <option value="Arabie Saoudite">Arabie Saoudite</option>
            <option value="Argentine">Argentine</option>
            <option value="Arménie">Arménie</option>
            <option value="Australie">Australie</option>
            <option value="Autriche">Autriche</option>
            <option value="Azerbaïdjan">Azerbaïdjan</option>
            <option value="Bahamas">Bahamas</option>
            <option value="Bahreïn">Bahreïn</option>
            <option value="Bangladesh">Bangladesh</option>
            <option value="Barbade">Barbade</option>
            <option value="Belgique">Belgique</option>
            <option value="Belize">Belize</option>
            <option value="Bénin">Bénin</option>
            <option value="Bhoutan">Bhoutan</option>
            <option value="Biélorussie">Biélorussie</option>
            <option value="Birmanie">Birmanie</option>
            <option value="Bolivie">Bolivie</option>
            <option value="Bosnie-Herzégovine">Bosnie-Herzégovine</option>
            <option value="Botswana">Botswana</option>
            <option value="Brésil">Brésil</option>
            <option value="Brunei">Brunei</option>
            <option value="Bulgarie">Bulgarie</option>
            <option value="Burkina Faso">Burkina Faso</option>
            <option value="Burundi">Burundi</option>
            <option value="Cambodge">Cambodge</option>
            <option value="Cameroun">Cameroun</option>
            <option value="Canada">Canada</option>
            <option value="Cap-Vert">Cap-Vert</option>
            <option value="Chili">Chili</option>
            <option value="Chine">Chine</option>
            <option value="Chypre">Chypre</option>
            <option value="Colombie">Colombie</option>
            <option value="Comores">Comores</option>
            <option value="Congo">Congo</option>
            <option value="Corée du Nord">Corée du Nord</option>
            <option value="Corée du Sud">Corée du Sud</option>
            <option value="Costa Rica">Costa Rica</option>
            <option value="Côte d'Ivoire">Côte d'Ivoire</option>
            <option value="Croatie">Croatie</option>
            <option value="Cuba">Cuba</option>
            <option value="Danemark">Danemark</option>
            <option value="Djibouti">Djibouti</option>
            <option value="Dominique">Dominique</option>
            <option value="Égypte">Égypte</option>
            <option value="Émirats Arabes Unis">Émirats Arabes Unis</option>
            <option value="Équateur">Équateur</option>
            <option value="Érythrée">Érythrée</option>
            <option value="Espagne">Espagne</option>
            <option value="Estonie">Estonie</option>
            <option value="Eswatini">Eswatini</option>
            <option value="États-Unis">États-Unis</option>
            <option value="Éthiopie">Éthiopie</option>
            <option value="Fidji">Fidji</option>
            <option value="Finlande">Finlande</option>
            <option value="France">France</option>
            <option value="Gabon">Gabon</option>
            <option value="Gambie">Gambie</option>
            <option value="Géorgie">Géorgie</option>
            <option value="Ghana">Ghana</option>
            <option value="Grèce">Grèce</option>
            <option value="Grenade">Grenade</option>
            <option value="Guatemala">Guatemala</option>
            <option value="Guinée">Guinée</option>
            <option value="Guinée équatoriale">Guinée équatoriale</option>
            <option value="Guinée-Bissau">Guinée-Bissau</option>
            <option value="Guyana">Guyana</option>
            <option value="Haïti">Haïti</option>
            <option value="Honduras">Honduras</option>
            <option value="Hongrie">Hongrie</option>
            <option value="Inde">Inde</option>
            <option value="Indonésie">Indonésie</option>
            <option value="Irak">Irak</option>
            <option value="Iran">Iran</option>
            <option value="Irlande">Irlande</option>
            <option value="Islande">Islande</option>
            <option value="Israël">Israël</option>
            <option value="Italie">Italie</option>
            <option value="Jamaïque">Jamaïque</option>
            <option value="Japon">Japon</option>
            <option value="Jordanie">Jordanie</option>
            <option value="Kazakhstan">Kazakhstan</option>
            <option value="Kenya">Kenya</option>
            <option value="Kirghizistan">Kirghizistan</option>
            <option value="Kiribati">Kiribati</option>
            <option value="Koweït">Koweït</option>
            <option value="Laos">Laos</option>
            <option value="Lesotho">Lesotho</option>
            <option value="Lettonie">Lettonie</option>
            <option value="Liban">Liban</option>
            <option value="Liberia">Liberia</option>
            <option value="Libye">Libye</option>
            <option value="Liechtenstein">Liechtenstein</option>
            <option value="Lituanie">Lituanie</option>
            <option value="Luxembourg">Luxembourg</option>
            <option value="Macédoine du Nord">Macédoine du Nord</option>
            <option value="Madagascar">Madagascar</option>
            <option value="Malaisie">Malaisie</option>
            <option value="Malawi">Malawi</option>
            <option value="Maldives">Maldives</option>
            <option value="Mali">Mali</option>
            <option value="Malte">Malte</option>
            <option value="Maroc">Maroc</option>
            <option value="Maurice">Maurice</option>
            <option value="Mauritanie">Mauritanie</option>
            <option value="Mexique">Mexique</option>
            <option value="Moldavie">Moldavie</option>
            <option value="Monaco">Monaco</option>
            <option value="Mongolie">Mongolie</option>
            <option value="Monténégro">Monténégro</option>
            <option value="Mozambique">Mozambique</option>
            <option value="Namibie">Namibie</option>
            <option value="Nauru">Nauru</option>
            <option value="Népal">Népal</option>
            <option value="Nicaragua">Nicaragua</option>
            <option value="Niger">Niger</option>
            <option value="Nigeria">Nigeria</option>
            <option value="Norvège">Norvège</option>
            <option value="Nouvelle-Zélande">Nouvelle-Zélande</option>
            <option value="Oman">Oman</option>
            <option value="Ouganda">Ouganda</option>
            <option value="Ouzbékistan">Ouzbékistan</option>
            <option value="Pakistan">Pakistan</option>
            <option value="Palaos">Palaos</option>
            <option value="Panama">Panama</option>
            <option value="Papouasie-Nouvelle-Guinée">Papouasie-Nouvelle-Guinée</option>
            <option value="Paraguay">Paraguay</option>
            <option value="Pays-Bas">Pays-Bas</option>
            <option value="Pérou">Pérou</option>
            <option value="Philippines">Philippines</option>
            <option value="Pologne">Pologne</option>
            <option value="Portugal">Portugal</option>
            <option value="Qatar">Qatar</option>
            <option value="RD Congo">RD Congo</option>
            <option value="République Centrafricaine">République Centrafricaine</option>
            <option value="République Dominicaine">République Dominicaine</option>
            <option value="République Tchèque">République Tchèque</option>
            <option value="Roumanie">Roumanie</option>
            <option value="Royaume-Uni">Royaume-Uni</option>
            <option value="Russie">Russie</option>
            <option value="Rwanda">Rwanda</option>
            <option value="Saint-Marin">Saint-Marin</option>
            <option value="Sainte-Lucie">Sainte-Lucie</option>
            <option value="Salvador">Salvador</option>
            <option value="Samoa">Samoa</option>
            <option value="Sénégal" selected>Sénégal</option>
            <option value="Serbie">Serbie</option>
            <option value="Seychelles">Seychelles</option>
            <option value="Sierra Leone">Sierra Leone</option>
            <option value="Singapour">Singapour</option>
            <option value="Slovaquie">Slovaquie</option>
            <option value="Slovénie">Slovénie</option>
            <option value="Somalie">Somalie</option>
            <option value="Soudan">Soudan</option>
            <option value="Soudan du Sud">Soudan du Sud</option>
            <option value="Sri Lanka">Sri Lanka</option>
            <option value="Suède">Suède</option>
            <option value="Suisse">Suisse</option>
            <option value="Suriname">Suriname</option>
            <option value="Syrie">Syrie</option>
            <option value="Tadjikistan">Tadjikistan</option>
            <option value="Tanzanie">Tanzanie</option>
            <option value="Tchad">Tchad</option>
            <option value="Thaïlande">Thaïlande</option>
            <option value="Timor oriental">Timor oriental</option>
            <option value="Togo">Togo</option>
            <option value="Tonga">Tonga</option>
            <option value="Trinité-et-Tobago">Trinité-et-Tobago</option>
            <option value="Tunisie">Tunisie</option>
            <option value="Turkménistan">Turkménistan</option>
            <option value="Turquie">Turquie</option>
            <option value="Tuvalu">Tuvalu</option>
            <option value="Ukraine">Ukraine</option>
            <option value="Uruguay">Uruguay</option>
            <option value="Vanuatu">Vanuatu</option>
            <option value="Vatican">Vatican</option>
            <option value="Venezuela">Venezuela</option>
            <option value="Vietnam">Vietnam</option>
            <option value="Yémen">Yémen</option>
            <option value="Zambie">Zambie</option>
            <option value="Zimbabwe">Zimbabwe</option>
        </select>
    </div>

    <div>
        <label for="sexe" class="block text-sm font-medium text-gray-700 mb-2">Sexe</label>
        <select id="sexe" name="sexe"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
            <option value="">Sélectionner</option>
            <option value="M">Masculin</option>
            <option value="F">Féminin</option>
        </select>
    </div>
</div>

<!-- Nouvelle section pour les contacts d'urgence -->
<div class="mb-6">
    <h4 class="text-md font-semibold text-gray-800 mb-3">Contact d'urgence</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="contact_urgence_nom" class="block text-sm font-medium text-gray-700 mb-2">Nom du contact</label>
            <input type="text" id="contact_urgence_nom" name="contact_urgence_nom"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label for="contact_urgence_relation" class="block text-sm font-medium text-gray-700 mb-2">Relation</label>
            <input type="text" id="contact_urgence_relation" name="contact_urgence_relation"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label for="contact_urgence_telephone" class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
            <input type="tel" id="contact_urgence_telephone" name="contact_urgence_telephone"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>
    </div>
</div>

<!-- Section adresse -->
<div class="mb-6">
    <h4 class="text-md font-semibold text-gray-800 mb-3">Adresse</h4>
    <div class="grid grid-cols-1 gap-4">
        <div>
            <label for="adresse" class="block text-sm font-medium text-gray-700 mb-2">Adresse complète</label>
            <textarea id="adresse" name="adresse" rows="2"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"></textarea>
        </div>
    </div>
</div>
<!-- Nouvelle section: Informations administratives -->
<div class="mb-6">
    <h4 class="text-md font-semibold text-gray-800 mb-3">Informations administratives</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="num_secu" class="block text-sm font-medium text-gray-700 mb-2">
                Numéro de sécurité sociale *
            </label>
            <input type="text" id="num_secu" name="num_secu" required
                pattern="[0-9]{15}" title="15 chiffres requis"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label for="num_identite" class="block text-sm font-medium text-gray-700 mb-2">
                Numéro de pièce d'identité *
            </label>
            <input type="text" id="num_identite" name="num_identite" required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label for="type_identite" class="block text-sm font-medium text-gray-700 mb-2">
                Type de pièce d'identité
            </label>
            <select id="type_identite" name="type_identite"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                <option value="CNI">Carte Nationale d'Identité</option>
                <option value="passeport">Passeport</option>
                <option value="titre_sejour">Titre de Séjour</option>
                <option value="permis_conduire">Permis de Conduire</option>
            </select>
        </div>
        <div>
            <label for="situation_familiale" class="block text-sm font-medium text-gray-700 mb-2">
                Situation familiale
            </label>
            <select id="situation_familiale" name="situation_familiale"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                <option value="celibataire">Célibataire</option>
                <option value="marie">Marié(e)</option>
                <option value="pacse">Pacsé(e)</option>
                <option value="divorce">Divorcé(e)</option>
                <option value="veuf">Veuf/Veuve</option>
            </select>
        </div>
        <div>
            <label for="nombre_enfants" class="block text-sm font-medium text-gray-700 mb-2">
                Nombre d'enfants à charge
            </label>
            <input type="number" id="nombre_enfants" name="nombre_enfants" min="0" value="0"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>
    </div>
</div>

<!-- Nouvelle section: Coordonnées bancaires -->
<div class="mb-6">
    <h4 class="text-md font-semibold text-gray-800 mb-3">Coordonnées bancaires</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label for="iban" class="block text-sm font-medium text-gray-700 mb-2">
                IBAN *
            </label>
            <input type="text" id="iban" name="iban" required
                placeholder="FR76 XXXX XXXX XXXX XXXX XXXX XXX"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label for="nom_banque" class="block text-sm font-medium text-gray-700 mb-2">
                Nom de la banque
            </label>
            <input type="text" id="nom_banque" name="nom_banque"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label for="titulaire_compte" class="block text-sm font-medium text-gray-700 mb-2">
                Titulaire du compte *
            </label>
            <input type="text" id="titulaire_compte" name="titulaire_compte" required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="md:col-span-2">
            <label for="bic" class="block text-sm font-medium text-gray-700 mb-2">
                Code BIC (optionnel)
            </label>
            <input type="text" id="bic" name="bic" placeholder="ABCDEFXX"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>
    </div>
</div>
<!-- Nouvelle section: Documents à uploader -->
<div class="mb-6">
    <h4 class="text-md font-semibold text-gray-800 mb-3">Documents à uploader</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label for="cv" class="block text-sm font-medium text-gray-700 mb-2">
                CV (PDF)
            </label>
            <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="md:col-span-2">
            <label for="contrat" class="block text-sm font-medium text-gray-700 mb-2">
                Contrat de travail signé (PDF)
            </label>
            <input type="file" id="contrat" name="contrat" accept=".pdf"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="md:col-span-2">
            <label for="piece_identite" class="block text-sm font-medium text-gray-700 mb-2">
                Copie pièce d'identité (PDF/Image)
            </label>
            <input type="file" id="piece_identite" name="piece_identite" accept=".pdf,.jpg,.jpeg,.png"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
        </div>
    </div>
</div>
                    <!-- Informations professionnelles -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="poste" class="block text-sm font-medium text-gray-700 mb-2">Poste *</label>
                            <select id="poste" name="poste_id" required onchange="updatePosteInfo()"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="">Sélectionner un poste</option>
                            </select>
                        </div>

                        <div>
                            <label for="salaire" class="block text-sm font-medium text-gray-700 mb-2">
                                Salaire (FCFA)
                                <span id="salaireRange" class="text-xs text-gray-500"></span>
                            </label>
                            <input type="number" id="salaire" name="salaire" min="0" step="1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                    </div>

                    <!-- Informations contrat -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="typeContrat" class="block text-sm font-medium text-gray-700 mb-2">Type de contrat</label>
                            <input type="text" id="typeContrat" name="type_contrat" readonly
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-700">
                        </div>

                        <div>
                            <label for="dureeContrat" class="block text-sm font-medium text-gray-700 mb-2">Durée du contrat</label>
                            <input type="text" id="dureeContrat" name="duree_contrat" readonly
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-700">
                        </div>
                    </div>

                    <!-- Dates et horaires -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label for="dateEmbauche" class="block text-sm font-medium text-gray-700 mb-2">Date d'embauche *</label>
                            <input type="date" id="dateEmbauche" name="date_embauche" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>

                        <div>
                            <label for="heureDebut" class="block text-sm font-medium text-gray-700 mb-2">Heure début</label>
                            <input type="time" id="heureDebut" name="heure_debut" value="08:00"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                                                <div>
                            <label for="heureFin" class="block text-sm font-medium text-gray-700 mb-2">Heure fin</label>
                            <input type="time" id="heureFin" name="heure_fin" value="17:00"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                    </div>

                    <!-- Statut et options -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="statut" class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                            <select id="statut" name="statut"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="actif">Actif</option>
                                <option value="en_conge">En congé</option>
                                <option value="absent">Absent</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center">
                                <input type="checkbox" id="isAdmin" name="is_admin" value="1"
                                    class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                <span class="ml-2 text-sm text-gray-700">
                                    <i class="fas fa-crown text-yellow-500 mr-1"></i>
                                    Administrateur
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Informations du poste (affichage uniquement) -->
                    <div id="posteInfo" class="bg-gray-50 rounded-lg p-4 mb-6 hidden">
                        <h4 class="text-md font-semibold text-gray-800 mb-3">Informations du poste</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="font-medium text-gray-600">Niveau hiérarchique:</span>
                                <span id="niveauHierarchique" class="ml-2 text-gray-800"></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-600">Code paie:</span>
                                <span id="codePaie" class="ml-2 text-gray-800"></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-600">Catégorie paie:</span>
                                <span id="categoriePaie" class="ml-2 text-gray-800"></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-600">Régime social:</span>
                                <span id="regimeSocial" class="ml-2 text-gray-800"></span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <span class="font-medium text-gray-600">Compétences requises:</span>
                            <div id="competencesRequises" class="mt-1 text-gray-800"></div>
                        </div>
                        <div class="mt-3">
                            <span class="font-medium text-gray-600">Avantages:</span>
                            <div id="avantages" class="mt-1 text-gray-800"></div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end space-x-3 border-t border-gray-200 pt-6">
                        <button type="button" onclick="closeModal()"
                                class="px-6 py-3 border-2 border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 flex items-center">
                            <i class="fas fa-times mr-2"></i>
                            Annuler
                        </button>
                        <button type="submit"
                                class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg font-medium hover:from-green-700 hover:to-emerald-700 transition-all duration-200 shadow-md hover:shadow-lg flex items-center">
                            <i class="fas fa-save mr-2"></i>
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Zone de notification -->
    <div id="notification" class="notification hidden"></div>
    <!-- Modal pour l'historique des présences -->
<div id="presenceHistoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-4xl w-full max-h-screen overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 id="presenceHistoryTitle" class="text-lg font-semibold text-gray-900">Historique des présences</h3>
                <button onclick="closePresenceHistoryModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="p-6">
                <div id="presenceHistoryContent" class="mb-6">
                    <!-- Le contenu sera chargé ici -->
                    <div class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-green-600 text-2xl mb-2"></i>
                        <p>Chargement de l'historique...</p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button onclick="closePresenceHistoryModal()"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="notification" class="notification hidden"></div>
    <script>
     // Variables globales
let employees = [];
let currentPage = 1;
let itemsPerPage = 10;
let totalPages = 1;
let postes = [];
let departements = [];

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initialisation de l\'application...');
    initializeApplication();
});

// Fonction d'initialisation principale
async function initializeApplication() {
    try {
        console.log('Début de l\'initialisation...');
        showNotification('Chargement de l\'application...', 'info');
        
        // Chargement séquentiel des données
        await loadDepartements();
        await loadPostes();
        await loadEmployees();
        await loadStatistics();
        await loadDashboardStats();
        
        // Initialiser les événements
        initializeEventListeners();
        
        // Mise à jour des statistiques rapides
        setTimeout(updateQuickStats, 1000);
        
        hideNotification();
        showNotification('Application chargée avec succès', 'success');
        setTimeout(hideNotification, 2000);
        
        console.log('Application initialisée avec succès');
    } catch (error) {
        console.error('Erreur lors de l\'initialisation:', error);
        hideNotification();
        showNotification('Erreur lors de l\'initialisation: ' + error.message, 'error');
    }
}

// Initialisation des événements
function initializeEventListeners() {
    const searchInput = document.getElementById('searchInput');
    const filterDepartement = document.getElementById('filterDepartement');
    const filterPoste = document.getElementById('filterPoste');
    const filterContrat = document.getElementById('filterContrat');
    const filterStatut = document.getElementById('filterStatut');
    const photoInput = document.getElementById('photo');
    const employeeForm = document.getElementById('employeeForm');
    const posteSelect = document.getElementById('poste');
    
    if (searchInput) searchInput.addEventListener('input', filterEmployees);
    if (filterDepartement) filterDepartement.addEventListener('change', filterEmployees);
    if (filterPoste) filterPoste.addEventListener('change', filterEmployees);
    if (filterContrat) filterContrat.addEventListener('change', filterEmployees);
    if (filterStatut) filterStatut.addEventListener('change', filterEmployees);
    if (photoInput) photoInput.addEventListener('change', previewPhoto);
    if (employeeForm) employeeForm.addEventListener('submit', saveEmployee);
    if (posteSelect) posteSelect.addEventListener('change', updatePosteInfo);

    const employeeModal = document.getElementById('employeeModal');
    if (employeeModal) {
        employeeModal.addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    }
}

// Fonctions de chargement des données
function loadDepartements() {
    return new Promise((resolve) => {
        console.log('Chargement des départements...');
        fetch('?action=get_departements')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    departements = data.departements || [];
                    updateDepartementsSelect();
                }
                resolve(data);
            })
            .catch(error => {
                console.error('Erreur fetch départements:', error);
                departements = [];
                updateDepartementsSelect();
                resolve({ success: false });
            });
    });
}

function loadPostes() {
    return new Promise((resolve) => {
        console.log('Chargement des postes...');
        fetch('?action=get_postes')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    postes = data.postes || [];
                    updatePostesSelects();
                }
                resolve(data);
            })
            .catch(error => {
                console.error('Erreur fetch postes:', error);
                postes = [];
                updatePostesSelects();
                resolve({ success: false });
            });
    });
}

function loadEmployees() {
    return new Promise((resolve) => {
        console.log('Chargement des employés...');
        
        fetch('?action=get_employees')
            .then(response => response.json())
            .then(data => {
                console.log('Données employés reçues:', data);
                
                if (data.success && Array.isArray(data.employees)) {
                    employees = data.employees;
                    displayEmployees(employees);
                    updateEmployeeSelect(employees);
                    console.log(`${employees.length} employés chargés`);
                } else {
                    console.error('Erreur:', data.message || 'Données invalides');
                    employees = [];
                    displayEmployees([]);
                    updateEmployeeSelect([]);
                }
                resolve(data);
            })
            .catch(error => {
                console.error('Erreur complète:', error);
                employees = [];
                displayEmployees([]);
                updateEmployeeSelect([]);
                resolve({ success: false });
            });
    });
}

function loadStatistics() {
    return new Promise((resolve) => {
        console.log('Chargement des statistiques...');
        fetch('?action=get_statistics')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.statistics) {
                    const stats = data.statistics;
                    updateElementText('totalActifs', stats.total_actifs || 0);
                    updateElementText('presentsAujourdhui', stats.presents_aujourd_hui || 0);
                    updateElementText('absentsAujourdhui', stats.absents_aujourd_hui || 0);
                    updateElementText('retardsAujourdhui', stats.retards_aujourd_hui || 0);
                    updateElementText('totalAdmins', stats.total_admins || 0);
                    updateElementText('totalInactifs', stats.total_inactifs || 0);
                }
                resolve(data);
            })
            .catch(error => {
                console.error('Erreur lors du chargement des statistiques:', error);
                resolve({ success: false });
            });
    });
}

function loadDashboardStats() {
    return new Promise((resolve) => {
        console.log('Chargement des statistiques du tableau de bord...');
        
        fetch('?action=get_dashboard_stats')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.stats) {
                    updateDashboardStats(data.stats);
                }
                resolve(data);
            })
            .catch(error => {
                console.error('Erreur fetch dashboard stats:', error);
                resolve({ success: false });
            });
    });
}

// Fonctions de mise à jour des selects
function updateDepartementsSelect() {
    const filterDepartement = document.getElementById('filterDepartement');
    if (filterDepartement) {
        filterDepartement.innerHTML = '<option value="">Tous les départements</option>';
        departements.forEach(dept => {
            filterDepartement.innerHTML += `<option value="${dept.id}">${escapeHtml(dept.nom)}</option>`;
        });
    }
}

function updatePostesSelects() {
    const filterPoste = document.getElementById('filterPoste');
    const modalPoste = document.getElementById('poste');

    if (filterPoste) {
        filterPoste.innerHTML = '<option value="">Tous les postes</option>';
        postes.forEach(poste => {
            filterPoste.innerHTML += `<option value="${poste.id}">${escapeHtml(poste.nom)}</option>`;
        });
    }

    if (modalPoste) {
        modalPoste.innerHTML = '<option value="">Sélectionner un poste</option>';
        postes.forEach(poste => {
            modalPoste.innerHTML += `<option value="${poste.id}">${escapeHtml(poste.nom)} - ${escapeHtml(poste.type_contrat || 'Non défini')}</option>`;
        });
    }
}

function updateEmployeeSelect(employeesList) {
    const employeSelect = document.getElementById('employe_id');
    if (!employeSelect) return;
    
    employeSelect.innerHTML = '<option value="">Sélectionnez un employé</option>';
    
    employeesList.filter(emp => emp.statut === 'actif').forEach(employee => {
        employeSelect.innerHTML += `<option value="${employee.id}">${escapeHtml(employee.nom)} ${escapeHtml(employee.prenom)} (${escapeHtml(employee.poste_nom || 'Aucun poste')})</option>`;
    });
}

// Fonction d'affichage des employés
function displayEmployees(employeesList) {
    console.log('Affichage des employés:', employeesList.length, 'employés');
    displayTableView(employeesList);
}

function displayTableView(employeesList) {
    const tbody = document.getElementById('employeesTableBody');
    if (!tbody) {
        console.error('Élément employeesTableBody non trouvé');
        return;
    }

    tbody.innerHTML = '';

    if (!Array.isArray(employeesList) || employeesList.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="12" class="px-6 py-4 text-center text-gray-500">
                    <i class="fas fa-users text-4xl mb-2"></i>
                    <p>Aucun employé trouvé</p>
                </td>
            </tr>
        `;
        updatePaginationInfo(0, 0, 0);
        return;
    }

    // Calculer la pagination
    totalPages = Math.ceil(employeesList.length / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = Math.min(startIndex + itemsPerPage, employeesList.length);

    // Afficher uniquement les employés de la page actuelle
    const employeesToDisplay = employeesList.slice(startIndex, endIndex);

    employeesToDisplay.forEach(employee => {
        const row = createEmployeeRow(employee);
        tbody.appendChild(row);
    });

    // Mettre à jour les informations de pagination
    updatePaginationInfo(startIndex + 1, endIndex, employeesList.length);
    renderPaginationButtons();

    console.log(`Page ${currentPage}/${totalPages}: ${employeesToDisplay.length} employés affichés sur ${employeesList.length} au total`);
}

function createEmployeeRow(employee) {
    const row = document.createElement('tr');
    row.className = employee.statut === 'inactif'
        ? 'hover:bg-gray-50 fade-in opacity-60 bg-gray-25'
        : 'hover:bg-gray-50 fade-in';

    // Gestion des données de présence
    let presenceClass = '';
    let presenceText = '';
    let presenceIcon = '';

    if (employee.statut === 'inactif') {
        presenceClass = 'bg-gray-100 text-gray-600';
        presenceText = 'Inactif';
        presenceIcon = 'fas fa-user-slash';
    } else {
        const statutPresence = employee.statut_presences || 'absent';
        switch(statutPresence) {
            case 'present':
                presenceClass = 'bg-green-100 text-green-800';
                presenceText = 'Présent';
                presenceIcon = 'fas fa-check-circle';
                break;
            case 'retard':
                presenceClass = 'bg-yellow-100 text-yellow-800';
                presenceText = `Retard (${employee.retard_minutes || 0}min)`;
                presenceIcon = 'fas fa-clock';
                break;
            case 'parti':
                presenceClass = 'bg-purple-100 text-purple-800';
                presenceText = 'Parti';
                presenceIcon = 'fas fa-sign-out-alt';
                break;
            case 'absent':
            default:
                presenceClass = 'bg-red-100 text-red-800';
                presenceText = 'Absent';
                presenceIcon = 'fas fa-times-circle';
                break;
        }
    }

    let heureArriveeDisplay = '';
    if (employee.heure_arrivee && employee.statut !== 'inactif') {
        try {
            const date = new Date(employee.heure_arrivee);
            if (!isNaN(date.getTime())) {
                heureArriveeDisplay = `<div class="text-xs text-gray-500 mt-1">Arrivé: ${date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})}</div>`;
            }
        } catch (e) {
            console.warn('Erreur formatage heure arrivée:', e);
        }
    }

    row.innerHTML = `
        <td class="px-6 py-4 whitespace-nowrap">
            <img src="uploads/photos/${employee.photo || 'default-avatar.png'}"
                class="h-10 w-10 rounded-full object-cover ${employee.statut === 'inactif' ? 'grayscale' : ''}"
                onerror="this.src='uploads/photos/default-avatar.png'">
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm font-medium ${employee.statut === 'inactif' ? 'text-gray-500' : 'text-gray-900'}">
                ${escapeHtml(employee.prenom || '')} ${escapeHtml(employee.nom || '')}
                ${employee.is_admin == 1 ? '<i class="fas fa-crown text-yellow-500 ml-1" title="Administrateur"></i>' : ''}
                ${employee.statut === 'inactif' ? '<i class="fas fa-user-slash text-red-500 ml-1" title="Employé inactif"></i>' : ''}
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center justify-center">
                <span class="departement-badge"
                    style="background-color: ${employee.departement_couleur || '#6B7280'}15; color: ${employee.departement_couleur || '#6B7280'}; border-color: ${employee.departement_couleur || '#6B7280'}40;">
                    <i class="fas fa-building mr-2"></i>
                    ${employee.departement_nom || 'Non assigné'}
                </span>
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                    style="background-color: ${employee.poste_couleur || '#6B7280'}20; color: ${employee.poste_couleur || '#6B7280'};">
                    ${employee.poste_nom || '<span class="missing-data">Non défini</span>'}
                </span>
            </div>
            <div class="text-xs text-gray-500 mt-1">Niveau: ${employee.niveau_hierarchique || 'N/A'}</div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex flex-col space-y-1">
                <span class="contract-badge contract-${(employee.type_contrat || '').toLowerCase().replace(' ', '_')}">
                    ${employee.type_contrat || '<span class="missing-data">Non défini</span>'}
                </span>
                <div class="text-xs text-gray-500">${employee.duree_contrat || '<span class="missing-data">Non spécifiée</span>'}</div>
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm ${employee.statut === 'inactif' ? 'text-gray-500' : 'text-gray-900'}">
            <div>${employee.email || '<span class="missing-data">N/A</span>'}</div>
            <div class="text-gray-500">${employee.telephone || '<span class="missing-data">N/A</span>'}</div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusClass(employee.statut)}">
                ${getStatusText(employee.statut)}
            </span>
        </td>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex flex-col space-y-1">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${presenceClass}">
                    <i class="${presenceIcon} mr-1"></i>
                    ${presenceText}
                </span>
                ${heureArriveeDisplay}
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm ${employee.statut === 'inactif' ? 'text-gray-500' : 'text-gray-900'}">
            ${employee.salaire ? formatSalaire(employee.salaire) + ' FCFA' : '<span class="missing-data">Non défini</span>'}
            <div class="text-xs text-gray-500">${employee.heure_debut || '08:00'} - ${employee.heure_fin || '17:00'}</div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm ${employee.statut === 'inactif' ? 'text-gray-500' : 'text-gray-900'}">
            <div class="flex items-center">
                <i class="fas fa-clock ${employee.statut === 'inactif' ? 'text-gray-400' : 'text-purple-600'} mr-1"></i>
                <span class="font-medium ${employee.statut === 'inactif' ? 'text-gray-400' : 'text-purple-700'}">
                    ${employee.heures_par_mois || '0'}h
                </span>
            </div>
            <div class="text-xs text-gray-500">par mois</div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm">
            <div class="flex flex-col space-y-1">
                ${employee.cv ? `
                    <a href="uploads/documents/${employee.cv}" target="_blank"
                    class="text-purple-600 hover:text-purple-800 text-xs flex items-center">
                        <i class="fas fa-file-pdf mr-1 text-red-500"></i>CV
                    </a>
                ` : '<span class="text-red-500 text-xs">Manquant</span>'}
                ${employee.contrat ? `
                    <a href="uploads/documents/${employee.contrat}" target="_blank"
                    class="text-purple-600 hover:text-purple-800 text-xs flex items-center">
                        <i class="fas fa-file-contract mr-1 text-green-500"></i>Contrat
                    </a>
                ` : '<span class="text-red-500 text-xs">Manquant</span>'}
                ${employee.piece_identite ? `
                    <a href="uploads/documents/${employee.piece_identite}" target="_blank"
                    class="text-purple-600 hover:text-purple-800 text-xs flex items-center">
                        <i class="fas fa-id-card mr-1 text-purple-500"></i>Pièce ID
                    </a>
                ` : '<span class="text-red-500 text-xs">Manquant</span>'}
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
            <div class="flex space-x-2">
                <button onclick="viewEmployee(${employee.id})" class="text-purple-600 hover:text-purple-900" title="Voir détails">
                    <i class="fas fa-eye"></i>
                </button>
                ${employee.statut !== 'inactif' ? `
                    <button onclick="editEmployee(${employee.id})" class="text-green-600 hover:text-green-900" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="generateBadge(${employee.id})" class="text-purple-600 hover:text-purple-900" title="Badge">
                        <i class="fas fa-qrcode"></i>
                    </button>
                    <button onclick="viewPresenceHistory(${employee.id})" class="text-indigo-600 hover:text-indigo-900" title="Historique présence">
                        <i class="fas fa-calendar-check"></i>
                    </button>
                    <button onclick="deleteEmployee(${employee.id})" class="text-red-600 hover:text-red-900" title="Désactiver">
                        <i class="fas fa-user-slash"></i>
                    </button>
                ` : `
                    <button onclick="reactivateEmployee(${employee.id})" class="text-green-600 hover:text-green-900" title="Réactiver">
                        <i class="fas fa-user-check"></i>
                    </button>
                    <button onclick="permanentDeleteEmployee(${employee.id})" class="text-red-800 hover:text-red-900" title="Supprimer définitivement">
                        <i class="fas fa-trash"></i>
                    </button>
                `}
            </div>
        </td>
    `;

    return row;
}

// ===== FONCTIONS DE PAGINATION =====

function updatePaginationInfo(start, end, total) {
    const startRange = document.getElementById('startRange');
    const endRange = document.getElementById('endRange');
    const totalEmployees = document.getElementById('totalEmployees');

    if (startRange) startRange.textContent = start;
    if (endRange) endRange.textContent = end;
    if (totalEmployees) totalEmployees.textContent = total;
}

function renderPaginationButtons() {
    const paginationNumbers = document.getElementById('paginationNumbers');
    if (!paginationNumbers) return;

    paginationNumbers.innerHTML = '';

    // Bouton Précédent
    const prevButton = document.createElement('button');
    prevButton.onclick = () => changePage(currentPage - 1);
    prevButton.disabled = currentPage === 1;
    prevButton.className = `relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium ${
        currentPage === 1 ? 'text-gray-300 cursor-not-allowed' : 'text-gray-500 hover:bg-gray-50'
    }`;
    prevButton.innerHTML = '<i class="fas fa-chevron-left"></i>';
    paginationNumbers.appendChild(prevButton);

    // Numéros de pages
    const maxPagesToShow = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);

    if (endPage - startPage < maxPagesToShow - 1) {
        startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }

    // Page 1 si pas visible
    if (startPage > 1) {
        const firstButton = createPageButton(1);
        paginationNumbers.appendChild(firstButton);
        if (startPage > 2) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700';
            ellipsis.textContent = '...';
            paginationNumbers.appendChild(ellipsis);
        }
    }

    // Pages visibles
    for (let i = startPage; i <= endPage; i++) {
        const pageButton = createPageButton(i);
        paginationNumbers.appendChild(pageButton);
    }

    // Dernière page si pas visible
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700';
            ellipsis.textContent = '...';
            paginationNumbers.appendChild(ellipsis);
        }
        const lastButton = createPageButton(totalPages);
        paginationNumbers.appendChild(lastButton);
    }

    // Bouton Suivant
    const nextButton = document.createElement('button');
    nextButton.onclick = () => changePage(currentPage + 1);
    nextButton.disabled = currentPage === totalPages;
    nextButton.className = `relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium ${
        currentPage === totalPages ? 'text-gray-300 cursor-not-allowed' : 'text-gray-500 hover:bg-gray-50'
    }`;
    nextButton.innerHTML = '<i class="fas fa-chevron-right"></i>';
    paginationNumbers.appendChild(nextButton);

    // Mettre à jour les boutons mobile
    const prevPageMobile = document.getElementById('prevPageMobile');
    const nextPageMobile = document.getElementById('nextPageMobile');
    if (prevPageMobile) prevPageMobile.disabled = currentPage === 1;
    if (nextPageMobile) nextPageMobile.disabled = currentPage === totalPages;
}

function createPageButton(pageNumber) {
    const button = document.createElement('button');
    button.onclick = () => changePage(pageNumber);
    button.className = `relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
        pageNumber === currentPage
            ? 'z-10 bg-green-50 border-green-500 text-green-600'
            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
    }`;
    button.textContent = pageNumber;
    return button;
}

function changePage(newPage) {
    if (newPage < 1 || newPage > totalPages || newPage === currentPage) return;

    currentPage = newPage;
    console.log(`Changement vers la page ${currentPage}`);

    // Réafficher avec la nouvelle page
    displayEmployees(employees);

    // Scroll vers le haut du tableau
    const tableContainer = document.querySelector('.overflow-x-auto');
    if (tableContainer) {
        tableContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Fonctions de filtrage
function filterEmployees() {
    if (!employees || employees.length === 0) {
        console.log('Aucun employé à filtrer');
        return;
    }

    // Réinitialiser à la page 1 lors d'un nouveau filtrage
    currentPage = 1;

    const searchTerm = getElementValue('searchInput').toLowerCase();
    const departementFilter = getElementValue('filterDepartement');
    const posteFilter = getElementValue('filterPoste');
    const contratFilter = getElementValue('filterContrat');
    const statutFilter = getElementValue('filterStatut');

    const filtered = employees.filter(employee => {
        const matchesSearch = !searchTerm ||
            (employee.nom && employee.nom.toLowerCase().includes(searchTerm)) ||
            (employee.prenom && employee.prenom.toLowerCase().includes(searchTerm)) ||
            (employee.email && employee.email.toLowerCase().includes(searchTerm));

        const matchesDepartement = !departementFilter || employee.departement_id == departementFilter;
        const matchesPoste = !posteFilter || employee.poste_id == posteFilter;
        const matchesContrat = !contratFilter || employee.type_contrat === contratFilter;
        const matchesStatut = !statutFilter || employee.statut === statutFilter;

        return matchesSearch && matchesDepartement && matchesPoste && matchesContrat && matchesStatut;
    });

    console.log(`${filtered.length} employés trouvés sur ${employees.length}`);
    displayEmployees(filtered);
}

function applyFilters() {
    filterEmployees();
    showNotification('Filtres appliqués', 'success');
    setTimeout(hideNotification, 2000);
}

function resetFilters() {
    ['searchInput', 'filterDepartement', 'filterPoste', 'filterContrat', 'filterStatut'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.value = '';
        }
    });

    showNotification('Filtres réinitialisés', 'success');
    setTimeout(() => {
        displayEmployees(employees);
        hideNotification();
    }, 500);
}

// Fonctions modales
function openAddModal() {
    const modal = document.getElementById('employeeModal');
    if (!modal) return;

    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus mr-2"></i>Ajouter un employé';
    document.getElementById('employeeForm').reset();
    document.getElementById('employeeId').value = '';
    document.getElementById('ajaxAction').value = 'add_employee';
    document.getElementById('photoPreview').src = 'uploads/photos/default-avatar.png';

    modal.classList.remove('hidden');
    // Animation d'ouverture
    setTimeout(() => {
        modal.classList.add('opacity-100');
    }, 10);
}

function closeModal() {
    const modal = document.getElementById('employeeModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('opacity-100');
    }
}

// Fermer le modal en cliquant sur le fond
function closeModalOnBackdrop(event) {
    if (event.target.id === 'employeeModal') {
        closeModal();
    }
}

// Prévisualiser la photo
function previewPhoto(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photoPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

// Fermer le modal avec la touche Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('employeeModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeModal();
        }
    }
});

function editEmployee(id) {
    const employee = employees.find(e => e.id == id);
    if (!employee) {
        showNotification('Employé non trouvé', 'error');
        return;
    }

    const modal = document.getElementById('employeeModal');
    if (!modal) return;

    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit mr-2"></i>Modifier l\'employé';
    document.getElementById('employeeId').value = employee.id;
    document.getElementById('ajaxAction').value = 'update_employee';
    
    setElementValue('nom', employee.nom);
    setElementValue('prenom', employee.prenom);
    setElementValue('email', employee.email);
    setElementValue('telephone', employee.telephone || '');
    setElementValue('poste', employee.poste_id || '');
    setElementValue('salaire', employee.salaire || '');
    setElementValue('dateEmbauche', employee.date_embauche);
    setElementValue('statut', employee.statut);
    setElementValue('heureDebut', employee.heure_debut);
    setElementValue('heureFin', employee.heure_fin);
    
    const isAdminCheckbox = document.getElementById('isAdmin');
    if (isAdminCheckbox) {
        isAdminCheckbox.checked = employee.is_admin == 1;
    }

    const photoPreview = document.getElementById('photoPreview');
    if (photoPreview) {
        photoPreview.src = `uploads/photos/${employee.photo || 'default-avatar.png'}`;
    }

    if (employee.poste_id) {
        updatePosteInfo();
    }

    modal.classList.remove('hidden');
}

function saveEmployee(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    showNotification('Enregistrement en cours...', 'info');

    const action = formData.get('ajax_action') || 'add_employee';
    const url = `${window.location.pathname}?action=${action}`;

    console.log('URL appelée:', url); // Debug
    console.log('Action:', action); // Debug

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Status:', response.status); // Debug
        console.log('Content-Type:', response.headers.get('content-type')); // Debug
        
        // Vérifier si c'est du JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Réponse non-JSON reçue:', text.substring(0, 500));
                throw new Error('Le serveur n\'a pas retourné du JSON');
            });
        }
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Données reçues:', data); // Debug
        hideNotification();

        if (data.success) {
            // Construire le message avec le code numérique si disponible
            let message = data.message || 'Employé sauvegardé avec succès!';
            if (data.numeric_code) {
                message += ` - Code numérique: ${data.numeric_code}`;
            }
            showNotification(message, 'success');
            closeModal();
            loadEmployees();
            loadStatistics();
            setTimeout(hideNotification, 5000);
        } else {
            showNotification(data.message || 'Erreur lors de la sauvegarde', 'error');
        }
    })
    .catch(error => {
        hideNotification();
        console.error('Erreur complète:', error);
        showNotification('Erreur: ' + error.message, 'error');
    });
}
function deleteEmployee(id) {
    if (confirm('Êtes-vous sûr de vouloir DÉSACTIVER cet employé?')) {
        fetch('?action=delete_employee', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Employé désactivé avec succès!', 'success');
                loadEmployees();
                loadStatistics();
            } else {
                showNotification(data.message || 'Erreur lors de la désactivation', 'error');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('Erreur lors de la désactivation', 'error');
        });
    }
}

function reactivateEmployee(id) {
    if (confirm('Êtes-vous sûr de vouloir réactiver cet employé?')) {
        fetch('?action=reactivate_employee', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Employé réactivé avec succès!', 'success');
                loadEmployees();
                loadStatistics();
            } else {
                showNotification(data.message || 'Erreur lors de la réactivation', 'error');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('Erreur lors de la réactivation', 'error');
        });
    }
}

function permanentDeleteEmployee(id) {
    if (confirm('ATTENTION: Supprimer définitivement cet employé?')) {
        if (confirm('Dernière confirmation: Cette action est irréversible.')) {
            fetch('?action=permanent_delete_employee', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Employé supprimé définitivement', 'success');
                    loadEmployees();
                    loadStatistics();
                } else {
                    showNotification(data.message || 'Erreur lors de la suppression', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Erreur lors de la suppression', 'error');
            });
        }
    }
}

// Fonctions de génération de rapports
function generateCustomReport() {
    const type = getElementValue('reportType');
    const startDate = getElementValue('reportStartDate');
    const endDate = getElementValue('reportEndDate');

    if (!startDate || !endDate) {
        showNotification('Veuillez sélectionner une période de dates', 'error');
        return;
    }

    if (startDate > endDate) {
        showNotification('La date de début doit être antérieure à la date de fin', 'error');
        return;
    }

    const reportLoading = document.getElementById('reportLoading');
    if (reportLoading) {
        reportLoading.classList.remove('hidden');
    }

    const filters = {
        date_debut: startDate,
        date_fin: endDate,
        departement_id: getElementValue('filterDepartement') || ''
    };

    fetch('?action=generate_report', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ type, filters })
    })
    .then(response => response.json())
    .then(data => {
        if (reportLoading) {
            reportLoading.classList.add('hidden');
        }

        if (data.success) {
            displayReport(data.report, type, startDate, endDate);
            showNotification('Rapport généré avec succès', 'success');
        } else {
            showNotification(data.message || 'Erreur lors de la génération du rapport', 'error');
        }
    })
    .catch(error => {
        if (reportLoading) {
            reportLoading.classList.add('hidden');
        }
        console.error('Erreur:', error);
        showNotification('Erreur de connexion lors de la génération du rapport', 'error');
    });
}

function displayReport(reportData, reportType, startDate, endDate) {
    const modal = document.getElementById('reportModal');
    const modalTitle = document.getElementById('reportModalTitle');
    const reportContent = document.getElementById('reportContent');
    
    if (!modal || !modalTitle || !reportContent) {
        console.error('Éléments du modal de rapport non trouvés');
        return;
    }

    const titles = {
        'presences': 'Rapport des Présences et Retards',
        'salaires': 'Rapport des Salaires et Coûts',
        'effectifs': 'Rapport des Effectifs et Démographie',
        'turnover': 'Rapport du Turnover et Rotation'
    };

    modalTitle.textContent = titles[reportType] || 'Rapport Personnalisé';

    const formattedStartDate = formatDateForDisplay(startDate);
    const formattedEndDate = formatDateForDisplay(endDate);

    let content = '';

    if (!reportData || reportData.length === 0) {
        content = `
            <div class="text-center py-8">
                <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                <p class="text-gray-500">Aucune donnée disponible pour la période sélectionnée</p>
                <p class="text-sm text-gray-400">(Du ${formattedStartDate} au ${formattedEndDate})</p>
            </div>
        `;
    } else {
        switch (reportType) {
            case 'presences':
                content = generatePresenceReportContent(reportData, formattedStartDate, formattedEndDate);
                break;
            case 'salaires':
                content = generateSalaryReportContent(reportData, formattedStartDate, formattedEndDate);
                break;
            case 'effectifs':
                content = generateWorkforceReportContent(reportData, formattedStartDate, formattedEndDate);
                break;
            case 'turnover':
                content = generateTurnoverReportContent(reportData, formattedStartDate, formattedEndDate);
                break;
            default:
                content = '<p class="text-center py-4">Type de rapport non supporté</p>';
        }
    }

    reportContent.innerHTML = content;
    modal.classList.remove('hidden');
}

function closeReportModal() {
    const modal = document.getElementById('reportModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Fonctions présence/historique
function viewPresenceHistory(employeeId) {
    const modal = document.getElementById('presenceHistoryModal');
    if (!modal) return;

    modal.classList.remove('hidden');

    const employee = employees.find(e => e.id == employeeId);
    if (employee) {
        const title = document.getElementById('presenceHistoryTitle');
        if (title) {
            title.textContent = `Historique des présences - ${employee.prenom} ${employee.nom}`;
        }
    }

    loadPresenceHistory(employeeId);
}

function closePresenceHistoryModal() {
    const modal = document.getElementById('presenceHistoryModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function loadPresenceHistory(employeeId) {
    fetch(`get_presence_history.php?employee_id=${employeeId}`)
        .then(response => response.json())
        .then(data => {
            const contentDiv = document.getElementById('presenceHistoryContent');
            if (!contentDiv) return;

            if (data.success && data.history && data.history.length > 0) {
                contentDiv.innerHTML = `
                    <div class="mb-4">
                        <h4 class="text-md font-semibold text-gray-800">Historique des 30 derniers jours</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="presence-table min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Arrivée</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Départ</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Heures</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Retard</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                ${data.history.map(entry => `
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm">${formatDate(entry.date)}</td>
                                        <td class="px-4 py-3 text-sm">${formatTime(entry.heure_arrivee)}</td>
                                        <td class="px-4 py-3 text-sm">${formatTime(entry.heure_depart)}</td>
                                        <td class="px-4 py-3 text-sm">${entry.heures_travaillees ? entry.heures_travaillees + 'h' : '-'}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusClass(entry.statut)}">
                                                ${getStatusText(entry.statut)}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm ${entry.retard_minutes > 0 ? 'text-red-600' : 'text-gray-900'}">${entry.retard_minutes ? entry.retard_minutes + ' min' : '-'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                contentDiv.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-history text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">Aucun historique de présence trouvé.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            const contentDiv = document.getElementById('presenceHistoryContent');
            if (contentDiv) {
                contentDiv.innerHTML = `
                    <div class="text-center py-8">
                        <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                        <p class="text-red-500">Erreur lors du chargement de l'historique.</p>
                    </div>
                `;
            }
        });
}

// Fonctions utilitaires
function updatePosteInfo() {
    const posteId = document.getElementById('poste').value;
    const posteInfo = document.getElementById('posteInfo');

    if (!posteId) {
        posteInfo.classList.add('hidden');
        document.getElementById('typeContrat').value = '';
        document.getElementById('dureeContrat').value = '';
        document.getElementById('salaire').value = '';
        return;
    }

    fetch(`?action=get_poste_details&id=${posteId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.poste) {
                const poste = data.poste;

                setElementValue('typeContrat', poste.type_contrat || '');
                setElementValue('dureeContrat', poste.duree_contrat || '');
                setElementValue('salaire', poste.salaire || '');

                const salaireRange = document.getElementById('salaireRange');
                if (salaireRange && poste.salaire_min && poste.salaire_max) {
                    salaireRange.textContent = `(${formatSalaire(poste.salaire_min)} - ${formatSalaire(poste.salaire_max)} FCFA)`;
                }

                updateElementText('niveauHierarchique', poste.niveau_hierarchique || 'Non défini');
                updateElementText('codePaie', poste.code_paie || 'Non défini');
                updateElementText('categoriePaie', poste.categorie_paie || 'Non définie');
                updateElementText('regimeSocial', poste.regime_social || 'Non défini');
                updateElementText('competencesRequises', poste.competences_requises || 'Aucune spécifiée');
                updateElementText('avantages', poste.avantages || 'Aucun spécifié');

                posteInfo.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            posteInfo.classList.add('hidden');
        });
}

function previewPhoto(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const photoPreview = document.getElementById('photoPreview');
            if (photoPreview) {
                photoPreview.src = e.target.result;
            }
        };
        reader.readAsDataURL(file);
    }
}

function viewEmployee(id) {
    window.open(`employee_details.php?id=${id}`, '_blank');
}

function generateBadge(id) {
    window.open(`generate_badge.php?id=${id}`, '_blank');
}

// Fonctions de formatage et utilitaires
function updateElementText(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value;
    }
}

function updateDashboardStats(stats) {
    updateElementText('totalEmployes', stats.total_employes || 0);
    updateElementText('tauxPresence', (stats.taux_presence || 0) + '%');
    updateElementText('masseSalariale', formatSalaire(stats.masse_salariale_mensuelle || 0) + ' FCFA');
    updateElementText('retardMoyen', (stats.moyenne_retards || 0) + ' min');
}

function setElementValue(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.value = value || '';
    }
}

function getElementValue(elementId) {
    const element = document.getElementById(elementId);
    return element ? element.value : '';
}

function formatDate(dateString) {
    if (!dateString) return '-';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return '-';
        return date.toLocaleDateString('fr-FR', {
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    } catch (e) {
        return '-';
    }
}

function formatDateForDisplay(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();

    return `${day}/${month}/${year}`;
}

function formatTime(timeString) {
    if (!timeString) return '-';
    try {
        if (typeof timeString === 'string' && timeString.includes(':')) {
            const parts = timeString.split(':');
            if (parts.length >= 2) {
                return `${parts[0]}:${parts[1]}`;
            }
        }
        return timeString;
    } catch (e) {
        return '-';
    }
}

function formatSalaire(montant) {
    if (!montant) return '0';
    return parseInt(montant).toLocaleString('fr-FR');
}

function getStatusClass(statut) {
    const classes = {
        'actif': 'bg-green-100 text-green-800',
        'en_conge': 'bg-yellow-100 text-yellow-800',
        'absent': 'bg-red-100 text-red-800',
        'inactif': 'bg-gray-100 text-gray-800',
        'present': 'bg-green-100 text-green-800',
        'retard': 'bg-yellow-100 text-yellow-800',
        'parti': 'bg-purple-100 text-purple-800'
    };
    return classes[statut] || 'bg-gray-100 text-gray-800';
}

function getStatusText(statut) {
    if (!statut) return 'Inconnu';
    const texts = {
        'actif': 'Actif',
        'en_conge': 'En congé',
        'absent': 'Absent',
        'inactif': 'Inactif',
        'present': 'Présent',
        'retard': 'En retard',
        'parti': 'Parti'
    };
    return texts[statut.toLowerCase()] || 'Inconnu';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateQuickStats() {
    if (!employees || employees.length === 0) return;
    const activeEmployees = employees.filter(e => e.statut === 'actif');
    console.log(`${activeEmployees.length} employés actifs pour les actions rapides`);
}

// Fonctions de notification
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    if (!notification) return;

    const colors = {
        'success': 'bg-green-500',
        'error': 'bg-red-500',
        'warning': 'bg-yellow-500',
        'info': 'bg-purple-500'
    };

    const icons = {
        'success': 'check',
        'error': 'times',
        'warning': 'exclamation-triangle',
        'info': 'info'
    };

    notification.innerHTML = `
        <div class="${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-${icons[type]}-circle mr-2"></i>
                <span>${message}</span>
            </div>
            <button onclick="hideNotification()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    notification.classList.remove('hidden');
    setTimeout(hideNotification, 5000);
}

function hideNotification() {
    const notification = document.getElementById('notification');
    if (notification) {
        notification.classList.add('hidden');
    }
}

// Fonctions de génération de contenu de rapport (versions simplifiées)
function generatePresenceReportContent(data, startDate, endDate) {
    return `
        <div class="mb-4">
            <h4 class="text-md font-semibold text-gray-800">Rapport des Présences</h4>
            <p class="text-sm text-gray-600">Du ${startDate} au ${endDate}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Employé</th>
                        <th class="px-4 py-2 text-center">Présences</th>
                        <th class="px-4 py-2 text-center">Retards</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.map(row => `
                        <tr class="border-b">
                            <td class="px-4 py-2">${escapeHtml(row.prenom || '')} ${escapeHtml(row.nom || '')}</td>
                            <td class="px-4 py-2 text-center">${row.jours_presence || 0}</td>
                            <td class="px-4 py-2 text-center">${row.jours_retard || 0}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function generateSalaryReportContent(data, startDate, endDate) {
    return `
        <div class="mb-4">
            <h4 class="text-md font-semibold text-gray-800">Rapport des Salaires</h4>
            <p class="text-sm text-gray-600">Du ${startDate} au ${endDate}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Employé</th>
                        <th class="px-4 py-2 text-center">Salaire Brut</th>
                        <th class="px-4 py-2 text-center">Salaire Net</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.map(row => `
                        <tr class="border-b">
                            <td class="px-4 py-2">${escapeHtml(row.nom || '')} ${escapeHtml(row.prenom || '')}</td>
                            <td class="px-4 py-2 text-center">${formatSalaire(row.salaire_brut || 0)} FCFA</td>
                            <td class="px-4 py-2 text-center">${formatSalaire(row.salaire_net || 0)} FCFA</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function generateWorkforceReportContent(data, startDate, endDate) {
    return `
        <div class="mb-4">
            <h4 class="text-md font-semibold text-gray-800">Rapport des Effectifs</h4>
            <p class="text-sm text-gray-600">Du ${startDate} au ${endDate}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Département</th>
                        <th class="px-4 py-2 text-center">Effectif</th>
                        <th class="px-4 py-2 text-center">Salaire Moyen</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.map(row => `
                        <tr class="border-b">
                            <td class="px-4 py-2">${escapeHtml(row.departement || 'Non assigné')}</td>
                            <td class="px-4 py-2 text-center">${row.nombre_employes || 0}</td>
                            <td class="px-4 py-2 text-center">${formatSalaire(row.salaire_moyen || 0)} FCFA</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function generateTurnoverReportContent(data, startDate, endDate) {
    return `
        <div class="mb-4">
            <h4 class="text-md font-semibold text-gray-800">Rapport du Turnover</h4>
            <p class="text-sm text-gray-600">Du ${startDate} au ${endDate}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Département</th>
                        <th class="px-4 py-2 text-center">Effectif</th>
                        <th class="px-4 py-2 text-center">Départs</th>
                        <th class="px-4 py-2 text-center">Taux Turnover</th>
                    </tr>
                </thead>
                <tbody>
                    ${data.map(row => {
                        const tauxTurnover = row.effectif_actuel > 0 ? Math.round((row.departs_periode / row.effectif_actuel) * 100) : 0;
                        return `
                            <tr class="border-b">
                                <td class="px-4 py-2">${escapeHtml(row.departement || 'Non assigné')}</td>
                                <td class="px-4 py-2 text-center">${row.effectif_actuel || 0}</td>
                                <td class="px-4 py-2 text-center">${row.departs_periode || 0}</td>
                                <td class="px-4 py-2 text-center">${tauxTurnover}%</td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
    `;
}

// Fonctions d'export (versions simplifiées)
function exportReportToPDF() {
    const reportContent = document.getElementById('reportContent');
    if (!reportContent) {
        showNotification('Aucun rapport à exporter', 'error');
        return;
    }

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Rapport RH - ${new Date().toLocaleDateString('fr-FR')}</title>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { border-collapse: collapse; width: 100%; margin: 10px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .text-center { text-align: center; }
            </style>
        </head>
        <body>
            <h1>Rapport RH - ${new Date().toLocaleDateString('fr-FR')}</h1>
            ${reportContent.innerHTML}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    setTimeout(() => {
        printWindow.print();
        showNotification('Impression du rapport lancée', 'success');
    }, 500);
}

function exportReportToExcel() {
    showNotification('Export Excel en cours de développement', 'info');
}
    </script>
        </div> <!-- Fin div px-8 py-6 -->
        </div> <!-- Fin flex-1 overflow-y-auto -->
    </div> <!-- Fin flex h-screen -->

    <!-- Footer -->
    <?php include 'footer.php'; ?>
</body>
</html>