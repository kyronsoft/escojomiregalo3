<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\CartController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ErrorEmailController;
use App\Http\Controllers\CampaignToyController;
use App\Http\Controllers\ColaboradorController;
use App\Http\Controllers\CustomLoginController;
use App\Http\Controllers\ImportErrorController;
use App\Http\Controllers\CampaignToysController;
use App\Http\Controllers\SeleccionadosController;
use App\Http\Controllers\Api\CampaignApiController;
use App\Http\Controllers\ColaboradorHijoController;
use App\Http\Controllers\CampaignToyImportController;
use App\Http\Controllers\ColaboradorImportController;
use App\Http\Controllers\Api\ColaboradorApiController;
use App\Http\Controllers\CampaignCollaboratorController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('/', function () {
    // Forzar cierre de sesión si ya hay usuario autenticado
    if (auth()->check()) {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
    return redirect()->route('login'); // siempre ir al login
})->name('root');

Route::get('/post-login', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }
    return auth()->user()->hasRole('Colaborador')
        ? redirect()->route('product')
        : redirect()->route('dashboard.index');
})->name('post-login');

Route::middleware(['auth', 'role:Admin'])->group(function () {
    // Index (vista con Tabulator)
    Route::get('/users', [UsersController::class, 'index'])->name('users.index');

    // Data para Tabulator (JSON)
    Route::get('/users/data', [UsersController::class, 'data'])->name('users.data');

    // CRUD clásico
    Route::get('/users/create', [UsersController::class, 'create'])->name('users.create');
    Route::post('/users', [UsersController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UsersController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UsersController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('users.destroy');
});

Route::get('/home', fn() => redirect()->route('post-login'))->name('home');

Route::middleware(['auth', 'role:Admin|Ejecutiva-Empresas|RRHH-Cliente'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
});

Route::prefix('ecommerce')->name('ecommerce.')->group(function () {
    Route::view('product-page',    'admin.apps.ecommerce.product-page')->name('product-page');
    Route::view('list-products',   'admin.apps.ecommerce.list-products')->name('list-products');
    Route::view('payment-details', 'admin.apps.ecommerce.payment-details')->name('payment-details');
    Route::view('order-history',   'admin.apps.ecommerce.order-history')->name('order-history');
    Route::view('invoice-template','admin.apps.ecommerce.invoice-template')->name('invoice-template');

    Route::get('cart',  [CartController::class, 'index'])->name('cart.index');
    Route::post('cart', [CartController::class, 'addcart'])->name('cart.add');
    Route::delete('cart', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('finish', [CartController::class, 'finish'])->name('cart.finish');
    Route::get('finish-review', [CartController::class, 'finishReview'])
        ->name('cart.finish.review')
        ->middleware(['auth', 'role:Colaborador']);
    Route::post('finish-update', [CartController::class, 'finishUpdate'])
        ->name('cart.finish.update')
        ->middleware(['auth', 'role:Colaborador']);

    Route::get('/checkout', [CartController::class, 'checkout'])
        ->name('checkout')
        ->middleware(['auth', 'role:Colaborador']);

    Route::view('list-wish', 'admin.apps.ecommerce.list-wish')->name('list-wish');
    Route::view('pricing',   'admin.apps.ecommerce.pricing')->name('pricing');
});

Route::get('/product', [ProductController::class, 'index'])
    ->name('product')
    ->middleware(['auth', 'role:Colaborador']);

Route::post('/product/aceptar-politica-datos', [ProductController::class, 'aceptarPolitica'])
    ->name('product.aceptarPolitica')
    ->middleware('role:Colaborador');

Route::post('/product/aceptar-welcome', [ProductController::class, 'aceptarWelcome'])
    ->name('product.aceptarWelcome')
    ->middleware('role:Colaborador');

Route::get('/custom-login/{token}', [CustomLoginController::class, 'show'])
    ->name('custom.login');
Route::post('/custom-login/auth', [CustomLoginController::class, 'auth'])->name('custom.login.auth');

Route::get('/empresas/select2', [EmpresaController::class, 'select2'])->name('empresas.select2');

Route::prefix('empresas')->name('empresas.')->group(function () {
    Route::get('/',                 [EmpresaController::class, 'index'])->name('index');
    Route::get('/create',           [EmpresaController::class, 'create'])->name('create');
    Route::post('/',                [EmpresaController::class, 'store'])->name('store');
    Route::get('/{empresa}',        [EmpresaController::class, 'show'])->name('show');
    Route::get('/{empresa}/edit',   [EmpresaController::class, 'edit'])->name('edit');
    Route::put('/{empresa}',        [EmpresaController::class, 'update'])->name('update');
    Route::delete('/{empresa}',     [EmpresaController::class, 'destroy'])->name('destroy');
});

/**
 * Listado (por campaña) manejado por CampaignToysController
 */
Route::get('/campaigns/{campaign}/toys',      [CampaignToysController::class, 'index'])->name('campaigns.toys');
Route::get('/campaigns/{campaign}/toys/data', [CampaignToysController::class, 'data'])->name('campaigns.toys.data');

/**
 * CRUD de campañas
 */
Route::prefix('campaigns')->name('campaigns.')->group(function () {
    Route::get('/data',             [CampaignController::class, 'data'])->name('data');

    Route::get('/',                 [CampaignController::class, 'index'])->name('index');
    Route::get('/create',           [CampaignController::class, 'create'])->name('create');
    Route::post('/',                [CampaignController::class, 'store'])->name('store');

    Route::get('/{campaign}',       [CampaignController::class, 'show'])->whereNumber('campaign')->name('show');
    Route::get('/{campaign}/edit',  [CampaignController::class, 'edit'])->whereNumber('campaign')->name('edit');
    Route::put('/{campaign}',       [CampaignController::class, 'update'])->whereNumber('campaign')->name('update');
    Route::delete('/{campaign}',    [CampaignController::class, 'destroy'])->whereNumber('campaign')->name('destroy');
});

/**
 * Rutas ANIDADAS para toys por campaña (CampaignToyController)
 * 👉 Aquí agregamos la ruta SHOW que te faltaba.
 */
Route::prefix('campaigns/{campaign}')->group(function () {
    // Mostrar detalle (nuevo)
    Route::get('toys/{toy}', [CampaignToyController::class, 'show'])
        ->name('campaigns.toys.show');

    // Editar / actualizar
    Route::get('toys/{toy}/edit', [CampaignToyController::class, 'edit'])
        ->name('campaigns.toys.edit');

    Route::put('toys/{toy}', [CampaignToyController::class, 'update'])
        ->name('campaigns.toys.update');

    // Descargar imagen desde SharePoint
    Route::post('toys/{toy}/image/fetch', [CampaignToyController::class, 'fetchImageFromSharePoint'])
        ->name('campaigns.toys.image.fetch');

    // Eliminar
    Route::delete('toys/{toy}', [CampaignToyController::class, 'destroy'])
        ->name('campaigns.toys.destroy');
});

/**
 * Colaboradores por campaña (Admin)
 */
Route::middleware(['auth', 'role:Admin'])->group(function () {
    // Listado y datos
    Route::get('/campaigns/{campaign}/collaborators',      [CampaignCollaboratorController::class, 'index'])->name('campaigns.collaborators');
    Route::get('/campaigns/{campaign}/collaborators/data', [CampaignCollaboratorController::class, 'data'])->name('campaigns.collaborators.data');

    // Acciones
    Route::post('/campaigns/{campaign}/collaborators/email-all', [CampaignCollaboratorController::class, 'emailAll'])->name('campaigns.collaborators.emailAll');
    Route::post('/campaigns/{campaign}/collaborators/email-one', [CampaignCollaboratorController::class, 'emailOne'])->name('campaigns.collaborators.emailOne');
    Route::post('/campaigns/{campaign}/collaborators/update',    [CampaignCollaboratorController::class, 'updateOne'])->name('campaigns.collaborators.updateOne');

    // Activar/desactivar notificación por colaborador
    Route::post('/campaigns/{campaign}/collaborators/toggle-notify', [CampaignCollaboratorController::class, 'toggleNotify'])->name('campaigns.collaborators.toggleNotify');

    // Eliminar por documento
    Route::delete('/campaigns/{campaign}/collaborators/{documento}', [CampaignCollaboratorController::class, 'destroy'])
        ->where('documento', '[A-Za-z0-9\-\.]+')
        ->name('campaigns.collaborators.destroy');
});

/**
 * Colaboradores (CRUD)
 */
Route::prefix('colaboradores')->name('colaboradores.')->group(function () {
    Route::get('/data',                   [ColaboradorController::class, 'data'])->name('data');
    Route::get('/import',                 [ColaboradorImportController::class, 'showForm'])->name('import');
    Route::post('/import',                [ColaboradorImportController::class, 'import'])->name('import.run');

    Route::get('/',                       [ColaboradorController::class, 'index'])->name('index');
    Route::get('/create',                 [ColaboradorController::class, 'create'])->name('create');
    Route::post('/',                      [ColaboradorController::class, 'store'])->name('store');

    Route::get('/{colaborador}',          [ColaboradorController::class, 'show'])->name('show');
    Route::get('/{colaborador}/edit',     [ColaboradorController::class, 'edit'])->name('edit');
    Route::put('/{colaborador}',          [ColaboradorController::class, 'update'])->name('update');
    Route::delete('/{colaborador}',       [ColaboradorController::class, 'destroy'])->name('destroy');
});

/**
 * Hijos de colaborador (CRUD)
 */
Route::prefix('colaborador-hijos')->name('colaborador_hijos.')->group(function () {
    Route::get('data',                        [ColaboradorHijoController::class, 'data'])->name('data');

    Route::get('/',                           [ColaboradorHijoController::class, 'index'])->name('index');
    Route::get('/create',                     [ColaboradorHijoController::class, 'create'])->name('create');
    Route::post('/',                          [ColaboradorHijoController::class, 'store'])->name('store');

    Route::get('/{colaborador_hijo}',         [ColaboradorHijoController::class, 'show'])
        ->whereNumber('colaborador_hijo')->name('show');

    Route::get('/{colaborador_hijo}/edit',    [ColaboradorHijoController::class, 'edit'])
        ->whereNumber('colaborador_hijo')->name('edit');

    Route::put('/{colaborador_hijo}',         [ColaboradorHijoController::class, 'update'])
        ->whereNumber('colaborador_hijo')->name('update');

    Route::delete('/{colaborador_hijo}',      [ColaboradorHijoController::class, 'destroy'])
        ->whereNumber('colaborador_hijo')->name('destroy');
});

/**
 * Seleccionados (reportes)
 */
Route::get('/seleccionados',        [SeleccionadosController::class, 'index'])->name('seleccionados.index');
Route::get('/seleccionados/data',   [SeleccionadosController::class, 'data'])->name('seleccionados.data');
Route::get('/seleccionados/export', [SeleccionadosController::class, 'export'])->name('seleccionados.export');

/**
 * Campaign Toys (área general + importaciones)
 * - Dejamos SOLO las rutas que no chocan con las anidadas.
 * - Se mantiene campaign_toys.data (usada por Tabulator).
 */
Route::prefix('campaign-toys')->name('campaign_toys.')->group(function () {
    // Data para Tabulator (JSON) — usada por la vista index con tabla
    Route::get('data', [CampaignToyController::class, 'data'])->name('data');

    // Importaciones
    Route::get('/import',           [CampaignToyImportController::class, 'showForm'])->name('import');
    Route::post('/import',          [CampaignToyImportController::class, 'import'])->name('import.run');
    Route::post('/import-async',    [CampaignToyImportController::class, 'importAsync'])->name('import.async');
    Route::get('/progress/{jobId}', [CampaignToyImportController::class, 'progress'])->name('import.progress');

    // Vista general (si la usas): index/create/store del catálogo global
    Route::get('/',                 [CampaignToyController::class, 'index'])->name('index');
    Route::get('/create',           [CampaignToyController::class, 'create'])->name('create');
    Route::post('/',                [CampaignToyController::class, 'store'])->name('store');

    // ❌ OJO: Se retiraron las rutas NO anidadas de show/edit/update/destroy para evitar conflicto
    // Route::get('/{campaign_toy}', ...
    // Route::get('/{campaign_toy}/edit', ...
    // Route::put('/{campaign_toy}', ...
    // Route::delete('/{campaign_toy}', ...
});

/**
 * API públicas
 */
Route::prefix('api')->name('api.')->group(function () {
    Route::get('/colaboradores', [ColaboradorApiController::class, 'index'])->name('colaboradores');
    Route::get('/campaigns',     [CampaignApiController::class, 'index'])->name('campaigns');
});

/**
 * Utilidades varias
 */
Route::post('/campaigns/generate-url', function (Request $request) {
    $request->validate(['nit' => 'required|string|max:20']);
    $encrypted = Crypt::encryptString($request->nit);
    $url = url('custom-login/' . $encrypted);
    return response()->json(['url' => $url]);
})->name('campaigns.generateCustomUrl');

Route::get('/admin/importerrors', [ImportErrorController::class, 'index'])->name('importerrors.index');

Route::middleware(['auth'])->group(function () {
    Route::get('/erroremails', [ErrorEmailController::class, 'index'])->name('erroremails.index');
});

Route::get('/debug/roles', function () {
    $u = auth()->user();
    return response()->json([
        'user_id'     => $u?->id,
        'guard'       => auth()->getDefaultDriver(),  // debería ser "web"
        'role_names'  => $u?->getRoleNames(),         // ¿incluye "Admin" exactamente?
        'has_Admin'   => $u?->hasRole('Admin'),
    ]);
})->middleware('auth');
