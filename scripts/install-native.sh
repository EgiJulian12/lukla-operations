#!/usr/bin/env bash
# ==============================================================================
# Script Instalasi Dependensi Server Bersih (Native Stack / Non-Docker)
# Sistem Operasi: Ubuntu 20.04 / 22.04 / 24.04 LTS atau Debian 11 / 12
# Stack: PHP 8.2-FPM, PostgreSQL 16, Nginx (Port 3000), Composer, Node.js 20, Supervisor
# ==============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 1. Pastikan dijalankan sebagai root atau dengan sudo
if [ "$(id -u)" -ne 0 ]; then
    log_error "Script ini harus dijalankan dengan hak akses root / sudo."
    echo "Gunakan: sudo bash $0"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

log_info "Memulai instalasi dependensi lengkap di server bersih..."
log_info "Direktori aplikasi: ${APP_DIR}"

export DEBIAN_FRONTEND=noninteractive

# 2. Update sistem dan instal paket utilitas dasar
log_info "1/9. Memperbarui repository dan menginstal perkakas dasar..."
apt-get update -y
apt-get install -y \
    curl \
    wget \
    git \
    unzip \
    zip \
    software-properties-common \
    ca-certificates \
    lsb-release \
    gnupg2 \
    ufw \
    build-essential

# 3. Instalasi PHP 8.2 dan ekstensi yang dibutuhkan Laravel 12 & Filament
log_info "2/9. Menambahkan repository Ondřej Surý dan menginstal PHP 8.2..."
if ! grep -q "ondrej/php" /etc/apt/sources.list /etc/apt/sources.list.d/* 2>/dev/null; then
    add-apt-repository ppa:ondrej/php -y
    apt-get update -y
fi

apt-get install -y \
    php8.2 \
    php8.2-fpm \
    php8.2-cli \
    php8.2-pgsql \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-bcmath \
    php8.2-curl \
    php8.2-zip \
    php8.2-gd \
    php8.2-intl \
    php8.2-redis \
    php8.2-opcache

# Optimasi konfigurasi PHP 8.2
PHP_INI_PATH="/etc/php/8.2/fpm/php.ini"
if [ -f "${PHP_INI_PATH}" ]; then
    sed -i "s/upload_max_filesize = .*/upload_max_filesize = 50M/" "${PHP_INI_PATH}"
    sed -i "s/post_max_size = .*/post_max_size = 50M/" "${PHP_INI_PATH}"
    sed -i "s/memory_limit = .*/memory_limit = 512M/" "${PHP_INI_PATH}"
    sed -i "s/max_execution_time = .*/max_execution_time = 120/" "${PHP_INI_PATH}"
fi
systemctl restart php8.2-fpm
systemctl enable php8.2-fpm

# 4. Instalasi Composer 2
log_info "3/9. Menginstal Composer (Package Manager PHP)..."
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    log_success "Composer berhasil diinstal: $(composer --version)"
else
    composer self-update || true
fi

# 5. Instalasi Node.js 20 LTS & NPM
log_info "4/9. Menginstal Node.js 20 LTS & NPM (NodeSource)..."
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
    log_success "Node.js berhasil diinstal: $(node -v) / NPM: $(npm -v)"
fi

# 6. Instalasi dan Konfigurasi PostgreSQL
log_info "5/9. Menginstal PostgreSQL..."
apt-get install -y postgresql postgresql-contrib
systemctl enable postgresql
systemctl start postgresql

# Buat database dan user postgres default untuk Lukla Operations jika belum ada
log_info "Menyiapkan database 'lukla_operations' dan user 'lukla_app'..."
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname = 'lukla_app'" | grep -q 1 || \
sudo -u postgres psql -c "CREATE USER lukla_app WITH ENCRYPTED PASSWORD 'secret';"

sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname = 'lukla_operations'" | grep -q 1 || \
sudo -u postgres psql -c "CREATE DATABASE lukla_operations OWNER lukla_app;"

sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE lukla_operations TO lukla_app;"
sudo -u postgres psql -c "ALTER USER lukla_app CREATEDB;"

# 7. Instalasi dan Konfigurasi Nginx (Listening on Port 3000)
log_info "6/9. Menginstal dan mengonfigurasi Nginx di port 3000..."
apt-get install -y nginx

