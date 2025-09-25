<?php
class PresenceManager {
    private $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    public function getEmployeePresences($employeeId, $startDate, $endDate) {
        try {
            $stmt = $this->conn->prepare("
                SELECT p.*, 
                    DATE(p.heure_arrivee) as date_presence,
                    TIME(p.heure_arrivee) as heure_arrivee_time,
                    TIME(p.heure_depart) as heure_depart_time
                FROM presences p
                WHERE p.employe_id = ?
                AND DATE(p.heure_arrivee) BETWEEN ? AND ?
                ORDER BY p.heure_arrivee
            ");
            $stmt->execute([$employeeId, $startDate, $endDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur getEmployeePresences: " . $e->getMessage());
            return [];
        }
    }
    
    public function calculateWorkedHours($employeeId, $startDate, $endDate) {
        try {
            $presences = $this->getEmployeePresences($employeeId, $startDate, $endDate);
            $totalHours = 0;
            $workingDays = [];
            
            foreach ($presences as $presence) {
                if ($presence['heure_arrivee'] && $presence['heure_depart']) {
                    $start = new DateTime($presence['heure_arrivee']);
                    $end = new DateTime($presence['heure_depart']);
                    $interval = $start->diff($end);
                    $hours = $interval->h + ($interval->i / 60);
                    
                    $date = $presence['date_presence'];
                    if (!isset($workingDays[$date])) {
                        $workingDays[$date] = 0;
                    }
                    $workingDays[$date] += $hours;
                }
            }
            
            foreach ($workingDays as $date => $hours) {
                $totalHours += min($hours, 10); // Limiter à 10h par jour max
            }
            
            return [
                'total_hours' => round($totalHours, 2),
                'working_days' => count($workingDays),
                'daily_breakdown' => $workingDays
            ];
        } catch (Exception $e) {
            error_log("Erreur calculateWorkedHours: " . $e->getMessage());
            return ['total_hours' => 0, 'working_days' => 0, 'daily_breakdown' => []];
        }
    }
    
    public function getOvertimeHours($employeeId, $startDate, $endDate, $standardHours = 173.33) {
        $workedData = $this->calculateWorkedHours($employeeId, $startDate, $endDate);
        $overtimeHours = max(0, $workedData['total_hours'] - $standardHours);
        
        return [
            'overtime_hours' => round($overtimeHours, 2),
            'standard_hours' => $standardHours,
            'total_worked' => $workedData['total_hours']
        ];
    }
    
    public function getLateDays($employeeId, $startDate, $endDate) {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as late_days
                FROM presences p
                INNER JOIN employes e ON p.employe_id = e.id
                WHERE p.employe_id = ?
                AND DATE(p.heure_arrivee) BETWEEN ? AND ?
                AND TIME(p.heure_arrivee) > e.heure_debut
            ");
            $stmt->execute([$employeeId, $startDate, $endDate]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['late_days'] ?? 0);
        } catch (PDOException $e) {
            error_log("Erreur getLateDays: " . $e->getMessage());
            return 0;
        }
    }
    
    public function getAbsenceDays($employeeId, $startDate, $endDate) {
        try {
            // Calculer les jours ouvrables dans la période
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $workingDays = 0;
            
            while ($start <= $end) {
                $dayOfWeek = (int) $start->format('N'); // 1 = Lundi, 7 = Dimanche
                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Lundi à Vendredi
                    $workingDays++;
                }
                $start->add(new DateInterval('P1D'));
            }
            
            // Calculer les jours de présence
            $stmt = $this->conn->prepare("
                SELECT COUNT(DISTINCT DATE(p.heure_arrivee)) as present_days
                FROM presences p
                WHERE p.employe_id = ?
                AND DATE(p.heure_arrivee) BETWEEN ? AND ?
                AND p.heure_arrivee IS NOT NULL
            ");
            $stmt->execute([$employeeId, $startDate, $endDate]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $presentDays = (int) ($result['present_days'] ?? 0);
            
            return max(0, $workingDays - $presentDays);
        } catch (Exception $e) {
            error_log("Erreur getAbsenceDays: " . $e->getMessage());
            return 0;
        }
    }
    
    public function getPresenceStatistics($employeeId, $startDate, $endDate) {
        $workedData = $this->calculateWorkedHours($employeeId, $startDate, $endDate);
        $overtimeData = $this->getOvertimeHours($employeeId, $startDate, $endDate);
        $lateDays = $this->getLateDays($employeeId, $startDate, $endDate);
        $absenceDays = $this->getAbsenceDays($employeeId, $startDate, $endDate);
        
        return [
            'worked_hours' => $workedData['total_hours'],
            'working_days' => $workedData['working_days'],
            'overtime_hours' => $overtimeData['overtime_hours'],
            'late_days' => $lateDays,
            'absence_days' => $absenceDays,
            'attendance_rate' => $workedData['working_days'] > 0 ? 
                round(($workedData['working_days'] / ($workedData['working_days'] + $absenceDays)) * 100, 2) : 0,
            'daily_breakdown' => $workedData['daily_breakdown']
        ];
    }
}
?>