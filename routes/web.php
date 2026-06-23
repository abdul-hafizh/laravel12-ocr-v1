<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MasterCabangController;
use App\Http\Controllers\MasterVendorController;
use App\Http\Controllers\MasterMesinController;
use App\Http\Controllers\MasterTokenListrikController;
use App\Http\Controllers\MasterKendaraanController;
use App\Http\Controllers\MasterSkpdController;
use App\Http\Controllers\MasterHargaBiayaController;
use App\Http\Controllers\MasterDayaListrikController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\DashboardScanController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\EmployeeMeasurementController;
use App\Models\MasterCabang;
use App\Models\MasterMesin;
use App\Models\User;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Ocr/Dashboard', [
            'summary' => [
                'total_cabang' => MasterCabang::where('is_active', true)->count(),

                'total_mesin' => MasterMesin::where('is_active', true)->count(),

                'total_user' => User::where('is_active', true)
                    ->where('is_delete', false)
                    ->count(),
            ],
        ]);
    })->name('dashboard');

    Route::get('/dashboard-scans', [DashboardScanController::class, 'index'])
        ->name('dashboard.scans');

    Route::post('/users-management/sync', [UserManagementController::class, 'sync'])
        ->name('users-management.sync');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    Route::post('/summary/scan-notes', [SummaryController::class, 'storeScanNote'])
        ->name('summary.scan-notes.store');

    Route::delete('/summary/scan-notes/{id}', [SummaryController::class, 'deleteScanNote'])
        ->name('summary.scan-notes.delete');

    Route::get('/document', function () {
        return Inertia::render('Ocr/Document');
    })->name('document');
    
    Route::delete('/image-scans/{id}', [SummaryController::class, 'destroy']);

    Route::get('/history', function () {
        return Inertia::render('Ocr/History');
    })->name('history');
});

Route::middleware(['auth', 'verified', 'role.url'])->group(function () {
    Route::resource('roles', RoleController::class)
        ->except(['create', 'show', 'edit']);

    Route::get('/users-management', [UserManagementController::class, 'index'])
        ->name('users-management.index');

    Route::post('/users-management', [UserManagementController::class, 'store'])
        ->name('users-management.store');

    Route::put('/users-management/{user}', [UserManagementController::class, 'update'])
        ->name('users-management.update');

    Route::delete('/users-management/{user}', [UserManagementController::class, 'destroy'])
        ->name('users-management.destroy');

    Route::resource('master-cabang', MasterCabangController::class);

    Route::resource('master-vendor', MasterVendorController::class)
        ->except(['create', 'edit', 'show']);

    Route::resource('master-mesin', MasterMesinController::class)
        ->except(['create', 'edit', 'show']);

    Route::resource('master-token-listrik', MasterTokenListrikController::class)
        ->except(['create', 'edit', 'show']);

    Route::resource('master-kendaraan', MasterKendaraanController::class)
        ->except(['create', 'edit', 'show']);

    Route::resource('master-skpd', MasterSkpdController::class)
        ->except(['create', 'edit', 'show']);

    Route::resource('master-harga-biaya', MasterHargaBiayaController::class)
        ->except(['create', 'edit', 'show']);

    Route::resource('master-daya-listrik', MasterDayaListrikController::class)
        ->except(['create', 'edit', 'show']);

    Route::get('/hasil-upload/mesin-cetak', function () {
        return Inertia::render('Ocr/HasilUpload/MesinCetak');
    })->name('hasil-upload.mesin-cetak');

    Route::get('/hasil-upload/token-listrik', function () {
        return Inertia::render('Ocr/HasilUpload/TokenListrik');
    })->name('hasil-upload.token-listrik');

    Route::get('/hasil-upload/struk-online', function () {
        return Inertia::render('Ocr/HasilUpload/StrukOnline');
    })->name('hasil-upload.struk-online');

    Route::get('/hasil-upload/part-maintenance', function () {
        return Inertia::render('Ocr/HasilUpload/PartMaintenance');
    })->name('hasil-upload.part-maintenance');

    Route::get('/hasil-upload/cea', function () {
        return Inertia::render('Ocr/HasilUpload/Cea');
    })->name('hasil-upload.cea');
    
    Route::get('/hasil-upload/asaba', function () {
        return Inertia::render('Ocr/HasilUpload/Asaba');
    })->name('hasil-upload.asaba');

    Route::get('/summary/electricity', [SummaryController::class, 'electricity'])
        ->name('summary.electricity');

    Route::post('/summary/electricity/send-wa', [SummaryController::class, 'sendElectricityWa'])
        ->name('summary.electricity.send-wa');

    Route::get('/summary/printer-billing', [SummaryController::class, 'printerBilling'])
        ->name('summary.printer-billing');

    Route::post('/summary/printer-billing/send-wa', [SummaryController::class, 'sendPrinterBillingWa'])
        ->name('summary.printer-billing.send-wa');

    Route::get('/summary/asaba', [SummaryController::class, 'asabaBilling'])
        ->name('summary.asaba');

    Route::get('/summary/cea-milik', [SummaryController::class, 'ceaBilling'])
        ->name('summary.cea-milik');

    Route::get('/summary/cea-sewa', [SummaryController::class, 'ceaSewaBilling'])
        ->name('summary.cea-sewa');

    Route::get('/employee-measurements', [EmployeeMeasurementController::class, 'index'])
        ->name('employee-measurements.index');

    Route::delete('/employee-measurements/{employeeMeasurement}', [EmployeeMeasurementController::class, 'destroy'])
        ->name('employee-measurements.destroy');
});

require __DIR__.'/auth.php';