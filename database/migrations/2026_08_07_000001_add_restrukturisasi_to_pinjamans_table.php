<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pinjamans', function (Blueprint $table) {
            // Restrukturisasi / Top-up Pinjaman (fitur Ketua):
            // Pinjaman lama yang sisa pokoknya digabung ke pinjaman baru.
            $table->foreignId('pinjaman_lama_id')->nullable()->after('user_id')
                ->constrained('pinjamans')->nullOnDelete();
            $table->boolean('is_restrukturisasi')->default(false)->after('is_bypassed');
            $table->decimal('sisa_pokok_lama', 15, 2)->nullable()->after('is_restrukturisasi');
        });
    }

    public function down(): void
    {
        Schema::table('pinjamans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinjaman_lama_id');
            $table->dropColumn(['is_restrukturisasi', 'sisa_pokok_lama']);
        });
    }
};
