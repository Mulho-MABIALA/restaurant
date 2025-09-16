<?php
// admin_newsletter.php
require_once '../config.php';
session_start();
// Rediriger si l'admin n'est pas connecté
    if (! isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }


// Récupérer les inscrits à la newsletter
$stmt = $conn->query("SELECT * FROM newsletter ORDER BY date_inscription DESC");
$subscribers = $stmt->fetchAll();

// Exporter les emails
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=newsletter_emails.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Email', 'Date d\'inscription'));
    
    foreach ($subscribers as $subscriber) {
        fputcsv($output, array($subscriber['email'], $subscriber['date_inscription']));
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Newsletter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Liste des inscrits à la newsletter</h1>
        
        <div class="mb-4 flex justify-between items-center">
            <p class="text-gray-600">Total: <?php echo count($subscribers); ?> inscrits</p>
            <a href="?export=csv" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-download mr-2"></i>Exporter en CSV
            </a>
        </div>
        
        <div class="bg-white shadow-md rounded my-6">
            <table class="min-w-full table-auto">
                <thead>
                    <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                        <th class="py-3 px-6 text-left">Email</th>
                        <th class="py-3 px-6 text-left">Date d'inscription</th>
                        <th class="py-3 px-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 text-sm font-light">
                    <?php if (count($subscribers) > 0): ?>
                        <?php foreach ($subscribers as $subscriber): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-100">
                            <td class="py-3 px-6 text-left">
                                <div class="flex items-center">
                                    <span class="font-medium"><?= htmlspecialchars($subscriber['email']) ?></span>
                                </div>
                            </td>
                            <td class="py-3 px-6 text-left">
                                <?= date('d/m/Y H:i', strtotime($subscriber['date_inscription'])) ?>
                            </td>
                            <td class="py-3 px-6 text-center">
                                <div class="flex item-center justify-center">
                                    <a href="mailto:<?= htmlspecialchars($subscriber['email']) ?>" class="w-4 mr-2 transform hover:text-purple-500 hover:scale-110">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                    <a href="#" onclick="confirmDelete(<?= $subscriber['id'] ?>)" class="w-4 mr-2 transform hover:text-red-500 hover:scale-110">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="py-3 px-6 text-center">Aucun inscrit à la newsletter pour le moment.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function confirmDelete(id) {
        if (confirm('Êtes-vous sûr de vouloir supprimer cet email de la newsletter ?')) {
            window.location.href = 'admin_newsletter_delete.php?id=' + id;
        }
    }
    </script>
</body>
</html>