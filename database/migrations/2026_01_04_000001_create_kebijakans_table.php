<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kebijakans', function (Blueprint $table) {
            $table->id();
            $table->decimal('plafon_maksimal', 15, 2)->default(50000000);
            $table->decimal('suku_bunga_persen', 5, 2)->default(2.5);
            $table->decimal('simpanan_wajib_nominal', 15, 2)->default(250000);
            $table->text('catatan_terakhir')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kebijakans');
    }
};
