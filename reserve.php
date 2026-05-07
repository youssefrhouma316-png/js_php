<?php
require_once 'config/db.php';
require_once 'includes/security.php';

ensure_session();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = getDB();
$pod_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$selected_date = $_GET['date'] ?? null;
$pod = null;
$error = "";

$stmt = $db->query("SELECT * FROM pods WHERE statut = 'disponible' ORDER BY nom ASC");
$pods = $stmt->fetchAll();

if ($pod_id) {
    $stmt = $db->prepare("SELECT * FROM pods WHERE id = ? AND statut = 'disponible'");
    $stmt->execute([$pod_id]);
    $pod = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = "Session expirée. Rechargez la page puis réessayez.";
    } else {
        $pod_id = filter_input(INPUT_POST, 'pod_id', FILTER_VALIDATE_INT);
        $date = $_POST['date_resa'] ?? '';
        $heure = $_POST['heure_debut'] ?? '';
        $duree = filter_input(INPUT_POST, 'duree_heures', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 8],
        ]);

        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        $validDate = $dateObj && $dateObj->format('Y-m-d') === $date && $date >= date('Y-m-d');
        $validHour = preg_match('/^(0[8-9]|1[0-9]|20):00$/', $heure);

        if (!$pod_id || !$validDate || !$validHour || !$duree) {
            $error = "Veuillez choisir un pod, une date, une heure et une durée valides.";
        } else {
            $startHour = (int) substr($heure, 0, 2);
            $endHour = $startHour + $duree;

            if ($startHour < 8 || $endHour > 21) {
                $error = "La réservation doit rester dans les horaires d'ouverture.";
            }
        }

        if (!$error) {
            $stmt = $db->prepare("SELECT * FROM pods WHERE id = ? AND statut = 'disponible'");
            $stmt->execute([$pod_id]);
            $pod_data = $stmt->fetch();

            if (!$pod_data) {
                $error = "Ce pod n'est pas disponible.";
            }
        }

        if (!$error) {
            $stmt = $db->prepare("
                SELECT heure_debut, duree_heures
                FROM reservations
                WHERE pod_id = ? AND date_resa = ? AND statut != 'annulee'
            ");
            $stmt->execute([$pod_id, $date]);
            $bookings = $stmt->fetchAll();

            foreach ($bookings as $booking) {
                $bookedStart = (int) substr($booking['heure_debut'], 0, 2);
                $bookedEnd = $bookedStart + (int) $booking['duree_heures'];

                if ($startHour < $bookedEnd && $endHour > $bookedStart) {
                    $error = "Ce créneau est déjà réservé. Choisissez une autre heure.";
                    break;
                }
            }
        }

        if (!$error) {
            $prix_total = $pod_data['prix_heure'] * $duree;

            $stmt = $db->prepare("INSERT INTO reservations (user_id, pod_id, date_resa, heure_debut, duree_heures, prix_total, statut) VALUES (?, ?, ?, ?, ?, ?, 'confirmee')");
            $stmt->execute([$_SESSION['user_id'], $pod_id, $date, $heure, $duree, $prix_total]);

            header("Location: my_reservations.php?success=1");
            exit;
        }
    }
}

include 'includes/header.php';
?>

