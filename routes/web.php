<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\Api\{UserController, LocationController, SettingController};
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('portal');

    // Shared read APIs for the portal tools (any authenticated user)
    Route::get('/locations-api', [LocationController::class, 'index']);
    Route::get('/settings-api/{key}', [SettingController::class, 'show'])->where('key', '[A-Za-z0-9_\-]+');
    Route::put('/settings-api/{key}', [SettingController::class, 'put'])->where('key', '[A-Za-z0-9_\-]+'); // controller enforces admin

    // DB-backed user management (admin only) for the portal Users tool
    Route::middleware('role:admin')->group(function () {
        Route::get('/users-api', [UserController::class, 'index']);
        Route::post('/users-api', [UserController::class, 'store']);
        Route::match(['put','patch'], '/users-api/{user}', [UserController::class, 'update']);
        Route::delete('/users-api/{user}', [UserController::class, 'destroy']);
    });

    Route::get('/legacy/{file}', fn (string $file) => redirect('/'.preg_replace('/\.html$/', '', $file)))->where('file', '.*');
    Route::get('/{page}.html', fn (string $page) => redirect('/'.$page))->where('page', '[A-Za-z0-9\-]+');
    Route::get('/{page}', [PortalController::class, 'tool'])->where('page', '[A-Za-z0-9\-]+')->name('tool');
});
