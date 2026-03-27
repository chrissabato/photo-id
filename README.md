# Photo ID

A web application for crowd-sourcing identification of people in photo galleries. Admins upload photo galleries and share links with identifiers, who tag names to each photo. When complete, the admin receives a notification and can view the results and download a CSV for use with the included metadata tagger.

## Features

- Upload photo galleries and generate shareable token-based links
- Photo-by-photo identification with keyboard navigation
- Known names sidebar with quick-add buttons and roster support
- Fullscreen photo lightbox
- Google Chat and email notifications on completion
- CSV export of identification results
- Windows tagger utility to write "Person Shown" XMP metadata to image files
- SQLite database — no database server required
- Shibboleth-compatible access control

## Requirements

- Apache with `mod_rewrite` and `.htaccess` support
- PHP 7.2+ with `pdo_sqlite` and `curl` extensions
- SQLite3

## Installation

**1. Clone the repo into your web root:**
```bash
git clone https://github.com/chrissabato/photo-id.git /path/to/webroot/photo-id
cd /path/to/webroot/photo-id
```

**2. Run the setup script** (as root):
```bash
sudo bash setup.sh
```

This will install missing PHP extensions, and set correct permissions on `data/` and `uploads/`.

**3. Configure notifications** by visiting `admin/settings.php` in your browser.

**4. Restrict access** — create `.htaccess` files for your auth system:

- Root `.htaccess` — allow any authenticated user to access the ID pages
- `admin/.htaccess` — restrict to specific admin users

These files are excluded from git since they contain server-specific configuration.

Example using Shibboleth:

```apache
# Root .htaccess — any authenticated user
AuthType shibboleth
ShibRequireSession On
Require valid-user
```

```apache
# admin/.htaccess — specific admins only
AuthType shibboleth
ShibRequireSession On
Require shib-user username1 username2
```

## Directory Structure

```
photo-id/
├── index.php              # Redirects to admin/
├── config.php             # App configuration
├── db.php                 # Database connection and schema
├── notify.php             # Email and Google Chat notification helpers
├── setup.php              # Browser-based diagnostics page (delete after setup)
├── setup.sh               # Server setup script
├── admin/
│   ├── index.php          # Admin dashboard (protected by admin/.htaccess)
│   ├── upload.php         # Create a gallery and upload photos
│   ├── view.php           # View gallery results
│   ├── export.php         # Download identification results as CSV
│   ├── delete.php         # Delete a gallery
│   ├── rosters.php        # Manage predefined name rosters
│   ├── settings.php       # Configure email and Google Chat notifications
│   └── test_gchat.php     # Send a test Google Chat message
├── identify/
│   └── index.php          # Public photo identification page
├── api/
│   ├── complete.php       # Save identifications and send notifications
│   └── rosters.php        # Serve roster data to the ID page
├── tools/
│   ├── photo_id_tagger.py # Windows tagger source (Python/tkinter)
│   └── photo_id_tagger.zip # Windows tagger distributable (exe + ExifTool)
├── data/                  # SQLite database (not committed)
└── uploads/               # Uploaded photos (not committed)
```

## Usage

### Admin
1. Go to `admin/` and click **New Gallery**
2. Enter a gallery name and upload photos
3. Copy the share link and send it to your identifiers
4. When notified of completion, click **View Results** to see the tagged photos
5. Download the CSV and use the **Tagger** utility to write names to image file metadata

### Identifiers
1. Open the share link
2. For each photo, type the names of people shown (comma-separated)
3. Use the **Known Names** sidebar to quickly re-use names, or **Load Roster** to pre-populate from a saved list
4. Click a photo to view it full screen
5. Use **←** / **→** arrow keys to navigate between photos
6. When all photos are visited, click the green **Submit** bar at the bottom

### Rosters
Predefined name lists (e.g. team rosters) can be created under **Admin → Rosters**. On the ID page, click **Load Roster** in the Known Names panel to load a roster's names as quick-add buttons.

## Metadata Tagger

The **Photo ID Tagger** is a Windows utility that reads the exported CSV and writes "Person Shown" (`XMP-iptcExt:PersonInImage`) metadata to your original image files using ExifTool.

Download it from the admin navbar (**Tagger** button). Extract the zip — it contains `photo_id_tagger.exe` and the ExifTool files. Run the exe, select the CSV and image folder, and click **Run**.

The tagger handles filename differences between the originals and the sanitized upload names (spaces converted to underscores, etc.) automatically.

## Notifications

Configure under **Admin → Settings**:

- **Email** — sends a plain text email when an identifier completes a gallery
- **Google Chat** — posts to a webhook when complete. To get a webhook URL: open a Space → Apps & integrations → Manage webhooks → Add webhook

## Configuration

`config.php` sets the database path, upload limits, and allowed file types. The base URL is auto-detected from the HTTP request — no manual configuration needed.

Settings that change between environments (email addresses, webhook URLs) are stored in the database and managed through the admin UI.
