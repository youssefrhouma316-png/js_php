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
$selected_date = $_GET['date'] ?? null;
$pod = null;
$pods = [];

// Get all available pods
$stmt = $db->query("SELECT * FROM pods WHERE statut = 'disponible' ORDER BY nom ASC");
$pods = $stmt->fetchAll();

if ($pod_id) {
    $stmt = $db->prepare("SELECT * FROM pods WHERE id = ?");
    $stmt->execute([$pod_id]);
    $pod = $stmt->fetch();
}

// Logique d'enregistrement de la réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pod_id = $_POST['pod_id'];
    $date = $_POST['date_resa'];
    $heure = $_POST['heure_debut'];
    $duree = $_POST['duree_heures'];
    
    // Get pod price
    $stmt = $db->prepare("SELECT prix_heure FROM pods WHERE id = ?");
    $stmt->execute([$pod_id]);
    $pod_data = $stmt->fetch();
    $prix_total = $pod_data['prix_heure'] * $duree;

    $stmt = $db->prepare("INSERT INTO reservations (user_id, pod_id, date_resa, heure_debut, duree_heures, prix_total, statut) VALUES (?, ?, ?, ?, ?, ?, 'confirmee')");
    $stmt->execute([$_SESSION['user_id'], $pod_id, $date, $heure, $duree, $prix_total]);
    
    header("Location: index.php?success=1");
    exit;
}

// Helper: Get available time slots for a pod on a given date
function getAvailableSlots(PDO $db, int $pod_id, string $date): array {
    $all_slots = [];
    for ($h = 8; $h <= 20; $h++) {
        $all_slots[] = sprintf("%02d:00", $h);
    }
    
    // Get booked slots for this pod on this date
    $stmt = $db->prepare("
        SELECT heure_debut, duree_heures 
        FROM reservations 
        WHERE pod_id = ? AND date_resa = ? AND statut != 'annulee'
    ");
    $stmt->execute([$pod_id, $date]);
    $bookings = $stmt->fetchAll();
    
    $booked_slots = [];
    foreach ($bookings as $booking) {
        $start_hour = (int)substr($booking['heure_debut'], 0, 2);
        for ($i = 0; $i < $booking['duree_heures']; $i++) {
            $booked_slots[] = sprintf("%02d:00", $start_hour + $i);
        }
    }
    
    return array_diff($all_slots, $booked_slots);
}

include 'includes/header.php';
?>

<main class="container section">
    <h1 class="mb-2">Réserver un <span>Pod</span></h1>
    
    <div class="grid-2">
        <!-- Pod Selection -->
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
                <img id="pod_image" src="assets/uploads/<?= $pod['image_url'] ?? 'default-pod.svg' ?>" class="card-img mb-2" style="height: 200px; object-fit: cover;">
                <h4 id="pod_name"><?= $pod['nom'] ?? '' ?></h4>
                <p class="text-muted" id="pod_desc"><?= $pod['description'] ?? '' ?></p>
                <div class="pod-price" id="pod_price"><?= $pod['prix_heure'] ?? 0 ?> DT <span>/ heure</span></div>
            </div>
        </div>

        <!-- Date & Time Selection -->
        <div class="card">
            <h3>2. Choisir date et <span>horaire</span></h3>
            <form method="POST" id="reservation-form">
                <input type="hidden" name="pod_id" id="pod_id_input" value="<?= $pod_id ?>">
                
                <div class="form-group">
                    <label>Date de réservation</label>
                    <input type="date" name="date_resa" id="date_resa" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= $selected_date ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Heure de début disponible</label>
                    <select name="heure_debut" id="heure_debut" class="form-control" required>
                        <option value="">-- Choisir d'abord une date --</option>
                    </select>
                    <small class="text-muted">Les créneaux grisés sont déjà réservés</small>
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
    
    // Update pod info when selection changes
    podSelect.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        const price = option.dataset.price || 0;
        currentPrice = parseFloat(price);
        podIdInput.value = this.value;
        
        // Show/hide pod info
        document.getElementById('pod_info').style.display = this.value ? 'block' : 'none';
        
        updateTotal();
        if (dateInput.value) loadAvailableSlots();
    });
    
    // Load available slots when date changes
    dateInput.addEventListener('change', function() {
        loadAvailableSlots();
    });
    
    // Update price when duration changes
    dureeInput.addEventListener('input', updateTotal);
    
    function loadAvailableSlots() {
        const podId = podSelect.value;
        const date = dateInput.value;
        
        if (!podId || !date) {
            heureSelect.innerHTML = '<option value="">-- Sélectionner un pod et une date --</option>';
            return;
        }
        
        // Fetch available slots via AJAX
        fetch(`api/available_slots.php?pod_id=${podId}&date=${date}`)
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
                    option.textContent = slot + ':00';
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
    
    // Initialize
    if (podSelect.value) {
        currentPrice = parseFloat(podSelect.options[podSelect.selectedIndex].dataset.price) || 0;
        updateTotal();
        if (dateInput.value) loadAvailableSlots();
    }
});
</script>

<?php include 'includes/footer.php'; ?>