<?php
/**
 * API Finance pour la gestion de la facturation
 */

require_once "../config.php";
session_start();

// Headers pour JSON et CORS
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

// Vérification de l'authentification admin
if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    http_response_code(401);
    echo json_encode(["error" => "Non autorisé", "message" => "Session admin requise"]);
    exit;
}

$action = $_GET["action"] ?? "";
$method = $_SERVER["REQUEST_METHOD"];

try {
    switch ($action) {
        case "fournisseurs":
            $stmt = $conn->query("SELECT id, nom, contact_nom, telephone, email FROM fournisseurs WHERE actif = 1 ORDER BY nom");
            $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($fournisseurs);
            break;
            
        case "factures_clients":
            $sql = "SELECT f.*, c.nom as nom_client 
                    FROM factures f 
                    LEFT JOIN clients c ON f.client_id = c.id 
                    WHERE f.type = 'client' ";
            
            // Filtres optionnels
            if (isset($_GET["date_debut"])) {
                $sql .= " AND f.date_facture >= :date_debut";
            }
            if (isset($_GET["date_fin"])) {
                $sql .= " AND f.date_facture <= :date_fin";
            }
            
            $sql .= " ORDER BY f.date_facture DESC";
            
            $stmt = $conn->prepare($sql);
            if (isset($_GET["date_debut"])) {
                $stmt->bindParam(":date_debut", $_GET["date_debut"]);
            }
            if (isset($_GET["date_fin"])) {
                $stmt->bindParam(":date_fin", $_GET["date_fin"]);
            }
            
            $stmt->execute();
            $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($factures);
            break;
            
        case "factures_fournisseurs":
            $sql = "SELECT f.*, fou.nom as nom_fournisseur 
                    FROM factures f 
                    LEFT JOIN fournisseurs fou ON f.fournisseur_id = fou.id 
                    WHERE f.type = 'fournisseur' 
                    ORDER BY f.date_facture DESC";
            $stmt = $conn->query($sql);
            $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($factures);
            break;
            
        case "echeances":
            $sql = "SELECT f.*, fou.nom as nom_fournisseur,
                    DATEDIFF(f.date_echeance, CURDATE()) as jours_restants
                    FROM factures f 
                    LEFT JOIN fournisseurs fou ON f.fournisseur_id = fou.id 
                    WHERE f.type = 'fournisseur' 
                    AND f.statut != 'payee'
                    AND f.date_echeance IS NOT NULL
                    ORDER BY f.date_echeance ASC";
            $stmt = $conn->query($sql);
            $echeances = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($echeances);
            break;
            
        case "facture_fournisseur":
            if ($method === "POST") {
                $data = json_decode(file_get_contents("php://input"), true);
                
                // Validation des données
                if (!$data || !isset($data["numero_facture"]) || !isset($data["fournisseur_id"])) {
                    throw new Exception("Données manquantes");
                }
                
                // Calculer les totaux
                $total_ht = 0;
                $total_tva = 0;
                
                foreach ($data["lignes"] as $ligne) {
                    $ligne_ht = $ligne["quantite"] * $ligne["prix_unitaire_ht"];
                    $ligne_tva = $ligne_ht * ($ligne["taux_tva"] / 100);
                    $total_ht += $ligne_ht;
                    $total_tva += $ligne_tva;
                }
                
                $total_ttc = $total_ht + $total_tva;
                
                // Insérer la facture
                $sql = "INSERT INTO factures (
                    numero_facture, type, fournisseur_id, date_facture, 
                    date_echeance, montant_ht, montant_tva, montant_ttc, statut
                ) VALUES (
                    :numero, 'fournisseur', :fournisseur_id, :date_facture,
                    :date_echeance, :montant_ht, :montant_tva, :montant_ttc, 'en_attente'
                )";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ":numero" => $data["numero_facture"],
                    ":fournisseur_id" => $data["fournisseur_id"],
                    ":date_facture" => $data["date_facture"],
                    ":date_echeance" => $data["date_echeance"],
                    ":montant_ht" => $total_ht,
                    ":montant_tva" => $total_tva,
                    ":montant_ttc" => $total_ttc
                ]);
                
                $facture_id = $conn->lastInsertId();
                
                echo json_encode([
                    "success" => true,
                    "message" => "Facture créée avec succès",
                    "facture_id" => $facture_id
                ]);
            }
            break;
            
        case "marquer_paye":
            if ($method === "POST") {
                $data = json_decode(file_get_contents("php://input"), true);
                
                if (!$data || !isset($data["facture_id"])) {
                    throw new Exception("ID de facture manquant");
                }
                
                $sql = "UPDATE factures SET statut = 'payee', date_paiement = NOW() WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([":id" => $data["facture_id"]]);
                
                echo json_encode([
                    "success" => true,
                    "message" => "Facture marquée comme payée"
                ]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(["error" => "Action non valide: " . $action]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
}
?>