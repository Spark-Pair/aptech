<?php

use App\Http\Controllers\Api\AttendanceSyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1/attendance-agent')
    ->middleware(['attendance.agent', 'throttle:60,1'])
    ->group(function () {
        Route::post('heartbeat', [AttendanceSyncController::class, 'heartbeat']);
        Route::post('sync', [AttendanceSyncController::class, 'sync']);
    });
