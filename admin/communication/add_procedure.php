<?php
session_start();
require_once '../../config.php';

// Vérification de l'authentification
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Messages
$message = '';
$message_type = '';

// SUPPRESSION
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Supprimer le fichier si existe
    $stmt = $conn->prepare("SELECT fichier_url FROM procedures WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetchColumn();
    if ($file && file_exists('../../' . $file)) {
        unlink('../../' . $file);
    }

    // Supprimer en BDD
    $stmt = $conn->prepare("DELETE FROM procedures WHERE id = ?");
    $stmt->execute([$id]);

    $message = "Procédure supprimée avec succès.";
    $message_type = 'success';
}

// MODIFICATION
$edit_proc = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM procedures WHERE id = ?");
    $stmt->execute([$id]);
    $edit_proc = $stmt->fetch(PDO::FETCH_ASSOC);
}

// TRAITEMENT DU FORMULAIRE (ajout ou modification)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre']);
    $categorie = trim($_POST['categorie']);
    $contenu = trim($_POST['contenu']);
    $fichier_url = $edit_proc['fichier_url'] ?? null;

    // Gestion fichier
    if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $upload_dir = '../../uploads/procedures/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $file_ext = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, $allowed_ext)) {
            $new_name = uniqid() . '.' . $file_ext;
            $file_path = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $file_path)) {
                // Supprimer ancien fichier si modification
                if ($edit_proc && $edit_proc['fichier_url'] && file_exists('../../' . $edit_proc['fichier_url'])) {
                    unlink('../../' . $edit_proc['fichier_url']);
                }
                $fichier_url = 'uploads/procedures/' . $new_name;
            }
        }
    }

    if ($edit_proc) {
        // Mise à jour
        $stmt = $conn->prepare("UPDATE procedures SET titre = ?, categorie = ?, contenu = ?, fichier_url = ? WHERE id = ?");
        $stmt->execute([$titre, $categorie, $contenu, $fichier_url, $edit_proc['id']]);
        $message = "Procédure mise à jour avec succès.";
    } else {
        // Ajout
        $stmt = $conn->prepare("INSERT INTO procedures (titre, categorie, contenu, fichier_url, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$titre, $categorie, $contenu, $fichier_url]);
        $message = "Procédure ajoutée avec succès.";
    }
    $message_type = 'success';
    header("Location: manage_procedures.php");
    exit;
}

// Récupérer toutes les procédures
$procedures = $conn->query("SELECT * FROM procedures ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des procédures</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
<div class="container mx-auto py-8">

    <h1 class="text-3xl font-bold text-blue-600 mb-6">📚 Gestion des procédures</h1>

    <?php if ($message): ?>
        <div class="p-3 mb-4 rounded <?= $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Formulaire ajout/modification -->
    <div class="bg-white shadow rounded p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4"><?= $edit_proc ? '✏️ Modifier une procédure' : '➕ Ajouter une procédure' ?></h2>
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block font-semibold mb-1">Titre *</label>
                <input type="text" name="titre" required class="w-full border rounded px-3 py-2" value="<?= $edit_proc['titre'] ?? '' ?>">
            </div>
            <div>
                <label class="block font-semibold mb-1">Catégorie *</label>
                <input type="text" name="categorie" required class="w-full border rounded px-3 py-2" value="<?= $edit_proc['categorie'] ?? '' ?>">
            </div>
            <div>
                <label class="block font-semibold mb-1">Contenu</label>
                <textarea name="contenu" rows="4" class="w-full border rounded px-3 py-2"><?= $edit_proc['contenu'] ?? '' ?></textarea>
            </div>
            <div>
                <label class="block font-semibold mb-1">Fichier (optionnel)</label>
                <input type="file" name="fichier" class="w-full border rounded px-3 py-2">
                <?php if ($edit_proc && $edit_proc['fichier_url']): ?>
                    <p class="text-sm text-gray-500 mt-1">Fichier actuel : <a href="../../<?= $edit_proc['fichier_url'] ?>" target="_blank" class="text-blue-600 underline">Voir</a></p>
                <?php endif; ?>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= $edit_proc ? 'Mettre à jour' : 'Enregistrer' ?></button>
            </div>
        </form>
    </div>

    <!-- Liste des procédures -->
    <div class="bg-white shadow rounded p-6">
        <h2 class="text-xl font-semibold mb-4">🗂️ Liste des procédures</h2>
        <?php if (empty($procedures)): ?>
            <p class="text-gray-500">Aucune procédure disponible.</p>
        <?php else: ?>
            <table class="min-w-full table-auto border-collapse border border-gray-200">
                <thead>
                    <tr class="bg-blue-600 text-white">
                        <th class="px-4 py-2">Titre</th>
                        <th class="px-4 py-2">Catégorie</th>
                        <th class="px-4 py-2">Fichier</th>
                        <th class="px-4 py-2">Ajouté le</th>
                        <th class="px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($procedures as $proc): ?>
                        <tr class="border-t border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2"><?= htmlspecialchars($proc['titre']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($proc['categorie']) ?></td>
                            <td class="px-4 py-2">
                                <?php if ($proc['fichier_url']): ?>
                                    <a href="../../<?= $proc['fichier_url'] ?>" target="_blank" class="text-blue-600 underline">Voir</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2"><?= date('d/m/Y', strtotime($proc['created_at'])) ?></td>
                            <td class="px-4 py-2 space-x-2">
                                <a href="?edit=<?= $proc['id'] ?>" class="text-yellow-600 hover:underline">✏️ Modifier</a>
                                <a href="?delete=<?= $proc['id'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Supprimer cette procédure ?')">🗑️ Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
