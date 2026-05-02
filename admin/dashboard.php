<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = getDB();

// Récupération des statistiques via la VUE SQL
$stats = $db->query("SELECT * FROM v_stats_monthly ORDER BY mois DESC LIMIT 6")->fetchAll();

// Totaux rapides
$total_users = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$total_pods = $db->query("SELECT COUNT(*) FROM pods")->fetchColumn();
$total_clients = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$total_reservations = $db->query("SELECT COUNT(*) FROM reservations")->fetchColumn();

$month = isset($_GET['month']) ? max(1, min(12, intval($_GET['month']))) : intval(date('m'));
$year = isset($_GET['year']) ? max(2020, min(2100, intval($_GET['year']))) : intval(date('Y'));
$monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$monthName = $monthNames[$month - 1];

$prevDate = new DateTime("$year-$month-01");
$prevDate->modify('-1 month');
$nextDate = new DateTime("$year-$month-01");
$nextDate->modify('+1 month');

$calendarStart = new DateTime("$year-$month-01");
$daysInMonth = (int)$calendarStart->format('t');
$startDayOfWeek = (int)$calendarStart->format('N');
$weekDays = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
$days = array_fill(1, $daysInMonth, []);

$calendarStmt = $db->prepare(
    "SELECT r.*, u.nom AS client_nom, u.prenom AS client_prenom, u.email AS client_email, p.nom AS pod_nom
     FROM reservations r
     JOIN users u ON u.id = r.user_id
     JOIN pods p ON p.id = r.pod_id
     WHERE YEAR(r.date_resa) = ? AND MONTH(r.date_resa) = ?
     ORDER BY r.date_resa, r.heure_debut"
);
$calendarStmt->execute([$year, $month]);
$monthReservations = $calendarStmt->fetchAll();
foreach ($monthReservations as $res) {
    $dayIndex = (int) date('j', strtotime($res['date_resa']));
    $days[$dayIndex][] = $res;
}

$dayCounts = array_map('count', $days);
$maxDayCount = $dayCounts ? max($dayCounts) : 0;

$upcomingStmt = $db->prepare(
    "SELECT r.*, u.nom AS client_nom, u.prenom AS client_prenom, u.email AS client_email, p.nom AS pod_nom
     FROM reservations r
     JOIN users u ON u.id = r.user_id
     JOIN pods p ON p.id = r.pod_id
     WHERE r.date_resa >= ?
     ORDER BY r.date_resa, r.heure_debut
     LIMIT 6"
);
$upcomingStmt->execute([date('Y-m-d')]);
$upcomingReservations = $upcomingStmt->fetchAll();

$reservations = $db->query(
    "SELECT r.*, u.nom AS client_nom, u.prenom AS client_prenom, u.email AS client_email, p.nom AS pod_nom
     FROM reservations r
     JOIN users u ON u.id = r.user_id
     JOIN pods p ON p.id = r.pod_id
     ORDER BY r.date_resa DESC, r.heure_debut DESC
     LIMIT 12"
)->fetchAll();

$clients = $db->query(
    "SELECT id, nom, prenom, email, created_at
     FROM users
     WHERE role = 'user'
     ORDER BY created_at DESC
     LIMIT 8"
)->fetchAll();

include '../includes/header.php';
?>

