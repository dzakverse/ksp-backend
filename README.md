# Backend Starter — KSP Sejahtera (Laravel + MySQL)

File-file di sini adalah **kode siap-pakai**, bukan project Laravel penuh (composer
tidak bisa dijalankan di sandbox ini). Ikuti langkah di bawah untuk memasangnya.

## 1. Buat project Laravel baru

```bash
composer create-project laravel/laravel ksp-backend
cd ksp-backend
composer require laravel/sanctum
```

## 2. Salin file dari folder ini ke project Laravel

| Dari folder ini                          | Ke project Laravel                              |
|-------------------------------------------|--------------------------------------------------|
| `database/migrations/*.php`               | `database/migrations/`                            |
| `app/Models/*.php`                        | `app/Models/`                                     |
| `app/Http/Controllers/Api/*.php`          | `app/Http/Controllers/Api/`                       |
| `app/Http/Middleware/CheckRole.php`       | `app/Http/Middleware/`                            |
| `routes/api.php`                          | `routes/api.php` (replace/merge)                  |

## 3. Daftarkan middleware `role`

Di `bootstrap/app.php` (Laravel 11+), tambahkan di dalam `->withMiddleware()`:

```php
$middleware->alias([
    'role' => \App\Http\Middleware\CheckRole::class,
]);
```

## 4. Setup .env & database

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

Buat database `ksp_sejahtera` di MySQL, lalu:

```bash
php artisan migrate
```

## 5. Aktifkan CORS (WAJIB biar FE bisa akses)

Publish config CORS bawaan Laravel lalu edit `config/cors.php`:

```php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => ['http://localhost:5173'], // ganti sesuai URL FE
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

## 6. Seeder akun + data demo (biar Beranda/Simpananku/Pinjaman/Profil langsung keisi)

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

// Data simpanan contoh
$anggota->simpanans()->create(['jenis' => 'POKOK', 'tipe' => 'SETOR', 'jumlah' => 500000, 'tanggal' => '2021-01-15', 'keterangan' => 'Setoran awal keanggotaan']);
$anggota->simpanans()->create(['jenis' => 'WAJIB', 'tipe' => 'SETOR', 'jumlah' => 200000, 'tanggal' => '2026-07-01', 'keterangan' => 'Simpanan wajib Juli']);
$anggota->simpanans()->create(['jenis' => 'SUKARELA', 'tipe' => 'SETOR', 'jumlah' => 1000000, 'tanggal' => '2026-07-05']);
$anggota->simpanans()->create(['jenis' => 'SUKARELA', 'tipe' => 'TARIK', 'jumlah' => 500000, 'tanggal' => '2026-07-02']);

// Data pinjaman contoh (langsung DISETUJUI biar keliatan di card "Pinjaman Aktif")
$pinjaman = $anggota->pinjamans()->create([
    'kode' => 'LN-2026-001', 'jumlah' => 5000000, 'tenor_bulan' => 12,
    'alasan' => 'Kebutuhan mendesak', 'status' => 'DISETUJUI',
]);
$pinjaman->cicilans()->create(['cicilan_ke' => 1, 'jumlah' => 458333, 'jatuh_tempo' => '2026-08-01', 'tanggal_bayar' => '2026-08-01', 'status' => 'LUNAS']);

// Pengajuan baru yang masih MENUNGGU verifikasi, biar antrean Bendahara ada isinya
$anggota2 = \App\Models\User::where('nip', 'anggota2')->first();
$anggota2->pinjamans()->create([
    'kode' => 'LN-2026-002', 'jumlah' => 25000000, 'tenor_bulan' => 24,
    'alasan' => 'Renovasi Rumah', 'status' => 'MENUNGGU',
]);
```

## 7. Jalankan server

```bash
php artisan serve
# -> http://127.0.0.1:8000
```

API sekarang bisa diakses di `http://127.0.0.1:8000/api/...` — lihat `routes/api.php`
untuk daftar lengkap endpoint dan halaman FE mana yang memakainya.

## Endpoint yang sudah dibuatkan

| Method | Endpoint                              | Dipakai di FE                          | Role         |
|--------|-----------------------------------------|-----------------------------------------|--------------|
| POST   | /api/login                              | pages/login.jsx                         | public       |
| GET    | /api/me                                 | validasi token saat reload              | semua login  |
| POST   | /api/logout                             | tombol logout                           | semua login  |
| GET    | /api/dashboard                          | pages/dashboard.jsx (Beranda)           | ANGGOTA      |
| GET    | /api/simpanan                           | pages/simpananku.jsx                    | ANGGOTA      |
| GET    | /api/pinjaman                           | pages/pinjaman.jsx                      | ANGGOTA      |
| GET    | /api/profile                            | pages/profil.jsx (semua role)           | semua login  |
| POST   | /api/pinjaman                           | pages/ajukan.jsx                        | ANGGOTA      |
| POST   | /api/change-password                    | UbahPassword.jsx (semua role)           | semua login  |
| GET    | /api/admin/dashboard                    | bendahara/DashboardBendahara.jsx        | BENDAHARA/KETUA |
| GET    | /api/admin/anggota                      | bendahara/DataAnggota.jsx               | BENDAHARA/KETUA |
| GET    | /api/admin/anggota/{id}                 | bendahara/DetailAnggota.jsx             | BENDAHARA/KETUA |
| POST   | /api/admin/anggota/{id}/simpanan        | bendahara/DetailAnggota.jsx (form)      | BENDAHARA/KETUA |
| PATCH  | /api/admin/anggota/{id}/status          | bendahara/DetailAnggota.jsx (toggle)    | BENDAHARA/KETUA |
| GET    | /api/admin/pinjaman                     | bendahara/VerifikasiPinjaman.jsx        | BENDAHARA/KETUA |
| GET    | /api/admin/pinjaman/{id}                | bendahara/VerifikasiDetail.jsx          | BENDAHARA/KETUA |
| POST   | /api/admin/pinjaman/{id}/verifikasi     | bendahara/VerifikasiDetail.jsx          | BENDAHARA/KETUA |
| POST   | /api/ketua/pinjaman/{id}/persetujuan    | ketua/PersetujuanPinjaman.jsx           | KETUA        |

Halaman Ketua yang belum dibuatkan endpoint-nya (menyusul di tahap berikutnya):
`EmergencyBypass.jsx`, `KendaliKebijakan.jsx`, `PengurusAnggota.jsx`, tab "Bypass" di
`PersetujuanPinjaman.jsx`, dan Audit Log Tracker di `DashboardKetua.jsx`. Pola controller
di atas bisa langsung dicontek untuk bikin endpoint-endpoint tersebut nanti.
