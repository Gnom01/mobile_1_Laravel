<?php

use Illuminate\Support\Facades\Route;



use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CrmUserController;

Route::get('/clients', [ClientController::class, 'index']);
Route::put('/clients/{id}', [ClientController::class, 'update']);

Route::get('/users', [CrmUserController::class, 'index']);
Route::put('/users/{id}', [CrmUserController::class, 'update']);
