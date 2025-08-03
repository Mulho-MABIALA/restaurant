<?php
require_once '../../config.php';
session_start();

$employe_id = $_SESSION['admin_id']; // ou autre selon ta session

// Gestion du formulaire POST pour ajouter un nouvel incident
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = $_POST['titre'];
    $description = $_POST['description'];
    $gravite = $_POST['gravite'];

    // Ici on pourrait gérer le fichier uploadé (optionnel)
    $fichier_url = null;
    if (!empty($_FILES['fichier']['name'])) {
        $upload_dir = '../uploads/incidents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $filename = basename($_FILES['fichier']['name']);
        $target_file = $upload_dir . time() . '_' . $filename;

        if (move_uploaded_file($_FILES['fichier']['tmp_name'], $target_file)) {
            $fichier_url = $target_file;
        }
    }

    $stmt = $conn->prepare("INSERT INTO incidents (employe_id, titre, description, gravite, fichier_url) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$employe_id, $titre, $description, $gravite, $fichier_url]);

    header('Location: incidents.php');
    exit;
}

// Récupérer la liste des incidents (tous ou filtrés)
$incidents = $conn->prepare("SELECT i.*, e.nom FROM incidents i JOIN employes e ON i.employe_id = e.id ORDER BY i.created_at DESC");
$incidents->execute();
$incidents = $incidents->fetchAll(PDO::FETCH_ASSOC);
?>

<h2 class="text-2xl font-bold mb-6">⚠️ Gestion des incidents internes</h2>

<!-- Formulaire déclaration -->
<form method="post" enctype="multipart/form-data" class="mb-8 bg-white p-6 rounded shadow max-w-lg">
    <h3 class="text-xl font-semibold mb-4">Signaler un nouvel incident</h3>

    <input type="text" name="titre" placeholder="Titre de l'incident" required
           class="w-full p-2 mb-4 border rounded" />

    <textarea name="description" rows="4" placeholder="Description détaillée" required
              class="w-full p-2 mb-4 border rounded"></textarea>

    <select name="gravite" required class="w-full p-2 mb-4 border rounded">
        <option value="">Niveau de gravité</option>
        <option value="basse">Basse</option>
        <option value="moyenne">Moyenne</option>
        <option value="élevée">Élevée</option>
    </select>

    <label class="block mb-4">
        Fichier (optionnel) :
        <input type="file" name="fichier" class="mt-1" />
    </label>

    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
        Signaler
    </button>
</form>

<!-- Liste des incidents -->
<?php if (empty($incidents)): ?>
    <p class="text-gray-500">Aucun incident signalé pour le moment.</p>
<?php else: ?>
    <div class="space-y-6">
        <?php foreach ($incidents as $incident): ?>
            <div class="bg-white p-4 rounded shadow border-l-4
                <?php
                    echo match ($incident['gravite']) {
                        'élevée' => 'border-red-500',
                        'moyenne' => 'border-yellow-500',
                        default => 'border-gray-300',
                    };
                ?>
            ">
                <h3 class="text-lg font-semibold"><?= htmlspecialchars($incident['titre']) ?></h3>
                <p class="italic text-sm text-gray-600 mb-2">Signalé par <strong><?= htmlspecialchars($incident['nom']) ?></strong> le <?= date('d/m/Y H:i', strtotime($incident['created_at'])) ?></p>
                <p class="mb-2"><?= nl2br(htmlspecialchars($incident['description'])) ?></p>

                <?php if ($incident['fichier_url']): ?>
                    <a href="<?= htmlspecialchars($incident['fichier_url']) ?>" target="_blank" class="text-blue-600 hover:underline inline-block mb-2">📎 Voir le fichier joint</a>
                <?php endif; ?>

                <p class="text-sm text-gray-500">Gravité : <?= htmlspecialchars($incident['gravite']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
