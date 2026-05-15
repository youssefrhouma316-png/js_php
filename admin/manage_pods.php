<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$message = "";
$error = "";

function uploadPodImage(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException("L'upload d'image est obligatoire.");
    }

    if ($file['size'] > 4 * 1024 * 1024) {
        throw new RuntimeException("L'image doit faire moins de 4 Mo.");
    }

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException("Formats acceptes : JPG, PNG ou WebP.");
    }

    $fileName = 'pod_' . bin2hex(random_bytes(8)) . '.' . $allowedMimeTypes[$mimeType];
    $destination = __DIR__ . '/../assets/pics/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException("Impossible d'enregistrer l'image du pod.");
    }

    return $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_pod'])) {
            $nom = trim($_POST['nom'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $capacite = max(1, (int)($_POST['capacite'] ?? 1));
            $prix = (float)($_POST['prix_heure'] ?? 0);
            $equipements = trim($_POST['equipements'] ?? '');
            $statut = $_POST['statut'] ?? 'disponible';

            if ($nom === '' || $prix <= 0) {
                throw new RuntimeException("Le nom et le prix du pod sont obligatoires.");
            }

            $image = uploadPodImage($_FILES['image'] ?? []);
            $stmt = $db->prepare(
                "INSERT INTO pods (nom, description, capacite, prix_heure, equipements, image, statut)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$nom, $desc ?: null, $capacite, $prix, $equipements ?: null, $image, $statut]);
            $message = "Pod ajoute avec succes.";
        }

        if (isset($_POST['update_pod'])) {
            $podId = (int)($_POST['pod_id'] ?? 0);
            $nom = trim($_POST['nom'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $capacite = max(1, (int)($_POST['capacite'] ?? 1));
            $prix = (float)($_POST['prix_heure'] ?? 0);
            $equipements = trim($_POST['equipements'] ?? '');
            $statut = $_POST['statut'] ?? 'disponible';

            if ($podId <= 0 || $nom === '' || $prix <= 0) {
                throw new RuntimeException("Informations du pod invalides.");
            }

            $stmt = $db->prepare(
                "UPDATE pods
                 SET nom = ?, description = ?, capacite = ?, prix_heure = ?, equipements = ?, statut = ?
                 WHERE id = ?"
            );
            $stmt->execute([$nom, $desc ?: null, $capacite, $prix, $equipements ?: null, $statut, $podId]);
            $message = "Pod mis a jour avec succes.";
        }

        if (isset($_POST['delete_pod'])) {
            $podId = (int)($_POST['pod_id'] ?? 0);
            if ($podId > 0) {
                $stmt = $db->prepare("DELETE FROM pods WHERE id = ?");
                $stmt->execute([$podId]);
                $message = "Pod supprime avec succes.";
            }
        }
    } catch (PDOException $e) {
        $error = "Action impossible : ce pod est peut-etre lie a des reservations.";
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$pods = $db->query("SELECT * FROM pods ORDER BY id DESC")->fetchAll();

include '../includes/header.php';
?>

<main class="container section">
    <div class="flex-between align-center mb-3">
        <div>
            <span class="tag">Administration</span>
            <h1>Gestion des Pods</h1>
        </div>
        <a href="dashboard.php" class="btn btn-outline">Voir statistiques</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="card admin-section mb-3">
        <h2 class="mb-2">Ajouter un pod</h2>
        <form method="POST" enctype="multipart/form-data" class="grid-form">
            <div class="form-group">
                <label for="nom">Nom du Pod</label>
                <input type="text" name="nom" id="nom" required class="form-control">
            </div>
            <div class="form-group">
                <label for="prix_heure">Prix par heure (DT)</label>
                <input type="number" step="0.01" min="1" name="prix_heure" id="prix_heure" required class="form-control">
            </div>
            <div class="form-group">
                <label for="capacite">Capacite</label>
                <input type="number" min="1" max="12" name="capacite" id="capacite" value="1" required class="form-control">
            </div>
            <div class="form-group">
                <label for="statut">Statut</label>
                <select name="statut" id="statut" class="form-control">
                    <option value="disponible">Disponible</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="inactif">Inactif</option>
                </select>
            </div>
            <div class="form-group form-wide">
                <label for="description">Description</label>
                <textarea name="description" id="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group form-wide">
                <label for="equipements">Equipements</label>
                <input type="text" name="equipements" id="equipements" class="form-control" placeholder="Wi-Fi, ecran, climatisation...">
            </div>
            <div class="form-group">
                <label for="pod-image">Image du Pod *</label>
                <input type="file" name="image" id="pod-image" accept="image/jpeg,image/png,image/webp" required class="form-control">
            </div>
            <button type="submit" name="add_pod" class="btn btn-primary">Ajouter le Pod</button>
        </form>
    </section>

    <section class="card admin-section">
        <h2 class="mb-2">Pods existants</h2>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Informations</th>
                        <th>Capacite</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th>Modifier</th>
                        <th>Supprimer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pods as $pod): ?>
                    <tr>
                        <td><img src="../assets/pics/<?= htmlspecialchars($pod['image'] ?? 'default-pod.jpg') ?>" width="68" height="52" alt=""></td>
                        <td>
                            <strong><?= htmlspecialchars($pod['nom']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($pod['description'] ?? '') ?></small>
                        </td>
                        <td><?= (int)$pod['capacite'] ?></td>
                        <td><?= number_format((float)$pod['prix_heure'], 2) ?> DT</td>
                        <td><span class="pill pill-confirmed"><?= htmlspecialchars($pod['statut']) ?></span></td>
                        <td>
                            <details class="admin-details">
                                <summary class="btn btn-outline btn-sm">Modifier</summary>
                                <form method="POST" class="edit-pod-form">
                                    <input type="hidden" name="pod_id" value="<?= (int)$pod['id'] ?>">
                                    <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($pod['nom']) ?>" required>
                                    <input type="number" step="0.01" min="1" name="prix_heure" class="form-control" value="<?= htmlspecialchars($pod['prix_heure']) ?>" required>
                                    <input type="number" min="1" max="12" name="capacite" class="form-control" value="<?= (int)$pod['capacite'] ?>" required>
                                    <select name="statut" class="form-control">
                                        <option value="disponible" <?= $pod['statut'] === 'disponible' ? 'selected' : '' ?>>Disponible</option>
                                        <option value="maintenance" <?= $pod['statut'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                        <option value="inactif" <?= $pod['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                                    </select>
                                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($pod['description'] ?? '') ?></textarea>
                                    <input type="text" name="equipements" class="form-control" value="<?= htmlspecialchars($pod['equipements'] ?? '') ?>">
                                    <button type="submit" name="update_pod" class="btn btn-primary btn-sm">Enregistrer</button>
                                </form>
                            </details>
                        </td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Supprimer ce pod ?');">
                                <input type="hidden" name="pod_id" value="<?= (int)$pod['id'] ?>">
                                <button type="submit" name="delete_pod" class="btn btn-danger btn-sm">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php include '../includes/footer.php'; ?>
