# Lukla Operations Engine — SRS Walkthrough (1)

> Extracted from the source PDF. Page boundaries are preserved for traceability.

**Pages:** 10

## Page 1

SRS v2.0 • 12 OCT 2026
Lukla Operations Engine
Operational Garment Management System for multi-client
manufacturing & dynamic payroll.
L U K L A  M A N U FA C T U R I N G  ×  S N Ø H U N D  —  C I W I D E Y  P R O D U C T I O N
FA C I L I T Y
±70
mesin jahit aktif
6
modul operasional
8
peran pengguna
(RBAC)
Product Owner: Lukla Manufacturing Operations Team & Snøhund Brand Management
Alkademi Software Requirements Specification

## Page 2

01 — KONTEKS BISNIS
Konteks Bisnis & Skema Maklon
Lukla Manufacturing mengoperasikan fasilitas produksi konveksi di Ciwidey dengan kapasitas
dinamis hingga ±70 mesin jahit. Fasilitas ini melayani brand internal Snøhund sekaligus klien
maklon B2B secara paralel. Setiap order ditangani di bawah satu dari tiga skema kerja sama
yang menentukan siapa menyediakan bahan baku.
CMT (Cut, Make, Trim)
Klien menyediakan seluruh bahan baku. Lukla menyediakan jasa cutting, jahit, dan finishing.
Semi-CMT
Klien menyediakan bahan utama. Lukla menyediakan bahan pendukung dan aksesoris.
CMT + Bahan (Full Package)
Lukla menyediakan seluruh bahan baku dan jasa produksi. Digunakan juga untuk order internal
brand Snøhund.
Alkademi Konteks Bisnis

## Page 3

02 — PROBLEM STATEMENT
Masalah Utama yang Diselesaikan
Tiga pain point operasional yang menjadi alasan pembangunan platform.

Risiko Kontaminasi Silang Stok
Bahan baku, WIP, dan barang jadi milik banyak
klien dikelola bersamaan tanpa pemisahan
sistemik. Akibatnya, bahan milik Klien A berisiko
tertukar untuk pesanan Klien B. Sistem saat ini
tidak memiliki mekanisme otomatis untuk
mencegah pencampuran ini.
FR-6.2

Payroll Kompleks & Headcount
Dinamis
Kapasitas penjahit berubah mengikuti volume
order, bukan tetap. Penghitungan upah
borongan mingguan berbasis output 30 menit,
pembagian bonus QC bersama, persentase
uang makan, dan alokasi overhead memerlukan
pencatatan otomatis yang akurat.
FR-10

Kurangnya Penelusuran & Histori
Revisi
Perubahan spesifikasi sample, estimasi
pemakaian bahan, time study, dan catatan
defect belum terpusat secara digital. Tidak ada
versioning yang menelusuri riwayat revisi sample
hingga konversi menjadi order produksi massal.
FR-3.1, FR-4.1
Alkademi Problem Statement

## Page 4

03 — OPERATIONAL FLOW
Alur Operasional 6 Fase
Pipeline sample hingga payroll — setiap fase mengaktifkan modul sistem yang berbeda.
 Setiap fase memiliki kewenangan RBAC tersendiri — lihat slide berikutnya untuk matriks 8 peran pengguna.
Alkademi Operational Flow

Approval & Mass
Order
Penentuan skema
maklon: CMT / Semi-
CMT / Full Package.
FR-4.1
 Line Produksi
Alokasi dinamis ±70
mesin + input output
30 menit.
FR-7
 Payroll & Rekap
Borongan mingguan +
uang makan % +
overhead.
FR-10
 Sample Phase
PO/Titipan & Time
Study per komponen.
FR-1..4
 Gudang & Cutting
Penerimaan bahan
multi-klien + cutting
massal.
FR-5..6
 QC & Barang Jadi
Lolos / Repair / Defect
— memicu pool bonus
QC.
FR-8..9
01 02 03 04 05 06

## Page 5

04 — ACCESS CONTROL
Matriks RBAC — 8 Peran Pengguna
Role-Based Access Control matrix — cakupan, izin, dan pembatasan per peran.
 Owner / Eksekutif Internal
IZIN
PEMBATASAN
Tidak dapat mengubah histori
transaksi yang dikunci.
 Tim Sample / Adis Sample
IZIN
PEMBATASAN
Tidak dapat menyetujui pesanan
massal tanpa konfirmasi.
 Gudang / Warehouse Stok
IZIN
PEMBATASAN
Dilarang mengeluarkan bahan jika ID
Klien/Pesanan tidak cocok.
 Kepala Produksi / Mandor Line
IZIN
PEMBATASAN
Tidak dapat mengubah formula
penggajian atau hasil QC final.
 Penjahit / Tailor Operator
