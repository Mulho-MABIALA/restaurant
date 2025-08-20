<?php
require_once '../config.php';
require_once '../vendor/autoload.php'; // TCPDF

// Vérifier si l'utilisateur est connecté et autorisé
// ...

// Récupérer la liste des employés actifs
$stmt = $conn->query("
    SELECT e.id, e.nom, e.prenom, p.nom as poste_nom
    FROM employes e
    JOIN postes p ON e.poste_id = p.id
    WHERE e.statut = 'actif'
    ORDER BY e.nom, e.prenom
");
$employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement de la génération de bulletin
if (isset($_GET['action']) && $_GET['action'] === 'generer_bulletin') {
    try {
        $employe_id = $_GET['employe_id'] ?? null;
        $mois_annee = $_GET['mois_annee'] ?? date('F Y'); // Par défaut : mois courant
        if (!$employe_id) {
            throw new Exception("ID de l'employé manquant.");
        }
        $pdfContent = genererBulletinPaie($conn, $employe_id, $mois_annee);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="bulletin_' . $employe_id . '.pdf"');
        echo $pdfContent;
        exit;
    } catch (Exception $e) {
        die("Erreur: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion de la Paie</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Gestion de la Paie</h1>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Générer un bulletin de paie</h2>
            <form method="GET" class="space-y-4">
                <input type="hidden" name="action" value="generer_bulletin">
                <div>
                    <label class="block text-sm font-medium mb-1">Employé</label>
                    <select name="employe_id" class="w-full p-2 border rounded" required>
                        <option value="">Sélectionnez un employé</option>
                        <?php foreach ($employes as $employe): ?>
                            <option value="<?php echo $employe['id']; ?>">
                                <?php echo htmlspecialchars($employe['nom'] . ' ' . $employe['prenom'] . ' (' . $employe['poste_nom'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Mois</label>
                    <input type="month" name="mois_annee" class="w-full p-2 border rounded"
                           value="<?php echo date('Y-m'); ?>" required>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Générer le bulletin
                </button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Liste des employés</h2>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Poste</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Salaire net estimé</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($employes as $employe): ?>
                        <?php
                        $calcul = calculerSalaireNet($conn, $employe['id']);
                        $salaire_net = $calcul['success'] ? $calcul['salaire_net'] : 'N/A';
                        ?>
                        <tr>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($employe['nom'] . ' ' . $employe['prenom']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($employe['poste_nom']); ?></td>
                            <td class="px-6 py-4"><?php echo $calcul['success'] ? number_format($salaire_net, 0, ',', ' ') . ' FCFA' : 'Erreur'; ?></td>
                            <td class="px-6 py-4">
                                <a href="?action=generer_bulletin&employe_id=<?php echo $employe['id']; ?>&mois_annee=<?php echo date('Y-m'); ?>"
                                   class="text-blue-600 hover:text-blue-800">Générer bulletin</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
