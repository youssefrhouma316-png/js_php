<?php
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

require_once __DIR__ . '/security.php';
ensure_session();

$base_path = str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/') ? '../' : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkPods | Espaces de Micro-Coworking</title>
    <link rel="stylesheet" href="<?= $base_path ?>assets/css/style.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="container nav-inner">
                <a href="<?= $base_path ?>index.php" class="nav-logo">Work<span>Pods</span></a>

                <ul class="nav-links">
                    <li><a href="<?= $base_path ?>index.php">Accueil</a></li>
                    <li><a href="<?= $base_path ?>reserve.php">Réserver</a></li>
                    <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') !== 'admin'): ?>
                        <li><a href="<?= $base_path ?>my_reservations.php">Mes réservations</a></li>
                    <?php endif; ?>
                </ul>

                <div class="nav-cta">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span class="nav-user">
                            <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                                <a href="<?= $base_path ?>admin/dashboard.php" class="btn btn-outline" style="background: rgba(108,99,255,.15); border-color: var(--accent2);">Tableau de bord</a>
                            <?php endif; ?>
                            <span style="color: var(--muted); font-size: .9rem;">Bienvenue, <?= htmlspecialchars($_SESSION['user_nom'] ?? 'Utilisateur') ?></span>
                            <a href="<?= $base_path ?>logout.php" class="btn btn-outline">Déconnecter</a>
                        </span>
                    <?php else: ?>
                        <a href="<?= $base_path ?>login.php" class="btn btn-outline">Connexion</a>
                        <a href="<?= $base_path ?>register.php" class="btn btn-primary">S'inscrire</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>
