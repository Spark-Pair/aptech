<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function(){ Route::get('login',[AuthController::class,'login'])->name('login'); Route::post('login',[AuthController::class,'loginPost'])->middleware('throttle:5,1')->name('loginPost'); });
Route::middleware('auth')->group(function(){
    Route::get('/',DashboardController::class)->name('dashboard'); Route::redirect('home','/')->name('home');
    Route::resource('employees',EmployeeController::class)->except('destroy');
    Route::get('shifts',[ShiftController::class,'index'])->name('shifts.index'); Route::post('shifts',[ShiftController::class,'store'])->name('shifts.store'); Route::put('shifts/{shift}',[ShiftController::class,'update'])->name('shifts.update');
    Route::get('attendances',[AttendanceController::class,'index'])->name('attendances.index'); Route::post('fetchLogs',[AttendanceController::class,'fetchLogs'])->middleware('throttle:2,1')->name('attendance.sync'); Route::get('fetchLogs',fn()=>redirect()->route('operations.index'));
    Route::get('operations',[OperationController::class,'index'])->name('operations.index'); Route::post('operations/import',[OperationController::class,'import'])->name('operations.import'); Route::post('operations/generate',[OperationController::class,'generate'])->name('operations.generate'); Route::get('leaves',[OperationController::class,'leaves'])->name('leaves.index'); Route::post('leaves',[OperationController::class,'storeLeave'])->name('leaves.store'); Route::post('logout',[AuthController::class,'logout'])->name('logout');
});
