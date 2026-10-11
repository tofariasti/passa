<?php

declare(strict_types=1);

use App\Livewire\Login;
use App\Livewire\MyCard;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::get('/login', Login::class)->name('login');

Route::get('/my-card', MyCard::class)->middleware('auth')->name('my-card');