<main class="container section">
    <div class="flex-between align-center mb-3">
        <h1>Tableau de Bord</h1>
        <a href="manage_pods.php" class="btn btn-primary">Gérer les Pods</a>
    </div>

    <div class="grid-stats">
        <div class="card stat-card">
            <span class="text-muted">Utilisateurs</span>
            <h2 class="text-accent"><?= $total_users ?></h2>
        </div>
        <div class="card stat-card">
            <span class="text-muted">Pods Actifs</span>
            <h2 class="text-accent"><?= $total_pods ?></h2>
        </div>
        <div class="card stat-card">
            <span class="text-muted">Clients</span>
            <h2 class="text-accent"><?= $total_clients ?></h2>
        </div>
        <div class="card stat-card">
            <span class="text-muted">Réservations</span>
            <h2 class="text-accent"><?= $total_reservations ?></h2>
        </div>
    </div>

    <section class="card mt-3 admin-calendar-section">
        <div class="calendar-header-row">
            <div>
                <span class="tag">Calendrier de réservation</span>
                <h3><?= $monthName ?> <?= $year ?></h3>
            </div>
            <div class="calendar-nav">
                <a href="?month=<?= $prevDate->format('m') ?>&year=<?= $prevDate->format('Y') ?>" class="btn btn-ghost">← <?= $prevDate->format('F') ?></a>
                <a href="?month=<?= $nextDate->format('m') ?>&year=<?= $nextDate->format('Y') ?>" class="btn btn-ghost"><?= $nextDate->format('F') ?> →</a>
            </div>
        </div>

        <div class="calendar-layout">
            <div class="calendar-card">
                <div class="calendar-grid">
                    <?php foreach ($weekDays as $weekday): ?>
                        <div class="calendar-weekday"><?= $weekday ?></div>
                    <?php endforeach; ?>

                    <?php for ($i = 1; $i < $startDayOfWeek; $i++): ?>
                        <div class="calendar-cell empty"></div>
                    <?php endfor; ?>

                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                        <?php $entries = $days[$day]; $dayCount = count($entries); ?>
                        <div class="calendar-cell <?= ($day === intval(date('j')) && $month === intval(date('m')) && $year === intval(date('Y')) ? 'today' : '') ?> <?= ($dayCount === $maxDayCount && $maxDayCount > 0 ? 'busiest-day' : '') ?>">
                            <div class="calendar-day"><?= $day ?></div>
                            <?php if (!empty($entries)): ?>
                                <span class="pill pill-confirmed" title="<?= htmlspecialchars($dayCount . ' réservation' . ($dayCount > 1 ? 's' : '')) ?>"><?= $dayCount ?> réservation<?= $dayCount > 1 ? 's' : '' ?></span>
                                <?php foreach (array_slice($entries, 0, 2) as $entry): ?>
                                    <div class="calendar-event" title="<?= htmlspecialchars($entry['pod_nom'] . ' • ' . $entry['client_prenom'] . ' ' . $entry['client_nom'] . ' • ' . substr($entry['heure_debut'], 0, 5)) ?>">
                                        <strong><?= htmlspecialchars(substr($entry['heure_debut'], 0, 5)) ?></strong>
                                        <?= htmlspecialchars($entry['pod_nom']) ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <aside class="calendar-sidebar">
                <div class="card calendar-summary">
                    <h4>Prochaines réservations</h4>
                    <?php if (empty($upcomingReservations)): ?>
                        <p class="text-muted">Aucune réservation à venir.</p>
                    <?php else: ?>
                        <?php foreach ($upcomingReservations as $item): ?>
                            <div class="event-item">
                                <div class="event-meta">
                                    <strong><?= htmlspecialchars($item['date_resa']) ?> à <?= htmlspecialchars(substr($item['heure_debut'], 0, 5)) ?></strong>
                                </div>
                                <div class="event-body">
                                    <?= htmlspecialchars($item['pod_nom']) ?> — <?= htmlspecialchars($item['client_prenom'] . ' ' . $item['client_nom']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </section>

    <section class="card mt-3">
        <h3>Historique des Réservations (6 derniers mois)</h3>
        <table class="admin-table mt-2">
            <thead>
                <tr>
                    <th>Mois</th>
                    <th>Nombre de Réservations</th>
                    <th>Revenus Générés</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats as $s): ?>
                <tr>
                    <td><?= $s['mois'] ?></td>
                    <td><?= $s['nb_reservations'] ?></td>
                    <td class="text-success"><?= $s['revenu_total'] ?> DT</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card mt-3">
        <h3>Réservations récentes</h3>
        <table class="admin-table mt-2">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Pod</th>
                    <th>Date</th>
                    <th>Heure</th>
                    <th>Durée</th>
                    <th>Statut</th>
                    <th>Prix</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reservations)): ?>
                <tr>
                    <td colspan="7" class="text-center">Aucune réservation pour le moment.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($reservations as $res): ?>
                <tr>
                    <td><?= htmlspecialchars($res['client_prenom'] . ' ' . $res['client_nom']) ?> <br><small><?= htmlspecialchars($res['client_email']) ?></small></td>
                    <td><?= htmlspecialchars($res['pod_nom']) ?></td>
                    <td><?= htmlspecialchars($res['date_resa']) ?></td>
                    <td><?= htmlspecialchars(substr($res['heure_debut'], 0, 5)) ?></td>
                    <td><?= htmlspecialchars($res['duree_heures']) ?>h</td>
                    <td><?= htmlspecialchars($res['statut']) ?></td>
                    <td class="text-success"><?= htmlspecialchars($res['prix_total']) ?> DT</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card mt-3">
        <h3>Clients récents</h3>
        <table class="admin-table mt-2">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Email</th>
                    <th>Inscription</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)): ?>
                <tr>
                    <td colspan="3" class="text-center">Aucun client trouvé.</td>
                </tr>
                <?php endif; ?>
                <?php foreach ($clients as $client): ?>
                <tr>
                    <td><?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?></td>
                    <td><?= htmlspecialchars($client['email']) ?></td>
                    <td><?= htmlspecialchars($client['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>

<?php include '../includes/footer.php'; ?>