<main class="container section">
    <h1 class="mb-2">Réserver un <span>Pod</span></h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <div class="card">
            <h3>1. Choisir un <span>Pod</span></h3>
            <div class="form-group">
                <label>Pod disponible</label>
                <select name="pod_id" id="pod_select" class="form-control" required>
                    <option value="">-- Sélectionner un pod --</option>
                    <?php foreach($pods as $p): ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= $p['prix_heure'] ?>" <?= $pod_id == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nom']) ?> - <?= $p['prix_heure'] ?> DT/h
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="pod_info" class="mt-2" style="display: <?= $pod ? 'block' : 'none' ?>;">
                <img id="pod_image" src="assets/pics/<?= htmlspecialchars($pod['image'] ?? 'default-pod.jpg') ?>" class="card-img mb-2" style="height: 200px; object-fit: cover;">
                <h4 id="pod_name"><?= htmlspecialchars($pod['nom'] ?? '') ?></h4>
                <p class="text-muted" id="pod_desc"><?= htmlspecialchars($pod['description'] ?? '') ?></p>
                <div class="pod-price" id="pod_price"><?= htmlspecialchars($pod['prix_heure'] ?? 0) ?> DT <span>/ heure</span></div>
            </div>
        </div>

        <div class="card">
            <h3>2. Choisir date et <span>horaire</span></h3>
            <form method="POST" id="reservation-form">
                <?= csrf_field() ?>
                <input type="hidden" name="pod_id" id="pod_id_input" value="<?= htmlspecialchars((string) $pod_id) ?>">

                <div class="form-group">
                    <label>Date de réservation</label>
                    <input type="date" name="date_resa" id="date_resa" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars((string) $selected_date) ?>" required>
                </div>

                <div class="form-group">
                    <label>Heure de début disponible</label>
                    <select name="heure_debut" id="heure_debut" class="form-control" required>
                        <option value="">-- Choisir d'abord une date --</option>
                    </select>
                    <small class="text-muted">Les créneaux déjà réservés ne sont pas proposés.</small>
                </div>

                <div class="form-group">
                    <label>Durée (heures)</label>
                    <input type="number" name="duree_heures" id="duree_input" class="form-control" min="1" max="8" value="1" required>
                </div>

                <div class="divider"></div>

                <div class="flex-between align-center">
                    <div>
                        <span class="text-muted">Total à payer :</span>
                        <h2 id="total_price">0 DT</h2>
                    </div>
                    <button type="submit" class="btn btn-primary">Confirmer la réservation</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const podSelect = document.getElementById('pod_select');
    const dateInput = document.getElementById('date_resa');
    const heureSelect = document.getElementById('heure_debut');
    const dureeInput = document.getElementById('duree_input');
    const totalPrice = document.getElementById('total_price');
    const podIdInput = document.getElementById('pod_id_input');

    let currentPrice = 0;

    podSelect.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        currentPrice = parseFloat(option.dataset.price || 0);
        podIdInput.value = this.value;

        document.getElementById('pod_info').style.display = this.value ? 'block' : 'none';

        updateTotal();
        if (dateInput.value) loadAvailableSlots();
    });

    dateInput.addEventListener('change', loadAvailableSlots);
    dureeInput.addEventListener('input', updateTotal);

    function loadAvailableSlots() {
        const podId = podSelect.value;
        const date = dateInput.value;

        if (!podId || !date) {
            heureSelect.innerHTML = '<option value="">-- Sélectionner un pod et une date --</option>';
            return;
        }

        fetch(`api/available_slots.php?pod_id=${encodeURIComponent(podId)}&date=${encodeURIComponent(date)}`)
            .then(response => response.json())
            .then(slots => {
                heureSelect.innerHTML = '';
                if (slots.length === 0) {
                    heureSelect.innerHTML = '<option value="">Aucun créneau disponible</option>';
                    return;
                }

                slots.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot;
                    option.textContent = slot;
                    heureSelect.appendChild(option);
                });
            })
            .catch(err => {
                console.error('Error loading slots:', err);
                heureSelect.innerHTML = '<option value="">Erreur lors du chargement</option>';
            });
    }

    function updateTotal() {
        const duree = parseInt(dureeInput.value) || 1;
        const total = (currentPrice * duree).toFixed(2);
        totalPrice.textContent = total + ' DT';
    }

    if (podSelect.value) {
        currentPrice = parseFloat(podSelect.options[podSelect.selectedIndex].dataset.price) || 0;
        updateTotal();
        if (dateInput.value) loadAvailableSlots();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
