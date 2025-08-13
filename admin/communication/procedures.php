<?php
require_once '../../config.php';
session_start();

// Récupérer toutes les procédures
$procedures = $conn->query("SELECT * FROM procedures ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
/* Variables CSS épurées */
:root {
    --primary: #2563eb;
    --primary-light: #3b82f6;
    --secondary: #64748b;
    --accent: #10b981;
    --success: #059669;
    --warning: #f59e0b;
    --danger: #dc2626;
    
    --gray-50: #f8fafc;
    --gray-100: #f1f5f9;
    --gray-200: #e2e8f0;
    --gray-300: #cbd5e1;
    --gray-400: #94a3b8;
    --gray-500: #64748b;
    --gray-600: #475569;
    --gray-700: #334155;
    --gray-800: #1e293b;
    --gray-900: #0f172a;
    
    --white: #ffffff;
    --black: #000000;
    
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    
    --radius-sm: 8px;
    --radius: 12px;
    --radius-lg: 16px;
    --radius-xl: 20px;
}

/* Reset et base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    line-height: 1.6;
    color: var(--gray-700);
}

.procedures-wrapper {
    min-height: 100vh;
    background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 50%, var(--gray-100) 100%);
    padding: 2rem 0;
    position: relative;
}

.procedures-wrapper::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        radial-gradient(circle at 25% 25%, var(--gray-200) 1px, transparent 1px),
        radial-gradient(circle at 75% 75%, var(--gray-200) 1px, transparent 1px);
    background-size: 60px 60px;
    opacity: 0.4;
    pointer-events: none;
}

.procedures-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1.5rem;
    position: relative;
    z-index: 1;
}

/* Header moderne et épuré */
.procedures-header {
    text-align: center;
    margin-bottom: 3rem;
    padding: 2rem 0;
}

.header-title {
    font-size: clamp(2.5rem, 5vw, 4rem);
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 1rem;
    line-height: 1.2;
}

.header-subtitle {
    font-size: 1.25rem;
    color: var(--gray-500);
    font-weight: 400;
    max-width: 600px;
    margin: 0 auto 2rem;
}

.header-stats {
    display: flex;
    justify-content: center;
    gap: 3rem;
    margin-top: 2rem;
}

.stat-card {
    background: var(--white);
    padding: 1.5rem 2rem;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    text-align: center;
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary);
    display: block;
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--gray-500);
    font-weight: 500;
    margin-top: 0.5rem;
}

/* État vide élégant */
.empty-state {
    background: var(--white);
    border: 2px dashed var(--gray-300);
    border-radius: var(--radius-xl);
    padding: 4rem 2rem;
    text-align: center;
    margin-top: 2rem;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.6;
}

.empty-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
}

.empty-description {
    font-size: 1rem;
    color: var(--gray-500);
}

/* Grille responsive */
.procedures-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

/* Cartes procédures raffinées */
.procedure-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.procedure-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-xl);
    border-color: var(--primary);
}

.procedure-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--accent));
}

.card-header {
    padding: 2rem 2rem 1rem;
    background: linear-gradient(135deg, var(--white) 0%, var(--gray-50) 100%);
    position: relative;
}

.card-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--gray-800);
    margin-bottom: 1rem;
    line-height: 1.3;
}

.card-category {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: var(--white);
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    box-shadow: var(--shadow-sm);
}

.category-icon {
    font-size: 0.9rem;
}

.card-content {
    padding: 0 2rem 1.5rem;
}

.content-text {
    color: var(--gray-600);
    line-height: 1.7;
    font-size: 1rem;
    max-height: 120px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.content-text::-webkit-scrollbar {
    width: 4px;
}

.content-text::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: 8px;
}

.content-text::-webkit-scrollbar-thumb {
    background: var(--gray-300);
    border-radius: 8px;
}

.content-text::-webkit-scrollbar-thumb:hover {
    background: var(--gray-400);
}

.card-footer {
    padding: 1.5rem 2rem;
    background: var(--gray-50);
    border-top: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.card-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--gray-500);
    font-weight: 500;
}

.date-icon {
    font-size: 1rem;
}

.file-button {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    background: linear-gradient(135deg, var(--accent), var(--success));
    color: var(--white);
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
}

.file-button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.6s;
}

