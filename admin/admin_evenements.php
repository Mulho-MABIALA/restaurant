<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Création du dossier uploads si nécessaire
if (!is_dir('uploads/evenements/')) {
    mkdir('uploads/evenements/', 0755, true);
}

// Variables pour les messages
$message = '';
$messageType = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'ajouter':
            $message = ajouterEvenement($conn);
            break;
        case 'modifier':
            $message = modifierEvenement($conn);
            break;
        case 'supprimer':
            $message = supprimerEvenement($conn);
            break;
    }
    
    $messageType = strpos($message, 'Erreur') !== false ? 'error' : 'success';
}

// Récupérer tous les événements pour l'affichage
$stmt = $conn->query("SELECT * FROM evenements ORDER BY date_evenement DESC");
$evenements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour ajouter un événement
function ajouterEvenement($conn) {
    try {
        $titre = trim($_POST['titre']);
        $date = $_POST['date_evenement'];
        $heure = $_POST['heure_evenement'];
        $lieu = trim($_POST['lieu']);
        $description = trim($_POST['description']);
        
        // Validation
        if (empty($titre) || empty($date) || empty($heure) || empty($lieu)) {
            return "Erreur : Tous les champs obligatoires doivent être remplis.";
        }
        
        // Upload de l'image
        $imageName = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imageName = uploadImage($_FILES['image']);
            if (strpos($imageName, 'Erreur') !== false) {
                return $imageName;
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO evenements (titre, date_evenement, heure_evenement, lieu, description, image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$titre, $date, $heure, $lieu, $description, $imageName]);
        
        return "Événement ajouté avec succès !";
    } catch (Exception $e) {
        return "Erreur lors de l'ajout : " . $e->getMessage();
    }
}

// Fonction pour modifier un événement
function modifierEvenement($conn) {
    try {
        $id = (int)$_POST['id'];
        $titre = trim($_POST['titre']);
        $date = $_POST['date_evenement'];
        $heure = $_POST['heure_evenement'];
        $lieu = trim($_POST['lieu']);
        $description = trim($_POST['description']);
        
        if (empty($titre) || empty($date) || empty($heure) || empty($lieu)) {
            return "Erreur : Tous les champs obligatoires doivent être remplis.";
        }
        
        // Récupérer l'ancienne image
        $stmt = $conn->prepare("SELECT image FROM evenements WHERE id = ?");
        $stmt->execute([$id]);
        $ancienneImage = $stmt->fetchColumn();
        
        $imageName = $ancienneImage;
        
        // Upload de la nouvelle image si fournie
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $nouvellImage = uploadImage($_FILES['image']);
            if (strpos($nouvellImage, 'Erreur') !== false) {
                return $nouvellImage;
            }
            
            // Supprimer l'ancienne image
            if ($ancienneImage && file_exists("uploads/evenements/$ancienneImage")) {
                unlink("uploads/evenements/$ancienneImage");
            }
            
            $imageName = $nouvellImage;
        }
        
        $stmt = $conn->prepare("UPDATE evenements SET titre = ?, date_evenement = ?, heure_evenement = ?, lieu = ?, description = ?, image = ? WHERE id = ?");
        $stmt->execute([$titre, $date, $heure, $lieu, $description, $imageName, $id]);
        
        return "Événement modifié avec succès !";
    } catch (Exception $e) {
        return "Erreur lors de la modification : " . $e->getMessage();
    }
}

