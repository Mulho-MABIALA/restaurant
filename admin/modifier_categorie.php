<?php
require_once '../config.php';

// Vérifier si l'ID de la catégorie est présent
if (!isset($_GET['id'])) {
    header("Location: categories_plats.php");
    exit;
}

$id = (int)$_GET['id'];

// Récupérer les informations de la catégorie
$stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$categorie = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérifier si la catégorie existe
if (!$categorie) {
    header("Location: categories_plats.php?message=error:Catégorie introuvable");
    exit;
}

// Traitement du formulaire de modification
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_categorie'])) {
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);

    if (!empty($nom)) {
        try {
            // Vérifier si le nouveau nom existe déjà (sauf pour la catégorie actuelle)
            $stmt = $conn->prepare("SELECT id FROM categories WHERE nom = ? AND id != ?");
            $stmt->execute([$nom, $id]);
            
            if ($stmt->rowCount() === 0) {
                $update = $conn->prepare("UPDATE categories SET nom = ?, description = ? WHERE id = ?");
                $update->execute([$nom, $description, $id]);
                $message = "success:Catégorie modifiée avec succès";
                // Rafraîchir les données
                $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $categorie = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $message = "error:Ce nom de catégorie existe déjà";
            }
        } catch (PDOException $e) {
            $message = "error:Erreur technique : " . $e->getMessage();
        }
    } else {
        $message = "error:Le nom de la catégorie est obligatoire";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Catégorie</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .animate-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <main class="p-6">
                <!-- En-tête -->
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl p-6 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold"><i class="fas fa-edit mr-2"></i>Modifier Catégorie</h1>
                            <p class="opacity-90">Modifiez les détails de cette catégorie</p>
                        </div>
                        <a href="categories_plats.php" class="bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Retour
                        </a>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (!empty($message)): ?>
                    <?php list($type, $text) = explode(':', $message, 2); ?>
                    <div class="mb-6 animate-in <?= $type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> p-4 rounded-lg">
                        <?= $text ?>
                    </div>
                <?php endif; ?>

                <!-- Formulaire de modification -->
                <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                    <form method="POST">
                        <div class="mb-6">
                            <label for="nom" class="block font-medium mb-2">Nom de la catégorie *</label>
                            <input type="text" id="nom" name="nom" required
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                value="<?= htmlspecialchars($categorie['nom']) ?>">
                        </div>

                        <div class="mb-6">
                            <label for="description" class="block font-medium mb-2">Description</label>
                            <textarea id="description" name="description" rows="3"
                                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($categorie['description']) ?></textarea>
                        </div>

                        <div class="flex justify-between items-center">
                            <button type="submit" name="modifier_categorie"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                                <i class="fas fa-save mr-2"></i>Enregistrer les modifications
                            </button>

                            <a href="categories_plats.php" class="text-gray-600 hover:text-gray-800">
                                <i class="fas fa-times mr-1"></i>Annuler
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Section danger -->
                <div class="mt-6 bg-red-50 border border-red-200 rounded-xl p-6">
                    <h2 class="text-xl font-bold text-red-800 mb-4 flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Zone dangereuse
                    </h2>
                    
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
                        <div class="mb-4 sm:mb-0">
                            <h3 class="font-medium text-red-700">Supprimer cette catégorie</h3>
                            <p class="text-sm text-red-600">
                                Attention : Cette action est irréversible. Vérifiez qu'aucun plat n'utilise cette catégorie avant suppression.
                            </p>
                        </div>
                        
                        <a href="categories_plats.php?supprimer=<?= $id ?>" 
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cette catégorie ?');"
                           class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors whitespace-nowrap">
                            <i class="fas fa-trash mr-2"></i>Supprimer
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Animation des éléments
        document.addEventListener('DOMContentLoaded', () => {
            const elements = document.querySelectorAll('.animate-in, .card-hover');
            elements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                element.style.animation = `fadeIn 0.5s ease-out ${index * 0.1}s forwards`;
            });
        });
    </script>
</body>
</html>