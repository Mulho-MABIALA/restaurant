<?php
require_once '../config.php';
session_start();

// Vérifier les droits admin
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Récupérer tous les employés
$stmt = $conn->prepare("SELECT id, nom, prenom, qr_code, code_numerique FROM employes ORDER BY nom");
$stmt->execute();
$employes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Génération des badges QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            .badge { page-break-after: auto; margin: 10mm; }
            body { background: white !important; }
        }
        .badge {
            width: 85mm;
            height: 54mm;
            border: 2px solid #4F46E5;
            border-radius: 8px;
            display: inline-block;
            margin: 10px;
            padding: 8px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-100 p-4">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white shadow-lg rounded-xl p-6 mb-6">
            <h1 class="text-3xl font-bold mb-6 text-center text-indigo-600">🏷️ Génération des badges QR</h1>
            
            <div class="no-print mb-6 text-center space-x-4">
                <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium">
                    🖨️ Imprimer tous les badges
                </button>
                <button onclick="generateSelectedBadges()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium">
                    📱 Générer QR sélectionnés
                </button>
                <a href="badgeuse.php" class="inline-block bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-medium">
                    ← Retour à la badgeuse
                </a>
            </div>

            <div class="no-print mb-6">
                <div class="flex items-center space-x-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="selectAll" class="mr-2">
                        <span class="text-sm font-medium">Sélectionner tout</span>
                    </label>
                    <span class="text-sm text-gray-600" id="selectedCount">0 employé(s) sélectionné(s)</span>
                </div>
            </div>
        </div>

        <!-- Liste des employés avec badges -->
     
    <?php foreach ($employes as $employe): ?>
        <div class="badge-container inline-block">
            <div class="no-print mb-2">
                <label class="flex items-center justify-center">
                    <input type="checkbox" class="employee-checkbox mr-2" data-id="<?= $employe['id'] ?>">
                    <span class="text-sm"><?= htmlspecialchars($employe['nom'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                </label>
            </div>
            
            <div class="badge">
                <div class="flex items-center justify-between h-full">
                    <div class="flex-1 text-left">
                        <div class="text-xs font-bold text-indigo-600 mb-1">BADGE EMPLOYÉ</div>
                        <div class="text-sm font-semibold mb-1" style="line-height: 1.2;">
                            <?= htmlspecialchars(($employe['prenom'] ?? '') . ' ' . ($employe['nom'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        
                        <div class="text-xs text-gray-500 mb-1">
                            ID: <?= $employe['id'] ?>
                        </div>
                        
                        <?php if (!empty($employe['code_numerique'])): ?>
                        <div class="text-xs font-mono bg-gray-100 px-2 py-1 rounded border" style="font-size: 10px;">
                            Code: <?= htmlspecialchars($employe['code_numerique'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex-shrink-0 ml-2">
                        <canvas id="qr-<?= $employe['id'] ?>" width="80" height="80"></canvas>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="no-print mt-8 bg-blue-50 rounded-lg p-6">
    <h3 class="font-semibold text-blue-800 mb-3">Instructions d'utilisation :</h3>
    <ul class="text-sm text-blue-700 space-y-2">
        <li><strong>QR Code :</strong> Scan avec l'application de pointage</li>
        <li><strong>Code numérique :</strong> Saisie manuelle si le scan ne fonctionne pas</li>
        <li><strong>Impression :</strong> Utilisez du papier épais (200-250g) pour une meilleure durabilité</li>
        <li><strong>Format :</strong> Les badges sont dimensionnés pour du papier A4 standard</li>
        <li><strong>Plastification :</strong> Recommandée pour une utilisation intensive</li>
        <li><strong>Test :</strong> Testez chaque badge avec le scanner avant distribution</li>
    </ul>
</div>
    </div>

    <script>
    // Génération des QR codes
    // Génération des QR codes (mise à jour pour inclure le code numérique)
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($employes as $employe): ?>
        // Créer les données QR avec le code numérique
        const qrData<?= $employe['id'] ?> = JSON.stringify({
            id: <?= $employe['id'] ?>,
            nom: "<?= htmlspecialchars($employe['nom'] ?? '', ENT_QUOTES, 'UTF-8') ?>",
            prenom: "<?= htmlspecialchars($employe['prenom'] ?? '', ENT_QUOTES, 'UTF-8') ?>",
            code_numerique: "<?= htmlspecialchars($employe['code_numerique'] ?? '', ENT_QUOTES, 'UTF-8') ?>",
            generated: "<?= date('Y-m-d H:i:s') ?>"
        });
        
        const qr<?= $employe['id'] ?> = new QRious({
            element: document.getElementById('qr-<?= $employe['id'] ?>'),
            value: qrData<?= $employe['id'] ?>,
            size: 80,
            background: 'white',
            foreground: '#4F46E5',
            level: 'M'
        });
    <?php endforeach; ?>
    
    updateSelectedCount();
});

    // Gestion de la sélection
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.employee-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });

    document.querySelectorAll('.employee-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    function updateSelectedCount() {
        const selected = document.querySelectorAll('.employee-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = `${selected} employé(s) sélectionné(s)`;
    }

    function generateSelectedBadges() {
        const selected = document.querySelectorAll('.employee-checkbox:checked');
        if (selected.length === 0) {
            alert('Veuillez sélectionner au moins un employé');
            return;
        }
        
        // Masquer les badges non sélectionnés pour l'impression
        document.querySelectorAll('.badge-container').forEach(container => {
            const checkbox = container.querySelector('.employee-checkbox');
            if (checkbox && !checkbox.checked) {
                container.style.display = 'none';
            }
        });
        
        // Imprimer
        window.print();
        
        // Réafficher tous les badges après impression
        setTimeout(() => {
            document.querySelectorAll('.badge-container').forEach(container => {
                container.style.display = 'inline-block';
            });
        }, 1000);
    }
    </script>
</body>
</html>