<?php
session_start();
require_once '../config.php';
require_once './permissions.php';
// Rediriger si l'admin n'est pas connecté
    if (! isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }
requireAccess($conn, $_SESSION['admin_id'], 'avis_admin');
// Traitement des actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id > 0) {
        try {
            if ($action === 'supprimer') {
                $stmt = $conn->prepare("DELETE FROM avis WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Avis supprimé avec succès";
            } 
            elseif ($action === 'valider') {
                $stmt = $conn->prepare("UPDATE avis SET valide = 1 WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Avis validé avec succès";
            }
            elseif ($action === 'invalider') {
                $stmt = $conn->prepare("UPDATE avis SET valide = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Avis invalidé avec succès";
            }
        } catch (Exception $e) {
            $error = "Erreur lors de l'opération: " . $e->getMessage();
        }
    }
}

// Récupération de tous les avis
try {
    $stmt = $conn->prepare("SELECT * FROM avis ORDER BY date_creation DESC");
    $stmt->execute();
    $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erreur lors de la récupération des avis: " . $e->getMessage();
    $avis = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration des Avis - Restaurant Mulho</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #ec4899, #f97316);
        }
        .table-responsive {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .badge-success {
            background-color: #198754;
        }
        .badge-warning {
            background-color: #ffc107;
        }
        .action-btn {
            margin: 0 3px;
        }
        .page-title {
            color: #2d3748;
            font-weight: 700;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Restaurant Mulho</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Retour au site</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Déconnexion</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="page-title">Administration des Avis Clients</h1>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Message</th>
                        <th>Note</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($avis) > 0): ?>
                        <?php foreach ($avis as $avi): ?>
                            <tr>
                                <td><?= $avi['id'] ?></td>
                                <td><?= htmlspecialchars($avi['nom']) ?></td>
                                <td><?= htmlspecialchars($avi['email']) ?></td>
                                <td><?= nl2br(htmlspecialchars(substr($avi['message'], 0, 100))) . (strlen($avi['message']) > 100 ? '...' : '') ?></td>
                                <td>
                                    <?= str_repeat('<i class="fas fa-star text-warning"></i>', $avi['note']) ?>
                                    <?= str_repeat('<i class="far fa-star text-warning"></i>', 5 - $avi['note']) ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($avi['date_creation'])) ?></td>
                                <td>
                                    <?php if ($avi['valide']): ?>
                                        <span class="badge badge-success">Validé</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">En attente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$avi['valide']): ?>
                                        <a href="?action=valider&id=<?= $avi['id'] ?>" class="btn btn-success btn-sm action-btn" title="Valider">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="?action=invalider&id=<?= $avi['id'] ?>" class="btn btn-warning btn-sm action-btn" title="Invalider">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?action=supprimer&id=<?= $avi['id'] ?>" class="btn btn-danger btn-sm action-btn" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet avis ?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Aucun avis pour le moment.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>