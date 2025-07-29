document.addEventListener('DOMContentLoaded', function () {
  // Fonction pour mettre à jour le compteur du panier
  function updateCartCount() {
    const cartCount = document.getElementById('cart-count');
    if (cartCount) {
      const cart = JSON.parse(localStorage.getItem('cart')) || [];
      cartCount.textContent = cart.length;
    }
  }

  // Appel immédiat lors du chargement
  updateCartCount();

  // Tu peux appeler updateCartCount() ailleurs aussi après modification du panier
});
