<?php

class AuditManager {
    private $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    public function logPayrollAction($action, $data, $userId = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO audit_paie (action, data, user_id, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $action,
                json_encode($data),
                $userId ?? ($_SESSION['user_id'] ?? null),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Erreur logPayrollAction: " . $e->getMessage());
        }
    }
    
    public function logPresenceModification($data, $userId = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO audit_presences (action, data, user_id, ip_address, created_at)
                VALUES ('MODIFY_PRESENCE', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                json_encode($data),
                $userId ?? ($_SESSION['user_id'] ?? null),
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Erreur logPresenceModification: " . $e->getMessage());
        }
    }
    
    public function getAuditTrail($filters = []) {
        try {
            $sql = "
                SELECT * FROM (
                    SELECT 'PAIE' as type, action, data, user_id, ip_address, created_at 
                    FROM audit_paie
                    UNION ALL
                    SELECT 'PRESENCE' as type, action, data, user_id, ip_address, created_at 
                    FROM audit_presences
                ) as audit_combined
                WHERE 1=1
            ";
            
            $params = [];
            
            if (!empty($filters['type'])) {
                $sql .= " AND type = ?";
                $params[] = strtoupper($filters['type']);
            }
            
            if (!empty($filters['date_debut'])) {
                $sql .= " AND DATE(created_at) >= ?";
                $params[] = $filters['date_debut'];
            }
            
            if (!empty($filters['date_fin'])) {
                $sql .= " AND DATE(created_at) <= ?";
                $params[] = $filters['date_fin'];
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT 1000";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Erreur getAuditTrail: " . $e->getMessage());
            return [];
        }
    }
}
?>