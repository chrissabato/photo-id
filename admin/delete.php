<?php
require_once __DIR__ . '/../db.php';

$id = (int)($_POST['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$db = get_db();

$gallery = $db->prepare("SELECT * FROM galleries WHERE id = ?");
$gallery->execute([$id]);
$gallery = $gallery->fetch();

if ($gallery) {
    // Delete upload directory and all photos
    $upload_dir = __DIR__ . '/../uploads/' . $id . '/';
    if (is_dir($upload_dir)) {
        foreach (glob($upload_dir . '*') as $file) {
            unlink($file);
        }
        rmdir($upload_dir);
    }

    // Delete from DB (cascades to photos + identifications)
    $db->prepare("DELETE FROM galleries WHERE id = ?")->execute([$id]);
}

header('Location: index.php');
exit;
