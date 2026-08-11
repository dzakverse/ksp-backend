<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pinjamans', 'sisa_pokok')) {
            return; // Sudah ada (mis. ditambahkan manual sebelumnya) -> skip, aman dijalankan ulang.
        }

        Schema::table('pinjamans', function (Blueprint $table) {
            // Kolom ini sebelumnya sudah dipakai di PinjamanController (store & restrukturisasi)
            // tapi belum pernah ada migration resminya -> ditambahkan di sini supaya
            // skema database konsisten di semua environment (bukan cuma di DB lokal
            // yang sudah "terlanjur" punya kolom ini).
            $table->decimal('sisa_pokok', 15, 2)->default(0)->after('jumlah');
        });
    }

    public function down(): void
    {
        Schema::table('pinjamans', function (Blueprint $table) {
            $table->dropColumn('sisa_pokok');
        });
    }
};
