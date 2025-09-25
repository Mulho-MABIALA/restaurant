<?php
class SecurityManager {
    public static function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Required check
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = "Le champ {$field} est requis";
                continue;
            }
            
            // Skip other validations if field is empty and not required
            if (empty($value)) {
                continue;
            }
            
            // Type validation
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "Format email invalide";
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[$field] = "Valeur numérique requise";
                        }
                        break;
                    case 'date':
                        if (!strtotime($value)) {
                            $errors[$field] = "Format de date invalide";
                        }
                        break;
                }
            }
            
            // Min/Max validation
            if (isset($rule['min']) && strlen($value) < $rule['min']) {
                $errors[$field] = "Minimum {$rule['min']} caractères requis";
            }
            if (isset($rule['max']) && strlen($value) > $rule['max']) {
                $errors[$field] = "Maximum {$rule['max']} caractères autorisés";
            }
        }
        
        return $errors;
    }
    
    public static function sanitizeOutput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeOutput'], $data);
        }
        
        // Gestion des valeurs null pour PHP 8.1+
        if ($data === null) {
            return '';
        }
        
        return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
    }
    
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function cleanInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'cleanInput'], $input);
        }
        
        if ($input === null) {
            return '';
        }
        
        return trim(strip_tags((string)$input));
    }
}
?>