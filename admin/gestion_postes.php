<?php
// gestion_postes.php - Gestion complète des postes (version FCFA améliorée)
require_once '../config.php';
require_once '../vendor/autoload.php'; // Pour TCPDF

// ====================================================================
// CONSTANTES ET CONFIGURATIONS
// ====================================================================
// Types de contrat disponibles
$types_contrat = [
    'CDI' => 'Contrat à Durée Indéterminée',
    'CDD' => 'Contrat à Durée Déterminée',
    'STAGE' => 'Stage',
    'APPRENTISSAGE' => 'Contrat d\'Apprentissage',
    'FREELANCE' => 'Freelance',
    'CONSULTANT' => 'Consultant',
    'SAISONNIER' => 'Contrat Saisonnier'
];
// Niveaux hiérarchiques
$niveaux_hierarchiques = [
    1 => 'Direction',
    2 => 'Encadrement supérieur',
    3 => 'Encadrement intermédiaire',
    4 => 'Agent de maîtrise',
    5 => 'Employé'
];

// ====================================================================
// FONCTIONS UTILITAIRES
// ====================================================================
/**
 * Envoie une réponse JSON et arrête l'exécution.
 */
function sendJsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Log une activité dans la base de données.
 */
function logActivity($conn, $action, $table, $id, $details) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$action, $table, $id, json_encode($details)]);
    } catch (Exception $e) {
        // Log silencieux en cas d'erreur
    }
}

/**
 * Génère un PDF de la liste des postes.
 */
