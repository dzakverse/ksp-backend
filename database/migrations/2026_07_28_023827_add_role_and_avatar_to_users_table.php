<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('anggota')->after('nip');
            $table->string('avatar')->nullable()->after('role');
            $table->string('telepon', 20)->nullable()->after('avatar');
            $table->text('alamat')->nullable()->after('telepon');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'avatar', 'telepon', 'alamat']);
        });
    }
};
