<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('portal');
    Route::get('/legacy/{file}', fn (string $file) => redirect('/'.preg_replace('/\.html$/', '', $file)))->where('file', '.*');
    Route::get('/{page}.html', fn (string $page) => redirect('/'.$page))->where('page', '[A-Za-z0-9\-]+');
    Route::get('/{page}', [PortalController::class, 'tool'])->where('page', '[A-Za-z0-9\-]+')->name('tool');
});
