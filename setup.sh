#!/usr/bin/env bash
# ==============================================================================
# Lukla Operations - Server Setup & Dependency Installer
# Mendukung Instalasi via Docker atau Native (Ubuntu/Debian)
# Port: 0.0.0.0:3000
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}==============================================================================${NC}"
echo -e "${GREEN}   LUKLA OPERATIONS ENGINE - SERVER DEPENDENCY INSTALLER                       ${NC}"
echo -e "${BLUE}==============================================================================${NC}"
echo "Target Port : 0.0.0.0:3000"
echo "Direktori   : ${SCRIPT_DIR}"
echo ""

if [ "$(id -u)" -ne 0 ]; then
    echo -e "${RED}[ERROR] Script ini membutuhkan hak akses root atau sudo.${NC}"
    echo "Silakan jalankan: sudo bash setup.sh"
    exit 1
fi

CHOICE="${1:-}"

if [ -z "${CHOICE}" ]; then
    echo "Pilih metode instalasi dependensi untuk server bersih Anda:"
    echo "  1) [Rekomendasi] Docker & Docker Compose (Otomatis, Terisolasi, Anti-Konflik)"
    echo "  2) Native Stack (PHP 8.2-FPM, PostgreSQL 16, Nginx port 3000, Node.js 20, Supervisor)"
    echo "  3) Keluar"
    echo ""
    read -rp "Masukkan pilihan [1-3] (default: 1): " INPUT_CHOICE
    CHOICE="${INPUT_CHOICE:-1}"
fi

case "${CHOICE}" in
    1|docker|--docker)
        echo -e "${BLUE}Menjalankan instalasi via Docker...${NC}"
        bash "${SCRIPT_DIR}/scripts/install-docker.sh"
        ;;
    2|native|--native)
        echo -e "${BLUE}Menjalankan instalasi Native Stack...${NC}"
        bash "${SCRIPT_DIR}/scripts/install-native.sh"
        ;;
    3|exit)
        echo "Instalasi dibatalkan."
        exit 0
        ;;
    *)
        echo -e "${RED}Pilihan tidak valid: ${CHOICE}${NC}"
        exit 1
        ;;
esac
