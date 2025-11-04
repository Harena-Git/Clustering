<?php
require_once("dbSessionHandler.php");
5
session_start();

$serveur = 'Serveur 2';

if (isset($_POST['couleur']) && !empty($_POST['couleur'])) {
    $_SESSION['couleur_preferee'] = htmlspecialchars($_POST['couleur']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo $serveur; ?></title>
</head>
<body >
    <h1><?php echo $serveur; ?></h1>
    <p><b>ID de session :</b> <?php echo session_id(); ?></p>

    <?php if (!empty($_SESSION['couleur_preferee'])): ?>
        <p>Votre couleur préférée est : <b><?php echo $_SESSION['couleur_preferee']; ?></b></p>
    <?php else: ?>
        <p>Aucune couleur choisie pour l'instant.</p>
    <?php endif; ?>

    <form method="post">
        <label>Entrez votre couleur préférée :</label>
        <input type="text" name="couleur" placeholder="ex: blue ou #ff0000">
        <button type="submit">Enregistrer</button>
    </form>

    <hr>
    <p>Adresse du serveur : <b><?php echo $_SERVER['SERVER_ADDR']; ?></b></p>
</body>
</html>