.file-button:hover::before {
    left: 100%;
}

.file-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
}

.file-icon {
    font-size: 1rem;
}

/* Animations d'entrée */
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.procedure-card {
    animation: slideUp 0.6s ease-out forwards;
    animation-delay: calc(var(--index, 0) * 0.1s);
}

/* Responsive design */
@media (max-width: 768px) {
    .procedures-container {
        padding: 0 1rem;
    }
    
    .header-stats {
        flex-direction: column;
        gap: 1rem;
        align-items: center;
    }
    
    .stat-card {
        padding: 1rem 1.5rem;
    }
    
    .procedures-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .card-header,
    .card-content {
        padding-left: 1.5rem;
        padding-right: 1.5rem;
    }
    
    .card-footer {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
        padding: 1.5rem;
    }
    
    .file-button {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .header-title {
        font-size: 2.5rem;
    }
    
    .header-subtitle {
        font-size: 1.1rem;
    }
    
    .card-header,
    .card-content,
    .card-footer {
        padding-left: 1rem;
        padding-right: 1rem;
    }
}

/* Mode sombre */
@media (prefers-color-scheme: dark) {
    :root {
        --white: #1e293b;
        --gray-50: #334155;
        --gray-100: #475569;
        --gray-200: #64748b;
        --gray-700: #cbd5e1;
        --gray-800: #f1f5f9;
    }
}

/* Accessibilité */
@media (prefers-reduced-motion: reduce) {
    * {
        animation: none !important;
        transition: none !important;
    }
}

.file-button:focus {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
}
</style>

<div class="procedures-wrapper">
    <div class="procedures-container">
        <!-- Header épuré et moderne -->
        <header class="procedures-header">
            <h1 class="header-title">📚 Documentation</h1>
            <p class="header-subtitle">
                Accédez rapidement à toutes vos procédures et fiches techniques
            </p>
            
            <?php if (!empty($procedures)): ?>
                <div class="header-stats">
                    <div class="stat-card">
                        <span class="stat-number"><?= count($procedures) ?></span>
                        <span class="stat-label">Procédures</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?= count(array_unique(array_column($procedures, 'categorie'))) ?></span>
                        <span class="stat-label">Catégories</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?= count(array_filter($procedures, function($p) { return !empty($p['fichier_url']); })) ?></span>
                        <span class="stat-label">Fichiers</span>
                    </div>
                </div>
            <?php endif; ?>
        </header>

        <!-- Contenu principal -->
        <main>
            <?php if (empty($procedures)): ?>
                <!-- État vide -->
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <h3 class="empty-title">Aucune procédure disponible</h3>
                    <p class="empty-description">
                        Les documents apparaîtront ici dès qu'ils seront ajoutés au système.
                    </p>
                </div>
            <?php else: ?>
                <!-- Grille des procédures -->
                <div class="procedures-grid">
                    <?php foreach ($procedures as $index => $proc): ?>
                        <article class="procedure-card" style="--index: <?= $index ?>">
                            <header class="card-header">
                                <h2 class="card-title"><?= htmlspecialchars($proc['titre']) ?></h2>
                                <div class="card-category">
                                    <span class="category-icon">🏷️</span>
                                    <?= htmlspecialchars($proc['categorie']) ?>
                                </div>
                            </header>
                            
                            <?php if ($proc['contenu']): ?>
                                <div class="card-content">
                                    <div class="content-text">
                                        <?= nl2br(htmlspecialchars($proc['contenu'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <footer class="card-footer">
                                <div class="card-date">
                                    <span class="date-icon">📅</span>
                                    <time datetime="<?= $proc['created_at'] ?>">
                                        <?= date('d/m/Y', strtotime($proc['created_at'])) ?>
                                    </time>
                                </div>
                                
                                <?php if ($proc['fichier_url']): ?>
                                    <a href="/restaurant/uploads/procedures/<?= htmlspecialchars(basename($proc['fichier_url'])) ?>" 
                                       target="_blank" 
                                       class="file-button"
                                       aria-label="Voir le fichier de <?= htmlspecialchars($proc['titre']) ?>">
                                        <span class="file-icon">📄</span>
                                        <span>Voir le fichier</span>
                                    </a>
                                <?php endif; ?>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>