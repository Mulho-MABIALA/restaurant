<?php
require_once '../../config.php';
session_start();
function getEmployeDisplay($incident) {
    // Nom complet depuis la jointure
    if (!empty($incident['employe_nom']) && trim($incident['employe_nom']) !== '') {
        return trim($incident['employe_nom']);
    }
    
    // Email comme fallback
    if (!empty($incident['email'])) {
        return explode('@', $incident['email'])[0];
    }
    
    // ID en dernier recours
    return 'Utilisateur ' . ($incident['employe_id'] ?? 'inconnu');
}

// Debug temporaire - à supprimer en production
error_reporting(E_ALL);
ini_set('display_errors', 1);

$employe_id = $_SESSION['admin_id'];
$user_role = $_SESSION['role'] ?? 'user'; // user, manager, admin

// Gestion des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            $titre = $_POST['titre'];
            $description = $_POST['description'];
            $gravite = $_POST['gravite'];
            $categorie = $_POST['categorie'] ?? null;
            $departement = $_POST['departement'] ?? null;
            $tags = $_POST['tags'] ?? '';

            // Gestion du fichier uploadé
            $fichier_url = null;
            if (!empty($_FILES['fichier']['name'])) {
                $upload_dir = '../uploads/incidents/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $filename = basename($_FILES['fichier']['name']);
                $target_file = $upload_dir . time() . '_' . $filename;

                if (move_uploaded_file($_FILES['fichier']['tmp_name'], $target_file)) {
                    $fichier_url = $target_file;
                }
            }

            // Vérifier si les colonnes existent avant de les utiliser
            try {
                $columns = $conn->query("SHOW COLUMNS FROM incidents")->fetchAll(PDO::FETCH_COLUMN);
                
                if (in_array('statut', $columns) && in_array('categorie', $columns)) {
                    // Version complète avec nouvelles colonnes
                    $stmt = $conn->prepare("INSERT INTO incidents (employe_id, titre, description, gravite, categorie, departement, tags, fichier_url, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'nouveau')");
                    $stmt->execute([$employe_id, $titre, $description, $gravite, $categorie, $departement, $tags, $fichier_url]);
                } else {
                    // Version basique si colonnes manquantes
                    $stmt = $conn->prepare("INSERT INTO incidents (employe_id, titre, description, gravite, fichier_url) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$employe_id, $titre, $description, $gravite, $fichier_url]);
                }
                
                $incident_id = $conn->lastInsertId();
                
                // Ajouter dans l'historique si la table existe
                $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                if (in_array('incident_historique', $tables)) {
                    $stmt = $conn->prepare("INSERT INTO incident_historique (incident_id, employe_id, action, commentaire) VALUES (?, ?, 'création', 'Incident créé')");
                    $stmt->execute([$incident_id, $employe_id]);
                }
            } catch (Exception $e) {
                error_log("Erreur création incident: " . $e->getMessage());
                echo "<script>alert('Erreur lors de la création: " . addslashes($e->getMessage()) . "');</script>";
            }
            
            break;
            
        case 'update_status':
            if ($user_role !== 'user') {
                $incident_id = $_POST['incident_id'];
                $nouveau_statut = $_POST['statut'];
                $commentaire = $_POST['commentaire'] ?? '';
                $assigne_a = $_POST['assigne_a'] ?? null;
                
                try {
                    $columns = $conn->query("SHOW COLUMNS FROM incidents")->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (in_array('statut', $columns)) {
                        $stmt = $conn->prepare("UPDATE incidents SET statut = ?, assigne_a = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$nouveau_statut, $assigne_a, $incident_id]);
                        
                        // Ajouter dans l'historique
                        $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                        if (in_array('incident_historique', $tables)) {
                            $stmt = $conn->prepare("INSERT INTO incident_historique (incident_id, employe_id, action, commentaire) VALUES (?, ?, 'mise_à_jour', ?)");
                            $stmt->execute([$incident_id, $employe_id, "Statut changé vers: $nouveau_statut. $commentaire"]);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Erreur mise à jour statut: " . $e->getMessage());
                }
            }
            break;
            
        case 'add_comment':
            $incident_id = $_POST['incident_id'];
            $commentaire = $_POST['commentaire'];
            
            try {
                $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                if (in_array('incident_historique', $tables)) {
                    $stmt = $conn->prepare("INSERT INTO incident_historique (incident_id, employe_id, action, commentaire) VALUES (?, ?, 'commentaire', ?)");
                    $stmt->execute([$incident_id, $employe_id, $commentaire]);
                }
            } catch (Exception $e) {
                error_log("Erreur ajout commentaire: " . $e->getMessage());
            }
            break;
    }
    
    header('Location: incidents.php');
    exit;
}

// Vérifier les colonnes et tables disponibles
$available_columns = [];
$available_tables = [];
try {
    $available_columns = $conn->query("SHOW COLUMNS FROM incidents")->fetchAll(PDO::FETCH_COLUMN);
    $available_tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    // Debug
    error_log("Colonnes incidents: " . implode(', ', $available_columns));
    error_log("Tables disponibles: " . implode(', ', $available_tables));
} catch (Exception $e) {
    error_log("Erreur colonnes/tables: " . $e->getMessage());
}

// Construire la requête SELECT selon les colonnes disponibles
$select_fields = "i.id, i.employe_id, i.titre, i.description, i.gravite, i.created_at";

// Ajouter les colonnes optionnelles si elles existent
if (in_array('fichier_url', $available_columns)) {
    $select_fields .= ", i.fichier_url";
}
if (in_array('statut', $available_columns)) {
    $select_fields .= ", i.statut";
}
if (in_array('categorie', $available_columns)) {
    $select_fields .= ", i.categorie";
}
if (in_array('departement', $available_columns)) {
    $select_fields .= ", i.departement";
}
if (in_array('tags', $available_columns)) {
    $select_fields .= ", i.tags";
}
if (in_array('assigne_a', $available_columns)) {
    $select_fields .= ", i.assigne_a";
}
if (in_array('updated_at', $available_columns)) {
    $select_fields .= ", i.updated_at";
}

// Construire les jointures
$joins = "FROM incidents i";

// Jointure avec employes si la table existe
if (in_array('employes', $available_tables)) {
    $joins .= " LEFT JOIN employes e ON i.employe_id = e.id";
    $select_fields .= ", TRIM(CONCAT(COALESCE(e.prenom, ''), ' ', COALESCE(e.nom, ''))) as employe_nom";
    $select_fields .= ", e.email";
    
    // Jointure pour l'assigné si la colonne existe
    if (in_array('assigne_a', $available_columns)) {
        $joins .= " LEFT JOIN employes e2 ON i.assigne_a = e2.id";
        $select_fields .= ", e2.nom as assigne_nom";
    }
} else {
    // Si pas de table employes, utiliser l'ID
    $select_fields .= ", CONCAT('Utilisateur ', i.employe_id) as employe_nom";
}

// Filtres
$where_conditions = [];
$params = [];

if (!empty($_GET['gravite'])) {
    $where_conditions[] = "i.gravite = ?";
    $params[] = $_GET['gravite'];
}

if (!empty($_GET['statut']) && in_array('statut', $available_columns)) {
    $where_conditions[] = "i.statut = ?";
    $params[] = $_GET['statut'];
}

if (!empty($_GET['categorie']) && in_array('categorie', $available_columns)) {
    $where_conditions[] = "i.categorie = ?";
    $params[] = $_GET['categorie'];
}

if (!empty($_GET['search'])) {
    $where_conditions[] = "(i.titre LIKE ? OR i.description LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Compter le total avec gestion d'erreur améliorée
$total_incidents = 0;
try {
    $count_query = "SELECT COUNT(*) $joins $where_clause";
    error_log("Requête count: " . $count_query);
    error_log("Paramètres count: " . print_r($params, true));
    
    $total_count = $conn->prepare($count_query);
    $total_count->execute($params);
    $total_incidents = $total_count->fetchColumn();
    
    error_log("Total incidents trouvés: " . $total_incidents);
} catch (Exception $e) {
    error_log("ERREUR Count: " . $e->getMessage());
    // Tentative de récupération simple
    try {
        $total_incidents = $conn->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
        error_log("Count simple réussi: " . $total_incidents);
    } catch (Exception $e2) {
        error_log("Même le count simple a échoué: " . $e2->getMessage());
    }
}

$total_pages = ceil($total_incidents / $per_page);

// Récupérer les incidents avec gestion d'erreur améliorée
$incidents = [];
try {
    $incidents_query = "SELECT $select_fields $joins $where_clause ORDER BY i.created_at DESC LIMIT $per_page OFFSET $offset";
    error_log("Requête incidents: " . $incidents_query);
    error_log("Paramètres incidents: " . print_r($params, true));
    
    $incidents_stmt = $conn->prepare($incidents_query);
    $incidents_stmt->execute($params);
    $incidents = $incidents_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Incidents récupérés: " . count($incidents));
    if (!empty($incidents)) {
        error_log("Premier incident: " . print_r($incidents[0], true));
    }
} catch (Exception $e) {
    error_log("ERREUR Incidents: " . $e->getMessage());
    
    // Tentative de récupération simple en cas d'erreur
    try {
        $simple_query = "SELECT * FROM incidents ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
        $incidents = $conn->query($simple_query)->fetchAll(PDO::FETCH_ASSOC);
        error_log("Requête simple réussie, incidents: " . count($incidents));
        
        // Ajouter le nom de l'employé manuellement si possible
        foreach ($incidents as &$incident) {
            if (in_array('employes', $available_tables)) {
                try {
                    $emp = $conn->query("SELECT nom FROM employes WHERE id = " . (int)$incident['employe_id'])->fetch();
                    $incident['employe_nom'] = $emp ? $emp['nom'] : 'Utilisateur ' . $incident['employe_id'];
                } catch (Exception $e3) {
                    $incident['employe_nom'] = 'Utilisateur ' . $incident['employe_id'];
                }
            } else {
                $incident['employe_nom'] = 'Utilisateur ' . $incident['employe_id'];
            }
        }
    } catch (Exception $e2) {
        error_log("Même la requête simple a échoué: " . $e2->getMessage());
        $incidents = [];
    }
}

// Récupérer les employés pour assignation
$employes = [];
try {
    if (in_array('employes', $available_tables)) {
        $emp_columns = $conn->query("SHOW COLUMNS FROM employes")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('role', $emp_columns)) {
            $employes = $conn->query("SELECT id, nom FROM employes WHERE role IN ('manager', 'admin')")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $employes = $conn->query("SELECT id, nom FROM employes LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    error_log("Erreur employes: " . $e->getMessage());
}

// Statistiques pour le dashboard
$stats = ['total' => 0, 'nouveau' => 0, 'en_cours' => 0, 'resolu' => 0, 'grave' => 0];
try {
    $stats['total'] = $conn->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
    
    if (in_array('statut', $available_columns)) {
        $stats_query = "SELECT 
            SUM(CASE WHEN statut = 'nouveau' OR statut IS NULL THEN 1 ELSE 0 END) as nouveau,
            SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN statut = 'resolu' THEN 1 ELSE 0 END) as resolu,
            SUM(CASE WHEN gravite = 'élevée' THEN 1 ELSE 0 END) as grave
            FROM incidents";
        $stats_result = $conn->query($stats_query)->fetch(PDO::FETCH_ASSOC);
        $stats = array_merge($stats, $stats_result);
    } else {
        $stats['nouveau'] = $stats['total']; // Si pas de statut, tout est "nouveau"
        $stats['grave'] = $conn->query("SELECT COUNT(*) FROM incidents WHERE gravite = 'élevée'")->fetchColumn();
    }
} catch (Exception $e) {
    error_log("Erreur stats: " . $e->getMessage());
}

// Test de connexion DB direct pour debug
try {
    $debug_test = $conn->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
    error_log("DEBUG: Test direct COUNT(*): $debug_test");
    
    $debug_sample = $conn->query("SELECT id, titre, created_at FROM incidents LIMIT 3")->fetchAll();
    error_log("DEBUG: Échantillon incidents: " . print_r($debug_sample, true));
} catch (Exception $e) {
    error_log("DEBUG: Erreur test direct: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Incidents</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden">
        <?php include '../sidebar.php'; ?>
        
        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-3xl font-bold text-gray-900">⚠️ Gestion des incidents internes</h2>
                    <button onclick="openModal('createModal')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                        + Nouveau incident
                    </button>
                </div>

                <!-- Dashboard statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
                    <div class="bg-white p-4 rounded-lg shadow">
                        <h3 class="text-sm font-medium text-gray-500">Total</h3>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['total'] ?></p>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow">
                        <h3 class="text-sm font-medium text-gray-500">Nouveaux</h3>
                        <p class="text-2xl font-bold text-blue-600"><?= $stats['nouveau'] ?></p>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow">
                        <h3 class="text-sm font-medium text-gray-500">En cours</h3>
                        <p class="text-2xl font-bold text-orange-600"><?= $stats['en_cours'] ?></p>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow">
                        <h3 class="text-sm font-medium text-gray-500">Résolus</h3>
                        <p class="text-2xl font-bold text-green-600"><?= $stats['resolu'] ?></p>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow">
                        <h3 class="text-sm font-medium text-gray-500">Graves</h3>
                        <p class="text-2xl font-bold text-red-600"><?= $stats['grave'] ?></p>
                    </div>
                </div>

                <!-- Actions et exports -->
                <?php if ($user_role !== 'user'): ?>
                <div class="bg-white p-4 rounded-lg shadow mb-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Actions rapides</h3>
                        <div class="flex gap-3">
                            <a href="export_incidents.php?format=csv" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm">
                                📊 Export CSV
                            </a>
                            <a href="export_incidents.php?format=pdf" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition text-sm">
                                📄 Export PDF
                            </a>
                            <button onclick="showStats()" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition text-sm">
                                📈 Statistiques
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filtres et recherche -->
                <div class="bg-white p-6 rounded-lg shadow mb-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <input type="text" name="search" placeholder="Rechercher..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                               class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-red-500" />
                        
                        <select name="gravite" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-red-500">
                            <option value="">Toutes gravités</option>
                            <option value="basse" <?= ($_GET['gravite'] ?? '') === 'basse' ? 'selected' : '' ?>>Basse</option>
                            <option value="moyenne" <?= ($_GET['gravite'] ?? '') === 'moyenne' ? 'selected' : '' ?>>Moyenne</option>
                            <option value="élevée" <?= ($_GET['gravite'] ?? '') === 'élevée' ? 'selected' : '' ?>>Élevée</option>
                        </select>
                        
                        <?php if (in_array('statut', $available_columns)): ?>
                        <select name="statut" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-red-500">
                            <option value="">Tous statuts</option>
                            <option value="nouveau" <?= ($_GET['statut'] ?? '') === 'nouveau' ? 'selected' : '' ?>>Nouveau</option>
                            <option value="en_cours" <?= ($_GET['statut'] ?? '') === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                            <option value="resolu" <?= ($_GET['statut'] ?? '') === 'resolu' ? 'selected' : '' ?>>Résolu</option>
                            <option value="ferme" <?= ($_GET['statut'] ?? '') === 'ferme' ? 'selected' : '' ?>>Fermé</option>
                        </select>
                        <?php else: ?>
                        <div class="px-3 py-2 border rounded-lg bg-gray-100 text-gray-500">
                            Statuts non disponibles
                        </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('categorie', $available_columns)): ?>
                        <select name="categorie" class="px-3 py-2 border rounded-lg focus:ring-2 focus:ring-red-500">
                            <option value="">Toutes catégories</option>
                            <option value="securite" <?= ($_GET['categorie'] ?? '') === 'securite' ? 'selected' : '' ?>>Sécurité</option>
                            <option value="it" <?= ($_GET['categorie'] ?? '') === 'it' ? 'selected' : '' ?>>IT</option>
                            <option value="rh" <?= ($_GET['categorie'] ?? '') === 'rh' ? 'selected' : '' ?>>RH</option>
                            <option value="equipement" <?= ($_GET['categorie'] ?? '') === 'equipement' ? 'selected' : '' ?>>Équipement</option>
                            <option value="autre" <?= ($_GET['categorie'] ?? '') === 'autre' ? 'selected' : '' ?>>Autre</option>
                        </select>
                        <?php else: ?>
                        <div class="px-3 py-2 border rounded-lg bg-gray-100 text-gray-500">
                            Catégories non disponibles
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            Filtrer
                        </button>
                        
                        <a href="incidents.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition text-center">
                            Reset
                        </a>
                    </form>
                </div>

                <!-- Liste des incidents -->
                <?php if (empty($incidents)): ?>
                    <div class="bg-white p-8 rounded-lg shadow text-center">
                        <p class="text-gray-500 text-lg">Aucun incident trouvé.</p>
                        
                        <!-- Debug info -->
                        <div class="mt-4 p-4 bg-gray-50 rounded text-left text-sm">
                            <strong>Debug info:</strong><br>
                            Total incidents dans la base: <?= $stats['total'] ?><br>
                            Colonnes disponibles: <?= implode(', ', $available_columns) ?><br>
                            <?php if (!empty($where_conditions)): ?>
                                Filtres appliqués: <?= implode(' AND ', $where_conditions) ?><br>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($incidents as $incident): ?>
                            <div class="bg-white rounded-lg shadow border-l-4 <?php
                                echo match ($incident['gravite']) {
                                    'élevée' => 'border-red-500',
                                    'moyenne' => 'border-yellow-500',
                                    default => 'border-gray-300',
                                };
                            ?>">
                                <div class="p-6">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-2">
                                                <h3 class="text-xl font-semibold text-gray-900"><?= htmlspecialchars($incident['titre']) ?></h3>
                                                
                                                <?php if (isset($incident['statut']) && $incident['statut']): ?>
                                                <span class="px-3 py-1 rounded-full text-xs font-medium <?php
                                                    echo match ($incident['statut']) {
                                                        'nouveau' => 'bg-blue-100 text-blue-800',
                                                        'en_cours' => 'bg-orange-100 text-orange-800',
                                                        'resolu' => 'bg-green-100 text-green-800',
                                                        'ferme' => 'bg-gray-100 text-gray-800',
                                                        default => 'bg-gray-100 text-gray-800',
                                                    };
                                                ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $incident['statut'])) ?>
                                                </span>
                                                <?php endif; ?>
                                                
                                                <?php if (isset($incident['categorie']) && $incident['categorie']): ?>
                                                    <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs">
                                                        <?= ucfirst($incident['categorie']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <p class="text-sm text-gray-600 mb-3">
Signalé par <strong><?= htmlspecialchars(getEmployeDisplay($incident)) ?></strong>
    le <?= date('d/m/Y H:i', strtotime($incident['created_at'])) ?>
    <?php if (!empty($incident['assigne_nom'])): ?>
        • Assigné à <strong><?= htmlspecialchars($incident['assigne_nom']) ?></strong>
    <?php endif; ?>
</p>
                                            
                                            <p class="text-gray-700 mb-4"><?= nl2br(htmlspecialchars(substr($incident['description'], 0, 200))) ?><?= strlen($incident['description']) > 200 ? '...' : '' ?></p>
                                            
                                            <?php if (isset($incident['tags']) && $incident['tags']): ?>
                                                <div class="flex flex-wrap gap-2 mb-4">
                                                    <?php foreach (explode(',', $incident['tags']) as $tag): ?>
                                                        <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs"><?= htmlspecialchars(trim($tag)) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($incident['fichier_url']): ?>
                                                <a href="<?= htmlspecialchars($incident['fichier_url']) ?>" target="_blank" 
                                                   class="text-blue-600 hover:underline inline-block mb-4">
                                                    📎 Fichier joint
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="flex gap-2">
                                            <button onclick="viewIncident(<?= $incident['id'] ?>)" 
                                                    class="text-blue-600 hover:text-blue-800 px-3 py-1 border border-blue-600 rounded hover:bg-blue-50 transition">
                                                Voir détails
                                            </button>
                                            <?php if ($user_role !== 'user'): ?>
                                                <button onclick="manageIncident(<?= $incident['id'] ?>)" 
                                                        class="text-green-600 hover:text-green-800 px-3 py-1 border border-green-600 rounded hover:bg-green-50 transition">
                                                    Gérer
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="border-t pt-3">
                                        <p class="text-sm text-gray-500">
                                            Gravité: <span class="font-medium"><?= ucfirst($incident['gravite']) ?></span>
                                            <?php if (isset($incident['departement']) && $incident['departement']): ?>
                                                • Département: <span class="font-medium"><?= htmlspecialchars($incident['departement']) ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="flex justify-center mt-8">
                            <nav class="flex space-x-2">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)) ?>" 
                                       class="px-4 py-2 border rounded <?= $i == $page ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal création incident -->
    <div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Signaler un nouvel incident</h3>
                <button onclick="closeModal('createModal')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <input type="text" name="titre" placeholder="Titre de l'incident" required
                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500" />
                    
                    <?php if (in_array('categorie', $available_columns)): ?>
                    <select name="categorie" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500">
                        <option value="">Catégorie (optionnel)</option>
                        <option value="securite">Sécurité</option>
                        <option value="it">IT</option>
                        <option value="rh">RH</option>
                        <option value="equipement">Équipement</option>
                        <option value="autre">Autre</option>
                    </select>
                    <?php else: ?>
                    <div class="w-full p-3 border rounded-lg bg-gray-100 text-gray-500">
                        Catégories non disponibles
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <select name="gravite" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500">
                        <option value="">Niveau de gravité</option>
                        <option value="basse">Basse</option>
                        <option value="moyenne">Moyenne</option>
                        <option value="élevée">Élevée</option>
                    </select>
                    
                    <?php if (in_array('departement', $available_columns)): ?>
                    <input type="text" name="departement" placeholder="Département (optionnel)"
                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500" />
                    <?php else: ?>
                    <div class="w-full p-3 border rounded-lg bg-gray-100 text-gray-500">
                        Département non disponible
                    </div>
                    <?php endif; ?>
                </div>

                <textarea name="description" rows="4" placeholder="Description détaillée" required
                          class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 mb-4"></textarea>

                <?php if (in_array('tags', $available_columns)): ?>
                <input type="text" name="tags" placeholder="Tags (séparés par des virgules)"
                       class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 mb-4" />
                <?php endif; ?>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fichier (optionnel)</label>
                    <input type="file" name="fichier" 
                           class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500" />
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal('createModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition">
                        Signaler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal détails incident -->
    <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Détails de l'incident</h3>
                <button onclick="closeModal('detailsModal')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="incidentDetails">Chargement...</div>
        </div>
    </div>

    <!-- Modal gestion incident -->
    <?php if ($user_role !== 'user' && in_array('statut', $available_columns)): ?>
    <div id="manageModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Gérer l'incident</h3>
                <button onclick="closeModal('manageModal')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form method="post" id="manageForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="incident_id" id="manageIncidentId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <select name="statut" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500">
                        <option value="nouveau">Nouveau</option>
                        <option value="en_cours">En cours</option>
                        <option value="resolu">Résolu</option>
                        <option value="ferme">Fermé</option>
                    </select>
                    
                    <select name="assigne_a" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500">
                        <option value="">Non assigné</option>
                        <?php foreach ($employes as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <textarea name="commentaire" rows="3" placeholder="Commentaire (optionnel)"
                          class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-red-500 mb-6"></textarea>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeModal('manageModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                        Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal statistiques -->
    <div id="statsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Statistiques des incidents</h3>
                <button onclick="closeModal('statsModal')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Graphique par gravité -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold mb-4">Répartition par gravité</h4>
                    <canvas id="graviteChart" width="300" height="200"></canvas>
                </div>
                
                <!-- Graphique par statut -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="font-semibold mb-4">Répartition par statut</h4>
                    <canvas id="statutChart" width="300" height="200"></canvas>
                </div>
            </div>
            
            <!-- Statistiques détaillées -->
            <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <p class="text-2xl font-bold text-blue-600"><?= $stats['total'] ?></p>
                    <p class="text-sm text-gray-600">Total incidents</p>
                </div>
                <div class="text-center p-4 bg-orange-50 rounded-lg">
                    <p class="text-2xl font-bold text-orange-600"><?= $stats['en_cours'] ?></p>
                    <p class="text-sm text-gray-600">En cours</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <p class="text-2xl font-bold text-green-600"><?= $stats['resolu'] ?></p>
                    <p class="text-sm text-gray-600">Résolus</p>
                </div>
                <div class="text-center p-4 bg-red-50 rounded-lg">
                    <p class="text-2xl font-bold text-red-600"><?= $stats['grave'] ?></p>
                    <p class="text-sm text-gray-600">Graves</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let graviteChart, statutChart;
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.getElementById(modalId).classList.add('flex');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('flex');
        }

        function viewIncident(incidentId) {
            document.getElementById('incidentDetails').innerHTML = 'Chargement...';
            openModal('detailsModal');
            
            fetch(`get_incident_details.php?id=${incidentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.html) {
                        document.getElementById('incidentDetails').innerHTML = data.html;
                    } else {
                        document.getElementById('incidentDetails').innerHTML = '<p class="text-red-600">Erreur lors du chargement des détails</p>';
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('incidentDetails').innerHTML = '<p class="text-red-600">Erreur de connexion</p>';
                });
        }

        function manageIncident(incidentId) {
            document.getElementById('manageIncidentId').value = incidentId;
            openModal('manageModal');
        }

        function showStats() {
            openModal('statsModal');
            
            // Créer les graphiques si pas déjà fait
            if (!graviteChart) {
                createCharts();
            }
        }

        function createCharts() {
            // Graphique gravité
            const graviteCtx = document.getElementById('graviteChart').getContext('2d');
            
            // Récupérer les données via PHP
            <?php
            try {
                $gravite_stats = $conn->query("SELECT gravite, COUNT(*) as count FROM incidents GROUP BY gravite")->fetchAll(PDO::FETCH_KEY_PAIR);
                echo "const graviteData = " . json_encode($gravite_stats) . ";";
            } catch (Exception $e) {
                echo "const graviteData = {};";
            }
            ?>
            
            graviteChart = new Chart(graviteCtx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(graviteData).map(g => g.charAt(0).toUpperCase() + g.slice(1)),
                    datasets: [{
                        data: Object.values(graviteData),
                        backgroundColor: ['#10B981', '#F59E0B', '#EF4444']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Graphique statut
            <?php if (in_array('statut', $available_columns)): ?>
            const statutCtx = document.getElementById('statutChart').getContext('2d');
            
            <?php
            try {
                $statut_stats = $conn->query("SELECT 
                    COALESCE(statut, 'nouveau') as statut, 
                    COUNT(*) as count 
                    FROM incidents 
                    GROUP BY COALESCE(statut, 'nouveau')")->fetchAll(PDO::FETCH_KEY_PAIR);
                echo "const statutData = " . json_encode($statut_stats) . ";";
            } catch (Exception $e) {
                echo "const statutData = {};";
            }
            ?>
            
            statutChart = new Chart(statutCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(statutData).map(s => s.replace('_', ' ').toUpperCase()),
                    datasets: [{
                        label: 'Nombre',
                        data: Object.values(statutData),
                        backgroundColor: ['#3B82F6', '#F59E0B', '#10B981', '#6B7280']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        }

        // Auto-refresh toutes les 30 secondes pour les nouveaux incidents
        setInterval(function() {
            if (!document.querySelector('.fixed:not(.hidden)')) { // Pas de modal ouverte
                const currentUrl = window.location.href;
                // Refresh silencieux des stats seulement
                fetch(currentUrl)
                    .then(response => response.text())
                    .then(html => {
                        // Mettre à jour juste les statistiques sans recharger la page
                        const parser = new DOMParser();
                        const newDoc = parser.parseFromString(html, 'text/html');
                        const newStats = newDoc.querySelectorAll('.grid.grid-cols-1.md\\:grid-cols-5 .text-2xl');
                        const currentStats = document.querySelectorAll('.grid.grid-cols-1.md\\:grid-cols-5 .text-2xl');
                        
                        newStats.forEach((stat, index) => {
                            if (currentStats[index] && stat.textContent !== currentStats[index].textContent) {
                                currentStats[index].textContent = stat.textContent;
                                // Animation flash pour indiquer le changement
                                currentStats[index].parentElement.classList.add('bg-yellow-100');
                                setTimeout(() => {
                                    currentStats[index].parentElement.classList.remove('bg-yellow-100');
                                }, 1000);
                            }
                        });
                    })
                    .catch(error => console.log('Refresh stats error:', error));
            }
        }, 30000);

        // Notification toast pour les actions réussies
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 p-4 rounded-lg text-white z-50 transition-all duration-300 ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            // Animation d'entrée
            setTimeout(() => toast.classList.add('translate-x-0'), 10);
            
            // Suppression après 3 secondes
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Fermer les modals en cliquant à l'extérieur
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('fixed')) {
                const modals = ['createModal', 'detailsModal', 'manageModal', 'statsModal'];
                modals.forEach(modalId => {
                    if (e.target.id === modalId) {
                        closeModal(modalId);
                    }
                });
            }
        });

        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modals = ['createModal', 'detailsModal', 'manageModal', 'statsModal'];
                modals.forEach(closeModal);
            }
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openModal('createModal');
            }
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                showStats();
            }
        });

        // Validation du formulaire
        document.querySelector('form[method="post"]').addEventListener('submit', function(e) {
            const titre = this.querySelector('input[name="titre"]').value.trim();
            const description = this.querySelector('textarea[name="description"]').value.trim();
            const gravite = this.querySelector('select[name="gravite"]').value;
            
            if (!titre || !description || !gravite) {
                e.preventDefault();
                showToast('Veuillez remplir tous les champs obligatoires', 'error');
                return;
            }
            
            // Indication de traitement
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Création...';
        });

        // Messages de succès après soumission
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        window.addEventListener('load', function() {
            showToast('Incident traité avec succès!');
        });
        <?php endif; ?>
        
        // Détection de nouveaux incidents (simulation)
        function checkForNewIncidents() {
            // Cette fonction pourrait faire un appel AJAX pour vérifier
            // s'il y a de nouveaux incidents depuis la dernière visite
            const lastCheck = localStorage.getItem('lastIncidentCheck');
            const now = new Date().getTime();
            
            if (!lastCheck || (now - parseInt(lastCheck)) > 60000) { // 1 minute
                localStorage.setItem('lastIncidentCheck', now.toString());
                
                // Ici tu pourrais faire un appel pour vérifier les nouveaux incidents
                // et afficher une notification si nécessaire
            }
        }

        // Démarrer la vérification
        checkForNewIncidents();
        setInterval(checkForNewIncidents, 60000);

        console.log('🎯 Système de gestion des incidents chargé');
        console.log('Raccourcis: Ctrl+N (nouveau), Ctrl+S (stats), Echap (fermer)');
    </script>
</body>
</html>