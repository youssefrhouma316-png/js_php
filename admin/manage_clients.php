<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$message = "";

function ensureClientColumns(PDO $db): void
{
    $columns = [
        'profession' => "ALTER TABLE users ADD COLUMN profession VARCHAR(120) DEFAULT NULL AFTER telephone",
        'entreprise' => "ALTER TABLE users ADD COLUMN entreprise VARCHAR(120) DEFAULT NULL AFTER profession",
        'objectif_usage' => "ALTER TABLE users ADD COLUMN objectif_usage VARCHAR(160) DEFAULT NULL AFTER entreprise",
        'adresse' => "ALTER TABLE users ADD COLUMN adresse VARCHAR(255) DEFAULT NULL AFTER objectif_usage",
        'photo' => "ALTER TABLE users ADD COLUMN photo VARCHAR(255) DEFAULT NULL AFTER adresse",
    ];

    $stmt = $db->query("SHOW COLUMNS FROM users");
    $existing = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    foreach ($columns as $column => $sql) {
        if (!in_array($column, $existing, true)) {
            $db->exec($sql);
        }
    }
}

ensureClientColumns($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_client'])) {
    $clientId = (int)($_POST['client_id'] ?? 0);
    if ($clientId > 0) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
        $stmt->execute([$clientId]);
        $message = "Client supprime avec succes.";
    }
}

$clients = $db->query(
    "SELECT u.*,
            COUNT(r.id) AS total_reservations,
            COALESCE(SUM(r.prix_total), 0) AS total_depense
     FROM users u
     LEFT JOIN reservations r ON r.user_id = u.id
     WHERE u.role = 'user'
     GROUP BY u.id
     ORDER BY u.created_at DESC"
)->fetchAll();

include '../includes/header.php';
?>

<main class="container section">
    <div class="flex-between align-center mb-3">
        <div>
            <span class="tag">Administration</span>
            <h1>Gestion des clients</h1>
        </div>
        <a href="dashboard.php" class="btn btn-outline">Retour dashboard</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <section class="card admin-section">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Profil</th>
                        <th>Reservations</th>
                        <th>Depense</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="6" class="text-center">Aucun client trouve.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></strong><br>
                                <small class="text-muted">Inscrit le <?= htmlspecialchars($client['created_at']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($client['email']) ?><br>
                                <small class="text-muted"><?= htmlspecialchars($client['telephone'] ?? 'Telephone non renseigne') ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($client['profession'] ?? 'Profil non renseigne') ?><br>
                                <small class="text-muted"><?= htmlspecialchars($client['entreprise'] ?? '') ?></small>
                            </td>
                            <td><?= (int)$client['total_reservations'] ?></td>
                            <td class="text-success"><?= number_format((float)$client['total_depense'], 2) ?> DT</td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Supprimer ce client et ses reservations ?');">
                                    <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">
                                    <button type="submit" name="delete_client" class="btn btn-danger btn-sm">Supprimer</button>
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
