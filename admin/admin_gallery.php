<?php
session_start();

// Vérification de l'authentification admin (à adapter selon votre système)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Page de connexion simple (remplacez par votre système d'auth)
    if (isset($_POST['admin_login'])) {
        $admin_username = 'admin'; // À changer
        $admin_password = 'mulho2024'; // À changer et hasher
        
        if ($_POST['username'] === $admin_username && $_POST['password'] === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin_gallery.php');
            exit;
        } else {
            $error_message = "Identifiants incorrects";
        }
    }
    
    // Affichage du formulaire de connexion
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <title>Connexion Admin - Galerie Mulho</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 50px 20px; }
            .login-container { max-width: 400px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .login-container h2 { text-align: center; margin-bottom: 30px; color: #333; }
            .form-group { margin-bottom: 20px; }
            .form-group label { display: block; margin-bottom: 5px; color: #555; }
            .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
            .btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #ec4899, #f97316); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
            .btn:hover { opacity: 0.9; }
            .error { color: #e74c3c; text-align: center; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Administration - Galerie Mulho</h2>
            <?php if (isset($error_message)): ?>
                <div class="error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Nom d'utilisateur:</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Mot de passe:</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" name="admin_login" class="btn">Se connecter</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Gestion des actions CRUD
$message = '';
$error = '';

// Création du dossier uploads s'il n'existe pas
$upload_dir = 'assets/img/gallery/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Fichier JSON pour stocker les données de la galerie
$gallery_data_file = 'gallery_data.json';

// Fonction pour lire les données de la galerie
function getGalleryData() {
    global $gallery_data_file;
    if (file_exists($gallery_data_file)) {
        $json = file_get_contents($gallery_data_file);
        return json_decode($json, true) ?: [];
    }
    return [];
}

// Fonction pour sauvegarder les données de la galerie
function saveGalleryData($data) {
    global $gallery_data_file;
    return file_put_contents($gallery_data_file, json_encode($data, JSON_PRETTY_PRINT));
}

// Ajout d'une nouvelle image
if (isset($_POST['add_image'])) {
    $title = trim($_POST['title']);
    $category = $_POST['category'];
    
    if (empty($title) || empty($category)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        
        if (!in_array($file['type'], $allowed_types)) {
            $error = "Type de fichier non autorisé. Utilisez JPEG, PNG ou WebP.";
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB max
            $error = "Le fichier est trop volumineux (max 5MB).";
        } else {
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $gallery_data = getGalleryData();
                $new_image = [
                    'id' => uniqid(),
                    'img' => $upload_path,
                    'title' => $title,
                    'category' => $category,
                    'date_added' => date('Y-m-d H:i:s')
                ];
                $gallery_data[] = $new_image;
                
                if (saveGalleryData($gallery_data)) {
                    $message = "Image ajoutée avec succès !";
                } else {
                    $error = "Erreur lors de la sauvegarde des données.";
                }
            } else {
                $error = "Erreur lors du téléchargement du fichier.";
            }
        }
    } else {
        $error = "Veuillez sélectionner une image.";
    }
}

// Suppression d'une image
if (isset($_POST['delete_image'])) {
    $image_id = $_POST['image_id'];
    $gallery_data = getGalleryData();
    
    foreach ($gallery_data as $key => $item) {
        if ($item['id'] === $image_id) {
            // Supprimer le fichier physique
            if (file_exists($item['img'])) {
                unlink($item['img']);
            }
            // Supprimer l'entrée du tableau
            unset($gallery_data[$key]);
            break;
        }
    }
    
    $gallery_data = array_values($gallery_data); // Réindexer le tableau
    
    if (saveGalleryData($gallery_data)) {
        $message = "Image supprimée avec succès !";
    } else {
        $error = "Erreur lors de la suppression.";
    }
}

// Modification d'une image
if (isset($_POST['edit_image'])) {
    $image_id = $_POST['image_id'];
    $title = trim($_POST['title']);
    $category = $_POST['category'];
    
    if (empty($title) || empty($category)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        $gallery_data = getGalleryData();
        
        foreach ($gallery_data as &$item) {
            if ($item['id'] === $image_id) {
                $item['title'] = $title;
                $item['category'] = $category;
                break;
            }
        }
        
        if (saveGalleryData($gallery_data)) {
            $message = "Image modifiée avec succès !";
        } else {
            $error = "Erreur lors de la modification.";
        }
    }
}

$gallery_data = getGalleryData();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Galerie Mulho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #ec4899, #f97316);
            --dark-bg: #0f172a;
        }
        
        body {
            background-color: #f8fafc;
            font-family: 'Inter', sans-serif;
        }
        
        .admin-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-bottom: 1px solid #e2e8f0;
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
            color: #1e293b;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .btn-danger {
            border-radius: 8px;
        }
        
        .btn-warning {
            border-radius: 8px;
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .gallery-item {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        
        .gallery-item:hover {
            transform: translateY(-5px);
        }
        
        .gallery-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .gallery-item-info {
            padding: 1.5rem;
        }
        
        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .category-plats { background: #fef3c7; color: #92400e; }
        .category-ambiance { background: #dbeafe; color: #1e40af; }
        .category-evenements { background: #f3e8ff; color: #7c3aed; }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 12px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #ec4899;
            box-shadow: 0 0 0 0.2rem rgba(236, 72, 153, 0.25);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .logout-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .stats-row {
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            color: #64748b;
            font-weight: 500;
            margin-top: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .gallery-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Bouton de déconnexion -->
    <a href="?logout=1" class="btn btn-outline-danger logout-btn">
        <i class="fas fa-sign-out-alt"></i> Déconnexion
    </a>
    
    <?php
    // Gestion de la déconnexion
    if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: admin_gallery.php');
        exit;
    }
    ?>

    <!-- Header -->
    <div class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-images me-2"></i>
                        Administration - Galerie Mulho
                    </h1>
                    <p class="mb-0 opacity-75">Gérez les images de votre galerie</p>
                </div>
                <div class="col-auto">
                    <a href="galerie.php" class="btn btn-light" target="_blank">
                        <i class="fas fa-eye me-1"></i> Voir la galerie
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-row">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= count($gallery_data) ?></div>
                        <div class="stat-label">Total d'images</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= count(array_filter($gallery_data, fn($item) => $item['category'] === 'plats')) ?></div>
                        <div class="stat-label">Plats</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= count(array_filter($gallery_data, fn($item) => $item['category'] === 'ambiance')) ?></div>
                        <div class="stat-label">Ambiance</div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= count(array_filter($gallery_data, fn($item) => $item['category'] === 'evenements')) ?></div>
                        <div class="stat-label">Événements</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire d'ajout -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    Ajouter une nouvelle image
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Titre de l'image *</label>
                            <input type="text" name="title" class="form-control" required 
                                   placeholder="Ex: Thieboudienne Royal">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Catégorie *</label>
                            <select name="category" class="form-select" required>
                                <option value="">Sélectionner une catégorie</option>
                                <option value="plats">Nos Plats</option>
                                <option value="ambiance">Ambiance</option>
                                <option value="evenements">Événements</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Image *</label>
                            <input type="file" name="image" class="form-control" required 
                                   accept="image/jpeg,image/jpg,image/png,image/webp">
                            <div class="form-text">
                                Formats acceptés: JPEG, PNG, WebP. Taille max: 5MB.
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_image" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Ajouter l'image
                    </button>
                </form>
            </div>
        </div>

        <!-- Liste des images -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Images de la galerie (<?= count($gallery_data) ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($gallery_data)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-images fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucune image dans la galerie</h5>
                        <p class="text-muted">Ajoutez votre première image en utilisant le formulaire ci-dessus.</p>
                    </div>
                <?php else: ?>
                    <div class="gallery-grid">
                        <?php foreach ($gallery_data as $item): ?>
                            <div class="gallery-item">
                                <img src="<?= htmlspecialchars($item['img']) ?>" 
                                     alt="<?= htmlspecialchars($item['title']) ?>">
                                <div class="gallery-item-info">
                                    <span class="category-badge category-<?= $item['category'] ?>">
                                        <?= ucfirst($item['category']) ?>
                                    </span>
                                    <h6 class="mb-2"><?= htmlspecialchars($item['title']) ?></h6>
                                    <small class="text-muted d-block mb-3">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= date('d/m/Y H:i', strtotime($item['date_added'])) ?>
                                    </small>
                                    
                                    <div class="d-flex gap-2">
                                        <!-- Bouton Modifier -->
                                        <button class="btn btn-warning btn-sm flex-fill" 
                                                onclick="editImage('<?= $item['id'] ?>', '<?= htmlspecialchars($item['title']) ?>', '<?= $item['category'] ?>')">
                                            <i class="fas fa-edit me-1"></i>Modifier
                                        </button>
                                        
                                        <!-- Bouton Supprimer -->
                                        <form method="POST" class="d-inline flex-fill" 
                                              onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette image ?')">
                                            <input type="hidden" name="image_id" value="<?= $item['id'] ?>">
                                            <button type="submit" name="delete_image" class="btn btn-danger btn-sm w-100">
                                                <i class="fas fa-trash me-1"></i>Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de modification -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Modifier l'image
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="image_id" id="editImageId">
                        <div class="mb-3">
                            <label class="form-label">Titre de l'image *</label>
                            <input type="text" name="title" id="editTitle" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catégorie *</label>
                            <select name="category" id="editCategory" class="form-select" required>
                                <option value="plats">Nos Plats</option>
                                <option value="ambiance">Ambiance</option>
                                <option value="evenements">Événements</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="edit_image" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Sauvegarder
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editImage(id, title, category) {
            document.getElementById('editImageId').value = id;
            document.getElementById('editTitle').value = title;
            document.getElementById('editCategory').value = category;
            
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
    </script>
</body>
</html>
<?php
// Déconnexion en cas de fermeture de session
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_gallery.php');
    exit;
}
?>