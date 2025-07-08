<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});



use App\Http\Controllers\CricketController;

Route::get('/cricket/live', [CricketController::class, 'fetchLiveMatches']);
Route::get('/cricket/matches', [CricketController::class, 'index']);




use App\Jobs\FetchCricketMatchesJob;

Route::get('/test-cricket-api', function () {
    FetchCricketMatchesJob::dispatch();

    return response()->json(['message' => 'FetchCricketMatchesJob dispatched']);
});


Route::get('/live-matches', function () {
    return view('live-matches');
});

// routes/api.php
use App\Http\Controllers\CricketMatchesController;

Route::get('/cricket-matches', [CricketMatchesController::class, 'index']);

require __DIR__.'/auth.php';
