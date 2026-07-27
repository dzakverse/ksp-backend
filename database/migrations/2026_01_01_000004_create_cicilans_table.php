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
            $table->foreignId('pinjaman_id')->constrained()->cascadeOnDelete();
            $table->integer('cicilan_ke');
            $table->decimal('jumlah', 15, 2);
            $table->date('jatuh_tempo');
            $table->date('tanggal_bayar')->nullable();
            $table->enum('status', ['BELUM_BAYAR', 'LUNAS', 'TELAT'])->default('BELUM_BAYAR');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cicilans');
    }
};
