<?php
/**
 * Générateur d'icônes PWA temporaires
 * Crée des icônes de base en attendant les vraies icônes
 */

// Créer le dossier si nécessaire
$iconDir = __DIR__ . '/assets/img/icons';
if (!is_dir($iconDir)) {
    mkdir($iconDir, 0755, true);
}

// Tailles d'icônes requises
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];

// Couleur de fond (vert Restaurant Mulho)
$bgColor = [16, 185, 129]; // #10b981

// Générer chaque icône
foreach ($sizes as $size) {
    $image = imagecreatetruecolor($size, $size);

    // Fond vert
    $bg = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
    imagefill($image, 0, 0, $bg);

    // Texte blanc
    $white = imagecolorallocate($image, 255, 255, 255);

    // Calculer la taille de police proportionnelle
    $fontSize = $size / 5;

    // Texte "M" pour Mulho
    $text = 'M';

    // Calculer la position centrée (approximative)
    $x = ($size / 2) - ($fontSize / 1.5);
    $y = ($size / 2) + ($fontSize / 2);

    // Ajouter le texte
    imagestring($image, 5, $x, $y - 10, $text, $white);

    // Ajouter un cercle décoratif
    $circleColor = imagecolorallocate($image, 255, 255, 255);
    imageellipse($image, $size / 2, $size / 2, $size * 0.7, $size * 0.7, $circleColor);

    // Re-dessiner le texte par-dessus
    imagestring($image, 5, $x, $y - 10, $text, $white);

    // Sauvegarder
    $filename = $iconDir . "/icon-{$size}x{$size}.png";
    imagepng($image, $filename);
    imagedestroy($image);

    echo "✅ Icône créée: icon-{$size}x{$size}.png\n";
}

// Générer les splash screens iOS (simplifié - même icône)
$splashSizes = [
    'iphone5_splash' => [640, 1136],
    'iphone6_splash' => [750, 1334],
    'iphoneplus_splash' => [1242, 2208],
    'iphonex_splash' => [1125, 2436],
    'iphonexr_splash' => [828, 1792],
    'iphonexsmax_splash' => [1242, 2688],
    'ipad_splash' => [1536, 2048],
    'ipadpro1_splash' => [1668, 2224],
    'ipadpro2_splash' => [2048, 2732]
];

$splashDir = __DIR__ . '/assets/img/splash';
if (!is_dir($splashDir)) {
    mkdir($splashDir, 0755, true);
}

foreach ($splashSizes as $name => $dimensions) {
    $width = $dimensions[0];
    $height = $dimensions[1];

    $image = imagecreatetruecolor($width, $height);

    // Fond vert
    $bg = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
    imagefill($image, 0, 0, $bg);

    // Blanc pour le logo
    $white = imagecolorallocate($image, 255, 255, 255);

    // Logo centré (cercle + texte)
    $logoSize = min($width, $height) / 3;
    $centerX = $width / 2;
    $centerY = $height / 2;

    // Cercle
    imageellipse($image, $centerX, $centerY, $logoSize, $logoSize, $white);

    // Texte "Mulho" centré
    $text = 'Mulho';
    $fontSize = 5;
    $textWidth = imagefontwidth($fontSize) * strlen($text);
    $textHeight = imagefontheight($fontSize);

    imagestring(
        $image,
        $fontSize,
        $centerX - ($textWidth / 2),
        $centerY - ($textHeight / 2),
        $text,
        $white
    );

    // Sauvegarder
    $filename = $splashDir . "/{$name}.png";
    imagepng($image, $filename);
    imagedestroy($image);

    echo "✅ Splash screen créé: {$name}.png ({$width}x{$height})\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "✅ ICÔNES PWA GÉNÉRÉES AVEC SUCCÈS!\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "\n";
echo "📂 Icônes créées dans: /public/assets/img/icons/\n";
echo "📂 Splash screens créés dans: /public/assets/img/splash/\n";
echo "\n";
echo "⚠️  IMPORTANT: Ce sont des icônes TEMPORAIRES!\n";
echo "   Pour une app professionnelle, remplacez-les avec:\n";
echo "   - Votre vrai logo\n";
echo "   - Haute résolution\n";
echo "   - Design professionnel\n";
echo "\n";
echo "📖 Consultez: /public/assets/img/icons/README.md\n";
echo "   pour les instructions de génération d'icônes professionnelles\n";
echo "\n";
echo "🧪 TEST DE LA PWA:\n";
echo "   1. Ouvrez: http://localhost/restaurant/public/\n";
echo "   2. Ouvrez DevTools (F12)\n";
echo "   3. Application → Manifest (vérifier détecté ✅)\n";
echo "   4. Application → Service Workers (vérifier enregistré ✅)\n";
echo "   5. Attendez 3 secondes → Bannière d'installation apparaît\n";
echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
?>
