<?php
session_start();
require_once '../../config.php';

// Vérification de l'authentification
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Récupérer l'ID de la procédure
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die('ID de procédure invalide');
}

// Récupérer la procédure
$stmt = $conn->prepare("SELECT p.*, a.username as created_by_name 
                       FROM procedures p 
                       LEFT JOIN admin a ON p.created_by = a.id 
                       WHERE p.id = ? AND p.status != 'deleted'");
$stmt->execute([$id]);
$procedure = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$procedure) {
    die('Procédure non trouvée');
}

// Récupérer les informations de la catégorie
$stmt = $conn->prepare("SELECT * FROM procedure_categories WHERE nom = ? AND active = 1");
$stmt->execute([$procedure['categorie']]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aperçu - <?= htmlspecialchars($procedure['titre']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<div class="container mx-auto px-6 py-8 max-w-4xl">
    
    <!-- En-tête avec boutons d'action -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6 no-print">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-eye mr-2 text-blue-600"></i>
                Aperçu de la procédure
            </h1>
            <div class="flex gap-3">
                <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-print mr-2"></i>Imprimer
                </button>
                <?php if ($procedure['fichier_url']): ?>
                    <a href="../../<?= htmlspecialchars($procedure['fichier_url']) ?>" target="_blank" 
                       class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-download mr-2"></i>Télécharger fichier
                    </a>
                <?php endif; ?>
                <button onclick="window.close()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-times mr-2"></i>Fermer
                </button>
            </div>
        </div>
    </div>

    <!-- Contenu de la procédure -->
    <div class="bg-white rounded-lg shadow-sm p-8">
        
        <!-- En-tête de la procédure -->
        <div class="border-b pb-6 mb-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">
                        <?= htmlspecialchars($procedure['titre']) ?>
                    </h1>
                    
                    <!-- Métadonnées -->
                    <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                        <div class="flex items-center">
                            <i class="fas fa-tag mr-1"></i>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: <?= $category['couleur'] ?? '#3B82F6' ?>20; color: <?= $category['couleur'] ?? '#3B82F6' ?>;">
                                <?= htmlspecialchars($procedure['categorie']) ?>
                            </span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-code-branch mr-1"></i>
                            Version <?= $procedure['version'] ?>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-calendar mr-1"></i>
                            Créé le <?= date('d/m/Y à H:i', strtotime($procedure['created_at'])) ?>
                        </div>
                        <?php if ($procedure['created_by_name']): ?>
                        <div class="flex items-center">
                            <i class="fas fa-user mr-1"></i>
                            Par <?= htmlspecialchars($procedure['created_by_name']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statut -->
                <div>
                    <?php
                    $status_colors = [
                        'draft' => 'bg-yellow-100 text-yellow-800',
                        'published' => 'bg-green-100 text-green-800',
                        'archived' => 'bg-gray-100 text-gray-800'
                    ];
                    $status_labels = [
                        'draft' => 'Brouillon',
                        'published' => 'Publié',
                        'archived' => 'Archivé'
                    ];
                    ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $status_colors[$procedure['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                        <i class="fas fa-circle mr-1 text-xs"></i>
                        <?= $status_labels[$procedure['status']] ?? $procedure['status'] ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Contenu principal -->
        <div class="prose max-w-none">
            <?php if ($procedure['contenu']): ?>
                <div class="text-gray-800 leading-relaxed">
                    <?= nl2br(htmlspecialchars_decode($procedure['contenu'])) ?>
                </div>
            <?php else: ?>
                <div class="text-gray-500 italic text-center py-8">
                    <i class="fas fa-file-alt text-4xl mb-4"></i>
                    <p>Aucun contenu disponible pour cette procédure.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Fichier joint -->
        <?php if ($procedure['fichier_url']): ?>
        <div class="mt-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <h3 class="text-lg font-semibold text-blue-900 mb-2">
                <i class="fas fa-paperclip mr-2"></i>Fichier joint
            </h3>
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <?php
                    $ext = strtolower(pathinfo($procedure['fichier_url'], PATHINFO_EXTENSION));
                    $icons = [
                        'pdf' => 'fa-file-pdf text-red-500',
                        'doc' => 'fa-file-word text-blue-500',
                        'docx' => 'fa-file-word text-blue-500',
                        'jpg' => 'fa-file-image text-green-500',
                        'jpeg' => 'fa-file-image text-green-500',
                        'png' => 'fa-file-image text-green-500',
                        'txt' => 'fa-file-alt text-gray-500',
                        'xlsx' => 'fa-file-excel text-green-500'
                    ];
                    ?>
                    <i class="fas <?= $icons[$ext] ?? 'fa-file text-gray-500' ?> text-2xl mr-3"></i>
                    <div>
                        <p class="font-medium text-gray-900"><?= basename($procedure['fichier_url']) ?></p>
                        <p class="text-sm text-gray-600">
                            <?php
                            $file_path = '../../' . $procedure['fichier_url'];
                            if (file_exists($file_path)) {
                                $file_size = filesize($file_path);
                                echo number_format($file_size / 1024, 2) . ' KB';
                            }
                            ?>
                        </p>
                    </div>
                </div>
                <a href="../../<?= htmlspecialchars($procedure['fichier_url']) ?>" target="_blank" 
                   class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition no-print">
                    <i class="fas fa-external-link-alt mr-1"></i>Ouvrir
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Informations de mise à jour -->
        <?php if ($procedure['updated_at'] && $procedure['updated_at'] != $procedure['created_at']): ?>
        <div class="mt-6 pt-6 border-t text-sm text-gray-500">
            <i class="fas fa-clock mr-1"></i>
            Dernière modification : <?= date('d/m/Y à H:i', strtotime($procedure['updated_at'])) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>