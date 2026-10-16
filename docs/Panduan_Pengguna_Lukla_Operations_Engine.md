# BUKU PANDUAN PENGGUNA & STANDAR OPERASIONAL PROSEDUR (USER GUIDEBOOK)
## LUKLA OPERATIONS ENGINE — Sistem Manajemen Operasional Konveksi & Maklon B2B

- **Versi Dokumen**: 1.0 (Edisi Oktober 2026)
- **Disusun Oleh**: Senior Business Analyst — Lukla Operations Project
- **Target Pembaca**: Owner / Eksekutif Internal, Tim Sample, Gudang & Cutting, Kepala Produksi / Mandor Line, Penjahit / Operator, QC Inspector, Finance & Payroll, serta Mitra Klien Maklon B2B.

---

## DAFTAR ISI

1. **BAB 1: PENDAHULUAN & GAMBARAN UMUM APLIKASI**
   - 1.1 Tujuan Aplikasi Lukla Operations Engine
   - 1.2 Tiga Skema Kerjasama Produksi (*Work Scheme*)
   - 1.3 Arsitektur Panel & Kanal Akses Pengguna
2. **BAB 2: PERAN PENGGUNA (RBAC), TANGGUNG JAWAB, & MATRIKS AKSES**
   - 2.1 Profil 8 Peran Pengguna (*User Roles*)
   - 2.2 Matriks Akses Menu Sidebar (*Full Action Matrix*)
   - 2.3 Prinsip Pemisahan Tugas (*Separation of Duties* / SoD)
   - 2.4 Daftar Akun Demo untuk Simulasi & Pelatihan
3. **BAB 3: PETA ALUR BISNIS END-TO-END & SIKLUS STATUS (STATE MACHINE)**
   - 3.1 Peta Alur Utama (*Master End-to-End Flow*) dari Pemesanan hingga Pelunasan Invoice
   - 3.2 Kamus Status Dokumen di Seluruh Modul
   - 3.3 Matriks Tanggung Jawab Peran per Tahapan Proses (RACI Matrix)
4. **BAB 4: TAHAP 1 — PEMESANAN (ORDER REQUEST) & PENAWARAN HARGA (QUOTATION)**
   - 4.1 Pengajuan Permintaan Pesanan oleh Klien Maklon (Portal Klien)
   - 4.2 Penyusunan & Pengiriman Penawaran Harga (*Quotation*) oleh Owner
   - 4.3 Persetujuan atau Permintaan Revisi Penawaran oleh Klien Maklon
   - 4.4 Pengajuan Pembuatan Sample oleh Owner ke Tim Sample
5. **BAB 5: TAHAP 2 — PEMBUATAN SAMPLE, TIME STUDY, TARIF BORONGAN & KONVERSI ORDER**
   - 5.1 Pembuatan Sample, Spesifikasi, Kebutuhan Bahan, & *Time Study* (SMV) oleh Tim Sample
   - 5.2 Pengunggahan Hasil Fisik Sample, Revisi Berurutan (`v1`, `v2`), & Persetujuan Sample
   - 5.3 Penetapan Usulan Tarif Borongan Komponen oleh Kepala Produksi
   - 5.4 Persetujuan *Rate Card* oleh Owner / Finance
   - 5.5 Konversi Sample Disetujui menjadi Order Produksi Massal (`Siap Produksi`)
6. **BAB 6: TAHAP 3 — PENGELOLAAN GUDANG, PO BAHAN, CUTTING & DISTRIBUSI PRODUKSI**
   - 6.1 Konsep Isolasi Stok Multi-Klien (*Zero Cross-Contamination*) & Label *Tag ID*
   - 6.2 Tujuh Langkah Berurutan pada Menu **Alur Bahan Pesanan**
   - 6.3 Pengajuan & Persetujuan *Purchase Order* (PO) Bahan Baku
   - 6.4 Manajemen Buku Besar Stok Gudang, Mutasi Manual, & Pencatatan Sisa Potongan (*Waste*)
7. **BAB 7: TAHAP 4 — PROSES PRODUKSI, OUTPUT 30 MENIT & PERAKITAN (ASSEMBLY)**
   - 7.1 Pengaturan Line Produksi & Alokasi Penjahit ke Stasiun Kerja
   - 7.2 Memulai Produksi & Penugasan Penjahit per Komponen Artikel
   - 7.3 Pencatatan Output 30 Menit oleh Penjahit (Menggunakan *Stopwatch Interaktif*)
   - 7.4 Verifikasi / Penolakan Output oleh Kepala Produksi & Pengajuan Ulang (*Resubmit*)
   - 7.5 Perakitan Komponen Terverifikasi menjadi Produk Utuh (*Input Perakitan*)
8. **BAB 8: TAHAP 5 — QUALITY CONTROL (QC), PERBAIKAN (REPAIR) & BARANG AFKIR (SCRAP)**
   - 8.1 Pemeriksaan Kualitas di Menu **Antrean Pesanan QC**
   - 8.2 Tiga Keputusan QC (`PASS`, `REPAIR`, `SCRAP`) & Dampaknya terhadap Sistem
   - 8.3 Siklus Penanganan Barang Perbaikan (*Repair*) & Inspeksi Ulang (*Re-inspection*)
   - 8.4 Pencatatan Kategori Cacat & Pemantauan Barang Afkir (*Scrap Log*)
9. **BAB 9: TAHAP 6 — KEUANGAN (FINANCE), PAYROLL MINGGUAN, OVERHEAD & PEMBAYARAN INVOICE**
   - 9.1 Pencatatan Biaya *Overhead* Bulanan & Alokasi *Overhead per Unit*
   - 9.2 Perhitungan, Persetujuan, & Pembayaran *Payroll* Borongan Mingguan
   - 9.3 Kalkulasi & Pembagian 3 Arah *Pool Bonus QC* (2 Mandor + 1 Kepala Operasional)
   - 9.4 Transparansi Pendapatan Pekerja (*Upah Jahit Saya* & *Bonus QC Saya*)
   - 9.5 Siklus Lengkap *Invoice* Klien: Pembuatan Draf, Penerbitan, Konfirmasi Bayar Klien, hingga Verifikasi Lunas & Penutupan Order (`CLOSED`)
10. **BAB 10: INTEGRASI BOT TELEGRAM, SIMULASI KASUS, & TROUBLESHOOTING (FAQ)**
    - 10.1 Panduan Koneksi & Penggunaan `@ClientBot` dan `@OwnerBot`
    - 10.2 Daftar Periksa Kendala Umum (*Troubleshooting Guide*)

---

## BAB 1: PENDAHULUAN & GAMBARAN UMUM APLIKASI

### 1.1 Tujuan Aplikasi Lukla Operations Engine
**Lukla Operations Engine** adalah sistem perencanaan dan pengendalian operasional pabrik garmen/konveksi terpadu yang dirancang untuk mendukung produksi *multi-client* — melayani baik brand internal (**Snøhund**) maupun mitra bisnis maklon (**Klien Maklon B2B**) secara paralel di fasilitas produksi yang sama.

Aplikasi ini menyelesaikan 3 tantangan utama operasional konveksi:
1. **Perlindungan Kepemilikan Material (*Zero Cross-Contamination*)**: Setiap bahan mentah, barang setengah jadi (*WIP*), dan barang jadi (*Finished Goods*) diikat secara ketat ke identitas `Klien` (`client_id`) dan `Nomor Pesanan` (`order_id`). Sistem secara otomatis menolak pengeluaran bahan jika pesanan tujuan tidak cocok dengan pemilik bahan.
2. **Ketertelusuran Hulu ke Hilir (*End-to-End Traceability*)**: Menghubungkan permintaan pesanan awal, surat penawaran (*quotation*), histori revisi sample (`v1`, `v2`), pengukuran waktu jahit (*Time Study* / SMV), tarif borongan (*Rate Card*), alur bahan gudang & *cutting*, output penjahit per 30 menit, perakitan (*assembly*), hingga pemeriksaan QC dan *invoice*.
3. **Akurasi *Payroll* Borongan Dinamis & Keuangan**: Menghitung upah jahit borongan mingguan secara otomatis hanya dari output yang telah **diverifikasi mandor**, menghitung uang makan secara proporsional, mendistribusikan *Pool Bonus QC* secara adil kepada 2 Mandor dan 1 Kepala Operasional, mengalokasikan biaya *overhead* bulanan per unit, serta mengotomatiskan tagihan *invoice* klien.

---

### 1.2 Tiga Skema Kerjasama Produksi (*Work Scheme*)
Setiap pesanan yang masuk ke Lukla wajib diklasifikasikan ke dalam salah satu dari 3 skema kerjasama berikut:

| Skema Kerjasama | Kode Sistem | Penyedia Bahan Utama (Kain) | Penyedia Aksesoris & Trim | Jasa Potong, Jahit, QC & Finishing | Perlakuan *Overhead* pada Invoice |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **1. CMT (*Cut, Make, Trim*)** | `cmt` | **Klien Maklon** | **Klien Maklon** | **Lukla** | Tidak dikenakan alokasi *overhead* pabrik |
| **2. Semi-CMT** | `semi_cmt` | **Klien Maklon** | **Lukla** *(di-tag ke ID Klien & Order)* | **Lukla** | Dikenakan pencatatan internal alokasi *overhead per unit* |
| **3. CMT + Bahan (*Full Package*)** | `full_package` | **Lukla** *(via PO Bahan)* | **Lukla** *(via PO Bahan)* | **Lukla** | Dikenakan pencatatan internal alokasi *overhead per unit* |

---

### 1.3 Arsitektur Panel & Kanal Akses Pengguna
Aplikasi memiliki **2 Panel Web** dan **2 Asisten Bot Telegram**:

1. **Panel Internal Pabrik (`/admin`)**
   - Diakses melalui URL: `http://<domain-aplikasi>/admin`
   - Digunakan oleh **7 peran internal pabrik** (`Owner`, `Tim Sample`, `Gudang`, `Kepala Produksi`, `Penjahit`, `QC Inspector`, dan `Finance & Payroll`).
   - Tampilan menu di bilah samping (*sidebar*) menyesuaikan secara otomatis dengan peran pengguna yang sedang login sehingga layar kerja tetap ringkas dan fokus.
2. **Portal B2B Klien Maklon (`/portal/{kode-klien}`)**
   - Diakses melalui URL: `http://<domain-aplikasi>/portal` (otomatis mengarahkan ke kode tenant klien, misalnya `/portal/MAK-DEMO`).
   - Digunakan khusus oleh peran **Klien Maklon (`klien_maklon`)**.
   - Memiliki isolasi data tingkat tinggi (*tenant isolation*): Klien hanya dapat melihat pesanan, sample, stok titipan, hasil QC, dan tagihan *invoice* milik perusahaannya sendiri.
3. **Integrasi Bot Telegram (`@ClientBot` & `@OwnerBot`)**
   - **Bot Klien (`@ClientBot`)**: Mengirimkan notifikasi penawaran harga & sample, menyediakan tombol persetujuan cepat (*Approve/Reject* langsung dari chat), cek `/orders` dan `/status <nomor_order>`, serta tanya-jawab AI seputar pesanan klien.
   - **Bot Eksekutif (`@OwnerBot`)**: Mengirimkan notifikasi ketika klien menyetujui/menolak penawaran harga, serta menyediakan asisten AI bagi Owner untuk menanyakan ringkasan pabrik, status barang, atau piutang lintas klien.

---

## BAB 2: PERAN PENGGUNA (RBAC), TANGGUNG JAWAB, & MATRIKS AKSES

### 2.1 Profil 8 Peran Pengguna (*User Roles*)

Berikut adalah rincian 8 peran pengguna di dalam aplikasi beserta fungsi dan tanggung jawabnya:

#### 1. Owner / Eksekutif Internal (`owner_eksekutif`)
- **Fungsi Utama**: Pengambil keputusan komersial, pemegang kebijakan tarif/sistem, dan pemantau eksekutif lintas klien.
- **Tanggung Jawab Proses**:
  - Mengelola data pengguna (`Pengguna`) dan membuat token registrasi Telegram.
  - Meninjau permintaan pesanan baru dari klien dan mengirimkan penawaran harga (`Kirim Penawaran`).
  - Menginstruksikan pembuatan sample kepada Tim Sample (`Ajukan Pembuatan Sample`).
  - Menyetujui atau menolak usulan tarif borongan komponen (*Rate Card*) dari Kepala Produksi.
  - Menyetujui *Purchase Order* (PO) pengadaan bahan baku dari Gudang.
  - Memantau seluruh tahapan produksi, QC, *payroll*, *overhead*, dan *invoice*, serta menerima porsi 1/3 dari *Pool Bonus QC* sebagai Kepala Operasional.
- **Batasan Sistem**: Tidak dapat mengubah transaksi pada order yang sudah berstatus terkunci (`COMPLETED`, `CLOSED`, atau `CANCELLED`).

#### 2. Tim Sample / Adis Sample (`tim_sample`)
- **Fungsi Utama**: Pengembang prototipe pakaian (*sample*), penyusun spesifikasi teknis & komposisi bahan (*Bill of Materials*), serta pengukur waktu standar jahit (*Time Study* / SMV).
- **Tanggung Jawab Proses**:
  - Menerima permintaan sample dari Owner melalui widget *Permintaan Sample dari Owner* atau membuat sample mandiri.
  - Mendefinisikan master `Artikel` dan tahapan `Komponen` jahitan (beserta urutan pengerjaannya).
  - Mengisi spesifikasi ukuran pakaian (*specs*), daftar kebutuhan bahan baku & aksesoris beserta penanggung jawabnya (`Ditanggung Klien`, `Ditanggung Perusahaan`, atau `Sebagian`), dan durasi *Time Study* per komponen.
  - Mengunggah foto hasil pembuatan sample fisik dan mengajukan revisi (`v1`, `v2`, dst.) untuk ditinjau klien.
  - Mengonversi sample yang telah disetujui (`APPROVED`) menjadi Order Produksi Massal (`Jadikan Daftar Pesanan`).
- **Batasan Sistem**: Dilarang menyetujui (*ACC*) sample buatannya sendiri (hak persetujuan ada pada Klien Maklon / Owner).

#### 3. Gudang / Warehouse Stok (`gudang`)
- **Fungsi Utama**: Pengelola katalog material, pengadaan PO bahan baku, penerimaan stok berlabel, proses *cutting* (pemotongan kain), pencatatan limbah (*waste*), dan distribusi bahan ke lantai produksi.
- **Tanggung Jawab Proses**:
  - Mengelola master katalog `Bahan & Aksesoris` (SKU, nama, kategori, satuan/UOM).
  - Memantau kekurangan bahan per pesanan di menu `Alur Bahan Pesanan`.
  - Mengajukan *Purchase Order* (`Ajukan PO`) ke Finance untuk bahan yang ditanggung perusahaan (skema *Full Package* / *Semi-CMT*).
  - Mencatat hasil konfirmasi pengiriman bahan dengan klien (`Konfirmasi Klien`) untuk bahan yang ditanggung klien (skema *CMT* / *Semi-CMT*).
  - Menerima kedatangan fisik bahan di gudang (`ACC Kedatangan Gudang`) dan mencetak label fisik (`Cetak Label` bertuliskan `TAG-{KLIEN}-{ORDER}-{ID}`).
  - Memulai proses pemotongan kain (`Mulai Cutting`) yang mengubah status kain dari `Bahan Mentah (RAW)` menjadi `WIP (Setengah Jadi)` di meja potong, serta mencatat sisa potongan kain di menu `Sisa Potongan`.
  - Mengirimkan bahan siap jahit ke lantai produksi (`Kirim ke Produksi`).
