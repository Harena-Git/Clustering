<?php
require_once __DIR__ . '/dbMultiPdo.php';

$db = new MultiDb();

// Simple router for actions
$action = $_POST['action'] ?? null;

if ($action === 'create_item') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name !== '') {
        $sql = "INSERT INTO items (name, description) VALUES (?, ?);";
        $db->writeBoth($sql, [$name, $desc]);
    }
    header('Location: manage.php'); exit;
}

if ($action === 'delete_item') {
    $id = intval($_POST['id'] ?? 0);
    if ($id) {
        $sql = "DELETE FROM items WHERE id = ?;";
        $db->writeBoth($sql, [$id]);
    }
    header('Location: manage.php'); exit;
}

if ($action === 'create_server') {
    $name = trim($_POST['name'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    if ($name !== '') {
        $sql = "INSERT INTO servers (name, address, status) VALUES (?, ?, ?);";
        $db->writeBoth($sql, [$name, $addr, 'up']);
    }
    header('Location: manage.php'); exit;
}

if ($action === 'set_server_status') {
    $id = intval($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? 'up');
    if ($id) {
        $sql = "UPDATE servers SET status = ? WHERE id = ?;";
        $db->writeBoth($sql, [$status, $id]);
    }
    header('Location: manage.php'); exit;
}

// Reads
$items = $db->read('SELECT * FROM items ORDER BY id DESC');
$servers = $db->read('SELECT * FROM servers ORDER BY id DESC');

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Manage - Clustering</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;max-width:900px;margin:20px;} table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:8px}</style>
</head>
<body>
    <h1>Interface de gestion (CRUD) — Clustering</h1>

    <h2>Créer un item</h2>
    <form method="post">
        <input type="hidden" name="action" value="create_item">
        <label>Nom: <input name="name"></label>
        <label>Description: <input name="description"></label>
        <button type="submit">Créer</button>
    </form>

    <h2>Items</h2>
    <table>
        <tr><th>ID</th><th>Nom</th><th>Description</th><th>Actions</th></tr>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?=htmlspecialchars($it['id'])?></td>
                <td><?=htmlspecialchars($it['name'])?></td>
                <td><?=htmlspecialchars($it['description'])?></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="id" value="<?=htmlspecialchars($it['id'])?>">
                        <button type="submit">Supprimer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Créer un serveur</h2>
    <form method="post">
        <input type="hidden" name="action" value="create_server">
        <label>Nom: <input name="name"></label>
        <label>Adresse: <input name="address"></label>
        <button type="submit">Créer</button>
    </form>

    <h2>Serveurs</h2>
    <p>Serveurs non fonctionnels (status != 'up')</p>
    <table>
        <tr><th>ID</th><th>Nom</th><th>Adresse</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($servers as $s): ?>
            <tr>
                <td><?=htmlspecialchars($s['id'])?></td>
                <td><?=htmlspecialchars($s['name'])?></td>
                <td><?=htmlspecialchars($s['address'])?></td>
                <td><?=htmlspecialchars($s['status'])?></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="set_server_status">
                        <input type="hidden" name="id" value="<?=htmlspecialchars($s['id'])?>">
                        <select name="status">
                            <option value="up" <?=($s['status']==='up')? 'selected':''?>>up</option>
                            <option value="down" <?=($s['status']==='down')? 'selected':''?>>down</option>
                        </select>
                        <button type="submit">Mettre à jour</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

</body>
</html>
