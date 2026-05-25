<?php

use Inertia\Inertia;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ImageScanController;
use App\Http\Controllers\MasterCabangController;
use App\Http\Controllers\MasterVendorController;
use App\Http\Controllers\MasterMesinController;
use App\Http\Controllers\MasterTokenListrikController;
use App\Http\Controllers\MasterKendaraanController;
use App\Http\Controllers\MasterSkpdController;
use App\Http\Controllers\MasterHargaBiayaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\DashboardScanController;
use App\Http\Controllers\SummaryController;

Route::get('/', function () { return redirect()->route('login'); });

Route::get('/document', function () { return Inertia::render('Ocr/Document'); })->middleware(['auth', 'verified'])->name('document');
Route::get('/history', function () { return Inertia::render('Ocr/History'); })->middleware(['auth', 'verified'])->name('history');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () { return Inertia::render('Ocr/Dashboard'); })->middleware(['auth', 'verified'])->name('dashboard');
    Route::get('/dashboard-scans', [DashboardScanController::class, 'index'])->name('dashboard.scans');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('roles', RoleController::class)->except(['create', 'show', 'edit']);
    Route::get('/users-management', [UserManagementController::class, 'index'])->name('users-management.index');
    Route::post('/users-management', [UserManagementController::class, 'store'])->name('users-management.store');
    Route::put('/users-management/{user}', [UserManagementController::class, 'update'])->name('users-management.update');
    Route::delete('/users-management/{user}', [UserManagementController::class, 'destroy'])->name('users-management.destroy');
    Route::resource('master-cabang', MasterCabangController::class);    
    Route::resource('master-vendor', MasterVendorController::class)->except(['create', 'edit', 'show']);
    Route::resource('master-mesin', MasterMesinController::class)->except(['create', 'edit', 'show']);
    Route::resource('master-token-listrik', MasterTokenListrikController::class)->except(['create', 'edit', 'show']);
    Route::resource('master-kendaraan', MasterKendaraanController::class)->except(['create', 'edit', 'show']);
    Route::resource('master-skpd', MasterSkpdController::class)->except(['create', 'edit', 'show']);
    Route::resource('master-harga-biaya', MasterHargaBiayaController::class)->except(['create', 'edit', 'show']);
    Route::get('/hasil-upload/mesin-cetak', function () {return Inertia::render('Ocr/HasilUpload/MesinCetak');})->name('hasil-upload.mesin-cetak');
    Route::get('/hasil-upload/token-listrik', function () {return Inertia::render('Ocr/HasilUpload/TokenListrik');})->name('hasil-upload.token-listrik');
    Route::get('/hasil-upload/struk-online', function () {return Inertia::render('Ocr/HasilUpload/StrukOnline');})->name('hasil-upload.struk-online');

    Route::get('/summary/electricity', [SummaryController::class, 'electricity'])->name('summary.electricity');
    Route::post('/summary/electricity/send-wa', [SummaryController::class, 'sendElectricityWa'])->middleware(['auth', 'verified'])->name('summary.electricity.send-wa');
});

require __DIR__.'/auth.php';