function generatePostesPDF($conn) {
    try {
        $stmt = $conn->query("
            SELECT p.*,
            (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif') as nb_employes,
            ps.nom as poste_superieur_nom
            FROM postes p
            LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
            WHERE p.actif = TRUE
            ORDER BY p.niveau_hierarchique, p.nom
        ");
        $postes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pdf = new TCPDF();
        $pdf->SetCreator('Système de Gestion RH');
        $pdf->SetAuthor('Restaurant Management System');
        $pdf->SetTitle('Liste des Postes - ' . date('d/m/Y'));
        $pdf->SetSubject('Rapport des postes');
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'LISTE DES POSTES', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Générée le ' . date('d/m/Y à H:i'), 0, 1, 'C');
        $pdf->Ln(10);

        // Statistiques globales
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
        // En-têtes du tableau
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(40, 8, 'POSTE', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'TYPE CONTRAT', 1, 0, 'C', true);
        $pdf->Cell(15, 8, 'NIVEAU', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'SALAIRE (FCFA)', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'EMPLOYÉS', 1, 0, 'C', true);
        $pdf->Cell(50, 8, 'DESCRIPTION', 1, 1, 'C', true);
        // Données du tableau
        $pdf->SetFont('helvetica', '', 8);
        foreach ($postes as $poste) {
           // Dans la boucle foreach ($postes as $poste)
$pdf->Cell(40, 6, substr($poste['nom'], 0, 25), 1, 0, 'L');
$pdf->Cell(25, 6, $poste['type_contrat'] ?? 'CDI', 1, 0, 'C');
$pdf->Cell(15, 6, $poste['niveau_hierarchique'] ?? '5', 1, 0, 'C');
$pdf->Cell(20, 6, $poste['code_paie'] ?? '-', 1, 0, 'C'); // Nouveau champ
$pdf->Cell(30, 6, number_format($poste['salaire'], 0, ',', ' '), 1, 0, 'R');
$pdf->Cell(20, 6, $poste['nb_employes'], 1, 0, 'C');
$pdf->Cell(50, 6, substr($poste['description'] ?? '', 0, 30), 1, 1, 'L');

        }
        return $pdf->Output('postes_' . date('Y-m-d') . '.pdf', 'S');
    } catch (Exception $e) {
        throw new Exception('Erreur lors de la génération du PDF: ' . $e->getMessage());
    }
}

// ====================================================================
// TRAITEMENT DES REQUÊTES AJAX
// ====================================================================
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    switch ($action) {
        // --- AJOUTER UN POSTE ---
        case 'add_poste':
            try {
                if (empty($_POST['nom'])) {
                    throw new Exception('Le nom du poste est requis');
                }
                // Vérifier si le nom existe déjà
                $stmt = $conn->prepare("SELECT id FROM postes WHERE nom = ? AND actif = TRUE");
                $stmt->execute([$_POST['nom']]);
                if ($stmt->fetch()) {
                    throw new Exception('Un poste avec ce nom existe déjà');
                }
               $stmt = $conn->prepare("
    INSERT INTO postes (nom, description, salaire, couleur, type_contrat,
                      niveau_hierarchique, poste_superieur_id, competences_requises,
                      nombre_postes_prevus, duree_contrat, avantages, code_paie,
                      categorie_paie, regime_social, taux_cotisation)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $_POST['nom'],
    $_POST['description'] ?? null,
    intval($_POST['salaire'] ?? 0),
    $_POST['couleur'] ?? '#3B82F6',
    $_POST['type_contrat'] ?? 'CDI',
    intval($_POST['niveau_hierarchique'] ?? 5),
    !empty($_POST['poste_superieur_id']) ? $_POST['poste_superieur_id'] : null,
    $_POST['competences_requises'] ?? null,
    intval($_POST['nombre_postes_prevus'] ?? 1),
    $_POST['duree_contrat'] ?? null,
    $_POST['avantages'] ?? null,
    $_POST['code_paie'] ?? null,
    $_POST['categorie_paie'] ?? null,
    $_POST['regime_social'] ?? null,
    $_POST['taux_cotisation'] ?? null
]);

                $poste_id = $conn->lastInsertId();
                logActivity($conn, 'CREATE_POSTE', 'postes', $poste_id, ['nom' => $_POST['nom']]);
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Poste ajouté avec succès',
                    'poste_id' => $poste_id
                ]);
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;

        // --- MODIFIER UN POSTE ---
        case 'update_poste':
            try {
                if (empty($_POST['id']) || empty($_POST['nom'])) {
                    throw new Exception('ID et nom du poste requis');
                }
                $poste_id = $_POST['id'];
                // Vérifier si le nom existe déjà (pour un autre poste)
                $stmt = $conn->prepare("SELECT id FROM postes WHERE nom = ? AND id != ? AND actif = TRUE");
                $stmt->execute([$_POST['nom'], $poste_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Un poste avec ce nom existe déjà');
                }
               $stmt = $conn->prepare("
    UPDATE postes
    SET nom = ?, description = ?, salaire = ?, couleur = ?, type_contrat = ?,
        niveau_hierarchique = ?, poste_superieur_id = ?, competences_requises = ?,
        nombre_postes_prevus = ?, duree_contrat = ?, avantages = ?,
        code_paie = ?, categorie_paie = ?, regime_social = ?, taux_cotisation = ?
    WHERE id = ? AND actif = TRUE
");
$stmt->execute([
    $_POST['nom'],
    $_POST['description'] ?? null,
    intval($_POST['salaire'] ?? 0),
    $_POST['couleur'] ?? '#3B82F6',
    $_POST['type_contrat'] ?? 'CDI',
    intval($_POST['niveau_hierarchique'] ?? 5),
    !empty($_POST['poste_superieur_id']) ? $_POST['poste_superieur_id'] : null,
    $_POST['competences_requises'] ?? null,
    intval($_POST['nombre_postes_prevus'] ?? 1),
    $_POST['duree_contrat'] ?? null,
    $_POST['avantages'] ?? null,
    $_POST['code_paie'] ?? null,
    $_POST['categorie_paie'] ?? null,
    $_POST['regime_social'] ?? null,
    $_POST['taux_cotisation'] ?? null,
    $poste_id
]);

                if ($stmt->rowCount() === 0) {
                    throw new Exception('Poste non trouvé ou non modifiable');
                }
                logActivity($conn, 'UPDATE_POSTE', 'postes', $poste_id, ['nom' => $_POST['nom']]);
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Poste modifié avec succès'
                ]);
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;

        // --- SUPPRIMER UN POSTE ---
        case 'delete_poste':
            try {
                $input = json_decode(file_get_contents('php://input'), true);
                if (empty($input['id'])) {
                    throw new Exception('ID poste requis');
                }
                $poste_id = $input['id'];
                // Vérifier si des employés sont associés à ce poste
                $stmt = $conn->prepare("SELECT COUNT(*) FROM employes WHERE poste_id = ? AND statut != 'inactif'");
                $stmt->execute([$poste_id]);
                $nb_employees = $stmt->fetchColumn();
                if ($nb_employees > 0) {
                    throw new Exception("Impossible de supprimer ce poste car $nb_employees employé(s) y sont associé(s)");
                }
                // Vérifier s'il y a des postes subordonnés
                $stmt = $conn->prepare("SELECT COUNT(*) FROM postes WHERE poste_superieur_id = ? AND actif = TRUE");
                $stmt->execute([$poste_id]);
                $nb_subordonnes = $stmt->fetchColumn();
                if ($nb_subordonnes > 0) {
                    throw new Exception("Impossible de supprimer ce poste car $nb_subordonnes poste(s) en dépendent hiérarchiquement");
                }
                // Désactivation logique du poste
                $stmt = $conn->prepare("UPDATE postes SET actif = FALSE WHERE id = ?");
                $stmt->execute([$poste_id]);
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Poste non trouvé');
                }
                logActivity($conn, 'DELETE_POSTE', 'postes', $poste_id, ['actif' => false]);
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Poste supprimé avec succès'
                ]);
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;

        // --- RÉCUPÉRER LES POSTES ---
        case 'get_postes':
            try {
                $stmt = $conn->query("
                    SELECT p.*, ps.nom as poste_superieur_nom
                    FROM postes p
                    LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
                    WHERE p.actif = TRUE
                    ORDER BY p.niveau_hierarchique, p.nom
                ");
                $postes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                sendJsonResponse([
                    'success' => true,
                    'postes' => $postes
                ]);
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors du chargement des postes'
                ]);
            }
            break;

        // --- RECHERCHE ET FILTRAGE AVANCÉ ---
        case 'search_postes':
            try {
                $search = $_POST['search'] ?? '';
                $salaire_min = $_POST['salaire_min'] ?? null;
                $salaire_max = $_POST['salaire_max'] ?? null;
                $type_contrat = $_POST['type_contrat'] ?? '';
                $niveau_hierarchique = $_POST['niveau_hierarchique'] ?? '';

                $sql = "SELECT p.*,
                        (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif') as nb_employes,
                        ps.nom as poste_superieur_nom
                        FROM postes p
                        LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
                        WHERE p.actif = TRUE";

                $params = [];
                if (!empty($search)) {
                    $sql .= " AND (p.nom LIKE ? OR p.description LIKE ? OR p.competences_requises LIKE ?)";
                    $params[] = "%$search%";
                    $params[] = "%$search%";
                    $params[] = "%$search%";
                }
                if (!empty($salaire_min)) {
                    $sql .= " AND p.salaire >= ?";
                    $params[] = $salaire_min;
                }
                if (!empty($salaire_max)) {
                    $sql .= " AND p.salaire <= ?";
                    $params[] = $salaire_max;
                }
                if (!empty($type_contrat)) {
                    $sql .= " AND p.type_contrat = ?";
                    $params[] = $type_contrat;
                }
                if (!empty($niveau_hierarchique)) {
                    $sql .= " AND p.niveau_hierarchique = ?";
                    $params[] = $niveau_hierarchique;
                }
                $sql .= " ORDER BY p.niveau_hierarchique, p.nom";

                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $postes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                sendJsonResponse([
                    'success' => true,
                    'postes' => $postes
                ]);
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;

        // --- STATISTIQUES AVANCÉES ---
        case 'get_stats':
            try {
                $stats = [];
                // Statistiques générales
                $stmt = $conn->query("SELECT COUNT(*) FROM postes WHERE actif = TRUE");
                $stats['total_postes'] = $stmt->fetchColumn();
                // Répartition par type de contrat
                $stmt = $conn->query("
                    SELECT type_contrat, COUNT(*) as nb_postes
                    FROM postes
                    WHERE actif = TRUE
                    GROUP BY type_contrat
                ");
                $stats['repartition_contrats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // Répartition par niveau hiérarchique
                $stmt = $conn->query("
                    SELECT niveau_hierarchique, COUNT(*) as nb_postes
                    FROM postes
                    WHERE actif = TRUE
                    GROUP BY niveau_hierarchique
                    ORDER BY niveau_hierarchique
                ");
                $stats['repartition_niveaux'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // Nombre d'employés par poste
                $stmt = $conn->query("
                    SELECT p.nom, COUNT(e.id) as nb_employes, p.nombre_postes_prevus
                    FROM postes p
                    LEFT JOIN employes e ON p.id = e.poste_id AND e.statut = 'actif'
                    WHERE p.actif = TRUE
                    GROUP BY p.id, p.nom, p.nombre_postes_prevus
                ");
                $stats['employes_par_poste'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // Statistiques salariales
                $stmt = $conn->query("
                    SELECT
                        AVG(salaire) as moyenne_salaire,
                        MIN(salaire) as minimum,
                        MAX(salaire) as maximum,
                        type_contrat
                    FROM postes
                    WHERE actif = TRUE AND salaire > 0
                    GROUP BY type_contrat
                ");
                $stats['salaires_par_contrat'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // Postes sous-dotés
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
                sendJsonResponse([
                    'success' => true,
                    'stats' => $stats
                ]);
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;

        // --- ORGANIGRAMME ---
        case 'get_organigramme':
            try {
                $stmt = $conn->query("
                    SELECT p.*, ps.nom as poste_superieur_nom,
                           (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif') as nb_employes
                    FROM postes p
                    LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
                    WHERE p.actif = TRUE
                    ORDER BY p.niveau_hierarchique, p.nom
                ");
                $postes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Initialiser un tableau pour stocker les postes par ID
                $postes_par_id = [];
                foreach ($postes as $poste) {
                    $postes_par_id[$poste['id']] = $poste;
                    $postes_par_id[$poste['id']]['enfants'] = [];
                }

                // Construire l'arbre hiérarchique
                $arbre = [];
                foreach ($postes as $poste) {
                    if (!empty($poste['poste_superieur_id']) && isset($postes_par_id[$poste['poste_superieur_id']])) {
                        // Ajouter le poste comme enfant de son supérieur
                        $postes_par_id[$poste['poste_superieur_id']]['enfants'][] = &$postes_par_id[$poste['id']];
                    } else {
                        // Ajouter à la racine de l'arbre si pas de supérieur
                        $arbre[] = &$postes_par_id[$poste['id']];
                    }
                }

                sendJsonResponse([
                    'success' => true,
                    'organigramme' => $arbre
                ]);
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;

        // --- DUPLIQUER UN POSTE ---
        case 'duplicate_poste':
            try {
                $input = json_decode(file_get_contents('php://input'), true);
                if (empty($input['id'])) {
                    throw new Exception('ID poste requis');
                }
                // Récupérer le poste original
                $stmt = $conn->prepare("SELECT * FROM postes WHERE id = ? AND actif = TRUE");
                $stmt->execute([$input['id']]);
                $original = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$original) {
                    throw new Exception('Poste non trouvé');
                }
                // Créer le nouveau nom
                $nouveau_nom = $original['nom'] . ' (Copie)';
                $counter = 1;
                // Vérifier l'unicité du nom
                while (true) {
                    $stmt = $conn->prepare("SELECT id FROM postes WHERE nom = ? AND actif = TRUE");
                    $stmt->execute([$nouveau_nom]);
                    if (!$stmt->fetch()) break;
                    $counter++;
                    $nouveau_nom = $original['nom'] . ' (Copie ' . $counter . ')';
                }
                // Insérer la copie
                $stmt = $conn->prepare("
                    INSERT INTO postes (nom, description, salaire, couleur, type_contrat,
                                      niveau_hierarchique, poste_superieur_id, competences_requises,
                                      nombre_postes_prevus, duree_contrat, avantages)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $nouveau_nom,
                    $original['description'],
                    $original['salaire'],
                    $original['couleur'],
                    $original['type_contrat'],
                    $original['niveau_hierarchique'],
                    $original['poste_superieur_id'],
                    $original['competences_requises'],
                    $original['nombre_postes_prevus'],
                    $original['duree_contrat'],
                    $original['avantages']
                ]);
                $nouveau_id = $conn->lastInsertId();
                logActivity($conn, 'DUPLICATE_POSTE', 'postes', $nouveau_id, [
                    'original_id' => $input['id'],
                    'nouveau_nom' => $nouveau_nom
                ]);
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Poste dupliqué avec succès',
                    'nouveau_id' => $nouveau_id
                ]);
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;

        default:
            sendJsonResponse([
                'success' => false,
                'message' => 'Action non reconnue'
            ]);
    }
}

// --- EXPORT PDF ---
if (isset($_GET['action']) && $_GET['action'] === 'export_postes_pdf' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdfContent = generatePostesPDF($conn);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="postes_' . date('Y-m-d') . '.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        logActivity($conn, 'EXPORT_POSTES_PDF', 'postes', null, ['date' => date('Y-m-d H:i:s')]);
        echo $pdfContent;
        exit;
    } catch (Exception $e) {
        die('Erreur lors de l\'export PDF: ' . $e->getMessage());
    }
}

// ====================================================================
// CHARGEMENT DES DONNÉES POUR L'AFFICHAGE
// ====================================================================
try {
    $stmt = $conn->query("
        SELECT p.*,
        (SELECT COUNT(*) FROM employes e WHERE e.poste_id = p.id AND e.statut = 'actif') as nb_employes,
        ps.nom as poste_superieur_nom
        FROM postes p
        LEFT JOIN postes ps ON p.poste_superieur_id = ps.id
        WHERE p.actif = TRUE
        ORDER BY p.niveau_hierarchique, p.nom
    ");
    $postes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Récupérer tous les postes pour les listes déroulantes
    $stmt = $conn->query("SELECT id, nom FROM postes WHERE actif = TRUE ORDER BY niveau_hierarchique, nom");
    $tous_postes = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .notification.show {
            opacity: 1;
        }
        .loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
        }
        .organigramme-node {
            transition: transform 0.2s ease;
        }
        .organigramme-node:hover {
            transform: scale(1.05);
        }
        .niveau-1 { border-left: 4px solid #ef4444; }
        .niveau-2 { border-left: 4px solid #f97316; }
        .niveau-3 { border-left: 4px solid #eab308; }
        .niveau-4 { border-left: 4px solid #22c55e; }
        .niveau-5 { border-left: 4px solid #3b82f6; }
        .tab-active {
            background-color: #3b82f6;
            color: white;
        }
        .tab-inactive {
            background-color: #f3f4f6;
            color: #6b7280;
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
                        <i class="fas fa-forecast mr-2"></i>Prévisions
                    </button>
                </nav>
            </div>
        </div>

        <!-- Contenu de l'onglet Liste des Postes -->
        <div id="content-postes">
            <!-- Barre de recherche et filtres avancés -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                        <input type="text" id="searchInput" placeholder="Nom, description, compétences..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type de contrat</label>
                        <select id="typeContratFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Tous les types</option>
                            <?php foreach ($types_contrat as $code => $libelle): ?>
                                <option value="<?php echo $code; ?>"><?php echo $libelle; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Niveau hiérarchique</label>
                        <select id="niveauFilter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Tous les niveaux</option>
                            <?php foreach ($niveaux_hierarchiques as $niveau => $libelle): ?>
                                <option value="<?php echo $niveau; ?>"><?php echo $niveau; ?> - <?php echo $libelle; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salaire</label>
                        <div class="flex space-x-2">
                            <input type="number" id="salaireMinFilter" placeholder="Min"
                                   class="w-20 px-2 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <input type="number" id="salaireMaxFilter" placeholder="Max"
                                   class="w-20 px-2 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
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
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
    <i class="fas fa-money-bill-wave mr-1"></i>
    Code paie: <?php echo htmlspecialchars($poste['code_paie'] ?? 'Non défini'); ?>
</div>
<div class="text-xs text-gray-500">
    <i class="fas fa-percentage mr-1"></i>
    Cotisation: <?php echo isset($poste['taux_cotisation']) ? $poste['taux_cotisation'] . '%' : 'Non défini'; ?>
</div>

                        <div class="text-center mb-4">
                            <span class="text-gray-500 text-sm">Salaire:</span>
                            <div class="font-medium text-green-600 text-lg"><?php echo number_format($poste['salaire'], 0, ',', ' '); ?> FCFA</div>
                            <?php if (!empty($poste['duree_contrat'])): ?>
                                <div class="text-xs text-gray-500">Durée: <?php echo htmlspecialchars($poste['duree_contrat']); ?></div>
                            <?php endif; ?>
                        </div>
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
                                    <?php echo $niveaux_hierarchiques[$poste['niveau_hierarchique'] ?? 5] ?? 'Non défini'; ?>
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
                                    <?php foreach ($types_contrat as $code => $libelle): ?>
                                        <option value="<?php echo $code; ?>"><?php echo $libelle; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="niveau_hierarchique" class="block text-sm font-medium text-gray-700 mb-2">Niveau hiérarchique</label>
                                <select id="niveau_hierarchique" name="niveau_hierarchique"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <?php foreach ($niveaux_hierarchiques as $niveau => $libelle): ?>
                                        <option value="<?php echo $niveau; ?>"><?php echo $niveau; ?> - <?php echo $libelle; ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                        <!-- Dans la section "Informations de base" -->
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

    <script>
        let postes = <?php echo json_encode($postes); ?>;
        let currentAction = null;
        let currentTab = 'postes';
        let charts = {};

        // ============================================================
        // FONCTIONS UTILITAIRES
        // ============================================================
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const notificationText = document.getElementById('notificationText');
            notificationText.textContent = message;
            notification.className = `notification ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white px-6 py-3 rounded-lg shadow-lg show`;
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }

        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatNumber(number) {
            return new Intl.NumberFormat('fr-FR').format(number);
        }

        // ============================================================
        // GESTION DES ONGLETS
        // ============================================================
        function showTab(tabName) {
            const contents = ['postes', 'organigramme', 'previsions'];
            contents.forEach(content => {
                document.getElementById(`content-${content}`).classList.add('hidden');
                const tab = document.getElementById(`tab-${content}`);
                if (tab) {
                    tab.classList.remove('tab-active');
                    tab.classList.add('tab-inactive');
                }
            });
            document.getElementById(`content-${tabName}`).classList.remove('hidden');
            const activeTab = document.getElementById(`tab-${tabName}`);
            if (activeTab) {
                activeTab.classList.remove('tab-inactive');
                activeTab.classList.add('tab-active');
            }
            currentTab = tabName;
            if (tabName === 'organigramme') {
                loadOrganigramme();
            } else if (tabName === 'previsions') {
                loadPrevisions();
            }
        }

        // ============================================================
        // FONCTIONS MODAL PRINCIPAL
        // ============================================================
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Ajouter un poste';
            document.getElementById('posteForm').reset();
            document.getElementById('posteId').value = '';
            document.getElementById('couleur').value = '#3B82F6';
            document.getElementById('type_contrat').value = 'CDI';
            document.getElementById('niveau_hierarchique').value = '5';
            document.getElementById('nombre_postes_prevus').value = '1';
            document.getElementById('posteModal').classList.remove('hidden');
            document.getElementById('nom').focus();
        }

        function editPoste(id) {
            const poste = postes.find(p => p.id == id);
            if (!poste) {
                showNotification('Poste non trouvé', 'error');
                return;
            }
            document.getElementById('modalTitle').textContent = 'Modifier le poste';
            document.getElementById('posteId').value = poste.id;
            document.getElementById('nom').value = poste.nom;
            document.getElementById('description').value = poste.description || '';
            document.getElementById('salaire').value = poste.salaire || '';
            document.getElementById('couleur').value = poste.couleur;
            document.getElementById('type_contrat').value = poste.type_contrat || 'CDI';
            document.getElementById('niveau_hierarchique').value = poste.niveau_hierarchique || '5';
            document.getElementById('poste_superieur_id').value = poste.poste_superieur_id || '';
            document.getElementById('competences_requises').value = poste.competences_requises || '';
            document.getElementById('nombre_postes_prevus').value = poste.nombre_postes_prevus || '1';
            document.getElementById('duree_contrat').value = poste.duree_contrat || '';
            document.getElementById('avantages').value = poste.avantages || '';
            document.getElementById('posteModal').classList.remove('hidden');
            document.getElementById('nom').focus();
        }

        function closeModal() {
            document.getElementById('posteModal').classList.add('hidden');
        }

        // ============================================================
        // FONCTIONS MODAL CONFIRMATION
        // ============================================================
        function openConfirmModal(message, action) {
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('confirmModal').classList.remove('hidden');
            currentAction = action;
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.add('hidden');
            currentAction = null;
        }

        // ============================================================
        // FONCTIONS CRUD
        // ============================================================
        function deletePoste(id) {
            const poste = postes.find(p => p.id == id);
            if (!poste) return;
            openConfirmModal(
                `Êtes-vous sûr de vouloir supprimer le poste "${poste.nom}" ?`,
                () => {
                    showLoading();
                    fetch('?action=delete_poste', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({id: id})
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            showNotification(data.message);
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        showNotification('Erreur de connexion: ' + error.message, 'error');
                    });
                    closeConfirmModal();
                }
            );
        }

        function duplicatePoste(id) {
            const poste = postes.find(p => p.id == id);
            if (!poste) return;
            openConfirmModal(
                `Voulez-vous dupliquer le poste "${poste.nom}" ?`,
                () => {
                    showLoading();
                    fetch('?action=duplicate_poste', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({id: id})
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            showNotification(data.message);
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        showNotification('Erreur de connexion: ' + error.message, 'error');
                    });
                    closeConfirmModal();
                }
            );
        }

        // ============================================================
        // FONCTIONS RECHERCHE ET FILTRAGE
        // ============================================================
        function applyFilters() {
            const search = document.getElementById('searchInput').value.trim();
            const salaireMin = document.getElementById('salaireMinFilter').value;
            const salaireMax = document.getElementById('salaireMaxFilter').value;
            const typeContrat = document.getElementById('typeContratFilter').value;
            const niveau = document.getElementById('niveauFilter').value;
            showLoading();
            const formData = new FormData();
            formData.append('search', search);
            formData.append('salaire_min', salaireMin);
            formData.append('salaire_max', salaireMax);
            formData.append('type_contrat', typeContrat);
            formData.append('niveau_hierarchique', niveau);
            fetch('?action=search_postes', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
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
            document.getElementById('salaireMinFilter').value = '';
            document.getElementById('salaireMaxFilter').value = '';
            document.getElementById('typeContratFilter').value = '';
            document.getElementById('niveauFilter').value = '';
            location.reload();
        }

        function updatePostesGrid(postesData) {
            const grid = document.getElementById('postesGrid');
            const noResults = document.getElementById('noResults');
            if (postesData.length === 0) {
                grid.classList.add('hidden');
                noResults.classList.remove('hidden');
                return;
            }
            grid.classList.remove('hidden');
            noResults.classList.add('hidden');
            const niveauxLabels = {
                1: 'Direction', 2: 'Encadrement supérieur', 3: 'Encadrement intermédiaire',
                4: 'Agent de maîtrise', 5: 'Employé'
            };
            grid.innerHTML = '';
            postesData.forEach(poste => {
                const card = document.createElement('div');
                card.className = `bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow niveau-${poste.niveau_hierarchique || 5} border border-gray-200`;
                card.innerHTML = `
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-4 h-4 rounded-full mr-3" style="background-color: ${poste.couleur}"></div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">${escapeHtml(poste.nom)}</h3>
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
                        <p class="text-gray-600 text-sm min-h-[40px]">${escapeHtml(poste.description || 'Aucune description')}</p>
                        ${poste.competences_requises ? `
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-star mr-1"></i>Compétences: ${escapeHtml(poste.competences_requises.substring(0, 50))}...
                            </div>
                        ` : ''}
                        ${poste.poste_superieur_nom ? `
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-level-up-alt mr-1"></i>Rapporte à: ${escapeHtml(poste.poste_superieur_nom)}
                            </div>
                        ` : ''}
                        ${poste.avantages ? `
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-gift mr-1"></i>Avantages: ${escapeHtml(poste.avantages.substring(0, 50))}...
                            </div>
                        ` : ''}
                    </div>
                    <div class="text-center mb-4">
                        <span class="text-gray-500 text-sm">Salaire:</span>
                        <div class="font-medium text-green-600 text-lg">${formatNumber(poste.salaire || 0)} FCFA</div>
                        ${poste.duree_contrat ? `<div class="text-xs text-gray-500">Durée: ${escapeHtml(poste.duree_contrat)}</div>` : ''}
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
                                ${niveauxLabels[poste.niveau_hierarchique] || 'Non défini'}
                            </span>
                        </div>
                    </div>
                `;
                grid.appendChild(card);
            });
        }

        // ============================================================
        // FONCTIONS ORGANIGRAMME
        // ============================================================
        function loadOrganigramme() {
            showLoading();
            fetch('?action=get_organigramme', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        renderOrganigramme(data.organigramme);
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
                    hideLoading();
                    const container = document.getElementById('organigrammeContainer');
                    container.innerHTML = `
                        <div class="text-center text-gray-500 py-8">
                            <i class="fas fa-exclamation-triangle text-4xl mb-2"></i>
                            <p>Erreur de connexion : ${error.message}</p>
                        </div>
                    `;
                });
        }

        function renderOrganigramme(arbre) {
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
                container.appendChild(createPosteNode(poste));
            });
        }

        function createPosteNode(poste) {
            const node = document.createElement('div');
            node.className = 'organigramme-node mb-4';

            const card = document.createElement('div');
            card.className = `bg-white border-l-4 rounded-lg p-4 shadow-md niveau-${poste.niveau_hierarchique}`;
            card.style.borderColor = poste.couleur;
            card.innerHTML = `
                <div class="flex justify-between items-center">
                    <div>
                        <h4 class="font-semibold text-gray-900">${escapeHtml(poste.nom)}</h4>
                        <p class="text-sm text-gray-600">${poste.type_contrat || 'CDI'}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium text-green-600">${formatNumber(poste.salaire || 0)} FCFA</div>
                        <div class="text-xs text-gray-500">${poste.nb_employes || 0} employé(s)</div>
                    </div>
                </div>
                ${poste.description ? `<p class="text-xs text-gray-500 mt-2">${escapeHtml(poste.description.substring(0, 100))}${poste.description.length > 100 ? '...' : ''}</p>` : ''}
            `;

            if (poste.enfants && poste.enfants.length > 0) {
                const childrenContainer = document.createElement('div');
                childrenContainer.className = 'children-container ml-6 mt-2 border-l-2 border-gray-200 pl-4';
                poste.enfants.forEach(enfant => {
                    childrenContainer.appendChild(createPosteNode(enfant));
                });
                node.appendChild(childrenContainer);
            }

            return node;
        }

        // ============================================================
        // FONCTIONS PRÉVISIONS
        // ============================================================
        function loadPrevisions() {
            showLoading();
            fetch('?action=get_stats', { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        renderPostesSousDotes(data.stats.postes_sous_dotes);
                        calculateCoutsPrevisionnels(data.stats.employes_par_poste);
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
                    hideLoading();
                    const sousDotesContainer = document.getElementById('postesSousDotesList');
                    sousDotesContainer.innerHTML = `
                        <div class="text-center text-gray-500 py-4">
                            <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                            <p>Erreur de connexion : ${error.message}</p>
                        </div>
                    `;
                });
        }

        function renderPostesSousDotes(postesSousDotes) {
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
                        <h4 class="font-medium text-red-900">${escapeHtml(poste.nom)}</h4>
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
        }

        function calculateCoutsPrevisionnels(employesParPoste) {
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
            const diffElement = document.getElementById('coutDifference');
            diffElement.textContent = (difference >= 0 ? '+' : '') + formatNumber(difference) + ' FCFA';
            diffElement.className = difference > 0 ? 'text-2xl font-bold text-red-600' :
                                   difference < 0 ? 'text-2xl font-bold text-green-600' :
                                   'text-2xl font-bold text-gray-600';
        }
function validatePosteForm() {
    const nom = document.getElementById('nom').value.trim();
    const salaire = parseInt(document.getElementById('salaire').value) || 0;
    const nombrePostes = parseInt(document.getElementById('nombre_postes_prevus').value) || 1;
    const tauxCotisation = parseFloat(document.getElementById('taux_cotisation').value) || 0;

    if (nom.length < 2) {
        showNotification('Le nom du poste doit contenir au moins 2 caractères', 'error');
        return false;
    }
    if (salaire < 0) {
        showNotification('Le salaire ne peut pas être négatif', 'error');
        return false;
    }
    if (nombrePostes < 1) {
        showNotification('Le nombre de postes prévus doit être au moins de 1', 'error');
        return false;
    }
    if (tauxCotisation < 0 || tauxCotisation > 100) {
        showNotification('Le taux de cotisation doit être compris entre 0 et 100%', 'error');
        return false;
    }
    return true;
}

        // ============================================================
        // FONCTIONS EXPORT
        // ============================================================
        function exportPostesPDF() {
            showLoading();
            const link = document.createElement('a');
            link.href = '?action=export_postes_pdf';
            link.download = `postes_${new Date().toISOString().split('T')[0]}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            setTimeout(() => {
                hideLoading();
                showNotification('Export PDF terminé avec succès');
            }, 1000);
        }

        // ============================================================
        // VALIDATION DU FORMULAIRE
        // ============================================================
        function validatePosteForm() {
            const nom = document.getElementById('nom').value.trim();
            const salaire = parseInt(document.getElementById('salaire').value) || 0;
            const nombrePostes = parseInt(document.getElementById('nombre_postes_prevus').value) || 1;
            if (nom.length < 2) {
                showNotification('Le nom du poste doit contenir au moins 2 caractères', 'error');
                return false;
            }
            if (salaire < 0) {
                showNotification('Le salaire ne peut pas être négatif', 'error');
                return false;
            }
            if (nombrePostes < 1) {
                showNotification('Le nombre de postes prévus doit être au moins de 1', 'error');
                return false;
            }
            return true;
        }

        // ============================================================
        // GESTIONNAIRES D'ÉVÉNEMENTS
        // ============================================================
        document.getElementById('posteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            if (!validatePosteForm()) return;
            showLoading();
            const formData = new FormData(e.target);
            const isEdit = formData.get('id') !== '';
            const action = isEdit ? 'update_poste' : 'add_poste';
            fetch('?action=' + action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showNotification(data.message);
                    closeModal();
                    setTimeout(() => applyFilters(), 500);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Erreur de connexion: ' + error.message, 'error');
            });
        });

        document.getElementById('confirmButton').addEventListener('click', function() {
            if (currentAction) {
                currentAction();
            }
        });

        // ============================================================
        // INITIALISATION
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', debounce(applyFilters, 500));
            document.getElementById('typeContratFilter').addEventListener('change', applyFilters);
            document.getElementById('niveauFilter').addEventListener('change', applyFilters);
            document.getElementById('salaireMinFilter').addEventListener('input', debounce(applyFilters, 1000));
            document.getElementById('salaireMaxFilter').addEventListener('input', debounce(applyFilters, 1000));
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                    closeConfirmModal();
                }
            });
            document.getElementById('posteModal').addEventListener('click', function(e) {
                if (e.target === this) closeModal();
            });
            document.getElementById('confirmModal').addEventListener('click', function(e) {
                if (e.target === this) closeConfirmModal();
            });
            document.getElementById('nom').addEventListener('blur', function() {
                const nom = this.value.trim();
                if (nom.length > 0 && nom.length < 2) {
                    showNotification('Le nom du poste doit contenir au moins 2 caractères', 'error');
                    this.focus();
                }
            });
            document.getElementById('salaire').addEventListener('blur', function() {
                const salaire = parseInt(this.value) || 0;
                if (this.value && salaire < 0) {
                    showNotification('Le salaire ne peut pas être négatif', 'error');
                    this.value = 0;
                }
            });
            document.getElementById('nombre_postes_prevus').addEventListener('blur', function() {
                const nombre = parseInt(this.value) || 1;
                if (nombre < 1) {
                    showNotification('Le nombre de postes prévus doit être au moins de 1', 'error');
                    this.value = 1;
                }
            });
            showTab('postes');
        });
    </script>
</body>
</html>
