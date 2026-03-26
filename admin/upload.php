<?php
require_once __DIR__ . '/../db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $error = 'Gallery name is required.';
    } elseif (empty($_FILES['photos']['name'][0])) {
        $error = 'Please select at least one photo.';
    } else {
        $db    = get_db();
        $token = bin2hex(random_bytes(24));

        $db->prepare("INSERT INTO galleries (token, name) VALUES (?, ?)")
           ->execute([$token, $name]);
        $gallery_id = $db->lastInsertId();

        $upload_dir = __DIR__ . '/../uploads/' . $gallery_id . '/';
        mkdir($upload_dir, 0755, true);

        $files   = $_FILES['photos'];
        $count   = count($files['name']);
        $order   = 0;
        $skipped = 0;

        $stmt = $db->prepare("INSERT INTO photos (gallery_id, filename, sort_order) VALUES (?, ?, ?)");

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) { $skipped++; continue; }
            if ($files['size'][$i] > MAX_FILE_SIZE)    { $skipped++; continue; }

            $mime = mime_content_type($files['tmp_name'][$i]);
            if (!in_array($mime, ALLOWED_TYPES))        { $skipped++; continue; }

            $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $filename = bin2hex(random_bytes(8)) . '.' . strtolower($ext);

            if (move_uploaded_file($files['tmp_name'][$i], $upload_dir . $filename)) {
                $stmt->execute([$gallery_id, $filename, $order++]);
            } else {
                $skipped++;
            }
        }

        $share_url = BASE_URL . '/identify/?token=' . $token;
        $success   = "Gallery created with {$order} photo(s)" .
                     ($skipped ? " ({$skipped} skipped due to errors/type)" : '') . '.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>New Gallery — Photo ID</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: #f8f9fa; }
  #drop-zone {
    border: 2px dashed #adb5bd;
    border-radius: 8px;
    padding: 3rem;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
  }
  #drop-zone.drag-over { border-color: #0d6efd; background: #e8f0fe; }
  #preview-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 1rem; }
  #preview-grid img { width: 100px; height: 100px; object-fit: cover; border-radius: 6px; }
</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
  <div class="container">
    <a href="../index.php" class="navbar-brand fw-bold"><i class="bi bi-camera"></i> Photo ID Admin</a>
  </div>
</nav>

<div class="container" style="max-width:600px">
  <h4 class="mb-4">Create New Gallery</h4>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <strong><?= htmlspecialchars($success) ?></strong><br>
      Share this link with identifiers:<br>
      <a href="<?= htmlspecialchars($share_url) ?>" target="_blank"><?= htmlspecialchars($share_url) ?></a>
      <button class="btn btn-sm btn-outline-secondary ms-2" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($share_url) ?>')">
        <i class="bi bi-clipboard"></i> Copy
      </button>
    </div>
    <a href="../index.php" class="btn btn-primary">Back to Dashboard</a>
  <?php else: ?>
    <form method="POST" enctype="multipart/form-data" id="upload-form">
      <div class="mb-3">
        <label class="form-label fw-semibold">Gallery Name</label>
        <input type="text" name="name" class="form-control" placeholder="e.g. Smith Family Reunion 2024" required>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Photos</label>
        <div id="drop-zone">
          <i class="bi bi-cloud-upload" style="font-size:2rem;color:#6c757d"></i>
          <p class="mb-1 mt-2">Drag & drop photos here, or <strong>click to select</strong></p>
          <small class="text-muted">JPEG, PNG, GIF, WebP — max 10 MB each</small>
          <input type="file" name="photos[]" id="file-input" multiple accept="image/*" class="d-none">
        </div>
        <div id="preview-grid"></div>
        <div id="file-count" class="text-muted small mt-1"></div>
      </div>

      <button type="submit" class="btn btn-primary w-100" id="submit-btn">
        <i class="bi bi-upload"></i> Create Gallery
      </button>
    </form>
  <?php endif; ?>
</div>

<script>
const dropZone  = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
const preview   = document.getElementById('preview-grid');
const fileCount = document.getElementById('file-count');

dropZone.addEventListener('click', () => fileInput.click());

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.classList.remove('drag-over');
  fileInput.files = e.dataTransfer.files;
  showPreviews(e.dataTransfer.files);
});

fileInput.addEventListener('change', () => showPreviews(fileInput.files));

function showPreviews(files) {
  preview.innerHTML = '';
  fileCount.textContent = files.length + ' file(s) selected';
  Array.from(files).forEach(f => {
    const img = document.createElement('img');
    img.src = URL.createObjectURL(f);
    preview.appendChild(img);
  });
}

document.getElementById('upload-form').addEventListener('submit', () => {
  document.getElementById('submit-btn').disabled = true;
  document.getElementById('submit-btn').textContent = 'Uploading…';
});
</script>
</body>
</html>
