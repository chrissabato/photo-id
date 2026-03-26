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

$identifiers_rs = $db->prepare("
    SELECT identifier_name, MIN(submitted_at) AS submitted_at
    FROM identifications
    WHERE gallery_id = ?
    GROUP BY identifier_name
    ORDER BY submitted_at
");
$identifiers_rs->execute([$id]);
$identifiers = $identifiers_rs->fetchAll();
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

  <?php if ($identifiers): ?>
  <div class="card mb-4">
    <div class="card-header fw-semibold"><i class="bi bi-people"></i> Identifiers</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($identifiers as $ident): ?>
      <li class="list-group-item small">
        <?= htmlspecialchars($ident['identifier_name']) ?>
        <span class="text-muted ms-2"><?= date('M j, Y g:i a', strtotime($ident['submitted_at'])) ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($photos as $photo): ?>
    <div class="col-sm-6 col-md-4 col-lg-3">
      <div class="card photo-card h-100">
        <img src="../uploads/<?= $gallery['id'] ?>/<?= htmlspecialchars($photo['filename']) ?>" alt="Photo">
        <div class="card-body p-2">
          <?php $entries = $id_map[$photo['id']] ?? []; ?>
          <?php if ($entries): ?>
            <?php foreach ($entries as $entry): ?>
              <div class="id-entry">
                <strong><?= htmlspecialchars($entry['identifier_name']) ?>:</strong>
                <?= htmlspecialchars($entry['people'] ?: '(blank)') ?>
              </div>
            <?php endforeach; ?>
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
