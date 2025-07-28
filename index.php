<?php
session_start(); // Démarrage de la session

// Choix de la langue : GET > session > défaut (français)
$lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'fr');
$_SESSION['lang'] = $lang;

// Chargement du fichier de langue
$lang_file = __DIR__ . "/langues/$lang.php";
if (file_exists($lang_file)) {
    include $lang_file; // Ce fichier doit définir $traduction
} else {
    include __DIR__ . "/langues/fr.php";
}

// Vérification de la variable $traduction
if (!isset($traduction) || !is_array($traduction)) {
    $traduction = [];
}



// Connexion à la base de données
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

// Requête pour récupérer les plats disponibles (triés du plus récent au plus ancien)
try {
    $stmt = $conn->query("SELECT * FROM plats ORDER BY created_at DESC");
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur SQL produits : " . $e->getMessage());
    $produits = [];
}

// Compter les articles dans le panier
$nb_articles_panier = 0;
if (!empty($_SESSION['panier']) && is_array($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $quantite) {
        if (is_numeric($quantite)) {
            $nb_articles_panier += (int)$quantite;
        }
    }
}
?>


  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Restaurant</title>
    <meta name="description" content="">
    <meta name="keywords" content="">

    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Inter:wght@100;200;300;400;500;600;700;800;900&family=Amatic+SC:wght@400;700&display=swap" rel="stylesheet">
  
    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    


    <!-- Main CSS File -->
    <link href="assets/css/main.css" rel="stylesheet">
    <script src="cart.js"></script>
<script>
  function updateCartCount() {
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    let count = cart.reduce((sum, item) => sum + item.quantity, 0);
    let cartCount = document.getElementById('cart-count');
    if (cartCount) cartCount.textContent = count;
  }

  document.addEventListener('DOMContentLoaded', updateCartCount);
</script>
<script>
  function toggleDropdown() {
    const dropdown = document.getElementById('languageDropdown');
    dropdown.classList.toggle('show');

    // Optionnel : ferme si clic en dehors
    document.addEventListener('click', function (event) {
      const dropdownWrapper = document.querySelector('.language-dropdown');
      if (!dropdownWrapper.contains(event.target)) {
        dropdown.classList.remove('show');
      }
    }, { once: true });
  }
</script>



     <style>
        
        .language-dropdown {
            position: relative;
            display: inline-block;
        }

        .language-btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .language-btn:hover {
            background-color: #0056b3;
        }

        .dropdown-arrow {
            font-size: 12px;
            transition: transform 0.3s;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 200px;
            box-shadow: 0px 8px 16px rgba(0,0,0,0.2);
            border-radius: 5px;
            z-index: 1;
            top: 100%;
            left: 0;
            margin-top: 5px;
            overflow: hidden;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.3s;
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }

        .flag {
            font-size: 20px;
        }

        .language-name {
            font-weight: 500;
        }

        /* Animation pour la flèche */
        .language-dropdown.active .dropdown-arrow {
            transform: rotate(180deg);
        }

     
        .current-language {
            background-color: #28a745;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
        }

        


    </style>

  </head>
  <?php include('lang.php'); ?>


  <body class="index-page">
    <header id="header" class="header d-flex align-items-center sticky-top">
      <div class="container position-relative d-flex align-items-center justify-content-between">
        <a href="index.html" class="logo d-flex align-items-center me-auto me-xl-0">
          <h1 class="sitename">Mulho</h1>
          
        </a>
        <nav id="navmenu" class="navmenu">
          <ul>
         <li><a href="#hero" class="active"><?= $traduction['home'] ?? 'Accueil' ?></a></li>