- **Batasan Sistem**: Sistem memblokir pengeluaran atau transfer bahan apabila `client_id` dan `order_id` bahan tidak sama dengan pesanan tujuan (*Zero Cross-Contamination*).

#### 4. Kepala Produksi / Mandor Line (`kepala_produksi`)
- **Fungsi Utama**: Pengendali operasional lantai jahit, penentu usulan tarif borongan per komponen, pengatur penugasan penjahit, verifikator output 30 menit, pelaksana perakitan (*assembly*), dan koordinator perbaikan (*repair*).
- **Tanggung Jawab Proses**:
  - Mengusulkan tarif borongan jahit per komponen (`Atur Tarif`) pada menu `Sample Disetujui` dengan berpedoman pada durasi *Time Study*.
  - Memverifikasi dan menerima kiriman bahan potongan dari Gudang (`ACC Penerimaan` pada menu `Alur Bahan Pesanan`), termasuk mencatat jika ada selisih fisik.
  - Mengelola `Line Produksi` dan mengalokasikan penjahit ke line/stasiun kerja.
  - Memulai pengerjaan order di menu `Antrean Produksi` (`Mulai Produksi`) dengan menugaskan satu penjahit penanggung jawab untuk setiap komponen artikel.
  - Memeriksa laporan output 30 menit dari penjahit di menu `Output 30 Menit` (`Verifikasi`, `Verifikasi Semua`, atau `Tolak` beserta alasan).
  - Menggabungkan komponen-komponen yang sudah terverifikasi menjadi unit pakaian utuh (`Input Perakitan`) agar masuk ke antrean QC.
  - Menugaskan petugas perbaikan (`Tugaskan`) dan menandai selesai perbaikan (`Selesai Perbaikan`) pada menu `Perbaikan / Repair`.
- **Batasan Sistem**: Tidak dapat memverifikasi laporan output atas namanya sendiri, tidak dapat menyetujui *Rate Card* yang diusulkannya sendiri, dan tidak dapat mengubah hasil akhir QC maupun rumus *payroll*.

#### 5. Penjahit / Tailor Operator (`penjahit`)
- **Fungsi Utama**: Operator mesin jahit pelaksana pengerjaan komponen pakaian sistem borongan.
- **Tanggung Jawab Proses**:
  - Melihat daftar tugas komponen yang ditugaskan kepadanya di menu `Output 30 Menit`.
  - Menekan tombol `Kerjakan`, menyalakan **Stopwatch Interaktif** (`Mulai` saat menjahit dan `Stop` saat selesai), lalu melaporkan jumlah unit selesai (`1–100 pcs`) beserta catatan.
  - Memperbaiki dan mengirimkan kembali laporan output yang ditolak mandor melalui aksi `Ajukan Ulang`.
  - Memantau perolehan upah borongan, uang makan, dan rincian proyek yang dikerjakan setiap minggu pada menu `Upah Jahit Saya`.
- **Batasan Sistem**: Hanya dapat melaporkan output pada komponen yang ditugaskan kepadanya, tidak dapat mengubah laporan yang sudah berstatus `Terverifikasi (VERIFIED)`, dan tidak dapat melihat data gaji penjahit lain.

#### 6. QC Inspector / Quality (`qc_inspector`)
- **Fungsi Utama**: Pemeriksa mutu pakaian hasil perakitan sebelum masuk ke stok barang jadi (*Finished Goods*).
- **Tanggung Jawab Proses**:
  - Mengelola master `Kategori Cacat` (kode, nama cacat, tingkat keparahan: *Ringan / Sedang / Kritis*).
  - Memeriksa batch perakitan yang siap dicek pada menu `Antrean Pesanan QC` (`Periksa QC`) atau `Inspeksi QC`.
  - Menetapkan hasil pemeriksaan: **Lolos (`PASS`)**, **Perlu Perbaikan (`REPAIR`)**, atau **Afkir / Reject (`SCRAP`)** beserta pemilihan *Kategori Cacat* wajib jika *Repair/Scrap*.
  - Melakukan pemeriksaan ulang (`Inspeksi Ulang`) pada menu `Perbaikan / Repair` terhadap unit yang telah selesai diperbaiki oleh tim produksi.
  - Memantau perolehan bonus kualitas mingguan pada menu `Bonus QC Saya`.
- **Batasan Sistem**: Keputusan QC bersifat *append-only* (catatan peristiwa yang tidak dapat dihapus/diubah sembarangan) dan QC tidak dapat mengubah harga pesanan maupun tarif *payroll*.

#### 7. Finance & Payroll (`finance_payroll`)
- **Fungsi Utama**: Pengelola keuangan pabrik, persetujuan pembelian bahan (PO), persetujuan tarif borongan (*Rate Card*), pencatatan biaya *overhead*, penggajian mingguan (*payroll*), dan penagihan *invoice* klien.
- **Tanggung Jawab Proses**:
  - Mengelola data master `Klien`.
  - Meninjau dan menyetujui *Purchase Order* bahan baku dari Gudang (`Persetujuan PO Bahan` / `Pengajuan PO Gudang`).
  - Meninjau dan menyetujui usulan tarif borongan komponen (`Rate Card`).
  - Mencatat biaya operasional bulanan (listrik, air, internet, dll.) di menu `Biaya Overhead` dan menghitung pembagian per unit di menu `Overhead per Unit`.
  - Membuat periode gaji mingguan di menu `Periode Payroll`, mengatur `% Uang Makan` dan `Tarif Bonus QC`, menjalankan `Hitung`, `Setujui`, hingga `Tandai Dibayar`.
  - Membuat draf `Invoice` klien (kalkulasi otomatis dari output terverifikasi × *Rate Card* + PPN), menerbitkan tagihan (`Terbitkan`), memeriksa bukti transfer dari klien, dan memverifikasi pelunasan (`Tandai Lunas`) serta menutup order (`Tutup Order`).
- **Batasan Sistem**: Dilarang mengubah jumlah stok fisik di gudang atau mem-bypass verifikasi output produksi.

#### 8. Klien Maklon / B2B Portal (`klien_maklon`)
- **Fungsi Utama**: Pelanggan B2B pengguna jasa produksi maklon Lukla.
- **Tanggung Jawab Proses**:
  - Mengajukan pesanan baru (`Pesanan Saya` → `Buat`) dengan memilih skema maklon, jumlah pcs, deskripsi model, dan mengunggah gambar desain pakaian.
  - Meninjau surat penawaran harga dari Owner dan memilih `Setujui Penawaran` atau `Minta Penyesuaian`.
  - Meninjau hasil pembuatan sample di menu `Sample Saya` (melihat foto hasil fisik, spesifikasi, dan histori revisi) lalu memilih `Setujui Sample` atau `Minta Revisi / Tolak`.
  - Memantau progres pengerjaan produksi, tingkat kelulusan QC, dan saldo stok bahan titipan melalui *Dashboard Portal*.
  - Melihat rincian biaya tagihan di menu `Tagihan & Invoice` (`Rincian Biaya`) dan mengirimkan bukti pembayaran bank melalui tombol `Konfirmasi Pembayaran`.
- **Batasan Sistem**: Terikat secara ketat pada `client_id` perusahaannya sendiri; sama sekali tidak dapat melihat data klien lain, upah internal penjahit, maupun catatan biaya *overhead* internal pabrik.

---

### 2.2 Matriks Akses Menu Sidebar (*Full Action Matrix*)

Tabel berikut merangkum menu apa saja yang tampil pada bilah navigasi (*sidebar*) untuk masing-masing peran:

| Grup Menu | Nama Menu di Aplikasi | URL Path | Owner | Tim Sample | Gudang | Kepala Produksi | Penjahit | QC Inspector | Finance | Klien (Portal) |
| :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Operasional** | Daftar Pesanan | `/admin/orders` | ✅ | *(Akses via Sample)* | — | — | — | — | ✅ | — |
| **Operasional** | Sample | `/admin/samples` | ✅ | ✅ | — | — | — | — | — | — |
| **Gudang & Cutting** | Alur Bahan Pesanan | `/admin/order-material-flows` | — | — | ✅ | ✅ | — | — | — | — |
| **Gudang & Cutting** | Pengajuan PO Gudang | `/admin/my-purchase-orders` | — | — | ✅ | — | — | — | ✅ | — |
| **Gudang & Cutting** | Stok Gudang | `/admin/inventory-items` | — | — | ✅ | — | — | — | — | — |
| **Gudang & Cutting** | Pergerakan Stok | `/admin/inventory-movements` | — | — | ✅ | — | — | — | — | — |
| **Gudang & Cutting** | Sisa Potongan | `/admin/waste-records` | *(URL)* | — | ✅ | — | — | — | — | — |
| **Produksi** | Sample Disetujui | `/admin/approved-samples` | ✅ | — | — | ✅ | — | — | — | — |
| **Produksi** | Antrean Produksi | `/admin/production-orders` | — | — | — | ✅ | — | — | — | — |
| **Produksi** | Output 30 Menit (Review & Rakit) | `/admin/output-reviews` | — | — | — | ✅ | — | — | — | — |
| **Produksi** | Output 30 Menit (Terminal Kerja) | `/admin/output-entries` | *(URL)* | — | — | *(URL)* | ✅ | — | — | — |
| **Produksi** | Line Produksi | `/admin/production-lines` | *(URL)* | — | — | *(URL)* | — | — | — | — |
| **Pekerjaan Saya** | Upah Jahit Saya | `/admin/tailor-wages` | — | — | — | — | ✅ | — | — | — |
| **Quality Control** | Antrean Pesanan QC | `/admin/qc-orders` | *(URL)* | — | — | *(URL)* | — | ✅ | — | — |
| **Quality Control** | Inspeksi QC | `/admin/qc-records` | *(URL)* | — | — | — | — | ✅ | — | — |
| **Quality Control** | Perbaikan / Repair | `/admin/repairs` | *(URL)* | — | — | *(URL)* | — | ✅ | — | — |
| **Quality Control** | Bonus QC Saya | `/admin/qc-bonuses` | — | — | — | — | — | ✅ | — | — |
| **Quality Control** | Kategori Cacat | `/admin/defect-categories` | *(URL)* | — | — | — | — | ✅ | — | — |
| **Payroll & Biaya** | Rate Card | `/admin/rate-cards` | ✅ | — | — | *(Via Sample)* | — | — | ✅ | — |
| **Payroll & Biaya** | Periode Payroll | `/admin/payroll-periods` | ✅ | — | — | — | — | — | ✅ | — |
| **Payroll & Biaya** | Persetujuan PO Bahan | `/admin/purchase-orders` | ✅ | — | — | — | — | — | ✅ | — |
| **Payroll & Biaya** | Biaya Overhead | `/admin/overhead-records` | ✅ | — | — | — | — | — | ✅ | — |
| **Payroll & Biaya** | Overhead per Unit | `/admin/overhead-allocations` | *(URL)* | — | — | — | — | — | ✅ | — |
| **Payroll & Biaya** | Invoice | `/admin/invoices` | ✅ | — | — | — | — | — | ✅ | — |
| **Master Data** | Klien | `/admin/clients` | ✅ | — | — | — | — | — | ✅ | — |
| **Master Data** | Pengguna | `/admin/users` | ✅ | — | — | — | — | — | — | — |
| **Master Data** | Bahan & Aksesoris | `/admin/materials` | *(URL)* | — | ✅ | — | — | — | — | — |
| **Portal Klien** | Pesanan Saya | `/portal/{kode}/orders` | — | — | — | — | — | — | — | ✅ |
| **Portal Klien** | Sample Saya | `/portal/{kode}/samples` | — | — | — | — | — | — | — | ✅ |
| **Portal Klien** | Tagihan & Invoice | `/portal/{kode}/invoices` | — | — | — | — | — | — | — | ✅ |

---

### 2.3 Prinsip Pemisahan Tugas (*Separation of Duties* / SoD)
Untuk mencegah kecurangan (*fraud*) dan kesalahan operasional, aplikasi menegakkan 5 aturan pemisahan wewenang:
1. **Pembuatan vs Persetujuan Sample**: `Tim Sample` menyusun sample dan revisinya, namun hanya `Klien Maklon` (atau `Owner` atas nama kesepakatan) yang dapat menekan tombol `Setujui Sample`.
2. **Pengusulan vs Persetujuan Tarif Borongan (*Rate Card*)**: `Kepala Produksi` mengusulkan tarif per komponen berdasarkan *Time Study* (status `Menunggu Persetujuan`), namun hanya `Owner` atau `Finance` yang dapat menyetujuinya menjadi `Disetujui`.
3. **Pengajuan vs Persetujuan PO Bahan**: `Gudang` mengajukan PO pembelian bahan perusahaan, namun hanya `Finance` atau `Owner` yang dapat menyetujui PO tersebut.
4. **Pengiriman Gudang vs Penerimaan Produksi**: `Gudang` menekan `Kirim ke Produksi`, namun perpindahan tanggung jawab stok baru sah ketika `Kepala Produksi` menekan `ACC Penerimaan` dan memverifikasi fisik barang.
5. **Pencatatan vs Verifikasi Output 30 Menit**: `Penjahit` melaporkan hasil jahitan per interval, namun hanya `Kepala Produksi / Mandor` yang dapat memverifikasinya (`ProductionService` secara eksplisit memblokir *self-verification*).

---

### 2.4 Daftar Akun Demo untuk Simulasi & Pelatihan
Sistem telah dilengkapi data akun siap pakai (`DatabaseSeeder`) dengan kata sandi standar `password`:

| Peran Pengguna | Nama Akun | Email Login | Panel Login |
| :--- | :--- | :--- | :--- |
| **Owner / Eksekutif Internal** | Owner / Eksekutif Internal | `owner_eksekutif@lukla.test` | `/admin` |
| **Tim Sample / Adis Sample** | Tim Sample / Adis Sample | `tim_sample@lukla.test` | `/admin` |
| **Gudang / Warehouse Stok** | Gudang / Warehouse Stok | `gudang@lukla.test` | `/admin` |
| **Kepala Produksi / Mandor Line** | Kepala Produksi / Mandor Line | `kepala_produksi@lukla.test` | `/admin` |
| **Penjahit / Tailor Operator** | Penjahit / Tailor Operator | `penjahit@lukla.test` | `/admin` |
| **Penjahit Tambahan 1** | Penjahit 1 (Agus) | `penjahit1@lukla.test` | `/admin` |
| **Penjahit Tambahan 2** | Penjahit 2 (Bambang) | `penjahit2@lukla.test` | `/admin` |
| **Penjahit Tambahan 3** | Penjahit 3 (Citra) | `penjahit3@lukla.test` | `/admin` |
| **QC Inspector / Quality** | QC Inspector / Quality | `qc_inspector@lukla.test` | `/admin` |
| **Finance & Payroll** | Finance & Payroll | `finance_payroll@lukla.test` | `/admin` |
| **Klien Maklon (PT Maklon Demo)** | Klien Maklon Demo | `klien@lukla.test` | `/portal/MAK-DEMO` |

