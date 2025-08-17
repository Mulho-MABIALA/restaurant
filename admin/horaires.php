<?php
session_start();

// Vérification admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "restaurant");
if ($mysqli->connect_errno) {
    die("Erreur de connexion : " . $mysqli->connect_error);
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jours = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
    foreach ($jours as $jour) {
        $ouverture = $_POST["{$jour}_ouverture"] ?? '';
        $fermeture = $_POST["{$jour}_fermeture"] ?? '';
        $ferme = isset($_POST["{$jour}_ferme"]) ? 1 : 0;

        $stmt = $mysqli->prepare("
            INSERT INTO horaires_ouverture (jour, heure_ouverture, heure_fermeture, ferme)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                heure_ouverture = VALUES(heure_ouverture),
                heure_fermeture = VALUES(heure_fermeture),
                ferme = VALUES(ferme)
        ");
        $stmt->bind_param("sssi", $jour, $ouverture, $fermeture, $ferme);
        $stmt->execute();
        $stmt->close();
    }
    $message = "✅ Horaires mis à jour avec succès.";
}

// Récupération des horaires existants pour affichage
$horaires = [];
$result = $mysqli->query("SELECT * FROM horaires_ouverture");   
while ($row = $result->fetch_assoc()) {
    $horaires[$row['jour']] = $row;
}
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des horaires d'ouverture</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        success: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'scale-in': 'scaleIn 0.2s ease-out',
                        'pulse-soft': 'pulseSoft 2s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        scaleIn: {
                            '0%': { opacity: '0', transform: 'scale(0.9)' },
                            '100%': { opacity: '1', transform: 'scale(1)' },
                        },
                        pulseSoft: {
                            '0%, 100%': { transform: 'scale(1)' },
                            '50%': { transform: 'scale(1.02)' },
                        }
                    },
                    backdropBlur: {
                        xs: '2px',
                    }
                }
            }
        }
    </script>
    <style>
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .dark .glass-effect {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .gradient-border {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2px;
            border-radius: 12px;
        }
        
        .gradient-border > div {
            background: white;
            border-radius: 10px;
        }
        
        .dark .gradient-border > div {
            background: #1f2937;
        }
        
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .dark .custom-scrollbar::-webkit-scrollbar-track {
            background: #374151;
        }
        
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #6b7280;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen transition-all duration-500 dark:from-gray-900 dark:via-slate-900 dark:to-indigo-950">

<div class="flex h-screen overflow-hidden">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 p-4 lg:p-8 overflow-y-auto custom-scrollbar">
        <!-- Header moderne avec navigation breadcrumb -->
        <div class="mb-8 animate-fade-in">
            <!-- Breadcrumb -->
            <nav class="flex mb-4" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="#" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-400 dark:hover:text-white">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                            </svg>
                            Administration
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2 dark:text-gray-400">Horaires</span>
                        </div>
                    </li>
                </ol>
            </nav>
            

            <!-- Header principal -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <div class="absolute inset-0 bg-gradient-to-r from-primary-500 to-purple-600 rounded-2xl blur-lg opacity-30"></div>
                        <div class="relative bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-xl">
                            <svg class="w-8 h-8 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 dark:from-white dark:to-gray-300 bg-clip-text text-transparent">
                            Gestion des horaires
                        </h1>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">Configurez les heures d'ouverture de votre restaurant</p>
                    </div>
                </div>
                
                <!-- Actions header -->
                <div class="flex items-center space-x-3">
                    <!-- Toggle mode sombre amélioré -->
                    <button id="darkModeToggle" class="relative p-3 rounded-xl bg-white dark:bg-gray-800 shadow-lg hover:shadow-xl transition-all duration-300 group border border-gray-200 dark:border-gray-700">
                        <svg id="sunIcon" class="w-5 h-5 text-amber-500 dark:hidden transition-transform group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <svg id="moonIcon" class="w-5 h-5 text-indigo-400 hidden dark:block transition-transform group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                    </button>
                    
                    <!-- Bouton info -->
                    <button class="p-3 rounded-xl bg-white dark:bg-gray-800 shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-200 dark:border-gray-700 group">
                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Message de succès redesigné -->
        <?php if (!empty($message)): ?>
            <div id="successMessage" class="mb-8 animate-slide-up">
                <div class="relative overflow-hidden rounded-2xl">
                    <div class="absolute inset-0 bg-gradient-to-r from-success-500 to-emerald-600"></div>
                    <div class="relative bg-white/10 backdrop-blur-sm border border-white/20 p-6 text-white">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="font-medium"><?= $message ?></p>
                            </div>
                            <button onclick="this.parentElement.parentElement.parentElement.remove()" class="ml-auto text-white/80 hover:text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Dashboard cards -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
            <!-- Graphique des heures d'ouverture -->
            <div class="xl:col-span-1">
                <div class="gradient-border animate-slide-up" style="animation-delay: 0.1s">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Répartition hebdomadaire</h3>
                            <div class="p-2 bg-primary-100 dark:bg-primary-900/30 rounded-lg">
                                <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="relative h-64">
                            <canvas id="hoursChart"></canvas>
                        </div>
                        <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Total hebdomadaire</span>
                                <span id="totalHours" class="text-lg font-bold text-primary-600 dark:text-primary-400">0h</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prévisualisation calendrier -->
            <div class="xl:col-span-2">
                <div class="gradient-border animate-slide-up" style="animation-delay: 0.2s">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Aperçu hebdomadaire</h3>
                            <div class="flex items-center space-x-2">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 bg-success-500 rounded-full mr-2"></div>
                                    <span class="text-xs text-gray-600 dark:text-gray-400">Ouvert</span>
                                </div>
                                <div class="flex items-center ml-4">
                                    <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                                    <span class="text-xs text-gray-600 dark:text-gray-400">Fermé</span>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-7 gap-3" id="weeklyPreview">
                            <!-- Généré par JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuration rapide améliorée -->
        <div class="mb-8 animate-slide-up" style="animation-delay: 0.3s">
            <div class="gradient-border">
                <div class="p-6">
                    <div class="flex items-center mb-6">
                        <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg mr-3">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Configuration rapide</h3>
                        <span class="ml-3 px-3 py-1 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 text-xs font-medium rounded-full">
                            Gain de temps
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Jours concernés</label>
                                <button type="button" onclick="selectAllDays()" class="text-primary-600 dark:text-primary-400 text-xs hover:underline font-medium">
                                    Tout sélectionner
                                </button>
                            </div>
                            <select multiple name="jours_groupes[]" id="jours_groupes" class="w-full h-24 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500 transition-colors duration-200">
                                <option value="Lundi">Lundi</option>
                                <option value="Mardi">Mardi</option>
                                <option value="Mercredi">Mercredi</option>
                                <option value="Jeudi">Jeudi</option>
                                <option value="Vendredi">Vendredi</option>
                                <option value="Samedi">Samedi</option>
                                <option value="Dimanche">Dimanche</option>
                            </select>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Heure d'ouverture</label>
                            <input type="time" name="groupe_ouverture" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500 transition-colors duration-200">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Heure de fermeture</label>
                            <input type="time" name="groupe_fermeture" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-primary-500 focus:ring-primary-500 transition-colors duration-200">
                        </div>
                        
                        <div class="flex items-end">
                            <button type="button" onclick="appliquerPlageHoraire()" class="w-full bg-gradient-to-r from-primary-600 to-purple-600 hover:from-primary-700 hover:to-purple-700 text-white font-semibold px-6 py-3 rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:focus:ring-primary-800">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                Appliquer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire principal redesigné -->
        <form method="post" id="scheduleForm" class="animate-slide-up" style="animation-delay: 0.4s">
            <div class="gradient-border">
                <div class="overflow-hidden">
                    <!-- En-tête du tableau moderne -->
                    <div class="bg-gradient-to-r from-primary-600 to-purple-600 px-6 py-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-semibold text-white">Horaires hebdomadaires</h2>
                                <p class="text-primary-100 mt-1">Configurez les heures d'ouverture pour chaque jour</p>
                            </div>
                            <div class="p-3 bg-white/10 rounded-xl">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Tableau responsive amélioré -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            Jour
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        <div class="flex items-center justify-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                            </svg>
                                            Ouverture
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        <div class="flex items-center justify-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                                            </svg>
                                            Fermeture
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                        <div class="flex items-center justify-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"></path>
                                            </svg>
                                            Statut
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <?php
                                $jours = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
                                $couleurs = [
                                    'from-blue-500 to-blue-600',
                                    'from-green-500 to-green-600', 
                                    'from-purple-500 to-purple-600',
                                    'from-orange-500 to-orange-600',
                                    'from-red-500 to-red-600',
                                    'from-indigo-500 to-indigo-600',
                                    'from-pink-500 to-pink-600'
                                ];
                                foreach ($jours as $index => $jour):
                                    $ouverture = $horaires[$jour]['heure_ouverture'] ?? '';
                                    $fermeture = $horaires[$jour]['heure_fermeture'] ?? '';
                                    $ferme = isset($horaires[$jour]['ferme']) ? $horaires[$jour]['ferme'] : 0;
                                ?>
                                <tr class="schedule-row hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-all duration-200 group" data-day="<?= $jour ?>" data-closed="<?= $ferme ?>">
                                    <td class="px-6 py-5">
                                        <div class="flex items-center space-x-4">
                                            <div class="relative">
                                                <div class="w-4 h-4 bg-gradient-to-r <?= $couleurs[$index] ?> rounded-full shadow-lg"></div>
                                                <div class="absolute inset-0 w-4 h-4 bg-gradient-to-r <?= $couleurs[$index] ?> rounded-full animate-ping opacity-20"></div>
                                            </div>
                                            <div>
                                                <span class="text-lg font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($jour) ?></span>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1" id="status-<?= $jour ?>">
                                                    <!-- Status mis à jour par JS -->
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <div class="relative">
                                            <input type="time" name="<?= $jour ?>_ouverture" value="<?= htmlspecialchars($ouverture) ?>" 
                                                   class="opening-time w-full max-w-xs mx-auto border-2 border-gray-200 dark:border-gray-600 rounded-xl px-4 py-3 text-center focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:focus:ring-primary-800 transition-all duration-200 bg-gray-50 dark:bg-gray-700 hover:bg-white dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100 font-medium">
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <div class="relative">
                                            <input type="time" name="<?= $jour ?>_fermeture" value="<?= htmlspecialchars($fermeture) ?>" 
                                                   class="closing-time w-full max-w-xs mx-auto border-2 border-gray-200 dark:border-gray-600 rounded-xl px-4 py-3 text-center focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:focus:ring-primary-800 transition-all duration-200 bg-gray-50 dark:bg-gray-700 hover:bg-white dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100 font-medium">
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <label class="relative inline-flex items-center cursor-pointer group">
                                            <input type="checkbox" name="<?= $jour ?>_ferme" value="1" <?= $ferme ? 'checked' : '' ?> 
                                                   class="sr-only closed-checkbox">
                                            <div class="relative">
                                                <!-- Toggle background -->
                                                <div class="toggle-bg w-14 h-8 bg-gray-200 dark:bg-gray-600 rounded-full shadow-inner transition-all duration-300 ease-in-out <?= $ferme ? 'bg-red-500 dark:bg-red-500' : '' ?>"></div>
                                                <!-- Toggle slider -->
                                                <div class="toggle-slider absolute top-1 left-1 w-6 h-6 bg-white rounded-full shadow-md transform transition-all duration-300 ease-in-out <?= $ferme ? 'translate-x-6 bg-white' : '' ?> group-hover:shadow-lg"></div>
                                                <!-- Icons -->
                                                <div class="absolute inset-0 flex items-center justify-between px-2 pointer-events-none">
                                                    <svg class="w-3 h-3 text-green-600 transition-opacity duration-300 <?= $ferme ? 'opacity-0' : 'opacity-100' ?>" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <svg class="w-3 h-3 text-red-600 transition-opacity duration-300 <?= $ferme ? 'opacity-100' : 'opacity-0' ?>" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <span class="ml-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                <span class="closed-text <?= $ferme ? 'block' : 'hidden' ?>">Fermé</span>
                                                <span class="open-text <?= $ferme ? 'hidden' : 'block' ?>">Ouvert</span>
                                            </span>
                                        </label>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Bouton d'enregistrement moderne -->
            <div class="mt-8 flex justify-center">
                <button type="submit" id="saveButton" class="relative inline-flex items-center px-8 py-4 bg-gradient-to-r from-primary-600 to-purple-600 hover:from-primary-700 hover:to-purple-700 text-white font-semibold text-lg rounded-2xl shadow-xl hover:shadow-2xl transform hover:scale-105 active:scale-95 transition-all duration-200 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:focus:ring-primary-800 group overflow-hidden">
                    <!-- Background animation -->
                    <div class="absolute inset-0 bg-gradient-to-r from-primary-400 to-purple-400 opacity-0 group-hover:opacity-20 transition-opacity duration-300"></div>
                    
                    <!-- Button content -->
                    <div class="relative flex items-center">
                        <svg id="saveIcon" class="w-6 h-6 mr-3 transition-transform group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <svg id="loadingIcon" class="w-6 h-6 mr-3 hidden animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span id="buttonText" class="font-semibold">Enregistrer les modifications</span>
                    </div>
                    
                    <!-- Ripple effect -->
                    <div class="absolute inset-0 opacity-0 group-active:opacity-30 bg-white rounded-2xl transition-opacity duration-150"></div>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Configuration et variables globales
    let hoursChart;
    
    // Gestion du mode sombre améliorée
    const darkModeToggle = document.getElementById('darkModeToggle');
    const html = document.documentElement;
    
    // Vérifier la préférence sauvegardée ou système
    const isDarkMode = localStorage.getItem('darkMode') === 'true' || 
                      (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches);
    
    if (isDarkMode) {
        html.classList.add('dark');
    }
    
    darkModeToggle.addEventListener('click', () => {
        html.classList.toggle('dark');
        localStorage.setItem('darkMode', html.classList.contains('dark'));
        updateChart();
        // Animation du bouton
        darkModeToggle.style.transform = 'scale(0.95)';
        setTimeout(() => darkModeToggle.style.transform = 'scale(1)', 150);
    });

    // Initialisation au chargement du DOM
    document.addEventListener('DOMContentLoaded', function() {
        // Animation séquentielle des éléments
        const animatedElements = document.querySelectorAll('.animate-slide-up');
        animatedElements.forEach((el, index) => {
            el.style.animationDelay = `${index * 0.1}s`;
        });
        
        initializeComponents();
        updateScheduleDisplay();
        updateChart();
        generateWeeklyPreview();
        
        // Auto-hide success message
        const successMessage = document.getElementById('successMessage');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.transform = 'translateX(100%)';
                successMessage.style.opacity = '0';
                setTimeout(() => successMessage.remove(), 300);
            }, 5000);
        }
    });

    // Initialisation des composants interactifs
    function initializeComponents() {
        // Gestion des checkboxes personnalisées
        document.querySelectorAll('.closed-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', handleCheckboxChange);
        });

        // Gestion des champs horaires
        document.querySelectorAll('.opening-time, .closing-time').forEach(input => {
            input.addEventListener('change', handleTimeChange);
            input.addEventListener('focus', handleInputFocus);
            input.addEventListener('blur', handleInputBlur);
        });

        // Gestion du formulaire
        document.getElementById('scheduleForm').addEventListener('submit', handleFormSubmit);
    }

    // Gestionnaire de changement de checkbox
    function handleCheckboxChange(event) {
        const checkbox = event.target;
        const label = checkbox.closest('label');
        const row = checkbox.closest('.schedule-row');
        const day = row.dataset.day;
        
        // Mise à jour des éléments visuels
        const toggleBg = label.querySelector('.toggle-bg');
        const toggleSlider = label.querySelector('.toggle-slider');
        const closedText = label.querySelector('.closed-text');
        const openText = label.querySelector('.open-text');
        
        if (checkbox.checked) {
            toggleSlider.classList.add('translate-x-6');
            toggleBg.classList.add('bg-red-500', 'dark:bg-red-500');
            toggleBg.classList.remove('bg-gray-200', 'dark:bg-gray-600');
            closedText.classList.remove('hidden');
            openText.classList.add('hidden');
            row.dataset.closed = '1';
            row.classList.add('opacity-60');
        } else {
            toggleSlider.classList.remove('translate-x-6');
            toggleBg.classList.remove('bg-red-500', 'dark:bg-red-500');
            toggleBg.classList.add('bg-gray-200', 'dark:bg-gray-600');
            closedText.classList.add('hidden');
            openText.classList.remove('hidden');
            row.dataset.closed = '0';
            row.classList.remove('opacity-60');
        }
        
        updateScheduleDisplay();
        updateChart();
        generateWeeklyPreview();
        updateDayStatus(day);
    }

    // Gestionnaire de changement d'horaire
    function handleTimeChange(event) {
        const input = event.target;
        const row = input.closest('.schedule-row');
        const day = row.dataset.day;
        
        // Animation de validation
        input.classList.add('border-green-500', 'bg-green-50', 'dark:bg-green-900/20');
        setTimeout(() => {
            input.classList.remove('border-green-500', 'bg-green-50', 'dark:bg-green-900/20');
        }, 1000);
        
        updateScheduleDisplay();
        updateChart();
        generateWeeklyPreview();
        updateDayStatus(day);
    }

    // Gestionnaire de focus des inputs
    function handleInputFocus(event) {
        const input = event.target;
        input.classList.add('transform', 'scale-105');
    }

    // Gestionnaire de blur des inputs
    function handleInputBlur(event) {
        const input = event.target;
        input.classList.remove('transform', 'scale-105');
    }

    // Gestionnaire de soumission du formulaire
    function handleFormSubmit(event) {
        const saveButton = document.getElementById('saveButton');
        const saveIcon = document.getElementById('saveIcon');
        const loadingIcon = document.getElementById('loadingIcon');
        const buttonText = document.getElementById('buttonText');
        
        // Animation de chargement
        saveIcon.classList.add('hidden');
        loadingIcon.classList.remove('hidden');
        buttonText.textContent = 'Enregistrement en cours...';
        saveButton.disabled = true;
        saveButton.classList.add('animate-pulse-soft');
        
        // Note: En production, retirez ce setTimeout
        setTimeout(() => {
            saveIcon.classList.remove('hidden');
            loadingIcon.classList.add('hidden');
            buttonText.textContent = 'Enregistrer les modifications';
            saveButton.disabled = false;
            saveButton.classList.remove('animate-pulse-soft');
        }, 2000);
    }

    // Mise à jour de l'affichage des horaires
    function updateScheduleDisplay() {
        document.querySelectorAll('.schedule-row').forEach(row => {
            const isClosed = row.dataset.closed === '1';
            const day = row.dataset.day;
            
            if (isClosed) {
                row.classList.add('opacity-60');
                row.style.background = 'linear-gradient(to right, rgba(239, 68, 68, 0.1), transparent)';
            } else {
                row.classList.remove('opacity-60');
                row.style.background = '';
            }
            
            updateDayStatus(day);
        });
    }

    // Mise à jour du statut d'un jour
    function updateDayStatus(day) {
        const row = document.querySelector(`[data-day="${day}"]`);
        const statusElement = document.getElementById(`status-${day}`);
        const isClosed = row.dataset.closed === '1';
        
        if (!statusElement) return;
        
        if (isClosed) {
            statusElement.innerHTML = '<span class="text-red-500 font-medium">Fermé</span>';
        } else {
            const openingInput = document.querySelector(`input[name="${day}_ouverture"]`);
            const closingInput = document.querySelector(`input[name="${day}_fermeture"]`);
            
            if (openingInput.value && closingInput.value) {
                const duration = calculateDuration(openingInput.value, closingInput.value);
                statusElement.innerHTML = `<span class="text-green-500 font-medium">Ouvert • ${duration}</span>`;
            } else {
                statusElement.innerHTML = '<span class="text-gray-400">Non configuré</span>';
            }
        }
    }

    // Calculer la durée d'ouverture
    function calculateDuration(opening, closing) {
        const start = new Date(`1970-01-01T${opening}:00`);
        const end = new Date(`1970-01-01T${closing}:00`);
        const diff = (end - start) / (1000 * 60 * 60);
        
        if (diff <= 0) return 'Horaires invalides';
        
        const hours = Math.floor(diff);
        const minutes = Math.round((diff - hours) * 60);
        
        if (minutes === 0) {
            return `${hours}h`;
        } else {
            return `${hours}h${minutes.toString().padStart(2, '0')}`;
        }
    }

    // Mise à jour du graphique
    function updateChart() {
        const ctx = document.getElementById('hoursChart').getContext('2d');
        const data = calculateHoursData();
        
        if (hoursChart) {
            hoursChart.destroy();
        }
        
        const isDark = document.documentElement.classList.contains('dark');
        
        hoursChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.hours,
                    backgroundColor: [
                        '#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', 
                        '#EF4444', '#6366F1', '#EC4899'
                    ],
                    borderColor: isDark ? '#1F2937' : '#ffffff',
                    borderWidth: 3,
                    hoverBorderWidth: 5,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: isDark ? '#1F2937' : '#ffffff',
                        titleColor: isDark ? '#ffffff' : '#1F2937',
                        bodyColor: isDark ? '#ffffff' : '#1F2937',
                        borderColor: isDark ? '#374151' : '#E5E7EB',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.parsed}h`;
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 1000
                }
            }
        });
        
        document.getElementById('totalHours').textContent = data.total + 'h';
    }

    // Calculer les données des heures
    function calculateHoursData() {
        const jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        const labels = [];
        const hours = [];
        let total = 0;
        
        jours.forEach(jour => {
            const row = document.querySelector(`[data-day="${jour}"]`);
            const isClosed = row.dataset.closed === '1';
            
            if (!isClosed) {
                const openingInput = document.querySelector(`input[name="${jour}_ouverture"]`);
                const closingInput = document.querySelector(`input[name="${jour}_fermeture"]`);
                
                if (openingInput.value && closingInput.value) {
                    const opening = new Date(`1970-01-01T${openingInput.value}:00`);
                    const closing = new Date(`1970-01-01T${closingInput.value}:00`);
                    const diff = (closing - opening) / (1000 * 60 * 60);
                    
                    if (diff > 0) {
                        labels.push(jour);
                        hours.push(Number(diff.toFixed(1)));
                        total += diff;
                    }
                }
            }
        });
        
        return { labels, hours, total: total.toFixed(1) };
    }

    // Générer la prévisualisation hebdomadaire
    function generateWeeklyPreview() {
        const preview = document.getElementById('weeklyPreview');
        const jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        const joursAbreges = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        
        preview.innerHTML = '';
        
        jours.forEach((jour, index) => {
            const row = document.querySelector(`[data-day="${jour}"]`);
            const isClosed = row.dataset.closed === '1';
            
            const dayElement = document.createElement('div');
            dayElement.className = `relative p-4 rounded-xl text-center transition-all duration-300 hover:scale-105 cursor-pointer ${
                isClosed 
                    ? 'bg-gradient-to-br from-red-500 to-red-600 text-white shadow-lg shadow-red-500/25' 
                    : 'bg-gradient-to-br from-success-500 to-success-600 text-white shadow-lg shadow-success-500/25'
            }`;
            
            const openingInput = document.querySelector(`input[name="${jour}_ouverture"]`);
            const closingInput = document.querySelector(`input[name="${jour}_fermeture"]`);
            
            dayElement.innerHTML = `
                <div class="font-bold text-sm mb-2">${joursAbreges[index]}</div>
                <div class="text-xs leading-relaxed">
                    ${isClosed ? 
                        '<div class="flex items-center justify-center"><svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>Fermé</div>' : 
                        (openingInput.value && closingInput.value ? 
                            `<div>${openingInput.value}</div><div class="text-xs opacity-75 my-1">à</div><div>${closingInput.value}</div>` : 
                            '<div class="flex items-center justify-center"><svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>À définir</div>')
                    }
                </div>
                <div class="absolute inset-0 bg-white opacity-0 hover:opacity-10 rounded-xl transition-opacity duration-200"></div>
            `;
            
            // Click pour aller à la ligne correspondante
            dayElement.addEventListener('click', () => {
                const targetRow = document.querySelector(`[data-day="${jour}"]`);
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                targetRow.classList.add('ring-4', 'ring-primary-300', 'dark:ring-primary-600');
                setTimeout(() => {
                    targetRow.classList.remove('ring-4', 'ring-primary-300', 'dark:ring-primary-600');
                }, 2000);
            });
            
            preview.appendChild(dayElement);
        });
    }

    // Configuration rapide - Fonctions utilitaires
    function appliquerPlageHoraire() {
        const jours = Array.from(document.getElementById('jours_groupes').selectedOptions).map(opt => opt.value);
        const ouverture = document.querySelector('input[name="groupe_ouverture"]').value;
        const fermeture = document.querySelector('input[name="groupe_fermeture"]').value;

        if (!ouverture || !fermeture || jours.length === 0) {
            showNotification('Veuillez sélectionner au moins un jour et définir les horaires.', 'warning');
            return;
        }

        jours.forEach(jour => {
            const ouvertureInput = document.querySelector(`input[name="${jour}_ouverture"]`);
            const fermetureInput = document.querySelector(`input[name="${jour}_fermeture"]`);
            const checkbox = document.querySelector(`input[name="${jour}_ferme"]`);

            if (ouvertureInput && fermetureInput && checkbox) {
                ouvertureInput.value = ouverture;
                fermetureInput.value = fermeture;
                checkbox.checked = false;

                // Mise à jour visuelle
                checkbox.dispatchEvent(new Event('change'));
                
                // Animation de confirmation
                const row = ouvertureInput.closest('.schedule-row');
                row.classList.add('bg-green-50', 'dark:bg-green-900/20');
                setTimeout(() => {
                    row.classList.remove('bg-green-50', 'dark:bg-green-900/20');
                }, 1500);
            }
        });

        updateScheduleDisplay();
        updateChart();
        generateWeeklyPreview();
        
        showNotification(`Horaires appliqués à ${jours.length} jour(s)`, 'success');
    }

    function selectAllDays() {
        const select = document.getElementById('jours_groupes');
        Array.from(select.options).forEach(option => option.selected = true);
        
        // Animation visuelle
        select.classList.add('ring-4', 'ring-primary-300', 'dark:ring-primary-600');
        setTimeout(() => {
            select.classList.remove('ring-4', 'ring-primary-300', 'dark:ring-primary-600');
        }, 1000);
    }

    // Système de notifications
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        const colors = {
            success: 'from-green-500 to-emerald-600',
            warning: 'from-yellow-500 to-orange-600',
            error: 'from-red-500 to-red-600',
            info: 'from-blue-500 to-indigo-600'
        };
        
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-xl text-white shadow-xl transform translate-x-full transition-transform duration-300 bg-gradient-to-r ${colors[type]}`;
        notification.innerHTML = `
            <div class="flex items-center">
                <div class="mr-3">
                    ${type === 'success' ? 
                        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>' :
                        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                    }
                </div>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animation d'entrée
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto-suppression
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Raccourcis clavier
    document.addEventListener('keydown', function(e) {
        // Ctrl + S pour sauvegarder
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.getElementById('scheduleForm').dispatchEvent(new Event('submit'));
        }
        
        // Ctrl + D pour basculer le mode sombre
        if (e.ctrlKey && e.key === 'd') {
            e.preventDefault();
            darkModeToggle.click();
        }
    });

    // Validation en temps réel
    function validateTimeInputs() {
        document.querySelectorAll('.opening-time, .closing-time').forEach(input => {
            input.addEventListener('change', function() {
                const row = this.closest('.schedule-row');
                const day = row.dataset.day;
                const openingInput = row.querySelector('.opening-time');
                const closingInput = row.querySelector('.closing-time');
                
                if (openingInput.value && closingInput.value) {
                    const opening = new Date(`1970-01-01T${openingInput.value}:00`);
                    const closing = new Date(`1970-01-01T${closingInput.value}:00`);
                    
                    if (closing <= opening) {
                        this.classList.add('border-red-500', 'bg-red-50', 'dark:bg-red-900/20');
                        showNotification('L\'heure de fermeture doit être après l\'heure d\'ouverture', 'error');
                        setTimeout(() => {
                            this.classList.remove('border-red-500', 'bg-red-50', 'dark:bg-red-900/20');
                        }, 3000);
                    }
                }
            });
        });
    }

    // Initialiser la validation
    validateTimeInputs();

    // Animation de chargement de la page
    window.addEventListener('load', function() {
        const loader = document.createElement('div');
        loader.className = 'fixed inset-0 bg-white dark:bg-gray-900 z-50 flex items-center justify-center transition-opacity duration-500';
        loader.innerHTML = `
            <div class="text-center">
                <div class="w-16 h-16 border-4 border-primary-200 border-t-primary-600 rounded-full animate-spin mx-auto"></div>
                <p class="mt-4 text-gray-600 dark:text-gray-400">Chargement...</p>
            </div>
        `;
        
        document.body.prepend(loader);
        
        setTimeout(() => {
            loader.style.opacity = '0';
            setTimeout(() => loader.remove(), 500);
        }, 1000);
    });

    // Sauvegarde automatique (optionnel)
    let autoSaveTimeout;
    function scheduleAutoSave() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(() => {
            // Ici vous pourriez implémenter une sauvegarde automatique via AJAX
            console.log('Auto-save triggered');
        }, 30000); // 30 secondes après la dernière modification
    }

    // Attacher l'auto-save aux changements
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('change', scheduleAutoSave);
    });

    // Détection de changements non sauvegardés
    let hasUnsavedChanges = false;
    
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('change', () => {
            hasUnsavedChanges = true;
        });
    });
    
    document.getElementById('scheduleForm').addEventListener('submit', () => {
        hasUnsavedChanges = false;
    });
    
    window.addEventListener('beforeunload', (e) => {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter?';
        }
    });

    // Amélioration de l'accessibilité
    document.querySelectorAll('input[type="time"]').forEach(input => {
        input.setAttribute('aria-label', `Heure pour ${input.name.split('_')[0]}`);
    });
    
    document.querySelectorAll('.closed-checkbox').forEach(checkbox => {
        checkbox.setAttribute('aria-label', `Marquer ${checkbox.name.split('_')[0]} comme fermé`);
    });

    // Performance: Debounce pour les mises à jour
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    const debouncedUpdate = debounce(() => {
        updateChart();
        generateWeeklyPreview();
    }, 300);

    // Remplacer les appels directs par la version debounced pour de meilleures performances
    document.querySelectorAll('.opening-time, .closing-time').forEach(input => {
        input.addEventListener('input', debouncedUpdate);
    });
</script>

</body>
</html>