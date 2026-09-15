<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login.show');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login.show');
    Route::post('login', [LoginController::class, 'login'])->name('login');
    Route::get('register', [RegisterController::class, 'show'])->name('register.show');
    Route::post('register', [RegisterController::class, 'register'])->name('register');
});

Route::post('logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('matches', [MatchController::class, 'index'])->name('matches.index');
    Route::get('matches/create', [MatchController::class, 'create'])->middleware('organizer')->name('matches.create');
    Route::post('matches', [MatchController::class, 'store'])->middleware('organizer')->name('matches.store');

    Route::get('matches/{match}', [MatchController::class, 'show'])->name('matches.show');
    Route::patch('matches/{match}/lock', [MatchController::class, 'lock'])->middleware('organizer')->name('matches.lock');
    Route::patch('matches/{match}/unlock', [MatchController::class, 'unlock'])->middleware('organizer')->name('matches.unlock');
    Route::patch('matches/{match}/finish', [MatchController::class, 'finish'])->middleware('organizer')->name('matches.finish');
    Route::post('matches/{match}/remind', [MatchController::class, 'remind'])->middleware('organizer')->name('matches.remind');

    Route::patch('matches/{match}/entries', [EntryController::class, 'update'])->name('entries.update');
    Route::delete('matches/{match}/entries', [EntryController::class, 'destroy'])->name('entries.destroy');
    Route::patch('matches/{match}/entries/reorder', [EntryController::class, 'reorder'])->middleware('organizer')->name('entries.reorder');

    Route::post('matches/{match}/guests', [GuestController::class, 'store'])->middleware('organizer')->name('guests.store');
    Route::delete('matches/{match}/guests/{guest}', [GuestController::class, 'destroy'])->middleware('organizer')->name('guests.destroy');

    Route::get('matches/{match}/ratings', [RatingController::class, 'create'])->name('ratings.create');
    Route::post('matches/{match}/ratings', [RatingController::class, 'store'])->name('ratings.store');

    Route::post('matches/{match}/teams/generate', [TeamController::class, 'generate'])->middleware('organizer')->name('teams.generate');
    Route::post('matches/{match}/teams/swap', [TeamController::class, 'swap'])->middleware('organizer')->name('teams.swap');

    Route::post('matches/{match}/result', [ResultController::class, 'store'])->middleware('organizer')->name('result.store');

    Route::get('stats', [StatsController::class, 'index'])->name('stats.index');
    Route::get('stats/{user}', [StatsController::class, 'show'])->name('stats.show');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
});