---

## BAB 3: PETA ALUR BISNIS END-TO-END & SIKLUS STATUS (STATE MACHINE)

### 3.1 Peta Alur Utama (*Master End-to-End Flow*)
Secara berurutan, siklus hidup operasional satu pesanan di Lukla Operations Engine berjalan melalui **6 Fase Utama (18 Langkah Kerja)** dari awal pemesanan hingga pelunasan *invoice*:

```
[FASE 1: PEMESANAN & PENAWARAN HARGA (QUOTATION)]
  Langkah 1 (Klien Maklon)   : Buat Permintaan Pesanan + Upload Desain di Portal  --> Status Order: REQUESTED
  Langkah 2 (Owner)          : Hitung Biaya & Kirim Surat Penawaran (Quotation)  --> Status Order: QUOTED
  Langkah 3 (Klien Maklon)   : Setujui Penawaran (atau Minta Penyesuaian)         --> Status Order: QUOTE_APPROVED
  Langkah 4 (Owner)          : Klik "Ajukan Pembuatan Sample"                     --> Masuk Antrean Tim Sample

        │
        ▼
[FASE 2: PEMBUATAN SAMPLE, TIME STUDY, RATE CARD & KONVERSI ORDER]
  Langkah 5 (Tim Sample)     : Buat Sample, Artikel, Komponen, Bahan & Time Study --> Status Sample: IN_REVIEW (v1)
  Langkah 6 (Tim Sample)     : Upload Foto Hasil Sample Fisik                     --> Siap Direview Klien/Owner
  Langkah 7 (Klien / Owner)  : Setujui Sample (atau Tolak -> Buat Revisi v2)      --> Status Sample: APPROVED
  Langkah 8 (Kepala Produksi): Klik "Atur Tarif" Borongan Komponen                --> Status RateCard: PENDING
  Langkah 9 (Owner / Finance): Klik "Setujui Rate Card"                           --> Status RateCard: APPROVED
  Langkah 10 (Sample/Mandor) : Klik "Jadikan Daftar Pesanan" (Konversi Massal)    --> Status Order: READY_FOR_PRODUCTION

        │
        ▼
[FASE 3: PENGELOLAAN GUDANG, PO BAHAN, CUTTING & PENGIRIMAN KE PRODUKSI]
  Langkah 11 (Gudang/Finance): Ajukan PO Bahan Lukla (Disetujui Finance) &/atau   --> Status Alur Bahan: ITEM_REQUEST /
                               Konfirmasi Bahan Klien                                 CLIENT_CONFIRMATION / PO_AND_CLIENT
  Langkah 12 (Gudang)        : Klik "ACC Kedatangan Gudang" + Cetak Label TAG     --> Status Alur Bahan: STANDBY
  Langkah 13 (Gudang)        : Klik "Mulai Cutting" (RAW -> WIP) & Catat Waste    --> Status Alur Bahan: PREPARING_DELIVERY
  Langkah 14 (Gudang)        : Klik "Kirim ke Produksi" (Terbit Dokumen DSP-...)  --> Status Alur Bahan: DISPATCHED
  Langkah 15 (Kepala Prod.)  : Klik "ACC Penerimaan" (Verifikasi Fisik Bahan)     --> Status Alur Bahan: RECEIVED_BY_PRODUCTION

        │
        ▼
[FASE 4: PROSES PRODUKSI, OUTPUT 30 MENIT & PERAKITAN (ASSEMBLY)]
  Langkah 16 (Kepala Prod.)  : Klik "Mulai Produksi" & Tugaskan Penjahit/Komponen --> Status Order: IN_PRODUCTION
  Langkah 17 (Penjahit)      : Klik "Kerjakan" (Stopwatch Mulai-Stop & Kirim Pcs) --> Status Output: SUBMITTED
  Langkah 18 (Kepala Prod.)  : Klik "Verifikasi" / "Verifikasi Semua" Output      --> Status Output: VERIFIED (Masuk Payroll)
  Langkah 19 (Kepala Prod.)  : Klik "Input Perakitan" (Gabung Komponen Jadi Unit) --> Status Order: IN_QC

        │
        ▼
[FASE 5: QUALITY CONTROL (QC), PERBAIKAN (REPAIR) & BARANG AFKIR (SCRAP)]
  Langkah 20 (QC Inspector)  : Klik "Periksa QC" pada Batch Perakitan:
                               ├─ Jika PASS   : Masuk Finished Goods (+1 Pool Bonus QC). Saat 100% Lolos --> Order: COMPLETED
                               ├─ Jika REPAIR : Buat Tiket Repair -> Mandor "Tugaskan" & "Selesai Perbaikan" -> QC "Inspeksi Ulang"
                               └─ Jika SCRAP  : Wajib Kategori Cacat -> Tercatat Otomatis di WasteRecord (Afkir)

        │
        ▼
[FASE 6: KEUANGAN (FINANCE), PAYROLL MINGGUAN, OVERHEAD & PELUNASAN INVOICE]
  Langkah 21 (Finance)       : Catat Biaya Overhead Bulanan & Alokasi Overhead/Unit
  Langkah 22 (Finance)       : Buat Periode Payroll -> "Hitung" (Upah Borongan + Uang Makan + Pool Bonus QC ÷ 3)
                               -> "Setujui" -> "Tandai Dibayar" (PAID)
  Langkah 23 (Finance)       : Buat Invoice Order (Auto-Hitung Ongkos Jahit + PPN) -> Klik "Terbitkan" (Status: ISSUED)
  Langkah 24 (Klien Maklon)  : Buka Portal "Tagihan & Invoice" -> Klik "Konfirmasi Pembayaran" + Upload Bukti Transfer
                               (Status Invoice: PAYMENT_CONFIRMED)
  Langkah 25 (Finance)       : Cek Bukti Transfer di Detail Invoice -> Klik "Tandai Lunas" (Status Invoice: PAID)
                               --> Otomatis Mengubah Stok Menjadi FINISHED & Menutup Order Menjadi CLOSED!
```

---

### 3.2 Kamus Status Dokumen di Seluruh Modul

#### A. Status Pesanan (`OrderStatus`)
| Kode | Label di Aplikasi | Arti & Kondisi Pemicu |
| :--- | :--- | :--- |
| `requested` | **Permintaan Klien** | Klien baru mengajukan pesanan dari Portal (atau setelah klien meminta penyesuaian ulang atas penawaran). |
| `quoted` | **Menunggu Persetujuan Klien** | Owner telah mengisi perkiraan biaya dan mengirim surat penawaran harga ke klien. |
| `quote_approved` | **Penawaran Disetujui** | Klien telah menyetujui penawaran harga melalui Portal atau Telegram Bot. |
| `draft` | **Draf** | Order dibuat manual sebagai draf internal sebelum dikonfirmasi. |
| `confirmed` | **Dikonfirmasi** | Order telah dikonfirmasi dari sample namun belum masuk antrean siap produksi. |
| `ready_for_production` | **Siap Produksi** | Sample yang telah `Disetujui` dikonversi ke produksi massal (`Jadikan Daftar Pesanan`). |
| `in_production` | **Dalam Produksi** | Kepala Produksi telah menekan `Mulai Produksi` dan menetapkan penjahit per komponen (atau penjahit mulai mengirim output). |
| `in_qc` | **Dalam QC** | Seluruh target pesanan telah dirakit (`Input Perakitan`) atau pemeriksaan QC pertama telah dimulai. |
| `completed` | **Selesai** | 100% kuantitas pesanan telah dinyatakan **Lolos (`PASS`)** oleh QC Inspector (order mulai dikunci dari edit). |
| `closed` | **Ditutup** | Invoice pesanan telah ditandai **Lunas (`PAID`)** oleh Finance (atau ditutup manual setelah seluruh invoice lunas). |
| `cancelled` | **Dibatalkan** | Pesanan dibatalkan oleh Owner disertai alasan pembatalan. |

#### B. Status Alur Bahan Gudang Level 1 (`OrderMaterialStatus`) & Level 2 (`ComponentMaterialStatus`)
| Urutan | Status Order (Level 1) | Status Item Bahan (Level 2) | Penjelasan Operasional |
| :---: | :--- | :--- | :--- |
| 1 | `Kosong (Belum Ada Bahan)` | `Kosong` | Order baru masuk ke daftar alur bahan, belum ada pengajuan PO maupun konfirmasi klien. |
| 2 | `Pengajuan Barang (PO)` | `Dalam Pengajuan PO` | Gudang telah klik `Ajukan PO` untuk bahan tanggungan perusahaan. |
| 3 | `Konfirmasi Klien` | `Konfirmasi Klien` | Gudang telah klik `Konfirmasi Klien` untuk bahan kiriman klien. |
| 4 | `Dalam PO & Konfirmasi Klien` | *(Kombinasi PO & Klien)* | Berlaku pada skema *Semi-CMT* ketika PO aksesoris Lukla dan konfirmasi kain klien berjalan bersamaan. |
| 5 | `Pengiriman Barang` | `Dalam Pengiriman` | Sebagian bahan telah diterima lewat `ACC Kedatangan Gudang`, namun masih ada item yang belum lengkap. |
| 6 | `Standby (Bahan Lengkap)` | `Standby di Gudang` | **100% kebutuhan bahan dan aksesoris telah tiba lengkap di gudang.** Tombol `Mulai Cutting` terbuka. |
| 7 | `Dalam Persiapan Pengiriman (Cutting)` | `Dalam Proses Cutting` *(Kain)* | Gudang telah klik `Mulai Cutting`; kain mentah (`RAW`) berubah menjadi potongan (`WIP`). Tombol `Kirim ke Produksi` terbuka. |
| 8 | `Sedang Dikirim` | `Sedang Dikirim` | Gudang telah klik `Kirim ke Produksi` (dokumen `DSP-...` terbit). Menunggu konfirmasi Kepala Produksi. |
| 9 | `Diterima Bagian Produksi` | `Diterima Produksi` | Kepala Produksi telah klik `ACC Penerimaan` dan memverifikasi jumlah fisik bahan di lantai jahit. |

---

## BAB 4: TAHAP 1 — PEMESANAN (ORDER REQUEST) & PENAWARAN HARGA (QUOTATION)

Tahap pertama dimulai ketika **Klien Maklon** mengajukan kebutuhan produksi melalui Portal B2B, dilanjutkan dengan pembuatan surat penawaran (*Quotation*) oleh **Owner**, hingga kesepakatan harga tercapai.

---

### 4.1 Proses 1.1: Pengajuan Permintaan Pesanan oleh Klien Maklon

- **Pelaksana (Role)**: `Klien Maklon` (`klien_maklon`)
- **Lokasi Menu**: Panel Portal Klien (`/portal/{kode-klien}`) → Menu Sidebar **Pesanan Saya** → Klik tombol **`Buat`** di pojok kanan atas.
- **Prasyarat**: Akun klien telah aktif dan terikat pada profil perusahaan `Client`.

#### Tabel Input Formulir Permintaan Pesanan:
| Nama Field di Layar | Tipe Input | Wajib / Opsional | Penjelasan & Contoh Pengisian |
| :--- | :--- | :---: | :--- |
| **Skema Pengerjaan** (`work_scheme`) | Pilihan (*Select*) | **Wajib** | Pilih salah satu: `CMT (Cut, Make, Trim)`, `Semi-CMT`, atau `CMT + Bahan (Full Package)`. |
| **Perkiraan Jumlah** (`quantity`) | Angka (*pcs*) | **Wajib** | Jumlah unit pakaian yang ingin diproduksi (minimal `1` pcs, contoh: `500` pcs). |
| **Harapan Selesai** (`due_date`) | Tanggal (*Date*) | Opsional | Target tanggal pengiriman barang jadi yang diharapkan klien. |
| **Deskripsi Permintaan** (`description`) | Teks Panjang | **Wajib** | Rincian model pakaian, pembagian ukuran (S/M/L/XL), jenis kain, warna, dan instruksi jahitan. |
| **Gambar Design** (`design_images`) | Unggah Gambar | **Wajib** | Unggah 1 s/d 5 file gambar desain/sketsa/tech-pack (JPG/PNG, maks 5 MB per file). |
| **Catatan Tambahan** (`notes`) | Teks Panjang | Opsional | Informasi tambahan mengenai pengemasan (*packaging*) atau pengiriman bahan. |

#### Langkah Penggunaan di Aplikasi:
1. Login ke Portal Klien (`/portal/{kode-klien}`), lalu klik menu **Pesanan Saya** di sebelah kiri.
2. Klik tombol **`Buat`** di bagian kanan atas halaman.
3. Pilih **Skema Pengerjaan**, masukkan **Perkiraan Jumlah (pcs)**, **Harapan Selesai**, dan tulis **Deskripsi Permintaan** secara lengkap.
4. Pada kotak **Gambar Design**, tarik dan lepaskan (*drag & drop*) file gambar desain pakaian hingga proses unggah selesai.
5. Klik tombol **`Buat`** di bagian bawah formulir untuk mengirim permintaan.

#### Output yang Dihasilkan:
- Sistem menghasilkan nomor pesanan otomatis dengan format `ORD-YYYYMMDD-XXXX` (contoh: `ORD-20261016-A1B2`).
- Status pesanan tercatat sebagai **`Permintaan Klien` (`REQUESTED`)**.
- Pesanan langsung tampil di daftar **Daftar Pesanan** (`/admin/orders`) milik **Owner** di Panel Admin.

---

### 4.2 Proses 1.2: Penyusunan & Pengiriman Penawaran Harga (*Quotation*) oleh Owner

- **Pelaksana (Role)**: `Owner / Eksekutif Internal` (`owner_eksekutif`)
- **Lokasi Menu**: Panel Admin (`/admin`) → Grup **Operasional** → Menu **Daftar Pesanan** → Klik baris pesanan atau klik tombol **`Lihat` / `Ubah`** → Klik tombol **`Kirim Penawaran`** di bagian atas halaman.
- **Prasyarat**: Status pesanan adalah `Permintaan Klien (REQUESTED)` atau `Menunggu Persetujuan Klien (QUOTED)` (jika ingin memperbarui penawaran).

#### Tabel Input Modal "Kirim Penawaran ke Klien":
| Nama Field di Layar | Tipe Input | Wajib / Opsional | Penjelasan & Contoh Pengisian |
| :--- | :--- | :---: | :--- |
| **Perkiraan Biaya Total** (`estimated_cost`) | Nominal Rupiah (`Rp`) | **Wajib** | Total nilai penawaran untuk seluruh kuantitas order (contoh: `25000000` untuk Rp 25.000.000). |
| **Rincian Penawaran** (`quote_notes`) | Teks Panjang | Opsional *(Sangat Disarankan)* | Klik tautan **`Gunakan template surat`** di pojok kanan atas field ini untuk menghasilkan draf surat penawaran resmi secara otomatis. |

