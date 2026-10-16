#!/usr/bin/env bash
# ==============================================================================
# Script Instalasi Otomatis via Docker untuk Server Bersih (Ubuntu / Debian)
# Aplikasi: Lukla Operations Engine
# Port: 0.0.0.0:3000
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

log_info "Memulai instalasi Docker & dependensi di server..."
log_info "Direktori aplikasi: ${APP_DIR}"

# 2. Update sistem dan instal paket dasar
log_info "Memperbarui paket sistem (apt-get update)..."
apt-get update -y
apt-get install -y \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    git \
    ufw

# 3. Instal Docker Engine & Docker Compose Plugin (Official Docker Repo)
if command -v docker &> /dev/null && docker compose version &> /dev/null; then
    log_success "Docker dan Docker Compose plugin sudah terinstal."
else
    log_info "Menambahkan repository resmi Docker..."
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc

    UBUNTU_CODENAME="$(. /etc/os-release && echo "${VERSION_CODENAME:-jammy}")"
    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
      ${UBUNTU_CODENAME} stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt-get update -y
    log_info "Menginstal Docker CE, CLI, containerd, dan Docker Compose plugin..."
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

    systemctl enable docker
    systemctl start docker
    log_success "Docker berhasil diinstal dan dijalankan."
fi

# 4. Berikan izin docker ke user non-root (jika dijalankan via sudo)
if [ -n "${SUDO_USER:-}" ] && [ "${SUDO_USER}" != "root" ]; then
    usermod -aG docker "${SUDO_USER}"
    log_info "Menambahkan user ${SUDO_USER} ke grup 'docker'."
fi

# 5. Konfigurasi Firewall (UFW)
log_info "Mengonfigurasi firewall UFW untuk port 3000 dan 22 (SSH)..."
ufw allow 22/tcp || true
ufw allow 3000/tcp || true
ufw allow 80/tcp || true
ufw allow 443/tcp || true

# 6. Menyiapkan environment (.env)
cd "${APP_DIR}"
if [ ! -f .env ]; then
    log_info "File .env belum ditemukan. Menyalin dari .env.example..."
    cp .env.example .env
fi

# 7. Jalankan container dengan Docker Compose
log_info "Membangun dan menjalankan container (docker compose up -d --build)..."
docker compose down --remove-orphans || true
docker compose up -d --build

# 8. Tunggu hingga service healthy
log_info "Menunggu database dan aplikasi siap..."
sleep 10

# Ambil IP publik server jika ada
SERVER_IP=$(curl -s -4 ifconfig.me || hostname -I | awk '{print $1}')

echo ""
echo "=============================================================================="
log_success "Instalasi Docker Selesai!"
echo "=============================================================================="
echo -e "Aplikasi Lukla Operations berhasil berjalan:"
echo -e "  URL Lokal / Akses Server: ${GREEN}http://${SERVER_IP}:3000${NC}"
echo -e "  Portal Klien            : ${GREEN}http://${SERVER_IP}:3000/portal${NC}"
echo -e "  Admin Panel             : ${GREEN}http://${SERVER_IP}:3000/admin${NC}"
echo ""
echo "Perintah berguna:"
echo "  - Cek status container : cd ${APP_DIR} && docker compose ps"
echo "  - Cek log aplikasi     : cd ${APP_DIR} && docker compose logs -f app"
echo "  - Restart container    : cd ${APP_DIR} && docker compose restart"
echo "=============================================================================="
