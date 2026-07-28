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
            $table->string('kode')->unique(); // Contoh: LN-2026-001
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Finansial & Kalkulasi
            $table->decimal('jumlah', 15, 2);
            $table->integer('tenor_bulan');
            $table->decimal('suku_bunga_persen', 5, 2)->default(0); // Tambahan dari versi coba
            $table->decimal('angsuran_per_bulan', 15, 2)->nullable(); // Tambahan dari versi coba
            
            // Detail & Lampiran
            $table->text('alasan')->nullable();
            $table->string('bukti_pendukung')->nullable(); // Tambahan dari versi coba
            
            // Status Approval & Verifikasi FE
            $table->enum('status', [
                'MENUNGGU', 
                'DISETUJUI_BENDAHARA', 
                'DISETUJUI', 
                'DITOLAK', 
                'LUNAS' // Tambahan status LUNAS
            ])->default('MENUNGGU');
            
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_verifikasi')->nullable();

            // Tracking Bypass Super Admin Filament
            $table->boolean('is_bypassed')->default(false);
            $table->foreignId('bypassed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexing Performance
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinjamans');
    }
};