#### Langkah Penggunaan di Aplikasi:
1. Login sebagai **Owner** di `/admin`, buka menu **Daftar Pesanan**.
2. Klik pesanan berstatus `Permintaan Klien`. Jika ingin memeriksa file desain dari klien terlebih dahulu, klik tombol **`Unduh Gambar Desain`** di bagian atas halaman (sistem akan mengunduh gambar atau bundel `.zip` jika lebih dari satu file).
3. Klik tombol **`Kirim Penawaran`** berwarna kuning (*warning*) di pojok kanan atas.
4. Pada jendela *pop-up* (modal) yang terbuka:
   - Isi **Perkiraan Biaya Total** (dalam Rupiah).
   - Klik tombol bantu **`Gunakan template surat`** di sebelah label *Rincian Penawaran*. Sistem secara otomatis akan menyusun naskah surat penawaran resmi lengkap dengan nomor surat (`QUO/.../LUKLA/...`), rincian skema, harga per pcs (`Biaya Total ÷ Jumlah Pcs`), termin pembayaran (DP 50%, Pelunasan 50%), dan masa berlaku 14 hari.
   - Sesuaikan isi surat jika diperlukan, lalu klik tombol **`Kirim Penawaran`**.

#### Output yang Dihasilkan:
- Status pesanan berubah dari `Permintaan Klien (REQUESTED)` menjadi **`Menunggu Persetujuan Klien` (`QUOTED`)**.
- Kolom `quoted_at` dan `quoted_by` tercatat untuk keperluan audit.
- **Notifikasi Otomatis**: Sistem mengirimkan pesan Telegram ke akun **Klien Maklon** melalui `@ClientBot` berisi nilai penawaran dan tombol persetujuan interaktif.
- Pada halaman detail pesanan di Portal Klien maupun Panel Admin, surat penawaran tampil dalam kotak dokumen resmi yang rapi.

---

### 4.3 Proses 1.3: Persetujuan atau Permintaan Penyesuaian Penawaran oleh Klien

- **Pelaksana (Role)**: `Klien Maklon` (`klien_maklon`)
- **Lokasi Menu**:
  - **Via Web Portal**: `/portal/{kode-klien}` → Menu **Pesanan Saya** → Klik pesanan berstatus `Menunggu Persetujuan Klien`.
  - **Via Telegram Bot**: Langsung menekan tombol inline pada pesan notifikasi `@ClientBot`.
- **Prasyarat**: Status pesanan adalah `Menunggu Persetujuan Klien (QUOTED)`.

#### Pilihan Aksi, Input, dan Output:
| Tombol Aksi yang Diklik | Input yang Diminta | Output & Perubahan Status Sistem |
| :--- | :--- | :--- |
| **`Setujui Penawaran`** *(Tombol Hijau)* | Konfirmasi klik pada modal *"Setujui penawaran ini?"* | 1. Status pesanan berubah menjadi **`Penawaran Disetujui` (`QUOTE_APPROVED`)**.<br>2. Waktu persetujuan (`quote_approved_at`) tercatat.<br>3. `@OwnerBot` mengirim notifikasi Telegram ke **Owner** bahwa penawaran telah disetujui klien. |
| **`Minta Penyesuaian`** *(Tombol Merah)* | **Catatan untuk Lukla** (`reason` — *Wajib diisi*, contoh: *"Mohon penyesuaian harga ke Rp 45.000/pcs untuk qty 600 pcs"*) | 1. Status pesanan kembali menjadi **`Permintaan Klien` (`REQUESTED`)**.<br>2. Catatan revisi dari klien ditambahkan ke riwayat catatan pesanan.<br>3. `@OwnerBot` mengirim notifikasi ke **Owner** beserta alasan penolakan agar Owner dapat klik **`Perbarui Penawaran`**. |

---

### 4.4 Proses 1.4: Pengajuan Pembuatan Sample oleh Owner ke Tim Sample

- **Pelaksana (Role)**: `Owner / Eksekutif Internal` (`owner_eksekutif`)
- **Lokasi Menu**: Panel Admin (`/admin`) → Menu **Daftar Pesanan** → Buka halaman pesanan yang berstatus `Penawaran Disetujui` → Klik tombol **`Ajukan Pembuatan Sample`** di bagian atas.
- **Prasyarat**: Status pesanan adalah `Penawaran Disetujui (QUOTE_APPROVED)` dan belum memiliki sample (`sample_requested_at === null`).

#### Langkah Penggunaan & Output:
1. Pada halaman detail pesanan yang penawarannya sudah disetujui klien, **Owner** mengklik tombol **`Ajukan Pembuatan Sample`** berwarna biru (*info*) lalu mengonfirmasi modal.
2. **Output**:
   - Sistem mencatat waktu `sample_requested_at = now()`.
   - Pesanan tersebut langsung muncul di tabel widget **"Permintaan Sample dari Owner"** di bagian paling atas halaman menu **Sample** milik **Tim Sample**.

---

## BAB 5: TAHAP 2 — PEMBUATAN SAMPLE, TIME STUDY, TARIF BORONGAN & KONVERSI ORDER

Setelah penawaran disetujui dan Owner mengajukan permintaan sample, proses beralih ke **Tim Sample** untuk membuat prototipe fisik, mencatat waktu pengerjaan (*Time Study*), meminta persetujuan klien, diikuti penetapan **Tarif Borongan Komponen (*Rate Card*)** oleh **Kepala Produksi** dan persetujuan oleh **Owner/Finance**, hingga konversi menjadi **Order Produksi Massal**.

---

### 5.1 Proses 2.1: Pembuatan Sample, Komponen, Kebutuhan Bahan & *Time Study* (SMV)

- **Pelaksana (Role)**: `Tim Sample / Adis Sample` (`tim_sample`)
- **Lokasi Menu**: Panel Admin (`/admin`) → Grup **Operasional** → Menu **Sample** (`/admin/samples`).
- **Dua Cara Memulai**:
  1. **Dari Widget "Permintaan Sample dari Owner" (Direkomendasikan)**: Di bagian atas halaman Sample, klik tombol **`Buat Sample`** pada baris order yang diajukan Owner. Seluruh data Klien, Nomor Order, Kode Sample (`SMP-ORD-...`), Judul, Deskripsi, dan Gambar Desain akan **terisi otomatis**.
  2. **Mandiri**: Klik tombol **`Buat Sample Mandiri`** di pojok kanan atas halaman.

#### Tabel Input Formulir Pembuatan Sample (`SampleForm`):
| Bagian Form | Nama Field di Layar | Wajib / Opsional | Penjelasan & Contoh Pengisian |
| :--- | :--- | :---: | :--- |
| **1. Informasi Sample** | **Dari Permintaan Order** (`order_id`) | Opsional | Otomatis terpilih jika masuk dari tombol *Buat Sample* pada widget permintaan Owner. |
| | **Klien** (`client_id`) | **Wajib** | Nama klien pemilik sample (otomatis terkunci mengikuti klien pada order). |
| | **Kode Artikel Baru** (`new_article_code`) | **Wajib** *(jika order baru)* | Kode unik artikel/model pakaian (contoh: `JKT-PARKA-01`). |
| | **Nama Artikel Baru** (`new_article_name`) | **Wajib** *(jika order baru)* | Nama produk pakaian (contoh: `Jaket Parka Outdoor Waterproof`). |
| | **Kategori Artikel** (`new_article_category`) | Opsional | Kategori produk (contoh: `Outerwear`, `Kemeja`, `Celana`). |
| | **Komponen & Time Study** (`components` — *Repeater*) | **Wajib** (Min. 1 baris) | Daftar bagian/operasi jahit yang menyusun pakaian ini. Setiap baris berisi:<br>• **Kode Komponen** (`code`, contoh: `KRH`)<br>• **Nama Komponen** (`name`, contoh: `Pasang Kerah`)<br>• **Urutan** (`sequence`, angka urutan proses: `1, 2, 3...`)<br>• **Time Study (menit)** (`duration_minutes`, durasi waktu jahit standar hasil pengukuran stopwatch fisik, contoh: `4.5` menit). |
| | **Kode Sample** (`code`) | **Wajib** | Kode unik dokumen sample (otomatis `SMP-{NOMOR_ORDER}`). |
| | **Judul** (`title`) | **Wajib** | Nama identifikasi sample. |
| **2. Revisi Awal (v1)** | **Spesifikasi & Ukuran** (`initial_specs` — *KeyValue*) | Opsional | Pasangan Parameter dan Nilai ukuran (contoh: `Lingkar Dada` = `108 cm`, `Panjang Badan` = `72 cm`). |
| | **Kebutuhan Bahan Baku & Aksesoris Fisik** (`initial_material_items` — *Repeater*) | **Wajib** | Daftar bahan per **1 pcs** pakaian (akan dikalikan jumlah order saat masuk gudang):<br>• **Pilih dari Katalog Gudang** (`material_id`): Pilih master bahan agar nama & satuan terisi otomatis.<br>• **Nama Bahan / Aksesoris** (`name`): Nama item (contoh: `Kain Taslan Waterproof`).<br>• **Jumlah** (`qty`): Kebutuhan per 1 pcs pakaian (contoh: `1.5`).<br>• **Satuan** (`unit`): `meter`, `yard`, `pcs`, `roll`, `lusin`, `kg`, `cone`, atau `set`.<br>• **Tanggungan** (`responsibility`): Pilih **`Ditanggung Klien`**, **`Ditanggung Perusahaan (Lukla)`**, atau **`Ditanggung Sebagian`**. |
| | **Langsung ajukan untuk persetujuan klien** (`initial_submit`) | Toggle | Biarkan **Aktif (Hijau)** agar status sample langsung menjadi `Menunggu Persetujuan (IN_REVIEW)`. |
| | **Catatan Revisi** (`initial_notes`) | Opsional | Catatan konstruksi pola/jahitan pada revisi `v1`. |

#### Output yang Dihasilkan:
- Sistem membuat data master **`Article`** dan seluruh **`Component`** jahitnya secara otomatis.
- Membuat dokumen **`Sample`** beserta **`SampleRevision` v1** (berstatus `Menunggu Persetujuan / IN_REVIEW`).
- Menyimpan catatan **`TimeStudy`** (dalam satuan detik = `menit × 60`) untuk setiap komponen artikel sebagai acuan penetapan tarif borongan.
- Menautkan `Order` ke `Article`, `Sample`, dan `SampleRevision` yang baru dibuat.

---

### 5.2 Proses 2.2: Unggah Foto Hasil Sample, Revisi Berurutan (`v1`, `v2`), & Persetujuan Sample

Setelah pakaian sample selesai dijahit secara fisik oleh Tim Sample, hasil fotonya diunggah untuk ditinjau oleh **Klien Maklon** (atau **Owner**).

#### A. Mengunggah Foto Hasil Sample Fisik (oleh Tim Sample)
1. Buka menu **Sample** (`/admin/samples`) → Klik sample terkait untuk masuk ke halaman Ubah/Detail.
2. Gulir ke bagian bawah pada tabel tab **Revisi** (`RevisionsRelationManager`).
3. Pada baris revisi aktif (`v1`), klik tombol **`Upload Hasil Sample`** berwarna biru.
4. Unggah foto pakaian fisik yang telah jadi (JPG/PNG/WebP, maks 5 MB), lalu klik **Simpan**. Foto ini akan langsung terlihat oleh Klien di Portal Klien.

#### B. Meninjau & Menyetujui / Menolak Sample (oleh Klien Maklon di Portal atau Owner)
- **Melalui Portal Klien (`/portal/{kode-klien}/samples`)**:
  1. Klien membuka menu **Sample Saya** → Klik baris sample berstatus `Menunggu Persetujuan`.
  2. Klien memeriksa **Foto Hasil Pembuatan Sample**, **Spesifikasi Teknis**, dan **Riwayat Revisi**.
  3. Di bagian kanan atas halaman, tersedia 2 tombol keputusan:
     - Klik **`Setujui Sample`** (Hijau): Jika fisik sample sudah sesuai standar.
     - Klik **`Minta Revisi / Tolak`** (Merah): Jika masih ada bagian yang perlu diperbaiki. Klien wajib mengisi **Catatan Revisi / Alasan Penolakan** (contoh: *"Kerah terlalu tinggi 1 cm, mohon diturunkan"*).
- **Melalui Panel Admin (`/admin/samples`) atau Telegram Bot (`@ClientBot`)**:
  - `Owner` dapat menekan tombol **`Setujui`** atau **`Tolak`** pada baris tabel **Revisi** di halaman Sample, atau Klien dapat menekan tombol inline pada pesan Telegram.

#### C. Membuat Revisi Baru `v2`, `v3`, dst. Jika Sample Ditolak (oleh Tim Sample)
1. Jika sample ditolak (`status = Ditolak / REJECTED`), anggota `Tim Sample` akan menerima **Notifikasi Sistem** berisi alasan penolakan.
2. Buka kembali halaman sample tersebut, gulir ke tabel **Revisi**, lalu klik tombol header **`Buat Revisi Baru`**.
3. Sistem secara otomatis menyalin daftar bahan dari revisi sebelumnya agar tidak perlu mengetik ulang. Sesuaikan spesifikasi, kebutuhan bahan, foto hasil perbaikan, dan durasi *Time Study* baru per komponen, lalu aktifkan toggle **`Langsung ajukan untuk persetujuan klien`** dan simpan.
4. Sistem menandai revisi lama sebagai `Telah Direvisi (REVISED)` dan menerbitkan **`v2`** dengan status `Menunggu Persetujuan (IN_REVIEW)`.

#### Output Saat Sample Disetujui (`APPROVED`):
- Status `Sample` dan `SampleRevision` berubah menjadi **`Disetujui` (`APPROVED`)** dan terkunci dari pengeditan.
- Sample otomatis masuk ke daftar menu **Sample Disetujui** (`/admin/approved-samples`) untuk proses penetapan tarif borongan dan konversi ke order produksi massal.

---

### 5.3 Proses 2.3: Penetapan Usulan Tarif Borongan Komponen oleh Kepala Produksi

Sebelum atau sesudah sample dikonversi menjadi order produksi massal, **Kepala Produksi** menetapkan tarif upah jahit borongan per komponen berdasarkan hasil *Time Study* (SMV).

- **Pelaksana (Role)**: `Kepala Produksi / Mandor Line` (`kepala_produksi`)
- **Lokasi Menu**: Panel Admin (`/admin`) → Grup **Produksi** → Menu **Sample Disetujui** (`/admin/approved-samples`).
- **Langkah Penggunaan**:
  1. Pada daftar **Sample Disetujui**, klik tombol **`Atur Tarif`** berwarna kuning pada baris sample (atau klik `Rincian` lalu klik `Atur Tarif` pada masing-masing baris komponen di tabel *Daftar Komponen & Tarif Borongan*).
  2. Pada modal **Atur Usulan Tarif Borongan Komponen**, sistem menampilkan durasi *Time Study* tiap komponen (misalnya: `Pasang Kerah — Time Study: 4 mnt 30 dtk`).
  3. Masukkan nominal **Tarif Borongan (Rp)** per pcs untuk setiap komponen (contoh: `Rp 3.500`) dan isi **Catatan** bila perlu, lalu klik **Simpan**.
- **Output**:
  - Sistem membuat/memperbarui data **`RateCard`** (`Ongkos Jahit / SEWING`) untuk setiap komponen dengan status **`Menunggu Persetujuan` (`PENDING`)**.

