<?php
require_once __DIR__ . '/../db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ../index.php'); exit; }

$db = get_db();

$gallery = $db->prepare("SELECT * FROM galleries WHERE id = ?");
$gallery->execute([$id]);
$gallery = $gallery->fetch();
if (!$gallery) { header('Location: ../index.php'); exit; }

// Fetch photos, then identifications separately (SQLite-compatible)
$photos = $db->prepare("SELECT * FROM photos WHERE gallery_id = ? ORDER BY sort_order");
$photos->execute([$id]);
$photos = $photos->fetchAll();

$ids_rs = $db->prepare("SELECT * FROM identifications WHERE gallery_id = ? ORDER BY identifier_name");
$ids_rs->execute([$id]);
// Group by photo_id in PHP
$id_map = [];
foreach ($ids_rs->fetchAll() as $row) {
    $id_map[$row['photo_id']][] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($gallery['name']) ?> — Results</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: #f8f9fa; }
  .photo-card img { width: 100%; height: 220px; object-fit: cover; border-radius: 6px 6px 0 0; }
  .id-entry { font-size: .85rem; border-bottom: 1px solid #e9ecef; padding: 4px 0; }
  .id-entry:last-child { border-bottom: none; }
</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
  <div class="container">
    <a href="../index.php" class="navbar-brand fw-bold"><i class="bi bi-camera"></i> Photo ID Admin</a>
  </div>
</nav>

<div class="container">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h4 class="mb-0"><?= htmlspecialchars($gallery['name']) ?></h4>
      <small class="text-muted">Created <?= date('M j, Y', strtotime($gallery['created_at'])) ?></small>
    </div>
    <?php if ($gallery['completed_at']): ?>
      <span class="badge bg-success fs-6">Completed <?= date('M j, Y', strtotime($gallery['completed_at'])) ?></span>
    <?php else: ?>
      <span class="badge bg-secondary fs-6">Pending</span>
    <?php endif; ?>
  </div>


  <div class="row g-3">
    <?php foreach ($photos as $photo): ?>
    <div class="col-sm-6 col-md-4 col-lg-3">
      <div class="card photo-card h-100">
        <img src="../uploads/<?= $gallery['id'] ?>/<?= htmlspecialchars($photo['filename']) ?>" alt="Photo">
        <div class="card-body p-2">
          <?php
            $entries = $id_map[$photo['id']] ?? [];
            $names = [];
            foreach ($entries as $entry) {
              foreach (explode(',', $entry['people']) as $n) {
                $n = trim($n);
                if ($n !== '' && !in_array($n, $names)) $names[] = $n;
              }
            }
          ?>
          <p class="text-muted small mb-1" style="font-size:.7rem;word-break:break-all"><?= htmlspecialchars($photo['filename']) ?></p>
          <?php if ($names): ?>
            <p class="mb-0 small"><?= htmlspecialchars(implode(', ', $names)) ?></p>
          <?php else: ?>
            <span class="text-muted small">No identifications yet</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
