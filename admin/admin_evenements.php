<?php
session_start();
require_once '../config.php';
require_once './permissions.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Vérifier que admin_id existe
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

requireAccess($conn, $_SESSION['admin_id'], 'admin_evenements');
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
    <title>Gestion des Événements - Restaurant Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .main-container {
            display: flex;
            min-height: 100vh;
        }

        .content-wrapper {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .stat-content h3 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-content p {
            color: #64748b;
            font-size: 0.875rem;
        }

        .content-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .events-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }

        .events-table thead th {
            color: #64748b;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            padding: 1rem;
            text-align: left;
            border: none;
        }

        .events-table tbody tr {
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .events-table tbody tr:hover {
            background: #f1f5f9;
            transform: scale(1.01);
        }

        .events-table tbody td {
            padding: 1.25rem 1rem;
            vertical-align: middle;
            border: none;
        }

        .events-table tbody tr td:first-child {
            border-radius: 12px 0 0 12px;
        }

        .events-table tbody tr td:last-child {
            border-radius: 0 12px 12px 0;
        }

        .event-image {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .event-image-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
        }

        .event-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .event-description {
            color: #64748b;
            font-size: 0.875rem;
        }

        .event-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #475569;
            margin-bottom: 0.25rem;
        }

        .event-location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #475569;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 700px;
            max-height: 85vh;
            overflow: visible;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-body {
            max-height: calc(85vh - 200px);
            overflow-y: auto;
        }

        /* Cacher la scrollbar mais garder la fonctionnalité */
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: transparent;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-close {
            background: transparent;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: background 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            color: #475569;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .form-text {
            color: #94a3b8;
            font-size: 0.75rem;
            margin-top: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .events-table {
                font-size: 0.875rem;
            }
        }
    </style>
</head>

