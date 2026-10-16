<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LayananController;

Route::apiResource('layanan', LayananController::class);

use App\Http\Controllers\Api\PengajuanController;

Route::get('/pengajuan', [PengajuanController::class, 'index']);
Route::post('/pengajuan', [PengajuanController::class, 'store']);
Route::get('/pengajuan/{id}', [PengajuanController::class, 'show']);
Route::patch('/pengajuan/{id}/status', [PengajuanController::class, 'updateStatus']);
