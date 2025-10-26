<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès Refusé</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <i class="fas fa-lock text-danger mb-4" style="font-size: 5rem;"></i>
        <h1 class="mb-3">Accès Refusé</h1>
        <p class="text-muted mb-4">Vous n'avez pas les permissions nécessaires pour accéder à cette page.</p>
        <a href="dashboard.php" class="btn btn-primary">
            <i class="fas fa-home me-2"></i>Retour au tableau de bord
        </a>
    </div>
</body>
</html>