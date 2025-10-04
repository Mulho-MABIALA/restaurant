<?php
session_start();
require_once '../../config.php';
require_once '../permissions.php';
// Activer les erreurs pour debug (à retirer en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérification de l'authentification
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
requireAccess($conn, $_SESSION['admin_id'], 'annonces');
// Configuration du dossier d'upload
$uploadDir = '../../uploads/annonces/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Configuration des chemins pour l'affichage
$webUploadPath = '../uploads/annonces/'; // Chemin relatif pour l'affichage web

// Traitement des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['ajax_action'] ?? '';
    $response = ['success' => false, 'message' => '', 'debug' => []];
    
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
            case 'supprimer_categorie':
                $response = supprimerCategorieAjax($conn);
                break;
            case 'get_annonce':
                $response = getAnnonceAjax($conn);
                break;
            case 'get_annonces':
                $response = getAnnoncesAjax($conn);
                break;
            case 'get_categories':
                $response = getCategoriesAjax($conn);
                break;
            default:
                $response['message'] = 'Action non reconnue : ' . $action;
        }
    } catch (Exception $e) {
        $response = [
            'success' => false, 
            'message' => 'Erreur : ' . $e->getMessage(),
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        ];
    }
    
    echo json_encode($response);
    exit();
}

// Récupération des données pour l'affichage initial
$annonces = getAnnonces($conn);
$categories = getCategories($conn);
$stats = getStatistiques($conn);

// Fonction pour gérer l'upload d'image
function handleImageUpload($fileInputName) {
    global $uploadDir;
    
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $file = $_FILES[$fileInputName];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Vérifier le type de fichier
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WebP.');
    }
    
    // Vérifier la taille
    if ($file['size'] > $maxSize) {
        throw new Exception('Fichier trop volumineux. Taille maximum: 5MB.');
    }
    
    // Générer un nom unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('annonce_') . '.' . $extension;
    $filePath = $uploadDir . $fileName;
    
    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return 'uploads/annonces/' . $fileName;
    } else {
        throw new Exception('Erreur lors de l\'upload du fichier.');
    }
}

