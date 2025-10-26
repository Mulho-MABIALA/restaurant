<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

requireAccess($conn, $_SESSION['admin_id'], 'gestion_about');

$message = '';
$messageType = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $titre = $_POST['titre'];
        $description = $_POST['description'];
        $sous_titre = $_POST['sous_titre'] ?? '';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 1;

        // Gestion de l'upload d'image
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../public/uploads/';
            $fileName = time() . '_' . basename($_FILES['image']['name']);
            $targetPath = $uploadDir . $fileName;

            // Vérifier le type de fichier
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $fileType = $_FILES['image']['type'];

            if (in_array($fileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $imagePath = $fileName;

                    // Supprimer l'ancienne image si elle existe
                    $stmtOld = $conn->prepare("SELECT image FROM about_section WHERE id = ?");
                    $stmtOld->execute([$id]);
                    $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);
                    if ($oldData && $oldData['image'] && file_exists($uploadDir . $oldData['image'])) {
                        unlink($uploadDir . $oldData['image']);
                    }
                }
            } else {
                throw new Exception("Type de fichier non autorisé");
            }
        }

        // Vérifier si l'enregistrement existe
        $stmtCheck = $conn->prepare("SELECT id FROM about_section WHERE id = ?");
        $stmtCheck->execute([$id]);
        $exists = $stmtCheck->fetch();

        if ($exists) {
            // Mise à jour
            if ($imagePath) {
                $stmt = $conn->prepare("UPDATE about_section SET titre = ?, description = ?, sous_titre = ?, image = ? WHERE id = ?");
                $stmt->execute([$titre, $description, $sous_titre, $imagePath, $id]);
            } else {
                $stmt = $conn->prepare("UPDATE about_section SET titre = ?, description = ?, sous_titre = ? WHERE id = ?");
                $stmt->execute([$titre, $description, $sous_titre, $id]);
            }
            $message = "Section À propos mise à jour avec succès!";
        } else {
            // Insertion
            $stmt = $conn->prepare("INSERT INTO about_section (id, titre, description, sous_titre, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id, $titre, $description, $sous_titre, $imagePath]);
            $message = "Section À propos créée avec succès!";
        }

        $messageType = 'success';
    } catch (Exception $e) {
        $message = "Erreur: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Récupérer les données actuelles
$stmt = $conn->prepare("SELECT * FROM about_section WHERE id = 1 LIMIT 1");
$stmt->execute();
$aboutData = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les statistiques automatiques
$stmtPlats = $conn->query("SELECT COUNT(*) as total FROM plats WHERE disponible = 1");
$totalPlats = $stmtPlats->fetch(PDO::FETCH_ASSOC)['total'];

$stmtReservations = $conn->query("SELECT COUNT(*) as total FROM reservations");
$totalReservations = $stmtReservations->fetch(PDO::FETCH_ASSOC)['total'];

// Calculer les années d'existence (depuis 2020 par exemple)
$anneeCreation = 2020;
$anneesExistence = date('Y') - $anneeCreation;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Section À Propos - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gray-50">

    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>

        <div class="flex-1 overflow-y-auto">
            <div class="p-8">
                <div class="max-w-4xl mx-auto">
                    <!-- Header -->
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-gray-900">Gestion Section À Propos</h1>
                        <p class="text-gray-600 mt-2">Gérez les informations de la section À propos affichée sur la page d'accueil</p>
                    </div>

                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Statistiques automatiques -->
                    <div class="grid grid-cols-3 gap-6 mb-8">
                        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-blue-100 text-sm font-medium">Années d'existence</p>
                                    <h3 class="text-4xl font-bold mt-2"><?= $anneesExistence ?>+</h3>
                                </div>
                                <div class="bg-white/20 p-4 rounded-xl">
                                    <i class="fas fa-calendar-alt text-3xl"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-6 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-green-100 text-sm font-medium">Plats disponibles</p>
                                    <h3 class="text-4xl font-bold mt-2"><?= $totalPlats ?>+</h3>
                                </div>
                                <div class="bg-white/20 p-4 rounded-xl">
                                    <i class="fas fa-utensils text-3xl"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 text-white">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-purple-100 text-sm font-medium">Réservations</p>
                                    <h3 class="text-4xl font-bold mt-2"><?= $totalReservations ?>+</h3>
                                </div>
                                <div class="bg-white/20 p-4 rounded-xl">
                                    <i class="fas fa-calendar-check text-3xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulaire -->
                    <div class="bg-white rounded-2xl shadow-lg p-8">
                        <form method="POST" enctype="multipart/form-data" class="space-y-6">
                            <input type="hidden" name="id" value="1">

                            <!-- Titre -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-heading mr-2 text-blue-500"></i>
                                    Titre principal
                                </label>
                                <input type="text" name="titre" required
                                       value="<?= htmlspecialchars($aboutData['titre'] ?? 'À propos de Mulho') ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>

                            <!-- Sous-titre -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-text-height mr-2 text-green-500"></i>
                                    Sous-titre
                                </label>
                                <input type="text" name="sous_titre"
                                       value="<?= htmlspecialchars($aboutData['sous_titre'] ?? '') ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-align-left mr-2 text-purple-500"></i>
                                    Description
                                </label>
                                <textarea name="description" rows="6" required
                                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?= htmlspecialchars($aboutData['description'] ?? '') ?></textarea>
                            </div>

                            <!-- Image -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-image mr-2 text-orange-500"></i>
                                    Image (optionnel)
                                </label>

                                <?php if (!empty($aboutData['image'])): ?>
                                    <div class="mb-4">
                                        <img src="../public/uploads/<?= htmlspecialchars($aboutData['image']) ?>"
                                             alt="Image actuelle"
                                             class="w-64 h-40 object-cover rounded-xl border-2 border-gray-200">
                                        <p class="text-sm text-gray-500 mt-2">Image actuelle</p>
                                    </div>
                                <?php endif; ?>

                                <input type="file" name="image" accept="image/*"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                <p class="text-sm text-gray-500 mt-2">Formats acceptés: JPG, PNG, WEBP (max 2MB)</p>
                            </div>

                            <!-- Bouton -->
                            <div class="flex justify-end">
                                <button type="submit"
                                        class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold px-8 py-3 rounded-xl transition-all duration-300 shadow-lg hover:shadow-xl">
                                    <i class="fas fa-save mr-2"></i>
                                    Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Informations -->
                    <div class="mt-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-semibold text-blue-800">Statistiques automatiques</h3>
                                <p class="text-sm text-blue-700 mt-1">
                                    Les statistiques (années, plats, réservations) sont calculées automatiquement et s'affichent sur la page d'accueil.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