<li><a href="#about"><?= $traduction['about'] ?? 'À propos' ?></a></li>
<li><a href="#menu"><?= $traduction['menu'] ?? 'Menu' ?></a></li>
<li><a href="#events"><?= $traduction['events'] ?? 'Événements' ?></a></li>
<!-- <li><a href="#chefs">Chefs</a></li> -->
<li><a href="#gallery"><?= $traduction['gallery'] ?? 'Galerie' ?></a></li>
<li><a href="#menu"><?= $traduction['order'] ?? 'Commander' ?></a></li>


             <li>
                <div class="language-dropdown">
                    <button class="language-btn" onclick="toggleDropdown()">
                        <span class="flag">🌐</span>
                        <span>Langues</span>
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="dropdown-content" id="languageDropdown">
                        <a href="?lang=fr" onclick="changeLanguage('fr')">
                            <span class="flag">🇫🇷</span>
                            <span class="language-name">Français</span>
                        </a>
                        <a href="?lang=en" onclick="changeLanguage('en')">
                            <span class="flag">🇬🇧</span>
                            <span class="language-name">English</span>
                        </a>
                        <a href="?lang=wo" onclick="changeLanguage('wo')">
                            <span class="flag">🇸🇳</span>
                            <span class="language-name">Wolof</span>
                        </a>
                    </div>
                </div>
            </li>
            <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
       <a class="btn-getstarted" href="#book-a-table">Réserver une table</a> 
        </nav>
       <a href="panier.php" class="text-gray-700 hover:text-pink-600 transition duration-300 font-medium relative">
                        <i class="fas fa-shopping-cart mr-2"></i>Panier
                        <?php if($nb_articles_panier > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-pink-500 text-white text-xs rounded-full h-6 w-6 flex items-center justify-center animate-pulse">
                            <?php echo $nb_articles_panier; ?>
                        </span>
                        <?php endif; ?>
                    </a>

      </div>


    </header>
    
    <main class="main">
      <!-- Section Hero -->
      <section id="hero" class="hero section light-background">
        <div class="container">
          <div class="row gy-4 justify-content-center justify-content-lg-between">
            <div class="col-lg-5 order-2 order-lg-1 d-flex flex-column justify-content-center">
              <h1 data-aos="fade-up">Profitez de vos meilleurs plats de qualité<br></h1> 
              <!-- Délicieuse -->
              <!-- <p data-aos="fade-up" data-aos-delay="100">Nous sommes une équipe de designers talentueux créant des sites web avec Bootstrap</p> -->
              <div class="d-flex" data-aos="fade-up" data-aos-delay="200">
                <a href="#book-a-table" class="btn-get-started">Réserver une table</a>
              
              </div>
            </div>
            <div class="col-lg-5 order-1 order-lg-2 hero-img" data-aos="zoom-out">
              <img src="assets/img/hero-img.png" class="img-fluid animated" alt="">
            </div>
          </div>
        </div>
      </section><!-- /Section Hero -->
      <!-- Section À propos -->
      <section id="about" class="about section">
        <!-- Titre de la section -->
        <div class="container section-title" data-aos="fade-up">
          <h2>À propos de nous</h2>
          <p><span>En savoir plus</span> <span class="description-title">À propos de nous</span></p>
        </div><!-- Fin du titre de la section -->
        <div class="container">
          <div class="row gy-4">
            <div class="col-lg-7" data-aos="fade-up" data-aos-delay="100">
              <img src="assets/img/about.jpg" class="img-fluid mb-4" alt="">
              <div class="book-a-table">
                <h3>Réserver une table</h3>
                <a href="tel:787308706">78 730 87 06</a>
              </div>
            </div>
            <div class="col-lg-5" data-aos="fade-up" data-aos-delay="250">
              <div class="content ps-0 ps-lg-5">
                <p class="fst-italic">
              Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
            </p>
            <ul>
              <li><i class="bi bi-check-circle-fill"></i> <span>Travail effectué sans prendre de repos, sauf pour ce qui est nécessaire au confort.</span></li>
              <li><i class="bi bi-check-circle-fill"></i> <span>Ils éprouvent une douleur terrible en cherchant le plaisir dans la volupté.</span></li>
              <li><i class="bi bi-check-circle-fill"></i> <span>Travail effectué sans prendre de repos, sauf pour ce qui est nécessaire au confort. Ils éprouvent une douleur terrible en cherchant le plaisir dans la volupté trideta storacalaperda mastiro, douleur qu’ils fuient sans aucune conséquence.</span></li>
            </ul>
            <p>
              Travail effectué sans prendre de repos, sauf pour ce qui est nécessaire au confort. Ils éprouvent une douleur terrible en cherchant le plaisir dans la volupté,
              cette volupté même qui engendre la douleur qu’ils fuient sans aucune conséquence. Sauf exception, ceux qui ne sont pas prudents n’en profitent pas.
            </p>
                <div class="position-relative mt-4">
                  <img src="assets/img/about-2.jpg" class="img-fluid" alt="">
                  <a href="https://www.youtube.com/watch?v=Y7f98aduVJ8" class="glightbox pulsating-play-btn"></a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section><!-- /Section À propos -->
      <!-- Section Pourquoi nous -->
      <section id="why-us" class="why-us section light-background">
        <div class="container">
          <div class="row gy-4">
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
              <div class="why-box">
                <h3>Pourquoi choisir Yummy</h3>
                <p>
                  Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Duis aute irure dolor in reprehenderit
                  Asperiores dolores sed et. Tenetur quia eos. Autem tempore quibusdam vel necessitatibus optio ad corporis.
                </p>
                <div class="text-center">
                  <a href="#" class="more-btn"><span>En savoir plus</span> <i class="bi bi-chevron-right"></i></a>
                </div>
              </div>
            </div><!-- Fin de la boîte Pourquoi -->
            <div class="col-lg-8 d-flex align-items-stretch">
              <div class="row gy-4" data-aos="fade-up" data-aos-delay="200">
                <div class="col-xl-4">
                  <div class="icon-box d-flex flex-column justify-content-center align-items-center">
                    <i class="bi bi-clipboard-data"></i>
                    <h4>Corporis voluptates officia eiusmod</h4>
                    <p>Consequuntur sunt aut quasi enim aliquam quae harum pariatur laboris nisi ut aliquip</p>
                  </div>
                </div><!-- Fin de la boîte d'icônes -->
                <div class="col-xl-4" data-aos="fade-up" data-aos-delay="300">
                  <div class="icon-box d-flex flex-column justify-content-center align-items-center">
                    <i class="bi bi-gem"></i>
                    <h4>Ullamco laboris ladore lore pan</h4>
                    <p>Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt</p>
                  </div>
                </div><!-- Fin de la boîte d'icônes -->
                <div class="col-xl-4" data-aos="fade-up" data-aos-delay="400">
                  <div class="icon-box d-flex flex-column justify-content-center align-items-center">
                    <i class="bi bi-inboxes"></i>
                    <h4>Labore consequatur incidid dolore</h4>
                    <p>Aut suscipit aut cum nemo deleniti aut omnis. Doloribus ut maiores omnis facere</p>
                  </div>
                </div><!-- Fin de la boîte d'icônes -->
              </div>
            </div>
          </div>
        </div>
      </section><!-- /Section Pourquoi nous -->
      <!-- Section Statistiques -->
      <section id="stats" class="stats section dark-background">
        <img src="assets/img/stats-bg.jpg" alt="" data-aos="fade-in">
        <div class="container position-relative" data-aos="fade-up" data-aos-delay="100">
          <div class="row gy-4">
            <div class="col-lg-3 col-md-6">
              <div class="stats-item text-center w-100 h-100">
                <span data-purecounter-start="0" data-purecounter-end="232" data-purecounter-duration="1" class="purecounter"></span>
                <p>Clients</p>
              </div>
            </div><!-- Fin de l'élément Statistiques -->
            <div class="col-lg-3 col-md-6">
              <div class="stats-item text-center w-100 h-100">
                <span data-purecounter-start="0" data-purecounter-end="521" data-purecounter-duration="1" class="purecounter"></span>
                <p>Projets</p>
              </div>
            </div><!-- Fin de l'élément Statistiques -->
            <div class="col-lg-3 col-md-6">
              <div class="stats-item text-center w-100 h-100">
                <span data-purecounter-start="0" data-purecounter-end="1453" data-purecounter-duration="1" class="purecounter"></span>
                <p>Heures de support</p>
              </div>
            </div><!-- Fin de l'élément Statistiques -->
            <div class="col-lg-3 col-md-6">
              <div class="stats-item text-center w-100 h-100">
                <span data-purecounter-start="0" data-purecounter-end="32" data-purecounter-duration="1" class="purecounter"></span>
                <p>Employés</p>
              </div>
            </div><!-- Fin de l'élément Statistiques -->
          </div>
        </div>
      </section><!-- /Section Statistiques -->
      <!-- Section Menu -->
      <?php include('menu.php');?>
    
      <!-- Section Événements -->
      <section id="events" class="events section">
        <div class="container-fluid" data-aos="fade-up" data-aos-delay="100">
          <div class="swiper init-swiper">
            <script type="application/json" class="swiper-config">
              {
                "loop": true,
                "speed": 600,
                "autoplay": {
                  "delay": 5000
                },
                "slidesPerView": "auto",
                "pagination": {
                  "el": ".swiper-pagination",
                  "type": "bullets",
                  "clickable": true
                },
                "breakpoints": {
                  "320": {
                    "slidesPerView": 1,
                    "spaceBetween": 40
                  },
                  "1200": {
                    "slidesPerView": 3,
                    "spaceBetween": 1
                  }
                }
              }
            </script>
            <div class="swiper-wrapper">
              <div class="swiper-slide event-item d-flex flex-column justify-content-end" style="background-image: url(assets/img/events-1.jpg)">
                <h3>Fêtes sur mesure</h3>
                <div class="price align-self-start">$99</div>
                <p class="description">
                  Quo corporis voluptas ea ad. Consectetur inventore sapiente ipsum voluptas eos omnis facere. Enim facilis veritatis id est rem repudiandae nulla expedita quas.
                </p>
              </div><!-- Fin de l'élément événement -->
              <div class="swiper-slide event-item d-flex flex-column justify-content-end" style="background-image: url(assets/img/events-2.jpg)">
                <h3>Fêtes privées</h3>
                <div class="price align-self-start">$289</div>
                <p class="description">
                  In delectus sint qui et enim. Et ab repudiandae inventore quaerat doloribus. Facere nemo vero est ut dolores ea assumenda et. Delectus saepe accusamus aspernatur.
                </p>
              </div><!-- Fin de l'élément événement -->
              <div class="swiper-slide event-item d-flex flex-column justify-content-end" style="background-image: url(assets/img/events-3.jpg)">
                <h3>Fêtes d'anniversaire</h3>
                <div class="price align-self-start">$499</div>
                <p class="description">
                  Laborum aperiam atque omnis minus omnis est qui assumenda quos. Quis id sit quibusdam. Esse quisquam ducimus officia ipsum ut quibusdam maxime. Non enim perspiciatis.
                </p>
              </div><!-- Fin de l'élément événement -->
              <div class="swiper-slide event-item d-flex flex-column justify-content-end" style="background-image: url(assets/img/events-4.jpg)">
                <h3>Fêtes de mariage</h3>
                <div class="price align-self-start">$899</div>
                <p class="description">
                  Laborum aperiam atque omnis minus omnis est qui assumenda quos. Quis id sit quibusdam. Esse quisquam ducimus officia ipsum ut quibusdam maxime. Non enim perspiciatis.
                </p>
              </div><!-- Fin de l'élément événement -->
            </div>
            <div class="swiper-pagination"></div>
          </div>
        </div>
      </section><!-- /Section Événements -->
      <!-- Section Réserver une table -->
      <section id="book-a-table" class="book-a-table section">
        <!-- Titre de la section -->
        <div class="container section-title" data-aos="fade-up">
          <h2>Réserver une table</h2>
          <!-- <span class="description-title">Séjour avec nous</span> -->
          <p><span>Réservez votre</span> </p>
        </div><!-- Fin du titre de la section -->
        <div class="container">
          <div class="row g-0" data-aos="fade-up" data-aos-delay="100">
            <div class="col-lg-4 reservation-img" style="background-image: url(assets/img/reservation.jpg);"></div>
            <div class="col-lg-8 d-flex align-items-center reservation-form-bg" data-aos="fade-up" data-aos-delay="200">
              <form action="forms/book-a-table.php" method="post" role="form" class="php-email-form">
                <div class="row gy-4">
                  <div class="col-lg-4 col-md-6">
                    <input type="text" name="name" class="form-control" id="name" placeholder="Votre nom" required="">
                  </div>
                  <div class="col-lg-4 col-md-6">
                    <input type="email" class="form-control" name="email" id="email" placeholder="Votre email" required="">
                  </div>
                  <div class="col-lg-4 col-md-6">
                    <input type="text" class="form-control" name="phone" id="phone" placeholder="Votre téléphone" required="">
                  </div>
                  <div class="col-lg-4 col-md-6">
                    <input type="date" name="date" class="form-control" id="date" placeholder="Date" required="">
                  </div>
                  <div class="col-lg-4 col-md-6">
                    <input type="time" class="form-control" name="time" id="time" placeholder="Heure" required="">
                  </div>
                  <div class="col-lg-4 col-md-6">
                    <input type="number" class="form-control" name="people" id="people" placeholder="Nombre de personnes" required="">
                  </div>
                </div>
                <div class="form-group mt-3">
                  <textarea class="form-control" name="message" rows="5" placeholder="Message"></textarea>
                </div>
                <div class="text-center mt-3">
                  <div class="loading">Chargement</div>
                  <div class="error-message"></div>
                  <div class="sent-message">Votre demande de réservation a été envoyée. Nous vous rappellerons ou enverrons un email pour confirmer votre réservation. Merci !</div>
                  <button type="submit">Réserver une table</button>
                </div>
              </form>
            </div><!-- Fin du formulaire de réservation -->
          </div>
        </div>
      </section><!-- /Section Réserver une table -->
      <!-- Section Galerie -->
      <section id="gallery" class="gallery section light-background">
        <!-- Titre de la section -->
        <div class="container section-title" data-aos="fade-up">
          <h2>Galerie</h2>
          <p><span>Consultez</span> <span class="description-title">Notre galerie</span></p>
        </div><!-- Fin du titre de la section -->
        <div class="container" data-aos="fade-up" data-aos-delay="100">
          <div class="swiper init-swiper">
            <script type="application/json" class="swiper-config">
              {
                "loop": true,
                "speed": 600,
                "autoplay": {
                  "delay": 5000
                },
                "slidesPerView": "auto",
                "centeredSlides": true,
                "pagination": {
                  "el": ".swiper-pagination",
                  "type": "bullets",
                  "clickable": true
                },
                "breakpoints": {
                  "320": {
                    "slidesPerView": 1,
                    "spaceBetween": 0
                  },
                  "768": {
                    "slidesPerView": 3,
                    "spaceBetween": 20
                  },
                  "1200": {
                    "slidesPerView": 5,
                    "spaceBetween": 20
                  }
                }
              }
            </script> 
            <div class="swiper-wrapper align-items-center">
              <div class="swiper-slide"><a class="glightbox" data-gallery="images-gallery" href="assets/img/gallery/gallery-1.jpg"><img src="assets/img/gallery/gallery-1.jpg" class="img-fluid" alt=""></a></div>
              <div class="swiper-slide"><a class="glightbox" data-gallery="images-gallery" href="assets/img/gallery/gallery-2.jpg"><img src="assets/img/gallery/gallery-2.jpg" class="img-fluid" alt=""></a></div>
              <div class="swiper-slide"><a class="glightbox" data-gallery="images-gallery" href="assets/img/gallery/gallery-3.jpg"><img src="assets/img/gallery/gallery-3.jpg" class="img-fluid" alt=""></a></div>
              <div class="swiper-slide"><a class="glightbox" data-gallery="images-gallery" href="assets/img/gallery/gallery-4.jpg"><img src="assets/img/gallery/gallery-4.jpg" class="img-fluid" alt=""></a></div>
              <div class="swiper-slide"><a class="glightbox" data-gallery="images-gallery" href="assets/img/gallery/gallery-5.jpg"><img src="assets/img/gallery/gallery-5.jpg" class="img-fluid" alt=""></a></div>
              <div class="swiper-slide"><a class="glightbox" data-gallery="images-gallery" href="assets/img/gallery/gallery-6.jpg"><img src="assets/img/gallery/gallery-6.jpg" class="img-fluid" alt=""></a></div>
              <div class="swiper-slide"><a class="glightbox" data-gallery="images-gallery" href="assets/img/gallery/gallery-7.jpg"><img src="assets/img/gallery/gallery-7.jpg" class="img-fluid" alt=""></a></div>
              <div class="swiper-slide"><a class="glightbox" data-gallery="images-gallery" href="assets/img/gallery/gallery-8.jpg"><img src="assets/img/gallery/gallery-8.jpg" class="img-fluid" alt=""></a></div>
            </div>
            <div class="swiper-pagination"></div>
          </div>
        </div>
      </section><!-- /Section Galerie -->
      <!-- Section Contact -->
      <section id="contact" class="contact section">
        <!-- Titre de la section -->
        <div class="container section-title" data-aos="fade-up">
          <h2>Contact</h2>
          <p><span>Besoin d'aide ?</span> <span class="description-title">Contactez-nous</span></p>
        </div><!-- Fin du titre de la section -->
        <div class="container" data-aos="fade-up" data-aos-delay="100">
          <div class="mb-5">
            <iframe style="width: 100%; height: 400px;" src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d12097.433213460943!2d-74.0062269!3d40.7101282!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0xb89d1fe6bc499443!2sDowntown+Conference+Center!5e0!3m2!1smk!2sbg!4v1539943755621" frameborder="0" allowfullscreen=""></iframe>
          </div><!-- Fin Google Maps -->
          <div class="row gy-4">
            <div class="col-md-6">
              <div class="info-item d-flex align-items-center" data-aos="fade-up" data-aos-delay="200">
                <i class="icon bi bi-geo-alt flex-shrink-0"></i>
                <div>
                  <h3>Adresse</h3>
                  <p>Dakar, Medina rue 27x24</p>
                </div>
              </div>
            </div><!-- Fin de l'élément d'information -->
            <div class="col-md-6">
              <div class="info-item d-flex align-items-center" data-aos="fade-up" data-aos-delay="300">
                <i class="icon bi bi-telephone flex-shrink-0"></i>
                          <div>
              <h3>Appelez-nous</h3>
              <p><a href="tel:787308706">78 730 87 06</a></p>
            </div>
              </div>
            </div><!-- Fin de l'élément d'information -->
            <div class="col-md-6">
              <div class="info-item d-flex align-items-center" data-aos="fade-up" data-aos-delay="400">
                <i class="icon bi bi-envelope flex-shrink-0"></i>
                <div>
                  <h3>Envoyez-nous un email</h3>
                  <p>mulhomabiala29@gmail.com</p>
                </div>
              </div>
            </div><!-- Fin de l'élément d'information -->
          <div class="col-md-6">
    <div class="info-item d-flex align-items-center" data-aos="fade-up" data-aos-delay="500">
        <i class="icon bi bi-clock flex-shrink-0"></i>
        <div>
            <h3>Heures d'ouverture</h3>
            <ul>
                <?php if (!empty($results)): ?>
                    <?php foreach ($results as $row): ?>
                        <li>
                            <strong><?= htmlspecialchars($row['jour']) ?> :</strong>
                            <?php if ($row['ferme'] == 1): ?>
                                Fermé
                            <?php else: ?>
                                <?= htmlspecialchars(substr($row['heure_ouverture'], 0, 5)) ?> - 
                                <?= htmlspecialchars(substr($row['heure_fermeture'], 0, 5)) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>Aucun horaire trouvé.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
          <form action="forms/contact.php" method="post" class="php-email-form" data-aos="fade-up" data-aos-delay="600">
            <div class="row gy-4">
              <div class="col-md-6">
                <input type="text" name="name" class="form-control" placeholder="Votre nom" required="">
              </div>
              <div class="col-md-6 ">
                <input type="email" class="form-control" name="email" placeholder="Votre email" required="">
              </div>
              <div class="col-md-12">
                <input type="text" class="form-control" name="subject" placeholder="Sujet" required="">
              </div>
              <div class="col-md-12">
                <textarea class="form-control" name="message" rows="6" placeholder="Message" required=""></textarea>
              </div>
              <div class="col-md-12 text-center">
                <div class="loading">Chargement</div>
                <div class="error-message"></div>
                <div class="sent-message">Votre message a été envoyé. Merci !</div>
                <button type="submit">Envoyer le message</button>
              </div>
            </div>
          </form><!-- Fin du formulaire de contact -->
        </div>
      </section><!-- /Section Contact -->
    </main>
    <footer id="footer" class="footer dark-background">
      <div class="container">
        <div class="row gy-3">
          <div class="col-lg-3 col-md-6 d-flex">
            <i class="bi bi-geo-alt icon"></i>
            <div class="address">
              <h4>Adresse</h4>
              <p>A108 Adam Street</p>
              <p>New York, NY 535022</p>
              <a class="btn-getstarted" href="admin/login.php">admin</a>
            </div>
          </div>
          <div class="col-lg-3 col-md-6 d-flex">
            <i class="bi bi-telephone icon"></i>
            <div>
              <h4>Contact</h4>
              <p>
                <strong>Téléphone :</strong> <span> <a href="tel:787308706">78 730 87 06</a></span><br>
                <strong>Email :</strong>
<a href="mailto:mulhomabiala29@gmail.com?subject=Contact depuis le site">mulhomabiala29@gmail.com</a>


              </p>
            </div>
          </div>
          <div class="col-lg-3 col-md-6 d-flex">
            <i class="bi bi-clock icon"></i>
            <div>
              <h4>Heures d'ouverture</h4>
              <p>
                <ul>
                <?php if (!empty($results)): ?>
                    <?php foreach ($results as $row): ?>
                        <li>
                            <strong><?= htmlspecialchars($row['jour']) ?> :</strong>
                            <?php if ($row['ferme'] == 1): ?>
                                Fermé
                            <?php else: ?>
                                <?= htmlspecialchars(substr($row['heure_ouverture'], 0, 5)) ?> - 
                                <?= htmlspecialchars(substr($row['heure_fermeture'], 0, 5)) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>Aucun horaire trouvé.</li>
                <?php endif; ?>
            </ul>
              </p>
            </div>
          </div>
          <div class="col-lg-3 col-md-6">
            <h4>Suivez-nous</h4>
            <div class="social-links d-flex">
              <a href="https://www.snapchat.com/add/yourusername" class="snapchat"><i class="bi bi-snapchat"></i></a>
              <a href="https://www.tiktok.com/@Ombrelumineuse" class="tiktok"><i class="bi bi-tiktok"></i></a>
              <a href="https://wa.me/+24205530852" class="whatsapp"><i class="bi bi-whatsapp"></i></a>
              <a href="https://www.facebook.com/votreprofil" class="facebook" target="_blank"><i class="bi bi-facebook"></i></a>
              <a href="https://www.instagram.com/votreprofil" class="instagram" target="_blank"><i class="bi bi-instagram"></i></a>
              
            </div>
          </div>
        </div>
      </div>
      <div class="container copyright text-center mt-4">
        <p>© <span>Copyright</span> <strong class="px-1 sitename">Mulho</strong> <span>Tous droits réservés</span></p>
        <div class="credits">
          Conçu par <a href="#">Mulho - MABIALA</a>
        </div>
      </div>
    </footer>
  </body>


    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

    <!-- Preloader -->
    <div id="preloader"></div>
    <!-- Ajoutez ce script après votre formulaire -->


 
<script>
   let currentLanguage = 'fr';

        function toggleDropdown() {
            const dropdown = document.getElementById('languageDropdown');
            const container = document.querySelector('.language-dropdown');
            
            dropdown.classList.toggle('show');
            container.classList.toggle('active');
        }

        function changeLanguage(lang) {
            currentLanguage = lang;
            
            const langInfo = {
                'fr': { flag: '🇫🇷', name: 'Français' },
                'en': { flag: '🇬🇧', name: 'English' },
                'wo': { flag: '🇸🇳', name: 'Wolof' }
            };

            // Mettre à jour l'affichage de la langue actuelle
            const currentLangElement = document.getElementById('currentLang');
            currentLangElement.innerHTML = `
                <span class="flag">${langInfo[lang].flag}</span>
                <span>Langue actuelle: ${langInfo[lang].name}</span>
            `;

            // Fermer le menu déroulant
            toggleDropdown();

            // Ici vous pouvez ajouter la logique pour changer réellement la langue du site
            console.log('Langue sélectionnée:', lang);
        }

        // Fermer le menu si on clique ailleurs
        window.onclick = function(event) {
            if (!event.target.matches('.language-btn') && !event.target.closest('.language-btn')) {
                const dropdown = document.getElementById('languageDropdown');
                const container = document.querySelector('.language-dropdown');
                
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                    container.classList.remove('active');
                }
            }
        }

        // Initialiser avec la langue par défaut
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const lang = urlParams.get('lang') || 'fr';
            changeLanguage(lang);
        }
</script>

<script>
document.getElementById('date_reservation').addEventListener('change', function() {
    const date = this.value;
    if (!date) return;

    const jour = new Date(date).toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();

    fetch(`get_horaires_jour.php?jour=${jour}`)
        .then(res => res.json())
        .then(data => {
            const heureInput = document.getElementById('heure_reservation');
            const infoText = document.getElementById('plage_horaire_info');

            if (data.success) {
                heureInput.min = data.heure_debut;
                heureInput.max = data.heure_fin;
                infoText.textContent = `Heures disponibles : ${data.heure_debut} - ${data.heure_fin}`;
                heureInput.disabled = false;
            } else {
                heureInput.disabled = true;
                infoText.textContent = data.message;
            }
        });
});
</script>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/purecounter/purecounter.js"></script>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/php-email-form/validate.js"></script>
    <script src="assets/vendor/aos/aos.js"></script>
    <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
    <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
    <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>

  </body>

  </html>