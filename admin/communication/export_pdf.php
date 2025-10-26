<?php
session_start();
require_once '../../config.php';

// Vérification de l'authentification
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Récupérer les procédures
$query = "SELECT p.*, a.username as created_by_name 
          FROM procedures p 
          LEFT JOIN admin a ON p.created_by = a.id 
          WHERE p.status != 'deleted' 
          ORDER BY p.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN p.status = 'published' THEN 1 ELSE 0 END) as published,
    SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) as draft,
    SUM(CASE WHEN p.status = 'archived' THEN 1 ELSE 0 END) as archived
    FROM procedures p WHERE p.status != 'deleted'")->fetch(PDO::FETCH_ASSOC);

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Export PDF - Procédures - <?= date('d/m/Y') ?></title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #3B82F6;
            padding-bottom: 20px;
        }
        
        .header h1 {
            margin: 0;
            color: #1E40AF;
            font-size: 28px;
        }
        
        .header .date {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0 40px 0;
            background: #F8FAFC;
            padding: 15px;
            border-radius: 8px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .total { color: #3B82F6; }
        .published { color: #10B981; }
        .draft { color: #F59E0B; }
        .archived { color: #6B7280; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
        }
        
        th, td {
            border: 1px solid #E5E7EB;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        
        th {
            background-color: #F3F4F6;
            font-weight: bold;
            color: #374151;
        }
        
        .procedure-title {
            font-weight: bold;
            color: #1E40AF;
        }
        
        .procedure-content {
            color: #6B7280;
            font-size: 10px;
            max-width: 200px;
        }
        
        .status {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .status.draft {
            background: #FEF3C7;
            color: #92400E;
        }
        
        .status.published {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .status.archived {
            background: #F3F4F6;
            color: #374151;
        }
        
        .category {
            background: #DBEAFE;
            color: #1E40AF;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
        }
        
        .version {
            font-family: monospace;
            background: #F3F4F6;
            padding: 2px 4px;
            border-radius: 3px;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        .footer {
            position: fixed;
            bottom: 30px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        
        @page {
            margin: 2cm;
            @bottom-center {
                content: "Page " counter(page) " sur " counter(pages);
            }
        }
        
        .no-break {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <!-- En-tête -->
    <div class="header">
        <h1>📋 Rapport des Procédures</h1>
        <div class="date">Généré le <?= date('d/m/Y à H:i') ?></div>
    </div>
    
    <!-- Statistiques -->
    <div class="stats">
        <div class="stat-item">
            <div class="stat-number total"><?= $stats['total'] ?></div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-item">
            <div class="stat-number published"><?= $stats['published'] ?></div>
            <div class="stat-label">Publiées</div>
        </div>
        <div class="stat-item">
            <div class="stat-number draft"><?= $stats['draft'] ?></div>
            <div class="stat-label">Brouillons</div>
        </div>
        <div class="stat-item">
            <div class="stat-number archived"><?= $stats['archived'] ?></div>
            <div class="stat-label">Archivées</div>
        </div>
    </div>
    
    <!-- Tableau des procédures -->
    <table>
        <thead>
            <tr>
                <th width="25%">Titre</th>
                <th width="15%">Catégorie</th>
                <th width="10%">Statut</th>
                <th width="8%">Version</th>
                <th width="12%">Date création</th>
                <th width="12%">Créé par</th>
                <th width="8%">Fichier</th>
                <th width="10%">Dernière MAJ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($procedures as $index => $proc): ?>
                <?php
                $status_labels = [
                    'draft' => 'Brouillon',
                    'published' => 'Publié',
                    'archived' => 'Archivé'
                ];
                ?>
                <tr class="no-break">
                    <td>
                        <div class="procedure-title"><?= htmlspecialchars($proc['titre']) ?></div>
                        <?php if ($proc['contenu']): ?>
                            <div class="procedure-content">
                                <?= htmlspecialchars(substr(strip_tags($proc['contenu']), 0, 100)) ?>...
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="category"><?= htmlspecialchars($proc['categorie']) ?></span>
                    </td>
                    <td>
                        <span class="status <?= $proc['status'] ?>">
                            <?= $status_labels[$proc['status']] ?? $proc['status'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="version">v<?= $proc['version'] ?></span>
                    </td>
                    <td><?= date('d/m/Y', strtotime($proc['created_at'])) ?><br>
                        <small><?= date('H:i', strtotime($proc['created_at'])) ?></small>
                    </td>
                    <td><?= htmlspecialchars($proc['created_by_name'] ?? 'N/A') ?></td>
                    <td>
                        <?php if ($proc['fichier_url']): ?>
                            <?php
                            $ext = strtolower(pathinfo($proc['fichier_url'], PATHINFO_EXTENSION));
                            $ext_labels = [
                                'pdf' => '📄 PDF',
                                'doc' => '📝 DOC',
                                'docx' => '📝 DOCX',
                                'jpg' => '🖼️ JPG',
                                'jpeg' => '🖼️ JPEG',
                                'png' => '🖼️ PNG',
                                'txt' => '📄 TXT',
                                'xlsx' => '📊 XLSX'
                            ];
                            echo $ext_labels[$ext] ?? '📎 ' . strtoupper($ext);
                            ?>
                        <?php else: ?>
                            <em>Aucun</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($proc['updated_at'] && $proc['updated_at'] !== $proc['created_at']): ?>
                            <?= date('d/m/Y', strtotime($proc['updated_at'])) ?>
                        <?php else: ?>
                            <em>—</em>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <!-- Saut de page tous les 15 éléments pour éviter la coupure -->
                <?php if (($index + 1) % 15 === 0 && $index < count($procedures) - 1): ?>
                    </tbody>
                    </table>
                    <div class="page-break"></div>
                    <table>
                        <thead>
                            <tr>
                                <th width="25%">Titre</th>
                                <th width="15%">Catégorie</th>
                                <th width="10%">Statut</th>
                                <th width="8%">Version</th>
                                <th width="12%">Date création</th>
                                <th width="12%">Créé par</th>
                                <th width="8%">Fichier</th>
                                <th width="10%">Dernière MAJ</th>
                            </tr>
                        </thead>
                        <tbody>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="footer no-print">
        <p>Document généré automatiquement • <?= count($procedures) ?> procédure(s) • <?= date('d/m/Y à H:i') ?></p>
    </div>
    
    <script>
        // Auto-print when loaded
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>