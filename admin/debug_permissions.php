<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';

// Vérifier la connexion
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("❌ Vous n'êtes pas connecté. <a href='login.php'>Se connecter</a>");
}

$adminId = $_SESSION['admin_id'] ?? null;

if (!$adminId) {
    die("❌ Session admin_id non trouvée");
}

// Récupérer les infos admin
$stmt = $conn->prepare("SELECT id, username, email, role, created_at FROM admin WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérifier les pages
$stmt = $conn->query("SELECT * FROM admin_pages ORDER BY page_name");
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vérifier les permissions
$stmt = $conn->prepare("SELECT * FROM admin_permissions WHERE admin_id = ?");
$stmt->execute([$adminId]);
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Permissions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">
                <i class="fas fa-bug text-red-500 mr-2"></i>
                Diagnostic des Permissions
            </h1>

            <!-- Infos Admin -->
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                <h2 class="text-xl font-bold text-blue-900 mb-3">
                    <i class="fas fa-user-shield mr-2"></i>Informations Admin
                </h2>
                <?php if ($admin): ?>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <strong>ID:</strong> <?= $admin['id'] ?>
                        </div>
                        <div>
                            <strong>Username:</strong> <?= htmlspecialchars($admin['username']) ?>
                        </div>
                        <div>
                            <strong>Email:</strong> <?= htmlspecialchars($admin['email']) ?>
                        </div>
                        <div>
                            <strong>Rôle:</strong>
                            <span class="px-3 py-1 rounded-full text-sm font-bold <?= $admin['role'] === 'superadmin' ? 'bg-purple-200 text-purple-800' : 'bg-blue-200 text-blue-800' ?>">
                                <?= htmlspecialchars($admin['role']) ?>
                            </span>
                        </div>
                        <div>
                            <strong>Créé le:</strong> <?= $admin['created_at'] ?>
                        </div>
                    </div>

                    <?php if ($admin['role'] === 'superadmin'): ?>
                        <div class="mt-4 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
                            <i class="fas fa-check-circle mr-2"></i>
                            <strong>SUPER ADMIN:</strong> Vous devriez avoir accès à TOUTES les pages automatiquement.
                        </div>
                    <?php else: ?>
                        <div class="mt-4 bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>ADMIN:</strong> Vos accès dépendent de la table admin_permissions.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-red-600">❌ Admin non trouvé dans la base de données</p>
                <?php endif; ?>
            </div>

            <!-- Test d'accès à gestion_plats -->
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
                <h2 class="text-xl font-bold text-green-900 mb-3">
                    <i class="fas fa-utensils mr-2"></i>Test: Accès à gestion_plats
                </h2>
                <?php
                $canAccessPlats = canAccess($conn, $adminId, 'gestion_plats');
                ?>
                <div class="text-lg">
                    <strong>Résultat:</strong>
                    <?php if ($canAccessPlats): ?>
                        <span class="text-green-600 font-bold">✅ ACCÈS AUTORISÉ</span>
                    <?php else: ?>
                        <span class="text-red-600 font-bold">❌ ACCÈS REFUSÉ</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pages disponibles -->
            <div class="bg-purple-50 border-l-4 border-purple-500 p-4 mb-6">
                <h2 class="text-xl font-bold text-purple-900 mb-3">
                    <i class="fas fa-list mr-2"></i>Pages dans admin_pages
                </h2>
                <?php if (count($pages) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border">
                            <thead class="bg-purple-100">
                                <tr>
                                    <th class="px-4 py-2 border">ID</th>
                                    <th class="px-4 py-2 border">Nom</th>
                                    <th class="px-4 py-2 border">Slug</th>
                                    <th class="px-4 py-2 border">Actif</th>
                                    <th class="px-4 py-2 border">Accès?</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pages as $page): ?>
                                    <?php $hasAccess = canAccess($conn, $adminId, $page['page_slug']); ?>
                                    <tr class="<?= $hasAccess ? 'bg-green-50' : 'bg-red-50' ?>">
                                        <td class="px-4 py-2 border"><?= $page['id'] ?></td>
                                        <td class="px-4 py-2 border font-semibold"><?= htmlspecialchars($page['page_name']) ?></td>
                                        <td class="px-4 py-2 border text-sm font-mono"><?= htmlspecialchars($page['page_slug']) ?></td>
                                        <td class="px-4 py-2 border text-center">
                                            <?= $page['is_active'] ? '✅' : '❌' ?>
                                        </td>
                                        <td class="px-4 py-2 border text-center font-bold">
                                            <?= $hasAccess ? '<span class="text-green-600">✅ OUI</span>' : '<span class="text-red-600">❌ NON</span>' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-red-600">❌ Aucune page trouvée dans admin_pages</p>
                    <div class="mt-4 bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded">
                        <strong>Action requise:</strong> La table admin_pages est vide. Vous devez l'initialiser.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Permissions spécifiques -->
            <div class="bg-orange-50 border-l-4 border-orange-500 p-4 mb-6">
                <h2 class="text-xl font-bold text-orange-900 mb-3">
                    <i class="fas fa-key mr-2"></i>Permissions dans admin_permissions
                </h2>
                <?php if (count($permissions) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white border">
                            <thead class="bg-orange-100">
                                <tr>
                                    <th class="px-4 py-2 border">Page Slug</th>
                                    <th class="px-4 py-2 border">Voir</th>
                                    <th class="px-4 py-2 border">Créer</th>
                                    <th class="px-4 py-2 border">Modifier</th>
                                    <th class="px-4 py-2 border">Supprimer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($permissions as $perm): ?>
                                    <tr>
                                        <td class="px-4 py-2 border font-mono text-sm"><?= htmlspecialchars($perm['page_slug']) ?></td>
                                        <td class="px-4 py-2 border text-center"><?= $perm['can_view'] ? '✅' : '❌' ?></td>
                                        <td class="px-4 py-2 border text-center"><?= $perm['can_create'] ? '✅' : '❌' ?></td>
                                        <td class="px-4 py-2 border text-center"><?= $perm['can_edit'] ? '✅' : '❌' ?></td>
                                        <td class="px-4 py-2 border text-center"><?= $perm['can_delete'] ? '✅' : '❌' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <?php if ($admin['role'] === 'superadmin'): ?>
                        <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Normal pour un SUPER ADMIN:</strong> Pas besoin d'entrées dans admin_permissions.
                        </div>
                    <?php else: ?>
                        <p class="text-red-600">❌ Aucune permission trouvée pour cet admin</p>
                        <div class="mt-4 bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded">
                            <strong>Action requise:</strong> Vous devez ajouter des permissions pour cet admin.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Actions recommandées -->
            <div class="bg-red-50 border-l-4 border-red-500 p-4">
                <h2 class="text-xl font-bold text-red-900 mb-3">
                    <i class="fas fa-tools mr-2"></i>Actions Recommandées
                </h2>
                <ul class="list-disc list-inside space-y-2">
                    <?php if (count($pages) === 0): ?>
                        <li class="text-red-700">
                            <strong>Initialiser admin_pages:</strong> Créer les entrées pour toutes les pages (gestion_plats, reservations, etc.)
                        </li>
                    <?php endif; ?>

                    <?php if ($admin['role'] !== 'superadmin' && count($permissions) === 0): ?>
                        <li class="text-red-700">
                            <strong>Ajouter des permissions:</strong> Créer des entrées dans admin_permissions pour cet admin
                        </li>
                    <?php endif; ?>

                    <?php if (!$canAccessPlats && $admin['role'] === 'superadmin'): ?>
                        <li class="text-red-700">
                            <strong>Vérifier le code:</strong> La fonction canAccess() ne reconnaît pas correctement le superadmin
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="mt-6 flex gap-4">
                <a href="dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold">
                    <i class="fas fa-arrow-left mr-2"></i>Retour au Dashboard
                </a>
                <a href="gestion_plats.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold">
                    <i class="fas fa-utensils mr-2"></i>Tester Gestion Plats
                </a>
            </div>
        </div>
    </div>
</body>
</html>
