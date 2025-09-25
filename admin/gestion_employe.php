<?php
session_start();

    require_once '../config.php';
    require_once 'phpqrcode/qrlib.php';
    require_once 'classes/PayrollCalculator.php';
require_once 'classes/BulletinPDFGenerateur.php'; 

// Rediriger si l'admin n'est pas connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
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
        return $currentFile;
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

    // AJOUTEZ LES COLONNES MANQUANTES DANS LA REQUÊTE
    $stmt = $this->conn->prepare("
        UPDATE employes
        SET nom = ?, prenom = ?, email = ?, telephone = ?, poste_id = ?, salaire = ?,
            date_embauche = ?, heure_debut = ?, heure_fin = ?, photo = ?,
            is_admin = ?, statut = ?,
            date_naissance = ?, lieu_naissance = ?, nationalite = ?, sexe = ?,
            contact_urgence_nom = ?, contact_urgence_relation = ?, contact_urgence_telephone = ?,
            adresse = ?, num_secu = ?, num_identite = ?, type_identite = ?, situation_familiale = ?,
            nombre_enfants = ?, iban = ?, nom_banque = ?, titulaire_compte = ?, bic = ?,
            niveau_etude = ?, langues = ?, competences = ?, formations = ?, experiences = ?,
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
        $data['niveau_etude'] ?? null,
        $data['langues'] ?? null,
        $data['competences'] ?? null,
        $data['formations'] ?? null,
        $data['experiences'] ?? null,
        $data['cv'] ?? null,
        $data['contrat'] ?? null,
        $data['piece_identite'] ?? null,
        $data['code_numerique'] ?? null,  // AJOUTEZ CETTE LIGNE
        $employee_id,
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
    $action = $_GET['action'] ?? $_POST['ajax_action'] ?? '';

    // Log de l'action demandée pour debug
    error_log("Action demandée: " . $action);

    // Actions qui ne nécessitent pas de header JSON
    $non_json_actions = ['generer_bulletin'];

    if (!in_array($action, $non_json_actions)) {
        header('Content-Type: application/json');
    }

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
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false, 
                    'message' => 'Action non reconnue: ' . $action,
                    'debug' => ['action' => $action, 'get' => $_GET, 'post_keys' => array_keys($_POST)]
                ]);
        }
    } catch (Exception $e) {
        error_log("Erreur dans handleRequest pour action $action: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Erreur serveur: ' . $e->getMessage(),
            'action' => $action
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
    <!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->
    <link rel="stylesheet" href="employe.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-utensils text-orange-600 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-900">Gestion Restaurant</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="openAddModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Ajouter Employé
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Statistiques Dashboard -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6 mb-8">
            <!-- Employés Actifs -->
            <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale stat-card-actifs">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Employés Actifs</p>
                        <p class="text-2xl font-bold text-gray-900" id="totalActifs">0</p>
                    </div>
                </div>
            </div>

            <!-- Présents Aujourd'hui -->
           <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale border-2 border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-check-circle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Présents</p>
                        <p class="text-2xl font-bold text-gray-900" id="presentsAujourdhui">0</p>
                    </div>
                </div>
            </div>

            <!-- Absents -->
           <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale border-2 border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <i class="fas fa-times-circle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Absents</p>
                        <p class="text-2xl font-bold text-gray-900" id="absentsAujourdhui">0</p>
                    </div>
                </div>
            </div>

            <!-- En Retard -->
           <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale border-2 border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">En Retard</p>
                        <p class="text-2xl font-bold text-gray-900" id="retardsAujourdhui">0</p>
                    </div>
                </div>
            </div>
           <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale border-2 border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-gray-100 text-gray-600">
                        <i class="fas fa-user-slash text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Inactifs</p>
                        <p class="text-2xl font-bold text-gray-900" id="totalInactifs">0</p>
                    </div>
                </div>
            </div>
            <!-- Administrateurs -->
           <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale border-2 border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                        <i class="fas fa-crown text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Admins</p>
                        <p class="text-2xl font-bold text-gray-900" id="totalAdmins">0</p>
                    </div>
                </div>
            </div>
        </div>
<!-- Section Tableau de Bord Avancé -->
<div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale stat-card-actifs">
    <h2 class="text-xl font-semibold mb-6">Tableau de Bord RH Avancé</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-blue-50 p-4 rounded-lg">
            <div class="flex items-center">
                <div class="p-2 rounded-full bg-blue-100 text-blue-600 mr-3">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-blue-600">Effectif Total</p>
                    <p class="text-2xl font-bold" id="totalEmployes">0</p>
                </div>
            </div>
        </div>

        <div class="bg-green-50 p-4 rounded-lg">
            <div class="flex items-center">
                <div class="p-2 rounded-full bg-green-100 text-green-600 mr-3">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-green-600">Taux de Présence</p>
                    <p class="text-2xl font-bold" id="tauxPresence">0%</p>
                </div>
            </div>
        </div>

        <div class="bg-purple-50 p-4 rounded-lg">
            <div class="flex items-center">
                <div class="p-2 rounded-full bg-purple-100 text-purple-600 mr-3">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-purple-600">Masse Salariale</p>
                    <p class="text-2xl font-bold" id="masseSalariale">0 FCFA</p>
                </div>
            </div>
        </div>

        <div class="bg-orange-50 p-4 rounded-lg">
            <div class="flex items-center">
                <div class="p-2 rounded-full bg-orange-100 text-orange-600 mr-3">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-orange-600">Retard Moyen</p>
                    <p class="text-2xl font-bold" id="retardMoyen">0 min</p>
                </div>
            </div>
        </div>
    </div>
<div class="bg-gray-50 p-4 rounded-lg">
    <h3 class="text-lg font-semibold mb-4">Générer un Rapport Personnalisé</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Type de Rapport</label>
            <select id="reportType" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                <option value="presences">Présences et Retards</option>
                <option value="salaires">Salaires et Coûts</option>
                <option value="effectifs">Effectifs et Démographie</option>
                <option value="turnover">Turnover et Rotation</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date Début</label>
            <input type="date" id="reportStartDate" class="w-full px-3 py-2 border border-gray-300 rounded-md">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Date Fin</label>
            <input type="date" id="reportEndDate" class="w-full px-3 py-2 border border-gray-300 rounded-md">
        </div>
    </div>
    <button onclick="generateCustomReport()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
        <i class="fas fa-file-export mr-2"></i>Générer le Rapport
    </button>

    <!-- Indicateur de chargement -->
    <div id="reportLoading" class="hidden mt-4 text-center">
        <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
        <p class="text-gray-600">Génération du rapport en cours...</p>
    </div>
</div>

<!-- Modal pour afficher les rapports -->
<div id="reportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-6xl w-full max-h-screen overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 id="reportModalTitle" class="text-lg font-semibold text-gray-900">Rapport Personnalisé</h3>
                <button onclick="closeReportModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <div id="reportContent" class="mb-6">
                    <!-- Le contenu du rapport sera chargé ici -->
                </div>
                <div class="flex justify-end space-x-3">
                    <button onclick="exportReportToPDF()" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        <i class="fas fa-file-pdf mr-2"></i>Exporter en PDF
                    </button>
                    <button onclick="exportReportToExcel()" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        <i class="fas fa-file-excel mr-2"></i>Exporter en Excel
                    </button>
                    <button onclick="closeReportModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="bg-white rounded-lg shadow-md p-6 mb-6 card-shadow border-2 border-gray-200">
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <!-- Champ de recherche -->
        <div>
            <input type="text" id="searchInput" placeholder="Rechercher par nom, email..."
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Filtre département -->
        <div>
            <select id="filterDepartement" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Tous les départements</option>
            </select>
        </div>

        <!-- Filtre poste -->
        <div>
            <select id="filterPoste" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Tous les postes</option>
            </select>
        </div>

        <!-- Filtre contrat -->
        <div>
            <select id="filterContrat" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
            <select id="filterStatut" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Tous les statuts</option>
                <option value="actif">Actif</option>
                <option value="en_conge">En congé</option>
                <option value="absent">Absent</option>
                <option value="inactif">Inactif</option>
            </select>
        </div>

        <!-- NOUVEAU: Boutons d'action -->
        <div class="flex space-x-2">
            <button onclick="applyFilters()" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition duration-200">
                <i class="fas fa-search mr-2"></i>Filtrer
            </button>
            <button onclick="resetFilters()" class="px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition duration-200" title="Réinitialiser">
                <i class="fas fa-undo"></i>
            </button>
        </div>
    </div>
</div>

    <div class="bg-white rounded-lg shadow-md p-6 card-shadow hover-scale stat-card-actifs">
    <h2 class="text-xl font-semibold mb-6">Génération des Bulletins de Paie</h2>
    <p class="text-gray-600 mb-4">Gérez la paie, primes, congés et générez les bulletins professionnels.</p>
    <a href="gestion_paie.php" class="bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition-colors inline-flex items-center">
        <i class="fas fa-calculator mr-2"></i>Accéder à la Gestion de Paie
    </a>
</div>

<!-- 6. MODIFICATION DE LA SECTION TABLEAU POUR AJOUTER LES BORDURES -->
<div id="tableView" class="bg-white rounded-lg shadow-md overflow-hidden card-shadow table-visible-borders border-2 border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Photo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employé</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Département</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poste</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contrat</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Présence</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Salaire</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Heures/Mois</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documents</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="employeesTableBody" class="bg-white divide-y divide-gray-200">
                <!-- Les employés seront chargés ici -->
            </tbody>
        </table>
    </div>
</div>

    </div>

    <!-- Modal Ajouter/Modifier Employé -->
    <div id="employeeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 id="modalTitle" class="text-lg font-semibold text-gray-900">Ajouter un employé</h3>
                </div>

                <form id="employeeForm" class="p-6" enctype="multipart/form-data">
                    <input type="hidden" id="employeeId" name="id">
                    <input type="hidden" name="ajax_action" id="ajaxAction" value="add_employee">

                    <!-- Photo de profil -->
                    <div class="mb-6 text-center">
                        <div class="relative inline-block">
                            <img id="photoPreview" src="uploads/photos/default-avatar.png"
                                class="w-24 h-24 rounded-full border-4 border-gray-200 object-cover">
                            <label for="photo" class="absolute bottom-0 right-0 bg-blue-600 text-white rounded-full p-2 cursor-pointer hover:bg-blue-700">
                                <i class="fas fa-camera text-sm"></i>
                                <input type="file" id="photo" name="photo" accept="image/*" class="hidden">
                            </label>
                        </div>
                    </div>

                    <!-- Informations personnelles -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="nom" class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                            <input type="text" id="nom" name="nom" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="prenom" class="block text-sm font-medium text-gray-700 mb-2">Prénom *</label>
                            <input type="text" id="prenom" name="prenom" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                            <input type="email" id="email" name="email" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="telephone" class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
        <input type="text" id="nationalite" name="nationalite"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
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
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Sélectionner un poste</option>
                            </select>
                        </div>

                        <div>
                            <label for="salaire" class="block text-sm font-medium text-gray-700 mb-2">
                                Salaire (FCFA)
                                <span id="salaireRange" class="text-xs text-gray-500"></span>
                            </label>
                            <input type="number" id="salaire" name="salaire" min="0" step="1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <div>
                            <label for="heureDebut" class="block text-sm font-medium text-gray-700 mb-2">Heure début</label>
                            <input type="time" id="heureDebut" name="heure_debut" value="08:00"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                                                <div>
                            <label for="heureFin" class="block text-sm font-medium text-gray-700 mb-2">Heure fin</label>
                            <input type="time" id="heureFin" name="heure_fin" value="17:00"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <!-- Statut et options -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="statut" class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                            <select id="statut" name="statut"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="actif">Actif</option>
                                <option value="en_conge">En congé</option>
                                <option value="absent">Absent</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center">
                                <input type="checkbox" id="isAdmin" name="is_admin" value="1"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
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

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-200">
                            Annuler
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-save mr-2"></i>Enregistrer
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
                        <i class="fas fa-spinner fa-spin text-blue-500 text-2xl mb-2"></i>
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
        return;
    }

    employeesList.forEach(employee => {
        const row = createEmployeeRow(employee);
        tbody.appendChild(row);
    });
    
    console.log(`${employeesList.length} lignes ajoutées au tableau`);
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
                presenceClass = 'bg-blue-100 text-blue-800';
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
                <i class="fas fa-clock ${employee.statut === 'inactif' ? 'text-gray-400' : 'text-blue-500'} mr-1"></i>
                <span class="font-medium ${employee.statut === 'inactif' ? 'text-gray-400' : 'text-blue-600'}">
                    ${employee.heures_par_mois || '0'}h
                </span>
            </div>
            <div class="text-xs text-gray-500">par mois</div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm">
            <div class="flex flex-col space-y-1">
                ${employee.cv ? `
                    <a href="uploads/documents/${employee.cv}" target="_blank"
                    class="text-blue-600 hover:text-blue-800 text-xs flex items-center">
                        <i class="fas fa-file-pdf mr-1 text-red-500"></i>CV
                    </a>
                ` : '<span class="text-red-500 text-xs">Manquant</span>'}
                ${employee.contrat ? `
                    <a href="uploads/documents/${employee.contrat}" target="_blank"
                    class="text-blue-600 hover:text-blue-800 text-xs flex items-center">
                        <i class="fas fa-file-contract mr-1 text-green-500"></i>Contrat
                    </a>
                ` : '<span class="text-red-500 text-xs">Manquant</span>'}
                ${employee.piece_identite ? `
                    <a href="uploads/documents/${employee.piece_identite}" target="_blank"
                    class="text-blue-600 hover:text-blue-800 text-xs flex items-center">
                        <i class="fas fa-id-card mr-1 text-purple-500"></i>Pièce ID
                    </a>
                ` : '<span class="text-red-500 text-xs">Manquant</span>'}
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
            <div class="flex space-x-2">
                <button onclick="viewEmployee(${employee.id})" class="text-blue-600 hover:text-blue-900" title="Voir détails">
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

// Fonctions de filtrage
function filterEmployees() {
    if (!employees || employees.length === 0) {
        console.log('Aucun employé à filtrer');
        return;
    }

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
    
    document.getElementById('modalTitle').textContent = 'Ajouter un employé';
    document.getElementById('employeeForm').reset();
    document.getElementById('employeeId').value = '';
    document.getElementById('ajaxAction').value = 'add_employee';
    document.getElementById('photoPreview').src = 'uploads/photos/default-avatar.png';
    
    modal.classList.remove('hidden');
}

function closeModal() {
    const modal = document.getElementById('employeeModal');
    if (modal) modal.classList.add('hidden');
}

function editEmployee(id) {
    const employee = employees.find(e => e.id == id);
    if (!employee) {
        showNotification('Employé non trouvé', 'error');
        return;
    }

    const modal = document.getElementById('employeeModal');
    if (!modal) return;

    document.getElementById('modalTitle').textContent = 'Modifier l\'employé';
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

// Fonctions CRUD employés
function saveEmployee(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    showNotification('Enregistrement en cours...', 'info');

    const action = formData.get('ajaxAction') || 'add_employee';
    const url = `${window.location.pathname}?action=${action}`;

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        hideNotification();
        if (data.success) {
            showNotification('Employé sauvegardé avec succès!', 'success');
            closeModal();
            loadEmployees();
            loadStatistics();
        } else {
            showNotification(data.message || 'Erreur lors de la sauvegarde', 'error');
        }
    })
    .catch(error => {
        hideNotification();
        console.error('Erreur:', error);
        showNotification('Erreur de communication avec le serveur', 'error');
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
        'parti': 'bg-blue-100 text-blue-800'
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
        'info': 'bg-blue-500'
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
</body>
</html>