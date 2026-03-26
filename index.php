<?php
require_once __DIR__ . '/db.php';

$db = get_db();
$galleries = $db->query("
    SELECT g.*,
           COUNT(DISTINCT p.id) AS photo_count,
           COUNT(DISTINCT i.identifier_name) AS identifier_count
    FROM galleries g
    LEFT JOIN photos p ON p.gallery_id = g.id
    LEFT JOIN identifications i ON i.gallery_id = g.id
    GROUP BY g.id
    ORDER BY g.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Photo ID — Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: #f8f9fa; }
  .gallery-card { transition: box-shadow .2s; }
  .gallery-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.12); }
  .badge-complete { background: #198754; }
  .badge-pending  { background: #6c757d; }
</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
  <div class="container">
    <span class="navbar-brand fw-bold"><i class="bi bi-camera"></i> Photo ID Admin</span>
    <a href="admin/upload.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Gallery</a>
  </div>
</nav>

<div class="container">
  <h5 class="mb-3 text-muted">Galleries</h5>

  <?php if (empty($galleries)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-images" style="font-size:3rem"></i>
      <p class="mt-2">No galleries yet. <a href="admin/upload.php">Create one.</a></p>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($galleries as $g): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card gallery-card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <h6 class="card-title mb-1"><?= htmlspecialchars($g['name']) ?></h6>
              <?php if ($g['completed_at']): ?>
                <span class="badge badge-complete">Complete</span>
              <?php else: ?>
                <span class="badge badge-pending">Pending</span>
              <?php endif; ?>
            </div>
            <small class="text-muted d-block mb-2"><?= date('M j, Y', strtotime($g['created_at'])) ?></small>
            <div class="d-flex gap-3 text-muted small mb-3">
              <span><i class="bi bi-image"></i> <?= $g['photo_count'] ?> photos</span>
              <span><i class="bi bi-people"></i> <?= $g['identifier_count'] ?> identifier(s)</span>
            </div>
            <div class="mb-2">
              <label class="form-label small text-muted mb-1">Share link</label>
              <div class="input-group input-group-sm">
                <input type="text" class="form-control share-link"
                       value="<?= BASE_URL ?>/identify/?token=<?= htmlspecialchars($g['token']) ?>"
                       readonly>
                <button class="btn btn-outline-secondary copy-btn" type="button" title="Copy">
                  <i class="bi bi-clipboard"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="card-footer bg-transparent d-flex gap-2">
            <a href="admin/view.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-outline-primary w-100">
              <i class="bi bi-eye"></i> View Results
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.copy-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = btn.previousElementSibling;
    navigator.clipboard.writeText(input.value).then(() => {
      btn.innerHTML = '<i class="bi bi-check"></i>';
      setTimeout(() => btn.innerHTML = '<i class="bi bi-clipboard"></i>', 1500);
    });
  });
});
</script>
</body>
</html>
