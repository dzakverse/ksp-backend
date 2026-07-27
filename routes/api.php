<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PinjamanController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SimpananController;
use Illuminate\Support\Facades\Route;

// ============ PUBLIC ============
Route::post('/login', [AuthController::class, 'login']);

// ============ PROTECTED (butuh token Sanctum) ============
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ---- ANGGOTA (semua role bisa akses punya sendiri) ----
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/simpanan', [SimpananController::class, 'index']);
    Route::get('/pinjaman', [PinjamanController::class, 'index']);
    Route::post('/pinjaman', [PinjamanController::class, 'store']);
    Route::get('/profile', [ProfileController::class, 'show']);

    // ---- BENDAHARA only ----
    Route::middleware('role:BENDAHARA,KETUA')->prefix('admin')->group(function () {
        Route::get('/pinjaman', [PinjamanController::class, 'indexAll']);
        Route::post('/pinjaman/{pinjaman}/verifikasi', [PinjamanController::class, 'verifikasi']);
    });

    // ---- KETUA only ----
    Route::middleware('role:KETUA')->prefix('ketua')->group(function () {
        Route::post('/pinjaman/{pinjaman}/persetujuan', [PinjamanController::class, 'persetujuanKetua']);
    });
});
