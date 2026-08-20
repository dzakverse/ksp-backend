<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kebijakans', function (Blueprint $table) {
            // Nominal minimal simpanan pokok per setoran, ditetapkan Ketua.
            // Selama ini kebijakan hanya mengatur simpanan wajib; simpanan pokok
            // tidak punya batas minimal sehingga Bendahara bisa input berapapun.
            $table->decimal('simpanan_pokok_nominal', 15, 2)
                ->default(100000)
                ->after('simpanan_wajib_nominal');
        });
    }

    public function down(): void
    {
        Schema::table('kebijakans', function (Blueprint $table) {
            $table->dropColumn('simpanan_pokok_nominal');
        });
    }
};
