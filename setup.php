<?php
// Quick diagnostics — DELETE THIS FILE after setup is confirmed working
$checks = [];

// PHP version
$checks['PHP Version'] = [
    'value' => PHP_VERSION,
    'ok'    => version_compare(PHP_VERSION, '7.2', '>='),
];

// SQLite extension
$checks['PDO SQLite'] = [
    'value' => extension_loaded('pdo_sqlite') ? 'loaded' : 'NOT loaded',
    'ok'    => extension_loaded('pdo_sqlite'),
];

// DB directory
$db_dir  = __DIR__ . '/data';
$db_path = $db_dir . '/photo_id.sqlite';
$dir_exists   = is_dir($db_dir);
$dir_writable = $dir_exists && is_writable($db_dir);

$checks['DB directory exists']   = ['value' => $db_dir,                              'ok' => $dir_exists];
$checks['DB directory writable'] = ['value' => $dir_writable ? 'yes' : 'no',         'ok' => $dir_writable];
$checks['DB file exists']        = ['value' => file_exists($db_path) ? 'yes' : 'no', 'ok' => true]; // not required yet

// Uploads directory
$upload_dir = __DIR__ . '/uploads';
$checks['Uploads dir exists']   = ['value' => $upload_dir,                              'ok' => is_dir($upload_dir)];
$checks['Uploads dir writable'] = ['value' => is_writable($upload_dir) ? 'yes' : 'no', 'ok' => is_writable($upload_dir)];

// Web server user
$checks['Web server user'] = ['value' => get_current_user() . ' / process: ' . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'unknown'), 'ok' => true];

$all_ok = array_reduce($checks, function($carry, $c) { return $carry && $c['ok']; }, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Photo ID — Setup Check</title>
<style>
  body { font-family: monospace; max-width: 700px; margin: 2rem auto; background:#f8f9fa; }
  h2 { font-family: sans-serif; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 8px 12px; border: 1px solid #dee2e6; text-align: left; }
  th { background: #e9ecef; }
  .ok  { color: #198754; font-weight: bold; }
  .bad { color: #dc3545; font-weight: bold; }
  .banner { padding: 1rem; border-radius: 6px; margin-bottom: 1rem; font-family: sans-serif; }
  .banner.ok  { background: #d1e7dd; }
  .banner.bad { background: #f8d7da; }
  pre { background: #212529; color: #f8f9fa; padding: 1rem; border-radius: 6px; overflow-x: auto; }
</style>
</head>
<body>
<h2>Photo ID — Setup Diagnostics</h2>

<div class="banner <?= $all_ok ? 'ok' : 'bad' ?>">
  <?= $all_ok ? 'All checks passed.' : 'One or more checks failed — see below.' ?>
</div>

<table>
  <tr><th>Check</th><th>Value</th><th>Status</th></tr>
  <?php foreach ($checks as $label => $check): ?>
  <tr>
    <td><?= htmlspecialchars($label) ?></td>
    <td><?= htmlspecialchars($check['value']) ?></td>
    <td class="<?= $check['ok'] ? 'ok' : 'bad' ?>"><?= $check['ok'] ? 'OK' : 'FAIL' ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<?php if (!$all_ok): ?>
<h3>Fix commands (run on server as root)</h3>
<pre>
<?php if (!extension_loaded('pdo_sqlite')): ?>
# Install SQLite PHP extension (RHEL/CentOS)
yum install php-pdo php-pdo_sqlite
# or
dnf install php-pdo php-pdo_sqlite
systemctl restart httpd

<?php endif; ?>
<?php if (!$dir_exists || !$dir_writable): ?>
# Fix data directory permissions
chown apache:apache <?= $db_dir ?>

chmod 750 <?= $db_dir ?>

<?php endif; ?>
<?php if (!is_writable($upload_dir)): ?>
# Fix uploads directory permissions
chown -R apache:apache <?= $upload_dir ?>

chmod 775 <?= $upload_dir ?>

<?php endif; ?>
</pre>
<?php endif; ?>

<p style="color:#6c757d;font-family:sans-serif;font-size:.85rem">
  <strong>Remember:</strong> Delete <code>setup.php</code> once everything is working.
</p>
</body>
</html>
