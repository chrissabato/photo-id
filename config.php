<?php
// SQLite database path (in data/ directory, protected by .htaccess)
define('DB_PATH', __DIR__ . '/data/photo_id.sqlite');

// Admin notification email
define('ADMIN_EMAIL', 'admin@example.com');
define('FROM_EMAIL',  'noreply@example.com');
define('FROM_NAME',   'Photo ID');

// Base URL — auto-detected from request (supports http/https, reverse proxies)
$_host   = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$_host   = trim(explode(',', $_host)[0]); // X-Forwarded-Host can be a comma-separated list
$_scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] :
           (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http'));
define('BASE_URL', $_scheme . '://' . $_host . '/photo-id');
unset($_host, $_scheme);

// Max upload size per photo (bytes) — 10 MB
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

// Allowed image types
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
