<?php
session_start();

// Vérifier si l'utilisateur est un admin connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$mysqli = new mysqli('localhost', 'root', '', 'restaurant');
if ($mysqli->connect_errno) {
    die('Erreur de connexion : ' . $mysqli->connect_error);
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Récupération des données de réservation existantes
$result = $mysqli->query("SELECT * FROM reservations WHERE id = $id");
if ($result->num_rows === 0) {
    die('Réservation non trouvée');
}
$reservation = $result->fetch_assoc();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $date = trim($_POST['date_reservation']);
    $heure = trim($_POST['heure_reservation']);
    $personnes = intval($_POST['personnes']);
    $message = trim($_POST['message']);

    if (!$nom || !$email || !$telephone || !$date || !$heure || $personnes <= 0) {
        $error = "Tous les champs doivent être correctement remplis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email est invalide.";
    } else {
        $stmt = $mysqli->prepare("UPDATE reservations SET nom = ?, email = ?, telephone = ?, date_reservation = ?, heure_reservation = ?, personnes = ?, message = ? WHERE id = ?");
        $stmt->bind_param('sssssssi', $nom, $email, $telephone, $date, $heure, $personnes, $message, $id);
        $stmt->execute();

        if ($stmt->affected_rows >= 0) {
            header("Location: reservations.php?success=1");
            exit;
        } else {
            $error = "Échec de la mise à jour.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Modifier la réservation</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    
    body {
      font-family: 'Inter', sans-serif;
    }
    
    .gradient-bg {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .glass-effect {
      backdrop-filter: blur(10px);
      background: rgba(255, 255, 255, 0.95);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .input-focus:focus {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .btn-hover:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }
    
    .floating-label {
      transition: all 0.3s ease;
    }
    
    .reservation-card {
      background: linear-gradient(145deg, #ffffff, #f8fafc);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }
    
    .icon-animate {
      transition: transform 0.3s ease;
    }
    
    .icon-animate:hover {
      transform: scale(1.1);
    }
  </style>
</head>
<body class="gradient-bg min-h-screen py-12 px-4">
  <div class="max-w-3xl mx-auto">
    <!-- Header avec animation -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full shadow-lg mb-4 icon-animate">
        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
      </div>
      <h1 class="text-3xl font-bold text-white mb-2">Modifier la réservation</h1>
      <p class="text-indigo-100 text-lg">#<?= $id ?></p>
    </div>

    <!-- Carte principale -->
    <div class="reservation-card rounded-2xl p-8 glass-effect">
      
      <?php if ($error): ?>
        <div class="bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-red-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <p class="text-red-700 font-medium"><?= $error ?></p>
          </div>
        </div>
      <?php endif; ?>

      <form method="post" class="space-y-6">
        
        <!-- Section Informations personnelles -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-xl">
          <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            Informations personnelles
          </h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="relative">
              <input type="text" name="nom" value="<?= htmlspecialchars($reservation['nom']) ?>" 
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all input-focus" 
                     placeholder="Nom complet" required>
              <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
              </div>
            </div>
            
            <div class="relative">
              <input type="email" name="email" value="<?= htmlspecialchars($reservation['email']) ?>" 
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all input-focus" 
                     placeholder="Adresse e-mail" required>
              <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
              </div>
            </div>
          </div>
          
          <div class="mt-4 relative">
            <input type="text" name="telephone" value="<?= htmlspecialchars($reservation['telephone']) ?>" 
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all input-focus" 
                   placeholder="Numéro de téléphone" required>
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
              </svg>
            </div>
          </div>
        </div>

        <!-- Section Détails de la réservation -->
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-6 rounded-xl">
          <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 0v4a2 2 0 002 2h4a2 2 0 002-2V7m-6 0h6"/>
            </svg>
            Détails de la réservation
          </h2>
          
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="relative">
              <input type="date" name="date_reservation" value="<?= htmlspecialchars($reservation['date_reservation']) ?>" 
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all input-focus" required>
              <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 0v4a2 2 0 002 2h4a2 2 0 002-2V7m-6 0h6"/>
                </svg>
              </div>
            </div>
            
            <div class="relative">
              <input type="time" name="heure_reservation" value="<?= htmlspecialchars($reservation['heure_reservation']) ?>" 
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all input-focus" required>
              <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
            </div>
            
            <div class="relative">
              <input type="number" name="personnes" value="<?= htmlspecialchars($reservation['personnes']) ?>" min="1" 
                     class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all input-focus" 
                     placeholder="Nombre de personnes" required>
              <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Section Message -->
        <div class="bg-gradient-to-r from-purple-50 to-pink-50 p-6 rounded-xl">
          <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            Message (optionnel)
          </h2>
          
          <textarea name="message" rows="4" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all input-focus resize-none" 
                    placeholder="Demandes spéciales, allergies, préférences..."><?= htmlspecialchars($reservation['message']) ?></textarea>
        </div>

        <!-- Boutons d'action -->
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6">
          <a href="reservations.php" 
             class="inline-flex items-center px-6 py-3 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-all btn-hover">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Retour aux réservations
          </a>
          
          <button type="submit" 
                  class="inline-flex items-center px-8 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg hover:from-indigo-700 hover:to-purple-700 transition-all btn-hover shadow-lg">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Enregistrer les modifications
          </button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
