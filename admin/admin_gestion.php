<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
require_once '../config.php';

$stmt = $conn->query("SELECT * FROM admin ORDER BY id DESC");
$admins = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Gestion des administrateurs</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
  <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
<body class="bg-gray-100 p-8">
  
  <div class="max-w-4xl mx-auto bg-white p-6 rounded-xl shadow">
    
    <h1 class="text-2xl font-bold mb-6">👤 Gestion des administrateurs</h1>

    <a href="admin_ajouter.php" class="mb-4 inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">➕ Ajouter un admin</a>

    <table class="w-full border mt-4">
      <thead>
        <tr class="bg-gray-100 text-left">
          <th class="p-2 border">ID</th>
          <th class="p-2 border">Nom d’utilisateur</th>
          <th class="p-2 border">Email</th>
          <th class="p-2 border">Actions</th>
          <th class="p-2 border">Rôle</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($admins as $admin): ?>
          <tr>
            <td class="p-2 border"><?= $admin['id'] ?></td>
            <td class="p-2 border"><?= htmlspecialchars($admin['username']) ?></td>
            <td class="p-2 border"><?= htmlspecialchars($admin['email']) ?></td>
            <td class="p-2 border"><?= htmlspecialchars($admin['role']) ?></td>

            <td class="p-2 border space-x-2">
              <a href="admin_modifier.php?id=<?= $admin['id'] ?>" class="text-blue-600 hover:underline">Modifier</a>
              <?php if ($_SESSION['admin_username'] !== $admin['username']): ?>
                <a href="admin_supprimer.php?id=<?= $admin['id'] ?>" onclick="return confirm('Supprimer cet admin ?')" class="text-red-600 hover:underline">Supprimer</a>
              <?php else: ?>
                <span class="text-gray-400">[Vous]</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <a href="dashboard.php" class="inline-block mt-6 text-blue-600 underline">⬅ Retour au dashboard</a>
  </div>
</body>
</html>
