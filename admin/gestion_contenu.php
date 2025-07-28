<?php
require_once '../config.php'; // Connexion PDO
session_start();

$id_page = 1;
$stmt = $conn->prepare("SELECT * FROM pages WHERE id = ?");
$stmt->execute([$id_page]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    $page = [
        'id' => $id_page,
        'titre' => '',
        'contenu' => '',
        'image' => null
    ];
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Gestion du Contenu</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Scrollbar custom */
    ::-webkit-scrollbar {
      width: 8px;
    }
    ::-webkit-scrollbar-thumb {
      background-color: #22c55e;
      border-radius: 10px;
    }
  </style>
</head>
<body class="flex min-h-screen bg-gray-100 font-sans text-gray-800">

      <?php include 'sidebar.php'; ?>

  <!-- Main Content Wrapper -->
  <div class="flex-1 flex flex-col md:ml-64">

    <!-- Mobile Header -->
    <header class="flex items-center justify-between bg-green-700 text-white p-4 md:hidden">
      <button id="btnSidebarToggle" aria-label="Toggle sidebar" class="focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" 
             xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" 
             d="M4 6h16M4 12h16M4 18h16"></path></svg>
      </button>
      <h1 class="text-xl font-bold">Gestion du Contenu</h1>
      <div></div>
    </header>

    <!-- Main -->
    <main class="p-8 bg-gray-50 min-h-screen">

      <h1 class="text-3xl font-semibold mb-8">Gestion du Contenu</h1>

      <form action="update_page.php" method="POST" enctype="multipart/form-data" class="bg-white shadow rounded-lg p-6 max-w-3xl mx-auto">

        <input type="hidden" name="id" value="<?= htmlspecialchars($page['id']) ?>">

        <div class="mb-6">
          <label for="titre" class="block text-gray-700 font-medium mb-2">Titre</label>
          <input id="titre" type="text" name="titre" value="<?= htmlspecialchars($page['titre']) ?>" 
                 class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500" required>
        </div>

        <div class="mb-6">
          <label for="contenu" class="block text-gray-700 font-medium mb-2">Contenu</label>
          <textarea id="contenu" name="contenu" rows="8" 
                    class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-green-500" 
                    required><?= htmlspecialchars($page['contenu']) ?></textarea>
        </div>

        <div class="mb-6">
          <label class="block text-gray-700 font-medium mb-2">Image Actuelle</label>
          <?php if (!empty($page['image'])): ?>
            <img src="../uploads/<?= htmlspecialchars($page['image']) ?>" alt="Image" class="w-64 rounded mb-4 shadow">
          <?php else: ?>
            <p class="text-gray-500 italic mb-4">Aucune image</p>
          <?php endif; ?>
          <input type="file" name="image" accept="image/*" 
                 class="block w-full text-gray-700 border border-gray-300 rounded cursor-pointer focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>

        <button type="submit" 
                class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3 rounded transition">Enregistrer</button>
      </form>

    </main>

  </div>

  <script>
    const sidebar = document.getElementById('sidebar');
    const btnToggle = document.getElementById('btnSidebarToggle');

    btnToggle.addEventListener('click', () => {
      sidebar.classList.toggle('-translate-x-full');
    });
  </script>

</body>
</html>
