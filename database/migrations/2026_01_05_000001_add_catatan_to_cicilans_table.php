<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cicilans', function (Blueprint $table) {
            $table->text('catatan')->nullable()->after('status');
            $table->foreignId('dibayar_oleh')->nullable()->after('catatan')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cicilans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dibayar_oleh');
            $table->dropColumn('catatan');
        });
    }
};
