<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::prefix('v1')->group(function () {

    Route::middleware(['basicAuth'])->group(function () {

        // wipe user controller
        Route::prefix('subscriptioncontroller')->group(function () {
            Route::controller(\App\Http\Controllers\v1\SubscriptionController::class)->group(function () {
                Route::post('/add', 'add');
                Route::get('/find/{id}', 'find');
                Route::get('/user/subscriptions', 'findusersubscriptions');
                Route::get('/reseller/{reseller_user_id}', 'reseller');
                Route::patch('/patch', 'patch');
            });
        });

    });

    Route::prefix('subscriptioncontroller')->group(function () {
        Route::controller(\App\Http\Controllers\v1\SubscriptionController::class)->group(function () {
            Route::get('scheduler', 'scheduler');
        });
    });

});
