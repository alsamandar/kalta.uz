<?php

use App\Http\Controllers\BioController;
use App\Http\Controllers\CodeController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KaltaController;
use App\Http\Controllers\ShortController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::get('/', [KaltaController::class, 'index'])->name('index');

Route::get('/test', function(){
    return view('test');
});

Route::post('kaltas/make', [ShortController::class, 'make'])->middleware('throttle:20,1')->name('shorts.make');

Route::resource('bio', BioController::class);

Route::get('{kalta}', [KaltaController::class, 'show'])->name('kaltas.show');
Route::resource('kaltas', KaltaController::class)->except('show');
Route::resource('files', FileController::class)->middleware('throttle:20,1');
Route::resource('codes', CodeController::class)->middleware('throttle:20,1');


require_once __DIR__.'/auth.php';
