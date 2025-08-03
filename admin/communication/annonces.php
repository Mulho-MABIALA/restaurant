<?php
// annonces.php

require_once '../../config.php'; // Inclusion du fichier de configuration

// Connexion à la base de données (supposons que $pdo est défini dans config.php)
if (!isset($conn)) {
    die('Erreur de connexion à la base de données.');
}

// Création des tables si elles n'existent pas
$conn->exec("
    CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(100) NOT NULL,
        couleur VARCHAR(7) DEFAULT '#007bff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$conn->exec("
    CREATE TABLE IF NOT EXISTS annonces (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titre VARCHAR(255) NOT NULL,
        contenu TEXT NOT NULL,
        categorie_id INT,
        priorite ENUM('basse', 'normale', 'haute') DEFAULT 'normale',
        statut ENUM('brouillon', 'actif', 'inactif', 'expire') DEFAULT 'actif',
        date_expiration DATE NULL,
        image_path VARCHAR(255) NULL,
        vues INT DEFAULT 0,
        date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL
    )
");

$conn->exec("
    CREATE TABLE IF NOT EXISTS annonce_tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        annonce_id INT,
        tag VARCHAR(50),
        FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE
    )
");

// Insérer quelques catégories par défaut
$stmt = $conn->query("SELECT COUNT(*) FROM categories");
if ($stmt->fetchColumn() == 0) {
    $categories_defaut = [
        ['Urgent', '#dc3545'],
        ['Information', '#007bff'],
        ['Promotion', '#28a745'],
        ['Événement', '#fd7e14'],
        ['Maintenance', '#6c757d']
    ];
    
    foreach ($categories_defaut as $cat) {
        $stmt = $conn->prepare("INSERT INTO categories (nom, couleur) VALUES (?, ?)");
        $stmt->execute($cat);
    }
}

$message = '';
$message_type = 'info';

// Traitement de l'upload d'image
function handleImageUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Type de fichier non autorisé');
    }
    
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filepath;
    }
    
    throw new Exception('Erreur lors de l\'upload');
}

