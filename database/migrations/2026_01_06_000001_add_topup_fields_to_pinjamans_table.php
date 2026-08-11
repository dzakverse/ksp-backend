<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pinjamans', function (Blueprint $table) {
            $table->boolean('is_topup')->default(false)->after('status');
            $table->foreignId('topup_dari_pinjaman_id')->nullable()->after('is_topup')
                ->constrained('pinjamans')->nullOnDelete();
            $table->decimal('potongan_pelunasan', 15, 2)->default(0)->after('topup_dari_pinjaman_id');
            $table->decimal('jumlah_pencairan_bersih', 15, 2)->nullable()->after('potongan_pelunasan');
        });
    }

    public function down(): void
    {
        Schema::table('pinjamans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('topup_dari_pinjaman_id');
            $table->dropColumn(['is_topup', 'potongan_pelunasan', 'jumlah_pencairan_bersih']);
        });
    }
};
