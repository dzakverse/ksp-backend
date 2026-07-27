<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nik')->nullable()->after('nama');
            $table->string('tempat_lahir')->nullable()->after('nik');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->enum('jenis_kelamin', ['Laki-Laki', 'Perempuan'])->nullable()->after('tanggal_lahir');
            $table->text('alamat')->nullable()->after('jenis_kelamin');
            $table->string('whatsapp')->nullable()->after('alamat');
            $table->string('email')->nullable()->after('whatsapp');
            $table->string('foto_url')->nullable()->after('email');
            $table->string('id_keanggotaan')->nullable()->unique()->after('id_anggota');
            $table->enum('status_keanggotaan', ['AKTIF', 'NONAKTIF'])->default('AKTIF')->after('id_keanggotaan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
                'alamat', 'whatsapp', 'email', 'foto_url',
                'id_keanggotaan', 'status_keanggotaan',
            ]);
        });
    }
};
