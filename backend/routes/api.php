<?php

use App\Http\Controllers\Api\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\Auth\CurrentUserController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\JobTitleController;
use Illuminate\Support\Facades\Route;

// Feature flow step 1: API login must always return JSON, never Laravel's guest redirect to /home.
Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', CurrentUserController::class);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/company', [CompanyController::class, 'current']);
    Route::put('/company', [CompanyController::class, 'update']);

    Route::apiResource('branches', BranchController::class)->except(['show']);
    Route::apiResource('departments', DepartmentController::class)->except(['show']);
    Route::apiResource('job-titles', JobTitleController::class)->except(['show']);
    Route::apiResource('employees', EmployeeController::class);
});
