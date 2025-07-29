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
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#d97706',
                        secondary: '#92400e',
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-soft': 'pulse 3s infinite',
                        'bounce-soft': 'bounce 2s infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-10px)' },
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
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .card-shadow:hover {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
    </style>
</head>
<body class="min-h-screen gradient-bg">
    <!-- Section Menu -->
    <section id="menu" class="py-20 px-4 sm:px-6 lg:px-8">
        <!-- Titre de la section avec design modernisé -->
        <div class="max-w-7xl mx-auto text-center mb-20" data-aos="fade-up">
            <div class="relative inline-block">
                <h2 class="text-5xl sm:text-6xl lg:text-7xl font-black text-transparent bg-clip-text bg-gradient-to-r from-gray-800 via-gray-900 to-gray-800 mb-6 tracking-tight">
                    Notre Menu
                </h2>
                <div class="absolute -inset-1 bg-gradient-to-r from-primary to-secondary rounded-lg blur opacity-20 animate-pulse-soft"></div>
            </div>
            <div class="max-w-2xl mx-auto">
                <p class="text-xl sm:text-2xl text-gray-700 font-light leading-relaxed">
                    Découvrez notre
                    <span class="text-primary font-bold bg-white px-3 py-1 rounded-full shadow-md inline-block transform hover:scale-105 transition-transform duration-300">
                        sélection gourmande
                    </span>
                    préparée avec passion
                </p>
            </div>
        </div>

        <div class="max-w-7xl mx-auto">
            <!-- Onglets avec design Card moderne -->
            <div class="mb-16" data-aos="fade-up" data-aos-delay="100">
                <div class="bg-white/80 backdrop-blur-sm rounded-3xl p-2 sm:p-3 shadow-2xl border border-white/20">
                    <div class="flex flex-wrap justify-center gap-2 sm:gap-3">
                        <?php foreach ($categories_db as $cat): ?>
                            <a href="?categorie_id=<?= (int)$cat['id'] ?>" 
                               class="group relative px-6 sm:px-8 py-4 rounded-2xl text-sm sm:text-base font-bold transition-all duration-500 transform hover:scale-105 <?= ($categorie_id === (int)$cat['id']) 
                                   ? 'bg-gradient-to-r from-primary to-secondary text-white shadow-xl shadow-primary/30' 
                                   : 'bg-white/70 text-gray-700 hover:bg-white hover:text-primary shadow-lg hover:shadow-xl' ?>">
                                
                                <?php if ($categorie_id === (int)$cat['id']): ?>
                                    <div class="absolute inset-0 bg-gradient-to-r from-primary to-secondary rounded-2xl opacity-100"></div>
                                    <div class="absolute inset-0 bg-gradient-to-r from-primary to-secondary rounded-2xl blur opacity-50 animate-pulse-soft"></div>
                                <?php endif; ?>
                                
                                <span class="relative z-10 flex items-center">
                                    <svg class="w-5 h-5 mr-2 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    <?= htmlspecialchars($cat['nom']) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Header de catégorie modernisé -->
            <div class="text-center mb-16 transition-all duration-500" data-aos="fade-up" data-aos-delay="200">
                <div class="inline-block">
                    <p class="text-primary text-sm sm:text-base uppercase tracking-widest font-black mb-3 opacity-80">
                        Catégorie sélectionnée
                    </p>
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
                    <h3 class="text-4xl sm:text-5xl lg:text-6xl font-black text-gray-800 tracking-tight bg-white/60 backdrop-blur-sm px-8 py-4 rounded-2xl shadow-lg border border-white/30">
                        <?= htmlspecialchars($categorie_nom) ?>
                    </h3>
                </div>
            </div>

            <!-- Grille des plats avec design Cards premium -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 sm:gap-8 lg:gap-10">
                <?php if (!empty($plats)): ?>
                    <?php foreach ($plats as $index => $plat): ?>
                        <div class="group relative bg-white/90 backdrop-blur-sm rounded-3xl card-shadow transition-all duration-500 overflow-hidden hover:-translate-y-3 hover:rotate-1 animate-float border border-white/50"
                             style="animation-delay: <?= $index * 100 ?>ms;">
                            
                            <!-- Effet de brillance au survol -->
                            <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>
                            
                            <!-- Image du plat avec overlay moderne -->
                            <div class="relative overflow-hidden h-56 sm:h-64">
                                <?php if (!empty($plat['image'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($plat['image']) ?>"
                                         alt="<?= htmlspecialchars($plat['nom']) ?>"
                                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gradient-to-br from-gray-100 via-gray-200 to-gray-300 flex items-center justify-center">
                                        <div class="text-center">
                                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-2 animate-bounce-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <p class="text-gray-500 text-sm font-medium">Image à venir</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Badge de prix modernisé -->
                                <div class="absolute top-4 right-4 group/price">
                                    <div class="bg-gradient-to-r from-primary to-secondary text-white px-4 py-2 rounded-2xl text-sm font-black shadow-2xl border-2 border-white/30 backdrop-blur-sm transform group-hover/price:scale-110 transition-transform duration-300">
                                        <div class="flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                            </svg>
                                            <span><?= number_format($plat['prix'] ?? 0, 0, ',', ' ') ?></span>
                                        </div>
                                        <div class="text-xs opacity-90 text-center">FCFA</div>
                                    </div>
                                </div>

                                <!-- Gradient overlay -->
                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </div>

                            <!-- Contenu du plat avec espacement amélioré -->
                            <div class="p-6 sm:p-8 space-y-6">
                                <div class="space-y-4">
                                    <h4 class="text-xl sm:text-2xl font-black text-gray-800 leading-tight group-hover:text-primary transition-colors duration-300">
                                        <?= htmlspecialchars($plat['nom'] ?? 'Nom non disponible') ?>
                                    </h4>
                                    <div class="h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent"></div>
                                    <p class="text-gray-600 text-sm sm:text-base leading-relaxed line-clamp-3 font-medium">
                                        <?= htmlspecialchars($plat['description'] ?? 'Description non disponible') ?>
                                    </p>
                                </div>
                                
                                <!-- Bouton d'ajout au panier premium -->
                                <button class="add-to-cart group/btn relative w-full bg-gradient-to-r from-primary via-orange-500 to-secondary text-white font-black py-4 px-6 rounded-2xl overflow-hidden transition-all duration-500 transform hover:scale-105 active:scale-95 shadow-xl hover:shadow-2xl border-2 border-white/20"
                                        data-id="<?= $plat['id'] ?>"
                                        data-name="<?= htmlspecialchars($plat['nom']) ?>"
                                        data-price="<?= $plat['prix'] ?>">
                                    
                                    <!-- Effet de vague au clic -->
                                    <div class="absolute inset-0 bg-white/20 translate-y-full group-hover/btn:translate-y-0 transition-transform duration-300"></div>
                                    
                                    <span class="relative z-10 flex items-center justify-center space-x-3">
                                        <svg class="w-6 h-6 group-hover/btn:rotate-12 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17M17 13v4a2 2 0 01-2 2H9a2 2 0 01-2-2v-4m8 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                                        </svg>
                                        <span class="text-base sm:text-lg">Ajouter au panier</span>
                                        <svg class="w-5 h-5 group-hover/btn:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-20">
                        <div class="bg-white/90 backdrop-blur-sm rounded-3xl card-shadow p-16 max-w-lg mx-auto border border-white/50">
                            <div class="space-y-6">
                                <div class="relative">
                                    <svg class="w-24 h-24 text-gray-300 mx-auto animate-bounce-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.469-1.009-5.927-2.709A7.963 7.963 0 014.169 14.5A7.962 7.962 0 003 12.291M21 12.29A7.962 7.962 0 0119.831 14.5a7.963 7.963 0 01-1.902-2.209A7.962 7.962 0 0116 15c-2.34 0-4.469-1.009-5.927-2.709M15 11V9a3 3 0 00-3-3h0a3 3 0 00-3 3v2"></path>
                                    </svg>
                                    <div class="absolute inset-0 bg-gradient-to-r from-primary/20 to-secondary/20 rounded-full blur-xl opacity-50"></div>
                                </div>
                                <div class="space-y-3">
                                    <h3 class="text-2xl sm:text-3xl font-black text-gray-700">Aucun plat disponible</h3>
                                    <div class="h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent w-1/2 mx-auto"></div>
                                    <p class="text-gray-500 text-lg font-medium">Cette catégorie sera bientôt remplie de délicieux plats !</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script>
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.dataset.id;
                const name = this.dataset.name;
                const price = this.dataset.price;

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
                        alert(data.message);
                        updateCartCount(); // tu peux aussi faire appel à une fonction ici pour mettre à jour un badge
                    } else {
                        alert('Erreur : ' + data.message);
                    }
                });
            });
        });
    </script>
</body>
</html>