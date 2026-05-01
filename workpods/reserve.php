<?php
session_start();
require_once 'config/db.php';

// Rediriger si non connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = getDB();
$pod_id = $_GET['id'] ?? null;
$pod = null;

if ($pod_id) {
    $stmt = $db->prepare("SELECT * FROM pods WHERE id = ?");
    $stmt->execute([$pod_id]);
    $pod = $stmt->fetch();
}

if (!$pod) {
    header("Location: index.php");
    exit;
}

// Logique d'enregistrement de la réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date_resa'];
    $heure = $_POST['heure_debut'];
    $duree = $_POST['duree_heures'];
    $prix_total = $pod['prix_heure'] * $duree;

    $stmt = $db->prepare("INSERT INTO reservations (user_id, pod_id, date_resa, heure_debut, duree_heures, prix_total) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $pod_id, $date, $heure, $duree, $prix_total]);
    
    header("Location: index.php?success=1");
    exit;
}

include 'includes/header.php';
?>

<main class="container section">
    <div class="grid-2">
        <div class="card">
            <img src="assets/uploads/<?= $pod['image_url'] ?>" class="card-img mb-2" style="height: 300px; object-fit: cover;">
            <h2><?= htmlspecialchars($pod['nom']) ?></h2>
            <p class="text-muted mb-2"><?= htmlspecialchars($pod['description']) ?></p>
            <div class="pod-price"><?= $pod['prix_heure'] ?> DT <span>/ heure</span></div>
        </div>

        <div class="card">
            <h3>Finaliser ma <span>réservation</span></h3>
            <form method="POST" class="grid-form mt-2" id="reservation-form">
                <div class="form-group" style="grid-column: span 2;">
                    <label>Date de réservation</label>
                    <input type="date" name="date_resa" class="form-control" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Heure de début</label>
                    <input type="time" name="heure_debut" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Durée (heures)</label>
                    <input type="number" name="duree_heures" id="duree_input" class="form-control" min="1" max="8" value="1" 
                           data-price="<?= $pod['prix_heure'] ?>" required>
                </div>

                <div class="divider"></div>

                <div class="flex-between align-center">
                    <div>
                        <span class="text-muted">Total à payer :</span>
                        <h2 id="total_price"><?= $pod['prix_heure'] ?> DT</h2>
                    </div>
                    <button type="submit" class="btn btn-primary">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>