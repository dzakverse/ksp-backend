<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kas_transaksis', function (Blueprint $table) {
            $table->id();
            $table->enum('tipe', ['MASUK', 'KELUAR'])->default('KELUAR');
            $table->decimal('jumlah', 15, 2);
            $table->text('catatan');
            $table->date('tanggal');
            $table->foreignId('dicatat_oleh')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_transaksis');
    }
};