NGINX_CONF="/etc/nginx/sites-available/lukla-operations"
cat << 'EOF' > "${NGINX_CONF}"
server {
    listen 3000 default_server;
    listen [::]:3000 default_server;
    server_name _;

    root APP_DIR_PLACEHOLDER/public;
    index index.php index.html;

    client_max_body_size 50M;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 180;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Ganti placeholder direktori aplikasi
sed -i "s|APP_DIR_PLACEHOLDER|${APP_DIR}|g" "${NGINX_CONF}"

# Enable site
ln -sf "${NGINX_CONF}" /etc/nginx/sites-enabled/lukla-operations
rm -f /etc/nginx/sites-enabled/default || true

nginx -t
systemctl restart nginx
systemctl enable nginx

# 8. Instalasi Supervisor (Queue Worker & Background Scheduler)
log_info "7/9. Mengonfigurasi Supervisor untuk antrian background..."
apt-get install -y supervisor

SUPERVISOR_CONF="/etc/supervisor/conf.d/lukla-worker.conf"
cat << EOF > "${SUPERVISOR_CONF}"
[program:lukla-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/lukla-worker.log
stopwaitsecs=3600
EOF

supervisorctl reread || true
supervisorctl update || true

# Setup cron job Laravel schedule
log_info "Menyiapkan cron job untuk Laravel Task Scheduler..."
(crontab -u www-data -l 2>/dev/null | grep -F "artisan schedule:run" || echo "* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1") | crontab -u www-data -

# 9. Setup Aplikasi Laravel (Permissions, Env, Composer, NPM, DB Migration)
log_info "8/9. Menginisialisasi aplikasi Laravel..."
cd "${APP_DIR}"

if [ ! -f .env ]; then
    log_info "Menyalin .env.example menjadi .env..."
    cp .env.example .env
    sed -i "s|APP_URL=.*|APP_URL=http://localhost:3000|g" .env
    sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=pgsql|g" .env
    sed -i "s|DB_HOST=.*|DB_HOST=127.0.0.1|g" .env
    sed -i "s|DB_PORT=.*|DB_PORT=5432|g" .env
    sed -i "s|DB_DATABASE=.*|DB_DATABASE=lukla_operations|g" .env
    sed -i "s|DB_USERNAME=.*|DB_USERNAME=lukla_app|g" .env
    sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=secret|g" .env
fi

# Atur izin folder
log_info "Mengatur kepemilikan dan permission direktori..."
chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

# Tambahkan user saat ini (jika bukan root) ke grup www-data
if [ -n "${SUDO_USER:-}" ] && [ "${SUDO_USER}" != "root" ]; then
    usermod -aG www-data "${SUDO_USER}"
fi

# Instal dependensi PHP (Composer)
log_info "Menjalankan composer install..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Instal dan build asset frontend (Vite / Tailwind)
log_info "Menjalankan npm install & npm run build..."
npm install
npm run build

# Generate APP_KEY jika belum ada
if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate --force
fi

# Storage Link
php artisan storage:link || true

# Migration & Seeder
log_info "Menjalankan migrasi database..."
php artisan migrate --force

# Cache konfigurasi untuk performa produksi
log_info "Membuat cache konfigurasi Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 10. Konfigurasi Firewall
log_info "9/9. Mengonfigurasi firewall UFW..."
ufw allow 22/tcp || true
ufw allow 3000/tcp || true
ufw allow 80/tcp || true
ufw allow 443/tcp || true

SERVER_IP=$(curl -s -4 ifconfig.me || hostname -I | awk '{print $1}')

echo ""
echo "=============================================================================="
log_success "Instalasi Dependensi Server Selesai!"
echo "=============================================================================="
echo -e "Aplikasi Lukla Operations aktif di port 3000:"
echo -e "  URL Akses Utama : ${GREEN}http://${SERVER_IP}:3000${NC}"
echo -e "  Portal Klien    : ${GREEN}http://${SERVER_IP}:3000/portal${NC}"
echo -e "  Admin Panel     : ${GREEN}http://${SERVER_IP}:3000/admin${NC}"
echo ""
echo "Status Service:"
echo "  - PHP 8.2-FPM : systemctl status php8.2-fpm"
echo "  - Nginx       : systemctl status nginx"
echo "  - PostgreSQL  : systemctl status postgresql"
echo "  - Supervisor  : supervisorctl status"
echo "=============================================================================="
