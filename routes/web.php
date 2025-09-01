<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CartController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignToyController;
use App\Http\Controllers\ColaboradorController;
use App\Http\Controllers\CustomLoginController;
use App\Http\Controllers\ImportErrorController;
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

@include_once('admin_web.php');

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
    return auth()->user()->hasRole('colaborador')
        ? redirect()->route('product')
        : redirect()->route('dashboard');
})->name('post-login');

Route::get('/home', fn() => redirect()->route('post-login'))->name('home');

Route::view('dashboard', 'admin.dashboard.default')->name('dashboard')->middleware(['auth', 'role:admin']);

Route::get('/product', [ProductController::class, 'index'])
    ->name('product')
    ->middleware(['auth', 'role:colaborador']);

Route::post('/product/aceptar-politica-datos', [ProductController::class, 'aceptarPolitica'])
    ->name('product.aceptarPolitica')
    ->middleware('role:colaborador');

Route::get('/custom-login/{token}', [CustomLoginController::class, 'show'])
    ->name('custom.login');
Route::post('/custom-login/auth', [CustomLoginController::class, 'auth'])->name('custom.login.auth');

Route::get('/empresas/select2', [EmpresaController::class, 'select2'])->name('empresas.select2');

Route::prefix('empresas')->name('empresas.')->group(function () {
    Route::get('/',           [EmpresaController::class, 'index'])->name('index');
    Route::get('/create',     [EmpresaController::class, 'create'])->name('create');
    Route::post('/',          [EmpresaController::class, 'store'])->name('store');
    Route::get('/{empresa}',  [EmpresaController::class, 'show'])->name('show');
    Route::get('/{empresa}/edit', [EmpresaController::class, 'edit'])->name('edit');
    Route::put('/{empresa}',  [EmpresaController::class, 'update'])->name('update');
    Route::delete('/{empresa}', [EmpresaController::class, 'destroy'])->name('destroy');
});

Route::prefix('campaigns')->name('campaigns.')->group(function () {
    Route::get('/data', [CampaignController::class, 'data'])->name('data'); // ANTES

    Route::get('/',                [CampaignController::class, 'index'])->name('index');
    Route::get('/create',          [CampaignController::class, 'create'])->name('create');
    Route::post('/',               [CampaignController::class, 'store'])->name('store');

    Route::get('/{campaign}',      [CampaignController::class, 'show'])->whereNumber('campaign')->name('show');
    Route::get('/{campaign}/edit', [CampaignController::class, 'edit'])->whereNumber('campaign')->name('edit');
    Route::put('/{campaign}',      [CampaignController::class, 'update'])->whereNumber('campaign')->name('update');
    Route::delete('/{campaign}',   [CampaignController::class, 'destroy'])->whereNumber('campaign')->name('destroy');
});
Route::middleware(['auth', 'role:admin|colaborador'])->group(function () {
    Route::get('/campaigns/{campaign}/collaborators', [CampaignCollaboratorController::class, 'index'])
        ->name('campaigns.collaborators');

    Route::get('/campaigns/{campaign}/collaborators/data', [CampaignCollaboratorController::class, 'data'])
        ->name('campaigns.collaborators.data');
});
Route::prefix('colaboradores')->name('colaboradores.')->group(function () {
    Route::get('/data', [ColaboradorController::class, 'data'])->name('data');
    Route::get('/import',  [ColaboradorImportController::class, 'showForm'])->name('import');
    Route::post('/import', [ColaboradorImportController::class, 'import'])->name('import.run');
    Route::get('/',                   [ColaboradorController::class, 'index'])->name('index');
    Route::get('/create',             [ColaboradorController::class, 'create'])->name('create');
    Route::post('/',                  [ColaboradorController::class, 'store'])->name('store');
    Route::get('/{colaborador}',      [ColaboradorController::class, 'show'])->name('show');
    Route::get('/{colaborador}/edit', [ColaboradorController::class, 'edit'])->name('edit');
    Route::put('/{colaborador}',      [ColaboradorController::class, 'update'])->name('update');
    Route::delete('/{colaborador}',   [ColaboradorController::class, 'destroy'])->name('destroy');
});

Route::prefix('colaborador-hijos')->name('colaborador_hijos.')->group(function () {
    Route::get('data', [ColaboradorHijoController::class, 'data'])->name('data');
    Route::get('/',                         [ColaboradorHijoController::class, 'index'])->name('index');
    Route::get('/create',                   [ColaboradorHijoController::class, 'create'])->name('create');
    Route::post('/',                        [ColaboradorHijoController::class, 'store'])->name('store');

    Route::get('/{colaborador_hijo}',       [ColaboradorHijoController::class, 'show'])
        ->whereNumber('colaborador_hijo')->name('show');

    Route::get('/{colaborador_hijo}/edit',  [ColaboradorHijoController::class, 'edit'])
        ->whereNumber('colaborador_hijo')->name('edit');

    Route::put('/{colaborador_hijo}',       [ColaboradorHijoController::class, 'update'])
        ->whereNumber('colaborador_hijo')->name('update');

    Route::delete('/{colaborador_hijo}',    [ColaboradorHijoController::class, 'destroy'])
        ->whereNumber('colaborador_hijo')->name('destroy');
});

Route::post('/campaigns/{campaign}/collaborators/email-all', [CampaignCollaboratorController::class, 'emailAll'])->name('campaigns.collaborators.emailAll');

Route::post('/campaigns/{campaign}/collaborators/email-one', [CampaignCollaboratorController::class, 'emailOne'])->name('campaigns.collaborators.emailOne');

Route::prefix('campaign-toys')->name('campaign_toys.')->group(function () {
    Route::get('data', [CampaignToyController::class, 'data'])->name('data');
    Route::get('/import',  [CampaignToyImportController::class, 'showForm'])->name('import');
    Route::post('/import', [CampaignToyImportController::class, 'import'])->name('import.run');
    // Nuevo: importación asíncrona y progreso
    Route::post('/import-async', [CampaignToyImportController::class, 'importAsync'])->name('import.async');
    Route::get('/progress/{jobId}', [CampaignToyImportController::class, 'progress'])->name('import.progress');
    Route::get('/',                   [CampaignToyController::class, 'index'])->name('index');
    Route::get('/create',             [CampaignToyController::class, 'create'])->name('create');
    Route::post('/',                  [CampaignToyController::class, 'store'])->name('store');
    Route::get('/{campaign_toy}',     [CampaignToyController::class, 'show'])->name('show');
    Route::get('/{campaign_toy}/edit', [CampaignToyController::class, 'edit'])->name('edit');
    Route::put('/{campaign_toy}',     [CampaignToyController::class, 'update'])->name('update');
    Route::delete('/{campaign_toy}',  [CampaignToyController::class, 'destroy'])->name('destroy');
});

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/colaboradores', [ColaboradorApiController::class, 'index'])->name('colaboradores');
    Route::get('/campaigns',     [CampaignApiController::class, 'index'])->name('campaigns');
});

Route::post('/campaigns/generate-url', function (Request $request) {
    $request->validate(['nit' => 'required|string|max:20']);
    $encrypted = Crypt::encryptString($request->nit);
    $url = url('custom-login/' . $encrypted);
    return response()->json(['url' => $url]);
})->name('campaigns.generateCustomUrl');

Route::get('/admin/importerrors', [ImportErrorController::class, 'index'])->name('importerrors.index');
