# Pengembangan Sistem Notifikasi Otomatis Berbasis WhatsApp pada SLiMS

Proyek Tugas Akhir ini mengembangkan fitur notifikasi WhatsApp otomatis pada **SLiMS (Senayan Library Management System) 9 Bulian**. Pesan dikirim melalui **Wablas** dan ditujukan kepada anggota perpustakaan, khususnya sebagai pengingat pengembalian buku.

## Gambaran Singkat

Petugas perpustakaan perlu mengingatkan anggota tentang buku yang akan jatuh tempo atau telah terlambat dikembalikan. Proyek ini menghubungkan data peminjaman SLiMS dengan layanan WhatsApp Wablas agar pengingat dapat diproses otomatis berdasarkan jadwal yang dikonfigurasi.

**Alur sederhananya:**

1. Sistem membaca data peminjaman dan nomor WhatsApp anggota dari SLiMS.
2. Sistem memilih peminjaman yang sesuai dengan jadwal pengingat aktif.
3. Isi pesan disusun menggunakan template yang dapat dikelola petugas.
4. Pesan dikirim melalui API Wablas.
5. Hasil pengiriman dicatat agar dapat ditinjau melalui fitur log.

## Fitur

- Integrasi pengiriman pesan WhatsApp melalui Wablas.
- Pengaturan URL API, token, dan secret key Wablas melalui halaman administrasi.
- Pengelolaan template pesan dan jadwal pengingat.
- Pengingat berdasarkan tanggal jatuh tempo, termasuk buku yang jatuh tempo atau terlambat.
- Dashboard untuk melihat status koneksi perangkat, kuota, dan masa aktif Wablas.
- Pencatatan hasil pengiriman notifikasi dan pemeriksaan riwayat melalui halaman log.
- Pemrosesan otomatis terjadwal melalui skrip CLI/cron; proses juga dapat dijalankan secara manual dari administrasi.

## Teknologi

- **Aplikasi utama:** PHP dan SLiMS 9 Bulian
- **Basis data:** MySQL atau MariaDB
- **Layanan pesan:** Wablas WhatsApp API
- **Otomatisasi:** cron pada Task Scheduler (Development) atau Server cPanel (Production) pada Windows

## Kebutuhan Sistem

Ikuti kebutuhan SLiMS 9 Bulian, termasuk:

- PHP **8.1 atau lebih baru**
- MySQL **5.7 atau lebih baru** atau MariaDB **10.3 atau lebih baru**
- Ekstensi PHP GD, gettext, mbstring, cURL, dan JSON
- Composer untuk memasang dependensi PHP proyek
- Akun Wablas yang aktif, perangkat WhatsApp yang terhubung, serta kuota pesan yang mencukupi

## Instalasi dan Konfigurasi

### 1. Siapkan SLiMS

Pasang proyek ini pada web server yang memenuhi kebutuhan di atas. Konfigurasikan koneksi basis data dan selesaikan instalasi SLiMS sesuai prosedur SLiMS 9 Bulian.

Dari direktori utama proyek, pasang dependensi Composer:

```bash
composer install
```

### 2. Siapkan tabel modul notifikasi

Selain tabel bawaan SLiMS, modul notifikasi menggunakan tabel `wa_settings`, `wa_templates`, dan `wa_logs`. **Repositori ini belum menyertakan skrip migrasi atau installer otomatis untuk tabel-tabel tersebut.** Sebelum menggunakan modul, pastikan tabel dan data awalnya sudah tersedia di basis data SLiMS sesuai kolom yang digunakan oleh kode modul.

### 3. Masukkan kredensial Wablas

Masuk ke administrasi SLiMS, buka pengaturan WhatsApp Notification, lalu masukkan:

- Base URL API Wablas
- API token
- Secret key

Nilai tersebut diperoleh dari akun Wablas. Jangan menaruh token atau secret key di README, repositori publik, tangkapan layar, maupun log yang dibagikan.

### 4. Siapkan data dan template

- Pastikan data anggota memiliki nomor WhatsApp yang valid.
- Atur jadwal dan aktifkan template pesan yang ingin digunakan.
- Template mendukung variabel seperti `{member_name}`, `{member_id}`, `{book_title}`, `{item_code}`, dan `{due_date}`.
- Periksa dashboard untuk memastikan Wablas dapat dihubungi dan perangkat WhatsApp tersambung.

### 5. Aktifkan proses otomatis

Skrip cron modul berjalan melalui PHP CLI. Contoh untuk Linux, dengan penyesuaian lokasi PHP dan direktori instalasi:

```cron
0 7 * * * /usr/bin/php /path/to/slims/admin/modules/wa_notification/cron.php >> /path/to/slims/admin/modules/wa_notification/cron.log 2>&1
```

Contoh tersebut menjalankan proses setiap hari pukul 07.00. Pastikan zona waktu server sesuai kebutuhan; skrip menetapkan zona waktu `Asia/Jakarta`. Pada Windows, jadwalkan `admin/modules/wa_notification/cron.php` menggunakan Task Scheduler dan executable PHP CLI yang terpasang.

## Penggunaan dan Pemantauan

1. Periksa status Wablas di dashboard.
2. Atur template serta jadwal pengingat di halaman administrasi modul.
3. Jalankan proses secara manual untuk pengujian awal, lalu periksa hasilnya pada halaman log.
4. Setelah pengiriman uji berhasil, aktifkan jadwal otomatis dan pantau log secara berkala.

Pengiriman membutuhkan koneksi server ke API Wablas, konfigurasi kredensial yang benar, kuota yang cukup, dan nomor WhatsApp anggota yang dapat dihubungi. Layanan Wablas merupakan layanan eksternal dan dapat memiliki ketentuan maupun biaya tersendiri.

## Struktur Bagian Notifikasi

```text
admin/modules/wa_notification/
├── dashboard.php                 # Status Wablas dan informasi gateway
├── settings.php                  # Pengaturan koneksi Wablas
├── templates.php                 # Template dan jadwal notifikasi
├── logs.php                      # Riwayat pengiriman
├── cron.php                      # Pemroses notifikasi terjadwal (CLI)
├── processor.php                 # Endpoint proses untuk administrasi
└── lib/
    ├── NotificationService.class.php
    └── WablasAPI.class.php
```

## Catatan untuk Pengembang

- Proses bisnis notifikasi berada di `admin/modules/wa_notification/lib/NotificationService.class.php`.
- Komunikasi dengan Wablas berada di `admin/modules/wa_notification/lib/WablasAPI.class.php`.
- Skrip cron hanya untuk CLI dan memuat konfigurasi SLiMS sebelum menjalankan proses notifikasi.
- Saat mengembangkan fitur, periksa kembali kecocokan struktur tabel tambahan modul dengan query yang digunakan oleh service dan halaman administrasi.

## Lisensi

SLiMS didistribusikan di bawah lisensi GNU GPL versi 3. Lihat berkas [LICENSE](./LICENSE) untuk informasi lisensi pada repositori ini.
