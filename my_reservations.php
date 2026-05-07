<?php
require_once 'config/db.php';
require_once 'includes/security.php';

ensure_session();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = getDB();
$message = "";

if (isset($_GET['success'])) {
    $message = "Réservation confirmée avec succès.";
}

$stmt = $db->prepare("
    SELECT r.*, p.nom AS pod_nom
    FROM reservations r
    JOIN pods p ON p.id = r.pod_id
    WHERE r.user_id = ?
    ORDER BY r.date_resa DESC, r.heure_debut DESC
");
$stmt->execute([$_SESSION['user_id']]);
$reservations = $stmt->fetchAll();

include 'includes/header.php';
?>

<main class="container section">
    <div class="flex-between align-center mb-3">
        <h1>Mes réservations</h1>
        <a href="reserve.php" class="btn btn-primary">Nouvelle réservation</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success" data-auto-dismiss><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <section class="card">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Pod</th>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Durée</th>
                    <th>Statut</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reservations)): ?>
                    <tr>
                        <td colspan="6" class="text-center">Aucune réservation pour le moment.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($reservations as $reservation): ?>
                    <tr>
                        <td><?= htmlspecialchars($reservation['pod_nom']) ?></td>
                        <td><?= htmlspecialchars($reservation['date_resa']) ?></td>
                        <td><?= htmlspecialchars(substr($reservation['heure_debut'], 0, 5)) ?></td>
                        <td><?= htmlspecialchars($reservation['duree_heures']) ?>h</td>
                        <td><?= htmlspecialchars($reservation['statut']) ?></td>
                        <td class="text-success"><?= htmlspecialchars($reservation['prix_total']) ?> DT</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
