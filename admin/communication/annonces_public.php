<?php
require_once '../../config.php'; // ⚠️ ton config.php doit créer $conn = new PDO(...)

$message = "";

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                $titre = $_POST['titre'];
                $contenu = $_POST['contenu'];
                $type = $_POST['type_annonce'];
                $couleur = $_POST['couleur'];
                $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
                $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;

                $query = "INSERT INTO annonce_public (titre, contenu, type_annonce, couleur, date_debut, date_fin) 
                          VALUES (:titre, :contenu, :type, :couleur, :date_debut, :date_fin)";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([
                    ':titre' => $titre,
                    ':contenu' => $contenu,
                    ':type' => $type,
                    ':couleur' => $couleur,
                    ':date_debut' => $date_debut,
                    ':date_fin' => $date_fin
                ]);

                $message = $success 
                    ? "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                        <i class='fas fa-check-circle me-2'></i>Annonce ajoutée avec succès!
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                       </div>"
                    : "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        <i class='fas fa-exclamation-circle me-2'></i>Erreur lors de l'ajout
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                       </div>";
                break;

            case 'modifier':
                $id = intval($_POST['id']);
                $titre = $_POST['titre'];
                $contenu = $_POST['contenu'];
                $type = $_POST['type_annonce'];
                $couleur = $_POST['couleur'];
                $statut = $_POST['statut'];
                $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
                $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;

                $query = "UPDATE annonce_public 
                          SET titre = :titre, contenu = :contenu, type_annonce = :type, 
                              couleur = :couleur, statut = :statut, 
                              date_debut = :date_debut, date_fin = :date_fin
                          WHERE id = :id";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([
                    ':titre' => $titre,
                    ':contenu' => $contenu,
                    ':type' => $type,
                    ':couleur' => $couleur,
                    ':statut' => $statut,
                    ':date_debut' => $date_debut,
                    ':date_fin' => $date_fin,
                    ':id' => $id
                ]);

                $message = $success 
                    ? "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                        <i class='fas fa-check-circle me-2'></i>Annonce modifiée avec succès!
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                       </div>"
                    : "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        <i class='fas fa-exclamation-circle me-2'></i>Erreur lors de la modification
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                       </div>";
                break;

            case 'supprimer':
                $id = intval($_POST['id']);
                $query = "DELETE FROM annonce_public WHERE id = :id";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([':id' => $id]);

                $message = $success 
                    ? "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                        <i class='fas fa-check-circle me-2'></i>Annonce supprimée avec succès!
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                       </div>"
                    : "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                        <i class='fas fa-exclamation-circle me-2'></i>Erreur lors de la suppression
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                       </div>";
                break;
        }
    }
}

/**
 * Nettoie les annonces expirées et déjà inactives
 */
function nettoyerAnnoncesExpirees() {
    global $pdo;

    $date_aujourd_hui = date('Y-m-d');

    $sql = "DELETE FROM annonce_public 
            WHERE date_fin IS NOT NULL 
            AND date_fin < :date 
            AND statut = 'inactive'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':date' => $date_aujourd_hui]);

    return $stmt->rowCount();
}

/**
 * Désactive automatiquement les annonces expirées
 */
function desactiverAnnoncesExpirees() {
    global $conn;

    $date_aujourd_hui = date('Y-m-d');

    $sql = "UPDATE annonce_public 
            SET statut = 'inactive' 
            WHERE date_fin IS NOT NULL 
            AND date_fin < :date 
            AND statut = 'active'";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':date' => $date_aujourd_hui]);

    return $stmt->rowCount();
}

/**
 * Obtient les statistiques des annonces
 */