// Traitement du formulaire d'ajout/modification d'annonce
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $titre = trim($_POST['titre']);
    $contenu = trim($_POST['contenu']);
    $categorie_id = $_POST['categorie_id'] ?: null;
    $priorite = $_POST['priorite'];
    $statut = $_POST['statut'];
    $date_expiration = $_POST['date_expiration'] ?: null;
    $tags = array_filter(array_map('trim', explode(',', (string)($_POST['tags'] ?? ''))));
    $tags = explode(',', (string)($annonce['tags'] ?? ''));  // ✅ sûr

    
    if ($titre && $contenu) {
        try {
            $image_path = null;
            if (isset($_FILES['image'])) {
                $image_path = handleImageUpload($_FILES['image']);
            }
            
            if ($_POST['action'] === 'add') {
                // Ajout d'annonce
                $stmt = $conn->prepare('INSERT INTO annonces (titre, contenu, categorie_id, priorite, statut, date_expiration, image_path, date_creation) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$titre, $contenu, $categorie_id, $priorite, $statut, $date_expiration, $image_path]);
                $annonce_id = $conn->lastInsertId();
                
                // Ajout des tags
                foreach ($tags as $tag) {
                    $stmt = $conn->prepare('INSERT INTO annonce_tags (annonce_id, tag) VALUES (?, ?)');
                    $stmt->execute([$annonce_id, $tag]);
                }
                
                $message = 'Annonce ajoutée avec succès.';
                $message_type = 'success';
            } elseif ($_POST['action'] === 'edit') {
                // Modification d'annonce
                $id = intval($_POST['id']);
                
                // Garder l'ancienne image si pas de nouvelle
                if (!$image_path) {
                    $stmt = $conn->prepare('SELECT image_path FROM annonces WHERE id = ?');
                    $stmt->execute([$id]);
                    $image_path = $stmt->fetchColumn();
                }
                
                $stmt = $conn->prepare('UPDATE annonces SET titre = ?, contenu = ?, categorie_id = ?, priorite = ?, statut = ?, date_expiration = ?, image_path = ? WHERE id = ?');
                $stmt->execute([$titre, $contenu, $categorie_id, $priorite, $statut, $date_expiration, $image_path, $id]);
                
                // Supprimer les anciens tags et ajouter les nouveaux
                $stmt = $conn->prepare('DELETE FROM annonce_tags WHERE annonce_id = ?');
                $stmt->execute([$id]);
                
                foreach ($tags as $tag) {
                    $stmt = $conn->prepare('INSERT INTO annonce_tags (annonce_id, tag) VALUES (?, ?)');
                    $stmt->execute([$id, $tag]);
                }
                
                $message = 'Annonce modifiée avec succès.';
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = 'Erreur : ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'Veuillez remplir tous les champs obligatoires.';
        $message_type = 'warning';
    }
}

// Suppression d'une annonce
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Supprimer l'image associée
    $stmt = $conn->prepare('SELECT image_path FROM annonces WHERE id = ?');
    $stmt->execute([$id]);
    $image_path = $stmt->fetchColumn();
    if ($image_path && file_exists($image_path)) {
        unlink($image_path);
    }
    
    $stmt = $conn->prepare('DELETE FROM annonces WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: annonces.php');
    exit;
}

// Changement de statut
if (isset($_GET['toggle_status'])) {
    $id = intval($_GET['toggle_status']);
    $stmt = $conn->prepare('UPDATE annonces SET statut = CASE WHEN statut = "actif" THEN "inactif" ELSE "actif" END WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: annonces.php');
    exit;
}

// Incrémenter les vues
if (isset($_GET['view'])) {
    $id = intval($_GET['view']);
    $stmt = $conn->prepare('UPDATE annonces SET vues = vues + 1 WHERE id = ?');
    $stmt->execute([$id]);
}

// Récupération des données pour les filtres
$search = $_GET['search'] ?? '';
$filter_categorie = $_GET['categorie'] ?? '';
$filter_statut = $_GET['statut'] ?? '';
$filter_priorite = $_GET['priorite'] ?? '';
$sort = $_GET['sort'] ?? 'date_creation';
$order = $_GET['order'] ?? 'DESC';

// Construction de la requête avec filtres
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(a.titre LIKE ? OR a.contenu LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_categorie) {
    $where_conditions[] = "a.categorie_id = ?";
    $params[] = $filter_categorie;
}

if ($filter_statut) {
    $where_conditions[] = "a.statut = ?";
    $params[] = $filter_statut;
}

if ($filter_priorite) {
    $where_conditions[] = "a.priorite = ?";
    $params[] = $filter_priorite;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Récupération des annonces avec filtres
$query = "
    SELECT a.*, c.nom as categorie_nom, c.couleur as categorie_couleur,
           GROUP_CONCAT(t.tag) as tags
    FROM annonces a 
    LEFT JOIN categories c ON a.categorie_id = c.id
    LEFT JOIN annonce_tags t ON a.id = t.annonce_id
    $where_clause
    GROUP BY a.id
    ORDER BY $sort $order
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des catégories
$categories = $conn->query('SELECT * FROM categories ORDER BY nom')->fetchAll(PDO::FETCH_ASSOC);

// Données pour l'édition
$annonce_edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare('
        SELECT a.*, GROUP_CONCAT(t.tag) as tags 
        FROM annonces a 
        LEFT JOIN annonce_tags t ON a.id = t.annonce_id 
        WHERE a.id = ? 
        GROUP BY a.id
    ');
    $stmt->execute([$id]);
    $annonce_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Statistiques pour le dashboard
$stats = [];
$stats['total'] = $conn->query('SELECT COUNT(*) FROM annonces')->fetchColumn();
$stats['actives'] = $conn->query('SELECT COUNT(*) FROM annonces WHERE statut = "actif"')->fetchColumn();
$stats['expires'] = $conn->query('SELECT COUNT(*) FROM annonces WHERE date_expiration < CURDATE() AND statut = "actif"')->fetchColumn();
$stats['vues_total'] = $conn->query('SELECT SUM(vues) FROM annonces')->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des annonces - Version complète</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #f8fafc;
            --accent-color: #059669;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --success-color: #16a34a;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 2rem auto;
            padding: 2rem;
            max-width: 1400px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            padding-bottom: 1.5rem;
            border-bottom: 3px solid var(--primary-color);
        }

        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff, #f8fafc);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .filters-section {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .form-section {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #1d4ed8);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
        }

        .table-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-color), #1d4ed8);
            color: white;
            font-weight: 600;
            padding: 1rem;
            border: none;
            cursor: pointer;
            user-select: none;
        }

        .table thead th:hover {
            background: linear-gradient(135deg, #1d4ed8, var(--primary-color));
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .priority-basse { background: #e5e7eb; color: #6b7280; }
        .priority-normale { background: #dbeafe; color: #1e40af; }
        .priority-haute { background: #fee2e2; color: #dc2626; }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-actif { background: #dcfce7; color: #16a34a; }
        .status-inactif { background: #fee2e2; color: #dc2626; }
        .status-brouillon { background: #fef3c7; color: #d97706; }
        .status-expire { background: #f3f4f6; color: #6b7280; }

        .category-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
        }

        .tag {
            display: inline-block;
            background: #e5e7eb;
            color: #374151;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin: 0.1rem;
        }

        .image-preview {
            max-width: 100px;
            max-height: 60px;
            border-radius: 8px;
            object-fit: cover;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #1d4ed8);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        @media (max-width: 768px) {
            .main-container {
                margin: 1rem;
                padding: 1rem;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-container">
            <!-- En-tête de la page -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-bullhorn"></i>
                    Gestion des annonces
                </h1>
                <p class="text-muted">Système complet de gestion d'annonces</p>
            </div>

            <!-- Dashboard statistiques -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number text-primary"><?= $stats['total'] ?></div>
                    <div class="text-muted">Total annonces</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-success"><?= $stats['actives'] ?></div>
                    <div class="text-muted">Actives</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-danger"><?= $stats['expires'] ?></div>
                    <div class="text-muted">Expirées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-info"><?= $stats['vues_total'] ?></div>
                    <div class="text-muted">Vues totales</div>
                </div>
            </div>

            <!-- Section des filtres -->
            <div class="filters-section">
                <h3 class="section-title">
                    <i class="fas fa-filter"></i>
                    Filtres et recherche
                </h3>
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="categorie" class="form-select">
                            <option value="">Toutes catégories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $filter_categorie == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="statut" class="form-select">
                            <option value="">Tous statuts</option>
                            <option value="brouillon" <?= $filter_statut == 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                            <option value="actif" <?= $filter_statut == 'actif' ? 'selected' : '' ?>>Actif</option>
                            <option value="inactif" <?= $filter_statut == 'inactif' ? 'selected' : '' ?>>Inactif</option>
                            <option value="expire" <?= $filter_statut == 'expire' ? 'selected' : '' ?>>Expiré</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="priorite" class="form-select">
                            <option value="">Toutes priorités</option>
                            <option value="basse" <?= $filter_priorite == 'basse' ? 'selected' : '' ?>>Basse</option>
                            <option value="normale" <?= $filter_priorite == 'normale' ? 'selected' : '' ?>>Normale</option>
                            <option value="haute" <?= $filter_priorite == 'haute' ? 'selected' : '' ?>>Haute</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrer
                        </button>
                    </div>
                    <div class="col-md-1">
                        <a href="annonces.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Messages toast -->
            <?php if ($message): ?>
                <div class="toast-container">
                    <div class="toast show" role="alert">
                        <div class="toast-header">
                            <i class="fas fa-info-circle text-<?= $message_type ?> me-2"></i>
                            <strong class="me-auto">Notification</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Section du formulaire -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-<?= $annonce_edit ? 'edit' : 'plus-circle' ?>"></i>
                    <?= $annonce_edit ? 'Modifier l\'annonce' : 'Nouvelle annonce' ?>
                </h2>
                
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?= $annonce_edit ? 'edit' : 'add' ?>">
                    <?php if ($annonce_edit): ?>
                        <input type="hidden" name="id" value="<?= $annonce_edit['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="titre" class="form-label">
                                <i class="fas fa-heading me-2"></i>Titre *
                            </label>
                            <input type="text" name="titre" id="titre" class="form-control" 
                                   value="<?= $annonce_edit ? htmlspecialchars($annonce_edit['titre']) : '' ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="categorie_id" class="form-label">
                                <i class="fas fa-folder me-2"></i>Catégorie
                            </label>
                            <select name="categorie_id" id="categorie_id" class="form-select">
                                <option value="">Aucune catégorie</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" 
                                            <?= ($annonce_edit && $annonce_edit['categorie_id'] == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="priorite" class="form-label">
                                <i class="fas fa-exclamation-triangle me-2"></i>Priorité
                            </label>
                            <select name="priorite" id="priorite" class="form-select">
                                <option value="basse" <?= ($annonce_edit && $annonce_edit['priorite'] == 'basse') ? 'selected' : '' ?>>Basse</option>
                                <option value="normale" <?= ($annonce_edit && $annonce_edit['priorite'] == 'normale') || !$annonce_edit ? 'selected' : '' ?>>Normale</option>
                                <option value="haute" <?= ($annonce_edit && $annonce_edit['priorite'] == 'haute') ? 'selected' : '' ?>>Haute</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="statut" class="form-label">
                                <i class="fas fa-toggle-on me-2"></i>Statut
                            </label>
                            <select name="statut" id="statut" class="form-select">
                                <option value="brouillon" <?= ($annonce_edit && $annonce_edit['statut'] == 'brouillon') ? 'selected' : '' ?>>Brouillon</option>
                                <option value="actif" <?= ($annonce_edit && $annonce_edit['statut'] == 'actif') || !$annonce_edit ? 'selected' : '' ?>>Actif</option>
                                <option value="inactif" <?= ($annonce_edit && $annonce_edit['statut'] == 'inactif') ? 'selected' : '' ?>>Inactif</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="date_expiration" class="form-label">
                                <i class="fas fa-calendar-times me-2"></i>Date d'expiration
                            </label>
                            <input type="date" name="date_expiration" id="date_expiration" class="form-control"
                                   value="<?= $annonce_edit ? $annonce_edit['date_expiration'] : '' ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="image" class="form-label">
                                <i class="fas fa-image me-2"></i>Image
                            </label>
                            <input type="file" name="image" id="image" class="form-control" accept="image/*">
                            <?php if ($annonce_edit && $annonce_edit['image_path']): ?>
                                <small class="text-muted">Image actuelle : <?= basename($annonce_edit['image_path']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contenu" class="form-label">
                            <i class="fas fa-edit me-2"></i>Contenu *
                        </label>
                        <textarea name="contenu" id="contenu" class="form-control" rows="6" required><?= $annonce_edit ? htmlspecialchars($annonce_edit['contenu']) : '' ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-<?= $annonce_edit ? 'save' : 'plus' ?> me-2"></i>
                            <?= $annonce_edit ? 'Modifier' : 'Ajouter' ?> l'annonce
                        </button>
                        <?php if ($annonce_edit): ?>
                            <a href="annonces.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-primary" onclick="previewAnnonce()">
                            <i class="fas fa-eye me-2"></i>Aperçu
                        </button>
                    </div>
                </form>
            </div>

            <!-- Section de la liste des annonces -->
            <div class="table-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="section-title mb-0">
                        <i class="fas fa-list"></i>
                        Liste des annonces
                        <span class="badge bg-primary ms-2"><?= count($annonces) ?></span>
                    </h2>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-download me-2"></i>Exporter
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-2"></i>PDF
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportToCSV()">
                                <i class="fas fa-file-csv me-2"></i>CSV
                            </a></li>
                        </ul>
                    </div>
                </div>
                
                <?php if (empty($annonces)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Aucune annonce trouvée</h4>
                        <p class="text-muted">Commencez par créer votre première annonce ou modifiez vos filtres.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th onclick="sortTable('titre')">
                                        <i class="fas fa-heading me-2"></i>Titre
                                        <i class="fas fa-sort"></i>
                                    </th>
                                    <th onclick="sortTable('categorie_nom')">
                                        <i class="fas fa-folder me-2"></i>Catégorie
                                        <i class="fas fa-sort"></i>
                                    </th>
                                    <th onclick="sortTable('priorite')">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Priorité
                                        <i class="fas fa-sort"></i>
                                    </th>
                                    <th onclick="sortTable('statut')">
                                        <i class="fas fa-toggle-on me-2"></i>Statut
                                        <i class="fas fa-sort"></i>
                                    </th>
                                    <th onclick="sortTable('vues')">
                                        <i class="fas fa-eye me-2"></i>Vues
                                        <i class="fas fa-sort"></i>
                                    </th>
                                    <th onclick="sortTable('date_creation')">
                                        <i class="fas fa-calendar me-2"></i>Créée le
                                        <i class="fas fa-sort"></i>
                                    </th>
                                    <th><i class="fas fa-cogs me-2"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                          <?php foreach ($annonces as $annonce): ?>
    <tr>
        <td>
            <div class="d-flex align-items-center">
                <?php if (!empty($annonce['image_path'])): ?>
                    <img src="<?= htmlspecialchars($annonce['image_path']) ?>" class="image-preview me-2" alt="Image">
                <?php endif; ?>
                <div>
                    <strong><?= htmlspecialchars($annonce['titre'] ?? 'Sans titre') ?></strong>
                    <?php if (!empty($annonce['tags'])): ?>
                        <div class="mt-1">
                            <?php foreach (explode(',', (string)$annonce['tags']) as $tag): ?>
                                <span class="tag"><?= htmlspecialchars(trim($tag)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </td>
        <td>
            <?php if (!empty($annonce['categorie_nom'])): ?>
                <span class="category-badge" style="background-color: <?= htmlspecialchars($annonce['categorie_couleur'] ?? '#ccc') ?>">
                    <?= htmlspecialchars($annonce['categorie_nom']) ?>
                </span>
            <?php else: ?>
                <span class="text-muted">Aucune</span>
            <?php endif; ?>
        </td>
        <td>
            <span class="priority-badge priority-<?= htmlspecialchars($annonce['priorite'] ?? 'basse') ?>">
                <?= ucfirst((string)($annonce['priorite'] ?? 'basse')) ?>
            </span>
        </td>
        <td>
            <span class="status-badge status-<?= htmlspecialchars($annonce['statut'] ?? 'inactif') ?>">
                <?= ucfirst((string)($annonce['statut'] ?? 'inactif')) ?>
            </span>
            <?php if (!empty($annonce['date_expiration']) && $annonce['date_expiration'] < date('Y-m-d')): ?>
                <i class="fas fa-exclamation-triangle text-warning ms-1" title="Expirée"></i>
            <?php endif; ?>
        </td>
        <td>
            <span class="badge bg-info"><?= htmlspecialchars((string)($annonce['vues'] ?? 0)) ?></span>
        </td>
        <td>
            <small class="text-muted">
                <?= !empty($annonce['date_creation']) ? date('d/m/Y H:i', strtotime($annonce['date_creation'])) : '—' ?>
            </small>
        </td>
        <td>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-info" onclick="viewAnnonce(<?= (int)$annonce['id'] ?>)" title="Voir">
                    <i class="fas fa-eye"></i>
                </button>
                <a href="?edit=<?= (int)$annonce['id'] ?>" class="btn btn-outline-primary" title="Modifier">
                    <i class="fas fa-edit"></i>
                </a>
                <a href="?toggle_status=<?= (int)$annonce['id'] ?>" 
                   class="btn btn-outline-<?= ($annonce['statut'] ?? '') === 'actif' ? 'warning' : 'success' ?>" 
                   title="<?= ($annonce['statut'] ?? '') === 'actif' ? 'Désactiver' : 'Activer' ?>">
                    <i class="fas fa-toggle-<?= ($annonce['statut'] ?? '') === 'actif' ? 'off' : 'on' ?>"></i>
                </a>
                <button class="btn btn-outline-danger" onclick="confirmDelete(<?= (int)$annonce['id'] ?>)" title="Supprimer">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    </tr>
<?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirmer la suppression
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer cette annonce ?</p>
                    <p class="text-danger"><small>Cette action est irréversible.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Supprimer
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal d'aperçu -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>
                        Aperçu de l'annonce
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="previewContent">
                    <!-- Contenu généré par JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de visualisation -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-alt me-2"></i>
                        Détails de l'annonce
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewContent">
                    <!-- Contenu chargé par AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/lang/summernote-fr-FR.min.js"></script>
    
    <script>
        // Initialisation de l'éditeur de texte riche
        $(document).ready(function() {
            $('#contenu').summernote({
                height: 200,
                lang: 'fr-FR',
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['fontname', ['fontname']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });

            // Auto-dismiss des toasts
            $('.toast').toast({delay: 5000});
            setTimeout(() => {
                $('.toast').toast('hide');
            }, 5000);
        });

        // Fonction de tri des colonnes
        function sortTable(column) {
            const url = new URL(window.location);
            const currentSort = url.searchParams.get('sort');
            const currentOrder = url.searchParams.get('order') || 'DESC';
            
            if (currentSort === column) {
                url.searchParams.set('order', currentOrder === 'DESC' ? 'ASC' : 'DESC');
            } else {
                url.searchParams.set('sort', column);
                url.searchParams.set('order', 'DESC');
            }
            
            window.location.href = url.toString();
        }

        // Confirmation de suppression
        function confirmDelete(id) {
            document.getElementById('confirmDeleteBtn').href = '?delete=' + id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Aperçu de l'annonce
        function previewAnnonce() {
            const titre = document.getElementById('titre').value;
            const contenu = $('#contenu').summernote('code');
            const categorie = document.getElementById('categorie_id').selectedOptions[0]?.text || 'Aucune catégorie';
            const priorite = document.getElementById('priorite').value;
            const statut = document.getElementById('statut').value;
            const tags = document.getElementById('tags').value;
            
            const previewHtml = `
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">${titre}</h5>
                        <div>
                            <span class="badge bg-primary">${categorie}</span>
                            <span class="badge bg-${priorite === 'haute' ? 'danger' : priorite === 'normale' ? 'primary' : 'secondary'}">${priorite}</span>
                            <span class="badge bg-${statut === 'actif' ? 'success' : 'warning'}">${statut}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">${contenu}</div>
                        ${tags ? `<div class="mb-2"><strong>Tags:</strong> ${tags.split(',').map(tag => `<span class="badge bg-light text-dark me-1">${tag.trim()}</span>`).join('')}</div>` : ''}
                        <small class="text-muted">Aperçu généré le ${new Date().toLocaleString('fr-FR')}</small>
                    </div>
                </div>
            `;
            
            document.getElementById('previewContent').innerHTML = previewHtml;
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }

        // Visualisation d'une annonce
        function viewAnnonce(id) {
            // Incrémenter le compteur de vues
            fetch(`?view=${id}`, {method: 'GET'});
            
            // Simuler le chargement des détails (vous pouvez implémenter un endpoint AJAX)
            const viewHtml = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2">Chargement des détails...</p>
                </div>
            `;
            
            document.getElementById('viewContent').innerHTML = viewHtml;
            new bootstrap.Modal(document.getElementById('viewModal')).show();
            
            // Simuler le chargement
            setTimeout(() => {
                document.getElementById('viewContent').innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Vue incrémentée ! Détails complets disponibles via AJAX.
                    </div>
                `;
            }, 1000);
        }

        // Export PDF (placeholder)
        function exportToPDF() {
            alert('Fonctionnalité d\'export PDF en cours de développement. Vous pouvez implémenter ceci avec une bibliothèque comme TCPDF ou mPDF.');
        }

        // Export CSV
        function exportToCSV() {
            // Récupérer les données du tableau
            const table = document.querySelector('.table');
            const rows = Array.from(table.querySelectorAll('tr'));
            
            let csv = [];
            rows.forEach(row => {
                const cols = Array.from(row.querySelectorAll('th, td'));
                const rowData = cols.map(col => `"${col.textContent.trim().replace(/"/g, '""')}"`);
                csv.push(rowData.join(','));
            });
            
            // Créer le fichier et le télécharger
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `annonces_${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Recherche en temps réel
        let searchTimeout;
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });

        // Sauvegarder automatiquement en brouillon
        let autoSaveTimeout;
        ['titre', 'contenu'].forEach(field => {
            const element = document.getElementById(field);
            if (element) {
                element.addEventListener('input', function() {
                    clearTimeout(autoSaveTimeout);
                    autoSaveTimeout = setTimeout(() => {
                        // Ici vous pouvez implémenter la sauvegarde automatique via AJAX
                        console.log('Auto-save triggered for:', field);
                    }, 2000);
                });
            }
        });
    </script>
</body>
</html>