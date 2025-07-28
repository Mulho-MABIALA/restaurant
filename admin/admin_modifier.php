<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
require_once '../config.php';

$id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->execute([$id]);
$admin = $stmt->fetch();

if (!$admin) {
    echo "Admin introuvable.";
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? 'admin';

    if ($username && $email && in_array($role, ['admin', 'superadmin'])) {
        if ($password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin SET username = ?, email = ?, password = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $email, $hashed, $role, $id]);
        } else {
            $stmt = $conn->prepare("UPDATE admin SET username = ?, email = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $id]);
        }
        header('Location: admin_gestion.php');
        exit;
    } else {
        $message = "Champs obligatoires manquants ou rôle invalide.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Modifier un admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
  <div class="max-w-md mx-auto bg-white p-6 rounded-xl shadow">
    <h1 class="text-xl font-bold mb-4">✏️ Modifier l’admin</h1>

    <?php if ($message): ?>
      <p class="text-red-600 mb-2"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <input type="text" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required class="w-full border p-2 rounded" />
      <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required class="w-full border p-2 rounded" />
      
      <input type="password" name="password" placeholder="Laisser vide pour ne pas changer" class="w-full border p-2 rounded" />

      <select name="role" class="w-full border p-2 rounded" required>
        <option value="admin" <?= $admin['role'] === 'admin' ? 'selected' : '' ?>>Admin simple</option>
        <option value="superadmin" <?= $admin['role'] === 'superadmin' ? 'selected' : '' ?>>Super admin</option>
      </select>

      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Mettre à jour</button>
    </form>

    <a href="admin_gestion.php" class="inline-block mt-4 text-blue-600 underline">⬅ Retour</a>
  </div>
</body>
</html>
