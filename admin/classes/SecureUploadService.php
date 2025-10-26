<?php
/**
 * Service d'upload sécurisé pour les fichiers
 * Gère validation, nettoyage et stockage des images
 */

class SecureUploadService {
    private $uploadDir;
    private $allowedMimeTypes;
    private $allowedExtensions;
    private $maxFileSize;

    /**
     * Constructeur
     *
     * @param string $uploadDir Répertoire de destination
     * @param int $maxFileSize Taille max en octets (défaut: 2MB)
     */
    public function __construct($uploadDir = '../uploads/', $maxFileSize = 2097152) {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->maxFileSize = $maxFileSize;

        // Types MIME autorisés
        $this->allowedMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        // Extensions autorisées
        $this->allowedExtensions = [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp'
        ];

        // Créer le répertoire si nécessaire
        $this->ensureDirectoryExists();
    }

    /**
     * Upload une image de manière sécurisée
     *
     * @param array $file Le fichier $_FILES['name']
     * @param string $subDir Sous-répertoire optionnel
     * @return array ['success' => bool, 'filename' => string|null, 'message' => string]
     */
    public function uploadImage($file, $subDir = '') {
        // Validation initiale
        $validation = $this->validateUpload($file);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'filename' => null,
                'message' => $validation['error']
            ];
        }

        // Validation du type MIME
        $mimeValidation = $this->validateMimeType($file['tmp_name']);
        if (!$mimeValidation['valid']) {
            return [
                'success' => false,
                'filename' => null,
                'message' => $mimeValidation['error']
            ];
        }

        // Validation de l'extension
        $extensionValidation = $this->validateExtension($file['name']);
        if (!$extensionValidation['valid']) {
            return [
                'success' => false,
                'filename' => null,
                'message' => $extensionValidation['error']
            ];
        }

        // Validation de l'image (vérification que c'est vraiment une image)
        $imageValidation = $this->validateImageContent($file['tmp_name']);
        if (!$imageValidation['valid']) {
            return [
                'success' => false,
                'filename' => null,
                'message' => $imageValidation['error']
            ];
        }

        // Générer un nom de fichier sécurisé
        $extension = $extensionValidation['extension'];
        $filename = $this->generateSecureFilename($extension);

        // Déterminer le chemin de destination
        $destination = $this->uploadDir;
        if (!empty($subDir)) {
            $destination .= rtrim($subDir, '/') . '/';
            $this->ensureDirectoryExists($destination);
        }

        $fullPath = $destination . $filename;

        // Déplacer le fichier
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            return [
                'success' => false,
                'filename' => null,
                'message' => 'Erreur lors du déplacement du fichier'
            ];
        }

        // Définir les permissions appropriées
        chmod($fullPath, 0644);

        // Optimiser l'image (optionnel)
        $this->optimizeImage($fullPath, $mimeValidation['mime']);

        return [
            'success' => true,
            'filename' => $filename,
            'message' => 'Fichier uploadé avec succès',
            'path' => $fullPath
        ];
    }

    /**
     * Valide le upload initial
     *
     * @param array $file
     * @return array
     */
    private function validateUpload($file) {
        // Vérifier que le fichier existe
        if (!isset($file) || !is_array($file)) {
            return ['valid' => false, 'error' => 'Aucun fichier fourni'];
        }

        // Vérifier les erreurs d'upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => $this->getUploadErrorMessage($file['error'])];
        }

        // Vérifier la taille
        if ($file['size'] > $this->maxFileSize) {
            $maxMB = round($this->maxFileSize / 1048576, 2);
            return ['valid' => false, 'error' => "Fichier trop volumineux (max: {$maxMB}MB)"];
        }

        // Vérifier que c'est bien un fichier uploadé
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Fichier non valide'];
        }

        return ['valid' => true];
    }

    /**
     * Valide le type MIME du fichier
     *
     * @param string $tmpPath
     * @return array
     */
    private function validateMimeType($tmpPath) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return [
                'valid' => false,
                'error' => 'Type de fichier non autorisé (uniquement images JPG, PNG, GIF, WebP)'
            ];
        }

        return ['valid' => true, 'mime' => $mimeType];
    }

    /**
     * Valide l'extension du fichier
     *
     * @param string $filename
     * @return array
     */
    private function validateExtension($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($extension, $this->allowedExtensions)) {
            return [
                'valid' => false,
                'error' => 'Extension non autorisée'
            ];
        }

        return ['valid' => true, 'extension' => $extension];
    }

    /**
     * Valide que le fichier est vraiment une image
     *
     * @param string $tmpPath
     * @return array
     */
    private function validateImageContent($tmpPath) {
        $imageInfo = @getimagesize($tmpPath);

        if ($imageInfo === false) {
            return [
                'valid' => false,
                'error' => 'Le fichier n\'est pas une image valide'
            ];
        }

        // Vérifier les dimensions (optionnel)
        $maxWidth = 5000;
        $maxHeight = 5000;

        if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
            return [
                'valid' => false,
                'error' => "Dimensions trop grandes (max: {$maxWidth}x{$maxHeight}px)"
            ];
        }

        return ['valid' => true, 'width' => $imageInfo[0], 'height' => $imageInfo[1]];
    }

    /**
     * Génère un nom de fichier sécurisé et unique
     *
     * @param string $extension
     * @return string
     */
    private function generateSecureFilename($extension) {
        // Utiliser un nom complètement aléatoire
        $randomName = bin2hex(random_bytes(16));

        // Ajouter un timestamp pour garantir l'unicité
        $timestamp = time();

        return "{$timestamp}_{$randomName}.{$extension}";
    }

    /**
     * Optimise une image uploadée
     *
     * @param string $path
     * @param string $mimeType
     * @return bool
     */
    private function optimizeImage($path, $mimeType) {
        // Charger l'image selon son type
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = @imagecreatefromjpeg($path);
                break;

            case 'image/png':
                $image = @imagecreatefrompng($path);
                break;

            case 'image/gif':
                $image = @imagecreatefromgif($path);
                break;

            case 'image/webp':
                $image = @imagecreatefromwebp($path);
                break;

            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        // Redimensionner si trop grande (optionnel)
        $maxDimension = 1920;
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > $maxDimension || $height > $maxDimension) {
            $ratio = $width / $height;

            if ($width > $height) {
                $newWidth = $maxDimension;
                $newHeight = $maxDimension / $ratio;
            } else {
                $newHeight = $maxDimension;
                $newWidth = $maxDimension * $ratio;
            }

            $resized = imagecreatetruecolor($newWidth, $newHeight);

            // Préserver la transparence pour PNG et GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            }

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        // Sauvegarder l'image optimisée
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                imagejpeg($image, $path, 85); // Qualité 85%
                break;

            case 'image/png':
                imagepng($image, $path, 8); // Compression niveau 8
                break;

            case 'image/gif':
                imagegif($image, $path);
                break;

            case 'image/webp':
                imagewebp($image, $path, 85);
                break;
        }

        imagedestroy($image);

        return true;
    }

    /**
     * Supprime un fichier uploadé
     *
     * @param string $filename
     * @param string $subDir
     * @return bool
     */
    public function deleteFile($filename, $subDir = '') {
        $destination = $this->uploadDir;
        if (!empty($subDir)) {
            $destination .= rtrim($subDir, '/') . '/';
        }

        $fullPath = $destination . $filename;

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    /**
     * Assure que le répertoire existe
     *
     * @param string|null $dir
     */
    private function ensureDirectoryExists($dir = null) {
        $targetDir = $dir ?? $this->uploadDir;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
    }

    /**
     * Obtient le message d'erreur d'upload
     *
     * @param int $errorCode
     * @return string
     */
    private function getUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la limite du serveur',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la limite du formulaire',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture sur le disque',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a stoppé l\'upload'
        ];

        return $errors[$errorCode] ?? 'Erreur inconnue lors de l\'upload';
    }

    /**
     * Vérifie si un fichier existe
     *
     * @param string $filename
     * @param string $subDir
     * @return bool
     */
    public function fileExists($filename, $subDir = '') {
        $destination = $this->uploadDir;
        if (!empty($subDir)) {
            $destination .= rtrim($subDir, '/') . '/';
        }

        return file_exists($destination . $filename);
    }

    /**
     * Obtient les informations d'un fichier
     *
     * @param string $filename
     * @param string $subDir
     * @return array|null
     */
    public function getFileInfo($filename, $subDir = '') {
        $destination = $this->uploadDir;
        if (!empty($subDir)) {
            $destination .= rtrim($subDir, '/') . '/';
        }

        $fullPath = $destination . $filename;

        if (!file_exists($fullPath)) {
            return null;
        }

        $imageInfo = @getimagesize($fullPath);

        return [
            'filename' => $filename,
            'path' => $fullPath,
            'size' => filesize($fullPath),
            'mime' => $imageInfo ? $imageInfo['mime'] : null,
            'width' => $imageInfo ? $imageInfo[0] : null,
            'height' => $imageInfo ? $imageInfo[1] : null,
            'uploaded_at' => filectime($fullPath)
        ];
    }
}
