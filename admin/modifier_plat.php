<?php
require_once '../config.php';

try {
    $conn = new PDO('mysql:host=localhost;dbname=restaurant', 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!isset($_GET['id'])) {
        die('ID plat manquant.');
    }

    $id = intval($_GET['id']);

    // Récupérer les données actuelles du plat
    $stmt = $conn->prepare("SELECT description, prix, categorie_id, image FROM plats WHERE id = ?");
    $stmt->execute([$id]);
    $plat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plat) {
        die("Plat introuvable.");
    }

    // Récupérer les catégories disponibles
    $categories = $conn->query("SELECT id, nom FROM categories ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $description = $_POST['description'] ?? '';
        $prix = $_POST['prix'] ?? 0;
        $categorie_id = $_POST['categorie'] ?? '';
        $imageName = $plat['image'] ?? ''; // Garder l'ancienne image par défaut

        // Gestion de l'upload d'image
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['image']['tmp_name'];
            $fileName = $_FILES['image']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $uploadFileDir = './uploads/';

                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }

                $destPath = $uploadFileDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    // Supprimer l'ancienne image si elle existe
                    if (!empty($plat['image']) && file_exists($uploadFileDir . $plat['image'])) {
                        unlink($uploadFileDir . $plat['image']);
                    }
                    $imageName = $newFileName;
                } else {
                    echo "Erreur lors du déplacement de l'image.";
                }
            } else {
                echo "Format d'image non supporté.";
            }
        }

        // Mise à jour du plat
        $stmt = $conn->prepare("UPDATE plats SET description = ?, prix = ?, categorie_id = ?, image = ? WHERE id = ?");
        $stmt->execute([$description, $prix, $categorie_id, $imageName, $id]);

        header('Location: gestion_plats.php');
        exit;
    }
} catch (PDOException $e) {
    die("Erreur de connexion ou de requête : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier un plat</title>
</head>
<body>
    <h1>Modifier un plat</h1>

    <form method="post" enctype="multipart/form-data">
        <label>Description :</label><br>
        <input type="text" name="description" value="<?= htmlspecialchars($plat['description'] ?? '') ?>" required><br><br>

        <label>Prix (FCFA) :</label><br>
        <input type="number" step="0.01" name="prix" value="<?= htmlspecialchars($plat['prix'] ?? '') ?>" required><br><br>

        <label>Catégorie :</label><br>
        <select name="categorie" required>
            <option value="">-- Sélectionnez --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $plat['categorie_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Image actuelle :</label><br>
        <?php if (!empty($plat['image'])): ?>
            <img src="uploads/<?= htmlspecialchars($plat['image']) ?>" style="width:100px;"><br>
        <?php else: ?>
            <p>Pas d'image</p>
        <?php endif; ?>

        <label>Changer l'image :</label><br>
        <input type="file" name="image" accept="image/*"><br><br>

        <button type="submit">Enregistrer les modifications</button>
    </form>
</body>
</html>
