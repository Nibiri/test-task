<?php

use App\Http\Controllers\DealController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/deals', [DealController::class, 'index']);
Route::post('/deals', [DealController::class, 'store']);
Route::get('/deals/{deal}', [DealController::class, 'show']);
Route::patch('/deals/{deal}', [DealController::class, 'update']);
Route::post('/deals/{deal}/refresh', [DealController::class, 'refresh']);
Route::post('/deals/{deal}/export', [DealController::class, 'retryExport']);
Route::post('/deals/{deal}/resolutions', [DealController::class, 'resolve']);

Route::post('/webhooks/deals', [WebhookController::class, 'store']);
