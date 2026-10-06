<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EsgController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EsgController::class, 'index'])->name('esg.index');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('esg.dashboard');
Route::post('/files', [EsgController::class, 'store'])->name('esg.store');
Route::get('/files/status', [EsgController::class, 'status'])->name('esg.status');
Route::get('/files/{file}/summary', [EsgController::class, 'summary'])->name('esg.summary');
Route::get('/files/{file}/download', [EsgController::class, 'download'])->name('esg.download');
Route::post('/files/{file}/resume', [EsgController::class, 'resume'])->name('esg.resume');
Route::delete('/files/{file}', [EsgController::class, 'destroy'])->name('esg.destroy');