---

### 5.4 Proses 2.4: Persetujuan *Rate Card* oleh Owner / Finance

Sesuai prinsip *Separation of Duties*, tarif borongan yang diusulkan Kepala Produksi wajib disetujui oleh **Owner** atau **Finance** sebelum aktif digunakan dalam perhitungan *Payroll* dan *Invoice*.

- **Pelaksana (Role)**: `Owner / Eksekutif Internal` (`owner_eksekutif`) atau `Finance & Payroll` (`finance_payroll`)
- **Lokasi Menu**:
  - **Jalur Cepat (Per Sample)**: Menu **Sample Disetujui** (`/admin/approved-samples`) → Klik tombol **`Setujui Rate Card`** pada baris sample (atau tombol **`Setujui Semua Tarif Borongan`** di halaman rincian sample).
  - **Jalur Tabel Tarif Individual**: Grup **Payroll & Biaya** → Menu **Rate Card** (`/admin/rate-cards`) → Klik **`Setujui`** atau **`Tolak / Revisi`** per komponen.
- **Langkah Penggunaan & Output**:
  1. Saat mengklik **`Setujui Rate Card`**, jendela modal menampilkan tabel perbandingan seluruh komponen, durasi *Time Study*, dan nilai tarif yang diusulkan Kepala Produksi.
  2. Pilih tindakan:
     - **`Setujui Tarif`**: Mengubah seluruh *Rate Card* komponen pada sample tersebut menjadi **`Disetujui` (`APPROVED`)** lengkap dengan pencatatan penyetuju dan waktu persetujuan.
     - **`Tolak Tarif`**: Isi **Alasan Penolakan / Target Revisi** (contoh: *"Tarif pasang lengan terlalu tinggi, maksimal Rp 2.500"*). Status berubah menjadi **`Ditolak / Perlu Revisi` (`REJECTED`)**, sehingga Kepala Produksi dapat mengoreksi nominalnya kembali.

---

### 5.5 Proses 2.5: Konversi Sample Disetujui menjadi Order Produksi Massal (`Siap Produksi`)

- **Pelaksana (Role)**: `Tim Sample` (`tim_sample`), `Kepala Produksi` (`kepala_produksi`), atau `Owner` (`owner_eksekutif`)
- **Lokasi Menu**: Menu **Sample** (`/admin/samples`) atau **Sample Disetujui** (`/admin/approved-samples`) → Klik tombol **`Jadikan Daftar Pesanan`** (pada halaman detail bernama **`Konfirmasi & Lanjut Produksi Massal`**).
- **Prasyarat**: Sample telah berstatus `Disetujui (APPROVED)`.

#### Tabel Input Modal Konversi ke Order Produksi Massal:
| Nama Field di Layar | Tipe Input | Wajib / Opsional | Penjelasan |
| :--- | :--- | :---: | :--- |
| **Skema Pengerjaan** (`work_scheme`) | Pilihan (*Select*) | **Wajib** | Otomatis terisi dari skema pesanan awal (`CMT`, `Semi-CMT`, atau `Full Package`). |
| **Jumlah Produksi Massal (pcs)** (`quantity`) | Angka (*pcs*) | **Wajib** | Kuantitas final produksi massal yang disepakati (otomatis terisi dari kuantitas order awal). |
| **Tenggat Selesai** (`due_date`) | Tanggal (*Date*) | Opsional | Batas waktu penyelesaian produksi massal. |
| **Catatan Order** (`notes`) | Teks Panjang | Opsional | Instruksi khusus produksi massal. |

#### Output yang Dihasilkan:
1. Status `Order` otomatis beralih menjadi **`Siap Produksi` (`READY_FOR_PRODUCTION`)** dan mengikat revisi sample yang disetujui (`sample_revision_id`).
2. Sistem memanggil `OrderMaterialFlowService::syncRequirementsForOrder()` yang secara otomatis menghitung **Total Kebutuhan Bahan Order** (`Kebutuhan per 1 pcs di Sample × Jumlah Pcs Order`) dan membuat daftar item bahan di menu **Alur Bahan Pesanan** bagi tim **Gudang**.
3. Pesanan otomatis masuk ke menu **Antrean Produksi** (`/admin/production-orders`) bagi **Kepala Produksi**.

---

## BAB 6: TAHAP 3 — PENGELOLAAN GUDANG, PO BAHAN, CUTTING & DISTRIBUSI PRODUKSI

Tahap ketiga adalah pengelolaan material di **Gudang** mulai dari pengadaan bahan (PO untuk bahan Lukla dan/atau konfirmasi untuk bahan klien), penerimaan barang fisik di gudang, pemotongan kain (*cutting*), hingga serah terima bahan ke lantai produksi.

---

### 6.1 Konsep Isolasi Stok Multi-Klien (*Zero Cross-Contamination*) & Label *Tag ID*
1. **Kepemilikan Ketat (`client_id` + `order_id`)**: Setiap bahan yang masuk ke gudang wajib terikat ke satu Klien dan satu Nomor Order. Bahkan untuk skema **Semi-CMT** di mana aksesoris dibeli oleh Lukla, aksesoris tersebut tetap diberi label kepemilikan klien & order tujuan agar tidak tercampur dengan pesanan klien lain.
2. **Label Fisik Siap Cetak (`tag_id`)**: Setiap batch stok yang diterima otomatis mendapatkan kode unik dengan format:
   `TAG-{KODE_KLIEN}-{NOMOR_ORDER}-{ID_ITEM}` (contoh: `TAG-MAK-DEMO-ORD-20261016-A1B2-0015`).
   Petugas gudang dapat mengklik tombol **`Cetak Label`** di menu **Stok Gudang** (`/admin/inventory-items`) untuk mencetak slip label berformat A4/Thermal dan menempelkannya pada gulungan kain atau kemasan aksesoris.
3. **Blokir Otomatis Pengeluaran Lintas Klien/Order**: Jika petugas gudang mencoba mengeluarkan bahan milik Order A untuk digunakan pada Order B, sistem secara otomatis menghentikan transaksi dan memunculkan pesan peringatan pelanggaran kepemilikan (*InventoryOwnershipMismatch*).

---

### 6.2 Tujuh Langkah Berurutan pada Menu **Alur Bahan Pesanan** (`/admin/order-material-flows`)

Seluruh siklus kesiapan bahan untuk satu pesanan dikendalikan dari satu layar terpusat di menu **Gudang & Cutting → Alur Bahan Pesanan**. Tombol aksi pada setiap baris pesanan akan **muncul secara bergantian sesuai urutan proses**:

#### Langkah 1: Periksa Kebutuhan Bahan (`Daftar Barang & Bahan`)
- **Pelaksana**: `Gudang` atau `Kepala Produksi`
- **Cara Kerja**: Klik tombol abu-abu **`Daftar Barang & Bahan`**. Jendela modal menampilkan rincian setiap material yang dibutuhkan pesanan tersebut (Nama Bahan, Tanggungan `Klien`/`Perusahaan`/`Sebagian`, Kebutuhan Order, Jumlah Diterima Gudang, Kekurangan, dan Status Item).

#### Langkah 2A: Pengajuan PO Bahan Tanggungan Perusahaan (`Ajukan PO`)
- **Pelaksana**: `Gudang` (`gudang`)
- **Kapan Tombol Muncul**: Saat pesanan memiliki bahan yang ditanggung perusahaan (skema *Full Package* atau aksesoris pada *Semi-CMT*) dan status alur bahan masih `Kosong` atau `Konfirmasi Klien`.
- **Input Modal**:
  - **Nomor PO** (`po_number`): Terisi otomatis (contoh: `PO-2026-16-18-00`).
  - **Pemasok / Supplier** (`supplier` — *Wajib*, contoh: `PT Tekstil Jaya Abadi`).
  - **Catatan PO** (`notes` — *Opsional*).
  - **Harga Satuan (Rp)** untuk setiap item bahan yang diajukan.
- **Output**:
  - Terbuat dokumen **`PurchaseOrder`** baru berstatus **`Diajukan` (`SUBMITTED`)** yang muncul di menu **Pengajuan PO Gudang** (`/admin/my-purchase-orders`) dan **Persetujuan PO Bahan** (`/admin/purchase-orders`).
  - Status alur bahan pesanan naik menjadi **`Pengajuan Barang (PO)`** (atau **`Dalam PO & Konfirmasi Klien`**).

#### Langkah 2B: Konfirmasi Bahan Kiriman Klien (`Konfirmasi Klien`)
- **Pelaksana**: `Gudang` (`gudang`)
- **Kapan Tombol Muncul**: Saat pesanan memiliki bahan yang ditanggung klien (skema *CMT* atau kain utama pada *Semi-CMT*) dan status alur bahan masih `Kosong` atau `Pengajuan Barang (PO)`.
- **Input Modal**: Isi **Catatan Konfirmasi Klien** (`notes`, contoh: *"Klien konfirmasi pengiriman 500 meter kain via ekspedisi besok pagi"*).
- **Output**: Status item bahan klien berubah menjadi `Konfirmasi Klien` dan status alur bahan pesanan menjadi **`Konfirmasi Klien`** (atau **`Dalam PO & Konfirmasi Klien`**).

#### Langkah 3: Persetujuan PO Bahan oleh Finance / Owner (`Setujui` PO)
- **Pelaksana**: `Finance & Payroll` (`finance_payroll`) atau `Owner` (`owner_eksekutif`)
- **Lokasi Menu**: Grup **Payroll & Biaya** → Menu **Persetujuan PO Bahan** (`/admin/purchase-orders`) atau **Pengajuan PO Gudang** (`/admin/my-purchase-orders`).
- **Cara Kerja & Output**: Klik tombol **`Rincian`** untuk meninjau daftar bahan, kuantitas, dan harga satuan. Kemudian klik tombol hijau **`Setujui`**. Status PO berubah menjadi **`Disetujui` (`APPROVED`)** sehingga pembelian bahan dapat direalisasikan.

#### Langkah 4: Penerimaan Kedatangan Barang di Gudang (`ACC Kedatangan Gudang`)
- **Pelaksana**: `Gudang` (`gudang`)
- **Kapan Tombol Muncul**: Saat status alur bahan berada pada `Pengajuan Barang (PO)`, `Konfirmasi Klien`, `Dalam PO & Konfirmasi Klien`, atau `Pengiriman Barang`.
- **Input Modal "ACC Kedatangan Barang ke Gudang"**:
  - **No. Surat Jalan / Lot / Batch** (`common_batch` — *Wajib*, contoh: `SJ-20261016-01`).
  - **Sumber / Pengirim** (`common_source` — *Wajib*, contoh: `Kiriman Klien` atau `Supplier PO`).
  - **Catatan Penerimaan** (`common_notes` — *Opsional*).
  - **Daftar Item**: Centang kotak **`Terima Item Ini`** pada bahan yang tiba dan isi **`Jumlah Diterima Sekarang`** (otomatis terisi sejumlah sisa kekurangan). Jika barang datang bertahap (parsial), isi sesuai jumlah yang datang terlebih dahulu.
- **Output**:
  - Sistem otomatis menambahkan stok fisik **`InventoryItem`** berstatus **`Bahan Mentah` (`RAW`)** lengkap dengan kode `TAG-...` di menu **Stok Gudang**, serta mencatat jurnal **`Penerimaan Bahan` (`RECEIPT`)** di menu **Pergerakan Stok**.
  - Jika baru sebagian bahan yang tiba, status alur bahan menjadi **`Pengiriman Barang` (`DELIVERY_IN_TRANSIT`)**.
  - Jika **seluruh (100%)** kebutuhan bahan dan aksesoris pesanan telah terpenuhi, status alur bahan otomatis naik menjadi **`Standby (Bahan Lengkap)` (`STANDBY`)**.

#### Langkah 5: Pemotongan Kain di Meja Potong (`Mulai Cutting`)
- **Pelaksana**: `Gudang` (`gudang`)
- **Kapan Tombol Muncul**: Hanya ketika seluruh bahan pesanan sudah lengkap dan berstatus **`Standby (Bahan Lengkap)` (`STANDBY`)**.
- **Cara Kerja**: Klik tombol oranye **`Mulai Cutting`**, lalu konfirmasi pada modal.
- **Output**:
  - Seluruh stok bahan bertipe **Bahan Utama / Kain (`FABRIC`)** pada pesanan tersebut otomatis dikeluarkan dari saldo `Bahan Mentah (RAW)` (mencatat mutasi `Masuk Proses Cutting / ISSUE_TO_CUTTING`) dan dipindahkan menjadi stok **`WIP (Setengah Jadi)`** di lokasi `Meja Potong / Cutting` dengan kode batch `{BATCH}-CUT`.
  - Status alur bahan pesanan naik menjadi **`Dalam Persiapan Pengiriman (Cutting)` (`PREPARING_DELIVERY`)**.
  - *(Opsional)* Jika terdapat sisa potongan kain/perca (*scrap*), petugas gudang dapat mencatatnya di menu **Sisa Potongan** (`/admin/waste-records`).

#### Langkah 6: Pengiriman Bahan Potongan & Aksesoris ke Lantai Produksi (`Kirim ke Produksi`)
- **Pelaksana**: `Gudang` (`gudang`)
- **Kapan Tombol Muncul**: Saat status alur bahan adalah **`Dalam Persiapan Pengiriman (Cutting)` (`PREPARING_DELIVERY`)**.
- **Cara Kerja**: Klik tombol biru **`Kirim ke Produksi`**, lalu konfirmasi pengiriman.
- **Output**:
  - Sistem menerbitkan dokumen pengiriman bahan (**`MaterialDispatch`** dengan nomor `DSP-YYYYMMDD-XXXX`) untuk seluruh item bahan dan aksesoris pesanan tersebut.
  - Status alur bahan pesanan berubah menjadi **`Sedang Dikirim` (`DISPATCHED`)**.

#### Langkah 7: Verifikasi & Penerimaan Bahan oleh Kepala Produksi (`ACC Penerimaan`)
- **Pelaksana**: `Kepala Produksi / Mandor Line` (`kepala_produksi`) — *(Pemisahan Tugas dengan Gudang)*
- **Lokasi Menu**: Login sebagai **Kepala Produksi** → Buka menu **Gudang & Cutting → Alur Bahan Pesanan** (`/admin/order-material-flows`).
- **Kapan Tombol Muncul**: Saat status alur bahan adalah **`Sedang Dikirim` (`DISPATCHED`)**.
- **Input Modal "ACC Penerimaan Bahan di Produksi"**:
  - Untuk setiap baris bahan yang dikirim Gudang, Kepala Produksi memilih **Kesesuaian Fisik**:
    - Pilih **`Sesuai Data Kiriman`** jika jumlah fisik yang tiba di lantai jahit sama persis dengan jumlah kiriman gudang.
    - Pilih **`Tidak Sesuai / Ada Selisih`** jika jumlah fisik berbeda, lalu wajib mengisi kolom **`Jumlah Aktual Diterima`** (sistem akan otomatis mengoreksi saldo penerimaan dan mencatat riwayat selisihnya).
  - Isi **Catatan Penerimaan Produksi** (`notes` — *Opsional*), lalu klik **Simpan**.
