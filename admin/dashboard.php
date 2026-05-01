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