<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simpanans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('jenis', ['POKOK', 'WAJIB', 'SUKARELA']);
            $table->enum('tipe', ['SETOR', 'TARIK'])->default('SETOR');
            $table->decimal('jumlah', 15, 2);
            $table->string('keterangan')->nullable();
            $table->enum('status', ['BERHASIL', 'PENDING', 'GAGAL'])->default('BERHASIL');
            $table->date('tanggal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simpanans');
    }
};
