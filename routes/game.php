<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\MoveController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GameController::class, 'create'])->name('game.create');
Route::post('/games', [GameController::class, 'store'])->name('game.store');
Route::get('/game/{playerToken}', [GameController::class, 'show'])->name('game.show');
Route::post('/game/{playerToken}/moves', [MoveController::class, 'store'])->name('game.move');