function getStatistiquesAnnonces() {
    global $conn;

    $stats = [];

    // Total des annonces
    $stmt = $conn->query("SELECT COUNT(*) as total FROM annonce_public");
    $stats['total'] = $stmt->fetchColumn();

    // Annonces actives
    $stmt = $conn->query("SELECT COUNT(*) as actives FROM annonce_public WHERE statut = 'active'");
    $stats['actives'] = $stmt->fetchColumn();

    // Annonces par type
    $stmt = $conn->query("SELECT type_annonce, COUNT(*) as count 
                         FROM annonce_public 
                         WHERE statut = 'active' 
                         GROUP BY type_annonce");
    $stats['par_type'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['par_type'][$row['type_annonce']] = $row['count'];
    }

    // Annonces expirées aujourd'hui
    $date_aujourd_hui = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as expirees 
                           FROM annonce_public 
                           WHERE date_fin = :date");
    $stmt->execute([':date' => $date_aujourd_hui]);
    $stats['expire_aujourdhui'] = $stmt->fetchColumn();

    return $stats;
}

// Récupération des annonces
$query = "SELECT * FROM annonce_public ORDER BY date_creation DESC";
$stmt = $conn->query($query);
$annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des statistiques
$stats = getStatistiquesAnnonces();

// Désactiver automatiquement les annonces expirées
desactiverAnnoncesExpirees();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Annonces Publiques</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
            --danger-gradient: linear-gradient(135deg, #fd79a8 0%, #fdcb6e 100%);
            --info-gradient: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
        }

        body {
            background: #ffffff;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .stats-card {
            background: white;
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.2s ease;
            overflow: hidden;
            position: relative;
            border-top: 4px solid;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }

        .stats-card.primary { border-top-color: #667eea; }
        .stats-card.success { border-top-color: #4ade80; }
        .stats-card.warning { border-top-color: #fbbf24; }
        .stats-card.danger { border-top-color: #f87171; }
        .stats-card.info { border-top-color: #60a5fa; }

        .stats-card .card-body {
            color: #374151;
            padding: 1.5rem;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #111827;
            margin: 0.5rem 0;
        }

        .stats-icon {
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stats-card.primary .stats-icon {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .stats-card.success .stats-icon {
            background: rgba(74, 222, 128, 0.1);
            color: #4ade80;
        }

        .stats-card.warning .stats-icon {
            background: rgba(251, 191, 36, 0.1);
            color: #fbbf24;
        }

        .stats-card.danger .stats-icon {
            background: rgba(248, 113, 113, 0.1);
            color: #f87171;
        }

        .stats-card.info .stats-icon {
            background: rgba(96, 165, 250, 0.1);
            color: #60a5fa;
        }

        .stats-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .stats-subtitle {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
        }

        .stats-trend {
            color: #10b981;
            font-weight: 600;
        }

        .modern-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }

        .modern-card:hover {
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .card-header.modern {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 20px 20px 0 0 !important;
            padding: 1.5rem;
        }

        .btn-modern {
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn-modern:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-modern:hover:before {
            left: 100%;
        }

        .btn-primary.btn-modern {
            background: var(--primary-gradient);
        }

        .btn-success.btn-modern {
            background: var(--success-gradient);
        }

        /* Nouveau style pour les tableaux */
        .elegant-table {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }

        .elegant-table thead th {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #334155;
            font-weight: 700;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 1.5rem 1rem;
            position: relative;
        }

        .elegant-table thead th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 1rem;
            right: 1rem;
            height: 2px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 1px;
        }

        .elegant-table tbody tr {
            border: none;
            transition: all 0.3s ease;
            background: #ffffff;
        }

        .elegant-table tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: scale(1.01);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
        }

        .elegant-table tbody tr:nth-child(even) {
            background: #fafbfc;
        }

        .elegant-table tbody tr:nth-child(even):hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .elegant-table tbody td {
            padding: 1.25rem 1rem;
            border: none;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .elegant-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Style pour les badges dans le tableau */
        .table-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .table-badge.badge-id {
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            color: #475569;
        }

        .table-badge.badge-menu {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .table-badge.badge-site {
            background: linear-gradient(135deg, #6b7280, #374151);
            color: white;
        }

        .table-badge.badge-active {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .table-badge.badge-inactive {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        /* Styles pour les détails de l'annonce */
        .annonce-title {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .annonce-content {
            font-size: 0.875rem;
            color: #64748b;
            background: #f8fafc;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border-left: 3px solid #cbd5e1;
        }

        .date-info {
            font-size: 0.8rem;
        }

        .date-label {
            font-weight: 600;
            color: #475569;
        }

        .date-value {
            color: #64748b;
        }

        /* Boutons d'action améliorés */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .action-btn.btn-edit {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .action-btn.btn-delete {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        /* Responsive pour les tableaux */
        @media (max-width: 768px) {
            .elegant-table thead th,
            .elegant-table tbody td {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
            }

            .stats-number {
                font-size: 2rem;
            }
            
            .stats-icon {
                width: 40px;
                height: 40px;
                font-size: 1.25rem;
                right: 1rem;
                top: 1rem;
            }

            .stats-title {
                font-size: 0.75rem;
            }

            .action-btn {
                width: 32px;
                height: 32px;
                font-size: 0.75rem;
            }
        }

        .badge-modern {
            border-radius: 50px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control-modern {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control-modern:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 20px 20px 0 0;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(102, 126, 234, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
            }
        }

        .floating-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0">
                        <i class="fas fa-bullhorn me-3"></i>
                        Gestion des Annonces Publiques
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Gérez vos annonces publiques en temps réel</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex justify-content-end align-items-center">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?= date('d/m/Y H:i') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($message)) echo $message; ?>

        <!-- Cartes de statistiques -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card primary h-100">
                    <div class="card-body position-relative">
                        <div class="stats-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <h6 class="stats-title">Total des annonces</h6>
                        <div class="stats-number"><?= $stats['total'] ?></div>
                        <div class="stats-subtitle">
                            <i class="fas fa-chart-line me-1"></i>
                            <span class="stats-trend">Toutes catégories</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card success h-100">
                    <div class="card-body position-relative">
                        <div class="stats-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h6 class="stats-title">Annonces actives</h6>
                        <div class="stats-number"><?= $stats['actives'] ?></div>
                        <div class="stats-subtitle">
                            <i class="fas fa-eye me-1"></i>
                            <span class="stats-trend">Visibles publiquement</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card info h-100">
                    <div class="card-body position-relative">
                        <div class="stats-icon">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h6 class="stats-title">Annonces menu</h6>
                        <div class="stats-number"><?= $stats['par_type']['menu'] ?? 0 ?></div>
                        <div class="stats-subtitle">
                            <i class="fas fa-restaurant me-1"></i>
                            <span class="stats-trend">Restaurant actif</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card warning h-100">
                    <div class="card-body position-relative">
                        <div class="stats-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h6 class="stats-title">Expirent aujourd'hui</h6>
                        <div class="stats-number"><?= $stats['expire_aujourdhui'] ?></div>
                        <div class="stats-subtitle">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <span class="stats-trend">À vérifier</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire d'ajout -->
        <div class="card modern-card mb-4">
            <div class="card-header modern">
                <h5 class="mb-0">
                    <i class="fas fa-plus me-2"></i>
                    Créer une Nouvelle Annonce
                </h5>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="ajouter">
                    <div class="row">
                        <div class="col-lg-6 col-md-12">
                            <div class="mb-3">
                                <label for="titre" class="form-label fw-bold">
                                    <i class="fas fa-heading me-1"></i>Titre de l'annonce *
                                </label>
                                <input type="text" class="form-control form-control-modern" id="titre" name="titre" required placeholder="Saisissez le titre...">
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="mb-3">
                                <label for="type_annonce" class="form-label fw-bold">
                                    <i class="fas fa-tag me-1"></i>Type d'annonce *
                                </label>
                                <select class="form-select form-control-modern" id="type_annonce" name="type_annonce" required>
                                    <option value="site">🌐 Site général</option>
                                    <option value="menu">🍽️ Menu restaurant</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="mb-3">
                                <label for="couleur" class="form-label fw-bold">
                                    <i class="fas fa-palette me-1"></i>Couleur
                                </label>
                                <input type="color" class="form-control form-control-modern form-control-color" id="couleur" name="couleur" value="#667eea">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="contenu" class="form-label fw-bold">
                            <i class="fas fa-align-left me-1"></i>Contenu de l'annonce *
                        </label>
                        <textarea class="form-control form-control-modern" id="contenu" name="contenu" rows="4" required placeholder="Rédigez votre annonce..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_debut" class="form-label fw-bold">
                                    <i class="fas fa-calendar-plus me-1"></i>Date de début
                                </label>
                                <input type="date" class="form-control form-control-modern" id="date_debut" name="date_debut">
                                <small class="text-muted">Laissez vide pour durée illimitée</small>
                            </div>
                        </div>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-modern pulse">
                            <i class="fas fa-rocket me-2"></i>Publier l'Annonce
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des annonces -->
        <div class="card modern-card">
            <div class="card-header modern d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Annonces Existantes (<?= count($annonces) ?>)
                </h5>
                <div class="d-flex gap-2">
                    <span class="badge badge-modern bg-light text-dark">
                        <i class="fas fa-eye me-1"></i><?= $stats['actives'] ?> actives
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($annonces)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Aucune annonce trouvée</h4>
                        <p class="text-muted">Créez votre première annonce en utilisant le formulaire ci-dessus</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table elegant-table mb-0">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag me-1"></i>ID</th>
                                    <th><i class="fas fa-info-circle me-1"></i>Détails</th>
                                    <th><i class="fas fa-tag me-1"></i>Type</th>
                                    <th><i class="fas fa-toggle-on me-1"></i>Statut</th>
                                    <th><i class="fas fa-calendar me-1"></i>Période</th>
                                    <th class="text-center"><i class="fas fa-cogs me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($annonces as $annonce): ?>
                                <tr>
                                    <td>
                                        <span class="table-badge badge-id">#<?= $annonce['id'] ?></span>
                                    </td>
                                    <td>
                                        <div class="annonce-title" style="color: <?= $annonce['couleur'] ?>;">
                                            <i class="fas fa-circle me-2" style="font-size: 0.6rem;"></i>
                                            <?= htmlspecialchars($annonce['titre']) ?>
                                        </div>
                                        <div class="annonce-content">
                                            <?= htmlspecialchars(substr($annonce['contenu'], 0, 80)) ?>
                                            <?= strlen($annonce['contenu']) > 80 ? '...' : '' ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="table-badge <?= $annonce['type_annonce'] == 'menu' ? 'badge-menu' : 'badge-site' ?>">
                                            <?= $annonce['type_annonce'] == 'menu' ? '🍽️ Menu' : '🌐 Site' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="table-badge <?= $annonce['statut'] == 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                            <i class="fas <?= $annonce['statut'] == 'active' ? 'fa-check' : 'fa-times' ?> me-1"></i>
                                            <?= ucfirst($annonce['statut']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="date-info">
                                            <div class="mb-2">
                                                <span class="date-label">
                                                    <i class="fas fa-play text-success me-1"></i>Début:
                                                </span>
                                                <span class="date-value"><?= $annonce['date_debut'] ?: 'Immédiat' ?></span>
                                            </div>
                                            <div>
                                                <span class="date-label">
                                                    <i class="fas fa-stop text-danger me-1"></i>Fin:
                                                </span>
                                                <span class="date-value"><?= $annonce['date_fin'] ?: 'Illimité' ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-buttons justify-content-center">
                                            <button class="action-btn btn-edit" 
                                                    onclick="modifierAnnonce(<?= htmlspecialchars(json_encode($annonce)) ?>)"
                                                    title="Modifier l'annonce">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn btn-delete" 
                                                    onclick="supprimerAnnonce(<?= $annonce['id'] ?>)"
                                                    title="Supprimer l'annonce">
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

    <!-- Bouton flottant pour remonter en haut -->
    <button class="btn btn-primary floating-btn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="Retour en haut">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Modal de modification -->
    <div class="modal fade" id="modalModifier" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Modifier l'Annonce
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formModifier">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="modifier">
                        <input type="hidden" name="id" id="mod_id">
                        
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="mb-3">
                                    <label for="mod_titre" class="form-label fw-bold">
                                        <i class="fas fa-heading me-1"></i>Titre *
                                    </label>
                                    <input type="text" class="form-control form-control-modern" id="mod_titre" name="titre" required>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="mb-3">
                                    <label for="mod_type_annonce" class="form-label fw-bold">
                                        <i class="fas fa-tag me-1"></i>Type *
                                    </label>
                                    <select class="form-select form-control-modern" id="mod_type_annonce" name="type_annonce" required>
                                        <option value="site">🌐 Site général</option>
                                        <option value="menu">🍽️ Menu restaurant</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="mb-3">
                                    <label for="mod_couleur" class="form-label fw-bold">
                                        <i class="fas fa-palette me-1"></i>Couleur
                                    </label>
                                    <input type="color" class="form-control form-control-modern form-control-color" id="mod_couleur" name="couleur">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mod_contenu" class="form-label fw-bold">
                                <i class="fas fa-align-left me-1"></i>Contenu *
                            </label>
                            <textarea class="form-control form-control-modern" id="mod_contenu" name="contenu" rows="4" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-lg-4">
                                <div class="mb-3">
                                    <label for="mod_statut" class="form-label fw-bold">
                                        <i class="fas fa-toggle-on me-1"></i>Statut
                                    </label>
                                    <select class="form-select form-control-modern" id="mod_statut" name="statut">
                                        <option value="active">✅ Active</option>
                                        <option value="inactive">❌ Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="mb-3">
                                    <label for="mod_date_debut" class="form-label fw-bold">
                                        <i class="fas fa-calendar-plus me-1"></i>Date de début
                                    </label>
                                    <input type="date" class="form-control form-control-modern" id="mod_date_debut" name="date_debut">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="mb-3">
                                    <label for="mod_date_fin" class="form-label fw-bold">
                                        <i class="fas fa-calendar-minus me-1"></i>Date de fin
                                    </label>
                                    <input type="date" class="form-control form-control-modern" id="mod_date_fin" name="date_fin">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-modern" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Annuler
                        </button>
                        <button type="submit" class="btn btn-success btn-modern">
                            <i class="fas fa-save me-2"></i>Enregistrer les Modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            // Animation des cartes de statistiques
            const statsCards = document.querySelectorAll('.stats-card');
            statsCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Effet de comptage animé pour les statistiques
            const numbers = document.querySelectorAll('.stats-number');
            numbers.forEach(number => {
                const target = parseInt(number.textContent);
                const increment = target / 30;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    number.textContent = Math.floor(current);
                }, 50);
            });
        });

        function modifierAnnonce(annonce) {
            // Remplir le formulaire modal avec les données
            document.getElementById('mod_id').value = annonce.id;
            document.getElementById('mod_titre').value = annonce.titre;
            document.getElementById('mod_contenu').value = annonce.contenu;
            document.getElementById('mod_type_annonce').value = annonce.type_annonce;
            document.getElementById('mod_couleur').value = annonce.couleur;
            document.getElementById('mod_statut').value = annonce.statut;
            document.getElementById('mod_date_debut').value = annonce.date_debut;
            document.getElementById('mod_date_fin').value = annonce.date_fin;
            
            // Afficher le modal avec animation
            const modal = new bootstrap.Modal(document.getElementById('modalModifier'));
            modal.show();
        }

        function supprimerAnnonce(id) {
            // SweetAlert-like confirmation avec Bootstrap
            const confirmation = confirm('⚠️ Confirmation de Suppression\n\nÊtes-vous sûr de vouloir supprimer définitivement cette annonce ?\n\nCette action est irréversible.');
            
            if (confirmation) {
                // Créer et soumettre le formulaire de suppression
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Effet de parallaxe sur le scroll
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const parallax = document.querySelector('.main-header');
            if (parallax) {
                const speed = scrolled * 0.5;
                parallax.style.transform = `translateY(${speed}px)`;
            }
        });

        // Auto-hide alerts après 5 secondes
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    if (bsAlert) {
                        bsAlert.close();
                    }
                }, 5000);
            });
        });

        // Validation en temps réel du formulaire
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
                inputs.forEach(input => {
                    input.addEventListener('input', function() {
                        if (this.value.trim() !== '') {
                            this.classList.remove('is-invalid');
                            this.classList.add('is-valid');
                        } else {
                            this.classList.remove('is-valid');
                            this.classList.add('is-invalid');
                        }
                    });
                });
            });
        });

        // Prévisualisation de couleur en temps réel
        document.addEventListener('DOMContentLoaded', function() {
            const colorInputs = document.querySelectorAll('input[type="color"]');
            colorInputs.forEach(input => {
                input.addEventListener('input', function() {
                    // Créer un aperçu de la couleur
                    const preview = document.createElement('div');
                    preview.style.cssText = `
                        position: absolute;
                        top: -30px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: ${this.value};
                        color: white;
                        padding: 5px 10px;
                        border-radius: 15px;
                        font-size: 12px;
                        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                        z-index: 1000;
                    `;
                    preview.textContent = this.value;
                    
                    // Supprimer l'ancien aperçu s'il existe
                    const oldPreview = this.parentElement.querySelector('.color-preview');
                    if (oldPreview) {
                        oldPreview.remove();
                    }
                    
                    preview.className = 'color-preview';
                    this.parentElement.style.position = 'relative';
                    this.parentElement.appendChild(preview);
                    
                    // Supprimer l'aperçu après 2 secondes
                    setTimeout(() => {
                        preview.remove();
                    }, 2000);
                });
            });
        });

        // Mise à jour automatique des dates minimales
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                if (input.name.includes('debut') || input.name.includes('fin')) {
                    input.min = today;
                }
            });
        });
    </script>
</body>
