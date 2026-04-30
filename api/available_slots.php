<?php
/* ============================================
   API: Available Time Slots
   Returns JSON array of available hours for a pod on a given date
   ============================================ */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$pod_id = $_GET['pod_id'] ?? null;
$date = $_GET['date'] ?? null;

if (!$pod_id || !$date) {
    echo json_encode([]);
    exit;
}

$db = getDB();

// Business hours: 8h - 20h
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

$available = array_diff($all_slots, $booked_slots);
echo json_encode(array_values($available));