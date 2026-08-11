<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kebijakans', function (Blueprint $table) {
            // Minimal persentase tenor yang sudah lunas sebelum anggota boleh
            // mengajukan Top-Up atas pinjaman aktifnya (mis. 30 = 30%).
            $table->decimal('minimal_progress_topup_persen', 5, 2)->default(30)->after('simpanan_wajib_nominal');
        });
    }

    public function down(): void
    {
        Schema::table('kebijakans', function (Blueprint $table) {
            $table->dropColumn('minimal_progress_topup_persen');
        });
    }
};
