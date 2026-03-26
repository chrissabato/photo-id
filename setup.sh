#!/bin/bash
set -e

echo "==============================="
echo " Photo ID — Server Setup"
echo "==============================="
echo ""

# Detect web server user
if id "apache" &>/dev/null; then
    WEB_USER="apache"
elif id "www-data" &>/dev/null; then
    WEB_USER="www-data"
else
    read -rp "Web server user not detected. Enter username: " WEB_USER
fi
echo "Web server user: $WEB_USER"
echo ""

# Detect OS / package manager
if command -v dnf &>/dev/null; then
    PKG="dnf"
elif command -v yum &>/dev/null; then
    PKG="yum"
elif command -v apt-get &>/dev/null; then
    PKG="apt-get"
else
    PKG=""
fi

# -----------------------------------------------
# 1. Check / install SQLite PHP extension
# -----------------------------------------------
echo "--- Checking PHP SQLite extension ---"
if php -m 2>/dev/null | grep -qi pdo_sqlite; then
    echo "[OK] pdo_sqlite is loaded"
else
    echo "[!!] pdo_sqlite not found — attempting install..."
    if [ "$PKG" = "dnf" ] || [ "$PKG" = "yum" ]; then
        $PKG install -y php-pdo php-pdo_sqlite
    elif [ "$PKG" = "apt-get" ]; then
        PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
        apt-get install -y php${PHP_VER}-sqlite3
    else
        echo "[!!] Could not detect package manager. Install pdo_sqlite manually."
        exit 1
    fi

    # Restart web server
    if systemctl is-active --quiet httpd; then
        systemctl restart httpd
    elif systemctl is-active --quiet apache2; then
        systemctl restart apache2
    fi
    echo "[OK] pdo_sqlite installed"
fi
echo ""

# -----------------------------------------------
# 2. Set permissions on data/ directory
# -----------------------------------------------
echo "--- Setting data/ directory permissions ---"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
DATA_DIR="$SCRIPT_DIR/data"

if [ ! -d "$DATA_DIR" ]; then
    mkdir -p "$DATA_DIR"
    echo "Created $DATA_DIR"
fi

chown "$WEB_USER":"$WEB_USER" "$DATA_DIR"
chmod 750 "$DATA_DIR"
echo "[OK] $DATA_DIR — owner: $WEB_USER, mode: 750"
echo ""

# -----------------------------------------------
# 3. Set permissions on uploads/ directory
# -----------------------------------------------
echo "--- Setting uploads/ directory permissions ---"
UPLOADS_DIR="$SCRIPT_DIR/uploads"

if [ ! -d "$UPLOADS_DIR" ]; then
    mkdir -p "$UPLOADS_DIR"
    echo "Created $UPLOADS_DIR"
fi

chown "$WEB_USER":"$WEB_USER" "$UPLOADS_DIR"
chmod 775 "$UPLOADS_DIR"
echo "[OK] $UPLOADS_DIR — owner: $WEB_USER, mode: 775"
echo ""

# -----------------------------------------------
# 4. Check config.php for defaults
# -----------------------------------------------
echo "--- Checking config.php ---"
CONFIG="$SCRIPT_DIR/config.php"
WARN=0

if grep -q "admin@example.com" "$CONFIG"; then
    echo "[!!] ADMIN_EMAIL is still set to admin@example.com"
    WARN=1
fi
if grep -q "noreply@example.com" "$CONFIG"; then
    echo "[!!] FROM_EMAIL is still set to noreply@example.com"
    WARN=1
fi
if [ "$WARN" -eq 0 ]; then
    echo "[OK] config.php looks configured"
fi
echo ""

# -----------------------------------------------
# Done
# -----------------------------------------------
echo "==============================="
echo " Setup complete."
if [ "$WARN" -gt 0 ]; then
    echo " Update config.php before going live."
fi
echo "==============================="
