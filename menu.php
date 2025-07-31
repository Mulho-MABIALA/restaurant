<?php
include('config.php');

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les catégories disponibles (id et nom)
    $stmt_cat = $conn->query("SELECT id, nom FROM categories ORDER BY nom ASC");
    $categories_db = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

    if (empty($categories_db)) {
        die("Aucune catégorie disponible.");
    }

    // Vérifier si categorie_id est défini dans l'URL et valide, sinon prendre la première catégorie
    $categorie_id = isset($_GET['categorie_id']) 
        ? (int) $_GET['categorie_id'] 
        : $categories_db[0]['id'];

    // Vérifier que l'id existe bien dans les catégories
    $categorie_ids = array_column($categories_db, 'id');
    if (!in_array($categorie_id, $categorie_ids)) {
        $categorie_id = $categories_db[0]['id'];
    }

    // Récupérer les plats de la catégorie sélectionnée
    $stmt = $conn->prepare("SELECT * FROM plats WHERE categorie_id = :categorie_id");
    $stmt->execute([':categorie_id' => $categorie_id]);
    $plats = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de connexion ou de requête : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#d97706',
                        secondary: '#92400e',
                        tertiary: '#fef3c7',
                        dark: '#1e293b',
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-soft': 'pulse 3s infinite',
                        'bounce-soft': 'bounce 2s infinite',
                        'fade-in': 'fadeIn 0.5s ease-in forwards',
                        'slide-in': 'slideIn 0.3s ease-out forwards',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-10px)' },
                        },
                        fadeIn: {
                            '0%': { opacity: 0 },
                            '100%': { opacity: 1 },
                        },
                        slideIn: {
                            '0%': { transform: 'translateY(20px)', opacity: 0 },
                            '100%': { transform: 'translateY(0)', opacity: 1 },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 50%, #fde68a 100%);
        }
        .card-shadow {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .card-shadow:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .category-tab {
            transition: all 0.3s ease;
        }
        .dish-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .dish-card:hover {
            transform: translateY(-5px);
        }
        .toast {
            animation: slideIn 0.3s forwards, fadeOut 4s forwards;
        }
        @keyframes fadeOut {
            0% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
  
    <!-- Menu Section -->
    <section id="menu" class="py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Category Tabs -->
            <div class="mb-12 overflow-x-auto pb-2">
                <div class="flex space-x-3 md:space-x-4 justify-center min-w-max">
                    <?php foreach ($categories_db as $cat): ?>
                        <a href="?categorie_id=<?= (int)$cat['id'] ?>" 
                           class="category-tab px-5 py-3 rounded-lg text-sm sm:text-base font-medium transition-all duration-300 whitespace-nowrap <?= ($categorie_id === (int)$cat['id']) 
                               ? 'bg-primary text-white shadow-md' 
                               : 'bg-white text-dark hover:bg-gray-100' ?>">
                            <?= htmlspecialchars($cat['nom']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Category Header -->
            <div class="text-center mb-12">
                <?php
                    // Trouver le nom de la catégorie active
                    $categorie_nom = '';
                    foreach ($categories_db as $cat) {
                        if ($categorie_id === (int)$cat['id']) {
                            $categorie_nom = $cat['nom'];
                            break;
                        }
                    }
                ?>
                <h2 class="text-3xl sm:text-4xl font-bold text-dark mb-4"><?= htmlspecialchars($categorie_nom) ?></h2>
                <p class="text-gray-600 max-w-2xl mx-auto">
                    Découvrez notre sélection de plats dans la catégorie <?= htmlspecialchars($categorie_nom) ?>. 
                    Tous nos plats sont préparés avec des ingrédients frais et de saison.
                </p>
            </div>

            <!-- Dishes Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (!empty($plats)): ?>
                    <?php foreach ($plats as $index => $plat): ?>
                        <div class="dish-card bg-white rounded-xl overflow-hidden card-shadow animate-fade-in" style="animation-delay: <?= $index * 0.05 ?>s;">
                            <!-- Dish Image -->
                            <div class="relative h-56 overflow-hidden">
                                <?php if (!empty($plat['image'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($plat['image']) ?>" 
                                         alt="<?= htmlspecialchars($plat['nom']) ?>" 
                                         class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                        <div class="text-center text-gray-500">
                                            <i class="fas fa-utensils text-4xl mb-3"></i>
                                            <p>Image non disponible</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute top-4 right-4 bg-primary text-white px-3 py-1 rounded-full text-sm font-bold">
                                    <?= number_format($plat['prix'] ?? 0, 0, ',', ' ') ?> FCFA
                                </div>
                            </div>
                            
                            <!-- Dish Content -->
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-4">
                                    <h3 class="text-xl font-bold text-dark"><?= htmlspecialchars($plat['nom'] ?? 'Nom non disponible') ?></h3>
                                    <button class="add-to-cart text-primary hover:text-secondary transition-colors"
                                            data-id="<?= $plat['id'] ?>"
                                            data-name="<?= htmlspecialchars($plat['nom']) ?>"
                                            data-price="<?= $plat['prix'] ?>">
                                        <i class="fas fa-plus-circle text-xl"></i>
                                    </button>
                                </div>
                                
                                <p class="text-gray-600 mb-6">
                                    <?= htmlspecialchars($plat['description'] ?? 'Description non disponible') ?>
                                </p>
                                
                                <div class="flex items-center justify-between">
                                    <div class="flex space-x-1 text-yellow-400">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star-half-alt"></i>
                                    </div>
                                    <button class="add-to-cart bg-primary hover:bg-secondary text-white px-4 py-2 rounded-lg transition-colors flex items-center"
                                            data-id="<?= $plat['id'] ?>"
                                            data-name="<?= htmlspecialchars($plat['nom']) ?>"
                                            data-price="<?= $plat['prix'] ?>">
                                        <i class="fas fa-cart-plus mr-2"></i>
                                        Ajouter
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-12">
                        <div class="max-w-lg mx-auto bg-white p-8 rounded-xl shadow-md">
                            <i class="fas fa-utensils text-5xl text-gray-300 mb-6"></i>
                            <h3 class="text-2xl font-bold text-gray-700 mb-4">Aucun plat disponible</h3>
                            <p class="text-gray-600 mb-6">
                                Cette catégorie sera bientôt remplie de délicieux plats. 
                                Veuillez revenir plus tard ou explorer nos autres catégories.
                            </p>
                            <a href="?" class="inline-block bg-primary hover:bg-secondary text-white px-6 py-3 rounded-lg transition-colors">
                                Voir toutes les catégories
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script>
        // Gestion du panier
        let cartCount = 0;
        const cartCountElement = document.getElementById('cart-count');
        const toastElement = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');
        
        // Mise à jour du compteur de panier
        function updateCartCount() {
            cartCountElement.textContent = cartCount;
            cartCountElement.classList.add('animate-bounce');
            setTimeout(() => {
                cartCountElement.classList.remove('animate-bounce');
            }, 1000);
        }
        
        // Affichage d'une notification toast
        function showToast(message) {
            toastMessage.textContent = message;
            toastElement.classList.remove('hidden');
            setTimeout(() => {
                toastElement.classList.add('hidden');
            }, 3000);
        }
        
        // Ajout au panier
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.dataset.id;
                const name = this.dataset.name;
                const price = this.dataset.price;
                
                // Animation sur le bouton
                this.classList.add('animate-ping');
                setTimeout(() => {
                    this.classList.remove('animate-ping');
                }, 500);
                
                // Envoi de la requête au serveur
                fetch('ajouter_au_panier.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${id}&name=${encodeURIComponent(name)}&price=${price}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cartCount++;
                        updateCartCount();
                        showToast(`${name} ajouté au panier!`);
                    } else {
                        showToast('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    showToast('Erreur de connexion');
                });
            });
        });
        
        // Animation au chargement de la page
        document.addEventListener('DOMContentLoaded', () => {
            // Animation des cartes
            const cards = document.querySelectorAll('.dish-card');
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
            });
            
            setTimeout(() => {
                cards.forEach(card => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                });
            }, 100);
        });
    </script>
</body>
</html>