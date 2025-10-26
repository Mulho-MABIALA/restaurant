<?php
/**
 * Page de test des paramètres
 */
require_once '../config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Paramètres</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { border-collapse: collapse; width: 100%; max-width: 800px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #4CAF50; color: white; }
        tr:nth-child(even) { background: #f2f2f2; }
        .ok { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🧪 Test des Paramètres Système</h1>

    <h2>1. Variables globales</h2>
    <table>
        <tr>
            <th>Variable</th>
            <th>Valeur</th>
            <th>Status</th>
        </tr>
        <tr>
            <td>$system_settings existe</td>
            <td><?php echo isset($system_settings) ? 'OUI' : 'NON'; ?></td>
            <td class="<?php echo isset($system_settings) ? 'ok' : 'error'; ?>">
                <?php echo isset($system_settings) ? '✅' : '❌'; ?>
            </td>
        </tr>
        <tr>
            <td>Nombre de paramètres</td>
            <td><?php echo isset($system_settings) ? count($system_settings) : 0; ?></td>
            <td class="<?php echo (isset($system_settings) && count($system_settings) > 0) ? 'ok' : 'error'; ?>">
                <?php echo (isset($system_settings) && count($system_settings) > 0) ? '✅' : '❌'; ?>
            </td>
        </tr>
    </table>

    <h2>2. Fonctions disponibles</h2>
    <table>
        <tr>
            <th>Fonction</th>
            <th>Status</th>
        </tr>
        <tr>
            <td>getSetting()</td>
            <td class="<?php echo function_exists('getSetting') ? 'ok' : 'error'; ?>">
                <?php echo function_exists('getSetting') ? '✅ Disponible' : '❌ Manquante'; ?>
            </td>
        </tr>
        <tr>
            <td>getAllSettings()</td>
            <td class="<?php echo function_exists('getAllSettings') ? 'ok' : 'error'; ?>">
                <?php echo function_exists('getAllSettings') ? '✅ Disponible' : '❌ Manquante'; ?>
            </td>
        </tr>
    </table>

    <h2>3. Constantes</h2>
    <table>
        <tr>
            <th>Constante</th>
            <th>Valeur</th>
            <th>Status</th>
        </tr>
        <tr>
            <td>RESTAURANT_NAME</td>
            <td><?php echo defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Non définie'; ?></td>
            <td class="<?php echo defined('RESTAURANT_NAME') ? 'ok' : 'error'; ?>">
                <?php echo defined('RESTAURANT_NAME') ? '✅' : '❌'; ?>
            </td>
        </tr>
        <tr>
            <td>RESTAURANT_EMAIL</td>
            <td><?php echo defined('RESTAURANT_EMAIL') ? RESTAURANT_EMAIL : 'Non définie'; ?></td>
            <td class="<?php echo defined('RESTAURANT_EMAIL') ? 'ok' : 'error'; ?>">
                <?php echo defined('RESTAURANT_EMAIL') ? '✅' : '❌'; ?>
            </td>
        </tr>
        <tr>
            <td>RESTAURANT_PHONE</td>
            <td><?php echo defined('RESTAURANT_PHONE') ? RESTAURANT_PHONE : 'Non définie'; ?></td>
            <td class="<?php echo defined('RESTAURANT_PHONE') ? 'ok' : 'error'; ?>">
                <?php echo defined('RESTAURANT_PHONE') ? '✅' : '❌'; ?>
            </td>
        </tr>
        <tr>
            <td>RESTAURANT_ADDRESS</td>
            <td><?php echo defined('RESTAURANT_ADDRESS') ? RESTAURANT_ADDRESS : 'Non définie'; ?></td>
            <td class="<?php echo defined('RESTAURANT_ADDRESS') ? 'ok' : 'error'; ?>">
                <?php echo defined('RESTAURANT_ADDRESS') ? '✅' : '❌'; ?>
            </td>
        </tr>
        <tr>
            <td>CURRENCY</td>
            <td><?php echo defined('CURRENCY') ? CURRENCY : 'Non définie'; ?></td>
            <td class="<?php echo defined('CURRENCY') ? 'ok' : 'error'; ?>">
                <?php echo defined('CURRENCY') ? '✅' : '❌'; ?>
            </td>
        </tr>
    </table>

    <h2>4. Fonction getSetting()</h2>
    <?php if (function_exists('getSetting')): ?>
    <table>
        <tr>
            <th>Clé</th>
            <th>Valeur</th>
        </tr>
        <tr>
            <td>restaurant_name</td>
            <td><?php echo htmlspecialchars(getSetting('restaurant_name', 'VIDE')); ?></td>
        </tr>
        <tr>
            <td>contact_email</td>
            <td><?php echo htmlspecialchars(getSetting('contact_email', 'VIDE')); ?></td>
        </tr>
        <tr>
            <td>contact_phone</td>
            <td><?php echo htmlspecialchars(getSetting('contact_phone', 'VIDE')); ?></td>
        </tr>
        <tr>
            <td>restaurant_address</td>
            <td><?php echo htmlspecialchars(getSetting('restaurant_address', 'VIDE')); ?></td>
        </tr>
    </table>
    <?php else: ?>
    <p class="error">❌ Fonction getSetting() non disponible</p>
    <?php endif; ?>

    <h2>5. Tous les paramètres en base</h2>
    <?php if (function_exists('getAllSettings')): ?>
    <table>
        <tr>
            <th>Clé</th>
            <th>Valeur</th>
        </tr>
        <?php foreach (getAllSettings() as $key => $value): ?>
        <tr>
            <td><?php echo htmlspecialchars($key); ?></td>
            <td><?php echo htmlspecialchars($value); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php else: ?>
    <p class="error">❌ Fonction getAllSettings() non disponible</p>
    <?php endif; ?>

    <h2>6. Test direct SQL</h2>
    <?php
    try {
        $stmt = $conn->query("SELECT setting_key, setting_value FROM settings LIMIT 5");
        $directSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        ?>
        <table>
            <tr>
                <th>Clé (direct SQL)</th>
                <th>Valeur (direct SQL)</th>
            </tr>
            <?php foreach ($directSettings as $key => $value): ?>
            <tr>
                <td><?php echo htmlspecialchars($key); ?></td>
                <td><?php echo htmlspecialchars($value); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php } catch (Exception $e) { ?>
        <p class="error">❌ Erreur SQL: <?php echo $e->getMessage(); ?></p>
    <?php } ?>

    <hr>
    <p><a href="index.php">← Retour à l'accueil</a></p>
</body>
</html>
