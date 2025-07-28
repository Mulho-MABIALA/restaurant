<?php
require_once '../config.php';

// Montants encaissés
$totals = $conn->query("
  SELECT DATE_FORMAT(date_commande, '%Y-%m') AS mois, SUM(ci.quantite * p.prix) AS total
  FROM commandes c
  JOIN commande_items ci ON ci.commande_id = c.id
  JOIN plats p ON ci.plat_id = p.id
  WHERE c.statut = 'Livrée'
  GROUP BY mois
");

// Plats les plus commandés
$plats = $conn->query("
  SELECT p.nom, SUM(ci.quantite) AS total_commandé
  FROM commande_items ci
  JOIN plats p ON ci.plat_id = p.id
  GROUP BY ci.plat_id
  ORDER BY total_commandé DESC
  LIMIT 5
");
?>
