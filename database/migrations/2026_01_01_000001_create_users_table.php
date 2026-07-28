<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nip')->unique();           // dipakai sbg username login
            $table->string('nama');
            $table->string('password');                 // otomatis di-hash
            $table->enum('role', ['SUPER_ADMIN', 'ANGGOTA', 'BENDAHARA', 'KETUA'])->default('ANGGOTA');
            $table->string('id_anggota')->nullable()->unique(); // contoh: ANG-2024-001
            $table->date('tanggal_bergabung')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
