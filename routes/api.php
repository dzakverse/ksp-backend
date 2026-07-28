<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AnggotaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController; // Single Controller untuk Anggota & Admin
use App\Http\Controllers\Api\PinjamanController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SimpananController;

// ============ PUBLIC ============
Route::post('/login', [AuthController::class, 'login']);

// ============ PROTECTED (butuh token Sanctum) ============
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // ---- ANGGOTA (semua role bisa akses punya sendiri) ----
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/simpanan', [SimpananController::class, 'index']);
    Route::get('/pinjaman', [PinjamanController::class, 'index']);
    Route::post('/pinjaman', [PinjamanController::class, 'store']);
    Route::get('/profile', [ProfileController::class, 'show']);

    // ---- BENDAHARA (KETUA juga boleh lihat) ----
    Route::middleware('role:BENDAHARA,KETUA')->prefix('admin')->group(function () {
        // DIUBAH: Arahkan ke method adminIndex di DashboardController
        Route::get('/dashboard', [DashboardController::class, 'adminIndex']);

        Route::get('/anggota', [AnggotaController::class, 'index']);
        Route::get('/anggota/{anggota}', [AnggotaController::class, 'show']);
        Route::post('/anggota/{anggota}/simpanan', [AnggotaController::class, 'storeSimpanan']);
        Route::patch('/anggota/{anggota}/status', [AnggotaController::class, 'updateStatus']);

        Route::get('/pinjaman', [PinjamanController::class, 'indexAll']);
        Route::get('/pinjaman/{pinjaman}', [PinjamanController::class, 'show']);
        Route::post('/pinjaman/{pinjaman}/verifikasi', [PinjamanController::class, 'verifikasi']);
    });

    // ---- KETUA only ----
    Route::middleware('role:KETUA')->prefix('ketua')->group(function () {
        Route::post('/pinjaman/{pinjaman}/persetujuan', [PinjamanController::class, 'persetujuanKetua']);
    });
});