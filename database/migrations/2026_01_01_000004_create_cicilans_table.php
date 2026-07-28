<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cicilans', function (Blueprint $table) {
            $table->id();
            
            // Perbaikan penting: Sebutkan 'pinjamans' di constrained() agar tidak mencari 'pinjamen'
            $table->foreignId('pinjaman_id')->constrained('pinjamans')->cascadeOnDelete();
            
            $table->integer('cicilan_ke');
            $table->decimal('jumlah', 15, 2);
            $table->date('jatuh_tempo');
            $table->date('tanggal_bayar')->nullable();
            
            // Status lengkap dari versi Asli
            $table->enum('status', ['BELUM_BAYAR', 'LUNAS', 'TELAT'])->default('BELUM_BAYAR');
            
            // Catatan tambahan dari versi coba
            $table->text('keterangan')->nullable();
            
            $table->timestamps();

            // Mencegah duplikasi nomor cicilan pada satu pinjaman
            $table->unique(['pinjaman_id', 'cicilan_ke']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cicilans');
    }
};