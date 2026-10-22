<?php

use App\Http\Controllers\MockDealController;
use Illuminate\Support\Facades\Route;

Route::get('/mock-api/deals/{externalId}', [MockDealController::class, 'show']);
Route::post('/mock-api/deals', [MockDealController::class, 'store']);
