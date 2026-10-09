<?php

declare(strict_types=1);

use App\Http\Controllers\Network\AuthorizationController;
use App\Http\Controllers\Network\EventController;
use Illuminate\Support\Facades\Route;

Route::post('/network/authorizations', [AuthorizationController::class, 'store'])
    ->middleware('network.signature');

Route::post('/network/events', [EventController::class, 'store'])
    ->middleware('network.signature');
