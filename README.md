# Download Video (YouTube, TikTok, Instagram, Facebook)

Aplikasi web sederhana berbasis Laravel untuk download video dari YouTube, TikTok, Instagram, dan Facebook. Tinggal tempel URL, pilih kualitas, langsung download — mirip situs downloader seperti vidssave.com, tapi self-hosted.

Mesin download-nya memakai [`yt-dlp`](https://github.com/yt-dlp/yt-dlp) (mendukung ratusan situs secara otomatis) dan `ffmpeg` (untuk menggabungkan stream video+audio serta ekstrak audio ke MP3).

> **Catatan penggunaan**: gunakan hanya untuk konten yang memang boleh diunduh (video publik/milik sendiri). Hormati Ketentuan Layanan tiap platform dan hak cipta pemilik konten. Video privat/dibatasi di Instagram/Facebook bisa gagal diunduh tanpa login — ini keterbatasan platform, bukan bug aplikasi.

## Fitur

- Tempel URL → cek video (judul, thumbnail, durasi) → pilih kualitas (1080p/720p/480p/360p/Kualitas Terbaik) atau audio saja (MP3) → download.
- Mendeteksi platform otomatis (YouTube, TikTok, Instagram, Facebook) lewat `yt-dlp`, tanpa logic terpisah per situs.
- Validasi URL hanya untuk domain yang didukung (mencegah aplikasi disalahgunakan sebagai proxy download umum).
- Rate limiting bawaan (`throttle`) di endpoint API.
- File hasil download otomatis dibersihkan setelah dikirim ke browser, plus job terjadwal untuk membersihkan sisa file yang tertinggal.

## Prasyarat

Pastikan sudah terpasang di komputer:

| Kebutuhan | Versi minimal | Cek dengan |
|---|---|---|
| PHP | 8.3+ | `php -v` |
| Composer | terbaru | `composer -V` |
| Node.js & npm | terbaru | `node -v` && `npm -v` |
| Python | 3.9+ (untuk menjalankan `yt-dlp`) | `python --version` |
| ffmpeg | terbaru | `ffmpeg -version` |

`yt-dlp` sendiri dipasang lewat `pip` di langkah instalasi di bawah, tidak perlu diinstal manual dulu.

## Instalasi

1. **Clone & masuk folder project**

   ```bash
   git clone <url-repo-ini> download-video
   cd download-video
   ```

2. **Install dependency PHP & JS**

   ```bash
   composer install
   npm install
   npm run build
   ```

3. **Siapkan file environment**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Pasang `yt-dlp`**

   ```bash
   pip install -U yt-dlp
   ```

   Cek lokasi binary-nya (dibutuhkan untuk langkah 6):

   ```bash
   # Windows
   where yt-dlp
   # macOS/Linux
   which yt-dlp
   ```

5. **Pasang `ffmpeg`** (kalau belum ada)

   ```bash
   # Windows (winget)
   winget install --id Gyan.FFmpeg -e

   # macOS (Homebrew)
   brew install ffmpeg

   # Ubuntu/Debian
   sudo apt install ffmpeg
   ```

   Cek lokasi binary-nya dengan `where ffmpeg` (Windows) atau `which ffmpeg` (macOS/Linux).

6. **Isi path binary di `.env`**

   Buka `.env`, isi tiga variabel berikut sesuai hasil langkah 4 & 5:

   ```env
   YTDLP_BIN=yt-dlp
   YTDLP_PYTHONPATH=
   FFMPEG_BIN=ffmpeg
   ```

   - Kalau `yt-dlp`/`ffmpeg` sudah ada di PATH sistem, nilai default (`yt-dlp`, `ffmpeg`) sudah cukup.
   - Kalau tidak (misalnya muncul error "yt-dlp is not recognized" atau proses gagal terus), isi `YTDLP_BIN`/`FFMPEG_BIN` dengan **path lengkap** ke file `.exe`/binary-nya (pakai `/` sebagai pemisah folder meskipun di Windows, contoh: `C:/Users/nama-anda/AppData/Roaming/Python/Python312/Scripts/yt-dlp.exe`).
   - Kalau setelah itu masih muncul error `ModuleNotFoundError: No module named 'yt_dlp'`, isi `YTDLP_PYTHONPATH` dengan output dari:

     ```bash
     python -c "import site; print(site.getusersitepackages())"
     ```

7. **Siapkan database lokal** (dipakai untuk session/cache bawaan Laravel, bukan untuk fitur download)

   ```bash
   touch database/database.sqlite
   php artisan migrate
   ```

8. **Jalankan aplikasinya** — pilih salah satu:

   - **Laravel Herd** (direkomendasikan kalau sudah pakai Herd): buka Herd, klik "Park" pada folder project ini, lalu akses lewat domain `.test` yang muncul (misal `https://download-video.test`).
   - **`php artisan serve`**:

     ```bash
     php artisan serve
     ```

     lalu buka `http://127.0.0.1:8000`.

## Cara Pakai

1. Buka halaman utama aplikasi.
2. Tempel link video YouTube/TikTok/Instagram/Facebook, klik **Cek Video**.
3. Setelah muncul thumbnail, judul, dan daftar kualitas, klik kualitas yang diinginkan (termasuk **Audio (MP3)** kalau cuma butuh suaranya).
4. Browser otomatis mulai mengunduh file-nya.

## Konfigurasi (`.env`)

| Variabel | Fungsi |
|---|---|
| `YTDLP_BIN` | Path/nama binary `yt-dlp`. Default `yt-dlp` (asumsi ada di PATH). |
| `YTDLP_PYTHONPATH` | Isi hanya kalau muncul `ModuleNotFoundError: No module named 'yt_dlp'` — path ke folder `site-packages` Python. |
| `FFMPEG_BIN` | Path/nama binary `ffmpeg`. Default `ffmpeg` (asumsi ada di PATH). |

## Pembersihan File Sementara

File hasil download disimpan sementara di `storage/app/tmp/<uuid>/` lalu dihapus otomatis setelah terkirim ke browser. Kalau ada proses yang gagal di tengah jalan dan meninggalkan sisa file, jalankan job pembersihan terjadwal dengan scheduler Laravel:

```bash
php artisan schedule:work
```

(atau daftarkan `php artisan schedule:run` sebagai cron/Task Scheduler tiap menit kalau di-deploy ke server). Job `app:cleanup-tmp-downloads` akan menghapus folder temp yang lebih tua dari 1 jam.

## Troubleshooting

- **"Video tidak ditemukan atau tidak bisa diakses"** — cek dulu `yt-dlp <url>` langsung lewat terminal. Kalau juga gagal di situ, kemungkinan video privat, dihapus, atau `yt-dlp` perlu di-update (`pip install -U yt-dlp`).
- **"File hasil download tidak ditemukan"** — biasanya karena `ffmpeg` tidak ditemukan (cek `FFMPEG_BIN`) atau proses gagal karena kombinasi format tertentu; coba kualitas lain.
- **Error terkait Python/`ModuleNotFoundError`** — isi `YTDLP_PYTHONPATH` seperti dijelaskan di langkah instalasi nomor 6.
- **Video Instagram/Facebook privat gagal diunduh** — keterbatasan platform (butuh login), bukan bug aplikasi.

## Lisensi

Proyek ini dibangun di atas [Laravel](https://laravel.com), open-source dengan lisensi MIT.
