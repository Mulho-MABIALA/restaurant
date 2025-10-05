<?php
require_once 'config.php';

echo "=== Tables existantes dans la base restaurant ===\n\n";

$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "✓ $table\n";
}

echo "\n\n=== Vérification table factures_fournisseurs ===\n";

$stmt = $conn->query("SHOW TABLES LIKE 'factures_fournisseurs'");
$exists = $stmt->fetch();

if ($exists) {
    echo "✅ La table factures_fournisseurs EXISTE\n";
} else {
    echo "❌ La table factures_fournisseurs N'EXISTE PAS\n";
}

echo "\n=== Vérification table fournisseurs ===\n";

$stmt = $conn->query("SHOW TABLES LIKE 'fournisseurs'");
$exists = $stmt->fetch();

if ($exists) {
    echo "✅ La table fournisseurs EXISTE\n";
} else {
    echo "❌ La table fournisseurs N'EXISTE PAS\n";
}
