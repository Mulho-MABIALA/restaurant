<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Données POST reçues :</h2><pre>";
    print_r($_POST);
    echo "</pre>";

    echo "<h2>Données FILES reçues :</h2><pre>";
    print_r($_FILES);
    echo "</pre>";
} else {
?>
<form action="" method="post" enctype="multipart/form-data">
    <label>Nom du plat :</label><br>
    <input type="text" name="nom" placeholder="Nom du plat" required><br><br>

    <label>Prix (FCFA) :</label><br>
    <input type="number" name="prix" placeholder="Prix" min="0" step="0.01" required><br><br>

    <label>Catégorie :</label><br>
    <select name="categorie_id" required>
        <option value="">-- Sélectionnez --</option>
        <option value="1">Boissons</option>
        <option value="2">Plats</option>
    </select><br><br>

    <label>Image :</label><br>
    <input type="file" name="image" accept="image/*" required><br><br>

    <button type="submit">Envoyer</button>
</form>
<?php } ?>
