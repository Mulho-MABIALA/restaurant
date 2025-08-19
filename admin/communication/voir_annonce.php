<?php
session_start();
require_once '../../config.php';

// Vérification de l'authentification
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
$annonce_id = $_GET['id'] ?? 0;

if (!$annonce_id) {
    header('Location: annonce.php');
    exit();
}

// Récupération de l'annonce
try {
    $stmt = $conn->prepare("
        SELECT a.*, c.nom as categorie_nom, c.couleur as categorie_couleur, c.icone as categorie_icone,
               ad.nom_utilisateur as admin_nom, ad.nom as admin_nom_complet, ad.prenom as admin_prenom
        FROM annonces a
        LEFT JOIN categories_annonces c ON a.categorie_id = c.id
        LEFT JOIN admins ad ON a.admin_id = ad.id
        WHERE a.id = ?
    ");
    $stmt->execute([$annonce_id]);
    $annonce = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$annonce) {
        header('Location: annonce.php');
        exit();
    }
    
    // Incrémenter le nombre de vues
    $stmt = $conn->prepare("UPDATE annonces SET nombre_vues = nombre_vues + 1 WHERE id = ?");
    $stmt->execute([$annonce_id]);
    
} catch (Exception $e) {
    header('Location: annonce.php');
    exit();
}

