<?php
require_once __DIR__ . '/../db.php';

$db = get_db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$error  = '';
$success = '';

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $names = trim($_POST['names'] ?? '');
        if ($name === '') {
            $error = 'Roster name is required.';
        } else {
            $db->prepare("INSERT INTO rosters (name) VALUES (?)")->execute([$name]);
            $roster_id = $db->lastInsertId();
            save_names($db, $roster_id, $names);
            header('Location: rosters.php?saved=1'); exit;
        }
    }

    if ($action === 'update') {
        $roster_id = (int)($_POST['id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $names     = trim($_POST['names'] ?? '');
        if ($name === '') {
            $error = 'Roster name is required.';
        } else {
            $db->prepare("UPDATE rosters SET name = ? WHERE id = ?")->execute([$name, $roster_id]);
            $db->prepare("DELETE FROM roster_names WHERE roster_id = ?")->execute([$roster_id]);
            save_names($db, $roster_id, $names);
            header('Location: rosters.php?saved=1'); exit;
        }
    }

    if ($action === 'delete') {
        $db->prepare("DELETE FROM rosters WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: rosters.php'); exit;
    }
}

function save_names(PDO $db, int $roster_id, string $raw): void {
    $stmt  = $db->prepare("INSERT INTO roster_names (roster_id, name, sort_order) VALUES (?, ?, ?)");
    $order = 0;
    foreach (explode("\n", $raw) as $line) {
        $line = trim($line);
        if ($line !== '') $stmt->execute([$roster_id, $line, $order++]);
    }
}

// --- Load data for views ---
$rosters = $db->query("SELECT * FROM rosters ORDER BY name")->fetchAll();

$editing = null;
$editing_names = '';
if ($action === 'edit' && $id) {
    $editing = $db->prepare("SELECT * FROM rosters WHERE id = ?");
    $editing->execute([$id]);
    $editing = $editing->fetch();
    if ($editing) {
        $rows = $db->prepare("SELECT name FROM roster_names WHERE roster_id = ? ORDER BY sort_order");
        $rows->execute([$id]);
        $editing_names = implode("\n", array_column($rows->fetchAll(), 'name'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rosters — Photo ID</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>body { background: #f8f9fa; }</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
  <div class="container">
    <a href="index.php" class="navbar-brand fw-bold"><i class="bi bi-camera"></i> Photo ID Admin</a>
  </div>
</nav>

<div class="container" style="max-width:700px">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Rosters</h4>
    <a href="?action=new" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Roster</a>
  </div>

  <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> Roster saved.</div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($action === 'new' || ($action === 'edit' && $editing)): ?>
  <!-- Create / Edit form -->
  <div class="card mb-4">
    <div class="card-header fw-semibold">
      <?= $editing ? 'Edit Roster' : 'New Roster' ?>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?>
          <input type="hidden" name="id" value="<?= $editing['id'] ?>">
        <?php endif; ?>
        <div class="mb-3">
          <label class="form-label fw-semibold">Roster Name</label>
          <input type="text" name="name" class="form-control"
                 value="<?= htmlspecialchars($editing['name'] ?? '') ?>"
                 placeholder="e.g. Varsity Basketball 2024" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Names <span class="text-muted fw-normal">(one per line)</span></label>
          <textarea name="names" class="form-control font-monospace" rows="12"
                    placeholder="Jane Smith&#10;John Doe&#10;..."><?= htmlspecialchars($editing_names) ?></textarea>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Roster</button>
          <a href="rosters.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Roster list -->
  <?php if (empty($rosters)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-people" style="font-size:2.5rem"></i>
      <p class="mt-2">No rosters yet. Create one to use on the identification page.</p>
    </div>
  <?php else: ?>
    <div class="list-group">
      <?php foreach ($rosters as $r): ?>
        <?php
          $count = $db->prepare("SELECT COUNT(*) FROM roster_names WHERE roster_id = ?");
          $count->execute([$r['id']]);
          $count = $count->fetchColumn();
        ?>
        <div class="list-group-item d-flex justify-content-between align-items-center">
          <div>
            <strong><?= htmlspecialchars($r['name']) ?></strong>
            <span class="text-muted ms-2 small"><?= $count ?> name(s)</span>
          </div>
          <div class="d-flex gap-2">
            <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-pencil"></i> Edit
            </a>
            <form method="POST" onsubmit="return confirm('Delete this roster?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
