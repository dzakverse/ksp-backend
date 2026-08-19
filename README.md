# Backend — KSP Sejahtera (Laravel + MySQL + Filament)

Backend Laravel untuk aplikasi koperasi simpan pinjam. Dua permukaan akses:

- **REST API** (`routes/api.php`, prefix `/api/...`) — dipakai FE React (Anggota/Bendahara/Ketua).
- **Panel Admin Filament** di `/admin` — khusus role `SUPER_ADMIN`, dipakai untuk audit & operasional darurat.

## 1. Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate
```

## 2. Setup `.env` & database

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ksp_sejahtera
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DOMAIN=localhost
FRONTEND_URL=http://localhost:5173
```

Buat database `ksp_sejahtera` di MySQL (pastikan servernya jalan), lalu:

```bash
php artisan migrate
```

## 3. Aktifkan CORS (WAJIB biar FE bisa akses)

Publish config CORS bawaan Laravel lalu edit `config/cors.php`:

```php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => ['http://localhost:5173'], // ganti sesuai URL FE
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

## 4. Seeder akun + data demo

```bash
php artisan tinker
```
```php
$anggota = \App\Models\User::create([
    'nip' => 'anggota', 'password' => bcrypt('123'), 'nama' => 'Budi Santoso',
    'role' => 'ANGGOTA', 'id_anggota' => 'ANG-2024-001', 'id_keanggotaan' => 'KSP-2024-0891',
    'unit_kerja' => 'Sekretariat', 'tanggal_bergabung' => '2021-01-15', 'nik' => '3273012903910004',
    'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1991-03-29', 'jenis_kelamin' => 'Laki-Laki',
    'alamat' => 'Jl. Gatot Subroto No. 124, Lengkong, Bandung',
    'whatsapp' => '+62 812-3456-7890', 'email' => 'budi.santoso@email.com',
]);
\App\Models\User::create(['nip' => 'anggota2', 'password' => bcrypt('123'), 'nama' => 'Siti Aminah, M.Si', 'role' => 'ANGGOTA', 'unit_kerja' => 'Rehabilitasi Sosial']);
\App\Models\User::create(['nip' => 'bendahara', 'password' => bcrypt('123'), 'nama' => 'Siti Aminah', 'role' => 'BENDAHARA']);
\App\Models\User::create(['nip' => 'ketua', 'password' => bcrypt('123'), 'nama' => 'Drs. H. Ahmad', 'role' => 'KETUA']);
// Akun panel admin Filament (/admin) - HARUS role SUPER_ADMIN, role lain ditolak di CustomLogin
\App\Models\User::create(['nip' => 'superadmin', 'password' => bcrypt('123'), 'nama' => 'Super Admin', 'role' => 'SUPER_ADMIN']);

// Data simpanan contoh
$anggota->simpanans()->create(['jenis' => 'POKOK', 'tipe' => 'SETOR', 'jumlah' => 500000, 'tanggal' => '2021-01-15', 'keterangan' => 'Setoran awal keanggotaan']);
$anggota->simpanans()->create(['jenis' => 'WAJIB', 'tipe' => 'SETOR', 'jumlah' => 200000, 'tanggal' => '2026-07-01', 'keterangan' => 'Simpanan wajib Juli']);
$anggota->simpanans()->create(['jenis' => 'SUKARELA', 'tipe' => 'SETOR', 'jumlah' => 1000000, 'tanggal' => '2026-07-05']);
$anggota->simpanans()->create(['jenis' => 'SUKARELA', 'tipe' => 'TARIK', 'jumlah' => 500000, 'tanggal' => '2026-07-02']);