- **Output**:
  - Seluruh dokumen `MaterialDispatch` berstatus `Diterima di Produksi (RECEIVED)` dan stok di gudang tercatat telah ditransfer (`TRANSFER`) ke `Lantai Produksi` (status stok gudang menjadi `Sudah Keluar (Diterima Produksi)`).
  - Status akhir alur bahan pesanan menjadi **`Diterima Bagian Produksi` (`RECEIVED_BY_PRODUCTION`)**.

---

## BAB 7: TAHAP 4 — PROSES PRODUKSI, OUTPUT 30 MENIT & PERAKITAN (ASSEMBLY)

Setelah bahan diterima di lantai produksi dan pesanan berstatus `Siap Produksi (READY_FOR_PRODUCTION)`, **Kepala Produksi** memulai pengerjaan order, menugaskan **Penjahit** per komponen, memverifikasi laporan output per interval, dan merakit komponen menjadi produk utuh.

---

### 7.1 Pengaturan Line Produksi & Alokasi Penjahit (`Line Produksi`)
- **Pelaksana**: `Kepala Produksi` (`kepala_produksi`) atau `Owner` (`owner_eksekutif`)
- **Lokasi Menu**: `/admin/production-lines` (Menu **Line Produksi**).
- **Cara Kerja**:
  - Kepala Produksi dapat membuat/mengelola daftar lini jahit (contoh: `LINE-A`, `LINE-B`, kapasitas harian pcs).
  - Pada halaman Edit Line Produksi, di bagian bawah terdapat tabel **Penugasan Penjahit**. Klik **`Tugaskan Penjahit`** untuk menempatkan penjahit ke line tersebut.
  - **Aturan Rotasi Dinamis**: Satu penjahit hanya aktif di satu line pada satu waktu. Jika penjahit ditugaskan ke line baru, sistem secara otomatis menutup penugasan lamanya (`unassigned_at = now()`) tanpa menghapus histori penempatan sebelumnya.

---

### 7.2 Proses 4.1: Memulai Produksi & Penugasan Penjahit per Komponen (`Mulai Produksi`)

- **Pelaksana (Role)**: `Kepala Produksi / Mandor Line` (`kepala_produksi`)
- **Lokasi Menu**: Panel Admin (`/admin`) → Grup **Produksi** → Menu **Antrean Produksi** (`/admin/production-orders`).
- **Prasyarat**: Status pesanan adalah **`Siap Produksi` (`READY_FOR_PRODUCTION`)** dan artikel pesanan telah memiliki daftar komponen serta *Rate Card* yang disetujui.

#### Langkah Penggunaan di Aplikasi:
1. Buka menu **Antrean Produksi**. Daftar pesanan diurutkan berdasarkan tenggat waktu (`due_date`) terdekat.
2. Pada baris pesanan berstatus `Siap Produksi`, klik tombol hijau **`Mulai Produksi`**.
3. Pada modal **Mulai produksi order...**, sistem menampilkan seluruh komponen artikel secara berurutan (misalnya: `1. Jahit Badan Depan`, `2. Pasang Kerah`, `3. Pasang Lengan`).
4. Pilih **1 Penjahit** penanggung jawab pada dropdown masing-masing komponen, lalu klik tombol **`Mulai Produksi`**.
   *(Catatan: Jika di tengah masa produksi ingin mengganti penjahit pada komponen tertentu, klik tombol kuning **`Ubah Penugasan`** pada baris order yang sama).*

#### Output yang Dihasilkan:
- Sistem menyimpan pemetaan penugasan ke tabel `OrderComponentAssignment`.
- Status pesanan otomatis berubah dari `Siap Produksi (READY_FOR_PRODUCTION)` menjadi **`Dalam Produksi` (`IN_PRODUCTION`)**.
- Daftar tugas komponen langsung muncul di layar kerja **Output 30 Menit** milik masing-masing **Penjahit** yang ditugaskan.

---

### 7.3 Proses 4.2: Pencatatan Output 30 Menit oleh Penjahit (*Stopwatch Interaktif*)

Aplikasi menyediakan antarmuka ramah layar sentuh (*touch-friendly*) dengan **Stopwatch Digital Terkunci** bagi penjahit di lantai produksi.

- **Pelaksana (Role)**: `Penjahit / Tailor Operator` (`penjahit`)
- **Lokasi Menu**: Panel Admin (`/admin`) → Grup **Produksi** → Menu **Output 30 Menit** (`/admin/output-entries`).
- **Prasyarat**: Penjahit telah ditugaskan ke komponen pada order yang sedang aktif.

#### Langkah Penggunaan di Aplikasi oleh Penjahit:
1. Login sebagai **Penjahit** (contoh: `penjahit1@lukla.test`), lalu buka menu **Output 30 Menit**.
2. Layar akan menampilkan daftar **Komponen** yang ditugaskan kepada penjahit tersebut beserta informasi Nomor Order, Klien, Artikel, Progres Lolos QC, dan Status Laporan Terakhir.
3. Klik tombol biru **`Kerjakan`** pada baris komponen yang akan dijahit:
   - Jendela modal kerja akan terbuka dan **mengunci layar** (tidak akan tertutup secara tidak sengaja jika layar tersentuh di luar kotak).
   - **Langkah A (Mulai Menjahit)**: Tekan tombol hijau **`Mulai`** pada Stopwatch. Waktu mulai (`interval_start`) tercatat dan penghitung waktu (`00:00:00`) mulai berjalan.
   - **Langkah B (Selesai Satu Sesi/Interval)**: Setelah selesai menjahit dalam interval tersebut, tekan tombol merah **`Stop`**.
   - **Langkah C (Isi Hasil Kerja)**: Setelah tombol *Stop* ditekan, kolom input akan terbuka. Masukkan:
     - **Jumlah selesai (pcs)** (`quantity` — wajib angka antara `1` s/d `100` pcs).
     - **Catatan** (`notes` — opsional, jika ada kendala benang/kain).
   - **Langkah D (Kirim)**: Klik tombol **`Kirim laporan`**.

#### Output yang Dihasilkan:
- Sistem membuat catatan **`OutputEntry`** baru dengan waktu mulai & selesai aktual, kuantitas pcs, dan status **`Menunggu Verifikasi` (`SUBMITTED`)**.
- Laporan ini belum dihitung sebagai upah (*payroll*) sampai diverifikasi oleh Kepala Produksi / Mandor.

---

### 7.4 Proses 4.3: Verifikasi / Penolakan Output oleh Kepala Produksi & Pengajuan Ulang

- **Pelaksana (Role)**: `Kepala Produksi / Mandor Line` (`kepala_produksi`)
- **Lokasi Menu**: Panel Admin (`/admin`) → Grup **Produksi** → Menu **Output 30 Menit** (`/admin/output-reviews`).
- **Cara Kerja**:
  1. Kepala Produksi melihat daftar order yang sedang berjalan beserta badge jumlah laporan yang **`Menunggu Verifikasi`**, **`Terverifikasi`**, dan **`Ditolak`**.
  2. Klik baris order untuk masuk ke halaman rincian verifikasi (`ViewOutputReview`).
  3. Di bagian bawah halaman terdapat tabel seluruh laporan output penjahit pada order tersebut. Kepala Produksi dapat memilih:
     - **Verifikasi per Baris**: Klik tombol hijau **`Verifikasi`** pada baris laporan penjahit.
     - **Verifikasi Massal Sekaligus**: Klik tombol hijau **`Verifikasi Semua`** di bagian kanan atas halaman untuk menerima seluruh laporan yang masih berstatus `Menunggu Verifikasi` dalam satu klik.
     - **Tolak Laporan (`Tolak`)**: Jika jumlah hitungan fisik atau kualitas jahitan komponen tidak sesuai, klik tombol merah **`Tolak`**, lalu isi **Alasan Penolakan** (contoh: *"Hitungan fisik hanya 18 pcs, bukan 20 pcs"*).
     - **Catat Output Manual (`Catat Output`)**: Mandor juga dapat membantu mencatatkan output atas nama penjahit melalui tombol `Catat Output` di pojok kanan atas.

#### Alur Pengajuan Ulang Output yang Ditolak (`Ajukan Ulang` oleh Penjahit):
- Apabila laporan ditolak mandor, status laporan berubah menjadi **`Ditolak` (`REJECTED`)** dan alasan penolakannya tampil di layar Penjahit.
- Penjahit memperbaiki pekerjaannya atau mengoreksi jumlah pcs melalui aksi **`Ajukan Ulang`** (`ResubmitOutputAction`), memasukkan **Jumlah Baru (pcs)** dan **Catatan Perbaikan**, sehingga statusnya kembali menjadi **`Menunggu Verifikasi` (`SUBMITTED`)** untuk diperiksa ulang oleh Mandor.

#### Output Saat Laporan Diverifikasi (`VERIFIED`):
- Status `OutputEntry` berubah menjadi **`Terverifikasi` (`VERIFIED`)**, mencatat `verified_by` dan `verified_at`.
- **Terkunci & Layak Payroll**: Laporan yang sudah `VERIFIED` bersifat permanen (tidak dapat diedit lagi oleh penjahit) dan resmi masuk ke dalam perhitungan **Upah Borongan Mingguan** serta **Kalkulasi Ongkos Jahit Invoice**.

---

### 7.5 Proses 4.4: Perakitan Komponen menjadi Produk Utuh (`Input Perakitan`)

Setelah seluruh komponen penyusun pakaian (misalnya badan, kerah, dan lengan) telah dijahit dan diverifikasi, **Kepala Produksi** mencatat proses perakitan (*Assembly*) menjadi pakaian utuh yang siap diperiksa oleh tim QC.

- **Pelaksana (Role)**: `Kepala Produksi / Mandor Line` (`kepala_produksi`)
- **Lokasi Menu**: Menu **Output 30 Menit** (`/admin/output-reviews`) → Klik baris order → Klik tombol biru **`Input Perakitan`** di bagian kanan atas halaman.
- **Syarat Kesiapan Perakitan (`Order::isReadyForAssembly()`)**:
  Tombol **`Input Perakitan`** hanya akan aktif (bisa disimpan) apabila:
  1. **Tidak ada** laporan output pada order tersebut yang masih berstatus `Menunggu Verifikasi` (semua harus diverifikasi/ditolak terlebih dahulu).
  2. **Setiap komponen** dari artikel pesanan tersebut telah memiliki minimal satu laporan output berstatus **`Terverifikasi` (`VERIFIED`)**.
  *(Jika belum memenuhi syarat, keterangan di dalam modal akan menjelaskan komponen mana yang belum terverifikasi).*

#### Tabel Input Modal "Input Perakitan":
| Nama Field di Layar | Tipe Input | Wajib / Opsional | Penjelasan |
| :--- | :--- | :---: | :--- |
| **Output Terverifikasi** (`output_entry_ids`) | Pilihan Ganda (*Multi-Select*) | **Wajib** | Otomatis memilih seluruh laporan output terverifikasi pada order ini (dapat disesuaikan jika perakitan dilakukan bertahap/parsial). |
| **Jumlah Hasil Rakitan** (`quantity`) | Angka (*pcs*) | **Wajib** | Jumlah unit pakaian utuh yang berhasil dirakit pada batch ini (contoh: `100` pcs). |
| **Catatan** (`notes`) | Teks Panjang | Opsional | Catatan batch perakitan. |

#### Output yang Dihasilkan:
- Sistem membuat record **`Assembly`** (batch pakaian utuh siap QC) dan menautkannya dengan komponen-komponen output penyusunnya.
- Apabila total unit yang dirakit telah mencapai target kuantitas order (`totalAssembled >= order->quantity`), status pesanan otomatis naik dari `Dalam Produksi (IN_PRODUCTION)` menjadi **`Dalam QC` (`IN_QC`)**.
- Unit hasil rakitan langsung muncul sebagai **`Siap QC (X unit)`** di layar **Antrean Pesanan QC** milik **QC Inspector**.

---

## BAB 8: TAHAP 5 — QUALITY CONTROL (QC), PERBAIKAN (REPAIR) & BARANG AFKIR (SCRAP)

Tahap kelima adalah pemeriksaan mutu akhir terhadap pakaian hasil perakitan oleh **QC Inspector**.

---

### 8.1 Proses 5.1: Pemeriksaan Kualitas di Menu **Antrean Pesanan QC** (`/admin/qc-orders`)

- **Pelaksana (Role)**: `QC Inspector / Quality` (`qc_inspector`)
- **Lokasi Menu**: Panel Admin (`/admin`) → Grup **Quality Control** → Menu **Antrean Pesanan QC** (`/admin/qc-orders`).
  *(Catatan: Pemeriksaan juga dapat dilihat log historisnya di menu **Inspeksi QC** `/admin/qc-records`).*
- **Prasyarat**: Order memiliki batch perakitan (`Assembly`) yang belum selesai diperiksa (`Siap QC > 0 unit`).

#### Tabel Input Modal "Periksa QC" (`inspectOrder`):
| Nama Field di Layar | Tipe Input | Wajib / Opsional | Penjelasan & Aturan Validasi |
| :--- | :--- | :---: | :--- |
| **Perakitan yang Siap Dicek** (`assembly_id`) | Pilihan (*Select*) | **Wajib** | Pilih batch perakitan (menampilkan sisa unit siap cek). Saat dipilih, kolom *Jumlah yang Diperiksa* otomatis terisi sisa unit batch tersebut. |
| **Hasil QC** (`result`) | Pilihan (*Select*) | **Wajib** | Pilih salah satu keputusan:<br>• **`Lolos (Pass)`**<br>• **`Perlu Perbaikan (Repair)`**<br>• **`Afkir / Reject (Scrap)`** |
| **Kategori Cacat** (`defect_category_id`) | Pilihan (*Select*) | **Wajib jika Repair / Scrap** | Hanya muncul dan **wajib dipilih** apabila Hasil QC adalah `Perlu Perbaikan (Repair)` atau `Afkir / Reject (Scrap)` (contoh: *Jahitan Loncat*, *Noda Oli*, *Ukuran Meleset*). |
| **Jumlah yang Diperiksa (unit)** (`quantity`) | Angka (*unit*) | **Wajib** | Jumlah unit pakaian yang mendapat hasil QC tersebut (minimal `1` unit). Jika dalam 1 batch 100 pcs terdapat 95 Lolos dan 5 Repair, lakukan input 2 kali (95 Lolos, lalu 5 Repair). |
| **Catatan Pemeriksaan** (`notes`) | Teks Panjang | Opsional | Detail letak cacat atau instruksi perbaikan. |

---

### 8.2 Tiga Keputusan QC & Dampaknya terhadap Sistem