// Fonction pour supprimer un événement
function supprimerEvenement($conn) {
    try {
        $id = (int)$_POST['id'];
        
        // Récupérer l'image à supprimer
        $stmt = $conn->prepare("SELECT image FROM evenements WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetchColumn();
        
        // Supprimer l'événement
        $stmt = $conn->prepare("DELETE FROM evenements WHERE id = ?");
        $stmt->execute([$id]);
        
        // Supprimer l'image du serveur
        if ($image && file_exists("uploads/evenements/$image")) {
            unlink("uploads/evenements/$image");
        }
        
        return "Événement supprimé avec succès !";
    } catch (Exception $e) {
        return "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Fonction pour l'upload d'images
function uploadImage($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return "Erreur : Type de fichier non autorisé. Utilisez JPEG, PNG, GIF ou WebP.";
    }
    
    if ($file['size'] > $maxSize) {
        return "Erreur : Le fichier est trop volumineux (max 5MB).";
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('event_', true) . '.' . $extension;
    $uploadPath = "uploads/evenements/$fileName";
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return $fileName;
    } else {
        return "Erreur : Impossible d'uploader le fichier.";
    }
}

// Récupérer un événement pour modification via AJAX
if (isset($_GET['get_event']) && isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM evenements WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($event);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gestion des Événements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .event-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .event-card:hover {
            transform: translateY(-5px);
        }
        .event-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
        }
        .btn-custom {
            border-radius: 25px;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>

<body class="bg-light">
    
    <div class="admin-header">
        <div class="container">
            <h1 class="mb-0"><i class="fas fa-calendar-alt me-3"></i>Administration - Gestion des Événements</h1>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show">
                <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <button class="btn btn-primary btn-custom" data-bs-toggle="modal" data-bs-target="#eventModal">
                    <i class="fas fa-plus me-2"></i>Ajouter un événement
                </button>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card event-card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Liste des événements
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($evenements)): ?>
                            <p class="text-muted text-center py-4">
                                <i class="fas fa-calendar-times fa-3x mb-3"></i><br>
                                Aucun événement trouvé
                            </p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Titre</th>
                                            <th>Date & Heure</th>
                                            <th>Lieu</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($evenements as $evenement): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($evenement['image']): ?>
                                                        <img src="uploads/evenements/<?= htmlspecialchars($evenement['image']) ?>" 
                                                             class="event-image" alt="Image événement">
                                                    <?php else: ?>
                                                        <div class="event-image bg-light d-flex align-items-center justify-content-center">
                                                            <i class="fas fa-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($evenement['titre']) ?></strong>
                                                    <br><small class="text-muted"><?= substr(htmlspecialchars($evenement['description']), 0, 50) ?>...</small>
                                                </td>
                                                <td>
                                                    <i class="fas fa-calendar me-1"></i><?= date('d/m/Y', strtotime($evenement['date_evenement'])) ?>
                                                    <br><i class="fas fa-clock me-1"></i><?= date('H:i', strtotime($evenement['heure_evenement'])) ?>
                                                </td>
                                                <td>
                                                    <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($evenement['lieu']) ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-outline-primary btn-sm btn-custom me-1" 
                                                            onclick="modifierEvenement(<?= $evenement['id'] ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger btn-sm btn-custom" 
                                                            onclick="supprimerEvenement(<?= $evenement['id'] ?>, '<?= addslashes($evenement['titre']) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
        </div>
    </div>

    <!-- Modal pour ajouter/modifier un événement -->
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="fas fa-plus-circle me-2"></i>Ajouter un événement
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="eventForm" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="ajouter">
                        <input type="hidden" name="id" id="eventId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Titre *</label>
                                <input type="text" class="form-control" name="titre" id="titre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lieu *</label>
                                <input type="text" class="form-control" name="lieu" id="lieu" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date *</label>
                                <input type="date" class="form-control" name="date_evenement" id="date_evenement" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Heure *</label>
                                <input type="time" class="form-control" name="heure_evenement" id="heure_evenement" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="4"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" class="form-control" name="image" id="image" accept="image/*">
                            <div class="form-text">Formats acceptés : JPEG, PNG, GIF, WebP. Taille max : 5MB</div>
                            <div id="currentImage" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-custom" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary btn-custom" id="submitBtn">
                            <i class="fas fa-save me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulaire caché pour suppression -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="supprimer">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function modifierEvenement(id) {
            fetch(`?get_event=1&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Modifier un événement';
                    document.getElementById('formAction').value = 'modifier';
                    document.getElementById('eventId').value = data.id;
                    document.getElementById('titre').value = data.titre;
                    document.getElementById('lieu').value = data.lieu;
                    document.getElementById('date_evenement').value = data.date_evenement;
                    document.getElementById('heure_evenement').value = data.heure_evenement;
                    document.getElementById('description').value = data.description || '';
                    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save me-2"></i>Modifier';
                    
                    // Afficher l'image actuelle
                    const currentImageDiv = document.getElementById('currentImage');
                    if (data.image) {
                        currentImageDiv.innerHTML = `
                            <small class="text-muted">Image actuelle :</small><br>
                            <img src="uploads/evenements/${data.image}" class="img-thumbnail" style="max-width: 200px;">
                        `;
                    } else {
                        currentImageDiv.innerHTML = '';
                    }
                    
                    new bootstrap.Modal(document.getElementById('eventModal')).show();
                })
                .catch(error => {
                    alert('Erreur lors du chargement de l\'événement');
                    console.error('Error:', error);
                });
        }

        function supprimerEvenement(id, titre) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'événement "${titre}" ?`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Réinitialiser le formulaire quand le modal se ferme
        document.getElementById('eventModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('eventForm').reset();
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Ajouter un événement';
            document.getElementById('formAction').value = 'ajouter';
            document.getElementById('eventId').value = '';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save me-2"></i>Enregistrer';
            document.getElementById('currentImage').innerHTML = '';
        });

        // Définir la date minimum à aujourd'hui
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('date_evenement').setAttribute('min', today);
    </script>
</body>
</html>