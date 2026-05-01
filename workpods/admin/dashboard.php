<?php
require_once '../config/db.php';
include '../includes/header.php';

$db = getDB();

// Récupération des statistiques via la VUE SQL
$stats = $db->query("SELECT * FROM v_stats_monthly ORDER BY mois DESC LIMIT 6")->fetchAll();

// Totaux rapides
$total_users = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$total_pods = $db->query("SELECT COUNT(*) FROM pods")->fetchColumn();
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
            <span class="text-muted">Revenu ce mois</span>
            <h2 class="text-success"><?= isset($stats[0]) ? $stats[0]['revenu_total'] : 0 ?> DT</h2>
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
</main>

<?php include '../includes/footer.php'; ?>