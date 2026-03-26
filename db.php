<?php
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
        init_schema($pdo);
    }
    return $pdo;
}

function init_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS galleries (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            token        TEXT UNIQUE NOT NULL,
            name         TEXT NOT NULL,
            created_at   TEXT DEFAULT (datetime('now')),
            completed_at TEXT NULL
        );

        CREATE TABLE IF NOT EXISTS photos (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            gallery_id INTEGER NOT NULL,
            filename   TEXT NOT NULL,
            sort_order INTEGER DEFAULT 0,
            FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS identifications (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            photo_id        INTEGER NOT NULL,
            gallery_id      INTEGER NOT NULL,
            identifier_name TEXT NOT NULL,
            people          TEXT,
            submitted_at    TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (photo_id)   REFERENCES photos(id)    ON DELETE CASCADE,
            FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE
        );
    ");
}
