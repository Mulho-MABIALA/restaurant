
        // Variables globales
        let selectedItem = {};
        let currentQuantity = 1;
        let cartItems = [];

        // Afficher le menu directement au chargement
        document.addEventListener('DOMContentLoaded', function() {
            showMenu();
            updateCartDisplay();
        });

        // Gestion du panier
        function openCartModal() {
            document.getElementById('cartModal').style.display = 'flex';
            renderCartItems();
        }

        function closeCartModal() {
            document.getElementById('cartModal').style.display = 'none';
        }

        function renderCartItems() {
            const cartItemsContainer = document.getElementById('cartItems');
            const cartEmpty = document.getElementById('cartEmpty');
            const cartFooter = document.getElementById('cartFooter');

            if (cartItems.length === 0) {
                cartEmpty.style.display = 'block';
                cartItemsContainer.style.display = 'none';
                cartFooter.style.display = 'none';
                return;
            }

            cartEmpty.style.display = 'none';
            cartItemsContainer.style.display = 'block';
            cartFooter.style.display = 'block';

            let cartHTML = '';
            let totalAmount = 0;

            cartItems.forEach((item, index) => {
                totalAmount += item.total;
                
                cartHTML += `
                    <div class="cart-item">
                        ${item.image && item.image.trim() !== '' ? 
                            `<img src="uploads/${item.image}" alt="${item.item}" class="cart-item-image">` :
                            `<div class="cart-item-placeholder"><i class="fas fa-utensils"></i></div>`
                        }
                        <div class="cart-item-details">
                            <div class="cart-item-name">${item.item}</div>
                            ${item.specialInstructions ? 
                                `<div class="cart-item-instructions">${item.specialInstructions}</div>` : 
                                ''
                            }
                            <div class="cart-item-price">${item.total.toLocaleString()} F</div>
                        </div>
                        <div class="cart-item-actions">
                            <div class="quantity-controls">
                                <button class="quantity-btn-small" onclick="updateCartItemQuantity(${index}, -1)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="quantity-display-small">${item.quantity}</span>
                                <button class="quantity-btn-small" onclick="updateCartItemQuantity(${index}, 1)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <i class="fas fa-trash remove-item" onclick="removeCartItem(${index})"></i>
                        </div>
                    </div>
                `;
            });

            cartItemsContainer.innerHTML = cartHTML;
            document.getElementById('cartTotalAmount').textContent = totalAmount.toLocaleString() + ' F';
        }

        function updateCartItemQuantity(index, change) {
            if (cartItems[index]) {
                const newQuantity = cartItems[index].quantity + change;
                if (newQuantity > 0) {
                    cartItems[index].quantity = newQuantity;
                    cartItems[index].total = cartItems[index].price * newQuantity;
                    renderCartItems();
                    updateCartDisplay();
                } else {
                    removeCartItem(index);
                }
            }
        }

        function removeCartItem(index) {
            cartItems.splice(index, 1);
            renderCartItems();
            updateCartDisplay();
            showToast('Article supprimé du panier');
        }

        function updateCartDisplay() {
            const cartBadge = document.getElementById('cartBadge');
            const totalItems = cartItems.reduce((total, item) => total + item.quantity, 0);
            
            cartBadge.textContent = totalItems;
            if (totalItems > 0) {
                cartBadge.classList.add('show');
            } else {
                cartBadge.classList.remove('show');
            }
        }

        function proceedToCheckout() {
            if (cartItems.length === 0) {
                showToast('Votre panier est vide');
                return;
            }
            
            // Envoyer les données au serveur via AJAX
            fetch('menu.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=update_cart&cart_data=' + encodeURIComponent(JSON.stringify(cartItems))
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'commander.php';
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                // Fallback : utiliser localStorage quand même
                localStorage.setItem('cartItems', JSON.stringify(cartItems));
                window.location.href = 'commander.php';
            });
        }

        // Alternative : fonction de redirection directe (à tester si la première ne marche pas)
        function redirectToCommander() {
            console.log('Redirection directe vers commander.php');
            window.location.replace('commander.php');
        }

        // Afficher le menu
        function showMenu() {
            document.getElementById('heroSection').style.display = 'none';
            document.getElementById('menuContainer').style.display = 'block';
        }

        // Afficher la modal de commande (renommé pour éviter les conflits)
        function showOrderModal() {
            document.getElementById('orderModal').style.display = 'flex';
        }

        // Ouvrir la modal avec les détails de l'item
        function openOrderModal(name, price, image, description) {
            selectedItem = { 
                name: name, 
                price: price, 
                image: image, 
                description: description 
            };
            
            // Update modal content
            document.getElementById('orderItemName').textContent = name;
            document.getElementById('orderItemPrice').textContent = price.toLocaleString() + ' FCFA';
            document.getElementById('unitPrice').textContent = price.toLocaleString() + ' F';
            
            // Update image
            const imageContainer = document.getElementById('orderItemImageContainer');
            if (image && image.trim() !== '') {
                imageContainer.innerHTML = `<img src="uploads/${image}" alt="${name}" class="order-item-image">`;
            } else {
                imageContainer.innerHTML = `<div class="order-item-placeholder"><i class="fas fa-utensils"></i></div>`;
            }
            
            // Reset and update totals
            currentQuantity = 1;
            document.getElementById('quantityDisplay').textContent = '1';
            document.getElementById('specialInstructions').value = '';
            updateOrderSummary();
            showOrderModal();
        }

        // Fermer la modal de commande
        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }

        // Ajout rapide au panier (nouveau)
        function quickAddToCart(name, price, image, description) {
            const orderData = {
                item: name,
                price: price,
                quantity: 1,
                total: price,
                specialInstructions: '',
                image: image,
                id: Date.now() // ID unique pour chaque item
            };

            // Ajouter au panier
            cartItems.push(orderData);
            updateCartDisplay();
            
            // Trouver le bouton qui a été cliqué
            const clickedButton = event.target.closest('.quick-add-btn');
            
            // Animation du bouton
            clickedButton.classList.add('quick-add-success');
            clickedButton.innerHTML = '<i class="fas fa-check"></i>';
            
            // Afficher une notification
            showToast(`${name} ajouté au panier !`);
            
            // Reset du bouton après 1 seconde
            setTimeout(() => {
                clickedButton.classList.remove('quick-add-success');
                clickedButton.innerHTML = '<i class="fas fa-plus"></i>';
            }, 1000);

            console.log('Panier mis à jour:', cartItems);
        }

        // Changer la quantité
        function changeQuantity(change) {
            const newQuantity = currentQuantity + change;
            if (newQuantity >= 1) {
                currentQuantity = newQuantity;
                document.getElementById('quantityDisplay').textContent = currentQuantity;
                updateOrderSummary();
            }
        }

        // Mettre à jour le résumé de commande
        function updateOrderSummary() {
            const total = selectedItem.price * currentQuantity;
            document.getElementById('summaryQuantity').textContent = currentQuantity;
            document.getElementById('totalPrice').textContent = total.toLocaleString() + ' F';
            document.getElementById('addToCartText').textContent = 
                `${total.toLocaleString()} F • Ajouter`;
        }

        // Ajouter au panier depuis la modal
        function addToCart() {
            const specialInstructions = document.getElementById('specialInstructions').value;
            
            const orderData = {
                item: selectedItem.name,
                price: selectedItem.price,
                quantity: currentQuantity,
                total: selectedItem.price * currentQuantity,
                specialInstructions: specialInstructions,
                image: selectedItem.image,
                id: Date.now()
            };

            // Ajouter au panier
            cartItems.push(orderData);
            updateCartDisplay();

            // Animation du bouton
            const btn = document.querySelector('.add-to-cart-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
            btn.style.background = 'linear-gradient(135deg, var(--success), #16a34a)';
            
            // Afficher une notification
            showToast(`${selectedItem.name} ajouté au panier !`);
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = 'linear-gradient(135deg, var(--accent), #c19654)';
                closeOrderModal();
            }, 1500);

            console.log('Panier mis à jour:', cartItems);
        }

        // Afficher une notification toast
        function showToast(message) {
            // Supprimer les anciens toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());
            
            // Créer le nouveau toast
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(toast);
            
            // Animer l'entrée
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            // Supprimer après 3 secondes
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 3000);
        }

        // Fermer les modals en cliquant à l'extérieur
        document.addEventListener('click', function(event) {
            const orderModal = document.getElementById('orderModal');
            const cartModal = document.getElementById('cartModal');
            
            if (event.target === orderModal) {
                closeOrderModal();
            }

            if (event.target === cartModal) {
                closeCartModal();
            }
        });

        // Gestion des touches du clavier
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeOrderModal();
                closeCartModal();
            }
        });
   