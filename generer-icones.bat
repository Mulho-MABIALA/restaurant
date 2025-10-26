@echo off
chcp 65001 >nul
cls

echo ═══════════════════════════════════════════════════════════
echo 🎨 GÉNÉRATEUR D'ICÔNES PWA - Restaurant Mulho
echo ═══════════════════════════════════════════════════════════
echo.

echo 📋 Ce script va vous aider à générer les icônes PWA
echo.

echo ┌─────────────────────────────────────────────────────────┐
echo │ OPTION 1: Générateur en ligne (RECOMMANDÉ)             │
echo └─────────────────────────────────────────────────────────┘
echo.
echo ✅ Méthode la plus simple et rapide (2 minutes)
echo.
echo 1. Ouvrez ce site dans votre navigateur:
echo    https://realfavicongenerator.net/
echo.
echo 2. Uploadez votre logo:
echo    %~dp0public\assets\img\logo.jpg
echo.
echo 3. Cliquez "Generate your Favicons and HTML code"
echo.
echo 4. Téléchargez le package
echo.
echo 5. Extrayez les fichiers PNG dans:
echo    %~dp0public\assets\img\icons\
echo.

set /p choice1="Voulez-vous ouvrir le site maintenant? (O/N): "
if /i "%choice1%"=="O" (
    echo.
    echo 🌐 Ouverture de realfavicongenerator.net...
    start https://realfavicongenerator.net/
    echo.
    echo 📂 Ouverture du dossier du logo...
    explorer "%~dp0public\assets\img"
    echo.
    echo ✅ Quand vous aurez téléchargé les icônes:
    echo    1. Extrayez le ZIP
    echo    2. Copiez les fichiers .PNG dans:
    echo       %~dp0public\assets\img\icons\
    echo.
)

echo.
echo ┌─────────────────────────────────────────────────────────┐
echo │ OPTION 2: Générateur HTML local                        │
echo └─────────────────────────────────────────────────────────┘
echo.
echo ✅ Générez les icônes directement dans votre navigateur
echo.

set /p choice2="Voulez-vous ouvrir le générateur HTML? (O/N): "
if /i "%choice2%"=="O" (
    echo.
    echo 🌐 Ouverture du générateur d'icônes...
    start http://localhost/restaurant/public/generer-icones.html
    echo.
    echo 📖 Instructions:
    echo    1. Cliquez sur "Utiliser le logo par défaut"
    echo    2. Cliquez sur "Générer toutes les icônes PWA"
    echo    3. Téléchargez chaque icône
    echo    4. Placez-les dans: public\assets\img\icons\
    echo.
)

echo.
echo ┌─────────────────────────────────────────────────────────┐
echo │ OPTION 3: Vérifier l'installation                      │
echo └─────────────────────────────────────────────────────────┘
echo.

set /p choice3="Voulez-vous tester si les icônes sont installées? (O/N): "
if /i "%choice3%"=="O" (
    echo.
    echo 🔍 Vérification des icônes...
    echo.

    set icon_count=0

    if exist "%~dp0public\assets\img\icons\icon-72x72.png" (
        echo ✅ icon-72x72.png trouvée
        set /a icon_count+=1
    ) else (
        echo ❌ icon-72x72.png manquante
    )

    if exist "%~dp0public\assets\img\icons\icon-96x96.png" (
        echo ✅ icon-96x96.png trouvée
        set /a icon_count+=1
    ) else (
        echo ❌ icon-96x96.png manquante
    )

    if exist "%~dp0public\assets\img\icons\icon-128x128.png" (
        echo ✅ icon-128x128.png trouvée
        set /a icon_count+=1
    ) else (
        echo ❌ icon-128x128.png manquante
    )

    if exist "%~dp0public\assets\img\icons\icon-144x144.png" (
        echo ✅ icon-144x144.png trouvée
        set /a icon_count+=1
    ) else (
        echo ❌ icon-144x144.png manquante
    )

    if exist "%~dp0public\assets\img\icons\icon-152x152.png" (
        echo ✅ icon-152x152.png trouvée
        set /a icon_count+=1
    ) else (
        echo ❌ icon-152x152.png manquante
    )

    if exist "%~dp0public\assets\img\icons\icon-192x192.png" (
        echo ✅ icon-192x192.png trouvée ⭐ OBLIGATOIRE
        set /a icon_count+=1
    ) else (
        echo ❌ icon-192x192.png manquante ⭐ OBLIGATOIRE
    )

    if exist "%~dp0public\assets\img\icons\icon-384x384.png" (
        echo ✅ icon-384x384.png trouvée
        set /a icon_count+=1
    ) else (
        echo ❌ icon-384x384.png manquante
    )

    if exist "%~dp0public\assets\img\icons\icon-512x512.png" (
        echo ✅ icon-512x512.png trouvée ⭐ OBLIGATOIRE
        set /a icon_count+=1
    ) else (
        echo ❌ icon-512x512.png manquante ⭐ OBLIGATOIRE
    )

    echo.
    echo ═══════════════════════════════════════════════════════════
    echo 📊 RÉSULTAT: %icon_count%/8 icônes trouvées
    echo ═══════════════════════════════════════════════════════════
    echo.

    if %icon_count% GEQ 2 (
        if exist "%~dp0public\assets\img\icons\icon-192x192.png" (
            if exist "%~dp0public\assets\img\icons\icon-512x512.png" (
                echo ✅ MINIMUM VIABLE: Les 2 icônes obligatoires sont présentes!
                echo    Votre PWA peut être installée.
                echo.
                set /p test="Voulez-vous tester la PWA maintenant? (O/N): "
                if /i "!test!"=="O" (
                    start http://localhost/restaurant/public/pwa-setup.html
                )
            ) else (
                echo ⚠️  Il manque l'icône 512x512 (obligatoire)
            )
        ) else (
            echo ⚠️  Il manque l'icône 192x192 (obligatoire)
        )
    ) else (
        echo ❌ AUCUNE icône trouvée. Utilisez l'Option 1 ou 2 ci-dessus.
    )
)

echo.
echo ┌─────────────────────────────────────────────────────────┐
echo │ DOCUMENTATION                                           │
echo └─────────────────────────────────────────────────────────┘
echo.
echo 📖 Guides disponibles:
echo    - PWA_QUICKSTART.md (démarrage rapide)
echo    - PWA_INSTALLATION.md (guide complet)
echo    - PWA_STATUS.md (statut d'installation)
echo.

set /p open_doc="Voulez-vous ouvrir le guide de démarrage rapide? (O/N): "
if /i "%open_doc%"=="O" (
    start notepad "%~dp0PWA_QUICKSTART.md"
)

echo.
echo ═══════════════════════════════════════════════════════════
echo ✅ Terminé!
echo ═══════════════════════════════════════════════════════════
echo.
echo Prochaines étapes:
echo   1. Générer les icônes (Option 1 ou 2)
echo   2. Les placer dans: public\assets\img\icons\
echo   3. Tester sur: http://localhost/restaurant/public/pwa-setup.html
echo.

pause
