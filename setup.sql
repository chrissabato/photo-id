CREATE DATABASE IF NOT EXISTS photo_id CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE photo_id;

CREATE TABLE IF NOT EXISTS galleries (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  token       VARCHAR(64) UNIQUE NOT NULL,
  name        VARCHAR(255) NOT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS photos (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  gallery_id  INT NOT NULL,
  filename    VARCHAR(255) NOT NULL,
  sort_order  INT DEFAULT 0,
  FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS identifications (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  photo_id        INT NOT NULL,
  gallery_id      INT NOT NULL,
  identifier_name VARCHAR(255) NOT NULL,
  people          TEXT,
  submitted_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (photo_id)    REFERENCES photos(id)    ON DELETE CASCADE,
  FOREIGN KEY (gallery_id)  REFERENCES galleries(id) ON DELETE CASCADE
);
