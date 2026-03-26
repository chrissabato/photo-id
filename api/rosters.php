<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

if ($id) {
    // Return names for a specific roster
    $stmt = $db->prepare("SELECT name FROM roster_names WHERE roster_id = ? ORDER BY sort_order");
    $stmt->execute([$id]);
    echo json_encode(['names' => array_column($stmt->fetchAll(), 'name')]);
} else {
    // Return list of all rosters
    $rosters = $db->query("SELECT id, name FROM rosters ORDER BY name")->fetchAll();
    echo json_encode(['rosters' => $rosters]);
}