IZIN
PEMBATASAN
Tidak dapat mengedit catatan
output yang sudah diverifikasi.
 QC Inspector Quality
IZIN
PEMBATASAN
Tidak dapat mengubah harga
borongan atau data order klien.
 Finance & Payroll Finance
IZIN
PEMBATASAN
Tidak dapat mengubah fisik stok di
gudang.
 Klien Maklon / Ext… B2B Portal
IZIN
PEMBATASAN
Terbatas hanya pada data client_id milik
sendiri.
Analitik tinggi & konfigurasi sistem•
Ubah rate card•
Catat pemakaian bahan sample•
Input time study per komponen•
Setujui versi sample•
Terima bahan + titipan klien•
Keluarkan bahan ke cutting•
Stok opname•
Atur line + alokasi penjahit•
Tentukan rate card komponen•
Catat output 30 menit•
Lihat tugas komponen•
Laporkan hasil kerja (diverifikasi
mandor)
•
Periksa barang jadi•
Set status lolos/reject/repair•
Catat jenis cacat•
Buat/setujui PO bahan•
Terbitkan invoice•
Hitung gaji mingguan & bulanan•
Pantau status pesanan•
Lihat progres real-time•
Lihat stok bahan titipan sendiri•
Alkademi RBAC Matrix

## Page 6

05 — MULTI-TENANT INVENTORY
Isolasi Stok & Anti-Kontaminasi Silang
Setiap item di gudang — bahan mentah, WIP, dan barang jadi — wajib terikat dengan Tag ID Klien dan
ID Pesanan. Sistem memblokir otomatis setiap upaya pengeluaran bahan yang tidak cocok antara
pemilik dan pesanan.
FR-6.1 — Mandatory Client Tagging
Setiap item stok di gudang — bahan mentah, bahan setengah jadi (WIP), dan barang jadi — wajib
terikat dengan Tag ID Klien dan ID Pesanan sejak momen penerimaan. Tidak ada item yatim di gudang.
Setiap transaksi keluar-masuk wajib membawa dua identifier ini.
FR-6.2 — Aturan Anti-Kontaminasi Silang
Sistem memblokir dan memberi peringatan otomatis jika petugas gudang mencoba mengeluarkan
bahan milik Klien A untuk pesanan Klien B. Blokade terjadi sebelum transaksi tercatat. Selain itu, FR-
6.3 mencatat sisa potongan bahan (waste/scrap) dengan kategori keterangan untuk pelaporan
efisiensi per klien.
 Skenario Pengujian 1
Coba keluarkan bahan atas nama Klien A untuk pesanan Klien B →  sistem menolak + tampilkan
peringatan eksplisit ketidakcocokan.
Alkademi Multi-Tenant Inventory

## Page 7

06 — PRODUCTION TRACKING
Tracking Output 30 Menit & Line Dinamis
Manajemen line produksi paralel dengan kapasitas cair 1–70+ mesin.
±70
MESIN JAHIT AKTIF
kapasitas dinamis, tanpa slot
kaku
30 mnt
INTERVAL OUTPUT
pcs komponen selesai per
interval
Real-time
DASHBOARD LINE
update otomatis tiap input
 FR-7.1 — Alokasi Kapasitas Dinamis
Kepala Produksi mengalokasikan penjahit ke beberapa line paralel tanpa pembatasan jumlah slot.
Mendukung kapasitas hingga ±70 mesin aktif.
 FR-7.2 — Rate Card per Artikel & Klien
Sistem menyimpan kustomisasi ongkos potong (cutting) dan ongkos jahit per komponen/artikel untuk setiap
klien, lengkap dengan riwayat perubahan.
 FR-7.3 / 7.4 — Output 30 Menit & Assembly
Mandor atau penjahit menginput jumlah pcs selesai setiap 30 menit. Sistem mencatat penggabungan
komponen dari beberapa penjahit menjadi produk utuh — status berubah menjadi Menunggu QC.
 Touch-friendly terminal input untuk mandor di
floor produksi — antarmuka besar, refresh real-
time.
Alkademi Production Tracking

## Page 8

