<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\Api\{UserController, LocationController, SettingController, TicketController,
    CleaningController, MaintenanceController, DocumentController, GuideController, BookkeepingController,
    SupplierController, SaleController, FranchiseController, UploadController, MaintenanceDocController,
    OnboardingController, TaskController, CrmController, MembershipController, NdaController, InvestorController};
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ---- Public invite / set-password (token) ----
Route::get('/welcome/{token}', [InviteController::class, 'show'])->where('token','[A-Za-z0-9]+');
Route::post('/welcome/{token}', [InviteController::class, 'store'])->where('token','[A-Za-z0-9]+')->middleware('throttle:20,1');

// Serve favicons directly (public assets; no auth required).
Route::get('/favicon.ico', fn () => response()->file(public_path('favicon.ico')));
Route::get('/favicon.gif', fn () => response()->file(public_path('favicon.gif')));
Route::get('/favicon-32.png', fn () => response()->file(public_path('favicon-32.png')));

Route::middleware('auth')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('portal');

    // ---- Admin "view as user" preview ----
    Route::get('/stop-impersonate', [ImpersonateController::class, 'stop']);

    // ---- Shared read + scoped-write APIs (any authenticated user; controllers enforce store scoping) ----
    Route::get('/locations-api', [LocationController::class, 'index']);
    // Postcode dataset served as JSON (large; kept out of the tool HTML to stay under the server response limit)
    Route::get('/postcode-data', [PortalController::class, 'postcodeData']);

    // ---- Investor data dashboard (assigned laundromats) ----
    Route::get('/investor-data-api', [InvestorController::class, 'data']);

    // ---- Digital membership card ----
    Route::get('/membership-card', [MembershipController::class, 'show']);
    Route::get('/membership-card/apple', [MembershipController::class, 'applePass']);
    Route::get('/membership-card/google', [MembershipController::class, 'googleSave']);
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
    Route::get('/documents-doc/{document}', [DocumentController::class, 'open']);
    Route::get('/guides-api', [GuideController::class, 'index']);
    Route::get('/maintenance-docs-api', [MaintenanceDocController::class, 'index']);
    Route::get('/bookkeeping-api', [BookkeepingController::class, 'index']);
    Route::post('/bookkeeping-api', [BookkeepingController::class, 'upsert']);
    Route::get('/suppliers-api', [SupplierController::class, 'index']);
    Route::get('/sales-api', [SaleController::class, 'index']);
    Route::get('/franchises-api', [FranchiseController::class, 'index']);
    Route::match(['put','patch'], '/franchises-api/{franchise}', [FranchiseController::class, 'update']);

    // ---- Onboarding (potential franchisee / investor) ----
    Route::get('/onboarding-api', [OnboardingController::class, 'state']);
    Route::post('/onboarding-api/sign', [OnboardingController::class, 'sign']);
    Route::post('/onboarding-api/track', [OnboardingController::class, 'track']);
    Route::post('/onboarding-api/interest', [OnboardingController::class, 'interest']);

    // Onboarding portal content (current user) + document viewer/tracking
    Route::get('/onboarding-content', [OnboardingController::class, 'content']);
    Route::get('/onboarding-docs', [OnboardingController::class, 'myDocuments']);
    Route::get('/onboarding-doc/{doc}', [OnboardingController::class, 'openDocument']);

    // Admin-or-granted-section reads (controllers self-gate via canSection)
    Route::get('/onboarding-admin-api', [OnboardingController::class, 'adminIndex']);
    Route::get('/investors-api', [OnboardingController::class, 'investors']);

    // Admin content manager: videos + documents + view analytics (self-gated via canSection laundre-onboarding-content)
    Route::get('/onboarding-content-admin', [OnboardingController::class, 'adminContent']);
    Route::put('/onboarding-content-admin/video', [OnboardingController::class, 'setVideos']);
    Route::post('/onboarding-content-admin/doc', [OnboardingController::class, 'storeDoc']);
    Route::delete('/onboarding-content-admin/doc/{doc}', [OnboardingController::class, 'destroyDoc']);
    Route::get('/onboarding-content-admin/doc/{doc}/views', [OnboardingController::class, 'docViews']);

    // ---- Tasks (admin CRUD; assignees see + update status of their own) ----
    Route::get('/tasks-api', [TaskController::class, 'index']);
    Route::patch('/tasks-api/{task}/status', [TaskController::class, 'setStatus']);

    // ---- Admin-only writes ----
    Route::middleware('role:admin')->group(function () {
        Route::post('/impersonate/{user}', [ImpersonateController::class, 'start']);

        // Site Locations (DB-backed): create/edit/delete approved laundromats.
        Route::post('/locations-api', [LocationController::class, 'store']);
        Route::match(['put','patch'], '/locations-api/{location}', [LocationController::class, 'update']);
        Route::delete('/locations-api/{location}', [LocationController::class, 'destroy']);

        Route::get('/users-api', [UserController::class, 'index']);
        Route::post('/users-api', [UserController::class, 'store']);
        Route::match(['put','patch'], '/users-api/{user}', [UserController::class, 'update']);
        Route::post('/users-api/{user}/reinvite', [UserController::class, 'reinvite']);
        Route::delete('/users-api/{user}', [UserController::class, 'destroy']);

        Route::match(['put','patch'], '/onboarding-admin-api/{onboarding}/stage', [OnboardingController::class, 'moveStage']);

        // ---- NDA register: signers list + downloadable executed PDF ----
        Route::get('/nda-admin-api', [NdaController::class, 'list']);
        Route::get('/nda-admin/{user}/pdf', [NdaController::class, 'pdf']);

        Route::post('/tasks-api', [TaskController::class, 'store']);
        Route::match(['put','patch'], '/tasks-api/{task}', [TaskController::class, 'update']);
        Route::delete('/tasks-api/{task}', [TaskController::class, 'destroy']);

        Route::get('/pipeline-api', [CrmController::class, 'index']);
        Route::post('/pipeline-api', [CrmController::class, 'storeCard']);
        Route::match(['put','patch'], '/pipeline-api/{card}', [CrmController::class, 'updateCard']);
        Route::patch('/pipeline-api/{card}/move', [CrmController::class, 'moveCard']);
        Route::delete('/pipeline-api/{card}', [CrmController::class, 'destroyCard']);
        Route::put('/pipeline-columns-api', [CrmController::class, 'saveColumns']);

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
