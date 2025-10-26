<?php
    session_start();
    require_once '../config.php';
    require_once 'includes/permissions.php';

    // Vérifie l'accès admin
    if (! isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }

    // Vérifier que admin_id existe
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    // Vérifier les permissions
    requireAccess($conn, $_SESSION['admin_id'], 'commandes');

    // Récupérer les infos de l'admin
    $stmt_admin = $conn->prepare("SELECT username, email FROM admin WHERE id = ?");
    $stmt_admin->execute([$_SESSION['admin_id']]);
    $admin_info = $stmt_admin->fetch(PDO::FETCH_ASSOC);
    $admin_name = $admin_info['username'] ?? $_SESSION['admin_username'] ?? 'Admin';
    $admin_email = $admin_info['email'] ?? 'admin@restaurant.com';
    $admin_photo = null;

    // Fonction pour échapper les valeurs
    function e($value)
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    // Fonction pour récupérer les produits avec catégories
    function getAllProductsWithCategories($conn) {
        try {
            $stmt = $conn->prepare("
                SELECT p.*, c.nom as nom_categorie, c.couleur as couleur_categorie
                FROM plats p
                LEFT JOIN categories c ON p.categorie_id = c.id
                WHERE p.disponible = 1
                ORDER BY c.nom, p.nom
            ");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($result === false) {
                throw new Exception("Erreur lors de l'exécution de la requête produits");
            }

            return $result;
        } catch (PDOException $e) {
            throw new Exception("Erreur base de données produits: " . $e->getMessage());
        }
    }

    // Fonction pour récupérer toutes les catégories
    function getAllCategories($conn) {
        try {
            $stmt = $conn->prepare("SELECT * FROM categories ORDER BY nom");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($result === false) {
                throw new Exception("Erreur lors de l'exécution de la requête catégories");
            }

            return $result;
        } catch (PDOException $e) {
            throw new Exception("Erreur base de données catégories: " . $e->getMessage());
        }
    }

    // ===== GESTION AJAX POUR CRÉER UNE COMMANDE MANUELLE =====
    if (isset($_POST['action']) && $_POST['action'] === 'creer_commande_manuelle') {
        header('Content-Type: application/json');

        try {
            $nom_client = $_POST['nom_client'] ?? '';
            $email = $_POST['email'] ?? '';
            $telephone = $_POST['telephone'] ?? '';
            $num_table = $_POST['num_table'] ?? '';
            $mode_paiement = $_POST['mode_paiement'] ?? 'Non spécifié';
            $produits = json_decode($_POST['produits'] ?? '[]', true);
            $remise_type = $_POST['remise_type'] ?? 'aucune';
            $remise_valeur = floatval($_POST['remise_valeur'] ?? 0);
            $total_original = floatval($_POST['total_original'] ?? 0);
            $total_final = floatval($_POST['total_final'] ?? 0);

            // Validation
            if (empty($num_table) || empty($produits)) {
                echo json_encode(['success' => false, 'message' => 'Veuillez renseigner le numéro de table et sélectionner des produits']);
                exit;
            }

            if (empty($mode_paiement) || $mode_paiement === 'Non spécifié') {
                echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner un mode de paiement']);
                exit;
            }

            // Valeurs par défaut
            if (empty($nom_client)) {
                $nom_client = "Table " . $num_table;
            }
            if (empty($telephone)) {
                $telephone = "0000000000";
            }

            // Calculer la remise
            $remise_montant = 0;
            if ($remise_type === 'pourcentage' && $remise_valeur > 0) {
                $remise_montant = ($total_original * $remise_valeur) / 100;
            } elseif ($remise_type === 'montant' && $remise_valeur > 0) {
                $remise_montant = $remise_valeur;
            }

            // Transaction
            $conn->beginTransaction();

            // Insérer la commande
            $stmt = $conn->prepare("
                INSERT INTO commandes (
                    nom_client, email, telephone, num_table, mode_paiement,
                    total, statut, statut_paiement, vu_admin,
                    type_commande, remise_type, remise_valeur, remise_montant,
                    created_at, date_commande
                ) VALUES (
                    :nom_client, :email, :telephone, :num_table, :mode_paiement,
                    :total, 'En cours', 'Impayé', 0,
                    'manuelle', :remise_type, :remise_valeur, :remise_montant,
                    NOW(), NOW()
                )
            ");

            $result = $stmt->execute([
                'nom_client' => $nom_client,
                'email' => $email,
                'telephone' => $telephone,
                'num_table' => $num_table,
                'mode_paiement' => $mode_paiement,
                'total' => $total_final,
                'remise_type' => $remise_type,
                'remise_valeur' => $remise_valeur,
                'remise_montant' => $remise_montant
            ]);

            if (!$result) {
                throw new Exception('Erreur lors de l\'insertion de la commande');
            }

            $commande_id = $conn->lastInsertId();

            // Insérer les détails
            foreach ($produits as $produit) {
                $stmt_detail = $conn->prepare("
                    INSERT INTO commande_details (
                        commande_id, nom_plat, quantite, prix
                    ) VALUES (
                        :commande_id, :nom_plat, :quantite, :prix
                    )
                ");

                $stmt_detail->execute([
                    'commande_id' => $commande_id,
                    'nom_plat' => $produit['nom'],
                    'quantite' => $produit['quantite'],
                    'prix' => $produit['prix']
                ]);
            }

            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'commande_id' => $commande_id
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
        }
        exit;
    }

    // ===== GESTION AJAX POUR RÉCUPÉRER LES PRODUITS =====
    if (isset($_POST['action']) && $_POST['action'] === 'get_produits') {
        header('Content-Type: application/json');

        try {
            $produits = getAllProductsWithCategories($conn);
            $categories = getAllCategories($conn);

            echo json_encode([
                'success' => true,
                'produits' => $produits,
                'categories' => $categories
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    // Récupérer les produits et catégories
    $produits_disponibles = getAllProductsWithCategories($conn);
    $categories_disponibles = getAllCategories($conn);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Commande Manuelle - Restaurant</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/cards-design.css">
    <style>
        body {
            font-family: ui-sans-serif, system-ui, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .category-btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .category-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .category-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .produit-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .produit-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
            border-color: #667eea;
        }

        .produit-selected {
            border-color: #10b981;
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>

        <div class="flex-1 overflow-x-hidden overflow-y-auto">
            <div class="p-6">
                <!-- Header -->
                <header class="bg-gradient-to-r from-green-600 to-emerald-700 shadow-lg rounded-2xl mb-8 p-6">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center space-x-4">
                            <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                                <i class="fas fa-plus-circle text-white text-2xl"></i>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-white">
                                    Nouvelle Commande Manuelle
                                </h1>
                                <p class="text-green-100 text-sm mt-1">
                                    Créer une commande pour une table du restaurant
                                </p>
                            </div>
                        </div>
                        <a href="commandes.php" class="px-6 py-3 bg-white text-green-700 rounded-lg font-semibold hover:bg-green-50 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Retour aux commandes
                        </a>
                    </div>
                </header>

                <!-- Formulaire Commande Manuelle -->
                <div class="bg-white rounded-2xl shadow-xl border-2 border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-8 py-6 border-b-2 border-green-200">
                        <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-utensils mr-3 text-green-600"></i>
                            Détails de la commande
                        </h2>
                    </div>

                    <form id="commandeForm" class="p-8">
                        <!-- Informations client et table -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    <i class="fas fa-table mr-2 text-green-600"></i>N° de table *
                                </label>
                                <input type="text" id="num_table" required
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 font-semibold"
                                       placeholder="Ex: 5">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    <i class="fas fa-user mr-2 text-blue-600"></i>Nom du client
                                </label>
                                <input type="text" id="nom_client"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Optionnel">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    <i class="fas fa-phone mr-2 text-purple-600"></i>Téléphone
                                </label>
                                <input type="tel" id="telephone"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                       placeholder="Optionnel">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    <i class="fas fa-credit-card mr-2 text-orange-600"></i>Mode de paiement *
                                </label>
                                <select id="mode_paiement" required
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 font-semibold">
                                    <option value="">Sélectionner...</option>
                                    <option value="Espèces">Espèces</option>
                                    <option value="Carte bancaire">Carte bancaire</option>
                                    <option value="Mobile Money">Mobile Money</option>
                                    <option value="Chèque">Chèque</option>
                                </select>
                            </div>
                        </div>

                        <!-- Sélection des produits -->
                        <div class="mb-8">
                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-shopping-cart mr-3 text-green-600"></i>
                                Sélection des produits
                            </h3>

                            <!-- Filtres par catégorie -->
                            <div class="flex flex-wrap gap-3 mb-6">
                                <button type="button" onclick="filterByCategory('')"
                                        class="category-btn active px-6 py-3 rounded-lg font-semibold bg-gray-200 text-gray-800">
                                    <i class="fas fa-th mr-2"></i>Tous
                                </button>
                                <?php foreach ($categories_disponibles as $cat): ?>
                                <button type="button" onclick="filterByCategory('<?= htmlspecialchars($cat['nom']) ?>')"
                                        class="category-btn px-6 py-3 rounded-lg font-semibold bg-gray-200 text-gray-800">
                                    <?= htmlspecialchars($cat['nom']) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>

                            <!-- Grille de produits -->
                            <div id="produitsGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                <?php foreach ($produits_disponibles as $produit): ?>
                                <div class="produit-card bg-white rounded-xl p-4 cursor-pointer"
                                     data-category="<?= htmlspecialchars($produit['nom_categorie'] ?? '') ?>"
                                     data-produit='<?= json_encode($produit, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                                     onclick="toggleProduit(this)">
                                    <?php if (!empty($produit['image']) && file_exists('../public/uploads/' . $produit['image'])): ?>
                                    <img src="../public/uploads/<?= htmlspecialchars($produit['image']) ?>"
                                         alt="<?= htmlspecialchars($produit['nom']) ?>"
                                         class="w-full h-32 object-cover rounded-lg mb-3">
                                    <?php else: ?>
                                    <div class="w-full h-32 bg-gradient-to-br from-gray-200 to-gray-300 rounded-lg mb-3 flex items-center justify-center">
                                        <i class="fas fa-utensils text-4xl text-gray-400"></i>
                                    </div>
                                    <?php endif; ?>

                                    <h4 class="font-bold text-gray-800 mb-1"><?= htmlspecialchars($produit['nom']) ?></h4>
                                    <p class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($produit['nom_categorie'] ?? '') ?></p>
                                    <p class="text-lg font-bold text-green-600"><?= number_format($produit['prix'], 0, ',', ' ') ?> FCFA</p>

                                    <div class="mt-3 hidden quantite-controls">
                                        <div class="flex items-center justify-between bg-gray-100 rounded-lg p-2">
                                            <button type="button" onclick="event.stopPropagation(); changeQuantite(this, -1)"
                                                    class="w-8 h-8 bg-red-500 text-white rounded-lg hover:bg-red-600">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <span class="quantite-display font-bold text-lg">1</span>
                                            <button type="button" onclick="event.stopPropagation(); changeQuantite(this, 1)"
                                                    class="w-8 h-8 bg-green-500 text-white rounded-lg hover:bg-green-600">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Résumé de la commande -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Panier -->
                            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border-2 border-blue-200">
                                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                    <i class="fas fa-receipt mr-3 text-blue-600"></i>
                                    Panier
                                </h3>
                                <div id="panierList" class="space-y-2 mb-4 max-h-60 overflow-y-auto">
                                    <p class="text-gray-500 text-center py-8">
                                        <i class="fas fa-shopping-basket text-4xl mb-3 block text-gray-300"></i>
                                        Aucun produit sélectionné
                                    </p>
                                </div>
                                <div class="border-t-2 border-blue-300 pt-4">
                                    <div class="flex justify-between text-lg font-bold">
                                        <span>Sous-total :</span>
                                        <span id="sousTotal">0 FCFA</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Remise et Total -->
                            <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-6 border-2 border-green-200">
                                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                    <i class="fas fa-tags mr-3 text-green-600"></i>
                                    Remise (optionnel)
                                </h3>

                                <div class="space-y-4 mb-6">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-2">Type de remise</label>
                                        <select id="remise_type" onchange="calculateTotal()"
                                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                                            <option value="aucune">Aucune</option>
                                            <option value="pourcentage">Pourcentage (%)</option>
                                            <option value="montant">Montant fixe</option>
                                        </select>
                                    </div>

                                    <div id="remise_value_container" class="hidden">
                                        <label class="block text-sm font-bold text-gray-700 mb-2">Valeur de la remise</label>
                                        <input type="number" id="remise_valeur" min="0" step="0.01" value="0" onchange="calculateTotal()"
                                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                                    </div>

                                    <div id="remise_display" class="hidden bg-orange-100 border-2 border-orange-300 rounded-lg p-3">
                                        <p class="text-sm font-semibold text-orange-800">
                                            Remise appliquée: <span id="remise_montant_display">0 FCFA</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="border-t-2 border-green-300 pt-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-2xl font-bold text-gray-800">TOTAL :</span>
                                        <span id="totalFinal" class="text-3xl font-bold text-green-600">0 FCFA</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="flex gap-4 mt-8">
                            <button type="button" onclick="window.location.href='commandes.php'"
                                    class="flex-1 px-6 py-4 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                                <i class="fas fa-times mr-2"></i>Annuler
                            </button>
                            <button type="submit"
                                    class="flex-1 px-6 py-4 bg-gradient-to-r from-green-600 to-emerald-700 text-white rounded-lg font-semibold hover:from-green-700 hover:to-emerald-800 transition-all transform hover:scale-105">
                                <i class="fas fa-check mr-2"></i>Créer la commande
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let produitsSelectionnes = [];
        let sousTotal = 0;

        // Filtrer par catégorie
        function filterByCategory(category) {
            const cards = document.querySelectorAll('.produit-card');
            const buttons = document.querySelectorAll('.category-btn');

            buttons.forEach(btn => {
                btn.classList.remove('active');
                if ((category === '' && btn.textContent.includes('Tous')) ||
                    btn.textContent.trim() === category) {
                    btn.classList.add('active');
                }
            });

            cards.forEach(card => {
                if (category === '' || card.dataset.category === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Toggle sélection produit
        function toggleProduit(element) {
            const produitData = JSON.parse(element.dataset.produit);
            const isSelected = element.classList.contains('produit-selected');

            if (isSelected) {
                element.classList.remove('produit-selected');
                element.querySelector('.quantite-controls').classList.add('hidden');
                produitsSelectionnes = produitsSelectionnes.filter(p => p.id !== produitData.id);
            } else {
                element.classList.add('produit-selected');
                element.querySelector('.quantite-controls').classList.remove('hidden');
                produitsSelectionnes.push({
                    id: produitData.id,
                    nom: produitData.nom,
                    prix: parseFloat(produitData.prix),
                    quantite: 1
                });
            }

            updatePanier();
        }

        // Changer quantité
        function changeQuantite(button, delta) {
            const card = button.closest('.produit-card');
            const produitData = JSON.parse(card.dataset.produit);
            const display = card.querySelector('.quantite-display');

            const produit = produitsSelectionnes.find(p => p.id === produitData.id);
            if (produit) {
                produit.quantite = Math.max(1, produit.quantite + delta);
                display.textContent = produit.quantite;
                updatePanier();
            }
        }

        // Mettre à jour le panier
        function updatePanier() {
            const panierList = document.getElementById('panierList');

            if (produitsSelectionnes.length === 0) {
                panierList.innerHTML = `
                    <p class="text-gray-500 text-center py-8">
                        <i class="fas fa-shopping-basket text-4xl mb-3 block text-gray-300"></i>
                        Aucun produit sélectionné
                    </p>
                `;
            } else {
                panierList.innerHTML = produitsSelectionnes.map(p => `
                    <div class="flex justify-between items-center bg-white rounded-lg p-3 border border-blue-200">
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800">${p.nom}</p>
                            <p class="text-sm text-gray-600">${p.quantite} x ${p.prix.toLocaleString('fr-FR')} FCFA</p>
                        </div>
                        <p class="font-bold text-blue-600">${(p.quantite * p.prix).toLocaleString('fr-FR')} FCFA</p>
                    </div>
                `).join('');
            }

            calculateTotal();
        }

        // Calculer le total
        function calculateTotal() {
            sousTotal = produitsSelectionnes.reduce((sum, p) => sum + (p.quantite * p.prix), 0);
            document.getElementById('sousTotal').textContent = sousTotal.toLocaleString('fr-FR') + ' FCFA';

            const remiseType = document.getElementById('remise_type').value;
            const remiseValeur = parseFloat(document.getElementById('remise_valeur').value) || 0;
            let remiseMontant = 0;

            if (remiseType === 'pourcentage' && remiseValeur > 0) {
                remiseMontant = (sousTotal * remiseValeur) / 100;
                document.getElementById('remise_value_container').classList.remove('hidden');
                document.getElementById('remise_display').classList.remove('hidden');
            } else if (remiseType === 'montant' && remiseValeur > 0) {
                remiseMontant = remiseValeur;
                document.getElementById('remise_value_container').classList.remove('hidden');
                document.getElementById('remise_display').classList.remove('hidden');
            } else {
                document.getElementById('remise_value_container').classList.add('hidden');
                document.getElementById('remise_display').classList.add('hidden');
            }

            document.getElementById('remise_montant_display').textContent = remiseMontant.toLocaleString('fr-FR') + ' FCFA';

            const totalFinal = Math.max(0, sousTotal - remiseMontant);
            document.getElementById('totalFinal').textContent = totalFinal.toLocaleString('fr-FR') + ' FCFA';
        }

        // Soumettre le formulaire
        document.getElementById('commandeForm').addEventListener('submit', function(e) {
            e.preventDefault();

            if (produitsSelectionnes.length === 0) {
                alert('Veuillez sélectionner au moins un produit');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'creer_commande_manuelle');
            formData.append('num_table', document.getElementById('num_table').value);
            formData.append('nom_client', document.getElementById('nom_client').value);
            formData.append('telephone', document.getElementById('telephone').value);
            formData.append('mode_paiement', document.getElementById('mode_paiement').value);
            formData.append('produits', JSON.stringify(produitsSelectionnes));
            formData.append('remise_type', document.getElementById('remise_type').value);
            formData.append('remise_valeur', document.getElementById('remise_valeur').value);
            formData.append('total_original', sousTotal);
            formData.append('total_final', sousTotal - (parseFloat(document.getElementById('remise_montant_display').textContent) || 0));

            fetch('commande_manuelle.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Commande créée avec succès!');
                    window.location.href = 'commandes.php';
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erreur de connexion: ' + error);
            });
        });

        // Gérer le changement de type de remise
        document.getElementById('remise_type').addEventListener('change', function() {
            if (this.value === 'aucune') {
                document.getElementById('remise_valeur').value = 0;
            }
            calculateTotal();
        });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
