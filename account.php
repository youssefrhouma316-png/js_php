<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$message = "";
$error = "";

function ensureAccountColumns(PDO $db): void
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

ensureAccountColumns($db);

function accountReservationOverlaps(PDO $db, int $podId, string $date, string $startTime, string $endTime, int $reservationId): bool
{
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM reservations
        WHERE pod_id = ?
          AND date_resa = ?
          AND statut != 'annulee'
          AND id != ?
          AND TIME_TO_SEC(heure_debut) < TIME_TO_SEC(?)
          AND (TIME_TO_SEC(heure_debut) + duree_heures * 3600) > TIME_TO_SEC(?)
    ");
    $stmt->execute([$podId, $date, $reservationId, $endTime, $startTime]);
    return (int)$stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $profession = trim($_POST['profession'] ?? '');
    $entreprise = trim($_POST['entreprise'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');

    if ($nom === '' || $prenom === '') {
        $error = "Le nom et le prenom sont obligatoires.";
    } else {
        $stmt = $db->prepare(
            "UPDATE users
             SET nom = ?, prenom = ?, telephone = ?, profession = ?, entreprise = ?, adresse = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $nom,
            $prenom,
            $telephone ?: null,
            $profession ?: null,
            $entreprise ?: null,
            $adresse ?: null,
            $_SESSION['user_id'],
        ]);
        $_SESSION['user_nom'] = $prenom;
        $message = "Profil mis a jour avec succes.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_reservation'])) {
    $reservationId = (int)($_POST['reservation_id'] ?? 0);
    $date = $_POST['date_resa'] ?? '';
    $heure = $_POST['heure_debut'] ?? '';
    $duree = max(1, min(8, (int)($_POST['duree_heures'] ?? 1)));

    try {
        $stmt = $db->prepare(
            "SELECT r.*, p.prix_heure
             FROM reservations r
             JOIN pods p ON p.id = r.pod_id
             WHERE r.id = ? AND r.user_id = ?"
        );
        $stmt->execute([$reservationId, $_SESSION['user_id']]);
        $reservation = $stmt->fetch();

        if (!$reservation) {
            throw new RuntimeException("Reservation introuvable.");
        }
        if ($date < date('Y-m-d')) {
            throw new RuntimeException("La date ne peut pas etre dans le passe.");
        }

        $startHour = (int)substr($heure, 0, 2);
        if ($startHour < 8 || ($startHour + $duree) > 21) {
            throw new RuntimeException("La reservation doit rester entre 08:00 et 21:00.");
        }

        $startTime = sprintf("%02d:00:00", $startHour);
        $endTime = sprintf("%02d:00:00", $startHour + $duree);

        if (accountReservationOverlaps($db, (int)$reservation['pod_id'], $date, $startTime, $endTime, $reservationId)) {
            throw new RuntimeException("Ce nouveau creneau chevauche deja une reservation.");
        }

        $prixTotal = (float)$reservation['prix_heure'] * $duree;
        $stmt = $db->prepare(
            "UPDATE reservations
             SET date_resa = ?, heure_debut = ?, duree_heures = ?, prix_total = ?, statut = 'confirmee'
             WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$date, $startTime, $duree, $prixTotal, $reservationId, $_SESSION['user_id']]);
        $message = "Reservation modifiee avec succes.";
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reservation'])) {
    $reservationId = (int)($_POST['reservation_id'] ?? 0);
    if ($reservationId > 0) {
        $stmt = $db->prepare("DELETE FROM reservations WHERE id = ? AND user_id = ?");
        $stmt->execute([$reservationId, $_SESSION['user_id']]);
        $message = "Reservation supprimee.";
    }
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$reservationsStmt = $db->prepare(
    "SELECT r.*, p.nom AS pod_nom, p.image AS pod_image
     FROM reservations r
     JOIN pods p ON p.id = r.pod_id
     WHERE r.user_id = ?
     ORDER BY r.date_resa DESC, r.heure_debut DESC"
);
$reservationsStmt->execute([$_SESSION['user_id']]);
$reservations = $reservationsStmt->fetchAll();

include 'includes/header.php';
?>

<main class="container section">
    <div class="account-layout">
        <aside class="profile-card">
            <?php if (!empty($user['photo'])): ?>
                <img src="<?= htmlspecialchars($user['photo']) ?>" alt="Photo de profil" class="profile-avatar-img">
            <?php else: ?>
                <div class="profile-avatar"><?= strtoupper(substr($user['prenom'] ?? 'U', 0, 1)) ?></div>
            <?php endif; ?>
            <h3><?= htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?></h3>
            <p><?= htmlspecialchars($user['email'] ?? '') ?></p>
            <ul class="profile-menu">
                <li><a href="#profil" class="active">Profil</a></li>
                <li><a href="#reservations">Mes reservations</a></li>
                <li><a href="reserve.php">Nouvelle reservation</a></li>
            </ul>
        </aside>

        <div>
            <section id="profil" class="card account-panel">
                <div class="flex-between align-center mb-2">
                    <div>
                        <span class="tag">Compte utilisateur</span>
                        <h1>Mes informations</h1>
                    </div>
                    <a href="reserve.php" class="btn btn-primary">Reserver un pod</a>
                </div>

                <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                <form method="POST" class="grid-form">
                    <div class="form-group">
                        <label for="prenom">Prenom</label>
                        <input type="text" name="prenom" id="prenom" class="form-control" value="<?= htmlspecialchars($user['prenom'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" name="nom" id="nom" class="form-control" value="<?= htmlspecialchars($user['nom'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="telephone">Telephone</label>
                        <input type="tel" name="telephone" id="telephone" class="form-control" value="<?= htmlspecialchars($user['telephone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="profession">Profession</label>
                        <input type="text" name="profession" id="profession" class="form-control" value="<?= htmlspecialchars($user['profession'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="entreprise">Entreprise</label>
                        <input type="text" name="entreprise" id="entreprise" class="form-control" value="<?= htmlspecialchars($user['entreprise'] ?? '') ?>">
                    </div>
                    <div class="form-group form-wide">
                        <label for="adresse">Adresse</label>
                        <input type="text" name="adresse" id="adresse" class="form-control" value="<?= htmlspecialchars($user['adresse'] ?? '') ?>">
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary form-wide">Mettre a jour</button>
                </form>
            </section>

            <section id="reservations" class="card account-panel mt-3">
                <h2 class="mb-2">Mes reservations</h2>
                <?php if (empty($reservations)): ?>
                    <p class="text-muted">Vous n'avez pas encore de reservation.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Pod</th>
                                    <th>Date</th>
                                    <th>Heure</th>
                                    <th>Duree</th>
                                    <th>Statut</th>
                                    <th>Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($reservation['pod_nom']) ?></td>
                                        <td><?= htmlspecialchars($reservation['date_resa']) ?></td>
                                        <td><?= htmlspecialchars(substr($reservation['heure_debut'], 0, 5)) ?></td>
                                        <td><?= htmlspecialchars($reservation['duree_heures']) ?>h</td>
                                        <td><span class="pill pill-confirmed"><?= htmlspecialchars($reservation['statut']) ?></span></td>
                                        <td class="text-success"><?= htmlspecialchars($reservation['prix_total']) ?> DT</td>
                                        <td>
                                            <details class="admin-details">
                                                <summary class="btn btn-outline btn-sm">Modifier</summary>
                                                <form method="POST" class="edit-pod-form">
                                                    <input type="hidden" name="reservation_id" value="<?= (int)$reservation['id'] ?>">
                                                    <input type="date" name="date_resa" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($reservation['date_resa']) ?>" required>
                                                    <select name="heure_debut" class="form-control" required>
                                                        <?php for ($hour = 8; $hour <= 20; $hour++): ?>
                                                            <?php $slot = sprintf('%02d:00', $hour); ?>
                                                            <option value="<?= $slot ?>" <?= substr($reservation['heure_debut'], 0, 5) === $slot ? 'selected' : '' ?>><?= $slot ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                    <input type="number" name="duree_heures" class="form-control" min="1" max="8" value="<?= (int)$reservation['duree_heures'] ?>" required>
                                                    <button type="submit" name="update_reservation" class="btn btn-primary btn-sm">Enregistrer</button>
                                                </form>
                                            </details>
                                            <form method="POST" class="mt-1" onsubmit="return confirm('Supprimer cette reservation ?');">
                                                <input type="hidden" name="reservation_id" value="<?= (int)$reservation['id'] ?>">
                                                <button type="submit" name="delete_reservation" class="btn btn-danger btn-sm">Supprimer</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
