<?php
require_once __DIR__ . '/../db.php';

$id = (int)($_GET['gallery_id'] ?? 0);
if (!$id) { header('Location: ../index.php'); exit; }

$db = get_db();

$gallery = $db->prepare("SELECT * FROM galleries WHERE id = ?");
$gallery->execute([$id]);
$gallery = $gallery->fetch();
if (!$gallery) { header('Location: ../index.php'); exit; }

$photos = $db->prepare("SELECT * FROM photos WHERE gallery_id = ? ORDER BY sort_order");
$photos->execute([$id]);
$photos = $photos->fetchAll();

$ids_rs = $db->prepare("SELECT * FROM identifications WHERE gallery_id = ? ORDER BY identifier_name");
$ids_rs->execute([$id]);
$id_map = [];
foreach ($ids_rs->fetchAll() as $row) {
    $id_map[$row['photo_id']][] = $row;
}

// Stream CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="gallery-' . $id . '-export.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fputcsv($out, ['filename', 'people']);

foreach ($photos as $photo) {
    $entries = $id_map[$photo['id']] ?? [];
    $names = [];
    foreach ($entries as $entry) {
        foreach (explode(',', $entry['people']) as $n) {
            $n = trim($n);
            if ($n !== '' && !in_array($n, $names)) $names[] = $n;
        }
    }
    fputcsv($out, [$photo['filename'], implode(', ', $names)]);
}

fclose($out);
exit;
