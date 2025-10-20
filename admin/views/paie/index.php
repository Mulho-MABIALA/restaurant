<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Système RH Intégré - Restaurant Le Savoureux</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        .notification.show { transform: translateX(0); }
        .notification.success { background-color: #10b981; }
        .notification.error { background-color: #ef4444; }
        .notification.info { background-color: #3b82f6; }

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .card-hover {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .tab-active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom: 3px solid #4f46e5;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-en-attente { background-color: #FEF3C7; color: #92400E; }
        .status-en_attente { background-color: #FEF3C7; color: #92400E; }
        .status-approuve { background-color: #D1FAE5; color: #065F46; }
        .status-refuse { background-color: #FEE2E2; color: #991B1B; }
        .status-brouillon { background-color: #F3F4F6; color: #374151; }
        .status-valide { background-color: #D1FAE5; color: #065F46; }
        .status-paye { background-color: #D1FAE5; color: #065F46; }
        .status-en-cours { background-color: #DBEAFE; color: #1E40AF; }
        .presence-present { background-color: #10b981; } /* Vert - Présent */
        .presence-absent { background-color: #ef4444; }  /* Rouge - Absent */
        .presence-retard { background-color: #f59e0b; }  /* Jaune - En retard */
        .presence-pause { background-color: #3b82f6; }  

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .loading {
            display: none;
        }
        .loading.show {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }

        .presence-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .presence-present { background-color: #10b981; }
        .presence-absent { background-color: #ef4444; }
        .presence-retard { background-color: #f59e0b; }

        /* AJOUTER CES STYLES DANS LA SECTION <style> DE gestion_paie.php */

.presence-card {
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.presence-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.presence-card.present {
    border-left-color: #10b981;
    background: linear-gradient(135deg, #ecfdf5, #f0fdf4);
}

.presence-card.absent {
    border-left-color: #ef4444;
    background: linear-gradient(135deg, #fef2f2, #fef7f7);
}

.presence-card.retard {
    border-left-color: #f59e0b;
    background: linear-gradient(135deg, #fffbeb, #fefce8);
}

.presence-card.pause {
    border-left-color: #3b82f6;
    background: linear-gradient(135deg, #eff6ff, #f0f9ff);
}

/* Indicateurs de statut animés */
.status-indicator {
    position: relative;
    display: inline-block;
}

.status-indicator.present::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #10b981;
    border-radius: 50%;
    animation: pulse-green 2s infinite;
}

.status-indicator.retard::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #f59e0b;
    border-radius: 50%;
    animation: pulse-yellow 2s infinite;
}

@keyframes pulse-green {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
    }
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

@keyframes pulse-yellow {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
    }
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 10px rgba(245, 158, 11, 0);
    }
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
    }
}

/* Tooltip pour les horaires planifiés */
.horaire-tooltip {
    position: relative;
    cursor: help;
}

.horaire-tooltip:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
    margin-bottom: 5px;
}

.horaire-tooltip:hover::before {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #1f2937;
    z-index: 1000;
}

/* Barres de progression pour le taux de présence */
.presence-progress {
    width: 100%;
    height: 8px;
    background-color: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}

.presence-progress-bar {
    height: 100%;
    transition: width 0.3s ease;
}

.presence-progress-bar.excellent {
    background: linear-gradient(90deg, #10b981, #34d399);
}

.presence-progress-bar.good {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}

.presence-progress-bar.poor {
    background: linear-gradient(90deg, #ef4444, #f87171);
}

/* Alertes contextuelles */
.alert-contextuelle {
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.alert-success {
    background-color: #d1fae5;
    border: 1px solid #a7f3d0;
    color: #065f46;
}

.alert-warning {
    background-color: #fef3c7;
    border: 1px solid #fde68a;
    color: #92400e;
}

.alert-danger {
    background-color: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.alert-info {
    background-color: #dbeafe;
    border: 1px solid #bfdbfe;
    color: #1e40af;
}

/* Badges de statut améliorés */
.status-badge-enhanced {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-present {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
    border: 1px solid #86efac;
}

.status-absent {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.status-retard {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    border: 1px solid #facc15;
}

.status-pause {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1e40af;
    border: 1px solid #93c5fd;
}

/* Animation de chargement pour les données de présence */
.loading-presence {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #e5e7eb;
    border-radius: 50%;
    border-top-color: #3b82f6;
    animation: spin-presence 1s ease-in-out infinite;
}

@keyframes spin-presence {
    to { transform: rotate(360deg); }
}

/* Tableau responsive pour les présences */
.presence-table {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.presence-table table {
    min-width: 800px;
}

@media (max-width: 768px) {
    .presence-card {
        margin-bottom: 12px;
    }
    
    .presence-details {
        flex-direction: column;
        gap: 8px;
    }
    
    .status-indicator {
        width: 8px;
        height: 8px;
    }
}

/* Styles pour les modales de présence */
.modal-presence {
    max-height: 90vh;
    overflow-y: auto;
}

.modal-presence .grid {
    gap: 1rem;
}

.modal-presence .stat-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    text-align: center;
}

.modal-presence .stat-value {
    font-size: 1.875rem;
    font-weight: 700;
    margin-bottom: 4px;
}

.modal-presence .stat-label {
    font-size: 0.875rem;
    color: #64748b;
}

/* Styles pour les graphiques de présence (si utilisés) */
.chart-container {
    position: relative;
    height: 300px;
    margin: 20px 0;
}

/* Styles pour les filtres de présence */
.presence-filters {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

.presence-filters .filter-group {
    display: flex;
    gap: 12px;
    align-items: end;
    flex-wrap: wrap;
}

.presence-filters .filter-item {
    display: flex;
    flex-direction: column;
    min-width: 150px;
}

.presence-filters .filter-item label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: 4px;
}

.presence-filters .filter-item select,
.presence-filters .filter-item input {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
}

/* Boutons d'action pour les présences */
.presence-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 16px;
}

.btn-presence {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-presence:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-presence.primary {
    background: #3b82f6;
    color: white;
}

.btn-presence.success {
    background: #10b981;
    color: white;
}

.btn-presence.warning {
    background: #f59e0b;
    color: white;
}

.btn-presence.danger {
    background: #ef4444;
    color: white;
}

.btn-presence.secondary {
    background: #6b7280;
    color: white;
}

/* Styles pour l'export et impression */
@media print {
    .presence-card {
        break-inside: avoid;
        margin-bottom: 16px;
    }
    
    .btn-presence,
    .presence-actions {
        display: none !important;
    }
    
    .modal-presence {
        max-height: none;
    }
}
    </style>
</head>
<body class="bg-gray-50">

    <!-- Injection des données PHP en JavaScript -->
    <script>
      window.initialData = {
    employes: <?php echo json_encode($employes, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    bulletins: <?php echo json_encode($bulletins, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    stats: <?php echo json_encode($stats, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    conges_attente: <?php echo json_encode($conges_attente, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    avances_attente: <?php echo json_encode($avances_attente, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    primes_attente: <?php echo json_encode($primes_attente, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    postes: <?php echo json_encode($postes, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    departements: <?php echo json_encode($departements, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    csrf_token: <?php echo json_encode($csrf_token, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
};
    </script>

    <!-- Structure avec sidebar -->
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../sidebar.php'; ?>

        <!-- Contenu principal -->
        <div class="flex-1 overflow-y-auto bg-gradient-to-br from-gray-50 to-gray-100">
            <div class="container mx-auto px-4 py-6">
        <!-- Header principal - Version moderne et épurée -->
        <div class="mb-8">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden mb-6 border border-gray-100">
                <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 px-8 py-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center space-x-4 mb-4 md:mb-0">
                            <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-xl p-4">
                                <i class="fas fa-utensils text-white text-3xl"></i>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-white tracking-tight">Restaurant Le Savoureux</h1>
                                <p class="text-white text-opacity-90 text-sm mt-1 font-medium">Système de Gestion RH Intégré</p>
                                <div class="flex items-center mt-2 space-x-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white bg-opacity-20 text-white backdrop-blur-sm">
                                        <i class="fas fa-calendar mr-2"></i><?php
                                        $mois_fr = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
                                        echo $mois_fr[date('n') - 1] . ' ' . date('Y');
                                        ?>
                                    </span>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white bg-opacity-20 text-white backdrop-blur-sm">
                                        <i class="fas fa-clock mr-2"></i><?php echo date('H:i'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="text-right">
                                <p class="text-white font-semibold text-lg">Admin RH</p>
                                <div class="flex items-center justify-end mt-1 space-x-2">
                                    <i class="fas fa-map-marker-alt text-white text-opacity-75 text-xs"></i>
                                    <p class="text-white text-opacity-90 text-sm">Dakar, Sénégal</p>
                                </div>
                                <p class="text-white text-opacity-75 text-xs mt-1">
                                    <i class="fas fa-user-check mr-1"></i>Connecté: <?php echo date('d/m/Y à H:i'); ?>
                                </p>
                            </div>
                            <div class="hidden md:block bg-white bg-opacity-20 backdrop-blur-sm rounded-full p-1">
                                <i class="fas fa-user-circle text-white text-4xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Barre d'actions rapides -->
                <div class="bg-gray-50 border-t border-gray-100 px-8 py-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <i class="fas fa-info-circle text-indigo-500"></i>
                            <span>Accès rapide aux fonctionnalités principales</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button onclick="window.location.reload()" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-sync-alt mr-2"></i>Actualiser
                            </button>
                            <button onclick="window.print()" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-print mr-2"></i>Imprimer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Tableau de Bord RH Avancé -->
            <div class="card p-6 mb-6 bg-white rounded-xl shadow-md">
                <h2 class="text-2xl font-semibold mb-6 text-gray-800">Tableau de Bord RH Avancé</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-2 rounded-lg bg-purple-100 text-purple-600 mr-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Effectif Total</p>
                                <p class="text-2xl font-bold text-gray-800" id="totalEmployes">0</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-2 rounded-lg bg-green-100 text-green-600 mr-3">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Taux de Présence</p>
                                <p class="text-2xl font-bold text-gray-800" id="tauxPresence">0%</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-2 rounded-lg bg-pink-100 text-pink-600 mr-3">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Masse Salariale</p>
                                <p class="text-2xl font-bold text-gray-800" id="masseSalariale">0 FCFA</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div class="flex items-center">
                            <div class="p-2 rounded-lg bg-orange-100 text-orange-600 mr-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Retard Moyen</p>
                                <p class="text-2xl font-bold text-gray-800" id="retardMoyen">0 min</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mt-4">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">Générer un Rapport Personnalisé</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Type de Rapport</label>
                            <select id="reportType" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-800 focus:border-green-500 focus:ring-2 focus:ring-green-200">
                                <option value="presences">Présences et Retards</option>
                                <option value="salaires">Salaires et Coûts</option>
                                <option value="effectifs">Effectifs et Démographie</option>
                                <option value="turnover">Turnover et Rotation</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date Début</label>
                            <input type="date" id="reportStartDate" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-800 focus:border-green-500 focus:ring-2 focus:ring-green-200">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date Fin</label>
                            <input type="date" id="reportEndDate" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-800 focus:border-green-500 focus:ring-2 focus:ring-green-200">
                        </div>
                    </div>
                    <button onclick="generateCustomReport()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-all duration-200 shadow-sm">
                        <i class="fas fa-file-export mr-2"></i>Générer le Rapport
                    </button>

                    <!-- Indicateur de chargement -->
                    <div id="reportLoading" class="hidden mt-4 text-center">
                        <i class="fas fa-spinner fa-spin text-green-600 text-2xl"></i>
                        <p class="text-gray-600">Génération du rapport en cours...</p>
                    </div>
                </div>

                <!-- Modal pour afficher les rapports -->
                <div id="reportModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50">
                    <div class="flex items-center justify-center min-h-screen p-4">
                        <div class="bg-white rounded-xl max-w-6xl w-full max-h-screen overflow-y-auto shadow-2xl">
                            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                <h3 id="reportModalTitle" class="text-lg font-semibold text-gray-800">Rapport Personnalisé</h3>
                                <button onclick="closeReportModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>
                            <div class="p-6">
                                <div id="reportContent" class="mb-6 text-gray-800">
                                    <!-- Le contenu du rapport sera chargé ici -->
                                </div>
                                <div class="flex justify-end space-x-3">
                                    <button onclick="exportReportToPDF()" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-all duration-200 shadow-sm">
                                        <i class="fas fa-file-pdf mr-2"></i>Exporter en PDF
                                    </button>
                                    <button onclick="exportReportToExcel()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-all duration-200 shadow-sm">
                                        <i class="fas fa-file-excel mr-2"></i>Exporter en Excel
                                    </button>
                                    <button onclick="closeReportModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-all duration-200">
                                        Fermer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistiques enrichies avec présences - Version moderne -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6" id="stats-container">
                <!-- Carte Employés -->
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-5 border border-gray-100 hover:border-blue-200 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <div class="p-2.5 rounded-lg bg-gradient-to-br from-blue-100 to-blue-50">
                                    <i class="fas fa-users text-blue-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Employés</p>
                            <p class="text-2xl font-bold text-gray-900" id="stat-employes"><?php echo h($stats['employes_actifs']); ?></p>
                        </div>
                        <div class="text-blue-600 opacity-20">
                            <i class="fas fa-users text-4xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Carte Bulletins -->
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-5 border border-gray-100 hover:border-green-200 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <div class="p-2.5 rounded-lg bg-gradient-to-br from-green-100 to-green-50">
                                    <i class="fas fa-file-invoice text-green-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Bulletins</p>
                            <p class="text-2xl font-bold text-gray-900" id="stat-bulletins"><?php echo h($stats['bulletins_mois']); ?></p>
                        </div>
                        <div class="text-green-600 opacity-20">
                            <i class="fas fa-file-invoice text-4xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Carte Présences -->
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-5 border border-gray-100 hover:border-purple-200 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <div class="p-2.5 rounded-lg bg-gradient-to-br from-purple-100 to-purple-50">
                                    <i class="fas fa-clock text-purple-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Présents</p>
                            <p class="text-2xl font-bold text-gray-900" id="stat-presences">-</p>
                        </div>
                        <div class="text-purple-600 opacity-20">
                            <i class="fas fa-clock text-4xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Carte Congés -->
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-5 border border-gray-100 hover:border-yellow-200 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <div class="p-2.5 rounded-lg bg-gradient-to-br from-yellow-100 to-yellow-50">
                                    <i class="fas fa-calendar-check text-yellow-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Congés</p>
                            <p class="text-2xl font-bold text-gray-900" id="stat-conges"><?php echo h($stats['conges_attente'] ?? 0); ?></p>
                        </div>
                        <div class="text-yellow-600 opacity-20">
                            <i class="fas fa-calendar-check text-4xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Carte Avances -->
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-5 border border-gray-100 hover:border-orange-200 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <div class="p-2.5 rounded-lg bg-gradient-to-br from-orange-100 to-orange-50">
                                    <i class="fas fa-hand-holding-usd text-orange-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Avances</p>
                            <p class="text-2xl font-bold text-gray-900" id="stat-avances"><?php echo h($stats['avances_attente'] ?? 0); ?></p>
                        </div>
                        <div class="text-orange-600 opacity-20">
                            <i class="fas fa-hand-holding-usd text-4xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Carte Masse salariale -->
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-300 p-5 border border-gray-100 hover:border-red-200 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <div class="p-2.5 rounded-lg bg-gradient-to-br from-red-100 to-red-50">
                                    <i class="fas fa-chart-line text-red-600 text-xl"></i>
                                </div>
                            </div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Masse</p>
                            <p class="text-lg font-bold text-gray-900 leading-tight" id="stat-masse"><?php echo formaterMontant($stats['masse_salariale']); ?></p>
                        </div>
                        <div class="text-red-600 opacity-20">
                            <i class="fas fa-chart-line text-4xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation par onglets - Version moderne -->
        <nav class="bg-white rounded-2xl shadow-lg mb-6 border border-gray-100 overflow-hidden">
            <div class="px-6">
                <div class="flex space-x-8 overflow-x-auto">
                    <button onclick="showTab('paie')" class="tab-btn tab-active px-6 py-4 font-semibold transition-all whitespace-nowrap">
                        <i class="fas fa-money-bill-wave mr-2"></i>Paie & Bulletins
                    </button>
                    <button onclick="showTab('presences')" class="tab-btn px-6 py-4 font-semibold transition-all hover:text-blue-600 whitespace-nowrap">
                        <i class="fas fa-clock mr-2"></i>Présences
                    </button>
                    <button onclick="showTab('conges')" class="tab-btn px-6 py-4 font-semibold transition-all hover:text-blue-600 whitespace-nowrap">
                        <i class="fas fa-calendar-alt mr-2"></i>Congés
                    </button>
                    <button onclick="showTab('avances')" class="tab-btn px-6 py-4 font-semibold transition-all hover:text-blue-600 whitespace-nowrap">
                        <i class="fas fa-hand-holding-usd mr-2"></i>Avances
                    </button>
                    <button onclick="showTab('primes')" class="tab-btn px-6 py-4 font-semibold transition-all hover:text-blue-600 whitespace-nowrap">
                        <i class="fas fa-award mr-2"></i>Primes
                    </button>
                    <button onclick="showTab('dashboard')" class="tab-btn px-6 py-4 font-semibold transition-all hover:text-blue-600 whitespace-nowrap">
                        <i class="fas fa-chart-dashboard mr-2"></i>Dashboard
                    </button>
                </div>
            </div>
        </nav>

        <!-- Contenu des onglets -->

        <!-- Onglet Paie & Bulletins -->
        <div id="paie-tab" class="tab-content active">
            <!-- Actions rapides paie -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Actions rapides - Paie</h3>
                    <div class="flex flex-wrap gap-3">
                        <button onclick="ouvrirModalGenerationIntegree()" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-plus mr-2"></i>Bulletin avec présences
                        </button>
                        <button onclick="ouvrirModalGenerationClassique()" 
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-file-alt mr-2"></i>Bulletin classique
                        </button>
                        <button onclick="genererBulletinsMasse()" 
                                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-users mr-2"></i>Génération en masse
                        </button>
                        <button onclick="exporterBulletins()" 
                                class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-download mr-2"></i>Exporter CSV
                        </button>
                        <button onclick="voirStatistiquesPaie()" 
                                class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-chart-bar mr-2"></i>Statistiques
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filtres Paie -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Filtres et recherche</h3>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <label for="filtre-mois" class="block text-sm font-medium text-gray-700 mb-1">Mois</label>
                            <select id="filtre-mois" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Tous les mois</option>
                                <?php for($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == date('n') ? 'selected' : ''; ?>>
                                        <?php 
                                        $mois_fr = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
                                        echo $mois_fr[$i]; 
                                        ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label for="filtre-annee" class="block text-sm font-medium text-gray-700 mb-1">Année</label>
                            <select id="filtre-annee" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Toutes les années</option>
                                <?php for($year = date('Y'); $year >= 2020; $year--): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $year == date('Y') ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label for="filtre-statut" class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                            <select id="filtre-statut" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Tous les statuts</option>
                                <option value="brouillon">Brouillon</option>
                                <option value="valide">Validé</option>
                                <option value="paye">Payé</option>
                            </select>
                        </div>
                        <div>
                            <label for="filtre-employe" class="block text-sm font-medium text-gray-700 mb-1">Employé</label>
                            <select id="filtre-employe" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Tous les employés</option>
                                <?php foreach($employes as $employe): ?>
                                    <option value="<?php echo h($employe['id']); ?>">
                                        <?php echo h($employe['prenom'] . ' ' . $employe['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button onclick="appliquerFiltresBulletins()" 
                                    class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors">
                                <i class="fas fa-search mr-2"></i>Filtrer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des bulletins -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Bulletins de paie</h3>
                        <div class="text-sm text-gray-500">
                            <span id="total-bulletins"><?php echo count($bulletins); ?></span> bulletins trouvés
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 table-responsive">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employé</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Période</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Salaire net</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Primes</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avances</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Présences</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tableau-bulletins" class="bg-white divide-y divide-gray-200">
                                <!-- Sera rempli par JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

      <!-- Onglet Présences -->
<div id="presences-tab" class="tab-content">
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Présences</h3>
                <div class="flex items-center space-x-3">
                    <label class="text-sm font-medium text-gray-700">Date :</label>
                    <input type="date" 
                           id="date-presences" 
                           value="<?php echo date('Y-m-d'); ?>"
                           class="border border-gray-300 rounded-md px-3 py-1"
                           onchange="changerDatePresences(this.value)">
                </div>
            </div>
            <div id="presences-jour" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Les présences s'afficheront ici -->
            </div>
        </div>
    </div>
</div>
        <!-- Onglet Congés -->
        <div id="conges-tab" class="tab-content">
            <!-- Actions rapides congés -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Actions rapides - Congés</h3>
                    <div class="flex flex-wrap gap-3">
                        <button onclick="ouvrirModalConge()" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-plus mr-2"></i>Nouvelle demande
                        </button>
                        <button onclick="voirCalendrierConges()" 
                                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-calendar mr-2"></i>Calendrier
                        </button>
                        <button onclick="initialiserSoldes()" 
                                class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-cog mr-2"></i>Init. soldes annuels
                        </button>
                    </div>
                </div>
            </div>

            <!-- Demandes en attente de validation -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Demandes en attente de validation</h3>
                    <div id="conges-en-attente" class="space-y-3">
                        <!-- Sera rempli par JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Historique des congés -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Historique des congés</h3>
                    <div id="historique-conges" class="overflow-x-auto">
                        <!-- Sera rempli par JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Avances -->
        <div id="avances-tab" class="tab-content">
            <!-- Actions rapides avances -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Actions rapides - Avances</h3>
                    <div class="flex flex-wrap gap-3">
                        <button onclick="ouvrirModalAvance()" 
                                class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-plus mr-2"></i>Nouvelle demande
                        </button>
                        <button onclick="voirRapportAvances()" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-chart-line mr-2"></i>Rapport mensuel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Liste des avances en attente -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Avances en attente de validation</h3>
                    <div id="avances-en-attente" class="space-y-3">
                        <!-- Sera rempli par JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Historique des avances -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Historique des avances</h3>
                    <div id="historique-avances" class="overflow-x-auto">
                        <!-- Sera rempli par JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Primes -->
        <div id="primes-tab" class="tab-content">
            <!-- Actions rapides primes -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Actions rapides - Primes</h3>
                    <div class="flex flex-wrap gap-3">
                        <button onclick="ouvrirModalPrime()" 
                                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-plus mr-2"></i>Attribuer prime
                        </button>
                        <button onclick="genererPrimesPresence()" 
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                            <i class="fas fa-magic mr-2"></i>Générer primes présence
                        </button>
                    </div>
                </div>
            </div>

            <!-- Liste des primes en attente -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Primes en attente de validation</h3>
                    <div id="primes-en-attente" class="space-y-3">
                        <!-- Sera rempli par JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Historique des primes -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Historique des primes</h3>
                    <div id="historique-primes" class="overflow-x-auto">
                        <!-- Sera rempli par JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Dashboard -->
        <div id="dashboard-tab" class="tab-content">
            <!-- Section supérieure - Stats principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- Carte Employés -->
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium mb-1">Employés Actifs</p>
                            <p class="text-3xl font-bold" id="dashboard-total-employes">0</p>
                            <p class="text-blue-100 text-xs mt-2">Total dans le système</p>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-full p-4">
                            <i class="fas fa-users text-3xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Carte Bulletins -->
                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium mb-1">Bulletins Ce Mois</p>
                            <p class="text-3xl font-bold" id="dashboard-total-bulletins">0</p>
                            <p class="text-green-100 text-xs mt-2">Générés en <?php echo date('F Y'); ?></p>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-full p-4">
                            <i class="fas fa-file-invoice text-3xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Carte Masse Salariale -->
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium mb-1">Masse Salariale</p>
                            <p class="text-2xl font-bold" id="dashboard-masse-salariale">0 FCFA</p>
                            <p class="text-purple-100 text-xs mt-2">Ce mois</p>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-full p-4">
                            <i class="fas fa-money-bill-wave text-3xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Carte Taux de Présence -->
                <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition-transform duration-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium mb-1">Taux de Présence</p>
                            <p class="text-3xl font-bold" id="dashboard-taux-presence">0%</p>
                            <p class="text-orange-100 text-xs mt-2">Moyenne mensuelle</p>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-full p-4">
                            <i class="fas fa-clock text-3xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Actions en attente -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow duration-200 overflow-hidden border-l-4 border-yellow-500">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Congés en Attente</h3>
                            <div class="bg-yellow-100 rounded-full p-3">
                                <i class="fas fa-calendar-alt text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="text-4xl font-bold text-yellow-600" id="dashboard-conges-attente">0</div>
                        <p class="text-gray-500 text-sm mt-2">Demandes à valider</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow duration-200 overflow-hidden border-l-4 border-orange-500">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Avances en Attente</h3>
                            <div class="bg-orange-100 rounded-full p-3">
                                <i class="fas fa-hand-holding-usd text-orange-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="text-4xl font-bold text-orange-600" id="dashboard-avances-attente">0</div>
                        <p class="text-gray-500 text-sm mt-2">Demandes à approuver</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow duration-200 overflow-hidden border-l-4 border-red-500">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Primes à Valider</h3>
                            <div class="bg-red-100 rounded-full p-3">
                                <i class="fas fa-award text-red-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="text-4xl font-bold text-red-600" id="dashboard-primes-attente">0</div>
                        <p class="text-gray-500 text-sm mt-2">En attente de validation</p>
                    </div>
                </div>
            </div>

            <!-- Section Graphiques et statistiques -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Répartition par département -->
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow duration-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                        <h4 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-building mr-3"></i>
                            Répartition par département
                        </h4>
                    </div>
                    <div class="p-6">
                        <div id="repartition-departements" class="space-y-3">
                            <!-- Sera rempli par JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Types de contrats -->
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow duration-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                        <h4 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-file-contract mr-3"></i>
                            Types de contrats
                        </h4>
                    </div>
                    <div class="p-6">
                        <div id="types-contrats" class="space-y-3">
                            <!-- Sera rempli par JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Indicateurs clés -->
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow duration-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                        <h4 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-chart-line mr-3"></i>
                            Indicateurs clés
                        </h4>
                    </div>
                    <div class="p-6">
                        <div id="indicateurs-cles" class="space-y-4">
                            <!-- Sera rempli par JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphique des présences sur 7 jours -->
            <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition-shadow duration-200 overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 px-6 py-4">
                    <h4 class="text-lg font-semibold text-white flex items-center">
                        <i class="fas fa-chart-area mr-3"></i>
                        Évolution des présences (7 derniers jours)
                    </h4>
                </div>
                <div class="p-6">
                    <div id="graphique-presences" class="h-64 flex items-center justify-center bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                        <div class="text-center">
                            <i class="fas fa-chart-line text-gray-400 text-4xl mb-3"></i>
                            <p class="text-gray-500">Graphique des présences à venir</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Zone de notification -->
    <div id="notification" class="notification"></div>

    <!-- Scripts JavaScript complets -->
    <script>
    // ================== GESTION DES VARIABLES GLOBALES ==================
let employes = window.initialData?.employes || [];
let bulletins = window.initialData?.bulletins || [];
let stats = window.initialData?.stats || {};
let conges_attente = window.initialData?.conges_attente || [];
let avances_attente = window.initialData?.avances_attente || [];
let primes_attente = window.initialData?.primes_attente || [];
let postes = window.initialData?.postes || [];
let departements = window.initialData?.departements || [];
let csrf_token = window.initialData?.csrf_token || '';

// ================== SYSTÈME DE NOTIFICATIONS ==================
const NotificationManager = {
    show: function(message, type = 'info', duration = 4000) {
        const colors = {
            'success': 'bg-green-500',
            'error': 'bg-red-500', 
            'warning': 'bg-yellow-500',
            'info': 'bg-blue-500'
        };
        
        const existing = document.querySelector('.notification-custom');
        if (existing) existing.remove();
        
        const notification = document.createElement('div');
        notification.className = `notification-custom fixed top-5 right-5 z-50 px-6 py-3 text-white font-medium rounded-lg shadow-lg ${colors[type] || colors.info}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, duration);
    }
};

// ================== SYSTÈME DE MODALES ==================
const ModalManager = {
    create: function(id, title, content, footer = '', size = 'default') {
        const existingModal = document.getElementById(id);
        if (existingModal) {
            existingModal.remove();
        }
        
        const sizes = {
            'small': 'max-w-md',
            'default': 'max-w-2xl',
            'large': 'max-w-4xl',
            'xlarge': 'max-w-6xl',
            'full': 'max-w-7xl'
        };
        
        const modalSize = sizes[size] || sizes.default;
        
        const modal = document.createElement('div');
        modal.id = id;
        modal.className = 'fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50 p-4 overflow-y-auto';
        modal.innerHTML = `
            <div class="bg-white rounded-xl shadow-2xl w-full ${modalSize} my-4 mx-auto">
                ${title ? `
                <div class="flex items-center justify-between p-6 border-b border-gray-200 flex-shrink-0">
                    <h2 class="text-xl font-bold text-gray-900">${title}</h2>
                    <button onclick="ModalManager.close('${id}')" class="text-gray-400 hover:text-gray-600 text-2xl transition-colors">
                        &times;
                    </button>
                </div>
                ` : `
                <div class="flex justify-end p-4 flex-shrink-0">
                    <button onclick="ModalManager.close('${id}')" class="text-gray-400 hover:text-gray-600 text-2xl transition-colors">
                        &times;
                    </button>
                </div>
                `}
                <div class="p-6 overflow-visible">
                    ${content}
                </div>
                ${footer ? `<div class="flex justify-end space-x-3 p-6 border-t border-gray-200 bg-gray-50 rounded-b-xl flex-shrink-0">${footer}</div>` : ''}
            </div>
        `;
        
        document.body.appendChild(modal);
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                ModalManager.close(id);
            }
        });
        
        return modal;
    },

    open: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    },

    close: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }
};

// ================== UTILITAIRES ==================
const Utils = {
    formatAmount: function(montant) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'XOF',
            minimumFractionDigits: 0
        }).format(montant || 0).replace('XOF', 'FCFA');
    },

    formatPeriod: function(mois, annee) {
        const moisFr = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 
                    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        return `${moisFr[parseInt(mois)]} ${annee}`;
    },

    capitalize: function(str) {
        return str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ');
    },

    apiCall: async function(action, data = {}, method = 'GET') {
        const config = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };

        if (method === 'POST') {
            config.body = JSON.stringify({...data, csrf_token: csrf_token});
        }

        const url = method === 'GET' && Object.keys(data).length > 0 
            ? `?action=${action}&${new URLSearchParams(data).toString()}`
            : `?action=${action}`;

        try {
            const response = await fetch(url, config);
            return await response.json();
        } catch (error) {
            console.error('Erreur API:', error);
            throw error;
        }
    }
};

// ================== GESTIONNAIRE D'ONGLETS ==================
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('tab-active');
    });
    
    const tabContent = document.getElementById(tabName + '-tab');
    if (tabContent) {
        tabContent.classList.add('active');
    }
    
    event.target.classList.add('tab-active');

    // Charger les données spécifiques à l'onglet
    switch(tabName) {
        case 'paie':
            chargerBulletins();
            break;
        case 'presences':
            chargerPresences();
            break;
        case 'conges':
            chargerConges();
            break;
        case 'avances':
            chargerAvances();
            break;
        case 'primes':
            chargerPrimes();
            break;
        case 'dashboard':
            chargerDashboard();
            break;
    }
}

// ================== ACTIONS BULLETINS INTÉGRÉES ==================
function ouvrirModalGenerationIntegree() {
    const content = `
        <form id="formBulletinIntegre" class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    <span class="text-sm text-blue-800">Ce bulletin intègrera automatiquement les données de présence de l'employé</span>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Employé *</label>
                <select name="employe_id" id="employe_select" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Sélectionner un employé</option>
                    ${employes.map(emp => `<option value="${emp.id}">${emp.prenom} ${emp.nom} - ${emp.poste_nom || 'Poste non défini'}</option>`).join('')}
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Période de paie *</label>
                <input type="month" name="periode" required 
                       value="${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>

            <div id="presence-preview" class="hidden bg-gray-50 border border-gray-200 rounded-lg p-4">
                <h4 class="font-medium text-gray-900 mb-2">Aperçu des présences</h4>
                <div id="presence-stats" class="text-sm text-gray-600">
                    <!-- Sera rempli par JavaScript -->
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Heures supplémentaires</label>
                    <input type="number" name="heures_supplementaires" min="0" step="0.5" value="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ajustements manuels (jours)</label>
                    <input type="number" name="ajustements_jours" min="-31" max="31" value="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Commentaires</label>
                <textarea name="commentaires" rows="3" 
                          class="w-full border border-gray-300 rounded-lg px-3 py-2"
                          placeholder="Commentaires optionnels..."></textarea>
            </div>
        </form>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalBulletinIntegre')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Annuler
        </button>
        <button onclick="genererBulletinIntegre()" 
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md">
            Générer avec présences
        </button>
    `;
    
    ModalManager.create('modalBulletinIntegre', 'Générer un bulletin avec présences', content, footer, 'large');
    ModalManager.open('modalBulletinIntegre');

    // Écouteur pour prévisualiser les présences
    document.getElementById('employe_select').addEventListener('change', function() {
        if (this.value) {
            previewPresences(this.value);
        } else {
            document.getElementById('presence-preview').classList.add('hidden');
        }
    });
}

function ouvrirModalGenerationClassique() {
    const content = `
        <form id="formBulletinClassique" class="space-y-4">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                    <span class="text-sm text-yellow-800">Mode classique - saisie manuelle des données de paie</span>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Employé *</label>
                <select name="employe_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Sélectionner un employé</option>
                    ${employes.map(emp => `<option value="${emp.id}">${emp.prenom} ${emp.nom} - ${emp.poste_nom || 'Poste non défini'}</option>`).join('')}
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Période de paie *</label>
                <input type="month" name="periode" required 
                       value="${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Heures supplémentaires</label>
                    <input type="number" name="heures_supplementaires" min="0" step="0.5" value="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Jours d'absence</label>
                    <input type="number" name="jours_absence" min="0" max="31" value="0"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Commentaires</label>
                <textarea name="commentaires" rows="3" 
                          class="w-full border border-gray-300 rounded-lg px-3 py-2"
                          placeholder="Commentaires optionnels..."></textarea>
            </div>
        </form>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalBulletinClassique')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Annuler
        </button>
        <button onclick="genererBulletinClassique()" 
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md">
            Générer bulletin
        </button>
    `;
    
    ModalManager.create('modalBulletinClassique', 'Générer un bulletin classique', content, footer);
    ModalManager.open('modalBulletinClassique');
}

async function previewPresences(employeId) {
    try {
        const periode = document.querySelector('input[name="periode"]').value.split('-');
        const result = await Utils.apiCall('get_presence_stats_for_payroll', {
            employee_id: employeId,
            month: periode[1],
            year: periode[0]
        });

        if (result.success) {
            const stats = result.presence_stats;
            document.getElementById('presence-stats').innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="font-medium">Jours travaillés:</span> ${stats.jours_travailles || 0}
                    </div>
                    <div>
                        <span class="font-medium">Heures totales:</span> ${stats.heures_totales || 0}h
                    </div>
                    <div>
                        <span class="font-medium">Retards:</span> ${stats.nb_retards || 0}
                    </div>
                    <div>
                        <span class="font-medium">Absences:</span> ${stats.nb_absences || 0}
                    </div>
                </div>
            `;
            document.getElementById('presence-preview').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Erreur preview présences:', error);
    }
}

async function genererBulletinIntegre() {
    const form = document.getElementById('formBulletinIntegre');
    if (!form) return;
    
    const formData = new FormData(form);
    const periode = formData.get('periode').split('-');
    
    const data = {
        employe_id: parseInt(formData.get('employe_id')),
        mois: parseInt(periode[1]),
        annee: parseInt(periode[0]),
        heures_supplementaires: parseFloat(formData.get('heures_supplementaires')) || 0,
        ajustements_jours: parseInt(formData.get('ajustements_jours')) || 0,
        commentaires: formData.get('commentaires') || '',
        avec_presences: true
    };
    
    if (!data.employe_id) {
        NotificationManager.show('Veuillez sélectionner un employé', 'error');
        return;
    }
    
    try {
        const result = await Utils.apiCall('generer_bulletin_integre', data, 'POST');
        
        if (result.success) {
            ModalManager.close('modalBulletinIntegre');
            NotificationManager.show('Bulletin généré avec succès (avec présences)', 'success');
            setTimeout(() => chargerBulletins(), 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function genererBulletinClassique() {
    const form = document.getElementById('formBulletinClassique');
    if (!form) return;
    
    const formData = new FormData(form);
    const periode = formData.get('periode').split('-');
    
    const data = {
        employe_id: parseInt(formData.get('employe_id')),
        mois: parseInt(periode[1]),
        annee: parseInt(periode[0]),
        heures_supplementaires: parseFloat(formData.get('heures_supplementaires')) || 0,
        jours_absence: parseInt(formData.get('jours_absence')) || 0,
        commentaires: formData.get('commentaires') || ''
    };
    
    if (!data.employe_id) {
        NotificationManager.show('Veuillez sélectionner un employé', 'error');
        return;
    }
    
    try {
        const result = await Utils.apiCall('generer_bulletin', data, 'POST');
        
        if (result.success) {
            ModalManager.close('modalBulletinClassique');
            NotificationManager.show('Bulletin généré avec succès', 'success');
            setTimeout(() => chargerBulletins(), 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

// ================== CHARGEMENT DES DONNÉES ==================
async function chargerBulletins() {
    try {
        // Utiliser les bulletins déjà chargés depuis PHP
        let bulletinsFiltres = [...bulletins];

        // Appliquer les filtres si présents
        const mois = document.getElementById('filtre-mois')?.value;
        const annee = document.getElementById('filtre-annee')?.value;
        const statut = document.getElementById('filtre-statut')?.value;
        const employe_id = document.getElementById('filtre-employe')?.value;

        if (mois) {
            bulletinsFiltres = bulletinsFiltres.filter(b => b.mois == mois);
        }
        if (annee) {
            bulletinsFiltres = bulletinsFiltres.filter(b => b.annee == annee);
        }
        if (statut) {
            bulletinsFiltres = bulletinsFiltres.filter(b => b.statut == statut);
        }
        if (employe_id) {
            bulletinsFiltres = bulletinsFiltres.filter(b => b.employe_id == employe_id);
        }

        afficherBulletins(bulletinsFiltres);
        const totalElement = document.getElementById('total-bulletins');
        if (totalElement) {
            totalElement.textContent = bulletinsFiltres.length;
        }
    } catch (error) {
        console.error('Erreur:', error);
    }
}

function afficherBulletins(bulletins) {
    const tbody = document.getElementById('tableau-bulletins');
    
    if (!tbody) {
        console.error('Élément tableau-bulletins introuvable');
        return;
    }
    
    console.log('Affichage de', bulletins.length, 'bulletins');
    
    if (!bulletins || bulletins.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-8">
                    <i class="fas fa-inbox text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500">Aucun bulletin trouvé</p>
                    <button onclick="ouvrirModalGenerationIntegree()" 
                            class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md">
                        <i class="fas fa-plus mr-2"></i>Créer le premier bulletin
                    </button>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = bulletins.map(bulletin => {
        console.log('Traitement bulletin:', bulletin);
        
        // CORRECTION: Gestion flexible des noms d'employés
        const employeNom = bulletin.employe_nom || bulletin.nom || '';
        const employePrenom = bulletin.employe_prenom || bulletin.prenom || '';
        const posteNom = bulletin.poste_nom || 'Poste non défini';
        
        // CORRECTION: ID du bulletin
        const bulletinId = bulletin.id || bulletin.id_bulletin;
        
        if (!bulletinId) {
            console.error('Bulletin sans ID:', bulletin);
            return '';
        }
        
        const avecPresences = bulletin.avec_presences ? 
            '<span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Avec présences</span>' : '';
        
        // CORRECTION: Formatage sécurisé de la période
        let periode = 'Non définie';
        if (bulletin.mois && bulletin.annee) {
            periode = Utils.formatPeriod(bulletin.mois, bulletin.annee);
        }
        
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">
                        ${employePrenom} ${employeNom}
                    </div>
                    <div class="text-sm text-gray-500">
                        ${posteNom}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${periode}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    ${Utils.formatAmount(bulletin.salaire_net || 0)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${Utils.formatAmount(bulletin.total_primes || 0)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${Utils.formatAmount(bulletin.total_avances || 0)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${avecPresences}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="status-badge status-${bulletin.statut || 'brouillon'}">
                        ${Utils.capitalize(bulletin.statut || 'brouillon')}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex justify-end space-x-1">
                        <button onclick="voirBulletin(${bulletinId})" 
                                class="text-blue-600 hover:text-blue-900 p-1" title="Voir">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="telechargerBulletin(${bulletinId})" 
                                class="text-green-600 hover:text-green-900 p-1" title="Télécharger">
                            <i class="fas fa-download"></i>
                        </button>
                        ${(bulletin.statut || '') === 'brouillon' ? `
                            <button onclick="validerBulletin(${bulletinId})" 
                                    class="text-purple-600 hover:text-purple-900 p-1" title="Valider">
                                <i class="fas fa-check"></i>
                            </button>
                            <button onclick="modifierBulletin(${bulletinId})" 
                                    class="text-yellow-600 hover:text-yellow-900 p-1" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="supprimerBulletin(${bulletinId})" 
                                    class="text-red-600 hover:text-red-900 p-1" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).filter(row => row !== '').join('');
    
    console.log('Affichage terminé');
}
// ================== GESTION CONGÉS COMPLÈTE ==================
function ouvrirModalConge() {
    const content = `
        <form id="formNouveauConge" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Employé *</label>
                <select name="employe_id" id="conge_employe_select" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Sélectionner un employé</option>
                    ${employes.map(emp => `<option value="${emp.id}">${emp.prenom} ${emp.nom} - ${emp.poste_nom || 'Poste non défini'}</option>`).join('')}
                </select>
            </div>

            <div id="solde-conges" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-medium text-gray-900 mb-2">Solde de congés</h4>
                <div id="solde-details" class="text-sm text-gray-600">
                    <!-- Sera rempli par JavaScript -->
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Type de congé *</label>
                <select name="type_conge" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Sélectionner un type</option>
                    <option value="annuel">Congé annuel</option>
                    <option value="maladie">Congé maladie</option>
                    <option value="maternite">Congé maternité</option>
                    <option value="paternite">Congé paternité</option>
                    <option value="exceptionnel">Congé exceptionnel</option>
                    <option value="sans_solde">Sans solde</option>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date de début *</label>
                    <input type="date" name="date_debut" required 
                           min="${new Date().toISOString().split('T')[0]}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date de fin *</label>
                    <input type="date" name="date_fin" required 
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>

            <div id="duree-preview" class="hidden bg-gray-50 border border-gray-200 rounded-lg p-3">
                <span class="text-sm text-gray-600">Durée: <span id="nb-jours-calcule" class="font-medium">0</span> jour(s)</span>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Motif</label>
                <textarea name="motif" rows="3" 
                          class="w-full border border-gray-300 rounded-lg px-3 py-2"
                          placeholder="Précisez le motif de la demande..."></textarea>
            </div>
        </form>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalNouveauConge')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Annuler
        </button>
        <button onclick="creerDemandeConge()" 
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md">
            Créer la demande
        </button>
    `;
    
    ModalManager.create('modalNouveauConge', 'Nouvelle demande de congé', content, footer, 'large');
    ModalManager.open('modalNouveauConge');

    // Écouteurs d'événements
    document.getElementById('conge_employe_select').addEventListener('change', function() {
        if (this.value) {
            chargerSoldeConges(this.value);
        } else {
            document.getElementById('solde-conges').classList.add('hidden');
        }
    });

    // Calcul automatique de la durée
    const dateDebut = document.querySelector('input[name="date_debut"]');
    const dateFin = document.querySelector('input[name="date_fin"]');
    
    function calculerDuree() {
        if (dateDebut.value && dateFin.value) {
            const debut = new Date(dateDebut.value);
            const fin = new Date(dateFin.value);
            const diffTime = fin - debut;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            if (diffDays > 0) {
                document.getElementById('nb-jours-calcule').textContent = diffDays;
                document.getElementById('duree-preview').classList.remove('hidden');
            } else {
                document.getElementById('duree-preview').classList.add('hidden');
            }
        } else {
            document.getElementById('duree-preview').classList.add('hidden');
        }
    }

    dateDebut.addEventListener('change', calculerDuree);
    dateFin.addEventListener('change', calculerDuree);

    // S'assurer que la date de fin est postérieure à la date de début
    dateDebut.addEventListener('change', function() {
        dateFin.min = this.value;
    });
}

async function validerBulletin(id) {
    if (!confirm('Êtes-vous sûr de vouloir valider ce bulletin ? Une fois validé, il ne pourra plus être modifié.')) {
        return;
    }
    
    try {
        const result = await Utils.apiCall('valider_bulletin', { bulletin_id: id }, 'POST');
        
        if (result.success) {
            NotificationManager.show('Bulletin validé avec succès', 'success');
            setTimeout(() => chargerBulletins(), 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function chargerSoldeConges(employeId) {
    try {
        const result = await Utils.apiCall('get_solde_conges', { employe_id: employeId });
        
        if (result.success) {
            const solde = result.solde;
            document.getElementById('solde-details').innerHTML = `
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="font-medium">Congés annuels:</span> ${solde.annuel || 25} jours
                    </div>
                    <div>
                        <span class="font-medium">Congés maladie:</span> ${solde.maladie || 0} jours
                    </div>
                </div>
                <div class="mt-2 text-xs text-gray-500">
                    Dernière mise à jour: ${solde.derniere_maj || 'Non définie'}
                </div>
            `;
            document.getElementById('solde-conges').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Erreur chargement solde:', error);
    }
}

async function creerDemandeConge() {
    const form = document.getElementById('formNouveauConge');
    if (!form) return;
    
    const formData = new FormData(form);
    
    const data = {
        employe_id: parseInt(formData.get('employe_id')),
        type_conge: formData.get('type_conge'),
        date_debut: formData.get('date_debut'),
        date_fin: formData.get('date_fin'),
        motif: formData.get('motif') || ''
    };
    
    if (!data.employe_id || !data.type_conge || !data.date_debut || !data.date_fin) {
        NotificationManager.show('Veuillez remplir tous les champs obligatoires', 'error');
        return;
    }
    
    try {
        const result = await Utils.apiCall('creer_conge', data, 'POST');
        
        if (result.success) {
            ModalManager.close('modalNouveauConge');
            NotificationManager.show('Demande de congé créée avec succès', 'success');
            setTimeout(() => chargerConges(), 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function chargerConges() {
    try {
        // Charger congés en attente
        afficherCongesEnAttente(conges_attente);
        
        // Charger historique des congés
        const result = await Utils.apiCall('get_conges_historique');
        if (result.success) {
            afficherHistoriqueConges(result.conges);
        }
    } catch (error) {
        console.error('Erreur chargement congés:', error);
    }
}

function afficherHistoriqueConges(conges) {
    const container = document.getElementById('historique-conges');
    
    if (conges.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-history text-4xl text-gray-400 mb-4"></i>
                <p>Aucun historique de congé trouvé</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="mb-4 flex flex-wrap gap-2">
            <input type="date" id="filtre-debut-conges" class="border border-gray-300 rounded px-3 py-1 text-sm" placeholder="Date début">
            <input type="date" id="filtre-fin-conges" class="border border-gray-300 rounded px-3 py-1 text-sm" placeholder="Date fin">
            <select id="filtre-statut-conges" class="border border-gray-300 rounded px-3 py-1 text-sm">
                <option value="">Tous les statuts</option>
                <option value="en_attente">En attente</option>
                <option value="approuve">Approuvé</option>
                <option value="refuse">Refusé</option>
            </select>
            <select id="filtre-employe-conges" class="border border-gray-300 rounded px-3 py-1 text-sm">
                <option value="">Tous les employés</option>
                ${employes.map(emp => `<option value="${emp.id}">${emp.prenom} ${emp.nom}</option>`).join('')}
            </select>
            <button onclick="filtrerHistoriqueConges()" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded">
                Filtrer
            </button>
        </div>
        
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employé</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Période</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durée</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date demande</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                ${conges.map(conge => {
                    const employe = employes.find(emp => emp.id == conge.employe_id);
                    const statusConfig = {
                        'approuve': { class: 'bg-green-100 text-green-800', text: 'Approuvé' },
                        'en_attente': { class: 'bg-yellow-100 text-yellow-800', text: 'En attente' },
                        'refuse': { class: 'bg-red-100 text-red-800', text: 'Refusé' }
                    };
                    const status = statusConfig[conge.statut] || statusConfig['en_attente'];
                    
                    return `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    ${employe ? `${employe.prenom} ${employe.nom}` : 'Employé inconnu'}
                                </div>
                                <div class="text-sm text-gray-500">
                                    ${employe ? (employe.poste_nom || 'Poste non défini') : ''}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${Utils.capitalize(conge.type || 'Non défini')}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                Du ${new Date(conge.date_debut).toLocaleDateString('fr-FR')} <br>
                                au ${new Date(conge.date_fin).toLocaleDateString('fr-FR')}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${conge.nb_jours || 0} jour(s)
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 rounded text-xs font-medium ${status.class}">
                                    ${status.text}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${new Date(conge.date_creation).toLocaleDateString('fr-FR')}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                ${conge.statut === 'en_attente' ? `
                                    <button onclick="approuverConge(${conge.id})" 
                                            class="text-green-600 hover:text-green-900 mr-2" title="Approuver">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button onclick="refuserConge(${conge.id})" 
                                            class="text-red-600 hover:text-red-900" title="Refuser">
                                        <i class="fas fa-times"></i>
                                    </button>
                                ` : `
                                    <button onclick="voirDetailsConge(${conge.id})" 
                                            class="text-blue-600 hover:text-blue-900" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                `}
                            </td>
                        </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
    `;
}
async function filtrerHistoriqueConges() {
    const filters = {
        debut: document.getElementById('filtre-debut-conges')?.value || '',
        fin: document.getElementById('filtre-fin-conges')?.value || '',
        statut: document.getElementById('filtre-statut-conges')?.value || '',
        employe_id: document.getElementById('filtre-employe-conges')?.value || ''
    };
    
    try {
        const result = await Utils.apiCall('get_conges_historique', filters);
        if (result.success) {
            afficherHistoriqueConges(result.conges);
        }
    } catch (error) {
        console.error('Erreur filtrage congés:', error);
        NotificationManager.show('Erreur lors du filtrage', 'error');
    }
}


function afficherCongesEnAttente(conges) {
    const container = document.getElementById('conges-en-attente');
    
    if (conges.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-gray-500">
                <i class="fas fa-check-circle text-2xl text-green-500 mb-2"></i>
                <p>Aucune demande en attente</p>
            </div>
        `;
        return;
    }

    container.innerHTML = conges.map(conge => {
        const employe = employes.find(emp => emp.id == conge.employe_id);
        return `
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex items-center justify-between">
                <div>
                    <h4 class="font-medium text-gray-900">
                        ${employe ? `${employe.prenom} ${employe.nom}` : 'Employé inconnu'}
                    </h4>
                    <p class="text-sm text-gray-600">
                        ${Utils.capitalize(conge.type || 'Non défini')} - ${conge.nb_jours || 0} jour(s)
                    </p>
                    <p class="text-sm text-gray-500">
                        Du ${new Date(conge.date_debut).toLocaleDateString('fr-FR')} 
                        au ${new Date(conge.date_fin).toLocaleDateString('fr-FR')}
                    </p>
                    ${conge.motif ? `<p class="text-xs text-gray-400 mt-1">${conge.motif}</p>` : ''}
                </div>
                <div class="flex space-x-2">
                    <button onclick="approuverConge(${conge.id})" 
                            class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-sm rounded">
                        <i class="fas fa-check mr-1"></i>Approuver
                    </button>
                    <button onclick="refuserConge(${conge.id})" 
                            class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-sm rounded">
                        <i class="fas fa-times mr-1"></i>Refuser
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

// ================== GESTION AVANCES COMPLÈTE ==================
function ouvrirModalAvance() {
    const content = `
        <form id="formNouvelleAvance" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Employé *</label>
                <select name="employe_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Sélectionner un employé</option>
                    ${employes.map(emp => `<option value="${emp.id}">${emp.prenom} ${emp.nom} - ${emp.poste_nom || 'Poste non défini'}</option>`).join('')}
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Montant demandé (FCFA) *</label>
                <input type="number" name="montant" required min="1000" step="1000"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2"
                       placeholder="Ex: 50000">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mode de remboursement *</label>
                <select name="mode_remboursement" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="UNIQUE">Remboursement unique</option>
                    <option value="MENSUEL_2">2 mensualités</option>
                    <option value="MENSUEL_3">3 mensualités</option>
                    <option value="MENSUEL_6">6 mensualités</option>
                </select>
            </div>

            <div id="remboursement-details" class="hidden bg-gray-50 border border-gray-200 rounded-lg p-3">
                <div class="text-sm text-gray-600">
                    <div>Mode: <span id="mode-detail" class="font-medium"></span></div>
                    <div>Montant par mensualité: <span id="montant-mensuel" class="font-medium"></span></div>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Motif de la demande *</label>
                <textarea name="motif" required rows="3" 
                          class="w-full border border-gray-300 rounded-lg px-3 py-2"
                          placeholder="Précisez le motif de votre demande d'avance..."></textarea>
            </div>

            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-orange-600 mr-2 mt-1"></i>
                    <div class="text-sm text-orange-800">
                        <p class="font-medium mb-1">Important:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>L'avance sera déduite automatiquement de vos prochains salaires</li>
                            <li>Le montant maximum est généralement de 50% du salaire mensuel</li>
                            <li>Cette demande doit être validée par les RH</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalNouvelleAvance')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Annuler
        </button>
        <button onclick="creerDemandeAvance()" 
                class="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-md">
            Créer la demande
        </button>
    `;
    
    ModalManager.create('modalNouvelleAvance', 'Nouvelle demande d\'avance sur salaire', content, footer, 'large');
    ModalManager.open('modalNouvelleAvance');

    // Calcul automatique des mensualités
    const montantInput = document.querySelector('input[name="montant"]');
    const modeSelect = document.querySelector('select[name="mode_remboursement"]');
    
    function calculerMensualites() {
        const montant = parseFloat(montantInput.value) || 0;
        const mode = modeSelect.value;
        
        if (montant > 0 && mode !== 'UNIQUE') {
            const nbMensualites = parseInt(mode.split('_')[1]);
            const montantMensuel = Math.ceil(montant / nbMensualites);
            
            document.getElementById('mode-detail').textContent = `${nbMensualites} mensualités`;
            document.getElementById('montant-mensuel').textContent = Utils.formatAmount(montantMensuel);
            document.getElementById('remboursement-details').classList.remove('hidden');
        } else {
            document.getElementById('remboursement-details').classList.add('hidden');
        }
    }

    montantInput.addEventListener('input', calculerMensualites);
    modeSelect.addEventListener('change', calculerMensualites);
}

async function creerDemandeAvance() {
    const form = document.getElementById('formNouvelleAvance');
    if (!form) return;
    
    const formData = new FormData(form);
    
    const data = {
        employe_id: parseInt(formData.get('employe_id')),
        montant: parseFloat(formData.get('montant')),
        mode_remboursement: formData.get('mode_remboursement'),
        motif: formData.get('motif')
    };
    
    if (!data.employe_id || !data.montant || !data.motif) {
        NotificationManager.show('Veuillez remplir tous les champs obligatoires', 'error');
        return;
    }
    
    if (data.montant < 1000) {
        NotificationManager.show('Le montant minimum est de 1 000 FCFA', 'error');
        return;
    }
    
    try {
        const result = await Utils.apiCall('creer_avance', data, 'POST');
        
        if (result.success) {
            ModalManager.close('modalNouvelleAvance');
            NotificationManager.show('Demande d\'avance créée avec succès', 'success');
            setTimeout(() => chargerAvances(), 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function chargerAvances() {
    try {
        // Afficher avances en attente
        afficherAvancesEnAttente(avances_attente);
        
        // Charger historique des avances
        const result = await Utils.apiCall('get_avances_historique');
        if (result.success) {
            afficherHistoriqueAvances(result.avances);
        }
    } catch (error) {
        console.error('Erreur chargement avances:', error);
    }
}

function afficherHistoriqueAvances(avances) {
    const container = document.getElementById('historique-avances');
    
    if (avances.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-history text-4xl text-gray-400 mb-4"></i>
                <p>Aucun historique d'avance trouvé</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="mb-4 flex flex-wrap gap-2">
            <input type="date" id="filtre-debut-avances" class="border border-gray-300 rounded px-3 py-1 text-sm" placeholder="Date début">
            <input type="date" id="filtre-fin-avances" class="border border-gray-300 rounded px-3 py-1 text-sm" placeholder="Date fin">
            <select id="filtre-statut-avances" class="border border-gray-300 rounded px-3 py-1 text-sm">
                <option value="">Tous les statuts</option>
                <option value="en_attente">En attente</option>
                <option value="approuve">Approuvé</option>
                <option value="refuse">Refusé</option>
                <option value="rembourse">Remboursé</option>
            </select>
            <select id="filtre-employe-avances" class="border border-gray-300 rounded px-3 py-1 text-sm">
                <option value="">Tous les employés</option>
                ${employes.map(emp => `<option value="${emp.id}">${emp.prenom} ${emp.nom}</option>`).join('')}
            </select>
            <button onclick="filtrerHistoriqueAvances()" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded">
                Filtrer
            </button>
        </div>
        
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employé</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Motif</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mensualités</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date demande</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                ${avances.map(avance => {
                    const employe = employes.find(emp => emp.id == avance.id_employe);
                    const statusConfig = {
                        'approuve': { class: 'bg-green-100 text-green-800', text: 'Approuvé' },
                        'en_attente': { class: 'bg-yellow-100 text-yellow-800', text: 'En attente' },
                        'refuse': { class: 'bg-red-100 text-red-800', text: 'Refusé' },
                        'rembourse': { class: 'bg-blue-100 text-blue-800', text: 'Remboursé' }
                    };
                    const status = statusConfig[avance.statut] || statusConfig['en_attente'];
                    
                    return `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    ${employe ? `${employe.prenom} ${employe.nom}` : 'Employé inconnu'}
                                </div>
                                <div class="text-sm text-gray-500">
                                    ${employe ? (employe.poste_nom || 'Poste non défini') : ''}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                ${Utils.formatAmount(avance.montant_demande || 0)}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 max-w-xs truncate" title="${avance.motif || ''}">
                                ${avance.motif || 'Non spécifié'}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${avance.nb_mensualites || 1} mensualité(s)
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 rounded text-xs font-medium ${status.class}">
                                    ${status.text}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${new Date(avance.date_demande).toLocaleDateString('fr-FR')}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                ${avance.statut === 'en_attente' ? `
                                    <button onclick="approuverAvance(${avance.id})" 
                                            class="text-green-600 hover:text-green-900 mr-2" title="Approuver">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button onclick="refuserAvance(${avance.id})" 
                                            class="text-red-600 hover:text-red-900" title="Refuser">
                                        <i class="fas fa-times"></i>
                                    </button>
                                ` : `
                                    <button onclick="voirDetailsAvance(${avance.id})" 
                                            class="text-blue-600 hover:text-blue-900" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                `}
                            </td>
                        </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
    `;
}

// Fonction de filtrage
async function filtrerHistoriqueAvances() {
    const filters = {
        debut: document.getElementById('filtre-debut-avances')?.value || '',
        fin: document.getElementById('filtre-fin-avances')?.value || '',
        statut: document.getElementById('filtre-statut-avances')?.value || '',
        employe_id: document.getElementById('filtre-employe-avances')?.value || ''
    };
    
    try {
        const result = await Utils.apiCall('get_avances_historique', filters);
        if (result.success) {
            afficherHistoriqueAvances(result.avances);
        }
    } catch (error) {
        console.error('Erreur filtrage avances:', error);
        NotificationManager.show('Erreur lors du filtrage', 'error');
    }
}


function afficherAvancesEnAttente(avances) {
    const container = document.getElementById('avances-en-attente');
    
    if (avances.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-gray-500">
                <i class="fas fa-check-circle text-2xl text-green-500 mb-2"></i>
                <p>Aucune avance en attente</p>
            </div>
        `;
        return;
    }

    container.innerHTML = avances.map(avance => {
        // Adapter selon la structure de votre table
        const employe = employes.find(emp => emp.id == avance.id_employe); // Changé ici
        return `
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 flex items-center justify-between">
                <div>
                    <h4 class="font-medium text-gray-900">
                        ${employe ? `${employe.prenom} ${employe.nom}` : 'Employé inconnu'}
                    </h4>
                    <p class="text-sm text-gray-600">
                        Demande: ${Utils.formatAmount(avance.montant_demande || 0)}
                    </p>
                    <p class="text-sm text-gray-500">
                        ${avance.motif || 'Aucun motif'}
                    </p>
                    <p class="text-xs text-gray-400">
                        Mode: ${avance.nb_mensualites > 1 ? `${avance.nb_mensualites} mensualités` : 'UNIQUE'}
                    </p>
                </div>
                <div class="flex space-x-2">
                    <button onclick="approuverAvance(${avance.id})" 
                            class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-sm rounded">
                        <i class="fas fa-check mr-1"></i>Approuver
                    </button>
                    <button onclick="refuserAvance(${avance.id})" 
                            class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-sm rounded">
                        <i class="fas fa-times mr-1"></i>Refuser
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

// ================== GESTION PRIMES COMPLÈTE ==================
function ouvrirModalPrime() {
    const content = `
        <form id="formNouvellePrime" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Type d'attribution *</label>
                <select name="type_attribution" required class="w-full border border-gray-300 rounded-lg px-3 py-2" onchange="toggleAttributionFields()">
                    <option value="">Sélectionner un type</option>
                    <option value="INDIVIDUEL">Attribution individuelle</option>
                    <option value="DEPARTEMENT">Attribution par département</option>
                    <option value="TOUS">Attribution à tous les employés</option>
                </select>
            </div>

            <div id="employe-selection" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Employé *</label>
                <select name="employe_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Sélectionner un employé</option>
                    ${employes.map(emp => `<option value="${emp.id}">${emp.prenom} ${emp.nom} - ${emp.poste_nom || 'Poste non défini'}</option>`).join('')}
                </select>
            </div>

            <div id="departement-selection" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Département *</label>
                <select name="departement" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Sélectionner un département</option>
                    ${departements.map(dept => `<option value="${dept.nom}">${dept.nom}</option>`).join('')}
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Type de prime *</label>
                <select name="type_prime" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">Sélectionner un type</option>
                    <option value="PERFORMANCE">Prime de performance</option>
                    <option value="PRESENCE">Prime de présence</option>
                    <option value="ANCIENNETE">Prime d'ancienneté</option>
                    <option value="OBJECTIF">Prime d'objectif</option>
                    <option value="EXCEPTIONNELLE">Prime exceptionnelle</option>
                    <option value="TRANSPORT">Prime de transport</option>
                    <option value="REPAS">Prime de repas</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Montant (FCFA) *</label>
                <input type="number" name="montant" required min="1000" step="1000"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2"
                       placeholder="Ex: 25000">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Période d'attribution *</label>
                <input type="month" name="periode" required 
                       value="${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Justification</label>
                <textarea name="justification" rows="3" 
                          class="w-full border border-gray-300 rounded-lg px-3 py-2"
                          placeholder="Justification de l'attribution de cette prime..."></textarea>
            </div>

            <div id="attribution-preview" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="font-medium text-gray-900 mb-2">Aperçu de l'attribution</h4>
                <div id="preview-details" class="text-sm text-gray-600">
                    <!-- Sera rempli par JavaScript -->
                </div>
            </div>
        </form>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalNouvellePrime')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Annuler
        </button>
        <button onclick="attribuerPrime()" 
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md">
            Attribuer la prime
        </button>
    `;
    
    ModalManager.create('modalNouvellePrime', 'Attribuer une prime', content, footer, 'large');
    ModalManager.open('modalNouvellePrime');
}

function toggleAttributionFields() {
    const typeAttribution = document.querySelector('select[name="type_attribution"]').value;
    const employeSelection = document.getElementById('employe-selection');
    const departementSelection = document.getElementById('departement-selection');
    const previewDiv = document.getElementById('attribution-preview');
    
    // Cacher tous les champs
    employeSelection.classList.add('hidden');
    departementSelection.classList.add('hidden');
    previewDiv.classList.add('hidden');
    
    // Afficher le champ approprié
    switch(typeAttribution) {
        case 'INDIVIDUEL':
            employeSelection.classList.remove('hidden');
            employeSelection.querySelector('select').required = true;
            departementSelection.querySelector('select').required = false;
            break;
        case 'DEPARTEMENT':
            departementSelection.classList.remove('hidden');
            departementSelection.querySelector('select').required = true;
            employeSelection.querySelector('select').required = false;
            break;
        case 'TOUS':
            previewAttributionTous();
            break;
    }
}

function previewAttributionTous() {
    const employesActifs = employes.filter(emp => emp.statut === 'actif');
    document.getElementById('preview-details').innerHTML = `
        <p>Cette prime sera attribuée à <strong>${employesActifs.length} employé(s) actif(s)</strong></p>
        <p class="mt-1 text-xs">Coût total estimé: <strong>${Utils.formatAmount(employesActifs.length * (document.querySelector('input[name="montant"]').value || 0))}</strong></p>
    `;
    document.getElementById('attribution-preview').classList.remove('hidden');
}

async function attribuerPrime() {
    const form = document.getElementById('formNouvellePrime');
    if (!form) return;
    
    const formData = new FormData(form);
    
    const data = {
        type_attribution: formData.get('type_attribution'),
        type_prime: formData.get('type_prime'),
        montant: parseFloat(formData.get('montant')),
        periode: formData.get('periode'),
        justification: formData.get('justification') || ''
    };
    
    // Ajouter les champs spécifiques selon le type d'attribution
    if (data.type_attribution === 'INDIVIDUEL') {
        data.employe_id = parseInt(formData.get('employe_id'));
        if (!data.employe_id) {
            NotificationManager.show('Veuillez sélectionner un employé', 'error');
            return;
        }
    } else if (data.type_attribution === 'DEPARTEMENT') {
        data.departement = formData.get('departement');
        if (!data.departement) {
            NotificationManager.show('Veuillez sélectionner un département', 'error');
            return;
        }
    }
    
    if (!data.type_attribution || !data.type_prime || !data.montant || !data.periode) {
        NotificationManager.show('Veuillez remplir tous les champs obligatoires', 'error');
        return;
    }
    
    if (data.montant < 1000) {
        NotificationManager.show('Le montant minimum est de 1 000 FCFA', 'error');
        return;
    }
    
    try {
        const result = await Utils.apiCall('attribuer_prime', data, 'POST');
        
        if (result.success) {
            ModalManager.close('modalNouvellePrime');
            NotificationManager.show(`Prime attribuée à ${result.count} employé(s)`, 'success');
            setTimeout(() => chargerPrimes(), 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function genererPrimesPresence() {
    const content = `
        <form id="formPrimesPresence" class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-magic text-blue-600 mr-2"></i>
                    <span class="text-sm text-blue-800">Génération automatique des primes de présence basée sur les données de pointage</span>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Période de calcul *</label>
                <input type="month" name="periode" required 
                       value="${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Montant de la prime de présence (FCFA) *</label>
                <input type="number" name="montant_presence" required min="1000" step="1000" value="15000"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Seuil de présence minimum (%) *</label>
                <input type="number" name="seuil_presence" required min="50" max="100" value="90"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">Pourcentage minimum de présence pour être éligible à la prime</p>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h4 class="font-medium text-gray-900 mb-2">Critères d'attribution</h4>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Taux de présence ≥ au seuil défini</li>
                    <li>• Aucune absence non justifiée</li>
                    <li>• Maximum 2 retards dans le mois</li>
                    <li>• Employé actif pendant toute la période</li>
                </ul>
            </div>
        </form>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalPrimesPresence')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Annuler
        </button>
        <button onclick="lancerGenerationPrimesPresence()" 
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md">
            Générer les primes
        </button>
    `;
    
    ModalManager.create('modalPrimesPresence', 'Générer les primes de présence', content, footer);
    ModalManager.open('modalPrimesPresence');
}

async function lancerGenerationPrimesPresence() {
    const form = document.getElementById('formPrimesPresence');
    if (!form) return;
    
    const formData = new FormData(form);
    const periode = formData.get('periode').split('-');
    
    const data = {
        mois: parseInt(periode[1]),
        annee: parseInt(periode[0]),
        montant_presence: parseFloat(formData.get('montant_presence')),
        seuil_presence: parseFloat(formData.get('seuil_presence'))
    };
    
    try {
        const result = await Utils.apiCall('generer_primes_presence', data, 'POST');
        
        if (result.success) {
            ModalManager.close('modalPrimesPresence');
            NotificationManager.show(`Primes de présence générées pour ${result.count} employé(s)`, 'success');
            setTimeout(() => chargerPrimes(), 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function chargerPrimes() {
    try {
        // Afficher primes en attente
        afficherPrimesEnAttente(primes_attente);
        
        // Charger historique des primes
        const result = await Utils.apiCall('get_primes_historique');
        if (result.success) {
            afficherHistoriquePrimes(result.primes);
        }
    } catch (error) {
        console.error('Erreur chargement primes:', error);
    }
}

function afficherHistoriquePrimes(primes) {
    const container = document.getElementById('historique-primes');
    
    if (primes.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-history text-4xl text-gray-400 mb-4"></i>
                <p>Aucun historique de prime trouvé</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="mb-4 flex flex-wrap gap-2">
            <input type="date" id="filtre-debut-primes" class="border border-gray-300 rounded px-3 py-1 text-sm" placeholder="Date début">
            <input type="date" id="filtre-fin-primes" class="border border-gray-300 rounded px-3 py-1 text-sm" placeholder="Date fin">
            <select id="filtre-statut-primes" class="border border-gray-300 rounded px-3 py-1 text-sm">
                <option value="">Tous les statuts</option>
                <option value="en_attente">En attente</option>
                <option value="valide">Validé</option>
            </select>
            <select id="filtre-employe-primes" class="border border-gray-300 rounded px-3 py-1 text-sm">
                <option value="">Tous les employés</option>
                ${employes.map(emp => `<option value="${emp.id}">${emp.prenom} ${emp.nom}</option>`).join('')}
            </select>
            <button onclick="filtrerHistoriquePrimes()" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded">
                Filtrer
            </button>
        </div>
        
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employé</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Période</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Note</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                ${primes.map(prime => {
                    const employe = employes.find(emp => emp.id == prime.id_employe);
                    const statusConfig = {
                        '1': { class: 'bg-green-100 text-green-800', text: 'Validé' },
                        '0': { class: 'bg-yellow-100 text-yellow-800', text: 'En attente' }
                    };
                    const status = statusConfig[prime.valide] || statusConfig['0'];
                    
                    return `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    ${employe ? `${employe.prenom} ${employe.nom}` : 'Employé inconnu'}
                                </div>
                                <div class="text-sm text-gray-500">
                                    ${employe ? (employe.poste_nom || 'Poste non défini') : ''}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${Utils.capitalize(prime.type_prime_nom || 'Prime')}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                ${Utils.formatAmount(prime.montant || 0)}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${Utils.formatPeriod(prime.mois, prime.annee)}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                ${prime.note_performance ? prime.note_performance + '/10' : '-'}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 rounded text-xs font-medium ${status.class}">
                                    ${status.text}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${new Date(prime.created_at).toLocaleDateString('fr-FR')}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                ${prime.valide === '0' ? `
                                    <button onclick="validerPrime(${prime.id})" 
                                            class="text-green-600 hover:text-green-900 mr-2" title="Valider">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button onclick="refuserPrime(${prime.id})" 
                                            class="text-red-600 hover:text-red-900" title="Refuser">
                                        <i class="fas fa-times"></i>
                                    </button>
                                ` : `
                                    <button onclick="voirDetailsPrime(${prime.id})" 
                                            class="text-blue-600 hover:text-blue-900" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                `}
                            </td>
                        </tr>
                    `;
                }).join('')}
            </tbody>
        </table>
    `;
}

// Fonction de filtrage
async function filtrerHistoriquePrimes() {
    const filters = {
        debut: document.getElementById('filtre-debut-primes')?.value || '',
        fin: document.getElementById('filtre-fin-primes')?.value || '',
        statut: document.getElementById('filtre-statut-primes')?.value || '',
        employe_id: document.getElementById('filtre-employe-primes')?.value || ''
    };
    
    try {
        const result = await Utils.apiCall('get_primes_historique', filters);
        if (result.success) {
            afficherHistoriquePrimes(result.primes);
        }
    } catch (error) {
        console.error('Erreur filtrage primes:', error);
        NotificationManager.show('Erreur lors du filtrage', 'error');
    }
}

async function voirDetailsPrime(id) {
    try {
        const result = await Utils.apiCall('get_prime_details', { prime_id: id });
        
        if (!result.success) {
            NotificationManager.show('Erreur: Prime introuvable', 'error');
            return;
        }
        
        const prime = result.prime;
        const employe = employes.find(emp => emp.id == prime.id_employe);
        
        const statusConfig = {
            '1': { class: 'text-green-600', text: 'Validée', icon: 'fa-check-circle' },
            '0': { class: 'text-yellow-600', text: 'En attente', icon: 'fa-clock' }
        };
        const status = statusConfig[prime.valide] || statusConfig['0'];
        
        const content = `
            <div class="space-y-6">
                <!-- En-tête avec statut -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">
                            Prime #${prime.id} - ${prime.type_prime_nom || 'Prime'}
                        </h3>
                        <p class="text-sm text-gray-600">
                            Créée le ${new Date(prime.created_at).toLocaleDateString('fr-FR')}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center ${status.class}">
                            <i class="fas ${status.icon} mr-2"></i>
                            <span class="font-medium">${status.text}</span>
                        </div>
                        ${prime.date_validation ? `
                            <p class="text-xs text-gray-500 mt-1">
                                Validée le ${new Date(prime.date_validation).toLocaleDateString('fr-FR')}
                            </p>
                        ` : ''}
                    </div>
                </div>

                <!-- Informations employé et prime -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white border rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3">
                            <i class="fas fa-user text-blue-600 mr-2"></i>
                            Informations employé
                        </h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Nom complet :</span>
                                <span class="font-medium">
                                    ${employe ? `${employe.prenom} ${employe.nom}` : 'Employé inconnu'}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Poste :</span>
                                <span class="font-medium">
                                    ${prime.poste_nom || 'Poste non défini'}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Email :</span>
                                <span class="font-medium">
                                    ${prime.email || 'Non défini'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Détails de la prime -->
                    <div class="bg-white border rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3">
                            <i class="fas fa-award text-green-600 mr-2"></i>
                            Détails de la prime
                        </h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Type :</span>
                                <span class="font-medium">${prime.type_prime_nom || 'Non défini'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Montant :</span>
                                <span class="font-medium text-green-600">${Utils.formatAmount(prime.montant || 0)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Période :</span>
                                <span class="font-medium">${Utils.formatPeriod(prime.mois, prime.annee)}</span>
                            </div>
                            ${prime.note_performance ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Note performance :</span>
                                    <span class="font-medium">${prime.note_performance}/10</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>

                <!-- Critères de performance et description -->
                ${prime.criteres_performance || prime.type_prime_description ? `
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">
                        <i class="fas fa-clipboard-list text-purple-600 mr-2"></i>
                        Critères et justification
                    </h4>
                    <div class="space-y-3">
                        ${prime.type_prime_description ? `
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Type de prime :</label>
                                <div class="bg-gray-50 rounded p-3 text-sm text-gray-800">
                                    ${prime.type_prime_description}
                                </div>
                            </div>
                        ` : ''}
                        ${prime.criteres_performance ? `
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Critères/Justification :</label>
                                <div class="bg-gray-50 rounded p-3 text-sm text-gray-800">
                                    ${prime.criteres_performance}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}

                <!-- Commentaire de validation -->
                ${prime.commentaire ? `
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">
                        <i class="fas fa-comment text-indigo-600 mr-2"></i>
                        Commentaire de validation
                    </h4>
                    <div class="bg-gray-50 rounded p-3 text-sm text-gray-800">
                        ${prime.commentaire}
                    </div>
                    ${prime.valideur_nom ? `
                        <p class="text-xs text-gray-500 mt-2">
                            Par ${prime.valideur_prenom} ${prime.valideur_nom}
                        </p>
                    ` : ''}
                </div>
                ` : ''}

                <!-- Historique/Timeline -->
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">
                        <i class="fas fa-history text-gray-600 mr-2"></i>
                        Historique de la prime
                    </h4>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                            <div class="text-sm">
                                <span class="font-medium">Prime créée</span>
                                <span class="text-gray-500 ml-2">
                                    ${new Date(prime.created_at).toLocaleString('fr-FR')}
                                </span>
                            </div>
                        </div>
                        ${prime.updated_at && prime.updated_at !== prime.created_at ? `
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                                <div class="text-sm">
                                    <span class="font-medium">Dernière modification</span>
                                    <span class="text-gray-500 ml-2">
                                        ${new Date(prime.updated_at).toLocaleString('fr-FR')}
                                    </span>
                                </div>
                            </div>
                        ` : ''}
                        ${prime.date_validation ? `
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                <div class="text-sm">
                                    <span class="font-medium">Prime validée</span>
                                    <span class="text-gray-500 ml-2">
                                        ${new Date(prime.date_validation).toLocaleString('fr-FR')}
                                        ${prime.valideur_nom ? ` par ${prime.valideur_prenom} ${prime.valideur_nom}` : ''}
                                    </span>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Calculs et impacts -->
                ${prime.valide === '1' ? `
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">
                        <i class="fas fa-calculator text-green-600 mr-2"></i>
                        Impact sur la paie
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div class="text-center">
                            <div class="text-lg font-bold text-green-600">${Utils.formatAmount(prime.montant)}</div>
                            <div class="text-gray-600">Montant brut</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-blue-600">${Utils.formatAmount(prime.montant * 0.78)}</div>
                            <div class="text-gray-600">Net estimé (78%)</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-purple-600">${Utils.formatAmount(prime.montant * 0.22)}</div>
                            <div class="text-gray-600">Charges estimées</div>
                        </div>
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        
        const footer = `
            <button onclick="ModalManager.close('modalDetailsPrime')" 
                    class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
                Fermer
            </button>
            ${prime.valide === '0' ? `
                <button onclick="validerPrimeDepuisModal(${prime.id})" 
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md">
                    <i class="fas fa-check mr-2"></i>Valider
                </button>
                <button onclick="refuserPrimeDepuisModal(${prime.id})" 
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md">
                    <i class="fas fa-times mr-2"></i>Refuser
                </button>
            ` : ''}
            ${prime.valide === '1' ? `
                <button onclick="imprimerDetailsPrime(${prime.id})" 
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md">
                    <i class="fas fa-print mr-2"></i>Imprimer
                </button>
            ` : ''}
        `;
        
        ModalManager.create('modalDetailsPrime', `Détails de la prime - ${employe ? `${employe.prenom} ${employe.nom}` : 'Employé'}`, content, footer, 'large');
        ModalManager.open('modalDetailsPrime');
        
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur lors du chargement des détails', 'error');
    }
}

// Fonctions auxiliaires pour les actions depuis le modal
async function validerPrimeDepuisModal(id) {
    ModalManager.close('modalDetailsPrime');
    await validerPrime(id);
    // Recharger les données après validation
    setTimeout(() => chargerPrimes(), 1500);
}

async function refuserPrimeDepuisModal(id) {
    ModalManager.close('modalDetailsPrime');
    await refuserPrime(id);
    // Recharger les données après refus
    setTimeout(() => chargerPrimes(), 1500);
}

function imprimerDetailsPrime(id) {
    window.print();
}

function afficherPrimesEnAttente(primes) {
    const container = document.getElementById('primes-en-attente');
    
    if (primes.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-gray-500">
                <i class="fas fa-check-circle text-2xl text-green-500 mb-2"></i>
                <p>Aucune prime en attente</p>
            </div>
        `;
        return;
    }

    container.innerHTML = primes.map(prime => {
        const employe = employes.find(emp => emp.id == prime.id_employe); // Changé ici
        return `
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 flex items-center justify-between">
                <div>
                    <h4 class="font-medium text-gray-900">
                        ${employe ? `${employe.prenom} ${employe.nom}` : 'Employé inconnu'}
                    </h4>
                    <p class="text-sm text-gray-600">
                        ${Utils.capitalize(prime.type_prime_nom || 'Prime')}: ${Utils.formatAmount(prime.montant)}
                    </p>
                    <p class="text-sm text-gray-500">
                        ${prime.criteres_performance || 'Aucune description'} {/* Changé ici */}
                    </p>
                    <p class="text-xs text-gray-400">
                        Période: ${Utils.formatPeriod(prime.mois, prime.annee)}
                    </p>
                </div>
                <div class="flex space-x-2">
                    <button onclick="validerPrime(${prime.id})" 
                            class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-sm rounded">
                        <i class="fas fa-check mr-1"></i>Valider
                    </button>
                    <button onclick="refuserPrime(${prime.id})" 
                            class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-sm rounded">
                        <i class="fas fa-times mr-1"></i>Refuser
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

// ================== CHARGEMENT PRÉSENCES ==================
async function chargerPresences() {
    try {
        const result = await Utils.apiCall('get_presences_jour', {
            date: new Date().toISOString().split('T')[0]
        });

        if (result.success) {
            const presencesJour = result.presences.map(presence => ({
                id: presence.employe_id,
                employe_id: presence.employe_id, // Conserver aussi employe_id pour compatibilité
                nom: presence.nom,
                prenom: presence.prenom,
                poste_nom: presence.poste_nom,
                statut: presence.statut,
                statut_presence: presence.statut_presence,
                heure_arrivee: presence.heure_arrivee_format,
                heure_depart: presence.heure_depart_format
            }));

            afficherPresencesJour(presencesJour);
            
            // Mettre à jour le compteur de présents
            const presents = presencesJour.filter(p => p.statut_presence === 'present').length;
            document.getElementById('stat-presences').textContent = presents;
        } else {
            console.error('Erreur chargement présences:', result.error);
            // Affichage vide en cas d'erreur
            afficherPresencesJour([]);
            document.getElementById('stat-presences').textContent = '0';
        }
        
    } catch (error) {
        console.error('Erreur chargement présences:', error);
        afficherPresencesJour([]);
        document.getElementById('stat-presences').textContent = '0';
    }
}

async function changerDatePresences(date) {
    try {
        const result = await Utils.apiCall('get_presences_jour', { date: date });

        if (result.success) {
            const presencesJour = result.presences.map(presence => ({
                id: presence.employe_id,
                employe_id: presence.employe_id, // Conserver aussi employe_id pour compatibilité
                nom: presence.nom,
                prenom: presence.prenom,
                poste_nom: presence.poste_nom,
                statut: presence.statut,
                statut_presence: presence.statut_presence,
                heure_arrivee: presence.heure_arrivee_format,
                heure_depart: presence.heure_depart_format
            }));

            afficherPresencesJour(presencesJour);
        }
    } catch (error) {
        console.error('Erreur:', error);
    }
}

function afficherPresencesJour(presences) {
    const container = document.getElementById('presences-jour');
    
    if (presences.length === 0) {
        container.innerHTML = `
            <div class="col-span-3 text-center py-8 text-gray-500">
                <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
                <p>Aucune donnée de présence aujourd'hui</p>
            </div>
        `;
        return;
    }

    container.innerHTML = presences.map(presence => {
        // Debug: vérifier que l'ID existe
        if (!presence.id && !presence.employe_id) {
            console.error('Présence sans ID:', presence);
        }

        const statusConfig = {
            'present': { class: 'presence-present', icon: 'fa-check-circle', text: 'Présent', color: 'text-green-600' },
            'absent': { class: 'presence-absent', icon: 'fa-times-circle', text: 'Absent', color: 'text-red-600' },
            'retard': { class: 'presence-retard', icon: 'fa-clock', text: 'En retard', color: 'text-yellow-600' },
            'pause': { class: 'presence-pause', icon: 'fa-pause-circle', text: 'En pause', color: 'text-blue-600' }
        };
        
        const config = statusConfig[presence.statut_presence] || statusConfig['absent'];
        
        // Affichage des horaires planifiés vs réels
        let horaireInfo = '';
        if (presence.est_programme) {
            horaireInfo = `
                <div class="text-xs text-gray-500 bg-gray-50 rounded px-2 py-1 mt-2">
                    <div class="flex justify-between">
                        <span>Prévu:</span>
                        <span>${presence.heure_debut_prevue} - ${presence.heure_fin_prevue}</span>
                    </div>
                    ${presence.heure_arrivee_format ? `
                    <div class="flex justify-between">
                        <span>Réel:</span>
                        <span>${presence.heure_arrivee_format}${presence.heure_depart_format ? ' - ' + presence.heure_depart_format : ''}</span>
                    </div>
                    ` : ''}
                </div>
            `;
        } else {
            horaireInfo = `
                <div class="text-xs text-blue-500 bg-blue-50 rounded px-2 py-1 mt-2">
                    <i class="fas fa-coffee mr-1"></i>Journée de pause
                </div>
            `;
        }
        
        return `
            <div class="bg-white border rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer"
                 onclick="voirDetailsPresenceEmploye(${presence.id || presence.employe_id || 0})">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-medium text-gray-900">${presence.prenom} ${presence.nom}</h4>
                    <span class="presence-indicator ${config.class}"></span>
                </div>
                <p class="text-sm text-gray-600 mb-1">${presence.poste_nom || 'Poste non défini'}</p>
                <div class="flex items-center ${config.color}">
                    <i class="fas ${config.icon} mr-2"></i>
                    <span class="text-sm font-medium">${config.text}</span>
                </div>
                ${horaireInfo}
                <div class="mt-2 text-xs text-blue-600">
                    <i class="fas fa-eye mr-1"></i>Cliquer pour plus de détails
                </div>
            </div>
        `;
    }).join('');
}

async function voirDetailsPresenceEmploye(employeId) {
    const dateSelectionnee = document.getElementById('date-presences')?.value || new Date().toISOString().split('T')[0];
    
    try {
        const result = await Utils.apiCall('get_details_presence_employe', {
            employe_id: employeId,
            date: dateSelectionnee
        });
        
        if (!result.success) {
            NotificationManager.show('Erreur: ' + (result.error || 'Employé introuvable'), 'error');
            return;
        }
        
        const { employe, presence_jour, horaire_planifie, stats_mois } = result;
        
        const content = `
            <div class="space-y-6">
                <!-- En-tête employé avec infos du poste -->
                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">
                            ${employe.prenom} ${employe.nom}
                        </h3>
                        <p class="text-sm text-gray-600">${employe.poste_nom || 'Poste non défini'}</p>
                        <p class="text-xs text-gray-500">${employe.departement_nom || ''}</p>
                        <div class="mt-1 text-xs text-blue-600">
                            Contrat: ${employe.heures_semaine || 35}h/semaine • ${employe.heures_mois || 152}h/mois
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-700">Matricule: ${employe.id}</p>
                        <p class="text-xs text-gray-500">Email: ${employe.email || 'Non défini'}</p>
                    </div>
                </div>

                <!-- Comparaison planifié vs réel pour le jour -->
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">
                        <i class="fas fa-calendar-day text-blue-600 mr-2"></i>
                        Présence du ${new Date(dateSelectionnee).toLocaleDateString('fr-FR')}
                    </h4>
                    
                    <!-- Horaires planifiés vs réels -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="bg-blue-50 rounded-lg p-3">
                            <h5 class="font-medium text-blue-900 mb-2">
                                <i class="fas fa-calendar-check mr-2"></i>Planification
                            </h5>
                            ${horaire_planifie.est_programme ? `
                                <div class="text-sm">
                                    <div class="flex justify-between">
                                        <span>Début prévu:</span>
                                        <span class="font-medium">${horaire_planifie.heure_debut}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Fin prévue:</span>
                                        <span class="font-medium">${horaire_planifie.heure_fin}</span>
                                    </div>
                                </div>
                            ` : `
                                <div class="text-sm text-blue-700">
                                    <i class="fas fa-coffee mr-1"></i>Journée de pause programmée
                                </div>
                            `}
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-3">
                            <h5 class="font-medium text-gray-900 mb-2">
                                <i class="fas fa-clock mr-2"></i>Réalité
                            </h5>
                            ${presence_jour ? `
                                <div class="text-sm">
                                    <div class="flex justify-between">
                                        <span>Arrivée:</span>
                                        <span class="font-medium ${presence_jour.statut_presence === 'retard' ? 'text-yellow-600' : 'text-green-600'}">
                                            ${presence_jour.heure_arrivee_format || 'N/A'}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Départ:</span>
                                        <span class="font-medium">${presence_jour.heure_depart_format || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Heures travaillées:</span>
                                        <span class="font-medium text-blue-600">
                                            ${Math.round((presence_jour.heures_travaillees || 0) * 100) / 100}h
                                        </span>
                                    </div>
                                </div>
                            ` : `
                                <div class="text-sm text-gray-600">
                                    ${horaire_planifie.est_programme ? 
                                        '<i class="fas fa-times-circle text-red-500 mr-1"></i>Absence non justifiée' : 
                                        '<i class="fas fa-coffee text-blue-500 mr-1"></i>Pas de présence requise'
                                    }
                                </div>
                            `}
                        </div>
                    </div>

                    <!-- Statut global du jour -->
                    <div class="text-center p-3 rounded-lg ${
                        presence_jour?.statut_presence === 'present' ? 'bg-green-100 text-green-800' :
                        presence_jour?.statut_presence === 'retard' ? 'bg-yellow-100 text-yellow-800' :
                        presence_jour?.statut_presence === 'pause' ? 'bg-blue-100 text-blue-800' :
                        'bg-red-100 text-red-800'
                    }">
                        <i class="fas ${
                            presence_jour?.statut_presence === 'present' ? 'fa-check-circle' :
                            presence_jour?.statut_presence === 'retard' ? 'fa-clock' :
                            presence_jour?.statut_presence === 'pause' ? 'fa-coffee' :
                            'fa-times-circle'
                        } mr-2"></i>
                        <span class="font-medium">
                            ${presence_jour?.statut_presence === 'present' ? 'Présent' :
                              presence_jour?.statut_presence === 'retard' ? 'En retard' :
                              presence_jour?.statut_presence === 'pause' ? 'En pause (programmée)' :
                              'Absent'}
                        </span>
                    </div>
                </div>

                <!-- Statistiques du mois avec planification -->
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">
                        <i class="fas fa-chart-bar text-purple-600 mr-2"></i>
                        Statistiques du mois (avec planification)
                    </h4>
                    
                    <!-- Comparaison heures planifiées vs réelles -->
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div>
                                <div class="text-2xl font-bold text-blue-600">
                                    ${Math.round(stats_mois.heures_planifiees_total || 0)}h
                                </div>
                                <div class="text-sm text-gray-600">Heures planifiées</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-green-600">
                                    ${Math.round(stats_mois.heures_reelles_total || 0)}h
                                </div>
                                <div class="text-sm text-gray-600">Heures réelles</div>
                            </div>
                        </div>
                        <div class="mt-3 text-center">
                            <div class="text-lg font-bold ${stats_mois.taux_presence >= 90 ? 'text-green-600' : stats_mois.taux_presence >= 75 ? 'text-yellow-600' : 'text-red-600'}">
                                ${Math.round(stats_mois.taux_presence || 0)}%
                            </div>
                            <div class="text-sm text-gray-600">Taux de présence</div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">${stats_mois.jours_travailles || 0}</div>
                            <div class="text-sm text-gray-600">Jours travaillés</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-orange-600">${stats_mois.jours_en_pause || 0}</div>
                            <div class="text-sm text-gray-600">Jours en pause</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600">${stats_mois.nb_retards || 0}</div>
                            <div class="text-sm text-gray-600">Retards</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-red-600">${stats_mois.nb_absences || 0}</div>
                            <div class="text-sm text-gray-600">Absences</div>
                        </div>
                    </div>
                </div>

                <!-- Détail des 7 derniers jours avec planification -->
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">
                        <i class="fas fa-calendar-week text-green-600 mr-2"></i>
                        Historique des 7 derniers jours
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Planifié</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Réel</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Heures</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${stats_mois.details_par_jour.slice(-7).map(jour => `
                                    <tr>
                                        <td class="px-3 py-2 text-sm font-medium text-gray-900">
                                            ${new Date(jour.date).toLocaleDateString('fr-FR')}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-600">
                                            ${jour.horaire_planifie.est_programme ? 
                                                `${jour.horaire_planifie.heure_debut} - ${jour.horaire_planifie.heure_fin}` : 
                                                '<span class="text-blue-600">Pause</span>'
                                            }
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-600">
                                            ${jour.heures_reelles > 0 ? `${Math.round(jour.heures_reelles * 100) / 100}h` : '-'}
                                        </td>
                                        <td class="px-3 py-2 text-sm font-medium">
                                            <span class="${jour.heures_planifiees > 0 ? 'text-blue-600' : 'text-gray-400'}">
                                                ${jour.heures_planifiees > 0 ? Math.round(jour.heures_planifiees * 100) / 100 + 'h' : '-'}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 text-sm">
                                            <span class="px-2 py-1 rounded text-xs font-medium ${
                                                jour.statut === 'present' ? 'bg-green-100 text-green-800' :
                                                jour.statut === 'retard' ? 'bg-yellow-100 text-yellow-800' :
                                                jour.statut === 'pause' ? 'bg-blue-100 text-blue-800' :
                                                'bg-red-100 text-red-800'
                                            }">
                                                ${Utils.capitalize(jour.statut)}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Alertes et recommandations -->
                ${stats_mois.taux_presence < 90 || stats_mois.nb_retards > 3 || stats_mois.nb_absences > 2 ? `
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h4 class="font-medium text-yellow-800 mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Points d'attention
                    </h4>
                    <ul class="text-sm text-yellow-700 space-y-1">
                        ${stats_mois.taux_presence < 90 ? `
                            <li>• Taux de présence en dessous de 90% (${Math.round(stats_mois.taux_presence)}%)</li>
                        ` : ''}
                        ${stats_mois.nb_retards > 3 ? `
                            <li>• Nombre de retards élevé ce mois (${stats_mois.nb_retards})</li>
                        ` : ''}
                        ${stats_mois.nb_absences > 2 ? `
                            <li>• Absences fréquentes ce mois (${stats_mois.nb_absences})</li>
                        ` : ''}
                    </ul>
                </div>
                ` : `
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center text-green-700">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span class="font-medium">Excellente assiduité ce mois !</span>
                    </div>
                </div>
                `}
            </div>
        `;
        
        const footer = `
            <button onclick="ModalManager.close('modalDetailsPresence')" 
                    class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
                Fermer
            </button>
            <button onclick="imprimerDetailsPresence()" 
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md">
                <i class="fas fa-print mr-2"></i>Imprimer
            </button>
            ${horaire_planifie.est_programme && !presence_jour ? `
                <button onclick="marquerPresenceManuelle(${employeId}, '${dateSelectionnee}')" 
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md">
                    <i class="fas fa-plus mr-2"></i>Ajouter présence
                </button>
            ` : ''}
        `;
        
        ModalManager.create('modalDetailsPresence', `Détails de présence - ${employe.prenom} ${employe.nom}`, content, footer, 'xlarge');
        ModalManager.open('modalDetailsPresence');
        
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur lors du chargement des détails', 'error');
    }
}

// 3. AJOUT D'UNE FONCTION POUR MARQUER UNE PRÉSENCE MANUELLEMENT
async function marquerPresenceManuelle(employeId, date) {
    const employe = employes.find(emp => emp.id == employeId);
    if (!employe) return;
    
    const content = `
        <form id="formPresenceManuelle" class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-user-plus text-blue-600 mr-2"></i>
                    <div>
                        <span class="text-sm text-blue-800 font-medium">
                            Ajout de présence pour ${employe.prenom} ${employe.nom}
                        </span>
                        <p class="text-xs text-blue-700">
                            Date: ${new Date(date).toLocaleDateString('fr-FR')}
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Heure d'arrivée *</label>
                    <input type="time" name="heure_arrivee" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Heure de départ</label>
                    <input type="time" name="heure_depart"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Commentaire</label>
                <textarea name="commentaire" rows="3" 
                          class="w-full border border-gray-300 rounded-lg px-3 py-2"
                          placeholder="Raison de l'ajout manuel (optionnel)..."></textarea>
            </div>
        </form>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalPresenceManuelle')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Annuler
        </button>
        <button onclick="sauvegarderPresenceManuelle(${employeId}, '${date}')" 
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md">
            Enregistrer
        </button>
    `;
    
    ModalManager.create('modalPresenceManuelle', 'Ajout de présence manuelle', content, footer);
    ModalManager.open('modalPresenceManuelle');
}

// 4. FONCTION POUR SAUVEGARDER LA PRÉSENCE MANUELLE
async function sauvegarderPresenceManuelle(employeId, date) {
    const form = document.getElementById('formPresenceManuelle');
    if (!form) return;
    
    const formData = new FormData(form);
    const data = {
        employe_id: employeId,
        date: date,
        heure_arrivee: formData.get('heure_arrivee'),
        heure_depart: formData.get('heure_depart'),
        commentaire: formData.get('commentaire'),
        ajout_manuel: true
    };
    
    if (!data.heure_arrivee) {
        NotificationManager.show('L\'heure d\'arrivée est obligatoire', 'error');
        return;
    }
    
    try {
        const result = await Utils.apiCall('ajouter_presence_manuelle', data, 'POST');
        
        if (result.success) {
            ModalManager.close('modalPresenceManuelle');
            ModalManager.close('modalDetailsPresence');
            NotificationManager.show('Présence ajoutée avec succès', 'success');
            setTimeout(() => {
                changerDatePresences(date); // Recharger les présences
            }, 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

// 5. AJOUT D'UNE FONCTION POUR VÉRIFIER LA COHÉRENCE PLANIFICATION/PRÉSENCES
async function verifierCoherencePlanification() {
    try {
        const result = await Utils.apiCall('verifier_coherence_planification');
        
        if (result.success) {
            if (result.incoherences.length === 0) {
                NotificationManager.show('Aucune incohérence détectée', 'success');
            } else {
                afficherIncoherences(result.incoherences);
            }
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur lors de la vérification', 'error');
    }
}

// 6. FONCTION POUR AFFICHER LES INCOHÉRENCES
function afficherIncoherences(incoherences) {
    const content = `
        <div class="space-y-4">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                    <span class="text-sm text-yellow-800">
                        ${incoherences.length} incohérence(s) détectée(s) entre la planification et les présences
                    </span>
                </div>
            </div>
            
            <div class="space-y-3">
                ${incoherences.map(incoh => `
                    <div class="bg-white border border-red-200 rounded-lg p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-medium text-gray-900">${incoh.nom}</h4>
                                <p class="text-sm text-red-600">${incoh.probleme}</p>
                                <p class="text-xs text-gray-500">
                                    ${incoh.taux ? `Taux: ${incoh.taux}` : ''}
                                    ${incoh.nb_retards ? `Retards: ${incoh.nb_retards}` : ''}
                                </p>
                            </div>
                            <button onclick="voirDetailsPresenceEmploye(${incoh.employe_id})"
                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-eye mr-1"></i>Détails
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalIncoherences')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Fermer
        </button>
        <button onclick="exporterRapportIncoherences()" 
                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md">
            Exporter rapport
        </button>
    `;
    
    ModalManager.create('modalIncoherences', 'Incohérences détectées', content, footer, 'large');
    ModalManager.open('modalIncoherences');
}

// 7. AJOUT DES STYLES CSS POUR LE STATUT "PAUSE"
const pauseStyles = `
.presence-pause {
    background-color: #3b82f6;
}
`;

// Ajouter les styles au document
if (!document.getElementById('presence-pause-styles')) {
    const style = document.createElement('style');
    style.id = 'presence-pause-styles';
    style.textContent = pauseStyles;
    document.head.appendChild(style);
}



function imprimerDetailsPresence() {
    window.print();
}
// ================== CHARGEMENT DASHBOARD ==================
async function chargerDashboard() {
    try {
        const result = await Utils.apiCall('get_dashboard_stats_advanced');
        
        if (result.success) {
            const stats = result.stats;
            
            // Mise à jour du résumé mensuel
            const resumeMensuel = document.getElementById('resume-mensuel');
            resumeMensuel.innerHTML = `
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Employés actifs</span>
                        <span class="font-semibold">${stats.employes?.total_actifs || 0}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Bulletins générés</span>
                        <span class="font-semibold">${stats.paie?.bulletins_generes || 0}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Masse salariale</span>
                        <span class="font-semibold">${Utils.formatAmount(stats.paie?.masse_salariale || 0)}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Présences moyennes</span>
                        <span class="font-semibold">${Math.round(stats.presences?.heures_moyennes_par_jour || 0)}h/jour</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Taux présence</span>
                        <span class="font-semibold">${Math.round((stats.presences?.employes_avec_presences / stats.presences?.total_employes_actifs) * 100 || 0)}%</span>
                    </div>
                </div>
            `;

            // Mise à jour des actions en attente
            const totalAttente = (stats.conges_attente || 0) + (stats.avances_attente || 0) + (stats.primes_attente || 0);
            const actionsAttente = document.getElementById('actions-attente');
            
            if (totalAttente === 0) {
                actionsAttente.innerHTML = `
                    <div class="text-center text-gray-500">
                        <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
                        <p>Aucune action en attente</p>
                    </div>
                `;
            } else {
                let html = '';
                if (stats.conges_attente > 0) {
                    html += `<div class="flex justify-between items-center text-yellow-700">
                        <span>Congés à valider</span>
                        <span class="font-semibold">${stats.conges_attente}</span>
                    </div>`;
                }
                if (stats.avances_attente > 0) {
                    html += `<div class="flex justify-between items-center text-orange-700">
                        <span>Avances à valider</span>
                        <span class="font-semibold">${stats.avances_attente}</span>
                    </div>`;
                }
                if (stats.primes_attente > 0) {
                    html += `<div class="flex justify-between items-center text-green-700">
                        <span>Primes à valider</span>
                        <span class="font-semibold">${stats.primes_attente}</span>
                    </div>`;
                }
                actionsAttente.innerHTML = html;
            }

            // Mise à jour des répartitions
            if (stats.departements) {
                const repartitionDept = document.getElementById('repartition-departements');
                repartitionDept.innerHTML = stats.departements.map(dept => `
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">${dept.nom}</span>
                        <span class="font-medium">${dept.nb_employes || 0}</span>
                    </div>
                `).join('');
            }

            // Simuler types de contrats
            const typesContrats = document.getElementById('types-contrats');
            typesContrats.innerHTML = `
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">CDI</span>
                    <span class="font-medium">${Math.floor(employes.length * 0.7)}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">CDD</span>
                    <span class="font-medium">${Math.floor(employes.length * 0.2)}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Stage</span>
                    <span class="font-medium">${Math.floor(employes.length * 0.1)}</span>
                </div>
            `;

            // Indicateurs clés
            const indicateursCles = document.getElementById('indicateurs-cles');
            const tauxTraitement = stats.employes?.total_actifs > 0 ? 
                Math.round((stats.paie?.bulletins_generes / stats.employes.total_actifs) * 100) : 0;
            const salaireMoyen = stats.paie?.bulletins_generes > 0 ? 
                stats.paie.masse_salariale / stats.paie.bulletins_generes : 0;

            indicateursCles.innerHTML = `
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Taux traitement paie</span>
                    <span class="font-medium">${tauxTraitement}%</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Salaire moyen</span>
                    <span class="font-medium">${Utils.formatAmount(salaireMoyen)}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Actions en attente</span>
                    <span class="font-medium">${totalAttente}</span>
                </div>
            `;
        }
    } catch (error) {
        console.error('Erreur chargement dashboard:', error);
    }
}

// ================== ACTIONS COMMUNES ==================
async function approuverConge(id) {
    if (!confirm('Approuver cette demande de congé ?')) return;
    
    try {
        const result = await Utils.apiCall('valider_conge', { 
            id_conge: id, 
            statut: 'approuve' 
        }, 'POST');
        
        if (result.success) {
            NotificationManager.show('Congé approuvé', 'success');
            setTimeout(() => {
                chargerConges();
                chargerDashboard();
            }, 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function refuserConge(id) {
    const motif = prompt('Motif du refus (optionnel):');
    
    try {
        const result = await Utils.apiCall('valider_conge', { 
            id_conge: id, 
            statut: 'refuse', 
            commentaire: motif 
        }, 'POST');
        
        if (result.success) {
            NotificationManager.show('Congé refusé', 'info');
            setTimeout(() => {
                chargerConges();
                chargerDashboard();
            }, 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function approuverAvance(id) {
    if (!confirm('Approuver cette demande d\'avance ?')) return;
    
    try {
        const result = await Utils.apiCall('valider_avance', { 
            id_avance: id, 
            statut: 'approuve' 
        }, 'POST');
        
        if (result.success) {
            NotificationManager.show('Avance approuvée', 'success');
            setTimeout(() => {
                chargerAvances();
                chargerDashboard();
            }, 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function refuserAvance(id) {
    const motif = prompt('Motif du refus:');
    if (!motif) return;
    
    try {
        const result = await Utils.apiCall('valider_avance', { 
            id_avance: id, 
            statut: 'refuse', 
            commentaire: motif 
        }, 'POST');
        
        if (result.success) {
            NotificationManager.show('Avance refusée', 'info');
            setTimeout(() => {
                chargerAvances();
                chargerDashboard();
            }, 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function validerPrime(id) {
    if (!confirm('Valider cette prime ?')) return;
    
    try {
        const result = await Utils.apiCall('valider_prime', { 
            id_prime: id, 
            statut: 'valide' 
        }, 'POST');
        
        if (result.success) {
            NotificationManager.show('Prime validée', 'success');
            setTimeout(() => {
                chargerPrimes();
                chargerDashboard();
            }, 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function refuserPrime(id) {
    const motif = prompt('Motif du refus:');
    if (!motif) return;
    
    try {
        const result = await Utils.apiCall('valider_prime', { 
            id_prime: id, 
            statut: 'refuse', 
            commentaire: motif 
        }, 'POST');
        
        if (result.success) {
            NotificationManager.show('Prime refusée', 'info');
            setTimeout(() => {
                chargerPrimes();
                chargerDashboard();
            }, 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

// ================== AUTRES ACTIONS ==================
// Fonction pour VOIR le bulletin (affichage inline dans le navigateur)
function voirBulletin(id) {
    // Ouvrir dans un nouvel onglet pour affichage
    window.open(`gestion_paie.php?action=voir_bulletin&id=${id}`, '_blank');
    NotificationManager.show('Ouverture du bulletin...', 'info');
}

// Fonction pour TÉLÉCHARGER le bulletin (forcer le téléchargement)
function telechargerBulletin(id) {
    // Créer un lien de téléchargement temporaire
    const link = document.createElement('a');
    link.href = `gestion_paie.php?action=telecharger_bulletin&id=${id}`;
    link.download = `bulletin_${id}.pdf`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    NotificationManager.show('Téléchargement en cours...', 'info');
}
function telechargerBulletin(id) {
    const link = document.createElement('a');
    link.href = `?action=telecharger_bulletin&id=${id}`;
    link.download = `bulletin_${id}.pdf`;
    link.click();
    NotificationManager.show('Téléchargement en cours...', 'info');
}

async function supprimerBulletin(id) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer ce bulletin ?')) {
        return;
    }
    
    try {
        const result = await Utils.apiCall('supprimer_bulletin', { bulletin_id: id }, 'POST');
        
        if (result.success) {
            NotificationManager.show('Bulletin supprimé avec succès', 'success');
            setTimeout(() => chargerBulletins(), 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function modifierBulletin(id) {
    try {
        // Récupérer les données actuelles du bulletin
        const result = await Utils.apiCall('get_bulletin_details', { bulletin_id: id });
        
        if (!result.success) {
            NotificationManager.show('Erreur: ' + (result.error || 'Bulletin introuvable'), 'error');
            return;
        }
        
        const bulletin = result.bulletin;
        
        if (bulletin.statut !== 'brouillon') {
            NotificationManager.show('Seuls les bulletins en brouillon peuvent être modifiés', 'warning');
            return;
        }
        
        const content = `
            <form id="formModifierBulletin" class="space-y-4">
                <input type="hidden" name="bulletin_id" value="${bulletin.id}">
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                        <i class="fas fa-edit text-blue-600 mr-2"></i>
                        <div>
                            <span class="text-sm text-blue-800 font-medium">
                                Modification du bulletin de ${bulletin.employe_nom}
                            </span>
                            <p class="text-xs text-blue-700">
                                Période: ${Utils.formatPeriod(bulletin.mois, bulletin.annee)}
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Salaire de base (FCFA) *</label>
                        <input type="number" name="salaire_base" required min="0" step="1000"
                               value="${bulletin.salaire_base || 0}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Heures supplémentaires</label>
                        <input type="number" name="heures_supplementaires" min="0" step="0.5"
                               value="${bulletin.heures_supplementaires || 0}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prime de transport (FCFA)</label>
                        <input type="number" name="prime_transport" min="0" step="1000"
                               value="${bulletin.prime_transport || 0}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prime de logement (FCFA)</label>
                        <input type="number" name="prime_logement" min="0" step="1000"
                               value="${bulletin.prime_logement || 0}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jours d'absence</label>
                        <input type="number" name="jours_absence" min="0" max="31"
                               value="${bulletin.jours_absence || 0}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Avances déduites (FCFA)</label>
                        <input type="number" name="avances" min="0" step="1000"
                               value="${bulletin.avances || 0}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
                
                <div id="calcul-preview" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-2">Aperçu du calcul</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div class="space-y-1">
                            <div class="flex justify-between">
                                <span>Salaire de base:</span>
                                <span id="preview-base">${Utils.formatAmount(bulletin.salaire_base || 0)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Heures supplémentaires:</span>
                                <span id="preview-heures">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Primes:</span>
                                <span id="preview-primes">-</span>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <div class="flex justify-between">
                                <span>Déductions absences:</span>
                                <span id="preview-absences" class="text-red-600">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Avances:</span>
                                <span id="preview-avances-calc" class="text-red-600">-</span>
                            </div>
                            <div class="flex justify-between font-medium border-t pt-1">
                                <span>Salaire net:</span>
                                <span id="preview-net" class="text-green-600">-</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Commentaires</label>
                    <textarea name="commentaires" rows="3" 
                              class="w-full border border-gray-300 rounded-lg px-3 py-2"
                              placeholder="Commentaires sur les modifications...">${bulletin.commentaires || ''}</textarea>
                </div>
            </form>
        `;
        
        const footer = `
            <button onclick="ModalManager.close('modalModifierBulletin')" 
                    class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
                Annuler
            </button>
            <button onclick="sauvegarderModificationBulletin()" 
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md">
                Sauvegarder les modifications
            </button>
        `;
        
        ModalManager.create('modalModifierBulletin', 'Modifier le bulletin de paie', content, footer, 'large');
        ModalManager.open('modalModifierBulletin');
        
        // Écouteurs pour le calcul en temps réel
        const inputs = ['salaire_base', 'heures_supplementaires', 'prime_transport', 'prime_logement', 'jours_absence', 'avances'];
        inputs.forEach(inputName => {
            document.querySelector(`input[name="${inputName}"]`).addEventListener('input', calculerPreviewBulletin);
        });
        
        // Calcul initial
        calculerPreviewBulletin();
        
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

function calculerPreviewBulletin() {
    const salaireBase = parseFloat(document.querySelector('input[name="salaire_base"]').value) || 0;
    const heuresSupp = parseFloat(document.querySelector('input[name="heures_supplementaires"]').value) || 0;
    const primeTransport = parseFloat(document.querySelector('input[name="prime_transport"]').value) || 0;
    const primeLogement = parseFloat(document.querySelector('input[name="prime_logement"]').value) || 0;
    const joursAbsence = parseFloat(document.querySelector('input[name="jours_absence"]').value) || 0;
    const avances = parseFloat(document.querySelector('input[name="avances"]').value) || 0;
    
    // Calculs approximatifs (à adapter selon votre logique)
    const tauxHoraire = salaireBase / 173.33; // Base 35h/semaine
    const montantHeuresSupp = heuresSupp * tauxHoraire * 1.25;
    const totalPrimes = primeTransport + primeLogement;
    const deductionAbsences = joursAbsence * (salaireBase / 22); // Base 22 jours ouvrés
    const salaireNet = salaireBase + montantHeuresSupp + totalPrimes - deductionAbsences - avances;
    
    // Mise à jour de l'aperçu
    document.getElementById('preview-base').textContent = Utils.formatAmount(salaireBase);
    document.getElementById('preview-heures').textContent = Utils.formatAmount(montantHeuresSupp);
    document.getElementById('preview-primes').textContent = Utils.formatAmount(totalPrimes);
    document.getElementById('preview-absences').textContent = Utils.formatAmount(deductionAbsences);
    document.getElementById('preview-avances-calc').textContent = Utils.formatAmount(avances);
    document.getElementById('preview-net').textContent = Utils.formatAmount(salaireNet);
}

async function sauvegarderModificationBulletin() {
    const form = document.getElementById('formModifierBulletin');
    if (!form) return;
    
    const formData = new FormData(form);
    const data = {};
    
    // Convertir FormData en objet
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Validation
    if (!data.salaire_base || parseFloat(data.salaire_base) <= 0) {
        NotificationManager.show('Le salaire de base est obligatoire', 'error');
        return;
    }
    
    try {
        const result = await Utils.apiCall('modifier_bulletin', data, 'POST');
        
        if (result.success) {
            ModalManager.close('modalModifierBulletin');
            NotificationManager.show('Bulletin modifié avec succès', 'success');
            setTimeout(() => chargerBulletins(), 1500);
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    }
}

async function genererBulletinsMasse() {
    const content = `
        <div class="space-y-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-users text-green-600 mr-2"></i>
                    <span class="text-sm text-green-800">Génération automatique des bulletins pour plusieurs employés</span>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Période de paie *</label>
                    <input type="month" id="periode-masse" required 
                           value="${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mode de génération</label>
                    <select id="mode-generation" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="INTEGRE">Avec intégration présences</option>
                        <option value="CLASSIQUE">Mode classique</option>
                    </select>
                </div>
            </div>
            
            <div class="space-y-3">
                <h4 class="font-medium text-gray-900">Filtres de sélection</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Département</label>
                        <select id="filtre-dept-masse" class="w-full border border-gray-300 rounded px-3 py-2">
                            <option value="">Tous les départements</option>
                            ${departements.map(dept => `<option value="${dept.id}">${dept.nom}</option>`).join('')}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type de contrat</label>
                        <select id="filtre-contrat-masse" class="w-full border border-gray-300 rounded px-3 py-2">
                            <option value="">Tous les contrats</option>
                            <option value="CDI">CDI</option>
                            <option value="CDD">CDD</option>
                            <option value="STAGE">Stage</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button onclick="previewEmployesMasse()" 
                                class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">
                            Prévisualiser
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="preview-employes-masse" class="hidden bg-gray-50 border rounded-lg p-4">
                <h4 class="font-medium text-gray-900 mb-3">Employés sélectionnés pour la génération</h4>
                <div id="liste-employes-masse" class="max-h-40 overflow-y-auto space-y-2">
                    <!-- Sera rempli par JavaScript -->
                </div>
                <div class="mt-3 pt-3 border-t flex justify-between">
                    <span class="text-sm text-gray-600">Total employés :</span>
                    <span id="total-employes-masse" class="text-sm font-medium">0</span>
                </div>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h4 class="font-medium text-gray-900 mb-2">Options de génération</h4>
                <div class="space-y-3">
                    <label class="flex items-center">
                        <input type="checkbox" id="inclure-heures-supp" checked class="mr-2">
                        <span class="text-sm">Inclure les heures supplémentaires automatiquement</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" id="inclure-primes" checked class="mr-2">
                        <span class="text-sm">Inclure les primes validées de la période</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" id="inclure-avances" checked class="mr-2">
                        <span class="text-sm">Déduire les avances approuvées</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" id="ignorer-existants" checked class="mr-2">
                        <span class="text-sm">Ignorer les employés ayant déjà un bulletin pour cette période</span>
                    </label>
                </div>
            </div>
            
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-orange-600 mr-3 mt-1"></i>
                    <div class="text-sm text-orange-800">
                        <p class="font-medium mb-1">Attention</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Cette opération peut prendre plusieurs minutes selon le nombre d'employés</li>
                            <li>Les bulletins générés seront en statut "brouillon" et pourront être modifiés</li>
                            <li>Vérifiez les paramètres avant de lancer la génération</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalGenererMasse')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Annuler
        </button>
        <button onclick="lancerGenerationMasse()" id="btn-generer-masse"
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md">
            Lancer la génération
        </button>
    `;
    
    ModalManager.create('modalGenererMasse', 'Génération en masse des bulletins', content, footer, 'large');
    ModalManager.open('modalGenererMasse');
}

async function previewEmployesMasse() {
    const periode = document.getElementById('periode-masse').value;
    if (!periode) {
        NotificationManager.show('Veuillez sélectionner une période', 'error');
        return;
    }
    
    const filtres = {
        departement_id: document.getElementById('filtre-dept-masse').value,
        type_contrat: document.getElementById('filtre-contrat-masse').value,
        statut: 'actif'
    };
    
    const ignoreExistants = document.getElementById('ignorer-existants').checked;
    const periodeParts = periode.split('-');
    
    try {
        const result = await Utils.apiCall('preview_employes_masse', {
            ...filtres,
            mois: parseInt(periodeParts[1]),
            annee: parseInt(periodeParts[0]),
            ignorer_existants: ignoreExistants
        });
        
        if (result.success) {
            const employesDisponibles = result.employes;
            
            document.getElementById('liste-employes-masse').innerHTML = employesDisponibles.map(emp => {
                const hasBulletin = emp.has_bulletin ? ' (bulletin existant)' : '';
                const classItem = emp.has_bulletin ? 'text-gray-500 line-through' : '';
                
                return `
                    <div class="flex justify-between items-center text-sm bg-white p-2 rounded ${classItem}">
                        <span>${emp.prenom} ${emp.nom} - ${emp.poste_nom || 'N/A'}${hasBulletin}</span>
                        <span class="text-gray-600">
                            Salaire: ${Utils.formatAmount(emp.salaire_base || 0)}
                        </span>
                    </div>
                `;
            }).join('');
            
            const employesEligibles = ignoreExistants 
                ? employesDisponibles.filter(emp => !emp.has_bulletin)
                : employesDisponibles;
                
            document.getElementById('total-employes-masse').textContent = employesEligibles.length;
            document.getElementById('preview-employes-masse').classList.remove('hidden');
            
            if (employesEligibles.length === 0) {
                NotificationManager.show('Aucun employé éligible trouvé pour cette période', 'warning');
            }
        }
    } catch (error) {
        console.error('Erreur preview:', error);
        NotificationManager.show('Erreur lors de la prévisualisation', 'error');
    }
}

async function lancerGenerationMasse() {
    const periode = document.getElementById('periode-masse').value;
    if (!periode) {
        NotificationManager.show('Veuillez sélectionner une période', 'error');
        return;
    }
    
    if (!confirm('Êtes-vous sûr de vouloir générer les bulletins en masse ? Cette opération peut prendre plusieurs minutes.')) {
        return;
    }
    
    const periodeParts = periode.split('-');
    const data = {
        mois: parseInt(periodeParts[1]),
        annee: parseInt(periodeParts[0]),
        mode_generation: document.getElementById('mode-generation').value,
        filtres: {
            departement_id: document.getElementById('filtre-dept-masse').value || null,
            type_contrat: document.getElementById('filtre-contrat-masse').value || null
        },
        options: {
            inclure_heures_supp: document.getElementById('inclure-heures-supp').checked,
            inclure_primes: document.getElementById('inclure-primes').checked,
            inclure_avances: document.getElementById('inclure-avances').checked,
            ignorer_existants: document.getElementById('ignorer-existants').checked
        }
    };
    
    try {
        document.getElementById('btn-generer-masse').disabled = true;
        document.getElementById('btn-generer-masse').innerHTML = '<div class="loading show mr-2"></div>Génération en cours...';
        
        const result = await Utils.apiCall('generer_bulletins_masse', data, 'POST');
        
        if (result.success) {
            ModalManager.close('modalGenererMasse');
            NotificationManager.show(`${result.count} bulletin(s) généré(s) avec succès`, 'success');
            setTimeout(() => chargerBulletins(), 2000);
            
            // Afficher un résumé
            if (result.errors && result.errors.length > 0) {
                setTimeout(() => {
                    NotificationManager.show(`Attention: ${result.errors.length} erreur(s) lors de la génération`, 'warning');
                }, 3000);
            }
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    } finally {
        document.getElementById('btn-generer-masse').disabled = false;
        document.getElementById('btn-generer-masse').innerHTML = 'Lancer la génération';
    }
}

function exporterBulletins() {
    const mois = new Date().getMonth() + 1;
    const annee = new Date().getFullYear();
    
    window.open(`?action=export_csv&mois=${mois}&annee=${annee}`, '_blank');
    NotificationManager.show('Export en cours...', 'info');
}

async function voirStatistiquesPaie() {
    const content = `
        <div class="space-y-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-chart-line text-blue-600 mr-2"></i>
                    <span class="text-sm text-blue-800">Statistiques détaillées de la paie pour la période sélectionnée</span>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Période début</label>
                    <input type="month" id="stats-debut" 
                           value="${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Période fin</label>
                    <input type="month" id="stats-fin"
                           value="${new Date().getFullYear()}-${String(new Date().getMonth() + 1).padStart(2, '0')}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>

            <div id="loading-stats" class="hidden text-center py-4">
                <div class="loading show"></div>
                <p class="text-gray-600 mt-2">Chargement des statistiques...</p>
            </div>

            <div id="stats-results" class="hidden space-y-6">
                <!-- Résumé général -->
                <div class="bg-white border rounded-lg p-6">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Résumé général</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="resume-stats">
                        <!-- Sera rempli par JavaScript -->
                    </div>
                </div>

                <!-- Graphiques -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white border rounded-lg p-6">
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Évolution mensuelle</h4>
                        <canvas id="evolution-chart" height="200"></canvas>
                    </div>
                    <div class="bg-white border rounded-lg p-6">
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Répartition par département</h4>
                        <canvas id="departement-chart" height="200"></canvas>
                    </div>
                </div>

                <!-- Détails par employé -->
                <div class="bg-white border rounded-lg p-6">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Top 10 salaires</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employé</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Poste</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Salaire moyen</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bulletins</th>
                                </tr>
                            </thead>
                            <tbody id="top-salaires" class="bg-white divide-y divide-gray-200">
                                <!-- Sera rempli par JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalStatistiquesPaie')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Fermer
        </button>
        <button onclick="genererStatistiquesPaie()" 
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md">
            Générer statistiques
        </button>
        <button onclick="exporterStatistiques()" id="btn-export-stats" 
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md hidden">
            Exporter PDF
        </button>
    `;
    
    ModalManager.create('modalStatistiquesPaie', 'Statistiques détaillées de la paie', content, footer, 'xlarge');
    ModalManager.open('modalStatistiquesPaie');
}

async function genererStatistiquesPaie() {
    const debut = document.getElementById('stats-debut').value;
    const fin = document.getElementById('stats-fin').value;
    
    if (!debut || !fin) {
        NotificationManager.show('Veuillez sélectionner les périodes', 'error');
        return;
    }
    
    if (debut > fin) {
        NotificationManager.show('La période de début doit être antérieure à la période de fin', 'error');
        return;
    }
    
    document.getElementById('loading-stats').classList.remove('hidden');
    document.getElementById('stats-results').classList.add('hidden');
    
    try {
        const result = await Utils.apiCall('get_statistiques_detaillees', {
            debut: debut,
            fin: fin
        });
        
        if (result.success) {
            afficherStatistiquesDetailees(result.stats);
            document.getElementById('btn-export-stats').classList.remove('hidden');
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    } finally {
        document.getElementById('loading-stats').classList.add('hidden');
    }
}

function afficherStatistiquesDetailees(stats) {
    // Résumé général
    document.getElementById('resume-stats').innerHTML = `
        <div class="text-center">
            <div class="text-2xl font-bold text-blue-600">${stats.total_employes || 0}</div>
            <div class="text-sm text-gray-600">Employés</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-green-600">${stats.total_bulletins || 0}</div>
            <div class="text-sm text-gray-600">Bulletins</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-purple-600">${Utils.formatAmount(stats.masse_salariale || 0)}</div>
            <div class="text-sm text-gray-600">Masse salariale</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-orange-600">${Utils.formatAmount(stats.salaire_moyen || 0)}</div>
            <div class="text-sm text-gray-600">Salaire moyen</div>
        </div>
    `;
    
    // Top salaires
    const topSalaires = stats.top_salaires || [];
    document.getElementById('top-salaires').innerHTML = topSalaires.map((emp, index) => `
        <tr class="${index < 3 ? 'bg-yellow-50' : ''}">
            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                ${index < 3 ? `<i class="fas fa-trophy text-yellow-500 mr-2"></i>` : ''}
                ${emp.nom_complet}
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">${emp.poste || 'N/A'}</td>
            <td class="px-4 py-3 text-sm font-medium text-gray-900">${Utils.formatAmount(emp.salaire_moyen)}</td>
            <td class="px-4 py-3 text-sm text-gray-600">${emp.nb_bulletins}</td>
        </tr>
    `).join('');
    
    document.getElementById('stats-results').classList.remove('hidden');
    NotificationManager.show('Statistiques générées avec succès', 'success');
}

function exporterStatistiques() {
    NotificationManager.show('Export des statistiques en cours...', 'info');
    window.open('?action=export_statistiques_pdf&debut=' + document.getElementById('stats-debut').value + '&fin=' + document.getElementById('stats-fin').value, '_blank');
}
function appliquerFiltresBulletins() {
    chargerBulletins();
}

async function voirCalendrierConges() {
    const content = `
        <div class="space-y-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-calendar-alt text-green-600 mr-2"></i>
                    <span class="text-sm text-green-800">Vue calendrier des congés pour tous les employés</span>
                </div>
            </div>
            
            <div class="flex justify-between items-center">
                <div class="flex space-x-4">
                    <button onclick="changerMoisCalendrier(-1)" class="p-2 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h3 id="mois-calendrier" class="text-lg font-medium text-gray-900"></h3>
                    <button onclick="changerMoisCalendrier(1)" class="p-2 text-gray-600 hover:text-gray-800">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <div class="flex space-x-2">
                    <select id="filtre-employe-calendrier" class="border border-gray-300 rounded px-3 py-1 text-sm">
                        <option value="">Tous les employés</option>
                        ${employes.map(emp => `<option value="${emp.id}">${emp.prenom} ${emp.nom}</option>`).join('')}
                    </select>
                    <button onclick="actualiserCalendrier()" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded">
                        <i class="fas fa-sync mr-1"></i>Actualiser
                    </button>
                </div>
            </div>

            <!-- Légende -->
            <div class="flex flex-wrap gap-4 text-sm">
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-blue-200 rounded mr-2"></div>
                    <span>Congé approuvé</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-yellow-200 rounded mr-2"></div>
                    <span>En attente</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-red-200 rounded mr-2"></div>
                    <span>Refusé</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-green-200 rounded mr-2"></div>
                    <span>Week-end</span>
                </div>
            </div>

            <!-- Calendrier -->
            <div class="bg-white border rounded-lg overflow-hidden">
                <div class="grid grid-cols-7 gap-0 bg-gray-50">
                    <div class="p-2 text-center text-xs font-medium text-gray-500 uppercase">Lun</div>
                    <div class="p-2 text-center text-xs font-medium text-gray-500 uppercase">Mar</div>
                    <div class="p-2 text-center text-xs font-medium text-gray-500 uppercase">Mer</div>
                    <div class="p-2 text-center text-xs font-medium text-gray-500 uppercase">Jeu</div>
                    <div class="p-2 text-center text-xs font-medium text-gray-500 uppercase">Ven</div>
                    <div class="p-2 text-center text-xs font-medium text-gray-500 uppercase">Sam</div>
                    <div class="p-2 text-center text-xs font-medium text-gray-500 uppercase">Dim</div>
                </div>
                <div id="jours-calendrier" class="grid grid-cols-7 gap-0">
                    <!-- Sera rempli par JavaScript -->
                </div>
            </div>

            <!-- Liste des congés du mois -->
            <div class="bg-white border rounded-lg p-4">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Congés du mois</h4>
                <div id="liste-conges-mois" class="space-y-2 max-h-60 overflow-y-auto">
                    <!-- Sera rempli par JavaScript -->
                </div>
            </div>
        </div>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalCalendrierConges')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Fermer
        </button>
        <button onclick="ouvrirModalConge()" 
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md">
            Nouveau congé
        </button>
    `;
    
    ModalManager.create('modalCalendrierConges', 'Calendrier des congés', content, footer, 'xlarge');
    ModalManager.open('modalCalendrierConges');
    
    // Initialisation du calendrier
    window.calendrierDate = new Date();
    await chargerCalendrierConges();
    
    // Écouteur pour le filtre employé
    document.getElementById('filtre-employe-calendrier').addEventListener('change', actualiserCalendrier);
}

async function chargerCalendrierConges() {
    const employeId = document.getElementById('filtre-employe-calendrier')?.value || '';
    
    try {
        const result = await Utils.apiCall('get_conges_calendrier', {
            mois: window.calendrierDate.getMonth() + 1,
            annee: window.calendrierDate.getFullYear(),
            employe_id: employeId
        });
        
        if (result.success) {
            afficherCalendrierConges(result.conges);
        } else {
            console.error('Erreur chargement calendrier:', result.error);
        }
    } catch (error) {
        console.error('Erreur:', error);
    }
}

function afficherCalendrierConges(conges) {
    const moisFr = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    
    // Mettre à jour le titre
    document.getElementById('mois-calendrier').textContent = 
        `${moisFr[window.calendrierDate.getMonth()]} ${window.calendrierDate.getFullYear()}`;
    
    // Générer les jours du calendrier
    const premierjour = new Date(window.calendrierDate.getFullYear(), window.calendrierDate.getMonth(), 1);
    const dernierjour = new Date(window.calendrierDate.getFullYear(), window.calendrierDate.getMonth() + 1, 0);
    
    // Ajuster le premier jour (Lundi = 0)
    const premierJourSemaine = (premierjour.getDay() + 6) % 7;
    
    let joursHTML = '';
    
    // Jours du mois précédent
    for (let i = premierJourSemaine - 1; i >= 0; i--) {
        const jour = new Date(premierjour);
        jour.setDate(jour.getDate() - i - 1);
        joursHTML += `<div class="p-2 h-20 bg-gray-50 text-gray-400 text-sm">${jour.getDate()}</div>`;
    }
    
    // Jours du mois actuel
    for (let jour = 1; jour <= dernierjour.getDate(); jour++) {
        const dateActuelle = new Date(window.calendrierDate.getFullYear(), window.calendrierDate.getMonth(), jour);
        const isWeekend = dateActuelle.getDay() === 0 || dateActuelle.getDay() === 6;
        
        // Chercher les congés pour ce jour
        const congesJour = conges.filter(conge => {
            const debut = new Date(conge.date_debut);
            const fin = new Date(conge.date_fin);
            return dateActuelle >= debut && dateActuelle <= fin;
        });
        
        let classeJour = 'p-2 h-20 border-r border-b text-sm relative hover:bg-gray-50';
        if (isWeekend) classeJour += ' bg-green-100';
        
        let contenuConges = '';
        congesJour.forEach(conge => {
            const employe = employes.find(emp => emp.id == conge.employe_id);
            let classeConge = 'absolute bottom-0 left-0 right-0 text-xs p-1 truncate ';
            
            switch (conge.statut) {
                case 'approuve':
                    classeConge += 'bg-blue-200 text-blue-800';
                    break;
                case 'en_attente':
                    classeConge += 'bg-yellow-200 text-yellow-800';
                    break;
                case 'refuse':
                    classeConge += 'bg-red-200 text-red-800';
                    break;
            }
            
            contenuConges += `<div class="${classeConge}" title="${employe ? employe.prenom + ' ' + employe.nom : 'Inconnu'} - ${conge.type || 'Congé'}">${employe ? employe.prenom.charAt(0) + '.' + employe.nom : '?'}</div>`;
        });
        
        joursHTML += `
            <div class="${classeJour}">
                <div class="font-medium">${jour}</div>
                ${contenuConges}
            </div>
        `;
    }
    
    document.getElementById('jours-calendrier').innerHTML = joursHTML;
    
    // Afficher la liste des congés du mois
    const congesMois = conges.filter(conge => {
        const debut = new Date(conge.date_debut);
        return debut.getMonth() === window.calendrierDate.getMonth() && 
               debut.getFullYear() === window.calendrierDate.getFullYear();
    });
    
    if (congesMois.length === 0) {
        document.getElementById('liste-conges-mois').innerHTML = 
            '<div class="text-center text-gray-500 py-4">Aucun congé ce mois-ci</div>';
    } else {
        document.getElementById('liste-conges-mois').innerHTML = congesMois.map(conge => {
            const employe = employes.find(emp => emp.id == conge.employe_id);
            const statusConfig = {
                'approuve': { class: 'bg-blue-100 text-blue-800', text: 'Approuvé' },
                'en_attente': { class: 'bg-yellow-100 text-yellow-800', text: 'En attente' },
                'refuse': { class: 'bg-red-100 text-red-800', text: 'Refusé' }
            };
            const status = statusConfig[conge.statut] || statusConfig['en_attente'];
            
            return `
                <div class="flex items-center justify-between p-3 border border-gray-200 rounded">
                    <div>
                        <div class="font-medium text-gray-900">
                            ${employe ? employe.prenom + ' ' + employe.nom : 'Employé inconnu'}
                        </div>
                        <div class="text-sm text-gray-600">
                            ${Utils.capitalize(conge.type || 'Congé')} - 
                            Du ${new Date(conge.date_debut).toLocaleDateString('fr-FR')} 
                            au ${new Date(conge.date_fin).toLocaleDateString('fr-FR')}
                            (${conge.nb_jours || 0} jour${(conge.nb_jours || 0) > 1 ? 's' : ''})
                        </div>
                    </div>
                    <span class="px-2 py-1 rounded text-xs font-medium ${status.class}">
                        ${status.text}
                    </span>
                </div>
            `;
        }).join('');
    }
}

function changerMoisCalendrier(direction) {
    window.calendrierDate.setMonth(window.calendrierDate.getMonth() + direction);
    actualiserCalendrier();
}

async function actualiserCalendrier() {
    await chargerCalendrierConges();
}

async function initialiserSoldes() {
    const content = `
        <div class="space-y-4">
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-orange-600 mr-3 mt-1"></i>
                    <div class="text-sm text-orange-800">
                        <p class="font-medium mb-2">Attention : Action irréversible</p>
                        <p>Cette opération va réinitialiser les soldes de congés pour tous les employés actifs selon les paramètres définis ci-dessous.</p>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Année d'attribution *</label>
                    <input type="number" id="annee-soldes" min="2020" max="2030"
                           value="${new Date().getFullYear()}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Solde congés annuels (jours)</label>
                    <input type="number" id="solde-annuel" min="0" max="60" value="25"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Solde congés maladie (jours)</label>
                    <input type="number" id="solde-maladie" min="0" max="30" value="10"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mode d'attribution</label>
                    <select id="mode-attribution" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="NOUVEAU">Réinitialiser complètement</option>
                        <option value="AJOUT">Ajouter au solde existant</option>
                        <option value="PRORATA">Calcul au prorata de l'ancienneté</option>
                    </select>
                </div>
            </div>
            
            <div id="filtre-employes" class="space-y-3">
                <h4 class="font-medium text-gray-900">Filtres employés</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Département</label>
                        <select id="filtre-dept" class="w-full border border-gray-300 rounded px-3 py-2">
                            <option value="">Tous les départements</option>
                            ${departements.map(dept => `<option value="${dept.id}">${dept.nom}</option>`).join('')}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type de contrat</label>
                        <select id="filtre-contrat" class="w-full border border-gray-300 rounded px-3 py-2">
                            <option value="">Tous les contrats</option>
                            <option value="CDI">CDI</option>
                            <option value="CDD">CDD</option>
                            <option value="STAGE">Stage</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button onclick="previewSoldesEmployes()" 
                                class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">
                            Prévisualiser
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="preview-soldes" class="hidden bg-gray-50 border rounded-lg p-4">
                <h4 class="font-medium text-gray-900 mb-3">Aperçu des employés concernés</h4>
                <div id="liste-preview" class="max-h-40 overflow-y-auto space-y-2">
                    <!-- Sera rempli par JavaScript -->
                </div>
                <div class="mt-3 pt-3 border-t">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Total employés :</span>
                        <span id="total-employes-preview" class="font-medium">0</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Coût total congés :</span>
                        <span id="cout-total-preview" class="font-medium">-</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 mr-2 mt-1"></i>
                    <div class="text-sm text-blue-800">
                        <p class="font-medium mb-1">Information</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Les soldes seront appliqués uniquement aux employés actifs</li>
                            <li>Un historique de cette opération sera conservé</li>
                            <li>Les soldes existants seront écrasés en mode "Réinitialiser"</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalInitSoldes')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Annuler
        </button>
        <button onclick="confirmerInitialisationSoldes()" id="btn-confirmer-soldes"
                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md">
            Confirmer l'initialisation
        </button>
    `;
    
    ModalManager.create('modalInitSoldes', 'Initialiser les soldes de congés', content, footer, 'large');
    ModalManager.open('modalInitSoldes');
}

async function previewSoldesEmployes() {
    const filtres = {
        departement_id: document.getElementById('filtre-dept').value,
        type_contrat: document.getElementById('filtre-contrat').value,
        statut: 'actif'
    };
    
    try {
        const result = await Utils.apiCall('get_employes_pour_soldes', filtres);
        
        if (result.success) {
            const employesConcernes = result.employes;
            const soldeAnnuel = parseInt(document.getElementById('solde-annuel').value) || 0;
            const soldeMaladie = parseInt(document.getElementById('solde-maladie').value) || 0;
            
            document.getElementById('liste-preview').innerHTML = employesConcernes.map(emp => `
                <div class="flex justify-between items-center text-sm bg-white p-2 rounded">
                    <span>${emp.prenom} ${emp.nom} - ${emp.poste_nom || 'N/A'}</span>
                    <span class="text-gray-600">
                        Annuel: ${soldeAnnuel}j | Maladie: ${soldeMaladie}j
                    </span>
                </div>
            `).join('');
            
            document.getElementById('total-employes-preview').textContent = employesConcernes.length;
            
            // Estimation du coût (très approximative)
            const coutEstime = employesConcernes.length * soldeAnnuel * 50000; // 50k FCFA par jour
            document.getElementById('cout-total-preview').textContent = Utils.formatAmount(coutEstime);
            
            document.getElementById('preview-soldes').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Erreur preview:', error);
        NotificationManager.show('Erreur lors de la prévisualisation', 'error');
    }
}

async function confirmerInitialisationSoldes() {
    if (!confirm('Êtes-vous absolument sûr de vouloir initialiser les soldes ? Cette action est irréversible.')) {
        return;
    }
    
    const data = {
        annee: parseInt(document.getElementById('annee-soldes').value),
        solde_annuel: parseInt(document.getElementById('solde-annuel').value),
        solde_maladie: parseInt(document.getElementById('solde-maladie').value),
        mode_attribution: document.getElementById('mode-attribution').value,
        filtres: {
            departement_id: document.getElementById('filtre-dept').value || null,
            type_contrat: document.getElementById('filtre-contrat').value || null
        }
    };
    
    if (!data.annee || data.solde_annuel < 0) {
        NotificationManager.show('Veuillez remplir correctement tous les champs', 'error');
        return;
    }
    
    try {
        document.getElementById('btn-confirmer-soldes').disabled = true;
        document.getElementById('btn-confirmer-soldes').innerHTML = '<div class="loading show mr-2"></div>Initialisation...';
        
        const result = await Utils.apiCall('initialiser_soldes_conges', data, 'POST');
        
        if (result.success) {
            ModalManager.close('modalInitSoldes');
            NotificationManager.show(`Soldes initialisés pour ${result.count} employé(s)`, 'success');
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    } finally {
        document.getElementById('btn-confirmer-soldes').disabled = false;
        document.getElementById('btn-confirmer-soldes').innerHTML = 'Confirmer l\'initialisation';
    }
}

async function voirRapportAvances() {
    const content = `
        <div class="space-y-6">
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-chart-line text-orange-600 mr-2"></i>
                    <span class="text-sm text-orange-800">Rapport détaillé des avances sur salaire</span>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date début</label>
                    <input type="date" id="rapport-debut" 
                           value="${new Date(Date.now() - 30*24*60*60*1000).toISOString().split('T')[0]}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date fin</label>
                    <input type="date" id="rapport-fin" 
                           value="${new Date().toISOString().split('T')[0]}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                    <select id="rapport-statut" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Tous les statuts</option>
                        <option value="en_attente">En attente</option>
                        <option value="approuve">Approuvé</option>
                        <option value="refuse">Refusé</option>
                        <option value="rembourse">Remboursé</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button onclick="genererRapportAvances()" 
                            class="w-full px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg">
                        Générer rapport
                    </button>
                </div>
            </div>

            <div id="loading-rapport" class="hidden text-center py-8">
                <div class="loading show"></div>
                <p class="text-gray-600 mt-2">Génération du rapport en cours...</p>
            </div>

            <div id="rapport-results" class="hidden space-y-6">
                <!-- Statistiques résumé -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white border rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-orange-600" id="total-avances">0</div>
                        <div class="text-sm text-gray-600">Total avances</div>
                    </div>
                    <div class="bg-white border rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-green-600" id="montant-total">0</div>
                        <div class="text-sm text-gray-600">Montant total</div>
                    </div>
                    <div class="bg-white border rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-blue-600" id="montant-rembourse">0</div>
                        <div class="text-sm text-gray-600">Remboursé</div>
                    </div>
                    <div class="bg-white border rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-red-600" id="montant-restant">0</div>
                        <div class="text-sm text-gray-600">Reste à rembourser</div>
                    </div>
                </div>

                <!-- Graphique par mois -->
                <div class="bg-white border rounded-lg p-6">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Évolution mensuelle des avances</h4>
                    <canvas id="avances-chart" height="200"></canvas>
                </div>

                <!-- Top employés -->
                <div class="bg-white border rounded-lg p-6">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Top 10 employés - Montant total avances</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employé</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nb avances</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant total</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Remboursé</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Restant</th>
                                </tr>
                            </thead>
                            <tbody id="top-employes-avances" class="bg-white divide-y divide-gray-200">
                                <!-- Sera rempli par JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Liste détaillée -->
                <div class="bg-white border rounded-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-lg font-medium text-gray-900">Liste détaillée des avances</h4>
                        <button onclick="exporterRapportAvances()" 
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded">
                            <i class="fas fa-download mr-2"></i>Exporter Excel
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employé</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Motif</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="liste-avances-detaillee" class="bg-white divide-y divide-gray-200">
                                <!-- Sera rempli par JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const footer = `
        <button onclick="ModalManager.close('modalRapportAvances')" 
                class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
            Fermer
        </button>
        <button onclick="imprimerRapport()" id="btn-imprimer-rapport" 
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md hidden">
            <i class="fas fa-print mr-2"></i>Imprimer
        </button>
    `;
    
    ModalManager.create('modalRapportAvances', 'Rapport des avances sur salaire', content, footer, 'xlarge');
    ModalManager.open('modalRapportAvances');
}

async function genererRapportAvances() {
    const debut = document.getElementById('rapport-debut').value;
    const fin = document.getElementById('rapport-fin').value;
    const statut = document.getElementById('rapport-statut').value;
    
    if (!debut || !fin) {
        NotificationManager.show('Veuillez sélectionner les dates', 'error');
        return;
    }
    
    if (debut > fin) {
        NotificationManager.show('La date de début doit être antérieure à la date de fin', 'error');
        return;
    }
    
    document.getElementById('loading-rapport').classList.remove('hidden');
    document.getElementById('rapport-results').classList.add('hidden');
    
    try {
        const result = await Utils.apiCall('get_rapport_avances_detaille', {
            debut: debut,
            fin: fin,
            statut: statut
        });
        
        if (result.success) {
            afficherRapportAvances(result.rapport);
            document.getElementById('btn-imprimer-rapport').classList.remove('hidden');
        } else {
            NotificationManager.show('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur de connexion', 'error');
    } finally {
        document.getElementById('loading-rapport').classList.add('hidden');
    }
}

function afficherRapportAvances(rapport) {
    // Statistiques résumé
    document.getElementById('total-avances').textContent = rapport.stats.total_avances || 0;
    document.getElementById('montant-total').textContent = Utils.formatAmount(rapport.stats.montant_total || 0);
    document.getElementById('montant-rembourse').textContent = Utils.formatAmount(rapport.stats.montant_rembourse || 0);
    document.getElementById('montant-restant').textContent = Utils.formatAmount(rapport.stats.montant_restant || 0);
    
    const avances = rapport.avances || [];
    document.getElementById('liste-avances-detaillee').innerHTML = avances.map(avance => {
        const statusConfig = {
            'en_attente': { class: 'bg-yellow-100 text-yellow-800', text: 'En attente' },
            'approuve': { class: 'bg-green-100 text-green-800', text: 'Approuvé' },
            'refuse': { class: 'bg-red-100 text-red-800', text: 'Refusé' },
            'rembourse': { class: 'bg-blue-100 text-blue-800', text: 'Remboursé' }
        };
        const status = statusConfig[avance.statut] || statusConfig['en_attente'];
        
        // Correction du formatage de la date
        let dateFormatee = 'Date invalide';
        if (avance.date_demande) {
            try {
                const date = new Date(avance.date_demande);
                if (!isNaN(date.getTime())) {
                    dateFormatee = date.toLocaleDateString('fr-FR');
                }
            } catch (e) {
                console.error('Erreur formatage date:', e);
            }
        }
        
        return `
            <tr>
                <td class="px-4 py-3 text-sm text-gray-900">
                    ${dateFormatee}
                </td>
                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                    ${avance.employe_nom || 'Employé inconnu'}
                </td>
                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                    ${Utils.formatAmount(avance.montant_demande || 0)}
                </td>
                <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate" title="${avance.motif || ''}">
                    ${avance.motif || 'Non spécifié'}
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">
                    ${avance.nb_mensualites > 1 ? `${avance.nb_mensualites} mensualités` : 'UNIQUE'}
                </td>
                <td class="px-4 py-3 text-sm">
                    <span class="px-2 py-1 rounded text-xs font-medium ${status.class}">
                        ${status.text}
                    </span>
                </td>
                <td class="px-4 py-3 text-sm">
                    ${avance.statut === 'en_attente' ? `
                        <button onclick="approuverAvance(${avance.id})" 
                                class="text-green-600 hover:text-green-900 mr-2" title="Approuver">
                            <i class="fas fa-check"></i>
                        </button>
                        <button onclick="refuserAvance(${avance.id})" 
                                class="text-red-600 hover:text-red-900" title="Refuser">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : `
                        <button onclick="voirDetailsAvance(${avance.id})" 
                                class="text-blue-600 hover:text-blue-900" title="Voir détails">
                            <i class="fas fa-eye"></i>
                        </button>
                    `}
                </td>
            </tr>
        `;
    }).join('');
    
    document.getElementById('rapport-results').classList.remove('hidden');
    NotificationManager.show('Rapport généré avec succès', 'success');
}

async function voirDetailsConge(id) {
    try {
        // Trouver le congé dans les données chargées ou faire un appel API
        const result = await Utils.apiCall('get_conge_details', { conge_id: id });
        
        if (!result.success) {
            NotificationManager.show('Erreur: Congé introuvable', 'error');
            return;
        }
        
        const conge = result.conge;
        const employe = employes.find(emp => emp.id == conge.employe_id);
        
        const statusConfig = {
            'approuve': { class: 'text-green-600', text: 'Approuvé', icon: 'fa-check-circle' },
            'en_attente': { class: 'text-yellow-600', text: 'En attente', icon: 'fa-clock' },
            'refuse': { class: 'text-red-600', text: 'Refusé', icon: 'fa-times-circle' }
        };
        const status = statusConfig[conge.statut] || statusConfig['en_attente'];
        
        const content = `
            <div class="space-y-6">
                <!-- En-tête avec statut -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">
                            Congé #${conge.id}
                        </h3>
                        <p class="text-sm text-gray-600">
                            Demande créée le ${new Date(conge.date_creation).toLocaleDateString('fr-FR')}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center ${status.class}">
                            <i class="fas ${status.icon} mr-2"></i>
                            <span class="font-medium">${status.text}</span>
                        </div>
                        ${conge.date_validation ? `
                            <p class="text-xs text-gray-500 mt-1">
                                Validé le ${new Date(conge.date_validation).toLocaleDateString('fr-FR')}
                            </p>
                        ` : ''}
                    </div>
                </div>

                <!-- Informations employé -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white border rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3">
                            <i class="fas fa-user text-blue-600 mr-2"></i>
                            Informations employé
                        </h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Nom complet :</span>
                                <span class="font-medium">
                                    ${employe ? `${employe.prenom} ${employe.nom}` : 'Employé inconnu'}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Poste :</span>
                                <span class="font-medium">
                                    ${employe ? (employe.poste_nom || 'Poste non défini') : 'N/A'}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Email :</span>
                                <span class="font-medium">
                                    ${employe ? (employe.email || 'Non défini') : 'N/A'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Détails du congé -->
                    <div class="bg-white border rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3">
                            <i class="fas fa-calendar-alt text-green-600 mr-2"></i>
                            Détails du congé
                        </h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Type :</span>
                                <span class="font-medium">${Utils.capitalize(conge.type || 'Non défini')}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Date début :</span>
                                <span class="font-medium">${new Date(conge.date_debut).toLocaleDateString('fr-FR')}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Date fin :</span>
                                <span class="font-medium">${new Date(conge.date_fin).toLocaleDateString('fr-FR')}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Durée :</span>
                                <span class="font-medium">${conge.nb_jours || 0} jour(s)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Motif et commentaires -->
                ${conge.motif || conge.commentaire ? `
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">
                        <i class="fas fa-comment text-purple-600 mr-2"></i>
                        Motif et commentaires
                    </h4>
                    ${conge.motif ? `
                        <div class="mb-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Motif de la demande :</label>
                            <div class="bg-gray-50 rounded p-3 text-sm text-gray-800">
                                ${conge.motif}
                            </div>
                        </div>
                    ` : ''}
                    ${conge.commentaire ? `
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Commentaire de validation :</label>
                            <div class="bg-gray-50 rounded p-3 text-sm text-gray-800">
                                ${conge.commentaire}
                            </div>
                        </div>
                    ` : ''}
                </div>
                ` : ''}

                <!-- Timeline si disponible -->
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">
                        <i class="fas fa-history text-gray-600 mr-2"></i>
                        Historique
                    </h4>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                            <div class="text-sm">
                                <span class="font-medium">Demande créée</span>
                                <span class="text-gray-500 ml-2">
                                    ${new Date(conge.date_creation).toLocaleString('fr-FR')}
                                </span>
                            </div>
                        </div>
                        ${conge.date_validation ? `
                            <div class="flex items-center">
                                <div class="w-3 h-3 ${status.class.includes('green') ? 'bg-green-500' : status.class.includes('red') ? 'bg-red-500' : 'bg-yellow-500'} rounded-full mr-3"></div>
                                <div class="text-sm">
                                    <span class="font-medium">${status.text}</span>
                                    <span class="text-gray-500 ml-2">
                                        ${new Date(conge.date_validation).toLocaleString('fr-FR')}
                                    </span>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
        
        const footer = `
            <button onclick="ModalManager.close('modalDetailsConge')" 
                    class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
                Fermer
            </button>
            ${conge.statut === 'en_attente' ? `
                <button onclick="approuverCongeDepuisModal(${conge.id})" 
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md">
                    <i class="fas fa-check mr-2"></i>Approuver
                </button>
                <button onclick="refuserCongeDepuisModal(${conge.id})" 
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md">
                    <i class="fas fa-times mr-2"></i>Refuser
                </button>
            ` : ''}
        `;
        
        ModalManager.create('modalDetailsConge', `Détails du congé - ${employe ? `${employe.prenom} ${employe.nom}` : 'Employé'}`, content, footer, 'large');
        ModalManager.open('modalDetailsConge');
        
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur lors du chargement des détails', 'error');
    }
}

// Fonctions pour approuver/refuser depuis le modal
async function approuverCongeDepuisModal(id) {
    ModalManager.close('modalDetailsConge');
    await approuverConge(id);
}

async function refuserCongeDepuisModal(id) {
    ModalManager.close('modalDetailsConge');
    await refuserConge(id);
}

function exporterRapportAvances() {
    const debut = document.getElementById('rapport-debut').value;
    const fin = document.getElementById('rapport-fin').value;
    const statut = document.getElementById('rapport-statut').value;
    
    const params = new URLSearchParams({
        action: 'export_rapport_avances',
        debut: debut,
        fin: fin,
        statut: statut
    });
    
    window.open(`?${params.toString()}`, '_blank');
    NotificationManager.show('Export en cours...', 'info');
}

function imprimerRapport() {
    window.print();
}
// ================== INITIALISATION ==================
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initialisation du système RH intégré...');
    console.log('Employés:', employes.length);
    console.log('Bulletins:', bulletins.length);
    console.log('Postes:', postes.length);
    console.log('Départements:', departements.length);
    
    // Fermeture des modales avec Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('[id*="modal"]');
            modals.forEach(modal => {
                if (modal.classList.contains('flex')) {
                    ModalManager.close(modal.id);
                }
            });
        }
    });
    
    // Chargement initial des données pour l'onglet actif
    chargerBulletins();

    // Mise à jour des statistiques si nécessaire
    if (window.initialData && window.initialData.stats) {
        updateStatsDisplay();
    }

    // Charger le Tableau de Bord RH Avancé
    chargerTableauBordRH();
});

// ================== FONCTIONS UTILITAIRES ==================
function updateStatsDisplay() {
    const elements = {
        'stat-employes': stats.employes_actifs,
        'stat-bulletins': stats.bulletins_mois,
        'stat-conges': stats.conges_attente,
        'stat-avances': stats.avances_attente,
        'stat-primes': stats.primes_attente
    };
    
    Object.keys(elements).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = elements[id];
        }
    });
}

// ================== FONCTIONS TABLEAU DE BORD RH AVANCÉ ==================
function chargerTableauBordRH() {
    // Charger les statistiques avancées
    fetch('gestion_paie.php?action=get_stats_avancees')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Effectif Total
                document.getElementById('totalEmployes').textContent = data.stats.effectif_total || employes.length;

                // Taux de Présence
                document.getElementById('tauxPresence').textContent = (data.stats.taux_presence || 0) + '%';

                // Masse Salariale
                const masseSalariale = data.stats.masse_salariale || 0;
                document.getElementById('masseSalariale').textContent = new Intl.NumberFormat('fr-FR').format(masseSalariale) + ' FCFA';

                // Retard Moyen
                document.getElementById('retardMoyen').textContent = (data.stats.retard_moyen || 0) + ' min';
            } else {
                // Valeurs par défaut si l'API n'est pas disponible
                document.getElementById('totalEmployes').textContent = employes.length;
                document.getElementById('tauxPresence').textContent = '0%';
                document.getElementById('masseSalariale').textContent = '0 FCFA';
                document.getElementById('retardMoyen').textContent = '0 min';
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des statistiques:', error);
            // Valeurs par défaut en cas d'erreur
            document.getElementById('totalEmployes').textContent = employes.length;
            document.getElementById('tauxPresence').textContent = 'N/A';
            document.getElementById('masseSalariale').textContent = 'N/A';
            document.getElementById('retardMoyen').textContent = 'N/A';
        });
}

function generateCustomReport() {
    const reportType = document.getElementById('reportType').value;
    const startDate = document.getElementById('reportStartDate').value;
    const endDate = document.getElementById('reportEndDate').value;

    if (!startDate || !endDate) {
        showNotification('Veuillez sélectionner une période', 'error');
        return;
    }

    // Afficher le loader
    document.getElementById('reportLoading').classList.remove('hidden');

    // Générer le rapport
    fetch('gestion_paie.php?action=generate_custom_report', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            type: reportType,
            date_debut: startDate,
            date_fin: endDate
        })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('reportLoading').classList.add('hidden');

        if (data.success) {
            // Afficher le rapport dans la modal
            document.getElementById('reportModalTitle').textContent = data.title || 'Rapport Personnalisé';
            document.getElementById('reportContent').innerHTML = data.content || '<p class="text-gray-600">Aucune donnée disponible pour cette période.</p>';
            openReportModal();
        } else {
            showNotification(data.message || 'Erreur lors de la génération du rapport', 'error');
        }
    })
    .catch(error => {
        document.getElementById('reportLoading').classList.add('hidden');
        console.error('Erreur:', error);
        showNotification('Erreur lors de la génération du rapport', 'error');
    });
}

function openReportModal() {
    document.getElementById('reportModal').classList.remove('hidden');
}

function closeReportModal() {
    document.getElementById('reportModal').classList.add('hidden');
}

function exportReportToPDF() {
    showNotification('Export PDF en cours de développement', 'info');
}

function exportReportToExcel() {
    showNotification('Export Excel en cours de développement', 'info');
}

async function voirDetailsAvance(id) {
    try {
        const result = await Utils.apiCall('get_avance_details', { avance_id: id });
        
        if (!result.success) {
            NotificationManager.show('Erreur: Avance introuvable', 'error');
            return;
        }
        
        const avance = result.avance;
        const employe = employes.find(emp => emp.id == avance.id_employe);
        
        const statusConfig = {
            'approuve': { class: 'text-green-600', text: 'Approuvé', icon: 'fa-check-circle' },
            'en_attente': { class: 'text-yellow-600', text: 'En attente', icon: 'fa-clock' },
            'refuse': { class: 'text-red-600', text: 'Refusé', icon: 'fa-times-circle' },
            'rembourse': { class: 'text-blue-600', text: 'Remboursé', icon: 'fa-money-bill-wave' }
        };
        const status = statusConfig[avance.statut] || statusConfig['en_attente'];
        
        const content = `
            <div class="space-y-6">
                <!-- En-tête avec statut -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">
                            Avance sur salaire #${avance.id}
                        </h3>
                        <p class="text-sm text-gray-600">
                            Demande créée le ${new Date(avance.date_demande).toLocaleDateString('fr-FR')}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center ${status.class}">
                            <i class="fas ${status.icon} mr-2"></i>
                            <span class="font-medium">${status.text}</span>
                        </div>
                        ${avance.date_validation ? `
                            <p class="text-xs text-gray-500 mt-1">
                                Validé le ${new Date(avance.date_validation).toLocaleDateString('fr-FR')}
                            </p>
                        ` : ''}
                    </div>
                </div>

                <!-- Informations employé et montant -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white border rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3">
                            <i class="fas fa-user text-blue-600 mr-2"></i>
                            Informations employé
                        </h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Nom complet :</span>
                                <span class="font-medium">
                                    ${employe ? `${employe.prenom} ${employe.nom}` : 'Employé inconnu'}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Poste :</span>
                                <span class="font-medium">
                                    ${employe ? (employe.poste_nom || 'Poste non défini') : 'N/A'}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Email :</span>
                                <span class="font-medium">
                                    ${employe ? (employe.email || 'Non défini') : 'N/A'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Détails financiers -->
                    <div class="bg-white border rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-3">
                            <i class="fas fa-money-bill-wave text-green-600 mr-2"></i>
                            Détails financiers
                        </h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Montant demandé :</span>
                                <span class="font-medium text-orange-600">${Utils.formatAmount(avance.montant_demande || 0)}</span>
                            </div>
                            ${avance.montant_accorde ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Montant accordé :</span>
                                    <span class="font-medium text-green-600">${Utils.formatAmount(avance.montant_accorde)}</span>
                                </div>
                            ` : ''}
                            <div class="flex justify-between">
                                <span class="text-gray-600">Nombre de mensualités :</span>
                                <span class="font-medium">${avance.nb_mensualites || 1}</span>
                            </div>
                            ${avance.montant_mensualite ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Montant par mensualité :</span>
                                    <span class="font-medium">${Utils.formatAmount(avance.montant_mensualite)}</span>
                                </div>
                            ` : ''}
                            <div class="flex justify-between">
                                <span class="text-gray-600">Mensualité actuelle :</span>
                                <span class="font-medium">${avance.mensualite_actuelle || 0}/${avance.nb_mensualites || 1}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Motif et commentaires -->
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">
                        <i class="fas fa-comment text-purple-600 mr-2"></i>
                        Motif et commentaires
                    </h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Motif de la demande :</label>
                            <div class="bg-gray-50 rounded p-3 text-sm text-gray-800">
                                ${avance.motif || 'Aucun motif spécifié'}
                            </div>
                        </div>
                        ${avance.commentaire_validation ? `
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Commentaire de validation :</label>
                                <div class="bg-gray-50 rounded p-3 text-sm text-gray-800">
                                    ${avance.commentaire_validation}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>

                <!-- Historique/Timeline -->
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-3">
                        <i class="fas fa-history text-gray-600 mr-2"></i>
                        Historique de la demande
                    </h4>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                            <div class="text-sm">
                                <span class="font-medium">Demande créée</span>
                                <span class="text-gray-500 ml-2">
                                    ${new Date(avance.date_demande).toLocaleString('fr-FR')}
                                </span>
                            </div>
                        </div>
                        ${avance.date_validation ? `
                            <div class="flex items-center">
                                <div class="w-3 h-3 ${status.class.includes('green') ? 'bg-green-500' : status.class.includes('red') ? 'bg-red-500' : status.class.includes('blue') ? 'bg-blue-500' : 'bg-yellow-500'} rounded-full mr-3"></div>
                                <div class="text-sm">
                                    <span class="font-medium">${status.text}</span>
                                    <span class="text-gray-500 ml-2">
                                        ${new Date(avance.date_validation).toLocaleString('fr-FR')}
                                        ${avance.valideur_nom ? ` par ${avance.valideur_prenom} ${avance.valideur_nom}` : ''}
                                    </span>
                                </div>
                            </div>
                        ` : ''}
                        ${avance.date_remboursement_complete ? `
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                <div class="text-sm">
                                    <span class="font-medium">Remboursement terminé</span>
                                    <span class="text-gray-500 ml-2">
                                        ${new Date(avance.date_remboursement_complete).toLocaleString('fr-FR')}
                                    </span>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
        
        const footer = `
            <button onclick="ModalManager.close('modalDetailsAvance')" 
                    class="px-4 py-2 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
                Fermer
            </button>
            ${avance.statut === 'en_attente' ? `
                <button onclick="approuverAvanceDepuisModal(${avance.id})" 
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md">
                    <i class="fas fa-check mr-2"></i>Approuver
                </button>
                <button onclick="refuserAvanceDepuisModal(${avance.id})" 
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md">
                    <i class="fas fa-times mr-2"></i>Refuser
                </button>
            ` : ''}
        `;
        
        ModalManager.create('modalDetailsAvance', `Détails de l'avance - ${employe ? `${employe.prenom} ${employe.nom}` : 'Employé'}`, content, footer, 'large');
        ModalManager.open('modalDetailsAvance');
        
    } catch (error) {
        console.error('Erreur:', error);
        NotificationManager.show('Erreur lors du chargement des détails', 'error');
    }
}

// Fonctions pour approuver/refuser depuis le modal
async function approuverAvanceDepuisModal(id) {
    ModalManager.close('modalDetailsAvance');
    await approuverAvance(id);
}

async function refuserAvanceDepuisModal(id) {
    ModalManager.close('modalDetailsAvance');
    await refuserAvance(id);
}


// ================== FONCTIONS GLOBALES POUR COMPATIBILITÉ ==================
window.showTab = showTab;
window.ouvrirModalGenerationIntegree = ouvrirModalGenerationIntegree;
window.ouvrirModalGenerationClassique = ouvrirModalGenerationClassique;
window.genererBulletinIntegre = genererBulletinIntegre;
window.genererBulletinClassique = genererBulletinClassique;
window.voirBulletin = voirBulletin;
window.telechargerBulletin = telechargerBulletin;
window.modifierBulletin = modifierBulletin;
window.supprimerBulletin = supprimerBulletin;
window.genererBulletinsMasse = genererBulletinsMasse;
window.exporterBulletins = exporterBulletins;
window.voirStatistiquesPaie = voirStatistiquesPaie;
window.appliquerFiltresBulletins = appliquerFiltresBulletins;

// Congés
window.ouvrirModalConge = ouvrirModalConge;
window.approuverConge = approuverConge;
window.refuserConge = refuserConge;
window.voirCalendrierConges = voirCalendrierConges;
window.initialiserSoldes = initialiserSoldes;

// Avances
window.ouvrirModalAvance = ouvrirModalAvance;
window.approuverAvance = approuverAvance;
window.refuserAvance = refuserAvance;
window.voirRapportAvances = voirRapportAvances;

// Primes
window.ouvrirModalPrime = ouvrirModalPrime;
window.genererPrimesPresence = genererPrimesPresence;
window.validerPrime = validerPrime;
window.refuserPrime = refuserPrime;
window.toggleAttributionFields = toggleAttributionFields;

// Ajout des nouvelles fonctions globales
window.initialiserSoldes = initialiserSoldes;
window.previewSoldesEmployes = previewSoldesEmployes;
window.confirmerInitialisationSoldes = confirmerInitialisationSoldes;
window.voirRapportAvances = voirRapportAvances;
window.genererRapportAvances = genererRapportAvances;
window.afficherRapportAvances = afficherRapportAvances;
window.exporterRapportAvances = exporterRapportAvances;
window.imprimerRapport = imprimerRapport;

// Ajout des nouvelles fonctions globales
window.modifierBulletin = modifierBulletin;
window.calculerPreviewBulletin = calculerPreviewBulletin;
window.sauvegarderModificationBulletin = sauvegarderModificationBulletin;
window.genererBulletinsMasse = genererBulletinsMasse;
window.previewEmployesMasse = previewEmployesMasse;
window.lancerGenerationMasse = lancerGenerationMasse;
window.validerBulletin = validerBulletin;

// Ajouter cette fonction aux exports globaux
window.filtrerHistoriqueConges = filtrerHistoriqueConges;
window.afficherHistoriqueConges = afficherHistoriqueConges;

// Ajouter ces nouvelles fonctions aux exports globaux
window.voirDetailsConge = voirDetailsConge;
window.approuverCongeDepuisModal = approuverCongeDepuisModal;
window.refuserCongeDepuisModal = refuserCongeDepuisModal;

// Ajouter aux exports globaux
window.afficherHistoriqueAvances = afficherHistoriqueAvances;
window.filtrerHistoriqueAvances = filtrerHistoriqueAvances;

// Ajouter ces nouvelles fonctions aux exports globaux
window.voirDetailsAvance = voirDetailsAvance;
window.approuverAvanceDepuisModal = approuverAvanceDepuisModal;
window.refuserAvanceDepuisModal = refuserAvanceDepuisModal;

// Ajouter ces nouvelles fonctions aux exports globaux
window.afficherHistoriquePrimes = afficherHistoriquePrimes;
window.filtrerHistoriquePrimes = filtrerHistoriquePrimes;
window.voirDetailsPrime = voirDetailsPrime;

// Ajouter ces nouvelles fonctions aux exports globaux
window.voirDetailsPrime = voirDetailsPrime;
window.validerPrimeDepuisModal = validerPrimeDepuisModal;
window.refuserPrimeDepuisModal = refuserPrimeDepuisModal;
window.imprimerDetailsPrime = imprimerDetailsPrime;

// 8. EXPORTS GLOBAUX
window.marquerPresenceManuelle = marquerPresenceManuelle;
window.sauvegarderPresenceManuelle = sauvegarderPresenceManuelle;
window.verifierCoherencePlanification = verifierCoherencePlanification;
window.afficherIncoherences = afficherIncoherences;  

console.log('Système RH intégré avec présences chargé avec succès');
    </script>

            </div> <!-- Fin container -->
        </div> <!-- Fin flex-1 overflow-y-auto -->
    </div> <!-- Fin flex h-screen -->

</body>
</html>