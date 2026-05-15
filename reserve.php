<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = getDB();
$pod_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected_date = $_GET['date'] ?? date('Y-m-d');
$error = "";

$stmt = $db->query("SELECT * FROM pods WHERE statut = 'disponible' ORDER BY nom ASC");
$pods = $stmt->fetchAll();

$selectedPod = null;
foreach ($pods as $item) {
    if ((int)$item['id'] === $pod_id) {
        $selectedPod = $item;
        break;
    }
}
if (!$selectedPod && !empty($pods)) {
    $selectedPod = $pods[0];
    $pod_id = (int)$selectedPod['id'];
}

function reservationOverlaps(PDO $db, int $podId, string $date, string $startTime, string $endTime, ?int $ignoreReservationId = null): bool
{
    $sql = "
        SELECT COUNT(*)
        FROM reservations
        WHERE pod_id = ?
          AND date_resa = ?
          AND statut != 'annulee'
          AND TIME_TO_SEC(heure_debut) < TIME_TO_SEC(?)
          AND (TIME_TO_SEC(heure_debut) + duree_heures * 3600) > TIME_TO_SEC(?)
    ";
    $params = [$podId, $date, $endTime, $startTime];

    if ($ignoreReservationId !== null) {
        $sql .= " AND id != ?";
        $params[] = $ignoreReservationId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pod_id = (int)($_POST['pod_id'] ?? 0);
    $date = $_POST['date_resa'] ?? '';
    $heure = $_POST['heure_debut'] ?? '';
    $duree = max(1, min(8, (int)($_POST['duree_heures'] ?? 1)));

    try {
        if ($pod_id <= 0 || $date === '' || $heure === '') {
            throw new RuntimeException("Veuillez choisir un pod, une date et une heure.");
        }

        if ($date < date('Y-m-d')) {
            throw new RuntimeException("La date de reservation ne peut pas etre dans le passe.");
        }

        $startHour = (int)substr($heure, 0, 2);
        if ($startHour < 8 || ($startHour + $duree) > 21) {
            throw new RuntimeException("La reservation doit rester entre 08:00 et 21:00.");
        }

        $stmt = $db->prepare("SELECT prix_heure FROM pods WHERE id = ? AND statut = 'disponible'");
        $stmt->execute([$pod_id]);
        $podData = $stmt->fetch();
        if (!$podData) {
            throw new RuntimeException("Ce pod n'est pas disponible.");
        }

        $startTime = sprintf("%02d:00:00", $startHour);
        $endTime = sprintf("%02d:00:00", $startHour + $duree);

        if (reservationOverlaps($db, $pod_id, $date, $startTime, $endTime)) {
            throw new RuntimeException("Ce creneau chevauche deja une reservation existante.");
        }

        $prixTotal = (float)$podData['prix_heure'] * $duree;
        $stmt = $db->prepare(
            "INSERT INTO reservations (user_id, pod_id, date_resa, heure_debut, duree_heures, prix_total, statut)
             VALUES (?, ?, ?, ?, ?, ?, 'confirmee')"
        );
        $stmt->execute([$_SESSION['user_id'], $pod_id, $date, $startTime, $duree, $prixTotal]);

        header("Location: account.php#reservations");
        exit;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<main class="container section reserve-page">
    <section class="reserve-hero">
        <span class="tag">Reservation</span>
        <h1>Reserver un pod sans perdre le fil</h1>
        <p class="text-muted">Choisissez votre espace, voyez le prix en direct, puis confirmez un creneau disponible.</p>
    </section>

    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (empty($pods)): ?>
        <div class="empty-state">
            <h2>Aucun pod disponible</h2>
            <p>Revenez plus tard ou contactez l'equipe WorkPods pour une reservation assistee.</p>
        </div>
    <?php else: ?>
        <section class="reserve-builder">
            <aside class="reserve-preview card">
                <div class="reserve-step">1</div>
                <h2>Votre pod</h2>
                <img
                    id="pod_image"
                    src="assets/pics/<?= htmlspecialchars($selectedPod['image'] ?? 'default-pod.jpg') ?>"
                    alt="<?= htmlspecialchars($selectedPod['nom'] ?? 'Pod') ?>"
                    class="reserve-pod-image">
                <h3 id="pod_name"><?= htmlspecialchars($selectedPod['nom'] ?? '') ?></h3>
                <p class="text-muted" id="pod_desc"><?= htmlspecialchars($selectedPod['description'] ?? '') ?></p>
                <div class="reserve-pod-meta">
                    <span id="pod_capacity"><?= (int)($selectedPod['capacite'] ?? 1) ?> personne(s)</span>
                    <strong id="pod_price"><?= number_format((float)($selectedPod['prix_heure'] ?? 0), 2) ?> DT/h</strong>
                </div>
            </aside>

            <form method="POST" id="reservation-form" class="card reserve-form-panel">
                <div class="reserve-step">2</div>
                <h2>Details de reservation</h2>

                <input type="hidden" name="pod_id" id="pod_id_input" value="<?= (int)$pod_id ?>">

                <div class="form-group">
                    <label for="pod_select">Pod disponible</label>
                    <select name="pod_select" id="pod_select" class="form-control" required>
                        <?php foreach($pods as $pod): ?>
                            <option
                                value="<?= (int)$pod['id'] ?>"
                                data-price="<?= htmlspecialchars($pod['prix_heure']) ?>"
                                data-name="<?= htmlspecialchars($pod['nom']) ?>"
                                data-desc="<?= htmlspecialchars($pod['description'] ?? '') ?>"
                                data-image="<?= htmlspecialchars($pod['image'] ?? 'default-pod.jpg') ?>"
                                data-capacity="<?= (int)$pod['capacite'] ?>"
                                <?= (int)$pod['id'] === (int)$pod_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pod['nom']) ?> - <?= number_format((float)$pod['prix_heure'], 2) ?> DT/h
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="reserve-form-grid">
                    <div class="form-group">
                        <label for="date_resa">Date</label>
                        <input type="date" name="date_resa" id="date_resa" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($selected_date) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="heure_debut">Heure</label>
                        <select name="heure_debut" id="heure_debut" class="form-control" required>
                            <option value="">Chargement des creneaux...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="duree_input">Duree</label>
                        <input type="number" name="duree_heures" id="duree_input" class="form-control" min="1" max="8" value="1" required>
                    </div>
                </div>

                <div class="reserve-summary-box">
                    <div>
                        <span class="text-muted">Total estime</span>
                        <h2 id="total_price">0 DT</h2>
                    </div>
                    <div class="text-muted" id="summary_text">Selectionnez un creneau.</div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Confirmer la reservation</button>
                <a href="account.php#reservations" class="btn btn-outline btn-block">Voir mes reservations</a>
            </form>
        </section>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const podSelect = document.getElementById('pod_select');
    const dateInput = document.getElementById('date_resa');
    const heureSelect = document.getElementById('heure_debut');
    const dureeInput = document.getElementById('duree_input');
    const totalPrice = document.getElementById('total_price');
    const podIdInput = document.getElementById('pod_id_input');
    const summaryText = document.getElementById('summary_text');
    const podImage = document.getElementById('pod_image');
    const podName = document.getElementById('pod_name');
    const podDesc = document.getElementById('pod_desc');
    const podCapacity = document.getElementById('pod_capacity');
    const podPrice = document.getElementById('pod_price');

    if (!podSelect) return;

    let currentPrice = parseFloat(podSelect.selectedOptions[0]?.dataset.price || 0);

    podSelect.addEventListener('change', function() {
        const option = this.selectedOptions[0];
        currentPrice = parseFloat(option.dataset.price || 0);
        podIdInput.value = this.value;
        podImage.src = `assets/pics/${option.dataset.image || 'default-pod.jpg'}`;
        podImage.alt = option.dataset.name || 'Pod';
        podName.textContent = option.dataset.name || '';
        podDesc.textContent = option.dataset.desc || '';
        podCapacity.textContent = `${option.dataset.capacity || 1} personne(s)`;
        podPrice.textContent = `${currentPrice.toFixed(2)} DT/h`;
        updateTotal();
        loadAvailableSlots();
    });

    dateInput.addEventListener('change', loadAvailableSlots);
    heureSelect.addEventListener('change', updateTotal);
    dureeInput.addEventListener('input', updateTotal);

    function loadAvailableSlots() {
        const podId = podSelect.value;
        const date = dateInput.value;

        if (!podId || !date) {
            heureSelect.innerHTML = '<option value="">Choisir un pod et une date</option>';
            updateTotal();
            return;
        }

        heureSelect.innerHTML = '<option value="">Chargement...</option>';
        fetch(`api/available_slots.php?pod_id=${podId}&date=${date}`)
            .then(response => response.json())
            .then(slots => {
                heureSelect.innerHTML = '';
                if (!slots.length) {
                    heureSelect.innerHTML = '<option value="">Aucun creneau disponible</option>';
                    updateTotal();
                    return;
                }

                slots.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot;
                    option.textContent = slot;
                    heureSelect.appendChild(option);
                });
                updateTotal();
            })
            .catch(() => {
                heureSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                updateTotal();
            });
    }

    function updateTotal() {
        const duree = parseInt(dureeInput.value, 10) || 1;
        const total = (currentPrice * duree).toFixed(2);
        totalPrice.textContent = `${total} DT`;
        const time = heureSelect.value || 'heure a choisir';
        summaryText.textContent = `${duree}h a partir de ${time}`;
    }

    updateTotal();
    loadAvailableSlots();
});
</script>

<?php include 'includes/footer.php'; ?>