// Récupération des fichiers attachés
try {
    $stmt = $conn->prepare("SELECT * FROM annonces_fichiers WHERE annonce_id = ?");
    $stmt->execute([$annonce_id]);
    $fichiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $fichiers = [];
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

function formatTaillieFichier($taille) {
    if ($taille < 1024) {
        return $taille . ' B';
    } elseif ($taille < 1048576) {
        return round($taille / 1024, 2) . ' KB';
    } else {
        return round($taille / 1048576, 2) . ' MB';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($annonce['titre']) ?> - Détail Annonce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .annonce-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        .meta-info {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
        }
        .contenu-annonce {
            line-height: 1.8;
            font-size: 1.1rem;
        }
        .fichier-item {
            transition: all 0.3s ease;
        }
        .fichier-item:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-0">
    <div class="container">
        <a class="navbar-brand" href="annonce.php">
            <i class="fas fa-arrow-left me-2"></i>Retour aux annonces
        </a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="modifier_annonce.php?id=<?= $annonce['id'] ?>">
                <i class="fas fa-edit me-1"></i>Modifier
            </a>
            <a class="nav-link" href="annonce.php">
                <i class="fas fa-list me-1"></i>Liste
            </a>
        </div>
    </div>
</nav>

<!-- En-tête de l'annonce -->
<div class="annonce-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-3"><?= htmlspecialchars($annonce['titre']) ?></h1>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <?php if ($annonce['categorie_nom']): ?>
                        <span class="badge fs-6 me-2" style="background-color: <?= $annonce['categorie_couleur'] ?>">
                            <?php if ($annonce['categorie_icone']): ?>
                                <i class="<?= $annonce['categorie_icone'] ?> me-1"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($annonce['categorie_nom']) ?>
                        </span>
                    <?php endif; ?>
                    
                    <span class="badge bg-<?= getPrioriteColor($annonce['priorite']) ?> fs-6 me-2">
                        <i class="fas fa-flag me-1"></i>
                        <?= ucfirst($annonce['priorite']) ?>
                    </span>
                    
                    <span class="badge bg-<?= getStatutColor($annonce['statut']) ?> fs-6">
                        <i class="fas fa-circle me-1"></i>
                        <?= ucfirst($annonce['statut']) ?>
                    </span>
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="mt-3 mt-md-0">
                    <div class="fs-5">
                        <i class="fas fa-eye me-2"></i>
                        <?= $annonce['nombre_vues'] ?> vue<?= $annonce['nombre_vues'] > 1 ? 's' : '' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container my-4">
    <div class="row">
        <!-- Contenu principal -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-file-text me-2"></i>Contenu de l'annonce
                    </h5>
                </div>
                <div class="card-body">
                    <div class="contenu-annonce">
                        <?= nl2br(htmlspecialchars($annonce['contenu'])) ?>
                    </div>
                    
                    <?php if ($annonce['lien_externe']): ?>
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6><i class="fas fa-external-link-alt me-2"></i>Lien externe</h6>
                            <a href="<?= htmlspecialchars($annonce['lien_externe']) ?>" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-external-link-alt me-1"></i>
                                Accéder au lien
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Fichiers attachés -->
            <?php if (!empty($fichiers)): ?>
                <div class="card shadow-sm mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-paperclip me-2"></i>Fichiers attachés
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($fichiers as $fichier): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card fichier-item">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <?php
                                                    $extension = pathinfo($fichier['nom_original'], PATHINFO_EXTENSION);
                                                    $icone = match(strtolower($extension)) {
                                                        'pdf' => 'fas fa-file-pdf text-danger',
                                                        'doc', 'docx' => 'fas fa-file-word text-primary',
                                                        'xls', 'xlsx' => 'fas fa-file-excel text-success',
                                                        'ppt', 'pptx' => 'fas fa-file-powerpoint text-warning',
                                                        'jpg', 'jpeg', 'png', 'gif' => 'fas fa-file-image text-info',
                                                        'zip', 'rar' => 'fas fa-file-archive text-secondary',
                                                        default => 'fas fa-file text-muted'
                                                    };
                                                    ?>
                                                    <i class="<?= $icone ?> fa-2x"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?= htmlspecialchars($fichier['nom_original']) ?></h6>
                                                    <small class="text-muted">
                                                        <?= formatTaillieFichier($fichier['taille']) ?>
                                                        • <?= date('d/m/Y', strtotime($fichier['date_upload'])) ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <a href="<?= htmlspecialchars($fichier['chemin']) ?>" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar avec informations -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Informations
                    </h5>
                </div>
                <div class="card-body">
                    <div class="meta-info mb-3">
                        <h6><i class="fas fa-calendar-plus me-2"></i>Date de création</h6>
                        <p class="mb-0"><?= date('d/m/Y à H:i', strtotime($annonce['date_creation'])) ?></p>
                    </div>
                    
                    <?php if ($annonce['date_modification'] !== $annonce['date_creation']): ?>
                        <div class="meta-info mb-3">
                            <h6><i class="fas fa-calendar-edit me-2"></i>Dernière modification</h6>
                            <p class="mb-0"><?= date('d/m/Y à H:i', strtotime($annonce['date_modification'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($annonce['admin_nom']): ?>
                        <div class="meta-info mb-3">
                            <h6><i class="fas fa-user me-2"></i>Créé par</h6>
                            <p class="mb-0">
                                <?= htmlspecialchars($annonce['admin_nom']) ?>
                                <?php if ($annonce['admin_nom_complet'] && $annonce['admin_prenom']): ?>
                                    <small class="text-muted d-block">
                                        <?= htmlspecialchars($annonce['admin_prenom'] . ' ' . $annonce['admin_nom_complet']) ?>
                                    </small>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($annonce['date_debut'] || $annonce['date_fin']): ?>
                        <div class="meta-info mb-3">
                            <h6><i class="fas fa-calendar-alt me-2"></i>Période d'affichage</h6>
                            <?php if ($annonce['date_debut']): ?>
                                <p class="mb-1">
                                    <strong>Début :</strong> <?= date('d/m/Y', strtotime($annonce['date_debut'])) ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($annonce['date_fin']): ?>
                                <p class="mb-0">
                                    <strong>Fin :</strong> <?= date('d/m/Y', strtotime($annonce['date_fin'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <a href="modifier_annonce.php?id=<?= $annonce['id'] ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-2"></i>Modifier l'annonce
                        </a>
                        <button class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Imprimer
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>Actions rapides
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($annonce['statut'] !== 'publie'): ?>
                            <button class="btn btn-success btn-sm" onclick="changerStatut(<?= $annonce['id'] ?>, 'publie')">
                                <i class="fas fa-check me-1"></i>Publier
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($annonce['statut'] !== 'brouillon'): ?>
                            <button class="btn btn-warning btn-sm" onclick="changerStatut(<?= $annonce['id'] ?>, 'brouillon')">
                                <i class="fas fa-edit me-1"></i>Mettre en brouillon
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($annonce['statut'] !== 'archive'): ?>
                            <button class="btn btn-secondary btn-sm" onclick="changerStatut(<?= $annonce['id'] ?>, 'archive')">
                                <i class="fas fa-archive me-1"></i>Archiver
                            </button>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <button class="btn btn-danger btn-sm" onclick="supprimerAnnonce(<?= $annonce['id'] ?>)">
                            <i class="fas fa-trash me-1"></i>Supprimer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Formulaires cachés pour les actions -->
<form id="formChangerStatut" method="POST" action="annonce.php" style="display: none;">
    <input type="hidden" name="action" value="changer_statut">
    <input type="hidden" name="annonce_id" id="statutAnnonceId">
    <input type="hidden" name="nouveau_statut" id="nouveauStatut">
</form>

<form id="formSupprimerAnnonce" method="POST" action="annonce.php" style="display: none;">
    <input type="hidden" name="action" value="supprimer">
    <input type="hidden" name="annonce_id" id="supprimerAnnonceId">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function changerStatut(annonceId, nouveauStatut) {
    const messages = {
        'publie': 'Êtes-vous sûr de vouloir publier cette annonce ?',
        'brouillon': 'Êtes-vous sûr de vouloir mettre cette annonce en brouillon ?',
        'archive': 'Êtes-vous sûr de vouloir archiver cette annonce ?'
    };
    
    if (confirm(messages[nouveauStatut] || 'Êtes-vous sûr de vouloir changer le statut ?')) {
        document.getElementById('statutAnnonceId').value = annonceId;
        document.getElementById('nouveauStatut').value = nouveauStatut;
        document.getElementById('formChangerStatut').submit();
    }
}

function supprimerAnnonce(annonceId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette annonce ?\n\nCette action est irréversible et supprimera également tous les fichiers attachés.')) {
        document.getElementById('supprimerAnnonceId').value = annonceId;
        document.getElementById('formSupprimerAnnonce').submit();
    }
}

// Amélioration de l'affichage pour l'impression
window.addEventListener('beforeprint', function() {
    document.body.classList.add('printing');
});

window.addEventListener('afterprint', function() {
    document.body.classList.remove('printing');
});
</script>

<style media="print">
    .navbar, .card-header, .btn, .meta-info { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    .container { max-width: none !important; }
    body.printing .contenu-annonce { font-size: 14px !important; }
</style>

</body>
</html>