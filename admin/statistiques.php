<?php
require_once '../config.php'; // Ton fichier doit contenir : $conn = new PDO(...);
session_start();
header('Content-Type: text/html; charset=utf-8');

// ✅ Récupérer les indicateurs dynamiques

// Total Réservations
$stmt = $conn->query("SELECT COUNT(*) FROM reservations");
$totalReservations = $stmt->fetchColumn();

// Réservations du Mois
$stmt = $conn->query("
  SELECT COUNT(*) FROM reservations
  WHERE MONTH(date_reservation) = MONTH(CURDATE())
    AND YEAR(date_reservation) = YEAR(CURDATE())
");
$monthlyReservations = $stmt->fetchColumn();

// Clients Uniques
$stmt = $conn->query("SELECT COUNT(DISTINCT client_id) FROM reservations");
$uniqueClients = $stmt->fetchColumn();

// Taux d'Occupation (exemple)
$capacite = 200; // Remplace par ta capacité réelle
$occupancyRate = $capacite > 0 ? round(($monthlyReservations / $capacite) * 100, 2) : 0;

// Évolution Mensuelle
$reservationsPerMonth = [];
for ($month = 1; $month <= 12; $month++) {
  $stmt = $conn->prepare("
    SELECT COUNT(*) FROM reservations
    WHERE MONTH(date_reservation) = :month
      AND YEAR(date_reservation) = YEAR(CURDATE())
  ");
  $stmt->execute(['month' => $month]);
  $reservationsPerMonth[] = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Tableau de Bord - Statistiques des Réservations</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Chart.js CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- Icônes Heroicons -->
  <script src="https://unpkg.com/heroicons@2.0.16/24/outline/index.js" type="module"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; }
  </style>
</head>

<body class=" bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">

  <!-- Header -->
  <header class="bg-white shadow-sm border-b border-gray-200">
    <!-- <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center py-4">
        <div class="flex items-center space-x-4">
          <div class="w-8 h-8 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
          </div> -->
          <!-- <div>
            <h1 class="text-2xl font-bold text-gray-900">Tableau de Bord</h1>
            <p class="text-sm text-gray-500">Statistiques des réservations</p>
          </div>
        </div>
        <div class="flex items-center space-x-4">
          <button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            Exporter
          </button>
          <button class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg text-sm font-medium hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all shadow-lg hover:shadow-xl">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nouvelle Réservation
          </button>
        </div>
      </div>
    </div> -->
  </header>

  <!-- Conteneur principal -->
  <div class="flex min-h-screen">
    <!-- Sidebar (si tu en as une) -->
     
    <?php include 'sidebar.php'; ?>

    <!-- Contenu principal -->
    <div class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Breadcrumb -->
      <nav class="flex mb-8" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
          <li class="inline-flex items-center">
            <a href="#" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
              </svg>
              Accueil
            </a>
          </li>
          <li aria-current="page">
            <div class="flex items-center">
              <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
              </svg>
              <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">Statistiques</span>
            </div>
          </li>
        </ol>
      </nav>

      <!-- Cards d'Indicateurs -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        <!-- Total Réservations -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium text-gray-600 mb-1">Total Réservations</h3>
              <p class="text-3xl font-bold text-gray-900"><?= number_format($totalReservations) ?></p>
              <p class="text-sm text-green-600 mt-2 flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
                +12% vs mois dernier
              </p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
              </svg>
            </div>
          </div>
        </div>

        <!-- Réservations du Mois -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium text-gray-600 mb-1">Réservations du Mois</h3>
              <p class="text-3xl font-bold text-gray-900"><?= number_format($monthlyReservations) ?></p>
              <p class="text-sm text-green-600 mt-2 flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
                +8% vs mois dernier
              </p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
              </svg>
            </div>
          </div>
        </div>

        <!-- Clients Uniques -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium text-gray-600 mb-1">Clients Uniques</h3>
              <p class="text-3xl font-bold text-gray-900"><?= number_format($uniqueClients) ?></p>
              <p class="text-sm text-green-600 mt-2 flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
                +15% vs mois dernier
              </p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
              </svg>
            </div>
          </div>
        </div>

        <!-- Taux d'Occupation -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium text-gray-600 mb-1">Taux d'Occupation</h3>
              <p class="text-3xl font-bold text-gray-900"><?= $occupancyRate ?>%</p>
              <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                <div class="bg-gradient-to-r from-orange-400 to-orange-600 h-2 rounded-full transition-all duration-300" style="width: <?= $occupancyRate ?>%"></div>
              </div>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
              <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Graphique -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-xl font-semibold text-gray-900">Évolution Mensuelle des Réservations</h2>
            <p class="text-sm text-gray-500 mt-1">Analyse des tendances sur l'année <?= date('Y') ?></p>
          </div>
          <div class="flex items-center space-x-2">
            <button class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
              </svg>
              Filtres
            </button>
            <div class="flex rounded-md shadow-sm">
              <button class="px-3 py-2 text-xs font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-l-md hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                7J
              </button>
              <button class="px-3 py-2 text-xs font-medium text-gray-500 bg-white border-t border-b border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                30J
              </button>
              <button class="px-3 py-2 text-xs font-medium text-gray-500 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                1AN
              </button>
            </div>
          </div>
        </div>
        
        <div class="h-80">
          <canvas id="reservationsChart" class="w-full h-full"></canvas>
        </div>
      </div>

      <!-- Actions Rapides -->
      <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions Rapides</h3>
          <div class="space-y-3">
            <button class="w-full flex items-center justify-between px-4 py-3 text-left text-sm font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
              <span class="flex items-center">
                <svg class="w-4 h-4 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Ajouter une réservation
              </span>
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
            </button>
            <button class="w-full flex items-center justify-between px-4 py-3 text-left text-sm font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
              <span class="flex items-center">
                <svg class="w-4 h-4 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Générer un rapport
              </span>
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
            </button>
            <button class="w-full flex items-center justify-between px-4 py-3 text-left text-sm font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
              <span class="flex items-center">
                <svg class="w-4 h-4 mr-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Paramètres
              </span>
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
              </svg>
            </button>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Alertes</h3>
          <div class="space-y-3">
            <div class="flex items-start space-x-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
              <svg class="w-5 h-5 text-yellow-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
              <div>
                <p class="text-sm font-medium text-yellow-800">Capacité bientôt atteinte</p>
                <p class="text-xs text-yellow-600 mt-1">85% de la capacité mensuelle utilisée</p>
              </div>
            </div>
            <div class="flex items-start space-x-3 p-3 bg-green-50 border border-green-200 rounded-lg">
              <svg class="w-5 h-5 text-green-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <div>
                <p class="text-sm font-medium text-green-800">Objectif mensuel atteint</p>
                <p class="text-xs text-green-600 mt-1">150 réservations ce mois</p>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Réservations Récentes</h3>
          <div class="space-y-3">
            <div class="flex items-center justify-between py-2">
              <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                  <span class="text-xs font-medium text-blue-600">JD</span>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900">Jean Dupont</p>
                  <p class="text-xs text-gray-500">il y a 2h</p>
                </div>
              </div>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                Confirmée
              </span>
            </div>
            <div class="flex items-center justify-between py-2">
              <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                  <span class="text-xs font-medium text-purple-600">ML</span>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900">Marie Leroy</p>
                  <p class="text-xs text-gray-500">il y a 4h</p>
                </div>
              </div>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                En attente
              </span>
            </div>
            <div class="flex items-center justify-between py-2">
              <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                  <span class="text-xs font-medium text-green-600">PL</span>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900">Pierre Laurent</p>
                  <p class="text-xs text-gray-500">il y a 6h</p>
                </div>
              </div>
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                Confirmée
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <!-- <footer class="bg-white border-t border-gray-200 mt-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">
          &copy; <?= date("Y") ?> MonSiteDeRéservation. Tous droits réservés.
        </p>
        <div class="flex items-center space-x-6">
          <a href="#" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">Support</a>
          <a href="#" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">Documentation</a>
          <a href="#" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">Contact</a>
        </div>
      </div>
    </div>
  </footer> -->

  <!-- Script Chart.js -->
  <script>
    const ctx = document.getElementById('reservationsChart').getContext('2d');
    
    // Configuration du graphique avec un style moderne
    const reservationsChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
        datasets: [{
          label: 'Réservations',
          data: <?= json_encode($reservationsPerMonth) ?>,
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          borderColor: 'rgba(59, 130, 246, 1)',
          borderWidth: 3,
          fill: true,
          tension: 0.4,
          pointBackgroundColor: 'rgba(59, 130, 246, 1)',
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
          pointRadius: 6,
          pointHoverRadius: 8,
          pointHoverBackgroundColor: 'rgba(59, 130, 246, 1)',
          pointHoverBorderColor: '#ffffff',
          pointHoverBorderWidth: 3,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          intersect: false,
          mode: 'index',
        },
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            backgroundColor: 'rgba(17, 24, 39, 0.9)',
            titleColor: '#ffffff',
            bodyColor: '#ffffff',
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 1,
            cornerRadius: 8,
            displayColors: false,
            titleFont: {
              size: 14,
              weight: 'bold'
            },
            bodyFont: {
              size: 13
            },
            padding: 12,
            callbacks: {
              title: function(context) {
                return context[0].label + ' <?= date('Y') ?>';
              },
              label: function(context) {
                return context.parsed.y + ' réservations';
              }
            }
          }
        },
        scales: {
          x: {
            grid: {
              display: false,
            },
            border: {
              display: false,
            },
            ticks: {
              font: {
                size: 12,
                weight: '500'
              },
              color: '#6B7280',
              padding: 10
            }
          },
          y: {
            beginAtZero: true,
            grid: {
              color: 'rgba(156, 163, 175, 0.2)',
              drawBorder: false,
            },
            border: {
              display: false,
            },
            ticks: {
              font: {
                size: 12,
                weight: '500'
              },
              color: '#6B7280',
              padding: 10,
              stepSize: 20,
              callback: function(value) {
                return value;
              }
            }
          }
        },
        elements: {
          line: {
            borderJoinStyle: 'round',
            borderCapStyle: 'round'
          }
        }
      }
    });

    // Animation au chargement
    document.addEventListener('DOMContentLoaded', function() {
      // Animation des cartes d'indicateurs
      const cards = document.querySelectorAll('.grid > div');
      cards.forEach((card, index) => {
        setTimeout(() => {
          card.style.opacity = '0';
          card.style.transform = 'translateY(20px)';
          card.style.transition = 'all 0.6s ease';
          
          setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
          }, 100);
        }, index * 100);
      });
    });

    // Effet de survol sur les cartes
    document.querySelectorAll('.hover\\:shadow-md').forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
        this.style.transition = 'all 0.3s ease';
      });
      
      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
      });
    });
  </script>

</body>
</html>