07 — PAYROLL ENGINE
Mesin Payroll Fleksibel
Empat komponen perhitungan biaya operasional — borongan, uang makan, pool bonus QC, dan overhead.
FR-10.1 
Setiap Sabtu
PERIODE PENGHITUNGAN
Upah borongan penjahit dihitung setiap hari Sabtu berdasarkan akumulasi
output komponen terverifikasi dikalikan rate jahit per artikel. Tidak ada batasan
jumlah penjahit yang dapat diproses. Output diverifikasi mandor sebelum masuk
perhitungan.
FR-10.2 
10–15%
DARI TOTAL ONGKOS JAHIT
Persentase diinput manual per periode produksi. Fleksibel mengikuti kebijakan
operasional tanpa perubahan kode sistem. Dihitung dari total dana ongkos jahit
yang dihasilkan dalam satu periode.
FR-10.3 
÷ 3
2 MANDOR + 1 KEPALA OPERASIONAL
Setiap produk yang lolos QC dari seluruh klien/order membentuk pool bonus
berdasarkan rate per pcs. Total pool dibagi rata kepada 3 orang penerima — 2
Mandor dan 1 Kepala Operasional. Pool akumulatif lintas order dan lintas klien.
FR-10.4 
Bulanan / Σ  Unit
OVERHEAD PER UNIT PRODUK
Catatan bulanan untuk tagihan listrik, air, internet, dan operasional pabrik Lukla
Manufacturing. Biaya overhead per unit dihitung dengan membagi total biaya
operasional bulanan dengan total seluruh unit produk yang diproduksi di bulan
tersebut.
Alkademi Payroll Engine

## Page 9

08 — UI VIEWS
Layar Tampilan Utama — Minimum Required UI Views
Enam tampilan utama yang wajib ada pada sistem Lukla Operations Engine.
01 
Dashboard Utama Operasional
Overview kondisi pabrik real-time. Menampilkan
line aktif, jumlah penjahit bertugas, progres order
per klien, dan grafik kecepatan produksi interval 30
menit.
02 
Sample & Time Study Tracker
Pengelolaan revisi versi sample dengan versioning
v1, v2, dst. Stopwatch digital untuk time study per
komponen dan tabel pencatatan waktu
pengerjaan.
03 
Gudang Multi-Klien & Stok
Grid inventaris multi-tenant dengan filter per klien.
Form penerimaan bahan titipan dan pemberitahuan
peringatan jika terjadi potensi pencampuran stok.
04 
Terminal Input 30 Menit
Antarmuka sederhana berukuran besar touch-
friendly. Memfasilitasi mandor menginput output
pcs per penjahit setiap 30 menit di area pabrik.
05 
Terminal Inspeksi QC
Pencatatan status pemeriksaan produk (lolos,
repair, scrap), pemilihan kategori defect, dan
pengalihan status barang ke rute tindakan yang
sesuai.
06 
Workspace Payroll & Biaya
Penghitungan gaji borongan mingguan penjahit,
kalkulasi pembagian bonus QC 3−arah,
penghitungan uang makan, dan rekap alokasi
overhead operasional pabrik.
Alkademi UI Views

## Page 10

09 — CLOSING
Tujuan Sistem & Skenario Pengujian
Ringkasan tujuan platform dan empat skenario penerimaan operasional kunci.
 Lukla Operations Engine — Tujuan Sistem
Platform operasional pabrik yang mengintegrasikan pemisahan inventaris
multi-klien, alokasi line produksi dinamis, pencatatan hasil kerja penjahit
tiap 30 menit, kontrol kualitas, serta perhitungan payroll fleksibel.
Dirancang untuk kapasitas cair 1 hingga 70+ mesin aktif tanpa pembatasan
sistemik. Parameter variabel — rate card, persentase uang makan, rate
bonus QC — dapat diubah oleh pengguna yang berwenang tanpa
perubahan kode sistem. Pemisahan data mutlak antar-klien menjadi
fondasi multi-tenant yang menjamin tidak ada kontaminasi silang stok
maupun kebocoran data operasional.
Multi-Tenant Real-time Output Flexible Payroll
Product Owner: Lukla Manufacturing Operations Team & Snøhund Brand Management
01 Proteksi Kontaminasi SilangStok
Coba keluarkan bahan atas nama Klien
A untuk pesanan Klien B. Sistem harus
menolak transaksi dan menampilkan
peringatan eksplisit ketidakcocokan
pemilik.
02 Payroll Headcount Dinamis
Tambah 5 penjahit baru di
pertengahan minggu, input output 30
menit mereka, lalu jalankan kalkulasi
gaji Sabtu. Upah borongan harus
terhitung akurat tanpa batasan jumlah.
03 Pembagian Bonus QC 3−Arah
Input 3.000 unit produk yang lolos QC
dari gabungan beberapa order klien.
Sistem menghitung total pool bonus,
lalu membaginya rata kepada 2
Mandor dan 1 Kepala Operasional.
04 Alur Revisi Sample ke Massal
Buat revisi pada Sample v1, hasilkan v2
yang terhubung, setujui v2 dan
konversi menjadi Order Produksi
Massal dengan Skema Semi-CMT.
Order harus membawa riwayat
spesifikasi v2.
Alkademi Closing — Tujuan & Test Scenarios
