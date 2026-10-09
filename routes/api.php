<?php

declare(strict_types=1);

use App\Http\Controllers\Network\AuthorizationController;
use Illuminate\Support\Facades\Route;

Route::post('/network/authorizations', [AuthorizationController::class, 'store'])
    ->middleware('network.signature');
