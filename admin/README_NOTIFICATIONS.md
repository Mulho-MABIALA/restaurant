# Système de Notifications pour les Réservations

## 📋 Vue d'ensemble

Ce système permet de recevoir des notifications en temps réel pour les nouvelles réservations effectuées par les clients depuis la page `index.php`. Il affiche également des rappels pour les réservations prévues le jour même.

## ✨ Fonctionnalités

### 1. Badge de notification
- Un badge rouge apparaît sur l'icône de cloche dans le header
- Affiche le nombre de nouvelles réservations non lues
- Animation pulsante pour attirer l'attention

### 2. Panneau de notifications
- Accessible en cliquant sur l'icône de cloche
- Divisé en deux sections :
  - **Réservations d'aujourd'hui** : Liste toutes les réservations prévues pour le jour actuel
  - **Nouvelles réservations** : Affiche les 5 dernières réservations non lues

### 3. Notifications visuelles (Toast)
- Messages popup dans le coin supérieur droit
- Types de notifications :
  - ✅ Succès (vert) : Nouvelle réservation reçue
  - ℹ️ Info (bleu) : Rappel des réservations du jour
  - ⚠️ Avertissement (orange)
  - ❌ Erreur (rouge)

### 4. Notification sonore
- Un son discret est joué lors de la réception d'une nouvelle réservation
- Utilise l'API Web Audio (fonctionne sur tous les navigateurs modernes)

### 5. Mise à jour automatique
- Les notifications sont actualisées toutes les **30 secondes**
- Pas besoin de rafraîchir manuellement la page

## 🔧 Fichiers impliqués

### 1. `admin/reservations.php`
Le fichier principal qui affiche :
- Le badge de notification dans le header
- Le panneau déroulant des notifications
- Le code JavaScript pour gérer les mises à jour

### 2. `admin/get_nouvelles_reservations.php`
Endpoint AJAX qui retourne en JSON :
```json
{
  "success": true,
  "nombre_nouvelles": 3,
  "reservations_aujourdhui": [...],
  "dernieres_reservations": [...],
  "date_actuelle": "2025-10-12"
}
```

### 3. `forms/book-a-table.php`
Le formulaire client qui insère les nouvelles réservations avec le statut `'non_lu'`

## 📊 Base de données

### Table : `reservations`

Les colonnes importantes pour les notifications :

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT | Identifiant unique |
| `nom` | VARCHAR | Nom du client |
| `email` | VARCHAR | Email du client |
| `telephone` | VARCHAR | Numéro de téléphone |
| `date_reservation` | DATE | Date de la réservation |
| `heure_reservation` | TIME | Heure de la réservation |
| `personnes` | INT | Nombre de personnes |
| `message` | TEXT | Message optionnel |
| `statut` | VARCHAR | `'non_lu'` ou `'lu'` |
| `date_envoi` | DATETIME | Date de création |

## 🎯 Comment ça fonctionne

### Flux de notification

1. **Client fait une réservation** (`index.php`)
   - Remplit le formulaire
   - Les données sont envoyées à `forms/book-a-table.php`
   - La réservation est insérée avec `statut = 'non_lu'`

2. **Mise à jour automatique côté admin**
   - Toutes les 30 secondes, `updateNotifications()` est appelé
   - Fait une requête AJAX vers `get_nouvelles_reservations.php`
   - Récupère les nouvelles réservations et celles du jour

3. **Affichage des notifications**
   - Si de nouvelles réservations sont détectées :
     - Le badge s'affiche avec le nombre
     - Un son est joué
     - Un toast de notification apparaît
   - Si des réservations sont prévues aujourd'hui :
     - Un rappel s'affiche au chargement de la page

4. **Consultation des réservations**
   - L'admin clique sur le badge
   - Le panneau s'ouvre avec tous les détails
   - Peut cliquer sur "Actualiser" pour forcer la mise à jour

### Marquage comme "lu"

Actuellement, toutes les réservations sont automatiquement marquées comme "lues" lors du chargement de la page :

```php
$conn->query("UPDATE reservations SET statut = 'lu' WHERE statut = 'non_lu'");
```

**Note** : Vous pouvez modifier ce comportement pour marquer comme "lu" uniquement lorsque l'admin clique sur une réservation spécifique.

## 🎨 Personnalisation

### Changer l'intervalle de mise à jour

Dans `reservations.php`, ligne ~2188 :
```javascript
setInterval(updateNotifications, 30000); // 30000 ms = 30 secondes
```

Pour vérifier toutes les 10 secondes :
```javascript
setInterval(updateNotifications, 10000);
```

### Modifier le son de notification

Dans la fonction `playNotificationSound()`, ligne ~2009 :
```javascript
oscillator.frequency.value = 800; // Fréquence du son (Hz)
gainNode.gain.setValueAtTime(0.3, audioContext.currentTime); // Volume (0 à 1)
```

### Changer la durée d'affichage des toasts

Dans la fonction `showToast()`, ligne ~2174 :
```javascript
setTimeout(() => {
    // Supprimer après 5 secondes
    toast.remove();
}, 5000); // 5000 ms = 5 secondes
```

## 🐛 Dépannage

### Les notifications ne s'affichent pas

1. Vérifier que Font Awesome est chargé :
   ```html
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   ```

2. Vérifier la console du navigateur (F12) pour les erreurs JavaScript

3. Vérifier que `get_nouvelles_reservations.php` est accessible :
   - Ouvrir directement : `http://localhost/restaurant/admin/get_nouvelles_reservations.php`
   - Devrait retourner du JSON

### Le son ne fonctionne pas

- Certains navigateurs bloquent l'autoplay audio
- L'utilisateur doit interagir avec la page d'abord (clic, touche clavier)
- Vérifier les paramètres de son du navigateur

### Les mises à jour sont lentes

- Réduire l'intervalle de mise à jour (voir section Personnalisation)
- Vérifier la connexion réseau
- Optimiser la requête SQL dans `get_nouvelles_reservations.php`

## 📱 Compatibilité

✅ Chrome / Edge / Brave
✅ Firefox
✅ Safari
✅ Opera
✅ Mobile (iOS / Android)

## 🚀 Améliorations possibles

1. **Notifications push** : Utiliser l'API Push pour recevoir des notifications même lorsque l'onglet est fermé
2. **Filtres avancés** : Permettre de filtrer les notifications par date, client, etc.
3. **Marquage individuel** : Marquer chaque réservation comme lue individuellement
4. **Historique** : Conserver un log de toutes les notifications reçues
5. **Paramètres** : Permettre à chaque admin de configurer ses préférences (son, intervalle, etc.)

## 📞 Support

Pour toute question ou problème, consultez le code source ou contactez le développeur.

---

**Dernière mise à jour** : Octobre 2025