// Data pinjaman contoh (langsung DISETUJUI biar keliatan di card "Pinjaman Aktif")
$pinjaman = $anggota->pinjamans()->create([
    'kode' => 'LN-2026-001', 'jumlah' => 5000000, 'tenor_bulan' => 12, 'sisa_pokok' => 5000000,
    'alasan' => 'Kebutuhan mendesak', 'status' => 'DISETUJUI',
]);
$pinjaman->cicilans()->create(['cicilan_ke' => 1, 'jumlah' => 458333, 'jatuh_tempo' => '2026-08-01', 'tanggal_bayar' => '2026-08-01', 'status' => 'LUNAS']);

// Pengajuan baru yang masih MENUNGGU verifikasi, biar antrean Bendahara ada isinya
$anggota2 = \App\Models\User::where('nip', 'anggota2')->first();
$anggota2->pinjamans()->create([
    'kode' => 'LN-2026-002', 'jumlah' => 25000000, 'tenor_bulan' => 24, 'sisa_pokok' => 25000000,
    'alasan' => 'Renovasi Rumah', 'status' => 'MENUNGGU',
]);

// Kas awal koperasi (opsional, biar saldo kas tidak 0 di /api/kas/saldo & panel admin)
\App\Models\KasTransaksi::create(['tipe' => 'MASUK', 'jumlah' => 50000000, 'catatan' => 'Setoran modal awal koperasi', 'tanggal' => now(), 'dicatat_oleh' => null]);
```

## 5. Jalankan server

```bash
php artisan serve
# -> http://127.0.0.1:8000
```

- API: `http://127.0.0.1:8000/api/...`
- Panel Admin (Filament, `SUPER_ADMIN` saja): `http://127.0.0.1:8000/admin`

## Struktur kode penting

| Path | Isi |
|---|---|
| `app/Http/Controllers/Api/*.php` | Controller REST API, dipakai FE React |
| `app/Filament/Resources/*.php` | Resource panel admin (`/admin`) — User, Pinjaman, Simpanan, KasTransaksi, dst |
| `app/Filament/Pages/CustomLogin.php` | Halaman login panel admin — validasi role `SUPER_ADMIN` + rate limiting |
| `app/Services/PinjamanService.php` | Aturan SOP pinjaman (cek numpuk pinjaman aktif, cek kas cukup, default suku bunga) — dipakai bareng oleh API dan Filament supaya aturannya konsisten di semua jalur |
| `app/Models/*.php` | Eloquent model + business rule inti (mis. `Pinjaman::generateCicilanSchedule()`, `KasTransaksi::saldoSaatIni()`) |

## Endpoint API

