<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\{LocationController, UserController, SaleController, DocumentController,
    GuideController, TicketController, CleaningController, MaintenanceController, SupplierController,
    CostProjectController, SiteScoreController, FranchiseController, BookkeepingController, SettingController};
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    // Scoped reads (controller enforces per-store scoping by role)
    Route::get('/sales', [SaleController::class, 'index']);
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::get('/guides', [GuideController::class, 'index']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply']);
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'status']);
    Route::get('/cleaning', [CleaningController::class, 'index']);
    Route::post('/cleaning', [CleaningController::class, 'submit']);
    Route::get('/cleaning-items', [CleaningController::class, 'items']);
    Route::get('/maintenance', [MaintenanceController::class, 'index']);
    Route::post('/maintenance', [MaintenanceController::class, 'submit']);
    Route::get('/franchises', [FranchiseController::class, 'index']);
    Route::match(['put','patch'], '/franchises/{franchise}', [FranchiseController::class, 'update']);
    Route::get('/bookkeeping', [BookkeepingController::class, 'index']);
    Route::post('/bookkeeping', [BookkeepingController::class, 'upsert']);
    Route::get('/settings/{key}', [SettingController::class, 'show']);
    Route::get('/locations', [LocationController::class, 'index']);

    // Admin-only
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('locations', LocationController::class)->except(['index','show']);
        Route::apiResource('users', UserController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('cost-projects', CostProjectController::class);
        Route::apiResource('site-scores', SiteScoreController::class);
        Route::post('/sales/import', [SaleController::class, 'import']);
        Route::delete('/sales/month', [SaleController::class, 'destroyMonth']);
        Route::post('/documents', [DocumentController::class, 'store']);
        Route::match(['put','patch'], '/documents/{document}', [DocumentController::class, 'update']);
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy']);
        Route::post('/guides', [GuideController::class, 'store']);
        Route::match(['put','patch'], '/guides/{guide}', [GuideController::class, 'update']);
        Route::delete('/guides/{guide}', [GuideController::class, 'destroy']);
        Route::post('/cleaning-items', [CleaningController::class, 'saveItems']);
        Route::post('/franchises', [FranchiseController::class, 'store']);
        Route::delete('/franchises/{franchise}', [FranchiseController::class, 'destroy']);
        Route::put('/settings/{key}', [SettingController::class, 'put']);
    });
});