| Keputusan QC | Dampak pada Barang & Stok | Dampak pada *Pool Bonus QC* | Dampak pada Status Order |
| :--- | :--- | :--- | :--- |
| **1. Lolos (`PASS`)** | Barang dinyatakan layak kirim (*Finished Goods*). | **Menambah `+Quantity` unit** ke perhitungan *Pool Bonus QC* periode berjalan. | Jika pesanan masih `IN_PRODUCTION`, otomatis naik ke `IN_QC`. **Jika akumulasi unit `PASS` telah mencapai 100% target kuantitas pesanan (`qcPassedQuantity >= order.quantity`), status pesanan otomatis berubah menjadi `Selesai` (`COMPLETED`)!** |
| **2. Perlu Perbaikan (`REPAIR`)** | Sistem otomatis membuat tiket perbaikan baru di menu **Perbaikan / Repair** (`/admin/repairs`) dengan status `Menunggu Perbaikan (PENDING)`. | Tidak dihitung ke *Pool Bonus QC* (baru dihitung jika nanti lolos saat *Inspeksi Ulang*). | Menahan penyelesaian 100% order sampai barang selesai diperbaiki dan lolos QC ulang. |
| **3. Afkir / Reject (`SCRAP`)** | Sistem otomatis membuat catatan barang rusak di tabel **`WasteRecord`** (`uom = pcs`, kategori = nama kategori cacat) dan menampilkannya di tab **Scrap** pada menu *Inspeksi QC*. | Tidak dihitung ke *Pool Bonus QC*. | Tercatat pada kolom badge merah **`X Afkir`** di tabel QC. |

---

### 8.3 Proses 5.2: Siklus Penanganan Barang Perbaikan (`Perbaikan / Repair`) & Inspeksi Ulang

Ketika terdapat unit yang diputuskan **`REPAIR`**, unit tersebut dikembalikan ke meja perbaikan dan melewati 3 langkah kolaborasi antara **Kepala Produksi** dan **QC Inspector** di menu **Quality Control → Perbaikan / Repair** (`/admin/repairs`):

#### Langkah 1: Menugaskan Petugas Perbaikan (`Tugaskan`)
- **Pelaksana**: `Kepala Produksi` (`kepala_produksi`) atau `QC Inspector`
- **Aksi**: Pada baris tiket berstatus `Menunggu Perbaikan (PENDING)`, klik tombol biru **`Tugaskan`**.
- **Input**: Pilih **Petugas Perbaikan** (`assigned_repairer_id`) dan tulis **Instruksi Perbaikan** (`notes`).
- **Output**: Status tiket berubah menjadi **`Sedang Diperbaiki` (`IN_REPAIR`)**.

#### Langkah 2: Menyelesaikan Perbaikan Fisik (`Selesai Perbaikan`)
- **Pelaksana**: `Kepala Produksi` (`kepala_produksi`)
- **Aksi**: Setelah penjahit selesai memperbaiki cacat pada pakaian tersebut, klik tombol hijau **`Selesai Perbaikan`** pada baris tiket.
- **Input**: Isi **Catatan Hasil Perbaikan** (`notes`), lalu konfirmasi.
- **Output**: Status tiket berubah menjadi **`Siap Dicek Ulang` (`COMPLETED`)** beserta pencatatan waktu `completed_at`. Tombol **`Inspeksi Ulang`** kini muncul untuk QC Inspector.

#### Langkah 3: Pemeriksaan Ulang Barang Hasil Perbaikan (`Inspeksi Ulang`)
- **Pelaksana**: `QC Inspector` (`qc_inspector`)
- **Aksi**: Pada baris tiket berstatus `Siap Dicek Ulang (COMPLETED)`, klik tombol biru **`Inspeksi Ulang`**.
- **Input & Output Berdasarkan Hasil QC Ulang**:
  1. **Jika dipilih `Lolos (Pass)`**:
     - Sistem otomatis membuat `QcRecord` baru berstatus **`PASS`** sejumlah unit tiket tersebut (sehingga menambah jumlah unit lolos order, menambah *Pool Bonus QC*, dan jika genap 100% target order, mengubah status order menjadi **`Selesai / COMPLETED`**).
     - Status tiket repair ditutup menjadi **`Lolos QC Ulang` (`VERIFIED`)**.
  2. **Jika dipilih `Perlu Perbaikan Ulang (Repair)`**:
     - Wajib memilih **Kategori Cacat Baru** dan mengisi catatan.
     - Status tiket repair dikembalikan menjadi **`Menunggu Perbaikan` (`PENDING`)** untuk diperbaiki kembali.
  3. **Jika dipilih `Afkir / Reject (Scrap)`**:
     - Wajib memilih **Kategori Cacat Baru**.
     - Sistem mencatat unit tersebut sebagai `SCRAP` (masuk ke `WasteRecord` afkir) dan menutup tiket repair.

---

## BAB 9: TAHAP 6 — KEUANGAN (FINANCE), PAYROLL MINGGUAN, OVERHEAD & PEMBAYARAN INVOICE

Tahap keenam mencakup seluruh pengelolaan keuangan pabrik oleh **Finance & Payroll**: pencatatan biaya *overhead* bulanan, perhitungan *payroll* mingguan & distribusi *Bonus QC*, hingga penerbitan **Invoice** kepada klien, konfirmasi pembayaran dari **Portal Klien**, dan verifikasi pelunasan (**`PAID`**) yang menutup siklus pesanan (**`CLOSED`**).

---

### 9.1 Proses 6.1: Pencatatan Biaya *Overhead* Bulanan & Alokasi *Overhead per Unit*

Biaya operasional pabrik bulanan (listrik, air, internet, perawatan mesin, dll.) dicatat untuk menghitung beban biaya *overhead* per unit produk yang dirakit pada bulan tersebut.

- **Pelaksana (Role)**: `Finance & Payroll` (`finance_payroll`) atau `Owner` (`owner_eksekutif`)
- **Langkah 1 — Input Biaya Bulanan (`Biaya Overhead` — `/admin/overhead-records`)**:
  1. Buka grup **Payroll & Biaya → Biaya Overhead** → Klik **`Buat`**.
  2. Isi **Bulan** (`period_month`, contoh: `Oktober 2026`), **Kategori** (`category`, contoh: `Listrik Pabrik`, `Air`, `Internet`), **Jumlah (Rp)** (`amount`), dan **Catatan** (`notes`), lalu klik **Simpan**.
- **Langkah 2 — Hitung Alokasi per Unit (`Overhead per Unit` — `/admin/overhead-allocations`)**:
  1. Buka menu **Overhead per Unit** (atau melalui pemanggilan alokasi bulanan).
  2. Sistem menghitung rumus:
     $$\text{Overhead per Unit} = \frac{\text{Total Biaya Overhead Bulan Tersebut}}{\text{Total Unit Pakaian yang Selesai Dirakit (Assembly) Bulan Tersebut}}$$
  3. Nilai `overhead_per_unit` ini otomatis digunakan sebagai catatan biaya internal pabrik ketika Finance membuat *Invoice* untuk pesanan berskema **Semi-CMT** dan **Full Package**.

---

### 9.2 Proses 6.2: Perhitungan, Persetujuan & Pembayaran *Payroll* Borongan Mingguan

Penggajian penjahit dan pembagian *Bonus QC* dilakukan setiap minggu (periode Senin s/d Minggu, dengan penjadwalan tutup otomatis setiap Sabtu pukul 00:00 WIB melalui command `payroll:close-weekly` maupun dieksekusi langsung oleh Finance).

- **Pelaksana (Role)**: `Finance & Payroll` (`finance_payroll`) atau `Owner` (`owner_eksekutif`)
- **Lokasi Menu**: Panel Admin (`/admin`) → Grup **Payroll & Biaya** → Menu **Periode Payroll** (`/admin/payroll-periods`).

#### Langkah 1: Membuat Periode Payroll Baru (`Buat`)
Klik tombol **`Buat`** di pojok kanan atas, lalu isi 4 parameter berikut:
| Nama Field di Layar | Wajib / Opsional | Penjelasan & Nilai Default |
| :--- | :---: | :--- |
| **Awal Periode** (`period_start`) | **Wajib** | Tanggal awal rentang kerja penjahit yang dihitung (default: Senin minggu ini). |
| **Akhir Periode** (`period_end`) | **Wajib** | Tanggal akhir rentang kerja penjahit (default: Minggu minggu ini). |
| **Uang Makan (%)** (`meal_allowance_percent`) | Opsional | Persentase dana uang makan dari total ongkos jahit periode ini (default: `10%`, lazimnya `10% – 15%`). |
| **Tarif Bonus QC (Rp)** (`qc_bonus_rate_per_unit`) | Opsional | Nilai rupiah bonus per 1 unit pakaian yang lolos QC lintas klien pada periode ini (contoh: `Rp 500`). |

Setelah disimpan, periode baru berstatus **`Terbuka` (`OPEN`)**.

#### Langkah 2: Menjalankan Kalkulasi Gaji & Bonus QC (`Hitung`)
Pada tabel **Periode Payroll**, klik tombol kuning **`Hitung`** pada baris periode terkait, lalu konfirmasi.
Sistem (`PayrollService::calculate`) secara otomatis melakukan 3 perhitungan dalam satu transaksi database:
1. **Upah Pokok Borongan per Penjahit (`sewing_amount`)**:
   - Mengambil seluruh `OutputEntry` berstatus **`Terverifikasi` (`VERIFIED`)** yang waktu kerjanya (`interval_start`) berada di dalam rentang `Awal Periode s/d Akhir Periode`.
   - Mengalikan kuantitas pcs tiap komponen dengan tarif **`RateCard` (`SEWING`)** yang aktif untuk klien, artikel, dan komponen tersebut:
     $$\text{Ongkos Jahit Penjahit} = \sum (\text{Qty Output Terverifikasi} \times \text{Tarif Komponen})$$
   - *Dynamic Headcount*: Siapa pun penjahit yang memiliki output terverifikasi pada minggu tersebut (termasuk penjahit baru yang masuk di tengah minggu) otomatis dibuatkan baris slip gajinya (`PayrollEntry`).
2. **Alokasi Uang Makan Proporsional (`meal_allowance_amount`)**:
   - Menghitung total dana uang makan periode: $\text{Pool Uang Makan} = \text{Total Ongkos Jahit Semua Penjahit} \times \text{Uang Makan (\%)}$.
   - Membagikan dana uang makan secara proporsional sesuai kontribusi ongkos jahit masing-masing penjahit (selisih pembulatan sen ditambahkan ke baris terakhir agar totalnya 100% akurat).
   - $\text{Total Diterima Penjahit} = \text{Ongkos Jahit} + \text{Uang Makan}$.
3. **Kalkulasi & Distribusi 3 Arah *Pool Bonus QC* (`QcBonusDistributionService`)**:
   - Menghitung seluruh unit pakaian yang dinyatakan **Lolos (`PASS`)** oleh QC pada rentang periode tersebut lintas semua klien & pesanan:
     $$\text{Total Pool Bonus QC} = \sum \text{Unit Lolos QC} \times \text{Tarif Bonus QC per Unit}$$
   - Membagi rata **Total Pool Bonus QC dibagi 3 (`Pool ÷ 3`)** kepada:
     - **2 orang Kepala Produksi / Mandor Line (`kepala_produksi`)**
     - **1 orang Kepala Operasional / Owner (`owner_eksekutif`)**
   - Rincian pembagian bonus per penerima dan sumber unit lolos per artikel dapat dilihat kapan saja dengan mengklik tombol abu-abu **`Bonus QC`** pada baris periode payroll.

**Output Langkah 2**: Status periode berubah menjadi **`Sudah Dihitung` (`CALCULATED`)**. Finance dapat mengklik baris periode untuk meninjau daftar rincian gaji tiap penjahit pada tabel di bawahnya (`PayrollEntriesRelationManager`). Selama belum disetujui, tombol `Hitung` dapat diklik ulang jika ada verifikasi output susulan.

#### Langkah 3: Menyetujui & Menandai Payroll Dibayar (`Setujui` → `Tandai Dibayar`)
1. Klik tombol hijau **`Setujui`** pada baris periode yang berstatus `Sudah Dihitung`. Status berubah menjadi **`Disetujui` (`APPROVED`)** dan seluruh nilai gaji periode tersebut **terkunci permanen** (`isLocked() = true`).
2. Setelah dana gaji ditransfer/dibayarkan kepada para penjahit dan penerima bonus, klik tombol biru **`Tandai Dibayar`**. Status akhir periode berubah menjadi **`Sudah Dibayar` (`PAID`)**.

---

### 9.3 Transparansi Pendapatan Pekerja (*Upah Jahit Saya* & *Bonus QC Saya*)
- **Bagi Penjahit (`/admin/tailor-wages` — Menu `Pekerjaan Saya → Upah Jahit Saya`)**:
  Setiap penjahit dapat melihat riwayat penghasilannya per periode mingguan (Total Pcs, Ongkos Jahit, Uang Makan, Total Diterima, dan Status Pembayaran). Klik tombol **`Rincian Proyek`** untuk melihat tabel transparan berisi Klien, Nomor Order, Artikel, dan Komponen apa saja yang telah dikerjakannya.
- **Bagi QC Inspector (`/admin/qc-bonuses` — Menu `Quality Control → Bonus QC Saya`)**:
  QC Inspector dapat memantau total unit lolos QC per periode, tarif bonus per unit, perolehan bonus, serta mengklik **`Rincian Proyek`** untuk melihat daftar pesanan dan artikel yang menyumbang unit lolos pada periode tersebut.

---

### 9.4 Proses 6.3: Siklus Lengkap *Invoice* Klien — Dari Penerbitan hingga Pembayaran & Penutupan Order (`CLOSED`)

Inilah tahap penutup yang menghubungkan hasil produksi di pabrik dengan penyelesaian pembayaran oleh **Klien Maklon**.

```
[FINANCE: Buat Invoice & Pilih Order]
  --> Sistem Otomatis Menghitung: Subtotal Ongkos Jahit (Output Verified x Rate Card) + PPN (%)
  --> Status Invoice: DRAFT (Draf)
        │
        ▼
[FINANCE: Klik Tombol "Terbitkan"]
  --> Status Invoice: ISSUED (Terbit)
  --> Tagihan langsung tampil di menu "Tagihan & Invoice" pada Portal Klien
        │
        ▼
[KLIEN MAKLON: Buka Portal "Tagihan & Invoice" -> Klik "Rincian Biaya" -> Klik "Konfirmasi Pembayaran"]
  --> Klien memilih Metode Bank, mengisi No. Referensi, & mengunggah Foto Bukti Transfer
  --> Status Invoice: PAYMENT_CONFIRMED (Menunggu Konfirmasi Finance)
        │
        ▼
[FINANCE: Buka Menu "Invoice" -> Klik "Detail" (Cek Bukti Transfer) -> Klik "Tandai Lunas"]
  --> Status Invoice: PAID (Lunas)
  --> Stok Inventaris Order otomatis menjadi FINISHED (Barang Jadi)
  --> Status Order otomatis ditutup menjadi CLOSED (Ditutup & Terkunci Permanen)
```

---

#### Langkah 1: Pembuatan Draf Invoice dengan Kalkulasi Otomatis (oleh Finance)
- **Pelaksana (Role)**: `Finance & Payroll` (`finance_payroll`)
- **Lokasi Menu**: Panel Admin (`/admin`) → Grup **Payroll & Biaya** → Menu **Invoice** (`/admin/invoices`) → Klik tombol **`Buat`**.

