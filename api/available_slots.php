<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/db.php';

$pod_id = filter_input(INPUT_GET, 'pod_id', FILTER_VALIDATE_INT);
$date = $_GET['date'] ?? null;
$dateObj = $date ? DateTime::createFromFormat('Y-m-d', $date) : false;

if (!$pod_id || !$dateObj || $dateObj->format('Y-m-d') !== $date) {
    echo json_encode([]);
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT id FROM pods WHERE id = ? AND statut = 'disponible'");
$stmt->execute([$pod_id]);
if (!$stmt->fetch()) {
    echo json_encode([]);
    exit;
}

$all_slots = [];
for ($h = 8; $h <= 20; $h++) {
    $all_slots[] = sprintf("%02d:00", $h);
}

$stmt = $db->prepare("
    SELECT heure_debut, duree_heures
    FROM reservations
    WHERE pod_id = ? AND date_resa = ? AND statut != 'annulee'
");
$stmt->execute([$pod_id, $date]);
$bookings = $stmt->fetchAll();

$booked_slots = [];
foreach ($bookings as $booking) {
    $start_hour = (int) substr($booking['heure_debut'], 0, 2);
    for ($i = 0; $i < (int) $booking['duree_heures']; $i++) {
        $booked_slots[] = sprintf("%02d:00", $start_hour + $i);
    }
}

$available = array_values(array_diff($all_slots, $booked_slots));
echo json_encode($available);
