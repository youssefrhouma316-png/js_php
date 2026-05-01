<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

include '../includes/header.php';

$db = getDB();
$message = "";

// Logique d'ajout d'un Pod (Upload obligatoire)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pod'])) {
    $nom = $_POST['nom'];
    $desc = $_POST['description'];
    $prix = $_POST['prix_heure'];
    
    // Gestion de l'upload d'image
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $fileExt = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('pod_') . '.' . $fileExt;
        $destination = '../assets/pics/' . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            $stmt = $db->prepare("INSERT INTO pods (nom, description, prix_heure, image) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nom, $desc, $prix, $fileName]);
            $message = "Pod ajouté avec succès !";
        }
    } else {
        $message = "L'upload d'image est obligatoire.";
    }
}

$pods = $db->query("SELECT * FROM pods ORDER BY id DESC")->fetchAll();
?>

<main class="container section">
    <div class="flex-between align-center mb-3">
        <h1>Gestion des Pods</h1>
        <a href="dashboard.php" class="btn btn-outline">Voir Statistiques</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <section class="card mb-3">
        <form method="POST" enctype="multipart/form-data" class="grid-form">
            <div class="form-group">
                <label>Nom du Pod</label>
                <input type="text" name="nom" required class="form-control">
            </div>
            <div class="form-group">
                <label>Prix par heure (DT)</label>
                <input type="number" step="0.01" name="prix_heure" required class="form-control">
            </div>
            <div class="form-group" style="grid-column: span 2;">
                <label>Description</label>
                <textarea name="description" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label>Image du Pod (Upload)</label>
                <input type="file" name="image" accept="image/*" required class="form-control">
            </div>
            <button type="submit" name="add_pod" class="btn btn-primary">Ajouter le Pod</button>
        </form>
    </section>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Image</th>
                <th>Nom</th>
                <th>Prix</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pods as $pod): ?>
            <tr>
                <td><img src="../assets/pics/<?= htmlspecialchars($pod['image'] ?? 'default-pod.jpg') ?>" width="60"></td>
                <td><?= htmlspecialchars($pod['nom']) ?></td>
                <td><?= $pod['prix_heure'] ?> DT</td>
                <td>
                    <button class="btn text-danger" data-confirm>Supprimer</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>

<?php include '../includes/footer.php'; ?>