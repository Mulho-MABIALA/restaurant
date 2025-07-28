<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
require_once '../config.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'admin';

    if ($username && $email && $password && in_array($role, ['admin', 'superadmin'])) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashed, $role]);
        header('Location: admin_gestion.php');
        exit;
    } else {
        $message = 'Veuillez remplir tous les champs.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Ajouter un admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
  <div class="max-w-md mx-auto bg-white p-6 rounded-xl shadow">
    <h1 class="text-xl font-bold mb-4">➕ Ajouter un admin</h1>

    <?php if ($message): ?>
      <p class="text-red-600 mb-2"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <input type="text" name="username" placeholder="Nom d'utilisateur" required class="w-full border p-2 rounded">
      <input type="email" name="email" placeholder="Email" required class="w-full border p-2 rounded">
      <input type="password" name="password" placeholder="Mot de passe" required class="w-full border p-2 rounded">

      <!-- 🟡 Champ rôle -->
      <select name="role" class="w-full border p-2 rounded" required>
        <option value="admin">Admin simple</option>
        <option value="superadmin">Super admin</option>
      </select>

      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Enregistrer</button>
    </form>

    <a href="admin_gestion.php" class="inline-block mt-4 text-blue-600 underline">⬅ Retour</a>
  </div>
</body>
</html>
