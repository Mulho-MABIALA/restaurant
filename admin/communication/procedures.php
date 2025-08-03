<?php
require_once '../../config.php';
session_start();

// Récupérer toutes les procédures
$procedures = $conn->query("SELECT * FROM procedures ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2 class="text-2xl font-bold mb-4">📚 Procédures et fiches techniques</h2>

<?php if (empty($procedures)): ?>
    <p class="text-gray-500">Aucune procédure disponible pour le moment.</p>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($procedures as $proc): ?>
            <div class="bg-white p-4 rounded shadow border-l-4 border-blue-400">
                <h3 class="text-xl font-semibold"><?= htmlspecialchars($proc['titre']) ?></h3>
                <p class="text-sm text-gray-600 italic mb-2"><?= htmlspecialchars($proc['categorie']) ?></p>
                <?php if ($proc['contenu']): ?>
                    <p class="text-gray-700"><?= nl2br(htmlspecialchars($proc['contenu'])) ?></p>
                <?php endif; ?>
                <?php if ($proc['fichier_url']): ?>
                    <a href="<?= htmlspecialchars($proc['fichier_url']) ?>" target="_blank" class="text-blue-600 hover:underline mt-2 inline-block">📄 Voir le fichier</a>
                <?php endif; ?>
                <p class="text-xs text-gray-400 mt-2">Ajouté le <?= date('d/m/Y', strtotime($proc['created_at'])) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