##### Tabel Input Formulir Pembuatan Invoice (`InvoiceForm`):
| Nama Field di Layar | Tipe Input | Wajib / Opsional | Penjelasan & Perilaku Otomatis Sistem |
| :--- | :--- | :---: | :--- |
| **Nomor Invoice** (`invoice_number`) | Teks Unik | **Wajib** | Masukkan nomor dokumen tagihan (contoh: `INV/2026/10/0001`). |
| **Klien** (`client_id`) | Pilihan (*Select*) | **Wajib** | Pilih nama Klien Maklon. Mengubah klien akan menyaring daftar Order milik klien tersebut. |
| **Order** (`order_id`) | Pilihan (*Select*) | **Wajib** *(untuk auto-hitung)* | Pilih pesanan yang akan ditagihkan. **Begitu Order dipilih, sistem (`InvoiceService::calculateAmountForOrder`) otomatis menampilkan tabel pratinjau *Rincian Kalkulasi*!** |
| **PPN (%)** (`tax_percent`) | Angka (`0–100%`) | Opsional | Diisi oleh Finance (contoh: `11` untuk PPN 11%, atau `0` jika tanpa pajak). Saat diubah, nilai PPN dan Total Tagihan otomatis dihitung ulang. |
| **Total Tagihan (Rp)** (`amount`) | Nominal (`Read-Only`) | **Otomatis** | **Terisi otomatis** dari rumus: $\text{Subtotal Ongkos Jahit} + \text{PPN}$. *(Catatan: Alokasi biaya overhead untuk skema Semi-CMT / Full Package ditampilkan dalam kotak biru sebagai **Catatan Internal Pabrik** dan disimpan ke database untuk laporan laba-rugi internal, namun **tidak ditambahkan** ke tagihan klien).* |
| **Tanggal Terbit** (`issued_at`) | Tanggal (*Date*) | Opsional | Jika dikosongkan, otomatis terisi tanggal hari ini saat tombol *Terbitkan* diklik. |
| **Jatuh Tempo** (`due_at`) | Tanggal (*Date*) | Opsional | Batas tanggal pembayaran tagihan oleh klien. |
| **Catatan** (`notes`) | Teks Panjang | Opsional | Informasi rekening bank tujuan transfer Lukla dan catatan penagihan. |

- Klik tombol **`Buat`** untuk menyimpan invoice. Status awal invoice adalah **`Draf` (`DRAFT`)**.

---

#### Langkah 2: Menerbitkan Invoice ke Portal Klien (`Terbitkan`)
- **Pelaksana (Role)**: `Finance & Payroll` (`finance_payroll`)
- **Langkah Penggunaan**:
  1. Pada tabel daftar **Invoice** (`/admin/invoices`), periksa kembali rincian tagihan dengan mengklik tombol abu-abu **`Detail`**.
  2. Klik tombol kuning **`Terbitkan`** (`issue`) pada baris invoice berstatus `Draf`, lalu konfirmasi pada modal.
- **Output**:
  - Status invoice berubah dari `Draf (DRAFT)` menjadi **`Terbit` (`ISSUED`)**, mencatat `issued_at` dan `issued_by`.
  - Invoice kini tampil secara *real-time* di menu **Tagihan & Invoice** pada Portal Klien (`/portal/{kode-klien}/invoices`).

---

#### Langkah 3: Pemeriksaan Rincian Biaya & Konfirmasi Pembayaran oleh Klien Maklon (di Portal Klien)
- **Pelaksana (Role)**: `Klien Maklon` (`klien_maklon`)
- **Lokasi Menu**: Panel Portal Klien (`/portal/{kode-klien}`) → Menu Sidebar **Tagihan & Invoice** (`/portal/{kode-klien}/invoices`).
- **Langkah Penggunaan oleh Klien**:
  1. Klien membuka menu **Tagihan & Invoice**. Pada baris invoice berstatus **`Terbit`**, klien dapat mengklik tombol abu-abu **`Rincian Biaya`** untuk melihat rincian transparan ongkos jahit per komponen (Nama Komponen, Qty Terverifikasi, Tarif/pcs, Subtotal), nilai PPN, dan Total Tagihan Klien *(tanpa melihat catatan overhead internal pabrik)*.
  2. Setelah melakukan pembayaran/transfer bank, klien mengklik tombol hijau **`Konfirmasi Pembayaran`** pada baris invoice tersebut.
  3. Pada modal **Konfirmasi Pembayaran** yang terbuka, klien mengisi:

| Nama Field di Layar | Tipe Input | Wajib / Opsional | Penjelasan & Contoh Pengisian |
| :--- | :--- | :---: | :--- |
| **Metode Pembayaran** (`payment_method`) | Pilihan (*Select*) | **Wajib** | Pilih rekening bank yang digunakan: `Transfer Bank BCA`, `Transfer Bank Mandiri`, `Transfer Bank BNI`, `Transfer Bank BRI`, atau `Lainnya / Tunai`. |
| **Catatan Pengirim / No. Referensi Transfer** (`payment_notes`) | Teks | **Wajib** | Contoh: *"Transfer via m-BCA a.n. PT Maklon Demo, No. Ref: 88291029"*. |
| **Unggah Bukti Transfer** (`payment_proof_path`) | Unggah Gambar | Opsional *(Sangat Disarankan)* | Unggah tangkapan layar (*screenshot*) atau foto struk bukti transfer bank (JPG/PNG, maks 5 MB). |

  4. Klik tombol **`Simpan / Kirim`** pada modal.
- **Output**:
  - Status invoice berubah dari `Terbit (ISSUED)` menjadi **`Menunggu Konfirmasi Finance` (`PAYMENT_CONFIRMED`)**.
  - Waktu konfirmasi (`payment_confirmed_at`), metode pembayaran, catatan referensi, dan file bukti transfer tersimpan di dalam invoice.

---

#### Langkah 4: Verifikasi Bukti Transfer & Pelunasan Invoice oleh Finance (`Tandai Lunas`)
- **Pelaksana (Role)**: `Finance & Payroll` (`finance_payroll`)
- **Lokasi Menu**: Panel Admin (`/admin`) → Grup **Payroll & Biaya** → Menu **Invoice** (`/admin/invoices`).
- **Langkah Penggunaan oleh Finance**:
  1. Pada tabel **Invoice**, cari baris tagihan yang berstatus biru **`Menunggu Konfirmasi Finance` (`PAYMENT_CONFIRMED`)** (atau `Terbit` jika klien mengonfirmasi secara langsung ke Finance).
  2. Klik tombol abu-abu **`Detail`** pada baris tersebut:
     - Di bagian atas modal rincian akan muncul kotak biru **"📌 Konfirmasi Pembayaran dari Klien"** yang menampilkan Waktu Konfirmasi, Metode Pembayaran, Catatan Klien, serta tautan **`Lihat Bukti Transfer`**.
     - Klik **`Lihat Bukti Transfer`** untuk membuka gambar bukti transfer bank dan mencocokkannya dengan mutasi rekening bank perusahaan.
  3. Setelah dana dipastikan masuk, tutup modal detail lalu klik tombol hijau **`Tandai Lunas`** (`pay`) pada baris invoice tersebut dan klik **Konfirmasi**.

#### Output Akhir Saat Invoice Ditandai Lunas (`InvoiceService::markPaid`):
1. **Status Invoice Menjadi Lunas**: Status invoice berubah menjadi **`Lunas` (`PAID`)**.
2. **Stok Inventaris Menjadi Barang Jadi**: Seluruh item inventaris (`InventoryItem`) yang terikat pada pesanan tersebut otomatis diperbarui statusnya menjadi **`Barang Jadi` (`FINISHED`)**.
3. **Penutupan & Penguncian Order Otomatis (`CLOSED`)**:
   - Apabila seluruh unit pesanan telah selesai (`COMPLETED`) dan seluruh invoice pesanan telah `PAID`, sistem secara otomatis memanggil `$order->close()` yang mengubah status pesanan menjadi **`Ditutup` (`CLOSED`)**.
   - Jika order sebelumnya belum berstatus `COMPLETED`, sistem menandainya sebagai `COMPLETED` terlebih dahulu, dan Finance/Owner dapat menekan tombol **`Tutup Order (Close)`** di menu **Daftar Pesanan** (`/admin/orders`).
   - Pesanan yang telah berstatus **`CLOSED`** terkunci secara permanen sebagai arsip transaksi yang utuh dan siap diaudit kapan saja.

---

## BAB 10: INTEGRASI BOT TELEGRAM & TROUBLESHOOTING (FAQ)

### 10.1 Panduan Koneksi & Penggunaan `@ClientBot` dan `@OwnerBot`

Aplikasi mendukung asisten pintar dan notifikasi instan melalui Telegram:

#### Cara Menautkan Akun ke Telegram:
1. **Cara 1 — Mandiri melalui Widget Dashboard Portal (Khusus Klien Maklon)**:
   - Login ke Portal Klien (`/portal/{kode-klien}`).
   - Pada halaman **Dashboard**, lihat kartu widget **Koneksi Bot Telegram**.
   - Klik tombol **`Hubungkan Telegram`** untuk membuka aplikasi Telegram menuju `@ClientBot` dengan tautan token otomatis (`/start <token>`), lalu tekan **Start** di Telegram.
2. **Cara 2 — Melalui Token dari Owner (Untuk Seluruh Role yang Didukung)**:
   - Login sebagai **Owner** di `/admin/users` (menu **Master Data → Pengguna**).
   - Klik tombol biru **`Token Telegram`** pada baris pengguna yang ingin dihubungkan.
   - Salin kode token 8 karakter yang muncul pada notifikasi (berlaku 24 jam).
   - Pengguna membuka `@ClientBot` (untuk Klien) atau `@OwnerBot` (untuk Owner/Staf Internal) di Telegram dan mengirimkan pesan `/start <TOKEN>`.

#### Daftar Perintah & Kemampuan Bot Telegram:
| Perintah / Fitur | Pengguna | Fungsi & Contoh Penggunaan |
| :--- | :--- | :--- |
| `/orders` | Klien Maklon | Menampilkan daftar 10 pesanan terbaru milik klien beserta statusnya. |
| `/status <nomor_order>` | Klien & Staf Internal | Menampilkan rincian status terkini, skema maklon, dan jumlah unit dari satu pesanan (contoh: `/status ORD-20261016-A1B2`). |
| **Tombol Persetujuan Inline** | Klien Maklon | Saat Owner mengirim penawaran harga atau Tim Sample mengajukan sample, bot mengirim pesan berisi tombol **Setujui** / **Tolak** yang dapat diklik langsung dari Telegram. |
| **Tanya Jawab AI Klien** (`BedrockClientBotService`) | Klien Maklon | Ketik pertanyaan bebas dalam bahasa Indonesia, seperti: *"Bagaimana progres pesanan saya?"*, *"Berapa sisa stok kain saya di gudang?"*, atau *"Apa bedanya skema CMT dan Semi-CMT?"*. |
| **Tanya Jawab AI Eksekutif** (`BedrockOwnerBotService`) | Owner | Ketik pertanyaan lintas pabrik kepada `@OwnerBot`, seperti: *"Ringkasan pabrik hari ini"*, *"Tagihan klien PT Maklon Demo yang belum lunas"*, atau *"Status order ORD-..."*. |

---

### 10.2 Daftar Periksa Kendala Umum (*Troubleshooting Guide / FAQ*)

| No | Gejala / Kendala yang Dihadapi Pengguna | Penyebab di Sistem | Solusi Langkah Penyelesaian |
| :---: | :--- | :--- | :--- |
| 1 | Tombol **`Mulai Cutting`** tidak muncul pada baris pesanan di menu *Alur Bahan Pesanan*. | Status alur bahan pesanan belum mencapai **`Standby (Bahan Lengkap)`** karena masih ada item bahan yang jumlah diterimanya kurang dari kebutuhan order. | Klik tombol **`Daftar Barang & Bahan`** untuk melihat item mana yang masih memiliki nilai *Kekurangan > 0*, lalu lakukan **`ACC Kedatangan Gudang`** hingga seluruh item berstatus `Standby di Gudang`. |
| 2 | Muncul pesan error merah saat mengeluarkan atau mentransfer stok di *Pergerakan Stok*. | Pesanan tujuan (`destination_order_id`) yang dipilih berbeda pemilik `client_id` atau `order_id`-nya dengan bahan yang dipilih (proteksi *Zero Cross-Contamination* FR-007), atau jumlah melebihi saldo stok. | Pastikan memilih bahan yang memiliki `Tag ID` sesuai dengan Klien dan Nomor Order tujuan, serta pastikan jumlah pengeluaran tidak melebihi saldo yang tersedia. |
| 3 | Tombol **`Input Perakitan`** di halaman *Output 30 Menit (Review)* berwarna abu-abu (tidak bisa diklik). | Masih ada laporan output penjahit yang berstatus `Menunggu Verifikasi (SUBMITTED)` atau masih ada komponen artikel yang belum memiliki laporan output terverifikasi sama sekali. | Klik tombol **`Verifikasi Semua`** terlebih dahulu untuk memverifikasi laporan yang masih menunggu, dan pastikan setiap komponen artikel sudah diselesaikan oleh penjahitnya. |
| 4 | Saat **Penjahit** menekan tombol **`Kerjakan`**, kolom *Jumlah Selesai (pcs)* dan tombol *Kirim* terkunci. | Penjahit belum menjalankan dan menghentikan **Stopwatch** kerja pada modal tersebut. | Tekan tombol hijau **`Mulai`** saat mulai menjahit, lalu tekan tombol merah **`Stop`** setelah selesai. Kolom input jumlah selesai akan langsung terbuka. |
| 5 | Saat **Finance** klik **`Hitung`** di menu *Periode Payroll*, muncul peringatan *"Tidak ada data pada periode ini (0 penjahit)"*. | Tidak ada `OutputEntry` berstatus `Terverifikasi (VERIFIED)` pada rentang tanggal `Awal Periode s/d Akhir Periode` tersebut (mungkin masih `Menunggu Verifikasi` atau tanggal periode tidak mencakup tanggal kerja penjahit). | 1. Pastikan **Kepala Produksi** sudah mengklik `Verifikasi` / `Verifikasi Semua` di menu *Output 30 Menit*.<br>2. Pastikan seluruh komponen telah memiliki **Rate Card** berstatus `Disetujui (APPROVED)`.<br>3. Sesuaikan rentang `Awal Periode` dan `Akhir Periode` pada *Periode Payroll*. |
| 6 | Saat **Finance** memilih Order pada formulir pembuatan **Invoice**, muncul peringatan kuning *"X komponen tanpa rate card aktif dikecualikan"*. | Komponen jahit pada order tersebut belum diatur tarif borongannya oleh Kepala Produksi atau *Rate Card*-nya masih berstatus `Menunggu Persetujuan (PENDING)`. | Buka menu **Rate Card** (`/admin/rate-cards`) atau **Sample Disetujui** (`/admin/approved-samples`), lalu klik **`Setujui`** pada tarif komponen yang bersangkutan. |
| 7 | Tombol **`Tandai Lunas`** pada menu *Invoice* tidak muncul. | Invoice masih berstatus **`Draf` (`DRAFT`)** atau sudah berstatus **`Lunas` (`PAID`)**. | Klik tombol kuning **`Terbitkan`** terlebih dahulu agar status invoice menjadi `Terbit (ISSUED)`. Setelah itu tombol `Tandai Lunas` akan tersedia. |
