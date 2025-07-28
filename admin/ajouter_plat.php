<?php
session_start();
require_once '../config.php';

// Vérifie que l'utilisateur est un admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Initialisation des variables pour affichage et erreurs
$errors = [];
$success = false;

// Récupération des catégories pour le formulaire
try {
    $categories = $conn->query("SELECT id, nom FROM categories ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des catégories : " . $e->getMessage());
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = floatval($_POST['prix'] ?? 0);
    $categorie_id = isset($_POST['categorie']) ? (int)$_POST['categorie'] : 0;
    $image = null;

    // Validation
    if ($nom === '') {
        $errors[] = "Le nom du plat est obligatoire.";
    }
    if ($description === '') {
        $errors[] = "La description est obligatoire.";
    }
    if ($prix <= 0) {
        $errors[] = "Le prix doit être supérieur à zéro.";
    }
    if ($categorie_id === 0) {
        $errors[] = "Vous devez sélectionner une catégorie.";
    }

    // Traitement de l'image si pas d'erreur jusque là
    if (empty($errors) && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $extensionsAutorisees = ['jpg', 'jpeg', 'png'];
            $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, $extensionsAutorisees)) {
                $errors[] = "Format de fichier non autorisé. Seuls jpg, jpeg et png sont acceptés.";
            } else {
                $nomFichier = time() . '_' . preg_replace('/[^a-zA-Z0-9_.]/', '', $_FILES['image']['name']);
                $cheminDestination = '../uploads/' . $nomFichier;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $cheminDestination)) {
                    $errors[] = "Erreur lors de l'enregistrement de l'image.";
                } else {
                    $image = $nomFichier;
                }
            }
        } else {
            $errors[] = "Erreur lors du téléchargement de l'image.";
        }
    }

    // Insertion si pas d'erreurs
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO plats (nom, description, prix, categorie_id, image) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$nom, $description, $prix, $categorie_id, $image])) {
            $success = true;
            // Redirection après succès possible
            header('Location: gestion_plats.php?success=1');
            exit;
        } else {
            $errors[] = "Erreur lors de l'ajout du plat en base de données.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>menu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#55D5E0',
                        'secondary': '#335F8A', 
                        'dark': '#2F4558',
                        'accent': '#F6B12D',
                        'warning': '#F26619'
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-dark shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-plus-circle text-primary text-2xl"></i>
                    <h1 class="text-white text-xl sm:text-2xl font-bold">Ajouter un produit</h1>
                </div>
                <a href="logout.php" class="flex items-center space-x-2 bg-warning hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="hidden sm:inline">Déconnexion</span>
                </a>
            </div>
        </div>
    </header>
    
    <!-- Navigation -->
    <nav class="bg-secondary shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-0 overflow-x-auto">
                <a href="index.php" class="flex items-center space-x-2 text-white hover:bg-dark px-4 py-3 transition-colors duration-200 whitespace-nowrap">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="produits.php" class="flex items-center space-x-2 text-white bg-dark px-4 py-3 transition-colors duration-200 whitespace-nowrap">
                    <i class="fas fa-box"></i>
                    <span>Produits</span>
                </a>
                <a href="commandes.php" class="flex items-center space-x-2 text-white hover:bg-dark px-4 py-3 transition-colors duration-200 whitespace-nowrap">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Commandes</span>
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Breadcrumb -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-500">
                <li>
                    <a href="index.php" class="hover:text-primary transition-colors duration-200">
                        <i class="fas fa-home"></i>
                    </a>
                </li>
                <li><i class="fas fa-chevron-right text-gray-300"></i></li>
                <li>
                    <a href="produits.php" class="hover:text-primary transition-colors duration-200">Produits</a>
                </li>
                <li><i class="fas fa-chevron-right text-gray-300"></i></li>
                <li class="text-dark font-medium">Nouveau produit</li>
            </ol>
        </nav>
    </div>
    
    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Form Section -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-primary to-cyan-400 px-6 py-4">
                        <h2 class="text-xl font-bold text-white flex items-center space-x-2">
                            <i class="fas fa-plus text-white"></i>
                            <span>Créer un nouveau produit</span>
                        </h2>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-6" id="productForm">
                        <!-- Nom du produit -->
                        <div class="space-y-2">
                            <label for="nom" class="flex items-center space-x-2 text-sm font-semibold text-dark">
                                <i class="fas fa-tag text-primary"></i>
                                <span>Nom du produit</span>
                                <span class="text-warning">*</span>
                            </label>
                            <input type="text" 
                                   id="nom" 
                                   name="nom" 
                                   required
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 transition-colors duration-200 text-dark placeholder-gray-400"
                                   placeholder="Ex: iPhone 14 Pro Max">
                            <p class="text-xs text-gray-500 flex items-center space-x-1">
                                <i class="fas fa-lightbulb"></i>
                                <span>Choisissez un nom clair et descriptif</span>
                            </p>
                        </div>
                        
                        <!-- Description -->
                        <div class="space-y-2">
                            <label for="description" class="flex items-center space-x-2 text-sm font-semibold text-dark">
                                <i class="fas fa-align-left text-primary"></i>
                                <span>Description</span>
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      rows="4"
                                      class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 transition-colors duration-200 text-dark placeholder-gray-400 resize-vertical"
                                      placeholder="Décrivez votre produit en détail..."></textarea>
                            <p class="text-xs text-gray-500 flex items-center space-x-1">
                                <i class="fas fa-info-circle"></i>
                                <span>Une bonne description améliore les ventes</span>
                            </p>
                        </div>
                        
                        <!-- Prix et Stock -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label for="prix" class="flex items-center space-x-2 text-sm font-semibold text-dark">
                                    <i class="fas fa-money-bill-wave text-accent"></i>
                                    <span>Prix (CFA)</span>
                                    <span class="text-warning">*</span>
                                </label>
                                <div class="relative">
                                    <input type="number" 
                                           id="prix" 
                                           name="prix" 
                                           step="0.01" 
                                           required
                                           min="0"
                                           class="w-full pl-4 pr-16 py-3 border-2 border-gray-200 rounded-lg focus:border-accent focus:ring focus:ring-accent focus:ring-opacity-50 transition-colors duration-200 text-dark"
                                           placeholder="0.00">
                                    <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-accent font-semibold">CFA</span>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
    <label for="categorie" class="flex items-center space-x-2 text-sm font-semibold text-dark">
        <i class="fas fa-tags text-secondary"></i>
        <span>Catégorie</span>
        <span class="text-warning">*</span>
    </label>
    <select id="categorie" name="categorie" required
            class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-secondary focus:ring focus:ring-secondary focus:ring-opacity-50 transition-colors duration-200 text-dark">
        <option value="">-- Choisir une catégorie --</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

                        </div>
                        
                        <!-- Image Upload -->
                        <div class="space-y-2">
                            <label for="image" class="flex items-center space-x-2 text-sm font-semibold text-dark">
                                <i class="fas fa-image text-primary"></i>
                                <span>Image du produit</span>
                            </label>
                            <div class="relative">
                                <input type="file" 
                                       id="image" 
                                       name="image" 
                                       accept="image/*"
                                       class="w-full px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 transition-colors duration-200 text-dark file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-cyan-600">
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs text-gray-500">
                                <div class="flex items-center space-x-1">
                                    <i class="fas fa-check text-green-500"></i>
                                    <span>Formats: JPG, JPEG, PNG, GIF</span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <i class="fas fa-info-circle text-blue-500"></i>
                                    <span>Taille recommandée: 800x800px</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4 pt-6 border-t border-gray-200">
                            <button type="submit" 
                                    class="flex-1 flex items-center justify-center space-x-2 bg-primary hover:bg-cyan-400 text-white font-semibold py-3 px-6 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-1">
                                <i class="fas fa-plus"></i>
                                <span>Ajouter le produit</span>
                            </button>
                            
                            <a href="produits.php" 
                               class="flex-1 flex items-center justify-center space-x-2 bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-6 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-1">
                                <i class="fas fa-times"></i>
                                <span>Annuler</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Sidebar Section -->
            <div class="space-y-6">
                <!-- Preview -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-accent to-orange-400 px-4 py-3">
                        <h3 class="text-lg font-bold text-white flex items-center space-x-2">
                            <i class="fas fa-eye text-white"></i>
                            <span>Aperçu</span>
                        </h3>
                    </div>
                    
                   <div class="p-4">
    <div class="border-2 border-dashed border-gray-200 rounded-lg p-4 text-center">
        <div id="imagePreview" class="hidden">
            <img id="previewImg" src="" alt="Aperçu" class="w-full h-32 object-cover rounded-lg mb-2">
        </div>
        <div id="imagePlaceholder" class="text-gray-400">
            <i class="fas fa-image text-4xl mb-2"></i>
            <p class="text-sm">Image du produit</p>
        </div>
    </div>
    <div class="mt-4 space-y-2">
        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Nom:</span>
            <span id="previewNom" class="text-sm font-semibold text-dark">-</span>
        </div>

        <div class="flex flex-col">
            <span class="text-sm text-gray-600">Description:</span>
            <span id="previewDescription" class="text-sm text-dark italic mt-1">-</span>
        </div>

        <div class="flex justify-between">
            <span class="text-sm text-gray-600">Prix:</span>
            <span id="previewPrix" class="text-sm font-bold text-accent">- CFA</span>
        </div>
    </div>
</div>

                
                <!-- Tips -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-secondary to-dark px-4 py-3">
                        <h3 class="text-lg font-bold text-white flex items-center space-x-2">
                            <i class="fas fa-lightbulb text-primary"></i>
                            <span>Conseils</span>
                        </h3>
                    </div>
                    
                    <div class="p-4 space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-primary rounded-full flex items-center justify-center">
                                <i class="fas fa-camera text-white text-xs"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-dark text-sm">Photo de qualité</h4>
                                <p class="text-xs text-gray-600">Utilisez des images nettes et bien éclairées</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-accent rounded-full flex items-center justify-center">
                                <i class="fas fa-money-bill text-white text-xs"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-dark text-sm">Prix compétitif</h4>
                                <p class="text-xs text-gray-600">Recherchez les prix du marché</p>
                            </div>
                        </div>
                        
                        
                        
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-warning rounded-full flex items-center justify-center">
                                <i class="fas fa-edit text-white text-xs"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-dark text-sm">Description détaillée</h4>
                                <p class="text-xs text-gray-600">Plus d'infos = plus de ventes</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-warning to-red-500 px-4 py-3">
                        <h3 class="text-lg font-bold text-white flex items-center space-x-2">
                            <i class="fas fa-chart-line text-white"></i>
                            <span>Actions rapides</span>
                        </h3>
                    </div>
                    
                    <div class="p-4 space-y-3">
                        <a href="produits.php" 
                           class="w-full flex items-center space-x-2 bg-gray-100 hover:bg-gray-200 text-dark font-medium py-2 px-4 rounded-lg transition-colors duration-200">
                            <i class="fas fa-list text-secondary"></i>
                            <span>Voir tous les produits</span>
                        </a>
                        
                        <button type="button" onclick="document.getElementById('productForm').reset(); updatePreview();" 
                                class="w-full flex items-center space-x-2 bg-primary hover:bg-cyan-400 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200">
                            <i class="fas fa-eraser"></i>
                            <span>Vider le formulaire</span>
                        </button>
                        
                        <a href="commandes.php" 
                           class="w-full flex items-center space-x-2 bg-accent hover:bg-yellow-500 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Voir les commandes</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

   <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Vérification de l'existence des éléments
        const nomInput = document.getElementById('nom');
        const prixInput = document.getElementById('prix');
        const imageInput = document.getElementById('image');
        const previewNom = document.getElementById('previewNom');
        const previewPrix = document.getElementById('previewPrix');
        const previewImg = document.getElementById('previewImg');
        const imagePreview = document.getElementById('imagePreview');
        const imagePlaceholder = document.getElementById('imagePlaceholder');
        const form = document.getElementById('productForm');
        const descriptionInput = document.getElementById('description');
descriptionInput?.addEventListener('input', updatePreview);


        function updatePreview() {
        const nom = nomInput?.value || '-';
        const prix = prixInput?.value || '0';
        const description = document.getElementById('description')?.value || '-';

        if (previewNom) previewNom.textContent = nom;
        if (previewPrix) previewPrix.textContent = prix ? `${prix} CFA` : '- CFA';
        const previewDescription = document.getElementById('previewDescription');
        if (previewDescription) previewDescription.textContent = description;
    }


        // Image preview
        if (imageInput) {
            imageInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        if (previewImg) previewImg.src = e.target.result;
                        imagePreview?.classList.remove('hidden');
                        imagePlaceholder?.classList.add('hidden');
                    };
                    reader.readAsDataURL(file);
                } else {
                    imagePreview?.classList.add('hidden');
                    imagePlaceholder?.classList.remove('hidden');
                }
            });
        }

        // Live preview events
        nomInput?.addEventListener('input', updatePreview);
        prixInput?.addEventListener('input', updatePreview);

        // Bouton de validation
        if (form) {
            form.addEventListener('submit', function (e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;

                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Ajout en cours...';
                submitBtn.disabled = true;

                // Restauration si le formulaire est invalide
                setTimeout(() => {
                    if (!this.checkValidity()) {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                }, 100);
            });
        }

        // Initialiser l’aperçu
        updatePreview();
    });

</script>

</body>
</html>