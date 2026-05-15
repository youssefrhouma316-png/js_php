<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $reservationId = (int)($_POST['reservation_id'] ?? 0);
    $status = $_POST['statut'] ?? '';
    $allowedStatuses = ['en_attente', 'confirmee', 'annulee'];

    if ($reservationId > 0 && in_array($status, $allowedStatuses, true)) {
        $stmt = $db->prepare("UPDATE reservations SET statut = ? WHERE id = ?");
        $stmt->execute([$status, $reservationId]);
        $message = "Statut de reservation mis a jour.";
    }
}

$reservations = $db->query(
    "SELECT r.*, u.nom AS client_nom, u.prenom AS client_prenom, u.email AS client_email,
            u.telephone AS client_telephone, p.nom AS pod_nom
     FROM reservations r
     JOIN users u ON u.id = r.user_id
     JOIN pods p ON p.id = r.pod_id
     ORDER BY r.date_resa DESC, r.heure_debut DESC"
)->fetchAll();

include '../includes/header.php';
?>

<main class="container section">
    <div class="flex-between align-center mb-3">
        <div>
            <span class="tag">Administration</span>
            <h1>Gestion des reservations</h1>
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
                        <th>Pod</th>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Duree</th>
                        <th>Prix</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reservations)): ?>
                        <tr><td colspan="7" class="text-center">Aucune reservation trouvee.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($reservation['client_prenom'] . ' ' . $reservation['client_nom']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($reservation['client_email']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($reservation['pod_nom']) ?></td>
                            <td><?= htmlspecialchars($reservation['date_resa']) ?></td>
                            <td><?= htmlspecialchars(substr($reservation['heure_debut'], 0, 5)) ?></td>
                            <td><?= htmlspecialchars($reservation['duree_heures']) ?>h</td>
                            <td class="text-success"><?= htmlspecialchars($reservation['prix_total']) ?> DT</td>
                            <td>
                                <form method="POST" class="inline-status-form">
                                    <input type="hidden" name="reservation_id" value="<?= (int)$reservation['id'] ?>">
                                    <select name="statut" class="form-control">
                                        <option value="en_attente" <?= $reservation['statut'] === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                        <option value="confirmee" <?= $reservation['statut'] === 'confirmee' ? 'selected' : '' ?>>Confirmee</option>
                                        <option value="annulee" <?= $reservation['statut'] === 'annulee' ? 'selected' : '' ?>>Annulee</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-primary btn-sm">OK</button>
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