// Fonction ajouterAnnonceAjax avec gestion d'image
function ajouterAnnonceAjax($conn) {
    try {
        // Validation des données requises
        $requiredFields = ['titre', 'contenu', 'priorite', 'statut'];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                return [
                    'success' => false, 
                    'message' => "Le champ '$field' est obligatoire",
                    'debug' => ['missing_field' => $field, 'post_data' => $_POST]
                ];
            }
        }
        
        // Gestion de l'upload d'image
        $imagePath = null;
        try {
            $imagePath = handleImageUpload('image');
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur image: ' . $e->getMessage()
            ];
        }
        
        // Vérifier la connexion à la base de données
        if (!$conn) {
            return [
                'success' => false, 
                'message' => 'Erreur de connexion à la base de données'
            ];
        }
        
        $stmt = $conn->prepare("
            INSERT INTO annonces (titre, contenu, categorie_id, priorite, statut, date_debut, date_fin, image, admin_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $categorie_id = !empty($_POST['categorie_id']) ? $_POST['categorie_id'] : null;
        $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
        $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
        
        $result = $stmt->execute([
            trim($_POST['titre']),
            trim($_POST['contenu']),
            $categorie_id,
            $_POST['priorite'],
            $_POST['statut'],
            $date_debut,
            $date_fin,
            $imagePath,
            $_SESSION['admin_id']
        ]);
        
        if ($result) {
            return [
                'success' => true, 
                'message' => 'Annonce ajoutée avec succès !',
                'debug' => ['insert_id' => $conn->lastInsertId()]
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Erreur lors de l\'insertion en base de données',
                'debug' => ['error_info' => $stmt->errorInfo()]
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false, 
            'message' => 'Erreur lors de l\'ajout : ' . $e->getMessage(),
            'debug' => [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ];
    }
}

// Fonction modifierAnnonceAjax avec gestion d'image
function modifierAnnonceAjax($conn) {
    try {
        // Validation des données requises
        $requiredFields = ['annonce_id', 'titre', 'contenu', 'priorite', 'statut'];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                return [
                    'success' => false, 
                    'message' => "Le champ '$field' est obligatoire"
                ];
            }
        }
        
        // Récupérer l'image actuelle
        $stmt = $conn->prepare("SELECT image FROM annonces WHERE id = ?");
        $stmt->execute([$_POST['annonce_id']]);
        $currentImage = $stmt->fetchColumn();
        
        // Gestion de l'upload d'image (optionnel en modification)
        $imagePath = $currentImage; // Conserver l'image actuelle par défaut
        
        try {
            $newImagePath = handleImageUpload('image');
            if ($newImagePath) {
                // Supprimer l'ancienne image si elle existe
                if ($currentImage && file_exists('../../' . $currentImage)) {
                    unlink('../../' . $currentImage);
                }
                $imagePath = $newImagePath;
            }
        } catch (Exception $e) {
            // Ne pas arrêter la modification si l'upload échoue
            // L'image actuelle sera conservée
        }
        
        $stmt = $conn->prepare("
            UPDATE annonces 
            SET titre = ?, contenu = ?, categorie_id = ?, priorite = ?, statut = ?, 
                date_debut = ?, date_fin = ?, image = ?
            WHERE id = ?
        ");
        
        $categorie_id = !empty($_POST['categorie_id']) ? $_POST['categorie_id'] : null;
        $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
        $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
        
        $result = $stmt->execute([
            trim($_POST['titre']),
            trim($_POST['contenu']),
            $categorie_id,
            $_POST['priorite'],
            $_POST['statut'],
            $date_debut,
            $date_fin,
            $imagePath,
            $_POST['annonce_id']
        ]);
        
        if ($result && $stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Annonce modifiée avec succès !'];
        } else {
            return ['success' => false, 'message' => 'Aucune modification effectuée ou annonce introuvable'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur lors de la modification : ' . $e->getMessage()];
    }
}

function supprimerAnnonceAjax($conn) {
    try {
        if (!isset($_POST['annonce_id']) || empty($_POST['annonce_id'])) {
            return ['success' => false, 'message' => 'ID de l\'annonce manquant'];
        }
        
        // Récupérer l'image pour la supprimer
        $stmt = $conn->prepare("SELECT image FROM annonces WHERE id = ?");
        $stmt->execute([$_POST['annonce_id']]);
        $image = $stmt->fetchColumn();
        
        // Supprimer l'annonce
        $stmt = $conn->prepare("DELETE FROM annonces WHERE id = ?");
        $result = $stmt->execute([$_POST['annonce_id']]);
        
        if ($result && $stmt->rowCount() > 0) {
            // Supprimer l'image du serveur si elle existe
            if ($image && file_exists('../../' . $image)) {
                unlink('../../' . $image);
            }
            return ['success' => true, 'message' => 'Annonce supprimée avec succès !'];
        } else {
            return ['success' => false, 'message' => 'Annonce introuvable ou déjà supprimée'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()];
    }
}

function changerStatutAnnonceAjax($conn) {
    try {
        if (!isset($_POST['annonce_id']) || !isset($_POST['nouveau_statut'])) {
            return ['success' => false, 'message' => 'Données manquantes pour changer le statut'];
        }
        
        $stmt = $conn->prepare("UPDATE annonces SET statut = ? WHERE id = ?");
        $result = $stmt->execute([$_POST['nouveau_statut'], $_POST['annonce_id']]);
        
        if ($result && $stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Statut modifié avec succès !'];
        } else {
            return ['success' => false, 'message' => 'Aucune modification effectuée'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur lors du changement de statut : ' . $e->getMessage()];
    }
}

function ajouterCategorieAjax($conn) {
    try {
        if (!isset($_POST['nom_categorie']) || empty(trim($_POST['nom_categorie']))) {
            return ['success' => false, 'message' => 'Le nom de la catégorie est obligatoire'];
        }
        
        $stmt = $conn->prepare("
            INSERT INTO categories_annonces (nom, description, couleur)
            VALUES (?, ?, ?)
        ");
        
        $description = !empty($_POST['description_categorie']) ? $_POST['description_categorie'] : null;
        $couleur = !empty($_POST['couleur_categorie']) ? $_POST['couleur_categorie'] : '#007bff';
        
        $result = $stmt->execute([
            trim($_POST['nom_categorie']),
            $description,
            $couleur
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Catégorie ajoutée avec succès !'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de l\'ajout de la catégorie'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur lors de l\'ajout de la catégorie : ' . $e->getMessage()];
    }
}

function supprimerCategorieAjax($conn) {
    try {
        if (!isset($_POST['categorie_id']) || empty($_POST['categorie_id'])) {
            return ['success' => false, 'message' => 'ID de la catégorie manquant'];
        }
        
        // Vérifier s'il y a des annonces liées à cette catégorie
        $stmt = $conn->prepare("SELECT COUNT(*) FROM annonces WHERE categorie_id = ?");
        $stmt->execute([$_POST['categorie_id']]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            return [
                'success' => false, 
                'message' => "Impossible de supprimer cette catégorie car $count annonce(s) y sont liées. Veuillez d'abord modifier ou supprimer ces annonces."
            ];
        }
        
        $stmt = $conn->prepare("DELETE FROM categories_annonces WHERE id = ?");
        $result = $stmt->execute([$_POST['categorie_id']]);
        
        if ($result && $stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Catégorie supprimée avec succès !'];
        } else {
            return ['success' => false, 'message' => 'Catégorie introuvable ou déjà supprimée'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()];
    }
}

function getAnnonceAjax($conn) {
    try {
        if (!isset($_POST['annonce_id']) || empty($_POST['annonce_id'])) {
            return ['success' => false, 'message' => 'ID de l\'annonce manquant'];
        }
        
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

function getCategoriesAjax($conn) {
    try {
        $categories = getCategories($conn);
        return ['success' => true, 'categories' => $categories];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
    }
}

// Fonctions utilitaires (améliorées avec toutes les colonnes)
function getAnnonces($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT a.*, c.nom as categorie_nom, c.couleur as categorie_couleur,
                   ad.nom_utilisateur as admin_nom
            FROM annonces a
            LEFT JOIN categories_annonces c ON a.categorie_id = c.id
            LEFT JOIN admins ad ON a.admin_id = ad.id
            ORDER BY a.date_creation DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur getAnnonces: " . $e->getMessage());
        return [];
    }
}

function getCategories($conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM categories_annonces WHERE actif = 1 ORDER BY nom");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur getCategories: " . $e->getMessage());
        return [];
    }
}

function getStatistiques($conn) {
    $stats = [];
    
    try {
        // Total des annonces
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM annonces");
        $stmt->execute();
        $stats['total'] = $stmt->fetchColumn();
        
        // Annonces publiées
        $stmt = $conn->prepare("SELECT COUNT(*) as publie FROM annonces WHERE statut = 'publie'");
        $stmt->execute();
        $stats['publie'] = $stmt->fetchColumn();
        
        // Annonces en brouillon
        $stmt = $conn->prepare("SELECT COUNT(*) as brouillon FROM annonces WHERE statut = 'brouillon'");
        $stmt->execute();
        $stats['brouillon'] = $stmt->fetchColumn();
        
        // Annonces urgentes
        $stmt = $conn->prepare("SELECT COUNT(*) as urgente FROM annonces WHERE priorite = 'urgente'");
        $stmt->execute();
        $stats['urgente'] = $stmt->fetchColumn();
        
    } catch (Exception $e) {
        error_log("Erreur getStatistiques: " . $e->getMessage());
        $stats = ['total' => 0, 'publie' => 0, 'brouillon' => 0, 'urgente' => 0];
    }
    
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
        /* Nouveau design moderne pour les cartes */
        .modern-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: none;
            height: 100%;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .modern-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }
        
        .modern-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 20px 20px 0 0;
        }
        
        .modern-card.card-purple::before {
            background: linear-gradient(90deg, #8B5CF6, #A855F7);
        }
        
        .modern-card.card-blue::before {
            background: linear-gradient(90deg, #3B82F6, #6366F1);
        }
        
        .modern-card.card-green::before {
            background: linear-gradient(90deg, #10B981, #059669);
        }
        
        .modern-card.card-orange::before {
            background: linear-gradient(90deg, #F59E0B, #D97706);
        }
        
        .card-header-modern {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-title-modern {
            color: #6B7280;
            font-size: 14px;
            font-weight: 500;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .card-icon-modern {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .icon-purple {
            background: linear-gradient(135deg, #8B5CF6, #A855F7);
        }
        
        .icon-blue {
            background: linear-gradient(135deg, #3B82F6, #6366F1);
        }
        
        .icon-green {
            background: linear-gradient(135deg, #10B981, #059669);
        }
        
        .icon-orange {
            background: linear-gradient(135deg, #F59E0B, #D97706);
        }
        
        .card-value-modern {
            font-size: 48px;
            font-weight: 700;
            color: #111827;
            line-height: 1;
            margin-bottom: 8px;
        }
        
        .card-trend-modern {
            display: flex;
            align-items: center;
            color: #10B981;
            font-size: 14px;
            font-weight: 500;
        }
        
        .card-trend-modern i {
            margin-right: 4px;
            font-size: 12px;
        }
        
        .card-subtitle-modern {
            color: #6B7280;
            font-size: 14px;
            margin-top: 4px;
        }

        /* Styles pour la gestion des catégories */
        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            margin-bottom: 5px;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
        
        .category-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
        }

        /* Styles pour l'aperçu d'image */
        .image-preview {
            max-width: 150px;
            max-height: 100px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .image-preview-container {
            position: relative;
            display: inline-block;
        }
        
        .remove-image {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            cursor: pointer;
        }

        /* Styles pour les miniatures dans le tableau */
        .table-image {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            object-fit: cover;
            cursor: pointer;
        }

        /* Conserver le reste du CSS existant */
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
        </div>
    </div>
</nav>

<div class="container">
    <!-- Zone des alertes -->
    <div id="alertZone"></div>

    <!-- Statistiques avec nouveau design moderne et valeurs dynamiques -->
    <div class="row mb-4 g-4" id="statsContainer">
        <div class="col-lg-3 col-md-6">
            <div class="card modern-card card-purple">
                <div class="card-header-modern">
                    <div>
                        <h6 class="card-title-modern">Total des annonces</h6>
                        <div class="card-value-modern" id="statTotal"><?= $stats['total'] ?></div>
                        <div class="card-trend-modern">
                            <i class="fas fa-arrow-up"></i>
                            Toutes catégories
                        </div>
                    </div>
                    <div class="card-icon-modern icon-purple">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card modern-card card-green">
                <div class="card-header-modern">
                    <div>
                        <h6 class="card-title-modern">Publiées</h6>
                        <div class="card-value-modern" id="statPublie"><?= $stats['publie'] ?></div>
                        <div class="card-subtitle-modern">Actives</div>
                    </div>
                    <div class="card-icon-modern icon-green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card modern-card card-blue">
                <div class="card-header-modern">
                    <div>
                        <h6 class="card-title-modern">Brouillons</h6>
                        <div class="card-value-modern" id="statBrouillon"><?= $stats['brouillon'] ?></div>
                        <div class="card-subtitle-modern">En attente</div>
                    </div>
                    <div class="card-icon-modern icon-blue">
                        <i class="fas fa-edit"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card modern-card card-orange">
                <div class="card-header-modern">
                    <div>
                        <h6 class="card-title-modern">Urgentes</h6>
                        <div class="card-value-modern" id="statUrgente"><?= $stats['urgente'] ?></div>
                        <div class="card-subtitle-modern">Prioritaires</div>
                    </div>
                    <div class="card-icon-modern icon-orange">
                        <i class="fas fa-exclamation-triangle"></i>
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
            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalGererCategories">
                <i class="fas fa-cogs me-1"></i>Gérer les Catégories
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
                            <th>Image</th>
                            <th>Titre</th>
                            <th>Catégorie</th>
                            <th>Priorité</th>
                            <th>Statut</th>
                            <th>Date début</th>
                            <th>Date fin</th>
                            <th>Vues</th>
                            <th>Créé le</th>
                            <th>Modifié le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="annoncesTableBody">
                        <?php foreach ($annonces as $annonce): ?>
                        <tr data-id="<?= $annonce['id'] ?>">
                            <td><?= $annonce['id'] ?></td>
                            <td>
                                <?php if ($annonce['image']): ?>
                                    <img src="<?= htmlspecialchars($annonce['image']) ?>" 
                                         alt="Image annonce" 
                                         class="table-image"
                                         onclick="showImageModal('<?= htmlspecialchars($annonce['image']) ?>', '<?= htmlspecialchars($annonce['titre']) ?>')">
                                <?php else: ?>
                                    <div class="text-muted">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
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
                            <td>
                                <?= $annonce['date_debut'] ? date('d/m/Y', strtotime($annonce['date_debut'])) : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td>
                                <?= $annonce['date_fin'] ? date('d/m/Y', strtotime($annonce['date_fin'])) : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td>
                                <span class="badge bg-info"><?= $annonce['nombre_vues'] ?></span>
                            </td>
                            <td>
                                <small><?= date('d/m/Y H:i', strtotime($annonce['date_creation'])) ?></small>
                            </td>
                            <td>
                                <small><?= date('d/m/Y H:i', strtotime($annonce['date_modification'])) ?></small>
                            </td>
                            <td class="table-actions">
                                <button class="btn btn-sm btn-outline-primary" onclick="voirAnnonce(<?= $annonce['id'] ?>)" title="Voir">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="modifierAnnonce(<?= $annonce['id'] ?>)" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="archiverAnnonce(<?= $annonce['id'] ?>)" title="Archiver">
                                    <i class="fas fa-archive"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="supprimerAnnonce(<?= $annonce['id'] ?>)" title="Supprimer">
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

<!-- Modal Ajouter/Modifier Annonce avec gestion d'image -->
<div class="modal fade" id="modalAjouterAnnonce" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formAjouterAnnonce" enctype="multipart/form-data">
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

                    <!-- Section Image -->
                    <div class="mb-3">
                        <label class="form-label">Image</label>
                        <input type="file" class="form-control" name="image" id="annonceImage" accept="image/*" onchange="previewImage(this)">
                        <small class="form-text text-muted">
                            Formats acceptés: JPG, PNG, GIF, WebP. Taille max: 5MB.
                        </small>
                        <div id="imagePreview" class="mt-2"></div>
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

<!-- Modal Gérer les Catégories -->
<div class="modal fade" id="modalGererCategories" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gérer les Catégories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="listeCategoriesGestion">
                    <!-- Les catégories seront chargées ici -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" onclick="chargerCategories()">
                    <i class="fas fa-sync-alt"></i> Actualiser
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour afficher l'image en grand -->
<div class="modal fade" id="modalImage" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalImageTitle">Image de l'annonce</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImageContent" src="" alt="Image" class="img-fluid rounded">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Variables globales
let isEditing = false;
let currentAnnonceId = null;

// Fonction pour prévisualiser l'image
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = `
                <div class="image-preview-container">
                    <img src="${e.target.result}" alt="Aperçu" class="image-preview">
                    <button type="button" class="remove-image" onclick="removeImagePreview()" title="Supprimer l'image">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Fonction pour supprimer l'aperçu d'image
function removeImagePreview() {
    document.getElementById('imagePreview').innerHTML = '';
    document.getElementById('annonceImage').value = '';
}

// Fonction pour afficher l'image en modal
function showImageModal(imageSrc, titre) {
    document.getElementById('modalImageContent').src = imageSrc;
    document.getElementById('modalImageTitle').textContent = `Image: ${titre}`;
    const modal = new bootstrap.Modal(document.getElementById('modalImage'));
    modal.show();
}

// Fonction pour afficher les alertes
function showAlert(message, type = 'success') {
    const alertZone = document.getElementById('alertZone');
    if (!alertZone) {
        console.error('Zone d\'alerte non trouvée');
        return;
    }
    
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

// Fonction pour effectuer une requête AJAX avec FormData
async function ajaxRequest(action, formData = null) {
    const form = formData || new FormData();
    form.append('ajax_action', action);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: form
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

// Fonction pour mettre à jour les statistiques avec animation
function updateStats(stats) {
    if (stats) {
        animateCounter('statTotal', stats.total || 0);
        animateCounter('statPublie', stats.publie || 0);
        animateCounter('statBrouillon', stats.brouillon || 0);
        animateCounter('statUrgente', stats.urgente || 0);
    }
}

// Animation des compteurs
function animateCounter(elementId, targetValue) {
    const element = document.getElementById(elementId);
    
    if (!element) {
        console.error(`Élément avec l'ID '${elementId}' non trouvé`);
        return;
    }
    
    const currentValue = parseInt(element.textContent) || 0;
    
    if (currentValue === targetValue) return;
    
    const increment = targetValue > currentValue ? 1 : -1;
    const duration = 1000;
    const steps = Math.abs(targetValue - currentValue);
    const stepDuration = steps > 0 ? duration / steps : 0;
    
    let current = currentValue;
    const timer = setInterval(() => {
        current += increment;
        element.textContent = current;
        
        if (current === targetValue) {
            clearInterval(timer);
        }
    }, stepDuration);
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
        ? `<span class="badge" style="background-color: ${annonce.categorie_couleur || '#007bff'}">${annonce.categorie_nom}</span>`
        : '<span class="text-muted">Aucune</span>';
    
    const adminHTML = annonce.admin_nom 
        ? `<small class="text-muted d-block">par ${annonce.admin_nom}</small>`
        : '';

    const imageHTML = annonce.image 
        ? `<img src="${annonce.image}" alt="Image annonce" class="table-image" onclick="showImageModal('${annonce.image}', '${annonce.titre}')">`
        : '<div class="text-muted"><i class="fas fa-image"></i></div>';
    
    return `
        <tr data-id="${annonce.id}">
            <td>${annonce.id}</td>
            <td>${imageHTML}</td>
            <td>
                <strong>${annonce.titre}</strong>
                ${adminHTML}
            </td>
            <td>${categorieHTML}</td>
            <td>
                <span class="badge bg-${prioriteColors[annonce.priorite] || 'primary'} priorite-badge">
                    ${annonce.priorite ? annonce.priorite.charAt(0).toUpperCase() + annonce.priorite.slice(1) : 'Normale'}
                </span>
            </td>
            <td>
                <span class="badge bg-${statutColors[annonce.statut] || 'primary'}">
                    ${annonce.statut ? annonce.statut.charAt(0).toUpperCase() + annonce.statut.slice(1) : 'Brouillon'}
                </span>
            </td>
            <td>${annonce.date_debut ? new Date(annonce.date_debut).toLocaleDateString('fr-FR') : '<span class="text-muted">-</span>'}</td>
            <td>${annonce.date_fin ? new Date(annonce.date_fin).toLocaleDateString('fr-FR') : '<span class="text-muted">-</span>'}</td>
            <td>
                <span class="badge bg-info">${annonce.nombre_vues || 0}</span>
            </td>
            <td>
                <small>${new Date(annonce.date_creation).toLocaleDateString('fr-FR', {
                    year: 'numeric', month: '2-digit', day: '2-digit',
                    hour: '2-digit', minute: '2-digit'
                })}</small>
            </td>
            <td>
                <small>${new Date(annonce.date_modification).toLocaleDateString('fr-FR', {
                    year: 'numeric', month: '2-digit', day: '2-digit',
                    hour: '2-digit', minute: '2-digit'
                })}</small>
            </td>
            <td class="table-actions">
                <button class="btn btn-sm btn-outline-primary" onclick="voirAnnonce(${annonce.id})" title="Voir">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline-warning" onclick="modifierAnnonce(${annonce.id})" title="Modifier">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="archiverAnnonce(${annonce.id})" title="Archiver">
                    <i class="fas fa-archive"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="supprimerAnnonce(${annonce.id})" title="Supprimer">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
}

// Fonction pour recharger la liste des annonces
async function reloadAnnonces() {
    const result = await ajaxRequest('get_annonces');
    
    if (result.success) {
        const tbody = document.getElementById('annoncesTableBody');
        if (tbody && result.annonces) {
            tbody.innerHTML = '';
            
            result.annonces.forEach(annonce => {
                tbody.insertAdjacentHTML('beforeend', generateAnnonceRow(annonce));
            });
            
            updateStats(result.stats);
        } else {
            console.error('Élément tbody non trouvé ou données manquantes');
        }
    } else {
        showAlert(result.message || 'Erreur lors du rechargement', 'danger');
    }
}

// Fonction pour charger les catégories dans le modal de gestion
async function chargerCategories() {
    const result = await ajaxRequest('get_categories');
    
    if (result.success) {
        const container = document.getElementById('listeCategoriesGestion');
        if (!container) {
            console.error('Container des catégories non trouvé');
            return;
        }
        
        container.innerHTML = '';
        
        if (!result.categories || result.categories.length === 0) {
            container.innerHTML = '<p class="text-muted text-center">Aucune catégorie trouvée.</p>';
            return;
        }
        
        result.categories.forEach(categorie => {
            const categorieHTML = `
                <div class="category-item" data-id="${categorie.id}">
                    <div class="d-flex align-items-center">
                        <div class="category-color" style="background-color: ${categorie.couleur || '#007bff'}"></div>
                        <div>
                            <strong>${categorie.nom}</strong>
                            ${categorie.description ? `<br><small class="text-muted">${categorie.description}</small>` : ''}
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-danger" onclick="supprimerCategorie(${categorie.id}, '${categorie.nom}')">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', categorieHTML);
        });
    } else {
        showAlert(result.message || 'Erreur lors du chargement des catégories', 'danger');
    }
}

// Fonction pour supprimer une catégorie
async function supprimerCategorie(categorieId, nomCategorie) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer la catégorie "${nomCategorie}" ?\n\nAttention : Cette action est irréversible et ne sera possible que si aucune annonce n'est liée à cette catégorie.`)) {
        const formData = new FormData();
        formData.append('categorie_id', categorieId);
        
        const result = await ajaxRequest('supprimer_categorie', formData);
        
        if (result.success) {
            showAlert(result.message, 'success');
            await chargerCategories();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(result.message, 'danger');
        }
    }
}

// Fonction pour réinitialiser le formulaire d'annonce
function resetAnnonceForm() {
    const form = document.getElementById('formAjouterAnnonce');
    if (!form) {
        console.error('Formulaire d\'annonce non trouvé');
        return;
    }
    
    form.reset();
    
    // Réinitialiser les variables globales
    isEditing = false;
    currentAnnonceId = null;
    
    // Réinitialiser l'aperçu d'image
    document.getElementById('imagePreview').innerHTML = '';
    
    // Réinitialiser le titre du modal
    const modalTitle = document.getElementById('modalAnnonceTitle');
    const btnSauvegarder = document.getElementById('btnSauvegarderAnnonce');
    const annonceId = document.getElementById('annonceId');
    
    if (modalTitle) modalTitle.textContent = 'Nouvelle Annonce';
    if (btnSauvegarder) btnSauvegarder.textContent = 'Ajouter l\'annonce';
    if (annonceId) annonceId.value = '';
    
    // Retirer les classes d'erreur
    form.querySelectorAll('.is-invalid').forEach(field => {
        if (field && field.classList) {
            field.classList.remove('is-invalid');
        }
    });
}

// Gestionnaire pour le formulaire d'ajout/modification d'annonce
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formAjouterAnnonce');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btnSauvegarderAnnonce');
            const spinner = document.getElementById('spinnerAnnonce');
            
            // Validation côté client
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (field && field.value !== undefined) {
                    if (!field.value.trim()) {
                        if (field.classList) field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        if (field.classList) field.classList.remove('is-invalid');
                    }
                }
            });
            
            if (!isValid) {
                showAlert('Veuillez remplir tous les champs obligatoires.', 'danger');
                return;
            }
            
            // Afficher le spinner
            if (btn) btn.disabled = true;
            if (spinner && spinner.classList) spinner.classList.remove('d-none');
            
            // Préparer les données avec FormData pour gérer les fichiers
            const formData = new FormData(this);
            
            // Déterminer l'action
            const action = isEditing ? 'modifier' : 'ajouter';
            
            try {
                const result = await ajaxRequest(action, formData);
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    
                    // Fermer le modal
                    const modalElement = document.getElementById('modalAjouterAnnonce');
                    if (modalElement) {
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) modal.hide();
                    }
                    
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
                if (btn) btn.disabled = false;
                if (spinner && spinner.classList) spinner.classList.add('d-none');
            }
        });
    }

    // Gestionnaire pour le formulaire d'ajout de catégorie
    const formCategorie = document.getElementById('formAjouterCategorie');
    if (formCategorie) {
        formCategorie.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btnSauvegarderCategorie');
            const spinner = document.getElementById('spinnerCategorie');
            
            // Validation côté client
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (field && field.value !== undefined) {
                    if (!field.value.trim()) {
                        if (field.classList) field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        if (field.classList) field.classList.remove('is-invalid');
                    }
                }
            });
            
            if (!isValid) {
                showAlert('Veuillez remplir tous les champs obligatoires.', 'danger');
                return;
            }
            
            // Afficher le spinner
            if (btn) btn.disabled = true;
            if (spinner && spinner.classList) spinner.classList.remove('d-none');
            
            // Préparer les données
            const formData = new FormData(this);
            
            try {
                const result = await ajaxRequest('ajouter_categorie', formData);
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    
                    // Fermer le modal
                    const modalElement = document.getElementById('modalAjouterCategorie');
                    if (modalElement) {
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) modal.hide();
                    }
                    
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
                if (btn) btn.disabled = false;
                if (spinner && spinner.classList) spinner.classList.add('d-none');
            }
        });
    }

    // Validation en temps réel
    document.querySelectorAll('[required]').forEach(function(field) {
        if (field) {
            field.addEventListener('blur', function() {
                if (this.value && this.value.trim()) {
                    if (this.classList) this.classList.remove('is-invalid');
                } else {
                    if (this.classList) this.classList.add('is-invalid');
                }
            });
        }
    });

    // Event listeners pour les modals
    const modalAnnonce = document.getElementById('modalAjouterAnnonce');
    if (modalAnnonce) {
        modalAnnonce.addEventListener('show.bs.modal', function() {
            if (!isEditing) {
                resetAnnonceForm();
            }
        });

        modalAnnonce.addEventListener('hidden.bs.modal', function() {
            resetAnnonceForm();
        });
    }

    const modalCategories = document.getElementById('modalGererCategories');
    if (modalCategories) {
        modalCategories.addEventListener('show.bs.modal', function() {
            chargerCategories();
        });
    }
});

