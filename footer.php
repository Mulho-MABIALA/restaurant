<?php

require_once 'config.php';

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Requête pour récupérer les horaires d'ouverture/fermeture par jour
    $query = "
        SELECT jour, heure_ouverture, heure_fermeture, ferme
        FROM horaires
        ORDER BY FIELD(jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche')
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur SQL horaires : " . $e->getMessage());
    $results = []; // Valeur de repli
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
      <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Inter:wght@100;200;300;400;500;600;700;800;900&family=Amatic+SC:wght@400;700&display=swap" rel="stylesheet">

    <!-- CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<style>
    /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .col-lg-5, .col-lg-7, .col-lg-4, .col-lg-8 {
                flex: 0 0 100%;
                margin-bottom: 2rem;
            }
            
            main.main {
                margin-top: 70px;
            }
        }

        /* Footer */
        .footer {
            background-color: #1a202c;
            color: white;
            padding: 60px 0 20px;
        }

        .footer h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .social-links {
            display: flex;
            gap: 10px;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #ec4899, #f97316);
            color: white;
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(236, 72, 153, 0.3);
        }

        #preloader {
            display: none !important;
        }
</style>
<body>
    

 <!-- Footer -->
    <footer id="footer" class="footer dark-background">
        <div class="container" style="padding: 60px 20px 20px;">
            <div class="row gy-3">
                <div class="col-lg-3 col-md-6 d-flex">
                    <i class="bi bi-geo-alt icon" style="color: #ec4899; font-size: 2rem; margin-right: 15px;"></i>
                    <div class="address">
                        <h4 style="color: white; font-weight: 600; margin-bottom: 10px;">Adresse</h4>
                        <p style="color: #cbd5e0; margin: 5px 0;">Dakar, Medina</p>
                        <p style="color: #cbd5e0; margin: 5px 0;">Rue 27x24</p>
                        <a class="btn-getstarted" href="admin/login.php" style="display: inline-block; margin-top: 15px; background: linear-gradient(135deg, #ec4899, #f97316); color: white; padding: 8px 20px; border-radius: 20px; text-decoration: none; font-size: 0.9rem;">Administration</a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 d-flex">
                    <i class="bi bi-telephone icon" style="color: #ec4899; font-size: 2rem; margin-right: 15px;"></i>
                    <div>
                        <h4 style="color: white; font-weight: 600; margin-bottom: 10px;">Contact</h4>
                        <p style="color: #cbd5e0; margin: 5px 0;">
                            <strong>Téléphone :</strong> 
                            <a href="tel:787308706" style="color: #cbd5e0; text-decoration: none;">78 730 87 06</a>
                        </p>
                        <p style="color: #cbd5e0; margin: 5px 0;">
                            <strong>Email :</strong><br>
                            <a href="mailto:mulhomabiala29@gmail.com?subject=Contact depuis le site" style="color: #cbd5e0; text-decoration: none; font-size: 0.9rem;">mulhomabiala29@gmail.com</a>
                        </p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 d-flex">
                    <i class="bi bi-clock icon" style="color: #ec4899; font-size: 2rem; margin-right: 15px;"></i>
                    <div>
                        <h4 style="color: white; font-weight: 600; margin-bottom: 10px;">Heures d'ouverture</h4>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <?php if (!empty($results)): ?>
                                <?php foreach ($results as $row): ?>
                                    <li style="color: #cbd5e0; margin: 3px 0; font-size: 0.9rem;">
                                        <strong><?= htmlspecialchars($row['jour']) ?> :</strong>
                                        <?php if ($row['ferme'] == 1): ?>
                                            <span style="color: #fc8181;">Fermé</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars(substr($row['heure_ouverture'], 0, 5)) ?> -
                                            <?= htmlspecialchars(substr($row['heure_fermeture'], 0, 5)) ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li style="color: #cbd5e0;">Aucun horaire trouvé.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <h4 style="color: white; font-weight: 600; margin-bottom: 15px;">Suivez-nous</h4>
                    <div class="social-links d-flex">
                        <a href="https://www.snapchat.com/add/yourusername" class="snapchat"><i class="bi bi-snapchat"></i></a>
                        <a href="https://www.tiktok.com/@Ombrelumineuse" class="tiktok" style="background: linear-gradient(135deg, #ec4899, #f97316);"><i class="bi bi-tiktok"></i></a>
                        <a href="https://wa.me/+24205530852" class="whatsapp"><i class="bi bi-whatsapp"></i></a>
                        <a href="https://www.facebook.com/votreprofil" class="facebook" target="_blank"><i class="bi bi-facebook"></i></a>
                        <a href="https://www.instagram.com/votreprofil" class="instagram" target="_blank"><i class="bi bi-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="container copyright text-center mt-4" style="border-top: 1px solid #2d3748; padding-top: 30px;">
            <p style="color: #cbd5e0; margin: 10px 0;">© <span>Copyright</span> <strong class="px-1 sitename" style="color: #ec4899;">Mulho</strong> <span>Tous droits réservés</span></p>
            <div class="credits" style="color: #a0aec0; font-size: 0.9rem;">
                Conçu par <a href="#" style="color: #ec4899; text-decoration: none;">Mulho - MABIALA</a>
            </div>
        </div>
    </footer>
</body>
</html>