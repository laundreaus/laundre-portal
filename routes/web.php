<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\Api\{UserController, LocationController, SettingController, TicketController,
    CleaningController, MaintenanceController, DocumentController, GuideController, BookkeepingController,
    SupplierController, SaleController, FranchiseController, UploadController, MaintenanceDocController};
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('portal');

    // ---- Shared read + scoped-write APIs (any authenticated user; controllers enforce store scoping) ----
    Route::get('/locations-api', [LocationController::class, 'index']);
    Route::get('/settings-api/{key}', [SettingController::class, 'show'])->where('key', '[A-Za-z0-9_\-]+');
    Route::put('/settings-api/{key}', [SettingController::class, 'put'])->where('key', '[A-Za-z0-9_\-]+');

    Route::get('/tickets-api', [TicketController::class, 'index']);
    Route::post('/tickets-api', [TicketController::class, 'store']);
    Route::post('/tickets-api/{ticket}/reply', [TicketController::class, 'reply']);
    Route::patch('/tickets-api/{ticket}/status', [TicketController::class, 'status']);

    Route::get('/cleaning-api', [CleaningController::class, 'index']);
    Route::post('/cleaning-api', [CleaningController::class, 'submit']);
    Route::get('/cleaning-items-api', [CleaningController::class, 'items']);

    Route::get('/maintenance-api', [MaintenanceController::class, 'index']);
    Route::post('/maintenance-api', [MaintenanceController::class, 'submit']);

    Route::get('/documents-api', [DocumentController::class, 'index']);
    Route::get('/guides-api', [GuideController::class, 'index']);
    Route::get('/maintenance-docs-api', [MaintenanceDocController::class, 'index']);
    Route::get('/bookkeeping-api', [BookkeepingController::class, 'index']);
    Route::post('/bookkeeping-api', [BookkeepingController::class, 'upsert']);
    Route::get('/suppliers-api', [SupplierController::class, 'index']);
    Route::get('/sales-api', [SaleController::class, 'index']);
    Route::get('/franchises-api', [FranchiseController::class, 'index']);
    Route::match(['put','patch'], '/franchises-api/{franchise}', [FranchiseController::class, 'update']);

    // ---- Admin-only writes ----
    Route::middleware('role:admin')->group(function () {
        Route::get('/users-api', [UserController::class, 'index']);
        Route::post('/users-api', [UserController::class, 'store']);
        Route::match(['put','patch'], '/users-api/{user}', [UserController::class, 'update']);
        Route::delete('/users-api/{user}', [UserController::class, 'destroy']);

        Route::post('/documents-api', [DocumentController::class, 'store']);
        Route::match(['put','patch'], '/documents-api/{document}', [DocumentController::class, 'update']);
        Route::delete('/documents-api/{document}', [DocumentController::class, 'destroy']);

        Route::post('/guides-api', [GuideController::class, 'store']);
        Route::match(['put','patch'], '/guides-api/{guide}', [GuideController::class, 'update']);
        Route::delete('/guides-api/{guide}', [GuideController::class, 'destroy']);

        Route::post('/maintenance-docs-api', [MaintenanceDocController::class, 'store']);
        Route::delete('/maintenance-docs-api/{maintenance_doc}', [MaintenanceDocController::class, 'destroy']);

        Route::post('/suppliers-api', [SupplierController::class, 'store']);
        Route::match(['put','patch'], '/suppliers-api/{supplier}', [SupplierController::class, 'update']);
        Route::delete('/suppliers-api/{supplier}', [SupplierController::class, 'destroy']);

        Route::post('/sales-api/import', [SaleController::class, 'import']);
        Route::delete('/sales-api/month', [SaleController::class, 'destroyMonth']);

        Route::post('/cleaning-items-api', [CleaningController::class, 'saveItems']);

        Route::post('/franchises-api', [FranchiseController::class, 'store']);
        Route::delete('/franchises-api/{franchise}', [FranchiseController::class, 'destroy']);

        Route::post('/upload-api', [UploadController::class, 'store']);
    });

    Route::get('/legacy/{file}', fn (string $file) => redirect('/'.preg_replace('/\.html$/', '', $file)))->where('file', '.*');
    Route::get('/{page}.html', fn (string $page) => redirect('/'.$page))->where('page', '[A-Za-z0-9\-]+');
    Route::get('/{page}', [PortalController::class, 'tool'])->where('page', '[A-Za-z0-9\-]+')->name('tool');
});
