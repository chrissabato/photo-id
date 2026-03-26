<?php
// SQLite database path (in data/ directory, protected by .htaccess)
define('DB_PATH', __DIR__ . '/data/photo_id.sqlite');

// Admin notification email
define('ADMIN_EMAIL', 'admin@example.com');
define('FROM_EMAIL',  'noreply@example.com');
define('FROM_NAME',   'Photo ID');

// Base URL (no trailing slash)
define('BASE_URL', 'http://localhost/photo-id');

// Max upload size per photo (bytes) — 10 MB
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

// Allowed image types
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