| Method | Endpoint | Dipakai di FE | Role |
|---|---|---|---|
| POST | `/api/login` | `pages/login.jsx` | public (throttle 5/menit) |
| GET | `/api/me` | validasi token saat reload | semua login |
| POST | `/api/logout` | tombol logout | semua login |
| POST | `/api/change-password` | `UbahPassword*.jsx` | semua login |
| GET | `/api/dashboard` | `pages/dashboard.jsx` (Beranda) | ANGGOTA |
| GET | `/api/simpanan` | `pages/simpananku.jsx` | ANGGOTA |
| POST | `/api/simpanan/tarik` | `pages/simpananku.jsx` (ajukan tarik) | ANGGOTA |
| GET | `/api/pinjaman` | `pages/pinjaman.jsx` | ANGGOTA |
| POST | `/api/pinjaman` | `pages/ajukan.jsx` (baru & top-up) | ANGGOTA |
| GET | `/api/profile` / PUT `/api/profile` | `pages/profil.jsx` | semua login |
| POST/DELETE | `/api/profile/foto` | `pages/profil.jsx` | semua login |
| GET | `/api/kebijakan` | form Ajukan Pinjaman (cek plafon) | semua login |
| GET | `/api/kas/saldo` | form Ajukan Pinjaman (cek kas cukup) | semua login |
| GET | `/api/admin/dashboard` | `admin/DashboardBendahara.jsx` | BENDAHARA/KETUA |
| GET | `/api/admin/anggota`, `/api/admin/anggota/{id}` | `admin/DataAnggota.jsx`, `admin/DetailAnggota.jsx` | BENDAHARA/KETUA |
| POST | `/api/admin/anggota/{id}/simpanan` (+`/tarik`) | `admin/DetailAnggota.jsx` | BENDAHARA/KETUA |
| PATCH | `/api/admin/anggota/{id}/status` | `admin/DetailAnggota.jsx` | BENDAHARA/KETUA |
| GET | `/api/admin/simpanan/pending`, POST `/konfirmasi` | konfirmasi tarik simpanan | BENDAHARA/KETUA |
| GET | `/api/admin/pinjaman`, `/{id}` | `admin/VerifikasiPinjaman.jsx`, `VerifikasiDetail.jsx` | BENDAHARA/KETUA |
| POST | `/api/admin/pinjaman/{id}/verifikasi` | `admin/VerifikasiDetail.jsx` | BENDAHARA/KETUA |
| POST | `/api/admin/cicilan/{cicilan}/bayar` | catat pembayaran cicilan | BENDAHARA/KETUA |
| GET | `/api/admin/kas`, POST `/api/admin/kas/tarik` | `admin/KasKoperasi.jsx` | BENDAHARA/KETUA |
| POST | `/api/ketua/pinjaman/{id}/persetujuan` | `ketua/PersetujuanPinjaman.jsx` | KETUA |
| POST | `/api/ketua/pinjaman/{id}/restrukturisasi` | restrukturisasi pinjaman aktif | KETUA |
| GET | `/api/ketua/pinjaman/bypass-queue`, POST `/{id}/bypass` | `ketua/EmergencyBypass.jsx` | KETUA |
| GET/PUT | `/api/ketua/kebijakan` | `ketua/KendaliKebijakan.jsx` | KETUA |
| GET/POST/PATCH | `/api/ketua/pengurus...` | `ketua/PengurusAnggota.jsx` | KETUA |

## Panel Admin (Filament, `/admin`)

Login khusus NIP + password dengan role `SUPER_ADMIN` (role lain ditolak & di-logout otomatis di `CustomLogin`). Resource yang tersedia: **User**, **Pinjaman**, **Simpanan**, **Kas Koperasi**.

Panel ini dipakai untuk *audit & operasional darurat* (mis. koreksi data, approve manual lewat action **Bypass Approval**), **bukan** jalur produksi utama untuk pengajuan pinjaman baru — jalur normal tetap lewat API (`ajukan.jsx` → alur verifikasi Bendahara/Ketua).

Aturan SOP yang sudah ditarik ke `PinjamanService` dan berlaku di panel ini juga (bukan cuma di API):
- Anggota tidak boleh punya 2 pinjaman `DISETUJUI` sekaligus (kecuali sedang top-up dari pinjaman itu sendiri).
- Kas koperasi harus cukup untuk mencairkan nominal pinjaman.
- Suku bunga default ikut `Kebijakan::current()`, bukan hardcode 0%.
- `jumlah`/`tenor_bulan` pinjaman yang sudah punya jadwal cicilan tidak bisa diedit langsung (jadwalnya tidak ikut ter-update) — gunakan Restrukturisasi.
- "Kas Keluar" di `KasTransaksiResource` diblokir kalau saldo kas tidak cukup, sama seperti `KasController::tarik()` di API.
- Login panel dibatasi 5x percobaan gagal per menit per NIP+IP.

## Catatan bug yang sudah diperbaiki

`PinjamanController::bayarCicilan()` sekarang mencatat `KasTransaksi` tipe `MASUK` setiap kali sebuah cicilan dibayar lunas. Sebelumnya, `KasTransaksi::saldoSaatIni()` mengurangi penuh nominal setiap pinjaman `DISETUJUI` dari saldo kas tapi tidak pernah ada entri balik saat dicicil — akibatnya saldo kas yang ditampilkan (API maupun panel admin) makin lama makin jauh dari kas riil koperasi.