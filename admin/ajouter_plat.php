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

    // Debug: Affichage des données reçues (à supprimer en production)
    error_log("Données reçues: nom=$nom, description=$description, prix=$prix, categorie_id=$categorie_id");

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

    // Vérifier que la catégorie existe
    if ($categorie_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$categorie_id]);
        if (!$stmt->fetch()) {
            $errors[] = "La catégorie sélectionnée n'existe pas.";
        }
    }

    // Traitement de l'image si pas d'erreur jusque là
    if (empty($errors) && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $extensionsAutorisees = ['jpg', 'jpeg', 'png', 'gif'];
            $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, $extensionsAutorisees)) {
                $errors[] = "Format de fichier non autorisé. Seuls jpg, jpeg, png et gif sont acceptés.";
            } else {
                // Créer le dossier uploads s'il n'existe pas
                $uploadDir = '../uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $nomFichier = time() . '_' . preg_replace('/[^a-zA-Z0-9_.]/', '', $_FILES['image']['name']);
                $cheminDestination = $uploadDir . $nomFichier;
                
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $cheminDestination)) {
                    $errors[] = "Erreur lors de l'enregistrement de l'image.";
                } else {
                    $image = $nomFichier;
                }
            }
        } else {
            $errors[] = "Erreur lors du téléchargement de l'image (Code: " . $_FILES['image']['error'] . ").";
        }
    }

    // Insertion si pas d'erreurs
    if (empty($errors)) {
        try {
            // Vérifier la structure de la table
            $stmt = $conn->prepare("DESCRIBE plats");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("Colonnes de la table plats: " . implode(', ', $columns));

            // Insertion avec gestion d'erreur améliorée
            $stmt = $conn->prepare("INSERT INTO plats (nom, description, prix, categorie_id, image) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute([$nom, $description, $prix, $categorie_id, $image]);
            
            if ($result) {
                $newId = $conn->lastInsertId();
                error_log("Plat ajouté avec succès. ID: $newId");
                $success = true;
                
                // Redirection après succès
                header('Location: gestion_plats.php?success=1&message=' . urlencode('Plat ajouté avec succès'));
                exit;
            } else {
                $errorInfo = $stmt->errorInfo();
                $errors[] = "Erreur lors de l'ajout du plat en base de données: " . $errorInfo[2];
                error_log("Erreur SQL: " . print_r($errorInfo, true));
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur de base de données: " . $e->getMessage();
            error_log("Exception PDO: " . $e->getMessage());
        }
    }

    // Debug: Affichage des erreurs
    if (!empty($errors)) {
        error_log("Erreurs: " . implode(', ', $errors));
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un plat - Menu</title>
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
                    },
                    animation: {
                        'fadeIn': 'fadeIn 0.5s ease-in-out',
                        'slideDown': 'slideDown 0.3s ease-out',
                        'bounce-subtle': 'bounceSubtle 2s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(-10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        slideDown: {
                            '0%': { opacity: '0', transform: 'translateY(-20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        bounceSubtle: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-5px)' }
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #55D5E0, #335F8A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .floating-animation {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        .input-glow:focus {
            box-shadow: 0 0 0 3px rgba(85, 213, 224, 0.3), 0 0 15px rgba(85, 213, 224, 0.2);
        }
        
        .card-hover {
            transition: all 0.3s ease;
            transform: perspective(1000px) rotateX(0deg);
        }
        
        .card-hover:hover {
            transform: perspective(1000px) rotateX(5deg) translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-cyan-100 min-h-screen">
    <!-- Particles Background Effect -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-10 w-2 h-2 bg-primary/20 rounded-full animate-bounce-subtle"></div>
        <div class="absolute top-40 right-20 w-3 h-3 bg-accent/20 rounded-full floating-animation" style="animation-delay: 1s;"></div>
        <div class="absolute bottom-40 left-20 w-2 h-2 bg-warning/20 rounded-full animate-bounce-subtle" style="animation-delay: 2s;"></div>
        <div class="absolute top-60 right-10 w-4 h-4 bg-secondary/20 rounded-full floating-animation" style="animation-delay: 0.5s;"></div>
    </div>

    <!-- Messages d'erreur et de succès -->
    <?php if (!empty($errors)): ?>
        <div class="fixed top-4 right-4 z-50 animate-slideDown">
            <div class="bg-red-50 border-l-4 border-red-400 text-red-800 px-6 py-4 rounded-lg shadow-xl backdrop-blur-sm max-w-md">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl mr-3"></i>
                    <div>
                        <h4 class="font-bold text-lg">Erreurs détectées</h4>
                        <ul class="mt-2 space-y-1">
                            <?php foreach ($errors as $error): ?>
                                <li class="text-sm flex items-center">
                                    <i class="fas fa-circle text-xs mr-2 text-red-400"></i>
                                    <?= htmlspecialchars($error) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="fixed top-4 right-4 z-50 animate-slideDown">
            <div class="bg-green-50 border-l-4 border-green-400 text-green-800 px-6 py-4 rounded-lg shadow-xl backdrop-blur-sm">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <div>
                        <h4 class="font-bold text-lg">Succès !</h4>
                        <p class="text-sm mt-1">Plat ajouté avec succès !</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Header avec effet glassmorphism -->
    <header class="relative bg-gradient-to-r from-dark via-secondary to-dark shadow-2xl overflow-hidden">
        <div class="absolute inset-0 bg-black/10"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <div class="w-12 h-12 bg-gradient-to-br from-primary to-cyan-300 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-plus text-white text-xl"></i>
                        </div>
                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-accent rounded-full animate-pulse"></div>
                    </div>
                    <div>
                        <h1 class="text-white text-2xl sm:text-3xl font-bold tracking-tight">Ajouter un plat</h1>
                        <p class="text-blue-200 text-sm mt-1">Créez un nouveau plat délicieux</p>
                    </div>
                </div>
                <a href="logout.php" class="group flex items-center space-x-2 bg-gradient-to-r from-warning to-orange-500 hover:from-orange-500 hover:to-red-500 text-white px-6 py-3 rounded-xl shadow-lg transition-all duration-300 transform hover:scale-105">
                    <i class="fas fa-sign-out-alt group-hover:rotate-12 transition-transform duration-300"></i>
                    <span class="hidden sm:inline font-semibold">Déconnexion</span>
                </a>
            </div>
        </div>
    </header>
    
    <!-- Navigation avec effet moderne -->
    <nav class="bg-gradient-to-r from-secondary to-dark shadow-lg sticky top-0 z-40 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-0 overflow-x-auto">
                <a href="index.php" class="group flex items-center space-x-3 text-blue-100 hover:text-white hover:bg-white/10 px-6 py-4 transition-all duration-300 whitespace-nowrap border-b-2 border-transparent hover:border-primary">
                    <i class="fas fa-tachometer-alt group-hover:scale-110 transition-transform duration-300"></i>
                    <span class="font-medium">Tableau de bord</span>
                </a>
                <a href="gestion_plats.php" class="group flex items-center space-x-3 text-white bg-gradient-to-r from-primary/20 to-cyan-400/20 px-6 py-4 transition-all duration-300 whitespace-nowrap border-b-2 border-primary">
                    <i class="fas fa-utensils group-hover:scale-110 transition-transform duration-300"></i>
                    <span class="font-medium">Gestion des plats</span>
                </a>
                <a href="commandes.php" class="group flex items-center space-x-3 text-blue-100 hover:text-white hover:bg-white/10 px-6 py-4 transition-all duration-300 whitespace-nowrap border-b-2 border-transparent hover:border-accent">
                    <i class="fas fa-shopping-cart group-hover:scale-110 transition-transform duration-300"></i>
                    <span class="font-medium">Commandes</span>
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Breadcrumb élégant -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <nav class="flex items-center space-x-2 text-sm">
            <a href="index.php" class="group flex items-center text-gray-500 hover:text-primary transition-colors duration-200">
                <i class="fas fa-home group-hover:scale-110 transition-transform duration-200"></i>
            </a>
            <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
            <a href="gestion_plats.php" class="text-gray-500 hover:text-primary transition-colors duration-200 font-medium">Gestion des plats</a>
            <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
            <span class="text-dark font-bold">Nouveau plat</span>
        </nav>
    </div>
    
    <!-- Main Content avec design moderne -->
    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Form Section avec design premium -->
            <div class="lg:col-span-2">
                <div class="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl overflow-hidden border border-white/20 card-hover">
                    <!-- Header du formulaire -->
                    <div class="bg-gradient-to-r from-primary via-cyan-400 to-blue-500 px-8 py-6">
                        <h2 class="text-2xl font-bold text-white flex items-center space-x-3">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-plus text-white"></i>
                            </div>
                            <span>Créer un nouveau plat</span>
                        </h2>
                        <p class="text-blue-100 mt-2">Remplissez les informations ci-dessous</p>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="p-8 space-y-8" id="productForm">
                        <!-- Nom du plat avec design premium -->
                        <div class="space-y-3">
                            <label for="nom" class="flex items-center space-x-2 text-lg font-bold text-gray-800">
                                <div class="w-8 h-8 bg-gradient-to-br from-primary to-cyan-400 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-utensils text-white text-sm"></i>
                                </div>
                                <span>Nom du plat</span>
                                <span class="text-warning text-xl">*</span>
                            </label>
                            <input type="text" 
                                   id="nom" 
                                   name="nom" 
                                   required
                                   value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                                   class="w-full px-6 py-4 border-2 border-gray-200 rounded-xl focus:border-primary input-glow transition-all duration-300 text-gray-800 placeholder-gray-400 text-lg bg-white/80"
                                   placeholder="Ex: Thieboudienne aux légumes délicieuse">
                            <div class="flex items-center space-x-2 text-sm text-gray-500 bg-blue-50/50 px-4 py-2 rounded-lg">
                                <i class="fas fa-lightbulb text-amber-500"></i>
                                <span>Choisissez un nom qui donne envie et soit mémorable</span>
                            </div>
                        </div>
                        
                        <!-- Description avec style moderne -->
                        <div class="space-y-3">
                            <label for="description" class="flex items-center space-x-2 text-lg font-bold text-gray-800">
                                <div class="w-8 h-8 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-align-left text-white text-sm"></i>
                                </div>
                                <span>Description</span>
                                <span class="text-warning text-xl">*</span>
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      rows="5"
                                      required
                                      class="w-full px-6 py-4 border-2 border-gray-200 rounded-xl focus:border-emerald-400 input-glow transition-all duration-300 text-gray-800 placeholder-gray-400 text-lg resize-vertical bg-white/80"
                                      placeholder="Décrivez votre plat de manière appétissante : ingrédients principaux, mode de préparation, saveurs..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            <div class="flex items-center space-x-2 text-sm text-gray-500 bg-emerald-50/50 px-4 py-2 rounded-lg">
                                <i class="fas fa-info-circle text-emerald-500"></i>
                                <span>Une description détaillée augmente vos ventes de 40%</span>
                            </div>
                        </div>
                        
                        <!-- Prix et Catégorie avec design premium -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="space-y-3">
                                <label for="prix" class="flex items-center space-x-2 text-lg font-bold text-gray-800">
                                    <div class="w-8 h-8 bg-gradient-to-br from-accent to-yellow-500 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-money-bill-wave text-white text-sm"></i>
                                    </div>
                                    <span>Prix (CFA)</span>
                                    <span class="text-warning text-xl">*</span>
                                </label>
                                <div class="relative">
                                    <input type="number" 
                                           id="prix" 
                                           name="prix" 
                                           step="0.01" 
                                           required
                                           min="0"
                                           value="<?= htmlspecialchars($_POST['prix'] ?? '') ?>"
                                           class="w-full pl-6 pr-20 py-4 border-2 border-gray-200 rounded-xl focus:border-accent input-glow transition-all duration-300 text-gray-800 text-lg bg-white/80"
                                           placeholder="0.00">
                                    <div class="absolute right-4 top-1/2 transform -translate-y-1/2 bg-gradient-to-r from-accent to-yellow-500 text-white px-3 py-1 rounded-lg font-bold text-sm">
                                        CFA
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-3">
                                <label for="categorie" class="flex items-center space-x-2 text-lg font-bold text-gray-800">
                                    <div class="w-8 h-8 bg-gradient-to-br from-secondary to-blue-600 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-tags text-white text-sm"></i>
                                    </div>
                                    <span>Catégorie</span>
                                    <span class="text-warning text-xl">*</span>
                                </label>
                                <select id="categorie" name="categorie" required
                                        class="w-full px-6 py-4 border-2 border-gray-200 rounded-xl focus:border-secondary input-glow transition-all duration-300 text-gray-800 text-lg bg-white/80">
                                    <option value="">🔖 Choisir une catégorie</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= (isset($_POST['categorie']) && $_POST['categorie'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Image Upload avec design moderne -->
                        <div class="space-y-3">
                            <label for="image" class="flex items-center space-x-2 text-lg font-bold text-gray-800">
                                <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-pink-500 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-image text-white text-sm"></i>
                                </div>
                                <span>Image du plat</span>
                            </label>
                            <div class="relative">
                                <input type="file" 
                                       id="image" 
                                       name="image" 
                                       accept="image/*"
                                       class="w-full px-6 py-4 border-2 border-dashed border-purple-300 rounded-xl focus:border-purple-500 input-glow transition-all duration-300 text-gray-800 bg-gradient-to-r from-purple-50/50 to-pink-50/50 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gradient-to-r file:from-purple-500 file:to-pink-500 file:text-white hover:file:from-pink-500 hover:file:to-purple-500 file:transition-all file:duration-300">
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="flex items-center space-x-2 bg-green-50/50 px-3 py-2 rounded-lg">
                                    <i class="fas fa-check text-green-500"></i>
                                    <span class="text-gray-600">Formats: JPG, PNG, GIF</span>
                                </div>
                                <div class="flex items-center space-x-2 bg-blue-50/50 px-3 py-2 rounded-lg">
                                    <i class="fas fa-info-circle text-blue-500"></i>
                                    <span class="text-gray-600">Optimal: 800x800px</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons avec effets premium -->
                        <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-6 pt-8 border-t-2 border-gray-100">
                            <button type="submit" 
                                    class="group flex-1 flex items-center justify-center space-x-3 bg-gradient-to-r from-primary to-cyan-400 hover:from-cyan-400 hover:to-blue-500 text-white font-bold py-4 px-8 rounded-xl shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 hover:scale-105">
                                <i class="fas fa-plus group-hover:rotate-90 transition-transform duration-300 text-lg"></i>
                                <span class="text-lg">Ajouter le plat</span>
                                <div class="w-2 h-2 bg-white/30 rounded-full animate-pulse"></div>
                            </button>
                            
                            <a href="gestion_plats.php" 
                               class="group flex-1 flex items-center justify-center space-x-3 bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-bold py-4 px-8 rounded-xl shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2">
                                <i class="fas fa-times group-hover:rotate-90 transition-transform duration-300 text-lg"></i>
                                <span class="text-lg">Annuler</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Sidebar avec design moderne -->
            <div class="space-y-6">
                <!-- Preview Card -->
                <div class="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl overflow-hidden border border-white/20 hover-lift">
                    <div class="bg-gradient-to-r from-accent to-orange-400 px-6 py-4">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-3">
                            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-eye text-white"></i>
                            </div>
                            <span>Aperçu en direct</span>
                        </h3>
                    </div>
                    
                    <div class="p-6">
                        <div class="border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center bg-gradient-to-br from-gray-50 to-white">
                            <div id="imagePreview" class="hidden">
                                <img id="previewImg" src="" alt="Aperçu" class="w-full h-40 object-cover rounded-xl mb-3 shadow-lg">
                            </div>
                            <div id="imagePlaceholder" class="text-gray-400">
                                <div class="w-16 h-16 bg-gradient-to-br from-gray-200 to-gray-300 rounded-2xl flex items-center justify-center mx-auto mb-3 floating-animation">
                                    <i class="fas fa-image text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-sm font-medium">Image du plat</p>
                            </div>
                        </div>
                        
                        <div class="mt-6 space-y-4">
                            <div class="bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl p-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-semibold text-gray-600 flex items-center">
                                        <i class="fas fa-utensils text-primary mr-2"></i>
                                        Nom:
                                    </span>
                                    <span id="previewNom" class="text-sm font-bold text-dark bg-white px-3 py-1 rounded-lg shadow-sm">-</span>
                                </div>
                            </div>

                            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-xl p-4">
                                <span class="text-sm font-semibold text-gray-600 flex items-center mb-2">
                                    <i class="fas fa-align-left text-emerald-500 mr-2"></i>
                                    Description:
                                </span>
                                <div class="bg-white rounded-lg p-3 shadow-sm">
                                    <span id="previewDescription" class="text-sm text-dark italic leading-relaxed">-</span>
                                </div>
                            </div>

                            <div class="bg-gradient-to-r from-yellow-50 to-amber-50 rounded-xl p-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-semibold text-gray-600 flex items-center">
                                        <i class="fas fa-money-bill-wave text-accent mr-2"></i>
                                        Prix:
                                    </span>
                                    <span id="previewPrix" class="text-lg font-bold text-accent bg-white px-4 py-1 rounded-lg shadow-sm">- CFA</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tips Card -->
                <div class="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl overflow-hidden border border-white/20 hover-lift">
                    <div class="bg-gradient-to-r from-secondary to-dark px-6 py-4">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-3">
                            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-lightbulb text-primary"></i>
                            </div>
                            <span>Conseils d'expert</span>
                        </h3>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        <div class="flex items-start space-x-4 p-4 bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl border-l-4 border-primary">
                            <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-primary to-cyan-400 rounded-xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-camera text-white text-sm"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-dark text-base mb-1">Photo professionnelle</h4>
                                <p class="text-xs text-gray-600 leading-relaxed">Une image de qualité augmente les commandes de 60%. Utilisez un éclairage naturel.</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4 p-4 bg-gradient-to-r from-yellow-50 to-amber-50 rounded-xl border-l-4 border-accent">
                            <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-accent to-orange-400 rounded-xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-money-bill text-white text-sm"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-dark text-base mb-1">Prix stratégique</h4>
                                <p class="text-xs text-gray-600 leading-relaxed">Analysez vos concurrents et positionnez-vous intelligemment sur le marché.</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4 p-4 bg-gradient-to-r from-orange-50 to-red-50 rounded-xl border-l-4 border-warning">
                            <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-warning to-red-500 rounded-xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-edit text-white text-sm"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-dark text-base mb-1">Description vendeuse</h4>
                                <p class="text-xs text-gray-600 leading-relaxed">Mentionnez les ingrédients, les saveurs et les bénéfices. Soyez précis et appétissant.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions Card -->
                <div class="bg-white/80 backdrop-blur-sm rounded-3xl shadow-2xl overflow-hidden border border-white/20 hover-lift">
                    <div class="bg-gradient-to-r from-warning to-red-500 px-6 py-4">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-3">
                            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-bolt text-white"></i>
                            </div>
                            <span>Actions rapides</span>
                        </h3>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        <a href="gestion_plats.php" 
                           class="group w-full flex items-center space-x-3 bg-gradient-to-r from-gray-100 to-gray-200 hover:from-secondary hover:to-dark text-dark hover:text-white font-bold py-3 px-5 rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-list group-hover:scale-110 transition-transform duration-300"></i>
                            <span>Voir tous les plats</span>
                        </a>
                        
                        <button type="button" onclick="document.getElementById('productForm').reset(); updatePreview();" 
                                class="group w-full flex items-center space-x-3 bg-gradient-to-r from-primary to-cyan-400 hover:from-cyan-400 hover:to-blue-500 text-white font-bold py-3 px-5 rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-eraser group-hover:rotate-12 transition-transform duration-300"></i>
                            <span>Réinitialiser</span>
                        </button>
                        
                        <a href="commandes.php" 
                           class="group w-full flex items-center space-x-3 bg-gradient-to-r from-accent to-orange-400 hover:from-orange-400 hover:to-red-400 text-white font-bold py-3 px-5 rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-shopping-cart group-hover:bounce transition-all duration-300"></i>
                            <span>Voir les commandes</span>
                        </a>
                    </div>
                </div>

                <!-- Stats Card -->
                <div class="bg-gradient-to-br from-purple-500 to-pink-500 rounded-3xl shadow-2xl overflow-hidden text-white hover-lift">
                    <div class="p-6">
                        <h3 class="text-lg font-bold mb-4 flex items-center space-x-2">
                            <i class="fas fa-chart-pie"></i>
                            <span>Statistiques</span>
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm opacity-90">Plats populaires</span>
                                <div class="flex space-x-1">
                                    <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                                    <div class="w-2 h-2 bg-white/70 rounded-full animate-pulse" style="animation-delay: 0.2s;"></div>
                                    <div class="w-2 h-2 bg-white/50 rounded-full animate-pulse" style="animation-delay: 0.4s;"></div>
                                </div>
                            </div>
                            <div class="text-xs opacity-75">Créez des plats qui marquent les esprits !</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts avec fonctionnalités améliorées -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Éléments du DOM
            const nomInput = document.getElementById('nom');
            const prixInput = document.getElementById('prix');
            const imageInput = document.getElementById('image');
            const descriptionInput = document.getElementById('description');
            const previewNom = document.getElementById('previewNom');
            const previewPrix = document.getElementById('previewPrix');
            const previewDescription = document.getElementById('previewDescription');
            const previewImg = document.getElementById('previewImg');
            const imagePreview = document.getElementById('imagePreview');
            const imagePlaceholder = document.getElementById('imagePlaceholder');
            const form = document.getElementById('productForm');

            // Animation d'entrée pour les éléments
            const animateElements = document.querySelectorAll('.hover-lift, .card-hover');
            animateElements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    el.style.transition = 'all 0.6s cubic-bezier(0.25, 0.8, 0.25, 1)';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 100 * index);
            });

            // Fonction de mise à jour de l'aperçu avec animations
            function updatePreview() {
                const nom = nomInput?.value || '-';
                const prix = prixInput?.value || '0';
                const description = descriptionInput?.value || '-';

                // Animation pour les changements
                if (previewNom) {
                    previewNom.style.transform = 'scale(0.9)';
                    setTimeout(() => {
                        previewNom.textContent = nom;
                        previewNom.style.transform = 'scale(1)';
                    }, 150);
                }
                
                if (previewPrix) {
                    previewPrix.style.transform = 'scale(0.9)';
                    setTimeout(() => {
                        previewPrix.textContent = prix ? `${prix} CFA` : '- CFA';
                        previewPrix.style.transform = 'scale(1)';
                    }, 150);
                }
                
                if (previewDescription) {
                    previewDescription.style.opacity = '0.5';
                    setTimeout(() => {
                        previewDescription.textContent = description;
                        previewDescription.style.opacity = '1';
                    }, 150);
                }
            }

            // Image preview avec animation
            if (imageInput) {
                imageInput.addEventListener('change', function (e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            if (previewImg) {
                                previewImg.src = e.target.result;
                                imagePreview?.classList.remove('hidden');
                                imagePlaceholder?.classList.add('hidden');
                                
                                // Animation d'apparition
                                previewImg.style.opacity = '0';
                                previewImg.style.transform = 'scale(0.8)';
                                setTimeout(() => {
                                    previewImg.style.transition = 'all 0.3s ease';
                                    previewImg.style.opacity = '1';
                                    previewImg.style.transform = 'scale(1)';
                                }, 50);
                            }
                        };
                        reader.readAsDataURL(file);
                    } else {
                        imagePreview?.classList.add('hidden');
                        imagePlaceholder?.classList.remove('hidden');
                    }
                });
            }

            // Événements de mise à jour en temps réel avec debounce
            let updateTimeout;
            function debouncedUpdate() {
                clearTimeout(updateTimeout);
                updateTimeout = setTimeout(updatePreview, 300);
            }

            nomInput?.addEventListener('input', debouncedUpdate);
            prixInput?.addEventListener('input', debouncedUpdate);
            descriptionInput?.addEventListener('input', debouncedUpdate);

            // Gestion du formulaire avec feedback visuel
            if (form) {
                form.addEventListener('submit', function (e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;

                    // Animation du bouton de soumission
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i><span>Création en cours...</span>';
                    submitBtn.disabled = true;
                    submitBtn.classList.add('animate-pulse');

                    // Restauration si le formulaire est invalide
                    setTimeout(() => {
                        if (!this.checkValidity()) {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('animate-pulse');
                        }
                    }, 100);
                });

                // Validation en temps réel avec indicateurs visuels
                const requiredInputs = form.querySelectorAll('[required]');
                requiredInputs.forEach(input => {
                    input.addEventListener('blur', function() {
                        if (this.value.trim() === '') {
                            this.classList.add('border-red-400', 'bg-red-50');
                            this.classList.remove('border-green-400', 'bg-green-50');
                        } else {
                            this.classList.add('border-green-400', 'bg-green-50');
                            this.classList.remove('border-red-400', 'bg-red-50');
                        }
                    });
                });
            }

            // Auto-hide des messages après 5 secondes
            const alerts = document.querySelectorAll('[class*="animate-slideDown"]');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transform = 'translateX(100%)';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });

            // Initialiser l'aperçu
            updatePreview();

            // Easter egg - confettis lors de la soumission réussie
            if (window.location.search.includes('success=1')) {
                createConfetti();
            }
        });

        // Fonction pour créer des confettis
        function createConfetti() {
            const colors = ['#55D5E0', '#F6B12D', '#F26619', '#335F8A'];
            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.style.position = 'fixed';
                    confetti.style.left = Math.random() * 100 + 'vw';
                    confetti.style.top = '-10px';
                    confetti.style.width = '10px';
                    confetti.style.height = '10px';
                    confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.borderRadius = '50%';
                    confetti.style.pointerEvents = 'none';
                    confetti.style.zIndex = '9999';
                    confetti.style.animation = `fall ${Math.random() * 3 + 2}s linear forwards`;
                    
                    document.body.appendChild(confetti);
                    
                    setTimeout(() => confetti.remove(), 5000);
                }, i * 50);
            }
        }

        // Animation CSS pour les confettis
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>