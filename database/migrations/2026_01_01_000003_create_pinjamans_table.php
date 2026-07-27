<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinjamans', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique(); // contoh: LN-2026-001
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('jumlah', 15, 2);
            $table->integer('tenor_bulan');
            $table->text('alasan')->nullable();
            $table->enum('status', ['MENUNGGU', 'DISETUJUI_BENDAHARA', 'DISETUJUI', 'DITOLAK'])
                  ->default('MENUNGGU');
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinjamans');
    }
};
