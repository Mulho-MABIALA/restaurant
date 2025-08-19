<?php
session_start();
require_once '../../config.php';

// Vérification de l'authentification
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Traitement des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['ajax_action'] ?? '';
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($action) {
            case 'ajouter':
                $response = ajouterAnnonceAjax($conn);
                break;
            case 'modifier':
                $response = modifierAnnonceAjax($conn);
                break;
            case 'supprimer':
                $response = supprimerAnnonceAjax($conn);
                break;
            case 'changer_statut':
                $response = changerStatutAnnonceAjax($conn);
                break;
            case 'ajouter_categorie':
                $response = ajouterCategorieAjax($conn);
                break;
            case 'get_annonce':
                $response = getAnnonceAjax($conn);
                break;
            case 'get_annonces':
                $response = getAnnoncesAjax($conn);
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}

// Récupération des données pour l'affichage initial
$annonces = getAnnonces($conn);
$categories = getCategories($conn);
$stats = getStatistiques($conn);

// Fonctions AJAX
function ajouterAnnonceAjax($conn) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO annonces (titre, contenu, categorie_id, priorite, statut, date_debut, date_fin, lien_externe, admin_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['titre'],
            $_POST['contenu'],
            $_POST['categorie_id'] ?: null,
            $_POST['priorite'],
            $_POST['statut'],
            $_POST['date_debut'] ?: null,
            $_POST['date_fin'] ?: null,
            $_POST['lien_externe'] ?: null,
            $_SESSION['admin_id']
        ]);
        
        return ['success' => true, 'message' => 'Annonce ajoutée avec succès !'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur lors de l\'ajout : ' . $e->getMessage()];
    }
}

function modifierAnnonceAjax($conn) {
    try {
        $stmt = $conn->prepare("
            UPDATE annonces 
            SET titre = ?, contenu = ?, categorie_id = ?, priorite = ?, statut = ?, 
                date_debut = ?, date_fin = ?, lien_externe = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['titre'],
            $_POST['contenu'],
            $_POST['categorie_id'] ?: null,
            $_POST['priorite'],
            $_POST['statut'],
            $_POST['date_debut'] ?: null,
            $_POST['date_fin'] ?: null,
            $_POST['lien_externe'] ?: null,
            $_POST['annonce_id']
        ]);
        
        return ['success' => true, 'message' => 'Annonce modifiée avec succès !'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur lors de la modification : ' . $e->getMessage()];
    }
}

function supprimerAnnonceAjax($conn) {
    try {
        $stmt = $conn->prepare("DELETE FROM annonces WHERE id = ?");
        $stmt->execute([$_POST['annonce_id']]);
        
        return ['success' => true, 'message' => 'Annonce supprimée avec succès !'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()];
    }
}

function changerStatutAnnonceAjax($conn) {
    try {
        $stmt = $conn->prepare("UPDATE annonces SET statut = ? WHERE id = ?");
        $stmt->execute([$_POST['nouveau_statut'], $_POST['annonce_id']]);
        
        return ['success' => true, 'message' => 'Statut modifié avec succès !'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur lors du changement de statut : ' . $e->getMessage()];
    }
}

function ajouterCategorieAjax($conn) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO categories_annonces (nom, description, couleur, icone)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['nom_categorie'],
            $_POST['description_categorie'],
            $_POST['couleur_categorie'],
            $_POST['icone_categorie']
        ]);
        
        return ['success' => true, 'message' => 'Catégorie ajoutée avec succès !'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur lors de l\'ajout de la catégorie : ' . $e->getMessage()];
    }
}