// Fonction pour voir une annonce
function voirAnnonce(annonceId) {
    window.open('voir_annonce.php?id=' + annonceId, '_blank');
}

// Fonction pour modifier une annonce
async function modifierAnnonce(annonceId) {
    const formData = new FormData();
    formData.append('annonce_id', annonceId);
    
    const result = await ajaxRequest('get_annonce', formData);
    
    if (result.success) {
        const annonce = result.annonce;
        
        // Remplir le formulaire avec vérifications
        const fields = {
            'annonceId': annonce.id,
            'annonceTitre': annonce.titre,
            'annonceContenu': annonce.contenu,
            'annonceCategorie': annonce.categorie_id || '',
            'annoncePriorite': annonce.priorite,
            'annonceStatut': annonce.statut,
            'annonceDateDebut': annonce.date_debut || '',
            'annonceDateFin': annonce.date_fin || ''
        };

        Object.keys(fields).forEach(fieldId => {
            const element = document.getElementById(fieldId);
            if (element) {
                element.value = fields[fieldId];
            } else {
                console.warn(`Champ ${fieldId} non trouvé`);
            }
        });
        
        // Afficher l'image actuelle si elle existe
        if (annonce.image) {
            document.getElementById('imagePreview').innerHTML = `
                <div class="image-preview-container">
                    <img src="${annonce.image}" alt="Image actuelle" class="image-preview">
                    <button type="button" class="remove-image" onclick="removeImagePreview()" title="Supprimer l'image">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <small class="text-muted d-block mt-1">Image actuelle (vous pouvez la remplacer en sélectionnant un nouveau fichier)</small>
            `;
        }
        
        // Configurer le mode édition
        isEditing = true;
        currentAnnonceId = annonceId;
        
        // Changer le titre du modal
        const modalTitle = document.getElementById('modalAnnonceTitle');
        const btnSauvegarder = document.getElementById('btnSauvegarderAnnonce');
        
        if (modalTitle) modalTitle.textContent = 'Modifier l\'annonce';
        if (btnSauvegarder) btnSauvegarder.textContent = 'Sauvegarder les modifications';
        
        // Ouvrir le modal
        const modalElement = document.getElementById('modalAjouterAnnonce');
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    } else {
        showAlert(result.message, 'danger');
    }
}

// Fonction pour archiver une annonce
async function archiverAnnonce(annonceId) {
    if (confirm('Êtes-vous sûr de vouloir archiver cette annonce ?')) {
        const formData = new FormData();
        formData.append('annonce_id', annonceId);
        formData.append('nouveau_statut', 'archive');
        
        const result = await ajaxRequest('changer_statut', formData);
        
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
        const formData = new FormData();
        formData.append('annonce_id', annonceId);
        
        const result = await ajaxRequest('supprimer', formData);
        
        if (result.success) {
            showAlert(result.message, 'success');
            await reloadAnnonces();
        } else {
            showAlert(result.message, 'danger');
        }
    }
}

// Auto-actualisation périodique des statistiques
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
</script>
</body>
</html>