<body>
    <div class="main-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-wrapper">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-calendar-alt mr-3"></i>Gestion des Événements</h1>
                <p class="page-subtitle">Créez et gérez les événements de votre restaurant</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count($evenements) ?></h3>
                        <p>Événements total</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count(array_filter($evenements, fn($e) => strtotime($e['date_evenement']) >= strtotime('today'))) ?></h3>
                        <p>Événements à venir</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= count(array_filter($evenements, fn($e) => strtotime($e['date_evenement']) < strtotime('today'))) ?></h3>
                        <p>Événements passés</p>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?>">
                    <i class="fas fa-<?= $messageType === 'error' ? 'exclamation-circle' : 'check-circle' ?> fa-lg"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <!-- Main Content Card -->
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-list mr-2"></i>Liste des événements</h2>
                    <button class="btn btn-primary" onclick="openModal()">
                        <i class="fas fa-plus"></i>
                        Nouvel événement
                    </button>
                </div>

                <?php if (empty($evenements)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3 style="color: #64748b; margin-bottom: 0.5rem;">Aucun événement</h3>
                        <p>Commencez par créer votre premier événement</p>
                    </div>
                <?php else: ?>
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Événement</th>
                                <th>Date & Heure</th>
                                <th>Lieu</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evenements as $evenement):
                                $isPast = strtotime($evenement['date_evenement']) < strtotime('today');
                            ?>
                                <tr>
                                    <td>
                                        <?php if ($evenement['image']): ?>
                                            <img src="uploads/evenements/<?= htmlspecialchars($evenement['image']) ?>"
                                                 class="event-image" alt="<?= htmlspecialchars($evenement['titre']) ?>">
                                        <?php else: ?>
                                            <div class="event-image-placeholder">
                                                <i class="fas fa-image fa-2x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="event-title"><?= htmlspecialchars($evenement['titre']) ?></div>
                                        <div class="event-description">
                                            <?= substr(htmlspecialchars($evenement['description'] ?? ''), 0, 60) ?>...
                                        </div>
                                    </td>
                                    <td>
                                        <div class="event-date">
                                            <i class="fas fa-calendar"></i>
                                            <?= date('d/m/Y', strtotime($evenement['date_evenement'])) ?>
                                        </div>
                                        <div class="event-date">
                                            <i class="fas fa-clock"></i>
                                            <?= date('H:i', strtotime($evenement['heure_evenement'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="event-location">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?= htmlspecialchars($evenement['lieu']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $isPast ? 'warning' : 'success' ?>">
                                            <?= $isPast ? 'Passé' : 'À venir' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button class="btn btn-success btn-sm"
                                                    onclick="modifierEvenement(<?= $evenement['id'] ?>)"
                                                    title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm"
                                                    onclick="supprimerEvenement(<?= $evenement['id'] ?>, '<?= addslashes($evenement['titre']) ?>')"
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal pour ajouter/modifier un événement -->
    <div class="modal" id="eventModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">
                    <i class="fas fa-plus-circle"></i> Nouvel événement
                </h3>
                <button type="button" class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="eventForm" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="ajouter">
                    <input type="hidden" name="id" id="eventId">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Titre de l'événement *</label>
                            <input type="text" class="form-control" name="titre" id="titre" required placeholder="Ex: Soirée Jazz">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Lieu *</label>
                            <input type="text" class="form-control" name="lieu" id="lieu" required placeholder="Ex: Restaurant La Belle Vie">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" name="date_evenement" id="date_evenement" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Heure *</label>
                            <input type="time" class="form-control" name="heure_evenement" id="heure_evenement" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" placeholder="Décrivez votre événement..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Image de l'événement</label>
                        <input type="file" class="form-control" name="image" id="image" accept="image/*">
                        <div class="form-text">Formats acceptés : JPEG, PNG, GIF, WebP • Taille max : 5MB</div>
                        <div id="currentImage" style="margin-top: 1rem;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 380px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); overflow: visible;">
            <div style="padding: 1.5rem 1.5rem; text-align: center;">
                <!-- Icône d'avertissement -->
                <div style="width: 70px; height: 70px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #dc2626;"></i>
                </div>

                <!-- Titre -->
                <h2 style="color: #1e293b; margin-bottom: 0.75rem; font-size: 1.35rem; font-weight: 700;">Confirmer la suppression</h2>

                <!-- Message -->
                <p style="color: #64748b; margin-bottom: 0.875rem; font-size: 0.875rem; line-height: 1.5;">Vous êtes sur le point de supprimer définitivement l'événement :</p>

                <!-- Info événement -->
                <div style="background: #f8fafc; border-radius: 8px; padding: 0.875rem; margin-bottom: 0.875rem; border: 1px solid #e2e8f0;">
                    <p style="font-weight: 600; color: #1e293b; font-size: 0.95rem; margin: 0;" id="deleteEventInfo"></p>
                </div>

                <!-- Avertissement -->
                <p style="color: #dc2626; font-weight: 600; font-size: 0.875rem; margin-bottom: 1.25rem;">Cette action est irréversible !</p>

                <!-- Boutons -->
                <div style="display: flex; gap: 0.625rem;">
                    <button type="button" onclick="closeDeleteModal()"
                            style="flex: 1; padding: 0.75rem 1rem; background: #e5e7eb; color: #374151; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                        Annuler
                    </button>
                    <button type="button" id="confirmDeleteBtn" onclick="confirmDeleteEvent()"
                            style="flex: 1; padding: 0.75rem 1rem; background: #dc2626; color: white; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                        Supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        #deleteModal button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        #deleteModal button:active {
            transform: translateY(0);
        }

        #deleteModal button:first-of-type:hover {
            background: #d1d5db;
        }

        #deleteModal button:last-of-type:hover {
            background: #b91c1c;
        }
    </style>

    <!-- Formulaire caché pour suppression -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="supprimer">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script>
        let eventToDelete = null;
        let eventTitleToDelete = '';

        // Ouvrir le modal
        function openModal() {
            document.getElementById('eventModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Fermer le modal
        function closeModal() {
            document.getElementById('eventModal').classList.remove('active');
            document.body.style.overflow = 'auto';
            resetForm();
        }

        // Réinitialiser le formulaire
        function resetForm() {
            document.getElementById('eventForm').reset();
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Nouvel événement';
            document.getElementById('formAction').value = 'ajouter';
            document.getElementById('eventId').value = '';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Enregistrer';
            document.getElementById('currentImage').innerHTML = '';
        }

        // Modifier un événement
        function modifierEvenement(id) {
            fetch(`?get_event=1&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Modifier l\'événement';
                    document.getElementById('formAction').value = 'modifier';
                    document.getElementById('eventId').value = data.id;
                    document.getElementById('titre').value = data.titre;
                    document.getElementById('lieu').value = data.lieu;
                    document.getElementById('date_evenement').value = data.date_evenement;
                    document.getElementById('heure_evenement').value = data.heure_evenement;
                    document.getElementById('description').value = data.description || '';
                    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Modifier';

                    // Afficher l'image actuelle
                    const currentImageDiv = document.getElementById('currentImage');
                    if (data.image) {
                        currentImageDiv.innerHTML = `
                            <div style="background: #f1f5f9; padding: 1rem; border-radius: 10px;">
                                <small style="color: #64748b; font-weight: 600;">Image actuelle :</small><br>
                                <img src="uploads/evenements/${data.image}" style="max-width: 100%; height: auto; border-radius: 8px; margin-top: 0.5rem;">
                            </div>
                        `;
                    } else {
                        currentImageDiv.innerHTML = '';
                    }

                    openModal();
                })
                .catch(error => {
                    alert('Erreur lors du chargement de l\'événement');
                    console.error('Error:', error);
                });
        }

        // Ouvrir le modal de confirmation de suppression
        function supprimerEvenement(id, titre) {
            eventToDelete = id;
            eventTitleToDelete = titre;
            document.getElementById('deleteEventInfo').textContent = titre;
            document.getElementById('deleteModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Fermer le modal de suppression
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            document.body.style.overflow = 'auto';
            eventToDelete = null;
            eventTitleToDelete = '';
            document.getElementById('deleteEventInfo').textContent = '';

            // Réinitialiser le bouton
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.innerHTML = '<i class="fas fa-trash"></i> Supprimer définitivement';
            confirmBtn.disabled = false;
        }

        // Confirmer la suppression
        function confirmDeleteEvent() {
            if (!eventToDelete) return;

            const confirmBtn = document.getElementById('confirmDeleteBtn');

            // Animation de chargement
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Suppression...';
            confirmBtn.disabled = true;

            // Soumettre le formulaire
            document.getElementById('deleteId').value = eventToDelete;
            document.getElementById('deleteForm').submit();
        }

        // Fermer les modals en cliquant à l'extérieur
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Définir la date minimum à aujourd'hui
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date_evenement').setAttribute('min', today);
        });
    </script>

    <!-- Footer -->
    <?php include 'footer.php'; ?>
</body>
</html>