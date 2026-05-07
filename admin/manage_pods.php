<?php
require_once '../config/db.php';
require_once '../includes/security.php';

ensure_session();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$message = "";
$messageType = "info";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $message = "Session expirée. Rechargez la page puis réessayez.";
        $messageType = "danger";
    } elseif (isset($_POST['delete_pod'])) {
        $podId = filter_input(INPUT_POST, 'pod_id', FILTER_VALIDATE_INT);

        if (!$podId) {
            $message = "Pod invalide.";
            $messageType = "danger";
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM reservations WHERE pod_id = ?");
            $stmt->execute([$podId]);
            $reservationCount = (int) $stmt->fetchColumn();

            if ($reservationCount > 0) {
                $stmt = $db->prepare("UPDATE pods SET statut = 'inactif' WHERE id = ?");
                $stmt->execute([$podId]);
                $message = "Ce pod a des réservations. Il a été désactivé au lieu d'être supprimé.";
            } else {
                $stmt = $db->prepare("SELECT image FROM pods WHERE id = ?");
                $stmt->execute([$podId]);
                $image = $stmt->fetchColumn();

                $stmt = $db->prepare("DELETE FROM pods WHERE id = ?");
                $stmt->execute([$podId]);

                $imagePath = $image ? realpath(__DIR__ . '/../assets/pics/' . $image) : false;
                $picsDir = realpath(__DIR__ . '/../assets/pics');
                $isGeneratedUpload = is_string($image) && preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $image);
                if ($isGeneratedUpload && $imagePath && $picsDir && str_starts_with($imagePath, $picsDir) && is_file($imagePath)) {
                    @unlink($imagePath);
                }

                $message = "Pod supprimé avec succès.";
            }
        }
    } elseif (isset($_POST['add_pod'])) {
        $nom = trim($_POST['nom'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $prix = filter_input(INPUT_POST, 'prix_heure', FILTER_VALIDATE_FLOAT);

        if ($nom === '' || $prix === false || $prix <= 0) {
            $message = "Le nom et le prix doivent être valides.";
            $messageType = "danger";
        } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $message = "L'upload d'image est obligatoire.";
            $messageType = "danger";
        } else {
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            $tmpPath = $_FILES['image']['tmp_name'];
            $mimeType = mime_content_type($tmpPath);
            $fileSize = (int) $_FILES['image']['size'];

            if (!isset($allowedTypes[$mimeType])) {
                $message = "Format d'image refusé. Utilisez JPG, PNG ou WebP.";
                $messageType = "danger";
            } elseif ($fileSize > 5 * 1024 * 1024) {
                $message = "Image trop grande. Taille maximum: 5 Mo.";
                $messageType = "danger";
            } else {
                $fileName = bin2hex(random_bytes(12)) . '.' . $allowedTypes[$mimeType];
                $destination = __DIR__ . '/../assets/pics/' . $fileName;

                if (move_uploaded_file($tmpPath, $destination)) {
                    $stmt = $db->prepare("INSERT INTO pods (nom, description, prix_heure, image) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nom, $desc, $prix, $fileName]);
                    $message = "Pod ajouté avec succès.";
                } else {
                    $message = "Impossible d'enregistrer l'image.";
                    $messageType = "danger";
                }
            }
        }
    }
}

$pods = $db->query("SELECT * FROM pods ORDER BY id DESC")->fetchAll();

include '../includes/header.php';
?>

<main class="container section">
    <div class="flex-between align-center mb-3">
        <h1>Gestion des Pods</h1>
        <a href="dashboard.php" class="btn btn-outline">Voir statistiques</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <section class="card mb-3">
        <form method="POST" enctype="multipart/form-data" class="grid-form">
            <?= csrf_field() ?>
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
                <label>Image du Pod</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="form-control">
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
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pods as $pod): ?>
            <tr>
                <td><img src="../assets/pics/<?= htmlspecialchars($pod['image'] ?? 'default-pod.jpg') ?>" width="60" alt="<?= htmlspecialchars($pod['nom']) ?>"></td>
                <td><?= htmlspecialchars($pod['nom']) ?></td>
                <td><?= htmlspecialchars($pod['prix_heure']) ?> DT</td>
                <td><?= htmlspecialchars($pod['statut']) ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="pod_id" value="<?= (int) $pod['id'] ?>">
                        <button type="submit" name="delete_pod" class="btn text-danger" data-confirm="Supprimer ce pod ?">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>

<?php include '../includes/footer.php'; ?>
