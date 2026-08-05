<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simpanans', function (Blueprint $table) {
            // Siapa yang mengeksekusi/konfirmasi transaksi PENDING (mis. Bendahara
            // yang menyetujui request tarik dari Anggota). Beda dari created_by
            // yang mencatat siapa pemrakarsa transaksi (Anggota sendiri / admin).
            $table->foreignId('diproses_oleh')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('simpanans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diproses_oleh');
        });
    }
};
