<?php
require_once __DIR__ . '/../db.php';

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['admin_email', 'from_email', 'from_name', 'gchat_webhook'];
    foreach ($fields as $f) {
        save_setting($f, trim($_POST[$f] ?? ''));
    }
    $saved = true;
}

$admin_email   = get_setting('admin_email');
$from_email    = get_setting('from_email');
$from_name     = get_setting('from_name', 'Photo ID');
$gchat_webhook = get_setting('gchat_webhook');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings — Photo ID</title>
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

<div class="container" style="max-width:600px">
  <h4 class="mb-4">Notification Settings</h4>

  <?php if ($saved): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> Settings saved.</div>
  <?php endif; ?>

  <form method="POST">

    <div class="card mb-4">
      <div class="card-header fw-semibold"><i class="bi bi-envelope"></i> Email</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Notification email <span class="text-muted">(where completion alerts go)</span></label>
          <input type="email" name="admin_email" class="form-control"
                 value="<?= htmlspecialchars($admin_email) ?>" placeholder="you@example.com">
        </div>
        <div class="mb-3">
          <label class="form-label">From address</label>
          <input type="email" name="from_email" class="form-control"
                 value="<?= htmlspecialchars($from_email) ?>" placeholder="noreply@example.com">
        </div>
        <div class="mb-0">
          <label class="form-label">From name</label>
          <input type="text" name="from_name" class="form-control"
                 value="<?= htmlspecialchars($from_name) ?>" placeholder="Photo ID">
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header fw-semibold"><i class="bi bi-chat"></i> Google Chat</div>
      <div class="card-body">
        <div class="mb-2">
          <label class="form-label">Incoming webhook URL</label>
          <input type="url" name="gchat_webhook" class="form-control"
                 value="<?= htmlspecialchars($gchat_webhook) ?>"
                 placeholder="https://chat.googleapis.com/v1/spaces/...">
          <div class="form-text">
            In Google Chat: open a Space → Apps &amp; integrations → Manage webhooks → Add webhook.
          </div>
        </div>
        <?php if ($gchat_webhook): ?>
        <div class="mt-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="test-gchat-btn">
            <i class="bi bi-send"></i> Send test message
          </button>
          <span id="test-gchat-result" class="ms-2 small"></span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100">
      <i class="bi bi-save"></i> Save Settings
    </button>
  </form>
</div>

<?php if ($gchat_webhook): ?>
<script>
document.getElementById('test-gchat-btn').addEventListener('click', function() {
  const btn = this;
  const result = document.getElementById('test-gchat-result');
  btn.disabled = true;
  result.textContent = 'Sending…';
  fetch('test_gchat.php', { method: 'POST' })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      result.textContent = d.ok ? 'Sent!' : ('Error: ' + d.error);
      result.style.color = d.ok ? 'green' : 'red';
      btn.disabled = false;
    })
    .catch(function() {
      result.textContent = 'Request failed.';
      result.style.color = 'red';
      btn.disabled = false;
    });
});
</script>
<?php endif; ?>
</body>
</html>
