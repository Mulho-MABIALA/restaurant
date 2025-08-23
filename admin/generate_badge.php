<?php
require_once '../config.php';
session_start();

// Vérifier les droits admin
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

/**
 * Fonction pour générer un code numérique unique
 * Utilise une méthode séquentielle garantissant l'unicité
 */
function generateUniqueCode($conn) {
    // Trouver le dernier code utilisé
    $stmt = $conn->prepare("
        SELECT MAX(CAST(code_numerique AS UNSIGNED)) as max_code 
        FROM employes 
        WHERE code_numerique REGEXP '^[0-9]+$' 
        AND code_numerique IS NOT NULL
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    // Si aucun code existe, commencer à 1000000000 (1 milliard)
    // Sinon, prendre le suivant
    $next_code = ($result && $result['max_code']) ? $result['max_code'] + 1 : 1000000000;
    
    return (string)$next_code;
}

/**
 * Fonction pour vérifier si un code est unique
 */
function isCodeUnique($conn, $code) {
    $stmt = $conn->prepare("SELECT id FROM employes WHERE code_numerique = ?");
    $stmt->execute([$code]);
    return !$stmt->fetch();
}

// Action pour régénérer les codes manquants
if (isset($_POST['regenerate_codes'])) {
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("
            SELECT id FROM employes 
            WHERE statut = 'actif' AND (code_numerique IS NULL OR code_numerique = '')
            ORDER BY id
        ");
        $stmt->execute();
        $employes_sans_codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $codes_generes = 0;
        $erreurs = [];
        
        foreach ($employes_sans_codes as $employee_id) {
            // Générer un code unique
            $numeric_code = generateUniqueCode($conn);
            
            // Double vérification de l'unicité (sécurité supplémentaire)
            if (isCodeUnique($conn, $numeric_code)) {
                $update_stmt = $conn->prepare("UPDATE employes SET code_numerique = ? WHERE id = ?");
                if ($update_stmt->execute([$numeric_code, $employee_id])) {
                    $codes_generes++;
                } else {
                    $erreurs[] = "Erreur mise à jour employé ID: $employee_id";
                }
            } else {
                // Si par miracle le code n'est pas unique, en générer un autre
                $attempt = 0;
                $max_attempts = 10;
                
                while ($attempt < $max_attempts && !isCodeUnique($conn, $numeric_code)) {
                    $numeric_code = generateUniqueCode($conn);
                    $attempt++;
                }
                
                if (isCodeUnique($conn, $numeric_code)) {
                    $update_stmt = $conn->prepare("UPDATE employes SET code_numerique = ? WHERE id = ?");
                    if ($update_stmt->execute([$numeric_code, $employee_id])) {
                        $codes_generes++;
                    }
                } else {
                    $erreurs[] = "Impossible de générer un code unique pour l'employé ID: $employee_id";
                }
            }
        }
        
        $conn->commit();
        
        $message = "✅ Codes générés pour $codes_generes employé(s)";
        if (!empty($erreurs)) {
            $message .= "<br>⚠️ Erreurs: " . implode("<br>", $erreurs);
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        $message = "❌ Erreur lors de la génération: " . $e->getMessage();
    }
}

// Action pour générer un code pour un employé spécifique
if (isset($_POST['generate_single']) && isset($_POST['employee_id'])) {
    try {
        $employee_id = (int)$_POST['employee_id'];
        
        // Vérifier que l'employé existe et n'a pas déjà de code
        $stmt = $conn->prepare("
            SELECT id, nom, prenom, code_numerique 
            FROM employes 
            WHERE id = ? AND statut = 'actif'
        ");
        $stmt->execute([$employee_id]);
        $employe = $stmt->fetch();
        
        if ($employe) {
            if (empty($employe['code_numerique'])) {
                $numeric_code = generateUniqueCode($conn);
                
                $update_stmt = $conn->prepare("UPDATE employes SET code_numerique = ? WHERE id = ?");
                if ($update_stmt->execute([$numeric_code, $employee_id])) {
                    $message = "✅ Code $numeric_code généré pour " . $employe['prenom'] . " " . $employe['nom'];
                } else {
                    $message = "❌ Erreur lors de la mise à jour";
                }
            } else {
                $message = "⚠️ L'employé a déjà un code: " . $employe['code_numerique'];
            }
        } else {
            $message = "❌ Employé introuvable";
        }
        
    } catch (Exception $e) {
        $message = "❌ Erreur: " . $e->getMessage();
    }
}

// Action pour régénérer un code spécifique
if (isset($_POST['regenerate_single']) && isset($_POST['employee_id'])) {
    try {
        $employee_id = (int)$_POST['employee_id'];
        
        $stmt = $conn->prepare("
            SELECT nom, prenom FROM employes 
            WHERE id = ? AND statut = 'actif'
        ");
        $stmt->execute([$employee_id]);
        $employe = $stmt->fetch();
        
        if ($employe) {
            $numeric_code = generateUniqueCode($conn);
            
            $update_stmt = $conn->prepare("UPDATE employes SET code_numerique = ? WHERE id = ?");
            if ($update_stmt->execute([$numeric_code, $employee_id])) {
                $message = "✅ Nouveau code $numeric_code généré pour " . $employe['prenom'] . " " . $employe['nom'];
            } else {
                $message = "❌ Erreur lors de la mise à jour";
            }
        } else {
            $message = "❌ Employé introuvable";
        }
        
    } catch (Exception $e) {
        $message = "❌ Erreur: " . $e->getMessage();
    }
}

// Récupérer tous les employés actifs avec leurs codes
$stmt = $conn->prepare("
    SELECT e.id, e.nom, e.prenom, e.qr_code, e.code_numerique, e.email, e.poste_id,
           p.nom as poste_nom, p.couleur as poste_couleur,
           CASE 
               WHEN e.code_numerique IS NULL OR e.code_numerique = '' THEN 0
               ELSE 1
           END as has_code
    FROM employes e 
    LEFT JOIN postes p ON e.poste_id = p.id 
    WHERE e.statut = 'actif'
    ORDER BY has_code ASC, e.nom, e.prenom
");
$stmt->execute();
$employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$total_employes = count($employes);
$avec_codes = count(array_filter($employes, fn($e) => !empty($e['code_numerique'])));
$sans_codes = $total_employes - $avec_codes;

// Vérifier les doublons (pour debug/monitoring)
$stmt = $conn->query("
    SELECT code_numerique, COUNT(*) as count
    FROM employes 
    WHERE code_numerique IS NOT NULL AND code_numerique != ''
    GROUP BY code_numerique 
    HAVING COUNT(*) > 1
");
$doublons = $stmt->fetchAll(PDO::FETCH_ASSOC);
$has_duplicates = !empty($doublons);
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
        .code-display {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 2px 4px;
            border-radius: 4px;
            border: 1px solid #d1d5db;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-missing-code {
            border-color: #EF4444;
            background: #FEF2F2;
        }
        .employee-actions {
            display: none;
        }
        .employee-row:hover .employee-actions {
            display: flex;
        }
    </style>
</head>
<body class="bg-gray-100 p-4">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white shadow-lg rounded-xl p-6 mb-6">
            <h1 class="text-3xl font-bold mb-6 text-center text-indigo-600">Génération des badges QR</h1>
            
            <?php if (isset($message)): ?>
                <div class="mb-4 p-4 rounded-lg <?= strpos($message, '❌') !== false ? 'bg-red-100 border border-red-400 text-red-700' : (strpos($message, '⚠️') !== false ? 'bg-yellow-100 border border-yellow-400 text-yellow-700' : 'bg-green-100 border border-green-400 text-green-700') ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <?php if ($has_duplicates): ?>
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <strong>⚠️ ATTENTION:</strong> Des doublons ont été détectés dans la base de données !
                    <?php foreach ($doublons as $doublon): ?>
                        <br>Code: <?= htmlspecialchars($doublon['code_numerique']) ?> (<?= $doublon['count'] ?> fois)
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="no-print mb-6 text-center space-x-4 flex flex-wrap justify-center gap-3">
                <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium">
                    🖨️ Imprimer tous les badges
                </button>
                <button onclick="generateSelectedBadges()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-medium">
                    📋 Générer QR sélectionnés
                </button>
                <?php if ($sans_codes > 0): ?>
                <form method="POST" class="inline">
                    <button type="submit" name="regenerate_codes" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium"
                            onclick="return confirm('Générer les codes manquants pour <?= $sans_codes ?> employé(s) ?')">
                        🔢 Générer codes manquants (<?= $sans_codes ?>)
                    </button>
                </form>
                <?php endif; ?>
                <button onclick="window.location.reload()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded-lg font-medium">
                    🔄 Actualiser
                </button>
                <a href="gestion_employes.php" class="inline-block bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-medium">
                    ← Retour à la gestion
                </a>
            </div>

            <!-- Affichage du statut des codes -->
            <div class="no-print mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="font-semibold text-blue-800 mb-2">📊 Statut des codes numériques :</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="font-medium">Total employés :</span>
                        <span class="text-blue-600"><?= $total_employes ?></span>
                    </div>
                    <div>
                        <span class="font-medium">Avec code :</span>
                        <span class="text-green-600"><?= $avec_codes ?></span>
                    </div>
                    <div>
                        <span class="font-medium">Sans code :</span>
                        <span class="text-red-600"><?= $sans_codes ?></span>
                    </div>
                    <div>
                        <span class="font-medium">Pourcentage :</span>
                        <span class="text-indigo-600"><?= $total_employes > 0 ? round(($avec_codes/$total_employes)*100, 1) : 0 ?>%</span>
                    </div>
                </div>
                
                <?php if ($sans_codes > 0): ?>
                <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded">
                    <p class="text-sm text-yellow-800">
                        <strong>Info :</strong> <?= $sans_codes ?> employé(s) n'ont pas de code numérique. 
                        Utilisez le bouton "Générer codes manquants" pour les créer automatiquement.
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if ($sans_codes == 0 && $avec_codes > 0): ?>
                <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded">
                    <p class="text-sm text-green-800">
                        <strong>✅ Parfait :</strong> Tous les employés ont un code numérique unique !
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Contrôles de sélection -->
            <div class="no-print mb-6">
                <div class="flex items-center space-x-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="selectAll" class="mr-2">
                        <span class="text-sm font-medium">Sélectionner tout</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" id="selectOnlyValid" class="mr-2">
                        <span class="text-sm font-medium">Sélectionner seulement ceux avec codes</span>
                    </label>
                    <span class="text-sm text-gray-600" id="selectedCount">0 employé(s) sélectionné(s)</span>
                </div>
            </div>
        </div>

        <!-- Liste des employés avec badges -->
        <?php if (empty($employes)): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
                Aucun employé actif trouvé.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($employes as $employe): ?>
                    <div class="badge-container">
                        <div class="no-print mb-2 bg-white p-2 rounded shadow employee-row">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <input type="checkbox" class="employee-checkbox mr-2" data-id="<?= $employe['id'] ?>" <?= empty($employe['code_numerique']) ? 'disabled' : '' ?>>
                                    <span class="text-sm font-medium"><?= htmlspecialchars(($employe['prenom'] ?? '') . ' ' . ($employe['nom'] ?? '')) ?></span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <?php if (empty($employe['code_numerique'])): ?>
                                        <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">Sans code</span>
                                        <div class="employee-actions space-x-1">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="employee_id" value="<?= $employe['id'] ?>">
                                                <button type="submit" name="generate_single" class="text-xs bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded" 
                                                        title="Générer un code pour cet employé">
                                                    ➕
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded font-mono">
                                            <?= htmlspecialchars($employe['code_numerique']) ?>
                                        </span>
                                        <div class="employee-actions space-x-1">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="employee_id" value="<?= $employe['id'] ?>">
                                                <button type="submit" name="regenerate_single" class="text-xs bg-orange-500 hover:bg-orange-600 text-white px-2 py-1 rounded" 
                                                        title="Régénérer le code de cet employé"
                                                        onclick="return confirm('Régénérer le code de <?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?> ?')">
                                                    🔄
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="badge <?= empty($employe['code_numerique']) ? 'badge-missing-code' : '' ?>">
                            <div class="flex items-center justify-between h-full">
                                <div class="flex-1 text-left">
                                    <div class="text-xs font-bold text-indigo-600 mb-1">BADGE EMPLOYÉ</div>
                                    <div class="text-sm font-semibold mb-1" style="line-height: 1.2;">
                                        <?= htmlspecialchars(($employe['prenom'] ?? '') . ' ' . ($employe['nom'] ?? '')) ?>
                                    </div>
                                    
                                    <?php if (!empty($employe['poste_nom'])): ?>
                                    <div class="text-xs text-gray-600 mb-1">
                                        <?= htmlspecialchars($employe['poste_nom']) ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-xs text-gray-500 mb-1">
                                        ID: <?= $employe['id'] ?>
                                    </div>
                                    
                                    <?php if (!empty($employe['code_numerique'])): ?>
                                    <div class="code-display">
                                        <?= htmlspecialchars($employe['code_numerique']) ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-xs text-red-600 font-medium">
                                        Code manquant
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-shrink-0 ml-2">
                                    <canvas id="qr-<?= $employe['id'] ?>" width="80" height="80"></canvas>
                                    <?php if (empty($employe['code_numerique'])): ?>
                                    <div class="text-xs text-red-600 text-center mt-1">QR invalide</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="no-print mt-8 bg-blue-50 rounded-lg p-6">
            <h3 class="font-semibold text-blue-800 mb-3">📋 Instructions d'utilisation :</h3>
            <ul class="text-sm text-blue-700 space-y-2">
                <li><strong>🔢 Code numérique :</strong> Chaque employé a un code unique généré automatiquement</li>
                <li><strong>📱 QR Code :</strong> Scan avec l'application de pointage pour identification rapide</li>
                <li><strong>⌨️ Saisie manuelle :</strong> Utilisez le code numérique si le scan QR ne fonctionne pas</li>
                <li><strong>➕ Nouvel employé :</strong> Les codes sont générés automatiquement sans risque de doublon</li>
                <li><strong>🔄 Actions individuelles :</strong> Survolez une ligne d'employé pour voir les actions disponibles</li>
                <li><strong>🖨️ Impression :</strong> Utilisez du papier épais (200-250g) pour une meilleure durabilité</li>
                <li><strong>🛡️ Plastification :</strong> Recommandée pour une utilisation intensive</li>
            </ul>
            
            <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded">
                <p class="text-sm text-green-800">
                    <strong>✅ Système sécurisé :</strong> La génération de codes utilise un système séquentiel qui garantit l'unicité à 100%. 
                    Aucun doublon n'est possible même lors de l'ajout simultané de plusieurs employés.
                </p>
            </div>
        </div>
    </div>

    <script>
    // Données des employés pour JavaScript
    const employeesData = <?= json_encode($employes) ?>;
    
    // Génération des QR codes
    document.addEventListener('DOMContentLoaded', function() {
        employeesData.forEach(employe => {
            const canvas = document.getElementById('qr-' + employe.id);
            if (!canvas) return;
            
            if (employe.code_numerique && employe.code_numerique.trim() !== '') {
                // Créer les données QR avec le code numérique
                const qrData = JSON.stringify({
                    type: 'employee_badge',
                    id: parseInt(employe.id),
                    code: employe.code_numerique,
                    nom: employe.nom || '',
                    prenom: employe.prenom || '',
                    email: employe.email || '',
                    poste_id: employe.poste_id ? parseInt(employe.poste_id) : null,
                    timestamp: Math.floor(Date.now() / 1000),
                    version: '1.1'
                });
                
                try {
                    new QRious({
                        element: canvas,
                        value: qrData,
                        size: 80,
                        background: 'white',
                        foreground: '#4F46E5',
                        level: 'H' // Niveau de correction d'erreur élevé
                    });
                } catch (error) {
                    console.error('Erreur génération QR pour employé', employe.id, ':', error);
                    // QR code d'erreur
                    new QRious({
                        element: canvas,
                        value: 'ERREUR_QR_' + employe.id,
                        size: 80,
                        background: '#FEE2E2',
                        foreground: '#DC2626',
                        level: 'L'
                    });
                }
            } else {
                // Afficher un QR code d'erreur si pas de code numérique
                try {
                    new QRious({
                        element: canvas,
                        value: 'CODE_MANQUANT_ID_' + employe.id,
                        size: 80,
                        background: '#FEE2E2',
                        foreground: '#DC2626',
                        level: 'M'
                    });
                } catch (error) {
                    console.error('Erreur génération QR d\'erreur:', error);
                }
            }
        });
        
        updateSelectedCount();
    });

    // Gestion de la sélection
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.employee-checkbox:not([disabled])');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });

    document.getElementById('selectOnlyValid').addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('selectAll').checked = false;
            const checkboxes = document.querySelectorAll('.employee-checkbox:not([disabled])');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
        } else {
            document.querySelectorAll('.employee-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        }
        updateSelectedCount();
    });

    document.querySelectorAll('.employee-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    function updateSelectedCount() {
        const selected = document.querySelectorAll('.employee-checkbox:checked').length;
        const valid = document.querySelectorAll('.employee-checkbox:not([disabled])').length;
        document.getElementById('selectedCount').textContent = `${selected} employé(s) sélectionné(s) sur ${valid} valides`;
    }

    function generateSelectedBadges() {
        const selected = document.querySelectorAll('.employee-checkbox:checked');
        if (selected.length === 0) {
            alert('Veuillez sélectionner au moins un employé avec un code valide');
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
        setTimeout(() => window.print(), 100);
        
        // Réafficher tous les badges après impression
        setTimeout(() => {
            document.querySelectorAll('.badge-container').forEach(container => {
                container.style.display = 'block';
            });
        }, 1000);
    }

    // Effet visuel pour les actions au survol
    document.querySelectorAll('.employee-row').forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.02)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    </script>
</body>
</html>