function getAnnonceAjax($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT a.*, c.nom as categorie_nom
            FROM annonces a
            LEFT JOIN categories_annonces c ON a.categorie_id = c.id
            WHERE a.id = ?
        ");
        $stmt->execute([$_POST['annonce_id']]);
        $annonce = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($annonce) {
            return ['success' => true, 'annonce' => $annonce];
        } else {
            return ['success' => false, 'message' => 'Annonce non trouvée'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

function getAnnoncesAjax($conn) {
    try {
        $annonces = getAnnonces($conn);
        $stats = getStatistiques($conn);
        return ['success' => true, 'annonces' => $annonces, 'stats' => $stats];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

// Fonctions utilitaires (inchangées)
function getAnnonces($conn) {
    $stmt = $conn->query("
        SELECT a.*, c.nom as categorie_nom, c.couleur as categorie_couleur,
               ad.nom_utilisateur as admin_nom
        FROM annonces a
        LEFT JOIN categories_annonces c ON a.categorie_id = c.id
        LEFT JOIN admins ad ON a.admin_id = ad.id
        ORDER BY a.date_creation DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCategories($conn) {
    $stmt = $conn->query("SELECT * FROM categories_annonces WHERE actif = 1 ORDER BY nom");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatistiques($conn) {
    $stats = [];
    
    // Total des annonces
    $stmt = $conn->query("SELECT COUNT(*) as total FROM annonces");
    $stats['total'] = $stmt->fetchColumn();
    
    // Annonces publiées
    $stmt = $conn->query("SELECT COUNT(*) as publie FROM annonces WHERE statut = 'publie'");
    $stats['publie'] = $stmt->fetchColumn();
    
    // Annonces en brouillon
    $stmt = $conn->query("SELECT COUNT(*) as brouillon FROM annonces WHERE statut = 'brouillon'");
    $stats['brouillon'] = $stmt->fetchColumn();
    
    // Annonces urgentes
    $stmt = $conn->query("SELECT COUNT(*) as urgente FROM annonces WHERE priorite = 'urgente'");
    $stats['urgente'] = $stmt->fetchColumn();
    
    return $stats;
}

function getPrioriteColor($priorite) {
    switch ($priorite) {
        case 'urgente': return 'danger';
        case 'haute': return 'warning';
        case 'normale': return 'primary';
        case 'basse': return 'secondary';
        default: return 'primary';
    }
}

function getStatutColor($statut) {
    switch ($statut) {
        case 'publie': return 'success';
        case 'brouillon': return 'warning';
        case 'archive': return 'secondary';
        default: return 'primary';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Annonces - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .priorite-badge {
            font-size: 0.75em;
        }
        .table-actions {
            white-space: nowrap;
        }
        .is-invalid {
            border-color: #dc3545;
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="#">
            <i class="fas fa-bullhorn me-2"></i>Gestion des Annonces
        </a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-dashboard me-1"></i>Tableau de bord
            </a>
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt me-1"></i>Déconnexion
            </a>
        </div>
    </div>
</nav>

<div class="container">
    <!-- Zone des alertes -->
    <div id="alertZone"></div>

    <!-- Statistiques -->
    <div class="row mb-4" id="statsContainer">
        <div class="col-md-3">
            <div class="card card-stats">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="card-category">Total</p>
                                <h3 class="card-title" id="statTotal"><?= $stats['total'] ?></h3>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon-big">
                                <i class="fas fa-bullhorn fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="card-category">Publiées</p>
                                <h3 class="card-title" id="statPublie"><?= $stats['publie'] ?></h3>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="card-category">Brouillons</p>
                                <h3 class="card-title" id="statBrouillon"><?= $stats['brouillon'] ?></h3>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <i class="fas fa-edit fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card" style="background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: white;">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="card-category">Urgentes</p>
                                <h3 class="card-title" id="statUrgente"><?= $stats['urgente'] ?></h3>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Boutons d'actions -->
    <div class="row mb-4">
        <div class="col-12">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouterAnnonce">
                <i class="fas fa-plus me-1"></i>Nouvelle Annonce
            </button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAjouterCategorie">
                <i class="fas fa-folder-plus me-1"></i>Nouvelle Catégorie
            </button>
        </div>
    </div>

    <!-- Liste des annonces -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Liste des Annonces
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="tableAnnonces">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Titre</th>
                            <th>Catégorie</th>
                            <th>Priorité</th>
                            <th>Statut</th>
                            <th>Date création</th>
                            <th>Vues</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="annoncesTableBody">
                        <?php foreach ($annonces as $annonce): ?>
                        <tr data-id="<?= $annonce['id'] ?>">
                            <td><?= $annonce['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($annonce['titre']) ?></strong>
                                <?php if ($annonce['admin_nom']): ?>
                                    <small class="text-muted d-block">par <?= htmlspecialchars($annonce['admin_nom']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($annonce['categorie_nom']): ?>
                                    <span class="badge" style="background-color: <?= $annonce['categorie_couleur'] ?>">
                                        <?= htmlspecialchars($annonce['categorie_nom']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Aucune</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= getPrioriteColor($annonce['priorite']) ?> priorite-badge">
                                    <?= ucfirst($annonce['priorite']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= getStatutColor($annonce['statut']) ?>">
                                    <?= ucfirst($annonce['statut']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($annonce['date_creation'])) ?></td>
                            <td>
                                <span class="badge bg-info"><?= $annonce['nombre_vues'] ?></span>
                            </td>
                            <td class="table-actions">
                                <button class="btn btn-sm btn-outline-primary" onclick="voirAnnonce(<?= $annonce['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="modifierAnnonce(<?= $annonce['id'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="archiverAnnonce(<?= $annonce['id'] ?>)">
                                    <i class="fas fa-archive"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="supprimerAnnonce(<?= $annonce['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajouter/Modifier Annonce -->
<div class="modal fade" id="modalAjouterAnnonce" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formAjouterAnnonce">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAnnonceTitle">Nouvelle Annonce</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="annonceId" name="annonce_id">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Titre *</label>
                                <input type="text" class="form-control" name="titre" id="annonceTitre" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Catégorie</label>
                                <select class="form-select" name="categorie_id" id="annonceCategorie">
                                    <option value="">Aucune catégorie</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contenu *</label>
                        <textarea class="form-control" name="contenu" id="annonceContenu" rows="6" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Priorité</label>
                                <select class="form-select" name="priorite" id="annoncePriorite">
                                    <option value="basse">Basse</option>
                                    <option value="normale" selected>Normale</option>
                                    <option value="haute">Haute</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Statut</label>
                                <select class="form-select" name="statut" id="annonceStatut">
                                    <option value="brouillon" selected>Brouillon</option>
                                    <option value="publie">Publié</option>
                                    <option value="archive">Archivé</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Date de début</label>
                                <input type="date" class="form-control" name="date_debut" id="annonceDateDebut">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Date de fin</label>
                                <input type="date" class="form-control" name="date_fin" id="annonceDateFin">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Lien externe</label>
                        <input type="url" class="form-control" name="lien_externe" id="annonceLien" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="btnSauvegarderAnnonce">
                        <span class="spinner-border spinner-border-sm d-none" id="spinnerAnnonce"></span>
                        Ajouter l'annonce
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ajouter Catégorie -->
<div class="modal fade" id="modalAjouterCategorie" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formAjouterCategorie">
                <div class="modal-header">
                    <h5 class="modal-title">Nouvelle Catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom *</label>
                        <input type="text" class="form-control" name="nom_categorie" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description_categorie" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Couleur</label>
                                <input type="color" class="form-control form-control-color" name="couleur_categorie" value="#007bff">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Icône (FontAwesome)</label>
                                <input type="text" class="form-control" name="icone_categorie" placeholder="fas fa-bullhorn">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success" id="btnSauvegarderCategorie">
                        <span class="spinner-border spinner-border-sm d-none" id="spinnerCategorie"></span>
                        Ajouter la catégorie
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Variables globales
let isEditing = false;
let currentAnnonceId = null;

// Fonction pour afficher les alertes
function showAlert(message, type = 'success') {
    const alertZone = document.getElementById('alertZone');
    const alertId = 'alert-' + Date.now();
    
    const alertHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" id="${alertId}">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    alertZone.insertAdjacentHTML('beforeend', alertHTML);
    
    // Auto-suppression après 5 secondes
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}

// Fonction pour effectuer une requête AJAX
async function ajaxRequest(action, data) {
    const formData = new FormData();
    formData.append('ajax_action', action);
    
    for (const key in data) {
        formData.append(key, data[key]);
    }
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error('Erreur réseau');
        }
        
        return await response.json();
    } catch (error) {
        console.error('Erreur AJAX:', error);
        return { success: false, message: 'Erreur de communication avec le serveur' };
    }
}

// Fonction pour mettre à jour les statistiques
function updateStats(stats) {
    document.getElementById('statTotal').textContent = stats.total;
    document.getElementById('statPublie').textContent = stats.publie;
    document.getElementById('statBrouillon').textContent = stats.brouillon;
    document.getElementById('statUrgente').textContent = stats.urgente;
}

// Fonction pour générer le HTML d'une ligne d'annonce
function generateAnnonceRow(annonce) {
    const prioriteColors = {
        'urgente': 'danger',
        'haute': 'warning',
        'normale': 'primary',
        'basse': 'secondary'
    };
    
    const statutColors = {
        'publie': 'success',
        'brouillon': 'warning',
        'archive': 'secondary'
    };
    
    const categorieHTML = annonce.categorie_nom 
        ? `<span class="badge" style="background-color: ${annonce.categorie_couleur}">${annonce.categorie_nom}</span>`
        : '<span class="text-muted">Aucune</span>';
    
    const adminHTML = annonce.admin_nom 
        ? `<small class="text-muted d-block">par ${annonce.admin_nom}</small>`
        : '';
    
    return `
        <tr data-id="${annonce.id}">
            <td>${annonce.id}</td>
            <td>
                <strong>${annonce.titre}</strong>
                ${adminHTML}
            </td>
            <td>${categorieHTML}</td>
            <td>
                <span class="badge bg-${prioriteColors[annonce.priorite]} priorite-badge">
                    ${annonce.priorite.charAt(0).toUpperCase() + annonce.priorite.slice(1)}
                </span>
            </td>
            <td>
                <span class="badge bg-${statutColors[annonce.statut]}">
                    ${annonce.statut.charAt(0).toUpperCase() + annonce.statut.slice(1)}
                </span>
            </td>
            <td>${new Date(annonce.date_creation).toLocaleDateString('fr-FR', {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit'
            })}</td>
            <td>
                <span class="badge bg-info">${annonce.nombre_vues || 0}</span>
            </td>
            <td class="table-actions">
                <button class="btn btn-sm btn-outline-primary" onclick="voirAnnonce(${annonce.id})">
                    <i class="fas fa-eye"></i>
                    </button>
                <button class="btn btn-sm btn-outline-warning" onclick="modifierAnnonce(${annonce.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="archiverAnnonce(${annonce.id})">
                    <i class="fas fa-archive"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="supprimerAnnonce(${annonce.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
}

// Fonction pour recharger la liste des annonces
async function reloadAnnonces() {
    const result = await ajaxRequest('get_annonces', {});
    
    if (result.success) {
        const tbody = document.getElementById('annoncesTableBody');
        tbody.innerHTML = '';
        
        result.annonces.forEach(annonce => {
            tbody.insertAdjacentHTML('beforeend', generateAnnonceRow(annonce));
        });
        
        updateStats(result.stats);
    } else {
        showAlert(result.message, 'danger');
    }
}

// Fonction pour réinitialiser le formulaire d'annonce
function resetAnnonceForm() {
    const form = document.getElementById('formAjouterAnnonce');
    form.reset();
    
    // Réinitialiser les variables globales
    isEditing = false;
    currentAnnonceId = null;
    
    // Réinitialiser le titre du modal
    document.getElementById('modalAnnonceTitle').textContent = 'Nouvelle Annonce';
    document.getElementById('btnSauvegarderAnnonce').textContent = 'Ajouter l\'annonce';
    document.getElementById('annonceId').value = '';
    
    // Retirer les classes d'erreur
    form.querySelectorAll('.is-invalid').forEach(field => {
        field.classList.remove('is-invalid');
    });
}

// Gestionnaire pour le formulaire d'ajout/modification d'annonce
document.getElementById('formAjouterAnnonce').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btnSauvegarderAnnonce');
    const spinner = document.getElementById('spinnerAnnonce');
    
    // Validation côté client
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        showAlert('Veuillez remplir tous les champs obligatoires.', 'danger');
        return;
    }
    
    // Afficher le spinner
    btn.disabled = true;
    spinner.classList.remove('d-none');
    
    // Préparer les données
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    
    // Déterminer l'action
    const action = isEditing ? 'modifier' : 'ajouter';
    
    try {
        const result = await ajaxRequest(action, data);
        
        if (result.success) {
            showAlert(result.message, 'success');
            
            // Fermer le modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalAjouterAnnonce'));
            modal.hide();
            
            // Recharger la liste
            await reloadAnnonces();
            
            // Réinitialiser le formulaire
            resetAnnonceForm();
        } else {
            showAlert(result.message, 'danger');
        }
    } catch (error) {
        showAlert('Erreur lors de la sauvegarde', 'danger');
    } finally {
        btn.disabled = false;
        spinner.classList.add('d-none');
    }
});

// Gestionnaire pour le formulaire d'ajout de catégorie
document.getElementById('formAjouterCategorie').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btnSauvegarderCategorie');
    const spinner = document.getElementById('spinnerCategorie');
    
    // Validation côté client
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        showAlert('Veuillez remplir tous les champs obligatoires.', 'danger');
        return;
    }
    
    // Afficher le spinner
    btn.disabled = true;
    spinner.classList.remove('d-none');
    
    // Préparer les données
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    
    try {
        const result = await ajaxRequest('ajouter_categorie', data);
        
        if (result.success) {
            showAlert(result.message, 'success');
            
            // Fermer le modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalAjouterCategorie'));
            modal.hide();
            
            // Réinitialiser le formulaire
            this.reset();
            
            // Recharger la page pour mettre à jour la liste des catégories dans le select
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert(result.message, 'danger');
        }
    } catch (error) {
        showAlert('Erreur lors de la sauvegarde', 'danger');
    } finally {
        btn.disabled = false;
        spinner.classList.add('d-none');
    }
});

// Fonction pour voir une annonce
function voirAnnonce(annonceId) {
    // Vous pouvez implémenter une modal de visualisation ou rediriger
    window.open('voir_annonce.php?id=' + annonceId, '_blank');
}

// Fonction pour modifier une annonce
async function modifierAnnonce(annonceId) {
    // Récupérer les données de l'annonce
    const result = await ajaxRequest('get_annonce', { annonce_id: annonceId });
    
    if (result.success) {
        const annonce = result.annonce;
        
        // Remplir le formulaire
        document.getElementById('annonceId').value = annonce.id;
        document.getElementById('annonceTitre').value = annonce.titre;
        document.getElementById('annonceContenu').value = annonce.contenu;
        document.getElementById('annonceCategorie').value = annonce.categorie_id || '';
        document.getElementById('annoncePriorite').value = annonce.priorite;
        document.getElementById('annonceStatut').value = annonce.statut;
        document.getElementById('annonceDateDebut').value = annonce.date_debut || '';
        document.getElementById('annonceDateFin').value = annonce.date_fin || '';
        document.getElementById('annonceLien').value = annonce.lien_externe || '';
        
        // Configurer le mode édition
        isEditing = true;
        currentAnnonceId = annonceId;
        
        // Changer le titre du modal
        document.getElementById('modalAnnonceTitle').textContent = 'Modifier l\'annonce';
        document.getElementById('btnSauvegarderAnnonce').textContent = 'Sauvegarder les modifications';
        
        // Ouvrir le modal
        const modal = new bootstrap.Modal(document.getElementById('modalAjouterAnnonce'));
        modal.show();
    } else {
        showAlert(result.message, 'danger');
    }
}

// Fonction pour archiver une annonce
async function archiverAnnonce(annonceId) {
    if (confirm('Êtes-vous sûr de vouloir archiver cette annonce ?')) {
        const result = await ajaxRequest('changer_statut', {
            annonce_id: annonceId,
            nouveau_statut: 'archive'
        });
        
        if (result.success) {
            showAlert(result.message, 'success');
            await reloadAnnonces();
        } else {
            showAlert(result.message, 'danger');
        }
    }
}

// Fonction pour supprimer une annonce
async function supprimerAnnonce(annonceId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette annonce ? Cette action est irréversible.')) {
        const result = await ajaxRequest('supprimer', { annonce_id: annonceId });
        
        if (result.success) {
            showAlert(result.message, 'success');
            await reloadAnnonces();
        } else {
            showAlert(result.message, 'danger');
        }
    }
}

// Réinitialiser le formulaire à l'ouverture du modal d'ajout
document.getElementById('modalAjouterAnnonce').addEventListener('show.bs.modal', function() {
    if (!isEditing) {
        resetAnnonceForm();
    }
});

// Réinitialiser les variables quand le modal se ferme
document.getElementById('modalAjouterAnnonce').addEventListener('hidden.bs.modal', function() {
    resetAnnonceForm();
});

// Validation en temps réel
document.addEventListener('DOMContentLoaded', function() {
    // Validation pour tous les champs requis
    document.querySelectorAll('[required]').forEach(function(field) {
        field.addEventListener('blur', function() {
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
            } else {
                this.classList.add('is-invalid');
            }
        });
    });
});

// Fonction utilitaire pour gérer les erreurs de connexion
function handleConnectionError() {
    showAlert('Erreur de connexion. Vérifiez votre connexion internet.', 'danger');
}

// Ajouter un indicateur de chargement pour le tableau
function showTableLoading() {
    const tbody = document.getElementById('annoncesTableBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
            </td>
        </tr>
    `;
}

// Auto-actualisation périodique (optionnel)
let autoRefreshInterval;

function startAutoRefresh() {
    autoRefreshInterval = setInterval(async () => {
        await reloadAnnonces();
    }, 300000); // Actualiser toutes les 5 minutes
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

// Démarrer l'auto-actualisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    startAutoRefresh();
});

// Arrêter l'auto-actualisation quand la page n'est plus visible
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});

// Fonction pour publier une annonce rapidement
async function publierAnnonce(annonceId) {
    if (confirm('Êtes-vous sûr de vouloir publier cette annonce ?')) {
        const result = await ajaxRequest('changer_statut', {
            annonce_id: annonceId,
            nouveau_statut: 'publie'
        });
        
        if (result.success) {
            showAlert(result.message, 'success');
            await reloadAnnonces();
        } else {
            showAlert(result.message, 'danger');
        }
    }
}

// Fonction pour mettre en brouillon une annonce
async function mettreEnBrouillon(annonceId) {
    if (confirm('Êtes-vous sûr de vouloir remettre cette annonce en brouillon ?')) {
        const result = await ajaxRequest('changer_statut', {
            annonce_id: annonceId,
            nouveau_statut: 'brouillon'
        });
        
        if (result.success) {
            showAlert(result.message, 'success');
            await reloadAnnonces();
        } else {
            showAlert(result.message, 'danger');
        }
    }
}

// Fonction pour filtrer les annonces par statut (bonus)
function filtrerAnnonces(statut) {
    const rows = document.querySelectorAll('#annoncesTableBody tr');
    
    rows.forEach(row => {
        if (statut === 'tous') {
            row.style.display = '';
        } else {
            const statutBadge = row.querySelector('td:nth-child(5) .badge');
            if (statutBadge && statutBadge.textContent.toLowerCase().includes(statut.toLowerCase())) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// Fonction pour trier les annonces (bonus)
function trierAnnonces(critere, ordre = 'asc') {
    const tbody = document.getElementById('annoncesTableBody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        let valeurA, valeurB;
        
        switch (critere) {
            case 'id':
                valeurA = parseInt(a.cells[0].textContent);
                valeurB = parseInt(b.cells[0].textContent);
                break;
            case 'titre':
                valeurA = a.cells[1].textContent.toLowerCase();
                valeurB = b.cells[1].textContent.toLowerCase();
                break;
            case 'date':
                valeurA = new Date(a.cells[5].textContent);
                valeurB = new Date(b.cells[5].textContent);
                break;
            case 'vues':
                valeurA = parseInt(a.cells[6].textContent);
                valeurB = parseInt(b.cells[6].textContent);
                break;
            default:
                return 0;
        }
        
        if (ordre === 'asc') {
            return valeurA > valeurB ? 1 : -1;
        } else {
            return valeurA < valeurB ? 1 : -1;
        }
    });
    
    // Réorganiser les lignes
    rows.forEach(row => tbody.appendChild(row));
}

// Fonction pour rechercher dans les annonces (bonus)
function rechercherAnnonces(terme) {
    const rows = document.querySelectorAll('#annoncesTableBody tr');
    const termeMinuscule = terme.toLowerCase();
    
    rows.forEach(row => {
        const titre = row.cells[1].textContent.toLowerCase();
        const categorie = row.cells[2].textContent.toLowerCase();
        
        if (titre.includes(termeMinuscule) || categorie.includes(termeMinuscule)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Gestion des raccourcis clavier (bonus)
document.addEventListener('keydown', function(e) {
    // Ctrl + N : Nouvelle annonce
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        const modal = new bootstrap.Modal(document.getElementById('modalAjouterAnnonce'));
        modal.show();
    }
    
    // Ctrl + R : Recharger les annonces
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        reloadAnnonces();
    }
    
    // Échap : Fermer les modals
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        });
    }
});

// Sauvegarde automatique des brouillons (bonus - à implémenter selon vos besoins)
let brouillonTimer;

function demarrerSauvegardeBrouillon() {
    const contenuField = document.getElementById('annonceContenu');
    const titreField = document.getElementById('annonceTitre');
    
    if (contenuField && titreField) {
        [contenuField, titreField].forEach(field => {
            field.addEventListener('input', function() {
                clearTimeout(brouillonTimer);
                brouillonTimer = setTimeout(() => {
                    // Sauvegarder en localStorage pour éviter les pertes
                    const brouillon = {
                        titre: titreField.value,
                        contenu: contenuField.value,
                        timestamp: Date.now()
                    };
                    localStorage.setItem('annonce_brouillon', JSON.stringify(brouillon));
                }, 2000); // Sauvegarde après 2 secondes d'inactivité
            });
        });
    }
}

// Restaurer un brouillon sauvé
function restaurerBrouillon() {
    const brouillon = localStorage.getItem('annonce_brouillon');
    if (brouillon) {
        const data = JSON.parse(brouillon);
        const maintenant = Date.now();
        
        // Restaurer seulement si le brouillon a moins de 24 heures
        if (maintenant - data.timestamp < 24 * 60 * 60 * 1000) {
            if (confirm('Un brouillon a été trouvé. Voulez-vous le restaurer ?')) {
                document.getElementById('annonceTitre').value = data.titre;
                document.getElementById('annonceContenu').value = data.contenu;
            }
        }
    }
}

// Nettoyer le brouillon après sauvegarde réussie
function nettoyerBrouillon() {
    localStorage.removeItem('annonce_brouillon');
}

// Initialisation finale
document.addEventListener('DOMContentLoaded', function() {
    demarrerSauvegardeBrouillon();
    
    // Proposer de restaurer un brouillon au clic sur "Nouvelle annonce"
    document.querySelector('[data-bs-target="#modalAjouterAnnonce"]').addEventListener('click', function() {
        setTimeout(restaurerBrouillon, 500);
    });
});
</script>
</body>
</html>