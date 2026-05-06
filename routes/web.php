<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\MasterCabangController;
use App\Http\Controllers\MasterVendorController;
use App\Http\Controllers\MasterMesinController;
use App\Http\Controllers\MasterTokenListrikController;
use App\Http\Controllers\MasterKendaraanController;
use App\Http\Controllers\MasterSkpdController;
use App\Http\Controllers\MasterHargaBiayaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\ImageScanController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Ocr/Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/document', function () {
    return Inertia::render('Ocr/Document'); 
})->middleware(['auth', 'verified'])->name('document');

Route::get('/history', function () {
    return Inertia::render('Ocr/History'); 
})->middleware(['auth', 'verified'])->name('history');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::resource('master-cabang', MasterCabangController::class);    
    Route::resource('master-vendor', MasterVendorController::class)->except(['create', 'edit', 'show']);
    Route::resource('master-mesin', MasterMesinController::class)->except(['create', 'edit', 'show']);
    Route::resource('master-token-listrik', MasterTokenListrikController::class)->except(['create', 'edit', 'show']);
    Route::resource('master-kendaraan', MasterKendaraanController::class)->except(['create', 'edit', 'show']);
    Route::resource('master-skpd', MasterSkpdController::class)->except(['create', 'edit', 'show']);
    Route::resource('master-harga-biaya', MasterHargaBiayaController::class)->except(['create', 'edit', 'show']);
    
    Route::get('/hasil-upload/mesin-cetak', function () {
        return Inertia::render('Ocr/HasilUpload/MesinCetak');
    })->name('hasil-upload.mesin-cetak');

    Route::get('/hasil-upload/token-listrik', function () {
        return Inertia::render('Ocr/HasilUpload/TokenListrik');
    })->name('hasil-upload.token-listrik');

    Route::get('/hasil-upload/struk-online', function () {
        return Inertia::render('Ocr/HasilUpload/StrukOnline');
    })->name('hasil-upload.struk-online');
});

require __DIR__.'/auth.php';
